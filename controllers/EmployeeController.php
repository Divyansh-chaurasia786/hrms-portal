<?php
// controllers/EmployeeController.php

class EmployeeController {
    public static function terminate(): void {
        requireRole(['admin']);
        requireActiveShift();
        $db = getDBConnection();
        $hrUser = authUser();
        $userId = (int)($_POST['user_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Termination by HR Management');

        if ($userId <= 0 || $userId === (int)$hrUser['id']) {
            setFlash('error', 'Invalid employee selected or cannot terminate self.');
            header('Location: ?page=admin-employees');
            exit;
        }

        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');

        // 1. Terminate user status & revoke session
        $db->prepare("
            UPDATE users 
            SET is_dismissed = 1, status = 'inactive', dismissal_reason = ?, 
                is_escalated_locked = 1, force_logout_at = ?, current_session_token = NULL 
            WHERE id = ?
        ")->execute([$reason, $now, $userId]);

        // 2. Terminate active shift if punched in today
        $db->prepare("UPDATE attendance SET clock_out = ?, notes = CONCAT(COALESCE(notes, ''), ' [Terminated by HR]') WHERE user_id = ? AND date = ? AND clock_out IS NULL")
           ->execute([$now, $userId, $today]);
        $db->prepare("UPDATE attendance_sessions SET clock_out = ?, ended_by = 'terminated', ended_by_user_id = ? WHERE user_id = ? AND clock_out IS NULL")
           ->execute([$now, $hrUser['id'], $userId]);

        setFlash('success', "🚫 Employee has been TERMINATED. Login access revoked immediately.");
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=admin-employees'));
        exit;
    }

    public static function restore(): void {
        requireRole(['admin']);
        requireActiveShift();
        $db = getDBConnection();
        $userId = (int)($_POST['user_id'] ?? 0);

        if ($userId <= 0) {
            setFlash('error', 'Invalid employee selected.');
            header('Location: ?page=admin-employees');
            exit;
        }

        $db->prepare("
            UPDATE users 
            SET is_dismissed = 0, status = 'active', dismissal_reason = NULL, 
                is_escalated_locked = 0, hr_warning_message = NULL, force_logout_at = NULL 
            WHERE id = ?
        ")->execute([$userId]);

        setFlash('success', "✅ Employee account RESTORED & login access re-enabled.");
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=admin-employees'));
        exit;
    }

    public static function create(): void {
        requireRole(['admin']);
        requireActiveShift();
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $role = $_POST['role'] ?? 'employee';
        $designation = trim($_POST['designation'] ?? '');
        
        // Reporting manager ID (TL for employees/TL support, HR Support or Head HR for TLs, Head HR for HR support)
        $reportingTLId = !empty($_POST['reporting_tl_id']) ? (int)$_POST['reporting_tl_id'] : null;
            
        $empType = $_POST['employment_type'] ?? 'full_time';
        $stipend = (float)($_POST['salary_basic'] ?? 0);

        if (empty($name) || empty($email) || empty($designation)) {
            setFlash('error', 'Name, Email, and Designation are required.');
            header('Location: ?page=admin-employees');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Please enter a valid email address.');
            header('Location: ?page=admin-employees');
            exit;
        }

        $db = getDBConnection();

        // Check duplicate email (Strict Unique Check)
        $check = $db->prepare("SELECT id, name, emp_id FROM users WHERE LOWER(email) = LOWER(?)");
        $check->execute([$email]);
        $existing = $check->fetch();
        if ($existing) {
            setFlash('error', "❌ Duplicate Email Blocked: The email address '{$email}' is already registered for {$existing['name']} ({$existing['emp_id']}). The same email cannot be used for multiple accounts.");
            header('Location: ?page=admin-employees');
            exit;
        }

        // Generate Emp ID
        $count = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn() + 1;
        $empId = 'EMP' . str_pad($count, 3, '0', STR_PAD_LEFT);
        $joiningDate = date('Y-m-d');

        $deptName = trim($_POST['department_name'] ?? 'Tech / Development');
        $whatsappNumber = trim($_POST['whatsapp_number'] ?? ($_POST['phone'] ?? ''));
        $workMode = $_POST['work_mode'] ?? 'office';

        // Auto-enforce Remote / GPS Everywhere for Field and HR departments
        if (stripos($deptName, 'Field') !== false || stripos($designation, 'Field') !== false) {
            $workMode = 'field';
        } elseif (stripos($deptName, 'HR') !== false || $role === 'admin' || stripos($designation, 'HR') !== false) {
            $workMode = 'wfh';
        }

        // Assigned permanent office location for Team Lead (or selected for staff)
        $assignedOfficeLocation = !empty($_POST['assigned_office_location']) ? (int)$_POST['assigned_office_location'] : 2;
        
        // If employee reports to a TL, automatically lock and inherit TL's permanent office location!
        if ($role === 'employee' && !empty($reportingTLId)) {
            $tlOffice = $db->query("SELECT assigned_office_location FROM users WHERE id = {$reportingTLId}")->fetchColumn();
            if ($tlOffice) {
                $assignedOfficeLocation = (int)$tlOffice;
            }
        }

        $stmt = $db->prepare("
            INSERT INTO users (emp_id, name, email, role, reporting_tl_id, designation, salary_basic, employment_type, joining_date, assigned_office_location, work_mode, department_name, phone, whatsapp_number, date_of_birth, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $dob = !empty($_POST['date_of_birth']) ? trim($_POST['date_of_birth']) : null;
        $stmt->execute([$empId, $name, $email, $role, $reportingTLId, $designation, $stipend, $empType, $joiningDate, $assignedOfficeLocation, $workMode, $deptName, $whatsappNumber, $whatsappNumber, $dob]);
        $newUserId = (int)$db->lastInsertId();

        // If newly created member is a Team Lead, assign selected team members
        if ($role === 'team_lead' && !empty($_POST['assigned_member_ids']) && is_array($_POST['assigned_member_ids'])) {
            $assignedIds = array_filter(array_map('intval', $_POST['assigned_member_ids']));
            if (!empty($assignedIds)) {
                $inAssigned = implode(',', $assignedIds);
                $notice = "👔 Team Lead Update: {$name} has been appointed as your new Team Lead by HR Administration. All shift approvals, tasks, and leave reviews will now be managed by {$name}.";
                $db->prepare("UPDATE users SET reporting_tl_id = ?, new_tl_notice = ? WHERE id IN ($inAssigned) AND status = 'active'")
                   ->execute([$newUserId, $notice]);
                
                $assignedCount = count($assignedIds);
                setFlash('success', "🎉 Team Lead {$name} ({$empId}) onboarded successfully! {$assignedCount} team member(s) assigned. Login via 100% Passwordless Email OTP.");
                header('Location: ?page=admin-employees');
                exit;
            }
        }

        setFlash('success', "🎉 Member {$name} ({$empId}) onboarded successfully! Login is 100% Passwordless via Email OTP.");
        header('Location: ?page=admin-employees');
        exit;
    }

    public static function delete(): void {
        requireRole(['admin']);
        requireActiveShift();
        $userId = (int)($_POST['user_id'] ?? 0);
        $newTlId = (int)($_POST['new_tl_id'] ?? 0);
        $user = authUser();

        if ($userId <= 0 || $userId === (int)$user['id']) {
            setFlash('error', 'Cannot delete self or invalid user.');
            header('Location: ?page=admin-employees');
            exit;
        }

        $db = getDBConnection();
        $targetUser = $db->query("SELECT id, name, role, status FROM users WHERE id = $userId")->fetch();

        if (!$targetUser) {
            setFlash('error', 'Employee not found.');
            header('Location: ?page=admin-employees');
            exit;
        }

        // If target is a Team Lead, verify team members and reassignment
        if ($targetUser['role'] === 'team_lead') {
            $members = $db->query("SELECT id, name FROM users WHERE reporting_tl_id = $userId AND status = 'active'")->fetchAll();
            $memberCount = count($members);

            if ($memberCount > 0) {
                $rawNewTl = trim($_POST['new_tl_id'] ?? '');

                if (empty($rawNewTl) || (int)$rawNewTl === $userId) {
                    setFlash('error', "⚠️ Cannot delete Team Lead: {$targetUser['name']} currently manages {$memberCount} active team members. You must assign a New Team Lead or transfer to HR first.");
                    header('Location: ?page=admin-employees');
                    exit;
                }

                if ($rawNewTl === 'admin' || (int)$rawNewTl === (int)$user['id']) {
                    // Transfer team directly to HR Admin
                    $notice = "👔 Team Lead Update: Following an organizational restructure, you now report directly to HR Administration ({$user['name']}).";
                    $db->prepare("UPDATE users SET reporting_tl_id = NULL, new_tl_notice = ? WHERE reporting_tl_id = ?")
                       ->execute([$notice, $userId]);
                } else {
                    $newTlId = (int)$rawNewTl;
                    $newTl = $db->query("SELECT id, name, role FROM users WHERE id = $newTlId AND status = 'active'")->fetch();
                    if (!$newTl) {
                        setFlash('error', 'Selected new Team Lead is invalid or inactive.');
                        header('Location: ?page=admin-employees');
                        exit;
                    }

                    // 1. Reassign all team members to new TL + set instant notification banner
                    $notice = "👔 Team Lead Update: {$newTl['name']} has been appointed as your new Team Lead by HR Administration. All shift approvals, tasks, and leave reviews will now be managed by {$newTl['name']}.";
                    $db->prepare("UPDATE users SET reporting_tl_id = ?, new_tl_notice = ? WHERE reporting_tl_id = ?")
                       ->execute([$newTlId, $notice, $userId]);

                    // 2. Notify the New TL via Directive
                    $db->prepare("INSERT INTO tl_feedbacks (tl_id, hr_id, message, priority, status) VALUES (?, ?, ?, 'important', 'unread')")
                       ->execute([$newTlId, $user['id'], "👥 Team Reassignment: HR has assigned {$memberCount} team members previously reporting to {$targetUser['name']} to your team roster."]);
                }
            }
        }

        // Completely Purge all records from backend database (Hard Delete)
        $db->prepare("DELETE FROM attendance_sessions WHERE user_id = ?")->execute([$userId]);
        $db->prepare("DELETE FROM attendance WHERE user_id = ?")->execute([$userId]);
        $db->prepare("DELETE FROM task_submissions WHERE submitted_by = ? OR task_id IN (SELECT id FROM tasks WHERE assigned_to = ?)")->execute([$userId, $userId]);
        $db->prepare("DELETE FROM tasks WHERE assigned_to = ? OR created_by = ?")->execute([$userId, $userId]);
        $db->prepare("DELETE FROM leave_applications WHERE user_id = ?")->execute([$userId]);
        $db->prepare("DELETE FROM daily_work_reports WHERE user_id = ? OR submitted_to_id = ?")->execute([$userId, $userId]);
        $db->prepare("DELETE FROM employee_escalations WHERE employee_id = ? OR tl_id = ?")->execute([$userId, $userId]);
        $db->prepare("DELETE FROM session_terminations WHERE user_id = ? OR terminated_by = ?")->execute([$userId, $userId]);
        $db->prepare("DELETE FROM tl_feedbacks WHERE tl_id = ?")->execute([$userId]);
        $db->prepare("DELETE FROM payroll WHERE user_id = ?")->execute([$userId]);
        $db->prepare("UPDATE users SET reporting_tl_id = NULL WHERE reporting_tl_id = ?")->execute([$userId]);
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);

        setFlash('success', "🗑️ {$targetUser['name']} and all associated data have been permanently deleted from the backend.");
        header('Location: ?page=admin-employees');
        exit;
    }

        public static function update(): void {
        requireRole(['admin']);
        requireActiveShift();
        $db = getDBConnection();
        $userId = (int)($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $role = $_POST['role'] ?? 'employee';
        $designation = trim($_POST['designation'] ?? '');
        $whatsappNumber = trim($_POST['whatsapp_number'] ?? ($_POST['phone'] ?? ''));
        $phone = $whatsappNumber;
        $empType = $_POST['employment_type'] ?? 'full_time';
        $salary = (float)($_POST['salary_basic'] ?? 0);
        $reportingTLId = !empty($_POST['reporting_tl_id']) ? (int)$_POST['reporting_tl_id'] : null;
        $deptName = trim($_POST['department_name'] ?? 'Tech / Development');
        $workMode = $_POST['work_mode'] ?? 'office';

        // Auto-enforce Remote / GPS Everywhere for Field and HR departments
        if (stripos($deptName, 'Field') !== false || stripos($designation, 'Field') !== false) {
            $workMode = 'field';
        } elseif (stripos($deptName, 'HR') !== false || $role === 'admin' || stripos($designation, 'HR') !== false) {
            $workMode = 'wfh';
        }

        $assignedOfficeLocation = !empty($_POST['assigned_office_location']) ? (int)$_POST['assigned_office_location'] : 2;
        
        // If employee reports to a TL, automatically lock and inherit TL's permanent office location!
        if ($role === 'employee' && !empty($reportingTLId)) {
            $tlOffice = $db->query("SELECT assigned_office_location FROM users WHERE id = {$reportingTLId}")->fetchColumn();
            if ($tlOffice) {
                $assignedOfficeLocation = (int)$tlOffice;
            }
        }

        if ($userId <= 0 || empty($name) || empty($email) || empty($designation)) {
            setFlash('error', 'Name, Email, and Designation are required.');
            header('Location: ?page=admin-employees');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Please enter a valid email address.');
            header('Location: ?page=admin-employees');
            exit;
        }

        $db = getDBConnection();
        $check = $db->prepare("SELECT id, name, emp_id FROM users WHERE LOWER(email) = LOWER(?) AND id != ?");
        $check->execute([$email, $userId]);
        $existing = $check->fetch();
        if ($existing) {
            setFlash('error', "❌ Duplicate Email Blocked: The email '{$email}' is already in use by {$existing['name']} ({$existing['emp_id']}).");
            header('Location: ?page=admin-employees');
            exit;
        }

        // If email was changed, invalidate all existing device sessions to force re-login with new email
        $origUser = $db->query("SELECT email FROM users WHERE id = {$userId}")->fetch();
        if ($origUser && strtolower($origUser['email']) !== strtolower($email)) {
            $db->prepare("UPDATE users SET current_session_token = NULL, force_logout_at = NOW() WHERE id = ?")->execute([$userId]);
        }

        $stmt = $db->prepare("
            UPDATE users 
            SET name = ?, email = ?, role = ?, designation = ?, phone = ?, whatsapp_number = ?, 
                employment_type = ?, salary_basic = ?, reporting_tl_id = ?, work_mode = ?, 
                department_name = ?, assigned_office_location = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $email, $role, $designation, $phone, $whatsappNumber, $empType, $salary, $reportingTLId, $workMode, $deptName, $assignedOfficeLocation, $dob, $userId]);

        $currUser = authUser();
        if ($currUser && (int)$currUser['id'] === (int)$userId) {
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['role'] = $role;
            $_SESSION['user']['designation'] = $designation;
            $_SESSION['user']['work_mode'] = $workMode;
            $_SESSION['user']['department_name'] = $deptName;
            setAuthCookie($_SESSION['user'], $_SESSION['user_session_token'] ?? '');
        }

        setFlash('success', "Profile details for {$name} updated successfully!");
        header('Location: ?page=admin-employees');
        exit;
    }

    public static function assignTeam(): void {
        requireRole(['admin']);
        requireActiveShift();
        $tlId = (int)($_POST['tl_id'] ?? 0);
        $assignedIds = isset($_POST['assigned_member_ids']) && is_array($_POST['assigned_member_ids']) 
            ? array_filter(array_map('intval', $_POST['assigned_member_ids']))
            : [];

        $db = getDBConnection();
        $tl = $db->query("SELECT id, name, role FROM users WHERE id = $tlId AND role = 'team_lead' AND status = 'active'")->fetch();

        if (!$tl) {
            setFlash('error', 'Invalid Team Lead selected.');
            header('Location: ?page=admin-employees');
            exit;
        }

        // 1. For currently assigned members who were UNCHECKED, reset their reporting_tl_id to NULL
        if (!empty($assignedIds)) {
            $inAssigned = implode(',', $assignedIds);
            $db->prepare("UPDATE users SET reporting_tl_id = NULL WHERE reporting_tl_id = ? AND id NOT IN ($inAssigned) AND status = 'active'")
               ->execute([$tlId]);
        } else {
            $db->prepare("UPDATE users SET reporting_tl_id = NULL WHERE reporting_tl_id = ? AND status = 'active'")
               ->execute([$tlId]);
        }

        // 2. For newly assigned members, set reporting_tl_id = $tlId and trigger notification
        if (!empty($assignedIds)) {
            $inAssigned = implode(',', $assignedIds);
            $notice = "👔 Team Lead Update: {$tl['name']} has been assigned as your Team Lead by HR Administration. All shift approvals, tasks, and leave reviews will now be managed by {$tl['name']}.";
            
            // Find who is newly assigned to this TL
            $newMembers = $db->query("SELECT id FROM users WHERE id IN ($inAssigned) AND (reporting_tl_id != $tlId OR reporting_tl_id IS NULL)")->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($newMembers)) {
                $inNew = implode(',', array_map('intval', $newMembers));
                $db->prepare("UPDATE users SET reporting_tl_id = ?, new_tl_notice = ? WHERE id IN ($inNew) AND status = 'active'")
                   ->execute([$tlId, $notice]);
            }
        }

        $cnt = count($assignedIds);
        setFlash('success', "✅ Team updated! {$cnt} team member(s) successfully assigned to {$tl['name']}.");
        header('Location: ?page=admin-employees');
        exit;
    }
}
