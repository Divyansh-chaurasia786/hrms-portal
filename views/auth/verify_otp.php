<!-- views/auth/verify_otp.php -->
<?php
$resendCount = (int)($resendCount ?? ($_SESSION['otp_resend_count'] ?? 0));
$cooldownRemaining = (int)($cooldownRemaining ?? 60);
$targetEmail = !empty($_GET['email']) ? strtolower(trim($_GET['email'])) : (!empty($_SESSION['pending_otp_email']) ? $_SESSION['pending_otp_email'] : (!empty($_COOKIE['pending_otp_email']) ? $_COOKIE['pending_otp_email'] : ''));
$user = $user ?? ['email' => $targetEmail];
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Two-Step Verification - EcoFone Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif; }
        .font-mono-code { font-family: 'JetBrains Mono', monospace; }
        
        /* 🌌 Glowing Ambient Mesh & Gradient Rotations */
        @keyframes ambientFloat {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-20px) scale(1.06); }
        }
        @keyframes borderShimmer {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        @keyframes popDigit {
            0% { transform: scale(0.85); opacity: 0.5; }
            50% { transform: scale(1.12); }
            100% { transform: scale(1); opacity: 1; }
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

        /* 🔢 Ultra-Smooth OTP Input Boxes */
        .otp-box {
            transition: all 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
            background: rgba(2, 6, 23, 0.85);
            border: 1.5px solid rgba(51, 65, 85, 0.8);
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.6);
        }
        .otp-box:focus {
            border-color: #6366f1;
            background: rgba(15, 23, 42, 0.95);
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.4), inset 0 2px 4px rgba(0, 0, 0, 0.6);
            transform: translateY(-3px) scale(1.04);
            outline: none;
        }
        .otp-box.has-value {
            border-color: rgba(99, 102, 241, 0.8);
            color: #ffffff;
            animation: popDigit 0.18s ease-out;
        }
        .otp-box.is-complete {
            border-color: #10b981;
            box-shadow: 0 0 22px rgba(16, 185, 129, 0.45);
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
        .shimmer-btn:active {
            transform: scale(0.98);
        }

        /* 🏷️ Logo badge */
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
    <!-- 🌌 Ambient Background Lighting Spheres -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden">
        <div class="ambient-orb-1 absolute -top-32 -left-32 w-[420px] h-[420px] bg-indigo-600/20 rounded-full blur-[110px]"></div>
        <div class="ambient-orb-2 absolute -bottom-32 -right-32 w-[420px] h-[420px] bg-purple-600/20 rounded-full blur-[110px]"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[550px] h-[550px] bg-orange-500/10 rounded-full blur-[140px]"></div>
    </div>

    <div class="w-full max-w-[430px] relative z-10 my-auto">
        <!-- 🏷️ Brand Header -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center mb-3">
                <div class="brand-logo-wrap ring-4 ring-white/10">
                    <img src="/logo_icon.png?v=3" alt="EcoFone App Logo" width="44" height="44" class="brand-logo-img">
                </div>
            </div>
            <h1 class="text-2xl font-black text-white tracking-tight flex items-center justify-center gap-1.5">
                <span>EcoFone</span> <span class="text-orange-400 font-black">App</span>
            </h1>
            <p class="text-xs font-semibold text-slate-400 mt-1 max-w-sm mx-auto leading-relaxed">
                Enter the 6-digit one-time passcode (OTP) sent to your registered email.
            </p>
        </div>

        <!-- 🔔 Flash Alert Messages -->
        <?php $flash = getFlash(); if ($flash): ?>
            <div id="flashAlert" class="mb-5 p-4 rounded-2xl text-xs font-semibold <?= $flash['type'] === 'error' ? 'bg-rose-500/15 text-rose-300 border border-rose-500/30' : ($flash['type'] === 'success' ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30' : 'bg-blue-500/15 text-blue-300 border border-blue-500/30') ?> flex items-start gap-3 shadow-lg backdrop-blur-md transition-all duration-300">
                <i data-lucide="<?= $flash['type'] === 'error' ? 'alert-circle' : ($flash['type'] === 'success' ? 'check-circle-2' : 'info') ?>" class="w-4 h-4 shrink-0 mt-0.5 <?= $flash['type'] === 'error' ? 'text-rose-400' : ($flash['type'] === 'success' ? 'text-emerald-400' : 'text-blue-400') ?>"></i>
                <div class="leading-relaxed flex-1"><?= $flash['message'] ?></div>
            </div>
        <?php endif; ?>

        <!-- 💎 Main Glassmorphic Card -->
        <div class="glass-glow-card rounded-3xl p-7 sm:p-8 space-y-6">
            <!-- Email Identity Chip -->
            <div class="bg-slate-950/70 rounded-2xl p-3.5 border border-slate-800/80 flex items-center justify-between gap-3 shadow-inner">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-indigo-500/15 text-indigo-400 flex items-center justify-center shrink-0 border border-indigo-500/25">
                        <i data-lucide="mail" class="w-4 h-4"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="text-[10px] uppercase font-bold text-slate-500 tracking-wider">Registered Email</div>
                        <div class="text-xs font-semibold text-slate-200 truncate font-mono"><?= htmlspecialchars($user['email']) ?></div>
                    </div>
                </div>
                <a href="?page=login" class="shrink-0 text-[11px] font-bold text-indigo-400 hover:text-indigo-300 transition flex items-center gap-1 hover:underline">
                    <span>Change</span>
                </a>
            </div>

            <!-- OTP Form with 6 Smooth Interactive Boxes -->
            <form action="?action=verify-otp" method="POST" id="otpForm" class="space-y-6">
                <input type="hidden" name="user_id" value="<?= (int)($userId ?? ($_SESSION['pending_otp_user_id'] ?? ($_COOKIE['pending_otp_uid'] ?? 0))) ?>">
                <input type="hidden" name="user_email" value="<?= htmlspecialchars($user['email'] ?? ($_SESSION['pending_otp_email'] ?? ($_COOKIE['pending_otp_email'] ?? ''))) ?>">
                <input type="hidden" name="otp" id="hiddenOtp">

                <div>
                    <div class="flex items-center justify-between mb-3 px-1">
                        <label class="text-xs font-extrabold text-slate-300 uppercase tracking-wider">
                            Verification Code
                        </label>
                        <span class="text-[10px] text-indigo-400 font-mono font-bold flex items-center gap-1">
                            <i data-lucide="shield-check" class="w-3 h-3 text-emerald-400"></i> Auto-Sync
                        </span>
                    </div>

                    <!-- 6-Box Grid with Motion Micro-Interactions -->
                    <div class="flex items-center justify-center gap-2 sm:gap-2.5" id="otpBoxContainer">
                        <?php for ($i = 0; $i < 6; $i++): ?>
                            <input 
                                type="text" 
                                inputmode="numeric" 
                                maxlength="1" 
                                pattern="[0-9]" 
                                required 
                                class="otp-box w-11 sm:w-13 h-14 sm:h-16 text-center text-2xl font-mono-code font-black text-white rounded-2xl"
                                data-index="<?= $i ?>"
                                autocomplete="one-time-code"
                            >
                        <?php endfor; ?>
                    </div>

                    <div class="flex items-center justify-between text-[11px] text-slate-500 mt-3 px-1">
                        <span>Code valid for 10 minutes</span>
                        <span class="text-slate-400 font-medium">Auto-submits on 6th digit</span>
                    </div>
                </div>

                <!-- Submit Button with Shimmer Sweep -->
                <button 
                    type="submit" 
                    id="submitBtn"
                    class="shimmer-btn w-full py-3.5 px-4 text-white text-sm font-extrabold rounded-2xl shadow-xl flex items-center justify-center gap-2 cursor-pointer"
                >
                    <i data-lucide="arrow-right-circle" class="w-4 h-4"></i>
                    <span id="submitBtnText">Verify & Continue</span>
                </button>
            </form>

            <!-- 🔄 Resend & Navigation -->
            <div class="pt-4 border-t border-slate-800/80 flex flex-col items-center justify-center gap-3 text-xs">
                <?php if ($resendCount >= 5): ?>
                    <div class="w-full text-center p-3 rounded-2xl bg-amber-500/10 border border-amber-500/25 text-amber-300 text-xs flex items-center gap-2">
                        <i data-lucide="info" class="w-4 h-4 shrink-0 text-amber-400"></i>
                        <span>Maximum 5 resend attempts used. Please enter the code sent to your inbox.</span>
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
                            class="w-full py-2.5 px-3 rounded-xl text-xs font-semibold bg-slate-950/80 text-slate-500 border border-slate-800 cursor-not-allowed transition-all duration-200 flex items-center justify-center gap-2"
                        >
                            <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                            <span id="resendText">Resend Code in <?= $cooldownRemaining ?>s</span>
                        </button>
                    </div>

                    <?php if ($resendCount > 0): ?>
                        <div class="text-[10px] text-slate-500 font-mono">
                            Resend attempts: <?= $resendCount ?> / 5
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="pt-1">
                    <a href="?page=login" class="text-xs text-slate-400 hover:text-white transition inline-flex items-center gap-1.5 font-medium">
                        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Back to Sign In
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        const inputs = document.querySelectorAll(".otp-box");
        const hiddenOtp = document.getElementById("hiddenOtp");
        const otpForm = document.getElementById("otpForm");
        const submitBtn = document.getElementById("submitBtn");
        const submitBtnText = document.getElementById("submitBtnText");

        if (inputs.length > 0) {
            // Auto focus 1st box
            setTimeout(() => inputs[0].focus(), 150);

            inputs.forEach((input, index) => {
                input.addEventListener("input", (e) => {
                    const val = e.target.value.replace(/[^0-9]/g, "");
                    e.target.value = val;

                    if (val) {
                        input.classList.add('has-value');
                        if (index < inputs.length - 1) {
                            inputs[index + 1].focus();
                        }
                    } else {
                        input.classList.remove('has-value');
                    }

                    checkCompletion();
                });

                input.addEventListener("keydown", (e) => {
                    if (e.key === "Backspace") {
                        if (!e.target.value && index > 0) {
                            inputs[index - 1].focus();
                            inputs[index - 1].value = '';
                            inputs[index - 1].classList.remove('has-value');
                        } else {
                            e.target.value = '';
                            e.target.classList.remove('has-value');
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
                                inputs[i].classList.add('has-value');
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
                inputs.forEach(inp => inp.classList.add('is-complete'));
                submitBtnText.innerHTML = `<span class="inline-block animate-spin mr-1">⏳</span> Verifying Code...`;
                setTimeout(() => {
                    otpForm.submit();
                }, 220);
            } else {
                inputs.forEach(inp => inp.classList.remove('is-complete'));
            }
        }

        if (otpForm) {
            otpForm.addEventListener("submit", (e) => {
                let fullCode = "";
                inputs.forEach((inp) => { fullCode += inp.value; });
                hiddenOtp.value = fullCode;
                if (fullCode.length !== 6) {
                    e.preventDefault();
                    alert("Please enter the complete 6-digit verification code sent to your email.");
                }
            });
        }

        // Live Resend Cooldown Countdown
        let secondsLeft = <?= $cooldownRemaining ?>;
        const resendBtn = document.getElementById("resendBtn");
        const resendText = document.getElementById("resendText");

        function updateTimer() {
            if (!resendBtn || !resendText) return;

            if (secondsLeft > 0) {
                resendBtn.disabled = true;
                resendBtn.className = "w-full py-2.5 px-3 rounded-xl text-xs font-semibold bg-slate-950/80 text-slate-500 border border-slate-800 cursor-not-allowed transition flex items-center justify-center gap-2";
                resendText.innerText = `Resend Code in ${secondsLeft}s`;
                secondsLeft--;
                setTimeout(updateTimer, 1000);
            } else {
                resendBtn.disabled = false;
                resendBtn.className = "w-full py-2.5 px-3 rounded-xl text-xs font-bold bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 border border-indigo-500/40 cursor-pointer transition shadow-sm flex items-center justify-center gap-2";
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