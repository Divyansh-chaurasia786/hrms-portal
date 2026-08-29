<!-- views/admin/travel_radar.php -->
<?php
$user = authUser();
$db = getDBConnection();
$selectedDate = !empty($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
$selectedUserId = !empty($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Fetch all active field staff for $selectedDate
$fieldEmployees = $db->query("
    SELECT u.id, u.name, u.emp_id, u.designation, u.work_mode, u.department_name, u.avatar,
           a.id as attendance_id, a.clock_in, a.clock_out, a.punch_in_lat, a.punch_in_lng, a.punch_out_lat, a.punch_out_lng, a.latitude as last_lat, a.longitude as last_lng,
           tl.name as tl_name,
           (SELECT COUNT(*) FROM employee_travel_logs l WHERE l.user_id = u.id AND DATE(l.recorded_at) = '{$selectedDate}') as waypoints_count,
           (SELECT COALESCE(SUM(l.distance_meters), 0) FROM employee_travel_logs l WHERE l.user_id = u.id AND DATE(l.recorded_at) = '{$selectedDate}') as total_distance_meters,
           (SELECT l.speed FROM employee_travel_logs l WHERE l.user_id = u.id AND DATE(l.recorded_at) = '{$selectedDate}' ORDER BY l.id DESC LIMIT 1) as current_speed
    FROM users u
    LEFT JOIN attendance a ON a.user_id = u.id AND a.date = '{$selectedDate}'
    LEFT JOIN users tl ON u.reporting_tl_id = tl.id
    WHERE (u.work_mode = 'field' OR u.department_name = 'Field Operations' OR u.designation LIKE '%Field%') AND u.status = 'active'
    ORDER BY CASE WHEN a.clock_in IS NOT NULL AND a.clock_out IS NULL THEN 1 ELSE 2 END, u.name ASC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Default selected user to first employee if not set
if ($selectedUserId === 0 && !empty($fieldEmployees[0]['id'])) {
    $selectedUserId = (int)$fieldEmployees[0]['id'];
}
?>

<!-- Global JSON Data for Initial Render -->
<script>
    window.fieldEmployeesRadarData = <?= json_encode($fieldEmployees, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<div class="space-y-6" x-data="{ 
    selectedUserId: <?= $selectedUserId ?>,
    selectedDate: '<?= htmlspecialchars($selectedDate) ?>',
    fieldEmployees: window.fieldEmployeesRadarData || [],
    selectedEmp: null,
    isLoadingWaypoints: false,
    livePollingTimer: null,
    init() {
        this.selectedEmp = this.fieldEmployees.find(e => Number(e.id) === Number(this.selectedUserId)) || (this.fieldEmployees[0] || null);
        this.$nextTick(() => {
            if (window.initRadarMapAndRender) {
                window.initRadarMapAndRender(this.selectedUserId, this.selectedDate);
            }
        });

        // ⚡ Live Auto-Refresh Polling every 12 seconds (Zero manual refresh needed!)
        this.livePollingTimer = setInterval(() => {
            if (this.selectedUserId && window.fetchAndRenderStaffLogs) {
                window.fetchAndRenderStaffLogs(this.selectedUserId, this.selectedDate, true);
            }
        }, 12000);
    },
    selectStaff(emp) {
        this.selectedUserId = emp.id;
        this.selectedEmp = emp;
        this.isLoadingWaypoints = true;
        if (window.fetchAndRenderStaffLogs) {
            window.fetchAndRenderStaffLogs(emp.id, this.selectedDate, false, () => {
                this.isLoadingWaypoints = false;
            });
        }
    },
    changeDate(newDate) {
        window.location.href = '?page=admin-travel-radar&date=' + newDate + '&user_id=' + this.selectedUserId;
    }
}">
    <!-- Header Banner & History Date Filter -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-extrabold shrink-0 border border-indigo-100 shadow-sm">
                <i data-lucide="map" class="w-6 h-6"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Field Staff Travel Radar & Route Tracker</h1>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-pulse"></span>
                        Live Auto-Sync Active
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Click any staff member on the left to immediately track route trails, speed, and KM distance in real-time.</p>
            </div>
        </div>

        <!-- Date History Selector -->
        <div class="flex items-center gap-2 flex-wrap">
            <div class="flex items-center gap-1.5 bg-slate-50 border border-slate-300 rounded-xl px-2.5 py-1.5 shadow-2xs">
                <i data-lucide="calendar" class="w-4 h-4 text-indigo-600"></i>
                <input type="date" :value="selectedDate" @change="changeDate($event.target.value)" class="bg-transparent text-xs font-bold text-slate-800 border-none outline-hidden cursor-pointer">
            </div>

            <button type="button" @click="changeDate('<?= date('Y-m-d') ?>')" class="px-3 py-2 text-xs font-bold rounded-xl transition cursor-pointer" :class="selectedDate === '<?= date('Y-m-d') ?>' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'">
                Today
            </button>
            <button type="button" @click="changeDate('<?= date('Y-m-d', strtotime('-1 day')) ?>')" class="px-3 py-2 text-xs font-bold rounded-xl transition cursor-pointer" :class="selectedDate === '<?= date('Y-m-d', strtotime('-1 day')) ?>' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'">
                Yesterday
            </button>
            <button type="button" @click="if (window.fetchAndRenderStaffLogs) window.fetchAndRenderStaffLogs(selectedUserId, selectedDate, false)" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition flex items-center gap-1.5 cursor-pointer">
                <i data-lucide="refresh-cw" class="w-3.5 h-3.5" :class="isLoadingWaypoints ? 'animate-spin' : ''"></i> Fetch Now
            </button>
        </div>
    </div>

    <!-- Radar Layout: Left Employee List, Right Map & Detailed Live Route Stats -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <!-- Left: Field Workforce Roster (4 cols) -->
        <div class="lg:col-span-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-4">
            <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                <span class="text-xs font-extrabold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                    <i data-lucide="users" class="w-4 h-4 text-slate-400"></i>
                    Field Workforce (<?= count($fieldEmployees) ?>)
                </span>
                <span class="text-[10px] font-mono text-slate-500 font-bold bg-slate-100 px-2 py-0.5 rounded-lg"><?= date('d M Y', strtotime($selectedDate)) ?></span>
            </div>

            <?php if (empty($fieldEmployees)): ?>
                <div class="text-center py-12 text-slate-400 text-xs space-y-2">
                    <i data-lucide="map-pin-off" class="w-8 h-8 mx-auto text-slate-300"></i>
                    <p>No staff registered in Field Operations.</p>
                </div>
            <?php else: ?>
                <div class="space-y-2.5 max-h-[580px] overflow-y-auto no-scrollbar pr-1">
                    <template x-for="emp in fieldEmployees" :key="emp.id">
                        <div @click="selectStaff(emp)" 
                             class="p-3.5 rounded-2xl border transition-all cursor-pointer flex flex-col gap-2 relative overflow-hidden group"
                             :class="Number(selectedUserId) === Number(emp.id) ? 'bg-indigo-50/95 border-indigo-500 ring-2 ring-indigo-500/20 shadow-md' : 'bg-slate-50/70 border-slate-200/80 hover:bg-slate-100/90'">
                            
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="w-9 h-9 rounded-xl font-bold text-xs flex items-center justify-center shrink-0 shadow-sm"
                                         :class="Number(selectedUserId) === Number(emp.id) ? 'bg-indigo-600 text-white' : 'bg-indigo-100 text-indigo-700'">
                                        <span x-text="emp.name.substring(0, 2).toUpperCase()"></span>
                                    </div>
                                    <div class="min-w-0">
                                        <h4 class="font-bold text-xs text-slate-900 truncate group-hover:text-indigo-600 transition" x-text="emp.name"></h4>
                                        <p class="text-[10px] text-slate-500 truncate" x-text="(emp.designation || 'Field Executive') + ' • ' + (emp.tl_name || 'HR Direct')"></p>
                                    </div>
                                </div>
                                <div class="shrink-0 text-right">
                                    <template x-if="emp.clock_in && !emp.clock_out">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-200 flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-pulse"></span>
                                            On Field
                                        </span>
                                    </template>
                                    <template x-if="!emp.clock_in || emp.clock_out">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-200 text-slate-600" x-text="emp.clock_out ? 'Shift Ended' : 'No Shift'"></span>
                                    </template>
                                </div>
                            </div>

                            <!-- Live Traveled Metrics Strip -->
                            <div class="grid grid-cols-3 gap-1 pt-1.5 border-t border-slate-200/60 text-center text-[10px]">
                                <div class="bg-white p-1 rounded-lg border border-slate-100 shadow-2xs">
                                    <span class="text-slate-400 block text-[9px]">Distance</span>
                                    <strong class="text-slate-800 font-mono" x-text="(Number(emp.total_distance_meters || 0) / 1000).toFixed(1) + ' km'"></strong>
                                </div>
                                <div class="bg-white p-1 rounded-lg border border-slate-100 shadow-2xs">
                                    <span class="text-slate-400 block text-[9px]">Speed</span>
                                    <strong class="text-indigo-600 font-mono" x-text="(Number(emp.current_speed || 0)).toFixed(0) + ' km/h'"></strong>
                                </div>
                                <div class="bg-white p-1 rounded-lg border border-slate-100 shadow-2xs">
                                    <span class="text-slate-400 block text-[9px]">Waypoints</span>
                                    <strong class="text-slate-800 font-mono" x-text="emp.waypoints_count || (emp.punch_in_lat ? 1 : 0)"></strong>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right: Real-Time Map & Route Analysis (8 cols) -->
        <div class="lg:col-span-8 space-y-4">
            
            <!-- Selected Staff Live Telemetry Bar -->
            <template x-if="selectedEmp">
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-indigo-600 via-indigo-500 to-purple-600 text-white flex items-center justify-center font-extrabold text-sm shadow-md shadow-indigo-600/30">
                            <span x-text="selectedEmp.name.substring(0, 2).toUpperCase()"></span>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-extrabold text-slate-900" x-text="selectedEmp.name"></h3>
                                <span class="font-mono text-[10px] bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded font-bold" x-text="selectedEmp.emp_id"></span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Reporting to: <strong class="text-slate-700" x-text="selectedEmp.tl_name || 'HR Direct'"></strong> • 
                                <span x-text="selectedEmp.clock_in ? 'Shift In: ' + selectedEmp.clock_in.substring(11, 16) + (selectedEmp.clock_out ? ' | Shift Out: ' + selectedEmp.clock_out.substring(11, 16) : ' (🟢 Active On Field)') : 'No punch records on this date'"></span>
                            </p>
                        </div>
                    </div>

                    <!-- Telemetry Stats Cards -->
                    <div class="flex items-center gap-2">
                        <div class="bg-emerald-50 border border-emerald-200 px-3.5 py-1.5 rounded-xl text-center shadow-2xs">
                            <span class="text-[9px] font-bold uppercase text-emerald-800 block">Total Travel</span>
                            <span class="text-sm font-extrabold text-emerald-900 font-mono" x-text="(Number(selectedEmp.total_distance_meters || 0) / 1000).toFixed(2) + ' KM'"></span>
                        </div>
                        <div class="bg-indigo-50 border border-indigo-200 px-3.5 py-1.5 rounded-xl text-center shadow-2xs">
                            <span class="text-[9px] font-bold uppercase text-indigo-800 block">Current Speed</span>
                            <span class="text-sm font-extrabold text-indigo-900 font-mono" x-text="(Number(selectedEmp.current_speed || 0)).toFixed(1) + ' KM/H'"></span>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Map Container -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-3">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center gap-2">
                        <i data-lucide="navigation" class="w-4 h-4 text-indigo-600"></i>
                        <span class="font-bold text-xs text-slate-800 uppercase tracking-wider">Live Route Trail & Waypoint Tracker</span>
                    </div>
                    <div class="flex items-center gap-3 text-[11px]">
                        <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Start (Punch In)</span>
                        <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span> Live Location</span>
                        <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span> Shift Stop / Punch Out</span>
                    </div>
                </div>

                <div id="radarMap" class="w-full h-[470px] rounded-xl border border-slate-200 z-10 shadow-inner"></div>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet JS & CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
(function() {
    let radarMap = null;
    let currentLayerGroup = null;

    function ensureMapInitialized() {
        if (!radarMap) {
            const mapContainer = document.getElementById('radarMap');
            if (!mapContainer) return null;

            radarMap = L.map('radarMap').setView([26.8467, 80.9462], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(radarMap);

            currentLayerGroup = L.layerGroup().addTo(radarMap);
        }
        setTimeout(() => radarMap.invalidateSize(), 200);
        return radarMap;
    }

    window.fetchAndRenderStaffLogs = function(userId, date, isSilent = false, callback = null) {
        if (!userId) return;
        ensureMapInitialized();

        fetch(`?action=get-travel-logs&user_id=${userId}&date=${date}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const emp = data.employee;
                const waypoints = data.waypoints || [];

                // Update Alpine selectedEmp telemetry if available
                const alpineEl = document.querySelector('[x-data]');
                if (alpineEl && alpineEl._x_dataStack && alpineEl._x_dataStack[0]) {
                    const ctx = alpineEl._x_dataStack[0];
                    if (emp) {
                        ctx.selectedEmp = emp;
                        // Also update left list item
                        const item = ctx.fieldEmployees.find(e => Number(e.id) === Number(userId));
                        if (item) {
                            item.total_distance_meters = emp.total_distance_meters;
                            item.current_speed = emp.current_speed;
                            item.waypoints_count = emp.waypoints_count;
                            item.clock_in = emp.clock_in;
                            item.clock_out = emp.clock_out;
                        }
                    }
                }

                renderCoordinatesOnMap(emp, waypoints, isSilent);
            }
            if (callback) callback();
        })
        .catch(err => {
            console.error('Failed to fetch travel logs:', err);
            if (callback) callback();
        });
    };

    function renderCoordinatesOnMap(emp, waypoints, isSilent) {
        if (!currentLayerGroup || !radarMap) return;
        currentLayerGroup.clearLayers();

        const coords = [];

        // 1. Add Punch In Coordinate
        if (emp && emp.punch_in_lat && emp.punch_in_lng) {
            coords.push([parseFloat(emp.punch_in_lat), parseFloat(emp.punch_in_lng)]);
        }

        // 2. Add All Travel Waypoints
        waypoints.forEach(wp => {
            if (wp.latitude && wp.longitude) {
                coords.push([parseFloat(wp.latitude), parseFloat(wp.longitude)]);
            }
        });

        // 3. Add Punch Out Coordinate if closed
        if (emp && emp.punch_out_lat && emp.punch_out_lng) {
            coords.push([parseFloat(emp.punch_out_lat), parseFloat(emp.punch_out_lng)]);
        }

        if (coords.length > 0) {
            // Draw Route Polyline
            if (coords.length > 1) {
                const polyline = L.polyline(coords, {
                    color: '#4f46e5',
                    weight: 5,
                    opacity: 0.85,
                    dashArray: '8, 8',
                    lineJoin: 'round'
                }).addTo(currentLayerGroup);

                if (!isSilent) {
                    radarMap.fitBounds(polyline.getBounds(), { padding: [50, 50] });
                }
            } else {
                if (!isSilent) {
                    radarMap.setView(coords[0], 15);
                }
            }

            // 1. Start Marker (Punch In Point)
            const startIcon = L.divIcon({
                html: '<div style="background:#10b981;color:#fff;font-weight:bold;font-size:10px;padding:4px 8px;border-radius:12px;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.3);white-space:nowrap;">🚩 Start (Punch In)</div>',
                className: 'custom-map-pin',
                iconAnchor: [30, 20]
            });
            L.marker(coords[0], { icon: startIcon }).addTo(currentLayerGroup)
             .bindPopup(`<strong>${emp ? emp.name : 'Staff'}</strong><br>🚩 Shift Started: ${emp && emp.clock_in ? emp.clock_in : 'Logged'}`);

            // 2. Latest Location Marker (Current Head)
            const latestCoord = coords[coords.length - 1];
            const liveIcon = L.divIcon({
                html: `<div style="background:#4f46e5;color:#fff;font-weight:bold;font-size:10px;padding:4px 8px;border-radius:12px;border:2px solid #fff;box-shadow:0 2px 8px rgba(79,70,229,0.5);white-space:nowrap;">🚗 ${emp ? emp.name : 'Staff'} (${Number(emp ? emp.current_speed || 0 : 0).toFixed(0)} km/h)</div>`,
                className: 'custom-live-pin',
                iconAnchor: [30, 20]
            });
            L.marker(latestCoord, { icon: liveIcon }).addTo(currentLayerGroup)
             .bindPopup(`<strong>🚗 Location: ${emp ? emp.name : 'Staff'}</strong><br>⚡ Speed: ${Number(emp ? emp.current_speed || 0 : 0).toFixed(1)} km/h<br>📍 Status: ${emp && emp.clock_in && !emp.clock_out ? 'On Field Duty' : 'Shift Concluded'}`);

            // 3. Stop Marker if Shift Concluded
            if (emp && emp.clock_out && coords.length > 1) {
                const stopIcon = L.divIcon({
                    html: '<div style="background:#ef4444;color:#fff;font-weight:bold;font-size:10px;padding:4px 8px;border-radius:12px;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.3);white-space:nowrap;">🛑 Shift Ended</div>',
                    className: 'custom-stop-pin',
                    iconAnchor: [30, 20]
                });
                L.marker(latestCoord, { icon: stopIcon }).addTo(currentLayerGroup);
            }

        } else {
            // No coordinates logged yet -> Default center on Lucknow
            if (!isSilent) {
                radarMap.setView([26.8467, 80.9462], 13);
            }
        }
    }

    window.initRadarMapAndRender = function(userId, date) {
        ensureMapInitialized();
        if (userId) {
            window.fetchAndRenderStaffLogs(userId, date, false);
        }
    };

    // Auto-run on direct load
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(() => {
            const initUser = <?= $selectedUserId ?>;
            const initDate = '<?= htmlspecialchars($selectedDate) ?>';
            if (initUser) window.initRadarMapAndRender(initUser, initDate);
        }, 300);
    } else {
        document.addEventListener('DOMContentLoaded', () => {
            const initUser = <?= $selectedUserId ?>;
            const initDate = '<?= htmlspecialchars($selectedDate) ?>';
            if (initUser) window.initRadarMapAndRender(initUser, initDate);
        });
    }
})();
</script>