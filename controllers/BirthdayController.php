<?php
// controllers/BirthdayController.php

class BirthdayController {
    public static function index(): void {
        requireRole(['admin']);
        $user = authUser();
        $db = getDBConnection();

        // 1. Fetch All Active Staff with Birthdays
        $allStaff = $db->query("
            SELECT id, name, emp_id, email, phone, whatsapp_number, designation, department_name, avatar, date_of_birth,
                   DATE_FORMAT(date_of_birth, '%d %M') as dob_formatted,
                   DATE_FORMAT(date_of_birth, '%m-%d') as dob_month_day,
                   TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) as current_age
            FROM users 
            WHERE status = 'active'
            ORDER BY 
                CASE WHEN date_of_birth IS NOT NULL THEN 0 ELSE 1 END,
                DATE_FORMAT(date_of_birth, '%m-%d') ASC,
                name ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $todayMonthDay = date('m-d');
        $currentMonth = date('m');

        $todayBirthdays = [];
        $upcomingThisMonth = [];
        $allUpcoming = [];
        $missingDob = [];

        foreach ($allStaff as $st) {
            if (empty($st['date_of_birth'])) {
                $missingDob[] = $st;
                continue;
            }

            $mDay = $st['dob_month_day'];
            if ($mDay === $todayMonthDay) {
                $todayBirthdays[] = $st;
            } else {
                // Calculate days remaining until next birthday
                $thisYearDob = date('Y') . '-' . $mDay;
                if ($thisYearDob < date('Y-m-d')) {
                    $nextBday = (date('Y') + 1) . '-' . $mDay;
                } else {
                    $nextBday = $thisYearDob;
                }

                $diffDays = (int)round((strtotime($nextBday) - strtotime(date('Y-m-d'))) / 86400);
                $st['days_remaining'] = $diffDays;
                $st['turning_age'] = (int)($st['current_age'] ?? 0) + 1;

                if (substr($mDay, 0, 2) === $currentMonth && $diffDays > 0) {
                    $upcomingThisMonth[] = $st;
                }
                $allUpcoming[] = $st;
            }
        }

        // Sort upcoming by days remaining
        usort($allUpcoming, fn($a, $b) => ($a['days_remaining'] ?? 0) <=> ($b['days_remaining'] ?? 0));
        usort($upcomingThisMonth, fn($a, $b) => ($a['days_remaining'] ?? 0) <=> ($b['days_remaining'] ?? 0));

        require __DIR__ . '/../views/admin/birthdays.php';
    }

    public static function updateDob(): void {
        requireRole(['admin']);
        requireActiveShift();
        $db = getDBConnection();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $dob = trim($_POST['date_of_birth'] ?? '');

            if ($userId > 0) {
                $dobVal = !empty($dob) ? $dob : null;
                $stmt = $db->prepare("UPDATE users SET date_of_birth = ? WHERE id = ?");
                $stmt->execute([$dobVal, $userId]);

                $uName = $db->query("SELECT name FROM users WHERE id = {$userId}")->fetchColumn() ?: 'Employee';
                setFlash('success', "🎂 Birthday for <strong>{$uName}</strong> updated to " . ($dobVal ? formatDate($dobVal) : 'None') . ".");
            }
        }
        header('Location: ?page=admin-birthdays');
        exit;
    }

    public static function sendCelebrationWish(): void {
        requireRole(['admin']);
        requireActiveShift();
        $hrUser = authUser();
        $db = getDBConnection();

        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId > 0) {
            $targetUser = $db->query("SELECT * FROM users WHERE id = {$userId}")->fetch(PDO::FETCH_ASSOC);
            if ($targetUser && !empty($targetUser['email'])) {
                $empName = $targetUser['name'];
                $desig = $targetUser['designation'] ?: 'Valued Team Member';
                $hrName = $hrUser['name'] ?? 'HR Leadership';

                $subject = "🎉 Happy Birthday, {$empName}! Wishing You a Fantastic Day from Ecofone HRMS!";
                $bodyHtml = '
                    <div style="text-align: center; padding: 15px 0;">
                        <span style="font-size: 42px;">🎂 🎈 🎁</span>
                        <h2 style="color: #4f46e5; margin: 10px 0 5px 0; font-size: 22px;">Happy Birthday, ' . htmlspecialchars($empName) . '!</h2>
                        <p style="color: #64748b; font-size: 13px; margin: 0 0 20px 0;">' . htmlspecialchars($desig) . ' • Ecovista Global Team</p>
                    </div>
                    <p style="font-size: 14px; line-height: 1.6; color: #334155; margin-bottom: 16px;">
                        On behalf of the entire leadership and workforce at <strong>Ecovista Global Private Limited</strong>, we wish you a very <strong>Happy Birthday</strong>!
                    </p>
                    <p style="font-size: 14px; line-height: 1.6; color: #334155; margin-bottom: 16px;">
                        We deeply appreciate your hard work, dedication, and the positive energy you bring to the team every single day. May the upcoming year bring you immense success, good health, and joyful moments.
                    </p>
                    <div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 16px; margin: 20px 0; text-align: center;">
                        <strong style="color: #166534; font-size: 14px;">🎉 Enjoy your special day!</strong>
                    </div>
                    <p style="margin-top: 24px; font-size: 13px; color: #64748b;">
                        Warm Regards,<br>
                        <strong>' . htmlspecialchars($hrName) . '</strong><br>
                        HR & Operations Department<br>
                        Ecovista Global Private Limited
                    </p>
                ';

                $fullHtml = getFormalEmailLetterhead(
                    'Happy Birthday Celebration',
                    'Official Company Birthday Greeting',
                    $bodyHtml,
                    'EGPL/BDAY/' . date('Ymd') . '/' . strtoupper(substr(md5($empName), 0, 5))
                );

                $plainText = "ECOVISTA GLOBAL PVT. LTD.\n\nHappy Birthday, {$empName}!\n\nWishing you a fantastic birthday and a wonderful year ahead filled with success and happiness.\n\nWarm Regards,\n{$hrName}\nHR Department";

                sendBrevoEmail($targetUser['email'], null, $subject, $plainText, $fullHtml);
                setFlash('success', "💌 Birthday celebration wishes successfully sent to <strong>{$empName}</strong> ({$targetUser['email']})!");
            }
        }
        header('Location: ?page=admin-birthdays');
        exit;
    }
}