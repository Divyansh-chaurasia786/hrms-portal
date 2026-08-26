<!-- views/layouts/sidebar.php -->
<?php
$user = authUser();
$role = $user['role'] ?? 'employee';
$page = $_GET['page'] ?? 'dashboard';
?>
<!-- Sidebar Backdrop for Mobile -->
<div x-show="sidebarOpen" 
     x-transition:enter="transition-opacity ease-linear duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity ease-linear duration-300"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @click="sidebarOpen = false" 
     class="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-xs lg:hidden" 
     x-cloak></div>

<!-- Sidebar -->
<aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'" class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 text-slate-300 flex flex-col transition-transform duration-300 ease-in-out no-scrollbar shadow-2xl lg:shadow-none">
    <!-- Brand Logo & Mobile Close -->
    <div class="h-16 flex items-center justify-between px-5 bg-slate-950/40 border-b border-slate-800 shrink-0">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white shadow-lg shadow-indigo-500/20 font-bold text-lg">
                H
            </div>
            <div>
                <h1 class="text-sm font-bold text-white tracking-wide">ENTERPRISE HRMS</h1>
                <p class="text-[11px] text-indigo-400 font-medium tracking-wider uppercase"><?= strtoupper(str_replace('_', ' ', $role)) ?> PORTAL</p>
            </div>
        </div>
        <button @click="sidebarOpen = false" class="lg:hidden p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition cursor-pointer" title="Close Menu">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>
    </div>

    <!-- Clickable User Profile Badge in Sidebar -->
    <a href="?page=profile" class="p-3 mx-3 my-2.5 bg-slate-800/60 hover:bg-slate-800 rounded-xl border border-slate-700/50 flex items-center gap-3 transition group shrink-0">
        <img src="<?= $user['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($user['name']) ?>" class="w-9 h-9 rounded-full object-cover ring-2 ring-indigo-500/40 group-hover:ring-indigo-400" alt="Avatar">
        <div class="overflow-hidden flex-1">
            <div class="flex items-center justify-between">
                <h2 class="text-xs font-semibold text-white truncate"><?= htmlspecialchars($user['name']) ?></h2>
                <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-slate-500 group-hover:text-indigo-400"></i>
            </div>
            <p class="text-[11px] text-slate-400 truncate"><?= htmlspecialchars($user['designation']) ?></p>
        </div>
    </a>

    <!-- Navigation Menu -->
    <nav class="flex-1 px-3 space-y-1 overflow-y-auto no-scrollbar text-sm font-medium">
        <?php if ($role === 'team_lead'): ?>
            <!-- Team Lead Links -->
            <div class="text-[10px] font-semibold tracking-wider text-slate-500 uppercase px-3 pt-3 pb-1">Team Operations</div>
            <a href="?page=tl-dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'tl-dashboard' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <i data-lucide="layout-dashboard" class="w-4 h-4"></i> TL Dashboard
            </a>
            <a href="?page=tl-attendance" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'tl-attendance' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <i data-lucide="clock" class="w-4 h-4"></i> Team Attendance
            </a>
            <a href="?page=tl-tasks" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'tl-tasks' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <i data-lucide="check-square" class="w-4 h-4"></i> Task Assignment & Review
            </a>
            <a href="?page=tl-reports" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'tl-reports' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <i data-lucide="file-text" class="w-4 h-4 text-purple-400"></i> Daily Work Reports & HR
            </a>
            <a href="?page=tl-leaves" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'tl-leaves' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <i data-lucide="calendar-check" class="w-4 h-4"></i> Team Leave Reviews
            </a>
            <a href="?page=tech-drive" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'tech-drive' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <i data-lucide="hard-drive" class="w-4 h-4 text-indigo-400"></i> Tech Cloud Drive
            </a>

        <?php elseif ($role === 'employee'): ?>
            <!-- Employee Links -->
            <div class="text-[10px] font-semibold tracking-wider text-slate-500 uppercase px-3 pt-3 pb-1">My Workspace</div>
            <a href="?page=employee-dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'employee-dashboard' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <i data-lucide="layout-dashboard" class="w-4 h-4"></i> My Dashboard
            </a>
            <a href="?page=employee-tasks" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'employee-tasks' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <i data-lucide="kanban" class="w-4 h-4"></i> My Tasks & Submission
            </a>
            <a href="?page=employee-attendance" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'employee-attendance' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <i data-lucide="clock" class="w-4 h-4"></i> Attendance History
            </a>
                        <?php if (($user['department_name'] ?? '') === 'Calling / Sales'): ?>
                <a href="?page=calling-queue" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'calling-queue' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                    <i data-lucide="phone-call" class="w-4 h-4 text-emerald-400"></i> My Calling Queue
                </a>
            <?php endif; ?>
            <a href="?page=employee-wfh" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'employee-wfh' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <i data-lucide="home" class="w-4 h-4"></i> Apply for WFH
            </a>
            <a href="?page=employee-leaves" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'employee-leaves' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <i data-lucide="calendar-heart" class="w-4 h-4"></i> Apply Leave
            </a>
            <a href="?page=tech-drive" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'tech-drive' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <i data-lucide="hard-drive" class="w-4 h-4 text-indigo-400"></i> Tech Cloud Drive
            </a>

        <?php elseif ($role === 'admin'): ?>
                        <!-- Admin / HR Links -->
            <div class="text-[10px] font-semibold tracking-wider text-slate-500 uppercase px-3 pt-3 pb-1">HR Administration</div>
            <a href="?page=admin-dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'admin-dashboard' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <i data-lucide="layout-dashboard" class="w-4 h-4"></i> HR Overview
            </a>
            <a href="?page=admin-employees" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'admin-employees' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <i data-lucide="users" class="w-4 h-4"></i> Employee & Intern Directory
            </a>
            <a href="?page=admin-attendance" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'admin-attendance' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <i data-lucide="clock" class="w-4 h-4"></i> Attendance Audit
            </a>
            <a href="?page=admin-travel-radar" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'admin-travel-radar' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <i data-lucide="map" class="w-4 h-4 text-amber-400"></i> Field Travel Radar
            </a>
            <a href="?page=admin-wfh" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'admin-wfh' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <i data-lucide="home" class="w-4 h-4 text-blue-400"></i> WFH Approvals & Policy
            </a>
            <a href="?page=admin-leaves" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'admin-leaves' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <i data-lucide="calendar-check" class="w-4 h-4"></i> Leave Approvals
            </a>
            <a href="?page=admin-smart-sheets" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'admin-smart-sheets' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <i data-lucide="file-spreadsheet" class="w-4 h-4 text-emerald-400"></i> Smart Sheet & AI Hub
            </a>
            <a href="?page=calling-manage" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'calling-manage' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <i data-lucide="phone-forwarded" class="w-4 h-4 text-emerald-400"></i> Telecalling Lead CRM
            </a>
            <?php
            $pendingEscCount = 0;
            $dbNav = getDBConnection();
            $pendingEscCount = (int)$dbNav->query("SELECT COUNT(*) FROM employee_escalations WHERE status = 'pending'")->fetchColumn();
            ?>
            <a href="?page=admin-tl-reports" class="flex items-center justify-between px-3 py-2.5 rounded-lg transition <?= $page === 'admin-tl-reports' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <div class="flex items-center gap-3">
                    <i data-lucide="shield-alert" class="w-4 h-4 text-rose-400"></i> TL Progress & Referrals
                </div>
                <?php if ($pendingEscCount > 0): ?>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-rose-500 text-white animate-pulse" title="<?= $pendingEscCount ?> Pending TL Referral(s)"><?= $pendingEscCount ?></span>
                <?php endif; ?>
            </a>
            <a href="?page=tech-drive" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'tech-drive' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <i data-lucide="hard-drive" class="w-4 h-4 text-indigo-400"></i> Tech Cloud Drive
            </a>
        <?php endif; ?>

        <!-- Dedicated My Profile & Settings Link for all roles -->
        <div class="text-[10px] font-semibold tracking-wider text-slate-500 uppercase px-3 pt-4 pb-1">Account & Settings</div>
        <a href="?page=profile" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'profile' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
            <i data-lucide="user" class="w-4 h-4"></i> My Profile & Security
        </a>
    </nav>

    <!-- Logout / Footer of Sidebar -->
    <div class="p-3 border-t border-slate-800/80">
        <a href="?action=logout" class="flex items-center gap-3 px-3 py-2 rounded-lg text-rose-400 hover:bg-rose-950/40 hover:text-rose-300 transition text-sm">
            <i data-lucide="log-out" class="w-4 h-4"></i> Sign Out
        </a>
    </div>
