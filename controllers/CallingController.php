<?php
// controllers/CallingController.php

class CallingController {

    public static function initiateCall(): void {
        requireAuth();
        header('Content-Type: application/json');
        $user = authUser();
        $leadId = (int)($_POST['lead_id'] ?? 0);
        $phone = trim($_POST['phone'] ?? '');

        if ($leadId <= 0 && empty($phone)) {
            echo json_encode(['success' => false, 'message' => 'Invalid lead or phone number.']);
            exit;
        }

        $db = getDBConnection();
        $lead = null;
        if ($leadId > 0) {
            $stmt = $db->prepare("SELECT * FROM calling_leads WHERE id = ?");
            $stmt->execute([$leadId]);
            $lead = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        $callSessionToken = bin2hex(random_bytes(16));
        $now = date('Y-m-d H:i:s');

        echo json_encode([
            'success' => true,
            'call_token' => $callSessionToken,
            'lead' => $lead,
            'phone' => $phone ?: ($lead['phone'] ?? ''),
            'lead_name' => $lead['lead_name'] ?? 'Direct Contact',
            'started_at' => $now,
            'message' => 'Connecting call...'
        ]);
        exit;
    }

    public static function inviteConference(): void {
        requireAuth();
        header('Content-Type: application/json');
        $user = authUser();
        $leadId = (int)($_POST['lead_id'] ?? 0);
        $participantId = (int)($_POST['participant_id'] ?? 0);

        $db = getDBConnection();
        $participant = $db->query("SELECT id, name, designation, phone FROM users WHERE id = {$participantId}")->fetch(PDO::FETCH_ASSOC);

        if (!$participant) {
            echo json_encode(['success' => false, 'message' => 'Participant not found.']);
            exit;
        }

        if ($leadId > 0) {
            $db->prepare("UPDATE calling_leads SET conference_active = 1 WHERE id = ?")->execute([$leadId]);
        }

        echo json_encode([
            'success' => true,
            'participant' => $participant,
            'message' => "Conference call bridged with {$participant['name']} ({$participant['designation']})"
        ]);
        exit;
    }

    public static function saveDisposition(): void {
        requireAuth();
        $user = authUser();
        $db = getDBConnection();

        $leadId = (int)($_POST['lead_id'] ?? 0);
        $phone = trim($_POST['phone'] ?? '');
        $customerName = trim($_POST['customer_name'] ?? 'Direct Lead');
        $disposition = trim($_POST['disposition'] ?? 'connected');
        $notes = trim($_POST['notes'] ?? '');
        $followUpDate = !empty($_POST['follow_up_date']) ? trim($_POST['follow_up_date']) : null;
        $callDuration = (int)($_POST['call_duration_seconds'] ?? 0);
        $isConference = !empty($_POST['is_conference']) ? 1 : 0;
        $dealValue = (float)($_POST['deal_value'] ?? 0.00);

        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');

        // 1. Map disposition to lead status
        $statusMap = [
            'converted' => 'converted',
            'interested' => 'interested',
            'call_later' => 'call_later',
            'callback' => 'call_later',
            'not_interested' => 'not_interested',
            'wrong_number' => 'not_interested',
            'ringing_no_answer' => 'new',
            'busy' => 'call_later'
        ];
        $leadStatus = $statusMap[$disposition] ?? 'interested';

        // 2. Update Lead if exists
        if ($leadId > 0) {
            $stmt = $db->prepare("
                UPDATE calling_leads SET 
                    status = ?, 
                    follow_up_date = ?, 
                    callback_datetime = ?, 
                    notes = CONCAT(COALESCE(notes, ''), '\n[', ?, '] ', ?),
                    deal_value = CASE WHEN ? > 0 THEN ? ELSE deal_value END,
                    conference_active = 0
                WHERE id = ?
            ");
            $stmt->execute([
                $leadStatus,
                $followUpDate,
                $followUpDate ? "{$followUpDate} 12:00:00" : null,
                date('d M h:i A'),
                $notes ?: "Call disposition: {$disposition}",
                $dealValue,
                $dealValue,
                $leadId
            ]);
        }

        // 3. Insert Call Log
        $logStmt = $db->prepare("
            INSERT INTO call_logs (
                lead_id, caller_id, phone, customer_name, disposition, 
                notes, call_date, call_time, call_duration_seconds, 
                call_type, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $logStmt->execute([
            $leadId > 0 ? $leadId : null,
            $user['id'],
            $phone,
            $customerName,
            $disposition,
            $notes,
            $today,
            date('H:i:s'),
            $callDuration,
            $isConference ? 'conference' : 'outbound',
            $now
        ]);

        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Call logged successfully!']);
            exit;
        }

        setFlash('success', '📞 Call disposition & notes recorded successfully!');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=calling-queue'));
        exit;
    }

    public static function bulkUploadLeads(): void {
        requireAuth();
        $user = authUser();
        $db = getDBConnection();

        $sheetTitle = trim($_POST['campaign_title'] ?? 'Campaign ' . date('d M Y'));
        $autoAllocate = !empty($_POST['auto_allocate']) ? 1 : 0;
        $targetCallerId = (int)($_POST['target_caller_id'] ?? 0);

        if (empty($_FILES['lead_file']['tmp_name'])) {
            setFlash('error', 'Please upload a valid .csv or .xlsx file.');
            header('Location: ?page=calling-manage');
            exit;
        }

        $filePath = $_FILES['lead_file']['tmp_name'];
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            setFlash('error', 'Could not read uploaded file.');
            header('Location: ?page=calling-manage');
            exit;
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            setFlash('error', 'Uploaded sheet is empty.');
            header('Location: ?page=calling-manage');
            exit;
        }

        // Normalize header keys
        $headerMap = [];
        foreach ($headers as $idx => $h) {
            $clean = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $h)));
            $headerMap[$clean] = $idx;
        }

