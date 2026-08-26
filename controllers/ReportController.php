<?php
// controllers/ReportController.php

class ReportController {
    public static function submitReport(): void {
        requireAuth();
        requireActiveShift();
        $user = authUser();
        $db = getDBConnection();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=dashboard'));
            exit;
        }

        $reportDate = trim($_POST['report_date'] ?? date('Y-m-d'));
        $title = trim($_POST['title'] ?? '');
        $tasksCompleted = trim($_POST['tasks_completed'] ?? '');
        $tasksInProgress = trim($_POST['tasks_in_progress'] ?? '');
        $blockers = trim($_POST['blockers'] ?? '');
        $planTomorrow = trim($_POST['plan_for_tomorrow'] ?? '');
        $attachmentUrl = trim($_POST['attachment_url'] ?? '');
        $hoursLogged = (float)($_POST['total_hours_logged'] ?? 0);

        if (empty($title) || empty($tasksCompleted)) {
            setFlash('error', 'Please provide a Report Title and Tasks Completed.');
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=dashboard'));
            exit;
        }

        // Determine destination recipient:
        // If Employee: send to their reporting TL (or Admin if none)
        // If Team Lead: send to HR Admin
        $submittedToId = null;
        if ($user['role'] === 'employee') {
            if (!empty($user['reporting_tl_id'])) {
                $submittedToId = (int)$user['reporting_tl_id'];
            } else {
                $adminId = $db->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn();
                $submittedToId = (int)$adminId;
            }
        } elseif ($user['role'] === 'team_lead') {
            $adminId = $db->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn();
            $submittedToId = (int)$adminId;
        } else {
            $submittedToId = (int)$user['id'];
        }

        // If Team Lead is submitting, automatically capture and attach all assigned team tasks of the day
        $tasksSnapshotJson = null;
        if ($user['role'] === 'team_lead') {
            $teamMembers = $db->query("SELECT id FROM users WHERE reporting_tl_id = {$user['id']} AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
            $inTeam = !empty($teamMembers) ? implode(',', array_map('intval', $teamMembers)) : '0';

            $tasksStmt = $db->query("
                SELECT t.id, t.title, t.priority, t.status, t.due_date, t.original_due_date, t.is_extended, t.extension_reason,
                       COALESCE(p.title, 'General') as project_name, u.name as employee_name, u.designation as employee_designation,
                       (SELECT ts.notes FROM task_submissions ts WHERE ts.task_id = t.id ORDER BY ts.submitted_at DESC LIMIT 1) as latest_employee_remarks,
                       (SELECT ts.attachment_file FROM task_submissions ts WHERE ts.task_id = t.id ORDER BY ts.submitted_at DESC LIMIT 1) as latest_attachment_file,
                       (SELECT ts.attachment_type FROM task_submissions ts WHERE ts.task_id = t.id ORDER BY ts.submitted_at DESC LIMIT 1) as latest_attachment_type,
                       (SELECT ts.attachment_url FROM task_submissions ts WHERE ts.task_id = t.id ORDER BY ts.submitted_at DESC LIMIT 1) as latest_attachment_url,
                       (SELECT ts.submitted_at FROM task_submissions ts WHERE ts.task_id = t.id ORDER BY ts.submitted_at DESC LIMIT 1) as latest_submission_time
                FROM tasks t
                LEFT JOIN projects p ON t.project_id = p.id
                JOIN users u ON t.assigned_to = u.id
                WHERE t.assigned_to IN ($inTeam) OR t.created_by = {$user['id']}
                ORDER BY CASE t.status WHEN 'review' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'completed' THEN 3 ELSE 4 END, t.due_date ASC
            ");
            $taskList = $tasksStmt ? $tasksStmt->fetchAll(PDO::FETCH_ASSOC) : [];
            $tasksSnapshotJson = json_encode($taskList);
        }

        // Insert report
        $stmt = $db->prepare("
            INSERT INTO daily_work_reports 
            (user_id, user_role, submitted_to_id, report_date, title, tasks_completed, tasks_in_progress, blockers, plan_for_tomorrow, total_hours_logged, attachment_url, tasks_snapshot_json, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted')
        ");
        $stmt->execute([
            $user['id'],
            $user['role'],
            $submittedToId,
            $reportDate,
            $title,
            $tasksCompleted,
            $tasksInProgress,
            $blockers,
            $planTomorrow,
            $hoursLogged,
            $attachmentUrl,
            $tasksSnapshotJson
        ]);

        if ($user['role'] === 'team_lead') {
            setFlash('success', "📤 Daily Team Executive Report for {$reportDate} successfully transmitted to HR with auto-attached task deliverables!");
        } else {
            setFlash('success', "📝 Daily Work Report (DSR) for {$reportDate} successfully submitted to your Team Lead!");
        }

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=dashboard'));
        exit;
    }

    public static function reviewReport(): void {
        requireAuth();
        requireRole(['team_lead', 'admin']);
        requireActiveShift();
        $user = authUser();
        $db = getDBConnection();

        $reportId = (int)($_POST['report_id'] ?? 0);
        $status = trim($_POST['status'] ?? 'reviewed');
        $feedback = trim($_POST['reviewer_feedback'] ?? '');

        if ($reportId <= 0) {
            setFlash('error', 'Invalid report selected.');
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=dashboard'));
            exit;
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare("
            UPDATE daily_work_reports 
            SET status = ?, reviewer_feedback = ?, reviewed_by = ?, reviewed_at = ?
            WHERE id = ?
        ");
        $stmt->execute([$status, $feedback, $user['id'], $now, $reportId]);

        setFlash('success', '? Report review & feedback saved successfully!');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=dashboard'));
        exit;
    }
}
