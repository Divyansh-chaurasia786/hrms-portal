<?php
// views/admin/smart_sheets.php
$title = "AI Smart Sheet & Document Hub - Ecofone HRMS";
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>
<main class="flex-1 min-w-0 overflow-y-auto bg-slate-900 text-slate-100 p-4 sm:p-8">
    <div class="max-w-6xl mx-auto space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center">
                        <i data-lucide="sparkles" class="w-5 h-5"></i>
                    </div>
                    Smart Sheet & AI Document Ingestion Hub
                </h1>
                <p class="text-xs text-slate-400 mt-1">Exclusive Head HR & Junior HR portal. Upload any Google Sheet link, Excel, or CSV to auto-generate dynamic modules & formula summaries.</p>
            </div>
            <button onclick="document.getElementById('uploadSmartSheetModal').classList.remove('hidden')" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs shadow-lg shadow-indigo-600/30">
                <i data-lucide="plus" class="w-4 h-4"></i> Upload New Sheet
            </button>
        </div>

        <?php $flash = getFlash(); if ($flash): ?>
            <div class="p-4 rounded-2xl text-xs font-medium <?= $flash['type'] === 'error' ? 'bg-rose-500/10 text-rose-300 border border-rose-500/30' : 'bg-emerald-500/10 text-emerald-300 border border-emerald-500/30' ?>">
                <?= $flash['message'] ?>
            </div>
        <?php endif; ?>

        <!-- Sheets List & Dynamic Views -->
        <div class="space-y-6">
            <?php if (empty($sheets)): ?>
                <div class="bg-slate-900 border border-slate-800 rounded-3xl p-12 text-center text-slate-500 text-xs shadow-xl">
                    <i data-lucide="table" class="w-8 h-8 text-slate-600 mx-auto mb-2"></i>
                    No custom or imported smart sheets yet. Click 'Upload New Sheet' to import your first Google Sheet or Excel file!
                </div>
            <?php else: ?>
                <?php foreach ($sheets as $s): 
                    $cols = json_decode($s['columns_json'], true) ?: [];
                    $rows = json_decode($s['rows_json'], true) ?: [];
                    $sum = json_decode($s['summary_json'], true) ?: [];
                ?>
                    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-800 pb-4">
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                                    Category: <?= strtoupper($s['category']) ?>
                                </span>
                                <h3 class="text-base font-bold text-white mt-1.5"><?= htmlspecialchars($s['title']) ?></h3>
                                <p class="text-[11px] text-slate-500">Source: <?= htmlspecialchars($s['original_filename']) ?> • <?= count($rows) ?> rows detected</p>
                            </div>
                            <div class="text-xs text-slate-400">
                                Uploaded <?= date('d M Y, h:i A', strtotime($s['created_at'])) ?>
                            </div>
                        </div>

                        <!-- Auto-Calculated Formula Summary Cards -->
                        <?php if (!empty($sum['column_sums'])): ?>
                            <div class="flex flex-wrap gap-3">
                                <?php foreach ($sum['column_sums'] as $colIdx => $colSum): if (isset($cols[$colIdx])): ?>
                                    <div class="bg-slate-950 p-3 rounded-xl border border-slate-800">
                                        <div class="text-[10px] uppercase font-bold text-slate-500"><?= htmlspecialchars($cols[$colIdx]) ?> (SUM)</div>
                                        <div class="text-sm font-extrabold text-emerald-400 mt-0.5"><?= number_format($colSum, 2) ?></div>
                                    </div>
                                <?php endif; endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Dynamic Table View -->
                        <div class="overflow-x-auto max-h-72 border border-slate-800 rounded-2xl">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-950 sticky top-0">
                                    <tr class="border-b border-slate-800 text-slate-400">
                                        <?php foreach ($cols as $c): ?>
                                            <th class="p-3 font-bold"><?= htmlspecialchars($c) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800/60">
                                    <?php foreach (array_slice($rows, 0, 20) as $row): ?>
                                        <tr class="hover:bg-slate-800/30">
                                            <?php foreach ($row as $val): ?>
                                                <td class="p-3 text-slate-300"><?= htmlspecialchars((string)$val) ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<div id="uploadSmartSheetModal" class="hidden fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl space-y-4">
        <h3 class="text-lg font-bold text-white">Import Smart Sheet / Google Sheet</h3>
        <form action="?action=upload-smart-sheet" method="POST" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1">Sheet Title</label>
                <input type="text" name="title" required placeholder="e.g. Q3 Sales & Calling Roster" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-xs text-white">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1">Upload CSV / Excel File</label>
                <input type="file" name="sheet_file" accept=".csv" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-xs text-white">
            </div>
            <div class="text-center text-xs text-slate-500 font-bold">— OR —</div>
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1">Google Sheet Public Link</label>
                <input type="url" name="sheet_url" placeholder="https://docs.google.com/spreadsheets/d/..." class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-xs text-white">
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('uploadSmartSheetModal').classList.add('hidden')" class="px-4 py-2 rounded-xl text-xs text-slate-400">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs">Import Sheet</button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>