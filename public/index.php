<?php
// public/index.php
date_default_timezone_set('Asia/Kolkata');

// 🛡️ Global Security Headers
if (!headers_sent()) {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/AttendanceController.php';
require_once __DIR__ . '/../controllers/TaskController.php';
require_once __DIR__ . '/../controllers/LeaveController.php';
require_once __DIR__ . '/../controllers/EmployeeController.php';
require_once __DIR__ . '/../controllers/ProjectController.php';
require_once __DIR__ . '/../controllers/FeedbackController.php';
require_once __DIR__ . '/../controllers/ProfileController.php';
require_once __DIR__ . '/../controllers/DriveController.php';
require_once __DIR__ . '/../controllers/RoleController.php';
require_once __DIR__ . '/../controllers/WfhController.php';
require_once __DIR__ . '/../controllers/CallingController.php';
require_once __DIR__ . '/../controllers/SmartSheetController.php';

// Ensure DB is initialized
$db = getDBConnection();

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Meta WhatsApp Cloud API Webhook Verification Endpoint
if ($uri === '/webhook/whatsapp' || $uri === '/api/webhook') {
    $hubMode = $_GET['hub_mode'] ?? ($_GET['hub.mode'] ?? '');
    $hubChallenge = $_GET['hub_challenge'] ?? ($_GET['hub.challenge'] ?? '');
    $hubVerifyToken = $_GET['hub_verify_token'] ?? ($_GET['hub.verify_token'] ?? '');

    if ($hubMode === 'subscribe' && $hubVerifyToken === 'ecovista_hrms_meta_webhook_2026') {
        header('Content-Type: text/plain');
        echo $hubChallenge;
        exit;
    }
    header('Content-Type: text/plain');
    echo 'EVENT_RECEIVED';
    exit;
}

if (preg_match('#^/(google[a-z0-9]+\.html)$#', $uri, $m)) {
    header('Content-Type: text/html; charset=utf-8');
    $filePath = __DIR__ . '/' . $m[1];
    if (file_exists($filePath)) {
        echo file_get_contents($filePath);
    } else {
        echo "google-site-verification: " . htmlspecialchars($m[1]);
    }
    exit;
}
if ($uri === '/robots.txt') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\nAllow: /\nAllow: /?page=login\nDisallow: /api/\nDisallow: /config/\n\nSitemap: https://hrms-ecofone.vercel.app/sitemap.xml\n";
    exit;
}
if ($uri === '/sitemap.xml') {
    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://hrms-ecofone.vercel.app/</loc>
    <lastmod>' . date('Y-m-d') . '</lastmod>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>
  <url>
    <loc>https://hrms-ecofone.vercel.app/?page=login</loc>
    <lastmod>' . date('Y-m-d') . '</lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.9</priority>
  </url>
</urlset>';
    exit;
}

$action = $_GET['action'] ?? null;
$page = $_GET['page'] ?? null;

