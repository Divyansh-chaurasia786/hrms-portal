<!-- views/employee/dashboard.php -->
<?php
$user = authUser();
$db = getDBConnection();
$today = date('Y-m-d');

// Fetch user profile with TL name
$stmtU = $db->prepare("SELECT u.*, tl.name as tl_name FROM users u LEFT JOIN users tl ON u.reporting_tl_id = tl.id WHERE u.id = ?");
$stmtU->execute([$user['id']]);
$userProfile = $stmtU->fetch() ?: $user;

// Attendance today
$todayAtt = AttendanceController::getTodayAttendanceForUser($user['id']);

// Fetch effective office location for geofence (inherits TL permanent/temporary location)
$empOfficeLocation = GEOFENCE_ENABLED ? getEffectiveUserLocation((int)$user['id']) : null;

// My active tasks
$stmt = $db->prepare("
    SELECT t.*, p.title as project_title, u.name as tl_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    JOIN users u ON t.created_by = u.id
    WHERE t.assigned_to = ? AND t.status != 'completed'
    ORDER BY CASE t.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END, t.due_date ASC
");
$stmt->execute([$user['id']]);
$myTasks = $stmt->fetchAll();

// Leave counts
$leaveTypes = $db->query("SELECT * FROM leave_types ORDER BY id ASC")->fetchAll();
$usedLeavesStmt = $db->prepare("SELECT leave_type_id, SUM(total_days) as used_days FROM leave_applications WHERE user_id = ? AND status = 'approved' GROUP BY leave_type_id");
$usedLeavesStmt->execute([$user['id']]);
$usedLeaves = $usedLeavesStmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<div class="space-y-6" x-data="{ submitModalOpen: false, activeTask: null }">
    <!-- HR Notice Banner (Sleek Modern Alert) -->
    <?php if ($todayAtt && $todayAtt['hr_corrected']): ?>
        <div class="p-4 rounded-2xl bg-rose-50/80 border border-rose-200 text-rose-900 shadow-sm flex items-start gap-3.5">
            <div class="p-2 rounded-xl bg-rose-100 text-rose-700 shrink-0">
                <i data-lucide="shield-alert" class="w-5 h-5"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-rose-900">Official HR Attendance Audit Notice</h3>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-200 text-rose-800">Locked by HR</span>
                </div>
                <p class="text-xs text-rose-700 font-medium mt-1"><?= htmlspecialchars($todayAtt['hr_alert_message'] ?: 'Your attendance was audited and corrected by HR.') ?></p>
                <div class="flex items-center gap-3 text-[11px] text-rose-600 font-mono mt-1.5 flex-wrap">
                    <span>Verified: <strong>In <?= formatTime($todayAtt['clock_in']) ?></strong></span>
                    <span>•</span>
                    <span><strong>Out <?= formatTime($todayAtt['clock_out']) ?></strong> (<?= $todayAtt['total_hours'] ?> hrs)</span>
                    <?php if (!empty($todayAtt['notes'])): ?>
                        <span>•</span>
                        <span>Reason: <?= htmlspecialchars($todayAtt['notes']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Official HR Warning Notice Banner -->
    <?php if (!empty($userProfile['hr_warning_message'])): ?>
        <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-900 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-start gap-3.5">
                <div class="p-2 rounded-xl bg-rose-500/20 text-rose-700 shrink-0 mt-0.5">
                    <i data-lucide="shield-alert" class="w-5 h-5"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-rose-900">⚠️ Official HR Disciplinary Warning Notice</h3>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-200 text-rose-900">Action Recorded</span>
                    </div>
                    <p class="text-xs text-rose-800 font-medium mt-1">
                        <?= htmlspecialchars($userProfile['hr_warning_message']) ?>
                    </p>
                    <p class="text-[11px] text-rose-900 mt-1.5 font-bold flex items-center gap-1.5">
                        <span>⚠️ Policy:</span>
                        <span class="font-semibold text-rose-800">This formal warning has been registered in your employee record. Please adhere to company policies.</span>
                    </p>
                </div>
            </div>

            <div class="shrink-0 flex items-center justify-end">
                <form action="?action=ack-hr-warning" method="POST" class="m-0">
                    <button type="submit" class="px-4 py-2 bg-rose-700 hover:bg-rose-800 text-white text-xs font-bold rounded-xl transition shadow-xs flex items-center gap-1.5">
                        <i data-lucide="check-circle" class="w-4 h-4"></i> Acknowledge Warning
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- New Team Lead Assigned Broadcast Notice -->
    <?php if (!empty($userProfile['new_tl_notice'])): ?>
        <div class="p-4 rounded-2xl bg-indigo-500/10 border border-indigo-500/30 text-indigo-950 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-start gap-3.5">
                <div class="p-2 rounded-xl bg-indigo-500/20 text-indigo-700 shrink-0 mt-0.5">
                    <i data-lucide="users" class="w-5 h-5"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-indigo-900">👔 Team Lead Hierarchy Update</h3>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-200 text-indigo-900">Organization Update</span>
                    </div>
                    <p class="text-xs text-indigo-900 font-semibold mt-1">
                        <?= htmlspecialchars($userProfile['new_tl_notice']) ?>
                    </p>
                    <p class="text-[11px] text-indigo-800 mt-1 font-medium">
                        Your direct reporting line has been updated. Please direct all sprint deliverables, daily attendance queries, and leave applications to your new Lead.
                    </p>
                </div>
            </div>

            <div class="shrink-0 flex items-center justify-end">
                <form action="?action=ack-new-tl-notice" method="POST" class="m-0">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition shadow-xs flex items-center gap-1.5">
                        <i data-lucide="check-circle" class="w-4 h-4"></i> Acknowledge
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php
    // Check for today's force logout alert for this employee (if not acknowledged)
    $forceLogoutRecord = $db->query("
        SELECT a.id as attendance_id, a.force_logout_at, tl.name as tl_name, st.reason
        FROM attendance a
        LEFT JOIN users tl ON a.force_logged_out_by = tl.id
        LEFT JOIN session_terminations st ON st.user_id = a.user_id AND DATE(st.created_at) = a.date
        WHERE a.user_id = {$user['id']} AND a.date = '$today' AND a.force_logged_out_by IS NOT NULL AND (a.force_logout_acknowledged = 0 OR a.force_logout_acknowledged IS NULL)
        ORDER BY a.force_logout_at DESC LIMIT 1
    ")->fetch();
    ?>
    <?php if ($forceLogoutRecord): ?>
        <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-900 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-start gap-3.5">
                <div class="p-2 rounded-xl bg-amber-500/20 text-amber-700 shrink-0 mt-0.5">
                    <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-amber-900">⚠️ Attendance Warning: Force Logout</h3>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-200 text-amber-900">Action Required</span>
                    </div>
                    <p class="text-xs text-amber-800 font-medium mt-1">
                        Your active session was force logged out by Team Lead <strong><?= htmlspecialchars($forceLogoutRecord['tl_name'] ?: 'Team Lead') ?></strong> at <strong><?= date('h:i A', strtotime($forceLogoutRecord['force_logout_at'])) ?></strong>.
                        <?php if (!empty($forceLogoutRecord['reason'])): ?>
                            <span class="block mt-0.5 text-amber-700">Reason: <?= htmlspecialchars($forceLogoutRecord['reason']) ?></span>
                        <?php endif; ?>
                    </p>
                    <p class="text-[11px] text-amber-900 mt-1.5 font-bold flex items-center gap-1.5">
                        <span>⚠️ Policy:</span>
                        <span class="font-semibold text-amber-800">Please remember to punch out on time when ending your shift.</span>
                    </p>
                </div>
            </div>

            <div class="shrink-0 flex items-center justify-end">
                <form action="?action=acknowledge-force-logout" method="POST" class="m-0">
                    <input type="hidden" name="attendance_id" value="<?= $forceLogoutRecord['attendance_id'] ?>">
                    <button type="submit" class="px-4 py-2 bg-amber-700 hover:bg-amber-800 text-white text-xs font-bold rounded-xl transition shadow-xs flex items-center gap-1.5">
                        <i data-lucide="check-circle" class="w-4 h-4"></i> Acknowledge
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Hero Profile & Shift Command Hub -->
    <?php
    $isCurrentlyIn = ($todayAtt && $todayAtt['clock_out'] === null);
    $empSessions = [];
    if ($todayAtt && !empty($todayAtt['id'])) {
        $sessStmt = $db->prepare("SELECT * FROM attendance_sessions WHERE attendance_id = ? ORDER BY session_number ASC");
        $sessStmt->execute([$todayAtt['id']]);
        $empSessions = $sessStmt->fetchAll();
    }
    ?>
    <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6 relative overflow-hidden">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
            
            <!-- Left Side: Employee Profile & Quick Access (7 cols) -->
            <div class="lg:col-span-7 flex flex-col sm:flex-row items-start sm:items-center gap-5">
                <div class="relative shrink-0">
                    <img src="<?= $userProfile['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($userProfile['name']) ?>" class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl object-cover ring-4 ring-indigo-50 shadow-md" alt="Avatar">
                    <span class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full bg-emerald-500 ring-2 ring-white flex items-center justify-center text-[10px] text-white font-bold" title="Active">✓</span>
                </div>
                <div class="space-y-1.5 flex-1 min-w-0">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Welcome, <?= htmlspecialchars($userProfile['name']) ?>! 👋</h1>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 uppercase">
                            <?= htmlspecialchars(str_replace('_', ' ', $userProfile['role'])) ?>
                        </span>
                    </div>
                    <div class="text-xs text-slate-600 flex items-center gap-2 flex-wrap font-medium">
                        <span class="font-bold text-slate-800"><?= htmlspecialchars($userProfile['designation'] ?: 'Software Engineer') ?></span>
                        <span class="text-slate-300">•</span>
                        <span class="font-mono text-indigo-600 font-semibold bg-indigo-50/60 px-1.5 py-0.5 rounded border border-indigo-100"><?= htmlspecialchars($userProfile['emp_id'] ?? '') ?></span>
                        <span class="text-slate-300">•</span>
                        <span class="text-slate-500">TL: <strong class="text-slate-800 font-semibold"><?= htmlspecialchars($userProfile['tl_name'] ?? 'HR / Direct') ?></strong></span>
                    </div>

                    <!-- Quick Navigation Chips -->
                    <div class="pt-2 flex items-center gap-2 flex-wrap">
                        <a href="?page=employee-leaves" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition inline-flex items-center gap-1.5 shadow-2xs">
                            <i data-lucide="calendar-plus" class="w-3.5 h-3.5 text-indigo-600"></i> Apply Leave
                        </a>
                        <a href="?page=employee-tasks" class="px-3 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold transition inline-flex items-center gap-1.5 border border-indigo-200/60 shadow-2xs">
                            <i data-lucide="kanban" class="w-3.5 h-3.5 text-indigo-600"></i> Task Board
                        </a>
                        <a href="?page=tech-drive" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition inline-flex items-center gap-1.5 shadow-2xs">
                            <i data-lucide="folder" class="w-3.5 h-3.5 text-slate-500"></i> Cloud Drive
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right Side: Shift & Web Punch Command Center (5 cols) -->
            <div class="lg:col-span-5 bg-slate-50/90 rounded-2xl p-4 sm:p-5 border border-slate-200/80 flex flex-col justify-between space-y-3.5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5 text-xs text-slate-600 font-semibold truncate max-w-[200px]" title="<?= $empOfficeLocation ? htmlspecialchars($empOfficeLocation['name']) : 'Office' ?>">
                        <i data-lucide="map-pin" class="w-3.5 h-3.5 text-indigo-600 shrink-0"></i>
                        <span class="truncate"><?= $empOfficeLocation ? htmlspecialchars($empOfficeLocation['name']) : 'Office' ?></span>
                    </div>
                    <div class="text-right">
                        <span class="text-xs font-mono font-bold text-slate-800" id="liveClock"><?= date('h:i:s A') ?></span>
                        <span class="text-[10px] text-slate-400 block -mt-0.5">IST (UTC+5:30)</span>
                    </div>
                </div>

                <!-- Shift Status Banner -->
                <?php if ($isCurrentlyIn): ?>
                    <div class="p-2.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 flex items-center justify-between text-xs font-semibold">
                        <span class="flex items-center gap-1.5 text-[11px]">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span>Active Shift</span>
                        </span>
                        <span class="font-mono text-emerald-800 font-bold">In: <?= formatTime($todayAtt['clock_in']) ?></span>
                    </div>
                <?php else: ?>
                    <div class="p-2.5 rounded-xl bg-white border border-slate-200 text-slate-600 flex items-center justify-between text-xs font-semibold shadow-2xs">
                        <span class="flex items-center gap-1.5 text-[11px]">
                            <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                            <span>Currently Logged Out</span>
                        </span>
                        <span class="font-mono text-slate-700 font-bold"><?= $todayAtt ? $todayAtt['total_hours'] . ' hrs logged' : '0.0 hrs today' ?></span>
                    </div>
                <?php endif; ?>

                <!-- Action Button -->
                <?php if ($isCurrentlyIn): ?>
                    <div x-data="{
                        outState: 'idle',
                        outLat: 0,
                        outLng: 0,
                        doEmpPunchOut() {
                            this.outState = 'saving';
                            if (navigator.geolocation) {
                                navigator.geolocation.getCurrentPosition(
                                    (p) => {
                                        this.outLat = p.coords.latitude;
                                        this.outLng = p.coords.longitude;
                                        this.$nextTick(() => this.$refs.empPunchOutForm.submit());
                                    },
                                    (e) => {
                                        this.$nextTick(() => this.$refs.empPunchOutForm.submit());
                                    },
                                    { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
                                );
                            } else {
                                this.$nextTick(() => this.$refs.empPunchOutForm.submit());
                            }
                        }
                    }">
                        <form x-ref="empPunchOutForm" action="?action=clock-out" method="POST" @submit.prevent="doEmpPunchOut()">
                            <input type="hidden" name="latitude" :value="outLat">
                            <input type="hidden" name="longitude" :value="outLng">
                            <button type="submit" :disabled="outState === 'saving'" class="w-full py-2.5 bg-rose-600 hover:bg-rose-700 disabled:bg-slate-500 text-white font-bold text-xs rounded-xl shadow-md shadow-rose-600/20 transition flex items-center justify-center gap-1.5 cursor-pointer">
                                <template x-if="outState === 'saving'">
                                    <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                </template>
                                <template x-if="outState !== 'saving'">
                                    <i data-lucide="stop-circle" class="w-4 h-4"></i>
                                </template>
                                <span x-text="outState === 'saving' ? 'Recording Location...' : 'Punch Out (End Shift)'"></span>
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div x-data="{
                        status: 'present',
                        locState: 'idle',
                        locError: '',
                        latitude: 0,
                        longitude: 0,
                        distance: 0,
                        officeLat: <?= $empOfficeLocation ? $empOfficeLocation['lat'] : 0 ?>,
                        officeLng: <?= $empOfficeLocation ? $empOfficeLocation['lng'] : 0 ?>,
                        officeName: '<?= $empOfficeLocation ? addslashes($empOfficeLocation['name']) : 'Not Assigned' ?>',
                        punchIn() {
                            if (this.status === 'wfh') {
                                this.$refs.punchForm.submit();
                                return;
                            }
                            if (!this.officeLat || !this.officeLng) {
                                this.locState = 'error';
                                this.locError = 'No office location assigned. Contact HR.';
                                return;
                            }
                            this.locState = 'checking';
                            this.locError = '';
                            if (!navigator.geolocation) {
                                this.locState = 'error';
                                this.locError = 'Browser does not support geolocation.';
                                return;
                            }
                            navigator.geolocation.getCurrentPosition(
                                (pos) => {
                                    this.latitude = pos.coords.latitude;
                                    this.longitude = pos.coords.longitude;
                                    const R = 6371000;
                                    const dLat = (this.officeLat - this.latitude) * Math.PI / 180;
                                    const dLon = (this.officeLng - this.longitude) * Math.PI / 180;
                                    const a = Math.sin(dLat/2)*Math.sin(dLat/2) + Math.cos(this.latitude*Math.PI/180)*Math.cos(this.officeLat*Math.PI/180)*Math.sin(dLon/2)*Math.sin(dLon/2);
                                    this.distance = R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
                                    if (this.distance <= <?= GEOFENCE_RADIUS_METERS ?>) {
                                        this.locState = 'in_range';
                                        this.$nextTick(() => this.$refs.punchForm.submit());
                                    } else {
                                        this.locState = 'out_of_range';
                                        this.locError = '📍 Out of location! ' + (this.distance/1000).toFixed(1) + ' km away from office.';
                                    }
                                },
                                (err) => {
                                    this.locState = 'error';
                                    if (err.code === 1) this.locError = 'Location permission denied. Please allow in browser.';
                                    else this.locError = 'Location unavailable. Try again.';
                                },
                                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                            );
                        }
                    }" class="space-y-2">
                        <template x-if="locState === 'out_of_range' || locState === 'error'">
                            <div class="p-2 rounded-lg bg-rose-50 border border-rose-200 text-[11px] font-semibold text-rose-700">
                                <span x-text="locError"></span>
                            </div>
                        </template>
                        <form x-ref="punchForm" action="?action=clock-in" method="POST" @submit.prevent="punchIn()">
                            <input type="hidden" name="status" :value="status">
                            <input type="hidden" name="latitude" :value="latitude">
                            <input type="hidden" name="longitude" :value="longitude">
                            <button type="submit" :disabled="locState === 'checking'" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-400 text-white font-bold text-xs rounded-xl shadow-md shadow-emerald-600/20 transition flex items-center justify-center gap-1.5">
                                <template x-if="locState === 'checking'">
                                    <span class="flex items-center gap-1.5"><svg class="animate-spin w-3.5 h-3.5" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg> Verifying GPS...</span>
                                </template>
                                <template x-if="locState !== 'checking'">
                                    <span class="flex items-center gap-1.5"><i data-lucide="play-circle" class="w-4 h-4"></i> Punch In (Start Shift)</span>
                                </template>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 4 Key Operational Metric Cards Row (Interactive Clickable Links) -->
    <?php
    $pendingTasksCount = count($myTasks);
    $underReviewCount = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE assigned_to = {$user['id']} AND status = 'review'")->fetchColumn();
    $curMonth = date('Y-m');
    $monthDaysCount = (int)$db->query("SELECT COUNT(DISTINCT date) FROM attendance WHERE user_id = {$user['id']} AND date LIKE '{$curMonth}%' AND (clock_in IS NOT NULL OR status IN ('present', 'wfh'))")->fetchColumn();
    ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Metric 1: Today's Shift -->
        <a href="?page=attendance" class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-2xs flex flex-col justify-between hover:border-indigo-300 hover:shadow-md hover:-translate-y-0.5 transition-all cursor-pointer group block">
            <div>
                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider group-hover:text-indigo-600 transition-colors">Today's Shift</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $isCurrentlyIn ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600' ?>">
                        <?= $isCurrentlyIn ? 'Active' : ($todayAtt ? 'Logged' : 'Pending') ?>
                    </span>
                </div>
                <div class="my-3">
                    <div class="text-3xl font-extrabold text-slate-900 tracking-tight font-mono group-hover:text-indigo-600 transition-colors">
                        <?= $todayAtt ? $todayAtt['total_hours'] . 'h' : '0.0h' ?>
                    </div>
                    <span class="text-xs text-slate-400 font-medium"><?= count($empSessions) > 0 ? count($empSessions) . ' session(s) recorded' : 'No punch sessions yet' ?></span>
                </div>
            </div>
            <div class="pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                <span>Status: <strong class="text-slate-700"><?= $todayAtt ? ucfirst($todayAtt['status']) : 'Not Marked' ?></strong></span>
                <span class="font-bold text-indigo-600 group-hover:text-indigo-800">History &rarr;</span>
            </div>
        </a>

        <!-- Metric 2: Open Tasks & Deliverables -->
        <a href="?page=employee-tasks" class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-2xs flex flex-col justify-between hover:border-blue-300 hover:shadow-md hover:-translate-y-0.5 transition-all cursor-pointer group block">
            <div>
                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider group-hover:text-blue-600 transition-colors">Open Tasks</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                        In Queue
                    </span>
                </div>
                <div class="my-3">
                    <div class="text-3xl font-extrabold text-slate-900 tracking-tight font-mono group-hover:text-blue-600 transition-colors">
                        <?= $pendingTasksCount ?>
                    </div>
                    <span class="text-xs text-slate-400 font-medium">Assigned sprint deliverables</span>
                </div>
            </div>
            <div class="pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                <span>Priority: <strong class="text-slate-700">Sprint Backlog</strong></span>
                <span class="font-bold text-indigo-600 group-hover:text-indigo-800">View Board &rarr;</span>
            </div>
        </a>

        <!-- Metric 3: Submissions Under Review -->
        <a href="?page=employee-tasks" class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-2xs flex flex-col justify-between hover:border-amber-300 hover:shadow-md hover:-translate-y-0.5 transition-all cursor-pointer group block">
            <div>
                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider group-hover:text-amber-600 transition-colors">Under TL Review</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                        Pending TL
                    </span>
                </div>
                <div class="my-3">
                    <div class="text-3xl font-extrabold text-slate-900 tracking-tight font-mono group-hover:text-amber-600 transition-colors">
                        <?= $underReviewCount ?>
                    </div>
                    <span class="text-xs text-slate-400 font-medium">Deliverables submitted</span>
                </div>
            </div>
            <div class="pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                <span>Status: <strong class="text-slate-700">Awaiting Feedback</strong></span>
                <span class="font-bold text-indigo-600 group-hover:text-indigo-800">Review &rarr;</span>
            </div>
        </a>

        <!-- Metric 4: Monthly Present Days -->
        <a href="?page=attendance" class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-2xs flex flex-col justify-between hover:border-purple-300 hover:shadow-md hover:-translate-y-0.5 transition-all cursor-pointer group block">
            <div>
                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider group-hover:text-purple-600 transition-colors"><?= date('F') ?> Attendance</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-50 text-purple-700 border border-purple-200">
                        Monthly
                    </span>
                </div>
                <div class="my-3">
                    <div class="text-3xl font-extrabold text-slate-900 tracking-tight font-mono group-hover:text-purple-600 transition-colors">
                        <?= $monthDaysCount ?> <span class="text-sm font-normal text-slate-400">days</span>
                    </div>
                    <span class="text-xs text-slate-400 font-medium">Logged in <?= date('M Y') ?></span>
                </div>
            </div>
            <div class="pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                <span>Records: <strong class="text-slate-700">Verified</strong></span>
                <span class="font-bold text-indigo-600 group-hover:text-indigo-800">Monthly Log &rarr;</span>
            </div>
        </a>
    </div>

    <!-- 2-Column Main Workspace: Tasks Queue (8 Cols) + Today's Sessions & Quick TL (4 Cols) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <!-- Left: Active Tasks & Work Queue (8 cols) -->
        <div class="lg:col-span-8 bg-white rounded-3xl border border-slate-200/90 shadow-sm p-6">
            <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-100">
                <div>
                    <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                            <i data-lucide="kanban" class="w-4 h-4"></i>
                        </div>
                        My Active Tasks & Work Queue
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Assigned sprint deliverables requiring progress updates or final submission to TL.</p>
                </div>
                <a href="?page=employee-tasks" class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1">
                    <span>Open Board</span> &rarr;
                </a>
            </div>

            <?php if (empty($myTasks)): ?>
                <div class="text-center py-12 text-slate-400 bg-slate-50/60 rounded-2xl border border-dashed border-slate-200">
                    <i data-lucide="check-circle-2" class="w-10 h-10 mx-auto text-emerald-400 mb-2"></i>
                    <p class="text-sm font-bold text-slate-700">All Tasks Completed!</p>
                    <p class="text-xs text-slate-400 mt-0.5">You have no pending deliverables in your work queue.</p>
                </div>
            <?php else: ?>
                <div class="space-y-3.5">
                    <?php foreach ($myTasks as $task): ?>
                        <div class="p-4 sm:p-5 rounded-2xl border border-slate-200/80 bg-slate-50/40 hover:bg-white hover:border-indigo-200 hover:shadow-sm transition flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                                    <span class="px-2.5 py-0.5 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-100 text-[11px] font-bold">
                                        <?= htmlspecialchars($task['project_title']) ?>
                                    </span>
                                    <?= getPriorityBadge($task['priority']) ?>
                                    <?= getStatusBadge($task['status']) ?>
                                </div>

                                <h3 class="text-sm font-bold text-slate-900 truncate"><?= htmlspecialchars($task['title']) ?></h3>
                                <?php if ($task['description']): ?>
                                    <p class="text-xs text-slate-500 mt-1 line-clamp-2"><?= htmlspecialchars($task['description']) ?></p>
                                <?php endif; ?>

                                <div class="text-[11px] text-slate-400 mt-2.5 flex items-center gap-4 flex-wrap font-medium">
                                    <span class="flex items-center gap-1 text-slate-600">
                                        <i data-lucide="user" class="w-3.5 h-3.5 text-slate-400"></i> TL: <strong><?= htmlspecialchars($task['tl_name']) ?></strong>
                                    </span>
                                    <span class="flex items-center gap-1 text-slate-600">
                                        <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400"></i> Due: <strong><?= formatDate($task['due_date']) ?></strong>
                                    </span>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex items-center gap-2 shrink-0">
                                <?php if ($task['status'] === 'todo'): ?>
                                    <form action="?action=update-task-status" method="POST">
                                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                        <input type="hidden" name="status" value="in_progress">
                                        <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-sm transition inline-flex items-center gap-1.5">
                                            <i data-lucide="play" class="w-3.5 h-3.5"></i> Start Work
                                        </button>
                                    </form>
                                <?php elseif ($task['status'] === 'in_progress'): ?>
                                    <button @click="activeTask = <?= htmlspecialchars(json_encode($task)) ?>; submitModalOpen = true" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition inline-flex items-center gap-1.5">
                                        <i data-lucide="send" class="w-3.5 h-3.5"></i> Submit Deliverable
                                    </button>
                                <?php elseif ($task['status'] === 'review'): ?>
                                    <span class="px-3.5 py-1.5 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 text-xs font-bold inline-flex items-center gap-1.5">
                                        <i data-lucide="clock" class="w-3.5 h-3.5"></i> Under TL Review
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right: Today's Punch Sessions & Office Guidelines (4 cols) -->
        <div class="lg:col-span-4 space-y-5">
            
            <!-- Today's Sessions Card -->
            <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-5">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3.5">
                    <span class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                        <i data-lucide="history" class="w-4 h-4 text-indigo-600"></i> Today's Punch Logs
                    </span>
                    <span class="text-[11px] font-bold text-slate-500 font-mono"><?= date('D, d M') ?></span>
                </div>

                <?php if (empty($empSessions)): ?>
                    <div class="text-center py-6 text-slate-400 bg-slate-50/50 rounded-xl text-xs font-medium">
                        No punch sessions recorded yet today.
                    </div>
                <?php else: ?>
                    <div class="space-y-2.5">
                        <?php foreach ($empSessions as $s): ?>
                            <div class="p-3 rounded-xl <?= $s['ended_by'] === 'force_logout' ? 'bg-rose-50/60 border border-rose-200' : 'bg-slate-50 border border-slate-200/70' ?> flex items-center justify-between text-xs">
                                <div>
                                    <div class="flex items-center gap-1.5 font-bold <?= $s['ended_by'] === 'force_logout' ? 'text-rose-800' : 'text-slate-800' ?>">
                                        <span class="px-1.5 py-0.2 rounded bg-indigo-50 text-indigo-700 text-[10px] font-mono">#<?= $s['session_number'] ?></span>
                                        <span class="font-mono"><?= formatTime($s['clock_in']) ?></span>
                                        <span class="text-slate-400">&rarr;</span>
                                        <span class="font-mono"><?= $s['clock_out'] ? formatTime($s['clock_out']) : '<span class="text-emerald-600 animate-pulse">Active</span>' ?></span>
                                    </div>
                                    <?php if ($s['ended_by'] === 'force_logout'): ?>
                                        <div class="text-[10px] text-rose-600 font-semibold mt-0.5">🚪 Forced Logout by TL</div>
                                    <?php endif; ?>
                                </div>
                                <span class="font-mono text-xs font-bold <?= $s['ended_by'] === 'force_logout' ? 'text-rose-700' : 'text-slate-700' ?>">
                                    <?= $s['hours'] > 0 ? $s['hours'] . 'h' : ($s['clock_out'] ? '< 1m' : 'In Progress') ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Office Geofence Info Card -->
            <div class="bg-gradient-to-br from-indigo-50/60 via-white to-slate-50 rounded-3xl border border-indigo-100/80 p-5 space-y-2.5">
                <div class="flex items-center gap-2 text-indigo-900 font-bold text-xs">
                    <i data-lucide="shield-check" class="w-4 h-4 text-indigo-600"></i>
                    <span>Office Geofence Policy</span>
                </div>
                <p class="text-[11px] text-slate-600 leading-relaxed">
                    You are assigned to <strong><?= $empOfficeLocation ? htmlspecialchars($empOfficeLocation['name']) : 'Office' ?></strong>. Web punch requires you to be within <strong>50 meters</strong> of the office coordinates.
                </p>
                <div class="pt-2 border-t border-indigo-100/60 flex items-center justify-between text-[10px] text-indigo-700 font-semibold">
                    <span>Radius: 50m</span>
                    <span>WFH Exempt</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Submit Work Modal (With Photo & Video Upload + Tech Cloud Drive Sync) -->
    <div x-show="submitModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" x-cloak>
        <div @click.away="submitModalOpen = false" class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200" x-data="{ fileName: '', fileType: '' }">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                        <i data-lucide="send" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Submit Work for TL Review</h3>
                        <p class="text-[11px] text-slate-500 truncate max-w-[280px]" x-text="activeTask ? activeTask.title : ''"></p>
                    </div>
                </div>
                <button @click="submitModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form action="?action=submit-work" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="task_id" :value="activeTask ? activeTask.id : ''">
                
                <!-- Proof Upload: Photos & Videos -->
                <div>
                    <?php
                    $tlId = (int)($user['reporting_tl_id'] ?: 30010);
                    $tlDriveRow = $db->query("SELECT is_connected FROM drive_settings WHERE team_lead_id = {$tlId}")->fetch();
                    $isDriveActive = !empty($tlDriveRow['is_connected']);
                    $assignedTlName = $db->query("SELECT name FROM users WHERE id = {$tlId}")->fetchColumn() ?: 'Team Lead';
                    ?>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Upload Deliverable Proof (Photo / Video / File)</label>
                    <?php if (!$isDriveActive): ?>
                        <div class="space-y-2">
                            <!-- Alert Notification Banner -->
                            <div class="p-3 bg-amber-50 border border-amber-200 rounded-2xl text-amber-900 text-xs flex items-start gap-2.5">
                                <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5"></i>
                                <div>
                                    <strong class="font-bold block">Team Cloud Drive is Inactive:</strong>
                                    <span class="text-amber-800 text-[11px]">Photo, video, and file uploads are disabled because your Team Lead (<strong><?= htmlspecialchars($assignedTlName) ?></strong>) has not connected Google Drive. You can submit your report as text only, or provide a link below.</span>
                                </div>
                            </div>
                            
                            <!-- Locked Upload Area with Interactive Click Alert -->
                            <div @click="alert('⚠️ Photo/Video/File uploads are disabled because Team Cloud Drive is not active. Please contact Team Lead (<?= htmlspecialchars(addslashes($assignedTlName)) ?>) to link Google Drive, or submit your report using the Work Description field below.')" class="border-2 border-dashed border-slate-200 bg-slate-100 rounded-2xl p-4 text-center cursor-pointer hover:bg-amber-50/50 hover:border-amber-300 transition">
                                <div class="flex items-center justify-center gap-2 text-slate-500 font-bold text-xs">
                                    <i data-lucide="lock" class="w-4 h-4 text-slate-400"></i>
                                    <span>Photo/Video Uploads Locked (Drive Inactive)</span>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-0.5">Click for alert • Only text report submissions allowed</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="relative border-2 border-dashed border-indigo-200 hover:border-indigo-400 bg-indigo-50/40 hover:bg-indigo-50/70 rounded-2xl p-4 text-center transition cursor-pointer">
                            <input 
                                type="file" 
                                name="attachment_file" 
                                accept="image/*,video/*,.pdf,.zip,.rar,.doc,.docx,.txt" 
                                @change="
                                    if ($event.target.files.length > 0) {
                                        fileName = $event.target.files[0].name;
                                        fileType = $event.target.files[0].type;
                                    } else {
                                        fileName = '';
                                        fileType = '';
                                    }
                                "
                                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                            >
                            <div class="space-y-1 py-1 pointer-events-none">
                                <div class="flex items-center justify-center gap-2 text-indigo-600 font-bold text-xs">
                                    <i data-lucide="upload-cloud" class="w-5 h-5"></i>
                                    <span x-text="fileName ? 'Selected: ' + fileName : 'Choose Screenshot, Video Demo, or Archive'"></span>
                                </div>
                                <p class="text-[10px] text-slate-400" x-show="!fileName">Supports PNG, JPG, MP4/WEBM Video (up to 50MB), PDF, ZIP</p>
                                <div x-show="fileName" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded bg-indigo-100 text-indigo-800 text-[10px] font-bold">
                                    <span x-text="fileType.includes('video') ? '🎥 Video File' : (fileType.includes('image') ? '🖼️ Photo/Image' : '📎 Document')"></span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Attachment Link / URL (Optional) -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Deliverable Link (GitHub PR / Figma / Drive Link - Optional)</label>
                    <input type="url" name="attachment_url" placeholder="https://github.com/... or https://figma.com/..." class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500 transition">
                </div>

                <!-- Deliverable Notes / Summary -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Work Description & Implementation Notes *</label>
                    <textarea name="notes" rows="3" required placeholder="Describe what you completed, PR links, deployment links, etc..." class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 focus:bg-white focus:ring-2 focus:ring-emerald-500 transition"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="submitModalOpen = false" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-sm inline-flex items-center gap-1.5">
                        <i data-lucide="send" class="w-4 h-4"></i> Submit to TL
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function updateClock() {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('en-US', { hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit' });
        const el = document.getElementById('liveClock');
        if (el) el.innerText = timeStr;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // Silent Background GPS Travel Streamer for Field Staff
    <?php if (!empty($isCurrentlyIn) && (($userProfile['work_mode'] ?? '') === 'field')): ?>
    (function initSilentTracker() {
        if ('geolocation' in navigator) {
            function streamPosition(pos) {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                const speed = pos.coords.speed || 0;
                fetch('?action=log-travel-coordinate', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `lat=${lat}&lng=${lng}&speed=${speed}`
                }).catch(() => {});
            }
            // Watch position on movements
            navigator.geolocation.watchPosition(streamPosition, () => {}, {
                enableHighAccuracy: true,
                maximumAge: 30000,
                timeout: 27000
            });
            // Backup periodic ping every 3 minutes
            setInterval(() => {
                navigator.geolocation.getCurrentPosition(streamPosition, () => {}, {enableHighAccuracy: true});
            }, 180000);
        }
    })();
    <?php endif; ?>
</script>