</aside>

<!-- Main Wrapper -->
<div class="flex-1 flex flex-col min-w-0 lg:pl-64">
    <!-- Top Navbar -->
    <header id="top-header-navbar" class="h-16 bg-white border-b border-slate-200 px-4 sm:px-6 flex items-center justify-between sticky top-0 z-30 shadow-sm">
        <div class="flex items-center gap-3">
            <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 rounded-lg text-slate-500 hover:bg-slate-100">
                <i data-lucide="menu" class="w-5 h-5"></i>
            </button>
            <div class="hidden sm:block">
                <span class="text-xs font-medium text-slate-500">Today: </span>
                <span class="text-xs font-semibold text-slate-700"><?= date('l, d F Y') ?></span>
            </div>
        </div>

        <!-- Quick Punch Status & User Info -->
        <div class="flex items-center gap-4">
            <?php
            $todayAtt = AttendanceController::getTodayAttendanceForUser($user['id']);
            $isCurrentlyIn = ($todayAtt && $todayAtt['clock_out'] === null);
            ?>
            <?php if ($isCurrentlyIn): ?>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-700 bg-emerald-50 px-3 py-1 rounded-xl border border-emerald-200 shadow-sm">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> In: <?= formatTime($todayAtt['clock_in']) ?>
                    </span>
                    <form action="?action=clock-out" method="POST" class="inline">
                        <button type="submit" class="inline-flex items-center gap-1 px-3 py-1 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-sm transition">
                            <i data-lucide="stop-circle" class="w-3.5 h-3.5"></i> Punch Out
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <?php
                // Fetch effective office location for this user (inherits TL permanent/temporary location)
                $headerOfficeLocation = GEOFENCE_ENABLED ? getEffectiveUserLocation((int)$user['id']) : null;
                ?>
                <div x-data="{
                    locState: 'idle',
                    lat: 0, lng: 0,
                    officeLat: <?= $headerOfficeLocation ? $headerOfficeLocation['lat'] : 0 ?>,
                    officeLng: <?= $headerOfficeLocation ? $headerOfficeLocation['lng'] : 0 ?>,
                    officeName: '<?= $headerOfficeLocation ? addslashes($headerOfficeLocation['name']) : '' ?>',
                    errMsg: '',
                    doPunch() {
                        if (!this.officeLat) { this.locState='error'; this.errMsg='No office location assigned. Contact HR.'; return; }
                        this.locState = 'checking';
                        navigator.geolocation.getCurrentPosition(
                            (p) => {
                                this.lat = p.coords.latitude; this.lng = p.coords.longitude;
                                const R=6371000, dLat=(this.officeLat-this.lat)*Math.PI/180, dLon=(this.officeLng-this.lng)*Math.PI/180;
                                const a=Math.sin(dLat/2)**2+Math.cos(this.lat*Math.PI/180)*Math.cos(this.officeLat*Math.PI/180)*Math.sin(dLon/2)**2;
                                const dist=R*2*Math.atan2(Math.sqrt(a),Math.sqrt(1-a));
                                if (dist <= <?= GEOFENCE_RADIUS_METERS ?>) {
                                    this.locState='ok'; this.$nextTick(()=>this.$refs.hdrForm.submit());
                                } else {
                                    this.locState='error'; this.errMsg='📍 Out of Location! '+(dist/1000).toFixed(1)+'km away from '+this.officeName+'. Go to office to punch in.';
                                }
                            },
                            (e) => { this.locState='error'; this.errMsg = e.code===1?'📍 Location permission denied! Allow location in browser settings.':'📍 Location unavailable. Try again.'; },
                            {enableHighAccuracy:true, timeout:10000, maximumAge:0}
                        );
                    }
                }" class="relative inline-block">
                    <form x-ref="hdrForm" action="?action=clock-in" method="POST" class="inline" @submit.prevent="doPunch()">
                        <input type="hidden" name="status" value="present">
                        <input type="hidden" name="latitude" :value="lat">
                        <input type="hidden" name="longitude" :value="lng">
                        <button type="submit" :disabled="locState==='checking'" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-500 text-white text-xs font-bold shadow-md shadow-emerald-600/20 transition">
                            <template x-if="locState==='checking'"><svg class="animate-spin w-4 h-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></template>
                            <template x-if="locState!=='checking'"><i data-lucide="play-circle" class="w-4 h-4"></i></template>
                            <span x-text="locState==='checking'?'Verifying...':'Punch In (Office Login)'"></span>
                        </button>
                    </form>
                    <!-- Error tooltip -->
                    <div x-show="locState==='error'" x-cloak class="absolute right-0 top-full mt-2 z-50 w-72 p-3 rounded-xl bg-rose-50 border border-rose-300 shadow-xl text-xs text-rose-800 font-semibold">
                        <span x-text="errMsg"></span>
                        <button @click="locState='idle'" class="ml-2 text-rose-500 hover:text-rose-700 font-bold">✕</button>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </header>

    <!-- Main Content Area -->
    <main id="main-content" class="flex-1 p-3.5 sm:p-6 lg:p-8 min-w-0 max-w-full bg-[#f8fafc] transition-all duration-200 overflow-x-hidden">
        <?php $flash = getFlash(); if ($flash): ?>
            <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show" x-transition:leave="transition ease-in duration-500" x-transition:leave-start="opacity-100 transform translate-y-0" x-transition:leave-end="opacity-0 transform -translate-y-2" class="mb-6 p-4 rounded-2xl text-sm font-medium flex items-center justify-between gap-3 shadow-sm <?= $flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : ($flash['type'] === 'warning' ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-rose-50 text-rose-800 border border-rose-200') ?>">
                <div class="flex items-center gap-3">
                    <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle-2' : ($flash['type'] === 'warning' ? 'alert-triangle' : 'alert-circle') ?>" class="w-5 h-5 shrink-0"></i>
                    <span><?= htmlspecialchars($flash['message']) ?></span>
                </div>
                <button @click="show = false" class="text-slate-400 hover:text-slate-600 transition p-1" title="Close">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
        <?php endif; ?>

        <?php if (!isInActiveShift()): ?>
            <div class="mb-5 p-3.5 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-900 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs shadow-sm">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-700 flex items-center justify-center font-bold shrink-0">
                        <i data-lucide="lock" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <span class="font-bold uppercase tracking-wide text-amber-800 text-[11px]">Workspace in Read-Only Mode</span>
                        <?php if ($currRole === 'admin'): ?>
                            <p class="text-[11px] text-amber-700 mt-0.5">HR Administration Hub is in Read-Only Mode. (<strong>Leave Approvals</strong> and <strong>Tech Cloud Drive</strong> remain <strong>accessible 24/7</strong>). Please <strong>Punch In (Office Login)</strong> to edit employee profiles or manage configurations.</p>
                        <?php else: ?>
                            <p class="text-[11px] text-amber-700 mt-0.5">You have not punched in yet today. (<strong>Apply for Leave</strong> and <strong>Tech Cloud Drive</strong> remain <strong>open 24/7 from home</strong>). Please <strong>Punch In (Office Login)</strong> when at the office to submit tasks or review files.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="shrink-0 flex items-center gap-2">
                    <?php if ($currRole === 'admin'): ?>
                        <a href="?page=admin-leaves" class="px-3 py-1.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-[11px] inline-flex items-center gap-1 shadow-xs transition">
                            <i data-lucide="calendar-check" class="w-3.5 h-3.5"></i> Review Leave Queue
                        </a>
                    <?php else: ?>
                                    <?php if (($user['department_name'] ?? '') === 'Calling / Sales'): ?>
                <a href="?page=calling-queue" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'calling-queue' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                    <i data-lucide="phone-call" class="w-4 h-4 text-emerald-400"></i> My Calling Queue
                </a>
            <?php endif; ?>
            <a href="?page=employee-wfh" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'employee-wfh' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <i data-lucide="home" class="w-4 h-4"></i> Apply for WFH
            </a>
            <a href="?page=employee-leaves" class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-[11px] inline-flex items-center gap-1 shadow-xs transition">
                            <i data-lucide="calendar-plus" class="w-3.5 h-3.5"></i> Apply for Leave
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
