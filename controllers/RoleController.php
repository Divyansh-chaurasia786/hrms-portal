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
        requireRole(['admin']);
        requireActiveShift();
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $canAuth = !empty($_POST['can_be_reporting_authority']) ? 1 : 0;
        $dept = trim($_POST['department_name'] ?? 'General');

        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' || !empty($_POST['ajax']);

        if (empty($name)) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Role name is required.']);
                exit;
            }
            setFlash('error', 'Role name is required.');
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=admin-employees'));
            exit;
        }

        $code = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $name));
        $slug = $code;
        $db = getDBConnection();

        try {
            $stmt = $db->prepare("INSERT INTO roles_master (name, code, slug, description, can_be_reporting_authority, department_name) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $code, $slug, $description, $canAuth, $dept]);
            $newId = (int)$db->lastInsertId();

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'role' => [
                        'id' => $newId,
                        'name' => $name,
                        'code' => $code,
                        'slug' => $slug,
                        'can_be_reporting_authority' => $canAuth
                    ]
                ]);
                exit;
            }

            setFlash('success', "Role '{$name}' created successfully!");
        } catch (Exception $e) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Role already exists.']);
                exit;
            }
            setFlash('error', 'Role with this name or code already exists.');
        }

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=admin-employees'));
        exit;
    }

    public static function delete(): void {
        requireRole(['admin']);
        requireActiveShift();
        $roleId = (int)($_POST['role_id'] ?? 0);
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' || !empty($_POST['ajax']);

        if ($roleId > 0) {
            $db = getDBConnection();
            $db->prepare("DELETE FROM roles_master WHERE id = ?")->execute([$roleId]);
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            }
            setFlash('success', 'Role deleted successfully.');
        }
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=admin-employees'));
        exit;
    }
}