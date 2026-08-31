    </main>
</div>

<?php
$currentUser = authUser();
$isFieldActive = false;
$activeAttId = 0;
if ($currentUser && (($currentUser['work_mode'] ?? '') === 'field' || stripos($currentUser['department_name'] ?? '', 'Field') !== false || stripos($currentUser['designation'] ?? '', 'Field') !== false)) {
    $dbTracker = getDBConnection();
    $todayDate = date('Y-m-d');
    $attRow = $dbTracker->query("SELECT id FROM attendance WHERE user_id = {$currentUser['id']} AND date = '{$todayDate}' AND clock_in IS NOT NULL AND clock_out IS NULL")->fetch();
    if ($attRow) {
        $isFieldActive = true;
        $activeAttId = (int)$attRow['id'];
    }
}
?>

<?php if ($isFieldActive): ?>
<script>
// 🚗 High-Resilience GPS Route Tracker with Offline Caching & Battery Telemetry
(function initResilientGpsTracker() {
    let lastLat = null;
    let lastLng = null;
    let currentBatteryLevel = null;
    const attId = <?= $activeAttId ?>;
    const QUEUE_KEY = 'hrms_gps_offline_queue_' + attId;

    // 1. Battery Telemetry Monitor
    if (navigator.getBattery) {
        navigator.getBattery().then(battery => {
            currentBatteryLevel = Math.round(battery.level * 100);
            battery.addEventListener('levelchange', () => {
                currentBatteryLevel = Math.round(battery.level * 100);
                // If battery drops below 5%, send emergency shutdown ping
                if (currentBatteryLevel <= 5 && lastLat && lastLng) {
                    sendGpsPing(lastLat, lastLng, 0, true);
                }
            });
        }).catch(() => {});
    }

    // 2. Offline Queue Helpers
    function getOfflineQueue() {
        try {
            return JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]');
        } catch(e) { return []; }
    }

    function saveToOfflineQueue(ping) {
        const q = getOfflineQueue();
        q.push(ping);
        // Keep max 500 pings to prevent memory overflow
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
                console.log(`✅ Flushed ${data.synced_count} offline GPS pings to server!`);
            }
        })
        .catch(() => {});
    }

    // Flush automatically when network comes back online
    window.addEventListener('online', flushOfflineQueue);

    function sendGpsPing(lat, lng, speed, isEmergency = false) {
        if (!lat || !lng) return;

        if (lastLat !== null && lastLng !== null && !isEmergency) {
            const dist = Math.sqrt(Math.pow(lat - lastLat, 2) + Math.pow(lng - lastLng, 2)) * 111000;
            if (dist < 8) return; // Ignore under 8 meters movement
        }

        lastLat = lat;
        lastLng = lng;

        const pingData = {
            attendance_id: attId,
            latitude: lat,
            longitude: lng,
            speed: speed ? (speed * 3.6).toFixed(1) : 0,
            battery_level: currentBatteryLevel,
            recorded_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
        };

        if (!navigator.onLine) {
            // Save to offline queue
            saveToOfflineQueue(pingData);
            return;
        }

        const fd = new FormData();
        fd.append('attendance_id', attId);
        fd.append('latitude', lat);
        fd.append('longitude', lng);
        fd.append('speed', pingData.speed);
        if (currentBatteryLevel !== null) fd.append('battery_level', currentBatteryLevel);
        fd.append('recorded_at', pingData.recorded_at);

        fetch('?action=record-travel-gps', {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(() => {
            // Also flush any previous offline queue
            flushOfflineQueue();
        })
        .catch(err => {
            saveToOfflineQueue(pingData);
        });
    }

    if (navigator.geolocation) {
        navigator.geolocation.watchPosition(
            function(pos) {
                sendGpsPing(pos.coords.latitude, pos.coords.longitude, pos.coords.speed || 0);
            },
            function(err) { console.log('Location watch error:', err); },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 5000 }
        );

        setInterval(function() {
            navigator.geolocation.getCurrentPosition(
                function(pos) {
                    sendGpsPing(pos.coords.latitude, pos.coords.longitude, pos.coords.speed || 0);
                },
                function(err) {},
                { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 }
            );
        }, 25000);
    }
})();
</script>
<?php endif; ?>
<?php
// Check if current user has an active shift right now
$hasActiveShift = false;
$activeShiftUserId = 0;
if ($currentUser) {
    $dbShift = getDBConnection();
    $todayDate = date('Y-m-d');
    $shiftAtt = $dbShift->query("SELECT id FROM attendance WHERE user_id = {$currentUser['id']} AND date = '{$todayDate}' AND clock_in IS NOT NULL AND clock_out IS NULL")->fetch();
    if ($shiftAtt) {
        $hasActiveShift = true;
        $activeShiftUserId = $currentUser['id'];
    }
}
?>

