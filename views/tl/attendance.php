<!-- views/tl/attendance.php -->
<?php
$user = authUser();
$db = getDBConnection();
$viewTab = $_GET['tab'] ?? 'daily';
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedMonth = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

$monthStart = $selectedMonth . '-01';
$daysInMonth = (int)date('t', strtotime($monthStart));
$monthEnd = $selectedMonth . '-' . str_pad($daysInMonth, 2, '0', STR_PAD_LEFT);
$monthTitle = date('F Y', strtotime($monthStart));
$prevMonth = date('Y-m', strtotime("$monthStart -1 month"));
$nextMonth = date('Y-m', strtotime("$monthStart +1 month"));

$searchQuery = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$filterVerification = $_GET['verification'] ?? '';
$filterMemberId = (int)($_GET['member_id'] ?? 0);

// Fetch managed team members (Supports TL and TL Support)
$teamUserIds = getManagedTeamUserIds($user['id']);
$inTLTeam = !empty($teamUserIds) ? implode(',', array_map('intval', $teamUserIds)) : '0';

$teamMembers = $db->query("
    SELECT id, emp_id, name, designation, avatar, employment_type, salary_basic
    FROM users 
    WHERE id IN ($inTLTeam) AND status = 'active'
    ORDER BY name ASC
")->fetchAll() ?: [];

// Daily direct reporting team roster
$stmt = $db->prepare("
    SELECT u.id, u.id as user_id, u.emp_id, u.name, u.designation, u.avatar,
           a.id as attendance_id, a.clock_in, a.clock_out, a.total_hours, a.status as attendance_status, a.notes,
           a.tl_approved, a.hr_corrected, a.hr_alert_message, a.locked_by_hr,
           a.force_logged_out_by, a.force_logout_at
    FROM users u
    LEFT JOIN attendance a ON u.id = a.user_id AND a.date = ?
    WHERE u.id IN ($inTLTeam) AND u.status = 'active'
    ORDER BY u.name ASC
");
$stmt->execute([$selectedDate]);
$rawRoster = $stmt->fetchAll() ?: [];

// Metrics for Daily Roster
$totalMembers = count($rawRoster);
$presentCount = 0;
$pendingReviewCount = 0;
$approvedCount = 0;
$hrAlertCount = 0;

foreach ($rawRoster as $r) {
    if (!empty($r['attendance_status']) && in_array($r['attendance_status'], ['present', 'wfh', 'half_day'])) {
        $presentCount++;
    }
    if ($r['hr_corrected']) {
        $hrAlertCount++;
    }
    if ($r['tl_approved']) {
        $approvedCount++;
    } elseif (!empty($r['clock_in'])) {
        $pendingReviewCount++;
    }
}

// Filter daily roster for display
$roster = array_filter($rawRoster, function($r) use ($searchQuery, $filterStatus, $filterVerification) {
    if ($searchQuery !== '') {
        $matchesSearch = stripos($r['name'], $searchQuery) !== false ||
                         stripos($r['emp_id'], $searchQuery) !== false ||
                         stripos($r['designation'], $searchQuery) !== false;
        if (!$matchesSearch) return false;
    }

    if ($filterStatus !== '') {
        $currStatus = $r['attendance_status'] ?? 'not_marked';
        if ($filterStatus === 'not_marked' && !empty($r['clock_in'])) return false;
        if ($filterStatus !== 'not_marked' && $currStatus !== $filterStatus) return false;
    }

    if ($filterVerification !== '') {
        if ($filterVerification === 'pending' && ($r['tl_approved'] || $r['hr_corrected'])) return false;
        if ($filterVerification === 'approved' && !$r['tl_approved']) return false;
        if ($filterVerification === 'hr_corrected' && !$r['hr_corrected']) return false;
    }

    return true;
});

// Fetch Monthly Team Data
$monthlyTeamStmt = $db->prepare("
    SELECT a.*, u.name, u.emp_id, u.designation, u.avatar
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE u.id IN ($inTLTeam) AND a.date >= ? AND a.date <= ?
    ORDER BY a.date DESC
");
$monthlyTeamStmt->execute([$monthStart, $monthEnd]);
$teamMonthlyLogs = $monthlyTeamStmt->fetchAll();

// Index team monthly logs by user_id -> date -> record
$teamMonthlyByMember = [];
foreach ($teamMembers as $tm) {
    $teamMonthlyByMember[$tm['id']] = [
        'info' => $tm,
        'present' => 0,
        'absent' => 0,
        'half_day' => 0,
        'wfh' => 0,
        'hours' => 0,
        'logs' => []
    ];
}

foreach ($teamMonthlyLogs as $tl) {
    $uid = $tl['user_id'];
    if (isset($teamMonthlyByMember[$uid])) {
        $st = strtolower($tl['status'] ?? '');
        if ($st === 'present') $teamMonthlyByMember[$uid]['present']++;
        elseif ($st === 'half_day') $teamMonthlyByMember[$uid]['half_day']++;
        elseif ($st === 'wfh') $teamMonthlyByMember[$uid]['wfh']++;
        elseif ($st === 'absent') $teamMonthlyByMember[$uid]['absent']++;
        
        $teamMonthlyByMember[$uid]['hours'] += (float)($tl['total_hours'] ?? 0);
        $teamMonthlyByMember[$uid]['logs'][$tl['date']] = $tl;
    }
}

// Fetch TL's Own Personal Monthly Attendance
$myAttStmt = $db->prepare("
    SELECT * FROM attendance
    WHERE user_id = ? AND date >= ? AND date <= ?
    ORDER BY date DESC
");
$myAttStmt->execute([$user['id'], $monthStart, $monthEnd]);
$myMonthlyLogs = $myAttStmt->fetchAll();

$myPresent = 0; $myAbsent = 0; $myHalf = 0; $myHours = 0;
$myAttMap = [];
foreach ($myMonthlyLogs as $ml) {
    $st = strtolower($ml['status'] ?? '');
    if ($st === 'present' || $st === 'wfh') $myPresent++;
    elseif ($st === 'half_day') $myHalf++;
    elseif ($st === 'absent') $myAbsent++;
    $myHours += (float)($ml['total_hours'] ?? 0);
    $myAttMap[$ml['date']] = $ml;
}
?>

<div class="space-y-6" x-data="{ 
    reviewModalOpen: false, 
    selectedRecord: null,
    inTime: '09:30',
    outTime: '',
    status: 'present',
    notes: '',
    isInActiveShift: false,
    openModal(rec) {
        this.selectedRecord = rec;
        this.status = rec.attendance_status || 'present';
        this.isInActiveShift = Boolean(rec.clock_in && !rec.clock_out && '<?= $selectedDate ?>' === '<?= date('Y-m-d') ?>');

        if (this.status === 'half_day') {
            this.inTime = rec.clock_in ? rec.clock_in.substring(11, 16) : '09:30';
            this.outTime = rec.clock_out ? rec.clock_out.substring(11, 16) : (this.isInActiveShift ? '' : '13:30');
        } else if (this.status === 'absent') {
            this.inTime = '';
            this.outTime = '';
        } else {
            this.inTime = rec.clock_in ? rec.clock_in.substring(11, 16) : '09:30';
            this.outTime = rec.clock_out ? rec.clock_out.substring(11, 16) : (this.isInActiveShift ? '' : '');
        }
        this.notes = rec.notes || '';
        this.reviewModalOpen = true;
    },
    onStatusChange(newStatus) {
        this.status = newStatus;
        if (newStatus === 'half_day') {
            this.inTime = this.inTime || '09:30';
            if (!this.isInActiveShift) this.outTime = '13:30';
        } else if (newStatus === 'present' || newStatus === 'wfh') {
            this.inTime = this.inTime || '09:30';
        } else if (newStatus === 'absent') {
            this.inTime = '';
            this.outTime = '';
        }
    },
    get calculatedHours() {
        if (this.status === 'absent' || !this.inTime) return 0;
        if (!this.outTime) {
            return this.isInActiveShift ? 'Active (In Progress)' : '0';
        }
        const [inH, inM] = this.inTime.split(':').map(Number);
        const [outH, outM] = this.outTime.split(':').map(Number);
        const totalMinutes = (outH * 60 + outM) - (inH * 60 + inM);
        return totalMinutes > 0 ? (totalMinutes / 60).toFixed(1) : 0;
    }
}">
    <!-- Navigation Tabs -->
    <div class="flex items-center gap-2 border-b border-slate-200 pb-3 flex-wrap">
        <a href="?page=tl-attendance&tab=daily&date=<?= htmlspecialchars($selectedDate) ?>" class="px-4 py-2 rounded-xl text-xs font-bold transition inline-flex items-center gap-1.5 <?= ($viewTab === 'daily') ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200' ?>">
            <i data-lucide="check-square" class="w-4 h-4"></i> Daily Roster & Approval
        </a>
        <a href="?page=tl-attendance&tab=monthly_team&month=<?= htmlspecialchars($selectedMonth) ?>" class="px-4 py-2 rounded-xl text-xs font-bold transition inline-flex items-center gap-1.5 <?= ($viewTab === 'monthly_team') ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200' ?>">
            <i data-lucide="users" class="w-4 h-4"></i> Monthly Team Register
        </a>
        <a href="?page=tl-attendance&tab=my_attendance&month=<?= htmlspecialchars($selectedMonth) ?>" class="px-4 py-2 rounded-xl text-xs font-bold transition inline-flex items-center gap-1.5 <?= ($viewTab === 'my_attendance') ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200' ?>">
            <i data-lucide="calendar" class="w-4 h-4"></i> My Attendance History
        </a>
    </div>

<?php if ($viewTab === 'daily'): ?>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="clock" class="w-5 h-5"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Daily Team Roster Review & Approval</h1>
                <p class="text-xs text-slate-500 mt-0.5">Review daily team check-ins, verify total logged hours, and send verified roster to HR.</p>
            </div>
        </div>

        <!-- Date Picker Controls -->
        <form method="GET" class="flex items-center gap-2 bg-slate-50 p-1.5 rounded-xl border border-slate-200">
            <input type="hidden" name="page" value="tl-attendance">
            <input type="hidden" name="tab" value="daily">
            <a href="?page=tl-attendance&tab=daily&date=<?= date('Y-m-d', strtotime($selectedDate . ' -1 day')) ?>" class="p-1.5 hover:bg-white text-slate-500 hover:text-slate-900 rounded-lg transition" title="Previous Day">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
            </a>
            <input type="date" name="date" value="<?= htmlspecialchars($selectedDate) ?>" max="<?= date('Y-m-d') ?>" onchange="this.form.submit()" class="bg-transparent border-0 text-xs font-bold text-slate-800 focus:ring-0 cursor-pointer">
            <a href="?page=tl-attendance&tab=daily&date=<?= date('Y-m-d', strtotime($selectedDate . ' +1 day')) ?>" class="p-1.5 hover:bg-white text-slate-500 hover:text-slate-900 rounded-lg transition <?= $selectedDate >= date('Y-m-d') ? 'pointer-events-none opacity-40' : '' ?>" title="Next Day">
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </a>
            <?php if ($selectedDate !== date('Y-m-d')): ?>
                <a href="?page=tl-attendance&tab=daily&date=<?= date('Y-m-d') ?>" class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg text-[10px] font-bold transition">Today</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- 4 Stats Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center shrink-0">
                <i data-lucide="users" class="w-4 h-4"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Team</span>
                <span class="text-lg font-extrabold text-slate-900 leading-none"><?= $totalMembers ?></span>
            </div>
        </div>

        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                <i data-lucide="check-circle-2" class="w-4 h-4"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Present Today</span>
                <span class="text-lg font-extrabold text-emerald-600 leading-none"><?= $presentCount ?></span>
            </div>
        </div>

        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                <i data-lucide="clock-3" class="w-4 h-4"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Pending TL Review</span>
                <span class="text-lg font-extrabold text-amber-600 leading-none"><?= $pendingReviewCount ?></span>
            </div>
        </div>

        <div class="bg-white p-3.5 rounded-xl border <?= $hrAlertCount > 0 ? 'border-rose-300 bg-rose-50/30' : 'border-slate-200' ?> shadow-sm flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg <?= $hrAlertCount > 0 ? 'bg-rose-100 text-rose-700' : 'bg-purple-50 text-purple-600' ?> flex items-center justify-center shrink-0">
                <i data-lucide="<?= $hrAlertCount > 0 ? 'alert-triangle' : 'shield-check' ?>" class="w-4 h-4"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold <?= $hrAlertCount > 0 ? 'text-rose-600' : 'text-slate-400' ?> uppercase tracking-wider block">
                    <?= $hrAlertCount > 0 ? 'HR Corrections' : 'Approved to HR' ?>
                </span>
                <span class="text-lg font-extrabold <?= $hrAlertCount > 0 ? 'text-rose-700' : 'text-purple-600' ?> leading-none">
                    <?= $hrAlertCount > 0 ? $hrAlertCount : $approvedCount ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Alert if HR corrected any attendance -->
    <?php if ($hrAlertCount > 0): ?>
        <div class="p-3.5 bg-rose-50 rounded-xl border border-rose-200 text-xs text-rose-900 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2.5">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600 shrink-0"></i>
                <span><strong>Notice from HR: <?= $hrAlertCount ?> record(s)</strong> were corrected by HR due to attendance discrepancies and have been locked.</span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Control Toolbar (Search, Status Filter) -->
    <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-3">
        <form method="GET" class="flex-1 w-full flex flex-col sm:flex-row items-center gap-2.5">
            <input type="hidden" name="page" value="tl-attendance">
            <input type="hidden" name="date" value="<?= htmlspecialchars($selectedDate) ?>">

            <!-- Search input -->
            <div class="relative flex-1 w-full">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search by employee name or ID..." class="w-full bg-slate-50 border border-slate-200 rounded-lg pl-8 pr-3 py-1.5 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <!-- Status filter -->
            <div class="w-full sm:w-40">
                <select name="status" onchange="this.form.submit()" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-700 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <option value="">All Statuses</option>
                    <option value="present" <?= $filterStatus === 'present' ? 'selected' : '' ?>>Present</option>
                    <option value="half_day" <?= $filterStatus === 'half_day' ? 'selected' : '' ?>>Half Day</option>
                    <option value="wfh" <?= $filterStatus === 'wfh' ? 'selected' : '' ?>>Work From Home</option>
                    <option value="absent" <?= $filterStatus === 'absent' ? 'selected' : '' ?>>Absent</option>
                    <option value="not_marked" <?= $filterStatus === 'not_marked' ? 'selected' : '' ?>>Not Marked</option>
                </select>
            </div>

            <!-- Verification filter -->
            <div class="w-full sm:w-44">
                <select name="verification" onchange="this.form.submit()" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-700 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <option value="">All Verification States</option>
                    <option value="pending" <?= $filterVerification === 'pending' ? 'selected' : '' ?>>Pending TL Review</option>
                    <option value="approved" <?= $filterVerification === 'approved' ? 'selected' : '' ?>>Approved to HR</option>
                    <option value="hr_corrected" <?= $filterVerification === 'hr_corrected' ? 'selected' : '' ?>>HR Corrected</option>
                </select>
            </div>

            <?php if (!empty($searchQuery) || !empty($filterStatus) || !empty($filterVerification)): ?>
                <a href="?page=tl-attendance&date=<?= htmlspecialchars($selectedDate) ?>" class="text-xs text-rose-600 hover:text-rose-700 font-semibold px-2 py-1">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Redesigned Roster Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden w-full min-w-0">
        <div class="overflow-x-auto no-scrollbar w-full">
            <table class="w-full text-left text-xs text-slate-600 border-collapse min-w-[760px]">
                <thead class="bg-slate-50 text-slate-500 text-[11px] uppercase tracking-wider font-bold border-b border-slate-200">
                    <tr>
                        <th class="min-w-[180px] py-3.5 pl-4 pr-2">Employee</th>
                        <th class="min-w-[170px] py-3.5 px-2 whitespace-nowrap">Punch Timing & Hours</th>
                        <th class="min-w-[110px] py-3.5 px-2 text-center whitespace-nowrap">Status</th>
                        <th class="min-w-[150px] py-3.5 px-2 text-center whitespace-nowrap">Verification State</th>
                        <th class="min-w-[130px] py-3.5 pl-2 pr-4 text-right whitespace-nowrap">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($roster)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-10 text-slate-400">
                                <i data-lucide="users" class="w-8 h-8 mx-auto text-slate-300 mb-1"></i>
                                <p class="text-xs font-semibold text-slate-600">No team members found for selected criteria</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($roster as $row): ?>
                        <tr class="hover:bg-slate-50/80 transition <?= $row['hr_corrected'] ? 'bg-rose-50/30' : '' ?>">
                            <!-- Employee Info -->
                            <td class="py-3.5 pl-4 pr-2 align-middle">
                                <div class="flex items-center gap-2.5 overflow-hidden">
                                    <img src="<?= $row['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($row['name']) ?>" class="w-8 h-8 rounded-full object-cover ring-1 ring-slate-200 shrink-0" alt="Avatar">
                                    <div class="min-w-0">
                                        <div class="font-bold text-slate-900 text-xs truncate"><?= htmlspecialchars($row['name']) ?></div>
                                        <div class="text-[10px] text-slate-400 truncate">
                                            <span class="font-mono font-semibold text-slate-500"><?= htmlspecialchars($row['emp_id']) ?></span> • <?= htmlspecialchars($row['designation']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Clock In / Out & Total Hours -->
                            <td class="py-3.5 px-2 align-middle">
                                <div class="space-y-1">
                                    <div class="font-mono text-[11px] text-slate-700 flex items-center gap-1.5">
                                        <?php if ($row['clock_in']): ?>
                                            <span class="font-semibold"><?= formatTime($row['clock_in']) ?></span>
                                            <span class="text-slate-300">&rarr;</span>
                                            <?php if ($row['clock_out']): ?>
                                                <span class="font-semibold"><?= formatTime($row['clock_out']) ?></span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-600">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Active
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-slate-400 text-[11px] font-sans">No punch logged</span>
                                        <?php endif; ?>
                                    </div>

                                    <?php
                                    $calcHours = $row['total_hours'];
                                    if (!empty($row['clock_in']) && !empty($row['clock_out'])) {
                                        $inTs = strtotime($row['clock_in']);
                                        $outTs = strtotime($row['clock_out']);
                                        if ($outTs > $inTs) {
                                            $calcHours = round(($outTs - $inTs) / 3600, 1);
                                        }
                                    }
                                    // Fetch all sessions for this attendance
                                    $sessions = [];
                                    if (!empty($row['attendance_id'])) {
                                        $sessStmt = $db->prepare("SELECT * FROM attendance_sessions WHERE attendance_id = ? ORDER BY session_number ASC");
                                        $sessStmt->execute([$row['attendance_id']]);
                                        $sessions = $sessStmt->fetchAll();
                                    }
                                    ?>
                                    <?php if (count($sessions) > 1): ?>
                                        <div x-data="{ open: false }" class="relative">
                                            <div class="flex items-center justify-between gap-1.5">
                                                <div>
                                                    <div class="flex items-center gap-1 font-bold text-slate-800">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                                                        <span><?= formatTime($sessions[0]['clock_in']) ?></span>
                                                        <span class="text-slate-300 font-normal">&rarr;</span>
                                                        <span><?= end($sessions)['clock_out'] ? formatTime(end($sessions)['clock_out']) : '<span class="text-emerald-600 font-bold">Active</span>' ?></span>
                                                    </div>
                                                    <div class="text-[10px] font-sans font-medium text-slate-500 mt-0.5 flex items-center gap-1.5">
                                                        <span>Total: <strong class="text-slate-900 font-mono"><?= $calcHours > 0 ? $calcHours . ' hrs' : round($totalSessHours, 1) . ' hrs' ?></strong></span>
                                                        <span class="text-slate-300">•</span>
                                                        <span class="px-1.5 py-0.2 rounded-full bg-indigo-50 text-indigo-700 font-bold text-[9px]"><?= count($sessions) ?> Punches</span>
                                                    </div>
                                                </div>
                                                <button @click="open = !open" type="button" class="p-1 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-indigo-600 transition shrink-0 flex items-center gap-0.5 text-[10px] font-bold" title="Toggle session details">
                                                    <span x-text="open ? 'Hide' : 'Details'"></span>
                                                    <i data-lucide="chevron-down" class="w-3.5 h-3.5 transition-transform duration-200" :class="open ? 'rotate-180 text-indigo-600' : ''"></i>
                                                </button>
                                            </div>

                                            <!-- Collapsible Details -->
                                            <div x-show="open" x-cloak class="mt-2 pt-1.5 border-t border-slate-100 space-y-1 bg-slate-50/90 p-2 rounded-xl border border-slate-200/80">
                                                <?php foreach ($sessions as $s): ?>
                                                    <div class="flex items-center justify-between text-[10px] <?= $s['ended_by'] === 'force_logout' ? 'text-rose-700 font-semibold' : 'text-slate-700' ?>">
                                                        <div class="flex items-center gap-1">
                                                            <span class="px-1 py-0.2 rounded bg-white text-indigo-700 font-bold text-[9px] border border-indigo-100">#<?= $s['session_number'] ?></span>
                                                            <span><?= formatTime($s['clock_in']) ?> &rarr; <?= $s['clock_out'] ? formatTime($s['clock_out']) : '<span class="text-emerald-600 font-bold">Active</span>' ?></span>
                                                        </div>
                                                        <div class="flex items-center gap-1">
                                                            <?php if ($s['hours'] > 0): ?>
                                                                <span class="text-slate-500 font-mono">(<?= $s['hours'] ?>h)</span>
                                                            <?php endif; ?>
                                                            <?php if ($s['ended_by'] === 'force_logout'): ?>
                                                                <span class="px-1 py-0.2 rounded bg-rose-100 text-rose-700 text-[8px] font-bold" title="Forced Logout by TL">🚪 Forced</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="flex items-center gap-1.5 text-slate-800 font-semibold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                                            <span><?= $row['clock_in'] ? formatTime($row['clock_in']) : 'No punch' ?></span>
                                            <span class="text-slate-300 font-normal">&rarr;</span>
                                            <span class="text-slate-600"><?= $row['clock_out'] ? formatTime($row['clock_out']) : ($row['clock_in'] ? 'Active' : '--') ?></span>
                                        </div>
                                        <?php if ($calcHours > 0 || $row['clock_in']): ?>
                                            <div class="text-[10px] font-sans font-medium text-slate-500 mt-0.5 flex items-center gap-1.5">
                                                <span>Total: <strong class="text-slate-800 font-mono"><?= $calcHours > 0 ? $calcHours . ' hrs' : 'In Progress' ?></strong></span>
                                                <span class="text-slate-300">•</span>
                                                <span class="px-1.5 py-0.2 rounded-full bg-slate-100 text-slate-600 font-bold text-[9px]">1 Punch</span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($row['force_logged_out_by'])): ?>
                                            <div class="mt-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-rose-50 border border-rose-200 text-[9px] font-bold text-rose-700">
                                                <i data-lucide="log-out" class="w-2.5 h-2.5 text-rose-500"></i>
                                                Forced Logout by TL at <?= $row['force_logout_at'] ? date('h:i A', strtotime($row['force_logout_at'])) : '' ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Attendance Status Badge -->
                            <td class="py-3.5 px-2 align-middle text-center">
                                <?php if ($row['clock_in'] || !empty($row['attendance_status'])): ?>
                                    <?= getStatusBadge($row['attendance_status'] ?: 'present') ?>
                                <?php else: ?>
                                    <span class="inline-flex items-center justify-center whitespace-nowrap px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-500 border border-slate-200">
                                        Not Marked
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Verification State -->
                            <td class="py-3.5 px-2 align-middle text-center">
                                <?php if ($row['hr_corrected']): ?>
                                    <div class="space-y-0.5">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700 border border-rose-300">
                                            <i data-lucide="alert-triangle" class="w-3 h-3 text-rose-600"></i> HR Corrected
                                        </span>
                                        <?php if ($row['hr_alert_message']): ?>
                                            <p class="text-[9px] text-rose-600 font-medium truncate max-w-[170px] mx-auto" title="<?= htmlspecialchars($row['hr_alert_message']) ?>">
                                                <?= htmlspecialchars($row['hr_alert_message']) ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($row['tl_approved']): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <i data-lucide="check-check" class="w-3 h-3 text-emerald-600"></i> Approved to HR
                                    </span>
                                <?php elseif (!empty($row['clock_in'])): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                        <i data-lucide="clock" class="w-3 h-3 text-amber-600"></i> Pending TL Verify
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-400 text-xs">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- Action Buttons -->
                            <td class="py-3.5 pl-2 pr-4 align-middle text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <?php if (!empty($row['clock_in']) && empty($row['clock_out'])): ?>
                                        <form action="?action=force-logout-user" method="POST" onsubmit="return confirm('Force logout <?= htmlspecialchars($row['name']) ?> from active session?');" class="inline">
                                            <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
                                            <button type="submit" class="p-1.5 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg border border-rose-200 transition" title="Active Session - End Session / Logout">
                                                <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($row['locked_by_hr']): ?>
                                        <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-400 bg-slate-100 px-2.5 py-1 rounded-lg border border-slate-200">
                                            <i data-lucide="lock" class="w-3 h-3"></i> Locked
                                        </span>
                                    <?php elseif (!empty($row['clock_in'])): ?>
                                        <button type="button" @click="openModal(<?= htmlspecialchars(json_encode($row)) ?>)" class="inline-flex items-center gap-1 px-2.5 py-1.5 <?= $row['tl_approved'] ? 'bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200' : 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm' ?> text-xs font-bold rounded-lg transition whitespace-nowrap">
                                            <i data-lucide="<?= $row['tl_approved'] ? 'edit-2' : 'check' ?>" class="w-3.5 h-3.5"></i>
                                            <?= $row['tl_approved'] ? 'Edit Punch' : 'Verify Punch' ?>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-[11px] text-slate-400 font-medium italic">Not Punched In</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TL Review & Approve Modal -->
    <div x-show="reviewModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" x-cloak>
        <div @click.away="reviewModalOpen = false" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                        <i data-lucide="check-circle" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Review & Approve Attendance</h3>
                        <p class="text-[11px] text-slate-500" x-text="selectedRecord ? (selectedRecord.name + ' (' + selectedRecord.emp_id + ')') : ''"></p>
                    </div>
                </div>
                <button @click="reviewModalOpen = false" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <form action="?action=tl-approve-attendance" method="POST" class="space-y-3.5">
                <input type="hidden" name="user_id" :value="selectedRecord ? selectedRecord.id : ''">
                <input type="hidden" name="attendance_id" :value="selectedRecord ? (selectedRecord.attendance_id || 0) : ''">
                <input type="hidden" name="date" value="<?= htmlspecialchars($selectedDate) ?>">
                <input type="hidden" name="total_hours" :value="calculatedHours">

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Attendance Status</label>
                    <select name="status" x-model="status" @change="onStatusChange($event.target.value)" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold">
                        <option value="present">Present (In Office)</option>
                        <option value="wfh">Work From Home (WFH)</option>
                        <option value="half_day">Half Day (09:30 AM → 01:30 PM, 4 hrs)</option>
                        <option value="absent">Absent</option>
                    </select>
                </div>

                <!-- Active Shift Notice -->
                <div x-show="isInActiveShift" class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-900 text-xs flex items-start gap-2.5">
                    <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse shrink-0 mt-1.5"></div>
                    <div>
                        <span class="font-bold block">Active Shift In Progress (🟢 Employee Working Now)</span>
                        <span class="text-[11px] text-emerald-700">Employee punched in at <strong class="font-mono" x-text="inTime"></strong>. Approving will verify their punch-in and keep their active session running without logging them out.</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3" x-show="status !== 'absent'">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Clock In Time *</label>
                        <input type="time" name="clock_in_time" x-model="inTime" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">
                            Clock Out Time <span x-show="isInActiveShift" class="text-slate-400 font-normal lowercase">(optional)</span>
                        </label>
                        <input type="time" name="clock_out_time" x-model="outTime" :placeholder="isInActiveShift ? 'Leave empty while working' : ''" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono">
                    </div>
                </div>

                <!-- Live Computed Hours Display -->
                <div class="p-2.5 bg-indigo-50/60 rounded-xl border border-indigo-100 flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-700 flex items-center gap-1.5">
                        <i data-lucide="clock" class="w-3.5 h-3.5 text-indigo-600"></i> Total Shift Hours:
                    </span>
                    <span class="text-xs font-mono font-bold text-indigo-700 bg-white px-2 py-0.5 rounded shadow-sm border border-indigo-100" x-text="typeof calculatedHours === 'number' || !isNaN(calculatedHours) ? calculatedHours + ' hrs' : calculatedHours"></span>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">TL Notes / Remarks</label>
                    <textarea name="notes" x-model="notes" rows="2" placeholder="e.g. Verified by TL..." class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs"></textarea>
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button type="button" @click="reviewModalOpen = false" class="px-3.5 py-1.5 text-xs font-semibold text-slate-500">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                        Approve & Send to HR
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; // End Daily Tab ?>

<?php if ($viewTab === 'monthly_team'): ?>
    <!-- Header & Month Controls -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="calendar" class="w-5 h-5"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Monthly Team Attendance Register</h1>
                <p class="text-xs text-slate-500 mt-0.5">Audit monthly present/absent history, total logged hours, and team compliance.</p>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <a href="?page=tl-attendance&tab=monthly_team&month=<?= $prevMonth ?>&member_id=<?= $filterMemberId ?>" class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl transition font-bold" title="Previous Month">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
            </a>

            <form method="GET" class="m-0 flex items-center gap-2">
                <input type="hidden" name="page" value="tl-attendance">
                <input type="hidden" name="tab" value="monthly_team">
                <input type="hidden" name="member_id" value="<?= $filterMemberId ?>">
                <input type="month" name="month" value="<?= $selectedMonth ?>" onchange="this.form.submit()" class="bg-slate-50 border border-slate-300 rounded-xl px-3 py-1.5 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500">
            </form>

            <a href="?page=tl-attendance&tab=monthly_team&month=<?= $nextMonth ?>&member_id=<?= $filterMemberId ?>" class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl transition font-bold" title="Next Month">
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </a>
        </div>
    </div>

    <!-- Member Filter Selector -->
    <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3">
        <label class="text-xs font-bold text-slate-700 uppercase">Select Member:</label>
        <select onchange="window.location.href='?page=tl-attendance&tab=monthly_team&month=<?= $selectedMonth ?>&member_id=' + this.value" class="bg-slate-50 border border-slate-300 rounded-xl px-3 py-1.5 text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-indigo-500">
            <option value="0">All Team Members (Matrix View)</option>
            <?php foreach ($teamMembers as $tm): ?>
                <option value="<?= $tm['id'] ?>" <?= $filterMemberId === (int)$tm['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($tm['name']) ?> (<?= htmlspecialchars($tm['emp_id']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <span class="text-xs text-slate-400 font-mono ml-auto">Month: <?= $monthTitle ?></span>
    </div>

    <?php if ($filterMemberId === 0): ?>
        <!-- Matrix View for All Team Members -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-slate-500 text-[11px] uppercase tracking-wider font-bold border-b border-slate-200">
                    <tr>
                        <th class="px-5 py-3.5">Team Member</th>
                        <th class="px-5 py-3.5 text-center">Present Days</th>
                        <th class="px-5 py-3.5 text-center">Absent Days</th>
                        <th class="px-5 py-3.5 text-center">Half Days</th>
                        <th class="px-5 py-3.5 text-center">Total Hours</th>
                        <th class="px-5 py-3.5 text-center">Compliance</th>
                        <th class="px-5 py-3.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($teamMembers)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-10 text-slate-400 font-semibold">No team members currently assigned under you.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($teamMembers as $tm): 
                        $mStats = $teamMonthlyByMember[$tm['id']];
                        $totLogs = count($mStats['logs']);
                        $rate = ($totLogs > 0) ? round((($mStats['present'] + $mStats['wfh'] + ($mStats['half_day'] * 0.5)) / max(1, $totLogs)) * 100, 1) : 100;
                    ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="px-5 py-3.5 align-middle">
                                <div class="font-bold text-slate-900"><?= htmlspecialchars($tm['name']) ?></div>
                                <div class="text-[11px] text-slate-400 font-mono"><?= htmlspecialchars($tm['emp_id']) ?> • <?= htmlspecialchars($tm['designation']) ?></div>
                            </td>
                            <td class="px-5 py-3.5 align-middle text-center">
                                <span class="px-2.5 py-1 rounded-full text-xs font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    <?= $mStats['present'] ?> <?= $mStats['wfh'] > 0 ? "+ {$mStats['wfh']} WFH" : '' ?>
                                </span>
                            </td>
                            <td class="px-5 py-3.5 align-middle text-center">
                                <span class="px-2.5 py-1 rounded-full text-xs font-extrabold <?= $mStats['absent'] > 0 ? 'bg-rose-100 text-rose-800 font-bold border border-rose-200' : 'bg-slate-100 text-slate-600' ?>">
                                    <?= $mStats['absent'] ?>
                                </span>
                            </td>
                            <td class="px-5 py-3.5 align-middle text-center">
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                    <?= $mStats['half_day'] ?>
                                </span>
                            </td>
                            <td class="px-5 py-3.5 align-middle text-center font-mono font-bold text-indigo-700">
                                <?= number_format($mStats['hours'], 1) ?> hrs
                            </td>
                            <td class="px-5 py-3.5 align-middle text-center font-bold text-purple-700">
                                <?= $rate ?>%
                            </td>
                            <td class="px-5 py-3.5 align-middle text-right">
                                <a href="?page=tl-attendance&tab=monthly_team&month=<?= $selectedMonth ?>&member_id=<?= $tm['id'] ?>" class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-lg text-xs font-bold transition inline-flex items-center gap-1">
                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i> View Details
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <!-- Individual Member Day-by-Day Sheet -->
        <?php 
        $targetMember = $teamMonthlyByMember[$filterMemberId] ?? null;
        ?>
        <?php if ($targetMember): ?>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h2 class="text-base font-bold text-slate-900"><?= htmlspecialchars($targetMember['info']['name']) ?> — Monthly Attendance Log</h2>
                        <p class="text-xs text-slate-500"><?= htmlspecialchars($targetMember['info']['emp_id']) ?> • <?= htmlspecialchars($targetMember['info']['designation']) ?> • Month of <?= $monthTitle ?></p>
                    </div>
                    <a href="?page=tl-attendance&tab=monthly_team&month=<?= $selectedMonth ?>" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
                        &larr; Back to Team List
                    </a>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100">
                        <span class="text-[10px] font-bold text-emerald-800 uppercase block">Present Days</span>
                        <span class="text-lg font-extrabold text-emerald-700"><?= $targetMember['present'] ?></span>
                    </div>
                    <div class="p-3 rounded-xl bg-rose-50 border border-rose-100">
                        <span class="text-[10px] font-bold text-rose-800 uppercase block">Absent Days</span>
                        <span class="text-lg font-extrabold text-rose-700"><?= $targetMember['absent'] ?></span>
                    </div>
                    <div class="p-3 rounded-xl bg-amber-50 border border-amber-100">
                        <span class="text-[10px] font-bold text-amber-800 uppercase block">Half Days</span>
                        <span class="text-lg font-extrabold text-amber-700"><?= $targetMember['half_day'] ?></span>
                    </div>
                    <div class="p-3 rounded-xl bg-indigo-50 border border-indigo-100">
                        <span class="text-[10px] font-bold text-indigo-800 uppercase block">Total Hours</span>
                        <span class="text-lg font-extrabold text-indigo-700"><?= number_format($targetMember['hours'], 1) ?> hrs</span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-slate-50 text-slate-500 text-[11px] uppercase font-bold border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Punch Timings</th>
                                <th class="px-4 py-3 text-center">Total Hours</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3">Approval</th>
                                <th class="px-4 py-3">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($targetMember['logs'])): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-8 text-slate-400">No attendance logs found for this month.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($targetMember['logs'] as $log): ?>
                                <tr class="hover:bg-slate-50/80 transition <?= $log['hr_corrected'] ? 'bg-rose-50/30' : '' ?>">
                                    <td class="px-4 py-3 font-bold text-slate-900"><?= formatDate($log['date']) ?> (<?= date('D', strtotime($log['date'])) ?>)</td>
                                    <td class="px-4 py-3 font-mono text-xs">
                                        <?= formatTime($log['clock_in']) ?> &rarr; <?= $log['clock_out'] ? formatTime($log['clock_out']) : 'Active' ?>
                                    </td>
                                    <td class="px-4 py-3 text-center font-bold text-slate-800"><?= $log['total_hours'] > 0 ? $log['total_hours'] . 'h' : '-' ?></td>
                                    <td class="px-4 py-3 text-center"><?= getStatusBadge($log['status']) ?></td>
                                    <td class="px-4 py-3">
                                        <?php if ($log['hr_corrected']): ?>
                                            <span class="text-[10px] font-bold text-rose-700 bg-rose-100 px-2 py-0.5 rounded-full">HR Corrected</span>
                                        <?php elseif ($log['tl_approved']): ?>
                                            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">TL Verified</span>
                                        <?php else: ?>
                                            <span class="text-[10px] font-semibold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-[11px] text-slate-500"><?= htmlspecialchars($log['notes'] ?: '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; // End Monthly Team Tab ?>

<?php if ($viewTab === 'my_attendance'): ?>
    <!-- Header & Month Controls for TL Personal Attendance -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="user" class="w-5 h-5"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">My Monthly Attendance History</h1>
                <p class="text-xs text-slate-500 mt-0.5">Your personal punch logs, leaves, work hours, and HR audits.</p>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <a href="?page=tl-attendance&tab=my_attendance&month=<?= $prevMonth ?>" class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl transition font-bold" title="Previous Month">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
            </a>

            <form method="GET" class="m-0 flex items-center gap-2">
                <input type="hidden" name="page" value="tl-attendance">
                <input type="hidden" name="tab" value="my_attendance">
                <input type="month" name="month" value="<?= $selectedMonth ?>" onchange="this.form.submit()" class="bg-slate-50 border border-slate-300 rounded-xl px-3 py-1.5 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500">
            </form>

            <a href="?page=tl-attendance&tab=my_attendance&month=<?= $nextMonth ?>" class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl transition font-bold" title="Next Month">
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </a>
        </div>
    </div>

    <!-- TL Personal Metric Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="user-check" class="w-5 h-5"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Present Days</span>
                <span class="text-xl font-extrabold text-emerald-600 leading-tight"><?= $myPresent ?></span>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="user-x" class="w-5 h-5"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Absent Days</span>
                <span class="text-xl font-extrabold text-rose-600 leading-tight"><?= $myAbsent ?></span>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="clock" class="w-5 h-5"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Half Days</span>
                <span class="text-xl font-extrabold text-amber-600 leading-tight"><?= $myHalf ?></span>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="timer" class="w-5 h-5"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Hours Logged</span>
                <span class="text-xl font-extrabold text-indigo-600 leading-tight"><?= number_format($myHours, 1) ?></span>
            </div>
        </div>
    </div>

    <!-- TL Personal Log Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-left text-xs text-slate-600">
            <thead class="bg-slate-50 text-slate-500 text-[11px] uppercase tracking-wider font-bold border-b border-slate-200">
                <tr>
                    <th class="px-5 py-3.5">Date & Day</th>
                    <th class="px-5 py-3.5">Punch Timings</th>
                    <th class="px-5 py-3.5 text-center">Total Hours</th>
                    <th class="px-5 py-3.5 text-center">Status</th>
                    <th class="px-5 py-3.5">Verification / Audit</th>
                    <th class="px-5 py-3.5">Notes</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($myMonthlyLogs)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-10 text-slate-400">No attendance records logged for <?= $monthTitle ?></td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($myMonthlyLogs as $ml): ?>
                    <tr class="hover:bg-slate-50/80 transition <?= $ml['hr_corrected'] ? 'bg-rose-50/30' : '' ?>">
                        <td class="px-5 py-3.5 font-bold text-slate-900"><?= formatDate($ml['date']) ?> (<?= date('l', strtotime($ml['date'])) ?>)</td>
                        <td class="px-5 py-3.5 font-mono">
                            <?= formatTime($ml['clock_in']) ?> &rarr; <?= $ml['clock_out'] ? formatTime($ml['clock_out']) : 'Active Shift' ?>
                        </td>
                        <td class="px-5 py-3.5 text-center font-bold text-slate-900"><?= $ml['total_hours'] > 0 ? $ml['total_hours'] . 'h' : '-' ?></td>
                        <td class="px-5 py-3.5 text-center"><?= getStatusBadge($ml['status']) ?></td>
                        <td class="px-5 py-3.5">
                            <?php if ($ml['hr_corrected']): ?>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200">HR Corrected</span>
                            <?php else: ?>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Standard</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3.5 text-[11px] text-slate-500"><?= htmlspecialchars($ml['notes'] ?: ($ml['hr_alert_message'] ?: '-')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; // End My Attendance Tab ?>

</div>

