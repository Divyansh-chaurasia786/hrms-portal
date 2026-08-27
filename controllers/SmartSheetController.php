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
            $title = trim($_POST['title'] ?? '');
            $sheetUrl = trim($_POST['sheet_url'] ?? '');
            $uploadedFile = $_FILES['sheet_file']['tmp_name'] ?? null;
            $originalName = $_FILES['sheet_file']['name'] ?? 'Spreadsheet';

            if (empty($title)) {
                $title = !empty($uploadedFile) ? pathinfo($originalName, PATHINFO_FILENAME) : 'Google Sheet Import';
            }

            // Parse via Universal Spreadsheet Parser (supports CSV, XLSX, TSV, XML, Google Sheets)
            $parsed = parseSpreadsheetData($uploadedFile, $sheetUrl, $originalName);
            $columns = $parsed['columns'] ?? [];
            $rows = $parsed['rows'] ?? [];

            if (empty($columns)) {
                setFlash('error', 'Unable to parse data from the uploaded sheet or URL. Ensure the Google Sheet is shared ("Anyone with the link can view") or upload a valid CSV / Excel (.xlsx) file.');
                header('Location: ?page=admin-smart-sheets');
                exit;
            }

            // Auto-Classification Intent Matcher
            $headerStr = strtolower(implode(' ', array_map('strval', $columns)));
            $category = 'custom';
            if (str_contains($headerStr, 'punch') || str_contains($headerStr, 'attendance') || str_contains($headerStr, 'clock') || str_contains($headerStr, 'in time')) {
                $category = 'attendance';
            } elseif (str_contains($headerStr, 'salary') || str_contains($headerStr, 'payroll') || str_contains($headerStr, 'net pay') || str_contains($headerStr, 'basic pay')) {
                $category = 'payroll';
            } elseif (str_contains($headerStr, 'email') && (str_contains($headerStr, 'designation') || str_contains($headerStr, 'department') || str_contains($headerStr, 'emp'))) {
                $category = 'employees';
            } elseif (str_contains($headerStr, 'phone') || str_contains($headerStr, 'lead') || str_contains($headerStr, 'calling') || str_contains($headerStr, 'client')) {
                $category = 'crm_leads';
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
            $stmt = $db->prepare("INSERT INTO smart_sheet_uploads (title, file_type, original_filename, category, columns_json, rows_json, summary_json, uploaded_by) VALUES (?, 'spreadsheet', ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $title,
                !empty($sheetUrl) ? 'Google Sheet Link: ' . substr($sheetUrl, 0, 80) : $originalName,
                $category,
                json_encode($columns),
                json_encode($rows),
                json_encode($summary),
                $user['id']
            ]);

            setFlash('success', "🎉 Smart Sheet '{$title}' parsed successfully (" . count($rows) . " rows)! Auto-classified as: " . strtoupper($category));
            header('Location: ?page=admin-smart-sheets');
            exit;
        }
    }
}