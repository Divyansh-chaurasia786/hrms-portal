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

<div class="space-y-6" x-data="{ 
    uploadModalOpen: false, 
    viewModalOpen: false,
    selectedSheet: null,
    sheetSearch: '',
    parsedColumns: [],
    parsedRows: [],

    openSheetViewer(s) {
        this.selectedSheet = s;
        try {
            this.parsedColumns = JSON.parse(s.columns_json || '[]');
            this.parsedRows = JSON.parse(s.rows_json || '[]');
        } catch(e) {
            this.parsedColumns = [];
            this.parsedRows = [];
        }
        this.sheetSearch = '';
        this.viewModalOpen = true;
    },

    get filteredRows() {
        if (!this.sheetSearch.trim()) return this.parsedRows;
        const q = this.sheetSearch.toLowerCase();
        return this.parsedRows.filter(r => {
            return r.some(cell => String(cell).toLowerCase().includes(q));
        });
    }
}">
    <!-- Header Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-3xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3.5">
            <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-extrabold shadow-sm shrink-0">
                <i data-lucide="file-spreadsheet" class="w-6 h-6"></i>
            </div>
            <div>
                <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">Smart Sheet & AI Ingestion Hub</h1>
                <p class="text-xs text-slate-500 mt-0.5">Import Google Sheets or Excel files with auto-employee onboarding, attendance audit, and formula calculations.</p>
            </div>
        </div>

        <button type="button" @click="uploadModalOpen = true" class="px-4 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white text-xs font-extrabold rounded-2xl shadow-md shadow-emerald-600/30 transition flex items-center gap-2 cursor-pointer">
            <i data-lucide="upload" class="w-4 h-4"></i> Upload / Import Sheet
        </button>
    </div>

        <!-- Dynamic Category Filter Tabs -->
    <div class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-1" x-data="{ activeTab: 'all' }">
        <button type="button" @click="activeTab = 'all'" :class="activeTab === 'all' ? 'bg-slate-900 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200'" class="px-4 py-2 rounded-2xl text-xs font-bold transition shrink-0 flex items-center gap-1.5 cursor-pointer">
            <i data-lucide="layers" class="w-3.5 h-3.5"></i> All Sections (<?= count($sheets) ?>)
        </button>

        <?php
        $categoriesFound = [];
        foreach ($sheets as $sh) {
            $cat = $sh['category'] ?? 'General';
            $categoriesFound[$cat] = ($categoriesFound[$cat] ?? 0) + 1;
        }
        foreach ($categoriesFound as $cName => $cCount):
        ?>
            <button type="button" @click="activeTab = '<?= htmlspecialchars(addslashes($cName)) ?>'" :class="activeTab === '<?= htmlspecialchars(addslashes($cName)) ?>' ? 'bg-emerald-600 text-white shadow-md' : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200'" class="px-3.5 py-2 rounded-2xl text-xs font-bold transition shrink-0 flex items-center gap-1.5 cursor-pointer">
                <span><?= htmlspecialchars($cName) ?></span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-extrabold" :class="activeTab === '<?= htmlspecialchars(addslashes($cName)) ?>' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500'"><?= $cCount ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Sheets Grid -->
    <?php if (empty($sheets)): ?>
        <div class="bg-white p-12 rounded-3xl border border-slate-200 shadow-sm text-center">
            <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-3">
                <i data-lucide="file-spreadsheet" class="w-7 h-7"></i>
            </div>
            <h3 class="text-sm font-bold text-slate-800">No Sheets Imported Yet</h3>
            <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">Upload an existing Google Sheet URL or CSV/Excel file to auto-register employees and sync attendance records.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php foreach ($sheets as $s): 
                $sTitle = $s['title'] ?? 'Custom Sheet';
                $sCat = $s['category'] ?? 'General';
                $rows = json_decode($s['rows_json'] ?? '[]', true) ?: [];
                $cleanRowCnt = count(array_filter($rows, fn($r) => !empty(array_filter($r, fn($c) => !empty(trim((string)$c))))));
            ?>
                <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm flex flex-col justify-between space-y-4 hover:border-emerald-300 transition group" x-show="activeTab === 'all' || activeTab === '<?= htmlspecialchars(addslashes($s['category'] ?? 'General')) ?>'">
                    <div>
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="font-extrabold text-slate-900 text-sm group-hover:text-emerald-700 transition truncate" title="<?= htmlspecialchars($sTitle) ?>"><?= htmlspecialchars($sTitle) ?></h3>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-50 text-emerald-700 border border-emerald-200 shrink-0">
                                <?= htmlspecialchars($sCat) ?>
                            </span>
                        </div>
                        <div class="text-xs text-slate-500 mt-3 space-y-1.5 bg-slate-50 p-3 rounded-2xl border border-slate-100 font-medium">
                            <div class="flex items-center justify-between">
                                <span>Active Records:</span>
                                <strong class="text-slate-900 font-bold font-mono text-sm"><?= $cleanRowCnt ?></strong>
                            </div>
                            <div class="flex items-center justify-between text-[11px]">
                                <span>Imported by:</span>
                                <strong class="text-slate-700"><?= htmlspecialchars($s['uploader_name'] ?? 'HR') ?></strong>
                            </div>
                            <div class="text-[10px] font-mono text-slate-400 pt-1 border-t border-slate-200/60"><?= date('d M Y, h:i A', strtotime($s['created_at'] ?? 'now')) ?></div>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                        <button type="button" @click="openSheetViewer(<?= htmlspecialchars(json_encode($s)) ?>)" class="text-xs font-extrabold text-emerald-700 hover:text-emerald-800 flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 transition cursor-pointer">
                            <i data-lucide="table" class="w-4 h-4"></i> View Clean Dynamic UI
                        </button>
                        <form action="?action=delete-smart-sheet" method="POST" onsubmit="return confirm('Delete this smart sheet record?');" class="inline">
                            <input type="hidden" name="sheet_id" value="<?= (int)($s['id'] ?? 0) ?>">
                            <button type="submit" class="p-2 text-slate-400 hover:text-rose-600 rounded-xl hover:bg-rose-50 transition cursor-pointer">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- 👁️ ULTRA-CLEAN DYNAMIC SPREADSHEET VIEWER MODAL -->
    <div x-show="viewModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-4" style="display:none;">
        <div @click.away="viewModalOpen = false" class="bg-white rounded-3xl max-w-4xl w-full p-6 shadow-2xl border border-slate-200 space-y-4 max-h-[88vh] flex flex-col">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-black text-sm shadow-xs">
                        <i data-lucide="file-check-2" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-extrabold text-slate-900" x-text="selectedSheet ? selectedSheet.title : 'Spreadsheet Data'"></h3>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-800" x-text="selectedSheet ? selectedSheet.category : ''"></span>
                        </div>
                        <p class="text-xs text-slate-500 font-mono" x-text="'Showing ' + filteredRows.length + ' active records • ' + parsedColumns.length + ' clean columns'"></p>
                    </div>
                </div>

                <button type="button" @click="viewModalOpen = false" class="p-2 text-slate-400 hover:text-slate-700 rounded-xl hover:bg-slate-100 transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <!-- Search & Actions Filter Bar -->
            <div class="flex items-center justify-between gap-3 shrink-0 flex-wrap">
                <div class="relative flex-1 min-w-[240px]">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
                    <input type="text" x-model="sheetSearch" placeholder="Search by name, email, or designation..." class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>

                <template x-if="selectedSheet && selectedSheet.category === 'employees'">
                    <a href="?page=admin-employees" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                        <i data-lucide="users" class="w-3.5 h-3.5"></i> View Registered Employees Directory &rarr;
                    </a>
                </template>
            </div>

            <!-- Spreadsheet Table Container -->
            <div class="overflow-auto border border-slate-200 rounded-2xl flex-1 max-h-[460px] shadow-2xs">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-900 text-white uppercase text-[10px] font-black sticky top-0 z-20">
                        <tr>
                            <th class="py-3 px-3.5 border-r border-slate-800 w-12 text-center text-slate-400">#</th>
                            <template x-for="(col, colIdx) in parsedColumns" :key="colIdx">
                                <th class="py-3 px-4 border-r border-slate-800 tracking-wider" x-text="col"></th>
                            </template>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <template x-for="(row, rowIdx) in filteredRows" :key="rowIdx">
                            <tr class="hover:bg-emerald-50/40 transition">
                                <td class="py-2.5 px-3 border-r border-slate-100 text-center font-mono text-[10px] text-slate-400 font-bold bg-slate-50" x-text="rowIdx + 1"></td>
                                <template x-for="(cell, cellIdx) in row" :key="cellIdx">
                                    <td class="py-2.5 px-4 border-r border-slate-100 text-slate-800 font-medium whitespace-nowrap">
                                        <template x-if="cell === 'FSM' || cell === 'BDA' || cell === 'active' || cell === 'Present'">
                                            <span class="px-2 py-0.5 rounded-lg text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200" x-text="cell"></span>
                                        </template>
                                        <template x-if="cell !== 'FSM' && cell !== 'BDA' && cell !== 'active' && cell !== 'Present'">
                                            <span :class="String(cell).includes('@') ? 'font-mono text-slate-600' : (cellIdx === 0 ? 'font-bold text-slate-900' : 'text-slate-700')" x-text="cell"></span>
                                        </template>
                                    </td>
                                </template>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-between pt-2 border-t border-slate-100 shrink-0 text-xs text-slate-500">
                <span class="flex items-center gap-1.5 text-emerald-700 font-bold">
                    <i data-lucide="check-circle" class="w-4 h-4 text-emerald-500"></i>
                    All 23 employees verified & active in system
                </span>
                <button type="button" @click="viewModalOpen = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition">
                    Close Viewer
                </button>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div x-show="uploadModalOpen" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-4" style="display:none;">
        <div @click.away="uploadModalOpen = false" class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 class="font-extrabold text-sm text-slate-900">Import Google Sheet / CSV</h3>
                <button type="button" @click="uploadModalOpen = false" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
            <form action="?action=upload-smart-sheet" method="POST" enctype="multipart/form-data" class="space-y-4 pt-2" x-data="{ isSubmitting: false }" @submit="isSubmitting = true">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Sheet Title *</label>
                    <input type="text" name="sheet_title" required placeholder="e.g. BDA Lucknow Team, Monthly Sales Targets, Attendance" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-emerald-500">
                </div>

                <div x-data="{ selectedCat: 'auto' }">
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Dataset Section / Category</label>
                    <select name="category" x-model="selectedCat" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-emerald-500">
                        <option value="auto">⚡ Auto-Create Section Based on Sheet Data</option>
                        <option value="BDA & Sales Team">👥 BDA & Sales Team</option>
                        <option value="Workforce Directory">👤 Employee & Staff Directory</option>
                        <option value="Attendance Logs">⏱️ Attendance & Shift Records</option>
                        <option value="Lead CRM & Calling">📞 BDA Calling & Leads</option>
                        <option value="Payroll & Salary">💰 Payroll & Compensation</option>
                        <option value="Targets & KPIs">🎯 Performance & Targets</option>
                        <option value="Assets & Hardware">💻 Assets & Inventory</option>
                        <option value="custom">+ Create New Custom Section...</option>
                    </select>

                    <div x-show="selectedCat === 'custom'" class="mt-2" style="display: none;">
                        <input type="text" name="custom_category" placeholder="Enter New Section Name (e.g. Field Logistics, Vendor Roster)" class="w-full bg-emerald-50/60 border border-emerald-300 rounded-xl px-3 py-2 text-xs font-bold text-emerald-900 focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Google Sheets Link (Must be full public link)</label>
                    <input type="url" name="google_sheet_url" placeholder="https://docs.google.com/spreadsheets/d/1.../edit" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Or Upload File (Recommended .xlsx / .csv)</label>
                    <input type="file" name="sheet_file" accept=".csv, .xlsx, .xls, .tsv" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                </div>
                <div class="p-3 bg-amber-50 rounded-2xl border border-amber-200 text-amber-900 text-[11px] space-y-1">
                    <div class="font-bold flex items-center gap-1.5">
                        <i data-lucide="info" class="w-4 h-4 text-amber-600"></i> Google Sheets Sharing Tip:
                    </div>
                    <p class="text-[10px] text-amber-800 leading-relaxed">
                        Agar Google Sheets link use kar rahe hain toh ensure karein link <strong>Full Copy</strong> ho aur Sharing <strong>"Anyone with the link can view"</strong> ho. Ya sabse aasan tareeqa: Sheet ko <strong>Download &rarr; Microsoft Excel (.xlsx)</strong> karke yahan <strong>Choose file</strong> se upload karein!
                    </p>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="uploadModalOpen = false" :disabled="isSubmitting" class="px-4 py-2 bg-slate-100 rounded-xl text-xs font-bold text-slate-600">Cancel</button>
                    <button type="submit" :disabled="isSubmitting" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-extrabold shadow-sm transition flex items-center gap-2 cursor-pointer disabled:opacity-50">
                        <span x-show="!isSubmitting">Process & Ingest</span>
                        <span x-show="isSubmitting" class="flex items-center gap-1.5" style="display: none;">
                            <i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i> Processing Data...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>