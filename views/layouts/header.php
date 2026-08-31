<!-- views/layouts/header.php -->
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Ecofone HRMS | Workforce & Operations Portal</title>
    <meta name="description" content="Ecofone HRMS - Enterprise Human Resource & Team Operations Management System.">
    <meta name="keywords" content="Ecofone HRMS, Ecofone, HRMS Ecofone, ecofone portal, HRMS portal">
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://unpkg.com" crossorigin>
    <link rel="dns-prefetch" href="https://unpkg.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <!-- Leaflet Maps CSS & JS (Deferred for faster page render) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" media="print" onload="this.media='all'" />
    <script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <!-- PWA Manifest & Mobile Standalone Icons -->
    <link rel="manifest" href="/manifest.json">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Ecofone HRMS">
    <link rel="apple-touch-icon" href="https://ui-avatars.com/api/?name=Ecofone+HRMS&background=4f46e5&color=fff&size=192&rounded=true&bold=true">
    <meta name="theme-color" content="#4f46e5">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            800: '#3730a3',
                            900: '#312e81',
                        }
                    }
                }
            }
        }
    </script>
    <!-- Client-Side Instant Cache Vault -->
    <script src="/js/hrms_offline_cache.js"></script>
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* Strict isolation: Never allow unstyled luckysheet menu spill on non-sheet pages */
        body:not([data-page="admin-smart-sheets"]) .luckysheet-context-menu,
        body:not([data-page="admin-smart-sheets"]) .luckysheet-cols-menu,
        body:not([data-page="admin-smart-sheets"]) .luckysheet-rows-menu,
        body:not([data-page="admin-smart-sheets"]) .luckysheet-filter-menu,
        body:not([data-page="admin-smart-sheets"]) .luckysheet-sheet-magicMenu,
        body:not([data-page="admin-smart-sheets"]) .luckysheet-menuButton,
        body:not([data-page="admin-smart-sheets"]) .luckysheet-menulist,
        body:not([data-page="admin-smart-sheets"]) .luckysheet-modal-dialog {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            pointer-events: none !important;
            height: 0 !important;
            width: 0 !important;
            overflow: hidden !important;
        }
        /* Hide scrollbars completely while keeping scrollability */
        .no-scrollbar::-webkit-scrollbar,
        aside::-webkit-scrollbar,
        nav::-webkit-scrollbar {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
        }
        .no-scrollbar,
        aside,
        nav {
            -ms-overflow-style: none !important;
            scrollbar-width: none !important;
        }
    </style>
                            <!-- 📲 PWA Installer Engine — NO auto popup, only on button click -->
    <script>
    // Store deferred install prompt silently — never auto-fire
    window.pwaInstallPrompt = null;

    // Register service worker silently
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('/sw.js').catch(function() {});
        });
    }

    // Suppress browser's own install popup — store for later manual use only
    window.addEventListener('beforeinstallprompt', function(e) {
        e.preventDefault(); // ← Prevents ANY automatic browser popup
        window.pwaInstallPrompt = e;
        // Subtle glow on install button to signal availability — no popup
        var btn = document.getElementById('installAppNavbarBtn');
        if (btn) btn.classList.add('ring-2', 'ring-indigo-400');
    });

    // Called ONLY when user explicitly clicks "Install App" button
    function triggerPwaInstall() {
        if (window.pwaInstallPrompt) {
            // Use native browser install prompt (already deferred above)
            window.pwaInstallPrompt.prompt();
            window.pwaInstallPrompt.userChoice.then(function(choice) {
                if (choice.outcome === 'accepted') {
                    window.pwaInstallPrompt = null;
                    var btn = document.getElementById('installAppNavbarBtn');
                    if (btn) btn.classList.remove('ring-2', 'ring-indigo-400');
                }
            });
        } else {
            // Fallback: show manual install guide modal
            var modal = document.getElementById('pwaInstallModal');
            if (modal) modal.classList.remove('hidden');
        }
    }

    function closePwaInstallModal() {
        var modal = document.getElementById('pwaInstallModal');
        if (modal) modal.classList.add('hidden');
    }

    function downloadDesktopLauncher() {
        var shortcutLines = [
            '[InternetShortcut]',
            'URL=https://hrms-ecovista.vercel.app/?source=desktop_shortcut',
            'IconFile=https://hrms-ecovista.vercel.app/icon-192.png',
            'IconIndex=0',
            'HotKey=0',
            'IDList=',
            '[{000214A0-0000-0000-C000-000000000046}]',
            'Prop3=19,2'
        ].join('\r\n');
        var blob = new Blob([shortcutLines], { type: 'application/octet-stream' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'Ecofone_HRMS.url';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(a.href);
    }

    // Safety guard: ensure modal is always hidden on page load
    document.addEventListener('DOMContentLoaded', function() {
        var modal = document.getElementById('pwaInstallModal');
        if (modal) modal.classList.add('hidden');
    });
    </script>
    <!-- 📍 GPS Live Tracker — Offline Queue, Auto Sync, Auto Punch-Out on GPS Off -->
    <script>
    (function() {
        var GPS = {
            watchId: null,
            interval: null,
            pingIntervalMs: 5 * 60 * 1000,  // Live ping every 5 minutes
            offlineQueueKey: 'eco_gps_queue',
            grantedKey: 'eco_gps_granted',
            shiftActive: document.body ? document.body.getAttribute('data-shift-active') === '1' : false,
            consecutiveErrors: 0,
            maxConsecutiveErrors: 3,         // After 3 GPS errors → auto punch-out
            stopped: false,

            // ── Offline Queue ──────────────────────────────────────────────
            enqueue: function(payload) {
                var q = [];
                try { q = JSON.parse(localStorage.getItem(this.offlineQueueKey) || '[]'); } catch(e) {}
                q.push(payload);
                // Keep last 100 pings max
                if (q.length > 100) q = q.slice(-100);
                try { localStorage.setItem(this.offlineQueueKey, JSON.stringify(q)); } catch(e) {}
            },

            flushQueue: function() {
                var q = [];
                try { q = JSON.parse(localStorage.getItem(this.offlineQueueKey) || '[]'); } catch(e) {}
                if (q.length === 0) return;
                var self = this;
                var toSend = q.slice();
                try { localStorage.setItem(self.offlineQueueKey, '[]'); } catch(e) {}
                toSend.forEach(function(p) {
                    fetch('/api/track-location.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(p)
                    }).catch(function() {
                        self.enqueue(p); // Re-queue if still failing
                    });
                });
            },

            // ── Send One Ping ──────────────────────────────────────────────
            sendPing: function(pos, type) {
                var payload = {
                    lat:      pos.coords.latitude,
                    lng:      pos.coords.longitude,
                    accuracy: pos.coords.accuracy,
                    type:     type || 'auto_ping',
                    device:   navigator.userAgent.substring(0, 200),
                    address:  '',
                    ts:       new Date().toISOString()
                };
                try { localStorage.setItem(this.grantedKey, '1'); } catch(e) {}
                this.consecutiveErrors = 0;

                if (!navigator.onLine) {
                    this.enqueue(payload);
                    return;
                }

                fetch('/api/track-location.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                }).catch(function() {
                    // Offline while sending — queue it
                    GPS.enqueue(payload);
                });
            },

            // ── GPS Error Handler ──────────────────────────────────────────
            onGpsError: function(err) {
                // PERMISSION_DENIED (1) OR POSITION_UNAVAILABLE (2) = GPS turned off
                if (err.code === 1) {
                    try { localStorage.setItem(this.grantedKey, 'denied'); } catch(e) {}
                }
                if (err.code === 1 || err.code === 2) {
                    this.consecutiveErrors++;
                    if (this.consecutiveErrors >= this.maxConsecutiveErrors && this.shiftActive) {
                        this.triggerAutoPunchOut();
                    }
                }
            },

            // ── Auto Punch-Out ─────────────────────────────────────────────
            triggerAutoPunchOut: function() {
                if (!this.shiftActive) return;
                this.stop();
                fetch('/api/auto-punchout.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ reason: 'gps_disabled' })
                }).then(function(r) { return r.json(); }).then(function(res) {
                    if (res.success) {
                        GPS.shiftActive = false;
                        // Show a subtle notification
                        var banner = document.createElement('div');
                        banner.innerHTML = '📍 <strong>Auto Punch-Out:</strong> GPS location was disabled. Your shift has been automatically ended. Total: <strong>' + (res.hours || 0) + ' hrs</strong>';
                        banner.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:9999;background:#dc2626;color:#fff;text-align:center;padding:10px 16px;font-size:13px;font-weight:600;';
                        document.body.prepend(banner);
                        setTimeout(function() { window.location.reload(); }, 4000);
                    }
                }).catch(function() {});
            },

            // ── Stop All Tracking ──────────────────────────────────────────
            stop: function() {
                this.stopped = true;
                if (this.watchId !== null) {
                    navigator.geolocation.clearWatch(this.watchId);
                    this.watchId = null;
                }
                if (this.interval) {
                    clearInterval(this.interval);
                    this.interval = null;
                }
            },

            // ── Start Live watchPosition ───────────────────────────────────
            startWatch: function() {
                var self = this;
                if (!navigator.geolocation || this.stopped) return;
                if (this.watchId !== null) navigator.geolocation.clearWatch(this.watchId);

                this.watchId = navigator.geolocation.watchPosition(
                    function(pos) { self.sendPing(pos, 'auto_ping'); },
                    function(err) { self.onGpsError(err); },
                    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
                );

                // Throttle to send max 1 ping per pingIntervalMs using interval gate
                if (this.interval) clearInterval(this.interval);
                var lastSentTs = 0;
                var origSend = this.sendPing.bind(this);
                this.sendPing = function(pos, type) {
                    var now = Date.now();
                    if (now - lastSentTs < self.pingIntervalMs && type !== 'clock_in' && type !== 'clock_out') return;
                    lastSentTs = now;
                    origSend(pos, type);
                };
            },

            // ── Init ───────────────────────────────────────────────────────
            init: function() {
                if (!navigator.geolocation) return;
                var self = this;
                var storedState = '';
                try { storedState = localStorage.getItem(this.grantedKey) || ''; } catch(e) {}

                if (storedState === 'denied') return;

                var doStart = function() {
                    self.startWatch();
                    // Flush offline queue on init
                    if (navigator.onLine) self.flushQueue();
                };

                if (storedState === '1') {
                    doStart();
                    return;
                }

                // First time — check Permissions API
                if (navigator.permissions && navigator.permissions.query) {
                    navigator.permissions.query({ name: 'geolocation' }).then(function(result) {
                        if (result.state === 'granted') {
                            try { localStorage.setItem(self.grantedKey, '1'); } catch(e) {}
                            doStart();
                        } else if (result.state === 'prompt') {
                            doStart(); // Triggers ONE-TIME browser popup
                        }
                        // denied — do nothing
                    }).catch(function() { doStart(); });
                } else {
                    doStart();
                }
            }
        };

        // ── Global: Called from punch-out buttons to stop tracking ──────────
        window.ecoStopGpsTracking = function() { GPS.stop(); };

        // ── Online: flush queued offline pings ────────────────────────────
        window.addEventListener('online', function() { GPS.flushQueue(); });

        // ── Tab visible: re-check ─────────────────────────────────────────
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden && navigator.onLine) GPS.flushQueue();
        });

        // ── Start on DOM ready ────────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', function() {
            GPS.shiftActive = document.body.getAttribute('data-shift-active') === '1';

            var storedState = '';
            try { storedState = localStorage.getItem(GPS.grantedKey) || ''; } catch(e) {}

            // Detect punch-in redirect (loc_start=1 in URL)
            var urlParams = new URLSearchParams(window.location.search);
            var justPunchedIn = urlParams.has('loc_start');

            // Clean the loc_start param from URL without page reload
            if (justPunchedIn && window.history && window.history.replaceState) {
                urlParams.delete('loc_start');
                var cleanUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                window.history.replaceState({}, '', cleanUrl);
            }

            // If permission already granted and user just punched in — silent immediate clock_in ping
            if (justPunchedIn && storedState === '1' && navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(pos) { GPS.sendPing(pos, 'clock_in'); },
                    function() {},
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                );
            }

            // Start background watch tracking (silent if already granted)
            GPS.init();
        });

        window._ecoGPS = GPS;
    })();
    </script>
</head>
<body class="h-full antialiased text-slate-800 flex" data-page="<?= htmlspecialchars($_GET['page'] ?? 'dashboard') ?>" data-shift-active="<?= isInActiveShift() ? '1' : '0' ?>" x-data="{ sidebarOpen: false, sidebarCollapsed: false }">

<script>
function handlePunchOutGeo(form) {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                document.getElementById('punchOutLat').value = pos.coords.latitude;
                document.getElementById('punchOutLng').value = pos.coords.longitude;
                form.submit();
            },
            function(err) {
                form.submit();
            },
            { timeout: 4000, enableHighAccuracy: true }
        );
    } else {
        form.submit();
    }
}
</script>