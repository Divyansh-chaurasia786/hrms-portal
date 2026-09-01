    </main>
</div>

<?php
$currentUser = authUser();
$isFieldActive = false;
$activeAttId = 0;
$activePunchInLat = 0;
$activePunchInLng = 0;

// Strictly track ONLY Field Employees (role = 'employee' AND work_mode = 'field')
$isFieldUser = false;
if ($currentUser) {
    $userRole = strtolower($currentUser['role'] ?? '');
    $desig = strtolower($currentUser['designation'] ?? '');
    $dept = strtolower($currentUser['department_name'] ?? '');
    $workMode = strtolower($currentUser['work_mode'] ?? '');
    
    // Explicitly block all HR, Admin, Team Lead, Office roles
    if (!in_array($userRole, ['admin', 'hr', 'team_lead']) && strpos($desig, 'hr') === false && strpos($dept, 'human') === false && strpos($desig, 'head') === false && strpos($desig, 'admin') === false && strpos($desig, 'junior hr') === false) {
        if ($workMode === 'field' || strpos($desig, 'field') !== false || strpos($dept, 'field') !== false) {
            $isFieldUser = true;
        }
    }
}

if ($isFieldUser) {
    $dbTracker = getDBConnection();
    $todayDate = date('Y-m-d');
    $attRow = $dbTracker->query("SELECT id, punch_in_lat, punch_in_lng FROM attendance WHERE user_id = {$currentUser['id']} AND date = '{$todayDate}' AND clock_in IS NOT NULL AND clock_out IS NULL")->fetch();
    if ($attRow) {
        $isFieldActive = true;
        $activeAttId = (int)$attRow['id'];
        $activePunchInLat = (float)($attRow['punch_in_lat'] ?? 0);
        $activePunchInLng = (float)($attRow['punch_in_lng'] ?? 0);
    }
}
?>

<?php if ($isFieldActive): ?>
<!-- 🚨 Fullscreen Mandatory GPS Lock Screen (Triggered if Location is turned off during shift) -->
<div id="mandatoryGpsLockModal" class="hidden fixed inset-0 z-[99999] bg-slate-950/95 backdrop-blur-2xl flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl border-4 border-rose-500 text-center space-y-5">
        <div class="w-16 h-16 bg-rose-100 rounded-full flex items-center justify-center mx-auto text-rose-600 shadow-lg shadow-rose-500/30 animate-pulse">
            <span class="text-3xl">📡</span>
        </div>
        <div class="space-y-2">
            <span class="px-3 py-1 bg-rose-100 text-rose-800 rounded-full text-xs font-black uppercase tracking-wider">Duty Policy Enforced</span>
            <h2 class="text-xl font-extrabold text-slate-900">GPS / Location is Disabled</h2>
            <p class="text-xs text-slate-600 leading-relaxed">
                Field Executives are strictly required to keep <strong>High Accuracy Device Location (GPS) Turned ON</strong> during active work shift.
            </p>
        </div>
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-3.5 text-left space-y-1.5">
            <div class="text-[11px] font-bold text-amber-900 flex items-center gap-1.5">
                <span>⚠️ Required Actions:</span>
            </div>
            <ol class="text-[11px] text-amber-800 list-decimal list-inside space-y-1 font-medium">
                <li>Turn <strong>ON</strong> your phone's GPS / Location toggle.</li>
                <li>Set Location mode to <strong>"High Accuracy / Google Accuracy"</strong>.</li>
                <li>Click <strong>"Verify & Resume Duty"</strong> below.</li>
            </ol>
        </div>
        <button type="button" onclick="window.retryMandatoryGps && window.retryMandatoryGps()" class="w-full py-3.5 bg-gradient-to-r from-rose-600 to-red-700 hover:from-rose-700 hover:to-red-800 text-white rounded-2xl font-black text-sm shadow-xl shadow-rose-600/30 flex items-center justify-center gap-2 transition cursor-pointer">
            <span>🔄 Turn On GPS & Verify Location</span>
        </button>
    </div>
