<?php
// controllers/SmartSheetController.php

class SmartSheetController {
    public static function index(): void {
        requireAuth('admin');
        $db = getDBConnection();

        // Fast summary query
        $sheets = $db->query("
            SELECT s.id, s.title, s.category, s.uploaded_by, s.created_at, 
                   COALESCE(JSON_LENGTH(s.rows_json), 0) as record_count, 
                   u.name as uploader_name 
            FROM smart_sheet_uploads s 
            JOIN users u ON s.uploaded_by = u.id 
            ORDER BY s.created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        require __DIR__ . '/../views/admin/smart_sheets.php';
    }

    public static function getSheetData(): void {
        requireAuth();
        $db = getDBConnection();
        $sheetId = (int)($_GET['sheet_id'] ?? 0);

        header('Content-Type: application/json');
        if ($sheetId <= 0) {
            echo json_encode(['columns' => [], 'rows' => [], 'styles' => []]);
            exit;
        }

        $row = $db->query("SELECT * FROM smart_sheet_uploads WHERE id = {$sheetId}")->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(['columns' => [], 'rows' => [], 'styles' => []]);
            exit;
        }

        echo json_encode([
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'category' => $row['category'],
            'columns' => json_decode($row['columns_json'] ?? '[]', true) ?: [],
            'rows' => json_decode($row['rows_json'] ?? '[]', true) ?: []
        ]);
        exit;
    }

    public static function saveSheetData(): void {
        requireAuth('admin');
        requireActiveShift();
        $db = getDBConnection();

        $sheetId = (int)($_POST['sheet_id'] ?? 0);
        $columns = json_decode($_POST['columns_json'] ?? '[]', true);
        $rows = json_decode($_POST['rows_json'] ?? '[]', true);

        header('Content-Type: application/json');
        if ($sheetId <= 0 || empty($columns)) {
            echo json_encode(['success' => false, 'message' => 'Invalid sheet payload']);
            exit;
        }

        $stmt = $db->prepare("UPDATE smart_sheet_uploads SET columns_json = ?, rows_json = ? WHERE id = ?");
        $stmt->execute([json_encode($columns, JSON_UNESCAPED_UNICODE), json_encode($rows, JSON_UNESCAPED_UNICODE), $sheetId]);

        // Auto-Sync edited data website-wide
        self::syncDataWebsiteWide($db, $columns, $rows);

        echo json_encode(['success' => true, 'message' => 'Workbook changes saved and synced website-wide!']);
        exit;
    }

