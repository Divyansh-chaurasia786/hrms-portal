<!-- views/admin/attendance.php -->
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
$statusFilter = $_GET['status_filter'] ?? '';
$filterEmployeeId = (int)($_GET['employee_id'] ?? 0);

// Fetch all active staff (TLs and Employees)
$allStaff = $db->query("
    SELECT u.id, u.emp_id, u.name, u.designation, u.avatar, u.role, u.employment_type, tl.name as tl_name
    FROM users u
    LEFT JOIN users tl ON u.reporting_tl_id = tl.id
    WHERE u.status = 'active' AND u.role != 'admin'
    ORDER BY u.role DESC, u.name ASC
")->fetchAll();

// Daily Verified Query
$sql = "
    SELECT u.id, u.emp_id, u.name, u.designation, u.avatar, u.role,
           tl.name as tl_name,
           a.id as attendance_id, a.clock_in, a.clock_out, a.total_hours, a.status as attendance_status, a.notes,
           a.tl_approved, a.hr_corrected, a.hr_alert_message, a.locked_by_hr,
           a.force_logged_out_by, a.force_logout_at, flo.name as force_logout_by_name
    FROM users u
    LEFT JOIN users tl ON u.reporting_tl_id = tl.id
    LEFT JOIN attendance a ON u.id = a.user_id AND a.date = :date
    LEFT JOIN users flo ON a.force_logged_out_by = flo.id
    WHERE u.status = 'active' AND (a.tl_approved = 1 OR a.hr_corrected = 1)
";

if (!empty($searchQuery)) {
    $sql .= " AND (u.name LIKE :search OR u.emp_id LIKE :search)";
}
if (!empty($statusFilter)) {
    if ($statusFilter === 'locked') {
        $sql .= " AND (a.hr_corrected = 1 OR a.locked_by_hr = 1)";
    } else {
        $sql .= " AND a.status = :status_val";
    }
}
$sql .= " ORDER BY u.role DESC, u.name ASC";

$stmt = $db->prepare($sql);
$params = [':date' => $selectedDate];
if (!empty($searchQuery)) $params[':search'] = "%{$searchQuery}%";
if (!empty($statusFilter) && $statusFilter !== 'locked') $params[':status_val'] = $statusFilter;
$stmt->execute($params);
$attendanceList = $stmt->fetchAll();

// Daily Stat metrics
$totalAudited = count($attendanceList);
$presentCount = 0;
$wfhCount = 0;
$lockedCount = 0;

foreach ($attendanceList as $item) {
    if ($item['hr_corrected'] || $item['locked_by_hr']) $lockedCount++;
    if ($item['attendance_status'] === 'wfh') $wfhCount++;
    if ($item['attendance_status'] === 'present') $presentCount++;
}

$pendingTLCount = (int)$db->query("SELECT COUNT(*) FROM attendance a WHERE a.date = '$selectedDate' AND a.tl_approved = 0")->fetchColumn();

// Monthly Organization Data Fetching
$orgMonthlyStmt = $db->prepare("
    SELECT a.*, u.name, u.emp_id, u.designation, u.role, u.avatar
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE u.status = 'active' AND u.role != 'admin' AND a.date >= ? AND a.date <= ?
    ORDER BY a.date DESC
");
$orgMonthlyStmt->execute([$monthStart, $monthEnd]);
$orgMonthlyLogs = $orgMonthlyStmt->fetchAll();

// Index by user_id
$orgMonthlyByStaff = [];
foreach ($allStaff as $stf) {
    $orgMonthlyByStaff[$stf['id']] = [
        'info' => $stf,
        'present' => 0,
        'absent' => 0,
        'half_day' => 0,
        'wfh' => 0,
        'hours' => 0,
        'corrected' => 0,
        'logs' => []
    ];
}

foreach ($orgMonthlyLogs as $ol) {
    $uid = $ol['user_id'];
    if (isset($orgMonthlyByStaff[$uid])) {
        $st = strtolower($ol['status'] ?? '');
        if ($st === 'present') $orgMonthlyByStaff[$uid]['present']++;
        elseif ($st === 'half_day') $orgMonthlyByStaff[$uid]['half_day']++;
        elseif ($st === 'wfh') $orgMonthlyByStaff[$uid]['wfh']++;
        elseif ($st === 'absent') $orgMonthlyByStaff[$uid]['absent']++;

        if (!empty($ol['hr_corrected'])) $orgMonthlyByStaff[$uid]['corrected']++;
        $orgMonthlyByStaff[$uid]['hours'] += (float)($ol['total_hours'] ?? 0);
        $orgMonthlyByStaff[$uid]['logs'][$ol['date']] = $ol;
    }
}

// Compute Org Monthly Totals
$totalOrgPresent = 0; $totalOrgAbsent = 0; $totalOrgHours = 0; $totalOrgEntries = 0;
foreach ($orgMonthlyByStaff as $sData) {
    $totalOrgPresent += ($sData['present'] + $sData['wfh']);
    $totalOrgAbsent += $sData['absent'];
    $totalOrgHours += $sData['hours'];
    $totalOrgEntries += count($sData['logs']);
}
$orgAvgCompliance = ($totalOrgEntries > 0) ? round(($totalOrgPresent / max(1, $totalOrgEntries)) * 100, 1) : 100;
?>

<div class="space-y-5" x-data="{ 
    hrCorrectModalOpen: false, 
    selectedRecord: null,
    inTime: '09:30',
    outTime: '18:30',
    status: 'present',
    notes: '',
    alertReason: 'Wrong attendance marked - Corrected & Locked by HR',
    openModal(rec) {
        this.selectedRecord = rec;
        this.status = rec.attendance_status || rec.status || 'present';
        if (this.status === 'half_day') {
            this.inTime = rec.clock_in ? rec.clock_in.substring(11, 16) : '09:30';
            this.outTime = rec.clock_out ? rec.clock_out.substring(11, 16) : '13:30';
        } else if (this.status === 'absent') {
            this.inTime = '';
            this.outTime = '';
        } else {
            this.inTime = rec.clock_in ? rec.clock_in.substring(11, 16) : '09:30';
            this.outTime = rec.clock_out ? rec.clock_out.substring(11, 16) : '18:30';
        }
        this.notes = rec.notes || '';
        this.alertReason = rec.hr_alert_message || 'Wrong attendance marked - Corrected & Locked by HR';
        this.hrCorrectModalOpen = true;
    },
    onStatusChange(newStatus) {
        this.status = newStatus;
        if (newStatus === 'half_day') {
            this.inTime = '09:30';
            this.outTime = '13:30';
        } else if (newStatus === 'present' || newStatus === 'wfh') {
            if (!this.inTime || (this.inTime === '09:30' && this.outTime === '13:30')) {
                this.inTime = '09:30';
                this.outTime = '18:30';
            }
        } else if (newStatus === 'absent') {
            this.inTime = '';
            this.outTime = '';
        }
    },
    get calculatedHours() {
        if (this.status === 'absent' || !this.inTime || !this.outTime) return 0;
        const [inH, inM] = this.inTime.split(':').map(Number);
        const [outH, outM] = this.outTime.split(':').map(Number);
        const totalMinutes = (outH * 60 + outM) - (inH * 60 + inM);
        return totalMinutes > 0 ? (totalMinutes / 60).toFixed(1) : 0;
    }
}">
    <!-- Navigation Tabs -->
    <div class="flex items-center gap-2 border-b border-slate-200 pb-3 flex-wrap">
        <a href="?page=admin-attendance&tab=daily&date=<?= htmlspecialchars($selectedDate) ?>" class="px-4 py-2 rounded-xl text-xs font-bold transition inline-flex items-center gap-1.5 <?= ($viewTab === 'daily') ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200' ?>">
            <i data-lucide="check-square" class="w-4 h-4"></i> Daily Verification & Audit
        </a>
        <a href="?page=admin-attendance&tab=monthly&month=<?= htmlspecialchars($selectedMonth) ?>" class="px-4 py-2 rounded-xl text-xs font-bold transition inline-flex items-center gap-1.5 <?= ($viewTab === 'monthly') ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200' ?>">
            <i data-lucide="calendar" class="w-4 h-4"></i> Monthly Company Timesheet & Register
        </a>
    </div>

<?php if ($viewTab === 'daily'): ?>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="shield-check" class="w-5 h-5"></i>
            </div>
            <div>
                <h1 class="text-lg font-bold text-slate-900 leading-tight">HR Attendance Audit & Verification</h1>
                <p class="text-xs text-slate-500">Audit verified check-ins and apply official HR Lock & Alert.</p>
            </div>
        </div>

        <!-- Date & Print -->
        <form method="GET" class="flex items-center gap-2">
            <input type="hidden" name="page" value="admin-attendance">
            <div class="relative flex items-center">
                <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 pointer-events-none"></i>
                <input type="date" name="date" value="<?= htmlspecialchars($selectedDate) ?>" onchange="this.form.submit()" class="bg-slate-50 border border-slate-300 rounded-xl pl-8 pr-2.5 py-1.5 text-xs font-semibold text-slate-700 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none cursor-pointer">
            </div>
            <button type="button" onclick="window.print()" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl border border-slate-200 transition flex items-center gap-1">
                <i data-lucide="printer" class="w-3.5 h-3.5"></i> Print
            </button>
        </form>
    </div>

    <!-- 4 Stats Banner -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                <i data-lucide="check-check" class="w-4 h-4"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Audited</span>
                <span class="text-lg font-extrabold text-slate-900 leading-none"><?= $totalAudited ?></span>
            </div>
        </div>

        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                <i data-lucide="building" class="w-4 h-4"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Present</span>
                <span class="text-lg font-extrabold text-emerald-600 leading-none"><?= $presentCount ?></span>
            </div>
        </div>

        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center shrink-0">
                <i data-lucide="home" class="w-4 h-4"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">WFH</span>
                <span class="text-lg font-extrabold text-purple-600 leading-none"><?= $wfhCount ?></span>
            </div>
        </div>

        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                <i data-lucide="alert-triangle" class="w-4 h-4"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">HR Locked</span>
                <span class="text-lg font-extrabold text-rose-600 leading-none"><?= $lockedCount ?></span>
            </div>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-sm">
        <form method="GET" class="flex flex-col sm:flex-row items-center gap-2.5">
            <input type="hidden" name="page" value="admin-attendance">
            <input type="hidden" name="date" value="<?= htmlspecialchars($selectedDate) ?>">

            <div class="relative flex-1 w-full">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search employee name or ID..." class="w-full bg-slate-50 border border-slate-200 rounded-lg pl-8 pr-3 py-1.5 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div class="w-full sm:w-44">
                <select name="status_filter" onchange="this.form.submit()" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-700 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <option value="">All Statuses</option>
                    <option value="present" <?= $statusFilter === 'present' ? 'selected' : '' ?>>Present</option>
                    <option value="wfh" <?= $statusFilter === 'wfh' ? 'selected' : '' ?>>Work From Home</option>
                    <option value="half_day" <?= $statusFilter === 'half_day' ? 'selected' : '' ?>>Half Day</option>
                    <option value="absent" <?= $statusFilter === 'absent' ? 'selected' : '' ?>>Absent</option>
                    <option value="locked" <?= $statusFilter === 'locked' ? 'selected' : '' ?>>HR Locked Only</option>
                </select>
            </div>

            <?php if (!empty($searchQuery) || !empty($statusFilter)): ?>
                <a href="?page=admin-attendance&date=<?= htmlspecialchars($selectedDate) ?>" class="text-xs text-rose-600 hover:text-rose-700 font-semibold px-2 py-1">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Verified Attendance Audit Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden w-full">
        <div class="overflow-x-auto no-scrollbar">
            <table class="w-full text-left text-xs text-slate-600 border-collapse min-w-[780px]">
                <thead class="bg-slate-50 text-slate-500 text-[11px] uppercase tracking-wider font-bold border-b border-slate-200">
                    <tr>
                        <th class="py-3.5 pl-5 pr-3 min-w-[200px]">Employee Profile</th>
                        <th class="py-3.5 px-3 min-w-[180px] whitespace-nowrap">Punch Timings & Hours</th>
                        <th class="py-3.5 px-2 text-center min-w-[110px] whitespace-nowrap">Status</th>
                        <th class="py-3.5 px-3 min-w-[180px] whitespace-nowrap">Verification & Alert</th>
                        <th class="py-3.5 pl-2 pr-5 text-right min-w-[110px] whitespace-nowrap">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php if (empty($attendanceList)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-10 text-slate-400">
                            <i data-lucide="file-x" class="w-8 h-8 mx-auto text-slate-300 mb-1"></i>
                            <p class="text-xs font-semibold text-slate-600">No verified attendance records found</p>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($attendanceList as $r): ?>
                    <tr class="hover:bg-slate-50/80 transition <?= $r['hr_corrected'] ? 'bg-rose-50/25' : '' ?>">
                        <!-- Employee Profile -->
                        <td class="py-3.5 pl-5 pr-3 align-middle">
                            <div class="flex items-center gap-2.5 overflow-hidden">
                                <img src="<?= $r['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($r['name']) ?>" class="w-8 h-8 rounded-full object-cover ring-1 ring-slate-200 shrink-0" alt="Avatar">
                                <div class="min-w-0 flex-1">
                                    <div class="font-bold text-slate-900 text-xs truncate flex items-center gap-1">
                                        <span class="truncate"><?= htmlspecialchars($r['name']) ?></span>
                                        <?php if ($r['role'] === 'team_lead'): ?>
                                            <span class="px-1.5 py-0.2 bg-indigo-100 text-indigo-700 rounded text-[9px] font-bold shrink-0">TL</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-[11px] text-slate-400 truncate">
                                        <span class="font-mono text-indigo-600 font-semibold"><?= htmlspecialchars($r['emp_id']) ?></span> • TL: <?= htmlspecialchars($r['tl_name'] ?: 'Self') ?>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- Timings & Hours -->
                        <td class="py-3.5 px-3 align-middle font-mono text-[11px]">
                            <?php if ($r['clock_in']): ?>
                                <?php
                                $sessions = [];
                                if (!empty($r['attendance_id'])) {
                                    $sessStmt = $db->prepare("SELECT s.*, u.name as ended_by_name FROM attendance_sessions s LEFT JOIN users u ON s.ended_by_user_id = u.id WHERE s.attendance_id = ? ORDER BY s.session_number ASC");
                                    $sessStmt->execute([$r['attendance_id']]);
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
                                                    <span>Total: <strong class="text-slate-900 font-mono"><?= $r['total_hours'] > 0 ? $r['total_hours'] . ' hrs' : round($totalSessHours, 1) . ' hrs' ?></strong></span>
                                                    <span class="text-slate-300">•</span>
                                                    <span class="px-1.5 py-0.2 rounded-full bg-indigo-50 text-indigo-700 font-bold text-[9px]"><?= count($sessions) ?> Punches</span>
                                                </div>
                                            </div>
                                            <button @click="open = !open" type="button" class="p-1 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-indigo-600 transition shrink-0 flex items-center gap-0.5 text-[10px] font-bold" title="Toggle punch session details">
                                                <span x-text="open ? 'Hide' : 'Details'"></span>
                                                <i data-lucide="chevron-down" class="w-3.5 h-3.5 transition-transform duration-200" :class="open ? 'rotate-180 text-indigo-600' : ''"></i>
                                            </button>
                                        </div>

                                        <!-- Collapsible Breakdown -->
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
                                                            <span class="px-1 py-0.2 rounded bg-rose-100 text-rose-700 text-[8px] font-bold" title="Forced Logout by TL">🚪 TL Forced</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="flex items-center gap-1.5 text-slate-800 font-semibold">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                                        <span><?= formatTime($r['clock_in']) ?></span>
                                        <span class="text-slate-300 font-normal">&rarr;</span>
                                        <span class="text-slate-600"><?= $r['clock_out'] ? formatTime($r['clock_out']) : 'Active' ?></span>
                                    </div>
                                    <div class="text-[10px] font-sans font-medium text-slate-500 mt-0.5 flex items-center gap-1.5">
                                        <span>Total: <strong class="text-slate-800 font-mono"><?= $r['total_hours'] > 0 ? $r['total_hours'] . ' hrs' : 'In Progress' ?></strong></span>
                                        <span class="text-slate-300">•</span>
                                        <span class="px-1.5 py-0.2 rounded-full bg-slate-100 text-slate-600 font-bold text-[9px]">1 Punch</span>
                                    </div>
                                    <?php if (!empty($r['force_logged_out_by'])): ?>
                                        <div class="mt-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-rose-50 border border-rose-200 text-[9px] font-bold text-rose-700">
                                            <i data-lucide="log-out" class="w-2.5 h-2.5 text-rose-500"></i>
                                            Forced Logout by TL at <?= $r['force_logout_at'] ? date('h:i A', strtotime($r['force_logout_at'])) : '' ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-slate-400 font-sans font-medium">No Punch Records</span>
                            <?php endif; ?>
                        </td>

                        <!-- Status -->
                        <td class="py-3.5 px-2 align-middle text-center">
                            <?= getStatusBadge($r['attendance_status'] ?: 'present') ?>
                        </td>

                        <!-- Verification & Alert State -->
                        <td class="py-3.5 px-3 align-middle">
                            <?php if ($r['hr_corrected']): ?>
                                <div class="overflow-hidden">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-300 truncate">
                                        <i data-lucide="shield-alert" class="w-3 h-3 text-rose-600 shrink-0"></i> HR Locked
                                    </span>
                                    <span class="text-[10px] text-rose-600 font-medium block truncate mt-0.5" title="<?= htmlspecialchars($r['hr_alert_message'] ?: '') ?>">
                                        <?= htmlspecialchars($r['hr_alert_message'] ?: 'Corrected by HR') ?>
                                    </span>
                                </div>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    <i data-lucide="check-circle" class="w-3 h-3 text-emerald-600 shrink-0"></i> Approved by TL
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Action -->
                        <td class="py-3.5 pl-2 pr-5 align-middle text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <?php if (!empty($r['clock_in']) && empty($r['clock_out']) && $r['id'] !== $user['id']): ?>
                                    <form action="?action=force-logout-user" method="POST" onsubmit="return confirm('Force logout <?= htmlspecialchars($r['name']) ?> from active session?');" class="inline">
                                        <input type="hidden" name="user_id" value="<?= $r['id'] ?>">
                                        <button type="submit" class="p-1 text-rose-600 hover:text-rose-700 hover:bg-rose-50 rounded-lg border border-rose-200 transition" title="Active Session - End Session / Logout">
                                            <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <button type="button" @click="openModal(<?= htmlspecialchars(json_encode($r)) ?>)" class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-lg transition">
                                    <i data-lucide="edit-3" class="w-3 h-3 text-rose-600"></i> Correct
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- HR Red Alert Correction Modal -->
    <div x-show="hrCorrectModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" x-cloak>
        <div @click.away="hrCorrectModalOpen = false" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center">
                        <i data-lucide="shield-alert" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Correct Attendance & Lock</h3>
                        <p class="text-[11px] text-slate-500" x-text="selectedRecord ? (selectedRecord.name + ' (' + selectedRecord.emp_id + ')') : ''"></p>
                    </div>
                </div>
                <button @click="hrCorrectModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form action="?action=hr-edit-attendance" method="POST" class="space-y-3">
                <input type="hidden" name="user_id" :value="selectedRecord ? selectedRecord.id : ''">
                <input type="hidden" name="date" value="<?= htmlspecialchars($selectedDate) ?>">
                <input type="hidden" name="total_hours" :value="calculatedHours">

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Status</label>
                    <select name="status" x-model="status" @change="onStatusChange($event.target.value)" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold">
                        <option value="present">Present (In Office)</option>
                        <option value="wfh">Work From Home (WFH)</option>
                        <option value="half_day">Half Day (09:30 AM → 01:30 PM, 4 hrs)</option>
                        <option value="absent">Absent</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3" x-show="status !== 'absent'">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Clock In Time</label>
                        <input type="time" name="clock_in_time" x-model="inTime" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Clock Out Time</label>
                        <input type="time" name="clock_out_time" x-model="outTime" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono">
                    </div>
                </div>

                <!-- Live Computed Hours Display -->
                <div class="p-2.5 bg-rose-50/60 rounded-xl border border-rose-100 flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-700 flex items-center gap-1.5">
                        <i data-lucide="clock" class="w-3.5 h-3.5 text-rose-600"></i> Total Hours (Calculated):
                    </span>
                    <span class="text-xs font-mono font-bold text-rose-700 bg-white px-2 py-0.5 rounded shadow-sm border border-rose-100" x-text="calculatedHours + ' hrs'"></span>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-rose-700 uppercase mb-1">Red Alert Notice</label>
                    <input type="text" name="hr_alert_message" x-model="alertReason" required class="w-full bg-rose-50 border border-rose-300 text-rose-800 rounded-xl px-3 py-2 text-xs font-bold">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">HR Audit Note</label>
                    <textarea name="notes" x-model="notes" rows="2" placeholder="Reason for correction..." class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs"></textarea>
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button type="button" @click="hrCorrectModalOpen = false" class="px-3.5 py-1.5 text-xs font-semibold text-slate-500">Cancel</button>
                    <button type="submit" class="px-3.5 py-1.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-xl flex items-center gap-1 shadow-sm">
                        <i data-lucide="shield-alert" class="w-3.5 h-3.5"></i> Save & Lock
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; // End Daily Tab ?>

<?php if ($viewTab === 'monthly'): ?>
    <!-- Header & Month Controls -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="calendar" class="w-5 h-5"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Organization Monthly Attendance Register</h1>
                <p class="text-xs text-slate-500 mt-0.5">Company-wide monthly timesheet, attendance compliance, and staff audit reports.</p>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <a href="?page=admin-attendance&tab=monthly&month=<?= $prevMonth ?>&employee_id=<?= $filterEmployeeId ?>" class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl transition font-bold" title="Previous Month">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
            </a>

            <form method="GET" class="m-0 flex items-center gap-2">
                <input type="hidden" name="page" value="admin-attendance">
                <input type="hidden" name="tab" value="monthly">
                <input type="hidden" name="employee_id" value="<?= $filterEmployeeId ?>">
                <input type="month" name="month" value="<?= $selectedMonth ?>" onchange="this.form.submit()" class="bg-slate-50 border border-slate-300 rounded-xl px-3 py-1.5 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500">
            </form>

            <a href="?page=admin-attendance&tab=monthly&month=<?= $nextMonth ?>&employee_id=<?= $filterEmployeeId ?>" class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl transition font-bold" title="Next Month">
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </a>
        </div>
    </div>

    <!-- Monthly Summary Metric Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="user-check" class="w-5 h-5"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Present Logged</span>
                <span class="text-xl font-extrabold text-emerald-600 leading-tight"><?= $totalOrgPresent ?></span>
                <span class="text-[10px] text-slate-400 block">Across all employees</span>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="user-x" class="w-5 h-5"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Absents Logged</span>
                <span class="text-xl font-extrabold text-rose-600 leading-tight"><?= $totalOrgAbsent ?></span>
                <span class="text-[10px] text-slate-400 block">Unexcused / Absent</span>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="timer" class="w-5 h-5"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Hours Logged</span>
                <span class="text-xl font-extrabold text-indigo-600 leading-tight"><?= number_format($totalOrgHours, 1) ?></span>
                <span class="text-[10px] text-slate-400 block">Company work hours</span>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="percent" class="w-5 h-5"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Avg Compliance</span>
                <span class="text-xl font-extrabold text-purple-600 leading-tight"><?= $orgAvgCompliance ?>%</span>
                <span class="text-[10px] text-slate-400 block"><?= $monthTitle ?></span>
            </div>
        </div>
    </div>

    <!-- Filter Employee Selector -->
    <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3">
        <label class="text-xs font-bold text-slate-700 uppercase">Filter Employee:</label>
        <select onchange="window.location.href='?page=admin-attendance&tab=monthly&month=<?= $selectedMonth ?>&employee_id=' + this.value" class="bg-slate-50 border border-slate-300 rounded-xl px-3 py-1.5 text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-indigo-500">
            <option value="0">All Staff Members (Company Timesheet Matrix)</option>
            <?php foreach ($allStaff as $stf): ?>
                <option value="<?= $stf['id'] ?>" <?= $filterEmployeeId === (int)$stf['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($stf['name']) ?> (<?= htmlspecialchars($stf['emp_id']) ?> - <?= ucfirst($stf['role']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <span class="text-xs text-slate-400 font-mono ml-auto">Month: <?= $monthTitle ?></span>
    </div>

    <?php if ($filterEmployeeId === 0): ?>
        <!-- Company-wide Matrix Table -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto no-scrollbar">
                <table class="w-full text-left text-xs text-slate-600 min-w-[850px]">
                    <thead class="bg-slate-50 text-slate-500 text-[11px] uppercase tracking-wider font-bold border-b border-slate-200">
                        <tr>
                            <th class="min-w-[170px] px-5 py-3.5">Staff Member</th>
                            <th class="min-w-[140px] px-5 py-3.5 whitespace-nowrap">Role / TL</th>
                            <th class="min-w-[110px] px-5 py-3.5 text-center whitespace-nowrap">Present Days</th>
                            <th class="min-w-[100px] px-5 py-3.5 text-center whitespace-nowrap">Absent Days</th>
                            <th class="min-w-[90px] px-5 py-3.5 text-center whitespace-nowrap">Half Days</th>
                            <th class="min-w-[100px] px-5 py-3.5 text-center whitespace-nowrap">Total Hours</th>
                            <th class="min-w-[90px] px-5 py-3.5 text-center whitespace-nowrap">Compliance</th>
                            <th class="min-w-[100px] px-5 py-3.5 text-right whitespace-nowrap">Action</th>
                        </tr>
                    </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($allStaff)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-10 text-slate-400">No active staff records found.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($allStaff as $stf): 
                        $sData = $orgMonthlyByStaff[$stf['id']];
                        $totLogs = count($sData['logs']);
                        $rate = ($totLogs > 0) ? round((($sData['present'] + $sData['wfh'] + ($sData['half_day'] * 0.5)) / max(1, $totLogs)) * 100, 1) : 100;
                    ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="px-5 py-3.5 align-middle">
                                <div class="font-bold text-slate-900 flex items-center gap-1.5">
                                    <span><?= htmlspecialchars($stf['name']) ?></span>
                                    <?php if ($stf['role'] === 'team_lead'): ?>
                                        <span class="px-1.5 py-0.2 bg-indigo-100 text-indigo-700 rounded text-[9px] font-bold">TL</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-[11px] text-slate-400 font-mono"><?= htmlspecialchars($stf['emp_id']) ?> • <?= htmlspecialchars($stf['designation']) ?></div>
                            </td>
                            <td class="px-5 py-3.5 align-middle font-mono text-xs">
                                <?= $stf['role'] === 'team_lead' ? '<span class="text-purple-700 font-bold">Team Lead</span>' : 'TL: ' . htmlspecialchars($stf['tl_name'] ?: 'Direct to HR') ?>
                            </td>
                            <td class="px-5 py-3.5 align-middle text-center">
                                <span class="px-2.5 py-1 rounded-full text-xs font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    <?= $sData['present'] ?> <?= $sData['wfh'] > 0 ? "+ {$sData['wfh']} WFH" : '' ?>
                                </span>
                            </td>
                            <td class="px-5 py-3.5 align-middle text-center">
                                <span class="px-2.5 py-1 rounded-full text-xs font-extrabold <?= $sData['absent'] > 0 ? 'bg-rose-100 text-rose-800 border border-rose-200' : 'bg-slate-100 text-slate-600' ?>">
                                    <?= $sData['absent'] ?>
                                </span>
                            </td>
                            <td class="px-5 py-3.5 align-middle text-center">
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                    <?= $sData['half_day'] ?>
                                </span>
                            </td>
                            <td class="px-5 py-3.5 align-middle text-center font-mono font-bold text-indigo-700">
                                <?= number_format($sData['hours'], 1) ?> hrs
                            </td>
                            <td class="px-5 py-3.5 align-middle text-center font-bold text-purple-700">
                                <?= $rate ?>%
                            </td>
                            <td class="px-5 py-3.5 align-middle text-right">
                                <a href="?page=admin-attendance&tab=monthly&month=<?= $selectedMonth ?>&employee_id=<?= $stf['id'] ?>" class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-lg text-xs font-bold transition inline-flex items-center gap-1">
                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i> Day Logs
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    <?php else: ?>
        <!-- Individual Staff Member Detailed Sheet for HR -->
        <?php 
        $targetStaff = $orgMonthlyByStaff[$filterEmployeeId] ?? null;
        ?>
        <?php if ($targetStaff): ?>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h2 class="text-base font-bold text-slate-900"><?= htmlspecialchars($targetStaff['info']['name']) ?> — Monthly Attendance & Audit Log</h2>
                        <p class="text-xs text-slate-500"><?= htmlspecialchars($targetStaff['info']['emp_id']) ?> • <?= htmlspecialchars($targetStaff['info']['designation']) ?> • Month of <?= $monthTitle ?></p>
                    </div>
                    <a href="?page=admin-attendance&tab=monthly&month=<?= $selectedMonth ?>" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
                        &larr; Back to Company List
                    </a>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-100">
                        <span class="text-[10px] font-bold text-emerald-800 uppercase block">Present Days</span>
                        <span class="text-lg font-extrabold text-emerald-700"><?= $targetStaff['present'] ?></span>
                    </div>
                    <div class="p-3 rounded-xl bg-rose-50 border border-rose-100">
                        <span class="text-[10px] font-bold text-rose-800 uppercase block">Absent Days</span>
                        <span class="text-lg font-extrabold text-rose-700"><?= $targetStaff['absent'] ?></span>
                    </div>
                    <div class="p-3 rounded-xl bg-amber-50 border border-amber-100">
                        <span class="text-[10px] font-bold text-amber-800 uppercase block">Half Days</span>
                        <span class="text-lg font-extrabold text-amber-700"><?= $targetStaff['half_day'] ?></span>
                    </div>
                    <div class="p-3 rounded-xl bg-indigo-50 border border-indigo-100">
                        <span class="text-[10px] font-bold text-indigo-800 uppercase block">Total Hours</span>
                        <span class="text-lg font-extrabold text-indigo-700"><?= number_format($targetStaff['hours'], 1) ?> hrs</span>
                    </div>
                </div>

                <div class="overflow-x-auto no-scrollbar">
                    <table class="w-full text-left text-xs text-slate-600 min-w-[780px]">
                        <thead class="bg-slate-50 text-slate-500 text-[11px] uppercase font-bold border-b border-slate-200">
                            <tr>
                                <th class="min-w-[130px] px-4 py-3 whitespace-nowrap">Date</th>
                                <th class="min-w-[160px] px-4 py-3 whitespace-nowrap">Punch Timings</th>
                                <th class="min-w-[90px] px-4 py-3 text-center whitespace-nowrap">Total Hours</th>
                                <th class="min-w-[90px] px-4 py-3 text-center whitespace-nowrap">Status</th>
                                <th class="min-w-[130px] px-4 py-3 whitespace-nowrap">Audit / Lock Status</th>
                                <th class="min-w-[140px] px-4 py-3">Notes & Reason</th>
                                <th class="min-w-[90px] px-4 py-3 text-right whitespace-nowrap">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($targetStaff['logs'])): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-8 text-slate-400">No attendance logs found for this month.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($targetStaff['logs'] as $log): ?>
                                <tr class="hover:bg-slate-50/80 transition <?= $log['hr_corrected'] ? 'bg-rose-50/30' : '' ?>">
                                    <td class="px-4 py-3 font-bold text-slate-900"><?= formatDate($log['date']) ?> (<?= date('D', strtotime($log['date'])) ?>)</td>
                                    <td class="px-4 py-3 font-mono text-xs">
                                        <?= formatTime($log['clock_in']) ?> &rarr; <?= $log['clock_out'] ? formatTime($log['clock_out']) : 'Active' ?>
                                    </td>
                                    <td class="px-4 py-3 text-center font-bold text-slate-800"><?= $log['total_hours'] > 0 ? $log['total_hours'] . 'h' : '-' ?></td>
                                    <td class="px-4 py-3 text-center"><?= getStatusBadge($log['status']) ?></td>
                                    <td class="px-4 py-3">
                                        <?php if ($log['hr_corrected']): ?>
                                            <span class="text-[10px] font-bold text-rose-700 bg-rose-100 px-2 py-0.5 rounded-full border border-rose-200">HR Locked</span>
                                        <?php elseif ($log['tl_approved']): ?>
                                            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">TL Verified</span>
                                        <?php else: ?>
                                            <span class="text-[10px] font-semibold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-[11px] text-slate-500 max-w-xs truncate"><?= htmlspecialchars($log['notes'] ?: ($log['hr_alert_message'] ?: '-')) ?></td>
                                    <td class="px-4 py-3 text-right">
                                        <button type="button" @click="openModal(<?= htmlspecialchars(json_encode(array_merge($log, ['name' => $targetStaff['info']['name'], 'emp_id' => $targetStaff['info']['emp_id']]))) ?>)" class="px-2.5 py-1 text-[11px] font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-lg transition">
                                            <i data-lucide="edit-3" class="w-3 h-3 inline"></i> Correct
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; // End Monthly Tab ?>

</div>
