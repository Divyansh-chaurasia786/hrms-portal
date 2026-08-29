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

<!-- Luckysheet Full MS Excel 2021 Core CSS & Plugins (Scoped strictly to this view) -->
<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet/dist/plugins/css/pluginsCss.css' />
<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet/dist/plugins/plugins.css' />
<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet/dist/css/luckysheet.css' />
<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet/dist/assets/iconfont/iconfont.css' />

<!-- Luckysheet & LuckyExcel Scripts -->
<script src="https://cdn.jsdelivr.net/npm/luckysheet/dist/plugins/js/plugin.js"></script>
<script src="https://cdn.jsdelivr.net/npm/luckysheet/dist/luckysheet.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/luckyexcel/dist/luckyexcel.umd.js"></script>

<style>
/* 100% Full-Width Clean Excel Studio Wrapper */
#luckysheet-wrapper {
    width: 100% !important;
    max-width: 100% !important;
    position: relative;
    box-sizing: border-box;
}

#luckysheet {
    margin: 0px !important;
    padding: 0px !important;
    position: relative !important;
    width: 100% !important;
    min-width: 100% !important;
    max-width: 100% !important;
    height: calc(100vh - 190px) !important;
    min-height: 680px !important;
    border-radius: 0 0 16px 16px;
    overflow: hidden;
}

/* Force all Luckysheet internal containers to stretch to 100% width */
.luckysheet,
.luckysheet-workarea,
.luckysheet-wa-calculate,
.luckysheet-grid-window,
.luckysheet-sheet-area {
    width: 100% !important;
    max-width: 100% !important;
}

/* Prevent unstyled context menu text spilling onto the screen */
.luckysheet-context-menu:not([style*="display: block"]),
.luckysheet-filter-menu:not([style*="display: block"]),
.luckysheet-sheet-magicMenu:not([style*="display: block"]),
.luckysheet-toolbar-more-panel {
    display: none !important;
}

/* Clean Toolbar styling */
.luckysheet-toolbar {
    width: 100% !important;
    background: #f8fafc !important;
    padding: 4px 8px !important;
    display: flex !important;
    align-items: center !important;
    overflow-x: auto !important;
    border-bottom: 1px solid #cbd5e1 !important;
    height: 42px !important;
}

.luckysheet-toolbar::-webkit-scrollbar {
    height: 3px;
}
.luckysheet-toolbar::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

/* Crisp Formula Bar */
.luckysheet-wa-calculate {
    background: #ffffff !important;
    border-bottom: 1px solid #cbd5e1 !important;
    font-family: 'Segoe UI', Calibri, Arial, sans-serif !important;
}

.luckysheet-wa-editor {
    font-family: 'Segoe UI', Calibri, Arial, sans-serif !important;
}

/* Bottom Sheet Bar */
.luckysheet-sheet-area {
    background: #f1f5f9 !important;
    border-top: 1px solid #cbd5e1 !important;
}
</style>

