<?php
// controllers/TaskController.php

class TaskController {
    public static function create(): void {
        requireRole(['admin', 'team_lead']);
        $user = authUser();

        $projectId = (int)($_POST['project_id'] ?? 0);
        $assignedTo = (int)($_POST['assigned_to'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';
        $dueDate = $_POST['due_date'] ?? null;
        $estHours = (float)($_POST['estimated_hours'] ?? 0);

        if (empty($title) || $assignedTo <= 0 || $projectId <= 0) {
            setFlash('error', 'Please fill in all required task fields.');
        } else {
            $db = getDBConnection();
            $stmt = $db->prepare("INSERT INTO tasks (project_id, assigned_to, created_by, title, description, priority, status, due_date, estimated_hours) VALUES (?, ?, ?, ?, ?, ?, 'todo', ?, ?)");
            $stmt->execute([$projectId, $assignedTo, $user['id'], $title, $description, $priority, $dueDate, $estHours]);
            setFlash('success', 'Task created and assigned successfully!');
        }

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=tl-tasks'));
        exit;
    }

    public static function updateStatus(): void {
        requireAuth();
        $user = authUser();
        $taskId = (int)($_POST['task_id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';

        $allowed = ['todo', 'in_progress', 'review', 'completed'];
        if (!in_array($newStatus, $allowed, true)) {
            setFlash('error', 'Invalid task status.');
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=dashboard'));
            exit;
        }

        $db = getDBConnection();
        // Check permissions
        if (isAdmin() || isTL()) {
            $stmt = $db->prepare("UPDATE tasks SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $taskId]);
            setFlash('success', 'Task status updated to ' . ucfirst(str_replace('_', ' ', $newStatus)));
        } else {
            // Employee can only update their own assigned tasks
            $stmt = $db->prepare("UPDATE tasks SET status = ? WHERE id = ? AND assigned_to = ?");
            $stmt->execute([$newStatus, $taskId, $user['id']]);
            setFlash('success', 'Task progress updated!');
        }

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=employee-tasks'));
        exit;
    }

    public static function submitWork(): void {
        requireRole(['employee']);
        $user = authUser();
        $taskId = (int)($_POST['task_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        $attachmentUrl = trim($_POST['attachment_url'] ?? '');
        $attachmentFile = null;
        $attachmentType = !empty($attachmentUrl) ? 'link' : null;

        // Handle uploaded photo, video, or deliverable file
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT reporting_tl_id FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $tlId = (int)$stmt->fetchColumn() ?: 30010;

        $stmt = $db->prepare("SELECT is_connected FROM drive_settings WHERE team_lead_id = ?");
        $stmt->execute([$tlId]);
        $driveRow = $stmt->fetch();
        $isDriveActive = !empty($driveRow['is_connected']);

        if (isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] === UPLOAD_ERR_OK && !empty($_FILES['attachment_file']['name'])) {
            if (!$isDriveActive) {
                $tlName = $db->query("SELECT name FROM users WHERE id = {$tlId}")->fetchColumn() ?: 'Team Lead';
                setFlash('error', "⚠️ Photo/Video/File uploads are disabled because your Team Lead ({$tlName}) has not connected the Team Cloud Drive yet. Please submit your report as text only, or approach your Team Lead to link Google Drive.");
                header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=employee-tasks'));
                exit;
            }

            $file = $_FILES['attachment_file'];
            $maxBytes = 50 * 1024 * 1024; // 50MB max for videos/photos
            
            if ($file['size'] > $maxBytes) {
                setFlash('error', 'Uploaded file exceeds the maximum 50MB limit.');
                header('Location: ?page=employee-tasks');
                exit;
            }

            $origName = $file['name'];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'webm', 'mov', 'mkv', 'avi', 'pdf', 'zip', 'rar', 'doc', 'docx', 'txt'];

            if (!in_array($ext, $allowedExts, true)) {
                setFlash('error', "Invalid file type '.{$ext}'. Allowed formats: Photos (PNG/JPG/GIF), Videos (MP4/WEBM/MOV), and Documents (PDF/ZIP).");
                header('Location: ?page=employee-tasks');
                exit;
            }

            $uploadDir = __DIR__ . '/../public/uploads/task_submissions';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $safeFileName = 'task_' . $taskId . '_u' . $user['id'] . '_' . time() . '_' . substr(md5(uniqid()), 0, 6) . '.' . $ext;
            $targetPath = $uploadDir . '/' . $safeFileName;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $attachmentFile = 'uploads/task_submissions/' . $safeFileName;
                
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                    $attachmentType = 'image';
                } elseif (in_array($ext, ['mp4', 'webm', 'mov', 'mkv', 'avi'], true)) {
                    $attachmentType = 'video';
                } else {
                    $attachmentType = 'file';
                }
            }
        }

        if (empty($notes) && empty($attachmentUrl) && empty($attachmentFile)) {
            setFlash('error', 'Please provide work submission notes, deliverable links, or upload a photo/video proof.');
            header('Location: ?page=employee-tasks');
            exit;
        }

        $db = getDBConnection();
        // Verify assignment
        $check = $db->prepare("SELECT id, title FROM tasks WHERE id = ? AND assigned_to = ?");
        $check->execute([$taskId, $user['id']]);
        $taskRow = $check->fetch();
        if (!$taskRow) {
            setFlash('error', 'Unauthorized task submission.');
            header('Location: ?page=employee-tasks');
            exit;
        }

        // Insert submission record with rich media attachments
        $sub = $db->prepare("
            INSERT INTO task_submissions 
            (task_id, submitted_by, notes, attachment_url, attachment_file, attachment_type, review_status) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        $sub->execute([$taskId, $user['id'], $notes, $attachmentUrl, $attachmentFile, $attachmentType]);

        // Auto-catalog file attachment into Tech Cloud Drive under Daily Tasks / [YYYY-MM-DD] / [Images|Videos|Documents]
        if (!empty($attachmentFile)) {
            require_once __DIR__ . '/DriveController.php';
            DriveController::storeDailyTaskSubmission(
                (int)$user['id'],
                (string)$user['name'],
                (string)$taskRow['title'],
                $taskId,
                $attachmentFile,
                $attachmentType,
                (int)($file['size'] ?? 0),
                $ext
            );
        }

        // Move task to 'review' status
        $update = $db->prepare("UPDATE tasks SET status = 'review' WHERE id = ?");
        $update->execute([$taskId]);

        setFlash('success', '🚀 Deliverable submitted successfully! File proof automatically archived to Tech Cloud Drive (Daily Tasks).');
        header('Location: ?page=employee-tasks');
        exit;
    }

    public static function reviewSubmission(): void {
        requireRole(['admin', 'team_lead']);
        $submissionId = (int)($_POST['submission_id'] ?? 0);
        $action = $_POST['action'] ?? ''; // approve or reject
        $feedback = trim($_POST['tl_feedback'] ?? '');

        $db = getDBConnection();
        $stmt = $db->prepare("SELECT ts.*, t.id as task_id FROM task_submissions ts JOIN tasks t ON ts.task_id = t.id WHERE ts.id = ?");
        $stmt->execute([$submissionId]);
        $sub = $stmt->fetch();

        if (!$sub) {
            setFlash('error', 'Submission not found.');
            header('Location: ?page=tl-tasks');
            exit;
        }

        if ($action === 'approve') {
            $upSub = $db->prepare("UPDATE task_submissions SET review_status = 'approved', tl_feedback = ? WHERE id = ?");
            $upSub->execute([$feedback, $submissionId]);

            $upTask = $db->prepare("UPDATE tasks SET status = 'completed' WHERE id = ?");
            $upTask->execute([$sub['task_id']]);
            setFlash('success', 'Work submission approved and marked as Completed!');
        } else {
            $upSub = $db->prepare("UPDATE task_submissions SET review_status = 'changes_requested', tl_feedback = ? WHERE id = ?");
            $upSub->execute([$feedback, $submissionId]);

            $upTask = $db->prepare("UPDATE tasks SET status = 'in_progress' WHERE id = ?");
            $upTask->execute([$sub['task_id']]);
            setFlash('warning', 'Requested changes on submission and returned task to In Progress.');
        }

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=tl-tasks'));
        exit;
    }

    public static function extendDeadline(): void {
        requireRole(['admin', 'team_lead']);
        $taskId = (int)($_POST['task_id'] ?? 0);
        $newDueDate = $_POST['new_due_date'] ?? null;
        $reason = trim($_POST['extension_reason'] ?? '');

        if ($taskId <= 0 || empty($newDueDate)) {
            setFlash('error', 'Valid new due date is required.');
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=tl-tasks'));
            exit;
        }

        $db = getDBConnection();
        $currTask = $db->query("SELECT due_date, original_due_date FROM tasks WHERE id = $taskId")->fetch();
        $orig = $currTask['original_due_date'] ?: ($currTask['due_date'] ?: date('Y-m-d'));

        $stmt = $db->prepare("UPDATE tasks SET original_due_date = ?, due_date = ?, is_extended = 1, extension_reason = ? WHERE id = ?");
        $stmt->execute([$orig, $newDueDate, $reason, $taskId]);

        setFlash('success', 'Task deadline extended to ' . formatDate($newDueDate) . ($reason ? " (Reason: $reason)" : ''));
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=tl-tasks'));
        exit;
    }

    /**
     * Automatically submit any unfinished tasks for employee, or daily executive report for TL on logout/punch-out
     */
    public static function autoSubmitOnShiftEnd(int $userId): void {
        if ($userId <= 0) return;
        try {
            $db = getDBConnection();
            $userStmt = $db->prepare("SELECT id, name, role, reporting_tl_id FROM users WHERE id = ?");
            $userStmt->execute([$userId]);
            $u = $userStmt->fetch();
            if (!$u) return;

            $today = date('Y-m-d');
            $now = date('Y-m-d H:i:s');

            // 1. AUTO-SUBMIT EMPLOYEE / USER PENDING TASKS
            // Find all tasks assigned to user that are currently 'in_progress' or 'todo'
            $tasksStmt = $db->prepare("SELECT id, title, project_id, status FROM tasks WHERE assigned_to = ? AND status IN ('todo', 'in_progress')");
            $tasksStmt->execute([$userId]);
            $pendingTasks = $tasksStmt->fetchAll();

            foreach ($pendingTasks as $pt) {
                // Check if there is already an unreviewed submission today
                $subCheck = $db->prepare("
                    SELECT id FROM task_submissions 
                    WHERE task_id = ? AND submitted_by = ? AND submitted_at LIKE ? AND review_status = 'pending'
                ");
                $subCheck->execute([$pt['id'], $userId, "$today%"]);
                if (!$subCheck->fetch()) {
                    $subStmt = $db->prepare("
                        INSERT INTO task_submissions 
                        (task_id, submitted_by, notes, review_status, is_auto_submitted, submitted_at)
                        VALUES (?, ?, ?, 'pending', 1, ?)
                    ");
                    $autoNote = "[AUTO-SUBMITTED ON LOGOUT/PUNCH-OUT] Shift ended. Task progress auto-captured and moved to review.";
                    $subStmt->execute([$pt['id'], $userId, $autoNote, $now]);

                    // Update task status to review
                    $db->prepare("UPDATE tasks SET status = 'review' WHERE id = ?")->execute([$pt['id']]);
                }
            }

            // 2. AUTO-SUBMIT DAILY EXECUTIVE REPORT FOR TEAM LEAD IF FORGOTTEN
            if ($u['role'] === 'team_lead') {
                $repCheck = $db->prepare("SELECT id FROM daily_work_reports WHERE user_id = ? AND report_date = ?");
                $repCheck->execute([$userId, $today]);
                $existingRep = $repCheck->fetch();

                if (!$existingRep) {
                    // Team Lead forgot to submit their daily report: auto-compile & submit to HR!
                    $adminId = (int)$db->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn();
                    $teamMembers = $db->query("SELECT id FROM users WHERE reporting_tl_id = {$userId} AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
                    $inTeam = !empty($teamMembers) ? implode(',', array_map('intval', $teamMembers)) : '0';

                    // Fetch team sprint snapshot
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
                        WHERE t.assigned_to IN ($inTeam) OR t.created_by = {$userId}
                        ORDER BY CASE t.status WHEN 'review' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'completed' THEN 3 ELSE 4 END, t.due_date ASC
                    ");
                    $taskList = $tasksStmt ? $tasksStmt->fetchAll(PDO::FETCH_ASSOC) : [];
                    $tasksSnapshotJson = json_encode($taskList);

                    $completedCount = count(array_filter($taskList, fn($t) => $t['status'] === 'completed'));
                    $inProgCount = count(array_filter($taskList, fn($t) => $t['status'] === 'in_progress' || $t['status'] === 'review'));

                    $autoTitle = "[AUTO-SUBMITTED ON LOGOUT] Daily Sprint Progress & Deliverables Summary - " . date('d M Y');
                    $autoCompleted = "Auto-compiled on shift logout: {$completedCount} completed deliverable(s) recorded for today.";
                    $autoInProgress = "Ongoing Sprints: {$inProgCount} task(s) currently active in progress / review.";
                    $autoBlockers = "No active blockers reported.";
                    $autoPlan = "Continue backlog sprint goals and review pending task deliverables.";

                    $insRep = $db->prepare("
                        INSERT INTO daily_work_reports 
                        (user_id, user_role, submitted_to_id, report_date, title, tasks_completed, tasks_in_progress, blockers, plan_for_tomorrow, total_hours_logged, tasks_snapshot_json, status, is_auto_submitted)
                        VALUES (?, 'team_lead', ?, ?, ?, ?, ?, ?, ?, 8, ?, 'submitted', 1)
                    ");
                    $insRep->execute([
                        $userId,
                        $adminId,
                        $today,
                        $autoTitle,
                        $autoCompleted,
                        $autoInProgress,
                        $autoBlockers,
                        $autoPlan,
                        $tasksSnapshotJson
                    ]);
                }
            }
        } catch (Exception $e) {
            // Silently log or continue to ensure logout/punchout never crashes
            error_log("Auto-submit error: " . $e->getMessage());
        }
    }
}
