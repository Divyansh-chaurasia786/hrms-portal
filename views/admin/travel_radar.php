<?php
// views/admin/travel_radar.php
$title = "Field Travel Radar & Live Route Map - Ecofone HRMS";
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
$db = getDBConnection();
$today = date('Y-m-d');
$fieldAgents = $db->query("
    SELECT u.id, u.name, u.emp_id, u.designation, a.id as attendance_id, a.punch_in_time, a.punch_out_time,
           (SELECT COUNT(*) FROM employee_travel_logs WHERE attendance_id = a.id) as log_count,
           (SELECT SUM(distance_meters) FROM employee_travel_logs WHERE attendance_id = a.id) as total_meters
    FROM users u
    JOIN attendance a ON u.id = a.user_id AND a.date = '{$today}'
    WHERE u.work_mode = 'field'
    ORDER BY a.punch_in_time DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!-- Leaflet CSS & JS for Interactive Route Map -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<main class="flex-1 min-w-0 overflow-y-auto bg-slate-900 text-slate-100 p-4 sm:p-8">
    <div class="max-w-6xl mx-auto space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center">
                        <i data-lucide="map-pin" class="w-5 h-5"></i>
                    </div>
                    Field Travel Radar & Live Route Map
                </h1>
                <p class="text-xs text-slate-400 mt-1">Live tracking and total KM calculation for field agents from Punch-In to Punch-Out.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Active Agents List -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-5 shadow-xl space-y-3">
                <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Field Staff Today (<?= count($fieldAgents) ?>)</h2>
                <?php if (empty($fieldAgents)): ?>
                    <div class="text-xs text-slate-500 text-center py-8">No field employees punched in today yet.</div>
                <?php else: ?>
                    <div class="space-y-2.5">
                        <?php foreach ($fieldAgents as $agent): 
                            $km = round(($agent['total_meters'] ?? 0) / 1000, 2);
                        ?>
                            <div onclick="loadAgentRoute(<?= $agent['attendance_id'] ?>, '<?= htmlspecialchars($agent['name']) ?>')" class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800 hover:border-indigo-500/50 cursor-pointer transition space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-white text-xs"><?= htmlspecialchars($agent['name']) ?></span>
                                    <span class="text-[10px] font-extrabold text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20"><?= $km ?> KM</span>
                                </div>
                                <div class="text-[11px] text-slate-500 flex items-center justify-between">
                                    <span><?= $agent['emp_id'] ?></span>
                                    <span>In: <?= date('h:i A', strtotime($agent['punch_in_time'])) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Leaflet Interactive Map -->
            <div class="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-3xl p-5 shadow-xl space-y-3 flex flex-col">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <i data-lucide="navigation" class="w-4 h-4 text-indigo-400"></i> Route Trail Visualizer
                    </h3>
                    <span id="selectedAgentLabel" class="text-xs text-indigo-300 font-semibold">Select an agent</span>
                </div>
                <div id="radarMap" class="w-full h-96 rounded-2xl border border-slate-800 overflow-hidden bg-slate-950"></div>
            </div>
        </div>
    </div>
</main>

<script>
let map = L.map('radarMap').setView([26.8467, 80.9462], 12);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

let currentPolyline = null;
let currentMarkers = [];

function loadAgentRoute(attendanceId, name) {
    document.getElementById('selectedAgentLabel').innerText = 'Viewing: ' + name;
    fetch(`?action=get-travel-logs&attendance_id=${attendanceId}`)
        .then(res => res.json())
        .then(logs => {
            if (currentPolyline) map.removeLayer(currentPolyline);
            currentMarkers.forEach(m => map.removeLayer(m));
            currentMarkers = [];

            if (logs.length === 0) {
                alert('No GPS breadcrumbs logged yet for this agent.');
                return;
            }

            let latlngs = logs.map(l => [parseFloat(l.latitude), parseFloat(l.longitude)]);
            currentPolyline = L.polyline(latlngs, {color: '#4f46e5', weight: 4}).addTo(map);
            
            // Add Start & End Markers
            let startMarker = L.marker(latlngs[0]).addTo(map).bindPopup(`🟢 Start: ${name}`).openPopup();
            currentMarkers.push(startMarker);
            if (latlngs.length > 1) {
                let endMarker = L.marker(latlngs[latlngs.length - 1]).addTo(map).bindPopup(`📍 Current / End`);
                currentMarkers.push(endMarker);
            }
            map.fitBounds(currentPolyline.getBounds(), {padding: [50, 50]});
        });
}
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>