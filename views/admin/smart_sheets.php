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

<div class="space-y-2 font-sans text-slate-800" x-data="infiniteMsExcelApp" x-init="initApp(<?= htmlspecialchars(json_encode($sheets)) ?>, <?= $initialSheetId ?>)">
    
    <!-- 🟢 MICROSOFT EXCEL 365 WORKSPACE WRAPPER -->
    <div class="bg-[#107c41] text-white rounded-2xl shadow-2xl overflow-hidden border border-[#0d6535] flex flex-col select-none">
        
        <!-- 1. TOP TITLE BAR -->
        <div class="bg-[#107c41] px-4 py-2 flex items-center justify-between gap-3 text-xs border-b border-emerald-700/60">
            <!-- Left: Excel Logo & Quick Save -->
            <div class="flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-white text-[#107c41] flex items-center justify-center font-black text-sm shadow-xs shrink-0">
                    X
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="saveAndSyncWorkbook()" title="Save & Sync Website-Wide (Ctrl+S)" class="flex items-center gap-1.5 px-3 py-1 rounded-md bg-emerald-800 hover:bg-emerald-900 border border-emerald-600 text-white font-bold transition cursor-pointer shadow-2xs">
                        <i data-lucide="save" class="w-3.5 h-3.5"></i>
                        <span class="text-[11px]" x-text="isSaving ? 'Syncing...' : 'Save & Sync'"></span>
                    </button>
                    <button type="button" @click="undo()" title="Undo" class="p-1 text-emerald-100 hover:bg-emerald-700/80 rounded transition cursor-pointer">↶</button>
                    <button type="button" @click="redo()" title="Redo" class="p-1 text-emerald-100 hover:bg-emerald-700/80 rounded transition cursor-pointer">↷</button>
                </div>

                <!-- Editable Workbook Name -->
                <div class="font-bold text-white tracking-wide flex items-center gap-1.5 pl-2 border-l border-emerald-600/60">
                    <input type="text" x-model="currentSheetTitle" @blur="renameCurrentSheet()" @keydown.enter="$event.target.blur()" class="bg-transparent text-white font-bold border-b border-transparent hover:border-emerald-400 focus:border-white focus:outline-none px-1 rounded transition max-w-[220px]">
                    <span class="text-[10px] text-emerald-200">.xlsx</span>
                    <span class="text-[10px] font-mono px-1.5 py-0.2 rounded bg-emerald-800 text-emerald-200">Cloud Synced</span>
                </div>
            </div>

            <!-- Right: Upload Sheet & Export Options Only -->
            <div class="flex items-center gap-2">
                <button type="button" @click="sidebarCollapsed = !sidebarCollapsed" class="px-2.5 py-1 bg-emerald-800/90 hover:bg-emerald-900 text-white rounded-lg font-bold text-[11px] border border-emerald-600 transition flex items-center gap-1.5 cursor-pointer">
                    <i data-lucide="maximize-2" class="w-3.5 h-3.5" x-show="!sidebarCollapsed"></i>
                    <i data-lucide="minimize-2" class="w-3.5 h-3.5" x-show="sidebarCollapsed" style="display: none;"></i>
                    <span x-text="sidebarCollapsed ? 'Exit Fullscreen' : 'Fullscreen'"></span>
                </button>
                <button type="button" @click="uploadModalOpen = true" class="px-3.5 py-1 bg-white hover:bg-emerald-50 text-[#107c41] rounded-lg font-black text-xs shadow-md transition flex items-center gap-1.5 cursor-pointer">
                    <i data-lucide="upload-cloud" class="w-4 h-4"></i> Upload Sheet
                </button>
                <button type="button" @click="exportAsCsv()" class="px-3 py-1 bg-emerald-800 hover:bg-emerald-900 text-white rounded-lg font-bold text-[11px] border border-emerald-600 transition flex items-center gap-1.5 cursor-pointer">
                    <i data-lucide="download" class="w-3.5 h-3.5"></i> Export
                </button>
            </div>
        </div>

        <!-- 2. EXCEL RIBBON TABS -->
        <div class="bg-[#107c41] px-4 pt-1.5 flex items-center gap-1 text-xs border-b border-emerald-800">
            <button type="button" @click="ribbonTab = 'home'" :class="ribbonTab === 'home' ? 'bg-[#f3f4f6] text-slate-900 font-bold shadow-xs' : 'text-white hover:bg-emerald-700/80 font-medium'" class="px-4 py-1.5 rounded-t-lg transition cursor-pointer">Home</button>
            <button type="button" @click="ribbonTab = 'formulas'" :class="ribbonTab === 'formulas' ? 'bg-[#f3f4f6] text-slate-900 font-bold shadow-xs' : 'text-white hover:bg-emerald-700/80 font-medium'" class="px-4 py-1.5 rounded-t-lg transition cursor-pointer">Formulas</button>
            <button type="button" @click="ribbonTab = 'data'" :class="ribbonTab === 'data' ? 'bg-[#f3f4f6] text-slate-900 font-bold shadow-xs' : 'text-white hover:bg-emerald-700/80 font-medium'" class="px-4 py-1.5 rounded-t-lg transition cursor-pointer">Data Sync</button>
        </div>

        <!-- 3. REAL MICROSOFT EXCEL RIBBON INSTRUMENTS (Home Tab) -->
        <div class="bg-[#f3f4f6] text-slate-800 p-2 flex items-center justify-between gap-3 text-xs border-b border-slate-300 flex-wrap shadow-inner">
            
            <div class="flex items-center gap-2 flex-wrap">
                <!-- Font & Size -->
                <div class="flex items-center gap-1 bg-white border border-slate-300 rounded-lg p-1 shadow-2xs">
                    <select x-model="fontFamily" class="bg-transparent text-xs font-semibold text-slate-800 focus:outline-none pr-1">
                        <option value="font-sans">Calibri</option>
                        <option value="font-sans">Segoe UI</option>
                        <option value="font-mono">Consolas</option>
                        <option value="font-serif">Georgia</option>
                    </select>
                    <select x-model="fontSize" class="bg-transparent text-xs font-bold text-slate-800 border-l border-slate-200 pl-1 focus:outline-none">
                        <option value="text-[11px]">9</option>
                        <option value="text-xs">11</option>
                        <option value="text-sm">12</option>
                        <option value="text-base">14</option>
                    </select>
                </div>

                <!-- B / I / U Styling -->
                <div class="flex items-center bg-white border border-slate-300 rounded-lg p-0.5 shadow-2xs">
                    <button type="button" @click="isBold = !isBold" :class="isBold ? 'bg-emerald-100 text-emerald-900 font-black' : 'text-slate-700 hover:bg-slate-100'" class="w-6 h-6 rounded text-xs font-bold transition flex items-center justify-center cursor-pointer">B</button>
                    <button type="button" @click="isItalic = !isItalic" :class="isItalic ? 'bg-emerald-100 text-emerald-900' : 'text-slate-700 hover:bg-slate-100'" class="w-6 h-6 rounded text-xs italic font-bold transition flex items-center justify-center cursor-pointer">I</button>
                    <button type="button" @click="isUnderline = !isUnderline" :class="isUnderline ? 'bg-emerald-100 text-emerald-900 underline' : 'text-slate-700 hover:bg-slate-100'" class="w-6 h-6 rounded text-xs underline font-bold transition flex items-center justify-center cursor-pointer">U</button>
                </div>

                <!-- Cell Fill Color Palette -->
                <div class="flex items-center gap-1 bg-white border border-slate-300 rounded-lg px-2 py-1 shadow-2xs">
                    <span class="text-[10px] uppercase font-bold text-slate-400">Fill:</span>
                    <button type="button" @click="activeCellFill = 'bg-white'" class="w-4 h-4 rounded-full bg-white border border-slate-300 cursor-pointer" title="No Fill"></button>
                    <button type="button" @click="activeCellFill = 'bg-yellow-200 text-yellow-900 font-bold'" class="w-4 h-4 rounded-full bg-yellow-300 cursor-pointer" title="Yellow"></button>
                    <button type="button" @click="activeCellFill = 'bg-emerald-200 text-emerald-900 font-bold'" class="w-4 h-4 rounded-full bg-emerald-400 cursor-pointer" title="Green"></button>
                    <button type="button" @click="activeCellFill = 'bg-rose-200 text-rose-900 font-bold'" class="w-4 h-4 rounded-full bg-rose-400 cursor-pointer" title="Red"></button>
                    <button type="button" @click="activeCellFill = 'bg-indigo-200 text-indigo-900 font-bold'" class="w-4 h-4 rounded-full bg-indigo-400 cursor-pointer" title="Blue"></button>
                </div>

                <!-- Alignments -->
                <div class="flex items-center bg-white border border-slate-300 rounded-lg p-0.5 shadow-2xs">
                    <button type="button" @click="textAlign = 'text-left'" :class="textAlign === 'text-left' ? 'bg-slate-200 text-slate-900' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-xs flex items-center justify-center cursor-pointer">≡</button>
                    <button type="button" @click="textAlign = 'text-center'" :class="textAlign === 'text-center' ? 'bg-slate-200 text-slate-900' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-xs flex items-center justify-center cursor-pointer">≢</button>
                    <button type="button" @click="textAlign = 'text-right'" :class="textAlign === 'text-right' ? 'bg-slate-200 text-slate-900' : 'text-slate-600 hover:bg-slate-100'" class="w-6 h-6 rounded text-xs flex items-center justify-center cursor-pointer">≣</button>
                </div>

                <div class="h-5 w-px bg-slate-300 mx-0.5"></div>

                <!-- Number / Format Tools -->
                <div class="flex items-center bg-white border border-slate-300 rounded-lg px-2 py-0.5 gap-1.5 shadow-2xs text-xs font-mono">
                    <button type="button" @click="formatCurrency()" class="px-1 py-0.5 hover:bg-slate-100 rounded text-slate-700 font-bold cursor-pointer">₹</button>
                    <button type="button" @click="formatPercent()" class="px-1 py-0.5 hover:bg-slate-100 rounded text-slate-700 font-bold cursor-pointer">%</button>
                    <button type="button" @click="formatComma()" class="px-1 py-0.5 hover:bg-slate-100 rounded text-slate-700 font-bold cursor-pointer">,</button>
                </div>
            </div>

            <!-- Find in Workbook Search -->
            <div class="relative w-48">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2"></i>
                <input type="text" x-model="searchQuery" placeholder="Find in sheet..." class="w-full bg-white border border-slate-300 rounded-lg pl-7 pr-2.5 py-1 text-xs text-slate-800 focus:outline-none focus:ring-1 focus:ring-emerald-600 shadow-2xs">
            </div>
        </div>

        <!-- 4. REAL EXCEL FORMULA BAR -->
        <div class="bg-white px-3 py-1.5 flex items-center gap-2 border-b border-slate-300 text-xs font-mono text-slate-800 shadow-inner">
            <div class="w-16 text-center font-black text-slate-800 bg-[#f3f4f6] py-0.5 rounded border border-slate-300 shadow-2xs" x-text="activeCell.coord"></div>
            <div class="h-4 w-px bg-slate-300 mx-0.5"></div>
            <div class="font-bold text-slate-400 italic text-sm">fx</div>
            <input type="text" 
                   x-model="formulaInput" 
                   @input="updateActiveCellValue()" 
                   placeholder="Enter cell text, number, or formula..." 
                   class="flex-1 text-xs font-semibold text-slate-900 bg-transparent focus:outline-none">
        </div>

        <!-- 5. SYNC BANNER NOTIFICATION -->
        <div x-show="syncSuccessBanner" class="p-3 bg-emerald-50 border-b border-emerald-200 text-emerald-900 text-xs font-bold flex items-center justify-between" style="display: none;">
            <span class="flex items-center gap-2">
                <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600"></i>
                ✅ All Excel edits saved! Employee Directory, Attendance Logs, and Birthday Hub updated automatically!
            </span>
            <button type="button" @click="syncSuccessBanner = false" class="text-emerald-700 hover:text-emerald-900 font-bold">✕</button>
        </div>

        <!-- 6. LOADING SPINNER -->
        <div x-show="isLoading" class="py-36 text-center text-slate-400 bg-white">
            <i data-lucide="loader-2" class="w-10 h-10 mx-auto animate-spin text-[#107c41] mb-2"></i>
            <p class="text-xs font-bold text-slate-700">Loading Excel Worksheet Data...</p>
        </div>

        <!-- 7. ♾️ INFINITE EXCEL SPREADSHEET CANVAS GRID -->
        <div x-show="!isLoading" 
             class="overflow-auto max-h-[660px] select-text bg-[#e6e6e6]" 
             @keydown.up.prevent="navigateCell(-1, 0)"
             @keydown.down.prevent="navigateCell(1, 0)"
             @keydown.left.prevent="navigateCell(0, -1)"
             @keydown.right.prevent="navigateCell(0, 1)">
            
            <table class="w-full text-left text-xs border-collapse font-sans bg-white">
                <!-- Top Excel Letters Header (A, B, C, D... Z, AA, AB...) -->
                <thead class="bg-[#f3f4f6] text-slate-600 font-mono text-[11px] sticky top-0 z-20 select-none shadow-xs">
                    <tr class="border-b border-slate-300">
                        <th class="py-1 px-2 border-r border-slate-300 w-12 text-center bg-[#e6e6e6] text-slate-500 font-bold text-xs">◢</th>
                        <template x-for="cIdx in totalColCount" :key="cIdx">
                            <th class="py-1 px-3 border-r border-slate-300 tracking-wider text-center font-bold" 
                                :class="activeCell.c === (cIdx - 1) ? 'bg-[#d1e7dd] text-[#0f5132] font-black' : ''"
                                x-text="getColLetter(cIdx - 1)"></th>
                        </template>
                    </tr>
                    <!-- Editable Column Header Names -->
                    <tr class="border-b-2 border-slate-400 bg-[#2d3748] text-white font-bold text-xs">
                        <th class="py-1.5 px-2 border-r border-slate-700 text-center bg-[#1a202c] font-mono text-[10px] text-slate-400">COL</th>
                        <template x-for="cIdx in totalColCount" :key="cIdx">
                            <th class="py-2 px-2 border-r border-slate-700 whitespace-nowrap min-w-[130px]">
                                <input type="text" 
                                       :value="columns[cIdx - 1] !== undefined ? columns[cIdx - 1] : getColLetter(cIdx - 1)" 
                                       @input="setColumnName(cIdx - 1, $event.target.value)"
                                       class="bg-transparent text-white font-bold text-xs focus:outline-none focus:ring-1 focus:ring-emerald-400 rounded px-1.5 py-0.5 w-full">
                            </th>
                        </template>
                    </tr>
                </thead>

                <!-- Infinite Row Cells (1, 2, 3... 50, 100...) -->
                <tbody class="divide-y divide-slate-200">
                    <template x-for="rIdx in totalRowCount" :key="rIdx">
                        <tr class="hover:bg-emerald-50/20 transition">
                            <!-- Row Number Index -->
                            <td class="py-1 px-2 border-r border-slate-300 text-center font-mono text-[10px] font-bold select-none" 
                                :class="activeCell.r === (rIdx - 1) ? 'bg-[#d1e7dd] text-[#0f5132] font-black' : 'bg-[#f3f4f6] text-slate-500'"
                                x-text="rIdx"></td>

                            <!-- Dynamic Cell Inputs -->
                            <template x-for="cIdx in totalColCount" :key="cIdx">
                                <td class="p-0 border-r border-slate-200 relative" 
                                    :class="activeCell.r === (rIdx - 1) && activeCell.c === (cIdx - 1) ? 'ring-2 ring-[#107c41] bg-emerald-50/80 z-10' : ''"
                                    @click="selectCell(rIdx - 1, cIdx - 1)">
                                    <input type="text" 
                                           :value="getCellValue(rIdx - 1, cIdx - 1)" 
                                           @input="setCellValue(rIdx - 1, cIdx - 1, $event.target.value)"
                                           @focus="selectCell(rIdx - 1, cIdx - 1)"
                                           :class="[
                                               fontSize,
                                               fontFamily,
                                               textAlign,
                                               isBold ? 'font-bold' : '',
                                               isItalic ? 'italic' : '',
                                               isUnderline ? 'underline' : '',
                                               getCellValue(rIdx - 1, cIdx - 1) === 'Present' || getCellValue(rIdx - 1, cIdx - 1) === 'active' ? 'text-emerald-700 font-bold' : (getCellValue(rIdx - 1, cIdx - 1) === 'Absent' || getCellValue(rIdx - 1, cIdx - 1) === 'inactive' ? 'text-rose-600 font-bold' : (String(getCellValue(rIdx - 1, cIdx - 1)).includes('@') ? 'font-mono text-indigo-700' : 'text-slate-800'))
                                           ]"
                                           class="w-full h-full py-2 px-3 bg-transparent focus:outline-none border-0 min-w-[125px]">
                                </td>
                            </template>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- 8. REAL EXCEL BOTTOM SHEET TABS & STATUS BAR -->
        <div class="bg-[#f3f4f6] px-3 py-1.5 border-t border-slate-300 flex items-center justify-between text-xs text-slate-600 font-mono select-none flex-wrap gap-2">
            
            <!-- Bottom Sheet Tabs -->
            <div class="flex items-center gap-1 overflow-x-auto no-scrollbar py-0.5">
                <template x-for="(sh, idx) in allSheets" :key="sh.id">
                    <div class="flex items-center rounded-t-lg transition border-t-2 border-r border-l"
                         :class="currentSheetId === sh.id ? 'bg-white text-[#107c41] border-t-[#107c41] border-slate-300 font-black shadow-xs' : 'bg-slate-200 text-slate-600 hover:bg-slate-300 border-t-transparent border-slate-300 font-medium'">
                        
                        <button type="button" 
                                @click="switchSheet(sh.id)" 
                                class="px-3 py-1 text-xs flex items-center gap-1.5 cursor-pointer font-sans">
                            <i data-lucide="sheet" class="w-3.5 h-3.5 text-[#107c41]"></i>
                            <span x-text="sh.title"></span>
                            <span class="text-[9px] font-mono text-slate-400" x-text="'(' + sh.record_count + ')'"></span>
                        </button>

                        <button type="button" 
                                @click.stop="deleteSheetInPlace(sh.id, sh.title)" 
                                title="Delete Sheet"
                                class="pr-2 pl-0.5 py-1 text-xs text-slate-400 hover:text-rose-600 cursor-pointer">
                            ✕
                        </button>
                    </div>
                </template>

                <!-- ➕ NATIVE EXCEL '+' BUTTON: ADDS BLANK WORKSHEET TAB DIRECTLY -->
                <button type="button" 
                        @click="addNewBlankSheet()" 
                        class="px-2.5 py-1 rounded-t-lg text-xs font-black bg-slate-200 hover:bg-slate-300 hover:text-[#107c41] text-slate-700 transition flex items-center gap-1 cursor-pointer" 
                        title="New Worksheet (+)">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- Status Calculation Bar -->
            <div class="flex items-center gap-4 text-[11px] text-slate-600 font-sans">
                <span class="flex items-center gap-1.5 text-[#107c41] font-bold">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Ready
                </span>
                <span class="font-mono" x-text="'Rows: ' + totalRowCount"></span>
                <span class="font-mono" x-text="'Cols: ' + totalColCount"></span>
                <span class="font-mono text-slate-400">Zoom: 100%</span>
            </div>
        </div>
    </div>

    <!-- 📤 UPLOAD MODAL (Opened ONLY from Ribbon Upload Sheet) -->
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
                        <input type="text" name="custom_category" placeholder="Enter New Section Name" class="w-full bg-emerald-50/60 border border-emerald-300 rounded-xl px-3 py-2 text-xs font-bold text-emerald-900 focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Google Sheets Link (Public link)</label>
                    <input type="url" name="google_sheet_url" placeholder="https://docs.google.com/spreadsheets/d/1.../edit" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Or Upload File (.xlsx / .csv)</label>
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
    Alpine.data('infiniteMsExcelApp', () => ({
        uploadModalOpen: false,
        allSheets: [],
        currentSheetId: 0,
        currentSheetTitle: '',
        columns: [],
        rows: [],
        activeCell: { r: 0, c: 0, coord: 'A1', val: '' },
        formulaInput: '',
        searchQuery: '',
        isLoading: false,
        isSaving: false,
        ribbonTab: 'home',
        isBold: false,
        isItalic: false,
        isUnderline: false,
        fontSize: 'text-xs',
        fontFamily: 'font-sans',
        textAlign: 'text-left',
        activeCellFill: 'bg-white',
        syncSuccessBanner: false,

        initApp(sheetList, defaultId) {
            this.allSheets = sheetList || [];
            if (defaultId > 0) {
                this.switchSheet(defaultId);
            }
        },

        // Dynamic Infinite Column & Row counts (Always extends at least 26 cols and 60 rows)
        get totalColCount() {
            return Math.max(this.columns.length + 5, 26);
        },

        get totalRowCount() {
            return Math.max(this.rows.length + 20, 60);
        },

        getColLetter(idx) {
            let letter = '';
            while (idx >= 0) {
                letter = String.fromCharCode((idx % 26) + 65) + letter;
                idx = Math.floor(idx / 26) - 1;
            }
            return letter;
        },

        getCellValue(r, c) {
            if (this.rows[r] && this.rows[r][c] !== undefined) {
                return this.rows[r][c];
            }
            return '';
        },

        setCellValue(r, c, val) {
            // Expand rows if needed
            while (this.rows.length <= r) {
                this.rows.push(new Array(this.totalColCount).fill(''));
            }
            while (this.rows[r].length <= c) {
                this.rows[r].push('');
            }
            this.rows[r][c] = val;
            if (this.activeCell.r === r && this.activeCell.c === c) {
                this.formulaInput = val;
                this.activeCell.val = val;
            }
        },

        setColumnName(c, val) {
            while (this.columns.length <= c) {
                this.columns.push(this.getColLetter(this.columns.length));
            }
            this.columns[c] = val;
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

        async addNewBlankSheet() {
            try {
                const res = await fetch('?action=create-blank-smart-sheet', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success && data.sheet) {
                    this.allSheets.push(data.sheet);
                    this.currentSheetId = data.sheet.id;
                    this.currentSheetTitle = data.sheet.title;
                    this.columns = data.sheet.columns;
                    this.rows = data.sheet.rows;
                    this.selectCell(0, 0);
                }
            } catch(e) {
                alert('Failed to add blank sheet');
            }
        },

        async renameCurrentSheet() {
            if (this.currentSheetId <= 0 || !this.currentSheetTitle.trim()) return;
            const formData = new FormData();
            formData.append('sheet_id', this.currentSheetId);
            formData.append('title', this.currentSheetTitle.trim());

            try {
                await fetch('?action=rename-smart-sheet', {
                    method: 'POST',
                    body: formData
                });
                const target = this.allSheets.find(s => s.id === this.currentSheetId);
                if (target) target.title = this.currentSheetTitle.trim();
            } catch(e) {}
        },

        selectCell(r, c) {
            this.activeCell.r = r;
            this.activeCell.c = c;
            this.activeCell.coord = this.getColLetter(c) + (r + 1);
            this.activeCell.val = this.getCellValue(r, c);
            this.formulaInput = this.activeCell.val;
        },

        navigateCell(dr, dc) {
            const newR = Math.max(0, Math.min(this.totalRowCount - 1, this.activeCell.r + dr));
            const newC = Math.max(0, Math.min(this.totalColCount - 1, this.activeCell.c + dc));
            this.selectCell(newR, newC);
        },

        updateActiveCellValue() {
            this.setCellValue(this.activeCell.r, this.activeCell.c, this.formulaInput);
        },

        formatCurrency() {
            let val = parseFloat(this.activeCell.val);
            if (!isNaN(val)) {
                this.formulaInput = '₹' + val.toLocaleString('en-IN', { minimumFractionDigits: 2 });
                this.updateActiveCellValue();
            }
        },

        formatPercent() {
            let val = parseFloat(this.activeCell.val);
            if (!isNaN(val)) {
                this.formulaInput = (val * 100).toFixed(2) + '%';
                this.updateActiveCellValue();
            }
        },

        formatComma() {
            let val = parseFloat(this.activeCell.val);
            if (!isNaN(val)) {
                this.formulaInput = val.toLocaleString('en-IN');
                this.updateActiveCellValue();
            }
        },

        undo() {},
        redo() {},

        async deleteSheetInPlace(sheetId, sheetTitle) {
            if (!confirm('Are you sure you want to delete "' + sheetTitle + '"?')) return;

            try {
                const formData = new FormData();
                formData.append('sheet_id', sheetId);

                await fetch('?action=delete-smart-sheet', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: formData
                });

                this.allSheets = this.allSheets.filter(s => s.id !== sheetId);

                if (this.currentSheetId === sheetId) {
                    if (this.allSheets.length > 0) {
                        this.switchSheet(this.allSheets[0].id);
                    } else {
                        this.columns = [];
                        this.rows = [];
                        this.currentSheetId = 0;
                        this.currentSheetTitle = '';
                    }
                }
            } catch(e) {
                alert('Failed to delete sheet');
            }
        },

        async saveAndSyncWorkbook() {
            if (this.currentSheetId <= 0) return;
            this.isSaving = true;

            // Trim trailing empty rows & cols for clean storage
            const cleanRows = this.rows.filter(r => r && r.some(c => c !== undefined && String(c).trim() !== ''));
            const cleanCols = this.columns.length > 0 ? this.columns : ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

            const formData = new FormData();
            formData.append('sheet_id', this.currentSheetId);
            formData.append('columns_json', JSON.stringify(cleanCols));
            formData.append('rows_json', JSON.stringify(cleanRows));

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
        }
    }));
});
</script>