<!-- views/admin/smart_sheets.php -->
<?php
$user = authUser();
$db = getDBConnection();

$sheets = $db->query("
    SELECT s.id, s.title, s.category, s.uploaded_by, s.created_at, 
           COALESCE(JSON_LENGTH(s.rows_json), 0) as record_count, 
           u.name as uploader_name 
    FROM smart_sheet_uploads s 
    JOIN users u ON s.uploaded_by = u.id 
    ORDER BY s.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<div class="space-y-6" x-data="{
    uploadModalOpen: false,
    viewModalOpen: false,
    selectedSheet: null,
    columns: [],
    rows: [],
    activeCell: { r: 0, c: 0, coord: 'A1', val: '' },
    formulaInput: '',
    sheetSearch: '',
    isLoadingSheet: false,
    isSavingSheet: false,
    activeTab: 'all',
    cellBold: false,
    cellItalic: false,
    cellAlign: 'left',
    syncSuccessBanner: false,

    // Convert col index (0, 1, 2...) to Excel letters (A, B, C... AA)
    getColLetter(idx) {
        let letter = '';
        while (idx >= 0) {
            letter = String.fromCharCode((idx % 26) + 65) + letter;
            idx = Math.floor(idx / 26) - 1;
        }
        return letter;
    },

    async openSheetViewer(s) {
        this.selectedSheet = s;
        this.sheetSearch = '';
        this.columns = [];
        this.rows = [];
        this.viewModalOpen = true;
        this.isLoadingSheet = true;
        this.syncSuccessBanner = false;

        try {
            const res = await fetch('?action=get-smart-sheet-data&sheet_id=' + s.id);
            const data = await res.json();
            this.columns = data.columns || [];
            this.rows = data.rows || [];
            this.selectCell(0, 0);
        } catch(e) {
            console.error(e);
        } finally {
            this.isLoadingSheet = false;
        }
    },

    selectCell(r, c) {
        this.activeCell.r = r;
        this.activeCell.c = c;
        this.activeCell.coord = this.getColLetter(c) + (r + 1);
        this.activeCell.val = (this.rows[r] && this.rows[r][c] !== undefined) ? this.rows[r][c] : '';
        this.formulaInput = this.activeCell.val;
    },

    updateActiveCellValue() {
        if (this.rows[this.activeCell.r]) {
            this.rows[this.activeCell.r][this.activeCell.c] = this.formulaInput;
            this.activeCell.val = this.formulaInput;
        }
    },

    addRow() {
        const newRow = new Array(this.columns.length).fill('');
        this.rows.push(newRow);
    },

    addColumn() {
        const newColName = 'Column ' + (this.columns.length + 1);
        this.columns.push(newColName);
        this.rows.forEach(r => r.push(''));
    },

    async saveAndSyncWorkbook() {
        this.isSavingSheet = true;
        const formData = new FormData();
        formData.append('sheet_id', this.selectedSheet.id);
        formData.append('columns_json', JSON.stringify(this.columns));
        formData.append('rows_json', JSON.stringify(this.rows));

        try {
            const res = await fetch('?action=save-smart-sheet-data', {
                method: 'POST',
                body: formData
            });
            const result = await res.json();
            if (result.success) {
                this.syncSuccessBanner = true;
                setTimeout(() => this.syncSuccessBanner = false, 4000);
            }
        } catch(e) {
            alert('Failed to save workbook changes');
        } finally {
            this.isSavingSheet = false;
        }
    },

    exportAsCsv() {
        let csv = this.columns.map(c => '\"' + String(c).replace(/\"/g, '\"\"') + '\"').join(',') + '\n';
        this.rows.forEach(r => {
            csv += r.map(c => '\"' + String(c).replace(/\"/g, '\"\"') + '\"').join(',') + '\n';
        });
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = (this.selectedSheet ? this.selectedSheet.title : 'Workbook') + '.csv';
        link.click();
    },

    get filteredRows() {
        if (!this.sheetSearch.trim()) return this.rows;
        const q = this.sheetSearch.toLowerCase();
        return this.rows.filter(r => {
            return r.some(cell => String(cell).toLowerCase().includes(q));
        });
    }
}">
    <!-- Header Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-3xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3.5">
            <div class="w-11 h-11 rounded-2xl bg-emerald-600 text-white flex items-center justify-center font-black shadow-md shadow-emerald-600/30 shrink-0">
                <i data-lucide="file-spreadsheet" class="w-6 h-6"></i>
            </div>
            <div>
                <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">Smart Sheet & Excel Ingestion Studio</h1>
                <p class="text-xs text-slate-500 mt-0.5">Upload Google Sheets or Excel files with live spreadsheet instruments, formula editor, and website-wide automatic synchronization.</p>
            </div>
        </div>

        <button type="button" @click="uploadModalOpen = true" class="px-4 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white text-xs font-extrabold rounded-2xl shadow-md shadow-emerald-600/30 transition flex items-center gap-2 cursor-pointer">
            <i data-lucide="upload" class="w-4 h-4"></i> Upload / Ingest Sheet
        </button>
    </div>

    <!-- Dynamic Category Filter Tabs -->
    <div class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-1">
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
                $cleanRowCnt = (int)($s['record_count'] ?? 0);
                $sCardData = [
                    'id' => (int)$s['id'],
                    'title' => $sTitle,
                    'category' => $sCat,
                    'record_count' => $cleanRowCnt,
                    'uploader_name' => $s['uploader_name'] ?? 'HR'
                ];
            ?>
                <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm flex flex-col justify-between space-y-4 hover:border-emerald-400 transition group" x-show="activeTab === 'all' || activeTab === '<?= htmlspecialchars(addslashes($sCat)) ?>'">
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
                        <button type="button" @click="openSheetViewer(<?= htmlspecialchars(json_encode($sCardData)) ?>)" class="text-xs font-extrabold text-emerald-800 hover:text-emerald-900 flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-100 hover:bg-emerald-200 transition cursor-pointer shadow-2xs">
                            <i data-lucide="layout-grid" class="w-4 h-4"></i> Open Excel Studio
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

    <!-- 📊 ENTERPRISE EXCEL WORKBOOK STUDIO MODAL -->
    <div x-show="viewModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-2 sm:p-4" style="display:none;">
        <div @click.away="viewModalOpen = false" class="bg-white rounded-3xl max-w-6xl w-full p-4 sm:p-6 shadow-2xl border border-slate-200 space-y-3 max-h-[95vh] flex flex-col">
            
            <!-- Excel Header Bar -->
            <div class="flex items-center justify-between pb-2 border-b border-slate-100 shrink-0 flex-wrap gap-2">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-emerald-600 text-white flex items-center justify-center font-black text-sm shadow-md shadow-emerald-600/30">
                        <i data-lucide="file-spreadsheet" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-extrabold text-slate-900" x-text="selectedSheet ? selectedSheet.title : 'Excel Workbook'"></h3>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-800" x-text="selectedSheet ? selectedSheet.category : ''"></span>
                        </div>
                        <p class="text-xs text-slate-500 font-mono" x-text="'Excel Engine • ' + rows.length + ' Rows • ' + columns.length + ' Columns • Auto-Synced Website-Wide'"></p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" @click="saveAndSyncWorkbook()" :disabled="isSavingSheet" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm cursor-pointer disabled:opacity-50">
                        <i data-lucide="save" class="w-4 h-4"></i>
                        <span x-text="isSavingSheet ? 'Saving & Syncing...' : 'Save & Sync All'"></span>
                    </button>
                    <button type="button" @click="exportAsCsv()" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer">
                        <i data-lucide="download" class="w-4 h-4"></i> Export CSV
                    </button>
                    <button type="button" @click="viewModalOpen = false" class="p-2 text-slate-400 hover:text-slate-700 rounded-xl hover:bg-slate-100 transition">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>

            <!-- Sync Success Toast -->
            <div x-show="syncSuccessBanner" class="p-3 bg-emerald-50 border border-emerald-200 rounded-2xl text-emerald-900 text-xs font-bold flex items-center justify-between transition">
                <span class="flex items-center gap-2">
                    <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600"></i>
                    ✅ Workbook changes saved! Employees, attendance, and all website sections have been automatically updated!
                </span>
                <button type="button" @click="syncSuccessBanner = false" class="text-emerald-700 hover:text-emerald-900">✕</button>
            </div>

            <!-- 🛠️ EXCEL INSTRUMENTS TOOLBAR RIBBON -->
            <div class="bg-slate-50 p-2 rounded-2xl border border-slate-200 flex items-center justify-between gap-2 shrink-0 flex-wrap text-xs">
                <div class="flex items-center gap-1.5 flex-wrap">
                    <!-- Bold / Italic / Alignment -->
                    <button type="button" @click="cellBold = !cellBold" :class="cellBold ? 'bg-slate-200 text-slate-900 font-black' : 'text-slate-600 hover:bg-slate-200'" class="p-1.5 rounded-lg text-xs font-bold transition w-7 h-7 flex items-center justify-center">B</button>
                    <button type="button" @click="cellItalic = !cellItalic" :class="cellItalic ? 'bg-slate-200 text-slate-900' : 'text-slate-600 hover:bg-slate-200'" class="p-1.5 rounded-lg text-xs italic transition w-7 h-7 flex items-center justify-center">I</button>
                    <div class="h-4 w-px bg-slate-300 mx-1"></div>

                    <!-- Add Row & Column -->
                    <button type="button" @click="addRow()" class="px-2.5 py-1 bg-white hover:bg-emerald-50 text-slate-700 border border-slate-200 rounded-lg font-bold flex items-center gap-1 cursor-pointer">
                        <i data-lucide="plus" class="w-3.5 h-3.5 text-emerald-600"></i> Row
                    </button>
                    <button type="button" @click="addColumn()" class="px-2.5 py-1 bg-white hover:bg-emerald-50 text-slate-700 border border-slate-200 rounded-lg font-bold flex items-center gap-1 cursor-pointer">
                        <i data-lucide="plus" class="w-3.5 h-3.5 text-emerald-600"></i> Column
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <div class="relative w-48">
                        <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2"></i>
                        <input type="text" x-model="sheetSearch" placeholder="Find in sheet..." class="w-full bg-white border border-slate-200 rounded-lg pl-7 pr-2 py-1 text-xs text-slate-800 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>
            </div>

            <!-- 📐 EXCEL FORMULA BAR (fx & Active Cell Coordinate) -->
            <div class="flex items-center gap-2 bg-white px-3 py-1.5 border border-slate-200 rounded-xl shrink-0 font-mono text-xs shadow-2xs">
                <div class="w-12 text-center font-bold text-slate-700 border-r border-slate-200 pr-2" x-text="activeCell.coord"></div>
                <div class="font-bold text-slate-400 italic">fx</div>
                <input type="text" x-model="formulaInput" @input="updateActiveCellValue()" placeholder="Cell Value / Formula" class="flex-1 text-xs font-medium text-slate-900 bg-transparent focus:outline-none">
            </div>

            <!-- Loading Spinner State -->
            <div x-show="isLoadingSheet" class="py-20 text-center text-slate-400">
                <i data-lucide="loader-2" class="w-8 h-8 mx-auto animate-spin text-emerald-600 mb-2"></i>
                <p class="text-xs font-bold text-slate-600">Loading Excel grid structure...</p>
            </div>

            <!-- 📊 FULL EXCEL SPREADSHEET GRID -->
            <div x-show="!isLoadingSheet" class="overflow-auto border border-slate-300 rounded-xl flex-1 max-h-[520px] shadow-inner bg-white select-text">
                <table class="w-full text-left text-xs border-collapse font-sans">
                    <!-- Excel Top Header Letters (A, B, C, D...) -->
                    <thead class="bg-slate-100 text-slate-600 font-mono text-[11px] sticky top-0 z-20 select-none">
                        <tr class="border-b border-slate-300">
                            <th class="py-2 px-2 border-r border-slate-300 w-12 text-center bg-slate-200 text-slate-500 font-bold">#</th>
                            <template x-for="(col, colIdx) in columns" :key="colIdx">
                                <th class="py-2 px-3 border-r border-slate-300 tracking-wider text-center font-bold" x-text="getColLetter(colIdx)"></th>
                            </template>
                        </tr>
                        <!-- Custom Column Name Header Row -->
                        <tr class="border-b-2 border-slate-400 bg-slate-800 text-white font-bold text-xs">
                            <th class="py-2 px-2 border-r border-slate-700 text-center bg-slate-900 font-mono text-[10px] text-slate-400">COL</th>
                            <template x-for="(col, colIdx) in columns" :key="colIdx">
                                <th class="py-2.5 px-3 border-r border-slate-700 whitespace-nowrap">
                                    <input type="text" x-model="columns[colIdx]" class="bg-transparent text-white font-bold text-xs focus:outline-none focus:ring-1 focus:ring-emerald-400 rounded px-1 w-full">
                                </th>
                            </template>
                        </tr>
                    </thead>
                    <!-- Excel Table Rows (1, 2, 3...) -->
                    <tbody class="divide-y divide-slate-200">
                        <template x-for="(row, rowIdx) in filteredRows" :key="rowIdx">
                            <tr class="hover:bg-emerald-50/30 transition">
                                <!-- Excel Row Number Index -->
                                <td class="py-1.5 px-2 border-r border-slate-300 text-center font-mono text-[10px] text-slate-500 font-bold bg-slate-100 select-none" x-text="rowIdx + 1"></td>
                                <!-- Excel Cells -->
                                <template x-for="(cell, cellIdx) in row" :key="cellIdx">
                                    <td class="p-0 border-r border-slate-200" 
                                        :class="activeCell.r === rowIdx && activeCell.c === cellIdx ? 'ring-2 ring-emerald-500 bg-emerald-50/50 z-10' : ''"
                                        @click="selectCell(rowIdx, cellIdx)">
                                        <input type="text" 
                                               x-model="row[cellIdx]" 
                                               @focus="selectCell(rowIdx, cellIdx)"
                                               :class="{
                                                   'font-bold': cellBold,
                                                   'italic': cellItalic,
                                                   'text-emerald-700 font-bold': cell === 'Present' || cell === 'active',
                                                   'text-rose-600 font-bold': cell === 'Absent' || cell === 'inactive'
                                               }"
                                               class="w-full h-full py-2 px-3 bg-transparent text-xs text-slate-800 focus:outline-none border-0 min-w-[120px]">
                                    </td>
                                </template>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Modal Footer Stats -->
            <div class="flex items-center justify-between pt-2 border-t border-slate-100 shrink-0 text-xs text-slate-500">
                <span class="flex items-center gap-2 text-emerald-700 font-bold">
                    <i data-lucide="refresh-cw" class="w-3.5 h-3.5 text-emerald-600 animate-spin"></i>
                    All changes sync automatically to Employee Directory, Attendance, and Birthday Hub!
                </span>
                <div class="flex items-center gap-3">
                    <span class="font-mono text-slate-500" x-text="rows.length + ' Total Records'"></span>
                    <button type="button" @click="viewModalOpen = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition cursor-pointer">
                        Close Studio
                    </button>
                </div>
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
            <form action="?action=upload-smart-sheet" method="POST" enctype="multipart/form-data" class="space-y-4 pt-2" x-data="{ isSubmitting: false, selectedCat: 'auto' }" @submit="isSubmitting = true">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Sheet Title *</label>
                    <input type="text" name="sheet_title" required placeholder="e.g. BDA Lucknow Team, Monthly Sales Targets, Attendance" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Dataset Section / Category</label>
                    <select name="category" x-model="selectedCat" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-emerald-500">
                        <option value="auto">⚡ Auto-Create Section & Sync Website-Wide</option>
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
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Google Sheets Link (Must be public link)</label>
                    <input type="url" name="google_sheet_url" placeholder="https://docs.google.com/spreadsheets/d/1.../edit" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Or Upload File (Recommended .xlsx / .csv)</label>
                    <input type="file" name="sheet_file" accept=".csv, .xlsx, .xls, .tsv" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                </div>

                <div class="p-3 bg-amber-50 rounded-2xl border border-amber-200 text-amber-900 text-[11px] space-y-1">
                    <div class="font-bold flex items-center gap-1.5">
                        <i data-lucide="info" class="w-4 h-4 text-amber-600"></i> 100% Website-Wide Sync:
                    </div>
                    <p class="text-[10px] text-amber-800 leading-relaxed">
                        Data upload hote hi **Employee Directory, Attendance Logs, aur Birthday Hub** mein automatically sync aur populate ho jayega!
                    </p>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="uploadModalOpen = false" :disabled="isSubmitting" class="px-4 py-2 bg-slate-100 rounded-xl text-xs font-bold text-slate-600">Cancel</button>
                    <button type="submit" :disabled="isSubmitting" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-extrabold shadow-sm transition flex items-center gap-2 cursor-pointer disabled:opacity-50">
                        <span x-show="!isSubmitting">Process & Ingest</span>
                        <span x-show="isSubmitting" class="flex items-center gap-1.5" style="display: none;">
                            <i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i> Ingesting & Syncing Data...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>