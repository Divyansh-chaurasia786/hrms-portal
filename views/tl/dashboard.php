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
$isBDATeam = (stripos($user['department_name'] ?? '', 'BDA') !== false || stripos($user['department_name'] ?? '', 'Calling') !== false || stripos($user['designation'] ?? '', 'BDA') !== false || stripos($user['designation'] ?? '', 'Calling') !== false);
$bdaStats = [
    'total_leads' => 0,
    'today_calls' => 0,
    'today_converted' => 0,
    'today_interested' => 0
];
if ($isBDATeam) {
    try {
        $bdaStats['total_leads'] = (int)$db->query("SELECT COUNT(*) FROM calling_leads")->fetchColumn();
        $bdaStats['today_calls'] = (int)$db->query("SELECT COUNT(*) FROM call_logs WHERE call_date = '$today'")->fetchColumn();
        $bdaStats['today_converted'] = (int)$db->query("SELECT COUNT(*) FROM call_logs WHERE call_date = '$today' AND disposition = 'converted'")->fetchColumn();
        $bdaStats['today_interested'] = (int)$db->query("SELECT COUNT(*) FROM call_logs WHERE call_date = '$today' AND disposition = 'interested'")->fetchColumn();
    } catch(Exception $e) {}
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

// Fetch UNREAD official HR Directives for this TL
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

$tlDesig = !empty($user['designation']) ? $user['designation'] : 'Team Lead';
$isTLSupport = (stripos($tlDesig, 'tl support') !== false || stripos($tlDesig, 'support tl') !== false);
?>

<div x-data="{ escalateModalOpen: false, selectedEmpId: '', selectedEmpName: '', reviewModalOpen: false, activeSubmission: null, showRejectInput: false, rejectRemarks: '' }" class="space-y-6">

    <!-- 🔔 Unread HR Directive Alert -->
    <?php if (!empty($unreadFeedbacks)): ?>
        <div class="space-y-3">
            <?php foreach ($unreadFeedbacks as $fb): ?>
                <div class="p-4 rounded-2xl bg-slate-900 text-white border border-indigo-500/40 shadow-xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-start gap-3.5">
                        <div class="w-10 h-10 rounded-xl bg-indigo-600/30 text-indigo-400 flex items-center justify-center shrink-0 mt-0.5 border border-indigo-500/30">
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

    <!-- 🌟 UNIFIED EXECUTIVE COMMAND HERO (Sleek Modern Gradient) -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 text-white p-6 sm:p-8 border border-indigo-500/20 shadow-xl">
        <!-- Ambient Glow Decorators -->
        <div class="absolute -right-16 -top-16 w-64 h-64 bg-indigo-500/15 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -left-16 -bottom-16 w-64 h-64 bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
            <!-- Left: TL Profile & Status -->
            <div class="flex items-start sm:items-center gap-4">
                <div class="relative shrink-0">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-500 text-white font-extrabold text-xl sm:text-2xl flex items-center justify-center shadow-lg shadow-indigo-600/30 border border-white/20">
                        <?= strtoupper(substr($user['name'] ?? 'TL', 0, 2)) ?>
                    </div>
                    <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-emerald-500 rounded-full border-2 border-slate-900"></div>
                </div>
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                            Welcome back, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?> 👋
                        </h1>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wide bg-indigo-500/20 text-indigo-300 border border-indigo-400/30">
                            <?= htmlspecialchars($tlDesig) ?>
                        </span>
                    </div>
                    <p class="text-xs sm:text-sm text-slate-300 mt-1 flex items-center gap-2 flex-wrap">
                        <span><?= htmlspecialchars($user['department_name'] ?? 'Business Development') ?></span>
                        <span class="text-slate-500">•</span>
                        <span class="text-emerald-400 font-semibold flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span> Live Operations Active
                        </span>
                    </p>
                </div>
            </div>

            <!-- Right: High-Priority Executive Actions -->
            <div class="flex items-center gap-2.5 flex-wrap">
                <?php if ($isBDATeam): ?>
                    <a href="?page=calling-manage" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-indigo-600/30 transition flex items-center gap-2 cursor-pointer border border-indigo-400/30">
                        <i data-lucide="phone-forwarded" class="w-4 h-4 text-indigo-200"></i>
                        <span>BDA Lead CRM</span>
                    </a>
                    <a href="?page=calling-manage&modal=upload" class="px-3.5 py-2.5 bg-white/10 hover:bg-white/15 text-white text-xs font-bold rounded-xl backdrop-blur-md border border-white/15 transition flex items-center gap-1.5 cursor-pointer">
                        <i data-lucide="upload-cloud" class="w-4 h-4 text-indigo-300"></i>
                        <span>Ingest Leads</span>
                    </a>
                    <a href="?action=export-calling-history" class="px-3.5 py-2.5 bg-emerald-600/90 hover:bg-emerald-600 text-white text-xs font-bold rounded-xl shadow-sm transition flex items-center gap-1.5 cursor-pointer border border-emerald-400/30">
                        <i data-lucide="download" class="w-4 h-4 text-emerald-200"></i>
                        <span>Export Excel</span>
                    </a>
                <?php else: ?>
                    <a href="?page=tl-tasks&action=new" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-indigo-600/30 transition flex items-center gap-2 cursor-pointer border border-indigo-400/30">
                        <i data-lucide="plus-circle" class="w-4 h-4 text-indigo-200"></i>
                        <span>Assign Task</span>
                    </a>
                    <button type="button" @click="selectedEmpId = ''; selectedEmpName = ''; escalateModalOpen = true" class="px-3.5 py-2.5 bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 text-xs font-bold rounded-xl backdrop-blur-md border border-rose-500/30 transition flex items-center gap-1.5 cursor-pointer">
                        <i data-lucide="shield-alert" class="w-4 h-4 text-rose-400"></i>
                        <span>Refer to HR</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isBDATeam): ?>
        <!-- 🎯 BDA Telecalling Pipeline Glassmorphic Metrics (Inside Hero) -->
        <div class="mt-6 pt-6 border-t border-white/10 grid grid-cols-2 sm:grid-cols-4 gap-3.5">
            <div class="bg-white/5 backdrop-blur-md p-4 rounded-2xl border border-white/10 hover:bg-white/10 transition group">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-slate-300 uppercase tracking-wider">Total Leads Pool</span>
                    <i data-lucide="folder-kanban" class="w-4 h-4 text-indigo-300 group-hover:scale-110 transition-transform"></i>
                </div>
                <div class="text-2xl font-black text-white mt-1"><?= number_format($bdaStats['total_leads']) ?></div>
                <span class="text-[10px] text-indigo-300 font-semibold mt-0.5 block">Active Pipeline</span>
            </div>

            <div class="bg-white/5 backdrop-blur-md p-4 rounded-2xl border border-white/10 hover:bg-white/10 transition group">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-slate-300 uppercase tracking-wider">Calls Made Today</span>
                    <i data-lucide="phone-call" class="w-4 h-4 text-blue-300 group-hover:scale-110 transition-transform"></i>
                </div>
                <div class="text-2xl font-black text-blue-300 mt-1"><?= number_format($bdaStats['today_calls']) ?></div>
                <span class="text-[10px] text-blue-200 font-semibold mt-0.5 block">Team Dialing Volume</span>
            </div>

            <div class="bg-white/5 backdrop-blur-md p-4 rounded-2xl border border-white/10 hover:bg-white/10 transition group">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-slate-300 uppercase tracking-wider">Interested Today</span>
                    <i data-lucide="flame" class="w-4 h-4 text-amber-300 group-hover:scale-110 transition-transform"></i>
                </div>
                <div class="text-2xl font-black text-amber-300 mt-1"><?= number_format($bdaStats['today_interested']) ?></div>
                <span class="text-[10px] text-amber-200 font-semibold mt-0.5 block">Hot Conversion Prospects</span>
            </div>

            <div class="bg-white/5 backdrop-blur-md p-4 rounded-2xl border border-white/10 hover:bg-white/10 transition group">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-slate-300 uppercase tracking-wider">Deals Closed</span>
                    <i data-lucide="trophy" class="w-4 h-4 text-emerald-300 group-hover:scale-110 transition-transform"></i>
                </div>
                <div class="text-2xl font-black text-emerald-300 mt-1"><?= number_format($bdaStats['today_converted']) ?></div>
                <span class="text-[10px] text-emerald-200 font-semibold mt-0.5 block">Won Today</span>
            </div>
        </div>
        <?php endif; ?>
    </div>

        

        <div class="flex items-center gap-2.5 relative z-10 shrink-0">
            <button type="button" onclick="triggerPwaInstall()" class="px-5 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white text-xs font-black rounded-xl shadow-lg shadow-emerald-600/30 transition flex items-center gap-2 cursor-pointer border border-emerald-400/30">
                <i data-lucide="download-cloud" class="w-4 h-4"></i>
                <span>Download / Install App</span>
            </button>
        </div>
    </div>

    <!-- 📊 TEAM VITALS & WORKFORCE METRICS -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <a href="?page=tl-attendance" class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-xs hover:shadow-md hover:border-indigo-300 hover:-translate-y-0.5 transition-all group">
            <div class="flex items-center justify-between">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white flex items-center justify-center transition-colors shadow-xs">
                    <i data-lucide="users" class="w-5 h-5"></i>
                </div>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 group-hover:bg-indigo-50 group-hover:text-indigo-700">Team</span>
            </div>
            <div class="mt-3">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Team Strength</div>
                <div class="text-2xl font-black text-slate-900 mt-0.5"><?= $teamSize ?> <span class="text-xs font-medium text-slate-400">members</span></div>
            </div>
        </a>

        <a href="?page=tl-attendance" class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-xs hover:shadow-md hover:border-emerald-300 hover:-translate-y-0.5 transition-all group">
            <div class="flex items-center justify-between">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white flex items-center justify-center transition-colors shadow-xs">
                    <i data-lucide="user-check" class="w-5 h-5"></i>
                </div>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700">Live</span>
            </div>
            <div class="mt-3">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Present Today</div>
                <div class="text-2xl font-black text-slate-900 mt-0.5"><?= $presentCount ?> <span class="text-xs font-medium text-slate-400">/ <?= $teamSize ?></span></div>
            </div>
        </a>

        <a href="?page=tl-tasks" class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-xs hover:shadow-md hover:border-amber-300 hover:-translate-y-0.5 transition-all group">
            <div class="flex items-center justify-between">
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 group-hover:bg-amber-600 group-hover:text-white flex items-center justify-center transition-colors shadow-xs">
                    <i data-lucide="file-check" class="w-5 h-5"></i>
                </div>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-50 text-amber-700">Review</span>
            </div>
            <div class="mt-3">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Work Review</div>
                <div class="text-2xl font-black text-slate-900 mt-0.5"><?= $reviewCount ?> <span class="text-xs font-medium text-slate-400">items</span></div>
            </div>
        </a>

        <a href="?page=tl-leaves" class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-xs hover:shadow-md hover:border-purple-300 hover:-translate-y-0.5 transition-all group">
            <div class="flex items-center justify-between">
                <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 group-hover:bg-purple-600 group-hover:text-white flex items-center justify-center transition-colors shadow-xs">
                    <i data-lucide="calendar" class="w-5 h-5"></i>
                </div>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-purple-50 text-purple-700">Leaves</span>
            </div>
            <div class="mt-3">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Leave Requests</div>
                <div class="text-2xl font-black text-slate-900 mt-0.5"><?= $pendingLeavesCount ?> <span class="text-xs font-medium text-slate-400">pending</span></div>
            </div>
        </a>
    </div>

        <!-- 🎂 INTEGRATED WORKFORCE BIRTHDAYS & CELEBRATIONS (NEXT 30 DAYS) -->
    <?php include __DIR__ . '/../partials/_upcoming_birthdays.php'; ?>

    <!-- 👥 TEAM LIVE ROSTER & RECENT ACTIVITIES -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left 2 Cols: Live Team Attendance & Status -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-xs p-5 sm:p-6 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        <i data-lucide="activity" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 text-sm">Live Team Attendance Roster</h3>
                        <p class="text-[11px] text-slate-400">Real-time punch in status for managed team</p>
                    </div>
                </div>
                <a href="?page=tl-attendance" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                    Full Attendance <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </a>
            </div>

            <?php if (empty($teamMembers)): ?>
                <div class="text-center py-10">
                    <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-2">
                        <i data-lucide="users" class="w-6 h-6"></i>
                    </div>
                    <p class="text-xs font-bold text-slate-600">No team members assigned yet</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Employees reporting to you will appear here.</p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-slate-100 max-h-96 overflow-y-auto">
                    <?php foreach ($teamMembers as $m): ?>
                        <div class="py-3 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-700 font-bold flex items-center justify-center text-xs shrink-0">
                                    <?= strtoupper(substr($m['name'], 0, 2)) ?>
                                </div>
                                <div class="min-w-0">
                                    <h4 class="text-xs font-bold text-slate-900 truncate"><?= htmlspecialchars($m['name']) ?></h4>
                                    <p class="text-[10px] text-slate-400 truncate"><?= htmlspecialchars($m['designation'] ?? 'Executive') ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <?php if (!empty($m['clock_in'])): ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> In: <?= date('h:i A', strtotime($m['clock_in'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                        Not Punched In
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Col: Quick Access Tools Hub -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5 sm:p-6 space-y-4">
            <div class="flex items-center gap-2.5 pb-3 border-b border-slate-100">
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <i data-lucide="layout-grid" class="w-4 h-4"></i>
                </div>
                <div>
                    <h3 class="font-bold text-slate-900 text-sm">Quick Operations Hub</h3>
                    <p class="text-[11px] text-slate-400">Core management shortcuts</p>
                </div>
            </div>

            <div class="space-y-2.5">
                <?php if ($isBDATeam): ?>
                    <a href="?page=calling-manage" class="w-full p-3 rounded-xl bg-slate-50 hover:bg-indigo-50/80 border border-slate-200 hover:border-indigo-200 flex items-center justify-between gap-3 transition group">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center shrink-0">
                                <i data-lucide="phone" class="w-4 h-4"></i>
                            </div>
                            <div class="text-left">
                                <div class="text-xs font-bold text-slate-900 group-hover:text-indigo-700">BDA Lead Management</div>
                                <div class="text-[10px] text-slate-400">Allocate & distribute lead pool</div>
                            </div>
                        </div>
                        <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400 group-hover:text-indigo-600 transition-transform group-hover:translate-x-0.5"></i>
                    </a>
                <?php endif; ?>

                <a href="?page=tl-attendance" class="w-full p-3 rounded-xl bg-slate-50 hover:bg-emerald-50/80 border border-slate-200 hover:border-emerald-200 flex items-center justify-between gap-3 transition group">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center shrink-0">
                            <i data-lucide="user-check" class="w-4 h-4"></i>
                        </div>
                        <div class="text-left">
                            <div class="text-xs font-bold text-slate-900 group-hover:text-emerald-700">Team Attendance Audit</div>
                            <div class="text-[10px] text-slate-400">Review shifts & hours</div>
                        </div>
                    </div>
                    <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400 group-hover:text-emerald-600 transition-transform group-hover:translate-x-0.5"></i>
                </a>

                <a href="?page=tl-tasks" class="w-full p-3 rounded-xl bg-slate-50 hover:bg-amber-50/80 border border-slate-200 hover:border-amber-200 flex items-center justify-between gap-3 transition group">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-600 text-white flex items-center justify-center shrink-0">
                            <i data-lucide="check-square" class="w-4 h-4"></i>
                        </div>
                        <div class="text-left">
                            <div class="text-xs font-bold text-slate-900 group-hover:text-amber-700">Task Review & Assign</div>
                            <div class="text-[10px] text-slate-400">Sprint tasks & submissions</div>
                        </div>
                    </div>
                    <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400 group-hover:text-amber-600 transition-transform group-hover:translate-x-0.5"></i>
                </a>

                <a href="?page=tl-leaves" class="w-full p-3 rounded-xl bg-slate-50 hover:bg-purple-50/80 border border-slate-200 hover:border-purple-200 flex items-center justify-between gap-3 transition group">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-purple-600 text-white flex items-center justify-center shrink-0">
                            <i data-lucide="calendar" class="w-4 h-4"></i>
                        </div>
                        <div class="text-left">
                            <div class="text-xs font-bold text-slate-900 group-hover:text-purple-700">Team Leave Approvals</div>
                            <div class="text-[10px] text-slate-400"><?= $pendingLeavesCount ?> applications pending</div>
                        </div>
                    </div>
                    <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400 group-hover:text-purple-600 transition-transform group-hover:translate-x-0.5"></i>
                </a>
            </div>
        </div>

    </div>

    <!-- 🚨 HR ESCALATION MODAL (If triggered) -->
    <div x-show="escalateModalOpen" x-cloak class="fixed inset-0 z-50 bg-slate-950/75 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="escalateModalOpen = false" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-rose-100 text-rose-700 flex items-center justify-center font-bold">
                        <i data-lucide="shield-alert" class="w-4 h-4"></i>
                    </div>
                    <h3 class="font-bold text-sm text-slate-900">Escalate / Refer Employee to HR</h3>
                </div>
                <button type="button" @click="escalateModalOpen = false" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>

            <form action="?action=tl-escalate-employee" method="POST" class="space-y-4 pt-1">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Select Employee *</label>
                    <select name="employee_id" required class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-rose-500">
                        <option value="">-- Choose Team Member --</option>
                        <?php foreach ($teamMembers as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['designation'] ?? 'Executive') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Escalation Reason *</label>
                    <textarea name="reason" rows="3" required placeholder="Describe performance or disciplinary issue..." class="w-full bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-xs font-medium focus:ring-2 focus:ring-rose-500"></textarea>
                </div>

                <div class="flex items-center gap-2 bg-amber-50 p-3 rounded-xl border border-amber-200">
                    <input type="checkbox" name="lock_account" value="1" id="lock_acc" class="rounded text-rose-600 focus:ring-rose-500">
                    <label for="lock_acc" class="text-xs font-semibold text-amber-900 cursor-pointer">Lock employee portal access until HR review</label>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="escalateModalOpen = false" class="px-4 py-2 bg-slate-100 rounded-lg text-xs font-bold text-slate-600">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-bold shadow-sm transition flex items-center gap-2 cursor-pointer">
                        <i data-lucide="send" class="w-3.5 h-3.5"></i> Submit Escalation
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>