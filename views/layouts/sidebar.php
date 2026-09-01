<!-- views/layouts/sidebar.php -->
<?php
$user = authUser();
$role = $user['role'] ?? 'employee';
$currRole = $role;
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
<aside :class="[ sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0', sidebarCollapsed ? 'lg:-translate-x-full lg:opacity-0 lg:pointer-events-none' : 'lg:translate-x-0 lg:opacity-100 lg:w-64' ]" class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 text-slate-300 flex flex-col transition-all duration-300 ease-in-out no-scrollbar shadow-2xl lg:shadow-none">
    <!-- Brand Logo & Mobile Close -->
    <div class="h-20 flex items-center justify-between px-4 bg-slate-950/60 border-b border-slate-800 shrink-0">
        <a href="?page=dashboard" class="flex items-center gap-3 group py-1">
            <div class="w-11 h-11 rounded-2xl flex items-center justify-center group-hover:scale-105 transition-transform shrink-0">
                <img src="/logo_icon.png?v=3" alt="EcoFone App" class="w-full h-full object-contain drop-shadow-md">
            </div>
            <div>
                <h1 class="text-base font-black text-white tracking-tight flex items-center gap-1 leading-none">
                    <span>EcoFone</span> <span class="text-orange-400 font-black">App</span>
                </h1>
                <p class="text-[10px] text-emerald-400 font-bold tracking-wider uppercase mt-1"><?= strtoupper(str_replace('_', ' ', $role)) ?> PORTAL</p>
            </div>
        </a>
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
            <div class="text-[10px] font-semibold tracking-wider text-slate-500 uppercase px-3 pt-3 pb-1">
                <?= (stripos($user['department_name'] ?? '', 'BDA') !== false || stripos($user['department_name'] ?? '', 'Calling') !== false || stripos($user['designation'] ?? '', 'BDA') !== false || stripos($user['designation'] ?? '', 'Calling') !== false) ? 'BDA Operations & CRM' : 'Team Operations' ?>
            </div>
            
            <a href="?page=tl-dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'tl-dashboard' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <i data-lucide="layout-dashboard" class="w-4 h-4"></i> <?= (stripos($user['department_name'] ?? '', 'BDA') !== false || stripos($user['department_name'] ?? '', 'Calling') !== false || stripos($user['designation'] ?? '', 'BDA') !== false || stripos($user['designation'] ?? '', 'Calling') !== false) ? 'BDA Hub Dashboard' : 'TL Dashboard' ?>
            </a>

            <?php if (stripos($user['department_name'] ?? '', 'BDA') !== false || stripos($user['department_name'] ?? '', 'Calling') !== false || stripos($user['designation'] ?? '', 'BDA') !== false || stripos($user['designation'] ?? '', 'Calling') !== false): ?>
                <a href="?page=calling-manage" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'calling-manage' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                    <i data-lucide="phone-forwarded" class="w-4 h-4 text-emerald-400"></i> BDA Lead CRM & Allocation
                </a>
            <?php endif; ?>

            <a href="?page=tl-attendance" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'tl-attendance' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <i data-lucide="clock" class="w-4 h-4"></i> Team Attendance
            </a>

            <?php if (($user['department_name'] ?? '') !== 'Calling / BDA Team'): ?>
                <a href="?page=tl-tasks" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'tl-tasks' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                    <i data-lucide="check-square" class="w-4 h-4"></i> Task Assignment & Review
                </a>
            <?php endif; ?>

            <a href="?page=tl-reports" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'tl-reports' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <i data-lucide="file-text" class="w-4 h-4 text-purple-400"></i> <?= (stripos($user['department_name'] ?? '', 'BDA') !== false || stripos($user['department_name'] ?? '', 'Calling') !== false || stripos($user['designation'] ?? '', 'BDA') !== false || stripos($user['designation'] ?? '', 'Calling') !== false) ? 'Calling Reports & HR' : 'Daily Work Reports & HR' ?>
            </a>
            
            <a href="?page=tl-leaves" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'tl-leaves' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <i data-lucide="calendar-check" class="w-4 h-4"></i> Team Leave Reviews
            </a>

            <?php if (($user['department_name'] ?? '') === 'Tech / Development'): ?>
                <a href="?page=tech-drive" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'tech-drive' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                    <i data-lucide="hard-drive" class="w-4 h-4 text-indigo-400"></i> Tech Cloud Drive
                </a>
            <?php endif; ?>

        <?php elseif ($role === 'employee'): ?>
            <?php 
                $isFieldStaff = (($user['work_mode'] ?? '') === 'field' || stripos($user['department_name'] ?? '', 'Field') !== false || stripos($user['designation'] ?? '', 'Field') !== false);
                $isBDAStaff = (stripos($user['department_name'] ?? '', 'BDA') !== false || stripos($user['department_name'] ?? '', 'Calling') !== false || stripos($user['designation'] ?? '', 'BDA') !== false || stripos($user['designation'] ?? '', 'Calling') !== false);
                $isTechStaff = (stripos($user['department_name'] ?? '', 'Tech') !== false || stripos($user['designation'] ?? '', 'Developer') !== false || stripos($user['designation'] ?? '', 'Engineer') !== false);
            ?>
            <!-- Employee Links -->
            <div class="text-[10px] font-semibold tracking-wider text-slate-500 uppercase px-3 pt-3 pb-1">
                <?= $isFieldStaff ? '🚗 Field Workspace' : 'My Workspace' ?>
            </div>
            <a href="?page=employee-dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'employee-dashboard' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <i data-lucide="layout-dashboard" class="w-4 h-4"></i> My Dashboard
            </a>

            <?php if ($isFieldStaff): ?>
                <a href="?page=employee-tasks" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'employee-tasks' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                    <i data-lucide="clipboard-list" class="w-4 h-4 text-amber-400"></i> Field Tasks & Visits
                </a>
                <a href="?page=tl-travel-radar" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'tl-travel-radar' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                    <i data-lucide="map-pin" class="w-4 h-4 text-emerald-400"></i> Field Travel Radar
                </a>
                <a href="?page=employee-attendance" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'employee-attendance' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                    <i data-lucide="clock" class="w-4 h-4"></i> GPS Attendance Logs
                </a>
                <a href="?page=employee-leaves" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'employee-leaves' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                    <i data-lucide="calendar-heart" class="w-4 h-4"></i> Apply Leave
                </a>
            <?php else: ?>
                <a href="?page=employee-tasks" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'employee-tasks' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                    <i data-lucide="kanban" class="w-4 h-4"></i> My Tasks & Submission
                </a>
                <a href="?page=employee-attendance" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'employee-attendance' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                    <i data-lucide="clock" class="w-4 h-4"></i> Attendance History
                </a>
                <?php if ($isBDAStaff): ?>
                    <a href="?page=calling-queue" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'calling-queue' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                        <i data-lucide="phone-call" class="w-4 h-4 text-emerald-400"></i> My BDA Queue
                    </a>
                <?php endif; ?>
                <a href="?page=employee-wfh" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'employee-wfh' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                    <i data-lucide="home" class="w-4 h-4"></i> Apply for WFH
                </a>
                <a href="?page=employee-leaves" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'employee-leaves' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                    <i data-lucide="calendar-heart" class="w-4 h-4"></i> Apply Leave
                </a>
                <?php if ($isTechStaff): ?>
                    <a href="?page=tech-drive" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'tech-drive' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                        <i data-lucide="hard-drive" class="w-4 h-4 text-indigo-400"></i> Tech Cloud Drive
                    </a>
                <?php endif; ?>
            <?php endif; ?>

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
                <i data-lucide="phone-forwarded" class="w-4 h-4 text-emerald-400"></i> BDA Lead CRM
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
            <?php if (($user['department_name'] ?? '') === 'Tech / Development'): ?>
            <a href="?page=tech-drive" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'tech-drive' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
                <i data-lucide="hard-drive" class="w-4 h-4 text-indigo-400"></i> Tech Cloud Drive
            </a>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Dedicated My Profile & Settings Link for all roles -->
        <div class="text-[10px] font-semibold tracking-wider text-slate-500 uppercase px-3 pt-4 pb-1">Account & Settings</div>
        <a href="?page=profile" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition <?= $page === 'profile' ? 'bg-indigo-600 text-white shadow-sm font-semibold' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200' ?>">
            <i data-lucide="user" class="w-4 h-4"></i> My Profile & Security
        </a>

        <!-- 🔄 1-Click Update Notification (ONLY VISIBLE WHEN NEW UPDATE IS AVAILABLE) -->
        <div id="appUpdateContainer" class="hidden pt-2 pb-1">
            <button type="button" onclick="window.applyAppUpdate && window.applyAppUpdate()" class="w-full flex items-center justify-between px-3 py-2 rounded-xl bg-gradient-to-r from-emerald-600/90 to-teal-600/90 hover:from-emerald-600 hover:to-teal-600 border border-emerald-400/30 text-white transition text-xs font-bold shadow-md shadow-emerald-950/30 group cursor-pointer animate-pulse" title="New update is available!">
                <span class="flex items-center gap-2">
                    <i data-lucide="sparkles" class="w-3.5 h-3.5 text-amber-300"></i>
                    <span>Update Available</span>
                </span>
                <span id="updateAvailableVersionBadge" class="text-[10px] px-1.5 py-0.5 rounded bg-black/30 text-emerald-200 font-mono font-bold">New</span>
            </button>
        </div>
    </nav>

    <!-- Logout / Footer of Sidebar -->
    <div class="p-3 border-t border-slate-800/80">
        <a href="?action=logout" class="flex items-center gap-3 px-3 py-2 rounded-lg text-rose-400 hover:bg-rose-950/40 hover:text-rose-300 transition text-sm">
            <i data-lucide="log-out" class="w-4 h-4"></i> Sign Out
        </a>
    </div>
