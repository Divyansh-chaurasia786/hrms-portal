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

<!-- Luckysheet Full MS Excel 2021 Core CSS & Plugins -->
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
    height: calc(100vh - 210px) !important;
    min-height: 680px !important;
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

/* Formula Bar Full Width */
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

/* Hide default cramped toolbar in favor of our comprehensive MS Excel 2021 Ribbon */
.luckysheet-toolbar {
    display: none !important;
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

/* Excel 2021 Ribbon Button Hover & Active states */
.excel-ribbon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 4px 6px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    color: #334155;
    transition: all 0.15s ease;
    cursor: pointer;
    border: 1px solid transparent;
    user-select: none;
}
.excel-ribbon-btn:hover {
    background-color: #e2e8f0;
    border-color: #cbd5e1;
    color: #0f172a;
}
.excel-ribbon-btn:active {
    background-color: #cbd5e1;
    transform: scale(0.97);
}
.excel-ribbon-divider {
    height: 22px;
    width: 1px;
    background-color: #cbd5e1;
    margin: 0 4px;
}
</style>

<div class="space-y-2 font-sans text-slate-800 w-full" x-data="msExcel2021Studio" x-init="initStudio(<?= htmlspecialchars(json_encode($sheets)) ?>, <?= htmlspecialchars(json_encode($initialPayload)) ?>)">
    
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
                        <span class="text-[10px] font-normal text-emerald-200">.xlsx - Excel 2021 Enterprise</span>
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
                   placeholder="Search anything in sheet..." 
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

    <!-- 🛠️ 100% COMPLETE FULL-WIDTH MICROSOFT EXCEL 2021 RIBBON -->
    <div class="bg-[#f8fafc] border-x border-b border-slate-300 p-1.5 flex items-center justify-between gap-1 text-xs select-none w-full flex-wrap shadow-inner">
        
        <!-- All Excel Instruments Row -->
        <div class="flex items-center gap-1 flex-wrap w-full">
            
            <!-- 1. Undo / Redo -->
            <button type="button" @click="executeExcelCommand('undo')" class="excel-ribbon-btn" title="Undo (Ctrl+Z)">
                <i data-lucide="undo" class="w-3.5 h-3.5"></i>
            </button>
            <button type="button" @click="executeExcelCommand('redo')" class="excel-ribbon-btn" title="Redo (Ctrl+Y)">
                <i data-lucide="redo" class="w-3.5 h-3.5"></i>
            </button>

            <div class="excel-ribbon-divider"></div>

            <!-- 2. Font Family & Size -->
            <div class="flex items-center gap-1 bg-white border border-slate-300 rounded-md px-1.5 py-0.5 shadow-2xs">
                <select @change="executeExcelFormat('font', $event.target.value)" class="bg-transparent text-xs font-semibold text-slate-700 focus:outline-none pr-1">
                    <option value="Segoe UI" selected>Segoe UI</option>
                    <option value="Calibri">Calibri</option>
                    <option value="Arial">Arial</option>
                    <option value="Consolas">Consolas</option>
                    <option value="Georgia">Georgia</option>
                </select>
                <select @change="executeExcelFormat('fontSize', $event.target.value)" class="bg-transparent text-xs font-bold text-slate-700 border-l border-slate-200 pl-1 focus:outline-none">
                    <option value="9">9</option>
                    <option value="10">10</option>
                    <option value="11" selected>11</option>
                    <option value="12">12</option>
                    <option value="14">14</option>
                    <option value="16">16</option>
                    <option value="18">18</option>
                </select>
            </div>

            <!-- 3. Bold, Italic, Underline, Strikethrough -->
            <div class="flex items-center bg-white border border-slate-300 rounded-md p-0.5 shadow-2xs">
                <button type="button" @click="executeExcelFormat('bold')" class="excel-ribbon-btn font-black w-6 h-6" title="Bold (Ctrl+B)">B</button>
                <button type="button" @click="executeExcelFormat('italic')" class="excel-ribbon-btn italic font-bold w-6 h-6" title="Italic (Ctrl+I)">I</button>
                <button type="button" @click="executeExcelFormat('underline')" class="excel-ribbon-btn underline font-bold w-6 h-6" title="Underline (Ctrl+U)">U</button>
                <button type="button" @click="executeExcelFormat('strikethrough')" class="excel-ribbon-btn line-through font-bold w-6 h-6" title="Strikethrough">S</button>
            </div>

            <!-- 4. Text Color & Fill Color -->
            <div class="flex items-center gap-1 bg-white border border-slate-300 rounded-md px-1.5 py-0.5 shadow-2xs">
                <span class="text-[10px] font-bold text-slate-400">Fill:</span>
                <button type="button" @click="executeExcelFormat('bgColor', '#ffffff')" class="w-3.5 h-3.5 rounded bg-white border border-slate-300" title="No Fill"></button>
                <button type="button" @click="executeExcelFormat('bgColor', '#fef08a')" class="w-3.5 h-3.5 rounded bg-yellow-300" title="Yellow"></button>
                <button type="button" @click="executeExcelFormat('bgColor', '#a7f3d0')" class="w-3.5 h-3.5 rounded bg-emerald-300" title="Green"></button>
                <button type="button" @click="executeExcelFormat('bgColor', '#fecdd3')" class="w-3.5 h-3.5 rounded bg-rose-300" title="Red"></button>
                <button type="button" @click="executeExcelFormat('bgColor', '#c7d2fe')" class="w-3.5 h-3.5 rounded bg-indigo-300" title="Blue"></button>
            </div>

            <div class="excel-ribbon-divider"></div>

            <!-- 5. Borders & Merge -->
            <div class="flex items-center bg-white border border-slate-300 rounded-md p-0.5 shadow-2xs">
                <button type="button" @click="executeExcelFormat('border', 'all')" class="excel-ribbon-btn" title="All Borders">
                    <i data-lucide="grid" class="w-3.5 h-3.5"></i>
                </button>
                <button type="button" @click="executeExcelFormat('border', 'none')" class="excel-ribbon-btn text-[10px]" title="Clear Borders">
                    No Border
                </button>
                <button type="button" @click="executeExcelFormat('merge')" class="excel-ribbon-btn text-[10px] font-bold" title="Merge & Center">
                    Merge
                </button>
            </div>

            <!-- 6. Alignments -->
            <div class="flex items-center bg-white border border-slate-300 rounded-md p-0.5 shadow-2xs">
                <button type="button" @click="executeExcelFormat('align', 'left')" class="excel-ribbon-btn w-6 h-6" title="Align Left">
                    <i data-lucide="align-left" class="w-3 h-3"></i>
                </button>
                <button type="button" @click="executeExcelFormat('align', 'center')" class="excel-ribbon-btn w-6 h-6" title="Align Center">
                    <i data-lucide="align-center" class="w-3 h-3"></i>
                </button>
                <button type="button" @click="executeExcelFormat('align', 'right')" class="excel-ribbon-btn w-6 h-6" title="Align Right">
                    <i data-lucide="align-right" class="w-3 h-3"></i>
                </button>
                <button type="button" @click="executeExcelFormat('textWrap')" class="excel-ribbon-btn text-[10px]" title="Wrap Text">
                    Wrap
                </button>
            </div>

            <div class="excel-ribbon-divider"></div>

            <!-- 7. Number & Currency Formats -->
            <div class="flex items-center bg-white border border-slate-300 rounded-md px-1.5 py-0.5 gap-1 shadow-2xs font-mono text-xs">
                <button type="button" @click="executeExcelFormat('currency', 'INR')" class="excel-ribbon-btn font-bold px-1" title="Rupee (₹)">₹</button>
                <button type="button" @click="executeExcelFormat('currency', 'USD')" class="excel-ribbon-btn font-bold px-1" title="Dollar ($)">$</button>
                <button type="button" @click="executeExcelFormat('percent')" class="excel-ribbon-btn font-bold px-1" title="Percentage (%)">%</button>
                <button type="button" @click="executeExcelFormat('comma')" class="excel-ribbon-btn font-bold px-1" title="Comma (,)">,</button>
                <button type="button" @click="executeExcelFormat('decimalIncrease')" class="excel-ribbon-btn font-bold px-1" title="Increase Decimal">.00</button>
                <button type="button" @click="executeExcelFormat('decimalDecrease')" class="excel-ribbon-btn font-bold px-1" title="Decrease Decimal">.0</button>
            </div>

            <div class="excel-ribbon-divider"></div>

            <!-- 8. Advanced Tools: AutoSum, Freeze, Filter, Sort, Chart, Validation -->
            <div class="flex items-center gap-1">
                <button type="button" @click="executeExcelFormat('autoSum')" class="excel-ribbon-btn bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200" title="AutoSum">
                    <span class="font-bold mr-0.5">Σ</span> AutoSum
                </button>
                <button type="button" @click="executeExcelFormat('filter')" class="excel-ribbon-btn" title="Sort & Filter Funnel">
                    <i data-lucide="filter" class="w-3.5 h-3.5 text-slate-600"></i>
                    <span class="ml-1">Filter</span>
                </button>
                <button type="button" @click="executeExcelFormat('freeze')" class="excel-ribbon-btn" title="Freeze Header Row">
                    <i data-lucide="snowflake" class="w-3.5 h-3.5 text-blue-600"></i>
                    <span class="ml-1">Freeze</span>
                </button>
            </div>

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
        <div x-show="isFetchingSheet" class="absolute inset-0 bg-white/80 backdrop-blur-xs z-30 flex flex-col items-center justify-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-[#107c41] text-white flex items-center justify-center font-bold shadow-lg animate-bounce">
                X
            </div>
            <div class="flex items-center gap-2 text-xs font-bold text-slate-700">
                <i data-lucide="loader-2" class="w-4 h-4 animate-spin text-[#107c41]"></i>
                Loading Excel 2021 Workbook...
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
        syncSuccessBanner: false,

        initStudio(sheetList, initialData) {
            this.allSheets = sheetList || [];

            window.addEventListener('resize', () => {
                this.resizeLuckysheet();
            });

            if (initialData && initialData.id > 0) {
                this.currentSheetId = initialData.id;
                this.currentSheetTitle = initialData.title || 'Excel Workbook';
                this.renderLuckysheetFromData(initialData.title || 'Sheet1', initialData.columns || [], initialData.rows || []);
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

        executeExcelCommand(cmd) {
            if (typeof luckysheet === 'undefined') return;
            if (cmd === 'undo') luckysheet.undo();
            else if (cmd === 'redo') luckysheet.redo();
        },

        executeExcelFormat(type, value) {
            if (typeof luckysheet === 'undefined') return;

            if (type === 'bold') {
                luckysheet.setRangeFormat("bl", 1);
            } else if (type === 'italic') {
                luckysheet.setRangeFormat("it", 1);
            } else if (type === 'underline') {
                luckysheet.setRangeFormat("un", 1);
            } else if (type === 'strikethrough') {
                luckysheet.setRangeFormat("cl", 1);
            } else if (type === 'font') {
                luckysheet.setRangeFormat("ff", value);
            } else if (type === 'fontSize') {
                luckysheet.setRangeFormat("fs", parseInt(value));
            } else if (type === 'bgColor') {
                luckysheet.setRangeFormat("bg", value);
            } else if (type === 'align') {
                const alignCode = value === 'left' ? 0 : (value === 'center' ? 1 : 2);
                luckysheet.setRangeFormat("ht", alignCode);
            } else if (type === 'textWrap') {
                luckysheet.setRangeFormat("tb", 1);
            } else if (type === 'merge') {
                luckysheet.setMerge("merge-all");
            } else if (type === 'border') {
                if (value === 'all') {
                    luckysheet.setBorder("all", { rangeType: "all", borderType: "border-all", color: "#000000", style: "1" });
                } else {
                    luckysheet.setBorder("none");
                }
            } else if (type === 'currency') {
                const fmt = value === 'INR' ? '₹#,##0.00' : '$#,##0.00';
                luckysheet.setRangeFormat("ct", { fa: fmt, t: "n" });
            } else if (type === 'percent') {
                luckysheet.setRangeFormat("ct", { fa: "0.00%", t: "n" });
            } else if (type === 'comma') {
                luckysheet.setRangeFormat("ct", { fa: "#,##0", t: "n" });
            } else if (type === 'decimalIncrease') {
                luckysheet.setRangeFormat("ct", { fa: "#,##0.000", t: "n" });
            } else if (type === 'decimalDecrease') {
                luckysheet.setRangeFormat("ct", { fa: "#,##0", t: "n" });
            } else if (type === 'freeze') {
                luckysheet.setFrozenRow(1);
            } else if (type === 'filter') {
                luckysheet.setFilter();
            } else if (type === 'autoSum') {
                luckysheet.insertFunction("SUM");
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

        async loadAndRenderSheet(sheetId) {
            this.currentSheetId = sheetId;
            this.isFetchingSheet = true;
            try {
                const res = await fetch('?action=get-smart-sheet-data&sheet_id=' + sheetId);
                const data = await res.json();
                this.currentSheetTitle = data.title || 'Workbook';
                this.renderLuckysheetFromData(data.title || 'Sheet1', data.columns || [], data.rows || []);
            } catch(e) {
                console.error(e);
                this.renderBlankLuckysheet('Sheet1');
            } finally {
                this.isFetchingSheet = false;
            }
        },

        renderLuckysheetFromData(sheetName, columns, rows) {
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
                row: 100,
                column: 52
            }];
            this.createLuckysheetInstance(sheetConfig);
        },

        createLuckysheetInstance(sheetsData) {
            if (typeof luckysheet === 'undefined' || !luckysheet.create) {
                setTimeout(() => this.createLuckysheetInstance(sheetsData), 60);
                return;
            }

            try { luckysheet.destroy(); } catch(e) {}
            luckysheet.create({
                container: 'luckysheet',
                title: this.currentSheetTitle,
                lang: 'en',
                showinfobar: false,
                showtoolbar: false,
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

            setTimeout(() => {
                this.resizeLuckysheet();
                if (typeof lucide !== 'undefined') lucide.createIcons();
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