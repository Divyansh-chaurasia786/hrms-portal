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
/* 📗 MICROSOFT EXCEL 2021 AUTHENTIC FLUENT DESIGN SYSTEM */
#luckysheet-wrapper {
    width: 100% !important;
    max-width: 100% !important;
    position: relative;
    box-sizing: border-box;
    font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif !important;
}

#luckysheet {
    margin: 0px !important;
    padding: 0px !important;
    position: relative !important;
    width: 100% !important;
    min-width: 100% !important;
    max-width: 100% !important;
    height: calc(100vh - 175px) !important;
    min-height: 720px !important;
    border-radius: 0 0 12px 12px;
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
    font-family: 'Segoe UI', Calibri, Arial, sans-serif !important;
}

/* 🟢 Excel 2021 Formula Bar */
.luckysheet-wa-calculate {
    background: #ffffff !important;
    border-bottom: 1px solid #d2d0ce !important;
    font-family: 'Segoe UI', Calibri, Arial, sans-serif !important;
    display: flex !important;
    align-items: center !important;
    width: 100% !important;
    height: 28px !important;
}

.luckysheet-wa-calculate .luckysheet-formula-functionvalue {
    flex: 1 !important;
    width: 100% !important;
    font-family: 'Segoe UI', Calibri, Arial, sans-serif !important;
    font-size: 12px !important;
    color: #201f1e !important;
}

/* 🟢 Official Excel 2021 Ribbon Toolbar Styling */
.luckysheet-toolbar {
    background: #f3f2f1 !important;
    border-bottom: 1px solid #d2d0ce !important;
    padding: 4px 8px !important;
    width: 100% !important;
    box-sizing: border-box !important;
}

.luckysheet-toolbar-button {
    font-family: 'Segoe UI', Calibri, Arial, sans-serif !important;
    border-radius: 4px !important;
    color: #323130 !important;
    transition: background-color 0.1s ease !important;
}

.luckysheet-toolbar-button:hover {
    background-color: #edebe9 !important;
}

.luckysheet-toolbar-button.luckysheet-toolbar-button-active {
    background-color: #e1dfdd !important;
    border: 1px solid #c8c6c4 !important;
}

/* 🟢 Authentic Excel 2021 Dialog & Popup Styling */
.luckysheet-modal-dialog,
.luckysheet-modal-dialog-content {
    font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif !important;
    border-radius: 8px !important;
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.22), 0 2px 6px rgba(0, 0, 0, 0.12) !important;
    border: 1px solid #d2d0ce !important;
    background: #ffffff !important;
    color: #201f1e !important;
}

.luckysheet-modal-dialog-title {
    background: #107c41 !important;
    color: #ffffff !important;
    font-weight: 700 !important;
    font-size: 13px !important;
    padding: 10px 16px !important;
    border-radius: 7px 7px 0 0 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
}

.luckysheet-modal-dialog button,
.luckysheet-modal-dialog-button,
.luckysheet-btn,
.luckysheet-btn-primary,
.luckysheet-modal-dialog input[type="button"],
.luckysheet-modal-dialog input[type="submit"] {
    background: #107c41 !important;
    color: #ffffff !important;
    border: 1px solid #0f6c38 !important;
    font-weight: 600 !important;
    border-radius: 4px !important;
    padding: 6px 16px !important;
    font-size: 12px !important;
    cursor: pointer !important;
    transition: all 0.15s ease !important;
}

.luckysheet-modal-dialog button:hover,
.luckysheet-modal-dialog input[type="button"]:hover {
    background: #0f6c38 !important;
}

.luckysheet-modal-dialog input[type="text"],
.luckysheet-modal-dialog select {
    border: 1px solid #8a8886 !important;
    border-radius: 3px !important;
    padding: 4px 8px !important;
    font-size: 12px !important;
    outline: none !important;
}

.luckysheet-modal-dialog input[type="text"]:focus,
.luckysheet-modal-dialog select:focus {
    border-color: #107c41 !important;
    box-shadow: 0 0 0 1px #107c41 !important;
}

/* 🟢 Bottom Sheet Bar with Excel Green Active Underline */
.luckysheet-sheet-area {
    background: #edebe9 !important;
    border-top: 1px solid #d2d0ce !important;
    font-family: 'Segoe UI', Calibri, Arial, sans-serif !important;
}

.luckysheet-sheets-item-active {
    background: #ffffff !important;
    color: #107c41 !important;
    font-weight: 700 !important;
    border-top: 2px solid #107c41 !important;
}

