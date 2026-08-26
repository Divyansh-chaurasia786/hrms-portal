<!-- views/calling/manage.php -->
<?php
$user = authUser();
$db = getDBConnection();

// Fetch Calling Staff
$callers = $db->query("
    SELECT id, name, emp_id, designation
    FROM users 
    WHERE department_name = 'Calling / Sales' AND status = 'active'
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Fetch Leads & Summary Stats (Matching 'status' column in calling_leads)
$stats = $db->query("
    SELECT 
        COUNT(*) as total_leads,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_leads,
        COUNT(CASE WHEN status = 'interested' THEN 1 END) as interested_leads,
        COUNT(CASE WHEN status = 'converted' THEN 1 END) as converted_leads,
        COUNT(CASE WHEN status = 'not_interested' THEN 1 END) as lost_leads
    FROM calling_leads
")->fetch(PDO::FETCH_ASSOC) ?: ['total_leads' => 0, 'pending_leads' => 0, 'interested_leads' => 0, 'converted_leads' => 0, 'lost_leads' => 0];

// Fetch Caller Performance
$leaderboard = $db->query("
    SELECT u.name, u.emp_id,
           COUNT(l.id) as total_assigned,
           COUNT(CASE WHEN l.status != 'pending' THEN 1 END) as calls_made,
           COUNT(CASE WHEN l.status = 'converted' THEN 1 END) as conversions
    FROM users u
    LEFT JOIN calling_leads l ON l.assigned_to = u.id
    WHERE u.department_name = 'Calling / Sales' AND u.status = 'active'
    GROUP BY u.id, u.name, u.emp_id
    ORDER BY conversions DESC, calls_made DESC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<div class="space-y-6" x-data="{ uploadModalOpen: false }">
    <!-- Header Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="phone-forwarded" class="w-5 h-5"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Telecalling Lead CRM & Management</h1>
                <p class="text-xs text-slate-500 mt-0.5">Upload lead sheets, auto-distribute equally across callers, and monitor live conversions.</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" @click="uploadModalOpen = true" class="px-4 py-2 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white text-xs font-bold rounded-xl shadow-sm transition flex items-center gap-1.5 cursor-pointer">
                <i data-lucide="upload" class="w-4 h-4"></i> Upload & Distribute Leads
            </button>
        </div>
    </div>

    <!-- 4 Stats Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Leads Pool</span>
            <div class="text-2xl font-extrabold text-slate-900 mt-0.5"><?= (int)($stats['total_leads'] ?? 0) ?></div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Pending Calls</span>
            <div class="text-2xl font-extrabold text-amber-600 mt-0.5"><?= (int)($stats['pending_leads'] ?? 0) ?></div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Interested Prospects</span>
            <div class="text-2xl font-extrabold text-blue-600 mt-0.5"><?= (int)($stats['interested_leads'] ?? 0) ?></div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Closed / Converted</span>
            <div class="text-2xl font-extrabold text-emerald-600 mt-0.5"><?= (int)($stats['converted_leads'] ?? 0) ?></div>
        </div>
    </div>

    <!-- Leaderboard Table -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-4">
        <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center justify-between">
            <span>Caller Performance Leaderboard</span>
            <span class="text-[10px] text-slate-400"><?= count($callers) ?> Active Calling Agents</span>
        </h3>

        <?php if (empty($leaderboard)): ?>
            <div class="text-center py-8 text-slate-400 text-xs">
                No active callers found in 'Calling / Sales' department.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 text-slate-400 uppercase text-[10px] font-bold border-b border-slate-200">
                        <tr>
                            <th class="py-2.5 px-3">Caller Name</th>
                            <th class="py-2.5 px-3">Assigned Leads</th>
                            <th class="py-2.5 px-3">Calls Made</th>
                            <th class="py-2.5 px-3">Sales Converted</th>
                            <th class="py-2.5 px-3 text-right">Conversion Rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($leaderboard as $lb): 
                            $tot = (int)($lb['total_assigned'] ?? 0);
                            $conv = (int)($lb['conversions'] ?? 0);
                            $rate = ($tot > 0) ? round(($conv / $tot) * 100, 1) : 0;
                        ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3 px-3">
                                    <div class="font-bold text-slate-900"><?= htmlspecialchars($lb['name'] ?? 'Agent') ?></div>
                                    <div class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($lb['emp_id'] ?? '') ?></div>
                                </td>
                                <td class="py-3 px-3 font-semibold text-slate-700"><?= $tot ?></td>
                                <td class="py-3 px-3 font-semibold text-indigo-700"><?= (int)($lb['calls_made'] ?? 0) ?></td>
                                <td class="py-3 px-3 font-bold text-emerald-700"><?= $conv ?></td>
                                <td class="py-3 px-3 text-right font-bold text-slate-900"><?= $rate ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Upload CSV Modal -->
    <div x-show="uploadModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" @click="uploadModalOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="font-bold text-sm text-slate-900">Upload & Distribute Calling Sheet</h3>
                    <button type="button" @click="uploadModalOpen = false" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-4 h-4"></i></button>
                </div>
                <form action="?action=upload-calling-leads" method="POST" enctype="multipart/form-data" class="space-y-4 pt-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Campaign / Sheet Name *</label>
                        <input type="text" name="campaign_name" required placeholder="e.g. August Telemarketing Leads" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Upload CSV File *</label>
                        <input type="file" name="lead_file" accept=".csv" required class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    </div>
                    <div class="p-3 bg-indigo-50/60 border border-indigo-100 rounded-xl text-[11px] text-indigo-900">
                        ℹ️ <strong>Auto-Distribution:</strong> Leads in the sheet will be divided equally and assigned immediately across all <?= count($callers) ?> active callers in the Calling Department.
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="uploadModalOpen = false" class="px-4 py-2 bg-slate-100 rounded-xl text-xs font-bold text-slate-600">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold shadow-sm">Upload & Distribute</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>