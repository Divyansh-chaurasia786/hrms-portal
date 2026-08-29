<!-- views/admin/wfh_approvals.php -->
<?php
$user = authUser() ?: ['id' => 1, 'role' => 'admin', 'name' => 'Admin'];
$db = getDBConnection();

// Fetch all employees for Direct WFH Grant modal
$allStaff = $db->query("SELECT id, name, emp_id, designation, department_name FROM users WHERE status = 'active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Fetch requests
if ($user['role'] === 'admin') {
    $requests = $db->query("
        SELECT r.*, u.name as emp_name, u.emp_id as emp_code, u.designation, u.avatar,
               tl.name as tl_name,
               hr.name as reviewer_name
        FROM wfh_requests r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN users tl ON u.reporting_tl_id = tl.id
        LEFT JOIN users hr ON r.reviewed_by = hr.id
        ORDER BY r.created_at DESC, r.applied_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} else {
    $requests = $db->query("
        SELECT r.*, u.name as emp_name, u.emp_id as emp_code, u.designation, u.avatar,
               tl.name as tl_name,
               hr.name as reviewer_name
        FROM wfh_requests r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN users tl ON u.reporting_tl_id = tl.id
        LEFT JOIN users hr ON r.reviewed_by = hr.id
        WHERE u.reporting_tl_id = " . (int)($user['id'] ?? 0) . "
        ORDER BY r.created_at DESC, r.applied_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$pendingRequests = array_filter($requests, fn($r) => ($r['status'] ?? '') === 'pending');
$approvedRequests = array_filter($requests, fn($r) => ($r['status'] ?? '') === 'approved');
?>

<div class="space-y-6" x-data="{ 
    grantModalOpen: false,
    activeTab: 'pending',
    selectedStaffId: '',
    fromDate: '<?= date('Y-m-d') ?>',
    toDate: '<?= date('Y-m-d') ?>'
}">
    <!-- Header Banner & Action Button -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-3xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3.5">
            <div class="w-11 h-11 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-extrabold shadow-sm shrink-0">
                <i data-lucide="home" class="w-5 h-5"></i>
            </div>
            <div>
                <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">WFH Policy & Direct Approvals</h1>
                <p class="text-xs text-slate-500 mt-0.5">Grant remote work authorization to employees for any duration and review applications.</p>
            </div>
        </div>

        <?php if ($user['role'] === 'admin'): ?>
            <button type="button" @click="grantModalOpen = true" class="px-4 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white rounded-2xl text-xs font-extrabold shadow-md shadow-indigo-600/30 transition flex items-center gap-2 cursor-pointer">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>Grant Direct WFH to Employee</span>
            </button>
        <?php endif; ?>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex items-center gap-2 border-b border-slate-200 pb-1">
        <button type="button" @click="activeTab = 'pending'" class="px-4 py-2 text-xs font-bold rounded-xl transition cursor-pointer flex items-center gap-2" :class="activeTab === 'pending' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'">
            <span>Pending Applications</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="activeTab === 'pending' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'"><?= count($pendingRequests) ?></span>
        </button>
        <button type="button" @click="activeTab = 'approved'" class="px-4 py-2 text-xs font-bold rounded-xl transition cursor-pointer flex items-center gap-2" :class="activeTab === 'approved' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'">
            <span>Approved WFH Roster</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px]" :class="activeTab === 'approved' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'"><?= count($approvedRequests) ?></span>
        </button>
        <button type="button" @click="activeTab = 'all'" class="px-4 py-2 text-xs font-bold rounded-xl transition cursor-pointer flex items-center gap-2" :class="activeTab === 'all' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'">
            <span>All WFH Audit History (<?= count($requests) ?>)</span>
        </button>
    </div>

    <!-- TAB 1: PENDING QUEUE -->
    <div x-show="activeTab === 'pending'" class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm space-y-4">
        <h3 class="text-xs font-extrabold text-slate-800 uppercase tracking-wider flex items-center justify-between">
            <span>Pending WFH Applications (<?= count($pendingRequests) ?>)</span>
            <span class="text-[10px] text-slate-400">Strict Advance Rules Enforced</span>
        </h3>

        <?php if (empty($pendingRequests)): ?>
            <div class="text-center py-10 text-slate-400 text-xs space-y-2">
                <i data-lucide="check-circle" class="w-9 h-9 mx-auto text-emerald-400"></i>
                <p class="font-bold text-slate-700">All WFH requests have been reviewed! Zero pending applications.</p>
                <p class="text-[11px]">You can use the <strong>"Grant Direct WFH"</strong> button above to authorize remote work for any employee.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 text-slate-400 uppercase text-[10px] font-bold border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-3">Applicant</th>
                            <th class="py-3 px-3">Target WFH Date</th>
                            <th class="py-3 px-3">Reason / Details</th>
                            <th class="py-3 px-3">Applied At</th>
                            <th class="py-3 px-3 text-right">Actions</th>
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
                                    <?= date('d M, h:i A', strtotime($pr['applied_at'])) ?>
                                </td>
                                <td class="py-3 px-3 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <form action="?action=review-wfh" method="POST" class="m-0">
                                            <input type="hidden" name="id" value="<?= $pr['id'] ?>">
                                            <input type="hidden" name="status" value="approved">
                                            <button type="submit" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition">
                                                Approve
                                            </button>
                                        </form>
                                        <form action="?action=review-wfh" method="POST" class="m-0">
                                            <input type="hidden" name="id" value="<?= $pr['id'] ?>">
                                            <input type="hidden" name="status" value="rejected">
                                            <button type="submit" class="px-3 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-bold transition">
                                                Reject
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB 2: APPROVED WFH ROSTER -->
    <div x-show="activeTab === 'approved'" class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm space-y-4" style="display:none;">
        <h3 class="text-xs font-extrabold text-slate-800 uppercase tracking-wider">
            Approved Remote Workforce Roster (<?= count($approvedRequests) ?>)
        </h3>

        <?php if (empty($approvedRequests)): ?>
            <div class="text-center py-10 text-slate-400 text-xs">
                No active or upcoming approved WFH records.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 text-slate-400 uppercase text-[10px] font-bold border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-3">Employee</th>
                            <th class="py-3 px-3">Authorized WFH Date</th>
                            <th class="py-3 px-3">Reason / Justification</th>
                            <th class="py-3 px-3">Authorized By</th>
                            <th class="py-3 px-3 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($approvedRequests as $ar): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3 px-3">
                                    <div class="font-bold text-slate-900"><?= htmlspecialchars($ar['emp_name'] ?? 'Staff') ?></div>
                                    <div class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($ar['emp_code'] ?? '') ?> • <?= htmlspecialchars($ar['designation'] ?? '') ?></div>
                                </td>
                                <td class="py-3 px-3 font-bold text-indigo-700 font-mono">
                                    <?= formatDate($ar['wfh_date']) ?>
                                    <?php if ($ar['wfh_date'] === date('Y-m-d')): ?>
                                        <span class="ml-1.5 px-2 py-0.5 rounded-full text-[9px] font-extrabold bg-emerald-100 text-emerald-800">Today</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3 text-slate-600 max-w-xs">
                                    <?= htmlspecialchars($ar['reason'] ?? '') ?>
                                </td>
                                <td class="py-3 px-3 text-slate-500 font-medium">
                                    <?= htmlspecialchars($ar['reviewer_name'] ?? 'HR Admin') ?>
                                </td>
                                <td class="py-3 px-3 text-right">
                                    <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg font-bold text-[11px]">
                                        Approved
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB 3: ALL AUDIT HISTORY -->
    <div x-show="activeTab === 'all'" class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm space-y-4" style="display:none;">
        <h3 class="text-xs font-extrabold text-slate-800 uppercase tracking-wider">
            Complete WFH History Log (<?= count($requests) ?>)
        </h3>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-slate-50 text-slate-400 uppercase text-[10px] font-bold border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-3">Employee</th>
                        <th class="py-3 px-3">Date</th>
                        <th class="py-3 px-3">Reason</th>
                        <th class="py-3 px-3">Reviewer</th>
                        <th class="py-3 px-3 text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($requests as $r): ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-3 px-3 font-bold text-slate-900"><?= htmlspecialchars($r['emp_name'] ?? 'Staff') ?></td>
                            <td class="py-3 px-3 font-mono font-semibold"><?= formatDate($r['wfh_date']) ?></td>
                            <td class="py-3 px-3 text-slate-600 max-w-xs truncate"><?= htmlspecialchars($r['reason'] ?? '') ?></td>
                            <td class="py-3 px-3 text-slate-500"><?= htmlspecialchars($r['reviewer_name'] ?? '-') ?></td>
                            <td class="py-3 px-3 text-right">
                                <span class="px-2 py-0.5 rounded-md font-bold text-[10px] <?= $r['status'] === 'approved' ? 'bg-emerald-50 text-emerald-700' : ($r['status'] === 'rejected' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700') ?>">
                                    <?= strtoupper($r['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ⚡ DIRECT GRANT WFH MODAL (ADMIN ONLY) -->
    <?php if ($user['role'] === 'admin'): ?>
    <div x-show="grantModalOpen" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-4" style="display:none;">
        <div @click.away="grantModalOpen = false" class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-black">
                        <i data-lucide="send" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-900">Direct Grant WFH Authorization</h3>
                        <p class="text-[11px] text-slate-500">Authorize remote attendance for any employee & date range.</p>
                    </div>
                </div>
                <button type="button" @click="grantModalOpen = false" class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <form action="?action=grant-direct-wfh" method="POST" class="space-y-3.5">
                <!-- Select Employee -->
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-slate-700">Select Employee *</label>
                    <select name="target_user_id" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-900 focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- Choose Employee / Staff --</option>
                        <?php foreach ($allStaff as $st): ?>
                            <option value="<?= $st['id'] ?>">
                                <?= htmlspecialchars($st['name']) ?> (<?= htmlspecialchars($st['emp_id']) ?> - <?= htmlspecialchars($st['designation']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Date Range -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-700">From Date *</label>
                        <input type="date" name="from_date" x-model="fromDate" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-bold text-slate-900">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-slate-700">To Date *</label>
                        <input type="date" name="to_date" x-model="toDate" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-bold text-slate-900">
                    </div>
                </div>

                <!-- Reason / Official Note -->
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-slate-700">Official Note / Reason *</label>
                    <input type="text" name="reason" placeholder="e.g. Approved for Remote Client Sprint / Medical Recovery" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-800">
                </div>

                <div class="pt-2 flex items-center justify-end gap-2">
                    <button type="button" @click="grantModalOpen = false" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-extrabold rounded-xl shadow-md transition">
                        Authorize & Grant WFH
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>