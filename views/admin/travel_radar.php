<!-- views/admin/travel_radar.php -->
<?php
$user = authUser();
$db = getDBConnection();
$selectedDate = !empty($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
$selectedUserId = !empty($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Fetch all active field staff for $selectedDate
$fieldEmployees = $db->query("
    SELECT u.id, u.name, u.emp_id, u.designation, u.work_mode, u.department_name, u.avatar,
           a.id as attendance_id, a.clock_in, a.clock_out, a.punch_in_lat, a.punch_in_lng, a.punch_out_lat, a.punch_out_lng,
           a.address as punch_address,
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

if ($selectedUserId === 0 && !empty($fieldEmployees[0]['id'])) {
    $selectedUserId = (int)$fieldEmployees[0]['id'];
}
?>

<script>
    window.fieldEmployeesRadarData = <?= json_encode($fieldEmployees, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<div class="space-y-6" x-data="{ 
    selectedUserId: <?= $selectedUserId ?>,
    selectedDate: '<?= htmlspecialchars($selectedDate) ?>',
    fieldEmployees: window.fieldEmployeesRadarData || [],
    selectedEmp: null,
    analytics: {
        total_distance_km: 0,
        total_waypoints: 0,
        total_stops: 0,
        max_speed_kmh: 0,
        avg_speed_kmh: 0,
        shift_start_time: '-',
        shift_end_time: '-',
        is_active_now: false
    },
    startLocation: null,
    endLocation: null,
    stops: [],
    waypoints: [],
    isLoading: false,
    searchTerm: '',
    liveTimer: null,

    get filteredEmployees() {
        if (!this.searchTerm.trim()) return this.fieldEmployees;
        const q = this.searchTerm.toLowerCase();
        return this.fieldEmployees.filter(e => e.name.toLowerCase().includes(q) || (e.emp_id && e.emp_id.toLowerCase().includes(q)));
    },

    init() {
        this.selectedEmp = this.fieldEmployees.find(e => Number(e.id) === Number(this.selectedUserId)) || (this.fieldEmployees[0] || null);
        this.$nextTick(() => {
            this.loadStaffRoute(this.selectedUserId, this.selectedDate);
        });

        // Auto-Polling every 10 seconds for real-time live staff
        this.liveTimer = setInterval(() => {
            if (this.selectedUserId && this.analytics.is_active_now) {
                this.loadStaffRoute(this.selectedUserId, this.selectedDate, true);
            }
        }, 10000);
    },

    selectEmployee(emp) {
        this.selectedUserId = emp.id;
        this.selectedEmp = emp;
        this.loadStaffRoute(emp.id, this.selectedDate);
    },

    loadStaffRoute(userId, date, isSilent = false) {
        if (!userId) return;
        if (!isSilent) this.isLoading = true;

        fetch(`?action=get-travel-logs&user_id=${userId}&date=${date}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                this.selectedEmp = data.employee;
                this.analytics = data.analytics || this.analytics;
                this.startLocation = data.start_location;
                this.endLocation = data.end_location;
                this.stops = data.stops || [];
                this.waypoints = data.waypoints || [];

                // Render Map Route
                if (window.renderRadarMapEngine) {
                    window.renderRadarMapEngine(this.selectedEmp, this.waypoints, this.stops, isSilent);
                }
            }
            this.isLoading = false;
        })
        .catch(err => {
            console.error('Error fetching route logs:', err);
            this.isLoading = false;
        });
    },

    changeDate(newDate) {
        this.selectedDate = newDate;
        window.location.href = `?page=admin-travel-radar&date=${newDate}&user_id=${this.selectedUserId}`;
    },

    focusStopOnMap(stop) {
        if (window.focusMapOnCoordinate) {
            window.focusMapOnCoordinate(stop.lat, stop.lng, stop.title + ' (' + stop.duration + ')');
        }
    },

    openGoogleMaps() {
        if (!this.startLocation) {
            alert('No coordinates recorded for this staff on this date.');
            return;
        }
        const sLat = this.startLocation.lat;
        const sLng = this.startLocation.lng;
        const eLat = this.endLocation ? this.endLocation.lat : sLat;
        const eLng = this.endLocation ? this.endLocation.lng : sLng;
        const gUrl = `https://www.google.com/maps/dir/?api=1&origin=${sLat},${sLng}&destination=${eLat},${eLng}&travelmode=driving`;
        window.open(gUrl, '_blank');
    }
}">
    <!-- 🧭 TOP COMMAND BAR & DATE CONTROLS -->
    <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div class="flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-indigo-600 to-purple-600 text-white flex items-center justify-center font-black shadow-md shadow-indigo-600/25 shrink-0">
                <i data-lucide="map-pin" class="w-6 h-6"></i>
            </div>
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">Field Travel Radar & GPS Journey Inspector</h1>
                    <template x-if="analytics.is_active_now">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-300 flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-pulse"></span>
                            Live Duty Active
                        </span>
                    </template>
                    <template x-if="!analytics.is_active_now">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                            Historical Route Replay
                        </span>
                    </template>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Audit complete field routes, login/logout places, stoppage duration, and real-time live tracking.</p>
            </div>
        </div>

        <!-- Date & Google Maps Actions -->
        <div class="flex items-center gap-2.5 flex-wrap">
            <div class="flex items-center gap-1.5 bg-slate-50 border border-slate-300 rounded-2xl px-3 py-1.5 shadow-2xs">
                <i data-lucide="calendar" class="w-4 h-4 text-indigo-600"></i>
                <input type="date" :value="selectedDate" @change="changeDate($event.target.value)" class="bg-transparent text-xs font-extrabold text-slate-800 border-none outline-hidden cursor-pointer">
            </div>

            <button type="button" @click="changeDate('<?= date('Y-m-d') ?>')" class="px-3.5 py-2 text-xs font-extrabold rounded-xl transition cursor-pointer" :class="selectedDate === '<?= date('Y-m-d') ?>' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'">
                Today
            </button>
            <button type="button" @click="changeDate('<?= date('Y-m-d', strtotime('-1 day')) ?>')" class="px-3.5 py-2 text-xs font-extrabold rounded-xl transition cursor-pointer" :class="selectedDate === '<?= date('Y-m-d', strtotime('-1 day')) ?>' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'">
                Yesterday
            </button>

            <button type="button" @click="openGoogleMaps()" class="px-3.5 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 text-xs font-bold rounded-xl transition flex items-center gap-1.5 cursor-pointer shadow-2xs">
                <i data-lucide="external-link" class="w-3.5 h-3.5 text-emerald-600"></i> Open in Google Maps
            </button>

            <button type="button" @click="loadStaffRoute(selectedUserId, selectedDate)" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition flex items-center gap-1.5 cursor-pointer">
                <i data-lucide="refresh-cw" class="w-3.5 h-3.5" :class="isLoading ? 'animate-spin' : ''"></i>
            </button>
        </div>
    </div>

    <!-- 🗺️ MAIN RADAR GRID: ROSTER ON LEFT, MAP & TIMELINE ON RIGHT -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <!-- Left: Field Staff Roster & Selector (4 Cols) -->
        <div class="lg:col-span-4 bg-white p-5 rounded-3xl border border-slate-200 shadow-sm space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <span class="text-xs font-black text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                    <i data-lucide="users" class="w-4 h-4 text-indigo-600"></i>
                    Field Staff Roster (<span x-text="fieldEmployees.length"></span>)
                </span>
                <span class="text-[10px] font-mono text-slate-500 font-bold bg-slate-100 px-2 py-0.5 rounded-lg"><?= date('d M Y', strtotime($selectedDate)) ?></span>
            </div>

            <!-- Search Filter -->
            <div class="relative">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-3"></i>
                <input type="text" x-model="searchTerm" placeholder="Search staff by name or EMP ID..." class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <!-- Staff List Cards -->
            <div class="space-y-2.5 max-h-[580px] overflow-y-auto no-scrollbar pr-1">
                <template x-for="emp in filteredEmployees" :key="emp.id">
                    <div @click="selectEmployee(emp)" 
                         class="p-3.5 rounded-2xl border transition-all cursor-pointer flex flex-col gap-2 relative overflow-hidden group"
                         :class="Number(selectedUserId) === Number(emp.id) ? 'bg-indigo-50/95 border-indigo-500 ring-2 ring-indigo-500/25 shadow-md' : 'bg-slate-50/70 border-slate-200/80 hover:bg-slate-100/90'">
                        
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="w-10 h-10 rounded-2xl font-bold text-xs flex items-center justify-center shrink-0 shadow-sm"
                                     :class="Number(selectedUserId) === Number(emp.id) ? 'bg-gradient-to-tr from-indigo-600 to-purple-600 text-white' : 'bg-indigo-100 text-indigo-700'">
                                    <span x-text="emp.name.substring(0, 2).toUpperCase()"></span>
                                </div>
                                <div class="min-w-0">
                                    <h4 class="font-bold text-xs text-slate-900 truncate group-hover:text-indigo-600 transition" x-text="emp.name"></h4>
                                    <p class="text-[10px] text-slate-500 truncate" x-text="(emp.designation || 'Field Executive') + ' • ' + (emp.emp_id || 'EMP')"></p>
                                </div>
                            </div>
                            <div class="shrink-0 text-right">
                                <template x-if="emp.clock_in && !emp.clock_out">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200 flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-pulse"></span>
                                        On Field
                                    </span>
                                </template>
                                <template x-if="!emp.clock_in || emp.clock_out">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-200 text-slate-600" x-text="emp.clock_out ? 'Shift Ended' : 'No Duty'"></span>
                                </template>
                            </div>
                        </div>

                        <!-- Distance, Speed & Waypoints Mini Strip -->
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
                                <strong class="text-slate-800 font-mono" x-text="emp.waypoints_count || 0"></strong>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Right: Real-Time Route Map & Stoppage Breakdown (8 Cols) -->
        <div class="lg:col-span-8 space-y-4">
            
            <!-- Map Card -->
            <div class="bg-white p-4 rounded-3xl border border-slate-200 shadow-sm space-y-3">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center gap-2">
                        <i data-lucide="compass" class="w-4 h-4 text-indigo-600"></i>
                        <span class="font-extrabold text-xs text-slate-800 uppercase tracking-wider">Live Route Map View</span>
                    </div>

                    <!-- Map Legend Badges -->
                    <div class="flex items-center gap-2.5 text-[11px] font-semibold flex-wrap">
                        <span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-800 px-2 py-0.5 rounded-full border border-emerald-200">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span> 🚩 Start
                        </span>
                        <span class="inline-flex items-center gap-1 bg-indigo-50 text-indigo-800 px-2 py-0.5 rounded-full border border-indigo-200">
                            <span class="w-2 h-2 rounded-full bg-indigo-600"></span> 🚗 Live Head
                        </span>
                        <span class="inline-flex items-center gap-1 bg-amber-50 text-amber-800 px-2 py-0.5 rounded-full border border-amber-200">
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span> 🛑 Stoppages
                        </span>
                        <span class="inline-flex items-center gap-1 bg-rose-50 text-rose-800 px-2 py-0.5 rounded-full border border-rose-200">
                            <span class="w-2 h-2 rounded-full bg-rose-500"></span> 🏁 Shift End
                        </span>
                    </div>
                </div>

                <!-- Leaflet Map Canvas -->
                <div id="radarMap" class="w-full h-[480px] rounded-2xl border border-slate-200 z-10 shadow-inner"></div>
            </div>

            <!-- Stoppages & Journey Timeline Inspector Card -->
            <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
                            <i data-lucide="list-ordered" class="w-4 h-4"></i>
                        </div>
                        <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider">Chronological Journey & Stoppage Timeline</h3>
                    </div>
                    <span class="text-[11px] text-slate-400 font-bold" x-text="stops.length + ' Stoppage(s) Detected'"></span>
                </div>

                <!-- Empty State -->
                <template x-if="waypoints.length === 0">
                    <div class="text-center py-8 text-slate-400 text-xs space-y-1.5">
                        <i data-lucide="map-pin-off" class="w-8 h-8 mx-auto text-slate-300"></i>
                        <p class="font-bold">No GPS waypoints recorded for this staff on <?= date('d M Y', strtotime($selectedDate)) ?>.</p>
                        <p class="text-[11px] text-slate-400">Pings are automatically recorded every 30 seconds when the employee is clocked in on duty.</p>
                    </div>
                </template>

                <!-- Journey Timeline Flow -->
                <template x-if="waypoints.length > 0">
                    <div class="space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <!-- 1. Start Card -->
                            <div class="p-3 bg-emerald-50/70 border border-emerald-200 rounded-2xl space-y-1">
                                <div class="flex items-center justify-between text-[10px] font-bold text-emerald-800 uppercase">
                                    <span>🚩 1. Shift Started</span>
                                    <span class="font-mono text-emerald-900" x-text="analytics.shift_start_time"></span>
                                </div>
                                <p class="text-xs font-semibold text-slate-700 truncate" x-text="startLocation ? (startLocation.lat.toFixed(5) + ', ' + startLocation.lng.toFixed(5)) : 'Punch In Point'"></p>
                            </div>

                            <!-- 2. Stoppages Summary Card -->
                            <div class="p-3 bg-amber-50/70 border border-amber-200 rounded-2xl space-y-1">
                                <div class="flex items-center justify-between text-[10px] font-bold text-amber-800 uppercase">
                                    <span>🛑 2. Total Stoppages</span>
                                    <span class="font-mono text-amber-900" x-text="stops.length + ' Stops'"></span>
                                </div>
                                <p class="text-xs font-semibold text-slate-700" x-text="stops.length > 0 ? (stops.map(s => s.duration).join(', ')) : 'Continuous Motion (No long halts)'"></p>
                            </div>

                            <!-- 3. Final Head / End Card -->
                            <div class="p-3 bg-indigo-50/70 border border-indigo-200 rounded-2xl space-y-1">
                                <div class="flex items-center justify-between text-[10px] font-bold text-indigo-800 uppercase">
                                    <span>🏁 3. Latest / End Point</span>
                                    <span class="font-mono text-indigo-900" x-text="analytics.shift_end_time"></span>
                                </div>
                                <p class="text-xs font-semibold text-slate-700 truncate" x-text="endLocation ? (endLocation.lat.toFixed(5) + ', ' + endLocation.lng.toFixed(5)) : 'Last Recorded Position'"></p>
                            </div>
                        </div>

                        <!-- Stoppage Detail Pill Grid -->
                        <template x-if="stops.length > 0">
                            <div class="space-y-2 pt-2">
                                <span class="text-[10px] font-extrabold uppercase text-slate-400 block tracking-wider">Click any Stoppage to Focus Map:</span>
                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5">
                                    <template x-for="st in stops" :key="st.stop_number">
                                        <div @click="focusStopOnMap(st)" class="p-2.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-indigo-50 hover:border-indigo-400 transition cursor-pointer flex items-center justify-between gap-2 shadow-2xs">
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 rounded-full bg-amber-500 text-white font-black text-[10px] flex items-center justify-center shrink-0" x-text="st.stop_number"></div>
                                                <div class="min-w-0">
                                                    <h5 class="text-xs font-bold text-slate-800 truncate" x-text="'Stop #' + st.stop_number"></h5>
                                                    <span class="text-[10px] text-slate-500 font-mono" x-text="st.arrival_time + ' - ' + st.departure_time"></span>
                                                </div>
                                            </div>
                                            <span class="text-[10px] font-extrabold px-2 py-0.5 bg-amber-100 text-amber-900 rounded-lg" x-text="st.duration"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet Map Engine Controller -->
<script>
(function initRadarMapController() {
    let map = null;
    let layerGroup = null;

    function getMapInstance() {
        const container = document.getElementById('radarMap');
        if (!container || typeof L === 'undefined') return null;

        if (container._leaflet_id && map) {
            map.invalidateSize();
            return map;
        }

        try {
            if (map) map.remove();
            map = L.map('radarMap', { zoomControl: true }).setView([26.8467, 80.9462], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(map);

            layerGroup = L.layerGroup().addTo(map);
            setTimeout(() => map.invalidateSize(), 150);
            return map;
        } catch(e) {
            console.error('Leaflet Map creation error:', e);
            return null;
        }
    }

    window.renderRadarMapEngine = function(emp, waypoints, stops, isSilent) {
        const m = getMapInstance();
        if (!m || !layerGroup) return;

        layerGroup.clearLayers();
        m.invalidateSize();

        if (!waypoints || waypoints.length === 0) {
            if (!isSilent) m.setView([26.8467, 80.9462], 13);
            return;
        }

        const latLngs = waypoints.map(wp => [wp.lat, wp.lng]);

        // 1. Draw High-Visibility Polyline Route Trail
        if (latLngs.length > 1) {
            const polyline = L.polyline(latLngs, {
                color: '#4f46e5',
                weight: 6,
                opacity: 0.9,
                dashArray: '6, 6',
                lineJoin: 'round'
            }).addTo(layerGroup);

            // Intermediate Waypoint dots along the route
            latLngs.forEach((pt, idx) => {
                if (idx > 0 && idx < latLngs.length - 1) {
                    L.circleMarker(pt, {
                        radius: 4,
                        color: '#4338ca',
                        fillColor: '#818cf8',
                        fillOpacity: 0.8,
                        weight: 2
                    }).addTo(layerGroup).bindPopup(`<strong>📍 Waypoint #${idx + 1}</strong><br>Time: ${waypoints[idx].time}<br>Speed: ${waypoints[idx].speed} km/h`);
                }
            });

            if (!isSilent) {
                try {
                    const bounds = polyline.getBounds();
                    if (bounds.isValid() && (bounds.getNorthEast().lat !== bounds.getSouthWest().lat)) {
                        m.fitBounds(bounds, { padding: [60, 60], maxZoom: 17 });
                    } else {
                        m.setView(latLngs[latLngs.length - 1], 16);
                    }
                } catch(e) {
                    m.setView(latLngs[latLngs.length - 1], 16);
                }
            }
        } else {
            if (!isSilent) m.setView(latLngs[0], 16);
        }

        // 2. Start Marker 🚩 (Punch In)
        const startPoint = latLngs[0];
        const startIcon = L.divIcon({
            html: '<div style="background:#10b981;color:#fff;font-weight:900;font-size:11px;padding:6px 12px;border-radius:16px;border:2.5px solid #fff;box-shadow:0 3px 10px rgba(0,0,0,0.35);white-space:nowrap;cursor:pointer;">🚩 Start (Punch In)</div>',
            className: 'custom-start-marker',
            iconAnchor: [45, 20]
        });
        L.marker(startPoint, { icon: startIcon }).addTo(layerGroup)
         .bindPopup(`<strong>${emp ? emp.name : 'Staff'}</strong><br>🚩 Shift Started: ${emp && emp.clock_in ? emp.clock_in : 'Logged'}`);

        // 3. Stoppage Markers 🛑 (Client Visits / Halts)
        if (stops && stops.length > 0) {
            stops.forEach(st => {
                const stopIcon = L.divIcon({
                    html: `<div style="background:#f59e0b;color:#fff;font-weight:900;font-size:10px;padding:4px 8px;border-radius:12px;border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,0.3);white-space:nowrap;cursor:pointer;">🛑 Stop #${st.stop_number} (${st.duration})</div>`,
                    className: 'custom-stop-marker',
                    iconAnchor: [35, 15]
                });
                L.marker([st.lat, st.lng], { icon: stopIcon }).addTo(layerGroup)
                 .bindPopup(`<strong>🛑 Stoppage #${st.stop_number}</strong><br>⏰ Time: ${st.arrival_time} - ${st.departure_time}<br>⏱️ Duration: <strong>${st.duration}</strong>`);
            });
        }

        // 4. Live / Latest Position Pin 🚗
        const latestPoint = latLngs[latLngs.length - 1];
        const speedKmh = Number(emp ? emp.current_speed || 0 : 0).toFixed(0);
        const liveIcon = L.divIcon({
            html: `<div style="background:#4f46e5;color:#fff;font-weight:900;font-size:11px;padding:6px 12px;border-radius:16px;border:2.5px solid #fff;box-shadow:0 3px 12px rgba(79,70,229,0.6);white-space:nowrap;cursor:pointer;">🚗 ${emp ? emp.name : 'Staff'} (${speedKmh} km/h)</div>`,
            className: 'custom-live-marker',
            iconAnchor: [45, 20]
        });
        L.marker(latestPoint, { icon: liveIcon }).addTo(layerGroup)
         .bindPopup(`<strong>🚗 Live Position: ${emp ? emp.name : 'Staff'}</strong><br>⚡ Current Speed: ${Number(emp ? emp.current_speed || 0 : 0).toFixed(1)} km/h<br>📍 Status: ${emp && emp.clock_in && !emp.clock_out ? '🟢 Active On Field Duty' : 'Shift Concluded'}`);

        // 5. Punch Out Marker 🏁 if shift ended
        if (emp && emp.clock_out && latLngs.length > 1) {
            const endIcon = L.divIcon({
                html: '<div style="background:#ef4444;color:#fff;font-weight:900;font-size:11px;padding:6px 12px;border-radius:16px;border:2.5px solid #fff;box-shadow:0 3px 10px rgba(0,0,0,0.35);white-space:nowrap;cursor:pointer;">🏁 Shift Ended</div>',
                className: 'custom-end-marker',
                iconAnchor: [45, 20]
            });
            L.marker(latestPoint, { icon: endIcon }).addTo(layerGroup);
        }
    };

    window.focusMapOnCoordinate = function(lat, lng, popupText) {
        const m = getMapInstance();
        if (!m) return;
        m.setView([lat, lng], 17, { animate: true });
        L.popup().setLatLng([lat, lng]).setContent(`<strong>${popupText}</strong>`).openOn(m);
    };
})();
</script>