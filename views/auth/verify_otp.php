<!-- views/auth/verify_otp.php -->
<?php
$resendCount = (int)($resendCount ?? ($_SESSION['otp_resend_count'] ?? 0));
$cooldownRemaining = (int)($cooldownRemaining ?? 60);
$targetEmail = !empty($_GET['email']) ? strtolower(trim($_GET['email'])) : (!empty($_SESSION['pending_otp_email']) ? $_SESSION['pending_otp_email'] : (!empty($_COOKIE['pending_otp_email']) ? $_COOKIE['pending_otp_email'] : ''));
$user = $user ?? ['email' => $targetEmail];
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-[#000000] text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Two-Factor Authentication • EcoFone</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@700;800&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Plus Jakarta Sans', Roboto, Helvetica, Arial, sans-serif;
            background-color: #000000;
            min-height: 100vh;
        }
        .font-mono-code { font-family: 'JetBrains Mono', monospace; }

        /* 🟣 Instagram / Viral Reel Glowing Ambient Backing */
        @keyframes igOrbFloat {
            0%, 100% { transform: scale(1) translate(0, 0); opacity: 0.35; }
            50% { transform: scale(1.18) translate(20px, -20px); opacity: 0.65; }
        }
        @keyframes igSpinGrad {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @keyframes pulseDot {
            0%, 100% { transform: scale(1); box-shadow: 0 0 10px rgba(168, 85, 247, 0.4); }
            50% { transform: scale(1.12); box-shadow: 0 0 25px rgba(236, 72, 153, 0.8); }
        }
        @keyframes popIn {
            0% { transform: scale(0.6); opacity: 0; }
            70% { transform: scale(1.15); }
            100% { transform: scale(1); opacity: 1; }
        }

        .ig-bg-glow {
            position: fixed;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
        }
        .ig-orb-1 {
            position: absolute;
            top: -10%;
            left: 50%;
            transform: translateX(-50%);
            width: 450px;
            height: 450px;
            background: radial-gradient(circle, rgba(168, 85, 247, 0.25) 0%, rgba(236, 72, 153, 0.18) 45%, transparent 70%);
            border-radius: 50%;
            filter: blur(80px);
            animation: igOrbFloat 8s ease-in-out infinite;
        }

        /* ⭕ INSTAGRAM CIRCULAR AUTHENTICATION AVATAR / BADGE */
        .ig-circle-auth-badge {
            position: relative;
            width: 96px;
            height: 96px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .ig-gradient-ring {
            position: absolute;
            inset: -4px;
            border-radius: 50%;
            background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
            padding: 3px;
            animation: igSpinGrad 6s linear infinite;
        }
        .ig-gradient-ring::after {
            content: '';
            display: block;
            width: 100%;
            height: 100%;
            background: #000000;
            border-radius: 50%;
        }
        .ig-circle-inner-icon {
            position: relative;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #121212;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.8);
            z-index: 2;
        }

        /* 🔘 EXACT CIRCULAR ROUND OTP INPUTS (CIRCLES INSTEAD OF SQUARES) */
        .ig-round-input {
            width: 46px;
            height: 46px;
            border-radius: 50% !important;
            background: rgba(255, 255, 255, 0.06);
            border: 2px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
            font-size: 20px;
            font-weight: 800;
            text-align: center;
            transition: all 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
            outline: none;
        }
        @media (min-width: 400px) {
            .ig-round-input {
                width: 50px;
                height: 50px;
                font-size: 22px;
            }
        }
        .ig-round-input:focus {
            border-color: #e1306c;
            background: rgba(225, 48, 108, 0.12);
            box-shadow: 0 0 20px rgba(225, 48, 108, 0.5), inset 0 0 10px rgba(225, 48, 108, 0.2);
            transform: scale(1.12);
        }
        .ig-round-input.filled {
            border-color: #833ab4;
            background: linear-gradient(135deg, rgba(131, 58, 180, 0.3), rgba(225, 48, 108, 0.3));
            color: #ffffff;
            box-shadow: 0 0 15px rgba(131, 58, 180, 0.4);
            animation: popIn 0.2s ease-out;
        }
        .ig-round-input.auth-success {
            border-color: #10b981 !important;
            background: rgba(16, 185, 129, 0.2) !important;
            box-shadow: 0 0 25px rgba(16, 185, 129, 0.6) !important;
            color: #34d399 !important;
        }

        /* 🚀 Instagram Gradient Pill Button */
        .ig-gradient-btn {
            background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
            border-radius: 9999px;
            font-weight: 700;
            letter-spacing: 0.02em;
            transition: all 0.25s ease;
            box-shadow: 0 10px 25px -5px rgba(220, 39, 67, 0.5);
        }
        .ig-gradient-btn:hover {
            opacity: 0.95;
            transform: scale(1.02);
            box-shadow: 0 14px 30px -4px rgba(220, 39, 67, 0.7);
        }
        .ig-gradient-btn:active {
            transform: scale(0.98);
        }

        /* 🎴 Clean Glassmorphic Container */
        .ig-card {
            background: rgba(18, 18, 18, 0.75);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 32px;
        }
    </style>
</head>
<body class="min-h-full flex flex-col items-center justify-center py-6 px-4 relative overflow-x-hidden antialiased">
    <!-- Ambient Glow -->
    <div class="ig-bg-glow">
        <div class="ig-orb-1"></div>
    </div>

    <div class="w-full max-w-[400px] relative z-10 my-auto">
        <!-- ⭕ Instagram Style Round Profile / 2FA Biometric Ring -->
        <div class="text-center mb-6">
            <div class="ig-circle-auth-badge mb-4">
                <div class="ig-gradient-ring"></div>
                <div class="ig-circle-inner-icon">
                    <div class="w-12 h-12 rounded-full bg-white flex items-center justify-center p-1.5 shadow-md overflow-hidden">
                        <img src="/logo_icon.png?v=<?= time() ?>" alt="EcoFone" width="36" height="36" class="w-full h-full object-contain">
                    </div>
                </div>
            </div>

            <h1 class="text-2xl font-extrabold text-white tracking-tight">
                Enter Confirmation Code
            </h1>
            <p class="text-xs text-slate-400 mt-1.5 max-w-xs mx-auto leading-relaxed">
                Enter the 6-digit security code sent to <br>
                <span class="font-bold text-slate-200"><?= htmlspecialchars($user['email']) ?></span>
            </p>
        </div>

        <!-- Flash Message -->
        <?php $flash = getFlash(); if ($flash): ?>
            <div id="flashAlert" class="mb-5 p-3.5 rounded-2xl text-xs font-semibold <?= $flash['type'] === 'error' ? 'bg-rose-500/15 text-rose-300 border border-rose-500/30' : ($flash['type'] === 'success' ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30' : 'bg-blue-500/15 text-blue-300 border border-blue-500/30') ?> flex items-start gap-2.5 backdrop-blur-xl">
                <i data-lucide="<?= $flash['type'] === 'error' ? 'alert-circle' : 'check-circle-2' ?>" class="w-4 h-4 shrink-0 mt-0.5"></i>
                <div class="leading-relaxed flex-1"><?= $flash['message'] ?></div>
            </div>
        <?php endif; ?>

        <!-- 🎴 Card Container -->
        <div class="ig-card p-6 sm:p-7 space-y-6 shadow-2xl">
            <!-- 🔘 6 Circular Input Rings (Round Authentication) -->
            <form action="?action=verify-otp" method="POST" id="otpForm" class="space-y-6">
                <input type="hidden" name="user_id" value="<?= (int)($userId ?? ($_SESSION['pending_otp_user_id'] ?? ($_COOKIE['pending_otp_uid'] ?? 0))) ?>">
                <input type="hidden" name="user_email" value="<?= htmlspecialchars($user['email'] ?? ($_SESSION['pending_otp_email'] ?? ($_COOKIE['pending_otp_email'] ?? ''))) ?>">
                <input type="hidden" name="otp" id="hiddenOtp">

                <div class="flex items-center justify-center gap-2 sm:gap-2.5" id="otpBoxContainer">
                    <?php for ($i = 0; $i < 6; $i++): ?>
                        <input 
                            type="text" 
                            inputmode="numeric" 
                            maxlength="1" 
                            pattern="[0-9]" 
                            required 
                            class="ig-round-input font-mono-code"
                            data-index="<?= $i ?>"
                            autocomplete="one-time-code"
                        >
                    <?php endfor; ?>
                </div>

                <!-- 🚀 Instagram Gradient Full Pill Button -->
                <button 
                    type="submit" 
                    id="submitBtn"
                    class="ig-gradient-btn w-full py-3.5 px-6 text-sm text-white flex items-center justify-center gap-2 cursor-pointer"
                >
                    <span id="submitBtnText">Confirm & Log In</span>
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </form>

            <!-- 🔄 Resend / Change Account -->
            <div class="pt-4 border-t border-white/10 flex flex-col items-center justify-center gap-3 text-xs">
                <?php if ($resendCount >= 5): ?>
                    <div class="text-amber-400 text-center text-xs">
                        Maximum resend attempts reached for today.
                    </div>
                <?php else: ?>
                    <button 
                        id="resendBtn" 
                        disabled 
                        onclick="window.location.href='?action=resend-otp'"
                        class="text-xs font-bold text-slate-500 cursor-not-allowed transition flex items-center gap-1.5"
                    >
                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                        <span id="resendText">Request New Code (<?= $cooldownRemaining ?>s)</span>
                    </button>
                <?php endif; ?>

                <a href="?page=login" class="text-xs font-bold text-indigo-400 hover:text-indigo-300 transition">
                    Log into another account
                </a>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        const inputs = document.querySelectorAll(".ig-round-input");
        const hiddenOtp = document.getElementById("hiddenOtp");
        const otpForm = document.getElementById("otpForm");
        const submitBtnText = document.getElementById("submitBtnText");

        if (inputs.length > 0) {
            setTimeout(() => inputs[0].focus(), 250);

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
                inputs.forEach(inp => inp.classList.add('auth-success'));
                submitBtnText.innerText = "Authenticating...";
                setTimeout(() => {
                    otpForm.submit();
                }, 200);
            } else {
                inputs.forEach(inp => inp.classList.remove('auth-success'));
            }
        }

        // Countdown Timer
        let secondsLeft = <?= $cooldownRemaining ?>;
        const resendBtn = document.getElementById("resendBtn");
        const resendText = document.getElementById("resendText");

        function updateTimer() {
            if (!resendBtn || !resendText) return;

            if (secondsLeft > 0) {
                resendBtn.disabled = true;
                resendBtn.className = "text-xs font-bold text-slate-500 cursor-not-allowed transition flex items-center gap-1.5";
                resendText.innerText = `Request New Code (${secondsLeft}s)`;
                secondsLeft--;
                setTimeout(updateTimer, 1000);
            } else {
                resendBtn.disabled = false;
                resendBtn.className = "text-xs font-bold text-rose-400 hover:text-rose-300 cursor-pointer transition flex items-center gap-1.5";
                resendText.innerText = "Resend Security Code";
            }
        }

        if (secondsLeft >= 0) {
            updateTimer();
        }
    </script>
</body>
</html>