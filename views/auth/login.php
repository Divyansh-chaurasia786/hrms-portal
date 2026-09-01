<!-- views/auth/login.php -->
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

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif; }
        
        @keyframes ambientFloat {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-20px) scale(1.06); }
        }
        @keyframes borderShimmer {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .ambient-orb-1 {
            animation: ambientFloat 8s ease-in-out infinite;
        }
        .ambient-orb-2 {
            animation: ambientFloat 10s ease-in-out infinite reverse;
        }

        /* 💎 Glassmorphic Neon Glowing Border */
        .glass-glow-card {
            position: relative;
            background: rgba(15, 23, 42, 0.82);
            backdrop-filter: blur(28px);
            -webkit-backdrop-filter: blur(28px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.85), 0 0 40px -10px rgba(99, 102, 241, 0.15);
        }
        .glass-glow-card::before {
            content: '';
            position: absolute;
            inset: -1.5px;
            border-radius: 28px;
            padding: 1.5px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.6), rgba(249, 115, 22, 0.4), rgba(168, 85, 247, 0.4), rgba(16, 185, 129, 0.3));
            background-size: 300% 300%;
            animation: borderShimmer 8s linear infinite;
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
        }

        /* 🚀 Shimmer Action Button */
        .shimmer-btn {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 50%, #9333ea 100%);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .shimmer-btn::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(60deg, transparent 30%, rgba(255, 255, 255, 0.25) 50%, transparent 70%);
            transform: rotate(30deg);
            animation: borderShimmer 4s infinite linear;
            pointer-events: none;
        }
        .shimmer-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px -5px rgba(99, 102, 241, 0.5);
        }

        .brand-logo-wrap {
            width: 56px !important;
            height: 56px !important;
            max-width: 56px !important;
            max-height: 56px !important;
            border-radius: 18px !important;
            overflow: hidden !important;
            background: #ffffff !important;
            padding: 6px !important;
            box-shadow: 0 10px 25px rgba(249, 115, 22, 0.35) !important;
        }
        .brand-logo-img {
            width: 44px !important;
            height: 44px !important;
            max-width: 44px !important;
            max-height: 44px !important;
            object-fit: contain !important;
        }
    </style>
</head>
<body class="min-h-full flex flex-col items-center justify-center py-6 px-4 sm:px-6 relative overflow-x-hidden overflow-y-auto bg-slate-950 antialiased selection:bg-indigo-500 selection:text-white">
    <!-- 🌌 Ambient Background Glows -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden">
        <div class="ambient-orb-1 absolute -top-32 -left-32 w-[420px] h-[420px] bg-indigo-600/20 rounded-full blur-[110px]"></div>
        <div class="ambient-orb-2 absolute -bottom-32 -right-32 w-[420px] h-[420px] bg-purple-600/20 rounded-full blur-[110px]"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[550px] h-[550px] bg-orange-500/10 rounded-full blur-[140px]"></div>
    </div>

    <div class="w-full max-w-[420px] relative z-10 my-auto">
        <!-- Logo & Header -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center mb-3">
                <div class="brand-logo-wrap ring-4 ring-white/10">
                    <img src="/logo_icon.png?v=3" alt="EcoFone App Logo" width="44" height="44" class="brand-logo-img">
                </div>
            </div>
            <h1 class="text-2xl font-black text-white tracking-tight flex items-center justify-center gap-1.5">
                <span>EcoFone</span> <span class="text-orange-400 font-black">App</span>
            </h1>
            <p class="text-xs font-semibold text-slate-400 mt-1">Official Workforce, CRM & Operations Portal</p>
        </div>

        <!-- Flash messages -->
        <?php $flash = getFlash(); if ($flash): ?>
            <div id="flashAlert" class="mb-4 p-3.5 rounded-2xl text-xs font-semibold <?= $flash['type'] === 'error' ? 'bg-rose-500/15 text-rose-300 border border-rose-500/30' : ($flash['type'] === 'success' ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30' : 'bg-blue-500/15 text-blue-300 border border-blue-500/30') ?> flex items-start gap-3 shadow-lg backdrop-blur-sm transition-all duration-500">
                <i data-lucide="<?= $flash['type'] === 'error' ? 'alert-circle' : ($flash['type'] === 'success' ? 'check-circle-2' : 'info') ?>" class="w-4 h-4 shrink-0 mt-0.5"></i>
                <div class="leading-relaxed"><?= $flash['message'] ?></div>
            </div>
        <?php endif; ?>

        <!-- Main Glassmorphic Login Card -->
        <div class="glass-glow-card rounded-3xl p-6 sm:p-8 space-y-5">
            <!-- Passwordless OTP Notice Chip -->
            <div class="flex items-center justify-center gap-2 text-[11px] font-bold text-indigo-300 bg-indigo-500/15 border border-indigo-500/25 rounded-full py-1.5 px-4 w-fit mx-auto shadow-inner">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>Passwordless Email 2FA Security</span>
            </div>

            <form action="?action=login" method="POST" class="space-y-4.5">
                <!-- Work Email Address -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-extrabold text-slate-300 uppercase tracking-wider">Registered Work Email</label>
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
                            class="w-full bg-slate-950/90 border border-slate-700/80 focus:border-indigo-500 rounded-2xl pl-10 pr-4 py-3 text-sm text-white focus:outline-none focus:ring-4 focus:ring-indigo-500/20 transition duration-200 placeholder:text-slate-600 font-semibold lowercase shadow-inner"
                        >
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">We'll send a 6-digit one-time verification code to your email.</p>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="shimmer-btn w-full mt-3 py-3.5 px-4 text-white text-sm font-extrabold rounded-2xl shadow-xl flex items-center justify-center gap-2 group cursor-pointer">
                    <span>Send Verification Code</span>
                    <i data-lucide="arrow-right" class="w-4 h-4 group-hover:translate-x-1 transition duration-200"></i>
                </button>
            </form>

            <!-- Security Footer Guarantee -->
            <div class="pt-4 border-t border-slate-800/80 flex items-center justify-between text-[11px] text-slate-500 font-medium">
                <span class="flex items-center gap-1.5"><i data-lucide="shield-check" class="w-3.5 h-3.5 text-emerald-400"></i> Encrypted 2FA Login</span>
                <span class="font-mono text-[10px]">EcoFone 2.4</span>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
        setTimeout(() => {
            const el = document.getElementById('flashAlert');
            if (el) {
                el.style.opacity = '0';
                el.style.transform = 'translateY(-10px)';
                setTimeout(() => el.remove(), 400);
            }
        }, 5000);
    </script>
</body>
</html>