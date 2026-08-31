<?php
// controllers/CallingController.php

class CallingController {

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
            // Update existing lead entry
            $stmt = $db->prepare("
                UPDATE calling_leads 
                SET name = ?, phone = ?, email = ?, city = ?, course_service = ?, 
                    status = ?, deal_value = ?, callback_datetime = ?, notes = ?, updated_at = ?
                WHERE id = ? AND (assigned_to = ? OR ? = 'admin' OR ? = 'team_lead')
            ");
            $stmt->execute([$name, $phone, $email, $city, $courseService, $status, $dealValue, $callbackDatetime, $notes, $now, $leadId, $user['id'], $user['role'], $user['role']]);
            setFlash('success', "✅ Lead data for '{$name}' updated successfully.");
        } else {
            // Insert new lead entry
            $assignedTo = ($user['role'] === 'employee') ? $user['id'] : (!empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : $user['id']);
            
            $stmt = $db->prepare("
                INSERT INTO calling_leads (
                    assigned_to, name, phone, email, city, course_service, 
                    budget, priority, status, deal_value, callback_datetime, notes, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, '', 'medium', ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$assignedTo, $name, $phone, $email, $city, $courseService, $status, $dealValue, $callbackDatetime, $notes, $now, $now]);
            $leadId = (int)$db->lastInsertId();

            // Insert into call logs audit
            $db->prepare("
                INSERT INTO call_logs (
                    lead_id, caller_id, call_date, start_time, end_time, 
                    call_duration_seconds, disposition, notes, created_at
                ) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?)
            ")->execute([$leadId, $user['id'], $today, $now, $now, $status, $notes, $now]);

            setFlash('success', "🎉 New calling entry for '{$name}' recorded successfully.");
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
                   l.status, l.deal_value, l.callback_datetime, l.notes, u.name as executive_name, l.created_at
            FROM calling_leads l
            LEFT JOIN users u ON l.assigned_to = u.id
            ORDER BY l.id DESC
        ";
        $leads = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=BDA_Calling_Data_Export_' . date('Ymd_His') . '.csv');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Candidate Name', 'Phone', 'Email', 'City', 'Course/Service', 'Status', 'Deal Value', 'Follow-up Date', 'Notes/Remarks', 'Executive', 'Entry Date']);

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
                $r['created_at']
            ]);
        }
        fclose($out);
        exit;
    }
}