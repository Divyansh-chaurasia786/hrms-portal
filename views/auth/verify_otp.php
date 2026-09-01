<!-- views/auth/verify_otp.php -->
<?php
$resendCount = (int)($resendCount ?? ($_SESSION['otp_resend_count'] ?? 0));
$cooldownRemaining = (int)($cooldownRemaining ?? 60);
$targetEmail = !empty($_GET['email']) ? strtolower(trim($_GET['email'])) : (!empty($_SESSION['pending_otp_email']) ? $_SESSION['pending_otp_email'] : (!empty($_COOKIE['pending_otp_email']) ? $_COOKIE['pending_otp_email'] : ''));
$user = $user ?? ['email' => $targetEmail];
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-[#07090e] text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Verify Identity • EcoFone</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@700;800&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #07090e;
        }
        .font-mono-code { font-family: 'JetBrains Mono', monospace; }

        /* 🌌 Glowing Ambient Orbs */
        @keyframes floatSlow {
            0%, 100% { transform: translate(0px, 0px) scale(1); }
            50% { transform: translate(30px, -20px) scale(1.1); }
        }
        @keyframes floatReverse {
            0%, 100% { transform: translate(0px, 0px) scale(1); }
            50% { transform: translate(-30px, 20px) scale(1.08); }
        }
        @keyframes haloPulse {
            0%, 100% { transform: scale(1); opacity: 0.4; }
            50% { transform: scale(1.15); opacity: 0.8; }
        }
        @keyframes ringSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @keyframes blinkCursor {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }

        .orb-1 { animation: floatSlow 10s ease-in-out infinite; }
        .orb-2 { animation: floatReverse 12s ease-in-out infinite; }
        .halo-glow { animation: haloPulse 3s ease-in-out infinite; }

        /* 💎 Usman Shams Style Glassmorphic Card */
        .reel-glass-card {
            background: linear-gradient(180deg, rgba(22, 27, 44, 0.75) 0%, rgba(11, 15, 26, 0.85) 100%);
            backdrop-filter: blur(40px);
            -webkit-backdrop-filter: blur(40px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 30px 80px -20px rgba(0, 0, 0, 0.9), 
                        0 0 50px -10px rgba(99, 102, 241, 0.2),
                        inset 0 1px 0 rgba(255, 255, 255, 0.15);
            border-radius: 32px;
        }

        /* 🔢 Reel Style OTP Inputs */
        .otp-input-box {
            position: relative;
            width: 48px;
            height: 60px;
            background: rgba(8, 12, 22, 0.75);
            border: 1.5px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            color: #ffffff;
            font-size: 24px;
            font-weight: 800;
            text-align: center;
            transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: inset 0 2px 6px rgba(0, 0, 0, 0.5);
        }
        @media (min-width: 640px) {
            .otp-input-box {
                width: 54px;
                height: 66px;
                font-size: 26px;
                border-radius: 18px;
            }
        }
        .otp-input-box:focus {
            outline: none;
            background: rgba(17, 24, 39, 0.95);
            border-color: #6366f1;
            box-shadow: 0 0 25px rgba(99, 102, 241, 0.5), inset 0 2px 4px rgba(0, 0, 0, 0.5);
            transform: translateY(-4px) scale(1.05);
        }
        .otp-input-box.filled {
            border-color: rgba(99, 102, 241, 0.7);
            background: rgba(15, 23, 42, 0.9);
            color: #a5b4fc;
        }
        .otp-input-box.success {
            border-color: #10b981 !important;
            box-shadow: 0 0 25px rgba(16, 185, 129, 0.5) !important;
            color: #34d399 !important;
        }

        /* 🚀 Reel Gradient Glow Button */
        .reel-glow-btn {
            position: relative;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #ec4899 100%);
            border-radius: 20px;
            color: white;
            font-weight: 800;
            letter-spacing: 0.02em;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 30px -5px rgba(99, 102, 241, 0.5), 0 0 20px rgba(139, 92, 246, 0.3);
        }
        .reel-glow-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px -5px rgba(99, 102, 241, 0.7), 0 0 30px rgba(236, 72, 153, 0.4);
        }
        .reel-glow-btn:active {
            transform: scale(0.98);
        }

        /* 🏷️ 3D Security Floating Icon */
        .floating-3d-badge {
            position: relative;
            width: 76px;
            height: 76px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 24px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(249, 115, 22, 0.15) 100%);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 20px 40px -10px rgba(99, 102, 241, 0.4);
        }
    </style>
