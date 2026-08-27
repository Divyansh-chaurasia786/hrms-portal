<!-- views/admin/travel_radar.php -->
<?php
$user = authUser();
$db = getDBConnection();
$today = date('Y-m-d');

// Fetch all active field staff with their shift attendance and latest coordinates
$fieldEmployees = $db->query("
    SELECT u.id, u.name, u.emp_id, u.designation, u.work_mode, u.department_name, u.avatar,
           a.id as attendance_id, a.clock_in, a.clock_out, a.punch_in_lat, a.punch_in_lng, a.latitude as last_lat, a.longitude as last_lng,
           tl.name as tl_name,
           (SELECT COUNT(*) FROM employee_travel_logs l WHERE l.user_id = u.id AND DATE(l.recorded_at) = '{$today}') as waypoints_count,
           (SELECT COALESCE(SUM(l.distance_meters), 0) FROM employee_travel_logs l WHERE l.user_id = u.id AND DATE(l.recorded_at) = '{$today}') as total_distance_meters,
           (SELECT l.speed FROM employee_travel_logs l WHERE l.user_id = u.id AND DATE(l.recorded_at) = '{$today}' ORDER BY l.id DESC LIMIT 1) as current_speed
    FROM users u
    LEFT JOIN attendance a ON a.user_id = u.id AND a.date = '{$today}'
    LEFT JOIN users tl ON u.reporting_tl_id = tl.id
    WHERE (u.work_mode = 'field' OR u.department_name = 'Field Operations' OR u.designation LIKE '%Field%') AND u.status = 'active'
    ORDER BY CASE WHEN a.clock_in IS NOT NULL AND a.clock_out IS NULL THEN 1 ELSE 2 END, u.name ASC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Fetch all travel waypoints grouped by user for today
$travelWaypoints = $db->query("
    SELECT l.*, u.name as emp_name, u.emp_id as emp_code
    FROM employee_travel_logs l
    JOIN users u ON l.user_id = u.id
    WHERE DATE(l.recorded_at) = '{$today}'
    ORDER BY l.id ASC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Also if any employee punched in today, ensure their punch-in coord is treated as waypoint #1
foreach ($fieldEmployees as $fe) {
    if (!empty($fe['punch_in_lat']) && !empty($fe['punch_in_lng'])) {
        $hasLogs = false;
        foreach ($travelWaypoints as $tw) {
            if ($tw['user_id'] == $fe['id']) {
                $hasLogs = true;
                break;
            }
        }
        if (!$hasLogs) {
            $travelWaypoints[] = [
                'id' => 999999 + $fe['id'],
                'user_id' => $fe['id'],
                'latitude' => $fe['punch_in_lat'],
                'longitude' => $fe['punch_in_lng'],
                'speed' => 0,
                'distance_meters' => 0,
                'recorded_at' => $fe['clock_in'] ?: date('Y-m-d H:i:s'),
                'emp_name' => $fe['name'],
                'emp_code' => $fe['emp_id']
            ];
        }
    }
}
?>

<div class="space-y-6" x-data="{ 
    selectedUserId: <?= !empty($fieldEmployees[0]['id']) ? (int)$fieldEmployees[0]['id'] : 0 ?>,
    fieldEmployees: <?= json_encode($fieldEmployees, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
    waypoints: <?= json_encode($travelWaypoints, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
    selectedEmp: null,
    init() {
        this.selectedEmp = this.fieldEmployees.find(e => Number(e.id) === Number(this.selectedUserId)) || (this.fieldEmployees[0] || null);
    },
    selectStaff(emp) {
        this.selectedUserId = emp.id;
        this.selectedEmp = emp;
        this.$nextTick(() => {
            if (window.renderStaffTrailOnMap) {
                window.renderStaffTrailOnMap(emp.id);
            }
        });
    }
}">
    <!-- Header Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center font-extrabold shrink-0 border border-amber-100 shadow-sm">
                <i data-lucide="map" class="w-6 h-6"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Field Staff Travel Radar & Route Tracker</h1>
                <p class="text-xs text-slate-500 mt-0.5">Live GPS location trail, km traveled, real-time speed, client route, and stoppage history.</p>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-xl text-xs font-bold shadow-2xs">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <?= count(array_filter($fieldEmployees, fn($f) => !empty($f['clock_in']) && empty($f['clock_out']))) ?> Live On Field
            </span>
            <button type="button" onclick="location.reload()" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition flex items-center gap-1.5 cursor-pointer">
                <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Refresh Radar
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
                <span class="text-[10px] font-mono text-slate-400"><?= date('d M Y') ?></span>
            </div>

            <?php if (empty($fieldEmployees)): ?>
                <div class="text-center py-12 text-slate-400 text-xs space-y-2">
                    <i data-lucide="map-pin-off" class="w-8 h-8 mx-auto text-slate-300"></i>
                    <p>No staff registered in Field Operations.</p>
                </div>
            <?php else: ?>
                <div class="space-y-2.5 max-h-[560px] overflow-y-auto no-scrollbar pr-1">
                    <template x-for="emp in fieldEmployees" :key="emp.id">
                        <div @click="selectStaff(emp)" 
                             class="p-3.5 rounded-2xl border transition-all cursor-pointer flex flex-col gap-2 relative overflow-hidden"
                             :class="Number(selectedUserId) === Number(emp.id) ? 'bg-indigo-50/80 border-indigo-400 ring-2 ring-indigo-500/20 shadow-sm' : 'bg-slate-50/70 border-slate-200/80 hover:bg-slate-100/80'">
                            
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="w-8 h-8 rounded-xl bg-indigo-100 text-indigo-700 font-bold text-xs flex items-center justify-center shrink-0">
                                        <span x-text="emp.name.substring(0, 2).toUpperCase()"></span>
                                    </div>
                                    <div class="min-w-0">
                                        <h4 class="font-bold text-xs text-slate-900 truncate" x-text="emp.name"></h4>
                                        <p class="text-[10px] text-slate-500 truncate" x-text="emp.designation || 'Field Executive'"></p>
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
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-200 text-slate-600" x-text="emp.clock_out ? 'Shift Ended' : 'Not In'"></span>
                                    </template>
                                </div>
                            </div>

                            <!-- Live Traveled Metrics Strip -->
                            <div class="grid grid-cols-3 gap-1 pt-1.5 border-t border-slate-200/60 text-center text-[10px]">
                                <div class="bg-white p-1 rounded-lg border border-slate-100">
                                    <span class="text-slate-400 block text-[9px]">Distance</span>
                                    <strong class="text-slate-800" x-text="(Number(emp.total_distance_meters || 0) / 1000).toFixed(1) + ' km'"></strong>
                                </div>
                                <div class="bg-white p-1 rounded-lg border border-slate-100">
                                    <span class="text-slate-400 block text-[9px]">Live Speed</span>
                                    <strong class="text-indigo-600" x-text="(Number(emp.current_speed || 0)).toFixed(0) + ' km/h'"></strong>
                                </div>
                                <div class="bg-white p-1 rounded-lg border border-slate-100">
                                    <span class="text-slate-400 block text-[9px]">Waypoints</span>
                                    <strong class="text-slate-800" x-text="emp.waypoints_count || (emp.punch_in_lat ? 1 : 0)"></strong>
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
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-500 to-purple-600 text-white flex items-center justify-center font-bold text-sm shadow-sm">
                            <span x-text="selectedEmp.name.substring(0, 2).toUpperCase()"></span>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-extrabold text-slate-900" x-text="selectedEmp.name"></h3>
                                <span class="font-mono text-[10px] bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded" x-text="selectedEmp.emp_id"></span>
                            </div>
                            <p class="text-xs text-slate-500">
                                Reporting to: <strong class="text-slate-700" x-text="selectedEmp.tl_name || 'HR Direct'"></strong> • 
                                <span x-text="selectedEmp.clock_in ? 'Punched In at ' + selectedEmp.clock_in.substring(11, 16) : 'No active shift today'"></span>
                            </p>
                        </div>
                    </div>

                    <!-- Telemetry Stats Cards -->
                    <div class="flex items-center gap-2">
                        <div class="bg-emerald-50 border border-emerald-200 px-3 py-1.5 rounded-xl text-center">
                            <span class="text-[9px] font-bold uppercase text-emerald-800 block">Total Travel</span>
                            <span class="text-sm font-extrabold text-emerald-900 font-mono" x-text="(Number(selectedEmp.total_distance_meters || 0) / 1000).toFixed(2) + ' KM'"></span>
                        </div>
                        <div class="bg-indigo-50 border border-indigo-200 px-3 py-1.5 rounded-xl text-center">
                            <span class="text-[9px] font-bold uppercase text-indigo-800 block">Latest Speed</span>
                            <span class="text-sm font-extrabold text-indigo-900 font-mono" x-text="(Number(selectedEmp.current_speed || 0)).toFixed(1) + ' KM/H'"></span>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Map Container -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i data-lucide="navigation" class="w-4 h-4 text-indigo-600"></i>
                        <span class="font-bold text-xs text-slate-800 uppercase tracking-wider">Live Route Map & Stop Points</span>
                    </div>
                    <div class="flex items-center gap-3 text-[11px]">
                        <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Start (Punch In)</span>
                        <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span> Current Location</span>
                        <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span> Client Visit / Stop</span>
                    </div>
                </div>

                <div id="radarMap" class="w-full h-[450px] rounded-xl border border-slate-200 z-10"></div>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet JS & CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Default center on Lucknow
    const map = L.map('radarMap').setView([26.8467, 80.9462], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    let currentLayerGroup = L.layerGroup().addTo(map);

    const allWaypoints = <?= json_encode($travelWaypoints) ?>;
    const allEmployees = <?= json_encode($fieldEmployees) ?>;

    window.renderStaffTrailOnMap = function(userId) {
        currentLayerGroup.clearLayers();

        const userWaypoints = allWaypoints.filter(w => Number(w.user_id) === Number(userId));
        const emp = allEmployees.find(e => Number(e.id) === Number(userId));

        const coords = [];

        // If punch in coordinates exist
        if (emp && emp.punch_in_lat && emp.punch_in_lng) {
            coords.push([parseFloat(emp.punch_in_lat), parseFloat(emp.punch_in_lng)]);
        }

        userWaypoints.forEach(wp => {
            if (wp.latitude && wp.longitude) {
                coords.push([parseFloat(wp.latitude), parseFloat(wp.longitude)]);
            }
        });

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

                map.fitBounds(polyline.getBounds(), { padding: [40, 40] });
            } else {
                map.setView(coords[0], 15);
            }

            // 1. Start Marker (Punch In Point)
            const startIcon = L.divIcon({
                html: '<div style="background:#10b981;color:#fff;font-weight:bold;font-size:10px;padding:4px 8px;border-radius:12px;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.3);white-space:nowrap;">🚩 Start (Punch In)</div>',
                className: 'custom-map-pin',
                iconAnchor: [30, 20]
            });
            L.marker(coords[0], { icon: startIcon }).addTo(currentLayerGroup)
             .bindPopup(`<strong>${emp ? emp.name : 'Staff'}</strong><br>🚩 Shift Started: ${emp && emp.clock_in ? emp.clock_in : 'Today'}`);

            // 2. Latest Location Marker (Current Head)
            const latestCoord = coords[coords.length - 1];
            const liveIcon = L.divIcon({
                html: `<div style="background:#4f46e5;color:#fff;font-weight:bold;font-size:10px;padding:4px 8px;border-radius:12px;border:2px solid #fff;box-shadow:0 2px 8px rgba(79,70,229,0.5);white-space:nowrap;">🚗 ${emp ? emp.name : 'Staff'} (${(emp ? emp.current_speed || 0 : 0)} km/h)</div>`,
                className: 'custom-live-pin',
                iconAnchor: [30, 20]
            });
            L.marker(latestCoord, { icon: liveIcon }).addTo(currentLayerGroup)
             .bindPopup(`<strong>🚗 Current Location: ${emp ? emp.name : 'Staff'}</strong><br>⚡ Live Speed: ${(emp ? emp.current_speed || 0 : 0)} km/h<br>📍 Status: ${emp && emp.clock_in && !emp.clock_out ? 'On Field Duty' : 'Shift Concluded'}`);

        } else {
            // No coordinates logged yet
            map.setView([26.8467, 80.9462], 12);
        }
    };

    // Render initially selected user
    const initUserId = <?= !empty($fieldEmployees[0]['id']) ? (int)$fieldEmployees[0]['id'] : 0 ?>;
    if (initUserId > 0) {
        window.renderStaffTrailOnMap(initUserId);
    }
});
</script>