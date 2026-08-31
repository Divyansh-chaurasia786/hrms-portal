    </main>
</div>

<?php
$currentUser = authUser();
$isFieldActive = false;
$activeAttId = 0;
if ($currentUser) {
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
// 🚗 High-Resilience GPS Route Tracker with Unthrottled WebWorker Heartbeat
(function initResilientGpsTracker() {
    let lastLat = null;
    let lastLng = null;
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
        }, { passive: true });
    });

    // Notify Service Worker for Persistent Notification in Status Bar
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

        const speedKmh = (speed !== null && speed >= 0) ? (speed * 3.6).toFixed(1) : 0;
        const pingData = {
            attendance_id: attId,
            latitude: lat,
            longitude: lng,
            speed: speedKmh,
            battery_level: currentBatteryLevel,
            recorded_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
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
            flushOfflineQueue();
        })
        .catch(() => {
            saveToOfflineQueue(pingData);
        });
    }

    if (navigator.geolocation) {
        // Immediate movement watcher
        navigator.geolocation.watchPosition(
            function(pos) {
                sendGpsPing(pos.coords.latitude, pos.coords.longitude, pos.coords.speed || 0);
            },
            function() {},
            { enableHighAccuracy: true, timeout: 8000, maximumAge: 2000 }
        );

        // ⚡ Un-throttled Background Web Worker Timer (Ticks every 3 seconds non-stop)
        try {
            const workerCode = "setInterval(function() { postMessage('TICK'); }, 3000);";
            const workerBlob = new Blob([workerCode], { type: 'application/javascript' });
            const tickerWorker = new Worker(URL.createObjectURL(workerBlob));
            tickerWorker.onmessage = function() {
                navigator.geolocation.getCurrentPosition(
                    function(pos) {
                        sendGpsPing(pos.coords.latitude, pos.coords.longitude, pos.coords.speed || 0);
                    },
                    function() {
                        // Fallback: If hardware lock is busy, ping last known position so radar remains live
                        if (lastLat && lastLng) {
                            sendGpsPing(lastLat, lastLng, 0);
                        }
                    },
                    { enableHighAccuracy: true, timeout: 3500, maximumAge: 2000 }
                );
            };
        } catch(e) {
            setInterval(function() {
                navigator.geolocation.getCurrentPosition(
                    function(pos) {
                        sendGpsPing(pos.coords.latitude, pos.coords.longitude, pos.coords.speed || 0);
                    },
                    function() {
                        if (lastLat && lastLng) sendGpsPing(lastLat, lastLng, 0);
                    },
                    { enableHighAccuracy: true, timeout: 3500, maximumAge: 2000 }
                );
            }, 3000);
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
</script>
</body>
</html>
