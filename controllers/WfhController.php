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
                // STRICT CORPORATE RULE: Must be approved AT LEAST 1 DAY IN ADVANCE!
                $today = date('Y-m-d');
                if ($action === 'approved' && $req['wfh_date'] <= $today) {
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

    public static function setHrWfhRange(): void {
        requireAuth('admin');
        $user = authUser();

        $startDate = trim($_POST['start_date'] ?? '');
        $endDate = trim($_POST['end_date'] ?? '');

        $db = getDBConnection();
        if (!empty($startDate) && !empty($endDate) && $endDate >= $startDate) {
            $stmt = $db->prepare("UPDATE users SET hr_wfh_start_date = ?, hr_wfh_end_date = ?, work_mode = 'wfh' WHERE id = ?");
            $stmt->execute([$startDate, $endDate, $user['id']]);
            setFlash('success', "HR WFH schedule set from " . formatDate($startDate) . " to " . formatDate($endDate) . ". System will auto-revert to Office Mode afterwards.");
        } else {
            // Clear HR WFH
            $db->prepare("UPDATE users SET hr_wfh_start_date = NULL, hr_wfh_end_date = NULL, work_mode = 'office' WHERE id = ?")->execute([$user['id']]);
            setFlash('success', "HR WFH cleared. Switched back to In-Office Mode.");
        }
        header('Location: ?page=admin-wfh');
        exit;
    }
}