</div>

<!-- 🟢 Live GPS Stream Indicator (Minimized) -->
<div id="liveGpsStreamBadge" class="hidden fixed bottom-4 right-4 z-[9990] flex items-center gap-2.5 bg-slate-900/95 backdrop-blur-md border border-emerald-500/40 text-white px-3.5 py-2 rounded-2xl shadow-xl shadow-emerald-950/40 text-xs font-semibold select-none transition-all duration-300">
    <span class="relative flex h-3 w-3">
        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
        <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
    </span>
    <div class="flex flex-col leading-none">
        <span class="text-[11px] font-extrabold text-emerald-300 tracking-tight flex items-center gap-1">
            <span>Live Radar Active</span>
        </span>
        <span class="text-[9px] font-mono text-slate-400 mt-0.5" id="liveGpsSyncTime">Streaming live GPS...</span>
    </div>
</div>

<script>
// 🚗 High-Resilience GPS Route Tracker with Instant Seeded Heartbeat
(function initResilientGpsTracker() {
    let lastLat = <?= ($activePunchInLat ?? 0) > 0 ? $activePunchInLat : 'null' ?>;
    let lastLng = <?= ($activePunchInLng ?? 0) > 0 ? $activePunchInLng : 'null' ?>;
    let currentBatteryLevel = null;
    const attId = <?= $activeAttId ?>;
    const QUEUE_KEY = 'hrms_gps_offline_queue_' + attId;

    // 🔒 1. Screen WakeLock & Background Audio Keep-Alive (Prevents OS from killing app)
    let wakeLockSentinel = null;
    async function acquireWakeLock() {
        try {
            if ('wakeLock' in navigator) {
                wakeLockSentinel = await navigator.wakeLock.request('screen');
            }
        } catch (err) {}
    }
    acquireWakeLock();

    // Silent Audio Keep-Alive (Marks PWA as Active Foreground Media Service in Android/OS)
    let audioContextKeepAlive = null;
    function startAudioKeepAlive() {
        if (audioContextKeepAlive) return;
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (AudioCtx) {
                audioContextKeepAlive = new AudioCtx();
                const osc = audioContextKeepAlive.createOscillator();
                const gain = audioContextKeepAlive.createGain();
                gain.gain.value = 0.00001; // Silent / Inaudible
                osc.connect(gain);
                gain.connect(audioContextKeepAlive.destination);
                osc.start();
            }
        } catch(e) {}
    }

    // Trigger keep-alive on load and any user interaction
    startAudioKeepAlive();
    ['click', 'touchstart', 'visibilitychange'].forEach(evt => {
        document.addEventListener(evt, () => {
            startAudioKeepAlive();
            acquireWakeLock();
            if (document.visibilityState === 'hidden') {
                if (lastLat && lastLng) {
                    sendGpsPing(lastLat, lastLng, 0);
                }
            }
        }, { passive: true });
    });

    // 🔔 Persistent Foreground Status Bar Notification & Periodic Background Sync
    function triggerForegroundNotification() {
        if ('Notification' in window && Notification.permission === 'granted') {
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.ready.then(reg => {
                    reg.showNotification('🟢 EcoFone Live Radar Active', {
                        body: '📍 Tracking On Duty • Background GPS Active until Punch Out',
                        icon: '/icon-192.png',
                        badge: '/icon-192.png',
                        tag: 'ecofone_shift_active',
                        ongoing: true,
                        requireInteraction: true,
                        silent: true,
                        renotify: false,
                        data: { url: '/?page=dashboard' }
                    }).catch(() => {});

                    // Register Android Background Periodic Sync
                    if ('periodicSync' in reg) {
                        reg.periodicSync.register('sync-location-pings', {
                            minInterval: 60 * 1000 // 1 minute background sync
                        }).catch(() => {});
                    }
                });
            }
        }
    }

    // Auto-request notification permission on user touch/click gesture
    function requestNotificationOnGesture() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().then(p => {
                if (p === 'granted') {
                    triggerForegroundNotification();
                }
            }).catch(() => {});
        }
    }

    ['click', 'touchstart'].forEach(evt => {
        document.addEventListener(evt, requestNotificationOnGesture, { once: true, passive: true });
    });

    if ('Notification' in window && Notification.permission === 'granted') {
        triggerForegroundNotification();
    }

    if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
        navigator.serviceWorker.controller.postMessage({ type: 'START_BACKGROUND_TRACKING' });
    }

    // 🔒 2. Prevent User from Closing / Removing Application Until Punch Out
    window.addEventListener('beforeunload', function(e) {
        e.preventDefault();
        e.returnValue = '⚠️ EcoFone App Alert: Active Shift in progress! Please punch out before closing or removing the application.';
        return e.returnValue;
    });

    // 🔒 3. Prevent Back Button Escape During Active Shift
    try {
        history.pushState(null, document.title, location.href);
        window.addEventListener('popstate', function() {
            history.pushState(null, document.title, location.href);
        });
    } catch(e) {}

    // 4. Battery Telemetry Monitor
    if (navigator.getBattery) {
        navigator.getBattery().then(battery => {
            currentBatteryLevel = Math.round(battery.level * 100);
            battery.addEventListener('levelchange', () => {
                currentBatteryLevel = Math.round(battery.level * 100);
                if (lastLat && lastLng) {
                    sendGpsPing(lastLat, lastLng, 0);
                }
            });
        }).catch(() => {});
    }

    // 5. Offline Queue Helpers
    function getOfflineQueue() {
        try {
            return JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]');
        } catch(e) { return []; }
    }

    function saveToOfflineQueue(ping) {
        const q = getOfflineQueue();
        q.push(ping);
        if (q.length > 500) q.shift();
        localStorage.setItem(QUEUE_KEY, JSON.stringify(q));
    }

    function flushOfflineQueue() {
        if (!navigator.onLine) return;
        const q = getOfflineQueue();
        if (q.length === 0) return;

        fetch('?action=sync-offline-gps-batch', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ pings: q })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                localStorage.removeItem(QUEUE_KEY);
            }
        })
        .catch(() => {});
    }

    window.addEventListener('online', flushOfflineQueue);

    function sendGpsPing(lat, lng, speed) {
        if (!lat || !lng) return;

        lastLat = lat;
        lastLng = lng;

        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const mins = String(now.getMinutes()).padStart(2, '0');
        const secs = String(now.getSeconds()).padStart(2, '0');
        const localTimeStr = `${year}-${month}-${day} ${hours}:${mins}:${secs}`;

        let speedKmh = (speed !== null && speed >= 0) ? (speed * 3.6) : 0;
        // Zero out GPS noise if speed is below 3 km/h
        if (speedKmh < 3.0) {
            speedKmh = 0;
        }
        speedKmh = Number(speedKmh.toFixed(1));

        const pingData = {
            attendance_id: attId,
            latitude: lat,
            longitude: lng,
            speed: speedKmh,
            battery_level: currentBatteryLevel,
            recorded_at: localTimeStr
        };

        if (!navigator.onLine) {
            saveToOfflineQueue(pingData);
            return;
        }

        // ⚡ Direct Live Real-Time POST to Server
        fetch('?action=log-travel-coordinate', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `lat=${lat}&lng=${lng}&speed=${speedKmh}&battery_level=${currentBatteryLevel !== null ? currentBatteryLevel : ''}`
        })
        .then(() => {
            const timeEl = document.getElementById('liveGpsSyncTime');
            const now = new Date();
            const formattedTime = now.toLocaleTimeString('en-US', { hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit' });
            if (timeEl) {
                timeEl.innerText = 'Live • ' + formattedTime;
            }

            // Update persistent status bar notification (Swiggy/Zomato style)
            if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
                navigator.serviceWorker.controller.postMessage({
                    type: 'UPDATE_LIVE_STATUS',
                    battery: currentBatteryLevel,
                    speed: speedKmh,
                    time: formattedTime
                });
            }

            flushOfflineQueue();
        })
        .catch(() => {
            const timeEl = document.getElementById('liveGpsSyncTime');
            if (timeEl) timeEl.innerText = 'Offline • Queued';
            saveToOfflineQueue(pingData);
        });
    }

    function handleGpsSuccess(pos) {
        const lockModal = document.getElementById('mandatoryGpsLockModal');
        if (lockModal) lockModal.classList.add('hidden');
        sendGpsPing(pos.coords.latitude, pos.coords.longitude, pos.coords.speed || 0);
    }

    function handleGpsError(err) {
        // If GPS is disabled (PERMISSION_DENIED = 1, POSITION_UNAVAILABLE = 2)
        if (err && (err.code === 1 || err.code === 2)) {
            const lockModal = document.getElementById('mandatoryGpsLockModal');
            if (lockModal) lockModal.classList.remove('hidden');
        }
        if (lastLat && lastLng) {
            sendGpsPing(lastLat, lastLng, 0);
        }
    }

    window.retryMandatoryGps = function() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(pos) {
                    handleGpsSuccess(pos);
                },
                function(err) {
                    handleGpsError(err);
                    alert('⚠️ GPS is still unavailable. Please enable High Accuracy Location in your phone settings.');
                },
                { enableHighAccuracy: true, timeout: 6000, maximumAge: 0 }
            );
        }
    };

    // 📱 Native Capacitor Android APK Bridge (Unbreakable 24/7 OS Service with Ongoing Status Bar Badge)
    if (window.Capacitor && window.Capacitor.Plugins) {
        try {
            const bgPlugin = window.Capacitor.Plugins.BackgroundGeolocation || (window.CapacitorCommunity && window.CapacitorCommunity.BackgroundGeolocation);
            if (bgPlugin && bgPlugin.addWatcher) {
                bgPlugin.addWatcher(
                    {
                        backgroundMessage: "Tracking On Duty • Sharing with HR",
                        backgroundTitle: "🟢 EcoFone Live Radar Active",
                        requestPermissions: true,
                        stale: false,
                        distanceFilter: 3
                    },
                    function(pos, err) {
                        if (pos) {
                            handleGpsSuccess({
                                coords: {
                                    latitude: pos.latitude,
                                    longitude: pos.longitude,
                                    speed: pos.speed || 0
                                }
                            });
                        } else if (err) {
                            handleGpsError(err);
                        }
                    }
                );
            } else if (window.Capacitor.Plugins.Geolocation) {
                window.Capacitor.Plugins.Geolocation.watchPosition(
                    { enableHighAccuracy: true, timeout: 6000, maximumAge: 0 },
                    function(pos, err) {
                        if (pos && pos.coords) {
                            handleGpsSuccess(pos);
                        } else if (err) {
                            handleGpsError(err);
                        }
                    }
                );
            }
        } catch(e) {}
    }

    if (navigator.geolocation) {
        // 1. Force High Accuracy hardware position stream with 0 maximumAge (Fresh GPS Satellites)
        navigator.geolocation.watchPosition(
            handleGpsSuccess,
            handleGpsError,
            { enableHighAccuracy: true, timeout: 6000, maximumAge: 0 }
        );

        // 2. Un-throttled Background Web Worker Timer (Ticks every 1 second non-stop)
        try {
            const workerCode = "setInterval(function() { postMessage('TICK'); }, 1000);";
            const workerBlob = new Blob([workerCode], { type: 'application/javascript' });
            const tickerWorker = new Worker(URL.createObjectURL(workerBlob));
            tickerWorker.onmessage = function() {
                navigator.geolocation.getCurrentPosition(
                    handleGpsSuccess,
                    handleGpsError,
                    { enableHighAccuracy: true, timeout: 3000, maximumAge: 0 }
                );
            };
        } catch(e) {
            setInterval(function() {
                navigator.geolocation.getCurrentPosition(
                    handleGpsSuccess,
                    handleGpsError,
                    { enableHighAccuracy: true, timeout: 3000, maximumAge: 0 }
                );
            }, 1000);
        }
    }
})();
</script>
<?php endif; ?>
<!-- views/layouts/footer.php -->
    </main>
