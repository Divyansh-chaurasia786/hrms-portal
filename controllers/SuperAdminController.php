<?php
// controllers/SuperAdminController.php

class SuperAdminController {

    public static function index(): void {
        requireSuperAdmin();
        $db = getDBConnection();
        $user = authUser();

        // Fetch current rollout settings
        $settingsRaw = $db->query("SELECT * FROM system_rollout_settings")->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        $rolloutMode = $settingsRaw['rollout_mode'] ?? 'it_testing';
        $allowedDepts = json_decode($settingsRaw['allowed_departments'] ?? '["Information Technology","IT"]', true) ?: [];
        $unlockedFeatures = json_decode($settingsRaw['unlocked_features'] ?? '{"punch":true,"gps_radar":true,"bda_crm":false,"smart_sheet":false}', true) ?: [];
        $sprintVersion = $settingsRaw['weekly_sprint_version'] ?? 'v1.2.2';

        // Fetch all departments
        $departments = $db->query("SELECT DISTINCT department_name FROM users WHERE department_name IS NOT NULL AND department_name != '' ORDER BY department_name ASC")->fetchAll(PDO::FETCH_COLUMN) ?: [];

        // Fetch total users stats
        $totalUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
        $itUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE status = 'active' AND (department_name = 'Information Technology' OR department_name = 'IT')")->fetchColumn();
        $fieldUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE status = 'active' AND (work_mode = 'field' OR department_name LIKE '%Field%')")->fetchColumn();
        $salesUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE status = 'active' AND (department_name LIKE '%Business%' OR department_name LIKE '%Sales%' OR department_name LIKE '%Calling%')")->fetchColumn();

        include __DIR__ . '/../views/admin/super_admin_control.php';
    }

    public static function updateRollout(): void {
        requireSuperAdmin();
        $db = getDBConnection();

        $mode = $_POST['rollout_mode'] ?? 'it_testing';
        $allowedDepts = $_POST['allowed_departments'] ?? [];
        $sprintVersion = trim($_POST['weekly_sprint_version'] ?? 'v1.2.2');
        $broadcastMessage = trim($_POST['broadcast_message'] ?? '');

        // Features
        $features = [
            'punch' => !empty($_POST['feature_punch']),
            'gps_radar' => !empty($_POST['feature_gps_radar']),
            'bda_crm' => !empty($_POST['feature_bda_crm']),
            'smart_sheet' => !empty($_POST['feature_smart_sheet']),
            'leaves' => !empty($_POST['feature_leaves'])
        ];

        $stmt = $db->prepare("
            INSERT INTO system_rollout_settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");

        $stmt->execute(['rollout_mode', $mode]);
        $stmt->execute(['allowed_departments', json_encode(array_values($allowedDepts))]);
        $stmt->execute(['unlocked_features', json_encode($features)]);
        $stmt->execute(['weekly_sprint_version', $sprintVersion]);

        if (!empty($broadcastMessage)) {
            $stmt->execute(['broadcast_announcement', $broadcastMessage]);
        }

        setFlash('success', '👑 Master Super Admin Control: Rollout settings & Weekly Sprint features updated successfully!');
        header('Location: ?page=super-admin-control');
        exit;
    }
}