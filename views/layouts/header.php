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
    <!-- 📍 ENTERPRISE 24/7 BACKGROUND GPS ENGINE (SW Persistent Keep-Alive, Screen-Lock & Closed-App Proof, Auto-Sync) -->
    <script>
    (function() {
        var GPS = {
            watchId:            null,
            timerId:            null,
            worker:             null,
            wakeLock:           null,
            audioKeepAlive:     null,
            pingIntervalMs:     4000,       // 4s live precision tracking
            minIntervalMs:      2000,       // 2s minimum throttle gate
            offlineQueueKey:    'eco_gps_queue',
            grantedKey:         'eco_gps_granted',
            shiftActive:        false,
            attendanceId:       0,
            consecutiveErrors:  0,
            maxConsecutiveErrors: 3,
            stopped:            false,
            lastPingTs:         0,

            // Local Date String YYYY-MM-DD HH:MM:SS in client's local timezone
            getLocalIsoString: function() {
                var d = new Date();
                var pad = function(n) { return n < 10 ? '0' + n : '' + n; };
                return d.getFullYear() + '-' +
                       pad(d.getMonth() + 1) + '-' +
                       pad(d.getDate()) + ' ' +
                       pad(d.getHours()) + ':' +
                       pad(d.getMinutes()) + ':' +
                       pad(d.getSeconds());
            },

            // Notify Service Worker to Show Sticky Background Notification (Prevents Android OS Kill)
            notifyServiceWorker: function(isActive) {
                if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
                    navigator.serviceWorker.controller.postMessage({
                        type: isActive ? 'START_BACKGROUND_TRACKING' : 'STOP_BACKGROUND_TRACKING'
                    });
                }
                // Also request notification permission silently if not prompted
                if (isActive && 'Notification' in window && Notification.permission === 'default') {
                    try { Notification.requestPermission(); } catch(e) {}
                }
                // Register Periodic Sync if available
                if ('serviceWorker' in navigator && 'periodicSync' in navigator.serviceWorker.ready) {
                    navigator.serviceWorker.ready.then(function(registration) {
                        try {
                            registration.periodicSync.register('sync-location-pings', {
                                minInterval: 5 * 60 * 1000 // 5 minutes
                            });
                        } catch(e) {}
                    });
                }
            },

            // Keep-Alive Background Thread (Prevents Mobile OS from Freezing GPS on Lock Screen / WhatsApp)
            startBackgroundKeepAlive: function() {
                var self = this;
                this.notifyServiceWorker(true);

                // 1. Silent Audio Keep-Alive
                try {
                    if (!this.audioKeepAlive) {
                        var ctx = new (window.AudioContext || window.webkitAudioContext)();
                        var osc = ctx.createOscillator();
                        var gain = ctx.createGain();
                        gain.gain.value = 0.001; // Inaudible
                        osc.connect(gain);
                        gain.connect(ctx.destination);
                        osc.start();
                        this.audioKeepAlive = { ctx: ctx, osc: osc };
                    }
                } catch(e) {}

                // 2. Wake Lock API
                if ('wakeLock' in navigator) {
                    try {
                        navigator.wakeLock.request('screen').then(function(lock) {
                            self.wakeLock = lock;
                        }).catch(function() {});
                    } catch(e) {}
                }

                // 3. Dedicated Web Worker Timer (Independent of tab visibility)
                try {
                    if (!this.worker) {
                        var workerBlob = new Blob([
                            "var t=null; self.onmessage=function(e){ if(e.data==='start'){ if(t)clearInterval(t); t=setInterval(function(){ self.postMessage('tick'); }, 4000); }else if(e.data==='stop'){ if(t){ clearInterval(t); t=null; } } };"
                        ], { type: 'application/javascript' });
                        this.worker = new Worker(URL.createObjectURL(workerBlob));
                        this.worker.onmessage = function() {
                            if (self.shiftActive && !self.stopped && navigator.geolocation) {
                                navigator.geolocation.getCurrentPosition(
                                    function(pos) { self.sendPing(pos, 'auto_ping'); },
                                    function(err) { self.onGpsError(err); },
                                    { enableHighAccuracy: true, timeout: 5000, maximumAge: 2000 }
                                );
                            }
                        };
                        this.worker.postMessage('start');
                    }
                } catch(e) {}
            },

            stopBackgroundKeepAlive: function() {
                this.notifyServiceWorker(false);
                if (this.audioKeepAlive) {
                    try {
                        this.audioKeepAlive.osc.stop();
                        this.audioKeepAlive.ctx.close();
                    } catch(e) {}
                    this.audioKeepAlive = null;
                }
                if (this.wakeLock) {
                    try { this.wakeLock.release(); } catch(e) {}
                    this.wakeLock = null;
                }
                if (this.worker) {
                    try {
                        this.worker.postMessage('stop');
                        this.worker.terminate();
                    } catch(e) {}
                    this.worker = null;
                }
            },

            // Offline Queue Enqueue
            enqueue: function(p) {
                var q = [];
                try { q = JSON.parse(localStorage.getItem(this.offlineQueueKey) || '[]'); } catch(e) {}
                q.push(p);
                if (q.length > 1000) q = q.slice(-1000);
                try { localStorage.setItem(this.offlineQueueKey, JSON.stringify(q)); } catch(e) {}
            },

            // Offline Queue Flush
            flushQueue: function() {
                var self = this;
                var q = [];
                try { q = JSON.parse(localStorage.getItem(this.offlineQueueKey) || '[]'); } catch(e) {}
                if (!q.length) return;
                try { localStorage.setItem(this.offlineQueueKey, '[]'); } catch(e) {}
                fetch('?action=sync-offline-gps-batch', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ pings: q })
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (!data || !data.success) {
                        q.forEach(function(p) { self.enqueue(p); });
                    }
                })
                .catch(function() {
                    q.forEach(function(p) { self.enqueue(p); });
                });
            },

            // Send Location Ping
            sendPing: function(pos, type) {
                if (this.stopped) return;
                var now = Date.now();
                var isExplicit = (type === 'clock_in' || type === 'clock_out' || type === 'manual');
                if (!isExplicit && (now - this.lastPingTs < this.minIntervalMs)) return;
                this.lastPingTs = now;

                var lat         = pos.coords.latitude;
                var lng         = pos.coords.longitude;
                var accuracy    = pos.coords.accuracy || 10;
                var speedMs     = pos.coords.speed || 0;
                var speedKmh    = Math.round(speedMs * 3.6 * 10) / 10;
                var recordedAt  = this.getLocalIsoString();

                try { localStorage.setItem(this.grantedKey, '1'); } catch(e) {}
                this.consecutiveErrors = 0;

                var self = this;
                var doSend = function(battery) {
                    var payload = {
                        attendance_id: self.attendanceId,
                        latitude: lat,
                        longitude: lng,
                        accuracy: accuracy,
                        speed: speedKmh,
                        battery_level: battery,
                        ping_type: type || 'auto_ping',
                        recorded_at: recordedAt,
                        distance_meters: 0,
                        is_offline: 0
                    };

                    if (!navigator.onLine) {
                        payload.is_offline = 1;
                        self.enqueue(payload);
                        return;
                    }

                    var fd = new FormData();
                    fd.append('attendance_id', self.attendanceId);
                    fd.append('latitude', lat);
                    fd.append('longitude', lng);
                    fd.append('accuracy', accuracy);
                    fd.append('speed', speedKmh);
                    fd.append('battery_level', (battery !== null && battery !== undefined) ? battery : '');
                    fd.append('ping_type', type || 'auto_ping');
                    fd.append('recorded_at', recordedAt);

                    fetch('?action=record-travel-gps', { method: 'POST', body: fd })
                        .then(function(r) { return r.json(); })
                        .then(function(res) {
                            if (res && res.attendance_id) {
                                self.attendanceId = res.attendance_id;
                            }
                            if (self.offlineQueueKey && navigator.onLine) {
                                self.flushQueue();
                            }
                        })
                        .catch(function() {
                            payload.is_offline = 1;
                            self.enqueue(payload);
                        });
                };

                if (navigator.getBattery) {
                    navigator.getBattery().then(function(b) {
                        doSend(Math.round(b.level * 100));
                    }).catch(function() { doSend(null); });
                } else {
                    doSend(null);
                }
            },

            onGpsError: function(err) {
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
                        var b = document.createElement('div');
                        b.innerHTML = '📍 <strong>Auto Punch-Out:</strong> GPS was turned off. Shift auto-ended. Total: <strong>' + (res.hours || 0) + ' hrs</strong>';
                        b.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:9999;background:#dc2626;color:#fff;text-align:center;padding:10px 16px;font-size:13px;font-weight:600;';
                        document.body.prepend(b);
                        setTimeout(function() { window.location.reload(); }, 3500);
                    }
                }).catch(function() {});
            },

            stop: function() {
                this.stopped = true;
                this.stopBackgroundKeepAlive();
                if (this.watchId !== null) {
                    try { navigator.geolocation.clearWatch(this.watchId); } catch(e) {}
                    this.watchId = null;
                }
                if (this.timerId !== null) {
                    clearInterval(this.timerId);
                    this.timerId = null;
                }
            },

            startTracking: function() {
                var self = this;
                if (!navigator.geolocation) return;
                this.stopped = false;
                this.shiftActive = true;

                // 1. Start Background Keep-Alive (Audio + Web Worker + WakeLock + ServiceWorker Sticky Notification)
                this.startBackgroundKeepAlive();

                // 2. Immediate first ping (0s delay)
                navigator.geolocation.getCurrentPosition(
                    function(pos) { self.sendPing(pos, 'auto_ping'); },
                    function(err) { self.onGpsError(err); },
                    { enableHighAccuracy: true, timeout: 6000, maximumAge: 0 }
                );

                // 3. Hardware watchPosition stream for active movement
                if (this.watchId !== null) try { navigator.geolocation.clearWatch(this.watchId); } catch(e) {}
                this.watchId = navigator.geolocation.watchPosition(
                    function(pos) { self.sendPing(pos, 'auto_ping'); },
                    function(err) { self.onGpsError(err); },
                    { enableHighAccuracy: true, timeout: 8000, maximumAge: 1000 }
                );

                // 4. Foreground Heartbeat timer (every 4s)
                if (this.timerId !== null) clearInterval(this.timerId);
                this.timerId = setInterval(function() {
                    if (self.stopped || !self.shiftActive) return;
                    navigator.geolocation.getCurrentPosition(
                        function(pos) { self.sendPing(pos, 'auto_ping'); },
                        function(err) { self.onGpsError(err); },
                        { enableHighAccuracy: true, timeout: 4000, maximumAge: 2000 }
                    );
                }, this.pingIntervalMs);

                // 5. Flush offline backlog
                if (navigator.onLine) this.flushQueue();
            },

            init: function() {
                if (!navigator.geolocation) return;
                var self = this;
                var st = '';
                try { st = localStorage.getItem(this.grantedKey) || ''; } catch(e) {}
                if (st === 'denied') return;

                if (st === '1') {
                    self.startTracking();
                    return;
                }

                if (navigator.permissions && navigator.permissions.query) {
                    navigator.permissions.query({ name: 'geolocation' }).then(function(r) {
                        if (r.state === 'granted') {
                            try { localStorage.setItem(self.grantedKey, '1'); } catch(e) {}
                            self.startTracking();
                        } else if (r.state === 'prompt') {
                            self.startTracking();
                        }
                    }).catch(function() { self.startTracking(); });
                } else {
                    self.startTracking();
                }
            }
        };

        window.ecoStopGpsTracking = function() { GPS.stop(); };
        window.ecoStartGpsTracking = function() { GPS.startTracking(); };
        window.addEventListener('online', function() { GPS.flushQueue(); });

        // Auto-resume and flush whenever tab visibility changes or device wakes up
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden && GPS.shiftActive && !GPS.stopped) {
                GPS.startTracking();
                if (navigator.onLine) GPS.flushQueue();
            }
        });
        window.addEventListener('focus', function() {
            if (GPS.shiftActive && !GPS.stopped) {
                GPS.startTracking();
                if (navigator.onLine) GPS.flushQueue();
            }
        });

        // SendBeacon on page swipe/close so final coordinates are never lost
        window.addEventListener('pagehide', function() {
            if (GPS.shiftActive && !GPS.stopped && navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(pos) {
                    var fd = new FormData();
                    fd.append('attendance_id', GPS.attendanceId);
                    fd.append('latitude', pos.coords.latitude);
                    fd.append('longitude', pos.coords.longitude);
                    fd.append('speed', 0);
                    fd.append('recorded_at', GPS.getLocalIsoString());
                    if (navigator.sendBeacon) {
                        navigator.sendBeacon('?action=record-travel-gps', fd);
                    }
                }, function() {}, { timeout: 1000, maximumAge: 5000 });
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            GPS.shiftActive    = document.body.getAttribute('data-shift-active') === '1';
            GPS.attendanceId   = parseInt(document.body.getAttribute('data-attendance-id') || '0', 10);

            var st = '';
            try { st = localStorage.getItem(GPS.grantedKey) || ''; } catch(e) {}

            // Clean loc_start from URL
            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('loc_start')) {
                if (window.history && window.history.replaceState) {
                    urlParams.delete('loc_start');
                    window.history.replaceState({}, '', window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : ''));
                }
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        function(pos) { GPS.sendPing(pos, 'clock_in'); },
                        function() {},
                        { enableHighAccuracy: true, timeout: 6000, maximumAge: 0 }
                    );
                }
            }

            // Start tracking if active shift
            if (GPS.shiftActive) {
                GPS.init();
            }
        });

        window._ecoGPS = GPS;
    })();
    </script>
</head>
<body class="h-full antialiased text-slate-800 flex" data-page="<?= htmlspecialchars($_GET['page'] ?? 'dashboard') ?>" data-shift-active="<?= isInActiveShift() ? '1' : '0' ?>" data-attendance-id="<?php $todayAtt = getDBConnection()->query('SELECT id FROM attendance WHERE user_id = ' . (int)(authUser()['id'] ?? 0) . ' AND date = \'' . date('Y-m-d') . '\' AND clock_out IS NULL ORDER BY id DESC LIMIT 1')?->fetch(PDO::FETCH_ASSOC); echo (int)($todayAtt['id'] ?? 0); ?>" x-data="{ sidebarOpen: false, sidebarCollapsed: false }">

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