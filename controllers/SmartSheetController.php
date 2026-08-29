<?php
// controllers/SmartSheetController.php

class SmartSheetController {
    public static function index(): void {
        requireAuth('admin');
        $db = getDBConnection();

        // High-Speed Summary Query (Without pulling megabytes of rows_json on every load)
        $sheets = $db->query("
            SELECT s.id, s.title, s.category, s.uploaded_by, s.created_at, 
                   COALESCE(JSON_LENGTH(s.rows_json), 0) as record_count, 
                   u.name as uploader_name 
            FROM smart_sheet_uploads s 
            JOIN users u ON s.uploaded_by = u.id 
            ORDER BY s.created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        require __DIR__ . '/../views/admin/smart_sheets.php';
    }

    public static function getSheetData(): void {
        requireAuth();
        $db = getDBConnection();
        $sheetId = (int)($_GET['sheet_id'] ?? 0);

        header('Content-Type: application/json');
        if ($sheetId <= 0) {
            echo json_encode(['columns' => [], 'rows' => []]);
            exit;
        }

        $row = $db->query("SELECT columns_json, rows_json FROM smart_sheet_uploads WHERE id = {$sheetId}")->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(['columns' => [], 'rows' => []]);
            exit;
        }

        echo json_encode([
            'columns' => json_decode($row['columns_json'] ?? '[]', true) ?: [],
            'rows' => json_decode($row['rows_json'] ?? '[]', true) ?: []
        ]);
        exit;
    }

