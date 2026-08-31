<!-- views/calling/manage.php (Team Lead & HR Live Calling Master Board with Equal Division & Live Status) -->
<?php
$user = authUser() ?: ['id' => 1, 'role' => 'admin', 'name' => 'Admin'];
$db = getDBConnection();
$today = date('Y-m-d');

// 1. Fetch active BDA team callers
$callers = $db->query("
    SELECT id, name, emp_id, designation 
    FROM users 
    WHERE status = 'active' 
      AND (department_name = 'Business Development' OR department_name LIKE '%Calling%' OR designation LIKE '%BDA%')
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

// 2. Caller Live Velocity Metrics
$callerStats = [];
foreach ($callers as $c) {
    $cid = (int)$c['id'];
    $assigned = (int)$db->query("SELECT COUNT(*) FROM calling_leads WHERE assigned_to = {$cid}")->fetchColumn();
    $called = (int)$db->query("SELECT COUNT(*) FROM calling_leads WHERE assigned_to = {$cid} AND status != 'new'")->fetchColumn();
    $interested = (int)$db->query("SELECT COUNT(*) FROM calling_leads WHERE assigned_to = {$cid} AND status = 'interested'")->fetchColumn();
    $converted = (int)$db->query("SELECT COUNT(*) FROM calling_leads WHERE assigned_to = {$cid} AND status = 'converted'")->fetchColumn();

    $callerStats[$cid] = [
        'name' => $c['name'],
        'emp_id' => $c['emp_id'],
        'assigned' => $assigned,
        'called' => $called,
        'interested' => $interested,
        'converted' => $converted,
        'pct' => $assigned > 0 ? (int)round(($called / $assigned) * 100) : 0
    ];
}

// 3. Master Leads Roster
$allLeads = $db->query("
    SELECT l.*, u.name as executive_name, u.emp_id as executive_emp_id
    FROM calling_leads l
    LEFT JOIN users u ON l.assigned_to = u.id
    ORDER BY l.updated_at DESC, l.id DESC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$unassignedCount = (int)$db->query("SELECT COUNT(*) FROM calling_leads WHERE assigned_to IS NULL OR assigned_to = 0")->fetchColumn();
?>

<div class="space-y-6" x-data="{
    searchQuery: '',
    statusFilter: 'all',
    execFilter: 'all',
    uploadModalOpen: <?= (isset($_GET['modal']) && $_GET['modal'] === 'upload') ? 'true' : 'false' ?>
}">

    <!-- Top Command Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white p-5 sm:p-6 rounded-3xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-indigo-600 to-purple-600 text-white flex items-center justify-center font-bold shrink-0 shadow-md shadow-indigo-600/20">
                <i data-lucide="users" class="w-6 h-6"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">BDA Live Calling Command Center</h1>
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping" title="Live Sync Active"></span>
                </div>
                <p class="text-xs text-slate-500 font-medium mt-0.5">Upload lead sheet with auto-equal division and track live executive call statuses.</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap">
            <button type="button" @click="uploadModalOpen = true" class="px-4 py-2.5 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-600/25 transition flex items-center gap-2 cursor-pointer border border-indigo-400/30">
                <i data-lucide="upload-cloud" class="w-4 h-4"></i>
                <span>Upload Sheet & Divide Numbers</span>
            </button>

            <?php if ($unassignedCount > 0): ?>
                <form action="?action=allocate-leads-round-robin" method="POST" class="m-0">
                    <button type="submit" onclick="return confirm('Divide <?= $unassignedCount ?> unassigned numbers equally across all active callers?');" class="px-3.5 py-2.5 bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-300 text-xs font-bold rounded-xl transition flex items-center gap-1.5 cursor-pointer">
                        <i data-lucide="scale" class="w-4 h-4 text-amber-600"></i>
                        <span>Divide <?= $unassignedCount ?> Numbers (Equal)</span>
                    </button>
                </form>
            <?php endif; ?>

            <a href="?action=export-calling-history" class="px-3.5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md shadow-emerald-600/20 transition flex items-center gap-1.5 cursor-pointer">
                <i data-lucide="download" class="w-4 h-4"></i>
                <span>Export CSV</span>
            </a>
        </div>
    </div>

    <!-- 📊 LIVE CALLER VELOCITY & ALLOCATION PROGRESS -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <span class="text-xs font-extrabold uppercase tracking-wider text-indigo-900">Live Executive Calling Velocity</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Live Status</span>
            </div>
            <span class="text-xs text-slate-400 font-medium"><?= count($callers) ?> Active Callers</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($callerStats as $cid => $cs): ?>
                <div class="bg-slate-50/80 p-4 rounded-2xl border border-slate-200 space-y-2.5 hover:border-indigo-300 transition">
                    <div class="flex items-center justify-between">
                        <div class="font-bold text-slate-900 text-xs"><?= htmlspecialchars($cs['name']) ?></div>
                        <span class="text-[10px] font-mono font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-lg border border-indigo-100">
                            <?= $cs['called'] ?> / <?= $cs['assigned'] ?> Called
                        </span>
                    </div>

                    <!-- Progress Bar -->
                    <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden">
                        <div class="bg-gradient-to-r from-indigo-600 to-emerald-500 h-2 rounded-full transition-all duration-500" style="width: <?= $cs['pct'] ?>%"></div>
                    </div>

                    <div class="flex items-center justify-between text-[10px] font-bold text-slate-500 pt-1">
                        <span>🔥 <?= $cs['interested'] ?> Interested</span>
                        <span>🏆 <?= $cs['converted'] ?> Won</span>
                        <span class="text-indigo-600"><?= $cs['pct'] ?>% Done</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 📋 MASTER CALLING DATA SHEET (REAL-TIME LOGS) -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-100">
            <div>
                <h3 class="text-base font-extrabold text-slate-900 tracking-tight">Master Calling Roster & Live Status</h3>
                <p class="text-xs text-slate-400">All divided candidate numbers and live filled statuses.</p>
            </div>

            <!-- Filters -->
            <div class="flex items-center gap-2 flex-wrap">
                <div class="relative">
                    <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5"></i>
                    <input type="text" x-model="searchQuery" placeholder="Search candidate, phone, city..." class="bg-slate-50 border border-slate-300 rounded-xl pl-8 pr-3 py-1.5 text-xs font-semibold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-500">
                </div>

                <select x-model="execFilter" class="bg-slate-50 border border-slate-300 rounded-xl px-2.5 py-1.5 text-xs font-bold text-slate-700 focus:bg-white">
                    <option value="all">All Executives</option>
                    <?php foreach ($callers as $ex): ?>
                        <option value="<?= (int)$ex['id'] ?>"><?= htmlspecialchars($ex['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <select x-model="statusFilter" class="bg-slate-50 border border-slate-300 rounded-xl px-2.5 py-1.5 text-xs font-bold text-slate-700 focus:bg-white">
                    <option value="all">All Statuses</option>
                    <option value="new">🆕 Pending</option>
                    <option value="interested">🔥 Interested</option>
                    <option value="call_later">⏰ Follow Up</option>
                    <option value="converted">🏆 Converted</option>
                    <option value="not_interested">❌ Not Interested</option>
                </select>
            </div>
        </div>

        <?php if (empty($allLeads)): ?>
            <div class="text-center py-12 bg-slate-50/60 rounded-2xl">
                <p class="text-xs font-bold text-slate-600">No leads in the system</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Click 'Upload Sheet & Divide Numbers' to begin.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-extrabold uppercase text-slate-400 tracking-wider">
                            <th class="py-3 px-3">Candidate</th>
                            <th class="py-3 px-3">Phone</th>
                            <th class="py-3 px-3">Assigned Caller</th>
                            <th class="py-3 px-3">Live Status</th>
                            <th class="py-3 px-3">Latest Discussion Remarks</th>
                            <th class="py-3 px-3 text-right">Updated</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($allLeads as $l): ?>
                            <?php
                            $st = $l['status'] ?? 'new';
                            $statusBadge = match($st) {
                                'new' => 'bg-purple-50 text-purple-700 border-purple-200',
                                'interested' => 'bg-amber-50 text-amber-800 border-amber-200',
                                'call_later' => 'bg-blue-50 text-blue-700 border-blue-200',
                                'converted' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'not_interested' => 'bg-rose-50 text-rose-700 border-rose-200',
                                default => 'bg-slate-100 text-slate-600 border-slate-200'
                            };
                            ?>
                            <tr class="hover:bg-slate-50/80 transition" x-show="
                                (statusFilter === 'all' || statusFilter === '<?= $st ?>') &&
                                (execFilter === 'all' || execFilter === '<?= (int)$l['assigned_to'] ?>') &&
                                (!searchQuery || '<?= strtolower(addslashes($l['name'] . ' ' . $l['phone'] . ' ' . $l['city'] . ' ' . ($l['executive_name'] ?? ''))) ?>'.includes(searchQuery.toLowerCase()))
                            ">
                                <td class="py-3.5 px-3 align-middle font-bold text-slate-900">
                                    <?= htmlspecialchars($l['name']) ?>
                                    <?php if (!empty($l['course_service'])): ?>
                                        <div class="text-[10px] text-slate-400 font-normal"><?= htmlspecialchars($l['course_service']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-3 align-middle font-mono font-bold text-slate-800">
                                    <?= htmlspecialchars($l['phone']) ?>
                                </td>
                                <td class="py-3.5 px-3 align-middle">
                                    <span class="font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-md border border-indigo-100">
                                        <?= htmlspecialchars($l['executive_name'] ?: 'Unassigned') ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-3 align-middle">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase border <?= $statusBadge ?>">
                                        <?= ucfirst(str_replace('_', ' ', $st)) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-3 align-middle max-w-xs text-slate-600">
                                    <div class="text-[11px] line-clamp-2"><?= htmlspecialchars($l['notes'] ?: 'Pending call...') ?></div>
                                    <?php if (!empty($l['callback_datetime'])): ?>
                                        <div class="text-[10px] text-blue-600 font-semibold mt-0.5">
                                            ⏰ Callback: <?= date('d M, h:i A', strtotime($l['callback_datetime'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-3 align-middle text-right text-slate-400 font-mono text-[11px] whitespace-nowrap">
                                    <?= date('d M, h:i A', strtotime($l['updated_at'] ?: $l['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- 📤 UPLOAD SHEET & AUTO-DIVIDE MODAL -->
    <div x-show="uploadModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4" x-cloak>
        <div @click.away="uploadModalOpen = false" class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 text-left my-auto space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold">
                        <i data-lucide="upload-cloud" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold text-slate-900">Upload Lead Sheet</h3>
                        <p class="text-xs text-slate-400">CSV Sheet with Candidate Names & Numbers</p>
                    </div>
                </div>
                <button type="button" @click="uploadModalOpen = false" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>

            <form action="?action=upload-bda-leads" method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Select CSV File *</label>
                    <input type="file" name="lead_file" accept=".csv" required class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 text-xs font-semibold">
                    <p class="text-[10px] text-slate-400 mt-1">Columns: Name, Phone, Email, City, Course, Budget</p>
                </div>

                <!-- Auto-Divide Toggle -->
                <div class="bg-indigo-50/70 p-3 rounded-2xl border border-indigo-100 space-y-1">
                    <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-indigo-950">
                        <input type="checkbox" name="auto_divide" value="1" checked class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500">
                        <span>⚖️ Divide Numbers Equally Across Callers</span>
                    </label>
                    <p class="text-[10px] text-indigo-700 ml-6">Automatically assigns uploaded leads in equal batches to all active BDA team members.</p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="uploadModalOpen = false" class="px-4 py-2 bg-slate-100 rounded-xl text-xs font-bold text-slate-600">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white rounded-xl text-xs font-bold shadow-md shadow-indigo-600/30 transition flex items-center gap-1.5 cursor-pointer">
                        <i data-lucide="upload-cloud" class="w-4 h-4"></i>
                        <span>Upload & Distribute</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>