</aside>

<!-- Main Wrapper -->
<div class="min-h-screen flex flex-col min-w-0 w-full transition-all duration-300 ease-in-out" :class="sidebarCollapsed ? 'lg:pl-0' : 'lg:pl-64'">
    <!-- Top Navbar -->
    <header id="top-header-navbar" class="h-16 bg-white border-b border-slate-200 px-4 sm:px-6 flex items-center justify-between sticky top-0 z-30 shadow-sm">
        <div class="flex items-center gap-3">
            <!-- Mobile Menu Toggle -->
            <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 rounded-xl text-slate-500 hover:bg-slate-100 cursor-pointer">
                <i data-lucide="menu" class="w-5 h-5"></i>
            </button>

            <div class="hidden sm:block pl-2">
                <span class="text-xs font-medium text-slate-500">Today: </span>
                <span class="text-xs font-semibold text-slate-700"><?= date('l, d F Y') ?></span>
            </div>
        </div>

        <!-- Quick Punch Status & User Info -->
        <div class="flex items-center gap-2 sm:gap-3 shrink-0">
            <!-- 📲 Direct 1-Click Install Application Button (Hidden inside Native App / Standalone mode) -->
            <button type="button" id="installAppNavbarBtn" onclick="triggerPwaInstall()" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white text-xs font-extrabold transition shadow-md shadow-indigo-500/20 cursor-pointer group hover:scale-[1.02]" title="Install EcoFone Native App">
                <i data-lucide="download" class="w-4 h-4 text-white group-hover:translate-y-0.5 transition-transform"></i>
                <span>Install EcoFone App</span>
            </button>
            <script>
                (function() {
                    if (window.Capacitor || window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) {
                        const b = document.getElementById('installAppNavbarBtn');
                        if (b) b.remove();
                    }
                })();
            </script>
            <?php
            $todayAtt = AttendanceController::getTodayAttendanceForUser($user['id']);
            $isCurrentlyIn = ($todayAtt && $todayAtt['clock_out'] === null);
            $headerOfficeLocation = GEOFENCE_ENABLED ? getEffectiveUserLocation((int)$user['id']) : null;
            ?>
            <?php if ($isCurrentlyIn): ?>
                <!-- PUNCHED IN: SHOW STATUS & PUNCH OUT BUTTON -->
                <div class="flex items-center gap-1.5 sm:gap-2">
                    <span class="hidden md:inline-flex items-center gap-1.5 text-xs font-bold text-emerald-700 bg-emerald-50 px-2.5 py-1.5 rounded-xl border border-emerald-200 shadow-2xs">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> In: <?= formatTime($todayAtt['clock_in']) ?>
                    </span>
                    <div x-data="{
                        outState: 'idle',
                        outLat: 0,
                        outLng: 0,
                        doPunchOut() {
                            this.outState = 'saving';
                            let submitted = false;
                            const submitWithCoords = (lat, lng) => {
                                if (submitted) return;
                                submitted = true;
                                const form = this.$refs.punchOutForm;
                                if (form) {
                                    const latInp = form.querySelector('input[name=latitude]');
                                    const lngInp = form.querySelector('input[name=longitude]');
                                    if (latInp && lat) latInp.value = lat;
                                    if (lngInp && lng) lngInp.value = lng;
                                    form.submit();
                                }
                            };

                            // Hard 800ms safety timeout: If browser location hangs or is blocked, submit immediately!
                            setTimeout(() => { submitWithCoords(null, null); }, 800);

                            if (navigator.geolocation) {
                                navigator.geolocation.getCurrentPosition(
                                    (p) => {
                                        submitWithCoords(p.coords.latitude, p.coords.longitude);
                                    },
                                    (e) => {
                                        submitWithCoords(null, null);
                                    },
                                    { timeout: 700, enableHighAccuracy: true, maximumAge: 5000 }
                                );
                            } else {
                                submitWithCoords(null, null);
                            }
                        }
                    }">
                        <form x-ref="punchOutForm" action="?action=punch-out" method="POST" class="m-0">
                            <input type="hidden" name="latitude" value="">
                            <input type="hidden" name="longitude" value="">
                            <button type="button" @click="doPunchOut()" :disabled="outState === 'saving'" class="px-2.5 sm:px-3.5 py-1.5 text-xs font-bold bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-500 hover:to-red-500 text-white rounded-xl shadow-xs transition flex items-center gap-1 cursor-pointer">
                                <i data-lucide="log-out" class="w-3.5 h-3.5" x-show="outState === 'idle'"></i>
                                <i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin" x-show="outState === 'saving'" style="display: none;"></i>
                                <span class="text-[11px] sm:text-xs" x-text="outState === 'saving' ? 'Exiting...' : 'Punch Out'"></span>
                            </button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- PUNCHED OUT: SHOW PUNCH IN BUTTON -->
                <div x-data="{
                    locState: 'idle',
                    lat: 0, lng: 0,
                    isAdmin: <?= (($user['role'] ?? '') === 'admin') ? 'true' : 'false' ?>,
                    isExempt: <?= ((($user['role'] ?? '') === 'admin') || (($user['work_mode'] ?? '') === 'field') || (($user['work_mode'] ?? '') === 'wfh')) ? 'true' : 'false' ?>,
                    officeLat: <?= $headerOfficeLocation ? $headerOfficeLocation['lat'] : 0 ?>,
                    officeLng: <?= $headerOfficeLocation ? $headerOfficeLocation['lng'] : 0 ?>,
                    officeName: '<?= $headerOfficeLocation ? addslashes($headerOfficeLocation['name']) : '' ?>',
                    errMsg: '',
                    doPunch() {
                        this.locState = 'checking';
                        if (navigator.geolocation) {
                            navigator.geolocation.getCurrentPosition(
                                (p) => {
                                    this.lat = p.coords.latitude; 
                                    this.lng = p.coords.longitude;
                                    try { localStorage.setItem('eco_gps_granted', '1'); } catch(e) {}
                                    if (window.ecoStartGpsTracking) { window.ecoStartGpsTracking(); }
                                    if (this.isExempt) {
                                        this.locState = 'ok';
                                        this.$nextTick(() => this.$refs.hdrForm.submit());
                                        return;
                                    }
                                    if (!this.officeLat) { this.locState='error'; this.errMsg='No office location assigned. Contact HR.'; return; }
                                    const R=6371000, dLat=(this.officeLat-this.lat)*Math.PI/180, dLon=(this.officeLng-this.lng)*Math.PI/180;
                                    const a=Math.sin(dLat/2)**2+Math.cos(this.lat*Math.PI/180)*Math.cos(this.officeLat*Math.PI/180)*Math.sin(dLon/2)**2;
                                    const dist=R*2*Math.atan2(Math.sqrt(a),Math.sqrt(1-a));
                                    if (dist <= <?= GEOFENCE_RADIUS_METERS ?>) {
                                        this.locState='ok'; 
                                        this.$nextTick(() => this.$refs.hdrForm.submit());
                                    } else {
                                        this.locState='error'; 
                                        this.errMsg='📍 Out of Location! '+(dist/1000).toFixed(1)+'km away from '+this.officeName+'. Go to office to punch in.';
                                    }
                                },
                                (e) => { 
                                    if (this.isExempt) {
                                        this.locState = 'ok';
                                        this.$nextTick(() => this.$refs.hdrForm.submit());
                                        return;
                                    }
                                    this.locState='error'; 
                                    this.errMsg = e.code===1?'📍 Location permission denied! Allow location in browser settings.':'📍 Location unavailable. Try again.'; 
                                },
                                { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
                            );
                        } else {
                            if (this.isExempt) {
                                this.locState = 'ok';
                                this.$nextTick(() => this.$refs.hdrForm.submit());
                            } else {
                                this.locState = 'error';
                                this.errMsg = 'Geolocation is not supported by your browser.';
                            }
                        }
                    }
                }" class="relative inline-block">
                    <form x-ref="hdrForm" action="?action=clock-in" method="POST" class="inline" @submit.prevent="doPunch()">
                        <input type="hidden" name="status" value="present">
                        <input type="hidden" name="latitude" :value="lat">
                        <input type="hidden" name="longitude" :value="lng">
                        <button type="submit" :disabled="locState==='checking'" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-500 text-white text-xs font-bold shadow-md shadow-emerald-600/20 transition cursor-pointer">
                            <template x-if="locState==='checking'"><svg class="animate-spin w-4 h-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></template>
                            <template x-if="locState!=='checking'"><i data-lucide="play-circle" class="w-4 h-4"></i></template>
                            <?php 
    $isField = (($user['work_mode'] ?? '') === 'field' || stripos($user['department_name'] ?? '', 'Field') !== false || stripos($user['designation'] ?? '', 'Field') !== false);
    $punchInText = $isField ? '🚗 Punch In (Field Duty)' : ((($user['work_mode'] ?? '') === 'wfh' || ($user['role'] ?? '') === 'admin') ? '🏠 Punch In (Remote)' : 'Punch In (Office Login)');
