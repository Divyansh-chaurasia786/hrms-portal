<!-- views/admin/live_location.php - Live Field Executive Location Tracker -->
<?php
requireRole(['admin', 'team_lead']);
$db = getDBConnection();
$today = date('Y-m-d');

// Fetch latest location ping per user for today
$liveLocations = $db->query("
    SELECT lp.user_id, lp.latitude, lp.longitude, lp.accuracy, lp.address, lp.ping_type, lp.created_at,
           u.name, u.emp_id, u.designation, u.role, u.avatar
    FROM location_pings lp
    INNER JOIN (
        SELECT user_id, MAX(id) as max_id FROM location_pings WHERE session_date = '{$today}' GROUP BY user_id
    ) latest ON lp.id = latest.max_id
    JOIN users u ON u.id = lp.user_id
    WHERE u.role NOT IN ('admin') AND u.designation NOT LIKE '%HR%'
    ORDER BY lp.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white p-5 sm:p-6 rounded-3xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-emerald-600 to-teal-600 text-white flex items-center justify-center font-bold shrink-0 shadow-md shadow-emerald-600/20">
                <i data-lucide="map-pin" class="w-6 h-6"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">Live Field Tracker</h1>
                    <span class="flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span> Live
                    </span>
                </div>
                <p class="text-xs text-slate-500 font-medium mt-0.5">Real-time GPS location of all active field executives today.</p>
            </div>
        </div>
        <div class="text-xs font-semibold text-slate-500">Auto-refreshes every 5 minutes</div>
    </div>

    <?php if (empty($liveLocations)): ?>
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-12 text-center">
            <div class="w-14 h-14 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
                <i data-lucide="map-pin-off" class="w-7 h-7"></i>
            </div>
            <h4 class="text-sm font-bold text-slate-700">No Location Pings Today</h4>
            <p class="text-xs text-slate-400 mt-1">Location pings will appear here as field executives start their shifts. Tracking starts automatically on app open.</p>
        </div>
    <?php else: ?>
        <!-- Location Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($liveLocations as $loc): ?>
                <?php
                $minsAgo = (int)round((time() - strtotime($loc['created_at'])) / 60);
                $freshness = $minsAgo < 10 ? 'text-emerald-600' : ($minsAgo < 60 ? 'text-amber-600' : 'text-rose-500');
                $freshnessLabel = $minsAgo < 1 ? 'Just now' : ($minsAgo < 60 ? "{$minsAgo}m ago" : round($minsAgo/60) . "h ago");
                $mapsUrl = "https://www.google.com/maps?q={$loc['latitude']},{$loc['longitude']}";
                ?>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 space-y-3 hover:border-emerald-300 transition">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-purple-600 text-white flex items-center justify-center font-black text-sm">
                                <?= strtoupper(substr($loc['name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($loc['name']) ?></div>
                                <div class="text-[10px] text-slate-400"><?= htmlspecialchars($loc['designation'] ?: $loc['role']) ?></div>
                            </div>
                        </div>
                        <span class="text-[10px] font-bold <?= $freshness ?>">📡 <?= $freshnessLabel ?></span>
                    </div>

                    <!-- Map Preview Thumbnail (click to open Maps) -->
                    <a href="<?= $mapsUrl ?>" target="_blank" class="block">
                        <div class="relative w-full h-28 rounded-xl overflow-hidden border border-slate-200 bg-slate-100 cursor-pointer hover:opacity-90 transition">
                            <iframe 
                                src="https://maps.google.com/maps?q=<?= $loc['latitude'] ?>,<?= $loc['longitude'] ?>&z=15&output=embed"
                                class="absolute inset-0 w-full h-full border-0 pointer-events-none" 
                                loading="lazy"
                                title="Location of <?= htmlspecialchars($loc['name']) ?>"
                            ></iframe>
                            <div class="absolute bottom-1.5 right-1.5 px-2 py-0.5 bg-white/90 backdrop-blur-sm text-[10px] font-bold text-slate-700 rounded-lg shadow-xs border border-slate-200">
                                📍 Open Maps
                            </div>
                        </div>
                    </a>

                    <!-- Coordinates + Details -->
                    <div class="flex items-center justify-between text-[11px]">
                        <div class="text-slate-500 font-mono">
                            <?= number_format($loc['latitude'], 6) ?>, <?= number_format($loc['longitude'], 6) ?>
                        </div>
                        <?php if ($loc['accuracy']): ?>
                            <span class="text-slate-400">±<?= round($loc['accuracy']) ?>m</span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($loc['address'])): ?>
                        <div class="text-xs text-slate-600 bg-slate-50 rounded-xl px-3 py-1.5 border border-slate-200 line-clamp-2">
                            📍 <?= htmlspecialchars($loc['address']) ?>
                        </div>
                    <?php endif; ?>

                    <a href="<?= $mapsUrl ?>" target="_blank" class="w-full flex items-center justify-center gap-1.5 py-2 bg-emerald-50 hover:bg-emerald-600 text-emerald-700 hover:text-white border border-emerald-200 rounded-xl text-xs font-bold transition cursor-pointer">
                        <i data-lucide="navigation" class="w-3.5 h-3.5"></i>
                        <span>Open in Google Maps</span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Auto-refresh location board every 5 minutes
setTimeout(function() { window.location.reload(); }, 5 * 60 * 1000);
</script>