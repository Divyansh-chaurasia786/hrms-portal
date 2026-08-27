<!-- views/tl/dashboard.php -->
<?php
$user = authUser();
$db = getDBConnection();
$today = date('Y-m-d');

// 1. Team stats (Supports Team Lead and TL Support)
$teamIds = getManagedTeamUserIds($user['id']);
$teamSize = count($teamIds);
$inClause = !empty($teamIds) ? implode(',', array_map('intval', $teamIds)) : '0';

// Present today
$presentStmt = $db->query("SELECT COUNT(DISTINCT user_id) FROM attendance WHERE user_id IN ($inClause) AND date = '$today' AND clock_in IS NOT NULL");
$presentCount = (int)$presentStmt->fetchColumn();

// Tasks under review
$reviewStmt = $db->query("SELECT COUNT(*) FROM tasks WHERE assigned_to IN ($inClause) AND status = 'review'");
$reviewCount = (int)$reviewStmt->fetchColumn();

// Pending leaves
$pendingLeavesStmt = $db->query("SELECT COUNT(*) FROM leave_applications WHERE user_id IN ($inClause) AND status = 'pending'");
$pendingLeavesCount = (int)$pendingLeavesStmt->fetchColumn();

// Live team roster
$teamMembers = AttendanceController::getTeamLiveStatus($user['id']);

// BDA Calling Stats (for BDA Team Lead)
$isBDATeam = (($user['department_name'] ?? '') === 'Calling / BDA Team');
$bdaStats = [
    'total_leads' => 0,
    'today_calls' => 0,
    'today_converted' => 0,
    'today_interested' => 0
];
if ($isBDATeam) {
    $bdaStats['total_leads'] = (int)$db->query("SELECT COUNT(*) FROM calling_leads")->fetchColumn();
    $bdaStats['today_calls'] = (int)$db->query("SELECT COUNT(*) FROM call_logs WHERE call_date = '$today'")->fetchColumn();
    $bdaStats['today_converted'] = (int)$db->query("SELECT COUNT(*) FROM call_logs WHERE call_date = '$today' AND disposition = 'converted'")->fetchColumn();
    $bdaStats['today_interested'] = (int)$db->query("SELECT COUNT(*) FROM call_logs WHERE call_date = '$today' AND disposition = 'interested'")->fetchColumn();
}