?>
<span x-text="locState==='checking'?'Recording Location...':'<?= $punchInText ?>'"></span>
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
            <?php 
            $userTodayShift = AttendanceController::getTodayAttendanceForUser((int)$user['id']);
            $hasShiftEndedToday = ($userTodayShift && !empty($userTodayShift['clock_out']));
            ?>
            <?php if ($hasShiftEndedToday): ?>
                <div class="mb-5 p-3.5 rounded-2xl bg-emerald-50 border border-emerald-200/90 text-emerald-950 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs shadow-xs">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold shrink-0">
                            <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600"></i>
                        </div>
                        <div>
                            <span class="font-bold uppercase tracking-wide text-emerald-900 text-[11px]">Shift Completed For Today</span>
                            <p class="text-[11px] text-emerald-700 mt-0.5">Clocked out at <strong><?= formatTime($userTodayShift['clock_out']) ?></strong>. Total logged time today: <strong><?= number_format((float)($userTodayShift['total_hours'] ?? 0), 2) ?> hrs</strong>. Great work!</p>
                        </div>
                    </div>
                    <div class="shrink-0 flex items-center gap-2">
                        <a href="?page=employee-attendance" class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px] inline-flex items-center gap-1 shadow-xs transition">
                            <i data-lucide="calendar" class="w-3.5 h-3.5"></i> View Logs
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="mb-5 p-3.5 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-900 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs shadow-sm">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-700 flex items-center justify-center font-bold shrink-0">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <span class="font-bold uppercase tracking-wide text-amber-800 text-[11px]">Workspace in Read-Only Mode</span>
                            <p class="text-[11px] text-amber-700 mt-0.5">You have not punched in yet today. Please <strong>Punch In (Top Right Button)</strong> to enable onboarding, employee profile editing, and all operations.</p>
                        </div>
                    </div>
                    <div class="shrink-0 flex items-center gap-2">
                        <?php if (($user['role'] ?? '') === 'admin'): ?>
                            <a href="?page=admin-leaves" class="px-3 py-1.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-[11px] inline-flex items-center gap-1 shadow-xs transition">
                                <i data-lucide="calendar-check" class="w-3.5 h-3.5"></i> Review Leave Queue
                            </a>
                        <?php else: ?>
                            <a href="?page=employee-wfh" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[11px] inline-flex items-center gap-1 shadow-xs transition">
                                <i data-lucide="home" class="w-3.5 h-3.5"></i> Apply for WFH
                            </a>
                            <a href="?page=employee-leaves" class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-[11px] inline-flex items-center gap-1 shadow-xs transition">
                                <i data-lucide="calendar-plus" class="w-3.5 h-3.5"></i> Apply for Leave
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
