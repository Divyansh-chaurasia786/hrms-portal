<!-- views/auth/verify_otp.php -->
<?php
$resendCount = (int)($resendCount ?? ($_SESSION['otp_resend_count'] ?? 0));
$cooldownRemaining = (int)($cooldownRemaining ?? 60);
$targetEmail = !empty($_GET['email']) ? strtolower(trim($_GET['email'])) : (!empty($_SESSION['pending_otp_email']) ? $_SESSION['pending_otp_email'] : (!empty($_COOKIE['pending_otp_email']) ? $_COOKIE['pending_otp_email'] : ''));
$user = $user ?? ['email' => $targetEmail];
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-[#05070d] text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Secure Verification • EcoFone</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@700;800&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: radial-gradient(circle at 50% 20%, #0d1224 0%, #05070d 100%);
            min-height: 100vh;
        }
        .font-mono-code { font-family: 'JetBrains Mono', monospace; }

        /* 🌀 1. ROUND CIRCULAR AUTHENTICATION RADAR MOTION */
        @keyframes spinRing {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @keyframes spinRingReverse {
            0% { transform: rotate(360deg); }
            100% { transform: rotate(0deg); }
        }
        @keyframes radarRipple {
            0% { transform: scale(0.85); opacity: 0.8; }
            50% { transform: scale(1.3); opacity: 0.2; }
            100% { transform: scale(1.6); opacity: 0; }
        }
        @keyframes scanBeam {
            0% { top: 10%; opacity: 0.3; }
            50% { top: 80%; opacity: 1; }
            100% { top: 10%; opacity: 0.3; }
        }
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 0 25px rgba(99, 102, 241, 0.4), inset 0 0 20px rgba(99, 102, 241, 0.3); }
            50% { box-shadow: 0 0 45px rgba(99, 102, 241, 0.7), inset 0 0 30px rgba(139, 92, 246, 0.5); }
        }
        @keyframes floatCard {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-8px); }
        }
        @keyframes digitPop {
            0% { transform: scale(0.7); opacity: 0.4; }
            60% { transform: scale(1.15); }
            100% { transform: scale(1); opacity: 1; }
        }

        /* 🌌 Ambient Floating Lights */
        @keyframes ambientLightMove {
            0%, 100% { transform: translate(0px, 0px) scale(1); }
            33% { transform: translate(40px, -30px) scale(1.15); }
            66% { transform: translate(-30px, 20px) scale(0.9); }
        }
        .ambient-light-1 {
            animation: ambientLightMove 12s ease-in-out infinite;
        }
        .ambient-light-2 {
            animation: ambientLightMove 15s ease-in-out infinite reverse;
        }

        /* 🛡️ Circular Round Authentication Module */
        .auth-circle-container {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .auth-radar-ring-outer {
            position: absolute;
            inset: -8px;
            border-radius: 50%;
            border: 2px dashed rgba(99, 102, 241, 0.45);
            animation: spinRing 12s linear infinite;
        }
        .auth-radar-ring-inner {
            position: absolute;
            inset: 2px;
            border-radius: 50%;
            border: 1.5px solid transparent;
            border-top-color: #ec4899;
            border-right-color: #8b5cf6;
            animation: spinRingReverse 6s linear infinite;
        }
        .auth-ripple-wave {
            position: absolute;
            inset: -4px;
            border-radius: 50%;
            border: 2px solid #6366f1;
            animation: radarRipple 2.6s cubic-bezier(0.25, 1, 0.5, 1) infinite;
        }
        .auth-circle-core {
            position: relative;
            width: 86px;
            height: 86px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.9) 0%, rgba(15, 23, 42, 0.95) 100%);
            border: 2px solid rgba(255, 255, 255, 0.15);
            animation: pulseGlow 4s ease-in-out infinite;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            z-index: 5;
        }
        .scan-laser-line {
            position: absolute;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, #818cf8, #ec4899, transparent);
            box-shadow: 0 0 10px #818cf8;
            animation: scanBeam 2.2s ease-in-out infinite;
            pointer-events: none;
        }

        /* 💎 Glassmorphic Master Card */
        .master-auth-card {
            background: rgba(15, 23, 42, 0.72);
            backdrop-filter: blur(35px);
            -webkit-backdrop-filter: blur(35px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 36px;
            box-shadow: 0 30px 90px -20px rgba(0, 0, 0, 0.95), 
                        0 0 50px -15px rgba(99, 102, 241, 0.3),
                        inset 0 1px 0 rgba(255, 255, 255, 0.2);
            animation: floatCard 6s ease-in-out infinite;
        }

        /* 🔢 Ultra-Smooth Neon Inputs */
        .neon-digit-input {
            position: relative;
            width: 46px;
            height: 58px;
            background: rgba(8, 12, 22, 0.85);
            border: 1.5px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            color: #ffffff;
            font-size: 24px;
            font-weight: 800;
            text-align: center;
            transition: all 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: inset 0 2px 5px rgba(0, 0, 0, 0.7);
        }
        @media (min-width: 640px) {
            .neon-digit-input {
                width: 52px;
                height: 64px;
                font-size: 26px;
                border-radius: 18px;
            }
        }
        .neon-digit-input:focus {
            outline: none;
            border-color: #818cf8;
            background: rgba(22, 30, 52, 0.95);
            box-shadow: 0 0 25px rgba(99, 102, 241, 0.55), inset 0 2px 4px rgba(0, 0, 0, 0.5);
            transform: translateY(-3px) scale(1.06);
        }
        .neon-digit-input.has-digit {
            border-color: rgba(99, 102, 241, 0.7);
            color: #c7d2fe;
            animation: digitPop 0.18s ease-out;
        }
        .neon-digit-input.auth-success {
            border-color: #10b981 !important;
            box-shadow: 0 0 30px rgba(16, 185, 129, 0.6) !important;
            color: #34d399 !important;
            background: rgba(6, 78, 59, 0.3) !important;
        }

        /* 🚀 Shimmer Glowing Button */
        .neon-pulse-btn {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #db2777 100%);
            border-radius: 20px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 30px -5px rgba(99, 102, 241, 0.55), 0 0 25px rgba(219, 39, 119, 0.35);
        }
        .neon-pulse-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px -5px rgba(99, 102, 241, 0.75), 0 0 35px rgba(219, 39, 119, 0.5);
        }
        .neon-pulse-btn:active {
            transform: scale(0.98);
        }
    </style>