    public static function upload(): void {
        requireAuth('admin');
        $user = authUser();
        $db = getDBConnection();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = trim($_POST['sheet_title'] ?? ($_POST['title'] ?? ''));
            $sheetUrl = trim($_POST['google_sheet_url'] ?? ($_POST['sheet_url'] ?? ''));
            $uploadedFile = $_FILES['sheet_file']['tmp_name'] ?? null;
            $originalName = $_FILES['sheet_file']['name'] ?? 'Spreadsheet';

            if (empty($title)) {
                $title = !empty($uploadedFile) ? pathinfo($originalName, PATHINFO_FILENAME) : 'Google Sheet Import';
            }

            // Parse via Universal Spreadsheet Parser (supports CSV, XLSX, TSV, XML, Google Sheets GViz)
            $parsed = parseSpreadsheetData($uploadedFile, $sheetUrl, $originalName);
            $columns = $parsed['columns'] ?? [];
            $rows = $parsed['rows'] ?? [];

            if (empty($columns)) {
                setFlash('error', 'Unable to parse data from the uploaded sheet or URL. If using Google Sheets, make sure Sharing is set to "Anyone with the link can view", or upload an .xlsx / .csv file.');
                header('Location: ?page=admin-smart-sheets');
                exit;
            }

            // Dynamic Classification & Custom Section Matcher
            $userCategory = trim($_POST['category'] ?? '');
            $customCategory = trim($_POST['custom_category'] ?? '');

            if (!empty($customCategory)) {
                $category = ucwords(strtolower($customCategory));
            } elseif (!empty($userCategory) && $userCategory !== 'auto') {
                $category = $userCategory;
            } else {
                // Auto-Classification from Title and Header Columns
                $combinedContext = strtolower($title . ' ' . implode(' ', array_map('strval', $columns)));
                
                if (str_contains($combinedContext, 'bda') || str_contains($combinedContext, 'fsm') || str_contains($combinedContext, 'sales')) {
                    $category = 'BDA & Sales Team';
                } elseif (str_contains($combinedContext, 'punch') || str_contains($combinedContext, 'attendance') || str_contains($combinedContext, 'clock') || str_contains($combinedContext, 'present') || str_contains($combinedContext, 'absent') || str_contains($combinedContext, 'status') || str_contains($combinedContext, 'in time')) {
                    $category = 'Attendance Logs';
                } elseif (str_contains($combinedContext, 'salary') || str_contains($combinedContext, 'payroll') || str_contains($combinedContext, 'net pay') || str_contains($combinedContext, 'basic pay') || str_contains($combinedContext, 'ctc')) {
                    $category = 'Payroll & Salary';
                } elseif (str_contains($combinedContext, 'lead') || str_contains($combinedContext, 'calling') || str_contains($combinedContext, 'client') || str_contains($combinedContext, 'prospect')) {
                    $category = 'Lead CRM & Calling';
                } elseif (str_contains($combinedContext, 'target') || str_contains($combinedContext, 'kpi') || str_contains($combinedContext, 'performance')) {
                    $category = 'Targets & KPIs';
                } elseif (str_contains($combinedContext, 'asset') || str_contains($combinedContext, 'inventory') || str_contains($combinedContext, 'hardware')) {
                    $category = 'Assets & Hardware';
                } elseif (str_contains($combinedContext, 'email') || str_contains($combinedContext, 'designation') || str_contains($combinedContext, 'name') || str_contains($combinedContext, 'staff') || str_contains($combinedContext, 'employee')) {
                    $category = 'Workforce Directory';
                } else {
                    $category = !empty($title) ? ucwords(strtolower($title)) : 'General Datasets';
                }
            }

            // --- ⚡ 1. AUTOMATIC EMPLOYEE REGISTRATION ENGINE ---
            $registeredEmpCount = 0;
            if (($category === 'employees' || $category === 'Workforce Directory' || $category === 'BDA & Sales Team' || str_contains(strtolower($category), 'bda') || str_contains(strtolower($category), 'employee')) && !empty($rows)) {
                $existingEmails = $db->query("SELECT email FROM users")->fetchAll(PDO::FETCH_COLUMN) ?: [];
                $existingEmails = array_map('strtolower', array_map('trim', $existingEmails));

                // Find max existing EMP ID
                $maxEmpNum = 5;
                $allEmpIds = $db->query("SELECT emp_id FROM users WHERE emp_id LIKE 'EMP%'")->fetchAll(PDO::FETCH_COLUMN) ?: [];
                foreach ($allEmpIds as $eid) {
                    if (preg_match('/EMP(\d+)/', $eid, $m)) {
                        $num = (int)$m[1];
                        if ($num > $maxEmpNum) $maxEmpNum = $num;
                    }
                }

                // Match column indices
                $colNameIdx = -1;
                $colEmailIdx = -1;
                $colDesigIdx = -1;

                foreach ($columns as $idx => $cName) {
                    $cn = strtolower(trim((string)$cName));
                    if ($colNameIdx === -1 && (str_contains($cn, 'name') || str_contains($cn, 'staff') || str_contains($cn, 'employee'))) $colNameIdx = $idx;
                    if ($colEmailIdx === -1 && (str_contains($cn, 'email') || str_contains($cn, 'mail'))) $colEmailIdx = $idx;
                    if ($colDesigIdx === -1 && (str_contains($cn, 'designation') || str_contains($cn, 'role') || str_contains($cn, 'position') || str_contains($cn, 'dept'))) $colDesigIdx = $idx;
                }

                if ($colNameIdx === -1) $colNameIdx = 0;
                if ($colEmailIdx === -1 && count($columns) > 1) $colEmailIdx = 1;
                if ($colDesigIdx === -1 && count($columns) > 2) $colDesigIdx = 2;

                // Look for DOB Column
                $colDobIdx = -1;
                foreach ($columns as $idx => $cName) {
                    $cn = strtolower(trim((string)$cName));
                    if (str_contains($cn, 'dob') || str_contains($cn, 'birth') || str_contains($cn, 'bday') || str_contains($cn, 'date of birth')) {
                        $colDobIdx = $idx;
                        break;
                    }
                }

                $stmtInsertEmp = $db->prepare("
                    INSERT INTO users (emp_id, name, email, role, designation, work_mode, department_name, date_of_birth, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                ");

                foreach ($rows as $row) {
                    $eName = trim((string)($row[$colNameIdx] ?? ''));
                    $eEmail = strtolower(trim((string)($row[$colEmailIdx] ?? '')));
                    $eDesig = trim((string)($row[$colDesigIdx] ?? ''));
                    $rawDob = ($colDobIdx !== -1) ? trim((string)($row[$colDobIdx] ?? '')) : null;
                    $parsedDob = !empty($rawDob) && strtotime($rawDob) ? date('Y-m-d', strtotime($rawDob)) : null;

                    if (empty($eName) || empty($eEmail) || !filter_var($eEmail, FILTER_VALIDATE_EMAIL)) {
                        continue;
                    }

                    if (in_array($eEmail, $existingEmails)) {
                        continue; // Already exists
                    }

                    $maxEmpNum++;
                    $newEmpId = sprintf('EMP%03d', $maxEmpNum);

                    $role = 'employee';
                    $workMode = 'office';
                    $departmentName = 'Operations';
                    $designation = !empty($eDesig) ? $eDesig : 'Executive';

                    $upperDesig = strtoupper($eDesig);
                    if (str_contains($upperDesig, 'FSM') || str_contains($upperDesig, 'FIELD')) {
                        $designation = 'Field Sales Manager (FSM)';
                        $workMode = 'field';
                        $departmentName = 'Field Operations';
                    } elseif (str_contains($upperDesig, 'BDA') || str_contains($upperDesig, 'CALL')) {
                        $designation = 'Business Development Associate (BDA)';
                        $workMode = 'office';
                        $departmentName = 'Business Development';
                    } elseif (str_contains($upperDesig, 'TL') || str_contains($upperDesig, 'LEAD')) {
                        $role = 'team_lead';
                        $designation = 'Team Lead';
                    }

                    $stmtInsertEmp->execute([$newEmpId, $eName, $eEmail, $role, $designation, $workMode, $departmentName, $parsedDob]);
                    $existingEmails[] = $eEmail;
                    $registeredEmpCount++;
                }
            }

            // --- ⚡ 2. AUTOMATIC ATTENDANCE AUTO-SYNC ENGINE ---
            $syncedAttendanceCount = 0;
            if ($category === 'attendance' && !empty($rows)) {
                $allUsers = $db->query("SELECT id, emp_id, name, email FROM users")->fetchAll(PDO::FETCH_ASSOC);

                $matchUser = function(string $empVal) use ($allUsers, $user): int {
                    $val = trim($empVal);
                    if (empty($val)) return $user['id'];
                    foreach ($allUsers as $u) {
                        if (!empty($u['emp_id']) && strcasecmp($u['emp_id'], $val) === 0) return $u['id'];
                        if (stripos($u['name'], $val) !== false || stripos($val, $u['name']) !== false) return $u['id'];
                        if (!empty($u['email']) && strcasecmp($u['email'], $val) === 0) return $u['id'];
                    }
                    return $user['id'];
                };

                $mapStatusAndTimes = function(string $rawStatus, ?string $rawIn, ?string $rawOut, ?float $rawHours): array {
                    $s = strtolower(trim($rawStatus));
                    $status = 'present';
                    $clockIn = '10:00:00';
                    $clockOut = '19:00:00';
                    $totalHours = 9.0;

                    if (in_array($s, ['a', 'absent', 'abs', '0', 'no', 'false'])) {
                        $status = 'absent';
                        $clockIn = null;
                        $clockOut = null;
                        $totalHours = 0.0;
                    } elseif (in_array($s, ['hd', 'half day', 'half_day', 'half', '0.5', 'h'])) {
                        $status = 'half_day';
                        $clockIn = '10:00:00';
                        $clockOut = '14:30:00';
                        $totalHours = 4.5;
                    } elseif (in_array($s, ['l', 'leave', 'pl', 'cl', 'sl', 'on_leave'])) {
                        $status = 'on_leave';
                        $clockIn = null;
                        $clockOut = null;
                        $totalHours = 0.0;
                    } else {
                        $status = 'present';
                        if (!empty($rawIn) && strtotime($rawIn)) {
                            $clockIn = date('H:i:s', strtotime($rawIn));
                        }
                        if (!empty($rawOut) && strtotime($rawOut)) {
                            $clockOut = date('H:i:s', strtotime($rawOut));
                        }
                        if ($rawHours !== null && $rawHours > 0) {
                            $totalHours = $rawHours;
                        } elseif ($clockIn && $clockOut) {
                            $totalHours = round((strtotime($clockOut) - strtotime($clockIn)) / 3600, 2);
                        } else {
                            $totalHours = 9.0;
                        }
                    }

                    return [
                        'status' => $status,
                        'clock_in' => $clockIn,
                        'clock_out' => $clockOut,
                        'total_hours' => $totalHours
                    ];
                };

                // Monthly Matrix Check
                $dayColumns = [];
                foreach ($columns as $idx => $cName) {
                    $trimmed = trim((string)$cName);
                    if (is_numeric($trimmed) && (int)$trimmed >= 1 && (int)$trimmed <= 31) {
                        $dayColumns[(int)$trimmed] = $idx;
                    }
                }

                if (count($dayColumns) >= 5) {
                    $currentYearMonth = date('Y-m');
                    if (preg_match('/(202\d[-_\/]\d{1,2})/', $title . ' ' . $originalName, $ymMatch)) {
                        $currentYearMonth = date('Y-m', strtotime($ymMatch[1] . '-01'));
                    }

                    foreach ($rows as $row) {
                        $empName = trim((string)($row[0] ?? ($row[1] ?? '')));
                        if (empty($empName)) continue;
                        $matchedUserId = $matchUser($empName);

                        foreach ($dayColumns as $dayNum => $colIdx) {
                            $dayVal = trim((string)($row[$colIdx] ?? ''));
                            if (empty($dayVal)) continue;

                            $dateStr = sprintf('%s-%02d', $currentYearMonth, $dayNum);
                            $eval = $mapStatusAndTimes($dayVal, null, null, null);

                            $existing = $db->query("SELECT id FROM attendance WHERE user_id = {$matchedUserId} AND date = '{$dateStr}'")->fetch(PDO::FETCH_ASSOC);
                            if ($existing) {
                                $stmt = $db->prepare("UPDATE attendance SET clock_in = ?, clock_out = ?, total_hours = ?, status = ?, notes = 'Imported Historical Matrix Sheet', tl_approved = 1, hr_corrected = 1 WHERE id = ?");
                                $stmt->execute([$eval['clock_in'], $eval['clock_out'], $eval['total_hours'], $eval['status'], $existing['id']]);
                            } else {
                                $stmt = $db->prepare("INSERT INTO attendance (user_id, date, clock_in, clock_out, total_hours, status, notes, tl_approved, hr_corrected, is_geofence_verified) VALUES (?, ?, ?, ?, ?, ?, 'Imported Historical Matrix Sheet', 1, 1, 1)");
                                $stmt->execute([$matchedUserId, $dateStr, $eval['clock_in'], $eval['clock_out'], $eval['total_hours'], $eval['status']]);
                            }
                            $syncedAttendanceCount++;
                        }
                    }
                } else {
                    $colMap = ['emp' => 0, 'date' => 1, 'in' => -1, 'out' => -1, 'status' => -1, 'hours' => -1];
                    foreach ($columns as $idx => $colName) {
                        $c = strtolower(trim((string)$colName));
                        if ($colMap['emp'] === 0 && (str_contains($c, 'emp') || str_contains($c, 'name') || str_contains($c, 'staff'))) $colMap['emp'] = $idx;
                        if ($colMap['date'] === 1 && (str_contains($c, 'date') || str_contains($c, 'day'))) $colMap['date'] = $idx;
                        if (str_contains($c, 'in') || str_contains($c, 'punch_in')) $colMap['in'] = $idx;
                        if (str_contains($c, 'out') || str_contains($c, 'punch_out')) $colMap['out'] = $idx;
                        if (str_contains($c, 'status') || str_contains($c, 'present') || str_contains($c, 'attendance')) $colMap['status'] = $idx;
                    }

                    foreach ($rows as $row) {
                        $empVal = trim((string)($row[$colMap['emp']] ?? ''));
                        if (empty($empVal)) continue;
                        $matchedUserId = $matchUser($empVal);

                        $rawDate = trim((string)($row[$colMap['date']] ?? date('Y-m-d')));
                        $parsedDate = date('Y-m-d', strtotime($rawDate));
                        if (!$parsedDate || $parsedDate === '1970-01-01') $parsedDate = date('Y-m-d');

                        $rawIn = $colMap['in'] !== -1 ? trim((string)($row[$colMap['in']] ?? '')) : null;
                        $rawOut = $colMap['out'] !== -1 ? trim((string)($row[$colMap['out']] ?? '')) : null;
                        $rawStatus = $colMap['status'] !== -1 ? trim((string)($row[$colMap['status']] ?? 'present')) : 'present';

                        $eval = $mapStatusAndTimes($rawStatus, $rawIn, $rawOut, null);

                        $existing = $db->query("SELECT id FROM attendance WHERE user_id = {$matchedUserId} AND date = '{$parsedDate}'")->fetch(PDO::FETCH_ASSOC);
                        if ($existing) {
                            $stmt = $db->prepare("UPDATE attendance SET clock_in = ?, clock_out = ?, total_hours = ?, status = ?, notes = 'Imported Historical Sheet', tl_approved = 1, hr_corrected = 1 WHERE id = ?");
                            $stmt->execute([$eval['clock_in'], $eval['clock_out'], $eval['total_hours'], $eval['status'], $existing['id']]);
                        } else {
                            $stmt = $db->prepare("INSERT INTO attendance (user_id, date, clock_in, clock_out, total_hours, status, notes, tl_approved, hr_corrected, is_geofence_verified) VALUES (?, ?, ?, ?, ?, ?, 'Imported Historical Sheet', 1, 1, 1)");
                            $stmt->execute([$matchedUserId, $parsedDate, $eval['clock_in'], $eval['clock_out'], $eval['total_hours'], $eval['status']]);
                        }
                        $syncedAttendanceCount++;
                    }
                }
            }

            // Calculate auto-formula aggregates
            $numericSums = [];
            foreach ($rows as $row) {
                foreach ($row as $colIdx => $val) {
                    $cleanedVal = preg_replace('/[^\d.]/', '', (string)$val);
                    if (is_numeric($cleanedVal) && !empty($cleanedVal)) {
                        $numericSums[$colIdx] = ($numericSums[$colIdx] ?? 0) + (float)$cleanedVal;
                    }
                }
            }

            $summary = [
                'total_rows' => count($rows),
                'total_columns' => count($columns),
                'column_sums' => $numericSums,
                'registered_employees' => $registeredEmpCount,
                'synced_attendance_records' => $syncedAttendanceCount
            ];

            $stmt = $db->prepare("INSERT INTO smart_sheet_uploads (title, file_type, original_filename, category, columns_json, rows_json, summary_json, uploaded_by) VALUES (?, 'spreadsheet', ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $title,
                !empty($sheetUrl) ? 'Google Sheet: ' . substr($sheetUrl, 0, 80) : $originalName,
                $category,
                json_encode($columns),
                json_encode($rows),
                json_encode($summary),
                $user['id']
            ]);

            $successMsg = "🎉 Smart Sheet '{$title}' processed successfully (" . count($rows) . " rows)! Category: " . strtoupper($category);
            if ($registeredEmpCount > 0) {
                $successMsg .= " • 👤 {$registeredEmpCount} Employees automatically registered into Employee Directory!";
            }
            if ($syncedAttendanceCount > 0) {
                $successMsg .= " • ⚡ {$syncedAttendanceCount} Attendance records synced!";
            }

            setFlash('success', $successMsg);
            header('Location: ?page=admin-smart-sheets');
            exit;
        }
    }

    public static function delete(): void {
        requireAuth('admin');
        $db = getDBConnection();
        $sheetId = (int)($_POST['sheet_id'] ?? 0);
        if ($sheetId > 0) {
            $db->prepare("DELETE FROM smart_sheet_uploads WHERE id = ?")->execute([$sheetId]);
            setFlash('success', 'Smart Sheet deleted successfully.');
        }
        header('Location: ?page=admin-smart-sheets');
        exit;
    }
}