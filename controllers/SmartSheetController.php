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

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = trim($_POST['title'] ?? 'Uploaded Sheet');
            $sheetUrl = trim($_POST['sheet_url'] ?? '');
            $columns = [];
            $rows = [];

            if (!empty($_FILES['sheet_file']['tmp_name'])) {
                $file = $_FILES['sheet_file']['tmp_name'];
                if (($handle = fopen($file, "r")) !== FALSE) {
                    $columns = fgetcsv($handle, 2000, ",") ?: [];
                    while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {
                        $rows[] = $data;
                    }
                    fclose($handle);
                }
            } elseif (!empty($sheetUrl)) {
                // If it's a Google Sheet URL, convert to export CSV
                if (preg_match('/spreadsheets\/d\/([a-zA-Z0-9-_]+)/', $sheetUrl, $matches)) {
                    $docId = $matches[1];
                    $csvUrl = "https://docs.google.com/spreadsheets/d/{$docId}/export?format=csv";
                    $content = @file_get_contents($csvUrl);
                    if ($content) {
                        $lines = explode("\n", $content);
                        $columns = str_getcsv(array_shift($lines));
                        foreach ($lines as $line) {
                            if (trim($line)) $rows[] = str_getcsv($line);
                        }
                    }
                }
            }

            if (empty($columns)) {
                setFlash('error', 'Unable to parse data from the uploaded sheet or URL.');
                header('Location: ?page=admin-smart-sheets');
                exit;
            }

            // Auto-Classification Intent Matcher
            $headerStr = strtolower(implode(' ', $columns));
            $category = 'custom';
            if (str_contains($headerStr, 'punch') || str_contains($headerStr, 'attendance') || str_contains($headerStr, 'clock')) {
                $category = 'attendance';
            } elseif (str_contains($headerStr, 'salary') || str_contains($headerStr, 'payroll') || str_contains($headerStr, 'net pay')) {
                $category = 'payroll';
            } elseif (str_contains($headerStr, 'email') && str_contains($headerStr, 'designation')) {
                $category = 'employees';
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
                'column_sums' => $numericSums
            ];

            $db = getDBConnection();
            $stmt = $db->prepare("INSERT INTO smart_sheet_uploads (title, file_type, original_filename, category, columns_json, rows_json, summary_json, uploaded_by) VALUES (?, 'csv', ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $title,
                $_FILES['sheet_file']['name'] ?? 'Google Sheet Link',
                $category,
                json_encode($columns),
                json_encode($rows),
                json_encode($summary),
                $user['id']
            ]);

            setFlash('success', "🎉 Smart Sheet '{$title}' parsed successfully! Auto-classified as: " . strtoupper($category));
            header('Location: ?page=admin-smart-sheets');
            exit;
        }
    }
}