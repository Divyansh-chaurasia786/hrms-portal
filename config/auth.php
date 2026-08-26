<?php
// config/auth.php

if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        // Set 1-Year Persistent Session (Never logout automatically)
        @ini_set('session.cookie_lifetime', 60 * 60 * 24 * 365);
        @ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 365);
        @session_set_cookie_params([
            'lifetime' => 60 * 60 * 24 * 365,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}

define('AUTH_SECRET_KEY', 'hrms_v3_sec_token_98374283_a0b1c2d3');

function generateAuthToken(array $user): string {
    $data = [
        'id' => $user['id'],
        'email' => $user['email'],
        'role' => $user['role'],
        'time' => time()
    ];
    $json = json_encode($data);
    $b64 = base64_encode($json);
    $sig = hash_hmac('sha256', $b64, AUTH_SECRET_KEY);
    return $b64 . '.' . $sig;
}

function verifyAuthToken(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 2) return null;
    [$b64, $sig] = $parts;
    $expected = hash_hmac('sha256', $b64, AUTH_SECRET_KEY);
    if (!hash_equals($expected, $sig)) return null;
    $json = base64_decode($b64);
    $data = json_decode($json, true);
    if (!is_array($data) || empty($data['id'])) return null;
    return $data;
}

function setAuthCookie(array $user): void {
    $token = generateAuthToken($user);
    $expire = time() + (60 * 60 * 24 * 365); // 1 Year
    $secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    setcookie('hrms_auth_token', $token, [
        'expires' => $expire,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    $_COOKIE['hrms_auth_token'] = $token;
}

function clearAuthCookie(): void {
    setcookie('hrms_auth_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    unset($_COOKIE['hrms_auth_token']);
}

function authUser(): ?array {
    if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
        return $_SESSION['user'];
    }

    // Seamless auto-recovery from signed persistent auth token cookie (for Vercel serverless lambdas)
    if (!empty($_COOKIE['hrms_auth_token'])) {
        $data = verifyAuthToken($_COOKIE['hrms_auth_token']);
        if ($data && !empty($data['id'])) {
            try {
                $db = getDBConnection();
                $stmt = $db->prepare("SELECT u.*, d.name as department_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = ?");
                $stmt->execute([(int)$data['id']]);
                $user = $stmt->fetch();
                if ($user && empty($user['is_dismissed']) && $user['status'] === 'active') {
                    unset($user['password']);
                    unset($user['login_otp']);
                    $user['logged_in_at'] = time();
                    $_SESSION['user'] = $user;
                    return $user;
                } else {
                    clearAuthCookie();
                }
            } catch (Throwable $e) {
                // If DB has temporary latency, construct verified user from signed token
                $user = [
                    'id' => (int)$data['id'],
                    'email' => $data['email'] ?? '',
                    'role' => $data['role'] ?? 'employee',
                    'name' => $data['email'] ?? 'User',
                    'status' => 'active'
                ];
                $_SESSION['user'] = $user;
                return $user;
            }
        }
    }

    return null;
}

function isLoggedIn(): bool {
    return authUser() !== null;
}

function getRole(): ?string {
    $u = authUser();
    return $u['role'] ?? null;
}

function isAdmin(): bool {
    return getRole() === 'admin';
}

function isTL(): bool {
    return getRole() === 'team_lead';
}

function isEmployee(): bool {
    return getRole() === 'employee';
}

// 🔒 Workspace Lock Policy (Enforces active shift punch-in to unlock operations)
define('WORKSPACE_LOCK_ENFORCED', true);

function isInActiveShift(?int $userId = null): bool {
    if (!WORKSPACE_LOCK_ENFORCED) {
        return true; // Fully unlocked for testing
    }
    $uid = $userId ?: (authUser()['id'] ?? 0);
    if (!$uid) return false;
    $db = getDBConnection();
    $today = date('Y-m-d');
    $stmt = $db->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = ? AND clock_in IS NOT NULL AND clock_out IS NULL");
    $stmt->execute([$uid, $today]);
    return (bool)$stmt->fetchColumn();
}

function requireActiveShift(): void {
    requireAuth();
    if (!WORKSPACE_LOCK_ENFORCED) {
        return; // Fully unlocked for testing
    }
    if (!isInActiveShift()) {
        setFlash('error', '🔒 Punch-In Required: Your account is in Read-Only mode. Please click [Punch In (Office Login)] in the top right header to edit, create records, or perform actions.');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=dashboard'));
        exit;
    }
}

function requireAuth(): void {
    if (!isLoggedIn()) {
        header('Location: ?page=login');
        exit;
    }

    // Check if user was dismissed by HR or forced logout
    $user = authUser();
    if (!empty($user['id'])) {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT status, is_dismissed, dismissal_reason, force_logout_at FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();
        if ($row) {
            // If dismissed by HR
            if (!empty($row['is_dismissed']) || $row['status'] === 'inactive') {
                unset($_SESSION['user']);
                session_destroy();
                session_start();
                $reason = !empty($row['dismissal_reason']) ? ' (' . htmlspecialchars($row['dismissal_reason']) . ')' : '';
                setFlash('error', "🚫 Access Denied: You have been dismissed/terminated by HR{$reason}. Please contact the HR Department.");
                header('Location: ?page=login');
                exit;
            }

            // If forced logout (Applies only to non-admins)
            if ($user['role'] !== 'admin' && !empty($row['force_logout_at'])) {
                $forceLogoutTime = strtotime($row['force_logout_at']);
                $loginTime = (int)($user['logged_in_at'] ?? 0);
                if ($forceLogoutTime > $loginTime) {
                    $db->prepare("UPDATE users SET force_logout_at = NULL WHERE id = ?")->execute([$user['id']]);
                    unset($_SESSION['user']);
                    session_destroy();
                    session_start();
                    setFlash('error', '⚠️ Warning: Your session was forcefully terminated by your Team Lead. Remember from next time to punch out on time.');
                    header('Location: ?page=login');
                    exit;
                }
            }
        }
    }
}

function requireRole(array $roles): void {
    requireAuth();
    if (!in_array(getRole(), $roles, true)) {
        header('Location: ?page=dashboard&error=unauthorized');
        exit;
    }
}
