<?php
// controllers/CallingController.php

class CallingController {
    public static function queue(): void {
        $user = authUser();
        $db = getDBConnection();

        $leads = $db->query("SELECT * FROM calling_leads WHERE assigned_to = {$user['id']} ORDER BY FIELD(status, 'new', 'call_later', 'interested', 'not_interested', 'converted'), id DESC")->fetchAll(PDO::FETCH_ASSOC);

        $counts = [
            'total' => count($leads),
            'new' => 0,
            'interested' => 0,
            'call_later' => 0,
            'converted' => 0
        ];
        foreach ($leads as $l) {
            if (isset($counts[$l['status']])) $counts[$l['status']]++;
        }

        require __DIR__ . '/../views/calling/queue.php';
    }

    public static function manage(): void {
        requireAuth(['admin', 'team_lead']);
        $db = getDBConnection();

        $callers = $db->query("SELECT id, name, emp_id, designation FROM users WHERE department_name = 'Calling / Sales' OR role IN ('employee', 'team_lead') ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        $stats = $db->query("
            SELECT u.name, u.emp_id, 
                   COUNT(c.id) as total_leads,
                   SUM(CASE WHEN c.status = 'converted' THEN 1 ELSE 0 END) as converted,
                   SUM(CASE WHEN c.status = 'interested' THEN 1 ELSE 0 END) as interested,
                   SUM(CASE WHEN c.last_called_at IS NOT NULL THEN 1 ELSE 0 END) as called_count
            FROM users u
            LEFT JOIN calling_leads c ON u.id = c.assigned_to
            WHERE u.department_name = 'Calling / Sales' OR u.role = 'team_lead'
            GROUP BY u.id
        ")->fetchAll(PDO::FETCH_ASSOC);

        require __DIR__ . '/../views/calling/manage.php';
    }

    public static function uploadLeads(): void {
        requireAuth(['admin', 'team_lead']);
        $user = authUser();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['lead_sheet'])) {
            $file = $_FILES['lead_sheet']['tmp_name'];
            $selectedCallers = $_POST['callers'] ?? [];

            if (empty($file) || !file_exists($file)) {
                setFlash('error', 'Please upload a valid CSV or Excel lead sheet.');
                header('Location: ?page=calling-manage');
                exit;
            }

            if (empty($selectedCallers)) {
                setFlash('error', 'Please select at least 1 caller to distribute leads to.');
                header('Location: ?page=calling-manage');
                exit;
            }

            $rows = [];
            if (($handle = fopen($file, "r")) !== FALSE) {
                $header = fgetcsv($handle, 1000, ",");
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (!empty($data[0]) || !empty($data[1])) {
                        $rows[] = [
                            'name' => trim($data[0] ?? 'Prospect'),
                            'phone' => trim($data[1] ?? ''),
                            'city' => trim($data[2] ?? 'General'),
                            'notes' => trim($data[3] ?? '')
                        ];
                    }
                }
                fclose($handle);
            }

            if (empty($rows)) {
                setFlash('error', 'No valid rows found in the uploaded file.');
                header('Location: ?page=calling-manage');
                exit;
            }

            $db = getDBConnection();
            $callerCount = count($selectedCallers);
            $stmt = $db->prepare("INSERT INTO calling_leads (lead_name, phone, city, notes, assigned_to, assigned_by) VALUES (?, ?, ?, ?, ?, ?)");

            foreach ($rows as $index => $r) {
                if (empty($r['phone'])) continue;
                $assignedCallerId = $selectedCallers[$index % $callerCount];
                $stmt->execute([$r['name'], $r['phone'], $r['city'], $r['notes'], $assignedCallerId, $user['id']]);
            }

            setFlash('success', "🎉 Successfully distributed " . count($rows) . " leads equally across {$callerCount} caller(s)!");
            header('Location: ?page=calling-manage');
            exit;
        }
    }

    public static function updateDisposition(): void {
        $user = authUser();
        $leadId = (int)($_POST['lead_id'] ?? 0);
        $status = trim($_POST['status'] ?? 'new');
        $notes = trim($_POST['notes'] ?? '');
        $followup = !empty($_POST['next_followup_at']) ? $_POST['next_followup_at'] : null;

        if ($leadId > 0) {
            $db = getDBConnection();
            $stmt = $db->prepare("UPDATE calling_leads SET status = ?, notes = ?, next_followup_at = ?, last_called_at = NOW() WHERE id = ? AND assigned_to = ?");
            $stmt->execute([$status, $notes, $followup, $leadId, $user['id']]);
            echo json_encode(['success' => true]);
            exit;
        }
        echo json_encode(['success' => false]);
        exit;
    }
}