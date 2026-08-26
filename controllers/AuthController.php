<?php
// controllers/AuthController.php

class AuthController {
    public static function login(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = strtolower(trim($_POST['email'] ?? ''));

            if (empty($email)) {
                setFlash('error', 'Please enter your registered work email address.');
                header('Location: ?page=login');
                exit;
            }

            $db = getDBConnection();
            $stmt = $db->prepare("SELECT u.*, d.name as department_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE LOWER(u.email) = LOWER(?)");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // 1. Check if employee is dismissed / terminated by HR
                if (!empty($user['is_dismissed']) || $user['status'] === 'inactive') {
                    $reason = !empty($user['dismissal_reason']) ? ' (' . htmlspecialchars($user['dismissal_reason']) . ')' : '';
                    setFlash('error', "🚫 Access Denied: You have been dismissed/terminated by HR{$reason}. Please contact the HR Department.");
                    header('Location: ?page=login');
                    exit;
                }

                // 2. Check if user is locked due to active HR escalation by TL
                if (!empty($user['is_escalated_locked'])) {
                    $tlName = 'Team Lead';
                    if (!empty($user['escalated_by_tl_id'])) {
                        $tlRow = $db->query("SELECT name FROM users WHERE id = {$user['escalated_by_tl_id']}")->fetch();
                        if ($tlRow) $tlName = $tlRow['name'];
                    }
                    $reason = !empty($user['escalated_lock_reason']) ? ' (Reason: ' . htmlspecialchars($user['escalated_lock_reason']) . ')' : '';
                    setFlash('error', "🔒 Access Restricted: Your account has been referred to HR by TL {$tlName}{$reason}. You cannot log in until your Team Lead unlocks/allows your account access.");
                    header('Location: ?page=login');
                    exit;
                }

                // 3. Check Daily OTP Limit (Max 5 per day, resets next day)
                $today = date('Y-m-d');
                $lastDate = $user['otp_last_sent_date'] ?? '';
                $sentCount = (int)($user['otp_sent_count_today'] ?? 0);

                if ($lastDate !== $today) {
                    // Next day: reset counter automatically
                    $sentCount = 0;
                    $db->prepare("UPDATE users SET otp_sent_count_today = 0, is_otp_blocked_today = 0, otp_last_sent_date = ? WHERE id = ?")
                       ->execute([$today, $user['id']]);
                }

                if ($sentCount >= 5) {
                    // Blocked for today until next day
                    $db->prepare("UPDATE users SET is_otp_blocked_today = 1 WHERE id = ?")->execute([$user['id']]);
                    setFlash('error', '🚫 Daily OTP Limit Exceeded: You have reached the maximum 5 OTP requests allowed for today. Your login is blocked until tomorrow. Please try again tomorrow.');
                    header('Location: ?page=login');
                    exit;
                }

                // 4. Generate 6-Digit OTP & Send via Registered Email (Brevo)
                $otpCode = (string)random_int(100000, 999999);
                $now = date('Y-m-d H:i:s');
                $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes

                $updateStmt = $db->prepare("
                    UPDATE users 
                    SET login_otp = ?, login_otp_expires_at = ?, login_otp_last_sent_at = ?, 
                        otp_last_sent_date = ?, is_otp_blocked_today = 0 
                    WHERE id = ?
                ");
                $updateStmt->execute([$otpCode, $expiresAt, $now, $today, $user['id']]);

                // Send email via Brevo API
                $mailRes = sendEmailOTP($user['email'], $user['name'], $otpCode);

                // Set Pending Auth Session
                $_SESSION['pending_otp_user_id'] = $user['id'];
                $_SESSION['pending_otp_email'] = $user['email'];
                $_SESSION['otp_resend_count'] = 0; // Fresh login starts with 0 resends used

                setFlash('success', "A 6-digit verification code has been sent to your registered email (<strong>{$user['email']}</strong>).");

                header('Location: ?page=verify-otp');
                exit;
            } else {
                setFlash('error', '❌ No registered account found with this email address. Please contact HR.');
                header('Location: ?page=login');
                exit;
            }
        }
        require __DIR__ . '/../views/auth/login.php';
    }

    public static function showVerifyOtp(): void {
        if (empty($_SESSION['pending_otp_user_id'])) {
            header('Location: ?page=login');
            exit;
        }

        $userId = (int)$_SESSION['pending_otp_user_id'];
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT u.*, d.name as department_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            unset($_SESSION['pending_otp_user_id']);
            header('Location: ?page=login');
            exit;
        }

        // Calculate remaining seconds for 60s timer
        $lastSent = !empty($user['login_otp_last_sent_at']) ? strtotime($user['login_otp_last_sent_at']) : 0;
        $elapsed = time() - $lastSent;
        $cooldownRemaining = max(0, 60 - $elapsed);
        $resendCount = (int)($_SESSION['otp_resend_count'] ?? 0);
        $isResendBlocked = $resendCount >= 5;

        require __DIR__ . '/../views/auth/verify_otp.php';
    }

    public static function verifyOtp(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['pending_otp_user_id'])) {
            header('Location: ?page=login');
            exit;
        }

        $userId = (int)$_SESSION['pending_otp_user_id'];
        $otp = trim($_POST['otp'] ?? '');

        if (empty($otp) || strlen($otp) !== 6) {
            setFlash('error', 'Please enter a valid 6-digit OTP verification code.');
            header('Location: ?page=verify-otp');
            exit;
        }

        $db = getDBConnection();
        $stmt = $db->prepare("SELECT u.*, d.name as department_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            unset($_SESSION['pending_otp_user_id']);
            setFlash('error', 'Session expired. Please sign in again.');
            header('Location: ?page=login');
            exit;
        }

        $now = date('Y-m-d H:i:s');

        // Check if OTP matches and has not expired
        if (!empty($user['login_otp']) && $user['login_otp'] === $otp && $user['login_otp_expires_at'] >= $now) {
            // Prevent Session Fixation Attack by regenerating session ID
            if (session_status() === PHP_SESSION_ACTIVE) {
                @session_regenerate_id(true);
            }

            // Clear OTP, reset resend limit counter, clear pending state, and reset force logout state upon successful login
            $db->prepare("UPDATE users SET login_otp = NULL, login_otp_expires_at = NULL, otp_sent_count_today = 0, is_otp_blocked_today = 0, force_logout_at = NULL WHERE id = ?")->execute([$userId]);
            unset($_SESSION['pending_otp_user_id']);
            unset($_SESSION['pending_otp_email']);
            unset($_SESSION['otp_resend_count']);

            unset($user['password']);
            unset($user['login_otp']);
            $user['logged_in_at'] = time();
            $_SESSION['user'] = $user;
            setAuthCookie($user); // Signed Persistent Token Cookie for instant serverless resilience

            // If there's an active HR warning message
            if (!empty($user['hr_warning_message'])) {
                setFlash('warning', "⚠️ HR Notice: " . $user['hr_warning_message']);
            } else {
                setFlash('success', '🔐 Welcome back, ' . $user['name'] . '!');
            }

            if ($user['role'] === 'admin') {
                header('Location: ?page=admin-dashboard');
            } elseif ($user['role'] === 'team_lead') {
                header('Location: ?page=tl-dashboard');
            } else {
                header('Location: ?page=employee-dashboard');
            }
            exit;
        } else {
            setFlash('error', '❌ Invalid or expired OTP verification code. Please check your email or request a new code.');
            header('Location: ?page=verify-otp');
            exit;
        }
    }

    public static function resendOtp(): void {
        if (empty($_SESSION['pending_otp_user_id'])) {
            header('Location: ?page=login');
            exit;
        }

        $userId = (int)$_SESSION['pending_otp_user_id'];
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            unset($_SESSION['pending_otp_user_id']);
            header('Location: ?page=login');
            exit;
        }

        // 1. Check max 5 resend attempts limit for this session
        $resendCount = (int)($_SESSION['otp_resend_count'] ?? 0);
        if ($resendCount >= 5) {
            setFlash('error', 'Maximum 5 OTP resends reached. Please check the verification code in your email or try signing in again.');
            header('Location: ?page=verify-otp');
            exit;
        }

        // 2. Check 60s cooldown timer
        $lastSent = !empty($user['login_otp_last_sent_at']) ? strtotime($user['login_otp_last_sent_at']) : 0;
        $elapsed = time() - $lastSent;
        if ($elapsed < 60) {
            $wait = 60 - $elapsed;
            setFlash('error', "Please wait {$wait} seconds before requesting a new verification code.");
            header('Location: ?page=verify-otp');
            exit;
        }

        // 3. Generate new 6-Digit OTP & Send via Brevo
        $otpCode = (string)random_int(100000, 999999);
        $newResendCount = $resendCount + 1;
        $_SESSION['otp_resend_count'] = $newResendCount;
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes

        $updateStmt = $db->prepare("
            UPDATE users 
            SET login_otp = ?, login_otp_expires_at = ?, login_otp_last_sent_at = ?, 
                otp_sent_count_today = ?, otp_last_sent_date = ?, is_otp_blocked_today = 0 
            WHERE id = ?
        ");
        $today = date('Y-m-d');
        $updateStmt->execute([$otpCode, $expiresAt, $now, $newResendCount, $today, $user['id']]);

        // Send email via Brevo
        $mailRes = sendEmailOTP($user['email'], $user['name'], $otpCode);

        setFlash('success', "A fresh verification code has been sent to <strong>{$user['email']}</strong>.");

        header('Location: ?page=verify-otp');
        exit;
    }

    public static function logout(): void {
        if (isset($_SESSION['user']['id'])) {
            TaskController::autoSubmitOnShiftEnd((int)$_SESSION['user']['id']);
        }
        
        // 1. Clear session array & destroy session
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        @session_destroy();

        // 2. Clear HMAC signed persistent token cookie
        clearAuthCookie();

        // 3. Prevent browser back-button caching
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Pragma: no-cache");

        setFlash('info', 'You have been securely signed out.');
        header('Location: ?page=login');
        exit;
    }

    public static function forceLogoutUser(): void {
        requireRole(['team_lead', 'admin']);
        $current = authUser();
        $targetId = (int)($_POST['user_id'] ?? 0);

        if ($targetId <= 0) {
            setFlash('error', 'Invalid teammate selected.');
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=tl-dashboard'));
            exit;
        }

        $db = getDBConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();

        if (!$target) {
            setFlash('error', 'Teammate not found.');
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=tl-dashboard'));
            exit;
        }

        if (!isAdmin() && $target['tl_id'] != $current['id']) {
            setFlash('error', 'You can only logout members of your own team.');
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=tl-dashboard'));
            exit;
        }

        // Auto-submit any unsubmitted tasks for employee/user before terminating session
        TaskController::autoSubmitOnShiftEnd($targetId);

        $stmt = $db->prepare("UPDATE users SET force_logout_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$targetId]);

        // Also clock out the employee in attendance for today
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        $attStmt = $db->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ? AND clock_out IS NULL");
        $attStmt->execute([$targetId, $today]);
        $att = $attStmt->fetch();

        if ($att) {
            $inTs = strtotime($att['clock_in']);
            $outTs = strtotime($now);
            $totalHours = round(($outTs - $inTs) / 3600, 2);
            $db->prepare("
                UPDATE attendance 
                SET clock_out = ?, total_hours = ?, force_logged_out_by = ?, force_logout_at = ?
                WHERE id = ?
            ")->execute([$now, $totalHours, $current['id'], $now, $att['id']]);

            // Close active session in attendance_sessions
            $db->prepare("
                UPDATE attendance_sessions 
                SET clock_out = ?, hours = ?, ended_by = 'force_logout', ended_by_user_id = ?
                WHERE attendance_id = ? AND clock_out IS NULL
            ")->execute([$now, $totalHours, $current['id'], $att['id']]);
        }

        // Audit Log for HR & Admin tracking
        $stmtLog = $db->prepare("
            INSERT INTO session_terminations (user_id, terminated_by, reason) 
            VALUES (?, ?, ?)
        ");
        $reason = !empty($_POST['reason']) ? trim($_POST['reason']) : 'Active session terminated by ' . ($current['role'] === 'team_lead' ? 'Team Lead' : 'Admin');
        $stmtLog->execute([$targetId, $current['id'], $reason]);

        setFlash('success', "🚪 Session terminated. {$target['name']} has been logged out and pending tasks auto-submitted.");
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=tl-dashboard'));
        exit;
    }

}
