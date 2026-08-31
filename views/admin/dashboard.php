<!-- views/admin/dashboard.php -->
<?php
$user = authUser();
$db = getDBConnection();
$today = date('Y-m-d');
$userDesig = $user['designation'] ?? 'Head HR';
$isHRSupport = (stripos($userDesig, 'hr support') !== false);

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
    LIMIT 3
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

// Pending Leaves Preview (Top 2 Short Card)
$pendingLeavesList = $db->query("
    SELECT l.*, u.name, u.emp_id, u.avatar, u.designation
    FROM leave_applications l
    JOIN users u ON l.user_id = u.id
    WHERE l.status = 'pending_tl_review' OR l.status = 'pending_hr_approval' OR l.status = 'pending'
    ORDER BY l.created_at DESC
    LIMIT 2
")->fetchAll();

// Recent Session Terminations / Audits (Top 2 Short Card)
$recentAudits = $db->query("
    SELECT st.*, u.name as employee_name, u.emp_id, tl.name as tl_name
    FROM session_terminations st
    JOIN users u ON st.user_id = u.id
    JOIN users tl ON st.terminated_by = tl.id
    ORDER BY st.created_at DESC
    LIMIT 2
")->fetchAll();
?>

<div class="space-y-6 pb-8">

    <!-- Seamless Modern Page Header (No dark badge, pure clean enterprise typography) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pt-1">
        <div>
            <div class="flex items-center gap-2.5 flex-wrap">
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
                    <?= $isHRSupport ? 'HR Support Administration Hub' : 'HR Administration Overview' ?>
                </h1>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-indigo-50 text-indigo-700 border border-indigo-200 uppercase tracking-wide">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <?= htmlspecialchars($userDesig) ?>
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-1 font-medium flex items-center gap-2 flex-wrap">
                <span>Real-time workforce intelligence & operations</span>
                <span class="text-slate-300">•</span>
                <span class="text-slate-700 font-semibold"><?= date('l, d F Y') ?></span>
            </p>
        </div>

        <!-- Quick Top Action Buttons -->
        <div class="flex items-center gap-2.5 shrink-0">
            <a href="?page=admin-employees" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-2 shadow-sm cursor-pointer hover:scale-[1.02]">
                <i data-lucide="user-plus" class="w-4 h-4 text-indigo-200"></i>
                <span>Directory & Onboard</span>
            </a>
            <a href="?page=admin-attendance" class="px-4 py-2.5 bg-white hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-2 border border-slate-200 shadow-2xs cursor-pointer hover:scale-[1.02]">
                <i data-lucide="calendar-check" class="w-4 h-4 text-emerald-600"></i>
                <span>Attendance Audit</span>
            </a>
        </div>
    </div>

    <!-- Conditional Priority Alert: Only displays when action is required -->
    <?php if ($pendingEscCount > 0): ?>
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-rose-600 text-white flex items-center justify-center shrink-0 shadow-sm">
                    <i data-lucide="shield-alert" class="w-4.5 h-4.5"></i>
                </div>
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-rose-900 flex items-center gap-1.5">
                        <?= $pendingEscCount ?> TL Disciplinary Referral(s) Pending HR Action
                        <span class="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
                    </h3>
                    <p class="text-[11px] text-rose-700 font-medium">Team Leads have forwarded employee cases for formal HR intervention.</p>
                </div>
            </div>
            <a href="?page=admin-tl-reports" class="px-3.5 py-1.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl transition inline-flex items-center gap-1 shrink-0">
                <span>Review</span> &rarr;
            </a>
        </div>
    <?php endif; ?>

    <!-- 4 High-Contrast Enterprise KPI Metric Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- KPI 1: Workforce Directory -->
        <a href="?page=admin-employees" class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-2xs hover:shadow-md hover:border-indigo-300 transition-all flex flex-col justify-between group cursor-pointer block">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider group-hover:text-indigo-600 transition-colors">Total Workforce</span>
                <div class="w-9 h-9 rounded-xl bg-indigo-50 group-hover:bg-indigo-600 text-indigo-600 group-hover:text-white flex items-center justify-center font-bold shrink-0 transition-colors border border-indigo-100">
                    <i data-lucide="users" class="w-4.5 h-4.5"></i>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight font-mono group-hover:text-indigo-600 transition-colors"><?= $empCount ?></div>
                <div class="flex items-center gap-1.5 mt-1.5 flex-wrap">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                        <?= $tlCount ?> Team Leads
                    </span>
                    <span class="text-[10px] text-slate-400 font-medium">Active</span>
                </div>
            </div>
        </a>

        <!-- KPI 2: Present Today (Live Duty Stream) -->
        <?php 
        $dutyRate = ($empCount > 0) ? round(($presentCount / $empCount) * 100) : 0;
        ?>
        <a href="?page=admin-attendance" class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-2xs hover:shadow-md hover:border-emerald-300 transition-all flex flex-col justify-between group cursor-pointer block">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider group-hover:text-emerald-600 transition-colors">Present Today</span>
                <div class="w-9 h-9 rounded-xl bg-emerald-50 group-hover:bg-emerald-600 text-emerald-600 group-hover:text-white flex items-center justify-center font-bold shrink-0 transition-colors border border-emerald-100">
                    <i data-lucide="clock-4" class="w-4.5 h-4.5"></i>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-2xl sm:text-3xl font-extrabold text-emerald-600 tracking-tight font-mono flex items-baseline gap-1.5">
                    <span><?= $presentCount ?></span>
                    <span class="text-xs sm:text-sm font-bold text-slate-400 font-sans">/ <?= $empCount ?></span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-1.5 mt-2 overflow-hidden">
                    <div class="bg-emerald-500 h-1.5 rounded-full" style="width: <?= min(100, max(5, $dutyRate)) ?>%"></div>
                </div>
                <div class="flex items-center justify-between mt-1.5">
                    <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200"><?= $dutyRate ?>% On Duty</span>
                    <span class="text-[10px] text-slate-400 font-medium"><?= date('d M') ?> Live</span>
                </div>
            </div>
        </a>

        <!-- KPI 3: Pending Leaves -->
        <a href="?page=admin-leaves" class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-2xs hover:shadow-md hover:border-amber-300 transition-all flex flex-col justify-between group cursor-pointer block">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider group-hover:text-amber-600 transition-colors">Pending Leaves</span>
                <div class="w-9 h-9 rounded-xl <?= $pendingLeaves > 0 ? 'bg-amber-500 text-white animate-pulse' : 'bg-amber-50 text-amber-600 group-hover:bg-amber-500 group-hover:text-white' ?> flex items-center justify-center font-bold shrink-0 transition-colors border border-amber-100">
                    <i data-lucide="calendar-off" class="w-4.5 h-4.5"></i>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-2xl sm:text-3xl font-extrabold <?= $pendingLeaves > 0 ? 'text-amber-600' : 'text-slate-900' ?> tracking-tight font-mono">
                    <?= $pendingLeaves ?>
                </div>
                <div class="flex items-center gap-1.5 mt-1.5 flex-wrap">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold <?= $pendingLeaves > 0 ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' ?>">
                        <?= $pendingLeaves > 0 ? 'Review Needed' : 'All Clear ✓' ?>
                    </span>
                    <span class="text-[10px] text-slate-400 font-medium">Queue</span>
                </div>
            </div>
        </a>

        <!-- KPI 4: TL Referrals & Compliance -->
        <a href="?page=admin-tl-reports" class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-2xs hover:shadow-md hover:border-purple-300 transition-all flex flex-col justify-between group cursor-pointer block">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider group-hover:text-purple-600 transition-colors">TL Referrals</span>
                <div class="w-9 h-9 rounded-xl <?= $pendingEscCount > 0 ? 'bg-rose-500 text-white animate-pulse' : 'bg-purple-50 text-purple-600 group-hover:bg-purple-600 group-hover:text-white' ?> flex items-center justify-center font-bold shrink-0 transition-colors border border-purple-100">
                    <i data-lucide="shield-check" class="w-4.5 h-4.5"></i>
                </div>
            </div>
            <div class="mt-3">
                <div class="text-2xl sm:text-3xl font-extrabold <?= $pendingEscCount > 0 ? 'text-rose-600' : 'text-slate-900' ?> tracking-tight font-mono">
                    <?= $pendingEscCount ?>
                </div>
                <div class="flex items-center gap-1.5 mt-1.5 flex-wrap">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold <?= $pendingEscCount > 0 ? 'bg-rose-100 text-rose-800 border border-rose-200' : 'bg-purple-50 text-purple-700 border border-purple-200' ?>">
                        <?= $pendingEscCount > 0 ? 'Escalated' : 'Compliant ✓' ?>
                    </span>
                    <span class="text-[10px] text-slate-400 font-medium">Audit Health</span>
                </div>
            </div>
        </a>
    </div>

    <!-- Main Content Grid (Balanced Two-Column Architecture) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

        <!-- Left Column (7 Cols): Live Attendance + Security Audit Logs -->
        <div class="lg:col-span-7 space-y-5">
            <!-- Card 1: Today Live Attendance Check-Ins -->
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs overflow-hidden">
                <!-- Header -->
                <div class="p-4 sm:p-5 bg-slate-50/70 border-b border-slate-100 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold shrink-0 border border-emerald-200">
                            <i data-lucide="radio" class="w-4 h-4 animate-pulse"></i>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">
                                Today's Live Check-Ins & Shifts
                            </h2>
                            <p class="text-[11px] text-slate-500">Real-time attendance stream for <?= date('d M Y') ?>.</p>
                        </div>
                    </div>
                    <a href="?page=admin-attendance" class="px-3 py-1.5 rounded-xl bg-white hover:bg-slate-100 text-indigo-600 text-xs font-bold border border-slate-200 shadow-2xs transition inline-flex items-center gap-1 shrink-0">
                        <span>Full Register</span> &rarr;
                    </a>
                </div>

                <!-- Table content -->
                <div class="overflow-x-auto no-scrollbar p-2 sm:p-3">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="text-slate-400 text-[10px] uppercase tracking-wider font-bold border-b border-slate-100">
                            <tr>
                                <th class="py-3 px-3">Employee</th>
                                <th class="py-3 px-2 whitespace-nowrap">Line / Role</th>
                                <th class="py-3 px-2 whitespace-nowrap">Punch In</th>
                                <th class="py-3 px-2 text-center whitespace-nowrap">Status</th>
                                <th class="py-3 px-2 text-center whitespace-nowrap">Location</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php if (empty($recentAtt)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-8 text-slate-400">
                                        <div class="w-10 h-10 rounded-xl bg-slate-50 text-slate-400 mx-auto flex items-center justify-center mb-1.5 border border-slate-100">
                                            <i data-lucide="clock" class="w-5 h-5"></i>
                                        </div>
                                        <p class="text-xs font-bold text-slate-700">No punches logged yet today</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($recentAtt as $r): ?>
                                <tr class="hover:bg-slate-50 transition rounded-xl">
                                    <td class="py-3 px-3 align-middle">
                                        <div class="flex items-center gap-2.5">
                                            <img src="<?= $r['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($r['name']) ?>" class="w-8 h-8 rounded-xl object-cover ring-1 ring-slate-200 shrink-0" alt="Avatar">
                                            <div class="min-w-0">
                                                <div class="font-bold text-slate-900 truncate max-w-[120px] sm:max-w-[150px] text-xs"><?= htmlspecialchars($r['name']) ?></div>
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
                                            })" class="px-2 py-1 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 transition cursor-pointer shadow-2xs inline-flex items-center gap-1.5 font-bold text-[10px] border border-indigo-100" title="View <?= $sessCount ?> Sessions">
                                                <i data-lucide="map-pin" class="w-3.5 h-3.5 text-indigo-600"></i>
                                                <span>GPS</span>
                                                <span class="px-1.5 py-0.2 bg-indigo-600 text-white rounded-full font-mono text-[9px]"><?= $sessCount ?></span>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-[11px] text-slate-400 font-mono">Office Biometric</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Card 2: Security & Session Audit Logs (Placed under live attendance on Left Column) -->
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs p-5 space-y-3.5">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold shrink-0 border border-purple-100">
                            <i data-lucide="shield-check" class="w-4 h-4"></i>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900">
                            Security & Session Logs
                        </h3>
                    </div>
                    <span class="text-[10px] font-mono text-slate-400 font-bold uppercase bg-slate-100 px-2 py-0.5 rounded-md">Audit Trail</span>
                </div>

                <?php if (empty($recentAudits)): ?>
                    <div class="text-center py-5 bg-slate-50/70 rounded-xl border border-slate-100">
                        <p class="text-xs font-bold text-slate-700">No session anomalies recorded</p>
                        <p class="text-[10px] text-slate-400 mt-0.5">All team active sessions operational.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach ($recentAudits as $ra): ?>
                            <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200/80 flex items-center justify-between gap-2 text-xs">
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

        <!-- Right Column (5 Cols): Leave Review Queue + Upcoming Birthdays -->
        <div class="lg:col-span-5 space-y-5">

            <!-- Card 1: Leave Review Queue -->
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs p-5 space-y-3.5">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold shrink-0 border border-indigo-100">
                            <i data-lucide="calendar" class="w-4 h-4"></i>
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
                    <div class="text-center py-5 bg-slate-50/70 rounded-xl border border-slate-100">
                        <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 mx-auto flex items-center justify-center mb-1 border border-emerald-100">
                            <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                        </div>
                        <p class="text-xs font-bold text-slate-700">All leave applications reviewed</p>
                        <p class="text-[10px] text-slate-400 mt-0.5">Zero pending leave requests in queue.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach ($pendingLeavesList as $lv): ?>
                            <div class="p-2.5 rounded-xl bg-slate-50 hover:bg-indigo-50/40 border border-slate-200/80 flex items-center justify-between gap-2 text-xs transition">
                                <div class="min-w-0">
                                    <div class="font-bold text-slate-900 truncate"><?= htmlspecialchars($lv['name']) ?></div>
                                    <div class="text-[10px] text-slate-500 font-mono"><?= formatDate($lv['start_date']) ?> → <?= formatDate($lv['end_date']) ?></div>
                                </div>
                                <a href="?page=admin-leaves" class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-[10px] font-bold transition shadow-sm shrink-0">
                                    Review
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Card 2: 🎂 INTEGRATED WORKFORCE BIRTHDAYS & CELEBRATIONS -->
            <?php include __DIR__ . '/../partials/_upcoming_birthdays.php'; ?>

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