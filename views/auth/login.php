<!-- views/auth/login.php -->
<!DOCTYPE html>
<html lang="en" class="h-full bg-black text-white">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    
    <title>EcoFone • Login</title>
    <meta name="description" content="Official EcoFone Portal - Sign in to access your attendance, CRM, and workforce dashboard.">
    <link rel="icon" type="image/png" href="/favicon.png">

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #000000;
        }

        /* 🟣 Instagram Signature Rotating Story Ring */
        @keyframes storySpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @keyframes floatSubtle {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }

        .ig-story-ring {
            position: relative;
            width: 88px;
            height: 88px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: floatSubtle 4s ease-in-out infinite;
        }
        .ig-story-gradient {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
            animation: storySpin 8s linear infinite;
        }
        .ig-story-cutout {
            position: absolute;
            inset: 3px;
            background: #000000;
            border-radius: 50%;
            z-index: 1;
        }
        .ig-story-avatar {
            position: relative;
            width: 74px;
            height: 74px;
            border-radius: 50%;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
            padding: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.6);
        }

        /* 📱 Instagram Native Card */
        .ig-auth-box {
            background-color: #000000;
            border: 1px solid #262626;
            border-radius: 1px;
        }
        @media (min-width: 480px) {
            .ig-auth-box {
                border-radius: 12px;
                padding: 40px 40px 24px;
            }
        }

        /* 🔘 Instagram Style Text Input */
        .ig-input {
            background-color: #121212;
            border: 1px solid #262626;
            border-radius: 8px;
            color: #ffffff;
            font-size: 13px;
            padding: 12px 14px;
            width: 100%;
            transition: border-color 0.2s ease, background-color 0.2s ease;
            outline: none;
        }
        .ig-input:focus {
            border-color: #555555;
            background-color: #181818;
        }
        .ig-input::placeholder {
            color: #737373;
            font-size: 13px;
        }

        /* 🚀 Instagram Primary Blue Button */
        .ig-btn-primary {
            background: #0095f6;
            color: #ffffff;
            font-weight: 700;
            font-size: 14px;
            padding: 10px 16px;
            border-radius: 8px;
            width: 100%;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .ig-btn-primary:hover {
            background: #1877f2;
        }
        .ig-btn-primary:active {
            opacity: 0.7;
        }

        /* 🌈 Instagram Gradient Alternative Button */
        .ig-btn-gradient {
            background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
            color: #ffffff;
            font-weight: 700;
            font-size: 14px;
            padding: 11px 16px;
            border-radius: 8px;
            width: 100%;
            transition: opacity 0.2s ease, transform 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(220, 39, 67, 0.3);
        }
        .ig-btn-gradient:hover {
            opacity: 0.95;
            transform: scale(1.01);
        }
        .ig-btn-gradient:active {
            transform: scale(0.99);
        }
    </style>
</head>
<body class="min-h-full flex flex-col justify-between items-center py-8 px-4 bg-black text-white antialiased selection:bg-[#0095f6] selection:text-white">
    <!-- Main Center Container -->
    <div class="w-full max-w-[360px] my-auto space-y-3">
        <!-- Main Login Box -->
        <div class="ig-auth-box p-6 sm:p-9 space-y-6">
            <!-- 🟣 Rotating Instagram Story Header Logo -->
            <div class="text-center">
                <div class="ig-story-ring mb-4">
                    <div class="ig-story-gradient"></div>
                    <div class="ig-story-cutout"></div>
                    <div class="ig-story-avatar">
                        <img src="/logo_icon.png?v=<?= time() ?>" alt="EcoFone Logo" width="48" height="48" class="w-full h-full object-contain">
                    </div>
                </div>
                
                <!-- Brand Title -->
                <h1 class="text-3xl font-extrabold tracking-tight text-white mb-1">
                    EcoFone
                </h1>
                <p class="text-xs text-[#a8a8a8] font-medium">Workforce, CRM & Live Attendance Portal</p>
            </div>

            <!-- Flash Alerts -->
            <?php $flash = getFlash(); if ($flash): ?>
                <div id="flashAlert" class="p-3.5 rounded-lg text-xs font-semibold <?= $flash['type'] === 'error' ? 'bg-[#2a0e14] text-[#ff4d6d] border border-[#ff4d6d]/40' : ($flash['type'] === 'success' ? 'bg-[#0f2e1e] text-[#2ec4b6] border border-[#2ec4b6]/40' : 'bg-[#0f1f38] text-[#3a86ff] border border-[#3a86ff]/40') ?> flex items-start gap-2.5 transition-all">
                    <i data-lucide="<?= $flash['type'] === 'error' ? 'alert-circle' : 'check-circle-2' ?>" class="w-4 h-4 shrink-0 mt-0.5"></i>
                    <div class="leading-relaxed flex-1"><?= $flash['message'] ?></div>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form action="?action=login" method="POST" class="space-y-3.5">
                <div>
                    <input 
                        type="email" 
                        name="email" 
                        id="emailInput" 
                        required 
                        autofocus
                        autocapitalize="none"
                        autocorrect="off"
                        spellcheck="false"
                        placeholder="Work email address" 
                        class="ig-input"
                    >
                </div>

                <!-- Submit Button with Instagram Gradient -->
                <button type="submit" class="ig-btn-gradient gap-2">
                    <span>Send Verification Code</span>
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </form>

            <!-- OR Divider -->
            <div class="flex items-center my-4">
                <div class="flex-grow border-t border-[#262626]"></div>
                <span class="px-4 text-[11px] font-bold text-[#737373] tracking-widest uppercase">OR</span>
                <div class="flex-grow border-t border-[#262626]"></div>
            </div>

            <!-- 2FA Passwordless Information -->
            <div class="text-center space-y-1">
                <div class="inline-flex items-center gap-1.5 text-xs text-[#0095f6] font-bold hover:underline cursor-pointer">
                    <i data-lucide="shield-check" class="w-4 h-4 text-emerald-400"></i>
                    <span>Passwordless Security Active</span>
                </div>
                <p class="text-[11px] text-[#737373] leading-relaxed">A 6-digit one-time passcode will be delivered to your registered email.</p>
            </div>
        </div>

        <!-- Secondary Bottom Box -->
        <div class="ig-auth-box p-4 text-center text-xs text-[#a8a8a8]">
            <span>Need help signing in? </span>
            <a href="mailto:support@ecofone.com" class="text-[#0095f6] font-bold hover:underline">Contact Admin</a>
        </div>
    </div>

    <!-- Instagram Style Minimalist Footer -->
    <footer class="w-full max-w-2xl text-center py-4 text-[11px] text-[#737373] space-y-2">
        <div class="flex flex-wrap justify-center gap-x-4 gap-y-1">
            <span class="hover:underline cursor-pointer">About</span>
            <span class="hover:underline cursor-pointer">Help</span>
            <span class="hover:underline cursor-pointer">Privacy</span>
            <span class="hover:underline cursor-pointer">Terms</span>
            <span class="hover:underline cursor-pointer">Locations</span>
            <span class="hover:underline cursor-pointer">Language</span>
        </div>
        <div class="text-[10px] text-[#555555]">
            © <?= date('Y') ?> EcoFone Portal • Enterprise Operations
        </div>
    </footer>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>