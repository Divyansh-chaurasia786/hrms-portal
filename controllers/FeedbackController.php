<?php
// controllers/FeedbackController.php

class FeedbackController {
    public static function postTLFeedback(): void {
        requireRole(['admin']);
        $user = authUser();
        $tlId = (int)($_POST['tl_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        $priority = $_POST['priority'] ?? 'normal';

        if ($tlId <= 0 || empty($message)) {
            setFlash('error', 'Please select a Team Lead and write feedback text.');
        } else {
            $db = getDBConnection();
            $stmt = $db->prepare("INSERT INTO tl_feedbacks (tl_id, hr_id, message, priority, status) VALUES (?, ?, ?, ?, 'unread')");
            $stmt->execute([$tlId, $user['id'], $message, $priority]);
            setFlash('success', 'Official HR feedback dispatched to the Team Lead!');
        }

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=admin-tl-reports'));
        exit;
    }

    public static function acknowledgeFeedback(): void {
        requireRole(['team_lead', 'admin']);
        $feedbackId = (int)($_POST['feedback_id'] ?? 0);

        if ($feedbackId > 0) {
            $db = getDBConnection();
            $stmt = $db->prepare("UPDATE tl_feedbacks SET status = 'acknowledged' WHERE id = ?");
            $stmt->execute([$feedbackId]);
            setFlash('success', 'HR feedback marked as Acknowledged.');
        }

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=tl-dashboard'));
        exit;
    }
}
