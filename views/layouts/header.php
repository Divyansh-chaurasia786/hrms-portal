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
                <!-- 📲 Native App Engine & Standalone Window Launcher -->
    <script>
    window.pwaInstallPrompt = null;

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js').then((reg) => {
                console.log('PWA ServiceWorker registered');
            }).catch(() => {});
        });
    }

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        window.pwaInstallPrompt = e;
    });

    window.addEventListener('appinstalled', () => {
        window.pwaInstallPrompt = null;
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

        // Open in Standalone Native App Window Mode (Frameless Desktop App)
        const currentUrl = window.location.href;
        const appWindow = window.open(
            currentUrl,
            'EcofoneHRMS_NativeApp',
            'toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=1366,height=850,top=50,left=100'
        );
        if (appWindow) {
            appWindow.focus();
        }
    }
    </script>
</head>

            <button type="button" onclick="closePwaModal()" class="w-8 h-8 rounded-full bg-slate-100 text-slate-400 hover:text-slate-700 hover:bg-slate-200 flex items-center justify-center cursor-pointer transition">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <!-- Browser Specific Step-by-Step Instructions -->
        <div class="space-y-3">
            <!-- Brave / Chrome Desktop Guide -->
            <div class="p-4 rounded-2xl bg-gradient-to-r from-orange-50/80 to-amber-50/80 border border-orange-200/80 flex items-start gap-3.5">
                <div class="w-8 h-8 rounded-xl bg-orange-600 text-white flex items-center justify-center font-bold text-xs shrink-0 shadow-xs">
                    1
                </div>
                <div class="text-xs text-slate-700">
                    <div class="font-bold text-slate-900 mb-0.5">For Brave / Chrome Browser:</div>
                    Look at your browser's **top-right menu button (≡ or ⋮)** next to the address bar, then click <strong class="text-orange-950 bg-orange-100 px-1.5 py-0.5 rounded">"Install Ecofone HRMS..."</strong>.
                </div>
            </div>

            <!-- Mobile Phone Guide -->
            <div class="p-4 rounded-2xl bg-gradient-to-r from-emerald-50/80 to-teal-50/80 border border-emerald-200/80 flex items-start gap-3.5">
                <div class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-xs shrink-0 shadow-xs">
                    2
                </div>
                <div class="text-xs text-slate-700">
                    <div class="font-bold text-slate-900 mb-0.5">For Android & iOS Mobile:</div>
                    Tap the <strong>(⋮) menu</strong> in Chrome or <strong>Share (↑)</strong> in Safari, then tap <strong class="text-emerald-950 bg-emerald-100 px-1.5 py-0.5 rounded">"Add to Home screen"</strong>.
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between pt-2">
            <span class="text-[11px] text-slate-400 font-medium">No Play Store download needed</span>
            <button type="button" onclick="closePwaModal()" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-600/20 transition cursor-pointer">
                Got It, Let Me Install!
            </button>
        </div>
    </div>
</div>
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