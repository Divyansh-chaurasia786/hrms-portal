<!-- views/admin/wfh_approvals.php -->
<?php
$user = authUser();
$db = getDBConnection();

$requests = $db->query("
    SELECT r.*, u.name as emp_name, u.emp_id as emp_code, u.designation, u.avatar,
           tl.name as tl_name
    FROM wfh_requests r
    JOIN users u ON r.user_id = u.id
    LEFT JOIN users tl ON u.reporting_tl_id = tl.id
    ORDER BY r.applied_at DESC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$pendingRequests = array_filter($requests, fn($r) => ($r['status'] ?? '') === 'pending');
?>

<div class="space-y-6">
    <!-- Header Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="home" class="w-5 h-5"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">WFH Policy & Approvals Command</h1>
                <p class="text-xs text-slate-500 mt-0.5">Strict 2-day advance notice enforcement, TL/HR reviews, and Head HR schedule manager.</p>
            </div>
        </div>
    </div>

    <!-- Top Card: Head HR WFH Date Range Manager -->
    <?php if ($user['role'] === 'admin'): ?>
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-3">
            <div class="flex items-center gap-2.5">
                <i data-lucide="calendar" class="w-4 h-4 text-indigo-600"></i>
                <h3 class="font-bold text-sm text-slate-900">Head HR Work-From-Home Schedule</h3>
            </div>
            <p class="text-xs text-slate-500">Set an active date window for Head HR remote attendance. Reverts to In-Office mode automatically after expiry.</p>

            <form action="?action=set-hr-wfh" method="POST" class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">From Date</label>
                    <input type="date" name="from_date" value="<?= $user['hr_wfh_start_date'] ?? '' ?>" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">To Date</label>
                    <input type="date" name="to_date" value="<?= $user['hr_wfh_end_date'] ?? '' ?>" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-sm transition">
                        Save HR Remote Schedule
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Pending Requests Queue -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-4">
        <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center justify-between">
            <span>Pending WFH Applications (<?= count($pendingRequests) ?>)</span>
            <span class="text-[10px] text-slate-400">Strict Advance Rules Enforced</span>
        </h3>

        <?php if (empty($pendingRequests)): ?>
            <div class="text-center py-8 text-slate-400 text-xs">
                <i data-lucide="check-circle" class="w-8 h-8 mx-auto mb-2 text-emerald-400"></i>
                All WFH requests have been reviewed! Zero pending applications.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 text-slate-400 uppercase text-[10px] font-bold border-b border-slate-200">
                        <tr>
                            <th class="py-2.5 px-3">Applicant</th>
                            <th class="py-2.5 px-3">Target WFH Date</th>
                            <th class="py-2.5 px-3">Reason / Details</th>
                            <th class="py-2.5 px-3">Applied At</th>
                            <th class="py-2.5 px-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($pendingRequests as $pr): 
                            $prEmp = $pr['emp_name'] ?? 'Staff';
                            $prCode = $pr['emp_code'] ?? '';
                            $prDesig = $pr['designation'] ?? '';
                        ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3 px-3">
                                    <div class="font-bold text-slate-900"><?= htmlspecialchars($prEmp) ?></div>
                                    <div class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($prCode) ?> • <?= htmlspecialchars($prDesig) ?></div>
                                </td>
                                <td class="py-3 px-3 font-bold text-indigo-900 font-mono">
                                    <?= formatDate($pr['wfh_date']) ?>
                                </td>
                                <td class="py-3 px-3 text-slate-600 max-w-xs">
                                    <?= htmlspecialchars($pr['reason'] ?? '') ?>
                                </td>
                                <td class="py-3 px-3 text-[11px] text-slate-400 font-mono">
                                    <?= date('d M Y, h:i A', strtotime($pr['applied_at'] ?? 'now')) ?>
                                </td>
                                <td class="py-3 px-3 text-right whitespace-nowrap space-x-1.5">
                                    <form action="?action=review-wfh" method="POST" class="inline">
                                        <input type="hidden" name="request_id" value="<?= (int)($pr['id'] ?? 0) ?>">
                                        <input type="hidden" name="status" value="approved">
                                        <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition">
                                            Approve
                                        </button>
                                    </form>
                                    <form action="?action=review-wfh" method="POST" class="inline">
                                        <input type="hidden" name="request_id" value="<?= (int)($pr['id'] ?? 0) ?>">
                                        <input type="hidden" name="status" value="rejected">
                                        <button type="submit" class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-bold transition">
                                            Reject
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>