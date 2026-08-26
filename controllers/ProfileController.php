<?php
// controllers/ProfileController.php

class ProfileController {
    public static function updateProfile(): void {
        requireAuth();
        $user = authUser();
        $avatarUrl = trim($_POST['avatar_url'] ?? '');
        $finalAvatar = $user['avatar'];

        // Check if an image file was uploaded
        if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if (in_array($ext, $allowedExts, true)) {
                $uploadDir = __DIR__ . '/../public/uploads/avatars';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $newFilename = 'avatar_' . $user['id'] . '_' . time() . '_' . substr(md5(uniqid()), 0, 6) . '.' . $ext;
                $uploadPath = $uploadDir . '/' . $newFilename;

                if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    $finalAvatar = 'uploads/avatars/' . $newFilename;
                } else {
                    setFlash('error', 'Failed to save uploaded image. Please try again.');
                    header('Location: ?page=profile');
                    exit;
                }
            } else {
                setFlash('error', 'Invalid image format. Allowed formats: PNG, JPG, JPEG, WEBP, GIF.');
                header('Location: ?page=profile');
                exit;
            }
        } elseif (!empty($avatarUrl)) {
            $finalAvatar = $avatarUrl;
        }

        $db = getDBConnection();
        $stmt = $db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
        $stmt->execute([$finalAvatar, $user['id']]);

        // Refresh session
        $refresh = $db->prepare("SELECT u.* FROM users u WHERE u.id = ?");
        $refresh->execute([$user['id']]);
        $updatedUser = $refresh->fetch();
        $_SESSION['user'] = $updatedUser;

        setFlash('success', '🎉 Profile picture updated successfully!');
        header('Location: ?page=profile');
        exit;
    }

    public static function requestEmailChange(): void {
        requireAuth();
        $user = authUser();
        $currentEmailInput = trim($_POST['current_email'] ?? '');
        $password = $_POST['password'] ?? '';
        $newEmail = trim($_POST['new_email'] ?? '');
        $confirmNewEmail = trim($_POST['confirm_new_email'] ?? '');

        // 1. Validate inputs
        if (empty($currentEmailInput) || empty($newEmail)) {
            setFlash('error', 'Current Email and New Email are required.');
            header('Location: ?page=profile');
            exit;
        }

        // 2. Validate current email matches
        if (strtolower($currentEmailInput) !== strtolower($user['email'])) {
            setFlash('error', 'The provided current email does not match your registered email.');
            header('Location: ?page=profile');
            exit;
        }

        // 3. Validate new email match & format
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Invalid new email format.');
            header('Location: ?page=profile');
            exit;
        }

        if (strtolower($newEmail) !== strtolower($confirmNewEmail)) {
            setFlash('error', 'New email and Confirm new email do not match.');
            header('Location: ?page=profile');
            exit;
        }

        if (strtolower($newEmail) === strtolower($user['email'])) {
            setFlash('error', 'New email cannot be the same as your current email.');
            header('Location: ?page=profile');
            exit;
        }

        $db = getDBConnection();

        // 4. Check if new email is already taken
        $check = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->execute([$newEmail, $user['id']]);
        if ($check->fetch()) {
            setFlash('error', 'The new email address is already registered by another account.');
            header('Location: ?page=profile');
            exit;
        }

        // 6. Generate 6-digit OTP verification code
        $otp = (string)random_int(100000, 999999);
        $_SESSION['email_change_request'] = [
            'new_email' => $newEmail,
            'current_email' => $user['email'],
            'otp' => $otp,
            'expires_at' => time() + 600 // 10 minutes
        ];

        // Send OTP to the new email address via Brevo
        sendEmailOTP($newEmail, $user['name'], $otp);

        setFlash('success', "📨 A 6-digit verification code has been sent to your new email address (<strong>{$newEmail}</strong>). Please enter the code below to activate your new email.");
        header('Location: ?page=profile');
        exit;
    }

    public static function verifyEmailChange(): void {
        requireAuth();
        $user = authUser();
        $enteredOtp = trim($_POST['otp'] ?? '');

        if (!isset($_SESSION['email_change_request'])) {
            setFlash('error', 'No active email verification request found.');
            header('Location: ?page=profile');
            exit;
        }

        $req = $_SESSION['email_change_request'];

        if (time() > $req['expires_at']) {
            unset($_SESSION['email_change_request']);
            setFlash('error', 'Verification code has expired. Please request a new email change.');
            header('Location: ?page=profile');
            exit;
        }

        if ($enteredOtp !== $req['otp']) {
            setFlash('error', 'Invalid verification code. Please enter the 6-digit OTP.');
            header('Location: ?page=profile');
            exit;
        }

        // OTP Validated! Update Email in DB
        $db = getDBConnection();
        $stmt = $db->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt->execute([$req['new_email'], $user['id']]);

        // Refresh session
        $refresh = $db->prepare("SELECT u.* FROM users u WHERE u.id = ?");
        $refresh->execute([$user['id']]);
        $updatedUser = $refresh->fetch();
        unset($updatedUser['password']);
        $_SESSION['user'] = $updatedUser;
        unset($_SESSION['email_change_request']);

        setFlash('success', "Email address verified and successfully updated to {$req['new_email']}!");
        header('Location: ?page=profile');
        exit;
    }

    public static function cancelEmailChange(): void {
        requireAuth();
        unset($_SESSION['email_change_request']);
        setFlash('success', 'Email change request cancelled.');
        header('Location: ?page=profile');
        exit;
    }
}
