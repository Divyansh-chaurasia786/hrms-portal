<?php
// controllers/ProjectController.php

class ProjectController {
    public static function create(): void {
        requireRole(['admin', 'team_lead']);
        $user = authUser();
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $tlId = (int)($_POST['tl_id'] ?? $user['id']);
        $deadline = $_POST['deadline'] ?? null;

        if (empty($title)) {
            setFlash('error', 'Project title is required.');
        } else {
            $db = getDBConnection();
            $stmt = $db->prepare("INSERT INTO projects (title, description, tl_id, deadline) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $description, $tlId, $deadline]);
            setFlash('success', 'Project created successfully!');
        }

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=tl-dashboard'));
        exit;
    }
}
