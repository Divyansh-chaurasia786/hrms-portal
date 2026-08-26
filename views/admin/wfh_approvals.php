<?php
// views/admin/wfh_approvals.php
$title = "WFH Approvals & HR Schedule - Ecofone HRMS";
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
$authUser = authUser();
?>
<main class="flex-1 min-w-0 overflow-y-auto bg-slate-900 text-slate-100 p-4 sm:p-8">
    <div class="max-w-6xl mx-auto space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center">
                        <i data-lucide="calendar-clock" class="w-5 h-5"></i>
                    </div>
                    WFH Approvals & Schedule Management
                </h1>
                <p class="text-xs text-slate-400 mt-1">Review team WFH requests and configure HR date-bounded WFH periods.</p>
            </div>
        </div>

        <?php $flash = getFlash(); if ($flash): ?>
            <div class="p-4 rounded-2xl text-xs font-medium <?= $flash['type'] === 'error' ? 'bg-rose-500/10 text-rose-300 border border-rose-500/30' : 'bg-emerald-500/10 text-emerald-300 border border-emerald-500/30' ?>">
                <?= $flash['message'] ?>
            </div>
        <?php endif; ?>

        <?php if ($authUser['role'] === 'admin'): ?>
            <!-- Head HR WFH Date Range Box -->
            <div class="bg-gradient-to-r from-indigo-950/80 to-slate-900 border border-indigo-500/30 rounded-3xl p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <i data-lucide="laptop" class="w-5 h-5 text-indigo-400"></i> Head HR Work From Home Schedule
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">Set an active date window for HR remote work. Automatically reverts to In-Office mode once expired.</p>
                    </div>
                    <?php if (!empty($authUser['hr_wfh_start_date'])): ?>
                        <span class="px-3 py-1 rounded-full bg-emerald-500/20 text-emerald-300 text-xs font-bold border border-emerald-500/30">
                            Active WFH: <?= formatDate($authUser['hr_wfh_start_date']) ?> to <?= formatDate($authUser['hr_wfh_end_date']) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <form action="?action=set-hr-wfh" method="POST" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 mb-1">From Date</label>
                        <input type="date" name="start_date" value="<?= $authUser['hr_wfh_start_date'] ?? '' ?>" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 mb-1">To Date</label>
                        <input type="date" name="end_date" value="<?= $authUser['hr_wfh_end_date'] ?? '' ?>" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white">
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs transition">Save Schedule</button>
                        <?php if (!empty($authUser['hr_wfh_start_date'])): ?>
                            <button type="submit" name="start_date" value="" class="px-3 py-2.5 rounded-xl bg-slate-800 text-slate-400 hover:text-white text-xs font-semibold">Clear</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- WFH Applications Table -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
            <h2 class="text-sm font-bold text-white uppercase tracking-wider text-slate-400">Team WFH Applications</h2>
            <?php if (empty($requests)): ?>
                <div class="text-center py-10 text-slate-500 text-xs">No pending or previous WFH applications found.</div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-800 text-slate-400">
                                <th class="pb-3">Employee</th>
                                <th class="pb-3">WFH Date</th>
                                <th class="pb-3">Reason</th>
                                <th class="pb-3">Status</th>
                                <th class="pb-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <?php foreach ($requests as $r): ?>
                                <tr>
                                    <td class="py-3.5">
                                        <div class="font-bold text-white"><?= htmlspecialchars($r['user_name'] ?? 'User') ?></div>
                                        <div class="text-[11px] text-slate-500"><?= htmlspecialchars($r['designation'] ?? '') ?></div>
                                    </td>
                                    <td class="py-3.5 font-bold text-indigo-300"><?= formatDate($r['wfh_date']) ?></td>
                                    <td class="py-3.5 max-w-xs text-slate-300"><?= htmlspecialchars($r['reason']) ?></td>
                                    <td class="py-3.5">
                                        <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full <?= $r['status'] === 'approved' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : ($r['status'] === 'rejected' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20') ?>">
                                            <?= strtoupper($r['status']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 text-right">
                                        <?php if ($r['status'] === 'pending'): ?>
                                            <div class="inline-flex items-center gap-2">
                                                <form action="?action=review-wfh" method="POST">
                                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                                    <input type="hidden" name="status" value="approved">
                                                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-[11px]">Approve</button>
                                                </form>
                                                <form action="?action=review-wfh" method="POST">
                                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                                    <input type="hidden" name="status" value="rejected">
                                                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-500 text-white font-semibold text-[11px]">Reject</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-[11px] text-slate-500">Processed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php require __DIR__ . '/../layouts/footer.php'; ?>