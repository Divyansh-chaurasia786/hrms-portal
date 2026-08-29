<!-- views/layouts/header.php -->
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Ecofone HRMS | Enterprise Team & Operations Portal</title>
    <meta name="description" content="Ecofone HRMS - Enterprise Human Resource & Team Operations Management System.">
    <meta name="keywords" content="Ecofone HRMS, Ecofone, HRMS Ecofone, ecofone portal, HRMS portal">
    <meta name="robots" content="noindex, nofollow">
        <!-- Leaflet Maps CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
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
</head>
<body class="h-full antialiased text-slate-800 flex" data-shift-active="<?= isInActiveShift() ? '1' : '0' ?>" x-data="{ sidebarOpen: false }">

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