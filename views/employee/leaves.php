<!-- views/employee/leaves.php -->
<?php
$user = authUser() ?: [];
$userId = (int)($user['id'] ?? 0);
$db = getDBConnection();

// Summary metrics (Actual Counts without Quota)
$totalApprovedDays = (float)$db->query("SELECT COALESCE(SUM(total_days), 0) FROM leave_applications WHERE user_id = $userId AND status = 'approved'")->fetchColumn();
$pendingCount = (int)$db->query("SELECT COUNT(*) FROM leave_applications WHERE user_id = $userId AND status IN ('pending_tl_review', 'pending_hr_approval')")->fetchColumn();
$totalSubmissions = (int)$db->query("SELECT COUNT(*) FROM leave_applications WHERE user_id = $userId")->fetchColumn();

// History with TL name and custom leave type support
$historyStmt = $db->prepare("
    SELECT la.*, COALESCE(la.custom_leave_type, 'Leave Application') as leave_type_name, tl.name as tl_name, hr.name as hr_name
    FROM leave_applications la
    LEFT JOIN users u ON la.user_id = u.id
    LEFT JOIN users tl ON u.reporting_tl_id = tl.id
    LEFT JOIN users hr ON la.hr_action_by = hr.id
    WHERE la.user_id = ?
    ORDER BY la.created_at DESC
");
$historyStmt->execute([$userId]);
$myLeaves = $historyStmt->fetchAll();
?>

<div class="space-y-6" x-data="{ applyModalOpen: false }">
    <!-- Clean Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-xl font-bold text-slate-900 tracking-tight">My Leave Applications</h1>
            <p class="text-xs text-slate-500 mt-0.5">Apply for time-off, track TL review recommendations, and check final HR approval status.</p>
        </div>
        <button @click="applyModalOpen = true" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition cursor-pointer">
            <i data-lucide="plus-circle" class="w-4 h-4"></i> Apply for Leave
        </button>
    </div>

    <!-- 3 Clean Actual Count KPI Cards (No Quotas) -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold shrink-0">
                    <i data-lucide="calendar-check" class="w-5 h-5"></i>
                </div>
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Leaves Taken</span>
                    <div class="flex items-baseline gap-1 mt-0.5">
                        <span class="text-2xl font-extrabold text-slate-900"><?= $totalApprovedDays ?></span>
                        <span class="text-xs text-slate-400">days approved</span>
                    </div>
                </div>
            </div>
            <span class="px-2 py-1 rounded-lg text-[10px] font-bold font-mono bg-indigo-50 text-indigo-700">
                Approved
            </span>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold shrink-0">
                    <i data-lucide="clock" class="w-5 h-5"></i>
                </div>
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Pending In Review</span>
                    <div class="flex items-baseline gap-1 mt-0.5">
                        <span class="text-2xl font-extrabold text-amber-700"><?= $pendingCount ?></span>
                        <span class="text-xs text-slate-400">applications</span>
                    </div>
                </div>
            </div>
            <span class="px-2 py-1 rounded-lg text-[10px] font-bold font-mono bg-amber-50 text-amber-700">
                In Review
            </span>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold shrink-0">
                    <i data-lucide="file-text" class="w-5 h-5"></i>
                </div>
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Applications</span>
                    <div class="flex items-baseline gap-1 mt-0.5">
                        <span class="text-2xl font-extrabold text-purple-700"><?= $totalSubmissions ?></span>
                        <span class="text-xs text-slate-400">total submitted</span>
                    </div>
                </div>
            </div>
            <span class="px-2 py-1 rounded-lg text-[10px] font-bold font-mono bg-purple-50 text-purple-700">
                History
            </span>
        </div>
    </div>

    <!-- My Applications Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
        <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider">Leave Applications & Approval Tracking</h2>
        <div class="overflow-x-auto no-scrollbar">
            <table class="w-full text-left text-xs text-slate-600 border-collapse min-w-[760px]">
                <thead class="bg-slate-50 text-slate-500 text-[11px] uppercase tracking-wider font-bold border-b border-slate-200">
                    <tr>
                        <th class="min-w-[160px] py-3.5 pl-4 pr-2">Leave Subject</th>
                        <th class="min-w-[160px] py-3.5 px-2 whitespace-nowrap">Duration & Days</th>
                        <th class="min-w-[180px] py-3.5 px-2">My Reason</th>
                        <th class="min-w-[160px] py-3.5 px-2">TL Review & Remarks</th>
                        <th class="min-w-[140px] py-3.5 pl-2 pr-4 text-right whitespace-nowrap">HR Final Decision</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($myLeaves)): ?>
                        <tr><td colspan="5" class="text-center py-8 text-slate-400">No leave applications submitted yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($myLeaves as $l): ?>
                        <tr class="hover:bg-slate-50/70 transition">
                            <!-- Leave Type / Subject -->
                            <td class="py-4 pl-4 pr-2 font-bold text-slate-900 align-top">
                                <div><?= htmlspecialchars($l['leave_type_name']) ?></div>
                                <span class="text-[10px] text-slate-400 font-mono"><?= date('d M Y', strtotime($l['created_at'])) ?></span>
                            </td>

                            <!-- Duration -->
                            <td class="py-4 px-2 font-mono text-[11px] align-top">
                                <div class="font-semibold text-slate-800"><?= formatDate($l['start_date']) ?> &rarr; <?= formatDate($l['end_date']) ?></div>
                                <span class="inline-block mt-0.5 px-2 py-0.2 bg-slate-100 rounded text-[10px] font-sans font-bold text-slate-700"><?= $l['total_days'] ?> day(s)</span>
                            </td>

                            <!-- Employee Reason -->
                            <td class="py-4 px-2 text-slate-700 align-top">
                                <p class="text-[11px] leading-relaxed"><?= htmlspecialchars($l['reason']) ?></p>
                            </td>

                            <!-- TL Review & Remark -->
                            <td class="py-4 px-2 align-top">
                                <?php if ($l['tl_reviewed']): ?>
                                    <div class="space-y-1">
                                        <?php if ($l['tl_recommendation'] === 'recommended'): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                <i data-lucide="check" class="w-3 h-3 text-emerald-600"></i> Recommended by TL
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                                <i data-lucide="alert-triangle" class="w-3 h-3 text-amber-600"></i> Not Recommended by TL
                                            </span>
                                        <?php endif; ?>
                                        <div class="text-[11px] text-slate-600 italic bg-slate-50 p-2 rounded-lg border border-slate-100">
                                            "<?= htmlspecialchars($l['tl_remarks'] ?: 'No notes added.') ?>"
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="flex items-center gap-1 text-[11px] text-amber-600 font-medium">
                                        <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                                        <span>Awaiting TL Review</span>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- HR Final Decision -->
                            <td class="py-4 pl-2 pr-4 text-right align-top">
                                <?php if ($l['status'] === 'approved'): ?>
                                    <div class="space-y-1">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <i data-lucide="check-circle" class="w-3 h-3 text-emerald-600"></i> Approved by HR
                                        </span>
                                        <?php if ($l['hr_remarks']): ?>
                                            <div class="text-[10px] text-slate-500 italic">
                                                Note: <?= htmlspecialchars($l['hr_remarks']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($l['status'] === 'rejected'): ?>
                                    <div class="space-y-1">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                            <i data-lucide="x-circle" class="w-3 h-3 text-rose-600"></i> Rejected by HR
                                        </span>
                                        <?php if ($l['hr_remarks']): ?>
                                            <div class="text-[10px] text-rose-600 italic">
                                                Note: <?= htmlspecialchars($l['hr_remarks']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($l['status'] === 'pending_hr_approval'): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                        <i data-lucide="clock" class="w-3 h-3 text-blue-600"></i> Pending HR Decision
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                        <i data-lucide="clock" class="w-3 h-3 text-amber-600"></i> In Queue
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Apply for Leave Modal (Simple Clean Unified Form) -->
    <div x-show="applyModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" x-cloak>
        <div @click.away="applyModalOpen = false" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200" 
             x-data="{
                 startDate: '<?= date('Y-m-d') ?>',
                 endDate: '<?= date('Y-m-d') ?>',
                 getDays() {
                     if (!this.startDate || !this.endDate) return 1;
                     const s = new Date(this.startDate);
                     const e = new Date(this.endDate);
                     if (e < s) return 1;
                     const diff = Math.ceil(Math.abs(e - s) / (1000 * 60 * 60 * 24)) + 1;
                     return diff;
                 }
             }">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                        <i data-lucide="calendar-plus" class="w-4 h-4"></i>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900">Apply for Leave</h3>
                </div>
                <button @click="applyModalOpen = false" class="text-slate-400 hover:text-slate-600 cursor-pointer"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <form action="?action=apply-leave" method="POST" class="space-y-3.5">
                <input type="hidden" name="leave_type_id" value="1">

                <!-- Single Clean Leave Subject Field -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Leave Subject / Type</label>
                    <input type="text" name="custom_leave_type" value="Leave Application" required placeholder="e.g. Personal Leave, Medical Leave, Family Event..." class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-indigo-500 focus:bg-white transition">
                </div>

                <!-- Date Range -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Start Date</label>
                        <input type="date" name="start_date" x-model="startDate" required min="<?= date('Y-m-d') ?>" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-800 font-medium">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">End Date</label>
                        <input type="date" name="end_date" x-model="endDate" required min="<?= date('Y-m-d') ?>" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-800 font-medium">
                    </div>
                </div>

                <!-- Auto-Calculated Duration Helper Card -->
                <div class="p-2.5 rounded-xl bg-indigo-50/70 border border-indigo-100 flex items-center justify-between">
                    <span class="text-[11px] font-bold text-indigo-900 flex items-center gap-1.5">
                        <i data-lucide="clock" class="w-3.5 h-3.5 text-indigo-600"></i> Auto-Calculated Duration:
                    </span>
                    <span class="px-2.5 py-0.5 rounded-lg bg-indigo-600 text-white font-bold font-mono text-xs shadow-2xs" x-text="getDays() + ' day(s)'"></span>
                </div>

                <!-- Reason & Handover -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Reason & Handover Info</label>
                    <textarea name="reason" rows="3" required placeholder="Explain why you need leave and who will cover tasks..." class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-800"></textarea>
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button type="button" @click="applyModalOpen = false" class="px-3 py-1.5 text-xs text-slate-500 hover:text-slate-700 font-medium">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition">Submit Application</button>
                </div>
            </form>
        </div>
    </div>
</div>
