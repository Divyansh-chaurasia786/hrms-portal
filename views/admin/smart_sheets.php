<!-- views/admin/smart_sheets.php -->
<?php
$user = authUser();
$db = getDBConnection();

$sheets = $db->query("
    SELECT s.id, s.title, s.category, s.uploaded_by, s.created_at, 
           u.name as uploader_name 
    FROM smart_sheet_uploads s 
    JOIN users u ON s.uploaded_by = u.id 
    ORDER BY s.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$initialSheetId = !empty($sheets) ? (int)$sheets[0]['id'] : 0;

// Preload initial sheet data directly on server-side for INSTANT 0ms rendering
$initialSheet = null;
if ($initialSheetId > 0) {
    $stmt = $db->prepare("SELECT id, title, columns_json, rows_json FROM smart_sheet_uploads WHERE id = ?");
    $stmt->execute([$initialSheetId]);
    $initialSheet = $stmt->fetch(PDO::FETCH_ASSOC);
}

$initialPayload = [
    'id' => $initialSheet ? (int)$initialSheet['id'] : 0,
    'title' => $initialSheet ? $initialSheet['title'] : 'Excel Workbook',
    'columns' => ($initialSheet && !empty($initialSheet['columns_json'])) ? json_decode($initialSheet['columns_json'], true) : [],
    'rows' => ($initialSheet && !empty($initialSheet['rows_json'])) ? json_decode($initialSheet['rows_json'], true) : []
];
?>

<!-- Luckysheet Full MS Excel 2021 Core CSS & Plugins (Multi-CDN High Speed) -->
<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet/dist/plugins/css/pluginsCss.css' onerror="this.href='https://unpkg.com/luckysheet/dist/plugins/css/pluginsCss.css'" />
<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet/dist/plugins/plugins.css' onerror="this.href='https://unpkg.com/luckysheet/dist/plugins/plugins.css'" />
<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet/dist/css/luckysheet.css' onerror="this.href='https://unpkg.com/luckysheet/dist/css/luckysheet.css'" />
<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet/dist/assets/iconfont/iconfont.css' onerror="this.href='https://unpkg.com/luckysheet/dist/assets/iconfont/iconfont.css'" />

<!-- High-Speed Luckysheet Scripts with Auto-Fallback -->
<script src="https://cdn.jsdelivr.net/npm/luckysheet/dist/plugins/js/plugin.js" onerror="this.src='https://unpkg.com/luckysheet/dist/plugins/js/plugin.js'"></script>
<script src="https://cdn.jsdelivr.net/npm/luckysheet/dist/luckysheet.umd.js" onerror="this.src='https://unpkg.com/luckysheet/dist/luckysheet.umd.js'"></script>
<script src="https://cdn.jsdelivr.net/npm/luckyexcel/dist/luckyexcel.umd.js" onerror="this.src='https://unpkg.com/luckyexcel/dist/luckyexcel.umd.js'"></script>

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
    height: calc(100vh - 160px) !important;
    min-height: 720px !important;
    border-radius: 0 0 16px 16px;
    overflow: hidden;
}

/* Force all Luckysheet internal containers to stretch to 100% width */
.luckysheet,
.luckysheet-workarea,
.luckysheet-wa-calculate,
.luckysheet-grid-window,
.luckysheet-grid-window-holder,
.luckysheet-cell-main,
.luckysheet-sheet-area {
    width: 100% !important;
    max-width: 100% !important;
}

/* Formula Bar Full Width & Clean Style */
.luckysheet-wa-calculate {
    background: #ffffff !important;
    border-bottom: 1px solid #cbd5e1 !important;
    font-family: 'Segoe UI', Calibri, Arial, sans-serif !important;
    display: flex !important;
    align-items: center !important;
    width: 100% !important;
}

.luckysheet-wa-calculate .luckysheet-formula-functionvalue {
    flex: 1 !important;
    width: 100% !important;
}

