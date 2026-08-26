<!-- views/tl/leaves.php -->
<?php
$user = authUser() ?: [];
$userId = (int)($user['id'] ?? 0);
$db = getDBConnection();

// Fetch managed team IDs (Supports TL and TL Support)
$teamIds = getManagedTeamUserIds($userId);
$inClause = !empty($teamIds) ? implode(',', array_map('intval', $teamIds)) : '0';

// Leaves pending TL review
$pendingStmt = $db->query("
    SELECT la.*, COALESCE(la.custom_leave_type, lt.name) as leave_type_name, u.name as employee_name, u.emp_id, u.avatar, u.designation
    FROM leave_applications la
    LEFT JOIN leave_types lt ON la.leave_type_id = lt.id
    JOIN users u ON la.user_id = u.id
    WHERE la.status = 'pending_tl_review' AND la.user_id IN ($inClause)
    ORDER BY la.created_at DESC
");
$pendingLeaves = $pendingStmt->fetchAll();

// Leaves previously reviewed by TL
$historyStmt = $db->query("
    SELECT la.*, COALESCE(la.custom_leave_type, lt.name) as leave_type_name, u.name as employee_name, u.emp_id, u.avatar
    FROM leave_applications la
    LEFT JOIN leave_types lt ON la.leave_type_id = lt.id
    JOIN users u ON la.user_id = u.id
    WHERE la.tl_reviewed = 1 AND la.user_id IN ($inClause)
    ORDER BY la.tl_reviewed_at DESC
    LIMIT 15
");
$reviewedLeaves = $historyStmt->fetchAll();
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="calendar-check" class="w-5 h-5"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Team Leave Reviews</h1>
                <p class="text-xs text-slate-500 mt-0.5">Review team leave requests, add handover notes/recommendations, and forward to HR for final approval.</p>
            </div>
        </div>
        <span class="px-3 py-1 bg-indigo-50 text-indigo-700 text-xs font-bold rounded-xl border border-indigo-200">
            <?= count($pendingLeaves) ?> Awaiting TL Review
        </span>
    </div>

    <!-- Pending TL Reviews -->
    <div class="space-y-4">
        <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider">Awaiting Your Review & Forwarding to HR</h2>
        <?php if (empty($pendingLeaves)): ?>
            <div class="bg-white p-8 rounded-2xl border border-slate-200 text-center text-slate-400">
                <i data-lucide="check-circle-2" class="w-10 h-10 mx-auto text-emerald-400 mb-2"></i>
                <p class="text-sm font-semibold text-slate-700">All caught up! No pending leave requests to review.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <?php foreach ($pendingLeaves as $l): ?>
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between space-y-4">
                        <div>
                            <div class="flex items-start justify-between gap-3 pb-3 border-b border-slate-100">
                                <div class="flex items-center gap-3">
                                    <img src="<?= $l['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($l['employee_name']) ?>" class="w-10 h-10 rounded-full object-cover ring-1 ring-slate-200" alt="Avatar">
                                    <div>
                                        <div class="font-bold text-sm text-slate-900"><?= htmlspecialchars($l['employee_name']) ?></div>
                                        <div class="text-xs text-slate-400"><?= htmlspecialchars($l['emp_id']) ?> • <?= htmlspecialchars($l['designation']) ?></div>
                                    </div>
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                    <?= htmlspecialchars($l['leave_type_name']) ?>
                                </span>
                            </div>

                            <div class="mt-3 space-y-2 text-xs">
                                <div class="flex items-center gap-2 text-slate-700 font-semibold">
                                    <i data-lucide="calendar" class="w-4 h-4 text-slate-400"></i>
                                    <span><?= formatDate($l['start_date']) ?> &rarr; <?= formatDate($l['end_date']) ?></span>
                                    <span class="px-2 py-0.5 bg-slate-100 rounded text-[11px] font-mono font-bold text-slate-800"><?= $l['total_days'] ?> day(s)</span>
                                </div>
                                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100 text-slate-600">
                                    <strong>Employee Reason:</strong> <?= htmlspecialchars($l['reason']) ?>
                                </div>
                            </div>
                        </div>

                        <!-- TL Review & Forward Form -->
                        <form action="?action=tl-review-leave" method="POST" class="pt-3 border-t border-slate-100 space-y-2.5">
                            <input type="hidden" name="leave_id" value="<?= $l['id'] ?>">
                            
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Your Recommendation to HR</label>
                                <select name="tl_recommendation" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1.5 text-xs font-semibold text-slate-700 focus:ring-2 focus:ring-indigo-500">
                                    <option value="recommended">✓ Recommended (Tasks covered / delegated)</option>
                                    <option value="not_recommended">⚠️ Not Recommended (Sprint deadline conflict)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Your Remark (Visible to Employee & HR)</label>
                                <input type="text" name="tl_remarks" required placeholder="e.g. Work delegated to Rahul, approved from team side." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-xs focus:ring-2 focus:ring-indigo-500">
                            </div>

                            <div class="flex justify-end pt-1">
                                <button type="submit" class="w-full sm:w-auto px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition flex items-center justify-center gap-1.5">
                                    <i data-lucide="send" class="w-3.5 h-3.5"></i> Submit Review & Forward to HR
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- History Table with TL Remarks prominently shown -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
        <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider">Leaves Reviewed by You</h2>
        <div class="overflow-x-auto no-scrollbar">
            <table class="w-full text-left text-xs text-slate-600 border-collapse min-w-[720px]">
                <thead class="bg-slate-50 text-slate-500 text-[11px] uppercase tracking-wider font-bold border-b border-slate-200">
                    <tr>
                        <th class="min-w-[160px] py-3 pl-4 pr-2">Employee</th>
                        <th class="min-w-[140px] py-3 px-2 whitespace-nowrap">Leave Type</th>
                        <th class="min-w-[140px] py-3 px-2 whitespace-nowrap">Dates</th>
                        <th class="min-w-[160px] py-3 px-2">Your Review & Remarks</th>
                        <th class="min-w-[120px] py-3 pl-2 pr-4 text-right whitespace-nowrap">HR Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($reviewedLeaves)): ?>
                        <tr><td colspan="5" class="text-center py-6 text-slate-400">No reviewed leave applications yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($reviewedLeaves as $h): ?>
                        <tr class="hover:bg-slate-50/70 transition">
                            <td class="py-3.5 pl-4 pr-2 font-bold text-slate-900 align-top"><?= htmlspecialchars($h['employee_name']) ?></td>
                            <td class="py-3.5 px-2 font-semibold align-top"><?= htmlspecialchars($h['leave_type_name']) ?></td>
                            <td class="py-3.5 px-2 font-mono text-[11px] align-top"><?= formatDate($h['start_date']) ?> (<?= $h['total_days'] ?>d)</td>
                            <td class="py-3.5 px-2 align-top">
                                <div class="space-y-1">
                                    <span class="text-[10px] font-bold <?= $h['tl_recommendation'] === 'recommended' ? 'text-emerald-700' : 'text-amber-700' ?>">
                                        <?= $h['tl_recommendation'] === 'recommended' ? '✓ Recommended' : '⚠️ Not Recommended' ?>
                                    </span>
                                    <div class="text-[11px] text-slate-600 italic bg-slate-50 p-1.5 rounded-lg border border-slate-100">
                                        "<?= htmlspecialchars($h['tl_remarks'] ?: 'No remark added.') ?>"
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 pl-2 pr-4 text-right align-top">
                                <?php if ($h['status'] === 'approved'): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Approved by HR</span>
                                <?php elseif ($h['status'] === 'rejected'): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">Rejected by HR</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-blue-50 text-blue-700 border border-blue-200">Pending HR Approval</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
