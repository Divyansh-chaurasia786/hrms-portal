<?php
// controllers/RoleController.php

class RoleController {
    public static function index(): void {
        requireAuth('admin');
        $db = getDBConnection();

        $roles = $db->query("SELECT * FROM roles_master ORDER BY can_be_reporting_authority DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        $roleCounts = [];
        $userRows = $db->query("SELECT designation, COUNT(*) as cnt FROM users GROUP BY designation")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($userRows as $r) {
            if (!empty($r['designation'])) {
                $roleCounts[$r['designation']] = $r['cnt'];
            }
        }

        require __DIR__ . '/../views/admin/roles.php';
    }

    public static function create(): void {
        requireAuth('admin');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $canBeAuth = !empty($_POST['can_be_reporting_authority']) ? 1 : 0;
            $dept = trim($_POST['department_name'] ?? 'General');

            if (empty($name)) {
                setFlash('error', 'Please provide a valid role name.');
                header('Location: ?page=admin-roles');
                exit;
            }

            $code = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $name));

            $db = getDBConnection();
            $stmt = $db->prepare("INSERT INTO roles_master (name, code, can_be_reporting_authority, department_name) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), can_be_reporting_authority = VALUES(can_be_reporting_authority), department_name = VALUES(department_name)");
            $stmt->execute([$name, $code, $canBeAuth, $dept]);

            setFlash('success', "Role '{$name}' added / updated successfully!");
            header('Location: ?page=admin-roles');
            exit;
        }
    }

    public static function delete(): void {
        requireAuth('admin');
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db = getDBConnection();
            $role = $db->query("SELECT * FROM roles_master WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);
            if ($role) {
                if ($role['code'] === 'head_hr') {
                    setFlash('error', 'Cannot delete primary Head HR role.');
                    header('Location: ?page=admin-roles');
                    exit;
                }
                $db->exec("DELETE FROM roles_master WHERE id = {$id}");
                setFlash('success', "Role '{$role['name']}' removed successfully.");
            }
        }
        header('Location: ?page=admin-roles');
        exit;
    }
}