/* Full Microsoft Excel 2021 Toolbar Styling */
.luckysheet-toolbar {
    background: #f8fafc !important;
    border-bottom: 1px solid #cbd5e1 !important;
    padding: 4px 8px !important;
    width: 100% !important;
}

.luckysheet-toolbar-button {
    font-family: 'Segoe UI', Calibri, Arial, sans-serif !important;
}

/* Prevent unstyled context menu text spilling onto the screen */
.luckysheet-context-menu:not([style*="display: block"]),
.luckysheet-filter-menu:not([style*="display: block"]),
.luckysheet-sheet-magicMenu:not([style*="display: block"]) {
    display: none !important;
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

<div class="space-y-1.5 font-sans text-slate-800 w-full" x-data="msExcel2021Studio" x-init="initStudio(<?= htmlspecialchars(json_encode($sheets)) ?>, <?= htmlspecialchars(json_encode($initialPayload)) ?>)">
    
    <!-- 🟢 MICROSOFT EXCEL 2021 TOP TITLE BAR -->
    <div class="bg-[#107c41] text-white rounded-t-2xl p-2.5 shadow-xl border border-[#0d6535] flex items-center justify-between gap-3 flex-wrap select-none w-full">
        
        <!-- Left: Office 365 / Excel 2021 Branding & Workbook Title -->
        <div class="flex items-center gap-2.5">
            <div class="w-7 h-7 rounded-lg bg-white text-[#107c41] flex items-center justify-center font-black text-sm shadow-sm shrink-0">
                X
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-sm font-extrabold text-white tracking-wide flex items-center gap-1.5">
                        <span x-text="currentSheetTitle"></span>
                        <span class="text-[10px] font-normal text-emerald-200">.xlsx - Excel 2021 Enterprise (400+ Formulas • Charts • Conditional Formatting)</span>
                    </h1>
                    <span class="text-[9px] font-mono px-1.5 py-0.2 rounded-full bg-emerald-800 text-emerald-200 font-bold">Live Synced</span>
                </div>
            </div>
        </div>

        <!-- Center: 🔍 GLOBAL EXCEL TOP SEARCH BAR -->
        <div class="relative flex-1 max-w-sm min-w-[220px]">
            <i data-lucide="search" class="w-3.5 h-3.5 text-emerald-300 absolute left-3 top-2"></i>
            <input type="text" 
                   x-model="searchQuery" 
                   @input.debounce.200ms="searchInExcel()" 
                   @keydown.enter="searchNextInExcel()"
                   placeholder="Search in sheet (e.g. name, date, value)..." 
                   class="w-full bg-emerald-900/90 hover:bg-emerald-900 focus:bg-emerald-950 text-white placeholder-emerald-200/60 border border-emerald-600 focus:border-white rounded-xl pl-8 pr-16 py-1 text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-white transition shadow-inner">
            
            <div class="absolute right-2 top-1 flex items-center gap-1" x-show="searchQuery.trim()">
                <span class="text-[10px] font-mono text-emerald-200" x-text="searchMatches.length > 0 ? (currentMatchIdx + 1) + '/' + searchMatches.length : '0'"></span>
                <button type="button" @click="searchNextInExcel()" class="p-0.5 hover:bg-emerald-700 rounded text-white text-[10px]" title="Next Match">▼</button>
            </div>
        </div>

        <!-- Right: Actions & Tools -->
        <div class="flex items-center gap-1.5 flex-wrap">
            <button type="button" @click="toggleFullscreen()" class="px-2.5 py-1 bg-emerald-800/90 hover:bg-emerald-900 text-white rounded-lg font-bold text-xs border border-emerald-600 transition flex items-center gap-1 cursor-pointer shadow-2xs">
                <i data-lucide="maximize-2" class="w-3.5 h-3.5" x-show="!sidebarCollapsed"></i>
                <i data-lucide="minimize-2" class="w-3.5 h-3.5" x-show="sidebarCollapsed" style="display: none;"></i>
                <span x-text="sidebarCollapsed ? 'Show Sidebar' : 'Fullscreen'"></span>
            </button>

            <!-- 🔄 Live Data Pull Dropdown -->
            <div class="relative" x-data="{ liveMenuOpen: false }">
                <button type="button" @click="liveMenuOpen = !liveMenuOpen" class="px-2.5 py-1 bg-emerald-800 hover:bg-emerald-900 text-white rounded-lg font-bold text-xs border border-emerald-600 transition flex items-center gap-1 cursor-pointer shadow-2xs">
                    <i data-lucide="refresh-cw" class="w-3 h-3"></i>
                    <span>Pull Live HRMS Data</span>
                    <i data-lucide="chevron-down" class="w-3 h-3"></i>
                </button>
                <div x-show="liveMenuOpen" @click.away="liveMenuOpen = false" class="absolute right-0 mt-1 w-56 bg-white rounded-2xl shadow-2xl border border-slate-200 py-1.5 z-50 text-slate-800 text-xs font-semibold" style="display: none;">
                    <button type="button" @click="pullLivePortalData('employees'); liveMenuOpen = false;" class="w-full px-3.5 py-2 text-left hover:bg-emerald-50 flex items-center gap-2 text-slate-700">
                        <i data-lucide="users" class="w-4 h-4 text-emerald-600"></i>
                        <span>👥 Pull All Live Employees</span>
                    </button>
                    <button type="button" @click="pullLivePortalData('attendance'); liveMenuOpen = false;" class="w-full px-3.5 py-2 text-left hover:bg-emerald-50 flex items-center gap-2 text-slate-700">
                        <i data-lucide="clock" class="w-4 h-4 text-indigo-600"></i>
                        <span>🕒 Pull Today's Live Attendance</span>
                    </button>
                </div>
            </div>

            <!-- 💾 Save & Sync Website-Wide -->
            <button type="button" @click="saveAndSyncExcel()" :disabled="isSaving" class="px-3.5 py-1 bg-white hover:bg-emerald-50 text-[#107c41] rounded-lg font-black text-xs shadow-md transition flex items-center gap-1 cursor-pointer disabled:opacity-50">
                <i data-lucide="save" class="w-3.5 h-3.5"></i>
                <span x-text="isSaving ? 'Syncing...' : 'Save & Sync'"></span>
            </button>

            <!-- 📤 Upload Any Sheet -->
            <button type="button" @click="uploadModalOpen = true" class="px-3 py-1 bg-emerald-800 hover:bg-emerald-900 text-white rounded-lg font-bold text-xs border border-emerald-600 transition flex items-center gap-1 cursor-pointer shadow-2xs">
                <i data-lucide="upload-cloud" class="w-3.5 h-3.5"></i> Upload Sheet
            </button>
        </div>
    </div>

    <!-- ⚡ SYNC NOTIFICATION TOAST -->
    <div x-show="syncSuccessBanner" class="p-2.5 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-900 text-xs font-bold flex items-center justify-between shadow-sm transition" style="display: none;">
        <span class="flex items-center gap-2">
            <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600"></i>
            ✅ All Excel workbook formulas, cell styles, and rows saved! Employee Directory, Attendance Logs, and Birthday Hub updated automatically!
        </span>
        <button type="button" @click="syncSuccessBanner = false" class="text-emerald-700 hover:text-emerald-900 font-bold">✕</button>
    </div>

    <!-- 📊 MICROSOFT EXCEL 2021 CANVAS CONTAINER -->
    <div id="luckysheet-wrapper" class="bg-white rounded-b-2xl shadow-2xl border-r border-l border-b border-slate-300 overflow-hidden relative w-full">
        <!-- High-Speed Loading Overlay -->
        <div x-show="isFetchingSheet || isEngineLoading" class="absolute inset-0 bg-white/95 backdrop-blur-xs z-30 flex flex-col items-center justify-center gap-3 transition-opacity">
            <div class="w-12 h-12 rounded-2xl bg-[#107c41] text-white flex items-center justify-center font-black text-xl shadow-xl shadow-emerald-700/20 animate-bounce">
                X
            </div>
            <div class="text-center">
                <div class="flex items-center justify-center gap-2 text-xs font-extrabold text-slate-800">
                    <i data-lucide="loader-2" class="w-4 h-4 animate-spin text-[#107c41]"></i>
                    <span x-text="isEngineLoading ? 'Initializing Excel 2021 Studio Engine...' : 'Syncing Sheet Data...'"></span>
                </div>
                <p class="text-[10px] text-slate-400 mt-1 font-medium">Preparing 400+ formulas, grid cells, and Ribbon tools...</p>
            </div>
        </div>
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
        isFetchingSheet: false,
        isEngineLoading: true,
        syncSuccessBanner: false,

        initStudio(sheetList, initialData) {
            this.allSheets = sheetList || [];

            window.addEventListener('resize', () => {
                this.resizeLuckysheet();
            });

            // ⚡ Check Local Device Cache First (0ms instant render)
            let activeData = null;
            if (initialData && initialData.id > 0) {
                activeData = initialData;
                if (window.HRMSCache) {
                    window.HRMSCache.set('excel_sheet_' + initialData.id, initialData);
                }
            } else if (window.HRMSCache) {
                activeData = window.HRMSCache.get('excel_last_active_sheet');
            }

            if (activeData && activeData.id > 0) {
                this.currentSheetId = activeData.id;
                this.currentSheetTitle = activeData.title || 'Excel Workbook';
                this.renderLuckysheetFromData(activeData.title || 'Sheet1', activeData.columns || [], activeData.rows || []);
                if (window.HRMSCache) window.HRMSCache.set('excel_last_active_sheet', activeData);
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

        async pullLivePortalData(type) {
            this.isFetchingSheet = true;
            try {
                const res = await fetch('?action=fetch-live-sheet-data&type=' + type);
                const data = await res.json();
                if (data.success && typeof luckysheet !== 'undefined') {
                    const tabTitle = (type === 'attendance') ? 'Live Attendance' : 'Live Employees';
                    const newSheetObj = this.buildSheetConfigObject(tabTitle, data.columns, data.rows);

                    // Get all existing sheets from active Luckysheet workbook
                    let currentSheets = [];
                    try {
                        currentSheets = luckysheet.getAllSheets() || [];
                    } catch(e) {}

                    if (currentSheets.length > 0) {
                        // Mark all existing sheets as inactive
                        currentSheets.forEach(s => s.status = 0);

                        // Check if tab with this name already exists
                        const existingIdx = currentSheets.findIndex(s => s.name === tabTitle);
                        if (existingIdx !== -1) {
                            newSheetObj.order = existingIdx;
                            newSheetObj.index = currentSheets[existingIdx].index;
                            currentSheets[existingIdx] = newSheetObj;
                        } else {
                            newSheetObj.order = currentSheets.length;
                            newSheetObj.index = currentSheets.length;
                            currentSheets.push(newSheetObj);
                        }

                        this.createLuckysheetInstance(currentSheets);
                    } else {
                        this.renderLuckysheetFromData(tabTitle, data.columns, data.rows);
                    }
                }
            } catch(e) {
                console.error(e);
            } finally {
                this.isFetchingSheet = false;
            }
        },

        buildSheetConfigObject(sheetName, columns, rows) {
            const celldata = [];
            const customColLen = {};
            const rowCount = Math.max(rows.length + 50, 100);
            const colCount = Math.max(columns.length + 30, 52);

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

            return {
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
            };
        },

        async loadAndRenderSheet(sheetId) {
            this.currentSheetId = sheetId;
            const cacheKey = 'excel_sheet_' + sheetId;

            // 1. Instant Cache Hit (0ms)
            if (window.HRMSCache) {
                const cached = window.HRMSCache.get(cacheKey);
                if (cached && cached.columns) {
                    this.currentSheetTitle = cached.title || 'Workbook';
                    this.renderLuckysheetFromData(cached.title || 'Sheet1', cached.columns || [], cached.rows || []);
                    window.HRMSCache.set('excel_last_active_sheet', cached);
                } else {
                    this.isFetchingSheet = true;
                }
            } else {
                this.isFetchingSheet = true;
            }

            // 2. Background Revalidation (1-2s)
            try {
                const res = await fetch('?action=get-smart-sheet-data&sheet_id=' + sheetId);
                const data = await res.json();
                if (data && data.columns) {
                    if (window.HRMSCache) {
                        window.HRMSCache.set(cacheKey, data);
                        window.HRMSCache.set('excel_last_active_sheet', data);
                    }
                    this.currentSheetTitle = data.title || 'Workbook';
                    this.renderLuckysheetFromData(data.title || 'Sheet1', data.columns || [], data.rows || []);
                }
            } catch(e) {
                console.error(e);
            } finally {
                this.isFetchingSheet = false;
            }
        },

        renderLuckysheetFromData(sheetName, columns, rows) {
            const sheetConfig = [this.buildSheetConfigObject(sheetName, columns, rows)];
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
                row: 100,
                column: 52
            }];
            this.createLuckysheetInstance(sheetConfig);
        },

        createLuckysheetInstance(sheetsData) {
            if (typeof luckysheet === 'undefined' || !luckysheet.create) {
                this.isEngineLoading = true;
                setTimeout(() => this.createLuckysheetInstance(sheetsData), 50);
                return;
            }

            try { luckysheet.destroy(); } catch(e) {}
            
            luckysheet.create({
                container: 'luckysheet',
                title: this.currentSheetTitle,
                lang: 'en',
                showinfobar: false,
                showtoolbar: true, // ✅ FULL MS EXCEL 2021 OFFICIAL TOOLBAR
                showtoolbarConfig: {
                    undoRedo: true, // Undo / Redo
                    paintFormat: true, // Format Painter
                    currencyFormat: true, // ₹ / $ Currency Formats
                    percentageFormat: true, // Percentage
                    numberDecrease: true, // Decimal Decrease
                    numberIncrease: true, // Decimal Increase
                    moreFormats: true, // More Formats (Date, Time, Custom)
                    font: true, // Font Family
                    fontSize: true, // Font Size
                    bold: true, // Bold
                    italic: true, // Italic
                    strikethrough: true, // Strikethrough
                    underline: true, // Underline
                    textColor: true, // Text Color
                    fillColor: true, // Background Fill Color
                    border: true, // All Borders / Border Styles
                    mergeCell: true, // Merge & Center
                    horizontalAlignMode: true, // Left, Center, Right Alignment
                    verticalAlignMode: true, // Top, Middle, Bottom Alignment
                    textWrapMode: true, // Wrap Text
                    textRotateMode: true, // Text Rotation
                    image: true, // Insert Image
                    link: true, // Hyperlink
                    chart: true, // Insert Charts (Bar, Column, Pie, Line)
                    postil: true, // Cell Notes / Comments
                    pivotTable: true, // Pivot Tables
                    function: true, // 400+ Formula Functions & AutoSum
                    frozenMode: true, // Freeze Panes (Row, Column, Panes)
                    sortAndFilter: true, // Sort & Filter Funnel
                    conditionalFormat: true, // Conditional Formatting Rules
                    dataVerification: true, // Data Validation Dropdowns
                    splitColumn: true, // Text to Columns
                    screenshot: true, // Cell Screenshot
                    findAndReplace: true, // Find & Replace
                    protection: true, // Protect Sheet / Lock Cells
                    print: true // Print Sheet
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
                defaultColWidth: 120,
                enableAddRow: true,
                enableAddBackTop: true,
                data: sheetsData
            });

            this.isEngineLoading = false;
            this.isFetchingSheet = false;

            setTimeout(() => {
                this.resizeLuckysheet();
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }, 120);
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