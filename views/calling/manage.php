<!-- views/calling/manage.php -->
<?php
$user = authUser() ?: ['id' => 1, 'role' => 'admin', 'name' => 'Admin'];
$db = getDBConnection();
$today = date('Y-m-d');
$isTLOrAdmin = in_array($user['role'] ?? '', ['admin', 'team_lead']);

// 1. Fetch active BDA Callers
$callers = $db->query("
    SELECT id, name, emp_id, designation, role 
    FROM users 
    WHERE status = 'active' AND role = 'employee' 
      AND (department_name = 'Business Development' OR department_name LIKE '%Calling%' OR designation LIKE '%BDA%')
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

// 2. Comprehensive Pipeline Metrics
$stats = $db->query("
    SELECT 
        COUNT(*) as total_leads,
        COUNT(CASE WHEN status = 'new' THEN 1 END) as new_leads,
        COUNT(CASE WHEN status = 'interested' THEN 1 END) as interested_leads,
        COUNT(CASE WHEN status = 'call_later' THEN 1 END) as followup_leads,
        COUNT(CASE WHEN status = 'converted' THEN 1 END) as converted_leads,
        COUNT(CASE WHEN status = 'not_interested' THEN 1 END) as lost_leads,
        COUNT(CASE WHEN assigned_to IS NULL THEN 1 END) as unassigned_leads,
        COALESCE(SUM(deal_value), 0) as total_pipeline_revenue
    FROM calling_leads
")->fetch(PDO::FETCH_ASSOC) ?: [];

// 3. Today's BDA Caller Performance Leaderboard
$todayCallingStats = $db->query("
    SELECT 
        u.id, u.name, u.emp_id, u.designation,
        COUNT(cl.id) as today_calls,
        COALESCE(SUM(cl.call_duration_seconds), 0) as total_talk_time,
        COUNT(CASE WHEN cl.disposition = 'converted' THEN 1 END) as today_converted,
        COUNT(CASE WHEN cl.disposition = 'interested' THEN 1 END) as today_interested,
        COUNT(CASE WHEN cl.disposition = 'call_later' THEN 1 END) as today_followup,
        (SELECT COUNT(*) FROM calling_leads WHERE assigned_to = u.id) as total_assigned_leads
    FROM users u
    LEFT JOIN call_logs cl ON cl.caller_id = u.id AND cl.call_date = '{$today}'
    WHERE u.status = 'active' AND u.role = 'employee' 
      AND (u.department_name = 'Business Development' OR u.department_name LIKE '%Calling%' OR u.designation LIKE '%BDA%')
    GROUP BY u.id, u.name, u.emp_id, u.designation
    ORDER BY today_converted DESC, today_calls DESC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

// 4. Recent Call Logs Audit
$recentCallLogs = $db->query("
    SELECT cl.*, u.name as caller_name, u.emp_id as caller_emp_id, l.course_service, l.city
    FROM call_logs cl
    JOIN users u ON cl.caller_id = u.id
    LEFT JOIN calling_leads l ON cl.lead_id = l.id
    ORDER BY cl.id DESC
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<div class="space-y-6" x-data="{ uploadModalOpen: <?= (isset($_GET['modal']) && $_GET['modal'] === 'upload') ? 'true' : 'false' ?>, allocateModalOpen: false, filterCaller: '', filterDate: '<?= date('Y-m-d') ?>' }">
    
    <!-- 🌟 TOP EXECUTIVE COMMAND BANNER -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 text-white p-6 sm:p-7 border border-indigo-500/20 shadow-xl">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-5">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-indigo-500/20 text-indigo-300 border border-indigo-400/30 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-400"></span> BDA Lead CRM & Allocation
                    </span>
                    <span class="text-xs text-slate-400">•</span>
                    <span class="text-xs font-semibold text-slate-300">Sales Intelligence & Telecalling Hub</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight mt-1">
                    BDA Operations Command Center
                </h1>
                <p class="text-xs text-slate-300 mt-1">
                    Bulk lead ingestion, intelligent Round-Robin auto-distribution, caller velocity monitoring, and revenue conversions.
                </p>
            </div>

            <!-- Fast Action Group -->
            <div class="flex items-center gap-2.5 flex-wrap">
                <button type="button" @click="uploadModalOpen = true" class="px-4 py-2.5 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-indigo-600/30 transition flex items-center gap-2 cursor-pointer border border-indigo-400/30">
                    <i data-lucide="upload-cloud" class="w-4 h-4"></i>
                    <span>Ingest Lead Sheet (.csv / .xlsx)</span>
                </button>

                <form action="?action=allocate-leads-round-robin" method="POST" class="m-0">
                    <button type="submit" onclick="return confirm('Distribute all unassigned leads equally across active BDA callers?');" class="px-4 py-2.5 bg-white/10 hover:bg-white/15 text-white text-xs font-bold rounded-xl backdrop-blur-md border border-white/15 transition flex items-center gap-1.5 cursor-pointer">
                        <i data-lucide="scale" class="w-4 h-4 text-emerald-300"></i>
                        <span>Auto Allocate (Round-Robin)</span>
                    </button>
                </form>

                <a href="?action=export-calling-history" class="px-3.5 py-2.5 bg-emerald-600/90 hover:bg-emerald-600 text-white text-xs font-bold rounded-xl shadow-sm transition flex items-center gap-1.5 cursor-pointer border border-emerald-400/30">
                    <i data-lucide="download" class="w-4 h-4 text-emerald-200"></i>
                    <span>Export Excel</span>
                </a>
            </div>
        </div>

        <!-- 4 Key Pipeline Metric Cards -->
        <div class="mt-6 pt-6 border-t border-white/10 grid grid-cols-2 sm:grid-cols-4 gap-3.5">
            <div class="bg-white/5 backdrop-blur-md p-4 rounded-2xl border border-white/10">
                <span class="text-[11px] font-bold text-slate-300 uppercase tracking-wider block">Total Master Leads</span>
                <div class="text-2xl font-black text-white mt-1"><?= number_format((int)$stats['total_leads']) ?></div>
                <span class="text-[10px] text-amber-300 font-semibold mt-0.5 block"><?= (int)$stats['unassigned_leads'] ?> unassigned in pool</span>
            </div>

            <div class="bg-white/5 backdrop-blur-md p-4 rounded-2xl border border-white/10">
                <span class="text-[11px] font-bold text-slate-300 uppercase tracking-wider block">Active / In Progress</span>
                <div class="text-2xl font-black text-blue-300 mt-1"><?= number_format((int)$stats['interested_leads'] + (int)$stats['followup_leads']) ?></div>
                <span class="text-[10px] text-blue-200 font-semibold mt-0.5 block"><?= (int)$stats['interested_leads'] ?> hot prospects</span>
            </div>

            <div class="bg-white/5 backdrop-blur-md p-4 rounded-2xl border border-white/10">
                <span class="text-[11px] font-bold text-slate-300 uppercase tracking-wider block">Total Deals Won 🏆</span>
                <div class="text-2xl font-black text-emerald-300 mt-1"><?= number_format((int)$stats['converted_leads']) ?></div>
                <span class="text-[10px] text-emerald-200 font-semibold mt-0.5 block"><?= $stats['total_leads'] > 0 ? round(($stats['converted_leads'] / $stats['total_leads']) * 100, 1) : 0 ?>% conversion rate</span>
            </div>

            <div class="bg-white/5 backdrop-blur-md p-4 rounded-2xl border border-white/10">
                <span class="text-[11px] font-bold text-slate-300 uppercase tracking-wider block">Won Deal Revenue</span>
                <div class="text-2xl font-black text-emerald-400 mt-1">₹<?= number_format((float)$stats['total_pipeline_revenue']) ?></div>
                <span class="text-[10px] text-slate-400 font-semibold mt-0.5 block">Pipeline value generated</span>
            </div>
        </div>
    </div>

    <!-- 🏆 LIVE BDA CALLER PERFORMANCE LEADERBOARD -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
                    <i data-lucide="trophy" class="w-4 h-4"></i>
                </div>
                <div>
                    <h3 class="font-bold text-slate-900 text-sm">BDA Caller Daily Velocity & Conversion Leaderboard</h3>
                    <p class="text-[11px] text-slate-400">Live call counts, talk time, and deal closures for today (<?= date('d M Y') ?>)</p>
                </div>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span> Live Tracking Active
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px]">
                    <tr>
                        <th class="p-3.5">Rank & Executive</th>
                        <th class="p-3.5 text-center">Assigned Leads</th>
                        <th class="p-3.5 text-center">Calls Made Today</th>
                        <th class="p-3.5 text-center">Total Talk Time</th>
                        <th class="p-3.5 text-center">Interested Leads</th>
                        <th class="p-3.5 text-center">Won Deals 🏆</th>
                        <th class="p-3.5 text-right">Conversion Ratio</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($todayCallingStats)): ?>
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-400">No active BDA telecalling executives found in directory.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($todayCallingStats as $idx => $caller): ?>
                            <?php
                            $talkMins = round($caller['total_talk_time'] / 60, 1);
                            $convRate = $caller['today_calls'] > 0 ? round(($caller['today_converted'] / $caller['today_calls']) * 100, 1) : 0;
                            ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="p-3.5 flex items-center gap-3">
                                    <span class="w-6 h-6 rounded-full font-black text-xs flex items-center justify-center <?= $idx === 0 ? 'bg-amber-400 text-slate-950 shadow-xs' : ($idx === 1 ? 'bg-slate-200 text-slate-800' : 'bg-slate-100 text-slate-600') ?>">
                                        <?= $idx + 1 ?>
                                    </span>
                                    <div>
                                        <div class="font-bold text-slate-900 text-xs"><?= htmlspecialchars($caller['name']) ?></div>
                                        <div class="text-[10px] text-slate-400"><?= htmlspecialchars($caller['emp_id'] ?? '') ?> • <?= htmlspecialchars($caller['designation']) ?></div>
                                    </div>
                                </td>
                                <td class="p-3.5 text-center font-bold text-slate-700">
                                    <?= (int)$caller['total_assigned_leads'] ?>
                                </td>
                                <td class="p-3.5 text-center">
                                    <span class="px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 font-extrabold text-xs border border-indigo-100">
                                        <?= (int)$caller['today_calls'] ?> calls
                                    </span>
                                </td>
                                <td class="p-3.5 text-center font-mono font-semibold text-slate-600">
                                    <?= $talkMins ?> mins
                                </td>
                                <td class="p-3.5 text-center">
                                    <span class="font-bold text-amber-600"><?= (int)$caller['today_interested'] ?></span>
                                </td>
                                <td class="p-3.5 text-center">
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        <?= (int)$caller['today_converted'] ?> Won
                                    </span>
                                </td>
                                <td class="p-3.5 text-right font-black text-slate-900">
                                    <?= $convRate ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 📜 CALL LOGS & INTERACTION AUDIT -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                    <i data-lucide="history" class="w-4 h-4"></i>
                </div>
                <div>
                    <h3 class="font-bold text-slate-900 text-sm">Live Call History & Disposition Audit</h3>
                    <p class="text-[11px] text-slate-400">Detailed logs of recent client interactions</p>
                </div>
            </div>
            <a href="?action=export-calling-history" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                <i data-lucide="download" class="w-3.5 h-3.5"></i> Export All to Excel
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px]">
                    <tr>
                        <th class="p-3.5">Time</th>
                        <th class="p-3.5">Caller / BDA</th>
                        <th class="p-3.5">Customer & City</th>
                        <th class="p-3.5">Phone Number</th>
                        <th class="p-3.5">Outcome / Disposition</th>
                        <th class="p-3.5">Duration</th>
                        <th class="p-3.5">Call Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($recentCallLogs)): ?>
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-400">No telecalling interactions logged yet today.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentCallLogs as $log): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="p-3.5 text-slate-500 font-mono text-[11px]">
                                    <?= date('h:i A', strtotime($log['call_time'])) ?>
                                </td>
                                <td class="p-3.5 font-bold text-slate-900">
                                    <?= htmlspecialchars($log['caller_name']) ?>
                                </td>
                                <td class="p-3.5 font-medium text-slate-800">
                                    <div><?= htmlspecialchars($log['customer_name']) ?></div>
                                    <div class="text-[10px] text-slate-400"><?= htmlspecialchars($log['city'] ?: 'General') ?></div>
                                </td>
                                <td class="p-3.5 font-mono text-slate-700">
                                    <?= htmlspecialchars($log['phone']) ?>
                                </td>
                                <td class="p-3.5">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase <?= $log['disposition'] === 'converted' ? 'bg-emerald-100 text-emerald-800' : ($log['disposition'] === 'interested' ? 'bg-blue-100 text-blue-800' : ($log['disposition'] === 'call_later' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700')) ?>">
                                        <?= str_replace('_', ' ', strtoupper($log['disposition'])) ?>
                                    </span>
                                </td>
                                <td class="p-3.5 font-mono text-slate-600 text-center">
                                    <?= gmdate('i:s', (int)$log['call_duration_seconds']) ?>
                                </td>
                                <td class="p-3.5 text-slate-600 max-w-xs truncate text-[11px]">
                                    <?= htmlspecialchars($log['notes'] ?: '-') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 📤 BULK LEAD INGESTION MODAL -->
    <div x-show="uploadModalOpen" x-cloak class="fixed inset-0 z-50 bg-slate-950/75 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="uploadModalOpen = false" class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold">
                        <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-sm text-slate-900">Ingest & Auto-Allocate Lead Sheet</h3>
                        <p class="text-[11px] text-slate-400">Supports .csv and .xlsx files (e.g. from D:\ drive)</p>
                    </div>
                </div>
                <button type="button" @click="uploadModalOpen = false" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>

            <form action="?action=upload-bda-leads" method="POST" enctype="multipart/form-data" class="space-y-4 pt-1" x-data="{ isSubmitting: false }" @submit="isSubmitting = true">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Campaign Title *</label>
                    <input type="text" name="campaign_title" required placeholder="e.g. Q3 Meta Ads Webinar Leads" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Upload Lead File (.csv / .xlsx)</label>
                    <input type="file" name="lead_file" required accept=".csv, .xlsx, .xls" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer">
                </div>

                <!-- Auto Allocation Options -->
                <div class="bg-indigo-50/70 p-3.5 rounded-2xl border border-indigo-100 space-y-2">
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="auto_allocate" value="1" id="auto_alloc" checked class="rounded text-indigo-600 focus:ring-indigo-500">
                        <label for="auto_alloc" class="text-xs font-bold text-indigo-950 cursor-pointer">Auto-Distribute Equally (Round-Robin)</label>
                    </div>
                    <p class="text-[10px] text-indigo-800/80 leading-relaxed">
                        Leads will be distributed equally across all <?= count($callers) ?> active BDA callers.
                    </p>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="uploadModalOpen = false" :disabled="isSubmitting" class="px-4 py-2 bg-slate-100 rounded-xl text-xs font-bold text-slate-600">Cancel</button>
                    <button type="submit" :disabled="isSubmitting" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-md shadow-indigo-600/20 transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50">
                        <span x-show="!isSubmitting" class="flex items-center gap-1.5"><i data-lucide="upload" class="w-3.5 h-3.5"></i> Ingest & Distribute</span>
                        <span x-show="isSubmitting" class="flex items-center gap-1.5" style="display: none;"><i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i> Ingesting...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>