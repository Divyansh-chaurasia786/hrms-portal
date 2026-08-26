<!-- views/admin/leaves.php -->
<?php
$user = authUser();
$db = getDBConnection();

// Fetch ALL pending leaves (both pending TL review and pending HR approval)
$pendingStmt = $db->query("
    SELECT la.*, COALESCE(la.custom_leave_type, 'Leave Application') as leave_type_name, 
           u.name as emp_name, u.avatar, u.designation,
           tl.name as tl_name
    FROM leave_applications la
    JOIN users u ON la.user_id = u.id
    LEFT JOIN users tl ON u.reporting_tl_id = tl.id
    WHERE la.status IN ('pending_hr_approval', 'pending_tl_review')
    ORDER BY la.created_at DESC
");
$pendingLeaves = $pendingStmt->fetchAll();

// Fetch complete decision history
$historyStmt = $db->query("
    SELECT la.*, COALESCE(la.custom_leave_type, 'Leave Application') as leave_type_name, 
           u.name as emp_name, u.avatar, u.designation,
           tl.name as tl_name, hr.name as hr_name
    FROM leave_applications la
    JOIN users u ON la.user_id = u.id
    LEFT JOIN users tl ON u.reporting_tl_id = tl.id
    LEFT JOIN users hr ON la.hr_action_by = hr.id
    WHERE la.status IN ('approved', 'rejected')
    ORDER BY COALESCE(la.hr_action_at, la.created_at) DESC
    LIMIT 50
");
$leaveHistory = $historyStmt->fetchAll();

// Metrics
$totalPending = count($pendingLeaves);
$tlForwardedCount = 0;
$awaitingTlCount = 0;
foreach ($pendingLeaves as $pl) {
    if ($pl['status'] === 'pending_hr_approval' || $pl['tl_reviewed'] == 1) {
        $tlForwardedCount++;
    } else {
        $awaitingTlCount++;
    }
}
$approvedCount = 0;
$rejectedCount = 0;
foreach ($leaveHistory as $h) {
    if ($h['status'] === 'approved') $approvedCount++;
    if ($h['status'] === 'rejected') $rejectedCount++;
}
?>

<div class="space-y-6" x-data="{
    activeTab: 'pending',
    letterModalOpen: false,
    decisionModalOpen: false,
    decisionAction: 'approved',
    decisionLeaveId: 0,
    decisionEmpName: '',
    decisionLeaveType: '',
    decisionPeriod: '',
    decisionRemarks: '',
    selectedLetter: {
        emp_name: '',
        designation: '',
        leave_type_name: '',
        start_date: '',
        end_date: '',
        total_days: 1,
        reason: '',
        tl_name: '',
        created_at: ''
    },
    openLetter(l) {
        this.selectedLetter = l;
        this.letterModalOpen = true;
    },
    openDecisionModal(leaveId, empName, leaveType, period, action) {
        this.decisionLeaveId = leaveId;
        this.decisionEmpName = empName;
        this.decisionLeaveType = leaveType;
        this.decisionPeriod = period;
        this.decisionAction = action;
        this.decisionRemarks = action === 'approved' ? 'Approved. Please ensure proper task handover.' : '';
        this.decisionModalOpen = true;
    }
}">
    <!-- Executive Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="calendar-check" class="w-5 h-5"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Executive Leave Management</h1>
                <p class="text-xs text-slate-500 mt-0.5">Primary approval authority. Review all employee leave applications, TL recommendations, and historical records.</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-3 py-1.5 bg-amber-50 text-amber-800 text-xs font-bold rounded-xl border border-amber-200 flex items-center gap-1.5">
                <i data-lucide="clock" class="w-3.5 h-3.5 text-amber-600"></i> <?= $totalPending ?> Total Pending
            </span>
        </div>
    </div>

    <!-- 4 Formal Metric Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5">
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Applications</span>
                <div class="text-2xl font-extrabold text-slate-900 mt-0.5"><?= count($pendingLeaves) + count($leaveHistory) ?></div>
            </div>
            <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center">
                <i data-lucide="files" class="w-4 h-4"></i>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Ready for HR Action</span>
                <div class="text-2xl font-extrabold text-purple-700 mt-0.5"><?= $tlForwardedCount ?></div>
            </div>
            <div class="w-9 h-9 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                <i data-lucide="check-circle" class="w-4 h-4"></i>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Approved Leaves</span>
                <div class="text-2xl font-extrabold text-emerald-600 mt-0.5"><?= $approvedCount ?></div>
            </div>
            <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <i data-lucide="calendar-heart" class="w-4 h-4"></i>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Rejected Leaves</span>
                <div class="text-2xl font-extrabold text-rose-600 mt-0.5"><?= $rejectedCount ?></div>
            </div>
            <div class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center">
                <i data-lucide="x-circle" class="w-4 h-4"></i>
            </div>
        </div>
    </div>

    <!-- Main Applications Data Hub -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <!-- Navigation Tabs -->
        <div class="flex items-center gap-2 p-3 bg-slate-50/70 border-b border-slate-200 overflow-x-auto">
            <button @click="activeTab = 'pending'" :class="activeTab === 'pending' ? 'bg-white text-indigo-700 font-bold shadow-xs border border-slate-200' : 'text-slate-500 hover:text-slate-800 font-semibold'" class="px-4 py-2 text-xs rounded-xl transition cursor-pointer flex items-center gap-1.5 shrink-0">
                <i data-lucide="inbox" class="w-3.5 h-3.5"></i> Pending Applications (<?= $totalPending ?>)
            </button>
            <button @click="activeTab = 'history'" :class="activeTab === 'history' ? 'bg-white text-indigo-700 font-bold shadow-xs border border-slate-200' : 'text-slate-500 hover:text-slate-800 font-semibold'" class="px-4 py-2 text-xs rounded-xl transition cursor-pointer flex items-center gap-1.5 shrink-0">
                <i data-lucide="archive" class="w-3.5 h-3.5"></i> Decision History (<?= count($leaveHistory) ?>)
            </button>
        </div>

        <!-- TAB 1: PENDING QUEUE TABLE -->
        <div x-show="activeTab === 'pending'" class="p-5 space-y-4">
            <?php if (empty($pendingLeaves)): ?>
                <div class="p-10 text-center text-slate-400 space-y-2">
                    <i data-lucide="check-check" class="w-10 h-10 mx-auto text-emerald-400"></i>
                    <p class="text-sm font-semibold text-slate-700">All caught up! No leave applications in queue.</p>
                    <p class="text-xs text-slate-400">New leave applications submitted by employees will appear here instantly.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto no-scrollbar">
                    <table class="w-full text-left text-xs text-slate-600 border-collapse min-w-[780px]">
                        <thead class="bg-slate-50 text-slate-500 text-[11px] uppercase tracking-wider font-bold border-b border-slate-200">
                            <tr>
                                <th class="min-w-[180px] py-3.5 pl-4 pr-2">Applicant</th>
                                <th class="min-w-[170px] py-3.5 px-2 whitespace-nowrap">Leave Subject & Period</th>
                                <th class="min-w-[180px] py-3.5 px-2">Reason & Details</th>
                                <th class="min-w-[160px] py-3.5 px-2 whitespace-nowrap">TL Recommendation</th>
                                <th class="min-w-[150px] py-3.5 pl-2 pr-4 text-right whitespace-nowrap">Official HR Decision</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($pendingLeaves as $l): ?>
                                <tr class="hover:bg-slate-50/70 transition">
                                    <!-- Applicant Info (NO EMP ID) -->
                                    <td class="py-4 pl-4 pr-2 align-top">
                                        <div class="flex items-center gap-2.5">
                                            <img src="<?= $l['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($l['emp_name']) ?>" class="w-9 h-9 rounded-full object-cover ring-1 ring-slate-200 shrink-0" alt="Avatar">
                                            <div class="overflow-hidden">
                                                <div class="font-bold text-slate-900 truncate"><?= htmlspecialchars($l['emp_name']) ?></div>
                                                <div class="text-[11px] text-slate-500 truncate"><?= htmlspecialchars($l['designation']) ?></div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Leave Subject & Period -->
                                    <td class="py-4 px-2 align-top font-mono text-[11px]">
                                        <div class="font-bold font-sans text-indigo-950"><?= htmlspecialchars($l['leave_type_name']) ?></div>
                                        <div class="text-slate-700 mt-0.5"><?= formatDate($l['start_date']) ?> &rarr; <?= formatDate($l['end_date']) ?></div>
                                        <span class="inline-block mt-1 px-2 py-0.5 bg-indigo-50 text-indigo-700 rounded-md text-[10px] font-bold font-sans">
                                            <?= $l['total_days'] ?> day(s)
                                        </span>
                                    </td>

                                    <!-- Reason & View Application Letter Link -->
                                    <td class="py-4 px-2 align-top text-slate-700">
                                        <p class="text-[11px] leading-relaxed line-clamp-2 italic text-slate-600">"<?= htmlspecialchars($l['reason']) ?>"</p>
                                        <button type="button" @click="openLetter(<?= htmlspecialchars(json_encode($l), ENT_QUOTES, 'UTF-8') ?>)" class="mt-1 text-[11px] font-bold text-indigo-600 hover:text-indigo-800 underline cursor-pointer inline-flex items-center gap-1">
                                            <i data-lucide="file-text" class="w-3 h-3"></i> View Formal Application
                                        </button>
                                    </td>

                                    <!-- TL Recommendation -->
                                    <td class="py-4 px-2 align-top">
                                        <?php if ($l['tl_reviewed']): ?>
                                            <div class="space-y-1">
                                                <?php if ($l['tl_recommendation'] === 'recommended'): ?>
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                        <i data-lucide="check" class="w-3 h-3 text-emerald-600"></i> Recommended
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                                        <i data-lucide="alert-triangle" class="w-3 h-3 text-amber-600"></i> Not Recommended
                                                    </span>
                                                <?php endif; ?>
                                                <div class="text-[10px] text-slate-500">
                                                    by <?= htmlspecialchars($l['tl_name'] ?: 'Team Lead') ?>
                                                </div>
                                                <?php if (!empty($l['tl_remarks'])): ?>
                                                    <div class="text-[10px] text-slate-600 bg-slate-50 p-1.5 rounded border border-slate-100 italic">
                                                        "<?= htmlspecialchars($l['tl_remarks']) ?>"
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="space-y-1">
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                                    <i data-lucide="clock" class="w-3 h-3 text-blue-600"></i> Awaiting TL Review
                                                </span>
                                                <div class="text-[10px] text-slate-400">
                                                    Assigned to <?= htmlspecialchars($l['tl_name'] ?: 'Team Lead') ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Official HR Action Buttons -->
                                    <td class="py-4 pl-2 pr-4 text-right align-top">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button type="button" 
                                                @click="openDecisionModal(<?= $l['id'] ?>, '<?= addslashes($l['emp_name']) ?>', '<?= addslashes($l['leave_type_name']) ?>', '<?= formatDate($l['start_date']) ?> to <?= formatDate($l['end_date']) ?> (<?= $l['total_days'] ?>d)', 'rejected')" 
                                                class="px-2.5 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-bold rounded-xl transition cursor-pointer flex items-center gap-1">
                                                <i data-lucide="x" class="w-3.5 h-3.5"></i> Reject
                                            </button>
                                            <button type="button" 
                                                @click="openDecisionModal(<?= $l['id'] ?>, '<?= addslashes($l['emp_name']) ?>', '<?= addslashes($l['leave_type_name']) ?>', '<?= formatDate($l['start_date']) ?> to <?= formatDate($l['end_date']) ?> (<?= $l['total_days'] ?>d)', 'approved')" 
                                                class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-xs transition cursor-pointer flex items-center gap-1.5">
                                                <i data-lucide="check" class="w-3.5 h-3.5"></i> Approve
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB 2: COMPLETE DECISION HISTORY TABLE -->
        <div x-show="activeTab === 'history'" class="p-5 space-y-4" x-cloak>
            <?php if (empty($leaveHistory)): ?>
                <div class="p-8 text-center text-slate-400">No past leave records found.</div>
            <?php else: ?>
                <div class="overflow-x-auto no-scrollbar">
                    <table class="w-full text-left text-xs text-slate-600 border-collapse min-w-[780px]">
                        <thead class="bg-slate-50 text-slate-500 text-[11px] uppercase tracking-wider font-bold border-b border-slate-200">
                            <tr>
                                <th class="min-w-[180px] py-3.5 pl-4 pr-2">Employee</th>
                                <th class="min-w-[170px] py-3.5 px-2 whitespace-nowrap">Leave Subject & Period</th>
                                <th class="min-w-[180px] py-3.5 px-2">Reason</th>
                                <th class="min-w-[150px] py-3.5 px-2 whitespace-nowrap">Decided By</th>
                                <th class="min-w-[140px] py-3.5 pl-2 pr-4 text-right whitespace-nowrap">Official Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($leaveHistory as $h): ?>
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-3.5 pl-4 pr-2 font-bold text-slate-900 align-top">
                                        <div class="flex items-center gap-2">
                                            <img src="<?= $h['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($h['emp_name']) ?>" class="w-7 h-7 rounded-full object-cover ring-1 ring-slate-200" alt="Avatar">
                                            <div>
                                                <div><?= htmlspecialchars($h['emp_name']) ?></div>
                                                <div class="text-[10px] text-slate-400 font-normal"><?= htmlspecialchars($h['designation']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-2 align-top">
                                        <div class="font-bold text-slate-800"><?= htmlspecialchars($h['leave_type_name']) ?></div>
                                        <div class="text-[11px] font-mono text-slate-500"><?= formatDate($h['start_date']) ?> &rarr; <?= formatDate($h['end_date']) ?> (<?= $h['total_days'] ?>d)</div>
                                    </td>
                                    <td class="py-3.5 px-2 align-top text-slate-600 italic">
                                        "<?= htmlspecialchars($h['reason']) ?>"
                                    </td>
                                    <td class="py-3.5 px-2 align-top text-[11px]">
                                        <div class="font-semibold text-slate-800"><?= htmlspecialchars($h['hr_name'] ?: 'HR Director') ?></div>
                                        <div class="text-[10px] text-slate-400 font-mono"><?= !empty($h['hr_action_at']) ? date('d M Y, h:i A', strtotime($h['hr_action_at'])) : '-' ?></div>
                                        <?php if (!empty($h['hr_remarks'])): ?>
                                            <div class="text-[10px] text-slate-500 italic mt-0.5">Note: <?= htmlspecialchars($h['hr_remarks']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3.5 pl-2 pr-4 text-right align-top">
                                        <?php if ($h['status'] === 'approved'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                <i data-lucide="check-circle" class="w-3 h-3 text-emerald-600"></i> Approved by HR
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                                <i data-lucide="x-circle" class="w-3 h-3 text-rose-600"></i> Rejected by HR
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
    </div>

    <!-- Official Decision Modal (HR Remarks) -->
    <div x-show="decisionModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" x-cloak>
        <div @click.away="decisionModalOpen = false" class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold" :class="decisionAction === 'approved' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'">
                        <i :data-lucide="decisionAction === 'approved' ? 'check-circle' : 'x-circle'" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900" x-text="decisionAction === 'approved' ? 'Sanction Leave Application' : 'Reject Leave Application'"></h3>
                        <p class="text-xs text-slate-500">Applicant: <strong class="text-slate-800" x-text="decisionEmpName"></strong></p>
                    </div>
                </div>
                <button @click="decisionModalOpen = false" class="text-slate-400 hover:text-slate-600 cursor-pointer"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <form action="?action=hr-action-leave" method="POST" class="space-y-4">
                <input type="hidden" name="leave_id" :value="decisionLeaveId">
                <input type="hidden" name="action" :value="decisionAction">

                <div class="p-3.5 rounded-xl border text-xs space-y-1" :class="decisionAction === 'approved' ? 'bg-emerald-50/50 border-emerald-100 text-emerald-950' : 'bg-rose-50/50 border-rose-100 text-rose-950'">
                    <div class="flex items-center justify-between">
                        <span class="font-bold uppercase tracking-wider text-[10px]" x-text="decisionAction === 'approved' ? 'Action: Approved & Sanctioned' : 'Action: Rejected'"></span>
                        <span class="font-mono text-[11px]" x-text="decisionPeriod"></span>
                    </div>
                    <div class="text-[11px] font-semibold" x-text="'Subject: ' + decisionLeaveType"></div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700">Official HR Remarks / Instructions:</label>
                    <textarea name="hr_remarks" x-model="decisionRemarks" rows="3" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs focus:ring-2 focus:ring-purple-500 focus:bg-white focus:outline-none" placeholder="Add official instructions, handover requirements, or reason for decision..."></textarea>
                    <p class="text-[11px] text-slate-400">This remark will be permanently recorded in the portal and emailed to the employee with TL in CC.</p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button type="button" @click="decisionModalOpen = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 text-white text-xs font-bold rounded-xl shadow-xs transition cursor-pointer flex items-center gap-1.5" :class="decisionAction === 'approved' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-rose-600 hover:bg-rose-700'">
                        <i :data-lucide="decisionAction === 'approved' ? 'check' : 'x'" class="w-4 h-4"></i>
                        <span x-text="decisionAction === 'approved' ? 'Confirm Approval & Sanction' : 'Confirm Rejection'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Formal Application Letter Modal Preview -->
    <div x-show="letterModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" x-cloak>
        <div @click.away="letterModalOpen = false" class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-2xl border border-slate-200 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                        <i data-lucide="file-text" class="w-4 h-4"></i>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900">Formal Leave Application Letter</h3>
                </div>
                <button @click="letterModalOpen = false" class="text-slate-400 hover:text-slate-600 cursor-pointer"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <!-- Formal Letter Paper Presentation -->
            <div class="p-5 bg-slate-50 rounded-xl border border-slate-200 font-sans text-xs text-slate-800 space-y-3 leading-relaxed">
                <div>
                    <strong>To,</strong><br>
                    The HR Department,<br>
                    Ecovista Global Private Limited
                </div>

                <div>
                    <strong>Subject: Formal Application for Leave of Absence - <span x-text="selectedLetter.emp_name"></span> (<span x-text="selectedLetter.designation"></span>)</strong>
                </div>

                <div>Respected Sir/Madam,</div>

                <div>
                    I am writing this formal application to respectfully request a leave of absence for <span class="font-bold text-indigo-900" x-text="selectedLetter.total_days + ' Day(s)'"></span>, commencing from <strong x-text="selectedLetter.start_date"></strong> to <strong x-text="selectedLetter.end_date"></strong>, on account of <strong x-text="selectedLetter.leave_type_name"></strong>.
                </div>

                <div class="p-3 bg-white rounded-lg border border-slate-200 italic text-slate-700">
                    "<span x-text="selectedLetter.reason"></span>"
                </div>

                <div>
                    I have duly briefed my Reporting Team Lead, <strong x-text="selectedLetter.tl_name || 'Team Lead'"></strong>, regarding the status of my ongoing responsibilities and have coordinated the necessary task handovers.
                </div>

                <div>
                    I kindly request you to please consider my application and grant approval for the requested leave duration.
                </div>

                <div class="pt-2">
                    Thanking you,<br>
                    <strong>Yours faithfully,</strong><br>
                    <span class="font-bold text-slate-900" x-text="selectedLetter.emp_name"></span><br>
                    <span class="text-slate-500" x-text="selectedLetter.designation"></span><br>
                    Ecovista Global Private Limited
                </div>
            </div>

            <div class="flex items-center justify-end">
                <button type="button" @click="letterModalOpen = false" class="px-4 py-2 bg-slate-800 text-white text-xs font-bold rounded-xl hover:bg-slate-900 transition cursor-pointer">
                    Close Preview
                </button>
            </div>
        </div>
    </div>
</div>