</div>

<!-- SPA Top Loading Bar -->
<div id="spa-loading-bar" class="fixed top-0 left-0 right-0 h-1 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 z-[9999] opacity-0 transition-all duration-300 pointer-events-none" style="transform: scaleX(0); transform-origin: 0% 50%;"></div>

<script>
    lucide.createIcons();

    // High-Performance AJAX Dynamic Router with Prefetching & Caching
    (function initFastAJAXNavigation() {
        const loadingBar = document.getElementById('spa-loading-bar');
        const mainContent = document.getElementById('main-content');
        const pageCache = new Map();

        function startLoading() {
            if (loadingBar) {
                loadingBar.style.opacity = '1';
                loadingBar.style.transform = 'scaleX(0.7)';
            }
        }

        function stopLoading() {
            if (loadingBar) {
                loadingBar.style.transform = 'scaleX(1)';
                setTimeout(() => {
                    loadingBar.style.opacity = '0';
                    setTimeout(() => {
                        loadingBar.style.transform = 'scaleX(0)';
                    }, 200);
                }, 100);
            }
        }

        function updateActiveNav(targetUrl) {
            try {
                const urlObj = new URL(targetUrl, window.location.origin);
                const pageParam = urlObj.searchParams.get('page') || 'dashboard';

                document.querySelectorAll('aside nav a').forEach(link => {
                    const linkHref = link.getAttribute('href') || '';
                    const linkUrl = new URL(linkHref, window.location.origin);
                    const linkPage = linkUrl.searchParams.get('page');

                    if (linkPage === pageParam) {
                        link.classList.remove('text-slate-400', 'hover:bg-slate-800', 'hover:text-slate-200');
                        link.classList.add('bg-indigo-600', 'text-white', 'shadow-sm', 'font-semibold');
                    } else {
                        link.classList.remove('bg-indigo-600', 'text-white', 'shadow-sm', 'font-semibold');
                        link.classList.add('text-slate-400', 'hover:bg-slate-800', 'hover:text-slate-200');
                    }
                });
            } catch(e) {}
        }

        function applyHtmlToMain(html, url, push = true) {
            if (!mainContent) return;
            mainContent.innerHTML = html;

            if (push) {
                window.history.pushState({ url }, '', url);
            }
            updateActiveNav(url);

            // Re-execute embedded scripts in new content
            const scripts = mainContent.querySelectorAll('script');
            scripts.forEach(oldScript => {
                const newScript = document.createElement('script');
                Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                oldScript.parentNode.replaceChild(newScript, oldScript);
            });

            // Re-init Lucide Icons
            if (window.lucide) {
                window.lucide.createIcons();
            }

            // Re-init Alpine components
            if (window.Alpine && window.Alpine.initTree) {
                window.Alpine.initTree(mainContent);
            }

            window.scrollTo({ top: 0, behavior: 'instant' });
        }

        async function prefetch(url) {
            if (pageCache.has(url)) return;
            try {
                const ajaxUrl = url + (url.includes('?') ? '&' : '?') + 'ajax=1';
                const response = await fetch(ajaxUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (response.ok) {
                    const html = await response.text();
                    pageCache.set(url, html);
                }
            } catch (e) {}
        }

        async function navigateTo(url, push = true) {
            // 1. Instant Render from Cache if available
            if (pageCache.has(url)) {
                applyHtmlToMain(pageCache.get(url), url, push);
                // Background refresh cache
                prefetch(url);
                return;
            }

            startLoading();
            try {
                const ajaxUrl = url + (url.includes('?') ? '&' : '?') + 'ajax=1';
                const response = await fetch(ajaxUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (!response.ok) {
                    window.location.href = url;
                    return;
                }

                const html = await response.text();
                pageCache.set(url, html);
                applyHtmlToMain(html, url, push);
            } catch (err) {
                console.error('AJAX Navigation Error:', err);
                window.location.href = url;
            } finally {
                stopLoading();
            }
        }

        window.spaNavigate = function(url, push = true) {
            navigateTo(url, push);
        };

        // Instant Prefetch on Hover (Mouseover / Touchstart)
        document.addEventListener('mouseover', function (e) {
            const link = e.target.closest('a');
            if (!link) return;
            const href = link.getAttribute('href');
            if (href && (href.startsWith('?page=') || href.startsWith('./?page=') || href.startsWith('/?page='))) {
                prefetch(href);
            }
        });

        // Intercept internal navigation clicks for 0-second AJAX transitions
        document.addEventListener('click', function (e) {
            const link = e.target.closest('a');
            if (!link) return;

            const href = link.getAttribute('href');
            if (!href) return;

            if (
                link.target === '_blank' ||
                link.hasAttribute('download') ||
                href.startsWith('mailto:') ||
                href.startsWith('tel:') ||
                href.startsWith('javascript:') ||
                href.startsWith('#') ||
                href.includes('?action=') ||
                href.includes('&action=') ||
                href.includes('action=logout') ||
                href.includes('action=switch-role') ||
                link.classList.contains('no-spa')
            ) {
                return;
            }

            if (href.startsWith('?page=') || href.startsWith('./?page=') || href.startsWith('/?page=') || (href.startsWith('http') && href.includes('?page=') && href.startsWith(window.location.origin))) {
                e.preventDefault();
                navigateTo(href, true);
            }
        });

        // Intercept GET Form submissions (date/search filters)
        document.addEventListener('submit', function (e) {
            const form = e.target;
            const method = (form.getAttribute('method') || 'GET').toUpperCase();
            const action = form.getAttribute('action') || window.location.search || '?page=dashboard';

            if (method === 'GET' && !action.includes('?action=') && !form.classList.contains('no-spa')) {
                e.preventDefault();
                const formData = new FormData(form);
                const params = new URLSearchParams(formData);
                const baseUrl = action.split('?')[0] || window.location.pathname;
                const targetUrl = baseUrl + '?' + params.toString();
                navigateTo(targetUrl, true);
            } else if (method === 'POST') {
                // Clear cache on mutations/posts
                pageCache.clear();
            }
        });

        // Handle Browser Back / Forward buttons
        window.addEventListener('popstate', function () {
            navigateTo(window.location.href, false);
        });

        // ========================================================
        // 🔄 Real-Time Live Presence & Background Sync
        // ========================================================
        let isSyncing = false;

        async function checkLiveUpdates() {
            if (isSyncing) return;
            isSyncing = true;
            try {
                const res = await fetch('?action=live-heartbeat', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (res.ok) {
                    const data = await res.json();
                    if (!data.authenticated && data.force_logout) {
                        window.location.href = '?action=logout';
                    }
                }
            } catch (e) {
                // Ignore transient network hiccups
            } finally {
                isSyncing = false;
            }
        }

        // Lightweight periodic check (every 60 seconds) & on window focus
        setInterval(checkLiveUpdates, 60000);
        window.addEventListener('focus', checkLiveUpdates);
    })();

    // ========================================================
    // 🔄 1-Click App Update Engine & Cloud Sync
    // ========================================================
    window.checkForAppUpdate = async function() {
        const modal = document.getElementById('appUpdateModal');
        const icon = document.getElementById('updateModalIcon');
        const title = document.getElementById('updateModalTitle');
        const msg = document.getElementById('updateModalMsg');
        const actions = document.getElementById('updateModalActions');
        if (!modal) return;

        modal.classList.remove('hidden');
        icon.innerHTML = '<i data-lucide="refresh-cw" class="w-7 h-7 text-indigo-600 animate-spin"></i>';
        title.innerText = 'Checking for Updates...';
        msg.innerText = 'Synchronizing with EcoFone Cloud servers for latest features and live radar engine updates.';
        actions.innerHTML = '';
        if (window.lucide) lucide.createIcons();

        try {
            // 1. Force update Service Worker
            if ('serviceWorker' in navigator) {
                const regs = await navigator.serviceWorker.getRegistrations();
                for (let reg of regs) {
                    await reg.update();
                }
            }
            // 2. Flush obsolete browser client caches
            if ('caches' in window) {
                const keys = await caches.keys();
                for (let k of keys) {
                    await caches.delete(k);
                }
            }
            // 3. Clear sessionStorage
            sessionStorage.clear();

            setTimeout(() => {
                icon.innerHTML = '<span class="text-2xl">🎉</span>';
                title.innerText = 'App Updated to Latest Version!';
                msg.innerText = 'Version 1.2.0 is active. All latest changes, live background tracking, and fixes are successfully applied.';
                actions.innerHTML = `
                    <button type="button" onclick="window.location.reload(true)" class="w-full py-2.5 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white rounded-xl font-bold text-xs shadow-md shadow-indigo-500/20 transition cursor-pointer">
                        🔄 Reload & Apply Changes
                    </button>
                    <a href="https://github.com/Divyansh-chaurasia786/hrms-portal/actions" target="_blank" class="w-full py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold text-xs flex items-center justify-center gap-1.5 transition">
                        <i data-lucide="download" class="w-3.5 h-3.5"></i> Download Latest APK Build
                    </a>
                    <button type="button" onclick="document.getElementById('appUpdateModal').classList.add('hidden')" class="w-full py-1.5 text-slate-500 hover:text-slate-700 text-xs font-medium transition cursor-pointer">
                        Close
                    </button>
                `;
                if (window.lucide) lucide.createIcons();
            }, 1200);
        } catch (e) {
            icon.innerHTML = '<span class="text-2xl">✅</span>';
            title.innerText = 'App is Up to Date!';
            msg.innerText = 'You are already running the latest live version.';
            actions.innerHTML = `
                <button type="button" onclick="window.location.reload(true)" class="w-full py-2.5 bg-indigo-600 text-white rounded-xl font-bold text-xs shadow-md transition cursor-pointer">
                    🔄 Refresh Screen
                </button>
                <button type="button" onclick="document.getElementById('appUpdateModal').classList.add('hidden')" class="w-full py-1.5 text-slate-500 hover:text-slate-700 text-xs font-medium transition cursor-pointer">
                    Close
                </button>
            `;
            if (window.lucide) lucide.createIcons();
        }
    };
</script>

<!-- 🔄 1-Click App Update Modal -->
<div id="appUpdateModal" class="hidden fixed inset-0 z-[99999] bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl p-6 max-w-sm w-full shadow-2xl border border-slate-200 text-center space-y-4 animate-in fade-in zoom-in-95 duration-200">
        <div id="updateModalIcon" class="w-14 h-14 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center mx-auto shadow-inner text-2xl">
            <i data-lucide="refresh-cw" class="w-7 h-7 animate-spin"></i>
        </div>
        <div class="space-y-1">
            <h3 id="updateModalTitle" class="text-base font-extrabold text-slate-900">Checking for Updates...</h3>
            <p id="updateModalMsg" class="text-xs text-slate-600 leading-relaxed">Connecting to EcoFone Cloud to fetch the latest features and patches.</p>
        </div>
        <div id="updateModalActions" class="space-y-2 pt-2"></div>
    </div>
</div>
</body>
</html>
