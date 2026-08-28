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
            if (str_contains($headerStr, 'punch') || str_contains($headerStr, 'attendance') || str_contains($headerStr, 'clock') || str_contains($headerStr, 'in time') || str_contains($headerStr, 'out time')) {
                $category = 'attendance';
            } elseif (str_contains($headerStr, 'salary') || str_contains($headerStr, 'payroll') || str_contains($headerStr, 'net pay') || str_contains($headerStr, 'basic pay')) {
                $category = 'payroll';
            } elseif (str_contains($headerStr, 'email') && (str_contains($headerStr, 'designation') || str_contains($headerStr, 'department') || str_contains($headerStr, 'emp'))) {
                $category = 'employees';
            } elseif (str_contains($headerStr, 'phone') || str_contains($headerStr, 'lead') || str_contains($headerStr, 'calling') || str_contains($headerStr, 'client')) {
                $category = 'crm_leads';
            }

            // --- ⚡ AUTOMATIC ATTENDANCE AUTO-SYNC INTO SYSTEM AUDIT ---
            $syncedAttendanceCount = 0;
            if ($category === 'attendance' && !empty($rows)) {
                // 1. Identify Column Indexes
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
                    if ($colMap['status'] === -1 && (str_contains($c, 'status') || str_contains($c, 'state') || str_contains($c, 'present') || str_contains($c, 'attendance'))) {
                        $colMap['status'] = $idx;
                    }
                    if ($colMap['hours'] === -1 && (str_contains($c, 'hour') || str_contains($c, 'duration') || str_contains($c, 'time spent'))) {
                        $colMap['hours'] = $idx;
                    }
                }

                // Fallback default indexes if not matched
                if ($colMap['emp'] === -1) $colMap['emp'] = 0;
                if ($colMap['date'] === -1) $colMap['date'] = 1;

                // Load all existing users for fast lookup
                $allUsers = $db->query("SELECT id, emp_id, name, email FROM users")->fetchAll(PDO::FETCH_ASSOC);

                foreach ($rows as $row) {
                    $empVal = trim((string)($row[$colMap['emp']] ?? ''));
                    if (empty($empVal)) continue;

                    // Match User by Emp ID, Name, or Email
                    $matchedUserId = null;
                    foreach ($allUsers as $u) {
                        if (!empty($u['emp_id']) && strcasecmp($u['emp_id'], $empVal) === 0) {
                            $matchedUserId = $u['id'];
                            break;
                        }
                        if (stripos($u['name'], $empVal) !== false || stripos($empVal, $u['name']) !== false) {
                            $matchedUserId = $u['id'];
                            break;
                        }
                        if (!empty($u['email']) && strcasecmp($u['email'], $empVal) === 0) {
                            $matchedUserId = $u['id'];
                            break;
                        }
                    }

                    // If still not matched, fallback to current admin user
                    if (!$matchedUserId) {
                        $matchedUserId = $user['id'];
                    }

                    // Parse Date
                    $rawDate = trim((string)($row[$colMap['date']] ?? date('Y-m-d')));
                    $parsedDate = date('Y-m-d', strtotime($rawDate));
                    if (!$parsedDate || $parsedDate === '1970-01-01') {
                        $parsedDate = date('Y-m-d');
                    }

                    // Parse Clock-In & Out Times
                    $rawIn = $colMap['in'] !== -1 ? trim((string)($row[$colMap['in']] ?? '')) : '';
                    $rawOut = $colMap['out'] !== -1 ? trim((string)($row[$colMap['out']] ?? '')) : '';
                    $clockIn = !empty($rawIn) && strtotime($rawIn) ? date('H:i:s', strtotime($rawIn)) : '09:30:00';
                    $clockOut = !empty($rawOut) && strtotime($rawOut) ? date('H:i:s', strtotime($rawOut)) : null;

                    // Calculate Total Hours
                    $rawHours = $colMap['hours'] !== -1 ? (float)preg_replace('/[^\d.]/', '', (string)($row[$colMap['hours']] ?? '0')) : 0;
                    if ($rawHours > 0) {
                        $totalHours = $rawHours;
                    } elseif ($clockIn && $clockOut) {
                        $totalHours = round((strtotime($clockOut) - strtotime($clockIn)) / 3600, 2);
                        if ($totalHours < 0) $totalHours = 0;
                    } else {
                        $totalHours = 8.5;
                    }

                    // Determine Status
                    $rawStatus = $colMap['status'] !== -1 ? strtolower(trim((string)($row[$colMap['status']] ?? ''))) : 'present';
                    $status = 'present';
                    if (in_array($rawStatus, ['a', 'absent', 'abs', 'leave', 'l', '0'])) {
                        $status = 'absent';
                    } elseif (in_array($rawStatus, ['hd', 'half day', 'half_day', 'half'])) {
                        $status = 'half_day';
                    }

                    // Check if Attendance record already exists for this user and date
                    $existingAtt = $db->query("SELECT id FROM attendance WHERE user_id = {$matchedUserId} AND date = '{$parsedDate}'")->fetch(PDO::FETCH_ASSOC);

                    if ($existingAtt) {
                        $upStmt = $db->prepare("
                            UPDATE attendance 
                            SET clock_in = ?, clock_out = ?, total_hours = ?, status = ?, notes = 'Imported & Arranged via Smart Sheet', tl_approved = 1, hr_corrected = 1 
                            WHERE id = ?
                        ");
                        $upStmt->execute([$clockIn, $clockOut, $totalHours, $status, $existingAtt['id']]);
                    } else {
                        $inStmt = $db->prepare("
                            INSERT INTO attendance (user_id, date, clock_in, clock_out, total_hours, status, notes, tl_approved, hr_corrected, is_geofence_verified)
                            VALUES (?, ?, ?, ?, ?, ?, 'Imported & Arranged via Smart Sheet', 1, 1, 1)
                        ");
                        $inStmt->execute([$matchedUserId, $parsedDate, $clockIn, $clockOut, $totalHours, $status]);
                    }
                    $syncedAttendanceCount++;
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