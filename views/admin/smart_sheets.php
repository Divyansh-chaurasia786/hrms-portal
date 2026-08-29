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

$initialSheetId = !empty($sheets) ? (int)$sheets[0]['id'] : 0;
?>

<div class="space-y-4" x-data="msExcelStudio" x-init="initStudio(<?= $initialSheetId ?>)">
    
    <!-- 🟢 MICROSOFT EXCEL 365 TOP RIBBON & TITLE BAR -->
    <div class="bg-[#107c41] text-white rounded-3xl p-3 sm:p-4 shadow-xl border border-emerald-800 flex flex-col gap-3">
        <!-- Top App Row -->
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-white text-[#107c41] flex items-center justify-center font-black text-lg shadow-md shrink-0">
                    <i data-lucide="file-spreadsheet" class="w-6 h-6"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-base font-extrabold tracking-tight text-white flex items-center gap-1.5">
                            <span>Microsoft Excel Enterprise Suite</span>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-white/20 text-white font-bold">Cloud Synced</span>
                        </h1>
                    </div>
                    <p class="text-xs text-emerald-100/90 font-medium">Full spreadsheet instruments, formulas, custom formatting, and 100% automatic website synchronization.</p>
                </div>
            </div>

            <!-- Top Action Buttons -->
            <div class="flex items-center gap-2 flex-wrap">
                <button type="button" @click="saveAndSyncWorkbook()" :disabled="isSaving" class="px-4 py-2 bg-white hover:bg-emerald-50 text-[#107c41] rounded-xl text-xs font-black shadow-md transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span x-text="isSaving ? 'Syncing...' : 'Save & Sync All'"></span>
                </button>
                <button type="button" @click="uploadModalOpen = true" class="px-3.5 py-2 bg-emerald-800/80 hover:bg-emerald-900 text-white rounded-xl text-xs font-bold border border-emerald-600 transition flex items-center gap-1.5 cursor-pointer">
                    <i data-lucide="upload" class="w-4 h-4"></i> Upload Sheet
                </button>
                <button type="button" @click="exportAsCsv()" class="px-3.5 py-2 bg-emerald-800/80 hover:bg-emerald-900 text-white rounded-xl text-xs font-bold border border-emerald-600 transition flex items-center gap-1.5 cursor-pointer">
                    <i data-lucide="download" class="w-4 h-4"></i> Export CSV
                </button>
            </div>
        </div>

        <!-- 📑 TOP WORKBOOK TABS (Click tab to open that exact sheet!) -->
        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pt-2 border-t border-emerald-600/60">
            <div class="text-[11px] uppercase font-bold text-emerald-200 tracking-wider mr-1 shrink-0 flex items-center gap-1">
                <i data-lucide="layers" class="w-3.5 h-3.5"></i> Sheets:
            </div>

            <?php foreach ($sheets as $idx => $sh): ?>
                <button type="button" 
                        @click="switchSheet(<?= (int)$sh['id'] ?>)" 
                        :class="currentSheetId === <?= (int)$sh['id'] ?> ? 'bg-white text-[#107c41] shadow-md font-black' : 'bg-emerald-800/60 text-emerald-100 hover:bg-emerald-700/80 font-bold border border-emerald-700'" 
                        class="px-3.5 py-1.5 rounded-xl text-xs transition shrink-0 flex items-center gap-1.5 cursor-pointer">
                    <span><?= htmlspecialchars($sh['title']) ?></span>
                    <span class="px-1.5 py-0.2 rounded-full text-[9px] font-mono" :class="currentSheetId === <?= (int)$sh['id'] ?> ? 'bg-emerald-100 text-emerald-800' : 'bg-emerald-900 text-emerald-300'"><?= (int)$sh['record_count'] ?></span>
                </button>
            <?php endforeach; ?>

            <button type="button" @click="uploadModalOpen = true" class="px-3 py-1.5 rounded-xl text-xs font-bold bg-emerald-700/50 hover:bg-emerald-600 text-white border border-dashed border-emerald-400 transition shrink-0 flex items-center gap-1 cursor-pointer">
                <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add Sheet
            </button>
        </div>
    </div>

    <!-- ⚡ SYNC SUCCESS TOAST BANNER -->
    <div x-show="syncSuccessBanner" class="p-3.5 bg-emerald-50 border border-emerald-200 rounded-2xl text-emerald-900 text-xs font-bold flex items-center justify-between shadow-sm transition" style="display: none;">
        <span class="flex items-center gap-2">
            <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600"></i>
            ✅ All sheet changes saved! Employee Directory, Attendance Logs, and Birthday Hub have been automatically updated!
        </span>
        <button type="button" @click="syncSuccessBanner = false" class="text-emerald-700 hover:text-emerald-900 font-bold">✕</button>
    </div>

    <!-- 🛠️ MICROSOFT EXCEL ONLINE WORKSPACE CONTAINER -->
    <div class="bg-white rounded-3xl border border-slate-300 shadow-xl overflow-hidden flex flex-col">
        
        <!-- 1. EXCEL TOOLBAR RIBBON (Instruments & Controls) -->
        <div class="bg-slate-50 p-2.5 border-b border-slate-200 flex items-center justify-between gap-3 shrink-0 flex-wrap text-xs">
            <div class="flex items-center gap-2 flex-wrap">
                
                <!-- Font Family & Size -->
                <div class="flex items-center gap-1 bg-white border border-slate-300 rounded-xl px-2 py-1 shadow-2xs">
                    <select x-model="fontFamily" class="bg-transparent text-xs font-semibold text-slate-700 focus:outline-none">
                        <option value="font-sans">Segoe UI (Default)</option>
                        <option value="font-mono">JetBrains Mono</option>
                        <option value="font-serif">Georgia / Serif</option>
                    </select>
                    <select x-model="fontSize" class="bg-transparent text-xs font-bold text-slate-700 border-l border-slate-200 pl-1 focus:outline-none">
                        <option value="text-[11px]">10</option>
                        <option value="text-xs">11</option>
                        <option value="text-sm">12</option>
                        <option value="text-base">14</option>
                    </select>
                </div>

                <!-- Text Formatting: Bold, Italic, Strikethrough -->
                <div class="flex items-center bg-white border border-slate-300 rounded-xl p-0.5 shadow-2xs">
                    <button type="button" @click="isBold = !isBold" :class="isBold ? 'bg-emerald-100 text-emerald-900 font-black' : 'text-slate-700 hover:bg-slate-100'" class="w-7 h-7 rounded-lg text-xs font-bold transition flex items-center justify-center cursor-pointer">B</button>
                    <button type="button" @click="isItalic = !isItalic" :class="isItalic ? 'bg-emerald-100 text-emerald-900' : 'text-slate-700 hover:bg-slate-100'" class="w-7 h-7 rounded-lg text-xs italic font-bold transition flex items-center justify-center cursor-pointer">I</button>
                </div>

                <!-- Cell Highlight Colors -->
                <div class="flex items-center gap-1 bg-white border border-slate-300 rounded-xl px-2 py-1 shadow-2xs">
                    <span class="text-[10px] uppercase font-bold text-slate-400">Color:</span>
                    <button type="button" @click="cellBg = 'bg-white'" class="w-4 h-4 rounded-full bg-white border border-slate-300 cursor-pointer" title="Clear"></button>
                    <button type="button" @click="cellBg = 'bg-emerald-100 text-emerald-900 font-bold'" class="w-4 h-4 rounded-full bg-emerald-300 cursor-pointer" title="Green"></button>
                    <button type="button" @click="cellBg = 'bg-amber-100 text-amber-900 font-bold'" class="w-4 h-4 rounded-full bg-amber-300 cursor-pointer" title="Yellow"></button>
                    <button type="button" @click="cellBg = 'bg-rose-100 text-rose-900 font-bold'" class="w-4 h-4 rounded-full bg-rose-300 cursor-pointer" title="Red"></button>
                    <button type="button" @click="cellBg = 'bg-indigo-100 text-indigo-900 font-bold'" class="w-4 h-4 rounded-full bg-indigo-300 cursor-pointer" title="Blue"></button>
                </div>

                <div class="h-5 w-px bg-slate-300 mx-0.5"></div>

                <!-- Add Row & Column -->
                <div class="flex items-center gap-1">
                    <button type="button" @click="addRow()" class="px-2.5 py-1 bg-white hover:bg-emerald-50 text-slate-700 hover:text-emerald-800 border border-slate-300 rounded-xl font-bold flex items-center gap-1 cursor-pointer shadow-2xs transition">
                        <i data-lucide="plus" class="w-3.5 h-3.5 text-emerald-600"></i> Row
                    </button>
                    <button type="button" @click="addColumn()" class="px-2.5 py-1 bg-white hover:bg-emerald-50 text-slate-700 hover:text-emerald-800 border border-slate-300 rounded-xl font-bold flex items-center gap-1 cursor-pointer shadow-2xs transition">
                        <i data-lucide="plus" class="w-3.5 h-3.5 text-emerald-600"></i> Column
                    </button>
                </div>
            </div>

            <!-- Search in Current Sheet -->
            <div class="relative w-56">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5"></i>
                <input type="text" x-model="searchQuery" placeholder="Find in spreadsheet..." class="w-full bg-white border border-slate-300 rounded-xl pl-8 pr-3 py-1.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 shadow-2xs">
            </div>
        </div>

        <!-- 2. EXCEL FORMULA BAR (fx & Coordinate Box) -->
        <div class="flex items-center gap-2.5 bg-white px-4 py-2 border-b border-slate-200 shrink-0 font-mono text-xs shadow-inner">
            <div class="w-14 text-center font-black text-slate-800 bg-slate-100 py-1 rounded-lg border border-slate-300" x-text="activeCell.coord"></div>
            <div class="font-bold text-slate-400 italic text-sm">fx</div>
            <input type="text" x-model="formulaInput" @input="updateActiveCellValue()" placeholder="Enter formula or cell text..." class="flex-1 text-xs font-semibold text-slate-900 bg-transparent focus:outline-none">
        </div>

        <!-- 3. LOADING SPINNER -->
        <div x-show="isLoading" class="py-28 text-center text-slate-400">
            <i data-lucide="loader-2" class="w-10 h-10 mx-auto animate-spin text-[#107c41] mb-2"></i>
            <p class="text-xs font-bold text-slate-700">Loading Excel Worksheet Data...</p>
        </div>

        <!-- 4. NATIVE EXCEL SPREADSHEET CANVAS GRID -->
        <div x-show="!isLoading" class="overflow-auto max-h-[640px] select-text bg-slate-200">
            <table class="w-full text-left text-xs border-collapse font-sans bg-white">
                <!-- Top Excel Letters Header (A, B, C, D...) -->
                <thead class="bg-[#f3f4f6] text-slate-600 font-mono text-[11px] sticky top-0 z-20 select-none shadow-xs">
                    <tr class="border-b border-slate-300">
                        <th class="py-1.5 px-2 border-r border-slate-300 w-12 text-center bg-slate-200 text-slate-500 font-black">#</th>
                        <template x-for="(col, colIdx) in columns" :key="colIdx">
                            <th class="py-1.5 px-3 border-r border-slate-300 tracking-wider text-center font-bold" x-text="getColLetter(colIdx)"></th>
                        </template>
                    </tr>
                    <!-- Editable Column Name Row -->
                    <tr class="border-b-2 border-slate-400 bg-slate-800 text-white font-bold text-xs">
                        <th class="py-2 px-2 border-r border-slate-700 text-center bg-slate-900 font-mono text-[10px] text-slate-400">COL</th>
                        <template x-for="(col, colIdx) in columns" :key="colIdx">
                            <th class="py-2 px-2 border-r border-slate-700 whitespace-nowrap min-w-[140px]">
                                <input type="text" x-model="columns[colIdx]" class="bg-transparent text-white font-bold text-xs focus:outline-none focus:ring-1 focus:ring-emerald-400 rounded px-1.5 py-0.5 w-full">
                            </th>
                        </template>
                    </tr>
                </thead>

                <!-- Excel Row Cells (1, 2, 3...) -->
                <tbody class="divide-y divide-slate-200">
                    <template x-for="(row, rowIdx) in filteredRows" :key="rowIdx">
                        <tr class="hover:bg-emerald-50/30 transition">
                            <!-- Left Row Number Index -->
                            <td class="py-1 px-2 border-r border-slate-300 text-center font-mono text-[10px] text-slate-500 font-bold bg-slate-100 select-none" x-text="rowIdx + 1"></td>
                            <!-- Cell Inputs -->
                            <template x-for="(cell, cellIdx) in row" :key="cellIdx">
                                <td class="p-0 border-r border-slate-200 relative" 
                                    :class="activeCell.r === rowIdx && activeCell.c === cellIdx ? 'ring-2 ring-emerald-600 bg-emerald-50/70 z-10' : ''"
                                    @click="selectCell(rowIdx, cellIdx)">
                                    <input type="text" 
                                           x-model="row[cellIdx]" 
                                           @focus="selectCell(rowIdx, cellIdx)"
                                           :class="[
                                               fontSize,
                                               fontFamily,
                                               isBold ? 'font-bold' : '',
                                               isItalic ? 'italic' : '',
                                               cell === 'Present' || cell === 'active' ? 'text-emerald-700 font-bold' : (cell === 'Absent' || cell === 'inactive' ? 'text-rose-600 font-bold' : (String(cell).includes('@') ? 'font-mono text-indigo-700' : 'text-slate-800'))
                                           ]"
                                           class="w-full h-full py-2 px-3 bg-transparent focus:outline-none border-0 min-w-[130px]">
                                </td>
                            </template>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- 5. EXCEL BOTTOM STATUS BAR -->
        <div class="bg-slate-100 px-4 py-2 border-t border-slate-300 flex items-center justify-between text-xs text-slate-600 font-mono shrink-0 select-none">
            <div class="flex items-center gap-4">
                <span class="flex items-center gap-1.5 text-emerald-700 font-bold">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Ready
                </span>
                <span x-text="'Records: ' + rows.length"></span>
                <span x-text="'Columns: ' + columns.length"></span>
            </div>

            <div class="flex items-center gap-4 text-slate-500">
                <span>Zoom: 100%</span>
                <span class="font-sans font-bold text-slate-700" x-text="currentSheetTitle"></span>
            </div>
        </div>
    </div>

    <!-- 📤 UPLOAD / IMPORT MODAL -->
    <div x-show="uploadModalOpen" class="fixed inset-0 z-50 bg-slate-950/75 backdrop-blur-xs flex items-center justify-center p-4" style="display:none;">
        <div @click.away="uploadModalOpen = false" class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold">
                        <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
                    </div>
                    <h3 class="font-extrabold text-sm text-slate-900">Upload & Ingest Spreadsheet</h3>
                </div>
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

                <div class="p-3 bg-emerald-50 rounded-2xl border border-emerald-200 text-emerald-900 text-[11px] space-y-1">
                    <div class="font-bold flex items-center gap-1.5">
                        <i data-lucide="refresh-cw" class="w-4 h-4 text-emerald-600"></i> Automatic 360° Website Sync:
                    </div>
                    <p class="text-[10px] text-emerald-800 leading-relaxed">
                        Data upload hote hi <strong>Employee Directory, Attendance, aur Birthday Hub</strong> mein automatically sync ho kar naya top tab ban jayega!
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

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('msExcelStudio', () => ({
        uploadModalOpen: false,
        currentSheetId: 0,
        currentSheetTitle: '',
        columns: [],
        rows: [],
        activeCell: { r: 0, c: 0, coord: 'A1', val: '' },
        formulaInput: '',
        searchQuery: '',
        isLoading: false,
        isSaving: false,
        isBold: false,
        isItalic: false,
        fontSize: 'text-xs',
        fontFamily: 'font-sans',
        cellBg: 'bg-white',
        syncSuccessBanner: false,

        initStudio(defaultId) {
            if (defaultId > 0) {
                this.switchSheet(defaultId);
            }
        },

        getColLetter(idx) {
            let letter = '';
            while (idx >= 0) {
                letter = String.fromCharCode((idx % 26) + 65) + letter;
                idx = Math.floor(idx / 26) - 1;
            }
            return letter;
        },

        async switchSheet(sheetId) {
            this.currentSheetId = sheetId;
            this.isLoading = true;
            this.syncSuccessBanner = false;

            try {
                const res = await fetch('?action=get-smart-sheet-data&sheet_id=' + sheetId);
                const data = await res.json();
                this.currentSheetTitle = data.title || 'Workbook';
                this.columns = data.columns || [];
                this.rows = data.rows || [];
                this.selectCell(0, 0);
            } catch(e) {
                console.error(e);
            } finally {
                this.isLoading = false;
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
            if (this.currentSheetId <= 0) return;
            this.isSaving = true;
            const formData = new FormData();
            formData.append('sheet_id', this.currentSheetId);
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
                    setTimeout(() => this.syncSuccessBanner = false, 5000);
                }
            } catch(e) {
                alert('Failed to save workbook changes');
            } finally {
                this.isSaving = false;
            }
        },

        exportAsCsv() {
            let csv = this.columns.map(c => '"' + String(c).replace(/"/g, '""') + '"').join(',') + '\n';
            this.rows.forEach(r => {
                csv += r.map(c => '"' + String(c).replace(/"/g, '""') + '"').join(',') + '\n';
            });
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = (this.currentSheetTitle || 'Workbook') + '.csv';
            link.click();
        },

        get filteredRows() {
            if (!this.searchQuery.trim()) return this.rows;
            const q = this.searchQuery.toLowerCase();
            return this.rows.filter(r => {
                return r.some(cell => String(cell).toLowerCase().includes(q));
            });
        }
    }));
});
</script>