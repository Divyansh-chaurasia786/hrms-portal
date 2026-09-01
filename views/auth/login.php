<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    
    <!-- Primary SEO Metadata -->
    <title>EcoFone App | Official Enterprise Workforce & Operations Portal</title>
    <meta name="title" content="EcoFone App | Official Enterprise Workforce & Operations Portal">
    <meta name="description" content="Official EcoFone App - Enterprise Workforce Management, Live Attendance, CRM, and Operations Portal.">
    <meta name="keywords" content="EcoFone App, EcoFone, ecofone portal, ecofone login, ecofone attendance, ecofone operations">
    <meta name="author" content="EcoFone">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="canonical" href="https://hrms-ecovista.vercel.app">

    <!-- Open Graph / Facebook / LinkedIn / WhatsApp Preview -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://hrms-ecovista.vercel.app">
    <meta property="og:title" content="EcoFone App - Official Portal">
    <meta property="og:description" content="Official EcoFone App - Live attendance, CRM, and workforce operations.">
    <meta property="og:site_name" content="EcoFone App">
    <meta property="og:image" content="/icon-512.png">

    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="https://hrms-ecovista.vercel.app">
    <meta name="twitter:title" content="EcoFone App - Official Portal">
    <meta name="twitter:description" content="Official EcoFone App - Attendance, CRM, and employee management.">

    <!-- Schema.org JSON-LD Structured Data for Google Search Knowledge Graph -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebApplication",
      "name": "EcoFone App",
      "alternateName": ["EcoFone", "EcoFone Portal", "EcoFone HRMS"],
      "url": "https://hrms-ecovista.vercel.app",
      "applicationCategory": "BusinessApplication",
      "operatingSystem": "All",
      "description": "Official EcoFone App Enterprise Portal for attendance, shift management, task submissions, and workforce operations.",
      "publisher": {
        "@type": "Organization",
        "name": "EcoFone",
        "url": "https://hrms-ecovista.vercel.app"
      }
    }
    </script>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif; }
        .brand-logo-wrap {
            width: 60px !important;
            height: 60px !important;
            max-width: 60px !important;
            max-height: 60px !important;
            min-width: 60px !important;
            min-height: 60px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            border-radius: 16px !important;
            overflow: hidden !important;
            background: #ffffff !important;
            padding: 6px !important;
            box-shadow: 0 10px 25px -5px rgba(249, 115, 22, 0.3) !important;
        }
        .brand-logo-img {
            width: 48px !important;
            height: 48px !important;
            max-width: 48px !important;
            max-height: 48px !important;
            object-fit: contain !important;
            display: block !important;
        }
    </style>
</head>
<body class="min-h-full flex flex-col items-center justify-center py-6 px-4 sm:px-6 relative overflow-x-hidden overflow-y-auto bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 antialiased">
    <!-- Ambient Background Glows (Fixed to prevent scroll spill) -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden">
        <div class="absolute -top-40 -left-40 w-96 h-96 bg-indigo-600/15 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-purple-600/15 rounded-full blur-3xl"></div>
    </div>

    <div class="w-full max-w-[420px] relative z-10 my-auto">
        <!-- Logo & Header -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center mb-3">
                <div class="brand-logo-wrap ring-4 ring-white/20">
                    <img src="/logo_icon.png?v=<?= time() ?>" alt="EcoFone App Logo" width="48" height="48" class="brand-logo-img">
                </div>
            </div>
            <h1 class="text-2xl font-black text-white tracking-tight flex items-center justify-center gap-1.5">
                <span>EcoFone</span> <span class="text-orange-400 font-black">App</span>
            </h1>
            <p class="text-xs font-semibold text-slate-400 mt-1">Official Workforce, CRM & Operations Portal</p>
        </div>

        <!-- Flash messages -->
        <?php $flash = getFlash(); if ($flash): ?>
            <div id="flashAlert" class="mb-4 p-3.5 rounded-2xl text-xs font-medium <?= $flash['type'] === 'error' ? 'bg-rose-500/10 text-rose-300 border border-rose-500/25' : ($flash['type'] === 'success' ? 'bg-emerald-500/10 text-emerald-300 border border-emerald-500/25' : 'bg-blue-500/10 text-blue-300 border border-blue-500/25') ?> flex items-start gap-3 shadow-lg backdrop-blur-sm transition-all duration-500">
                <i data-lucide="<?= $flash['type'] === 'error' ? 'alert-circle' : ($flash['type'] === 'success' ? 'check-circle-2' : 'info') ?>" class="w-4 h-4 shrink-0 mt-0.5"></i>
                <div class="leading-relaxed"><?= $flash['message'] ?></div>
            </div>
        <?php endif; ?>

        <!-- Main Login Card -->
        <div class="bg-slate-900/90 backdrop-blur-xl rounded-3xl border border-slate-800/80 p-6 sm:p-8 shadow-2xl shadow-black/60 space-y-5">
            <!-- Passwordless OTP Notice Chip -->
            <div class="flex items-center justify-center gap-2 text-[11px] font-semibold text-indigo-300 bg-indigo-500/10 border border-indigo-500/20 rounded-full py-1 px-3.5 w-fit mx-auto shadow-inner">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>Passwordless Email OTP Authentication</span>
            </div>

            <form action="?action=login" method="POST" class="space-y-4.5">
                <!-- Work Email Address -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-300 tracking-wide">Registered Work Email</label>
                    <div class="relative">
                        <i data-lucide="mail" class="w-4 h-4 text-slate-500 absolute left-3.5 top-3.5"></i>
                        <input 
                            type="email" 
                            name="email" 
                            id="emailInput" 
                            required 
                            autofocus
                            autocapitalize="none"
                            autocorrect="off"
                            spellcheck="false"
                            placeholder="name@company.com" 
                            class="w-full bg-slate-950/80 border border-slate-800 focus:border-indigo-500 rounded-2xl pl-10 pr-4 py-3 text-sm text-white focus:outline-none focus:ring-4 focus:ring-indigo-500/20 transition duration-200 placeholder:text-slate-600 font-medium lowercase"
                        >
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">We'll send a 6-digit one-time verification code to this email.</p>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="w-full mt-3 py-3.5 px-4 bg-gradient-to-r from-indigo-600 via-indigo-500 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white text-sm font-bold rounded-2xl shadow-xl shadow-indigo-600/30 hover:shadow-indigo-600/40 transition duration-200 flex items-center justify-center gap-2 group cursor-pointer">
                    <span>Send Verification Code</span>
                    <i data-lucide="arrow-right" class="w-4 h-4 group-hover:translate-x-0.5 transition duration-200"></i>
                </button>
            </form>

            <!-- Security Footer Guarantee -->
            <div class="pt-4 border-t border-slate-800/80 flex items-center justify-between text-[11px] text-slate-500 font-medium">
                <span class="flex items-center gap-1.5"><i data-lucide="shield-check" class="w-3.5 h-3.5 text-emerald-400"></i> Secure OTP Login</span>
                <span>Enterprise v2.4</span>
            </div>
        </div>

        <div class="text-center mt-6 text-[11px] text-slate-500 font-medium">
            &copy; <?= date('Y') ?> EcoFone App & Operations Platform. All rights reserved.
        </div>
    </div>

    <script>
        lucide.createIcons();

        setTimeout(() => {
            const el = document.getElementById('flashAlert');
            if (el) {
                el.style.opacity = '0';
                el.style.transform = 'translateY(-10px)';
                setTimeout(() => el.remove(), 500);
            }
        }, 5000);
    </script>
</body>
</html>
