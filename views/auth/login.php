<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    
    <!-- Primary SEO Metadata -->
    <title>Ecofone HRMS | Enterprise Human Resource & Operations Management Portal</title>
    <meta name="title" content="Ecofone HRMS | Enterprise Human Resource & Operations Management Portal">
    <meta name="description" content="Official Ecofone HRMS Portal - Enterprise Human Resource Management System for Ecofone. Live attendance tracking, team task reporting, cloud drive storage, and operations management.">
    <meta name="keywords" content="Ecofone HRMS, Ecofone, HRMS Ecofone, ecofone portal, ecofone hrms login, ecofone employee login, ecofone attendance, ecofone operations, HRMS portal">
    <meta name="author" content="Ecofone">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <link rel="canonical" href="https://hrms-ecofone.vercel.app">

    <!-- Open Graph / Facebook / LinkedIn / WhatsApp Preview -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://hrms-ecofone.vercel.app">
    <meta property="og:title" content="Ecofone HRMS - Enterprise Portal">
    <meta property="og:description" content="Official Ecofone HRMS Portal - Live attendance, task management, team cloud drive, and operations.">
    <meta property="og:site_name" content="Ecofone HRMS">
    <meta property="og:image" content="https://ui-avatars.com/api/?name=Ecofone+HRMS&background=4f46e5&color=fff&size=512">

    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="https://hrms-ecofone.vercel.app">
    <meta name="twitter:title" content="Ecofone HRMS - Enterprise Portal">
    <meta name="twitter:description" content="Official Ecofone HRMS Portal - Attendance, team operations, and employee management.">

    <!-- Schema.org JSON-LD Structured Data for Google Search Knowledge Graph -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebApplication",
      "name": "Ecofone HRMS",
      "alternateName": ["HRMS Ecofone", "Ecofone Portal", "Ecofone Human Resource Management System"],
      "url": "https://hrms-ecofone.vercel.app",
      "applicationCategory": "BusinessApplication",
      "operatingSystem": "All",
      "description": "Official Ecofone HRMS Enterprise Portal for attendance, shift management, task submissions, and workforce operations.",
      "publisher": {
        "@type": "Organization",
        "name": "Ecofone",
        "url": "https://hrms-ecofone.vercel.app"
      }
    }
    </script>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif; }
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
        <div class="text-center mb-5">
            <div class="inline-flex w-12 h-12 rounded-2xl bg-gradient-to-tr from-indigo-600 via-indigo-500 to-purple-600 items-center justify-center text-white shadow-xl shadow-indigo-600/30 ring-1 ring-white/20 mb-3">
                <i data-lucide="shield-check" class="w-6 h-6"></i>
            </div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">Ecofone HRMS</h1>
            <p class="text-xs font-medium text-slate-400 mt-1">Sign in to access your organization portal & operations</p>
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

        <div class="text-center mt-6 text-xs text-slate-500">
            &copy; <?= date('Y') ?> Ecofone HRMS & Operations Platform. All rights reserved.
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