    public static function upload(): void {
        requireAuth('admin');
        requireActiveShift();
        $user = authUser();
        $db = getDBConnection();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = trim($_POST['sheet_title'] ?? ($_POST['title'] ?? ''));
            $sheetUrl = trim($_POST['google_sheet_url'] ?? ($_POST['sheet_url'] ?? ''));
            $uploadedFile = $_FILES['sheet_file']['tmp_name'] ?? null;
            $originalName = $_FILES['sheet_file']['name'] ?? 'Spreadsheet';

            if (empty($title)) {
                $title = !empty($uploadedFile) ? pathinfo($originalName, PATHINFO_FILENAME) : 'Google Sheet Import';
            }

            // Parse via High-Accuracy Spreadsheet Engine
            $parsed = parseSpreadsheetData($uploadedFile, $sheetUrl, $originalName);
            $columns = $parsed['columns'] ?? [];
            $rows = $parsed['rows'] ?? [];

            if (empty($columns)) {
                setFlash('error', 'Unable to parse data from the uploaded sheet or URL. Please ensure the link is public or upload the .xlsx file directly.');
                header('Location: ?page=admin-smart-sheets');
                exit;
            }

            // Classification & Custom Section Matching
            $userCategory = trim($_POST['category'] ?? '');
            $customCategory = trim($_POST['custom_category'] ?? '');

            if (!empty($customCategory)) {
                $category = ucwords(strtolower($customCategory));
            } elseif (!empty($userCategory) && $userCategory !== 'auto') {
                $category = $userCategory;
            } else {
                $combinedContext = strtolower($title . ' ' . implode(' ', array_map('strval', $columns)));
                if (str_contains($combinedContext, 'bda') || str_contains($combinedContext, 'sales')) {
                    $category = 'BDA & Sales Team';
                } elseif (str_contains($combinedContext, 'attendance') || str_contains($combinedContext, 'punch')) {
                    $category = 'Attendance Logs';
                } elseif (str_contains($combinedContext, 'payroll') || str_contains($combinedContext, 'salary')) {
                    $category = 'Payroll & Salary';
                } elseif (str_contains($combinedContext, 'lead') || str_contains($combinedContext, 'calling')) {
                    $category = 'Lead CRM & Calling';
                } elseif (str_contains($combinedContext, 'email') || str_contains($combinedContext, 'employee') || str_contains($combinedContext, 'staff')) {
                    $category = 'Workforce Directory';
                } else {
                    $category = !empty($title) ? ucwords(strtolower($title)) : 'General Datasets';
                }
            }

            // 💾 Insert into smart_sheet_uploads
            $stmt = $db->prepare("INSERT INTO smart_sheet_uploads (uploaded_by, title, category, columns_json, rows_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$user['id'], $title, $category, json_encode($columns, JSON_UNESCAPED_UNICODE), json_encode($rows, JSON_UNESCAPED_UNICODE)]);

            // ⚡ UNIVERSAL 360-DEGREE WEBSITE-WIDE AUTO-SYNC
            $syncReport = self::syncDataWebsiteWide($db, $columns, $rows);

            $msg = "📊 Sheet <strong>" . htmlspecialchars($title) . "</strong> ingested successfully!";
            if (!empty($syncReport['employees'])) {
                $msg .= " • <strong>{$syncReport['employees']}</strong> workforce profiles synced.";
            }
            if (!empty($syncReport['attendance'])) {
                $msg .= " • <strong>{$syncReport['attendance']}</strong> attendance records auto-populated.";
            }
            if (!empty($syncReport['birthdays'])) {
                $msg .= " • <strong>{$syncReport['birthdays']}</strong> employee birthdays updated.";
            }

            setFlash('success', $msg);
            header('Location: ?page=admin-smart-sheets');
            exit;
        }
    }

