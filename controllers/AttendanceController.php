<?php
// controllers/AttendanceController.php

class AttendanceController {
                public static function clockIn(): void {
        requireAuth();
        $user = authUser();
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        $status = $_POST['status'] ?? 'present';
        $notes = trim($_POST['notes'] ?? '');
        $userLat = isset($_POST['latitude']) && $_POST['latitude'] !== '' && $_POST['latitude'] != 0 ? (float)$_POST['latitude'] : null;
        $userLng = isset($_POST['longitude']) && $_POST['longitude'] !== '' && $_POST['longitude'] != 0 ? (float)$_POST['longitude'] : null;

        $db = getDBConnection();
        $isAdmin = (($user['role'] ?? '') === 'admin');
        $workMode = $user['work_mode'] ?? 'office';
        $isGeofenceExempt = $isAdmin || $workMode === 'field' || $workMode === 'wfh' || $status === 'wfh';

        // Geofence check ONLY for regular in-office staff
        if (GEOFENCE_ENABLED && !$isGeofenceExempt && $status === 'present') {
            if ($userLat === null || $userLng === null) {
                setFlash('error', '📍 Location access denied! Please allow location permission in your browser to punch in from office.');
                header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=dashboard'));
                exit;
            }

            $officeLocation = getEffectiveUserLocation((int)$user['id']);
            if (!$officeLocation) {
                setFlash('error', '📍 No office location assigned to your team yet. Please contact HR.');
                header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=dashboard'));
                exit;
            }

            $distance = self::calculateDistance($userLat, $userLng, $officeLocation['lat'], $officeLocation['lng']);
            if ($distance > GEOFENCE_RADIUS_METERS) {
                $distKm = round($distance / 1000, 1);
                $locName = $officeLocation['name'];
                setFlash('error', "📍 Out of Office Location! You are {$distKm} km away from '{$locName}'. Please go to the office to punch in, or select Work From Home.");
                header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=dashboard'));
                exit;
            }
        }

        $stmt = $db->prepare("SELECT id, locked_by_hr, force_logged_out_by, clock_out, clock_in FROM attendance WHERE user_id = ? AND date = ?");
        $stmt->execute([$user['id'], $today]);
        $existing = $stmt->fetch();

        if ($existing) {
            if ($existing['locked_by_hr']) {
                setFlash('error', 'Attendance for today is locked and managed by HR.');
            } elseif ($existing['clock_out'] !== null) {
                // Re-punch for new session
                $db->prepare("
                    UPDATE attendance 
                    SET clock_in = ?, clock_out = NULL, status = ?, notes = ?,
                        punch_in_lat = COALESCE(?, punch_in_lat), punch_in_lng = COALESCE(?, punch_in_lng),
                        tl_approved = 0, force_logged_out_by = NULL, force_logout_at = NULL
                    WHERE id = ?
                ")->execute([$now, $status, $notes, $userLat, $userLng, $existing['id']]);

                // Get next session number
                $maxSess = (int)$db->query("SELECT COALESCE(MAX(session_number), 0) FROM attendance_sessions WHERE attendance_id = {$existing['id']}")->fetchColumn();
                $sessNum = $maxSess + 1;

                $db->prepare("INSERT INTO attendance_sessions (attendance_id, user_id, session_number, clock_in, punch_in_lat, punch_in_lng) VALUES (?, ?, ?, ?, ?, ?)")
                   ->execute([$existing['id'], $user['id'], $sessNum, $now, $userLat, $userLng]);

                setFlash('success', 'Clocked in at ' . date('h:i A') . ' (Session #' . $sessNum . ')');
            } else {
                setFlash('error', 'You are already clocked in! Punch out first to end your current session.');
            }
        } else {
            $insert = $db->prepare("INSERT INTO attendance (user_id, date, clock_in, status, notes, punch_in_lat, punch_in_lng, latitude, longitude, tl_approved, hr_corrected, locked_by_hr) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0)");
            $insert->execute([$user['id'], $today, $now, $status, $notes, $userLat, $userLng, $userLat, $userLng]);
            $attId = $db->lastInsertId();

            // Create Session #1
            $db->prepare("INSERT INTO attendance_sessions (attendance_id, user_id, session_number, clock_in, punch_in_lat, punch_in_lng) VALUES (?, ?, 1, ?, ?, ?)")
               ->execute([$attId, $user['id'], $now, $userLat, $userLng]);

            setFlash('success', 'Clocked in successfully at ' . date('h:i A') . '!');
        }

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=dashboard'));
        exit;
    }

    /**
     * Calculate distance between two GPS coordinates using Haversine formula
     * Returns distance in meters
     */
    private static function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float {
        $earthRadius = 6371000; // meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

                public static function clockOut(): void {
        requireAuth();
        $user = authUser();
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        $userLat = isset($_POST['latitude']) && $_POST['latitude'] !== '' && $_POST['latitude'] != 0 ? (float)$_POST['latitude'] : null;
        $userLng = isset($_POST['longitude']) && $_POST['longitude'] !== '' && $_POST['longitude'] != 0 ? (float)$_POST['longitude'] : null;

        $db = getDBConnection();
        $stmt = $db->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
        $stmt->execute([$user['id'], $today]);
        $att = $stmt->fetch();

        if (!$att) {
            setFlash('error', 'No active punch found for today.');
        } elseif ($att['locked_by_hr']) {
            setFlash('error', 'Attendance is locked by HR.');
        } elseif ($att['clock_out'] !== null) {
            setFlash('error', 'You have already clocked out for today.');
        } else {
            // Find active session
            $activeSess = $db->query("SELECT * FROM attendance_sessions WHERE attendance_id = {$att['id']} AND clock_out IS NULL ORDER BY id DESC LIMIT 1")->fetch();
            $sessInTime = $activeSess ? strtotime($activeSess['clock_in']) : strtotime($att['clock_in'] ?: $now);
            $outTime = strtotime($now);
            $sessionHours = round(max(0, $outTime - $sessInTime) / 3600, 2);

            // Close active session
            $db->prepare("
                UPDATE attendance_sessions 
                SET clock_out = ?, hours = ?, punch_out_lat = ?, punch_out_lng = ?, ended_by = 'self'
                WHERE attendance_id = ? AND clock_out IS NULL
            ")->execute([$now, $sessionHours, $userLat, $userLng, $att['id']]);

            // Calculate total hours from ALL sessions
            $totalHours = (float)$db->query("SELECT COALESCE(SUM(hours), 0) FROM attendance_sessions WHERE attendance_id = {$att['id']}")->fetchColumn();
            if ($totalHours == 0) $totalHours = $sessionHours;

            $update = $db->prepare("UPDATE attendance SET clock_out = ?, total_hours = ?, punch_out_lat = ?, punch_out_lng = ? WHERE id = ?");
            $update->execute([$now, $totalHours, $userLat, $userLng, $att['id']]);

            TaskController::autoSubmitOnShiftEnd($user['id']);

            // If Team Lead punches out, auto-compile and transmit Daily TL Executive Report to HR
            if ($user['role'] === 'team_lead') {
                ReportController::autoSubmitTLReportOnShiftEnd($user['id'], $totalHours);
            }

            setFlash('success', 'Clocked out at ' . date('h:i A') . ". Total Shift Hours: {$totalHours} hrs");
        }

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=dashboard'));
        exit;
    }

    public static function tlApprove(): void {
        requireRole(['team_lead', 'admin']);
        $user = authUser();
        $attendanceId = (int)($_POST['attendance_id'] ?? 0);
        $date = $_POST['date'] ?? date('Y-m-d');
        $userId = (int)($_POST['user_id'] ?? 0);
        
        $status = $_POST['status'] ?? 'present';
        $clockInTime = $_POST['clock_in_time'] ?? '';
        $clockOutTime = $_POST['clock_out_time'] ?? '';
        $hours = (float)($_POST['total_hours'] ?? 8);
        $notes = trim($_POST['notes'] ?? '');

        $db = getDBConnection();
        
        // Check if locked by HR
        if ($attendanceId > 0) {
            $check = $db->query("SELECT locked_by_hr FROM attendance WHERE id = {$attendanceId}")->fetch();
            if ($check && $check['locked_by_hr'] && !isAdmin()) {
                setFlash('error', 'This record is locked & corrected by HR. Only HR can modify it.');
                header('Location: ?page=tl-attendance');
                exit;
            }
        }

        $existingRec = null;
        if ($attendanceId > 0) {
            $existingRec = $db->query("SELECT * FROM attendance WHERE id = {$attendanceId}")->fetch();
        } else {
            $stmtCheck = $db->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
            $stmtCheck->execute([$userId, $date]);
            $existingRec = $stmtCheck->fetch();
        }

        if ($status === 'absent') {
            $clockIn = null;
            $clockOut = null;
            $hours = 0;
        } else {
            $clockIn = !empty($clockInTime) ? "{$date} {$clockInTime}:00" : ($existingRec['clock_in'] ?? "{$date} 09:30:00");
            
            // If active shift today and no out time entered/recorded, keep clock_out NULL so employee stays in active shift!
            if ($date === date('Y-m-d') && empty($clockOutTime) && empty($existingRec['clock_out'])) {
                $clockOut = null;
                $hours = (float)($existingRec['total_hours'] ?? 0);
            } else {
                $clockOut = !empty($clockOutTime) ? "{$date} {$clockOutTime}:00" : ($existingRec['clock_out'] ?? null);
                if (!empty($clockIn) && !empty($clockOut)) {
                    $inTs = strtotime($clockIn);
                    $outTs = strtotime($clockOut);
                    $hours = ($outTs > $inTs) ? round(($outTs - $inTs) / 3600, 2) : 0;
                } else {
                    $hours = (float)($existingRec['total_hours'] ?? 0);
                }
            }
        }

        $now = date('Y-m-d H:i:s');

        try {
            $stmt = $db->prepare("
                INSERT INTO attendance (user_id, date, clock_in, clock_out, total_hours, status, notes, tl_approved, tl_approved_at, tl_approved_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
                ON CONFLICT(user_id, date) DO UPDATE SET
                clock_in = excluded.clock_in,
                clock_out = excluded.clock_out,
                total_hours = excluded.total_hours,
                status = excluded.status,
                notes = excluded.notes,
                tl_approved = 1,
                tl_approved_at = excluded.tl_approved_at,
                tl_approved_by = excluded.tl_approved_by
            ");
            $stmt->execute([$userId, $date, $clockIn, $clockOut, $hours, $status, $notes, $now, $user['id']]);
            setFlash('success', 'Attendance verified and approved! Active shift preserved.');
        } catch (Exception $e) {
            setFlash('error', 'Error updating attendance: ' . $e->getMessage());
        }

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '?page=tl-attendance'));
        exit;
    }

    public static function hrEditAttendance(): void {
        requireRole(['admin']);
        $user = authUser();
        $userId = (int)($_POST['user_id'] ?? 0);
        $date = $_POST['date'] ?? date('Y-m-d');
        $status = $_POST['status'] ?? 'present';
        $clockInTime = $_POST['clock_in_time'] ?? '';
        $clockOutTime = $_POST['clock_out_time'] ?? '';
        $hours = (float)($_POST['total_hours'] ?? 0);
        $alertReason = trim($_POST['hr_alert_message'] ?? 'Wrong attendance marked - Corrected by HR');
        $notes = trim($_POST['notes'] ?? '');

        if ($userId <= 0 || empty($date)) {
            setFlash('error', 'Invalid employee or date for HR correction.');
            header('Location: ?page=admin-attendance');
            exit;
        }

        if ($status === 'absent') {
            $clockIn = null;
            $clockOut = null;
            $hours = 0;
        } else {
            $clockIn = !empty($clockInTime) ? "{$date} {$clockInTime}:00" : "{$date} 09:30:00";
            $clockOut = !empty($clockOutTime) ? "{$date} {$clockOutTime}:00" : null;

            if (!empty($clockIn) && !empty($clockOut)) {
                $inTs = strtotime($clockIn);
                $outTs = strtotime($clockOut);
                if ($outTs > $inTs) {
                    $hours = round(($outTs - $inTs) / 3600, 2);
                } else {
                    $hours = 0;
                }
            }
        }

        $db = getDBConnection();
        try {
            $stmt = $db->prepare("
                INSERT INTO attendance (user_id, date, clock_in, clock_out, total_hours, status, notes, tl_approved, hr_corrected, hr_alert_message, locked_by_hr)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, ?, 1)
                ON CONFLICT(user_id, date) DO UPDATE SET
                clock_in = excluded.clock_in,
                clock_out = excluded.clock_out,
                total_hours = excluded.total_hours,
                status = excluded.status,
                notes = excluded.notes,
                tl_approved = 1,
                hr_corrected = 1,
                hr_alert_message = excluded.hr_alert_message,
                locked_by_hr = 1
            ");
            $stmt->execute([$userId, $date, $clockIn, $clockOut, $hours, $status, $notes, $alertReason]);

            // Dispatch Warning Alerts
            $targetUser = $db->query("SELECT id, name, role, reporting_tl_id FROM users WHERE id = $userId")->fetch();
            if ($targetUser) {
                if ($targetUser['role'] === 'team_lead') {
                    // Team Lead themselves marked wrong attendance -> Warn TL directly
                    $warnMsg = "⚠️ Official Attendance Warning: HR audited and corrected your attendance record for {$date}.\nReason/Discrepancy: {$alertReason}\nPlease ensure strict compliance with scheduled office hours and accurate punch logs.";
                    $db->prepare("INSERT INTO tl_feedbacks (tl_id, hr_id, message, priority, status) VALUES (?, ?, ?, 'urgent', 'unread')")
                       ->execute([$userId, $user['id'], $warnMsg]);
                } elseif ($targetUser['role'] === 'employee') {
                    // If employee has a TL, warn the TL who oversees them
                    if (!empty($targetUser['reporting_tl_id'])) {
                        $warnMsg = "⚠️ Attendance Discrepancy Notice: HR audited and corrected the attendance for your team member {$targetUser['name']} for {$date}.\nReason/Discrepancy: {$alertReason}\nPlease verify team punch logs carefully before approving.";
                        $db->prepare("INSERT INTO tl_feedbacks (tl_id, hr_id, message, priority, status) VALUES (?, ?, ?, 'important', 'unread')")
                           ->execute([$targetUser['reporting_tl_id'], $user['id'], $warnMsg]);
                    }
                }
            }

            setFlash('success', "Attendance corrected & locked! Warning alert dispatched.");
        } catch (Exception $e) {
            setFlash('error', 'Error saving correction: ' . $e->getMessage());
        }

        header('Location: ?page=admin-attendance&date=' . urlencode($date));
        exit;
    }

    public static function getTodayAttendanceForUser(int $userId): ?array {
        $db = getDBConnection();
        $today = date('Y-m-d');
        $stmt = $db->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
        $stmt->execute([$userId, $today]);
        $att = $stmt->fetch();
        if (!$att) return null;

        // Fetch latest session to ensure accurate current active session clock_in time
        $latestSess = $db->query("SELECT * FROM attendance_sessions WHERE attendance_id = {$att['id']} ORDER BY session_number DESC, id DESC LIMIT 1")->fetch();
        if ($latestSess) {
            if ($latestSess['clock_out'] === null) {
                $att['clock_in'] = $latestSess['clock_in'];
                $att['clock_out'] = null;
            } else {
                $att['clock_out'] = $latestSess['clock_out'];
            }
        }
        return $att;
    }

    public static function getTeamLiveStatus(int $tlId): array {
        $db = getDBConnection();
        $today = date('Y-m-d');
        $teamIds = getManagedTeamUserIds($tlId);
        if (empty($teamIds)) {
            return [];
        }
        $inClause = implode(',', array_map('intval', $teamIds));
        $stmt = $db->query("
            SELECT u.id, u.name, u.emp_id, u.designation, u.avatar, u.is_escalated_locked, u.escalated_lock_reason, u.is_dismissed, u.dismissal_reason, u.hr_warning_message,
                   a.id as attendance_id, a.clock_in, a.clock_out, a.total_hours, a.status as attendance_status, a.notes,
                   a.tl_approved, a.hr_corrected, a.hr_alert_message, a.locked_by_hr
            FROM users u
            LEFT JOIN attendance a ON u.id = a.user_id AND a.date = '$today'
            WHERE u.id IN ($inClause) AND (u.status = 'active' OR u.is_dismissed = 1)
            ORDER BY u.name ASC
        ");
        return $stmt->fetchAll() ?: [];
    }

    public static function logTravelCoordinate(): void {
        $user = authUser();
        $db = getDBConnection();
        $today = date('Y-m-d');

        $activeAtt = $db->query("SELECT id FROM attendance WHERE user_id = {$user['id']} AND date = '{$today}' AND punch_out_time IS NULL ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$activeAtt) {
            echo json_encode(['success' => false, 'message' => 'No active shift.']);
            exit;
        }

        $lat = (float)($_POST['lat'] ?? 0);
        $lng = (float)($_POST['lng'] ?? 0);
        $speed = (float)($_POST['speed'] ?? 0);

        if ($lat != 0 && $lng != 0) {
            // Calculate distance from previous coordinate
            $prev = $db->query("SELECT latitude, longitude FROM employee_travel_logs WHERE attendance_id = {$activeAtt['id']} ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $distMeters = 0;
            if ($prev) {
                $distMeters = (int)calculateDistance($lat, $lng, (float)$prev['latitude'], (float)$prev['longitude']);
            }

            // Record log if distance moved >= 20 meters or first point
            if (!$prev || $distMeters >= 20) {
                $stmt = $db->prepare("INSERT INTO employee_travel_logs (attendance_id, user_id, latitude, longitude, speed, distance_meters) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$activeAtt['id'], $user['id'], $lat, $lng, $speed, $distMeters]);
            }

            echo json_encode(['success' => true, 'dist' => $distMeters]);
            exit;
        }

        echo json_encode(['success' => false]);
        exit;
    }

        public static function getTravelLogs(): void {
        requireAuth();
        $userId = (int)($_GET['user_id'] ?? 0);
        $date = !empty($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
        $db = getDBConnection();

        if ($userId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Missing user_id']);
            exit;
        }

        // 1. Fetch User & Shift Details for $date
        $emp = $db->query("
            SELECT u.id, u.name, u.emp_id, u.designation, u.work_mode, u.department_name, u.avatar,
                   tl.name as tl_name,
                   a.id as attendance_id, a.clock_in, a.clock_out, a.punch_in_lat, a.punch_in_lng, a.punch_out_lat, a.punch_out_lng,
                   a.address, a.total_hours, a.status as attendance_status,
                   (SELECT COUNT(*) FROM employee_travel_logs l WHERE l.user_id = u.id AND DATE(l.recorded_at) = '{$date}') as waypoints_count,
                   (SELECT COALESCE(SUM(l.distance_meters), 0) FROM employee_travel_logs l WHERE l.user_id = u.id AND DATE(l.recorded_at) = '{$date}') as total_distance_meters,
                   (SELECT l.speed FROM employee_travel_logs l WHERE l.user_id = u.id AND DATE(l.recorded_at) = '{$date}' ORDER BY l.id DESC LIMIT 1) as current_speed
            FROM users u
            LEFT JOIN attendance a ON a.user_id = u.id AND a.date = '{$date}'
            LEFT JOIN users tl ON u.reporting_tl_id = tl.id
            WHERE u.id = {$userId}
        ")->fetch(PDO::FETCH_ASSOC);

        if (!$emp) {
            echo json_encode(['success' => false, 'message' => 'Employee not found']);
            exit;
        }

                // 2. Fetch Raw Waypoints for $date (including Battery & Offline flags)
        $logs = $db->query("
            SELECT id, user_id, latitude, longitude, speed, battery_level, is_offline_sync, distance_meters, recorded_at 
            FROM employee_travel_logs 
            WHERE user_id = {$userId} AND DATE(recorded_at) = '{$date}' 
            ORDER BY id ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 3. Compute Stoppages, Speeds, and Start/End points
        $maxSpeed = 0;
        $totalSpeed = 0;
        $speedSamples = 0;
        $stops = [];
        $cleanWaypoints = [];
        $lastPt = null;

        // If punch in coordinates exist, ensure first waypoint
        if (!empty($emp['punch_in_lat']) && !empty($emp['punch_in_lng'])) {
            $cleanWaypoints[] = [
                'lat' => (float)$emp['punch_in_lat'],
                'lng' => (float)$emp['punch_in_lng'],
                'speed' => 0,
                'time' => $emp['clock_in'] ? date('h:i A', strtotime($emp['clock_in'])) : '09:00 AM',
                'type' => 'start',
                'title' => 'Shift Started (Punch In)'
            ];
        }

        foreach ($logs as $l) {
            $lat = (float)$l['latitude'];
            $lng = (float)$l['longitude'];
            $speed = (float)$l['speed'];
            $timeStr = date('h:i A', strtotime($l['recorded_at']));

            if ($lat == 0 || $lng == 0) continue;

            if ($speed > $maxSpeed) $maxSpeed = $speed;
            if ($speed > 0) {
                $totalSpeed += $speed;
                $speedSamples++;
            }

            // Identify stoppage if distance moved is small and speed < 3 km/h
            $isStop = false;
            if ($lastPt) {
                $distDelta = (int)$l['distance_meters'];
                $timeDeltaSeconds = strtotime($l['recorded_at']) - strtotime($lastPt['recorded_at']);
                if ($distDelta < 20 && $timeDeltaSeconds >= 180) { // Stationary for 3+ minutes
                    $isStop = true;
                    $stopDurationMins = round($timeDeltaSeconds / 60);
                    $stops[] = [
                        'stop_number' => count($stops) + 1,
                        'lat' => $lat,
                        'lng' => $lng,
                        'arrival_time' => date('h:i A', strtotime($lastPt['recorded_at'])),
                        'departure_time' => $timeStr,
                        'duration' => "{$stopDurationMins} mins",
                        'title' => "Client Visit / Stoppage #" . (count($stops) + 1)
                    ];
                }
            }

            $cleanWaypoints[] = [
                'lat' => $lat,
                'lng' => $lng,
                'speed' => $speed,
                'time' => $timeStr,
                'distance_meters' => (int)$l['distance_meters'],
                'is_stop' => $isStop,
                'type' => 'waypoint'
            ];

            $lastPt = $l;
        }

        // If punch out coordinates exist, append as final point
        if (!empty($emp['punch_out_lat']) && !empty($emp['punch_out_lng'])) {
            $cleanWaypoints[] = [
                'lat' => (float)$emp['punch_out_lat'],
                'lng' => (float)$emp['punch_out_lng'],
                'speed' => 0,
                'time' => $emp['clock_out'] ? date('h:i A', strtotime($emp['clock_out'])) : '07:00 PM',
                'type' => 'end',
                'title' => 'Shift Concluded (Punch Out)'
            ];
        }

        $avgSpeed = $speedSamples > 0 ? round($totalSpeed / $speedSamples, 1) : 0;
        $totalDistanceKm = round(((float)$emp['total_distance_meters']) / 1000, 2);

        $startLocation = !empty($cleanWaypoints) ? $cleanWaypoints[0] : null;
        $endLocation = !empty($cleanWaypoints) ? $cleanWaypoints[count($cleanWaypoints) - 1] : null;

                $lastLog = !empty($logs) ? $logs[count($logs) - 1] : null;
        $latestBattery = $lastLog && isset($lastLog['battery_level']) ? (int)$lastLog['battery_level'] : null;
        $lastSeenAt = $lastLog ? date('h:i A', strtotime($lastLog['recorded_at'])) : ($emp['clock_in'] ? date('h:i A', strtotime($emp['clock_in'])) : null);

        $analytics = [
            'total_distance_km' => $totalDistanceKm,
            'total_waypoints' => count($cleanWaypoints),
            'total_stops' => count($stops),
            'max_speed_kmh' => round($maxSpeed, 1),
            'avg_speed_kmh' => $avgSpeed,
            'latest_battery_level' => $latestBattery,
            'last_seen_at' => $lastSeenAt,
            'shift_start_time' => $emp['clock_in'] ? date('h:i A', strtotime($emp['clock_in'])) : 'Not Started',
            'shift_end_time' => $emp['clock_out'] ? date('h:i A', strtotime($emp['clock_out'])) : ($emp['clock_in'] ? 'Active On Field' : 'No Shift'),
            'is_active_now' => (!empty($emp['clock_in']) && empty($emp['clock_out']))
        ];

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'employee' => $emp,
            'analytics' => $analytics,
            'start_location' => $startLocation,
            'end_location' => $endLocation,
            'stops' => $stops,
            'waypoints' => $cleanWaypoints
        ]);
        exit;
    }

    public static function travelRadar(): void {
        requireAuth(['admin', 'team_lead']);
        require __DIR__ . '/../views/admin/travel_radar.php';
    }
}