// Submissions needing review
$subsStmt = $db->prepare("
    SELECT ts.*, t.title as task_title, t.priority, t.due_date, u.name as employee_name, u.avatar, p.title as project_name
    FROM task_submissions ts
    JOIN tasks t ON ts.task_id = t.id
    JOIN users u ON ts.submitted_by = u.id
    LEFT JOIN projects p ON t.project_id = p.id
    WHERE ts.review_status = 'pending' AND t.assigned_to IN ($inClause)
    ORDER BY ts.submitted_at DESC
");
$subsStmt->execute();
$pendingSubmissions = $subsStmt->fetchAll();

// Pending leave applications
$leavesStmt = $db->prepare("
    SELECT la.*, lt.name as leave_type_name, u.name as employee_name, u.avatar
    FROM leave_applications la
    JOIN leave_types lt ON la.leave_type_id = lt.id
    JOIN users u ON la.user_id = u.id
    WHERE la.status = 'pending' AND la.user_id IN ($inClause)
    ORDER BY la.created_at DESC
");
$leavesStmt->execute();
$pendingLeaves = $leavesStmt->fetchAll();

// Fetch UNREAD official HR Directives for this TL (Only unread alerts show at top)
$fbStmt = $db->prepare("
    SELECT f.*, hr.name as hr_name
    FROM tl_feedbacks f
    JOIN users hr ON f.hr_id = hr.id
    WHERE f.tl_id = ? AND f.status = 'unread'
    ORDER BY f.created_at DESC
");
$fbStmt->execute([$user['id']]);
$unreadFeedbacks = $fbStmt->fetchAll();

// Fetch escalations submitted by this TL
$escalationsStmt = $db->prepare("
    SELECT e.*, u.name as employee_name, u.designation as employee_designation, u.avatar as employee_avatar, u.is_escalated_locked, u.is_dismissed, hr.name as hr_name
    FROM employee_escalations e
    JOIN users u ON e.employee_id = u.id
    LEFT JOIN users hr ON e.hr_action_by = hr.id
    WHERE e.tl_id = ?
    ORDER BY e.created_at DESC
");
$escalationsStmt->execute([$user['id']]);
$myEscalations = $escalationsStmt->fetchAll();
?>

<div x-data="{ escalateModalOpen: false, selectedEmpId: '', selectedEmpName: '', reviewModalOpen: false, activeSubmission: null, showRejectInput: false, rejectRemarks: '' }" class="space-y-6">

    <!-- Conditional Unread HR Directive Alert (Only appears when there is an unread alert) -->
    <?php if (!empty($unreadFeedbacks)): ?>
        <div class="space-y-3">
            <?php foreach ($unreadFeedbacks as $fb): ?>
                <div class="p-4 rounded-2xl bg-indigo-950 text-white border border-indigo-500/40 shadow-lg flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-start gap-3.5">
                        <div class="w-10 h-10 rounded-xl bg-indigo-800 text-indigo-300 flex items-center justify-center shrink-0 mt-0.5">
                            <i data-lucide="shield-alert" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs font-bold uppercase tracking-wider text-indigo-300">Official HR Directive</span>
                                <span class="text-[10px] px-2 py-0.5 rounded-full font-bold uppercase <?= $fb['priority'] === 'urgent' ? 'bg-rose-500 text-white' : 'bg-amber-400 text-slate-950' ?>"><?= $fb['priority'] ?></span>
                                <span class="text-xs text-indigo-300 font-mono">from <?= htmlspecialchars($fb['hr_name']) ?> • <?= date('d M, h:i A', strtotime($fb['created_at'])) ?></span>
                            </div>
                            <p class="text-sm font-semibold text-white mt-1 leading-relaxed"><?= nl2br(htmlspecialchars($fb['message'])) ?></p>
                        </div>
                    </div>
                    <div class="shrink-0">
                        <form action="?action=ack-tl-feedback" method="POST" class="m-0">
                            <input type="hidden" name="feedback_id" value="<?= $fb['id'] ?>">
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5 shadow-sm">
                                <i data-lucide="check" class="w-4 h-4"></i> Acknowledge
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Clean Header & Fast Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3.5">
            <div class="w-11 h-11 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="briefcase" class="w-6 h-6"></i>
            </div>
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <?php
                    $tlDesig = !empty($user['designation']) ? $user['designation'] : 'Team Lead';
                    $isTLSupport = (stripos($tlDesig, 'tl support') !== false || stripos($tlDesig, 'support tl') !== false);
                    ?>
                    <h1 class="text-xl font-bold text-slate-900 tracking-tight"><?= $isTLSupport ? 'TL Support Operations Hub' : 'Team Lead Operations Hub' ?></h1>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold <?= $isTLSupport ? 'bg-sky-100 text-sky-800 border border-sky-200' : 'bg-blue-100 text-blue-800 border border-blue-200' ?> uppercase">
                        <?= htmlspecialchars($tlDesig) ?>
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Live monitoring of team attendance, sprint deliverables, and disciplinary management.</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap">
            <?php if ($isBDATeam): ?>
                <a href="?action=export-calling-history" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-sm transition cursor-pointer">
                    <i data-lucide="download" class="w-4 h-4"></i> Export Calls (Excel)
                </a>
                <a href="?page=calling-manage" class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm shadow-indigo-500/20 transition">
                    <i data-lucide="phone-forwarded" class="w-4 h-4"></i> BDA Lead CRM
                </a>
            <?php else: ?>
                <button type="button" @click="selectedEmpId = ''; selectedEmpName = ''; escalateModalOpen = true" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-bold rounded-xl transition shadow-2xs">
                    <i data-lucide="shield-alert" class="w-4 h-4 text-rose-600"></i> Refer to HR
                </button>
                <a href="?page=tl-tasks&action=new" class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm shadow-indigo-500/20 transition">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i> Assign Task
                </a>
            <?php endif; ?>
        </div>
    </div>

<?php if ($isBDATeam): ?>
<!-- BDA Calling Quick Hub Banner -->
<div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white p-5 rounded-3xl border border-indigo-500/30 shadow-xl mb-6 space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-indigo-800/40 pb-3">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-indigo-500/20 border border-indigo-500/40 text-indigo-300 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="phone-call" class="w-5 h-5"></i>
            </div>
            <div>
                <h2 class="text-sm font-extrabold tracking-wide uppercase text-indigo-200">BDA Telecalling & Team Metrics</h2>
                <p class="text-xs text-slate-400">Live summary of today's calls, conversions, and lead pool management.</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="?page=calling-manage" class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl transition inline-flex items-center gap-1.5 shadow-sm">
                <i data-lucide="upload-cloud" class="w-3.5 h-3.5"></i> Upload / Allocate Leads
            </a>
            <a href="?action=export-calling-history" class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl transition inline-flex items-center gap-1.5 shadow-sm">
                <i data-lucide="download" class="w-3.5 h-3.5"></i> Export Excel
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-slate-950/60 p-3.5 rounded-2xl border border-indigo-500/20">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Leads Pool</span>
            <div class="text-xl font-extrabold text-white mt-0.5"><?= $bdaStats['total_leads'] ?></div>
        </div>
        <div class="bg-slate-950/60 p-3.5 rounded-2xl border border-indigo-500/20">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Calls Made Today</span>
            <div class="text-xl font-extrabold text-indigo-400 mt-0.5"><?= $bdaStats['today_calls'] ?></div>
        </div>
        <div class="bg-slate-950/60 p-3.5 rounded-2xl border border-indigo-500/20">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Interested Today</span>
            <div class="text-xl font-extrabold text-blue-400 mt-0.5"><?= $bdaStats['today_interested'] ?></div>
        </div>
        <div class="bg-slate-950/60 p-3.5 rounded-2xl border border-indigo-500/20">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Deals Closed Today</span>
            <div class="text-xl font-extrabold text-emerald-400 mt-0.5"><?= $bdaStats['today_converted'] ?></div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Metric Stat Cards (2x2 Grid on Mobile, 4 Cols on Desktop) -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6 sm:mb-8">
    <a href="?page=tl-attendance" class="bg-white p-3.5 sm:p-5 rounded-2xl sm:rounded-3xl border border-slate-200 shadow-sm flex flex-col sm:flex-row items-start sm:items-center gap-2.5 sm:gap-4 hover:shadow-md hover:border-blue-300 hover:-translate-y-0.5 transition-all cursor-pointer group">
        <div class="w-8 h-8 sm:w-12 sm:h-12 rounded-xl bg-blue-50 text-blue-600 group-hover:bg-blue-600 group-hover:text-white flex items-center justify-center transition-colors shrink-0 shadow-2xs">
            <i data-lucide="users" class="w-4 h-4 sm:w-6 sm:h-6"></i>
        </div>
        <div class="min-w-0">
            <div class="text-[10px] sm:text-xs font-semibold text-slate-500 uppercase tracking-wider group-hover:text-blue-600 transition-colors truncate">Team Size</div>
            <div class="text-lg sm:text-2xl font-bold text-slate-900 mt-0.5"><?= $teamSize ?> <span class="text-[10px] sm:text-xs font-normal text-slate-400">members</span></div>
        </div>
    </a>

    <a href="?page=tl-attendance" class="bg-white p-3.5 sm:p-5 rounded-2xl sm:rounded-3xl border border-slate-200 shadow-sm flex flex-col sm:flex-row items-start sm:items-center gap-2.5 sm:gap-4 hover:shadow-md hover:border-emerald-300 hover:-translate-y-0.5 transition-all cursor-pointer group">
        <div class="w-8 h-8 sm:w-12 sm:h-12 rounded-xl bg-emerald-50 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white flex items-center justify-center transition-colors shrink-0 shadow-2xs">
            <i data-lucide="user-check" class="w-4 h-4 sm:w-6 sm:h-6"></i>
        </div>
        <div class="min-w-0">
            <div class="text-[10px] sm:text-xs font-semibold text-slate-500 uppercase tracking-wider group-hover:text-emerald-600 transition-colors truncate">Present Today</div>
            <div class="text-lg sm:text-2xl font-bold text-slate-900 mt-0.5"><?= $presentCount ?> <span class="text-[10px] sm:text-xs font-normal text-slate-400">/ <?= $teamSize ?></span></div>
        </div>
    </a>

    <a href="?page=tl-tasks" class="bg-white p-3.5 sm:p-5 rounded-2xl sm:rounded-3xl border border-slate-200 shadow-sm flex flex-col sm:flex-row items-start sm:items-center gap-2.5 sm:gap-4 hover:shadow-md hover:border-amber-300 hover:-translate-y-0.5 transition-all cursor-pointer group">
        <div class="w-8 h-8 sm:w-12 sm:h-12 rounded-xl bg-amber-50 text-amber-600 group-hover:bg-amber-600 group-hover:text-white flex items-center justify-center transition-colors shrink-0 shadow-2xs">
            <i data-lucide="file-check" class="w-4 h-4 sm:w-6 sm:h-6"></i>
        </div>
        <div class="min-w-0">
            <div class="text-[10px] sm:text-xs font-semibold text-slate-500 uppercase tracking-wider group-hover:text-amber-600 transition-colors truncate">Work Review</div>
            <div class="text-lg sm:text-2xl font-bold text-slate-900 mt-0.5"><?= $reviewCount ?> <span class="text-[10px] sm:text-xs font-normal text-slate-400">items</span></div>
        </div>
    </a>

    <a href="?page=tl-leaves" class="bg-white p-3.5 sm:p-5 rounded-2xl sm:rounded-3xl border border-slate-200 shadow-sm flex flex-col sm:flex-row items-start sm:items-center gap-2.5 sm:gap-4 hover:shadow-md hover:border-purple-300 hover:-translate-y-0.5 transition-all cursor-pointer group">
        <div class="w-8 h-8 sm:w-12 sm:h-12 rounded-xl bg-purple-50 text-purple-600 group-hover:bg-purple-600 group-hover:text-white flex items-center justify-center transition-colors shrink-0 shadow-2xs">
            <i data-lucide="calendar-off" class="w-4 h-4 sm:w-6 sm:h-6"></i>
        </div>
        <div class="min-w-0">
            <div class="text-[10px] sm:text-xs font-semibold text-slate-500 uppercase tracking-wider group-hover:text-purple-600 transition-colors truncate">Leave Requests</div>
            <div class="text-lg sm:text-2xl font-bold text-slate-900 mt-0.5"><?= $pendingLeavesCount ?> <span class="text-[10px] sm:text-xs font-normal text-slate-400">pending</span></div>
        </div>
    </a>
</div>

<!-- Section 1: Live Team Attendance Roster -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-8">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                Live Team Attendance Status
            </h2>
            <p class="text-xs text-slate-500">Real-time attendance logs for your direct reporting members today.</p>
        </div>
        <a href="?page=tl-attendance" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">View Full History &rarr;</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($teamMembers as $m): 
            $isDismissed = !empty($m['is_dismissed']);
            $isOnline = !$isDismissed && !empty($m['clock_in']) && empty($m['clock_out']);
            $isClockedOut = !$isDismissed && !empty($m['clock_out']);
            $isLocked = !$isDismissed && !empty($m['is_escalated_locked']);
        ?>
            <div class="p-4 rounded-xl border <?= $isDismissed ? 'border-rose-400 bg-rose-50/70 shadow-xs' : ($isLocked ? 'border-rose-300 bg-rose-50/50 shadow-xs' : 'border-slate-200 bg-white hover:border-indigo-200 hover:shadow-sm') ?> transition flex flex-col justify-between gap-3">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="relative shrink-0">
                            <img src="<?= $m['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($m['name']) ?>" class="w-10 h-10 rounded-full object-cover ring-2 <?= $isDismissed ? 'ring-rose-600 opacity-75' : ($isLocked ? 'ring-rose-400' : 'ring-slate-100') ?>" alt="User">
                            <?php if ($isDismissed): ?>
                                <span class="absolute bottom-0 right-0 w-3.5 h-3.5 rounded-full bg-rose-700 ring-2 ring-white flex items-center justify-center text-[8px] text-white font-bold" title="Dismissed by HR">🚫</span>
                            <?php elseif ($isLocked): ?>
                                <span class="absolute bottom-0 right-0 w-3.5 h-3.5 rounded-full bg-rose-600 ring-2 ring-white flex items-center justify-center text-[8px] text-white font-bold" title="Locked by Referral">🔒</span>
                            <?php elseif ($isOnline): ?>
                                <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full bg-emerald-500 ring-2 ring-white" title="Online & Working"></span>
                            <?php elseif ($isClockedOut): ?>
                                <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full bg-slate-400 ring-2 ring-white" title="Clocked Out"></span>
                            <?php else: ?>
                                <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full bg-slate-300 ring-2 ring-white" title="Offline / Not In"></span>
                            <?php endif; ?>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-slate-900 truncate <?= $isDismissed ? 'line-through text-slate-500' : '' ?>"><?= htmlspecialchars($m['name']) ?></div>
                            <div class="text-[11px] text-slate-500 truncate"><?= htmlspecialchars($m['designation']) ?></div>
                        </div>
                    </div>

                    <div class="shrink-0 text-right">
                        <?php if ($isDismissed): ?>
                            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-600 text-white shadow-2xs">🚫 Dismissed by HR</span>
                        <?php elseif (!empty($m['hr_warning_message'])): ?>
                            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300">⚠️ Warning Issued</span>
                        <?php elseif ($isLocked): ?>
                            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200">🔒 Login Blocked</span>
                        <?php elseif ($m['clock_in']): ?>
                            <?= getStatusBadge($m['attendance_status'] ?: 'present') ?>
                            <div class="text-[10px] text-slate-400 font-mono mt-0.5">In: <?= formatTime($m['clock_in']) ?></div>
                        <?php else: ?>
                            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-50 text-rose-600 border border-rose-100">Not In</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Footer Status & Action -->
                <div class="pt-2.5 border-t <?= ($isDismissed || $isLocked) ? 'border-rose-200/80' : 'border-slate-100' ?> flex items-center justify-between text-xs">
                    <div class="flex items-center gap-1.5 text-[11px]">
                        <?php if ($isDismissed): ?>
                            <span class="w-2 h-2 rounded-full bg-rose-600"></span>
                            <span class="font-bold text-rose-800">Terminated from Company</span>
                        <?php elseif ($isLocked): ?>
                            <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                            <span class="font-bold text-rose-700">Referred to HR</span>
                        <?php elseif ($isOnline): ?>
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="font-bold text-emerald-700">Active Session</span>
                        <?php elseif ($isClockedOut): ?>
                            <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                            <span class="text-slate-500 font-medium">Shift Ended (<?= formatTime($m['clock_out']) ?>)</span>
                        <?php else: ?>
                            <span class="w-2 h-2 rounded-full bg-slate-300"></span>
                            <span class="text-slate-400 font-medium">Offline</span>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <?php if ($isDismissed): ?>
                            <span class="text-[10px] font-bold text-rose-700 bg-rose-100 px-2 py-1 rounded-lg">Access Terminated</span>
                        <?php elseif ($isLocked): ?>
                            <form action="?action=unlock-employee" method="POST" onsubmit="return confirm('Allow <?= htmlspecialchars($m['name']) ?> to log in and punch in again?');" class="m-0 p-0">
                                <input type="hidden" name="employee_id" value="<?= $m['id'] ?>">
                                <button type="submit" class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 hover:text-emerald-800 bg-emerald-100 hover:bg-emerald-200 px-2.5 py-1 rounded-lg border border-emerald-300 transition" title="Allow this employee to log in">
                                    <i data-lucide="unlock" class="w-3.5 h-3.5 text-emerald-600"></i> Allow Login
                                </button>
                            </form>
                        <?php else: ?>
                            <button type="button" @click="selectedEmpId = '<?= $m['id'] ?>'; selectedEmpName = '<?= addslashes($m['name']) ?>'; escalateModalOpen = true" class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-700 hover:text-amber-800 hover:bg-amber-50 px-2 py-1 rounded-lg border border-amber-200 transition" title="Report / Refer this employee to HR">
                                <i data-lucide="flag" class="w-3 h-3 text-amber-600"></i> Refer to HR
                            </button>
                            <?php if ($isOnline): ?>
                                <form action="?action=force-logout-user" method="POST" onsubmit="return confirm('Force logout <?= htmlspecialchars($m['name']) ?> and terminate active session?');" class="m-0 p-0">
                                    <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                                    <button type="submit" class="inline-flex items-center gap-1 text-[11px] font-bold text-rose-600 hover:text-rose-700 hover:bg-rose-50 px-2 py-1 rounded-lg border border-rose-200 transition" title="Log out this teammate">
                                        <i data-lucide="log-out" class="w-3 h-3"></i> End Session
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Section 2: Two Columns (Work Submissions & Leave Requests) -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Left: Submissions Requiring TL Review -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                <i data-lucide="check-circle" class="w-5 h-5 text-indigo-600"></i>
                Work Submissions (Review Queue)
            </h2>
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700"><?= count($pendingSubmissions) ?> Pending</span>
        </div>

        <?php if (empty($pendingSubmissions)): ?>
            <div class="text-center py-10 text-slate-400">
                <i data-lucide="check-check" class="w-10 h-10 mx-auto text-slate-300 mb-2"></i>
                <p class="text-sm font-medium">All caught up! No submissions waiting for review.</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($pendingSubmissions as $sub): 
                    $subJsObj = [
                        'id' => (int)$sub['id'],
                        'task_id' => (int)$sub['task_id'],
                        'task_title' => (string)$sub['task_title'],
                        'project_name' => (string)($sub['project_name'] ?? 'General Project'),
                        'employee_name' => (string)$sub['employee_name'],
                        'submitted_at' => (string)$sub['submitted_at'],
                        'due_date' => (string)($sub['due_date'] ?? ''),
                        'submission_notes' => (string)($sub['notes'] ?? ''),
                        'attachment_url' => (string)($sub['attachment_url'] ?? ''),
                        'attachment_file' => (string)($sub['attachment_file'] ?? ''),
                        'attachment_type' => (string)($sub['attachment_type'] ?? '')
                    ];
                    $subJson = htmlspecialchars(json_encode($subJsObj), ENT_QUOTES, 'UTF-8');
                    $fileType = $sub['attachment_type'] ?? '';
                    $isImg = ($fileType === 'image' || preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $sub['attachment_file'] ?? ''));
                    $isVid = ($fileType === 'video' || preg_match('/\.(mp4|webm|mov|mkv)$/i', $sub['attachment_file'] ?? ''));
                ?>
                    <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50/70 hover:bg-white hover:border-indigo-300 hover:shadow-md transition flex flex-col justify-between gap-3">
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-[10px]">
                                        <?= strtoupper(substr($sub['employee_name'], 0, 2)) ?>
                                    </div>
                                    <span class="text-xs font-bold text-slate-900"><?= htmlspecialchars($sub['employee_name']) ?></span>
                                </div>
                                <?= getPriorityBadge($sub['priority']) ?>
                            </div>

                            <h3 class="text-sm font-bold text-slate-900 leading-snug"><?= htmlspecialchars($sub['task_title']) ?></h3>
                            <div class="text-[11px] text-slate-500 font-medium mt-0.5"><?= htmlspecialchars($sub['project_name'] ?? 'General Project') ?></div>

                            <!-- Media proof indicator badges -->
                            <div class="flex items-center gap-2 mt-2.5 flex-wrap">
                                <?php if ($isImg): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-sky-50 text-sky-700 border border-sky-200 text-[10px] font-bold">
                                        <i data-lucide="image" class="w-3 h-3"></i> 🖼️ Photo Proof Attached
                                    </span>
                                <?php elseif ($isVid): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-purple-50 text-purple-700 border border-purple-200 text-[10px] font-bold">
                                        <i data-lucide="video" class="w-3 h-3"></i> 🎥 Video Demo Attached
                                    </span>
                                <?php elseif (!empty($sub['attachment_file'])): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-200 text-[10px] font-bold">
                                        <i data-lucide="file" class="w-3 h-3"></i> 📎 File Attached
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($sub['attachment_url'])): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 text-[10px] font-bold">
                                        <i data-lucide="link" class="w-3 h-3"></i> 🔗 URL Link Attached
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($sub['notes'])): ?>
                                <p class="text-xs text-slate-600 mt-2 bg-white p-2.5 rounded-xl border border-slate-200/70 italic line-clamp-2">"<?= htmlspecialchars($sub['notes']) ?>"</p>
                            <?php endif; ?>
                        </div>

                        <!-- Action: Open Full Proof Review Modal -->
                        <div class="pt-3 border-t border-slate-200/80 flex items-center justify-between gap-2">
                            <span class="text-[11px] text-slate-400 font-mono">🕒 <?= date('d M, h:i A', strtotime($sub['submitted_at'])) ?></span>
                            <button 
                                type="button" 
                                @click='activeSubmission = <?= $subJson ?>; showRejectInput = false; rejectRemarks = ""; reviewModalOpen = true'
                                class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-xs hover:shadow transition inline-flex items-center gap-1.5 cursor-pointer"
                            >
                                <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                <span>Inspect Proof & Review</span>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right: Pending Leave Requests -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                <i data-lucide="calendar-heart" class="w-5 h-5 text-purple-600"></i>
                Team Leave Requests
            </h2>
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-purple-50 text-purple-700"><?= count($pendingLeaves) ?> Pending</span>
        </div>

        <?php if (empty($pendingLeaves)): ?>
            <div class="text-center py-10 text-slate-400">
                <i data-lucide="calendar-check" class="w-10 h-10 mx-auto text-slate-300 mb-2"></i>
                <p class="text-sm font-medium">No pending leave applications from your team.</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($pendingLeaves as $l): ?>
                    <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/60 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between">
                                <div class="text-sm font-bold text-slate-800"><?= htmlspecialchars($l['employee_name']) ?></div>
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-purple-100 text-purple-700"><?= htmlspecialchars($l['leave_type_name']) ?></span>
                            </div>
                            <div class="text-xs text-slate-500 mt-1 flex items-center gap-1.5">
                                <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400"></i>
                                <span><?= formatDate($l['start_date']) ?> &rarr; <?= formatDate($l['end_date']) ?> (<strong><?= $l['total_days'] ?> day(s)</strong>)</span>
                            </div>
                            <div class="text-xs text-slate-600 mt-2 bg-white p-2.5 rounded-lg border border-slate-200/80">
                                <strong>Reason:</strong> <?= htmlspecialchars($l['reason']) ?>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="mt-4 flex items-center justify-end gap-2">
                            <form action="?action=action-leave" method="POST" class="inline">
                                <input type="hidden" name="leave_id" value="<?= $l['id'] ?>">
                                <input type="hidden" name="action" value="rejected">
                                <input type="hidden" name="action_remarks" value="Rejected by TL">
                                <button type="submit" class="px-3 py-1.5 rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50 text-xs font-semibold transition">
                                    Reject
                                </button>
                            </form>
                            <form action="?action=action-leave" method="POST" class="inline">
                                <input type="hidden" name="leave_id" value="<?= $l['id'] ?>">
                                <input type="hidden" name="action" value="approved">
                                <input type="hidden" name="action_remarks" value="Approved by TL">
                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm transition flex items-center gap-1">
                                    <i data-lucide="check" class="w-3.5 h-3.5"></i> Approve Leave
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Section 3: Escalated Cases & Referrals to HR -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mt-8">
    <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
        <div>
            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                <i data-lucide="shield-alert" class="w-5 h-5 text-rose-600"></i>
                Employee Escalations & HR Referrals (<?= count($myEscalations) ?>)
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">Complaints, conduct reports, or performance issues escalated by you to HR for formal intervention.</p>
        </div>
        <button type="button" @click="selectedEmpId = ''; selectedEmpName = ''; escalateModalOpen = true" class="px-3.5 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold transition flex items-center gap-1.5 border border-rose-200">
            <i data-lucide="plus-circle" class="w-3.5 h-3.5 text-rose-600"></i> New Escalation
        </button>
    </div>

    <?php if (empty($myEscalations)): ?>
        <div class="text-center py-8 text-slate-400 bg-slate-50 rounded-xl">
            <i data-lucide="check-circle" class="w-8 h-8 mx-auto text-emerald-400 mb-1.5"></i>
            <p class="text-xs font-bold text-slate-600">No active complaints or escalations to HR.</p>
            <p class="text-[11px] text-slate-400">If any team member violates policies or needs HR action, click "Refer / Escalate to HR" above.</p>
        </div>
    <?php else: ?>
        <div class="space-y-3.5">
            <?php foreach ($myEscalations as $esc): ?>
                <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 flex flex-col md:flex-row md:items-start justify-between gap-4">
                    <div class="space-y-1.5 flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-bold text-sm text-slate-900"><?= htmlspecialchars($esc['employee_name']) ?></span>
                            <span class="text-xs text-slate-400 font-medium">(<?= htmlspecialchars($esc['employee_designation']) ?>)</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $esc['severity'] === 'urgent' ? 'bg-rose-100 text-rose-700 border border-rose-200' : ($esc['severity'] === 'high' ? 'bg-amber-100 text-amber-700 border border-amber-200' : 'bg-slate-100 text-slate-600') ?>">
                                <?= $esc['severity'] ?> severity
                            </span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-indigo-50 text-indigo-700 border border-indigo-100">
                                <?= htmlspecialchars(str_replace('_', ' ', $esc['category'])) ?>
                            </span>
                        </div>
                        <h4 class="text-xs font-bold text-slate-800"><?= htmlspecialchars($esc['title']) ?></h4>
                        <p class="text-xs text-slate-600"><?= nl2br(htmlspecialchars($esc['description'])) ?></p>
                        <div class="text-[10px] text-slate-400 font-mono">
                            Escalated on <?= date('d M Y, h:i A', strtotime($esc['created_at'])) ?>
                        </div>

                        <?php if (!empty($esc['hr_response'])): ?>
                            <div class="mt-2 p-2.5 rounded-lg bg-indigo-50/80 border border-indigo-100 text-xs">
                                <div class="font-bold text-indigo-900 flex items-center gap-1">
                                    <i data-lucide="message-square" class="w-3.5 h-3.5 text-indigo-600"></i> HR Response (from <?= htmlspecialchars($esc['hr_name'] ?: 'HR') ?>):
                                </div>
                                <p class="text-indigo-800 mt-0.5"><?= nl2br(htmlspecialchars($esc['hr_response'])) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="shrink-0 text-right space-y-2">
                        <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold uppercase <?= $esc['status'] === 'resolved' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : ($esc['status'] === 'action_taken' ? 'bg-blue-50 text-blue-700 border border-blue-200' : ($esc['status'] === 'dismissed' ? 'bg-rose-600 text-white shadow-2xs' : 'bg-amber-50 text-amber-700 border border-amber-200')) ?>">
                            <?= $esc['status'] === 'dismissed' ? '🚫 Dismissed by HR' : str_replace('_', ' ', $esc['status']) ?>
                        </span>
                        <div>
                            <?php if ($esc['status'] === 'dismissed'): ?>
                                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-rose-600 bg-rose-50 px-2 py-1 rounded-lg border border-rose-200">
                                    <i data-lucide="x-circle" class="w-3.5 h-3.5"></i> Offboarding
                                </span>
                            <?php elseif (!empty($esc['is_escalated_locked'])): ?>
                                <form action="?action=unlock-employee" method="POST" onsubmit="return confirm('Allow <?= htmlspecialchars($esc['employee_name']) ?> to log in and punch in again?');">
                                    <input type="hidden" name="employee_id" value="<?= $esc['employee_id'] ?>">
                                    <button type="submit" class="inline-flex items-center gap-1 text-xs font-bold text-emerald-700 hover:text-emerald-800 bg-emerald-100 hover:bg-emerald-200 px-3 py-1.5 rounded-lg border border-emerald-300 transition shadow-2xs">
                                        <i data-lucide="unlock" class="w-3.5 h-3.5 text-emerald-600"></i> Allow Login
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-600">
                                    <i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Access Allowed
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Escalate Employee / Complaint to HR -->
<div x-show="escalateModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" x-cloak>
    <div @click.away="escalateModalOpen = false" class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold">
                    <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900">Refer / Escalate Employee to HR</h3>
                    <p class="text-xs text-slate-500">File a formal incident report or disciplinary referral with HR.</p>
                </div>
            </div>
            <button @click="escalateModalOpen = false" class="text-slate-400 hover:text-slate-600">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form action="?action=create-escalation" method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Select Team Member *</label>
                <select name="employee_id" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs text-slate-900 focus:bg-white focus:ring-2 focus:ring-rose-500">
                    <option value="">-- Choose Employee --</option>
                    <?php foreach ($teamMembers as $m): ?>
                        <option :selected="selectedEmpId == '<?= $m['id'] ?>'" value="<?= $m['id'] ?>">
                            <?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['designation']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Category *</label>
                    <select name="category" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs text-slate-900 focus:bg-white focus:ring-2 focus:ring-rose-500">
                        <option value="attendance">⏰ Attendance / Tardiness</option>
                        <option value="performance">📉 Low Performance / Missed Deadlines</option>
                        <option value="behavior">⚠️ Insubordination / Behavior</option>
                        <option value="policy_violation">🚫 Policy Violation / Misconduct</option>
                        <option value="other">📝 Other Concern</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Severity *</label>
                    <select name="severity" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs text-slate-900 focus:bg-white focus:ring-2 focus:ring-rose-500">
                        <option value="low">🟡 Low (Informational)</option>
                        <option value="medium" selected>🟠 Medium (Requires Attention)</option>
                        <option value="high">🔴 High (Serious Incident)</option>
                        <option value="urgent">🚨 Urgent (Immediate HR Intervention)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Incident Title / Summary *</label>
                <input type="text" name="title" required placeholder="e.g., Continuous unauthorized absence or missed sprint deliverable" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:bg-white focus:ring-2 focus:ring-rose-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Detailed Description & Evidence *</label>
                <textarea name="description" rows="4" required placeholder="Describe the specifics of what happened, dates, warnings given verbally, or requested HR action..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs text-slate-900 focus:bg-white focus:ring-2 focus:ring-rose-500"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" @click="escalateModalOpen = false" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold transition shadow-sm inline-flex items-center gap-1.5">
                    <i data-lucide="send" class="w-4 h-4"></i> Submit Escalation to HR
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Review Submitted Deliverable with Rich Media & Cloud Drive Redirect -->
<div x-show="reviewModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" x-cloak>
    <div @click.away="reviewModalOpen = false" class="bg-white rounded-3xl max-w-xl w-full p-6 shadow-2xl border border-slate-200 space-y-4 max-h-[92vh] overflow-y-auto">
        <!-- Header -->
        <div class="flex items-center justify-between pb-3.5 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <i data-lucide="file-check-2" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Review Submitted Deliverable</h3>
                    <p class="text-xs text-slate-500 font-medium" x-text="activeSubmission ? activeSubmission.employee_name : ''"></p>
                </div>
            </div>
            <button type="button" @click="reviewModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Task & Project Info Card -->
        <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200/80 space-y-1.5">
            <div class="flex items-center justify-between text-[11px]">
                <span class="font-extrabold text-indigo-600 uppercase tracking-wider text-[10px]" x-text="activeSubmission ? activeSubmission.project_name : ''"></span>
                <span class="text-slate-400 font-mono" x-text="activeSubmission ? 'Submitted: ' + activeSubmission.submitted_at : ''"></span>
            </div>
            <h4 class="text-base font-bold text-slate-900" x-text="activeSubmission ? activeSubmission.task_title : ''"></h4>
            <div class="flex items-center gap-3 text-xs text-slate-500 pt-1 border-t border-slate-200/60">
                <span class="flex items-center gap-1"><i data-lucide="user" class="w-3.5 h-3.5"></i> <strong class="text-slate-700" x-text="activeSubmission ? activeSubmission.employee_name : ''"></strong></span>
                <span x-show="activeSubmission && activeSubmission.due_date" class="flex items-center gap-1 font-mono text-[11px]"><i data-lucide="calendar" class="w-3.5 h-3.5"></i> Due: <span x-text="activeSubmission ? activeSubmission.due_date : ''"></span></span>
            </div>
        </div>

        <!-- Employee Submission Notes -->
        <div class="p-3.5 bg-emerald-50/50 rounded-2xl border border-emerald-200/70 space-y-1">
            <div class="flex items-center gap-1.5 text-emerald-800 text-[10px] font-bold uppercase tracking-wider">
                <i data-lucide="message-square" class="w-3.5 h-3.5"></i>
                <span>Employee Submission Notes</span>
            </div>
            <p class="text-xs text-slate-700 font-medium leading-relaxed whitespace-pre-wrap" x-text="activeSubmission && activeSubmission.submission_notes ? activeSubmission.submission_notes : 'No extra notes provided by the employee.'"></p>
        </div>

        <!-- Uploaded Media / Photos / Videos / Files (View Only - Redirect to Drive for Downloads) -->
        <template x-if="activeSubmission && activeSubmission.attachment_file">
            <div class="p-4 bg-indigo-50/60 rounded-xl border border-indigo-200 space-y-2.5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <i data-lucide="file-check" class="w-3.5 h-3.5 text-indigo-600"></i>
                        <span class="text-[10px] font-bold text-indigo-700 uppercase tracking-wider">
                            Submitted Deliverable Proof (<span x-text="activeSubmission.attachment_type || 'File'"></span>)
                        </span>
                    </div>
                    <a href="?page=tech-drive" class="inline-flex items-center gap-1 text-[11px] font-bold text-indigo-600 hover:text-indigo-800 transition">
                        <i data-lucide="hard-drive" class="w-3.5 h-3.5"></i> Open in Tech Cloud Drive &rarr;
                    </a>
                </div>

                <!-- 1. PHOTO / IMAGE PREVIEW (View Only) -->
                <template x-if="activeSubmission.attachment_type === 'image' || (activeSubmission.attachment_file && activeSubmission.attachment_file.match(/\.(jpg|jpeg|png|webp|gif)$/i))">
                    <div class="rounded-xl overflow-hidden border border-indigo-200 bg-white p-1.5 text-center shadow-xs">
                        <a :href="activeSubmission.attachment_file" target="_blank" title="Click to open full resolution image">
                            <img :src="activeSubmission.attachment_file" class="max-h-72 w-auto mx-auto rounded-lg object-contain hover:opacity-95 transition" alt="Task Deliverable Proof">
                        </a>
                        <div class="flex items-center justify-between px-2 pt-2 text-[11px] text-slate-500">
                            <span>🔍 Click image to view full resolution</span>
                            <a href="?page=tech-drive" class="text-indigo-600 font-bold hover:underline">Download from Cloud Drive &rarr;</a>
                        </div>
                    </div>
                </template>

                <!-- 2. VIDEO STREAMING PLAYER (View Only) -->
                <template x-if="activeSubmission.attachment_type === 'video' || (activeSubmission.attachment_file && activeSubmission.attachment_file.match(/\.(mp4|webm|mov|mkv)$/i))">
                    <div class="space-y-1.5">
                        <div class="rounded-xl overflow-hidden border border-indigo-200 bg-black shadow-md">
                            <video :src="activeSubmission.attachment_file" controls class="w-full max-h-72 rounded-xl bg-black" preload="metadata">
                                Your browser does not support video playback.
                            </video>
                        </div>
                        <div class="flex items-center justify-end text-[11px] text-slate-500 px-1">
                            <a href="?page=tech-drive" class="text-indigo-600 font-bold hover:underline">Download video from Tech Cloud Drive &rarr;</a>
                        </div>
                    </div>
                </template>

                <!-- 3. OTHER DOCUMENT / ARCHIVE / PDF (View & Redirect to Drive) -->
                <template x-if="activeSubmission.attachment_type === 'file' || (!activeSubmission.attachment_file.match(/\.(jpg|jpeg|png|webp|gif|mp4|webm|mov)$/i))">
                    <div class="p-3 bg-white rounded-xl border border-indigo-200 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 text-xs font-bold text-slate-800 truncate">
                                <i data-lucide="file-text" class="w-4 h-4 text-indigo-600 shrink-0"></i>
                                <span class="truncate" x-text="activeSubmission.attachment_file.split('/').pop()"></span>
                            </div>
                            <a :href="activeSubmission.attachment_file" target="_blank" class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-lg text-xs font-bold shrink-0 transition inline-flex items-center gap-1">
                                <i data-lucide="eye" class="w-3.5 h-3.5"></i> Preview / View
                            </a>
                        </div>
                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                            <span>To download or manage this file:</span>
                            <a href="?page=tech-drive" class="text-indigo-600 font-bold hover:underline inline-flex items-center gap-1">
                                <i data-lucide="hard-drive" class="w-3 h-3"></i> Go to Tech Cloud Drive &rarr;
                            </a>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        <!-- Attachment / Deliverable Link -->
        <template x-if="activeSubmission && activeSubmission.attachment_url">
            <div class="p-4 bg-indigo-50/60 rounded-xl border border-indigo-200">
                <div class="flex items-center gap-1.5 mb-2">
                    <i data-lucide="paperclip" class="w-3.5 h-3.5 text-indigo-600"></i>
                    <span class="text-[10px] font-bold text-indigo-700 uppercase tracking-wider">Attached Deliverable / Project URL</span>
                </div>
                <div class="flex items-center justify-between gap-2 p-2.5 bg-white rounded-lg border border-indigo-200">
                    <div class="flex items-center gap-2 text-xs text-indigo-900 font-bold truncate overflow-hidden">
                        <i data-lucide="external-link" class="w-4 h-4 text-indigo-600 shrink-0"></i>
                        <span class="truncate" x-text="activeSubmission.attachment_url"></span>
                    </div>
                    <a :href="activeSubmission.attachment_url" target="_blank" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-[10px] font-bold shrink-0 transition">Open Link &rarr;</a>
                </div>
            </div>
        </template>

        <!-- Decision Section: Approve vs Request Changes -->
        <div class="pt-3 border-t border-slate-200 space-y-3">
            <!-- Request Changes Expandable Box -->
            <div x-show="showRejectInput" class="p-3.5 bg-rose-50 rounded-2xl border border-rose-200 space-y-2">
                <label class="block text-[11px] font-bold text-rose-800 uppercase">Specify Required Changes & Feedback for Employee *</label>
                <textarea x-model="rejectRemarks" rows="3" placeholder="Explain what is missing, bugs found, or what changes are required before approval..." class="w-full bg-white border border-rose-300 rounded-xl p-2.5 text-xs text-slate-900 focus:ring-2 focus:ring-rose-500"></textarea>
                <div class="flex items-center justify-end gap-2">
                    <button type="button" @click="showRejectInput = false" class="px-3 py-1 text-xs text-slate-500 hover:text-slate-700 font-semibold">Cancel</button>
                    <form action="?action=review-submission" method="POST" class="inline">
                        <input type="hidden" name="submission_id" :value="activeSubmission ? activeSubmission.id : ''">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="remarks" :value="rejectRemarks">
                        <button type="submit" :disabled="!rejectRemarks.trim()" class="px-4 py-1.5 bg-rose-600 hover:bg-rose-700 disabled:opacity-50 text-white text-xs font-bold rounded-xl shadow-xs transition">
                            Confirm & Request Changes
                        </button>
                    </form>
                </div>
            </div>

            <!-- Action Buttons Bar -->
            <div x-show="!showRejectInput" class="flex items-center justify-between gap-3">
                <button type="button" @click="showRejectInput = true" class="px-4 py-2.5 rounded-xl border border-slate-300 hover:border-rose-300 hover:bg-rose-50 text-slate-700 hover:text-rose-700 text-xs font-bold transition inline-flex items-center gap-1.5">
                    <i data-lucide="rotate-ccw" class="w-4 h-4 text-rose-600"></i>
                    <span>Request Changes</span>
                </button>

                <form action="?action=review-submission" method="POST" class="inline">
                    <input type="hidden" name="submission_id" :value="activeSubmission ? activeSubmission.id : ''">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-sm inline-flex items-center gap-1.5">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        <span>Approve & Mark Completed</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

</div> <!-- End x-data wrapper -->
