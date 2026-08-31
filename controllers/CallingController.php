<?php
// controllers/CallingController.php

class CallingController {

    /**
     * 📤 TL Ingests Lead Sheet & Auto-Divides Across Active BDA Callers
     */
    public static function bulkUploadLeads(): void {
        requireRole(['admin', 'team_lead']);
        requireActiveShift();
        $db = getDBConnection();
        $user = authUser();

        if (empty($_FILES['lead_file']) || $_FILES['lead_file']['error'] !== UPLOAD_ERR_OK) {
            setFlash('error', 'Please select a valid CSV file to upload.');
            header('Location: ?page=calling-manage');
            exit;
        }

        $tmpPath = $_FILES['lead_file']['tmp_name'];
        $handle = fopen($tmpPath, 'r');
        if (!$handle) {
            setFlash('error', 'Failed to read uploaded CSV file.');
            header('Location: ?page=calling-manage');
            exit;
        }

        $header = fgetcsv($handle, 2000, ',');
        if (!$header) {
            fclose($handle);
            setFlash('error', 'CSV file appears to be empty.');
            header('Location: ?page=calling-manage');
            exit;
        }

        // Map column indices
        $nameIdx = 0; $phoneIdx = 1; $emailIdx = -1; $cityIdx = -1; $courseIdx = -1; $budgetIdx = -1;
        foreach ($header as $i => $col) {
            $clean = strtolower(trim($col));
            if (in_array($clean, ['name', 'candidate_name', 'full_name', 'student_name', 'client_name', 'lead_name'])) $nameIdx = $i;
            elseif (in_array($clean, ['phone', 'contact', 'mobile', 'phone_number', 'contact_number', 'mobile_no'])) $phoneIdx = $i;
            elseif (in_array($clean, ['email', 'email_address', 'mail'])) $emailIdx = $i;
            elseif (in_array($clean, ['city', 'location', 'state', 'address'])) $cityIdx = $i;
            elseif (in_array($clean, ['course', 'course_service', 'service', 'program', 'product', 'interest'])) $courseIdx = $i;
            elseif (in_array($clean, ['budget', 'deal_value', 'amount', 'fee', 'price'])) $budgetIdx = $i;
        }

        // Fetch active BDA Callers to divide numbers equally
        $callers = $db->query("
            SELECT id FROM users 
            WHERE status = 'active' AND role = 'employee' 
              AND (department_name = 'Business Development' OR department_name LIKE '%Calling%' OR designation LIKE '%BDA%')
            ORDER BY id ASC
        ")->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $autoDivide = isset($_POST['auto_divide']) && $_POST['auto_divide'] === '1';
        $callerCount = count($callers);
        $callerIndex = 0;

        $inserted = 0;
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare("
            INSERT INTO calling_leads (
                assigned_to, name, phone, email, city, course_service, 
                budget, priority, status, deal_value, notes, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'medium', 'new', ?, '', ?, ?)
        ");

        while (($row = fgetcsv($handle, 2000, ',')) !== false) {
            if (empty(array_filter($row))) continue;
            $name = trim($row[$nameIdx] ?? 'Prospect ' . ($inserted + 1));
            $phone = preg_replace('/[^0-9]/', '', trim($row[$phoneIdx] ?? ''));
            if (empty($phone)) continue;

            $email = ($emailIdx >= 0 && isset($row[$emailIdx])) ? strtolower(trim($row[$emailIdx])) : '';
            $city = ($cityIdx >= 0 && isset($row[$cityIdx])) ? trim($row[$cityIdx]) : '';
            $course = ($courseIdx >= 0 && isset($row[$courseIdx])) ? trim($row[$courseIdx]) : 'General Inquiry';
            $budget = ($budgetIdx >= 0 && isset($row[$budgetIdx])) ? (float)preg_replace('/[^0-9.]/', '', $row[$budgetIdx]) : 0;

            // Auto divide equally among callers
            $assignedTo = null;
            if ($autoDivide && $callerCount > 0) {
                $assignedTo = (int)$callers[$callerIndex % $callerCount];
                $callerIndex++;
            }

            $stmt->execute([$assignedTo, $name, $phone, $email, $city, $course, $budget ? "₹{$budget}" : '', $budget, $now, $now]);
            $inserted++;
        }
        fclose($handle);

        if ($autoDivide && $callerCount > 0) {
            setFlash('success', "🎉 Uploaded & Divided {$inserted} numbers equally across {$callerCount} active BDA executives!");
        } else {
            setFlash('success', "🎉 Uploaded {$inserted} lead numbers successfully. You can divide them via Auto-Allocate.");
        }

        header('Location: ?page=calling-manage');
        exit;
    }

    /**
     * ⚖️ Divide Unassigned Leads Equally (Round-Robin Split)
     */
    public static function allocateRoundRobin(): void {
        requireRole(['admin', 'team_lead']);
        requireActiveShift();
        $db = getDBConnection();

        $callers = $db->query("
            SELECT id FROM users 
            WHERE status = 'active' AND role = 'employee' 
              AND (department_name = 'Business Development' OR department_name LIKE '%Calling%' OR designation LIKE '%BDA%')
            ORDER BY id ASC
        ")->fetchAll(PDO::FETCH_COLUMN) ?: [];

        if (empty($callers)) {
            setFlash('error', 'No active BDA calling executives found to divide numbers.');
            header('Location: ?page=calling-manage');
            exit;
        }

        $unassigned = $db->query("SELECT id FROM calling_leads WHERE assigned_to IS NULL OR assigned_to = 0 ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if (empty($unassigned)) {
            setFlash('warning', 'All numbers are already divided and assigned.');
            header('Location: ?page=calling-manage');
            exit;
        }

        $stmt = $db->prepare("UPDATE calling_leads SET assigned_to = ?, updated_at = NOW() WHERE id = ?");
        $callerCount = count($callers);
        $count = 0;

        foreach ($unassigned as $i => $leadId) {
            $assignedCaller = $callers[$i % $callerCount];
            $stmt->execute([$assignedCaller, $leadId]);
            $count++;
        }

        setFlash('success', "⚖️ Divided {$count} numbers equally across {$callerCount} active BDA executives!");
        header('Location: ?page=calling-manage');
        exit;
    }

    /**
     * 📞 BDA Executive Updates Call Status & Notes
     */
    public static function updateCallStatus(): void {
        requireActiveShift();
        $user = authUser();
        $db = getDBConnection();

        $leadId = (int)($_POST['lead_id'] ?? 0);
        $status = trim($_POST['status'] ?? 'new');
        $notes = trim($_POST['notes'] ?? '');
        $callbackDatetime = !empty($_POST['callback_datetime']) ? $_POST['callback_datetime'] : null;
        $dealValue = (float)($_POST['deal_value'] ?? 0);

        if ($leadId <= 0) {
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Invalid lead ID']);
                exit;
            }
            setFlash('error', 'Invalid lead ID.');
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=calling-queue'));
            exit;
        }

        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');

        // Update lead status
        $stmt = $db->prepare("
            UPDATE calling_leads 
            SET status = ?, notes = ?, callback_datetime = ?, deal_value = ?, updated_at = ? 
            WHERE id = ? AND (assigned_to = ? OR ? IN ('admin', 'team_lead'))
        ");
        $stmt->execute([$status, $notes, $callbackDatetime, $dealValue, $now, $leadId, $user['id'], $user['role']]);

        // Insert call log audit
        $db->prepare("
            INSERT INTO call_logs (
                lead_id, caller_id, call_date, start_time, end_time, 
                call_duration_seconds, disposition, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?)
        ")->execute([$leadId, $user['id'], $today, $now, $now, $status, $notes, $now]);

        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'status' => $status, 'updated_at' => $now]);
            exit;
        }

        setFlash('success', '✅ Call status updated.');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=calling-queue'));
        exit;
    }

    /**
     * ➕ Add / Edit Lead Data Entry
     */
    public static function saveLeadData(): void {
        requireActiveShift();
        $user = authUser();
        $db = getDBConnection();

        $name = trim($_POST['name'] ?? '');
        $phone = preg_replace('/[^0-9]/', '', trim($_POST['phone'] ?? ''));
        $email = strtolower(trim($_POST['email'] ?? ''));
        $city = trim($_POST['city'] ?? '');
        $courseService = trim($_POST['course_service'] ?? 'General Inquiry');
        $status = trim($_POST['status'] ?? 'new');
        $dealValue = (float)($_POST['deal_value'] ?? 0);
        $callbackDatetime = !empty($_POST['callback_datetime']) ? $_POST['callback_datetime'] : null;
        $notes = trim($_POST['notes'] ?? '');
        $leadId = (int)($_POST['lead_id'] ?? 0);

        if (empty($name) || empty($phone)) {
            setFlash('error', 'Candidate Name and Phone Number are required.');
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=calling-queue'));
            exit;
        }

        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');

        if ($leadId > 0) {
            $stmt = $db->prepare("
                UPDATE calling_leads 
                SET name = ?, phone = ?, email = ?, city = ?, course_service = ?, 
                    status = ?, deal_value = ?, callback_datetime = ?, notes = ?, updated_at = ?
                WHERE id = ? AND (assigned_to = ? OR ? IN ('admin', 'team_lead'))
            ");
            $stmt->execute([$name, $phone, $email, $city, $courseService, $status, $dealValue, $callbackDatetime, $notes, $now, $leadId, $user['id'], $user['role']]);
            setFlash('success', "✅ Lead data for '{$name}' updated.");
        } else {
            $assignedTo = ($user['role'] === 'employee') ? $user['id'] : (!empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : $user['id']);
            $stmt = $db->prepare("
                INSERT INTO calling_leads (
                    assigned_to, name, phone, email, city, course_service, 
                    budget, priority, status, deal_value, callback_datetime, notes, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, '', 'medium', ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$assignedTo, $name, $phone, $email, $city, $courseService, $status, $dealValue, $callbackDatetime, $notes, $now, $now]);
            $leadId = (int)$db->lastInsertId();

            $db->prepare("
                INSERT INTO call_logs (
                    lead_id, caller_id, call_date, start_time, end_time, 
                    call_duration_seconds, disposition, notes, created_at
                ) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?)
            ")->execute([$leadId, $user['id'], $today, $now, $now, $status, $notes, $now]);

            setFlash('success', "🎉 New calling entry for '{$name}' saved.");
        }

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=calling-queue'));
        exit;
    }

    public static function deleteLead(): void {
        requireActiveShift();
        $user = authUser();
        $db = getDBConnection();
        $leadId = (int)($_POST['lead_id'] ?? 0);

        if ($leadId <= 0) {
            setFlash('error', 'Invalid lead ID.');
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=calling-queue'));
            exit;
        }

        if ($user['role'] === 'admin' || $user['role'] === 'team_lead') {
            $db->prepare("DELETE FROM call_logs WHERE lead_id = ?")->execute([$leadId]);
            $db->prepare("DELETE FROM calling_leads WHERE id = ?")->execute([$leadId]);
        } else {
            $db->prepare("DELETE FROM call_logs WHERE lead_id = ? AND caller_id = ?")->execute([$leadId, $user['id']]);
            $db->prepare("DELETE FROM calling_leads WHERE id = ? AND assigned_to = ?")->execute([$leadId, $user['id']]);
        }

        setFlash('success', '🗑️ Lead record deleted.');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=calling-queue'));
        exit;
    }

    public static function exportCallingReport(): void {
        requireRole(['admin', 'team_lead']);
        $db = getDBConnection();

        $sql = "
            SELECT l.id, l.name as candidate_name, l.phone, l.email, l.city, l.course_service, 
                   l.status, l.deal_value, l.callback_datetime, l.notes, u.name as executive_name, l.created_at, l.updated_at
            FROM calling_leads l
            LEFT JOIN users u ON l.assigned_to = u.id
            ORDER BY l.id DESC
        ";
        $leads = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=BDA_Master_Calling_Report_' . date('Ymd_His') . '.csv');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Candidate Name', 'Phone', 'Email', 'City', 'Course/Service', 'Status', 'Deal Value', 'Follow-up Date', 'Notes/Remarks', 'Executive', 'Entry Date', 'Last Updated']);

        foreach ($leads as $r) {
            fputcsv($out, [
                $r['id'],
                $r['candidate_name'],
                $r['phone'],
                $r['email'],
                $r['city'],
                $r['course_service'],
                ucfirst(str_replace('_', ' ', $r['status'])),
                $r['deal_value'],
                $r['callback_datetime'] ?: '-',
                $r['notes'],
                $r['executive_name'] ?: 'Unassigned',
                $r['created_at'],
                $r['updated_at']
            ]);
        }
        fclose($out);
        exit;
    }
}