<!-- views/employee/attendance.php -->
<?php
$user = authUser();
$db = getDBConnection();

// Selected month (defaults to current month YYYY-MM)
$selectedMonth = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

$startDate = $selectedMonth . '-01';
$daysInMonth = (int)date('t', strtotime($startDate));
$endDate = $selectedMonth . '-' . str_pad($daysInMonth, 2, '0', STR_PAD_LEFT);
$monthTitle = date('F Y', strtotime($startDate));

// Prev & Next Month Links
$prevMonth = date('Y-m', strtotime("$startDate -1 month"));
$nextMonth = date('Y-m', strtotime("$startDate +1 month"));
$isCurrentMonth = ($selectedMonth === date('Y-m'));

// Fetch all attendance records for this employee in selected month
$stmt = $db->prepare("
    SELECT * FROM attendance 
    WHERE user_id = ? AND date >= ? AND date <= ?
    ORDER BY date DESC
");
$stmt->execute([$user['id'], $startDate, $endDate]);
$rawLogs = $stmt->fetchAll();

// Index by date
$attendanceMap = [];
foreach ($rawLogs as $l) {
    $attendanceMap[$l['date']] = $l;
}

// Calculate Monthly Statistics
$presentDays = 0;
$absentDays = 0;
$halfDays = 0;
$wfhDays = 0;
$totalHoursLogged = 0;
$verifiedCount = 0;

foreach ($rawLogs as $l) {
    $status = strtolower($l['status'] ?? '');
    if ($status === 'present') {
        $presentDays++;
    } elseif ($status === 'half_day') {
        $halfDays++;
    } elseif ($status === 'wfh') {
        $wfhDays++;
    } elseif ($status === 'absent') {
        $absentDays++;
    }

    $totalHoursLogged += (float)($l['total_hours'] ?? 0);
    if (!empty($l['tl_approved']) || !empty($l['hr_corrected'])) {
        $verifiedCount++;
    }
}

// Working days in month up to today
$totalLoggedEntries = count($rawLogs);
$complianceRate = ($totalLoggedEntries > 0) 
    ? round((($presentDays + $wfhDays + ($halfDays * 0.5)) / max(1, $totalLoggedEntries)) * 100, 1) 
    : 100;
?>

<div class="space-y-6" x-data="{ activeTab: 'table' }">

    <!-- Header & Month Selector Toolbar -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="calendar" class="w-6 h-6"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">My Monthly Attendance History</h1>
                <p class="text-xs text-slate-500 mt-0.5">Comprehensive audit of your monthly check-ins, leaves, hours, and compliance.</p>
            </div>
        </div>

        <!-- Month Navigation Controller -->
        <div class="flex items-center gap-2 flex-wrap">
            <a href="?page=employee-attendance&month=<?= $prevMonth ?>" class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl transition font-bold" title="Previous Month">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
            </a>

            <form method="GET" action="" class="m-0 flex items-center gap-2">
                <input type="hidden" name="page" value="employee-attendance">
                <input type="month" name="month" value="<?= $selectedMonth ?>" onchange="this.form.submit()" class="bg-slate-50 border border-slate-300 rounded-xl px-3 py-1.5 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500">
            </form>

            <a href="?page=employee-attendance&month=<?= $nextMonth ?>" class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl transition font-bold" title="Next Month">
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </a>

            <?php if (!$isCurrentMonth): ?>
                <a href="?page=employee-attendance&month=<?= date('Y-m') ?>" class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 text-xs font-bold rounded-xl transition">
                    Current Month
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Monthly Summary Metric Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3.5">
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="user-check" class="w-5 h-5"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Present Days</span>
                <span class="text-xl font-extrabold text-emerald-600 leading-tight"><?= $presentDays ?></span>
                <span class="text-[10px] text-slate-400 block"><?= $wfhDays > 0 ? "+ {$wfhDays} WFH" : 'In Office' ?></span>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="user-x" class="w-5 h-5"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Absent Days</span>
                <span class="text-xl font-extrabold text-rose-600 leading-tight"><?= $absentDays ?></span>
                <span class="text-[10px] text-slate-400 block">Unmarked / Absent</span>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="clock" class="w-5 h-5"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Half Days</span>
                <span class="text-xl font-extrabold text-amber-600 leading-tight"><?= $halfDays ?></span>
                <span class="text-[10px] text-slate-400 block">Partial Shifts</span>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="timer" class="w-5 h-5"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Hours Logged</span>
                <span class="text-xl font-extrabold text-indigo-600 leading-tight"><?= number_format($totalHoursLogged, 1) ?></span>
                <span class="text-[10px] text-slate-400 block">Total Work Hours</span>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-3 col-span-2 sm:col-span-1">
            <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="percent" class="w-5 h-5"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Compliance</span>
                <span class="text-xl font-extrabold text-purple-600 leading-tight"><?= $complianceRate ?>%</span>
                <span class="text-[10px] text-slate-400 block"><?= $monthTitle ?></span>
            </div>
        </div>
    </div>

    <!-- View Switcher Tabs -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
            <button type="button" @click="activeTab = 'table'" :class="activeTab === 'table' ? 'bg-indigo-600 text-white font-bold shadow-sm' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50'" class="px-4 py-2 rounded-xl text-xs transition inline-flex items-center gap-1.5">
                <i data-lucide="list" class="w-4 h-4"></i> Detailed Log Sheet
            </button>
            <button type="button" @click="activeTab = 'calendar'" :class="activeTab === 'calendar' ? 'bg-indigo-600 text-white font-bold shadow-sm' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50'" class="px-4 py-2 rounded-xl text-xs transition inline-flex items-center gap-1.5">
                <i data-lucide="grid" class="w-4 h-4"></i> Monthly Calendar Grid
            </button>
        </div>
        <span class="text-xs text-slate-500 font-medium">Showing records for <strong><?= $monthTitle ?></strong></span>
    </div>

    <!-- TAB 1: Detailed Table View -->
    <div x-show="activeTab === 'table'" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto no-scrollbar">
            <table class="w-full text-left text-xs text-slate-600 min-w-[760px]">
                <thead class="bg-slate-50 text-slate-500 text-[11px] uppercase tracking-wider font-bold border-b border-slate-200">
                    <tr>
                        <th class="min-w-[130px] px-5 py-3.5 whitespace-nowrap">Date & Day</th>
                        <th class="min-w-[170px] px-5 py-3.5 whitespace-nowrap">Punch Timings</th>
                        <th class="min-w-[100px] px-5 py-3.5 text-center whitespace-nowrap">Total Hours</th>
                        <th class="min-w-[100px] px-5 py-3.5 text-center whitespace-nowrap">Status</th>
                        <th class="min-w-[160px] px-5 py-3.5 whitespace-nowrap">Approval / Audit Status</th>
                        <th class="min-w-[160px] px-5 py-3.5">Notes & Remarks</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($rawLogs)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-12 text-slate-400">
                                <i data-lucide="calendar-x" class="w-8 h-8 mx-auto text-slate-300 mb-2"></i>
                                <p class="text-xs font-semibold text-slate-600">No attendance records logged for <?= $monthTitle ?></p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($rawLogs as $l): ?>
                        <tr class="hover:bg-slate-50/80 transition <?= $l['hr_corrected'] ? 'bg-rose-50/30' : '' ?>">
                            <td class="px-5 py-3.5 align-middle">
                                <div class="font-bold text-slate-900"><?= formatDate($l['date']) ?></div>
                                <div class="text-[10px] text-slate-400"><?= date('l', strtotime($l['date'])) ?></div>
                            </td>
                            <td class="px-5 py-3.5 align-middle font-mono">
                                <?php if ($l['clock_in']): ?>
                                    <div class="text-slate-800 font-semibold"><?= formatTime($l['clock_in']) ?> &rarr; <?= $l['clock_out'] ? formatTime($l['clock_out']) : '<span class="text-emerald-600 font-bold">Active Shift</span>' ?></div>
                                <?php else: ?>
                                    <span class="text-slate-400 italic">No Punches</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3.5 align-middle text-center font-bold text-slate-900">
                                <?= $l['total_hours'] > 0 ? number_format($l['total_hours'], 2) . ' hrs' : '-' ?>
                            </td>
                            <td class="px-5 py-3.5 align-middle text-center">
                                <?= getStatusBadge($l['status'] ?? 'absent') ?>
                            </td>
                            <td class="px-5 py-3.5 align-middle">
                                <?php if ($l['hr_corrected']): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200" title="<?= htmlspecialchars($l['hr_alert_message'] ?? '') ?>">
                                        <i data-lucide="shield-alert" class="w-3 h-3 text-rose-600"></i> HR Corrected
                                    </span>
                                <?php elseif ($l['tl_approved']): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <i data-lucide="check" class="w-3 h-3 text-emerald-600"></i> TL Verified
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                        Pending Review
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3.5 align-middle text-[11px] text-slate-500 max-w-xs truncate">
                                <?= htmlspecialchars($l['notes'] ?: ($l['hr_alert_message'] ?: '-')) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB 2: Visual Monthly Calendar Grid -->
    <div x-show="activeTab === 'calendar'" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4" x-cloak>
        <div class="grid grid-cols-7 gap-2 text-center text-xs font-bold text-slate-500 uppercase tracking-wider pb-2 border-b border-slate-100">
            <span>Sun</span>
            <span>Mon</span>
            <span>Tue</span>
            <span>Wed</span>
            <span>Thu</span>
            <span>Fri</span>
            <span>Sat</span>
        </div>

        <?php
        $firstDayOfWeek = (int)date('w', strtotime($startDate)); // 0 for Sun
        ?>
        <div class="grid grid-cols-7 gap-2">
            <!-- Empty offset cells before 1st of month -->
            <?php for ($i = 0; $i < $firstDayOfWeek; $i++): ?>
                <div class="min-h-[85px] p-2 rounded-xl bg-slate-50/40 border border-transparent"></div>
            <?php endfor; ?>

            <!-- Days of the month -->
            <?php for ($d = 1; $d <= $daysInMonth; $d++): 
                $dateStr = $selectedMonth . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
                $isWeekend = (date('N', strtotime($dateStr)) >= 6); // Sat/Sun
                $rec = $attendanceMap[$dateStr] ?? null;
                $isToday = ($dateStr === date('Y-m-d'));
            ?>
                <div class="min-h-[90px] p-2.5 rounded-xl border flex flex-col justify-between transition <?= $isToday ? 'ring-2 ring-indigo-500 bg-indigo-50/30' : ($rec ? ($rec['status'] === 'present' ? 'bg-emerald-50/40 border-emerald-200' : ($rec['status'] === 'absent' ? 'bg-rose-50/40 border-rose-200' : 'bg-amber-50/40 border-amber-200')) : ($isWeekend ? 'bg-slate-50 border-slate-200 opacity-60' : 'bg-white border-slate-200')) ?>">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold <?= $isToday ? 'text-indigo-700' : 'text-slate-800' ?>"><?= $d ?></span>
                        <?php if ($isToday): ?>
                            <span class="text-[9px] font-bold uppercase px-1.5 py-0.2 rounded bg-indigo-600 text-white">Today</span>
                        <?php elseif ($isWeekend): ?>
                            <span class="text-[9px] font-semibold text-slate-400">Off</span>
                        <?php endif; ?>
                    </div>

                    <div class="my-1">
                        <?php if ($rec): ?>
                            <div class="text-[10px] font-bold uppercase <?= $rec['status'] === 'present' ? 'text-emerald-700' : ($rec['status'] === 'absent' ? 'text-rose-700' : 'text-amber-700') ?>">
                                <?= str_replace('_', ' ', $rec['status']) ?>
                            </div>
                            <?php if ($rec['clock_in']): ?>
                                <div class="text-[9px] font-mono text-slate-500 truncate">
                                    <?= formatTime($rec['clock_in']) ?>
                                </div>
                            <?php endif; ?>
                        <?php elseif (!$isWeekend && $dateStr <= date('Y-m-d')): ?>
                            <span class="text-[10px] text-slate-400 italic">No log</span>
                        <?php endif; ?>
                    </div>

                    <div class="text-[9px] text-right text-slate-400 font-mono">
                        <?= ($rec && $rec['total_hours'] > 0) ? $rec['total_hours'] . 'h' : '' ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>

</div>