        // Identify key columns
        $nameCol = $headerMap['customername'] ?? ($headerMap['leadname'] ?? ($headerMap['name'] ?? 0));
        $phoneCol = $headerMap['phonenumber'] ?? ($headerMap['phone'] ?? ($headerMap['mobile'] ?? 1));
        $emailCol = $headerMap['emailaddress'] ?? ($headerMap['email'] ?? null);
        $cityCol = $headerMap['city'] ?? ($headerMap['location'] ?? null);
        $courseCol = $headerMap['interestedcourseservice'] ?? ($headerMap['course'] ?? ($headerMap['service'] ?? null));
        $sourceCol = $headerMap['leadsource'] ?? ($headerMap['source'] ?? null);
        $budgetCol = $headerMap['budgetinr'] ?? ($headerMap['budget'] ?? null);
        $notesCol = $headerMap['callingnotes'] ?? ($headerMap['notes'] ?? null);

        // Fetch active BDA team members for Round-Robin allocation
        $bdaCallers = $db->query("
            SELECT id FROM users 
            WHERE status = 'active' AND role = 'employee' 
              AND (department_name = 'Business Development' OR department_name LIKE '%Calling%' OR designation LIKE '%BDA%')
            ORDER BY id ASC
        ")->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $callerCount = count($bdaCallers);
        $callerIdx = 0;
        $insertedCount = 0;

        $stmt = $db->prepare("
            INSERT INTO calling_leads (
                lead_name, phone, email, city, course_service, budget, 
                lead_source, status, priority, notes, assigned_to, assigned_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'new', 'warm', ?, ?, ?, NOW())
        ");

        while (($row = fgetcsv($handle)) !== false) {
            if (empty($row) || !isset($row[$phoneCol]) || empty(trim($row[$phoneCol]))) continue;

            $leadName = isset($row[$nameCol]) ? trim($row[$nameCol]) : 'Lead ' . ($insertedCount + 1);
            $phone = preg_replace('/[^0-9+]/', '', trim($row[$phoneCol]));
            $email = ($emailCol !== null && isset($row[$emailCol])) ? trim($row[$emailCol]) : null;
            $city = ($cityCol !== null && isset($row[$cityCol])) ? trim($row[$cityCol]) : 'General';
            $course = ($courseCol !== null && isset($row[$courseCol])) ? trim($row[$courseCol]) : 'Corporate Suite';
            $source = ($sourceCol !== null && isset($row[$sourceCol])) ? trim($row[$sourceCol]) : 'Sheet Upload';
            $budget = ($budgetCol !== null && isset($row[$budgetCol])) ? (float)preg_replace('/[^0-9.]/', '', $row[$budgetCol]) : 0.00;
            $notes = ($notesCol !== null && isset($row[$notesCol])) ? trim($row[$notesCol]) : '';

            $assignedTo = null;
            if ($targetCallerId > 0) {
                $assignedTo = $targetCallerId;
            } elseif ($autoAllocate && $callerCount > 0) {
                $assignedTo = $bdaCallers[$callerIdx % $callerCount];
                $callerIdx++;
            }

            $stmt->execute([
                $leadName,
                $phone,
                $email,
                $city,
                $course,
                $budget,
                $source,
                $notes,
                $assignedTo,
                $user['id']
            ]);
            $insertedCount++;
        }
        fclose($handle);

        setFlash('success', "🎉 Successfully ingested {$insertedCount} BDA leads!" . ($autoAllocate ? " Distributed across {$callerCount} active callers." : ""));
        header('Location: ?page=calling-manage');
        exit;
    }

    public static function allocateRoundRobin(): void {
        requireAuth();
        $user = authUser();
        $db = getDBConnection();

        $leadIds = $_POST['lead_ids'] ?? [];
        if (empty($leadIds)) {
            // Allocate all unassigned leads
            $leadIds = $db->query("SELECT id FROM calling_leads WHERE assigned_to IS NULL ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
        }

        if (empty($leadIds)) {
            setFlash('error', 'No unassigned leads found in pipeline.');
            header('Location: ?page=calling-manage');
            exit;
        }

        $bdaCallers = $db->query("
            SELECT id FROM users 
            WHERE status = 'active' AND role = 'employee' 
              AND (department_name = 'Business Development' OR department_name LIKE '%Calling%' OR designation LIKE '%BDA%')
            ORDER BY id ASC
        ")->fetchAll(PDO::FETCH_COLUMN) ?: [];

        if (empty($bdaCallers)) {
            setFlash('error', 'No active BDA executives available for allocation.');
            header('Location: ?page=calling-manage');
            exit;
        }

        $callerCount = count($bdaCallers);
        $stmt = $db->prepare("UPDATE calling_leads SET assigned_to = ?, assigned_by = ? WHERE id = ?");

        $allocatedCount = 0;
        foreach ($leadIds as $idx => $lid) {
            $callerId = $bdaCallers[$idx % $callerCount];
            $stmt->execute([$callerId, $user['id'], (int)$lid]);
            $allocatedCount++;
        }

        setFlash('success', "⚖️ Round-Robin Allocation Complete: Distributed {$allocatedCount} leads equally across {$callerCount} BDA executives.");
        header('Location: ?page=calling-manage');
        exit;
    }

    public static function exportCallingHistory(): void {
        requireAuth();
        $user = authUser();
        $db = getDBConnection();

        $logs = $db->query("
            SELECT 
                cl.id as log_id,
                cl.call_date,
                cl.call_time,
                u.name as bda_executive_name,
                u.emp_id as executive_emp_id,
                cl.customer_name,
                cl.phone,
                l.city,
                l.course_service,
                l.budget,
                cl.disposition,
                cl.call_duration_seconds,
                cl.call_type,
                cl.notes
            FROM call_logs cl
            JOIN users u ON cl.caller_id = u.id
            LEFT JOIN calling_leads l ON cl.lead_id = l.id
            ORDER BY cl.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
        $xml .= '<Styles><Style ss:ID="H"><Font ss:Bold="1" ss:Color="#FFFFFF" ss:FontName="Segoe UI"/><Interior ss:Color="#107C41" ss:Pattern="Solid"/></Style></Styles>' . "\n";
        $xml .= '<Worksheet ss:Name="BDA_Call_Logs"><Table ss:DefaultRowHeight="20">' . "\n";

        if (!empty($logs)) {
            $xml .= '<Row ss:Height="24">' . "\n";
            foreach (array_keys($logs[0]) as $h) {
                $xml .= '<Cell ss:StyleID="H"><Data ss:Type="String">' . htmlspecialchars(ucwords(str_replace('_', ' ', $h))) . '</Data></Cell>' . "\n";
            }
            $xml .= '</Row>' . "\n";

            foreach ($logs as $r) {
                $xml .= '<Row>' . "\n";
                foreach ($r as $v) {
                    $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars((string)$v) . '</Data></Cell>' . "\n";
                }
                $xml .= '</Row>' . "\n";
            }
        }

        $xml .= '</Table></Worksheet></Workbook>';

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="BDA_Telecalling_Report_' . date('Y_m_d_His') . '.xls"');
        echo $xml;
        exit;
    }
}