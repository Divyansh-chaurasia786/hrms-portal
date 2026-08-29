<?php
// views/employee/wfh_request.php
$user = authUser();
$db = getDBConnection();

if (!isset($requests)) {
    $requests = $db->query("SELECT * FROM wfh_requests WHERE user_id = {$user['id']} ORDER BY COALESCE(wfh_date, request_date) DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
$minAllowed = date('Y-m-d', strtotime('+2 days'));
?>

<div class="space-y-6" x-data="{ applyModalOpen: false }">
    <!-- Top Header Banner -->
    <div class="bg-white rounded-2xl p-5 sm:p-6 border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-extrabold shadow-sm border border-indigo-100">
                <i data-lucide="home" class="w-6 h-6"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Work From Home (WFH) Requests</h1>
                <p class="text-xs text-slate-500 mt-0.5">Plan and submit work from home requests at least 2 days in advance for HR & TL approval.</p>
            </div>
        </div>

        <button type="button" @click="applyModalOpen = true" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition flex items-center gap-2 cursor-pointer shrink-0 self-start sm:self-auto">
            <i data-lucide="plus-circle" class="w-4 h-4"></i> Apply for WFH
        </button>
    </div>

    <!-- Policy Guideline Alert -->
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50/50 border border-blue-200/80 rounded-2xl p-4 sm:p-5 flex items-start gap-3.5">
        <div class="w-8 h-8 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold shrink-0 mt-0.5">
            <i data-lucide="info" class="w-4 h-4"></i>
        </div>
        <div class="text-xs text-slate-700 leading-relaxed space-y-1">
            <p class="font-bold text-slate-900">Official Company WFH Policy Guidelines:</p>
            <ul class="list-disc list-inside space-y-0.5 text-slate-600 pl-1">
                <li>WFH must be requested at least <strong>2 days in advance</strong> (Earliest selectable date: <strong><?= formatDate($minAllowed) ?></strong>).</li>
                <li>Same-day emergency WFH is strictly restricted; please apply for <strong>Leave</strong> instead.</li>
                <li>Ensure all your daily tasks, targets, and communication handovers are coordinated with your Team Lead.</li>
            </ul>
        </div>
    </div>

    <!-- Requests History Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                <i data-lucide="history" class="w-4 h-4 text-slate-500"></i>
                My WFH Request History (<?= count($requests) ?>)
            </h2>
        </div>

        <?php if (empty($requests)): ?>
            <div class="p-12 text-center">
                <div class="w-14 h-14 rounded-2xl bg-slate-100 text-slate-400 mx-auto flex items-center justify-center mb-3">
                    <i data-lucide="home" class="w-7 h-7"></i>
                </div>
                <h3 class="text-sm font-bold text-slate-800">No WFH Requests Submitted</h3>
                <p class="text-xs text-slate-500 mt-1">You haven't requested any Work From Home days yet.</p>
                <button type="button" @click="applyModalOpen = true" class="mt-4 px-4 py-2 bg-indigo-50 text-indigo-600 hover:bg-indigo-100 text-xs font-bold rounded-xl transition inline-flex items-center gap-1.5">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Apply for WFH Now
                </button>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-600 uppercase font-extrabold text-[10px] tracking-wider">
                            <th class="py-3 px-4">Requested WFH Date</th>
                            <th class="py-3 px-4">Reason & Deliverables</th>
                            <th class="py-3 px-4">Applied On</th>
                            <th class="py-3 px-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                        <?php foreach ($requests as $r): ?>
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <div class="font-bold text-slate-900"><?= formatDate($r['COALESCE(wfh_date, request_date)']) ?></div>
                                    <div class="text-[10px] text-slate-400 font-mono"><?= date('l', strtotime($r['COALESCE(wfh_date, request_date)'])) ?></div>
                                </td>
                                <td class="py-3.5 px-4 max-w-md">
                                    <div class="text-xs text-slate-800 line-clamp-2"><?= htmlspecialchars($r['reason']) ?></div>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap text-slate-500 text-[11px]">
                                    <?= date('d M Y, h:i A', strtotime($r['applied_at'])) ?>
                                </td>
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <?php if ($r['status'] === 'approved'): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <i data-lucide="check-circle" class="w-3 h-3 text-emerald-600"></i> Approved
                                        </span>
                                    <?php elseif ($r['status'] === 'rejected'): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                            <i data-lucide="x-circle" class="w-3 h-3 text-rose-600"></i> Rejected
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                            <i data-lucide="clock" class="w-3 h-3 text-amber-600"></i> Pending Review
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Apply WFH Modal -->
    <div x-show="applyModalOpen" x-transition.opacity class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4" x-cloak>
        <div @click.outside="applyModalOpen = false" class="bg-white rounded-2xl p-6 max-w-md w-full shadow-2xl border border-slate-200 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                        <i data-lucide="home" class="w-4 h-4"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-900">Apply for Work From Home</h3>
                </div>
                <button type="button" @click="applyModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form action="?action=apply-wfh" method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Select WFH Date (Min 2 Days in Advance) *</label>
                    <input type="date" name="COALESCE(wfh_date, request_date)" min="<?= $minAllowed ?>" value="<?= $minAllowed ?>" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 focus:ring-2 focus:ring-indigo-500">
                    <p class="text-[10px] text-slate-400 mt-1">Earliest selectable date: <?= formatDate($minAllowed) ?></p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Reason & Planned Deliverables *</label>
                    <textarea name="reason" rows="3" required placeholder="Describe your planned tasks and deliverables for this WFH day..." class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button type="button" @click="applyModalOpen = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm transition">
                        Submit WFH Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>