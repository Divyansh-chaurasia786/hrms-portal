<?php
// config/database.php
date_default_timezone_set('Asia/Kolkata');

$defaultDbPath = __DIR__ . '/../database/hrms.sqlite';
if (getenv('VERCEL') || isset($_ENV['VERCEL']) || (isset($_SERVER['VERCEL']) && $_SERVER['VERCEL'])) {
    $tmpDb = '/tmp/hrms.sqlite';
    if (!file_exists($tmpDb) && file_exists($defaultDbPath)) {
        @copy($defaultDbPath, $tmpDb);
    }
    define('DB_FILE', file_exists($tmpDb) ? $tmpDb : $defaultDbPath);
} else {
    define('DB_FILE', $defaultDbPath);
}

// 📍 Office Geofence Configuration (Strict office boundary verification)
define('GEOFENCE_ENABLED', true);         // Strictly enforced
define('GEOFENCE_RADIUS_METERS', 50);     // 50 meter radius allowed

define('OFFICE_LOCATIONS', json_encode([
    1 => [
        'id'   => 1,
        'name' => 'Metro Height, Transport Nagar, Lucknow',
        'lat'  => 26.7816122,
        'lng'  => 80.8852283,
    ],
    2 => [
        'id'   => 2,
        'name' => 'Sachan Complex, Krishna Nagar, Lucknow',
        'lat'  => 26.7897624,
        'lng'  => 80.8895117,
    ],
]));

function getOfficeLocations(): array {
    return json_decode(OFFICE_LOCATIONS, true);
}

function getOfficeLocationById(int $id): ?array {
    $locations = getOfficeLocations();
    return $locations[$id] ?? null;
}

function getEffectiveUserLocation(int $userId): array {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT id, name, role, designation, reporting_tl_id, assigned_office_location, temp_office_location, temp_location_expires_at, temp_location_days FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $u = $stmt->fetch();
    if (!$u) {
        $loc = getOfficeLocationById(2) ?: ['id' => 2, 'name' => 'Innovation Hub - Kapoorthala, Aliganj', 'lat' => 26.884578, 'lng' => 80.938924, 'radius' => 150];
        $loc['is_temporary'] = false;
        $loc['days_left'] = 0;
        return $loc;
    }

    $targetTlId = null;
    $desig = strtolower($u['designation'] ?? '');
    $isTLSupport = (strpos($desig, 'tl support') !== false || strpos($desig, 'support tl') !== false);

    if ($u['role'] === 'admin') {
        $locId = (int)($u['assigned_office_location'] ?: 2);
        $loc = getOfficeLocationById($locId) ?: getOfficeLocationById(2);
        $loc['is_temporary'] = false;
        $loc['days_left'] = 0;
        return $loc;
    }

    if ($u['role'] === 'team_lead') {
        if ($isTLSupport && !empty($u['reporting_tl_id'])) {
            $targetTlId = (int)$u['reporting_tl_id'];
        } else {
            $targetTlId = (int)$u['id'];
        }
    } else {
        // Employee inherits TL location
        $targetTlId = (int)($u['reporting_tl_id'] ?: 0);
    }

    if ($targetTlId > 0) {
        $stmt2 = $db->prepare("SELECT id, name, assigned_office_location, temp_office_location, temp_location_expires_at, temp_location_days FROM users WHERE id = ?");
        $stmt2->execute([$targetTlId]);
        $tl = $stmt2->fetch();
    } else {
        $tl = $u;
    }

    if (!$tl) {
        $loc = getOfficeLocationById(2);
        $loc['is_temporary'] = false;
        $loc['days_left'] = 0;
        return $loc;
    }

    $today = date('Y-m-d');
    // Check if Temporary Location is active
    if (!empty($tl['temp_office_location']) && !empty($tl['temp_location_expires_at']) && $tl['temp_location_expires_at'] >= $today) {
        $loc = getOfficeLocationById((int)$tl['temp_office_location']);
        if ($loc) {
            $daysLeft = (int)((strtotime($tl['temp_location_expires_at']) - strtotime($today)) / 86400) + 1;
            $loc['is_temporary'] = true;
            $loc['days_left'] = max(1, $daysLeft);
            $loc['expires_at'] = $tl['temp_location_expires_at'];
            $loc['permanent_location_id'] = (int)($tl['assigned_office_location'] ?: 2);
            $loc['tl_name'] = $tl['name'];
            return $loc;
        }
    }

    // Permanent Location
    $permId = (int)($tl['assigned_office_location'] ?: 2);
    $loc = getOfficeLocationById($permId) ?: getOfficeLocationById(2);
    $loc['is_temporary'] = false;
    $loc['days_left'] = 0;
    $loc['tl_name'] = $tl['name'];
    return $loc;
}

function getDBConnection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        // TiDB Cloud MySQL Configuration
        $dbHost = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? ($_SERVER['DB_HOST'] ?? 'gateway01.ap-southeast-1.prod.aws.tidbcloud.com'));
        $dbPort = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? ($_SERVER['DB_PORT'] ?? 4000));
        $dbUser = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? ($_SERVER['DB_USER'] ?? '2P59qqNczcBgyLg.root'));
        $dbPass = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? ($_SERVER['DB_PASS'] ?? 'mgGPzRZeGnCvE8Lb'));
        $dbName = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? ($_SERVER['DB_NAME'] ?? 'hrms'));

        $caFile = __DIR__ . '/cacert.pem';

        try {
            $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5
            ];

            if (file_exists($caFile)) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = $caFile;
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
            } else {
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }

            $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
        } catch (Throwable $e) {
            // SQLite Fallback if cloud MySQL is unreachable
            error_log("Cloud MySQL connection fallback to SQLite: " . $e->getMessage());
            
            $dbDir = dirname(DB_FILE);
            if (!is_dir($dbDir)) {
                @mkdir($dbDir, 0777, true);
            }
            
            $isNew = !file_exists(DB_FILE);
            $pdo = new PDO('sqlite:' . DB_FILE);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec('PRAGMA foreign_keys = ON;');
            
            if ($isNew) {
                require_once __DIR__ . '/../database/schema.php';
                initDatabaseSchema($pdo);
                require_once __DIR__ . '/../database/seed.php';
                seedDatabase($pdo);
            }
        }
    }
    return $pdo;
}
