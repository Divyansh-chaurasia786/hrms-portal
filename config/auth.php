<?php
// config/auth.php

if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        // High-Security Ephemeral Session (Strictly destroyed when browser is closed)
        @ini_set('session.cookie_lifetime', 0);
        @session_set_cookie_params([
            'lifetime' => 0, // 0 = Strict Session Cookie (Destroyed on browser close)
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}

define('AUTH_SECRET_KEY', 'hrms_v5_session_only_sec_key_20260826_ephemeral');

function generateAuthToken(array $user, string $sessionToken): string {
    $data = [
        'id' => $user['id'],
        'email' => $user['email'],
        'role' => $user['role'],
        'token' => $sessionToken,
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

function setAuthCookie(array $user, string $sessionToken): void {
    $token = generateAuthToken($user, $sessionToken);
    $expire = 0; // 0 = Browser Session Only (Auto-logout on browser close)
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
    $sessionUser = $_SESSION['user'] ?? null;
    $sessionToken = $_SESSION['user_session_token'] ?? null;

    if (!empty($sessionUser) && is_array($sessionUser) && !empty($sessionUser['id'])) {
        // Enforce Single-Device & Active Status check on every single request
        try {
            $db = getDBConnection();
            $stmt = $db->prepare("SELECT id, name, role, email, status, is_dismissed, dismissal_reason, force_logout_at, current_session_token FROM users WHERE id = ?");
            $stmt->execute([(int)$sessionUser['id']]);
            $dbUser = $stmt->fetch();

            if ($dbUser) {
                // 1. Check if dismissed or inactive
                if (!empty($dbUser['is_dismissed']) || $dbUser['status'] === 'inactive') {
                    unset($_SESSION['user'], $_SESSION['user_session_token']);
                    clearAuthCookie();
                    return null;
                }

                // 2. Check Single-Device Conflict: If another device logged in and generated a new session token
                $dbToken = $dbUser['current_session_token'] ?? '';
                if (!empty($dbToken) && !empty($sessionToken) && !hash_equals($dbToken, $sessionToken)) {
                    // Previous device session terminated
                    unset($_SESSION['user'], $_SESSION['user_session_token']);
                    clearAuthCookie();
                    setFlash('error', '🚨 Logged Out from Previous Device: Your account was just logged in on another device or browser. Only one active device is allowed at a time.');
                    return null;
                }

                // 3. Forced logout by Admin
                if ($sessionUser['role'] !== 'admin' && !empty($dbUser['force_logout_at'])) {
                    $forceLogoutTime = strtotime($dbUser['force_logout_at']);
                    $loginTime = (int)($sessionUser['logged_in_at'] ?? 0);
                    if ($forceLogoutTime > $loginTime) {
                        unset($_SESSION['user'], $_SESSION['user_session_token']);
                        clearAuthCookie();
                        setFlash('error', '⚠️ Session Terminated: Your session has been revoked by HR Administration.');
                        return null;
                    }
                }

                return $sessionUser;
            }
        } catch (\Throwable $e) {
            return $sessionUser;
        }
    }

    // Auto-recovery from Signed Cookie if session expired
    if (!empty($_COOKIE['hrms_auth_token'])) {
        $data = verifyAuthToken($_COOKIE['hrms_auth_token']);
        if ($data && !empty($data['id'])) {
            try {
                $db = getDBConnection();
                $stmt = $db->prepare("SELECT u.* FROM users u WHERE u.id = ?");
                $stmt->execute([(int)$data['id']]);
                $user = $stmt->fetch();
                if ($user && empty($user['is_dismissed']) && $user['status'] === 'active') {
                    $dbToken = $user['current_session_token'] ?? '';
                    $cookieToken = $data['token'] ?? '';

                    if (!empty($dbToken) && hash_equals($dbToken, $cookieToken)) {
                        unset($user['password'], $user['login_otp']);
                        $user['logged_in_at'] = time();
                        $_SESSION['user'] = $user;
                        $_SESSION['user_session_token'] = $dbToken;
                        return $user;
                    }
                }
                clearAuthCookie();
            } catch (\Throwable $e) {}
        }
    }

    return null;
}

function isLoggedIn(): bool {
    return authUser() !== null;
}

function isAdmin(): bool {
    $u = authUser();
    return $u && $u['role'] === 'admin';
}

function isTeamLead(): bool {
    $u = authUser();
    return $u && ($u['role'] === 'team_lead' || $u['role'] === 'admin');
}

function requireRole($roles): void {
    requireAuth();
    $u = authUser();
    if (is_string($roles)) $roles = [$roles];
    if (!$u || !in_array($u['role'], $roles)) {
        setFlash('error', 'Unauthorized access.');
        header('Location: ?page=dashboard');
        exit;
    }
}

// 🔒 Workspace Lock Policy (Enforces active shift punch-in to unlock operations)
define('WORKSPACE_LOCK_ENFORCED', true);

function isInActiveShift(?int $userId = null): bool {
    if (!WORKSPACE_LOCK_ENFORCED) {
        return true;
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
        return;
    }
    if (!isInActiveShift()) {
        setFlash('error', '🔒 Punch-In Required: Your account is in Read-Only mode. Please click [Punch In (Office Login)] in the top right header to edit, create records, or perform actions.');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=dashboard'));
        exit;
    }
}

function requireAuth(?string $allowedRole = null): void {
    if (!isLoggedIn()) {
        header('Location: ?page=login');
        exit;
    }

    $user = authUser();
    if (!empty($user['id'])) {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT id, name, role, email, status, is_dismissed, dismissal_reason, force_logout_at, current_session_token FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();
        if ($row) {
            // 1. Check if dismissed / inactive
            if (!empty($row['is_dismissed']) || $row['status'] === 'inactive') {
                unset($_SESSION['user']);
                unset($_SESSION['user_session_token']);
                clearAuthCookie();
                session_destroy();
                session_start();
                $reason = !empty($row['dismissal_reason']) ? ' (' . htmlspecialchars($row['dismissal_reason']) . ')' : '';
                setFlash('error', "🚫 Access Denied: You have been dismissed/terminated by HR{$reason}. Please contact the HR Department.");
                header('Location: ?page=login');
                exit;
            }

            // 2. Check Single-Device Conflict (Another device logged in with this account)
            $dbToken = $row['current_session_token'] ?? null;
            $localToken = $_SESSION['user_session_token'] ?? null;

            if (!empty($dbToken) && !empty($localToken) && !hash_equals($dbToken, $localToken)) {
                unset($_SESSION['user']);
                unset($_SESSION['user_session_token']);
                clearAuthCookie();
                session_destroy();
                session_start();
                setFlash('error', "🚨 Logged Out from Previous Device: Your account was just logged in on another device. Your previous shift was auto-punched out for security. Please log in and punch in again on this device if needed.");
                header('Location: ?page=login');
                exit;
            }

            // 3. Check if email was changed
            if (strtolower($row['email']) !== strtolower($user['email'])) {
                unset($_SESSION['user']);
                unset($_SESSION['user_session_token']);
                clearAuthCookie();
                session_destroy();
                session_start();
                setFlash('warning', "🔒 Security Notice: Your work email address was updated. Please sign in with your new email ({$row['email']}).");
                header('Location: ?page=login');
                exit;
            }

            // 4. If forced logout by Admin
            if ($user['role'] !== 'admin' && !empty($row['force_logout_at'])) {
                $forceLogoutTime = strtotime($row['force_logout_at']);
                $loginTime = (int)($user['logged_in_at'] ?? 0);
                if ($forceLogoutTime > $loginTime) {
                    $db->prepare("UPDATE users SET force_logout_at = NULL WHERE id = ?")->execute([$user['id']]);
                    unset($_SESSION['user']);
                    unset($_SESSION['user_session_token']);
                    clearAuthCookie();
                    session_destroy();
                    session_start();
                    setFlash('error', '⚠️ Session Terminated: Your session has been revoked by HR Administration. Please sign in again.');
                    header('Location: ?page=login');
                    exit;
                }
            }
        }
    }

    if ($allowedRole !== null && $user['role'] !== $allowedRole && $user['role'] !== 'admin') {
        setFlash('error', 'Unauthorized access.');
        header('Location: ?page=dashboard');
        exit;
    }
}