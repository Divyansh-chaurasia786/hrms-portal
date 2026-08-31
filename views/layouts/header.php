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
                            <!-- 📲 Native PWA & Desktop App Installer Engine -->
    <script>
    window.pwaInstallPrompt = null;

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js').catch(() => {});
        });
    }

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        window.pwaInstallPrompt = e;
        const btn = document.getElementById('installAppNavbarBtn');
        if (btn) btn.classList.add('ring-2', 'ring-indigo-400');
    });

    function triggerPwaInstall() {
        if (window.pwaInstallPrompt) {
            window.pwaInstallPrompt.prompt();
            window.pwaInstallPrompt.userChoice.then((choice) => {
                if (choice.outcome === 'accepted') {
                    window.pwaInstallPrompt = null;
                }
            });
            return;
        }

        // Show Desktop Install Modal
        const modal = document.getElementById('pwaInstallModal');
        if (modal) {
            modal.classList.remove('hidden');
        }
    }

    function closePwaInstallModal() {
        const modal = document.getElementById('pwaInstallModal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    function downloadDesktopLauncher() {
        const url = 'https://hrms-ecovista.vercel.app/?source=desktop_app';
        const shortcutContent = `[InternetShortcut]\nURL=${url}\nIconIndex=0\nIconFile=https://hrms-ecovista.vercel.app/icon-192.png\n`;
        const blob = new Blob([shortcutContent], { type: 'application/octet-stream' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'Ecofone_HRMS_Portal.url';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }
    </script>

    <!-- 📍 GPS Location Tracker (Permission asked ONCE, never again) -->
    <script>
    var _ecoGpsInterval = null;
    var _ecoLastPing = 0;
    var _ecoPingIntervalMs = 30 * 60 * 1000; // 30-min auto ping

    // Silent GPS ping — no prompt shown, called only when permission already granted
    function _ecoSendLocationPing(type) {
        if (!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                fetch('/api/track-location.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        lat:      pos.coords.latitude,
                        lng:      pos.coords.longitude,
                        accuracy: pos.coords.accuracy,
                        type:     type || 'auto_ping',
                        device:   navigator.userAgent.substring(0, 200),
                        address:  ''
                    })
                }).catch(function() {});
                _ecoLastPing = Date.now();
                // Mark permission as permanently granted in localStorage
                try { localStorage.setItem('eco_gps_granted', '1'); } catch(e) {}
            },
            function(err) {
                // If explicitly denied, remember so we never ask again
                if (err.code === 1) {
                    try { localStorage.setItem('eco_gps_granted', 'denied'); } catch(e) {}
                }
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
        );
    }

    function _ecoStartIntervalPing() {
        if (_ecoGpsInterval) clearInterval(_ecoGpsInterval);
        _ecoGpsInterval = setInterval(function() {
            _ecoSendLocationPing('auto_ping');
        }, _ecoPingIntervalMs);
    }

    function _ecoInitGpsTracking() {
        if (!navigator.geolocation) return;

        var storedState = '';
        try { storedState = localStorage.getItem('eco_gps_granted') || ''; } catch(e) {}

        // If user already denied — never ask again, never ping
        if (storedState === 'denied') return;

        // If already granted in a previous session — track silently, no popup
        if (storedState === '1') {
            _ecoSendLocationPing('auto_ping');
            _ecoStartIntervalPing();
            return;
        }

        // First time: use Permissions API to check status before calling anything
        if (navigator.permissions && navigator.permissions.query) {
            navigator.permissions.query({ name: 'geolocation' }).then(function(result) {
                if (result.state === 'granted') {
                    // Already granted by browser — silently track
                    try { localStorage.setItem('eco_gps_granted', '1'); } catch(e) {}
                    _ecoSendLocationPing('auto_ping');
                    _ecoStartIntervalPing();

                } else if (result.state === 'prompt') {
                    // Ask ONCE — after this the browser remembers the choice
                    // We only ask if we have never stored a decision
                    _ecoSendLocationPing('auto_ping'); // This triggers the ONE-TIME browser popup
                    _ecoStartIntervalPing();

                }
                // state === 'denied': do nothing, never ask
            }).catch(function() {
                // Permissions API not supported — fallback: ask once
                if (storedState !== 'denied') {
                    _ecoSendLocationPing('auto_ping');
                    _ecoStartIntervalPing();
                }
            });
        } else {
            // Older browser — ask once, store result
            if (storedState !== 'denied') {
                _ecoSendLocationPing('auto_ping');
                _ecoStartIntervalPing();
            }
        }
    }

    // On visibility restore (tab switch back) — silent ping only if already granted
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            var st = '';
            try { st = localStorage.getItem('eco_gps_granted') || ''; } catch(e) {}
            if (st === '1' && Date.now() - _ecoLastPing > 5 * 60 * 1000) {
                _ecoSendLocationPing('auto_ping');
            }
        }
    });

    document.addEventListener('DOMContentLoaded', _ecoInitGpsTracking);
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