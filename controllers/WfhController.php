<?php
// controllers/WfhController.php

class WfhController {
    public static function employeeIndex(): void {
        requireAuth('employee');
        $user = authUser();
        $db = getDBConnection();

        $requests = $db->query("SELECT * FROM wfh_requests WHERE user_id = {$user['id']} ORDER BY wfh_date DESC")->fetchAll(PDO::FETCH_ASSOC);

        require __DIR__ . '/../views/employee/wfh_request.php';
    }

    public static function apply(): void {
        requireAuth('employee');
        $user = authUser();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $wfhDate = trim($_POST['wfh_date'] ?? '');
            $reason = trim($_POST['reason'] ?? '');

            if (empty($wfhDate) || empty($reason)) {
                setFlash('error', 'Please select a date and provide a valid reason.');
                header('Location: ?page=employee-wfh');
                exit;
            }

            // STRICT CORPORATE RULE: Must apply AT LEAST 2 DAYS IN ADVANCE!
            $today = date('Y-m-d');
            $minAllowedDate = date('Y-m-d', strtotime('+2 days'));

            if ($wfhDate < $minAllowedDate) {
                setFlash('error', "🚫 Same-Day / Short-Notice WFH Blocked: WFH requests must be submitted at least 2 days in advance (Earliest allowed date: " . formatDate($minAllowedDate) . "). For same-day absence, please apply for Leave.");
                header('Location: ?page=employee-wfh');
                exit;
            }

            $db = getDBConnection();
            $stmt = $db->prepare("INSERT INTO wfh_requests (user_id, wfh_date, reason, status) VALUES (?, ?, ?, 'pending')");
            $stmt->execute([$user['id'], $wfhDate, $reason]);

            setFlash('success', "WFH Request for " . formatDate($wfhDate) . " submitted! Awaiting TL/HR approval (must be approved at least 1 day in advance).");
            header('Location: ?page=employee-wfh');
            exit;
        }
    }

    public static function adminIndex(): void {
        requireAuth(['admin', 'team_lead']);
        $user = authUser();
        $db = getDBConnection();

        if ($user['role'] === 'admin') {
            $requests = $db->query("SELECT w.*, u.name as user_name, u.emp_id, u.designation FROM wfh_requests w JOIN users u ON w.user_id = u.id ORDER BY w.applied_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $requests = $db->query("SELECT w.*, u.name as user_name, u.emp_id, u.designation FROM wfh_requests w JOIN users u ON w.user_id = u.id WHERE u.reporting_tl_id = {$user['id']} ORDER BY w.applied_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        }

        require __DIR__ . '/../views/admin/wfh_approvals.php';
    }

    public static function review(): void {
        requireAuth(['admin', 'team_lead']);
        $user = authUser();

        $id = (int)($_POST['id'] ?? 0);
        $action = $_POST['status'] ?? 'rejected';

        if ($id > 0 && in_array($action, ['approved', 'rejected'])) {
            $db = getDBConnection();
            $req = $db->query("SELECT * FROM wfh_requests WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);

            if ($req) {
                // STRICT CORPORATE RULE: Must be approved AT LEAST 1 DAY IN ADVANCE (unless Admin)!
                $today = date('Y-m-d');
                if ($action === 'approved' && $req['wfh_date'] <= $today && !isAdmin()) {
                    setFlash('error', "🚫 Approval Expired: WFH requests cannot be approved on or after the WFH date itself. The employee must take a Leave.");
                    header('Location: ?page=admin-wfh');
                    exit;
                }

                $stmt = $db->prepare("UPDATE wfh_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
                $stmt->execute([$action, $user['id'], $id]);

                setFlash('success', "WFH Request #{$id} marked as " . strtoupper($action) . ".");
            }
        }
        header('Location: ?page=admin-wfh');
        exit;
    }

    public static function grantDirectWfh(): void {
        requireAuth('admin');
        $hrUser = authUser();
        $db = getDBConnection();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $targetUserId = (int)($_POST['target_user_id'] ?? 0);
            $fromDate = trim($_POST['from_date'] ?? '');
            $toDate = trim($_POST['to_date'] ?? '');
            $reason = trim($_POST['reason'] ?? 'Direct WFH granted by HR Administration');

            if ($targetUserId <= 0 || empty($fromDate) || empty($toDate)) {
                setFlash('error', 'Please select an employee and valid date range.');
                header('Location: ?page=admin-wfh');
                exit;
            }

            if ($toDate < $fromDate) {
                setFlash('error', 'To Date cannot be before From Date.');
                header('Location: ?page=admin-wfh');
                exit;
            }

            $targetEmp = $db->query("SELECT id, name, emp_id, email FROM users WHERE id = {$targetUserId}")->fetch(PDO::FETCH_ASSOC);
            if (!$targetEmp) {
                setFlash('error', 'Selected employee not found.');
                header('Location: ?page=admin-wfh');
                exit;
            }

            // Loop through all dates in range and insert approved WFH
            $current = strtotime($fromDate);
            $end = strtotime($toDate);
            $insertedCount = 0;

            $stmtCheck = $db->prepare("SELECT id FROM wfh_requests WHERE user_id = ? AND wfh_date = ?");
            $stmtInsert = $db->prepare("
                INSERT INTO wfh_requests (user_id, wfh_date, reason, status, reviewed_by, reviewed_at) 
                VALUES (?, ?, ?, 'approved', ?, NOW())
            ");
            $stmtUpdate = $db->prepare("
                UPDATE wfh_requests 
                SET status = 'approved', reason = ?, reviewed_by = ?, reviewed_at = NOW() 
                WHERE id = ?
            ");

            while ($current <= $end) {
                $curDate = date('Y-m-d', $current);
                $stmtCheck->execute([$targetUserId, $curDate]);
                $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    $stmtUpdate->execute([$reason, $hrUser['id'], $existing['id']]);
                } else {
                    $stmtInsert->execute([$targetUserId, $curDate, $reason, $hrUser['id']]);
                }
                $insertedCount++;
                $current = strtotime('+1 day', $current);
            }

            $dateRangeStr = ($fromDate === $toDate) ? formatDate($fromDate) : (formatDate($fromDate) . " to " . formatDate($toDate));
            setFlash('success', "🎉 WFH Successfully Granted to <strong>{$targetEmp['name']}</strong> for <strong>{$dateRangeStr}</strong> ({$insertedCount} Days). Remote attendance enabled!");
            header('Location: ?page=admin-wfh');
            exit;
        }
    }
}