<?php if ($hasActiveShift): ?>
<!-- 🚨 REAL-TIME GPS / LOCATION INTEGRITY WATCHDOG -->
<div id="gps-security-modal" class="fixed inset-0 z-[99999] bg-slate-950/90 backdrop-blur-md flex items-center justify-center p-4 hidden">
    <div class="bg-slate-900 border-2 border-rose-500/50 rounded-3xl p-6 sm:p-8 max-w-md w-full text-center space-y-4 shadow-2xl shadow-rose-950/80 animate-bounce">
        <div class="w-16 h-16 rounded-2xl bg-rose-500/20 text-rose-400 flex items-center justify-center mx-auto text-2xl font-bold border border-rose-500/40">
            <i data-lucide="map-pin-off" class="w-8 h-8"></i>
        </div>
        <div class="space-y-1.5">
            <h3 class="text-lg font-black text-white uppercase tracking-wider">GPS / Location Disabled</h3>
            <p class="text-xs text-rose-300 font-medium leading-relaxed">
                Corporate security violation detected. Location services were turned off during your active work shift.
            </p>
        </div>
        <div class="p-3 bg-rose-950/40 rounded-xl border border-rose-800/40 text-[11px] text-slate-300 font-mono">
            Auto Shift-Out & Session Termination in progress...
        </div>
    </div>
</div>

<script>
(function initGpsIntegrityWatchdog() {
    let isTerminating = false;

    function triggerSecurityLogout(reason) {
        if (isTerminating) return;
        isTerminating = true;

        const modal = document.getElementById('gps-security-modal');
        if (modal) {
            modal.classList.remove('hidden');
            if (window.lucide) lucide.createIcons();
        }

        setTimeout(() => {
            fetch('?action=location-disabled-logout', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(() => {
                window.location.href = '?page=login';
            })
            .catch(() => {
                window.location.href = '?action=location-disabled-logout';
            });
        }, 1200);
    }

    if (!navigator.geolocation) {
        triggerSecurityLogout('Geolocation unsupported');
        return;
    }

    // 1. Monitor real-time position watcher errors
    navigator.geolocation.watchPosition(
        function(pos) {
            // Position received OK
        },
        function(err) {
            // Error Code 1 = PERMISSION_DENIED (User turned off permission)
            // Error Code 2 = POSITION_UNAVAILABLE (Device GPS toggled off)
            if (err.code === 1 || err.code === 2) {
                console.warn('GPS Security Alert: Location disabled (Code ' + err.code + ')');
                triggerSecurityLogout('Location turned off or permission denied');
            }
        },
        { enableHighAccuracy: true, timeout: 12000, maximumAge: 5000 }
    );

    // 2. Active recurring heartbeat check every 15 seconds
    setInterval(function() {
        if (isTerminating) return;

        navigator.geolocation.getCurrentPosition(
            function(pos) {
                // GPS active OK
            },
            function(err) {
                if (err.code === 1 || err.code === 2) {
                    triggerSecurityLogout('Device GPS disabled');
                }
            },
            { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 }
        );
    }, 15000);
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

    // 3. Background Shift Lock: Alert ONLY if user actually closes browser/tab, NEVER on form submit
    let isFormSubmitting = false;
    document.addEventListener('submit', () => {
        isFormSubmitting = true;
    }, true);

    window.addEventListener('beforeunload', (e) => {
        if (isFormSubmitting) return; // Allow form submit without popup!
        const isShiftActive = document.body.dataset.shiftActive === '1';
        if (isShiftActive) {
            e.preventDefault();
            e.returnValue = '⚠️ You have an active duty shift running. If you wish to finish your shift, please click [Punch Out] first.';
        }
    });
</script>
</body>
</html>