</head>
<body class="min-h-full flex flex-col items-center justify-center py-6 px-4 sm:px-6 relative overflow-x-hidden overflow-y-auto antialiased selection:bg-indigo-500 selection:text-white">
    <!-- 🌌 Dynamic Moving Ambient Glows -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden">
        <div class="ambient-light-1 absolute -top-32 -left-32 w-[520px] h-[520px] bg-indigo-600/30 rounded-full blur-[130px]"></div>
        <div class="ambient-light-2 absolute -bottom-32 -right-32 w-[520px] h-[520px] bg-pink-600/25 rounded-full blur-[130px]"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-purple-600/15 rounded-full blur-[160px]"></div>
    </div>

    <div class="w-full max-w-[430px] relative z-10 my-auto">
        <!-- 🌀 Round Authentication Radar Scanning Module (Exact Reel Feature) -->
        <div class="text-center mb-6 relative">
            <div class="auth-circle-container mb-4">
                <div class="auth-ripple-wave"></div>
                <div class="auth-radar-ring-outer"></div>
                <div class="auth-radar-ring-inner"></div>
                <div class="auth-circle-core shadow-2xl">
                    <div class="scan-laser-line"></div>
                    <div class="w-12 h-12 rounded-2xl bg-white flex items-center justify-center p-1.5 shadow-xl overflow-hidden shrink-0 z-10 ring-2 ring-white/40">
                        <img src="/logo_icon.png?v=<?= time() ?>" alt="EcoFone Logo" width="36" height="36" class="w-full h-full object-contain">
                    </div>
                </div>
            </div>

            <!-- Identity Badges -->
            <div class="inline-flex items-center gap-1.5 px-3.5 py-1.2 rounded-full bg-indigo-500/15 border border-indigo-500/30 text-indigo-300 text-[11px] font-extrabold tracking-wide uppercase mb-2 shadow-inner">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>Round 2FA Authentication</span>
            </div>

            <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                Enter Verification Code
            </h1>
            <p class="text-xs text-slate-400 mt-1 max-w-xs mx-auto leading-relaxed">
                6-digit code has been dispatched to your email address.
            </p>
        </div>

        <!-- 🔔 Flash Alerts -->
        <?php $flash = getFlash(); if ($flash): ?>
            <div id="flashAlert" class="mb-5 p-4 rounded-2xl text-xs font-semibold <?= $flash['type'] === 'error' ? 'bg-rose-500/15 text-rose-300 border border-rose-500/30' : ($flash['type'] === 'success' ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30' : 'bg-blue-500/15 text-blue-300 border border-blue-500/30') ?> flex items-start gap-3 shadow-2xl backdrop-blur-2xl transition-all duration-300">
                <i data-lucide="<?= $flash['type'] === 'error' ? 'alert-circle' : ($flash['type'] === 'success' ? 'check-circle-2' : 'info') ?>" class="w-4 h-4 shrink-0 mt-0.5 <?= $flash['type'] === 'error' ? 'text-rose-400' : ($flash['type'] === 'success' ? 'text-emerald-400' : 'text-blue-400') ?>"></i>
                <div class="leading-relaxed flex-1"><?= $flash['message'] ?></div>
            </div>
        <?php endif; ?>

        <!-- 💎 Glassmorphic Interactive Floating Card -->
        <div class="master-auth-card p-6 sm:p-8 space-y-6">
            <!-- Email Identity Tag -->
            <div class="bg-slate-950/75 rounded-2xl p-3.5 border border-white/10 flex items-center justify-between gap-3 shadow-inner">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-indigo-500/20 text-indigo-300 flex items-center justify-center shrink-0 border border-indigo-500/30 shadow-md">
                        <i data-lucide="mail" class="w-4 h-4"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="text-[9px] uppercase font-black text-slate-400 tracking-wider">Account Email</div>
                        <div class="text-xs font-bold text-slate-100 truncate font-mono"><?= htmlspecialchars($user['email']) ?></div>
                    </div>
                </div>
                <a href="?page=login" class="shrink-0 text-[11px] font-bold text-indigo-400 hover:text-indigo-300 transition hover:underline">
                    Edit
                </a>
            </div>

            <!-- 🔢 Interactive 6-Box Neon Form -->
            <form action="?action=verify-otp" method="POST" id="otpForm" class="space-y-6">
                <input type="hidden" name="user_id" value="<?= (int)($userId ?? ($_SESSION['pending_otp_user_id'] ?? ($_COOKIE['pending_otp_uid'] ?? 0))) ?>">
                <input type="hidden" name="user_email" value="<?= htmlspecialchars($user['email'] ?? ($_SESSION['pending_otp_email'] ?? ($_COOKIE['pending_otp_email'] ?? ''))) ?>">
                <input type="hidden" name="otp" id="hiddenOtp">

                <div>
                    <!-- 6-Box Input Grid -->
                    <div class="flex items-center justify-center gap-2 sm:gap-2.5" id="otpBoxContainer">
                        <?php for ($i = 0; $i < 6; $i++): ?>
                            <input 
                                type="text" 
                                inputmode="numeric" 
                                maxlength="1" 
                                pattern="[0-9]" 
                                required 
                                class="neon-digit-input font-mono-code"
                                data-index="<?= $i ?>"
                                autocomplete="one-time-code"
                            >
                        <?php endfor; ?>
                    </div>

                    <div class="flex items-center justify-between text-[11px] text-slate-400 mt-3.5 px-1 font-medium">
                        <span class="flex items-center gap-1.5 text-amber-300">
                            <i data-lucide="shield-check" class="w-3.5 h-3.5 text-emerald-400"></i> Code valid 10m
                        </span>
                        <span class="text-emerald-400 font-bold flex items-center gap-1">
                            <i data-lucide="zap" class="w-3.5 h-3.5"></i> Auto-Submit on 6th
                        </span>
                    </div>
                </div>

                <!-- 🚀 Gradient Pulse Button -->
                <button 
                    type="submit" 
                    id="submitBtn"
                    class="neon-pulse-btn w-full py-4 px-6 text-sm font-black text-white flex items-center justify-center gap-2 cursor-pointer active:scale-95"
                >
                    <i data-lucide="fingerprint" class="w-5 h-5"></i>
                    <span id="submitBtnText">Authenticate & Continue</span>
                </button>
            </form>

            <!-- 🔄 Resend Section -->
            <div class="pt-4 border-t border-white/10 flex flex-col items-center justify-center gap-3 text-xs">
                <?php if ($resendCount >= 5): ?>
                    <div class="w-full text-center p-3 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-300 text-xs flex items-center gap-2">
                        <i data-lucide="info" class="w-4 h-4 shrink-0 text-amber-400"></i>
                        <span>Daily limit reached (5/5). Please check your inbox.</span>
                    </div>
                <?php else: ?>
                    <div class="text-slate-400 text-center text-xs">
                        Didn't receive the email code?
                    </div>

                    <div class="w-full text-center">
                        <button 
                            id="resendBtn" 
                            disabled 
                            onclick="window.location.href='?action=resend-otp'"
                            class="w-full py-3 px-4 rounded-2xl text-xs font-bold bg-white/5 hover:bg-white/10 text-slate-400 border border-white/10 cursor-not-allowed transition-all duration-200 flex items-center justify-center gap-2"
                        >
                            <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                            <span id="resendText">Resend Code (<?= $cooldownRemaining ?>s)</span>
                        </button>
                    </div>
                <?php endif; ?>

                <div class="pt-1">
                    <a href="?page=login" class="text-xs text-slate-400 hover:text-white transition inline-flex items-center gap-1.5 font-bold">
                        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Back to Email Login
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        const inputs = document.querySelectorAll(".neon-digit-input");
        const hiddenOtp = document.getElementById("hiddenOtp");
        const otpForm = document.getElementById("otpForm");
        const submitBtn = document.getElementById("submitBtn");
        const submitBtnText = document.getElementById("submitBtnText");
        const outerRing = document.querySelector(".auth-radar-ring-outer");
        const innerRing = document.querySelector(".auth-radar-ring-inner");

        if (inputs.length > 0) {
            setTimeout(() => inputs[0].focus(), 250);

            inputs.forEach((input, index) => {
                input.addEventListener("input", (e) => {
                    const val = e.target.value.replace(/[^0-9]/g, "");
                    e.target.value = val;

                    if (val) {
                        input.classList.add('has-digit');
                        // Speed up radar spin during typing
                        if (outerRing) outerRing.style.animationDuration = '4s';
                        if (innerRing) innerRing.style.animationDuration = '2s';

                        if (index < inputs.length - 1) {
                            inputs[index + 1].focus();
                        }
                    } else {
                        input.classList.remove('has-digit');
                    }

                    checkCompletion();
                });

                input.addEventListener("keydown", (e) => {
                    if (e.key === "Backspace") {
                        if (!e.target.value && index > 0) {
                            inputs[index - 1].focus();
                            inputs[index - 1].value = '';
                            inputs[index - 1].classList.remove('has-digit');
                        } else {
                            e.target.value = '';
                            e.target.classList.remove('has-digit');
                        }
                        checkCompletion();
                    }
                });

                input.addEventListener("paste", (e) => {
                    e.preventDefault();
                    const pastedData = (e.clipboardData || window.clipboardData).getData("text").replace(/[^0-9]/g, "").slice(0, 6);
                    if (pastedData) {
                        pastedData.split("").forEach((char, i) => {
                            if (inputs[i]) {
                                inputs[i].value = char;
                                inputs[i].classList.add('has-digit');
                            }
                        });
                        const nextIdx = Math.min(pastedData.length, inputs.length - 1);
                        inputs[nextIdx].focus();
                        checkCompletion();
                    }
                });
            });
        }

        function checkCompletion() {
            let fullCode = "";
            inputs.forEach((inp) => { fullCode += inp.value; });
            hiddenOtp.value = fullCode;

            if (fullCode.length === 6) {
                inputs.forEach(inp => inp.classList.add('auth-success'));
                if (outerRing) {
                    outerRing.style.borderColor = '#10b981';
                    outerRing.style.animationDuration = '1.5s';
                }
                submitBtnText.innerHTML = `<span class="inline-block animate-spin mr-1">🌀</span> Authenticating...`;
                setTimeout(() => {
                    otpForm.submit();
                }, 220);
            } else {
                inputs.forEach(inp => inp.classList.remove('auth-success'));
                if (outerRing) outerRing.style.borderColor = 'rgba(99, 102, 241, 0.45)';
            }
        }

        if (otpForm) {
            otpForm.addEventListener("submit", (e) => {
                let fullCode = "";
                inputs.forEach((inp) => { fullCode += inp.value; });
                hiddenOtp.value = fullCode;
                if (fullCode.length !== 6) {
                    e.preventDefault();
                    alert("Please enter the complete 6-digit code sent to your email.");
                }
            });
        }

        // Live Countdown
        let secondsLeft = <?= $cooldownRemaining ?>;
        const resendBtn = document.getElementById("resendBtn");
        const resendText = document.getElementById("resendText");

        function updateTimer() {
            if (!resendBtn || !resendText) return;

            if (secondsLeft > 0) {
                resendBtn.disabled = true;
                resendBtn.className = "w-full py-3 px-4 rounded-2xl text-xs font-bold bg-white/5 text-slate-500 border border-white/10 cursor-not-allowed transition flex items-center justify-center gap-2";
                resendText.innerText = `Resend Code (${secondsLeft}s)`;
                secondsLeft--;
                setTimeout(updateTimer, 1000);
            } else {
                resendBtn.disabled = false;
                resendBtn.className = "w-full py-3 px-4 rounded-2xl text-xs font-extrabold bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 border border-indigo-500/40 cursor-pointer transition shadow-lg shadow-indigo-600/20 flex items-center justify-center gap-2";
                resendText.innerText = "Resend Verification Code";
            }
        }

        if (secondsLeft >= 0) {
            updateTimer();
        }

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