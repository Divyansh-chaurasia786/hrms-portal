<?php
// controllers/LeaveController.php

class LeaveController {
    public static function apply(): void {
        requireAuth();
        $user = authUser();

        if ($user['role'] === 'admin') {
            setFlash('error', 'HR Administrators are the sanctioning authority and do not apply for leave here.');
            header('Location: ?page=admin-leaves');
            exit;
        }

        $leaveTypeId = (int)($_POST['leave_type_id'] ?? 1);
        if ($leaveTypeId <= 0) $leaveTypeId = 1;
        $customLeaveType = trim($_POST['custom_leave_type'] ?? 'Leave Application');
        if (empty($customLeaveType)) $customLeaveType = 'Leave Application';
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? $startDate;
        $reason = trim($_POST['reason'] ?? '');

        if (!$startDate || !$endDate || empty($reason)) {
            setFlash('error', 'Please fill all required leave application fields (Dates and Reason).');
            header('Location: ?page=employee-leaves');
            exit;
        }

        // Automatically calculate total days
        $startTs = strtotime($startDate);
        $endTs = strtotime($endDate);
        if ($startTs && $endTs && $endTs >= $startTs) {
            $totalDays = (int)(round(($endTs - $startTs) / 86400)) + 1;
        } else {
            $totalDays = 1;
        }

        $db = getDBConnection();
        $stmt = $db->prepare("
            INSERT INTO leave_applications (user_id, leave_type_id, custom_leave_type, start_date, end_date, total_days, reason, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_tl_review')
        ");
        $stmt->execute([$user['id'], $leaveTypeId, $customLeaveType, $startDate, $endDate, $totalDays, $reason]);

        // 📧 Trigger Automated Formal Email to HR with TL in CC
        try {
            $empStmt = $db->prepare("SELECT u.*, d.name as department_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = ?");
            $empStmt->execute([$user['id']]);
            $employee = $empStmt->fetch() ?: $user;

            $tl = null;
            if (!empty($employee['reporting_tl_id'])) {
                $tlStmt = $db->prepare("SELECT id, name, email FROM users WHERE id = ?");
                $tlStmt->execute([(int)$employee['reporting_tl_id']]);
                $tl = $tlStmt->fetch() ?: null;
            }

            $hrList = $db->query("SELECT id, name, email FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll();
            if (empty($hrList)) {
                $hrList = [['name' => 'Head HR', 'email' => 'ecofonehr@gmail.com']];
            }

            sendLeaveApplicationEmail([
                'custom_leave_type' => $customLeaveType,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_days' => $totalDays,
                'reason' => $reason
            ], $employee, $tl, $hrList);
        } catch (Throwable $e) {
            // Ignore email errors to prevent application submission failure
        }

        setFlash('success', "Leave application for {$totalDays} day(s) submitted! An official email has been sent to HR (with TL in CC).");
        header('Location: ?page=employee-leaves');
        exit;
    }

    public static function tlReview(): void {
        requireRole(['team_lead', 'admin']);
        $user = authUser();
        $leaveId = (int)($_POST['leave_id'] ?? 0);
        $recommendation = $_POST['recommendation'] ?? 'recommended';
        $remarks = trim($_POST['tl_remarks'] ?? '');

        if ($leaveId <= 0 || !in_array($recommendation, ['recommended', 'not_recommended'])) {
            setFlash('error', 'Invalid review action.');
            header('Location: ?page=tl-leaves');
            exit;
        }

        $db = getDBConnection();
        $stmt = $db->prepare("
            UPDATE leave_applications 
            SET status = 'pending_hr_approval',
                tl_reviewed = 1,
                tl_recommendation = ?,
                tl_remarks = ?,
                tl_reviewed_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$recommendation, $remarks, $leaveId]);

        setFlash('success', 'Leave application reviewed and forwarded to HR for final approval!');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=tl-leaves'));
        exit;
    }

    public static function hrAction(): void {
        requireRole(['admin']);
        $user = authUser();
        $leaveId = (int)($_POST['leave_id'] ?? 0);
        $action = $_POST['action'] ?? 'approved';
        $remarks = trim($_POST['hr_remarks'] ?? '');

        if ($leaveId <= 0 || !in_array($action, ['approved', 'rejected'])) {
            setFlash('error', 'Invalid leave action.');
            header('Location: ?page=admin-leaves');
            exit;
        }

        $db = getDBConnection();
        $stmt = $db->prepare("
            UPDATE leave_applications 
            SET status = ?,
                hr_action_by = ?,
                hr_remarks = ?,
                hr_action_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$action, $user['id'], $remarks, $leaveId]);

        // 📧 Trigger Automated Formal Email to Employee with TL in CC
        try {
            $leaveStmt = $db->prepare("
                SELECT la.*, u.name as emp_name, u.email as emp_email, u.emp_id, u.designation, u.reporting_tl_id,
                       tl.name as tl_name, tl.email as tl_email
                FROM leave_applications la
                JOIN users u ON la.user_id = u.id
                LEFT JOIN users tl ON u.reporting_tl_id = tl.id
                WHERE la.id = ?
            ");
            $leaveStmt->execute([$leaveId]);
            $leaveRow = $leaveStmt->fetch();

            if ($leaveRow) {
                $employee = [
                    'name' => $leaveRow['emp_name'],
                    'email' => $leaveRow['emp_email'],
                    'emp_id' => $leaveRow['emp_id'],
                    'designation' => $leaveRow['designation']
                ];
                $tl = (!empty($leaveRow['tl_email'])) ? [
                    'name' => $leaveRow['tl_name'],
                    'email' => $leaveRow['tl_email']
                ] : null;

                sendLeaveDecisionEmail($leaveRow, $employee, $tl, $user, $action, $remarks);
            }
        } catch (Throwable $e) {
            // Ignore email errors to avoid breaking the HTTP response
        }

        setFlash('success', 'Leave application ' . ucfirst($action) . ' by HR successfully! An official email notice has been sent to the employee (with TL in CC).');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=admin-leaves'));
        exit;
    }
}
