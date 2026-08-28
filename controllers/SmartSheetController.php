<?php
// controllers/SmartSheetController.php

class SmartSheetController {
    public static function index(): void {
        requireAuth('admin');
        $db = getDBConnection();

        $sheets = $db->query("SELECT * FROM smart_sheet_uploads ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

        require __DIR__ . '/../views/admin/smart_sheets.php';
    }

    public static function upload(): void {
        requireAuth('admin');
        $user = authUser();
        $db = getDBConnection();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = trim($_POST['title'] ?? '');
            $sheetUrl = trim($_POST['sheet_url'] ?? '');
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

            // Auto-Classification Intent Matcher
            $headerStr = strtolower(implode(' ', array_map('strval', $columns)));
            $category = 'custom';
            if (str_contains($headerStr, 'punch') || str_contains($headerStr, 'attendance') || str_contains($headerStr, 'clock') || str_contains($headerStr, 'present') || str_contains($headerStr, 'absent') || str_contains($headerStr, 'status') || str_contains($headerStr, 'in time')) {
                $category = 'attendance';
            } elseif (str_contains($headerStr, 'salary') || str_contains($headerStr, 'payroll') || str_contains($headerStr, 'net pay') || str_contains($headerStr, 'basic pay')) {
                $category = 'payroll';
            } elseif (str_contains($headerStr, 'email') && (str_contains($headerStr, 'designation') || str_contains($headerStr, 'department') || str_contains($headerStr, 'emp'))) {
                $category = 'employees';
            } elseif (str_contains($headerStr, 'phone') || str_contains($headerStr, 'lead') || str_contains($headerStr, 'calling') || str_contains($headerStr, 'client')) {
                $category = 'crm_leads';
            }

            // --- ⚡ AUTOMATIC ATTENDANCE AUTO-SYNC (HANDLES PUNCH TIMES & PURE PRESENT/ABSENT) ---
            $syncedAttendanceCount = 0;
            if ($category === 'attendance' && !empty($rows)) {
                // Load all existing users for fast matching
                $allUsers = $db->query("SELECT id, emp_id, name, email FROM users")->fetchAll(PDO::FETCH_ASSOC);

                // Helper to match user
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

                // Helper to normalize status & assign default hours/times when punch times are missing
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
                        // Present / P / 1
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

                // Check if this is a Monthly Matrix / Calendar Grid format (Columns are Day numbers 1..31)
                $dayColumns = [];
                foreach ($columns as $idx => $cName) {
                    $trimmed = trim((string)$cName);
                    if (is_numeric($trimmed) && (int)$trimmed >= 1 && (int)$trimmed <= 31) {
                        $dayColumns[(int)$trimmed] = $idx;
                    }
                }

                if (count($dayColumns) >= 5) {
                    // --- FORMAT A: MONTHLY MATRIX (Rows = Employees, Columns = Days 1..31) ---
                    $currentYearMonth = date('Y-m'); // Default current month
                    // Extract month from title if mentioned (e.g., 'July 2025' or '2026-08')
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
                    // --- FORMAT B: STANDARD LIST FORMAT (Rows = Records) ---
                    $colMap = ['emp' => -1, 'date' => -1, 'in' => -1, 'out' => -1, 'status' => -1, 'hours' => -1];
                    foreach ($columns as $idx => $colName) {
                        $c = strtolower(trim((string)$colName));
                        if ($colMap['emp'] === -1 && (str_contains($c, 'emp') || str_contains($c, 'name') || str_contains($c, 'staff') || str_contains($c, 'employee') || str_contains($c, 'user'))) {
                            $colMap['emp'] = $idx;
                        }
                        if ($colMap['date'] === -1 && (str_contains($c, 'date') || str_contains($c, 'day') || str_contains($c, 'punch date'))) {
                            $colMap['date'] = $idx;
                        }
                        if ($colMap['in'] === -1 && (str_contains($c, 'in') || str_contains($c, 'punch_in') || str_contains($c, 'clock_in') || str_contains($c, 'entry'))) {
                            $colMap['in'] = $idx;
                        }
                        if ($colMap['out'] === -1 && (str_contains($c, 'out') || str_contains($c, 'punch_out') || str_contains($c, 'clock_out') || str_contains($c, 'exit'))) {
                            $colMap['out'] = $idx;
                        }
                        if ($colMap['status'] === -1 && (str_contains($c, 'status') || str_contains($c, 'state') || str_contains($c, 'present') || str_contains($c, 'attendance') || str_contains($c, 'remark') || str_contains($c, 'p/a'))) {
                            $colMap['status'] = $idx;
                        }
                        if ($colMap['hours'] === -1 && (str_contains($c, 'hour') || str_contains($c, 'duration') || str_contains($c, 'time spent'))) {
                            $colMap['hours'] = $idx;
                        }
                    }

                    if ($colMap['emp'] === -1) $colMap['emp'] = 0;
                    if ($colMap['date'] === -1) $colMap['date'] = 1;
                    if ($colMap['status'] === -1 && count($columns) > 2) $colMap['status'] = 2;

                    foreach ($rows as $row) {
                        $empVal = trim((string)($row[$colMap['emp']] ?? ''));
                        if (empty($empVal)) continue;
                        $matchedUserId = $matchUser($empVal);

                        // Parse Date
                        $rawDate = trim((string)($row[$colMap['date']] ?? date('Y-m-d')));
                        $parsedDate = date('Y-m-d', strtotime($rawDate));
                        if (!$parsedDate || $parsedDate === '1970-01-01') {
                            $parsedDate = date('Y-m-d');
                        }

                        $rawIn = $colMap['in'] !== -1 ? trim((string)($row[$colMap['in']] ?? '')) : null;
                        $rawOut = $colMap['out'] !== -1 ? trim((string)($row[$colMap['out']] ?? '')) : null;
                        $rawHours = $colMap['hours'] !== -1 ? (float)preg_replace('/[^\d.]/', '', (string)($row[$colMap['hours']] ?? '0')) : null;
                        $rawStatus = $colMap['status'] !== -1 ? trim((string)($row[$colMap['status']] ?? 'present')) : 'present';

                        $eval = $mapStatusAndTimes($rawStatus, $rawIn, $rawOut, $rawHours);

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

            // Calculate auto-formula aggregates (SUM of numeric columns)
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

            $successMsg = "🎉 Smart Sheet '{$title}' parsed successfully (" . count($rows) . " rows)! Auto-classified as: " . strtoupper($category);
            if ($syncedAttendanceCount > 0) {
                $successMsg .= " • ⚡ {$syncedAttendanceCount} Attendance records automatically arranged & synced into Attendance Section!";
            }

            setFlash('success', $successMsg);
            header('Location: ?page=admin-smart-sheets');
            exit;
        }
    }
}