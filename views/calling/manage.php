<!-- views/calling/manage.php -->
<?php
$user = authUser();
$db = getDBConnection();
$today = date('Y-m-d');
$isTLOrAdmin = in_array($user['role'], ['admin', 'team_lead']);

// If variables are not pre-passed by controller, query them directly
if (!isset($callers)) {
    $callers = $db->query("
        SELECT id, name, emp_id, designation, role 
        FROM users 
        WHERE status = 'active' AND role = 'employee' AND (department_name = 'Calling / BDA Team' OR department_name = 'Calling / Sales' OR designation LIKE '%BDA%' OR designation LIKE '%Caller%' OR designation LIKE '%Telecaller%')
        ORDER BY name ASC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

if (!isset($stats)) {
    $stats = $db->query("
        SELECT 
            COUNT(*) as total_leads,
            COUNT(CASE WHEN status = 'new' THEN 1 END) as new_leads,
            COUNT(CASE WHEN status = 'interested' THEN 1 END) as interested_leads,
            COUNT(CASE WHEN status = 'call_later' THEN 1 END) as followup_leads,
            COUNT(CASE WHEN status = 'converted' THEN 1 END) as converted_leads,
            COUNT(CASE WHEN status = 'not_interested' THEN 1 END) as lost_leads
        FROM calling_leads
    ")->fetch(PDO::FETCH_ASSOC) ?: [];
}

if (!isset($todayCallingStats)) {
    $todayCallingStats = $db->query("
        SELECT 
            u.id, u.name, u.emp_id, u.designation,
            COUNT(cl.id) as today_calls,
            COUNT(CASE WHEN cl.disposition = 'converted' THEN 1 END) as today_converted,
            COUNT(CASE WHEN cl.disposition = 'interested' THEN 1 END) as today_interested,
            COUNT(CASE WHEN cl.disposition = 'call_later' THEN 1 END) as today_followup,
            (SELECT COUNT(*) FROM calling_leads WHERE assigned_to = u.id) as total_assigned_leads
        FROM users u
        LEFT JOIN call_logs cl ON cl.caller_id = u.id AND cl.call_date = '{$today}'
        WHERE u.status = 'active' AND u.role = 'employee' AND (u.department_name = 'Calling / BDA Team' OR u.department_name = 'Calling / Sales' OR u.designation LIKE '%BDA%' OR u.designation LIKE '%Caller%' OR u.designation LIKE '%Telecaller%')
        GROUP BY u.id, u.name, u.emp_id, u.designation
        ORDER BY today_calls DESC, today_converted DESC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

if (!isset($recentCallLogs)) {
    $recentCallLogs = $db->query("
        SELECT cl.*, u.name as caller_name, u.emp_id as caller_emp_id
        FROM call_logs cl
        JOIN users u ON cl.caller_id = u.id
        ORDER BY cl.id DESC
        LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
?>

<div class="space-y-6" x-data="{ uploadModalOpen: false, filterCaller: '', filterDate: '<?= date('Y-m-d') ?>' }">
    
    <!-- Header Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-purple-600 text-white flex items-center justify-center font-bold shrink-0 shadow-sm">
                <i data-lucide="phone-forwarded" class="w-5 h-5"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">BDA Telecalling CRM & Live History</h1>
                <p class="text-xs text-slate-500 mt-0.5">Upload lead numbers sheet, auto-distribute to team, track every call with caller name, and export to Excel.</p>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <!-- EXCEL EXPORT BUTTON (Only TL and Admin) -->
            <a href="?action=export-calling-history" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-sm transition flex items-center gap-1.5 cursor-pointer">
                <i data-lucide="download" class="w-4 h-4"></i> Export Call History (Excel)
            </a>

            <!-- UPLOAD SHEET BUTTON -->
            <button type="button" @click="uploadModalOpen = true" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition flex items-center gap-1.5 cursor-pointer">
                <i data-lucide="upload" class="w-4 h-4"></i> Upload & Distribute Numbers
            </button>
        </div>
    </div>

    <!-- 4 Stats Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Leads Pool</span>
                <div class="text-2xl font-extrabold text-slate-900 mt-0.5"><?= (int)($stats['total_leads'] ?? 0) ?></div>
            </div>
            <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center">
                <i data-lucide="database" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Fresh / New</span>
                <div class="text-2xl font-extrabold text-amber-600 mt-0.5"><?= (int)($stats['new_leads'] ?? 0) ?></div>
            </div>
            <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                <i data-lucide="phone-missed" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Interested / Follow-up</span>
                <div class="text-2xl font-extrabold text-blue-600 mt-0.5"><?= (int)($stats['interested_leads'] ?? 0) + (int)($stats['followup_leads'] ?? 0) ?></div>
            </div>
            <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                <i data-lucide="phone-call" class="w-4 h-4"></i>
            </div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Closed / Converted</span>
                <div class="text-2xl font-extrabold text-emerald-600 mt-0.5"><?= (int)($stats['converted_leads'] ?? 0) ?></div>
            </div>
            <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <i data-lucide="check-circle" class="w-4 h-4"></i>
            </div>
        </div>
    </div>

    <!-- Today's Live Caller Productivity Table -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-slate-900 tracking-tight flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    Today's Calling Leaderboard & Performance (<?= date('d M Y') ?>)
                </h3>
                <p class="text-xs text-slate-400">Live count of how many calls each employee has done today with their conversions.</p>
            </div>
            <span class="text-xs font-bold text-indigo-700 bg-indigo-50 px-3 py-1 rounded-xl border border-indigo-100">
                <?= count($todayCallingStats) ?> Active BDA Agents
            </span>
        </div>

        <?php if (empty($todayCallingStats)): ?>
            <div class="text-center py-8 text-slate-400 text-xs">
                No active callers found in BDA Team.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 text-slate-500 uppercase text-[10px] font-bold border-b border-slate-200">
                        <tr>
                            <th class="py-2.5 px-3">Employee / Caller</th>
                            <th class="py-2.5 px-3 text-center">Total Assigned</th>
                            <th class="py-2.5 px-3 text-center bg-indigo-50/60 text-indigo-900 font-extrabold">Calls Done Today</th>
                            <th class="py-2.5 px-3 text-center">Interested</th>
                            <th class="py-2.5 px-3 text-center">Follow-ups</th>
                            <th class="py-2.5 px-3 text-center text-emerald-700 font-bold">Conversions</th>
                            <th class="py-2.5 px-3 text-right">Today's Conversion %</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($todayCallingStats as $c): 
                            $calls = (int)$c['today_calls'];
                            $conv = (int)$c['today_converted'];
                            $cRate = ($calls > 0) ? round(($conv / $calls) * 100, 1) : 0;
                        ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3 px-3">
                                    <div class="font-bold text-slate-900"><?= htmlspecialchars($c['name']) ?></div>
                                    <div class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($c['emp_id']) ?> • <?= htmlspecialchars($c['designation'] ?: 'Caller') ?></div>
                                </td>
                                <td class="py-3 px-3 text-center font-semibold text-slate-700"><?= (int)$c['total_assigned_leads'] ?></td>
                                <td class="py-3 px-3 text-center font-extrabold text-indigo-700 bg-indigo-50/40 text-sm">
                                    <?= $calls ?>
                                </td>
                                <td class="py-3 px-3 text-center font-bold text-blue-600"><?= (int)$c['today_interested'] ?></td>
                                <td class="py-3 px-3 text-center font-bold text-amber-600"><?= (int)$c['today_followup'] ?></td>
                                <td class="py-3 px-3 text-center font-extrabold text-emerald-700"><?= $conv ?></td>
                                <td class="py-3 px-3 text-right font-extrabold text-slate-900"><?= $cRate ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Live Call History Log (Who called whom with numbers) -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h3 class="text-sm font-bold text-slate-900 tracking-tight flex items-center gap-2">
                    <i data-lucide="history" class="w-4 h-4 text-indigo-600"></i>
                    Live Call History & Number Log (Saved Every Call)
                </h3>
                <p class="text-xs text-slate-400">Complete record of every call attempt: employee name, customer name, phone number, and feedback.</p>
            </div>
            <a href="?action=export-calling-history" class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-xl text-xs font-bold transition inline-flex items-center gap-1.5">
                <i data-lucide="file-spreadsheet" class="w-3.5 h-3.5"></i> Download CSV/Excel
            </a>
        </div>

        <?php if (empty($recentCallLogs)): ?>
            <div class="text-center py-10 bg-slate-50 rounded-2xl text-slate-400 text-xs">
                No call history logs recorded yet. Start calling from the Live Calling Queue to see real-time history logs.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto max-h-96 overflow-y-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 text-slate-500 uppercase text-[10px] font-bold sticky top-0 border-b border-slate-200 shadow-2xs">
                        <tr>
                            <th class="py-2.5 px-3">Call Time</th>
                            <th class="py-2.5 px-3">Caller (Employee)</th>
                            <th class="py-2.5 px-3">Customer / Lead</th>
                            <th class="py-2.5 px-3">Phone Number</th>
                            <th class="py-2.5 px-3 text-center">Disposition</th>
                            <th class="py-2.5 px-3">Notes / Feedback</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($recentCallLogs as $log): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-2.5 px-3 whitespace-nowrap text-slate-500 font-mono text-[11px]">
                                    <?= date('d M, h:i A', strtotime($log['call_time'])) ?>
                                </td>
                                <td class="py-2.5 px-3 whitespace-nowrap">
                                    <span class="font-bold text-slate-900"><?= htmlspecialchars($log['caller_name']) ?></span>
                                    <span class="text-[10px] text-slate-400 font-mono block"><?= htmlspecialchars($log['caller_emp_id']) ?></span>
                                </td>
                                <td class="py-2.5 px-3 font-semibold text-slate-800 whitespace-nowrap">
                                    <?= htmlspecialchars($log['customer_name'] ?: 'Prospect') ?>
                                </td>
                                <td class="py-2.5 px-3 font-mono font-bold text-indigo-700 whitespace-nowrap">
                                    <?= htmlspecialchars($log['phone']) ?>
                                </td>
                                <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                    <?php 
                                    $disp = $log['disposition'];
                                    if ($disp === 'converted'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800">✓ Converted</span>
                                    <?php elseif ($disp === 'interested'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800">Interested</span>
                                    <?php elseif ($disp === 'call_later'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">Follow-up</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600"><?= ucfirst(str_replace('_', ' ', $disp)) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2.5 px-3 text-slate-600 max-w-xs truncate">
                                    <?= htmlspecialchars($log['notes'] ?: '-') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Upload Excel/CSV Modal -->
    <div x-show="uploadModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" @click="uploadModalOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="font-bold text-sm text-slate-900 flex items-center gap-1.5">
                        <i data-lucide="upload-cloud" class="w-4 h-4 text-indigo-600"></i>
                        Upload & Auto-Distribute Lead Numbers Sheet
                    </h3>
                    <button type="button" @click="uploadModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>

                <form action="?action=upload-calling-leads" method="POST" enctype="multipart/form-data" class="space-y-4 pt-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Select Excel / CSV File with Numbers *</label>
                        <input type="file" name="lead_file" accept=".csv,.txt" required class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer border border-slate-300 rounded-xl p-1 bg-slate-50">
                        <p class="text-[10px] text-slate-400 mt-1">Columns format: <code>Column 1: Customer Name, Column 2: Phone Number, Column 3: City (optional)</code></p>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1.5">Distribute Leads To Callers (Check All Applicable):</label>
                        <div class="max-h-40 overflow-y-auto space-y-1.5 bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                            <?php foreach ($callers as $clr): ?>
                                <label class="flex items-center justify-between p-1.5 hover:bg-white rounded-lg cursor-pointer text-xs transition">
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox" name="callers[]" value="<?= $clr['id'] ?>" checked class="rounded text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                                        <span class="font-bold text-slate-800"><?= htmlspecialchars($clr['name']) ?></span>
                                    </div>
                                    <span class="text-[10px] font-mono text-slate-400"><?= htmlspecialchars($clr['emp_id']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="p-3 bg-indigo-50/80 border border-indigo-100 rounded-xl text-xs text-indigo-900 leading-relaxed">
                        💡 <strong>Auto-Round-Robin:</strong> Uploaded phone numbers will be divided equally and instantly assigned to the checked callers.
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" @click="uploadModalOpen = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold transition cursor-pointer">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-md shadow-indigo-600/20 transition cursor-pointer">Upload & Allocate Leads</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>