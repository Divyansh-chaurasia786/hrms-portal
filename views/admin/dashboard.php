<!-- views/admin/dashboard.php -->
<?php
$user = authUser();
$db = getDBConnection();
$today = date('Y-m-d');

$stats = $db->query("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE status = 'active') as empCount,
        (SELECT COUNT(*) FROM users WHERE role = 'team_lead' AND status = 'active') as tlCount,
        (SELECT COUNT(*) FROM attendance WHERE date = '$today') as presentCount,
        (SELECT COUNT(*) FROM leave_applications WHERE status IN ('pending_tl_review', 'pending_hr_approval', 'pending')) as pendingLeaves,
        (SELECT COUNT(*) FROM employee_escalations WHERE status = 'pending') as pendingEscCount
")->fetch() ?: [];

$empCount = (int)($stats['empCount'] ?? 0);
$tlCount = (int)($stats['tlCount'] ?? 0);
$presentCount = (int)($stats['presentCount'] ?? 0);
$pendingLeaves = (int)($stats['pendingLeaves'] ?? 0);
$pendingEscCount = (int)($stats['pendingEscCount'] ?? 0);

// Recent Live Attendance Check-ins
$recentAtt = $db->query("
    SELECT a.*, u.name, u.emp_id, u.avatar, u.role, u.designation, u.work_mode, tl.name as tl_name
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN users tl ON u.reporting_tl_id = tl.id
    WHERE a.date = '$today'
    ORDER BY a.clock_in DESC
    LIMIT 10
")->fetchAll();

$allTodaySessions = [];
if (!empty($recentAtt)) {
    $attIds = implode(',', array_column($recentAtt, 'id'));
    if ($attIds) {
        $sessRows = $db->query("SELECT * FROM attendance_sessions WHERE attendance_id IN ($attIds) ORDER BY session_number ASC")->fetchAll();
        foreach ($sessRows as $s) {
            $allTodaySessions[$s['attendance_id']][] = $s;
        }
    }
}
?>
<script>
    window.todayAttendanceSessions = <?= json_encode($allTodaySessions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>
<?php

// Pending Leaves Preview
$pendingLeavesList = $db->query("
    SELECT l.*, u.name, u.emp_id, u.avatar, u.designation
    FROM leave_applications l
    JOIN users u ON l.user_id = u.id
    WHERE l.status = 'pending_tl_review' OR l.status = 'pending_hr_approval' OR l.status = 'pending'
    ORDER BY l.created_at DESC
    LIMIT 3
")->fetchAll();

// Recent Session Terminations / Audits
$recentAudits = $db->query("
    SELECT st.*, u.name as employee_name, u.emp_id, tl.name as tl_name
    FROM session_terminations st
    JOIN users u ON st.user_id = u.id
    JOIN users tl ON st.terminated_by = tl.id
    ORDER BY st.created_at DESC
    LIMIT 4
")->fetchAll();
?>

<div class="space-y-6 pb-8">

    <!-- Executive Command Header (Ultra-Premium Glass & Slate Gradient Banner) -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white p-6 sm:p-7 shadow-xl shadow-slate-900/20 border border-slate-800">
        <!-- Ambient Background Glows -->
        <div class="absolute -right-20 -top-20 w-80 h-80 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -left-20 -bottom-20 w-80 h-80 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
            <!-- Left Brand Identity & Title -->
            <div class="flex items-start sm:items-center gap-4">
                <div class="w-13 h-13 rounded-2xl bg-white/10 backdrop-blur-md border border-white/20 flex items-center justify-center text-white shrink-0 shadow-lg shadow-indigo-950/50">
                    <img src="/logo_icon.png?v=3" alt="EcoFone" class="w-8 h-8 object-contain">
                </div>
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight leading-tight">
                            <?= $isHRSupport ? 'HR Support Administration Hub' : 'HR Administration Overview' ?>
                        </h1>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-indigo-500/30 text-indigo-200 border border-indigo-400/30 uppercase tracking-wide">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            <?= htmlspecialchars($userDesig) ?>
                        </span>
                    </div>
                    <p class="text-xs text-slate-300 mt-1 font-medium flex items-center gap-2 flex-wrap">
                        <span>Real-time workforce intelligence & operations monitor</span>
                        <span class="text-slate-500">•</span>
                        <span class="text-indigo-300 font-semibold"><?= date('l, d F Y') ?></span>
                    </p>
                </div>
            </div>

            <!-- Right Quick Action Launchers -->
            <div class="flex items-center gap-2.5 sm:gap-3 flex-wrap">
                <a href="?page=admin-employees" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-extrabold transition-all duration-200 flex items-center gap-2 shadow-lg shadow-indigo-600/30 hover:scale-[1.02] cursor-pointer">
                    <i data-lucide="user-plus" class="w-4 h-4 text-indigo-200"></i>
                    <span>Directory & Onboard</span>
                </a>
                <a href="?page=admin-attendance" class="px-4 py-2.5 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-extrabold transition-all duration-200 flex items-center gap-2 border border-white/20 backdrop-blur-sm shadow-sm cursor-pointer hover:scale-[1.02]">
                    <i data-lucide="calendar-check" class="w-4 h-4 text-emerald-400"></i>
                    <span>Attendance Audit</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Conditional Priority Alert: Only displays when action is required -->
    <?php if ($pendingEscCount > 0): ?>
        <div class="p-5 rounded-3xl bg-gradient-to-r from-rose-50 to-orange-50 border-2 border-rose-300/80 text-rose-900 shadow-[0_10px_25px_-5px_rgba(244,63,94,0.15)] flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-2xl bg-rose-500 text-white flex items-center justify-center shrink-0 shadow-md shadow-rose-500/30">
                    <i data-lucide="shield-alert" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-rose-900 flex items-center gap-2">
                        <?= $pendingEscCount ?> TL Disciplinary Referral(s) Pending HR Action
                        <span class="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
                    </h3>
                    <p class="text-xs text-rose-700 font-medium mt-0.5">
                        Team Leads have forwarded employee misconduct/attendance cases for formal HR intervention.
                    </p>
                </div>
            </div>
            <a href="?page=admin-tl-reports" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl transition inline-flex items-center gap-1.5 shadow-md shadow-rose-600/30 shrink-0">
                <span>Review Referrals</span> &rarr;
            </a>
        </div>
    <?php endif; ?>

    <!-- 4 High-Contrast Executive KPI Metric Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5 sm:gap-5">
        <!-- KPI 1: Workforce Directory -->
        <a href="?page=admin-employees" class="bg-white p-4 sm:p-5 rounded-2xl sm:rounded-3xl border border-slate-200/90 shadow-sm hover:shadow-xl hover:shadow-indigo-500/10 hover:border-indigo-200 hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between group cursor-pointer block">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold text-slate-500 uppercase tracking-wider group-hover:text-indigo-600 transition-colors">Total Workforce</span>
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-2xl bg-indigo-50 group-hover:bg-indigo-600 text-indigo-600 group-hover:text-white flex items-center justify-center font-bold shrink-0 transition-all duration-300 shadow-sm border border-indigo-100">
                    <i data-lucide="users" class="w-4 h-4 sm:w-5 sm:h-5"></i>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight font-mono group-hover:text-indigo-600 transition-colors"><?= $empCount ?></div>
                <div class="flex items-center gap-1.5 mt-2 flex-wrap">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200/80">
                        <?= $tlCount ?> Team Leads
                    </span>
                    <span class="text-[10px] text-slate-400 font-semibold">Active Portal</span>
                </div>
            </div>
        </a>

        <!-- KPI 2: Present Today (Live Duty Stream) -->
        <?php 
        $dutyRate = ($empCount > 0) ? round(($presentCount / $empCount) * 100) : 0;
        ?>
        <a href="?page=admin-attendance" class="bg-white p-4 sm:p-5 rounded-2xl sm:rounded-3xl border border-slate-200/90 shadow-sm hover:shadow-xl hover:shadow-emerald-500/10 hover:border-emerald-200 hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between group cursor-pointer block">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold text-slate-500 uppercase tracking-wider group-hover:text-emerald-600 transition-colors">Present Today</span>
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-2xl bg-emerald-50 group-hover:bg-emerald-600 text-emerald-600 group-hover:text-white flex items-center justify-center font-bold shrink-0 transition-all duration-300 shadow-sm border border-emerald-100">
                    <i data-lucide="clock-4" class="w-4 h-4 sm:w-5 sm:h-5"></i>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-2xl sm:text-3xl font-black text-emerald-600 tracking-tight font-mono flex items-baseline gap-1.5">
                    <span><?= $presentCount ?></span>
                    <span class="text-xs sm:text-sm font-bold text-slate-400 font-sans">/ <?= $empCount ?></span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-1.5 mt-2 overflow-hidden">
                    <div class="bg-emerald-500 h-1.5 rounded-full transition-all duration-500" style="width: <?= min(100, max(5, $dutyRate)) ?>%"></div>
                </div>
                <div class="flex items-center justify-between mt-1.5">
                    <span class="text-[10px] font-bold text-emerald-700"><?= $dutyRate ?>% On Duty</span>
                    <span class="text-[10px] text-slate-400 font-medium"><?= date('d M') ?> Live</span>
                </div>
            </div>
        </a>

        <!-- KPI 3: Pending Leaves -->
        <a href="?page=admin-leaves" class="bg-white p-4 sm:p-5 rounded-2xl sm:rounded-3xl border border-slate-200/90 shadow-sm hover:shadow-xl hover:shadow-amber-500/10 hover:border-amber-200 hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between group cursor-pointer block">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold text-slate-500 uppercase tracking-wider group-hover:text-amber-600 transition-colors">Pending Leaves</span>
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-2xl <?= $pendingLeaves > 0 ? 'bg-amber-500 text-white animate-pulse' : 'bg-amber-50 text-amber-600 group-hover:bg-amber-500 group-hover:text-white' ?> flex items-center justify-center font-bold shrink-0 transition-all duration-300 shadow-sm border border-amber-100">
                    <i data-lucide="calendar-off" class="w-4 h-4 sm:w-5 sm:h-5"></i>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-2xl sm:text-3xl font-black <?= $pendingLeaves > 0 ? 'text-amber-600' : 'text-slate-900' ?> tracking-tight font-mono">
                    <?= $pendingLeaves ?>
                </div>
                <div class="flex items-center gap-1.5 mt-2 flex-wrap">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold <?= $pendingLeaves > 0 ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' ?>">
                        <?= $pendingLeaves > 0 ? 'Review Needed' : 'All Reviewed ✓' ?>
                    </span>
                    <span class="text-[10px] text-slate-400 font-semibold">Queue</span>
                </div>
            </div>
        </a>

        <!-- KPI 4: TL Referrals & Compliance -->
        <a href="?page=admin-tl-reports" class="bg-white p-4 sm:p-5 rounded-2xl sm:rounded-3xl border border-slate-200/90 shadow-sm hover:shadow-xl hover:shadow-purple-500/10 hover:border-purple-200 hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between group cursor-pointer block">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold text-slate-500 uppercase tracking-wider group-hover:text-purple-600 transition-colors">TL Referrals</span>
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-2xl <?= $pendingEscCount > 0 ? 'bg-rose-500 text-white animate-pulse' : 'bg-purple-50 text-purple-600 group-hover:bg-purple-600 group-hover:text-white' ?> flex items-center justify-center font-bold shrink-0 transition-all duration-300 shadow-sm border border-purple-100">
                    <i data-lucide="shield-check" class="w-4 h-4 sm:w-5 sm:h-5"></i>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-2xl sm:text-3xl font-black <?= $pendingEscCount > 0 ? 'text-rose-600' : 'text-slate-900' ?> tracking-tight font-mono">
                    <?= $pendingEscCount ?>
                </div>
                <div class="flex items-center gap-1.5 mt-2 flex-wrap">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold <?= $pendingEscCount > 0 ? 'bg-rose-100 text-rose-800 border border-rose-300' : 'bg-purple-50 text-purple-700 border border-purple-200' ?>">
                        <?= $pendingEscCount > 0 ? 'Escalated' : '100% Compliant ✓' ?>
                    </span>
                    <span class="text-[10px] text-slate-400 font-semibold">Audit Health</span>
                </div>
            </div>
        </a>
    </div>

    <!-- Main Two-Column Content Grid (Floating Elevated Layout) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-7">

        <!-- Left Column (7 Cols): Today Live Attendance Check-Ins -->
        <div class="lg:col-span-7 bg-white rounded-3xl border border-slate-200/90 shadow-[0_10px_30px_-5px_rgba(0,0,0,0.06),0_4px_12px_-2px_rgba(0,0,0,0.04)] hover:shadow-[0_20px_40px_-8px_rgba(0,0,0,0.1)] transition-all duration-300 overflow-hidden flex flex-col">
            <!-- Header with subtle contrast tint -->
            <div class="p-5 sm:p-6 bg-slate-50/80 border-b border-slate-100 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold shrink-0 border border-emerald-200/50">
                        <i data-lucide="radio" class="w-4.5 h-4.5 animate-pulse"></i>
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            Today's Live Check-Ins & Shifts
                        </h2>
                        <p class="text-[11px] text-slate-500 mt-0.5">Real-time attendance punch stream for <?= date('d M Y') ?>.</p>
                    </div>
                </div>
                <a href="?page=admin-attendance" class="px-3 py-1.5 rounded-xl bg-white hover:bg-slate-100 text-indigo-600 text-xs font-bold border border-slate-200 shadow-2xs transition inline-flex items-center gap-1 shrink-0">
                    <span>Full Register</span> &rarr;
                </a>
            </div>

                        <!-- Table content (Clean, Perfectly Arranged Inside Box) -->
            <div class="overflow-x-auto no-scrollbar flex-1 px-4 py-2">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="text-slate-400 text-[10px] uppercase tracking-wider font-bold border-b border-slate-100">
                        <tr>
                            <th class="py-3 px-2">Employee</th>
                            <th class="py-3 px-2 whitespace-nowrap">Line / Role</th>
                            <th class="py-3 px-2 whitespace-nowrap">Punch In</th>
                            <th class="py-3 px-2 text-center whitespace-nowrap">Status</th>
                            <th class="py-3 px-2 text-center whitespace-nowrap">Location</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php if (empty($recentAtt)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-12 text-slate-400">
                                    <div class="w-12 h-12 rounded-2xl bg-slate-50 text-slate-400 mx-auto flex items-center justify-center mb-2 border border-slate-100">
                                        <i data-lucide="clock" class="w-6 h-6"></i>
                                    </div>
                                    <p class="text-xs font-bold text-slate-700">No punches logged yet today</p>
                                    <p class="text-[11px] text-slate-400 mt-0.5">Check back once employees clock in for their shift.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($recentAtt as $r): ?>
                            <tr class="hover:bg-slate-50/80 transition rounded-xl">
                                <td class="py-3 px-2 align-middle">
                                    <div class="flex items-center gap-2.5">
                                        <img src="<?= $r['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($r['name']) ?>" class="w-8 h-8 rounded-xl object-cover ring-1 ring-slate-200 shrink-0 shadow-2xs" alt="Avatar">
                                        <div class="min-w-0">
                                            <div class="font-bold text-slate-900 truncate max-w-[120px] sm:max-w-[150px]"><?= htmlspecialchars($r['name']) ?></div>
                                            <div class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($r['emp_id']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-2 align-middle text-[11px] font-medium text-slate-700 truncate max-w-[100px]">
                                    <?= htmlspecialchars($r['tl_name'] ?: ($r['role'] === 'team_lead' ? 'Direct HR' : ($r['role'] === 'admin' ? 'Head HR' : 'HR'))) ?>
                                </td>
                                <td class="py-3 px-2 align-middle font-mono text-[11px] font-semibold text-slate-800 whitespace-nowrap">
                                    <?= formatTime($r['clock_in']) ?>
                                </td>
                                <td class="py-3 px-2 align-middle text-center whitespace-nowrap">
                                    <?= getStatusBadge($r['status']) ?>
                                </td>
                                                                                                <td class="py-3 px-2 align-middle text-center whitespace-nowrap">
                                    <?php 
                                    $isExemptUser = (($r['role'] ?? '') === 'admin' || ($r['work_mode'] ?? '') === 'field' || ($r['work_mode'] ?? '') === 'wfh' || ($r['status'] ?? '') === 'wfh');
                                    $sessCount = !empty($allTodaySessions[$r['id']]) ? count($allTodaySessions[$r['id']]) : 1;
                                    ?>
                                    <?php if ($isExemptUser): ?>
                                        <button type="button" @click="$dispatch('open-loc-modal', {
                                            attId: <?= (int)$r['id'] ?>,
                                            name: '<?= addslashes($r['name'] ?? 'Staff') ?>',
                                            date: '<?= date('d M Y') ?>',
                                            total_hours: '<?= $r['total_hours'] ?? '' ?>',
                                            clock_in: '<?= $r['clock_in'] ? date('h:i A', strtotime($r['clock_in'])) : '-' ?>',
                                            clock_in_raw: '<?= $r['clock_in'] ?? '' ?>',
                                            clock_out: '<?= !empty($r['clock_out']) ? date('h:i A', strtotime($r['clock_out'])) : '' ?>',
                                            clock_out_raw: '<?= $r['clock_out'] ?? '' ?>',
                                            in_lat: '<?= $r['punch_in_lat'] ?? $r['latitude'] ?? '' ?>',
                                            in_lng: '<?= $r['punch_in_lng'] ?? $r['longitude'] ?? '' ?>',
                                            out_lat: '<?= $r['punch_out_lat'] ?? '' ?>',
                                            out_lng: '<?= $r['punch_out_lng'] ?? '' ?>'
                                        })" class="px-2 py-1 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 transition cursor-pointer shadow-2xs inline-flex items-center gap-1.5 font-bold text-[10px] border border-indigo-100" title="View <?= $sessCount ?> Punch In/Out Sessions">
                                            <i data-lucide="map-pin" class="w-3.5 h-3.5 text-indigo-600"></i>
                                            <span>GPS</span>
                                            <span class="px-1.5 py-0.2 bg-indigo-600 text-white rounded-full font-mono text-[9px] font-extrabold"><?= $sessCount ?></span>
                                        </button>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200/60" title="Fixed 150m Geofence Verified">
                                            <i data-lucide="building" class="w-3 h-3"></i> In-Office
                                            <span class="ml-0.5 px-1 bg-emerald-200/60 text-emerald-900 rounded font-mono text-[9px]"><?= $sessCount ?></span>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Right Column (5 Cols): Fast Management Hub & Operational Feeds -->
        <div class="lg:col-span-5 space-y-7">

            <!-- Card 1: Leave Approval Quick Queue (Floating Elevated Card) -->
            <div class="bg-white rounded-3xl border border-slate-200/90 shadow-[0_10px_30px_-5px_rgba(0,0,0,0.06),0_4px_12px_-2px_rgba(0,0,0,0.04)] hover:shadow-[0_20px_40px_-8px_rgba(0,0,0,0.1)] transition-all duration-300 p-5 sm:p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold shrink-0 border border-indigo-200/50">
                            <i data-lucide="calendar-check" class="w-4 h-4"></i>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900">
                            Leave Review Queue
                        </h3>
                    </div>
                    <a href="?page=admin-leaves" class="text-xs font-bold text-indigo-600 hover:text-indigo-700 transition flex items-center gap-1">
                        <span>View All (<?= $pendingLeaves ?>)</span> &rarr;
                    </a>
                </div>

                <?php if (empty($pendingLeavesList)): ?>
                    <div class="text-center py-7 bg-slate-50/70 rounded-2xl border border-slate-100/80">
                        <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 mx-auto flex items-center justify-center mb-1.5 border border-emerald-100">
                            <i data-lucide="check-circle-2" class="w-5 h-5"></i>
                        </div>
                        <p class="text-xs font-bold text-slate-700">All leave applications reviewed</p>
                        <p class="text-[10px] text-slate-400 mt-0.5">Zero pending leave requests in queue.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-2.5">
                        <?php foreach ($pendingLeavesList as $lv): ?>
                            <div class="p-3 rounded-2xl bg-slate-50 hover:bg-indigo-50/40 border border-slate-200/80 flex items-center justify-between gap-3 text-xs transition">
                                <div class="min-w-0">
                                    <div class="font-bold text-slate-900 truncate"><?= htmlspecialchars($lv['name']) ?></div>
                                    <div class="text-[10px] text-slate-500 font-mono"><?= formatDate($lv['start_date']) ?> → <?= formatDate($lv['end_date']) ?></div>
                                </div>
                                <a href="?page=admin-leaves" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-[11px] font-bold transition shadow-sm shrink-0">
                                    Review
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Card 2: 🎂 INTEGRATED WORKFORCE BIRTHDAYS & CELEBRATIONS -->
            <?php include __DIR__ . '/../partials/_upcoming_birthdays.php'; ?>

            <!-- Card 3: Recent Security & Session Audit (Floating Elevated Card) -->
            <div class="bg-white rounded-3xl border border-slate-200/90 shadow-[0_10px_30px_-5px_rgba(0,0,0,0.06),0_4px_12px_-2px_rgba(0,0,0,0.04)] hover:shadow-[0_20px_40px_-8px_rgba(0,0,0,0.1)] transition-all duration-300 p-5 sm:p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold shrink-0 border border-purple-200/50">
                            <i data-lucide="shield-check" class="w-4 h-4"></i>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900">
                            Recent Security & Session Logs
                        </h3>
                    </div>
                    <span class="text-[10px] font-mono text-slate-400 font-bold uppercase bg-slate-100 px-2 py-0.5 rounded-md">Audit Trail</span>
                </div>

                <?php if (empty($recentAudits)): ?>
                    <div class="text-center py-7 bg-slate-50/70 rounded-2xl border border-slate-100/80">
                        <p class="text-xs font-bold text-slate-700">No session anomalies recorded</p>
                        <p class="text-[10px] text-slate-400 mt-0.5">All team active sessions operational.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-2.5">
                        <?php foreach ($recentAudits as $ra): ?>
                            <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200/80 flex items-center justify-between gap-3 text-xs">
                                <div class="min-w-0">
                                    <span class="font-bold text-slate-900"><?= htmlspecialchars($ra['employee_name']) ?></span>
                                    <span class="text-[10px] text-slate-400 block truncate">By TL: <?= htmlspecialchars($ra['tl_name']) ?> • <?= htmlspecialchars($ra['reason']) ?></span>
                                </div>
                                <span class="text-[10px] font-mono text-slate-400 shrink-0"><?= date('h:i A', strtotime($ra['created_at'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>

    </div>

</div>


<!-- Attendance Location & Collapsible Multi-Session Timeline Modal -->
<div x-data="{ 
    locModalOpen: false, 
    locData: null, 
    activeSessionIdx: 0,
    getSessions(detail) {
        if (!detail) return [];
        const map = window.todayAttendanceSessions || window.auditAttendanceSessions || {};
        let sess = (detail.attId && map[detail.attId]) ? map[detail.attId] : [];
        if (!sess || !sess.length) {
            // Fallback to primary punch record
            sess = [{
                session_number: 1,
                clock_in: detail.clock_in_raw || detail.clock_in,
                clock_out: detail.clock_out_raw || detail.clock_out,
                punch_in_lat: detail.in_lat,
                punch_in_lng: detail.in_lng,
                punch_out_lat: detail.out_lat,
                punch_out_lng: detail.out_lng,
                hours: detail.total_hours
            }];
        }
        return sess;
    }
}" @open-loc-modal.window="
    locData = $event.detail;
    locData.sessions = getSessions($event.detail);
    activeSessionIdx = (locData.sessions && locData.sessions.length) ? (locData.sessions.length - 1) : 0;
    locModalOpen = true;
">
    <div x-show="locModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4" x-cloak>
        <div @click.away="locModalOpen = false" class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 text-left my-auto">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                        <i data-lucide="map-pin" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900" x-text="locData ? locData.name : 'Attendance Location'"></h3>
                        <p class="text-[11px] text-slate-400" x-text="locData ? ('Date: ' + locData.date + (locData.total_hours ? ' • Total: ' + locData.total_hours + ' hrs' : '')) : ''"></p>
                    </div>
                </div>
                <button type="button" @click="locModalOpen = false" class="p-1 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-50 transition cursor-pointer">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- Sessions Collapsible Accordion -->
            <div class="space-y-2.5 max-h-96 overflow-y-auto pr-1" x-show="locData">
                <template x-if="locData && locData.sessions && locData.sessions.length">
                    <div class="space-y-2">
                        <template x-for="(s, idx) in locData.sessions" :key="s.id || idx">
                            <div class="rounded-2xl border border-slate-200 overflow-hidden transition-all duration-200" :class="activeSessionIdx === idx ? 'bg-slate-50/90 ring-2 ring-indigo-500/20' : 'bg-white hover:bg-slate-50/50'">
                                
                                <!-- Collapsible Dropdown Header Trigger -->
                                <button type="button" @click="activeSessionIdx = (activeSessionIdx === idx ? -1 : idx)" class="w-full p-3.5 flex items-center justify-between text-left cursor-pointer transition">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <div class="w-6 h-6 rounded-lg bg-indigo-100 text-indigo-800 flex items-center justify-center text-[10px] font-extrabold shrink-0" x-text="'#' + (s.session_number || (idx + 1))"></div>
                                        <div class="min-w-0">
                                            <span class="text-xs font-bold text-slate-900 truncate block">
                                                Session <span x-text="s.session_number || (idx + 1)"></span>
                                            </span>
                                            <span class="text-[10px] text-slate-500 font-mono" x-text="(s.clock_in ? new Date(s.clock_in).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '-') + ' → ' + (s.clock_out ? new Date(s.clock_out).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : 'Active')"></span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" :class="s.clock_out ? 'bg-slate-100 text-slate-700' : 'bg-emerald-100 text-emerald-800'" x-text="s.hours ? (s.hours + ' hrs') : '🟢 Active Now'"></span>
                                        <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="activeSessionIdx === idx ? 'rotate-180 text-indigo-600' : ''"></i>
                                    </div>
                                </button>

                                <!-- Collapsible Session Body -->
                                <div x-show="activeSessionIdx === idx" x-collapse class="px-3.5 pb-3.5 pt-1 space-y-2 border-t border-slate-200/60">
                                    <!-- Punch In Details -->
                                    <div class="p-2.5 bg-emerald-50/70 rounded-xl border border-emerald-200/70 flex items-center justify-between text-xs">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5 text-emerald-800 font-bold text-[11px]">
                                                <span>🟢 Punch In:</span>
                                                <span class="font-mono" x-text="s.clock_in ? new Date(s.clock_in).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '-'"></span>
                                            </div>
                                            <div x-show="s.punch_in_lat" class="text-[10px] font-mono text-slate-500 mt-0.5">
                                                GPS: <span x-text="Number(s.punch_in_lat).toFixed(5) + ', ' + Number(s.punch_in_lng).toFixed(5)"></span>
                                            </div>
                                        </div>
                                        <div class="shrink-0" x-show="s.punch_in_lat">
                                            <a :href="'https://www.google.com/maps?q=' + s.punch_in_lat + ',' + s.punch_in_lng" target="_blank" class="px-2 py-1 rounded-lg bg-emerald-100 hover:bg-emerald-200 text-emerald-900 text-[10px] font-bold inline-flex items-center gap-1 transition">
                                                <i data-lucide="external-link" class="w-3 h-3"></i> Maps 🗺️
                                            </a>
                                        </div>
                                    </div>

                                    <!-- Punch Out Details -->
                                    <div class="p-2.5 bg-rose-50/70 rounded-xl border border-rose-200/70 flex items-center justify-between text-xs">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5 text-rose-800 font-bold text-[11px]">
                                                <span>🔴 Punch Out:</span>
                                                <span class="font-mono" x-text="s.clock_out ? new Date(s.clock_out).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : 'Active (Not Logged Out)'"></span>
                                            </div>
                                            <div x-show="s.punch_out_lat" class="text-[10px] font-mono text-slate-500 mt-0.5">
                                                GPS: <span x-text="Number(s.punch_out_lat).toFixed(5) + ', ' + Number(s.punch_out_lng).toFixed(5)"></span>
                                            </div>
                                        </div>
                                        <div class="shrink-0" x-show="s.punch_out_lat">
                                            <a :href="'https://www.google.com/maps?q=' + s.punch_out_lat + ',' + s.punch_out_lng" target="_blank" class="px-2 py-1 rounded-lg bg-rose-100 hover:bg-rose-200 text-rose-900 text-[10px] font-bold inline-flex items-center gap-1 transition">
                                                <i data-lucide="external-link" class="w-3 h-3"></i> Maps 🗺️
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- Fallback if no sessions array -->
                <template x-if="!locData || !locData.sessions || !locData.sessions.length">
                    <div class="p-4 bg-slate-50 rounded-2xl text-center text-xs text-slate-500">
                        No session breakdown recorded for this day.
                    </div>
                </template>
            </div>

            <!-- Footer -->
            <div class="flex justify-end pt-3 border-t border-slate-100 mt-4">
                <button type="button" @click="locModalOpen = false" class="px-4 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition cursor-pointer">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>