</head>
<body class="min-h-full flex flex-col items-center justify-center py-6 px-4 sm:px-6 relative overflow-x-hidden overflow-y-auto antialiased selection:bg-indigo-500 selection:text-white">
    <!-- 🌌 Dynamic Deep Cyber Ambient Mesh Background -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden">
        <div class="orb-1 absolute -top-40 -left-40 w-[550px] h-[550px] bg-indigo-600/25 rounded-full blur-[130px]"></div>
        <div class="orb-2 absolute -bottom-40 -right-40 w-[550px] h-[550px] bg-purple-600/20 rounded-full blur-[130px]"></div>
        <div class="absolute top-1/3 left-1/2 -translate-x-1/2 w-[600px] h-[600px] bg-orange-500/10 rounded-full blur-[160px]"></div>
    </div>

    <div class="w-full max-w-[430px] relative z-10 my-auto">
        <!-- 🛡️ 3D Floating Security Shield with Glowing Halo Rings -->
        <div class="text-center mb-6 relative">
            <div class="floating-3d-badge mb-3">
                <div class="halo-glow absolute inset-0 bg-indigo-500/30 rounded-2xl blur-xl"></div>
                <div class="w-14 h-14 rounded-2xl bg-white flex items-center justify-center p-2 shadow-2xl overflow-hidden shrink-0 z-10 ring-2 ring-white/30">
                    <img src="/logo_icon.png?v=<?= time() ?>" alt="EcoFone Logo" width="40" height="40" class="w-full h-full object-contain">
                </div>
            </div>
            
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-300 text-[11px] font-bold tracking-wide uppercase mb-2">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>Two-Factor Authentication</span>
            </div>

            <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                Enter Verification Code
            </h1>
            <p class="text-xs text-slate-400 mt-1.5 max-w-xs mx-auto leading-relaxed">
                We've sent a 6-digit verification code to your registered email address.
            </p>
        </div>

        <!-- 🔔 Flash Alerts -->
        <?php $flash = getFlash(); if ($flash): ?>
            <div id="flashAlert" class="mb-5 p-4 rounded-2xl text-xs font-semibold <?= $flash['type'] === 'error' ? 'bg-rose-500/15 text-rose-300 border border-rose-500/30' : ($flash['type'] === 'success' ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30' : 'bg-blue-500/15 text-blue-300 border border-blue-500/30') ?> flex items-start gap-3 shadow-xl backdrop-blur-xl transition-all duration-300">
                <i data-lucide="<?= $flash['type'] === 'error' ? 'alert-circle' : ($flash['type'] === 'success' ? 'check-circle-2' : 'info') ?>" class="w-4 h-4 shrink-0 mt-0.5 <?= $flash['type'] === 'error' ? 'text-rose-400' : ($flash['type'] === 'success' ? 'text-emerald-400' : 'text-blue-400') ?>"></i>
                <div class="leading-relaxed flex-1"><?= $flash['message'] ?></div>
            </div>
        <?php endif; ?>

        <!-- 💎 Main Reel Glassmorphic Card -->
        <div class="reel-glass-card p-6 sm:p-8 space-y-6">
            <!-- Email Identity Pill -->
            <div class="bg-black/40 rounded-2xl p-3.5 border border-white/5 flex items-center justify-between gap-3 shadow-inner">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-indigo-500/20 text-indigo-300 flex items-center justify-center shrink-0 border border-indigo-500/30">
                        <i data-lucide="mail" class="w-4 h-4"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="text-[9px] uppercase font-extrabold text-slate-400 tracking-wider">Sent To</div>
                        <div class="text-xs font-bold text-slate-100 truncate font-mono"><?= htmlspecialchars($user['email']) ?></div>
                    </div>
                </div>
                <a href="?page=login" class="shrink-0 text-[11px] font-bold text-indigo-400 hover:text-indigo-300 transition hover:underline">
                    Edit
                </a>
            </div>

            <!-- 🔢 Interactive 6-Box OTP Form -->
            <form action="?action=verify-otp" method="POST" id="otpForm" class="space-y-6">
                <input type="hidden" name="user_id" value="<?= (int)($userId ?? ($_SESSION['pending_otp_user_id'] ?? ($_COOKIE['pending_otp_uid'] ?? 0))) ?>">
                <input type="hidden" name="user_email" value="<?= htmlspecialchars($user['email'] ?? ($_SESSION['pending_otp_email'] ?? ($_COOKIE['pending_otp_email'] ?? ''))) ?>">
                <input type="hidden" name="otp" id="hiddenOtp">

                <div>
                    <!-- 6-Box Grid -->
                    <div class="flex items-center justify-center gap-2 sm:gap-2.5" id="otpBoxContainer">
                        <?php for ($i = 0; $i < 6; $i++): ?>
                            <input 
                                type="text" 
                                inputmode="numeric" 
                                maxlength="1" 
                                pattern="[0-9]" 
                                required 
                                class="otp-input-box font-mono-code"
                                data-index="<?= $i ?>"
                                autocomplete="one-time-code"
                            >
                        <?php endfor; ?>
                    </div>

                    <div class="flex items-center justify-between text-[11px] text-slate-400 mt-3.5 px-1 font-medium">
                        <span class="flex items-center gap-1.5">
                            <i data-lucide="clock" class="w-3.5 h-3.5 text-amber-400"></i> Expires in 10 mins
                        </span>
                        <span class="text-emerald-400 font-bold flex items-center gap-1">
                            <i data-lucide="zap" class="w-3.5 h-3.5"></i> Instant Auto-Submit
                        </span>
                    </div>
                </div>

                <!-- 🚀 Gradient Glow Action Button -->
                <button 
                    type="submit" 
                    id="submitBtn"
                    class="reel-glow-btn w-full py-4 px-6 text-sm flex items-center justify-center gap-2 cursor-pointer active:scale-95"
                >
                    <i data-lucide="shield-check" class="w-4 h-4"></i>
                    <span id="submitBtnText">Verify & Continue</span>
                </button>
            </form>

            <!-- 🔄 Resend Section -->
            <div class="pt-4 border-t border-white/5 flex flex-col items-center justify-center gap-3 text-xs">
                <?php if ($resendCount >= 5): ?>
                    <div class="w-full text-center p-3 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-300 text-xs flex items-center gap-2">
                        <i data-lucide="info" class="w-4 h-4 shrink-0 text-amber-400"></i>
                        <span>Maximum resend attempts reached for today.</span>
                    </div>
                <?php else: ?>
                    <div class="text-slate-400 text-center">
                        Didn't receive the email code?
                    </div>

                    <div class="w-full text-center">
                        <button 
                            id="resendBtn" 
                            disabled 
                            onclick="window.location.href='?action=resend-otp'"
                            class="w-full py-3 px-4 rounded-2xl text-xs font-bold bg-white/5 hover:bg-white/10 text-slate-400 border border-white/5 cursor-not-allowed transition-all duration-200 flex items-center justify-center gap-2"
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

        const inputs = document.querySelectorAll(".otp-input-box");
        const hiddenOtp = document.getElementById("hiddenOtp");
        const otpForm = document.getElementById("otpForm");
        const submitBtn = document.getElementById("submitBtn");
        const submitBtnText = document.getElementById("submitBtnText");

        if (inputs.length > 0) {
            setTimeout(() => inputs[0].focus(), 200);

            inputs.forEach((input, index) => {
                input.addEventListener("input", (e) => {
                    const val = e.target.value.replace(/[^0-9]/g, "");
                    e.target.value = val;

                    if (val) {
                        input.classList.add('filled');
                        if (index < inputs.length - 1) {
                            inputs[index + 1].focus();
                        }
                    } else {
                        input.classList.remove('filled');
                    }

                    checkCompletion();
                });

                input.addEventListener("keydown", (e) => {
                    if (e.key === "Backspace") {
                        if (!e.target.value && index > 0) {
                            inputs[index - 1].focus();
                            inputs[index - 1].value = '';
                            inputs[index - 1].classList.remove('filled');
                        } else {
                            e.target.value = '';
                            e.target.classList.remove('filled');
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
                                inputs[i].classList.add('filled');
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
                inputs.forEach(inp => inp.classList.add('success'));
                submitBtnText.innerHTML = `<span class="inline-block animate-spin mr-1">⚡</span> Authenticating...`;
                setTimeout(() => {
                    otpForm.submit();
                }, 200);
            } else {
                inputs.forEach(inp => inp.classList.remove('success'));
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

        // Live Resend Countdown
        let secondsLeft = <?= $cooldownRemaining ?>;
        const resendBtn = document.getElementById("resendBtn");
        const resendText = document.getElementById("resendText");

        function updateTimer() {
            if (!resendBtn || !resendText) return;

            if (secondsLeft > 0) {
                resendBtn.disabled = true;
                resendBtn.className = "w-full py-3 px-4 rounded-2xl text-xs font-bold bg-white/5 text-slate-500 border border-white/5 cursor-not-allowed transition flex items-center justify-center gap-2";
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