// Handle Actions
if ($action) {
    switch ($action) {
        case 'location-disabled-logout':
            if (isset($_SESSION['user'])) {
                $user = $_SESSION['user'];
                $db = getDBConnection();
                $today = date('Y-m-d');
                $now = date('Y-m-d H:i:s');

                // Check and auto clock-out active shift
                $att = $db->query("SELECT * FROM attendance WHERE user_id = {$user['id']} AND date = '{$today}' AND clock_out IS NULL")->fetch(PDO::FETCH_ASSOC);
                if ($att) {
                    $inTs = strtotime($att['clock_in']);
                    $outTs = strtotime($now);
                    $totalHours = round(($outTs - $inTs) / 3600, 2);
                    if ($totalHours < 0) $totalHours = 0;

                    $stmt = $db->prepare("
                        UPDATE attendance 
                        SET clock_out = ?, total_hours = ?, notes = 'Auto Shift-Out: Location/GPS turned off during active shift', hr_alert_message = 'Security Alert: GPS/Location disabled by user' 
                        WHERE id = ?
                    ");
                    $stmt->execute([$now, $totalHours, $att['id']]);
                }

                // Destroy session
                unset($_SESSION['user']);
                session_destroy();
                session_start();
                setFlash('error', '🚨 Security Alert: You have been automatically logged out because your GPS/Location services were turned off during active duty. Please enable device Location to sign back in.');
            }

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                echo json_encode(['success' => true, 'redirect' => '?page=login']);
                exit;
            }
            header('Location: ?page=login');
            exit;
        case 'admin-run-archival':
            requireRole(['admin']);
            $res = run3YearAutoArchival(true);
            if ($res['executed']) {
                setFlash('success', "📦 " . $res['message']);
            } else {
                setFlash('info', "ℹ️ " . $res['message']);
            }
            header('Location: ?page=admin-smart-sheets');
            exit;

        case 'admin-download-archive-backup':
            requireRole(['admin']);
            $db = getDBConnection();

            // Fetch structured data for all major enterprise modules (Exact schema verified)
            $usersData = $db->query("SELECT id, emp_id, name, email, role, designation, department_name, work_mode, phone, salary_basic, status, joining_date FROM users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $attData = $db->query("SELECT a.id, a.date, u.name as employee_name, u.emp_id, a.clock_in, a.clock_out, a.total_hours, a.status, a.notes, a.tl_approved, a.latitude, a.longitude FROM attendance a JOIN users u ON a.user_id = u.id ORDER BY a.date DESC, a.id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $gpsData = $db->query("SELECT l.id, l.recorded_at, u.name as employee_name, u.emp_id, l.latitude, l.longitude, l.speed as speed_kmh, l.distance_meters FROM employee_travel_logs l JOIN users u ON l.user_id = u.id ORDER BY l.id DESC LIMIT 5000")->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $tasksData = $db->query("SELECT t.id, t.title, p.title as project_name, u.name as assigned_to, creator.name as assigned_by, t.priority, t.status, t.due_date FROM tasks t LEFT JOIN projects p ON t.project_id = p.id LEFT JOIN users u ON t.assigned_to = u.id LEFT JOIN users creator ON t.created_by = creator.id ORDER BY t.id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $leavesData = $db->query("SELECT l.id, u.name as employee_name, u.emp_id, lt.name as leave_type, l.start_date, l.end_date, l.total_days, l.status, l.reason, l.created_at FROM leave_applications l JOIN users u ON l.user_id = u.id JOIN leave_types lt ON l.leave_type_id = lt.id ORDER BY l.id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $payrollData = $db->query("SELECT p.id, u.name as employee_name, u.emp_id, p.month, p.year, p.basic_salary, p.allowances, p.deductions, p.net_salary, p.status, p.payment_date FROM payroll p JOIN users u ON p.user_id = u.id ORDER BY p.id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $sheets = [
                'Workforce & Employees' => $usersData,
                'Attendance Logs' => $attData,
                'Field Travel GPS' => $gpsData,
                'Tasks & Deliverables' => $tasksData,
                'Leave Applications' => $leavesData,
                'Payroll & Salaries' => $payrollData
            ];

            // Build Official Multi-Sheet Excel XML Spreadsheet (Opens directly in MS Excel & Google Sheets)
            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
            $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" '
                 . 'xmlns:o="urn:schemas-microsoft-com:office:office" '
                 . 'xmlns:x="urn:schemas-microsoft-com:office:excel" '
                 . 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" '
                 . 'xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";

            // Excel Styles: Corporate Header & Zebra Rows
            $xml .= '<Styles>' . "\n";
            $xml .= '<Style ss:ID="HeaderStyle">' . "\n";
            $xml .= '<Font ss:Bold="1" ss:Color="#FFFFFF" ss:FontName="Segoe UI" ss:Size="10"/>' . "\n";
            $xml .= '<Interior ss:Color="#4338CA" ss:Pattern="Solid"/>' . "\n";
            $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>' . "\n";
            $xml .= '<Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#312E81"/></Borders>' . "\n";
            $xml .= '</Style>' . "\n";
            $xml .= '<Style ss:ID="DataStyle">' . "\n";
            $xml .= '<Font ss:FontName="Segoe UI" ss:Size="9"/>' . "\n";
            $xml .= '<Alignment ss:Vertical="Center"/>' . "\n";
            $xml .= '</Style>' . "\n";
            $xml .= '</Styles>' . "\n";

            foreach ($sheets as $sheetName => $dataRows) {
                $xml .= '<Worksheet ss:Name="' . htmlspecialchars(substr($sheetName, 0, 31)) . '">' . "\n";
                $xml .= '<Table ss:DefaultRowHeight="20">' . "\n";

                if (!empty($dataRows)) {
                    // 1. Header Row
                    $headers = array_keys($dataRows[0]);
                    $xml .= '<Row ss:Height="24">' . "\n";
                    foreach ($headers as $h) {
                        $colLabel = ucwords(str_replace('_', ' ', $h));
                        $xml .= '<Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">' . htmlspecialchars($colLabel) . '</Data></Cell>' . "\n";
                    }
                    $xml .= '</Row>' . "\n";

                    // 2. Data Rows
                    foreach ($dataRows as $row) {
                        $xml .= '<Row>' . "\n";
                        foreach ($row as $val) {
                            $cellValue = ($val === null) ? '' : (string)$val;
                            $dataType = is_numeric($cellValue) && !preg_match('/^0[0-9]/', $cellValue) ? 'Number' : 'String';
                            $xml .= '<Cell ss:StyleID="DataStyle"><Data ss:Type="' . $dataType . '">' . htmlspecialchars($cellValue) . '</Data></Cell>' . "\n";
                        }
                        $xml .= '</Row>' . "\n";
                    }
                } else {
                    $xml .= '<Row><Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">Status</Data></Cell></Row>' . "\n";
                    $xml .= '<Row><Cell ss:StyleID="DataStyle"><Data ss:Type="String">No records available in this module</Data></Cell></Row>' . "\n";
                }

                $xml .= '</Table>' . "\n";
                $xml .= '</Worksheet>' . "\n";
            }

            $xml .= '</Workbook>';

            $filename = "HRMS_Enterprise_Backup_" . date('Y_m_d_His') . ".xls";
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            echo $xml;
            exit;
                case 'record-travel-gps':
            $user = authUser();
            $attendanceId = (int)($_POST['attendance_id'] ?? 0);
            $lat = (float)($_POST['latitude'] ?? 0);
            $lng = (float)($_POST['longitude'] ?? 0);
            $speed = (float)($_POST['speed'] ?? 0);
            $battery = isset($_POST['battery_level']) ? (int)$_POST['battery_level'] : null;
            $recordedAt = !empty($_POST['recorded_at']) ? trim($_POST['recorded_at']) : date('Y-m-d H:i:s');
            $isOffline = !empty($_POST['is_offline']) ? 1 : 0;

            if ($user && $lat != 0 && $lng != 0) {
                $db = getDBConnection();
                if ($attendanceId <= 0) {
                    $today = date('Y-m-d');
                    $attRow = $db->query("SELECT id FROM attendance WHERE user_id = {$user['id']} AND date = '{$today}' AND clock_out IS NULL ORDER BY id DESC LIMIT 1")->fetch();
                    if ($attRow) $attendanceId = (int)$attRow['id'];
                }

                if ($attendanceId > 0) {
                    // Calculate distance from previous coordinate
                    $prev = $db->query("SELECT latitude, longitude FROM employee_travel_logs WHERE attendance_id = {$attendanceId} ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                    $distMeters = 0;
                    if ($prev) {
                        $distMeters = (int)calculateDistance($lat, $lng, (float)$prev['latitude'], (float)$prev['longitude']);
                    }

                    $stmt = $db->prepare("
                        INSERT INTO employee_travel_logs (attendance_id, user_id, latitude, longitude, speed, battery_level, is_offline_sync, distance_meters, recorded_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$attendanceId, $user['id'], $lat, $lng, $speed, $battery, $isOffline, $distMeters, $recordedAt]);

                    // Update latest location in attendance
                    $db->prepare("UPDATE attendance SET latitude = ?, longitude = ? WHERE id = ?")->execute([$lat, $lng, $attendanceId]);

                    echo json_encode(['success' => true, 'dist' => $distMeters]);
                    exit;
                }
            }
            echo json_encode(['success' => false]);
            exit;

        case 'sync-offline-gps-batch':
            $user = authUser();
            $input = json_decode(file_get_contents('php://input'), true);
            $pings = $input['pings'] ?? [];

            if ($user && !empty($pings)) {
                $db = getDBConnection();
                $today = date('Y-m-d');
                $attRow = $db->query("SELECT id FROM attendance WHERE user_id = {$user['id']} AND date = '{$today}' AND clock_out IS NULL ORDER BY id DESC LIMIT 1")->fetch();
                $attendanceId = $attRow ? (int)$attRow['id'] : 0;

                if ($attendanceId > 0) {
                    $stmt = $db->prepare("
                        INSERT INTO employee_travel_logs (attendance_id, user_id, latitude, longitude, speed, battery_level, is_offline_sync, distance_meters, recorded_at) 
                        VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)
                    ");

                    $syncedCount = 0;
                    $lastLat = 0;
                    $lastLng = 0;

                    foreach ($pings as $p) {
                        $pLat = (float)($p['latitude'] ?? 0);
                        $pLng = (float)($p['longitude'] ?? 0);
                        $pSpeed = (float)($p['speed'] ?? 0);
                        $pBattery = isset($p['battery_level']) ? (int)$p['battery_level'] : null;
                        $pTime = !empty($p['recorded_at']) ? $p['recorded_at'] : date('Y-m-d H:i:s');
                        $pDist = (int)($p['distance_meters'] ?? 0);

                        if ($pLat != 0 && $pLng != 0) {
                            $stmt->execute([$attendanceId, $user['id'], $pLat, $pLng, $pSpeed, $pBattery, $pDist, $pTime]);
                            $syncedCount++;
                            $lastLat = $pLat;
                            $lastLng = $pLng;
                        }
                    }

                    if ($lastLat != 0 && $lastLng != 0) {
                        $db->prepare("UPDATE attendance SET latitude = ?, longitude = ? WHERE id = ?")->execute([$lastLat, $lastLng, $attendanceId]);
                    }

                    echo json_encode(['success' => true, 'synced_count' => $syncedCount]);
                    exit;
                }
            }
            echo json_encode(['success' => false, 'message' => 'No active shift or empty pings']);
            exit;

        case 'create-role': RoleController::create(); break;
    case 'delete-role': RoleController::delete(); break;
    case 'apply-wfh': WfhController::apply(); break;
    case 'review-wfh': WfhController::review(); break;
    case 'set-hr-wfh': WfhController::setHrWfhRange(); break;
    case 'export-calling-history':
        CallingController::exportHistory();
        break;
    case 'upload-calling-leads': CallingController::uploadLeads(); break;
    case 'update-calling-disposition': CallingController::updateDisposition(); break;
    case 'upload-smart-sheet': SmartSheetController::upload(); break;
    case 'log-travel-coordinate': AttendanceController::logTravelCoordinate(); break;
    case 'get-travel-logs': AttendanceController::getTravelLogs(); break;
    case 'login':
            AuthController::login();
            exit;
        case 'logout':
            AuthController::logout();
            exit;
        case 'force-logout-user':
            AuthController::forceLogoutUser();
            exit;
        case 'download-db-backup':
            requireRole('admin');
            $db = getDBConnection();
            $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
            
            if ($driver === 'mysql') {
                $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
            }

            $sqlDump = "-- ========================================================\n";
            $sqlDump .= "-- HRMS Cloud Database SQL Backup\n";
            $sqlDump .= "-- Generated on: " . date('Y-m-d H:i:s T') . "\n";
            $sqlDump .= "-- Database Engine: " . strtoupper($driver) . "\n";
            $sqlDump .= "-- Total Tables: " . count($tables) . "\n";
            $sqlDump .= "-- ========================================================\n\n";
            if ($driver === 'mysql') {
                $sqlDump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
            }

            foreach ($tables as $t) {
                $sqlDump .= "-- --------------------------------------------------------\n";
                $sqlDump .= "-- Table structure and rows for `{$t}`\n";
                $sqlDump .= "-- --------------------------------------------------------\n";

                if ($driver === 'mysql') {
                    $createRow = $db->query("SHOW CREATE TABLE `{$t}`")->fetch(PDO::FETCH_NUM);
                    if ($createRow && isset($createRow[1])) {
                        $sqlDump .= "DROP TABLE IF EXISTS `{$t}`;\n";
                        $sqlDump .= $createRow[1] . ";\n\n";
                    }
                }

                $rows = $db->query("SELECT * FROM `{$t}`")->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($rows)) {
                    foreach ($rows as $row) {
                        $cols = array_keys($row);
                        $escapedCols = array_map(fn($c) => "`$c`", $cols);
                        $escapedVals = array_map(function($v) use ($db) {
                            if ($v === null) return "NULL";
                            return $db->quote($v);
                        }, array_values($row));

                        $sqlDump .= "INSERT INTO `{$t}` (" . implode(", ", $escapedCols) . ") VALUES (" . implode(", ", $escapedVals) . ");\n";
                    }
                    $sqlDump .= "\n";
                }
            }

            if ($driver === 'mysql') {
                $sqlDump .= "SET FOREIGN_KEY_CHECKS = 1;\n";
            }
            $sqlDump .= "-- Backup completed successfully.\n";

            $filename = "hrms_cloud_backup_" . date('Y-m-d_His') . ".sql";
            header('Content-Type: application/sql; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($sqlDump));
            header('Pragma: no-cache');
            header('Expires: 0');
            echo $sqlDump;
            exit;
        case 'clock-in':
            AttendanceController::clockIn();
            exit;
        case 'clock-out':
            AttendanceController::clockOut();
            exit;
        case 'tl-approve-attendance':
            requireActiveShift();
            AttendanceController::tlApprove();
            exit;
        case 'hr-edit-attendance':
            requireActiveShift();
            AttendanceController::hrEditAttendance();
            exit;
        case 'post-tl-feedback':
            requireActiveShift();
            FeedbackController::postTLFeedback();
            exit;
        case 'ack-tl-feedback':
            FeedbackController::acknowledgeFeedback();
            exit;
        case 'ack-hr-warning':
            requireAuth();
            $currUser = authUser();
            $db->prepare("UPDATE users SET hr_warning_message = NULL WHERE id = ?")
               ->execute([$currUser['id']]);
            setFlash('success', 'HR Disciplinary Notice acknowledged.');
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=dashboard'));
        case 'ack-new-tl-notice':
            requireAuth();
            $currUser = authUser();
            $db->prepare("UPDATE users SET new_tl_notice = NULL WHERE id = ?")
               ->execute([$currUser['id']]);
            setFlash('success', 'Team Lead update acknowledged.');
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=dashboard'));
            exit;
        case 'acknowledge-force-logout':
            requireAuth();
            $currUser = authUser();
            $attId = (int)($_POST['attendance_id'] ?? 0);
            if ($attId > 0) {
                $db->prepare("UPDATE attendance SET force_logout_acknowledged = 1 WHERE id = ? AND user_id = ?")
                   ->execute([$attId, $currUser['id']]);
                setFlash('success', 'Attendance warning acknowledged.');
            }
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=dashboard'));
            exit;
        case 'create-task':
            requireActiveShift();
            TaskController::create();
            exit;
        case 'extend-deadline':
            requireActiveShift();
            TaskController::extendDeadline();
            exit;
        case 'update-task-status':
            requireActiveShift();
            TaskController::updateStatus();
            exit;
        case 'submit-work':
            requireActiveShift();
            TaskController::submitWork();
            exit;
        case 'review-submission':
            requireActiveShift();
            TaskController::reviewSubmission();
            exit;
        case 'apply-leave':
            LeaveController::apply();
            exit;
        case 'tl-review-leave':
            LeaveController::tlReview();
            exit;
        case 'hr-action-leave':
            LeaveController::hrAction();
            exit;
        case 'create-employee':
            requireActiveShift();
            EmployeeController::create();
            exit;
        case 'update-employee':
            requireActiveShift();
            EmployeeController::update();
            exit;
        case 'delete-employee':
            requireActiveShift();
            EmployeeController::delete();
            exit;
        case 'assign-tl-team':
            requireActiveShift();
            EmployeeController::assignTeam();
            exit;
        case 'assign-tl-location':
            requireRole(['admin']);
            requireActiveShift();
            $tlId = (int)($_POST['tl_id'] ?? 0);
            $locationId = (int)($_POST['location_id'] ?? 0);
            $assignmentType = $_POST['assignment_type'] ?? 'permanent';
            $loc = getOfficeLocationById($locationId);

            $tlUser = $db->query("SELECT * FROM users WHERE id = {$tlId}")->fetch(PDO::FETCH_ASSOC);

            if ($tlId > 0 && $loc && $tlUser) {
                $currentPermId = (int)($tlUser['assigned_office_location'] ?: 2);

                if ($assignmentType === 'temporary') {
                    // Check if selected location is already the permanent location
                    if ($locationId === $currentPermId) {
                        setFlash('error', "❌ Invalid Temporary Location: '{$loc['name']}' is already {$tlUser['name']}'s Permanent Office. For a temporary override, please select a different office location.");
                        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=admin-tl-reports&tab=progress'));
                        exit;
                    }

                    $tempStartDate = !empty($_POST['temp_start_date']) ? $_POST['temp_start_date'] : date('Y-m-d');
                    $tempEndDate = !empty($_POST['temp_end_date']) ? $_POST['temp_end_date'] : date('Y-m-d', strtotime('+3 days'));
                    
                    $d1 = strtotime($tempStartDate);
                    $d2 = strtotime($tempEndDate);
                    $tempDays = max(1, (int)round(($d2 - $d1) / 86400) + 1);
                    $expiresAt = $tempEndDate;

                    $db->prepare("
                        UPDATE users SET 
                        temp_office_location = ?,
                        temp_location_expires_at = ?,
                        temp_location_days = ?
                        WHERE id = ?
                    ")->execute([$locationId, $expiresAt, $tempDays, $tlId]);

                    $tlName = $db->query("SELECT name FROM users WHERE id = {$tlId}")->fetchColumn() ?: 'Team Lead';
                                                            // Fetch entire team (TL, TL Support, Team Members) for notification
                    $teamMembers = $db->query("SELECT name, email, whatsapp_number, phone FROM users WHERE (id = {$tlId} OR reporting_tl_id = {$tlId}) AND status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
                    
                    $memberNamesList = [];
                    foreach ($teamMembers as $m) {
                        $memberNamesList[] = "• " . $m['name'];
                    }
                    $memberNamesText = implode("\n", $memberNamesList);

                    $hrName = authUser()['name'] ?? 'Head HR';
                    $hrDesig = authUser()['designation'] ?: 'HR Leadership';
                    $todayFormatted = date('d M Y');
                    $expiresFormatted = !empty($expiresAt) ? formatDate($expiresAt) : '';

                    if ($assignmentType === 'temporary') {
                        $durationText = "Temporary for {$tempDays} Day(s) (From {$todayFormatted} till {$expiresFormatted})";
                    } else {
                        $durationText = "Permanent (Effective from {$todayFormatted})";
                    }

                    $waBroadcastText = "🏢 *ECOVISTA GLOBAL PVT. LTD. - OFFICIAL DIRECTIVE*\n\n"
                                     . "📢 *ATTENTION: {$tlName} & Team Members*\n\n"
                                     . "This is an official announcement from *{$hrName} ({$hrDesig})*.\n\n"
                                     . "📍 *New Reporting Office:* {$loc['name']}\n"
                                     . "📅 *Duration:* {$durationText}\n\n"
                                     . "👥 *Reporting Team:*\n"
                                     . "{$memberNamesText}\n\n"
                                     . "⚠️ *Instruction:* All team members must report to this office location and complete attendance punch-in.\n\n"
                                     . "Regards,\n*HR & Operations Department*\n*Ecovista Global Private Limited*";

                    $_SESSION['wa_broadcast_text'] = $waBroadcastText;
                    $_SESSION['wa_broadcast_tl'] = $tlName;
                    $_SESSION['wa_broadcast_loc'] = $loc['name'];

                    $notifCount = sendAutomatedTeamLocationNotifications(authUser(), $tlName, $teamMembers, $loc['name'], $assignmentType, $tempDays, $expiresAt);
                    setFlash('success', "📍 Location updated! Official notification sent to {$notifCount} team member(s) via Email. You can also share the announcement directly to WhatsApp Group below.");
                    // Original fallback
                    $teamMembers = $db->query("SELECT name, email, whatsapp_number, phone FROM users WHERE (id = {$tlId} OR reporting_tl_id = {$tlId}) AND status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
                    $notifCount = sendAutomatedTeamLocationNotifications(authUser(), $tlName, $teamMembers, $loc['name'], $assignmentType, $tempDays, $expiresAt);
                    setFlash('success', "📍 Temporary location saved! Formal notification automatically sent to {$notifCount} team member(s) via Email & WhatsApp (Valid for {$tempDays} day(s) till " . formatDate($expiresAt) . ").");
                } else {
                    $db->prepare("
                        UPDATE users SET 
                        assigned_office_location = ?,
                        temp_office_location = NULL,
                        temp_location_expires_at = NULL,
                        temp_location_days = NULL
                        WHERE id = ?
                    ")->execute([$locationId, $tlId, $tlId]);

                    $tlName = $db->query("SELECT name FROM users WHERE id = {$tlId}")->fetchColumn() ?: 'Team Lead';
                                        $teamMembers = $db->query("SELECT name, email, whatsapp_number, phone FROM users WHERE (id = {$tlId} OR reporting_tl_id = {$tlId}) AND status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
                    $notifCount = sendAutomatedTeamLocationNotifications(authUser(), $tlName, $teamMembers, $loc['name'], 'permanent', 0, null);
                    setFlash('success', "📍 Permanent location saved! Formal notification automatically sent to {$notifCount} team member(s) via Email & WhatsApp.");
                }
            } else {
                setFlash('error', 'Invalid Team Lead or location selected.');
            }
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=admin-tl-reports'));
            exit;
        case 'clear-temp-tl-location':
            requireRole(['admin']);
            requireActiveShift();
            $tlId = (int)($_POST['tl_id'] ?? 0);
            if ($tlId > 0) {
                $db->prepare("UPDATE users SET temp_office_location = NULL, temp_location_expires_at = NULL, temp_location_days = NULL WHERE id = ?")
                   ->execute([$tlId]);
                setFlash('success', '📍 Temporary location cleared. Team has reverted to their permanent office location.');
            }
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=admin-tl-reports'));
            exit;
        case 'create-escalation':
            requireRole(['team_lead', 'admin']);
            requireActiveShift();
            $tlId = authUser()['id'];
            $employeeId = (int)($_POST['employee_id'] ?? 0);
            $category = trim($_POST['category'] ?? 'performance');
            $severity = trim($_POST['severity'] ?? 'medium');
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if ($employeeId > 0 && !empty($title) && !empty($description)) {
                // 1. Insert Escalation record
                $stmt = $db->prepare("INSERT INTO employee_escalations (tl_id, employee_id, category, severity, title, description, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$tlId, $employeeId, $category, $severity, $title, $description]);

                // 2. Flag employee account so once they log out, they cannot log in again until TL approves
                $db->prepare("UPDATE users SET is_escalated_locked = 1, escalated_lock_reason = ?, escalated_by_tl_id = ? WHERE id = ?")
                   ->execute([$title, $tlId, $employeeId]);

                setFlash('success', '🚨 Complaint submitted to HR! The employee remains in their active shift; once they log out / punch out, their next login will be locked until approved by you.');
            } else {
                setFlash('error', 'Please fill in all required escalation details.');
            }
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=tl-dashboard'));
            exit;
        case 'unlock-employee':
            requireRole(['team_lead', 'admin']);
            requireActiveShift();
            $targetEmpId = (int)($_POST['employee_id'] ?? 0);
            $empStmt = $db->prepare("SELECT name FROM users WHERE id = ?");
            $empStmt->execute([$targetEmpId]);
            $empRow = $empStmt->fetch();

            if ($targetEmpId > 0 && $empRow) {
                $db->prepare("UPDATE users SET is_escalated_locked = 0, escalated_lock_reason = NULL WHERE id = ?")
                   ->execute([$targetEmpId]);
                setFlash('success', "🔓 Access Granted! {$empRow['name']} is now unlocked and allowed to log in.");
            } else {
                setFlash('error', 'Invalid employee selected.');
            }
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=tl-dashboard'));
            exit;
        case 'resolve-escalation':
            requireRole(['admin']);
            requireActiveShift();
            $hrId = authUser()['id'];
            $escalationId = (int)($_POST['escalation_id'] ?? 0);
            $status = trim($_POST['status'] ?? 'resolved');
            $hrResponse = trim($_POST['hr_response'] ?? '');

            if ($escalationId > 0) {
                // 1. Fetch escalation details
                $esc = $db->query("SELECT * FROM employee_escalations WHERE id = $escalationId")->fetch();
                if ($esc) {
                    $targetEmpId = (int)$esc['employee_id'];

                    $nowDateTime = date('Y-m-d H:i:s');
                    // 2. Update escalation record
                    $stmt = $db->prepare("UPDATE employee_escalations SET status = ?, hr_response = ?, hr_action_by = ?, hr_action_at = ? WHERE id = ?");
                    $stmt->execute([$status, $hrResponse, $hrId, $nowDateTime, $escalationId]);

                    // 3. Apply Decision to Employee
                    if ($status === 'dismissed') {
                        // Dismiss employee from company
                        $db->prepare("UPDATE users SET is_dismissed = 1, status = 'inactive', dismissal_reason = ?, is_escalated_locked = 1, force_logout_at = ? WHERE id = ?")
                           ->execute([$hrResponse, $nowDateTime, $targetEmpId]);
                        
                        // Terminate today's shift if active
                        $today = date('Y-m-d');
                        $nowTime = date('H:i:s');
                        $db->prepare("UPDATE attendance SET clock_out = ?, notes = 'Dismissed by HR' WHERE user_id = ? AND date = ? AND clock_out IS NULL")
                           ->execute([$nowTime, $targetEmpId, $today]);
                        $db->prepare("UPDATE attendance_sessions SET clock_out = ?, ended_by = 'force_logout', ended_by_user_id = ? WHERE user_id = ? AND date = ? AND clock_out IS NULL")
                           ->execute([$nowTime, $hrId, $targetEmpId, $today]);

                        setFlash('success', '🚫 Decision Recorded: Employee has been DISMISSED and login access is terminated.');
                    } elseif ($status === 'action_taken') {
                        // Formal warning issued
                        $db->prepare("UPDATE users SET is_dismissed = 0, status = 'active', hr_warning_message = ?, is_escalated_locked = 0 WHERE id = ?")
                           ->execute([$hrResponse, $targetEmpId]);
                        setFlash('success', '⚠️ Decision Recorded: Formal Disciplinary Warning issued to employee.');
                    } elseif ($status === 'resolved') {
                        // Issue resolved, clear locks
                        $db->prepare("UPDATE users SET is_dismissed = 0, status = 'active', dismissal_reason = NULL, hr_warning_message = NULL, is_escalated_locked = 0 WHERE id = ?")
                           ->execute([$targetEmpId]);
                        setFlash('success', '✅ Decision Recorded: Case marked as Resolved. Employee access restored.');
                    } else {
                        setFlash('success', '🔍 Decision Recorded: Case placed under HR Investigation.');
                    }

                    // 4. Send Instant Directive / Alert to the Referring Team Lead
                    $empRow = $db->query("SELECT name FROM users WHERE id = $targetEmpId")->fetch();
                    $empName = $empRow['name'] ?? 'Team Member';
                    $statusLabel = [
                        'dismissed' => '🚫 Terminated / Dismissed from Company',
                        'action_taken' => '⚠️ Formal Disciplinary Warning Issued',
                        'resolved' => '✅ Issue Resolved & Restored',
                        'under_review' => '🔍 Under HR Investigation'
                    ][$status] ?? ucfirst(str_replace('_', ' ', $status));

                    $directiveMsg = "HR Action on your Referral for {$empName}:\nStatus: {$statusLabel}\nOfficial Remarks: " . ($hrResponse ?: 'No additional notes provided.');
                    $priority = in_array($status, ['dismissed', 'action_taken']) ? 'urgent' : 'important';

                    $db->prepare("INSERT INTO tl_feedbacks (tl_id, hr_id, message, priority, status) VALUES (?, ?, ?, ?, 'unread')")
                       ->execute([$esc['tl_id'], $hrId, $directiveMsg, $priority]);
                }
            }
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=admin-tl-reports'));
            exit;
        case 'create-project':
            requireActiveShift();
            ProjectController::create();
            exit;
        case 'update-profile':
            ProfileController::updateProfile();
            exit;
        case 'request-email-change':
            ProfileController::requestEmailChange();
            exit;
        case 'verify-email-change':
            ProfileController::verifyEmailChange();
            exit;
        case 'cancel-email-change':
            ProfileController::cancelEmailChange();
            exit;
        case 'drive-create-folder':
            DriveController::createFolder();
            exit;
        case 'drive-upload':
            DriveController::uploadFile();
            exit;
        case 'drive-delete':
            DriveController::deleteItem();
            exit;
        case 'drive-settings':
            DriveController::updateSettings();
            exit;
        case 'drive-oauth-start':
            DriveController::startOAuth();
            exit;
        case 'drive-oauth-callback':
            DriveController::handleOAuthCallback();
            exit;
        case 'drive-sync':
            DriveController::syncWithGoogleDrive();
            exit;
        case 'drive-auto-sync-json':
            DriveController::autoSyncJson();
            exit;
        case 'drive-stream':
            DriveController::streamFile();
            exit;
        case 'drive-download-zip':
            DriveController::downloadZip();
            exit;
        case 'drive-disconnect':
            DriveController::disconnectDrive();
            exit;
        case 'verify-otp':
            AuthController::verifyOtp();
            exit;
        case 'resend-otp':
            AuthController::resendOtp();
            exit;
        case 'live-heartbeat':
            header('Content-Type: application/json');
            if (!isLoggedIn()) {
                echo json_encode(['authenticated' => false]);
                exit;
            }
            $hbUser = authUser();
            $hbDb = getDBConnection();
            $today = date('Y-m-d');
            $now = date('Y-m-d H:i:s');

            // 1. Update live presence timestamp (Lightweight)
            $hbDb->prepare("UPDATE users SET last_seen_at = ? WHERE id = ?")->execute([$now, $hbUser['id']]);

            // 2. Check if user account was terminated/dismissed
            $hbChk = $hbDb->prepare("SELECT status, is_dismissed, is_escalated_locked, force_logout_at FROM users WHERE id = ?");
            $hbChk->execute([$hbUser['id']]);
            $uLive = $hbChk->fetch();
            if (!$uLive || $uLive['status'] === 'inactive' || !empty($uLive['is_dismissed'])) {
                echo json_encode(['authenticated' => false, 'force_logout' => true]);
                exit;
            }

            echo json_encode([
                'authenticated' => true,
                'user_id' => (int)$hbUser['id'],
                'status' => 'ok'
            ]);
            exit;
        case 'submit-daily-report':
            requireActiveShift();
            ReportController::submitReport();
            exit;
        case 'review-daily-report':
            requireActiveShift();
            ReportController::reviewReport();
            exit;
    }
}

// Unauthenticated redirect
if (!isLoggedIn()) {
    if ($page === 'verify-otp') {
        AuthController::showVerifyOtp();
        exit;
    }
    require __DIR__ . '/../views/auth/login.php';
    exit;
}

$user = authUser();
$role = $user['role'];

// Default dashboard redirect
if (!$page || $page === 'dashboard' || $page === 'login') {
    if ($role === 'team_lead') {
        $page = 'tl-dashboard';
    } elseif ($role === 'admin') {
        $page = 'admin-dashboard';
    } else {
        $page = 'employee-dashboard';
    }
}

// Page Routing
$viewMap = [
    // TL Pages
    'tl-dashboard' => ['file' => 'tl/dashboard.php', 'roles' => ['team_lead', 'admin']],
    'tl-attendance' => ['file' => 'tl/attendance.php', 'roles' => ['team_lead', 'admin']],
    'tl-tasks' => ['file' => 'tl/tasks.php', 'roles' => ['team_lead', 'admin']],
    'tl-leaves' => ['file' => 'tl/leaves.php', 'roles' => ['team_lead', 'admin']],
    'tl-reports' => ['file' => 'tl/reports.php', 'roles' => ['team_lead', 'admin']],
    'tl-travel-radar' => ['file' => 'admin/travel_radar.php', 'roles' => ['team_lead', 'admin']],

    // Employee Pages
    'employee-dashboard' => ['file' => 'employee/dashboard.php', 'roles' => ['employee', 'team_lead', 'admin']],
    'employee-tasks' => ['file' => 'employee/tasks.php', 'roles' => ['employee', 'team_lead', 'admin']],
    'employee-reports' => ['file' => 'employee/tasks.php', 'roles' => ['employee', 'team_lead', 'admin']],
    'employee-attendance' => ['file' => 'employee/attendance.php', 'roles' => ['employee', 'team_lead', 'admin']],
    'employee-leaves' => ['file' => 'employee/leaves.php', 'roles' => ['employee', 'team_lead', 'admin']],
    'employee-wfh' => ['file' => 'employee/wfh_request.php', 'roles' => ['employee', 'team_lead', 'admin']],
    'calling-queue' => ['file' => 'calling/queue.php', 'roles' => ['employee', 'team_lead', 'admin']],

    // Admin Pages
    'admin-dashboard' => ['file' => 'admin/dashboard.php', 'roles' => ['admin']],
    'admin-employees' => ['file' => 'admin/employees.php', 'roles' => ['admin']],
    'admin-roles' => ['file' => 'admin/roles.php', 'roles' => ['admin']],
    'admin-attendance' => ['file' => 'admin/attendance.php', 'roles' => ['admin']],
    'admin-travel-radar' => ['file' => 'admin/travel_radar.php', 'roles' => ['admin', 'team_lead']],
    'admin-wfh' => ['file' => 'admin/wfh_approvals.php', 'roles' => ['admin', 'team_lead']],
    'admin-leaves' => ['file' => 'admin/leaves.php', 'roles' => ['admin']],
    'admin-smart-sheets' => ['file' => 'admin/smart_sheets.php', 'roles' => ['admin']],
    'calling-manage' => ['file' => 'calling/manage.php', 'roles' => ['admin', 'team_lead']],
    'admin-tl-reports' => ['file' => 'admin/tl_reports.php', 'roles' => ['admin']],

    // Tech Cloud Drive (Google Drive Integration)
    'tech-drive' => ['file' => 'tech/drive.php', 'roles' => ['admin', 'team_lead', 'employee']],
    'drive' => ['file' => 'tech/drive.php', 'roles' => ['admin', 'team_lead', 'employee']],

    // Global Profile Page (All roles)
    'profile' => ['file' => 'profile.php', 'roles' => ['admin', 'team_lead', 'employee']],

    // Convenient Aliases
    'apply-leave' => ['file' => 'employee/leaves.php', 'roles' => ['employee', 'team_lead', 'admin']],
    'leaves' => ['file' => 'employee/leaves.php', 'roles' => ['employee', 'team_lead', 'admin']],
    'tasks' => ['file' => 'employee/tasks.php', 'roles' => ['employee', 'team_lead', 'admin']],
    'attendance' => ['file' => 'employee/attendance.php', 'roles' => ['employee', 'team_lead', 'admin']],
];

if (!isset($viewMap[$page])) {
    $page = ($role === 'team_lead' ? 'tl-dashboard' : ($role === 'admin' ? 'admin-dashboard' : 'employee-dashboard'));
}

$route = $viewMap[$page];

// Permission check
if (!in_array($role, $route['roles'], true)) {
    setFlash('error', 'Unauthorized page access.');
    header('Location: ?page=dashboard');
    exit;
}

// Render Page: Fast AJAX partial or full layout
$isAjax = !empty($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if ($isAjax) {
    // Fast AJAX: Render ONLY the inner view without headers, sidebar or footers
    require __DIR__ . '/../views/' . $route['file'];
    exit;
}

require __DIR__ . '/../views/layouts/header.php';
require __DIR__ . '/../views/layouts/sidebar.php';
require __DIR__ . '/../views/' . $route['file'];
require __DIR__ . '/../views/layouts/footer.php';
