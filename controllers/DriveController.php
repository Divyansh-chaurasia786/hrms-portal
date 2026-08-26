<?php
// controllers/DriveController.php

class DriveController {

    public static function checkAccess(): void {
        requireAuth();
        $user = authUser();
        if (!in_array($user['role'], ['admin', 'team_lead', 'employee'])) {
            setFlash('error', 'Access restricted to Tech Team.');
            header('Location: ?page=dashboard');
            exit;
        }

        // Check if user is locked due to active HR referral / escalation
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT is_escalated_locked, escalated_lock_reason FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();
        if ($row && !empty($row['is_escalated_locked'])) {
            setFlash('error', '🔒 Cloud Drive Locked: Your account has an active referral/escalation to HR by your Team Lead. Cloud Drive access is restricted.');
            header('Location: ?page=dashboard');
            exit;
        }
    }

    /**
     * Resolves the active Team Lead ID for cloud drive scoping:
     * - Team Lead: Their own ID
     * - Employee / TL Support: Their assigned reporting_tl_id
     * - Admin / Head HR: Selected team_lead_id from GET/POST or first active Team Lead
     */
    public static function getActiveTeamLeadId(): int {
        $user = authUser();
        $db = getDBConnection();

        if ($user['role'] === 'team_lead') {
            return (int)$user['id'];
        }

        if ($user['role'] === 'admin') {
            $requestedTlId = (int)($_GET['team_lead_id'] ?? $_POST['team_lead_id'] ?? 0);
            if ($requestedTlId > 0) {
                return $requestedTlId;
            }
            $firstTl = (int)$db->query("SELECT id FROM users WHERE role = 'team_lead' AND status = 'active' ORDER BY id ASC LIMIT 1")->fetchColumn();
            return $firstTl ?: 30010;
        }

        // For Employee / TL Support
        if (!empty($user['reporting_tl_id'])) {
            return (int)$user['reporting_tl_id'];
        }

        $firstTl = (int)$db->query("SELECT id FROM users WHERE role = 'team_lead' AND status = 'active' ORDER BY id ASC LIMIT 1")->fetchColumn();
        return $firstTl ?: 30010;
    }

    public static function getValidAccessToken(int $teamLeadId = 0): ?string {
        if ($teamLeadId <= 0) {
            $teamLeadId = self::getActiveTeamLeadId();
        }

        $db = getDBConnection();
        $stmt = $db->prepare("SELECT * FROM drive_settings WHERE team_lead_id = ?");
        $stmt->execute([$teamLeadId]);
        $settings = $stmt->fetch();

        if (!$settings || empty($settings['refresh_token'])) {
            return $settings['access_token'] ?? null;
        }

        // If access token is still valid (with 60s buffer)
        $expiresAt = strtotime($settings['access_token_expires_at'] ?? '2000-01-01');
        if (!empty($settings['access_token']) && $expiresAt > (time() + 60)) {
            return $settings['access_token'];
        }

        // Refresh token from Google
        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $postData = [
            'client_id' => $settings['client_id'],
            'client_secret' => $settings['client_secret'],
            'refresh_token' => $settings['refresh_token'],
            'grant_type' => 'refresh_token',
        ];

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        curl_close($ch);

        $tokenData = json_decode($response, true);
        if (!empty($tokenData['access_token'])) {
            $newAccessToken = $tokenData['access_token'];
            $expiresIn = (int)($tokenData['expires_in'] ?? 3600);
            $newExpiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

            $upd = $db->prepare("UPDATE drive_settings SET access_token = ?, access_token_expires_at = ?, updated_at = CURRENT_TIMESTAMP WHERE team_lead_id = ?");
            $upd->execute([$newAccessToken, $newExpiresAt, $teamLeadId]);
            return $newAccessToken;
        }

        return $settings['access_token'] ?? null;
    }

    public static function autoSyncIfNeeded(): void {
        $teamLeadId = self::getActiveTeamLeadId();
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT * FROM drive_settings WHERE team_lead_id = ?");
        $stmt->execute([$teamLeadId]);
        $settings = $stmt->fetch();

        if (!$settings || empty($settings['is_connected']) || empty($settings['refresh_token'])) {
            return;
        }

        $lastSync = !empty($settings['last_synced_at']) ? strtotime($settings['last_synced_at']) : 0;
        // If more than 120 seconds (2 mins) passed since last sync
        if ((time() - $lastSync) >= 120) {
            self::performSilentSync($teamLeadId);
        }
    }

    public static function autoSyncJson(): void {
        header('Content-Type: application/json');
        $teamLeadId = self::getActiveTeamLeadId();
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT * FROM drive_settings WHERE team_lead_id = ?");
        $stmt->execute([$teamLeadId]);
        $settings = $stmt->fetch();

        if (!$settings || empty($settings['is_connected']) || empty($settings['refresh_token'])) {
            echo json_encode(['status' => 'not_connected']);
            exit;
        }

        $lastSync = !empty($settings['last_synced_at']) ? strtotime($settings['last_synced_at']) : 0;
        $updated = false;

        if ((time() - $lastSync) >= 120) {
            $updated = self::performSilentSync($teamLeadId);
        }

        $stmt->execute([$teamLeadId]);
        $settings = $stmt->fetch();
        echo json_encode([
            'status' => 'ok',
            'updated' => $updated,
            'last_synced_at' => $settings['last_synced_at'],
            'used_bytes' => $settings['used_storage_bytes'],
            'total_bytes' => $settings['total_storage_bytes']
        ]);
        exit;
    }

