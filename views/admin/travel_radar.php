<!-- views/admin/travel_radar.php -->
<?php
$user = authUser();
$db = getDBConnection();
$today = date('Y-m-d');

// Fetch all active field staff with their latest coordinates
$fieldEmployees = $db->query("
    SELECT u.id, u.name, u.emp_id, u.designation, u.work_mode, u.department_name,
           a.id as attendance_id, a.clock_in, a.clock_out,
           tl.name as tl_name
    FROM users u
    LEFT JOIN attendance a ON a.user_id = u.id AND a.date = '{$today}'
    LEFT JOIN users tl ON u.reporting_tl_id = tl.id
    WHERE u.work_mode = 'field' AND u.status = 'active'
    ORDER BY CASE WHEN a.clock_in IS NOT NULL AND a.clock_out IS NULL THEN 1 ELSE 2 END, u.name ASC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Fetch recent coordinates for today
$travelLogs = $db->query("
    SELECT l.*, u.name as emp_name, u.emp_id as emp_code
    FROM employee_travel_logs l
    JOIN users u ON l.user_id = u.id
    WHERE DATE(l.recorded_at) = '{$today}'
    ORDER BY l.recorded_at ASC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<div class="space-y-6">
    <!-- Header Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="map" class="w-5 h-5"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Field Staff Travel Radar</h1>
                <p class="text-xs text-slate-500 mt-0.5">Live GPS routes, movement trails, and travel distance tracking for on-field staff.</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-xl text-xs font-bold">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <?= count(array_filter($fieldEmployees, fn($f) => !empty($f['clock_in']) && empty($f['clock_out']))) ?> Active on Field
            </span>
        </div>
    </div>

    <!-- Radar Map & Active Staff Split -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left: Active Field Staff List -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-4">
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center justify-between">
                <span>Field Workforce (<?= count($fieldEmployees) ?>)</span>
                <span class="text-[10px] text-slate-400">Date: <?= date('d M Y') ?></span>
            </h3>

            <?php if (empty($fieldEmployees)): ?>
                <div class="text-center py-8 text-slate-400 text-xs">
                    <i data-lucide="users" class="w-8 h-8 mx-auto mb-2 text-slate-300"></i>
                    No staff currently marked as 'Field Staff'.
                </div>
            <?php else: ?>
                <div class="space-y-2.5 max-h-[450px] overflow-y-auto no-scrollbar">
                    <?php foreach ($fieldEmployees as $fe): 
                        $isOnShift = (!empty($fe['clock_in']) && empty($fe['clock_out']));
                        $feName = $fe['name'] ?? 'Staff';
                        $feCode = $fe['emp_id'] ?? '';
                        $feDesig = $fe['designation'] ?? 'Field Staff';
                    ?>
                        <div class="p-3 rounded-xl border border-slate-100 bg-slate-50/70 hover:bg-slate-100 transition flex items-center justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-bold text-xs text-slate-900 truncate"><?= htmlspecialchars($feName) ?></span>
                                    <span class="font-mono text-[10px] text-slate-400">(<?= htmlspecialchars($feCode) ?>)</span>
                                </div>
                                <div class="text-[11px] text-slate-500 truncate"><?= htmlspecialchars($feDesig) ?> • TL: <?= htmlspecialchars($fe['tl_name'] ?? 'HR Direct') ?></div>
                            </div>
                            <div class="shrink-0 text-right">
                                <?php if ($isOnShift): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-700 border border-emerald-200 animate-pulse">On Field</span>
                                    <span class="block text-[9px] font-mono text-slate-400 mt-0.5">In: <?= date('h:i A', strtotime($fe['clock_in'])) ?></span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-200 text-slate-600">Offline</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right: OpenStreetMap Interactive Trail -->
        <div class="lg:col-span-2 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-3 flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i data-lucide="navigation" class="w-4 h-4 text-indigo-600"></i>
                    <span class="font-bold text-xs text-slate-800 uppercase tracking-wider">Live Route Trail & Waypoints</span>
                </div>
                <span class="text-[11px] font-semibold text-slate-500 font-mono"><?= count($travelLogs) ?> waypoints logged today</span>
            </div>

            <!-- Leaflet Map Container -->
            <div id="travelMap" class="w-full h-96 rounded-xl border border-slate-200 z-10"></div>
        </div>
    </div>
</div>

<!-- Leaflet JS & CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const map = L.map('travelMap').setView([26.884578, 80.938924], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    const logs = <?= json_encode($travelLogs) ?>;
    if (logs && logs.length > 0) {
        const latlngs = logs.map(l => [parseFloat(l.latitude), parseFloat(l.longitude)]);
        const polyline = L.polyline(latlngs, {color: '#6366f1', weight: 4, opacity: 0.8}).addTo(map);
        map.fitBounds(polyline.getBounds());

        logs.forEach((l, idx) => {
            L.marker([parseFloat(l.latitude), parseFloat(l.longitude)])
             .addTo(map)
             .bindPopup(`<strong>${l.emp_name || 'Staff'}</strong><br>Time: ${l.recorded_at || ''}<br>Speed: ${l.speed || 0} km/h`);
        });
    }
});
</script>