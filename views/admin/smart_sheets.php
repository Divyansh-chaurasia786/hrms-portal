<!-- views/admin/smart_sheets.php -->
<?php
$user = authUser();
$db = getDBConnection();

$sheets = $db->query("
    SELECT s.*, u.name as uploader_name
    FROM smart_sheet_uploads s
    JOIN users u ON s.uploaded_by = u.id
    ORDER BY s.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<div class="space-y-6" x-data="{ uploadModalOpen: false, selectedSheet: null }">
    <!-- Header Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="file-spreadsheet" class="w-5 h-5"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Smart Sheet & AI Ingestion Hub</h1>
                <p class="text-xs text-slate-500 mt-0.5">Exclusive to Head HR & Junior HR. Import Google Sheets, Excel, or CSV with auto-structuring & formula sums.</p>
            </div>
        </div>

        <button type="button" @click="uploadModalOpen = true" class="px-4 py-2 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white text-xs font-bold rounded-xl shadow-sm transition flex items-center gap-1.5 cursor-pointer">
            <i data-lucide="upload" class="w-4 h-4"></i> Upload / Import Sheet
        </button>
    </div>

    <!-- Sheets Grid -->
    <?php if (empty($sheets)): ?>
        <div class="bg-white p-12 rounded-2xl border border-slate-200 shadow-sm text-center">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-3">
                <i data-lucide="file-spreadsheet" class="w-6 h-6"></i>
            </div>
            <h3 class="text-sm font-bold text-slate-800">No Sheets Imported Yet</h3>
            <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">Upload an existing Google Sheet URL or CSV file to auto-generate structured dashboards and formula calculations.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($sheets as $s): 
                $sTitle = $s['title'] ?? 'Custom Sheet';
                $sCat = $s['category'] ?? 'General';
                $rows = json_decode($s['rows_json'] ?? '[]', true) ?: [];
                $rowCnt = count($rows);
            ?>
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between space-y-4 hover:border-emerald-300 transition">
                    <div>
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($sTitle) ?></h3>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <?= htmlspecialchars($sCat) ?>
                            </span>
                        </div>
                        <div class="text-xs text-slate-500 mt-2 space-y-1">
                            <div>Rows: <strong class="text-slate-800"><?= $rowCnt ?></strong></div>
                            <div>Imported by: <strong class="text-slate-800"><?= htmlspecialchars($s['uploader_name'] ?? 'HR') ?></strong></div>
                            <div class="text-[10px] font-mono text-slate-400"><?= date('d M Y, h:i A', strtotime($s['created_at'] ?? 'now')) ?></div>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                        <button type="button" @click="selectedSheet = <?= htmlspecialchars(json_encode($s)) ?>" class="text-xs font-bold text-emerald-600 hover:text-emerald-700 flex items-center gap-1">
                            <i data-lucide="eye" class="w-3.5 h-3.5"></i> View Dynamic UI
                        </button>
                        <form action="?action=delete-smart-sheet" method="POST" onsubmit="return confirm('Delete this smart sheet?');" class="inline">
                            <input type="hidden" name="sheet_id" value="<?= (int)($s['id'] ?? 0) ?>">
                            <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Upload Modal -->
    <div x-show="uploadModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" @click="uploadModalOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="font-bold text-sm text-slate-900">Import Google Sheet / CSV</h3>
                    <button type="button" @click="uploadModalOpen = false" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-4 h-4"></i></button>
                </div>
                <form action="?action=upload-smart-sheet" method="POST" enctype="multipart/form-data" class="space-y-4 pt-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Sheet Title *</label>
                        <input type="text" name="sheet_title" required placeholder="e.g. Q3 Employee Attendance & Asset Register" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Google Sheets Public Link (Optional)</label>
                        <input type="url" name="google_sheet_url" placeholder="https://docs.google.com/spreadsheets/d/..." class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Or Upload CSV / Excel File</label>
                        <input type="file" name="sheet_file" accept=".csv, .xlsx, .xls" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="uploadModalOpen = false" class="px-4 py-2 bg-slate-100 rounded-xl text-xs font-bold text-slate-600">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-emerald-600 text-white rounded-xl text-xs font-bold shadow-sm">Process & Ingest</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>