    public static function performSilentSync(int $teamLeadId = 0): bool {
        if ($teamLeadId <= 0) {
            $teamLeadId = self::getActiveTeamLeadId();
        }

        $db = getDBConnection();
        $stmt = $db->prepare("SELECT * FROM drive_settings WHERE team_lead_id = ?");
        $stmt->execute([$teamLeadId]);
        $settings = $stmt->fetch();

        if (!$settings) return false;

        $accessToken = self::getValidAccessToken($teamLeadId);
        if (!$accessToken) return false;

        // 1. Fetch Real Storage Quota
        $aboutUrl = 'https://www.googleapis.com/drive/v3/about?fields=storageQuota,user';
        $ch = curl_init($aboutUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$accessToken}"]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $aboutResp = curl_exec($ch);
        curl_close($ch);

        $aboutData = json_decode($aboutResp, true);
        if (!empty($aboutData['storageQuota'])) {
            $quota = $aboutData['storageQuota'];
            $totalBytes = (int)($quota['limit'] ?? (15 * 1024 * 1024 * 1024));
            $usedBytes = (int)($quota['usage'] ?? 0);
            $userEmail = $aboutData['user']['emailAddress'] ?? $settings['connected_account_email'];

            $upd = $db->prepare("UPDATE drive_settings SET total_storage_bytes = ?, used_storage_bytes = ?, connected_account_email = ?, last_synced_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE team_lead_id = ?");
            $upd->execute([$totalBytes, $usedBytes, $userEmail, $teamLeadId]);
        } else {
            $db->prepare("UPDATE drive_settings SET last_synced_at = CURRENT_TIMESTAMP WHERE team_lead_id = ?")->execute([$teamLeadId]);
        }

        // 2. Fetch Real Files & Folders
        $filesUrl = 'https://www.googleapis.com/drive/v3/files?pageSize=100&fields=files(id,name,mimeType,size,createdTime,thumbnailLink,webViewLink,webContentLink,parents,trashed)&q=trashed=false';
        $ch2 = curl_init($filesUrl);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$accessToken}"]);
        curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch2, CURLOPT_TIMEOUT, 15);
        $filesResp = curl_exec($ch2);
        curl_close($ch2);

        $filesData = json_decode($filesResp, true);
        if (!empty($filesData['files'])) {
            $db->prepare("DELETE FROM drive_items WHERE is_google_synced = 1 AND team_lead_id = ?")->execute([$teamLeadId]);

            foreach ($filesData['files'] as $f) {
                $isFolder = ($f['mimeType'] === 'application/vnd.google-apps.folder');
                $type = $isFolder ? 'folder' : 'file';
                $name = $f['name'];
                $mimeType = $f['mimeType'] ?: 'application/octet-stream';
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $size = (int)($f['size'] ?? 0);
                $webView = $f['webViewLink'] ?? '#';
                $webDownload = $f['webContentLink'] ?? $webView;
                $thumb = $f['thumbnailLink'] ?? '';

                $ins = $db->prepare("
                    INSERT INTO drive_items (team_lead_id, google_file_id, name, type, mime_type, file_extension, parent_folder_id, size_bytes, thumbnail_url, web_view_link, web_download_link, is_google_synced, uploaded_by)
                    VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, 1, ?)
                ");
                $ins->execute([
                    $teamLeadId,
                    $f['id'],
                    $name,
                    $type,
                    $mimeType,
                    $ext,
                    $size,
                    $thumb,
                    $webView,
                    $webDownload,
                    $teamLeadId
                ]);
            }
        }
        return true;
    }

    public static function syncWithGoogleDrive(): void {
        self::checkAccess();
        $teamLeadId = self::getActiveTeamLeadId();
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT * FROM drive_settings WHERE team_lead_id = ?");
        $stmt->execute([$teamLeadId]);
        $settings = $stmt->fetch();

        if (!$settings || empty($settings['is_connected']) || empty($settings['refresh_token'])) {
            setFlash('error', 'Please connect your Google Drive first.');
            header('Location: ?page=tech-drive');
            exit;
        }

        $accessToken = self::getValidAccessToken($teamLeadId);
        if (!$accessToken) {
            setFlash('error', 'Could not obtain valid access token from Google.');
            header('Location: ?page=tech-drive');
            exit;
        }

        // 1. Fetch Real Storage Quota & User Info
        $aboutUrl = 'https://www.googleapis.com/drive/v3/about?fields=storageQuota,user';
        $ch = curl_init($aboutUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$accessToken}"]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $aboutResp = curl_exec($ch);
        curl_close($ch);

        $aboutData = json_decode($aboutResp, true);
        if (!empty($aboutData['storageQuota'])) {
            $quota = $aboutData['storageQuota'];
            $totalBytes = (int)($quota['limit'] ?? (15 * 1024 * 1024 * 1024));
            $usedBytes = (int)($quota['usage'] ?? 0);
            $userEmail = $aboutData['user']['emailAddress'] ?? $settings['connected_account_email'];

            $upd = $db->prepare("UPDATE drive_settings SET total_storage_bytes = ?, used_storage_bytes = ?, connected_account_email = ?, updated_at = CURRENT_TIMESTAMP WHERE team_lead_id = ?");
            $upd->execute([$totalBytes, $usedBytes, $userEmail, $teamLeadId]);
        }

        // 2. Fetch Real Files & Folders from Google Drive
        $filesUrl = 'https://www.googleapis.com/drive/v3/files?pageSize=100&fields=files(id,name,mimeType,size,createdTime,thumbnailLink,webViewLink,webContentLink,parents,trashed)&q=trashed=false';
        $ch2 = curl_init($filesUrl);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$accessToken}"]);
        curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);
        $filesResp = curl_exec($ch2);
        curl_close($ch2);

        $filesData = json_decode($filesResp, true);
        $fetchedCount = 0;

        if (!empty($filesData['files'])) {
            $user = authUser();
            $db->prepare("DELETE FROM drive_items WHERE is_google_synced = 1 AND team_lead_id = ?")->execute([$teamLeadId]);

            foreach ($filesData['files'] as $f) {
                $isFolder = ($f['mimeType'] === 'application/vnd.google-apps.folder');
                $type = $isFolder ? 'folder' : 'file';
                $name = $f['name'];
                $mimeType = $f['mimeType'] ?: 'application/octet-stream';
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $size = (int)($f['size'] ?? 0);
                $webView = $f['webViewLink'] ?? '#';
                $webDownload = $f['webContentLink'] ?? $webView;
                $thumb = $f['thumbnailLink'] ?? '';

                $ins = $db->prepare("
                    INSERT INTO drive_items (team_lead_id, google_file_id, name, type, mime_type, file_extension, parent_folder_id, size_bytes, thumbnail_url, web_view_link, web_download_link, is_google_synced, uploaded_by)
                    VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, 1, ?)
                ");
                $ins->execute([
                    $teamLeadId,
                    $f['id'],
                    $name,
                    $type,
                    $mimeType,
                    $ext,
                    $size,
                    $thumb,
                    $webView,
                    $webDownload,
                    $user['id']
                ]);
                $fetchedCount++;
            }
        }

        setFlash('success', "🔄 Live Google Drive Synchronized! Fetched {$fetchedCount} items & updated real storage quota.");
        header('Location: ?page=tech-drive');
        exit;
    }

    public static function createFolder(): void {
        self::checkAccess();
        $user = authUser();
        $teamLeadId = self::getActiveTeamLeadId();
        $folderName = trim($_POST['folder_name'] ?? '');
        $parentId = (int)($_POST['parent_folder_id'] ?? 0);

        if (empty($folderName)) {
            setFlash('error', 'Folder name cannot be empty.');
            header('Location: ?page=tech-drive&folder=' . $parentId);
            exit;
        }

        $db = getDBConnection();
        $accessToken = self::getValidAccessToken($teamLeadId);
        $googleFolderId = null;

        // Create directly in Google Drive if connected
        if ($accessToken) {
            $ch = curl_init('https://www.googleapis.com/drive/v3/files');
            $postFields = json_encode([
                'name' => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$accessToken}",
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $resp = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($resp, true);
            $googleFolderId = $data['id'] ?? null;
        }

        $stmt = $db->prepare("
            INSERT INTO drive_items (team_lead_id, google_file_id, name, type, mime_type, file_extension, parent_folder_id, size_bytes, uploaded_by)
            VALUES (?, ?, ?, 'folder', 'application/vnd.google-apps.folder', '', ?, 0, ?)
        ");
        $stmt->execute([$teamLeadId, $googleFolderId, $folderName, $parentId, $user['id']]);

        setFlash('success', "Folder '{$folderName}' created in Team Cloud Drive!");
        header('Location: ?page=tech-drive&folder=' . $parentId);
        exit;
    }

    public static function uploadFile(): void {
        self::checkAccess();
        $user = authUser();
        $teamLeadId = self::getActiveTeamLeadId();
        $parentId = (int)($_POST['parent_folder_id'] ?? 0);

        if (empty($_FILES['files']['name'][0])) {
            setFlash('error', 'Please select at least one file to upload.');
            header('Location: ?page=tech-drive&folder=' . $parentId);
            exit;
        }

        $db = getDBConnection();
        $accessToken = self::getValidAccessToken($teamLeadId);
        $uploadDir = __DIR__ . '/../public/uploads/drive/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $uploadedCount = 0;
        $totalBytesAdded = 0;
        $fileCount = count($_FILES['files']['name']);

        for ($i = 0; $i < $fileCount; $i++) {
            $fileName = basename($_FILES['files']['name'][$i]);
            $tmpPath = $_FILES['files']['tmp_name'][$i];
            $fileSize = (int)$_FILES['files']['size'][$i];
            $mimeType = $_FILES['files']['type'][$i] ?: 'application/octet-stream';
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (empty($fileName) || $fileSize <= 0) continue;

            $uniqueName = time() . '_' . uniqid() . '.' . $ext;
            $targetPath = $uploadDir . $uniqueName;

            if (move_uploaded_file($tmpPath, $targetPath)) {
                $relativeUrl = 'uploads/drive/' . $uniqueName;
                $googleFileId = null;
                $webViewLink = $relativeUrl;
                $webDownloadLink = $relativeUrl;
                $thumbUrl = $relativeUrl;

                // If real Google Drive connected, stream upload to Google Drive API v3
                if ($accessToken && file_exists($targetPath)) {
                    $boundary = '-------' . md5(time());
                    $metadata = json_encode(['name' => $fileName, 'mimeType' => $mimeType]);
                    $fileData = file_get_contents($targetPath);

                    $body = "--{$boundary}\r\n";
                    $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
                    $body .= $metadata . "\r\n";
                    $body .= "--{$boundary}\r\n";
                    $body .= "Content-Type: {$mimeType}\r\n\r\n";
                    $body .= $fileData . "\r\n";
                    $body .= "--{$boundary}--";

                    $ch = curl_init('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,name,webViewLink,webContentLink,thumbnailLink');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        "Authorization: Bearer {$accessToken}",
                        "Content-Type: multipart/related; boundary={$boundary}",
                        "Content-Length: " . strlen($body)
                    ]);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    $resp = curl_exec($ch);
                    curl_close($ch);

                    $gData = json_decode($resp, true);
                    if (!empty($gData['id'])) {
                        $googleFileId = $gData['id'];
                        $webViewLink = $gData['webViewLink'] ?? $relativeUrl;
                        $webDownloadLink = $gData['webContentLink'] ?? $webViewLink;
                        $thumbUrl = $gData['thumbnailLink'] ?? $relativeUrl;

                        // Make Google Drive file viewable in dashboard
                        $chPerm = curl_init("https://www.googleapis.com/drive/v3/files/{$googleFileId}/permissions");
                        curl_setopt($chPerm, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($chPerm, CURLOPT_POSTFIELDS, json_encode(['role' => 'reader', 'type' => 'anyone']));
                        curl_setopt($chPerm, CURLOPT_HTTPHEADER, [
                            "Authorization: Bearer {$accessToken}",
                            "Content-Type: application/json"
                        ]);
                        curl_setopt($chPerm, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($chPerm, CURLOPT_SSL_VERIFYHOST, false);
                        curl_exec($chPerm);
                        curl_close($chPerm);
                    }
                }

                $stmt = $db->prepare("
                    INSERT INTO drive_items (team_lead_id, google_file_id, name, type, mime_type, file_extension, parent_folder_id, size_bytes, thumbnail_url, web_view_link, web_download_link, uploaded_by)
                    VALUES (?, ?, ?, 'file', ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $teamLeadId,
                    $googleFileId,
                    $fileName,
                    $mimeType,
                    $ext,
                    $parentId,
                    $fileSize,
                    $thumbUrl,
                    $webViewLink,
                    $webDownloadLink,
                    $user['id']
                ]);

                $uploadedCount++;
                $totalBytesAdded += $fileSize;
            }
        }

        if ($totalBytesAdded > 0) {
            $db->prepare("UPDATE drive_settings SET used_storage_bytes = used_storage_bytes + ?, updated_at = CURRENT_TIMESTAMP WHERE team_lead_id = ?")
               ->execute([$totalBytesAdded, $teamLeadId]);
        }

        if ($uploadedCount > 0) {
            setFlash('success', "🎉 Uploaded {$uploadedCount} file(s) to Team Cloud Drive!");
        } else {
            setFlash('error', 'Failed to upload files. Please try again.');
        }

        header('Location: ?page=tech-drive&folder=' . $parentId);
        exit;
    }

    public static function streamFile(): void {
        self::checkAccess();
        $id = (int)($_GET['id'] ?? 0);
        $teamLeadId = self::getActiveTeamLeadId();
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT * FROM drive_items WHERE id = ? AND team_lead_id = ?");
        $stmt->execute([$id, $teamLeadId]);
        $item = $stmt->fetch();
        if (!$item) {
            http_response_code(404);
            exit('File not found');
        }

        $localPath = __DIR__ . '/../public/' . $item['web_view_link'];
        if (file_exists($localPath) && is_file($localPath)) {
            header('Content-Type: ' . ($item['mime_type'] ?: 'application/octet-stream'));
            header('Content-Length: ' . filesize($localPath));
            header('Content-Disposition: inline; filename="' . basename($item['name']) . '"');
            readfile($localPath);
            exit;
        }

        // Proxy stream from Google Drive API with Access Token
        if (!empty($item['google_file_id'])) {
            $token = self::getValidAccessToken($teamLeadId);
            $ch = curl_init("https://www.googleapis.com/drive/v3/files/{$item['google_file_id']}?alt=media");
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$token}"]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            header('Content-Type: ' . ($item['mime_type'] ?: 'application/octet-stream'));
            header('Content-Disposition: inline; filename="' . basename($item['name']) . '"');
            curl_exec($ch);
            curl_close($ch);
            exit;
        }

        http_response_code(404);
        exit('File data not reachable');
    }

    public static function deleteItem(): void {
        self::checkAccess();
        $itemId = (int)($_POST['item_id'] ?? 0);
        $parentId = (int)($_POST['parent_folder_id'] ?? 0);
        $teamLeadId = self::getActiveTeamLeadId();
        $isAjax = !empty($_POST['is_ajax']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

        if ($itemId <= 0) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid item to delete.']);
                exit;
            }
            setFlash('error', 'Invalid item to delete.');
            header('Location: ?page=tech-drive&folder=' . $parentId);
            exit;
        }

        $db = getDBConnection();
        $stmt = $db->prepare("SELECT * FROM drive_items WHERE id = ? AND team_lead_id = ?");
        $stmt->execute([$itemId, $teamLeadId]);
        $item = $stmt->fetch();
        if ($item) {
            $accessToken = self::getValidAccessToken($teamLeadId);

            // If real Google Drive item, delete via API
            if ($accessToken && !empty($item['google_file_id'])) {
                $ch = curl_init("https://www.googleapis.com/drive/v3/files/{$item['google_file_id']}");
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$accessToken}"]);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_exec($ch);
                curl_close($ch);
            }

            // If local upload file, delete from disk
            if (!empty($item['web_view_link']) && strpos($item['web_view_link'], 'uploads/drive/') !== false) {
                $localPath = __DIR__ . '/../public/' . $item['web_view_link'];
                if (file_exists($localPath)) {
                    @unlink($localPath);
                }
            }

            // Remove database record & deduct storage
            $db->prepare("DELETE FROM drive_items WHERE id = ? AND team_lead_id = ?")->execute([$itemId, $teamLeadId]);
            if ($item['size_bytes'] > 0) {
                $db->prepare("UPDATE drive_settings SET used_storage_bytes = GREATEST(0, used_storage_bytes - ?), updated_at = CURRENT_TIMESTAMP WHERE team_lead_id = ?")
                   ->execute([(int)$item['size_bytes'], $teamLeadId]);
            }
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Item permanently deleted from Team Drive.']);
            exit;
        }

        setFlash('success', 'Item permanently deleted from Team Drive.');
        header('Location: ?page=tech-drive&folder=' . $parentId);
        exit;
    }

    public static function downloadZip(): void {
        self::checkAccess();
        $folderId = (int)($_GET['folder'] ?? 0);
        $teamLeadId = self::getActiveTeamLeadId();
        $db = getDBConnection();

        $folderName = 'Team_Drive_Export';
        if ($folderId > 0) {
            $stmt = $db->prepare("SELECT name FROM drive_items WHERE id = ? AND type = 'folder' AND team_lead_id = ?");
            $stmt->execute([$folderId, $teamLeadId]);
            $fn = $stmt->fetchColumn();
            if ($fn) {
                $folderName = preg_replace('/[^\w\-]/', '_', $fn);
            }
        }

        // Fetch all files in current folder
        $stmt = $db->prepare("SELECT * FROM drive_items WHERE parent_folder_id = ? AND type = 'file' AND is_deleted = 0 AND team_lead_id = ?");
        $stmt->execute([$folderId, $teamLeadId]);
        $files = $stmt->fetchAll();

        if (empty($files)) {
            setFlash('error', 'No files available in this folder to download as ZIP.');
            header('Location: ?page=tech-drive&folder=' . $folderId);
            exit;
        }

        $stagingDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hrms_zip_' . uniqid();
        if (!is_dir($stagingDir)) {
            mkdir($stagingDir, 0777, true);
        }

        $accessToken = self::getValidAccessToken($teamLeadId);
        $hasFiles = false;

        foreach ($files as $f) {
            $destFile = $stagingDir . DIRECTORY_SEPARATOR . basename($f['name']);
            $localPath = __DIR__ . '/../public/' . $f['web_view_link'];

            if (file_exists($localPath) && is_file($localPath)) {
                copy($localPath, $destFile);
                $hasFiles = true;
            } elseif (!empty($f['google_file_id']) && $accessToken) {
                $ch = curl_init("https://www.googleapis.com/drive/v3/files/{$f['google_file_id']}?alt=media");
                $fp = fopen($destFile, 'w+');
                curl_setopt($ch, CURLOPT_FILE, $fp);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$accessToken}"]);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_exec($ch);
                curl_close($ch);
                fclose($fp);
                if (file_exists($destFile) && filesize($destFile) > 0) {
                    $hasFiles = true;
                }
            }
        }

        if (!$hasFiles) {
            @rmdir($stagingDir);
            setFlash('error', 'No downloadable files could be retrieved.');
            header('Location: ?page=tech-drive&folder=' . $folderId);
            exit;
        }

        $zipFileName = $folderName . '_' . date('Y-m-d_His') . '.zip';
        $zipFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zipFileName;

        // Create Zip using tar.exe
        $tarCmd = 'tar.exe -a -c -f ' . escapeshellarg($zipFilePath) . ' -C ' . escapeshellarg($stagingDir) . ' .';
        exec($tarCmd, $tarOut, $tarStatus);

        if ($tarStatus !== 0 || !file_exists($zipFilePath) || filesize($zipFilePath) === 0) {
            $psCmd = 'powershell.exe -NoProfile -Command "Compress-Archive -Path \'' . addslashes($stagingDir) . '\*\' -DestinationPath \'' . addslashes($zipFilePath) . '\' -Force"';
            exec($psCmd, $psOut, $psStatus);
        }

        // Clean staging files
        $staged = glob($stagingDir . '/*');
        if ($staged) {
            foreach ($staged as $sf) {
                @unlink($sf);
            }
        }
        @rmdir($stagingDir);

        if (!file_exists($zipFilePath) || filesize($zipFilePath) === 0) {
            setFlash('error', 'Failed to generate ZIP archive file.');
            header('Location: ?page=tech-drive&folder=' . $folderId);
            exit;
        }

        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Description: File Transfer');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
        header('Content-Length: ' . filesize($zipFilePath));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Expires: 0');
        readfile($zipFilePath);
        @unlink($zipFilePath);
        exit;
    }

    // 🔒 STRICT RULE: Only Team Leads can register/connect/disconnect their Team's Google Drive. HR cannot register.
    public static function updateSettings(): void {
        self::checkAccess();
        $user = authUser();
        if ($user['role'] !== 'team_lead') {
            setFlash('error', '⛔ Access Denied: Only Team Leads can register or manage Team Cloud Drive settings.');
            header('Location: ?page=tech-drive');
            exit;
        }

        $teamLeadId = (int)$user['id'];
        $connectedEmail = trim($_POST['connected_account_email'] ?? '');
        $rootFolderName = trim($_POST['root_folder_name'] ?? ($user['name'] . '_Team_Drive'));
        $clientId = trim($_POST['client_id'] ?? '');
        $clientSecret = trim($_POST['client_secret'] ?? '');

        $db = getDBConnection();
        $existing = $db->prepare("SELECT id FROM drive_settings WHERE team_lead_id = ?");
        $existing->execute([$teamLeadId]);
        if ($existing->fetch()) {
            $stmt = $db->prepare("
                UPDATE drive_settings SET 
                connected_account_email = ?,
                root_folder_name = ?,
                client_id = ?,
                client_secret = ?,
                is_connected = 1,
                updated_at = CURRENT_TIMESTAMP
                WHERE team_lead_id = ?
            ");
            $stmt->execute([$connectedEmail, $rootFolderName, $clientId, $clientSecret, $teamLeadId]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO drive_settings (team_lead_id, connected_account_email, root_folder_name, client_id, client_secret, is_connected, total_storage_bytes, used_storage_bytes, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 1, 16106127360, 0, NOW(), NOW())
            ");
            $stmt->execute([$teamLeadId, $connectedEmail, $rootFolderName, $clientId, $clientSecret]);
        }

        setFlash('success', "Team Cloud Drive configuration for {$user['name']} updated successfully!");
        header('Location: ?page=tech-drive');
        exit;
    }

    public static function startOAuth(): void {
        self::checkAccess();
        $user = authUser();
        if ($user['role'] !== 'team_lead') {
            setFlash('error', '⛔ Access Denied: Only Team Leads can connect Google Drive OAuth.');
            header('Location: ?page=tech-drive');
            exit;
        }

        $teamLeadId = (int)$user['id'];
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT * FROM drive_settings WHERE team_lead_id = ?");
        $stmt->execute([$teamLeadId]);
        $settings = $stmt->fetch();

        $clientId = trim($_POST['client_id'] ?? $settings['client_id'] ?? '');
        $clientSecret = trim($_POST['client_secret'] ?? $settings['client_secret'] ?? '');
        $rootFolderName = trim($_POST['root_folder_name'] ?? $settings['root_folder_name'] ?? ($user['name'] . '_Team_Drive'));

        if (empty($clientId) || empty($clientSecret)) {
            setFlash('error', 'Please provide both Google Client ID and Client Secret.');
            header('Location: ?page=tech-drive');
            exit;
        }

        if ($settings) {
            $upd = $db->prepare("UPDATE drive_settings SET client_id = ?, client_secret = ?, root_folder_name = ? WHERE team_lead_id = ?");
            $upd->execute([$clientId, $clientSecret, $rootFolderName, $teamLeadId]);
        } else {
            $ins = $db->prepare("INSERT INTO drive_settings (team_lead_id, client_id, client_secret, root_folder_name, is_connected, total_storage_bytes, used_storage_bytes, created_at, updated_at) VALUES (?, ?, ?, ?, 0, 16106127360, 0, NOW(), NOW())");
            $ins->execute([$teamLeadId, $clientId, $clientSecret, $rootFolderName]);
        }

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
        $redirectUri = $protocol . $host . '/?action=drive-oauth-callback';

        $authUrl = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/drive https://www.googleapis.com/auth/userinfo.email',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => (string)$teamLeadId
        ]);

        header("Location: {$authUrl}");
        exit;
    }

    public static function handleOAuthCallback(): void {
        self::checkAccess();
        $user = authUser();
        if ($user['role'] !== 'team_lead') {
            setFlash('error', '⛔ Access Denied: Only Team Leads can authorize Google Drive.');
            header('Location: ?page=tech-drive');
            exit;
        }

        $code = $_GET['code'] ?? '';
        $error = $_GET['error'] ?? '';
        $teamLeadId = (int)($user['id']);

        if (!empty($error)) {
            setFlash('error', "Google authorization was cancelled or failed: {$error}");
            header('Location: ?page=tech-drive');
            exit;
        }

        if (empty($code)) {
            setFlash('error', 'No authorization code received from Google.');
            header('Location: ?page=tech-drive');
            exit;
        }

        $db = getDBConnection();
        $stmt = $db->prepare("SELECT * FROM drive_settings WHERE team_lead_id = ?");
        $stmt->execute([$teamLeadId]);
        $settings = $stmt->fetch();

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
        $redirectUri = $protocol . $host . '/?action=drive-oauth-callback';

        // Exchange code for permanent refresh_token
        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $postData = [
            'code' => $code,
            'client_id' => $settings['client_id'],
            'client_secret' => $settings['client_secret'],
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ];

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $tokenData = json_decode($response, true);

        if ($httpCode !== 200 || empty($tokenData['access_token'])) {
            $msg = $tokenData['error_description'] ?? $tokenData['error'] ?? $curlError ?? 'Failed to exchange token with Google.';
            setFlash('error', "Google OAuth Error: {$msg}");
            header('Location: ?page=tech-drive');
            exit;
        }

        $accessToken = $tokenData['access_token'];
        $refreshToken = $tokenData['refresh_token'] ?? $settings['refresh_token'];
        $expiresIn = (int)($tokenData['expires_in'] ?? 3600);
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

        // Fetch User's Google Account Email
        $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
        $ch2 = curl_init($userInfoUrl);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$accessToken}"]);
        curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch2, CURLOPT_TIMEOUT, 15);
        $userInfoResp = curl_exec($ch2);
        curl_close($ch2);
        $userInfo = json_decode($userInfoResp, true);
        $email = $userInfo['email'] ?? $settings['connected_account_email'] ?? 'teamlead@company.com';

        // Update Drive Settings with Permanent Token & Status for this Team Lead
        $upd = $db->prepare("
            UPDATE drive_settings SET 
            access_token = ?,
            refresh_token = ?,
            access_token_expires_at = ?,
            connected_account_email = ?,
            is_connected = 1,
            updated_at = CURRENT_TIMESTAMP
            WHERE team_lead_id = ?
        ");
        $upd->execute([$accessToken, $refreshToken, $expiresAt, $email, $teamLeadId]);

        // Auto-sync real Google Drive files and storage immediately
        self::syncWithGoogleDrive();
    }

    public static function disconnectDrive(): void {
        self::checkAccess();
        $user = authUser();
        if ($user['role'] !== 'team_lead') {
            setFlash('error', '⛔ Access Denied: Only Team Leads can disconnect Team Cloud Drive.');
            header('Location: ?page=tech-drive');
            exit;
        }

        $teamLeadId = (int)$user['id'];
        $db = getDBConnection();
        $db->prepare("
            UPDATE drive_settings SET 
            access_token = NULL,
            refresh_token = NULL,
            is_connected = 0,
            updated_at = CURRENT_TIMESTAMP
            WHERE team_lead_id = ?
        ")->execute([$teamLeadId]);

        setFlash('success', 'Your Team Google Drive has been disconnected.');
        header('Location: ?page=tech-drive');
        exit;
    }

    /**
     * Automatically catalogs employee task attachments into their Team Lead's Cloud Drive:
     * Daily Tasks / [YYYY-MM-DD] / [Images|Videos|Documents] / [Employee Name - Task Title.ext]
     */
    public static function storeDailyTaskSubmission(
        int $userId, 
        string $userName, 
        string $taskTitle, 
        int $taskId, 
        string $filePath, 
        string $attachmentType, 
        int $fileSize, 
        string $fileExt
    ): void {
        try {
            $db = getDBConnection();

            // Resolve employee's Team Lead ID
            $stmt = $db->prepare("SELECT reporting_tl_id FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $teamLeadId = (int)$stmt->fetchColumn();
            if ($teamLeadId <= 0) {
                $teamLeadId = (int)$db->query("SELECT id FROM users WHERE role = 'team_lead' AND status = 'active' ORDER BY id ASC LIMIT 1")->fetchColumn() ?: 30010;
            }

            // 1. Root folder: 'Daily Tasks' scoped to team_lead_id
            $stmt = $db->prepare("SELECT id FROM drive_items WHERE name = 'Daily Tasks' AND type = 'folder' AND parent_folder_id = 0 AND is_deleted = 0 AND team_lead_id = ? LIMIT 1");
            $stmt->execute([$teamLeadId]);
            $rootFolderId = (int)$stmt->fetchColumn();

            if (!$rootFolderId) {
                $ins = $db->prepare("
                    INSERT INTO drive_items (team_lead_id, name, type, mime_type, file_extension, parent_folder_id, size_bytes, uploaded_by)
                    VALUES (?, 'Daily Tasks', 'folder', 'application/vnd.google-apps.folder', '', 0, 0, ?)
                ");
                $ins->execute([$teamLeadId, $userId]);
                $rootFolderId = (int)$db->lastInsertId();
            }

            // 2. Date-wise folder: e.g. '2026-08-25'
            $todayDate = date('Y-m-d');
            $stmt = $db->prepare("SELECT id FROM drive_items WHERE name = ? AND type = 'folder' AND parent_folder_id = ? AND is_deleted = 0 AND team_lead_id = ? LIMIT 1");
            $stmt->execute([$todayDate, $rootFolderId, $teamLeadId]);
            $dateFolderId = (int)$stmt->fetchColumn();

            if (!$dateFolderId) {
                $ins = $db->prepare("
                    INSERT INTO drive_items (team_lead_id, name, type, mime_type, file_extension, parent_folder_id, size_bytes, uploaded_by)
                    VALUES (?, ?, 'folder', 'application/vnd.google-apps.folder', '', ?, 0, ?)
                ");
                $ins->execute([$teamLeadId, $todayDate, $rootFolderId, $userId]);
                $dateFolderId = (int)$db->lastInsertId();
            }

            // 3. Media subfolder: 'Images', 'Videos', or 'Documents'
            $subfolderName = ($attachmentType === 'image') ? 'Images' : (($attachmentType === 'video') ? 'Videos' : 'Documents');
            $stmt = $db->prepare("SELECT id FROM drive_items WHERE name = ? AND type = 'folder' AND parent_folder_id = ? AND is_deleted = 0 AND team_lead_id = ? LIMIT 1");
            $stmt->execute([$subfolderName, $dateFolderId, $teamLeadId]);
            $mediaFolderId = (int)$stmt->fetchColumn();

            if (!$mediaFolderId) {
                $ins = $db->prepare("
                    INSERT INTO drive_items (team_lead_id, name, type, mime_type, file_extension, parent_folder_id, size_bytes, uploaded_by)
                    VALUES (?, ?, 'folder', 'application/vnd.google-apps.folder', '', ?, 0, ?)
                ");
                $ins->execute([$teamLeadId, $subfolderName, $dateFolderId, $userId]);
                $mediaFolderId = (int)$db->lastInsertId();
            }

            // 4. File Display Name with Employee Name
            $cleanTitle = preg_replace('/[^\w\s\-]/u', '', $taskTitle);
            $cleanTitle = trim(preg_replace('/\s+/', ' ', $cleanTitle));
            if (mb_strlen($cleanTitle) > 35) {
                $cleanTitle = mb_substr($cleanTitle, 0, 35) . '...';
            }
            $fileDisplayName = "{$userName} - Task #{$taskId} {$cleanTitle}.{$fileExt}";

            $mimeType = match($fileExt) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png'         => 'image/png',
                'gif'         => 'image/gif',
                'webp'        => 'image/webp',
                'mp4'         => 'video/mp4',
                'webm'        => 'video/webm',
                'mov'         => 'video/quicktime',
                'pdf'         => 'application/pdf',
                'zip'         => 'application/zip',
                default       => 'application/octet-stream'
            };

            // 5. Insert file record into Team Cloud Drive
            $insFile = $db->prepare("
                INSERT INTO drive_items 
                (team_lead_id, name, type, mime_type, file_extension, parent_folder_id, size_bytes, thumbnail_url, web_view_link, web_download_link, uploaded_by)
                VALUES (?, ?, ?, 'file', ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insFile->execute([
                $teamLeadId,
                $fileDisplayName,
                $mimeType,
                $fileExt,
                $mediaFolderId,
                $fileSize,
                ($attachmentType === 'image' ? $filePath : ''),
                $filePath,
                $filePath,
                $userId
            ]);

            // 6. Update used storage in drive settings for this team lead
            if ($fileSize > 0) {
                $db->prepare("UPDATE drive_settings SET used_storage_bytes = used_storage_bytes + ?, updated_at = CURRENT_TIMESTAMP WHERE team_lead_id = ?")
                   ->execute([$fileSize, $teamLeadId]);
            }
        } catch (\Throwable $e) {
            error_log("Failed to sync daily task submission to Team Cloud Drive: " . $e->getMessage());
        }
    }
}