/* Ribbon Tabs Bar */
.excel-ribbon-tabs {
    background: #107c41;
    color: #ffffff;
    display: flex;
    align-items: center;
    gap: 1px;
    padding: 0 8px;
    font-size: 12px;
    font-weight: 600;
    user-select: none;
}

.excel-tab-btn {
    padding: 6px 14px;
    cursor: pointer;
    transition: background-color 0.15s ease;
    border-radius: 6px 6px 0 0;
    color: #ffffff;
}

.excel-tab-btn:hover {
    background-color: #0f6c38;
}

.excel-tab-btn.active {
    background-color: #f3f2f1;
    color: #107c41;
    font-weight: 700;
}
</style>

<div class="font-sans text-slate-800 w-full" x-data="msExcel2021Studio" x-init="initStudio(<?= htmlspecialchars(json_encode($sheets)) ?>, <?= htmlspecialchars(json_encode($initialPayload)) ?>)">
    
    <!-- 🟢 MICROSOFT EXCEL 2021 TOP TITLE & RIBBON HEADER -->
    <div class="bg-[#107c41] text-white rounded-t-xl shadow-lg border border-[#0d6535] overflow-hidden select-none w-full">
        
        <!-- Top App Bar: Title, Search, Status & Actions -->
        <div class="px-3 py-2 flex items-center justify-between gap-3 flex-wrap border-b border-[#0d6535]/60">
            <!-- Left: Excel 2021 App Icon & Title -->
            <div class="flex items-center gap-2.5">
                <div class="w-6 h-6 rounded-md bg-white text-[#107c41] flex items-center justify-center font-black text-xs shadow-sm shrink-0">
                    X
                </div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xs font-bold text-white tracking-wide flex items-center gap-1.5">
                        <span x-text="currentSheetTitle"></span>
                        <span class="text-[10px] font-normal text-emerald-100/80">.xlsx - Excel 2021</span>
                    </h1>
                    <!-- ☁️ Google Sheets-Style Real-time Sync Indicator -->
                    <div class="flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold transition" :class="syncStatusClass">
                        <template x-if="syncStatus === 'saving'">
                            <span class="flex items-center gap-1 text-emerald-200">
                                <i data-lucide="cloud" class="w-3 h-3 animate-pulse"></i>
                                <span>Saving...</span>
                            </span>
                        </template>
                        <template x-if="syncStatus === 'saved'">
                            <span class="flex items-center gap-1 text-emerald-100 font-bold">
                                <i data-lucide="cloud-check" class="w-3 h-3 text-emerald-300"></i>
                                <span>Saved to cloud</span>
                            </span>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Center: 🔍 Excel Search Bar -->
            <div class="relative flex-1 max-w-xs min-w-[180px]">
                <i data-lucide="search" class="w-3 h-3 text-emerald-300 absolute left-2.5 top-1.5"></i>
                <input type="text" 
                       x-model="searchQuery" 
                       @input.debounce.200ms="searchInExcel()" 
                       @keydown.enter="searchNextInExcel()"
                       placeholder="Search sheet..." 
                       class="w-full bg-emerald-900/80 hover:bg-emerald-900 focus:bg-emerald-950 text-white placeholder-emerald-200/60 border border-emerald-600 focus:border-white rounded-lg pl-7 pr-12 py-0.5 text-xs font-medium focus:outline-none transition shadow-inner">
                
                <div class="absolute right-2 top-1 flex items-center gap-1" x-show="searchQuery.trim()">
                    <span class="text-[9px] font-mono text-emerald-200" x-text="searchMatches.length > 0 ? (currentMatchIdx + 1) + '/' + searchMatches.length : '0'"></span>
                    <button type="button" @click="searchNextInExcel()" class="p-0.5 hover:bg-emerald-700 rounded text-white text-[9px]">▼</button>
                </div>
            </div>

            <!-- Right: Pull Live Data, Fullscreen, Upload -->
            <div class="flex items-center gap-1.5">
                <!-- 🔄 Live Data Pull Dropdown -->
                <div class="relative" x-data="{ liveMenuOpen: false }">
                    <button type="button" @click="liveMenuOpen = !liveMenuOpen" class="px-2.5 py-1 bg-emerald-800 hover:bg-emerald-900 text-white rounded-md font-semibold text-xs border border-emerald-600 transition flex items-center gap-1 cursor-pointer">
                        <i data-lucide="refresh-cw" class="w-3 h-3"></i>
                        <span>Pull Live HRMS Data</span>
                        <i data-lucide="chevron-down" class="w-3 h-3"></i>
                    </button>
                    <div x-show="liveMenuOpen" @click.away="liveMenuOpen = false" class="absolute right-0 mt-1 w-56 bg-white rounded-xl shadow-2xl border border-slate-200 py-1 z-50 text-slate-800 text-xs font-medium" style="display: none;">
                        <button type="button" @click="pullLivePortalData('employees'); liveMenuOpen = false;" class="w-full px-3 py-2 text-left hover:bg-emerald-50 flex items-center gap-2 text-slate-700">
                            <i data-lucide="users" class="w-4 h-4 text-emerald-600"></i>
                            <span>👥 Pull All Live Employees</span>
                        </button>
                        <button type="button" @click="pullLivePortalData('attendance'); liveMenuOpen = false;" class="w-full px-3 py-2 text-left hover:bg-emerald-50 flex items-center gap-2 text-slate-700">
                            <i data-lucide="clock" class="w-4 h-4 text-indigo-600"></i>
                            <span>🕒 Pull Today's Live Attendance</span>
                        </button>
                    </div>
                </div>

                <button type="button" @click="toggleFullscreen()" class="px-2.5 py-1 bg-emerald-800 hover:bg-emerald-900 text-white rounded-md font-semibold text-xs border border-emerald-600 transition flex items-center gap-1 cursor-pointer">
                    <i data-lucide="maximize-2" class="w-3 h-3" x-show="!sidebarCollapsed"></i>
                    <i data-lucide="minimize-2" class="w-3 h-3" x-show="sidebarCollapsed" style="display: none;"></i>
                    <span x-text="sidebarCollapsed ? 'Show Sidebar' : 'Fullscreen'"></span>
                </button>

                <button type="button" @click="uploadModalOpen = true" class="px-2.5 py-1 bg-emerald-800 hover:bg-emerald-900 text-white rounded-md font-semibold text-xs border border-emerald-600 transition flex items-center gap-1 cursor-pointer">
                    <i data-lucide="upload-cloud" class="w-3 h-3"></i> Upload .xlsx
                </button>
            </div>
        </div>

        <!-- 📑 Microsoft Excel 2021 Ribbon Tabs (Home, Insert, Page Layout, Formulas, Data, Review, View) -->
        <div class="excel-ribbon-tabs pt-1">
            <div class="excel-tab-btn active">Home</div>
            <div class="excel-tab-btn" @click="executeTabAction('insert')">Insert</div>
            <div class="excel-tab-btn" @click="executeTabAction('formulas')">Formulas</div>
            <div class="excel-tab-btn" @click="executeTabAction('data')">Data</div>
            <div class="excel-tab-btn" @click="executeTabAction('review')">Review</div>
            <div class="excel-tab-btn" @click="executeTabAction('view')">View</div>
        </div>
    </div>

    <!-- 📊 MICROSOFT EXCEL 2021 CANVAS CONTAINER -->
    <div id="luckysheet-wrapper" class="bg-white rounded-b-xl shadow-xl border-r border-l border-b border-slate-300 overflow-hidden relative w-full">
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
        <div @click.away="uploadModalOpen = false" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold">
                        <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
                    </div>
                    <h3 class="font-bold text-sm text-slate-900">Upload & Ingest Spreadsheet</h3>
                </div>
                <button type="button" @click="uploadModalOpen = false" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>

            <form action="?action=upload-smart-sheet" method="POST" enctype="multipart/form-data" class="space-y-4 pt-2" x-data="{ isSubmitting: false }" @submit="isSubmitting = true">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Workbook Title *</label>
                    <input type="text" name="sheet_title" required placeholder="e.g. Sales Report, Attendance 2026" class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Upload File (.xlsx / .csv / .xls)</label>
                    <input type="file" name="sheet_file" accept=".csv, .xlsx, .xls, .tsv" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="uploadModalOpen = false" :disabled="isSubmitting" class="px-4 py-2 bg-slate-100 rounded-lg text-xs font-bold text-slate-600">Cancel</button>
                    <button type="submit" :disabled="isSubmitting" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-sm transition flex items-center gap-2 cursor-pointer disabled:opacity-50">
                        <span x-show="!isSubmitting">Upload Workbook</span>
                        <span x-show="isSubmitting" class="flex items-center gap-1.5" style="display: none;">
                            <i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i> Processing...
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
        syncStatus: 'saved', // 'saved' | 'saving' | 'syncing'
        autoSaveTimeout: null,

        get syncStatusClass() {
            if (this.syncStatus === 'saving') return 'bg-emerald-900/90 text-emerald-200';
            if (this.syncStatus === 'syncing') return 'bg-amber-900/80 text-amber-200';
            return 'bg-emerald-800/80 text-emerald-100';
        },

        initStudio(sheetList, initialData) {
            this.allSheets = sheetList || [];

            window.addEventListener('resize', () => {
                this.resizeLuckysheet();
            });

            // ⚡ 1. Check if Multi-Sheet Workbook is preserved in Device Vault
            let savedMultiSheets = null;
            if (window.HRMSCache) {
                savedMultiSheets = window.HRMSCache.get('excel_multi_sheets_vault');
            }

            if (savedMultiSheets && Array.isArray(savedMultiSheets) && savedMultiSheets.length > 0) {
                this.createLuckysheetInstance(savedMultiSheets);
                return;
            }

            // ⚡ 2. Fallback to initial server preloaded data
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

        executeTabAction(tab) {
            if (typeof luckysheet === 'undefined') return;
            if (tab === 'insert') {
                // Focus chart or image
                const btn = document.querySelector('.luckysheet-icon-chart');
                if (btn) btn.click();
            } else if (tab === 'formulas') {
                const btn = document.querySelector('.luckysheet-icon-function');
                if (btn) btn.click();
            } else if (tab === 'data') {
                const btn = document.querySelector('.luckysheet-icon-sort-filter');
                if (btn) btn.click();
            } else if (tab === 'review') {
                const btn = document.querySelector('.luckysheet-icon-protection');
                if (btn) btn.click();
            } else if (tab === 'view') {
                const btn = document.querySelector('.luckysheet-icon-frozen');
                if (btn) btn.click();
            }
        },

        triggerRealtimeAutoSync() {
            this.syncStatus = 'saving';
            clearTimeout(this.autoSaveTimeout);
            this.autoSaveTimeout = setTimeout(() => {
                this.performBackgroundDatabaseSync();
            }, 600);
        },

        async performBackgroundDatabaseSync() {
            if (typeof luckysheet === 'undefined') return;

            try {
                const fullSheet = luckysheet.getSheetData();
                if (!fullSheet || fullSheet.length === 0) {
                    this.syncStatus = 'saved';
                    return;
                }

                // Update local device vault immediately
                const currentSheets = luckysheet.getAllSheets() || [];
                if (window.HRMSCache && currentSheets.length > 0) {
                    window.HRMSCache.set('excel_multi_sheets_vault', currentSheets);
                }

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
                formData.append('sheet_title', this.currentSheetTitle);
                formData.append('columns_json', JSON.stringify(columns));
                formData.append('rows_json', JSON.stringify(rows));

                const res = await fetch('?action=save-smart-sheet-data', {
                    method: 'POST',
                    body: formData
                });
                const result = await res.json();
                if (result && result.success) {
                    if (result.sheet_id) this.currentSheetId = result.sheet_id;
                    this.syncStatus = 'saved';
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                }
            } catch(e) {
                console.error('Auto-sync error:', e);
                this.syncStatus = 'saved';
            }
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
                        bg: '#107c41',
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
                defaultColWidth: 120,
                enableAddRow: true,
                enableAddBackTop: true,
                data: sheetsData,
                hook: {
                    cellUpdated: (r, c, oldVal, newVal, isRefresh) => {
                        this.triggerRealtimeAutoSync();
                    },
                    sheetActivate: (index, isPivotInitial, isNewSheet) => {
                        this.triggerRealtimeAutoSync();
                    },
                    sheetDelete: (sheet) => {
                        this.triggerRealtimeAutoSync();
                    },
                    updated: (operate) => {
                        this.triggerRealtimeAutoSync();
                    }
                }
            });

            this.isEngineLoading = false;
            this.isFetchingSheet = false;

            // Auto-persist active sheets in device vault
            if (window.HRMSCache && Array.isArray(sheetsData) && sheetsData.length > 0) {
                window.HRMSCache.set('excel_multi_sheets_vault', sheetsData);
            }

            setTimeout(() => {
                this.resizeLuckysheet();
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }, 120);
        }
    }));
});
</script>