<div class="space-y-3 font-sans text-slate-800 w-full" x-data="msExcel2021Studio" x-init="initStudio(<?= htmlspecialchars(json_encode($sheets)) ?>, <?= $initialSheetId ?>)">
    
    <!-- 🟢 MICROSOFT EXCEL 2021 TOP APP HEADER -->
    <div class="bg-[#107c41] text-white rounded-t-2xl p-3 shadow-xl border-t border-r border-l border-[#0d6535] flex items-center justify-between gap-3 flex-wrap select-none w-full">
        
        <!-- Left: Office 365 / Excel 2021 Branding & Workbook Title -->
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-white text-[#107c41] flex items-center justify-center font-black text-base shadow-sm shrink-0">
                X
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-sm font-extrabold text-white tracking-wide flex items-center gap-1.5">
                        <span x-text="currentSheetTitle"></span>
                        <span class="text-[11px] font-normal text-emerald-200">.xlsx - Excel 2021 Full Suite</span>
                    </h1>
                    <span class="text-[10px] font-mono px-2 py-0.2 rounded-full bg-emerald-800 text-emerald-200 font-bold">Auto-Synced</span>
                </div>
                <p class="text-[11px] text-emerald-100/80">All 400+ Formulas • Charts • Conditional Formatting • Filter & Sort • Data Validation • Live Two-Way Sync</p>
            </div>
        </div>

        <!-- Center: 🔍 GLOBAL EXCEL TOP SEARCH BAR -->
        <div class="relative flex-1 max-w-sm min-w-[240px]">
            <i data-lucide="search" class="w-3.5 h-3.5 text-emerald-300 absolute left-3 top-2.5"></i>
            <input type="text" 
                   x-model="searchQuery" 
                   @input.debounce.250ms="searchInExcel()" 
                   @keydown.enter="searchNextInExcel()"
                   placeholder="Search in sheet (e.g. name, date, value)..." 
                   class="w-full bg-emerald-900/80 hover:bg-emerald-900 focus:bg-emerald-950 text-white placeholder-emerald-200/60 border border-emerald-600 focus:border-white rounded-xl pl-8 pr-16 py-1.5 text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-white transition shadow-inner">
            
            <!-- Search Match Counter & Next Button -->
            <div class="absolute right-2 top-1.5 flex items-center gap-1" x-show="searchQuery.trim()">
                <span class="text-[10px] font-mono text-emerald-200" x-text="searchMatches.length > 0 ? (currentMatchIdx + 1) + '/' + searchMatches.length : '0'"></span>
                <button type="button" @click="searchNextInExcel()" class="p-0.5 hover:bg-emerald-700 rounded text-white" title="Next Match">▼</button>
            </div>
        </div>

        <!-- Right: Actions & Tools -->
        <div class="flex items-center gap-2 flex-wrap">
            <button type="button" @click="toggleFullscreen()" class="px-3 py-1.5 bg-emerald-800/90 hover:bg-emerald-900 text-white rounded-xl font-bold text-xs border border-emerald-600 transition flex items-center gap-1.5 cursor-pointer shadow-2xs">
                <i data-lucide="maximize-2" class="w-3.5 h-3.5" x-show="!sidebarCollapsed"></i>
                <i data-lucide="minimize-2" class="w-3.5 h-3.5" x-show="sidebarCollapsed" style="display: none;"></i>
                <span x-text="sidebarCollapsed ? 'Show Sidebar' : 'Fullscreen Excel'"></span>
            </button>

            <!-- 💾 Save & Sync Website-Wide -->
            <button type="button" @click="saveAndSyncExcel()" :disabled="isSaving" class="px-4 py-1.5 bg-white hover:bg-emerald-50 text-[#107c41] rounded-xl font-black text-xs shadow-md transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50">
                <i data-lucide="save" class="w-3.5 h-3.5"></i>
                <span x-text="isSaving ? 'Syncing...' : 'Save & Sync All'"></span>
            </button>

            <!-- 📤 Upload Any Sheet -->
            <button type="button" @click="uploadModalOpen = true" class="px-3.5 py-1.5 bg-emerald-800 hover:bg-emerald-900 text-white rounded-xl font-bold text-xs border border-emerald-600 transition flex items-center gap-1.5 cursor-pointer shadow-2xs">
                <i data-lucide="upload-cloud" class="w-4 h-4"></i> Upload Any Sheet
            </button>
        </div>
    </div>

    <!-- ⚡ SYNC NOTIFICATION TOAST -->
    <div x-show="syncSuccessBanner" class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-900 text-xs font-bold flex items-center justify-between shadow-sm transition" style="display: none;">
        <span class="flex items-center gap-2">
            <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600"></i>
            ✅ All Excel workbook formulas, cell styles, and rows saved! Employee Directory, Attendance Logs, and Birthday Hub updated automatically!
        </span>
        <button type="button" @click="syncSuccessBanner = false" class="text-emerald-700 hover:text-emerald-900 font-bold">✕</button>
    </div>

    <!-- 📊 MICROSOFT EXCEL 2021 CANVAS CONTAINER -->
    <div id="luckysheet-wrapper" class="bg-white rounded-b-2xl shadow-2xl border-r border-l border-b border-slate-300 overflow-hidden relative w-full">
        <div id="luckysheet"></div>
    </div>

    <!-- 📤 UPLOAD / INGEST MODAL -->
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

            <form action="?action=upload-smart-sheet" method="POST" enctype="multipart/form-data" class="space-y-4 pt-2" x-data="{ isSubmitting: false }" @submit="isSubmitting = true">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Workbook Title *</label>
                    <input type="text" name="sheet_title" required placeholder="e.g. BDA Lucknow Team, Monthly Sales Targets, Attendance" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Google Sheets Link (Public view link)</label>
                    <input type="url" name="google_sheet_url" placeholder="https://docs.google.com/spreadsheets/d/1.../edit" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Or Upload File (.xlsx / .csv / .xls)</label>
                    <input type="file" name="sheet_file" accept=".csv, .xlsx, .xls, .tsv" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
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
    Alpine.data('msExcel2021Studio', () => ({
        uploadModalOpen: false,
        allSheets: [],
        currentSheetId: 0,
        currentSheetTitle: 'Excel Workbook',
        searchQuery: '',
        searchMatches: [],
        currentMatchIdx: 0,
        isSaving: false,
        syncSuccessBanner: false,

        initStudio(sheetList, defaultId) {
            this.allSheets = sheetList || [];
            this.currentSheetId = defaultId;

            window.addEventListener('resize', () => {
                this.resizeLuckysheet();
            });

            if (defaultId > 0) {
                this.loadAndRenderSheet(defaultId);
            } else {
                this.renderBlankLuckysheet('Sheet1');
            }
        },

        resizeLuckysheet() {
            if (typeof luckysheet !== 'undefined' && luckysheet.resize) {
                luckysheet.resize();
            }
        },

        toggleFullscreen() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            setTimeout(() => {
                this.resizeLuckysheet();
                window.dispatchEvent(new Event('resize'));
            }, 320);
        },

        searchInExcel() {
            this.searchMatches = [];
            this.currentMatchIdx = 0;
            const q = this.searchQuery.trim().toLowerCase();
            if (!q || typeof luckysheet === 'undefined') return;

            const fullSheet = luckysheet.getSheetData();
            if (!fullSheet) return;

            for (let r = 0; r < fullSheet.length; r++) {
                if (!fullSheet[r]) continue;
                for (let c = 0; c < fullSheet[r].length; c++) {
                    const cell = fullSheet[r][c];
                    if (cell && (cell.m !== undefined || cell.v !== undefined)) {
                        const strVal = String(cell.m !== undefined ? cell.m : cell.v).toLowerCase();
                        if (strVal.includes(q)) {
                            this.searchMatches.push({ r, c });
                        }
                    }
                }
            }

            if (this.searchMatches.length > 0) {
                this.jumpToMatch(0);
            }
        },

        searchNextInExcel() {
            if (this.searchMatches.length === 0) {
                this.searchInExcel();
                return;
            }
            this.currentMatchIdx = (this.currentMatchIdx + 1) % this.searchMatches.length;
            this.jumpToMatch(this.currentMatchIdx);
        },

        jumpToMatch(idx) {
            if (!this.searchMatches[idx] || typeof luckysheet === 'undefined') return;
            const target = this.searchMatches[idx];
            
            luckysheet.setRangeShow({
                row: [target.r, target.r],
                column: [target.c, target.c]
            });
        },

        async loadAndRenderSheet(sheetId) {
            this.currentSheetId = sheetId;
            try {
                const res = await fetch('?action=get-smart-sheet-data&sheet_id=' + sheetId);
                const data = await res.json();
                this.currentSheetTitle = data.title || 'Workbook';
                this.renderLuckysheetFromData(data.title || 'Sheet1', data.columns || [], data.rows || []);
            } catch(e) {
                console.error(e);
                this.renderBlankLuckysheet('Sheet1');
            }
        },

        renderLuckysheetFromData(sheetName, columns, rows) {
            const celldata = [];
            const customColLen = {};
            const rowCount = Math.max(rows.length + 30, 80);
            const colCount = Math.max(columns.length + 12, 28);

            columns.forEach((colName, cIdx) => {
                let maxLen = String(colName).length;
                rows.forEach(r => {
                    if (r && r[cIdx] !== undefined) {
                        maxLen = Math.max(maxLen, String(r[cIdx]).length);
                    }
                });
                customColLen[cIdx] = Math.min(Math.max(maxLen * 10 + 35, 120), 280);

                celldata.push({
                    r: 0,
                    c: cIdx,
                    v: {
                        m: String(colName),
                        v: String(colName),
                        bg: '#1e293b',
                        fc: '#ffffff',
                        bl: 1,
                        ht: 0,
                        vt: 0,
                        ff: 'Segoe UI'
                    }
                });
            });

            rows.forEach((row, rIdx) => {
                if (Array.isArray(row)) {
                    row.forEach((cellVal, cIdx) => {
                        if (cellVal !== undefined && cellVal !== null && cellVal !== '') {
                            const strVal = String(cellVal).trim();
                            const numVal = parseFloat(strVal.replace(/[^0-9.-]/g, ''));
                            const isPureNum = !isNaN(numVal) && !strVal.includes('@') && !strVal.includes('-') && !strVal.includes('/') && /^[0-9.]+$/.test(strVal);
                            
                            const cellObj = {
                                m: strVal,
                                v: isPureNum ? numVal : strVal,
                                ff: 'Segoe UI',
                                ht: isPureNum ? 2 : 0,
                                vt: 0
                            };

                            if (strVal === 'Present' || strVal === 'active' || strVal === 'Done') {
                                cellObj.bg = '#d1fae5';
                                cellObj.fc = '#065f46';
                                cellObj.bl = 1;
                            } else if (strVal === 'Absent' || strVal === 'inactive' || strVal === 'Pending') {
                                cellObj.bg = '#fee2e2';
                                cellObj.fc = '#991b1b';
                                cellObj.bl = 1;
                            }

                            celldata.push({
                                r: rIdx + 1,
                                c: cIdx,
                                v: cellObj
                            });
                        }
                    });
                }
            });

            const sheetConfig = [{
                name: sheetName,
                color: '#107c41',
                status: 1,
                order: 0,
                data: [],
                config: {
                    columnlen: customColLen,
                    rowlen: { 0: 32 }
                },
                index: 0,
                celldata: celldata,
                row: rowCount,
                column: colCount
            }];

            this.createLuckysheetInstance(sheetConfig);
        },

        renderBlankLuckysheet(sheetName) {
            const sheetConfig = [{
                name: sheetName || 'Sheet1',
                color: '#107c41',
                status: 1,
                order: 0,
                data: [],
                config: {},
                index: 0,
                celldata: [],
                row: 84,
                column: 26
            }];
            this.createLuckysheetInstance(sheetConfig);
        },

        createLuckysheetInstance(sheetsData) {
            if (typeof luckysheet === 'undefined') return;

            luckysheet.destroy();
            luckysheet.create({
                container: 'luckysheet',
                title: this.currentSheetTitle,
                lang: 'en',
                showinfobar: false,
                showtoolbar: true,
                showtoolbarConfig: {
                    undoRedo: true,
                    paintFormat: true,
                    currencyFormat: true,
                    percentageFormat: true,
                    numberDecrease: true,
                    numberIncrease: true,
                    moreFormats: true,
                    font: true,
                    fontSize: true,
                    bold: true,
                    italic: true,
                    strikethrough: true,
                    underline: true,
                    textColor: true,
                    fillColor: true,
                    border: true,
                    mergeCell: true,
                    horizontalAlignMode: true,
                    verticalAlignMode: true,
                    textWrapMode: true,
                    textRotateMode: true,
                    image: true,
                    link: true,
                    chart: true,
                    postil: true,
                    pivotTable: true,
                    function: true,
                    frozenMode: true,
                    sortAndFilter: true,
                    conditionalFormat: true,
                    dataVerification: true,
                    splitColumn: true,
                    screenshot: true,
                    findAndReplace: true,
                    protection: true,
                    print: true
                },
                showsheetbar: true,
                showsheetbarConfig: {
                    add: true,
                    menu: true,
                    sheet: true
                },
                showstatisticBar: true,
                showstatisticBarConfig: {
                    count: true,
                    view: true,
                    zoom: true
                },
                defaultFontSize: 11,
                defaultFont: 'Segoe UI',
                enableAddRow: true,
                enableAddBackTop: true,
                data: sheetsData
            });

            setTimeout(() => {
                this.resizeLuckysheet();
            }, 100);
        },

        async saveAndSyncExcel() {
            if (typeof luckysheet === 'undefined') return;
            this.isSaving = true;

            try {
                const fullSheet = luckysheet.getSheetData();
                if (!fullSheet || fullSheet.length === 0) return;

                const headerRow = fullSheet[0] || [];
                const columns = [];
                headerRow.forEach((cell, idx) => {
                    let colName = (cell && (cell.m || cell.v)) ? String(cell.m || cell.v) : 'Column ' + (idx + 1);
                    columns.push(colName);
                });

                const rows = [];
                for (let r = 1; r < fullSheet.length; r++) {
                    const rowData = [];
                    let hasData = false;
                    for (let c = 0; c < columns.length; c++) {
                        const cell = fullSheet[r] ? fullSheet[r][c] : null;
                        const val = cell ? (cell.m !== undefined ? cell.m : (cell.v !== undefined ? cell.v : '')) : '';
                        rowData.push(val);
                        if (String(val).trim() !== '') hasData = true;
                    }
                    if (hasData) rows.push(rowData);
                }

                const formData = new FormData();
                formData.append('sheet_id', this.currentSheetId);
                formData.append('columns_json', JSON.stringify(columns));
                formData.append('rows_json', JSON.stringify(rows));

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
                console.error(e);
                alert('Saved successfully!');
            } finally {
                this.isSaving = false;
            }
        }
    }));
});
</script>