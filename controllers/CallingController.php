<?php
// controllers/CallingController.php

class CallingController {
    
    // Caller's Live Calling Queue Screen
    public static function queue(): void {
        requireAuth();
        $user = authUser();
        $db = getDBConnection();
        $today = date('Y-m-d');

        $leads = $db->query("
            SELECT * FROM calling_leads 
            WHERE assigned_to = {$user['id']} 
            ORDER BY FIELD(status, 'new', 'call_later', 'interested', 'not_interested', 'converted'), id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $counts = [
            'total' => count($leads),
            'new' => 0,
            'interested' => 0,
            'call_later' => 0,
            'converted' => 0,
            'not_interested' => 0,
            'today_calls' => 0
        ];
        
        foreach ($leads as $l) {
            if (isset($counts[$l['status']])) $counts[$l['status']]++;
        }

        // Count calls made today by this user
        $counts['today_calls'] = (int)$db->query("SELECT COUNT(*) FROM call_logs WHERE caller_id = {$user['id']} AND call_date = '{$today}'")->fetchColumn();

        require __DIR__ . '/../views/calling/queue.php';
    }

    // TL & Admin CRM Management Screen
    public static function manage(): void {
        requireRole(['admin', 'team_lead']);
        $user = authUser();
        $db = getDBConnection();
        $today = date('Y-m-d');

        // Fetch all active BDA team members & callers
        $callers = $db->query("
            SELECT id, name, emp_id, designation, role 
            FROM users 
            WHERE status = 'active' AND (department_name = 'Calling / BDA Team' OR department_name = 'Calling / Sales' OR role = 'team_lead')
            ORDER BY name ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        // Overall Lead Pool Stats
        $stats = $db->query("
            SELECT 
                COUNT(*) as total_leads,
                COUNT(CASE WHEN status = 'new' THEN 1 END) as new_leads,
                COUNT(CASE WHEN status = 'interested' THEN 1 END) as interested_leads,
                COUNT(CASE WHEN status = 'call_later' THEN 1 END) as followup_leads,
                COUNT(CASE WHEN status = 'converted' THEN 1 END) as converted_leads,
                COUNT(CASE WHEN status = 'not_interested' THEN 1 END) as lost_leads
            FROM calling_leads
        ")->fetch(PDO::FETCH_ASSOC) ?: [];

        // Today's Calling Leaderboard / Activity per Employee
        $todayCallingStats = $db->query("
            SELECT 
                u.id, u.name, u.emp_id, u.designation,
                COUNT(cl.id) as today_calls,
                COUNT(CASE WHEN cl.disposition = 'converted' THEN 1 END) as today_converted,
                COUNT(CASE WHEN cl.disposition = 'interested' THEN 1 END) as today_interested,
                COUNT(CASE WHEN cl.disposition = 'call_later' THEN 1 END) as today_followup,
                (SELECT COUNT(*) FROM calling_leads WHERE assigned_to = u.id) as total_assigned_leads
            FROM users u
            LEFT JOIN call_logs cl ON cl.caller_id = u.id AND cl.call_date = '{$today}'
            WHERE u.status = 'active' AND (u.department_name = 'Calling / BDA Team' OR u.department_name = 'Calling / Sales' OR u.role = 'team_lead')
            GROUP BY u.id, u.name, u.emp_id, u.designation
            ORDER BY today_calls DESC, today_converted DESC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Recent Call Logs History (Last 100 calls)
        $recentCallLogs = $db->query("
            SELECT cl.*, u.name as caller_name, u.emp_id as caller_emp_id
            FROM call_logs cl
            JOIN users u ON cl.caller_id = u.id
            ORDER BY cl.id DESC
            LIMIT 100
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        require __DIR__ . '/../views/calling/manage.php';
    }

    // Upload & Auto-Distribute Lead Sheets (Only TL & Admin)
    public static function uploadLeads(): void {
        requireRole(['admin', 'team_lead']);
        $user = authUser();
        $db = getDBConnection();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['lead_file'])) {
            $file = $_FILES['lead_file']['tmp_name'];
            $selectedCallers = $_POST['callers'] ?? [];

            if (empty($file) || !file_exists($file)) {
                setFlash('error', 'Please upload a valid CSV or Excel lead sheet.');
                header('Location: ?page=calling-manage');
                exit;
            }

            // If no specific callers selected, select all active BDA members
            if (empty($selectedCallers)) {
                $selectedCallers = $db->query("SELECT id FROM users WHERE status = 'active' AND (department_name = 'Calling / BDA Team' OR department_name = 'Calling / Sales')")->fetchAll(PDO::FETCH_COLUMN);
            }

            if (empty($selectedCallers)) {
                // Fallback to current user
                $selectedCallers = [$user['id']];
            }

            $rows = [];
            if (($handle = fopen($file, "r")) !== FALSE) {
                // Read and detect delimiter/header
                $header = fgetcsv($handle, 2000, ",");
                while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {
                    $phone = preg_replace('/[^0-9]/', '', $data[1] ?? ($data[0] ?? ''));
                    $name = trim($data[0] ?? '');
                    if (empty($phone) && is_numeric($name)) {
                        $phone = $name;
                        $name = 'Prospect';
                    }
                    if (!empty($phone)) {
                        $rows[] = [
                            'name' => !empty($name) && !is_numeric($name) ? $name : 'Prospect ' . substr($phone, -4),
                            'phone' => $phone,
                            'city' => trim($data[2] ?? 'General'),
                            'notes' => trim($data[3] ?? '')
                        ];
                    }
                }
                fclose($handle);
            }

            if (empty($rows)) {
                setFlash('error', 'No valid phone numbers found in the uploaded file. Ensure column 1 has names and column 2 has phone numbers.');
                header('Location: ?page=calling-manage');
                exit;
            }

            $callerCount = count($selectedCallers);
            $stmt = $db->prepare("INSERT INTO calling_leads (lead_name, phone, city, notes, assigned_to, assigned_by, status) VALUES (?, ?, ?, ?, ?, ?, 'new')");

            foreach ($rows as $index => $r) {
                $assignedCallerId = $selectedCallers[$index % $callerCount];
                $stmt->execute([$r['name'], $r['phone'], $r['city'], $r['notes'], $assignedCallerId, $user['id']]);
            }

            $totalUploaded = count($rows);
            setFlash('success', "🎉 Successfully imported {$totalUploaded} numbers and distributed equally across {$callerCount} caller(s)!");
            header('Location: ?page=calling-manage');
            exit;
        }

        header('Location: ?page=calling-manage');
        exit;
    }

    // Save Call Record & Disposition
    public static function updateDisposition(): void {
        requireAuth();
        $user = authUser();
        $db = getDBConnection();

        $leadId = (int)($_POST['lead_id'] ?? 0);
        $status = trim($_POST['status'] ?? 'new');
        $notes = trim($_POST['notes'] ?? '');
        $followup = !empty($_POST['next_followup_at']) ? $_POST['next_followup_at'] : null;
        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');

        if ($leadId > 0) {
            $lead = $db->query("SELECT * FROM calling_leads WHERE id = {$leadId}")->fetch(PDO::FETCH_ASSOC);
            if ($lead) {
                // Update lead in calling_leads
                $stmt = $db->prepare("UPDATE calling_leads SET status = ?, notes = ?, next_followup_at = ?, last_called_at = ? WHERE id = ?");
                $stmt->execute([$status, $notes, $followup, $now, $leadId]);

                // Record in call_logs history table
                $logStmt = $db->prepare("
                    INSERT INTO call_logs (lead_id, caller_id, customer_name, phone, disposition, notes, call_time, call_date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $logStmt->execute([$leadId, $user['id'], $lead['lead_name'], $lead['phone'], $status, $notes, $now, $today]);

                echo json_encode(['success' => true, 'message' => 'Call logged successfully!']);
                exit;
            }
        }
        echo json_encode(['success' => false, 'error' => 'Invalid lead ID']);
        exit;
    }

    // Export Call History to Excel / CSV (ONLY Team Lead & Admin)
    public static function exportHistory(): void {
        requireRole(['admin', 'team_lead']);
        $db = getDBConnection();
        
        $filterDate = $_GET['date'] ?? '';
        $filterCaller = (int)($_GET['caller_id'] ?? 0);

        $sql = "
            SELECT 
                cl.id, cl.call_date, cl.call_time, 
                u.name as caller_name, u.emp_id as caller_emp_id,
                cl.customer_name, cl.phone, cl.disposition, cl.notes,
                l.city, l.next_followup_at
            FROM call_logs cl
            JOIN users u ON cl.caller_id = u.id
            LEFT JOIN calling_leads l ON cl.lead_id = l.id
            WHERE 1=1
        ";

        if (!empty($filterDate)) {
            $sql .= " AND cl.call_date = '$filterDate'";
        }
        if ($filterCaller > 0) {
            $sql .= " AND cl.caller_id = $filterCaller";
        }

        $sql .= " ORDER BY cl.id DESC";
        $logs = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $filename = "calling_history_export_" . date('Y_m_d_His') . ".csv";

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        // UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // CSV Header
        fputcsv($output, ['Log ID', 'Call Date', 'Call Time', 'Caller Employee', 'Caller Emp ID', 'Customer / Lead Name', 'Phone Number', 'City / Location', 'Call Disposition / Status', 'Call Notes & Feedback', 'Next Scheduled Follow-up']);

        foreach ($logs as $row) {
            fputcsv($output, [
                $row['id'],
                $row['call_date'],
                $row['call_time'],
                $row['caller_name'],
                $row['caller_emp_id'],
                $row['customer_name'],
                $row['phone'],
                $row['city'] ?: 'N/A',
                strtoupper(str_replace('_', ' ', $row['disposition'])),
                $row['notes'] ?: '-',
                $row['next_followup_at'] ?: 'None'
            ]);
        }

        fclose($output);
        exit;
    }
}