    public static function delete(): void {
        requireAuth('admin');
        requireActiveShift();
        $db = getDBConnection();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $sheetId = (int)($_POST['sheet_id'] ?? 0);
            if ($sheetId > 0) {
                $db->prepare("DELETE FROM smart_sheet_uploads WHERE id = ?")->execute([$sheetId]);
                setFlash('success', 'Smart sheet record deleted.');
            }
        }
        header('Location: ?page=admin-smart-sheets');
        exit;
    }

    // ⚡ UNIVERSAL SYNC ENGINE
    private static function syncDataWebsiteWide($db, array $columns, array $rows): array {
        $report = ['employees' => 0, 'attendance' => 0, 'birthdays' => 0];
        if (empty($columns) || empty($rows)) return $report;

        // 1. Column Index Resolvers
        $colNameIdx = -1;
        $colEmailIdx = -1;
        $colDesigIdx = -1;
        $colPhoneIdx = -1;
        $colDobIdx = -1;
        $colSalaryIdx = -1;
        $colDeptIdx = -1;
        $colDateIdx = -1;
        $colStatusIdx = -1;
        $colInIdx = -1;
        $colOutIdx = -1;

        foreach ($columns as $idx => $cName) {
            $cn = strtolower(trim((string)$cName));
            if ($colNameIdx === -1 && (str_contains($cn, 'name') || str_contains($cn, 'employee name') || str_contains($cn, 'staff name'))) $colNameIdx = $idx;
            if ($colEmailIdx === -1 && (str_contains($cn, 'email') || str_contains($cn, 'mail'))) $colEmailIdx = $idx;
            if ($colDesigIdx === -1 && (str_contains($cn, 'desig') || str_contains($cn, 'role') || str_contains($cn, 'position'))) $colDesigIdx = $idx;
            if ($colPhoneIdx === -1 && (str_contains($cn, 'phone') || str_contains($cn, 'mobile') || str_contains($cn, 'contact') || str_contains($cn, 'whatsapp'))) $colPhoneIdx = $idx;
            if ($colDobIdx === -1 && (str_contains($cn, 'dob') || str_contains($cn, 'birth') || str_contains($cn, 'bday'))) $colDobIdx = $idx;
            if ($colSalaryIdx === -1 && (str_contains($cn, 'salary') || str_contains($cn, 'basic') || str_contains($cn, 'stipend') || str_contains($cn, 'ctc'))) $colSalaryIdx = $idx;
            if ($colDeptIdx === -1 && (str_contains($cn, 'dept') || str_contains($cn, 'department'))) $colDeptIdx = $idx;
            if ($colDateIdx === -1 && (str_contains($cn, 'date') && !str_contains($cn, 'birth'))) $colDateIdx = $idx;
            if ($colStatusIdx === -1 && (str_contains($cn, 'status') || str_contains($cn, 'attendance'))) $colStatusIdx = $idx;
            if ($colInIdx === -1 && (str_contains($cn, 'in time') || str_contains($cn, 'clock in') || str_contains($cn, 'punch in'))) $colInIdx = $idx;
            if ($colOutIdx === -1 && (str_contains($cn, 'out time') || str_contains($cn, 'clock out') || str_contains($cn, 'punch out'))) $colOutIdx = $idx;
        }

        if ($colNameIdx === -1 && count($columns) > 0) $colNameIdx = 0;
        if ($colEmailIdx === -1 && count($columns) > 1) {
            foreach ($rows as $r) {
                if (isset($r[1]) && str_contains((string)$r[1], '@')) {
                    $colEmailIdx = 1;
                    break;
                }
            }
        }

        $existingUsers = $db->query("SELECT id, LOWER(email) as email, name FROM users")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $emailToUser = [];
        $nameToUser = [];
        foreach ($existingUsers as $eu) {
            if (!empty($eu['email'])) $emailToUser[$eu['email']] = $eu;
            if (!empty($eu['name'])) $nameToUser[strtolower(trim($eu['name']))] = $eu;
        }

        // Get max EMP ID
        $maxEmp = $db->query("SELECT MAX(CAST(SUBSTRING(emp_id, 4) AS UNSIGNED)) FROM users WHERE emp_id LIKE 'EMP%'")->fetchColumn() ?: 25;

        // Loop rows for Workforce Sync & DOB Sync
        foreach ($rows as $row) {
            $rawName = ($colNameIdx !== -1 && isset($row[$colNameIdx])) ? trim((string)$row[$colNameIdx]) : '';
            $rawEmail = ($colEmailIdx !== -1 && isset($row[$colEmailIdx])) ? strtolower(trim((string)$row[$colEmailIdx])) : '';
            $rawDesig = ($colDesigIdx !== -1 && isset($row[$colDesigIdx])) ? trim((string)$row[$colDesigIdx]) : '';
            $rawPhone = ($colPhoneIdx !== -1 && isset($row[$colPhoneIdx])) ? trim((string)$row[$colPhoneIdx]) : '';
            $rawDob = ($colDobIdx !== -1 && isset($row[$colDobIdx])) ? trim((string)$row[$colDobIdx]) : '';
            $rawSalary = ($colSalaryIdx !== -1 && isset($row[$colSalaryIdx])) ? (float)preg_replace('/[^0-9.]/', '', (string)$row[$colSalaryIdx]) : 0;
            $rawDept = ($colDeptIdx !== -1 && isset($row[$colDeptIdx])) ? trim((string)$row[$colDeptIdx]) : '';

            $parsedDob = !empty($rawDob) && strtotime($rawDob) ? date('Y-m-d', strtotime($rawDob)) : null;

            if (empty($rawName) && empty($rawEmail)) continue;

            // Generate clean email if missing
            if (empty($rawEmail) && !empty($rawName)) {
                $rawEmail = strtolower(preg_replace('/[^a-z0-9]/', '', $rawName)) . '@ecofone.in';
            }

            // Clean designation & role
            $role = 'employee';
            $desig = !empty($rawDesig) ? $rawDesig : 'Executive';
            $workMode = 'office';
            $dept = !empty($rawDept) ? $rawDept : 'Operations';

            $upperDesig = strtoupper($desig);
            if ($upperDesig === 'BDA' || str_contains($upperDesig, 'BUSINESS DEVELOPMENT')) {
                $desig = 'BDA';
                $dept = 'Business Development';
            } elseif ($upperDesig === 'FSM' || str_contains($upperDesig, 'FIELD')) {
                $desig = 'FSM';
                $workMode = 'field';
                $dept = 'Field Operations';
            }

            if (isset($emailToUser[$rawEmail])) {
                // Update existing user profile & DOB
                $uId = $emailToUser[$rawEmail]['id'];
                $updateFields = [];
                $params = [];

                if ($parsedDob) {
                    $updateFields[] = "date_of_birth = COALESCE(date_of_birth, ?)";
                    $params[] = $parsedDob;
                    $report['birthdays']++;
                }
                if (!empty($rawPhone)) {
                    $updateFields[] = "phone = COALESCE(phone, ?)";
                    $params[] = $rawPhone;
                }
                if ($rawSalary > 0) {
                    $updateFields[] = "salary_basic = CASE WHEN salary_basic IS NULL OR salary_basic = 0 THEN ? ELSE salary_basic END";
                    $params[] = $rawSalary;
                }

                if (!empty($updateFields)) {
                    $params[] = $uId;
                    $db->prepare("UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?")->execute($params);
                }
            } else {
                // Register new user
                $maxEmp++;
                $newEmpId = 'EMP' . str_pad($maxEmp, 3, '0', STR_PAD_LEFT);
                $stmtNew = $db->prepare("
                    INSERT INTO users (emp_id, name, email, role, designation, work_mode, department_name, date_of_birth, phone, salary_basic, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                ");
                $stmtNew->execute([$newEmpId, $rawName, $rawEmail, $role, $desig, $workMode, $dept, $parsedDob, $rawPhone ?: null, $rawSalary ?: null]);
                $emailToUser[$rawEmail] = ['id' => $db->lastInsertId(), 'name' => $rawName, 'email' => $rawEmail];
                $report['employees']++;
                if ($parsedDob) $report['birthdays']++;
            }

            // Attendance Sync if Date and Status present
            if ($colDateIdx !== -1 && isset($row[$colDateIdx])) {
                $rawDate = trim((string)$row[$colDateIdx]);
                if (!empty($rawDate) && strtotime($rawDate)) {
                    $attDate = date('Y-m-d', strtotime($rawDate));
                    $matchedUser = $emailToUser[$rawEmail] ?? ($nameToUser[strtolower($rawName)] ?? null);

                    if ($matchedUser) {
                        $attStatus = 'present';
                        $rawSt = ($colStatusIdx !== -1 && isset($row[$colStatusIdx])) ? strtolower(trim((string)$row[$colStatusIdx])) : '';
                        if (str_contains($rawSt, 'absent') || $rawSt === 'a') $attStatus = 'absent';
                        elseif (str_contains($rawSt, 'wfh')) $attStatus = 'wfh';
                        elseif (str_contains($rawSt, 'leave') || $rawSt === 'l') $attStatus = 'on_leave';

                        $clockIn = ($colInIdx !== -1 && !empty($row[$colInIdx])) ? date('Y-m-d H:i:s', strtotime("{$attDate} {$row[$colInIdx]}")) : "{$attDate} 09:30:00";
                        $clockOut = ($colOutIdx !== -1 && !empty($row[$colOutIdx])) ? date('Y-m-d H:i:s', strtotime("{$attDate} {$row[$colOutIdx]}")) : "{$attDate} 18:30:00";

                        $existingAtt = $db->query("SELECT id FROM attendance WHERE user_id = {$matchedUser['id']} AND date = '{$attDate}'")->fetch();
                        if (!$existingAtt) {
                            $db->prepare("
                                INSERT INTO attendance (user_id, date, clock_in, clock_out, total_hours, status, notes, tl_approved, hr_corrected, is_geofence_verified)
                                VALUES (?, ?, ?, ?, 9.0, ?, 'Imported via Smart Sheet Ingestion', 1, 1, 1)
                            ")->execute([$matchedUser['id'], $attDate, $clockIn, $clockOut, $attStatus]);
                            $report['attendance']++;
                        }
                    }
                }
            }
        }

        return $report;
    }
}