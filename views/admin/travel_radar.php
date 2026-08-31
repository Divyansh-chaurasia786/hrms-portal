<!-- views/admin/travel_radar.php -->
<?php
$user = authUser();
$db = getDBConnection();
$selectedDate = !empty($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
$selectedUserId = !empty($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Fetch all active field staff for $selectedDate
// Single High-Speed JOIN query without correlated subqueries
$fieldEmployees = $db->query("
    SELECT u.id, u.name, u.emp_id, u.designation, u.work_mode, u.department_name, u.avatar,
           a.id as attendance_id, a.clock_in, a.clock_out, a.punch_in_lat, a.punch_in_lng, a.punch_out_lat, a.punch_out_lng,
           a.address as punch_address,
           tl.name as tl_name,
           COALESCE(stats.waypoints_count, 0) as waypoints_count,
           COALESCE(stats.total_distance_meters, 0) as total_distance_meters,
           COALESCE(stats.current_speed, 0) as current_speed
    FROM users u
    LEFT JOIN attendance a ON a.user_id = u.id AND a.date = '{$selectedDate}'
    LEFT JOIN users tl ON u.reporting_tl_id = tl.id
    LEFT JOIN (
        SELECT user_id, COUNT(*) as waypoints_count, SUM(distance_meters) as total_distance_meters, MAX(speed) as current_speed
        FROM employee_travel_logs
        WHERE recorded_at >= '{$selectedDate} 00:00:00' AND recorded_at <= '{$selectedDate} 23:59:59'
        GROUP BY user_id
    ) stats ON stats.user_id = u.id
    WHERE u.role NOT IN ('admin', 'hr')
      AND u.designation NOT LIKE '%HR%'
      AND (u.work_mode = 'field' OR u.department_name = 'Field Operations' OR u.designation LIKE '%Field%')
      AND u.status = 'active'
    ORDER BY CASE WHEN a.clock_in IS NOT NULL AND a.clock_out IS NULL THEN 1 ELSE 2 END,
             COALESCE(stats.waypoints_count, 0) DESC,
             u.name ASC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

if ($selectedUserId === 0 && !empty($fieldEmployees[0]['id'])) {
    $selectedUserId = (int)$fieldEmployees[0]['id'];
}
?>

<script>
    window.fieldEmployeesRadarData = <?= json_encode($fieldEmployees, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<div class="space-y-5" x-data="{ 
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
    mapLayerType: 'roadmap', // roadmap or satellite
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

        // 5s silent polling — matches 5s GPS ping interval for near-instant live tracking
        this.liveTimer = setInterval(() => {
            if (this.selectedUserId && this.analytics.is_active_now) {
                this.loadStaffRoute(this.selectedUserId, this.selectedDate, true);
            }
        }, 5000);
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

                if (window.renderCleanGoogleMap) {
                    window.renderCleanGoogleMap(this.selectedEmp, this.waypoints, this.stops, isSilent);
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

    toggleMapLayer(type) {
        this.mapLayerType = type;
        if (window.switchMapLayer) {
            window.switchMapLayer(type);
        }
    },

    focusStop(st) {
        if (window.focusMapStop) {
            window.focusMapStop(st.lat, st.lng, `🛑 Stop #${st.stop_number} (${st.duration})<br>Time: ${st.arrival_time} - ${st.departure_time}`);
        }
    },

    openGoogleMapsApp() {
        if (!this.startLocation) {
            alert('No coordinates recorded for this date.');
            return;
        }
        const sLat = this.startLocation.lat;
        const sLng = this.startLocation.lng;
        const eLat = this.endLocation ? this.endLocation.lat : sLat;
        const eLng = this.endLocation ? this.endLocation.lng : sLng;
        const url = `https://www.google.com/maps/dir/?api=1&origin=${sLat},${sLng}&destination=${eLat},${eLng}&travelmode=driving`;
        window.open(url, '_blank');
    }
}">
    <!-- 🧭 TOP HEADER BAR -->
    <div class="bg-white px-6 py-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center font-extrabold shadow-md shadow-blue-600/30 shrink-0">
                <i data-lucide="map" class="w-5 h-5"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-lg font-extrabold text-slate-900 tracking-tight">Field Staff GPS Route Radar</h1>
                    <template x-if="analytics.is_active_now">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300 flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-pulse"></span>
                            Live Active
                        </span>
                    </template>
                    <template x-if="!analytics.is_active_now">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                            History Replay
                        </span>
                    </template>
                </div>
                <p class="text-xs text-slate-500">Real-time GPS journey routes, stoppages, and shift audit on Google Maps.</p>
            </div>
        </div>

        <!-- Controls & Date Picker -->
        <div class="flex items-center gap-2 flex-wrap">
            <div class="flex items-center gap-1.5 bg-slate-50 border border-slate-300 rounded-xl px-2.5 py-1.5 shadow-2xs">
                <i data-lucide="calendar" class="w-4 h-4 text-blue-600"></i>
                <input type="date" :value="selectedDate" @change="changeDate($event.target.value)" class="bg-transparent text-xs font-bold text-slate-800 border-none outline-hidden cursor-pointer">
            </div>

            <button type="button" @click="changeDate('<?= date('Y-m-d') ?>')" class="px-3 py-1.5 text-xs font-bold rounded-xl transition cursor-pointer" :class="selectedDate === '<?= date('Y-m-d') ?>' ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'">
                Today
            </button>
            <button type="button" @click="changeDate('<?= date('Y-m-d', strtotime('-1 day')) ?>')" class="px-3 py-1.5 text-xs font-bold rounded-xl transition cursor-pointer" :class="selectedDate === '<?= date('Y-m-d', strtotime('-1 day')) ?>' ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'">
                Yesterday
            </button>

            <button type="button" @click="openGoogleMapsApp()" class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 text-xs font-bold rounded-xl transition flex items-center gap-1.5 cursor-pointer">
                <i data-lucide="external-link" class="w-3.5 h-3.5 text-emerald-600"></i> Open in Google Maps
            </button>

            <button type="button" @click="loadStaffRoute(selectedUserId, selectedDate)" class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition flex items-center gap-1 cursor-pointer">
                <i data-lucide="refresh-cw" class="w-3.5 h-3.5" :class="isLoading ? 'animate-spin' : ''"></i>
            </button>
        </div>
    </div>

    <!-- 🗺️ MAIN RADAR CONTAINER: 2-COLUMN BALANCED VIEW -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        
        <!-- Left: Field Staff Selector (4 Cols) -->
        <div class="lg:col-span-4 bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-3">
            <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                <span class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                    <i data-lucide="users" class="w-4 h-4 text-blue-600"></i>
                    Field Workforce (<span x-text="fieldEmployees.length"></span>)
                </span>
                <span class="text-[10px] font-mono text-slate-500 font-bold bg-slate-100 px-2 py-0.5 rounded-lg"><?= date('d M Y', strtotime($selectedDate)) ?></span>
            </div>

            <!-- Search -->
            <div class="relative">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5"></i>
                <input type="text" x-model="searchTerm" placeholder="Search staff name or EMP ID..." class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-3 py-1.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Staff Cards -->
            <div class="space-y-2 max-h-[620px] overflow-y-auto no-scrollbar pr-1">
                <template x-for="emp in filteredEmployees" :key="emp.id">
                    <div @click="selectEmployee(emp)" 
                         class="p-3 rounded-xl border transition-all cursor-pointer flex flex-col gap-2 relative overflow-hidden group"
                         :class="Number(selectedUserId) === Number(emp.id) ? 'bg-blue-50/90 border-blue-500 ring-2 ring-blue-500/20 shadow-sm' : 'bg-slate-50/70 border-slate-200/80 hover:bg-slate-100/90'">
                        
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="w-9 h-9 rounded-xl font-bold text-xs flex items-center justify-center shrink-0 shadow-xs"
                                     :class="Number(selectedUserId) === Number(emp.id) ? 'bg-blue-600 text-white' : 'bg-blue-100 text-blue-800'">
                                    <span x-text="emp.name.substring(0, 2).toUpperCase()"></span>
                                </div>
                                <div class="min-w-0">
                                    <h4 class="font-bold text-xs text-slate-900 truncate group-hover:text-blue-600 transition" x-text="emp.name"></h4>
                                    <p class="text-[10px] text-slate-500 truncate" x-text="(emp.designation || 'Field Executive') + ' • ' + (emp.emp_id || 'EMP')"></p>
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
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-200 text-slate-600" x-text="emp.clock_out ? 'Shift Ended' : 'No Duty'"></span>
                                </template>
                            </div>
                        </div>

                        <!-- Mini Traveled Stats -->
                        <div class="grid grid-cols-3 gap-1 pt-1.5 border-t border-slate-200/60 text-center text-[10px]">
                            <div class="bg-white p-1 rounded-lg border border-slate-100">
                                <span class="text-slate-400 block text-[9px]">Distance</span>
                                <strong class="text-slate-800 font-mono" x-text="(Number(emp.total_distance_meters || 0) / 1000).toFixed(1) + ' km'"></strong>
                            </div>
                            <div class="bg-white p-1 rounded-lg border border-slate-100">
                                <span class="text-slate-400 block text-[9px]">Speed</span>
                                <strong class="text-blue-600 font-mono" x-text="(Number(emp.current_speed || 0)).toFixed(0) + ' km/h'"></strong>
                            </div>
                            <div class="bg-white p-1 rounded-lg border border-slate-100">
                                <span class="text-slate-400 block text-[9px]">Waypoints</span>
                                <strong class="text-slate-800 font-mono" x-text="emp.waypoints_count || 0"></strong>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Right: Real Google Maps Canvas & Clean Journey Trail (8 Cols) -->
        <div class="lg:col-span-8 space-y-4">
            
            <!-- Map Container with Clean Floating Controls -->
            <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-sm space-y-2.5 relative">
                
                <!-- Map Top Bar: Selected Staff Summary & Map Style Switcher -->
                <div class="flex items-center justify-between flex-wrap gap-2 px-1">
                    <template x-if="selectedEmp">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-bold text-xs text-slate-900" x-text="selectedEmp.name"></span>
                            <span class="text-[10px] text-slate-500 font-mono" x-text="'(' + (selectedEmp.emp_id || '') + ')'"></span>
                            <span class="text-xs text-slate-400">•</span>
                            <span class="text-xs font-bold text-blue-700 font-mono" x-text="analytics.total_distance_km + ' KM traveled'"></span>
                            <span class="text-xs text-slate-400">•</span>
                            <span class="text-xs font-semibold text-slate-600" x-text="analytics.total_stops + ' stops'"></span>
                            
                            <!-- 🔋 Live Battery & Last Seen Status Telemetry -->
                            <template x-if="analytics.latest_battery_level !== null && analytics.latest_battery_level !== undefined">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border flex items-center gap-1 shadow-2xs"
                                      :class="analytics.latest_battery_level > 20 ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-300 animate-pulse'">
                                    <span x-text="analytics.latest_battery_level > 20 ? '🔋' : '🪫'"></span>
                                    <span x-text="analytics.latest_battery_level + '% Battery'"></span>
                                </span>
                            </template>

                            <template x-if="analytics.last_seen_at">
                                <span class="text-[10px] font-mono text-slate-600 bg-slate-100 px-2.5 py-0.5 rounded-lg border border-slate-200 inline-flex items-center gap-1.5 shadow-2xs">
                                    <span class="w-2 h-2 rounded-full inline-block" :class="(analytics.last_seen_seconds_ago !== null && analytics.last_seen_seconds_ago < 30) ? 'bg-emerald-500 animate-ping' : ((analytics.last_seen_seconds_ago !== null && analytics.last_seen_seconds_ago < 120) ? 'bg-emerald-500' : 'bg-amber-500')"></span>
                                    <span>Last Ping: <strong class="text-slate-900" x-text="analytics.last_seen_at"></strong></span>
                                    <template x-if="analytics.last_seen_seconds_ago !== null && analytics.last_seen_seconds_ago !== undefined">
                                        <span class="text-slate-400 text-[9px]" x-text="analytics.last_seen_seconds_ago < 60 ? ('(' + analytics.last_seen_seconds_ago + 's ago)') : ('(' + Math.round(analytics.last_seen_seconds_ago/60) + 'm ago)')"></span>
                                    </template>
                                </span>
                            </template>
                        </div>
                    </template>

                    <!-- Clean Google Map Style Switcher -->
                    <div class="flex items-center gap-1 bg-slate-100 p-0.5 rounded-xl border border-slate-200 text-[11px] font-semibold">
                        <button type="button" @click="toggleMapLayer('roadmap')" class="px-2.5 py-1 rounded-lg transition cursor-pointer" :class="mapLayerType === 'roadmap' ? 'bg-white text-blue-700 shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900'">
                            Map
                        </button>
                        <button type="button" @click="toggleMapLayer('satellite')" class="px-2.5 py-1 rounded-lg transition cursor-pointer" :class="mapLayerType === 'satellite' ? 'bg-white text-blue-700 shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900'">
                            Satellite
                        </button>
                    </div>
                </div>

                <!-- Google Maps Canvas -->
                <div id="radarMap" class="w-full h-[500px] rounded-xl border border-slate-200 z-10 shadow-inner"></div>

                <!-- Clean Bottom Stoppages / Timeline Bar (Only shows when stops exist) -->
                <template x-if="stops.length > 0">
                    <div class="pt-2 border-t border-slate-100">
                        <div class="flex items-center justify-between mb-1.5 px-1">
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Stoppage Stops (Click to focus on Map):</span>
                            <span class="text-[10px] text-slate-400" x-text="stops.length + ' stops detected'"></span>
                        </div>
                        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-1">
                            <template x-for="st in stops" :key="st.stop_number">
                                <div @click="focusStop(st)" class="px-3 py-1.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-blue-50 hover:border-blue-300 transition cursor-pointer shrink-0 flex items-center gap-2 text-xs shadow-2xs">
                                    <span class="w-5 h-5 rounded-full bg-amber-500 text-white font-black text-[10px] flex items-center justify-center shrink-0" x-text="st.stop_number"></span>
                                    <div class="text-[11px] leading-tight">
                                        <strong class="text-slate-800" x-text="st.duration"></strong>
                                        <span class="text-slate-400 text-[10px] block font-mono" x-text="st.arrival_time"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<!-- Clean Google Maps / Leaflet Engine -->
<script>
(function() {
    let map = null;
    let currentLayerGroup = null;
    let baseTileLayer = null;

    const ROADMAP_URL = 'https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}';
    const SATELLITE_URL = 'https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}';

    function initMap() {
        const container = document.getElementById('radarMap');
        if (!container || typeof L === 'undefined') return null;

        if (container._leaflet_id && map) {
            map.invalidateSize();
            return map;
        }

        try {
            if (map) map.remove();
            // Real Google Maps Streets Tiles
            map = L.map('radarMap', { zoomControl: true }).setView([26.8467, 80.9462], 13);
            
            baseTileLayer = L.tileLayer(ROADMAP_URL, {
                attribution: '© Google Maps',
                maxZoom: 20
            }).addTo(map);

            currentLayerGroup = L.layerGroup().addTo(map);
            setTimeout(() => map.invalidateSize(), 150);
            return map;
        } catch(e) {
            console.error('Map init error:', e);
            return null;
        }
    }

    window.switchMapLayer = function(type) {
        if (!map || !baseTileLayer) return;
        const newUrl = type === 'satellite' ? SATELLITE_URL : ROADMAP_URL;
        baseTileLayer.setUrl(newUrl);
    };

    window.renderCleanGoogleMap = function(emp, waypoints, stops, isSilent) {
        const m = initMap();
        if (!m || !currentLayerGroup) return;

        currentLayerGroup.clearLayers();
        m.invalidateSize();

        if (!waypoints || waypoints.length === 0) {
            if (!isSilent) m.setView([26.8467, 80.9462], 13);
            return;
        }

        const latLngs = waypoints.map(wp => [wp.lat, wp.lng]);

        // 1. Draw Clean Crisp Polyline (Google Maps Blue)
        if (latLngs.length > 1) {
            // Shadow under polyline for depth
            L.polyline(latLngs, {
                color: '#1d4ed8',
                weight: 6,
                opacity: 0.85,
                lineCap: 'round',
                lineJoin: 'round'
            }).addTo(currentLayerGroup);

            // Core Blue Line
            const polyline = L.polyline(latLngs, {
                color: '#3b82f6',
                weight: 4,
                opacity: 1,
                lineCap: 'round',
                lineJoin: 'round'
            }).addTo(currentLayerGroup);

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

        // 2. Start Pin 🟢 (Clean Google Style Origin Dot with Pulse)
        const startPoint = latLngs[0];
        const startPin = L.divIcon({
            html: `<div style="position:relative;width:24px;height:24px;background:#10b981;border:3px solid #ffffff;border-radius:50%;box-shadow:0 3px 8px rgba(0,0,0,0.4);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:bold;font-size:10px;">A</div>`,
            className: 'clean-start-pin',
            iconSize: [24, 24],
            iconAnchor: [12, 12]
        });
        L.marker(startPoint, { icon: startPin }).addTo(currentLayerGroup)
         .bindPopup(`<strong>🚩 Start Point (Punch In)</strong><br>${emp ? emp.name : 'Staff'}<br>Time: ${emp && emp.clock_in ? emp.clock_in : 'Logged'}`);

        // 3. Stoppage Pins 🛑 (Clean Numbered Badges - No big text boxes!)
        if (stops && stops.length > 0) {
            stops.forEach(st => {
                const stopPin = L.divIcon({
                    html: `<div style="width:20px;height:20px;background:#f59e0b;border:2px solid #ffffff;border-radius:50%;box-shadow:0 2px 6px rgba(0,0,0,0.35);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:900;font-size:10px;">${st.stop_number}</div>`,
                    className: 'clean-stop-pin',
                    iconSize: [20, 20],
                    iconAnchor: [10, 10]
                });
                L.marker([st.lat, st.lng], { icon: stopPin }).addTo(currentLayerGroup)
                 .bindPopup(`<strong>🛑 Stoppage #${st.stop_number}</strong><br>Duration: <strong>${st.duration}</strong><br>Time: ${st.arrival_time} - ${st.departure_time}`);
            });
        }

        // 4. Live / Latest Head Marker 🚗 (Clean Google Navigation Arrow)
        const latestPoint = latLngs[latLngs.length - 1];
        const speedVal = Number(emp ? emp.current_speed || 0 : 0).toFixed(0);
        const livePin = L.divIcon({
            html: `<div style="position:relative;width:28px;height:28px;background:#2563eb;border:3px solid #ffffff;border-radius:50%;box-shadow:0 3px 10px rgba(37,99,235,0.6);display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;">🚗</div>`,
            className: 'clean-live-pin',
            iconSize: [28, 28],
            iconAnchor: [14, 14]
        });
        L.marker(latestPoint, { icon: livePin }).addTo(currentLayerGroup)
         .bindPopup(`<strong>🚗 ${emp ? emp.name : 'Staff'}</strong><br>Speed: <strong>${speedVal} km/h</strong><br>Status: ${emp && emp.clock_in && !emp.clock_out ? '🟢 Active On Duty' : 'Shift Concluded'}`);

        // 5. Punch Out Marker 🏁 if shift concluded
        if (emp && emp.clock_out && latLngs.length > 1) {
            const endPin = L.divIcon({
                html: `<div style="position:relative;width:24px;height:24px;background:#ef4444;border:3px solid #ffffff;border-radius:50%;box-shadow:0 3px 8px rgba(0,0,0,0.4);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:bold;font-size:10px;">B</div>`,
                className: 'clean-end-pin',
                iconSize: [24, 24],
                iconAnchor: [12, 12]
            });
            L.marker(latestPoint, { icon: endPin }).addTo(currentLayerGroup)
             .bindPopup(`<strong>🏁 Shift Concluded (Punch Out)</strong><br>Time: ${emp.clock_out}`);
        }
    };

    window.focusMapStop = function(lat, lng, popupHtml) {
        const m = initMap();
        if (!m) return;
        m.setView([lat, lng], 18, { animate: true });
        L.popup().setLatLng([lat, lng]).setContent(popupHtml).openOn(m);
    };
})();
</script>