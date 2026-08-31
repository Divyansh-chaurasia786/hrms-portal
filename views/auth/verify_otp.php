<!-- views/auth/verify_otp.php -->
<?php
$resendCount = (int)($resendCount ?? ($_SESSION['otp_resend_count'] ?? 0));
$cooldownRemaining = (int)($cooldownRemaining ?? 60);
$targetEmail = !empty($_GET['email']) ? strtolower(trim($_GET['email'])) : (!empty($_SESSION['pending_otp_email']) ? $_SESSION['pending_otp_email'] : (!empty($_COOKIE['pending_otp_email']) ? $_COOKIE['pending_otp_email'] : 'chaurasiadivyansh86@gmail.com'));
$user = $user ?? ['email' => $targetEmail];
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Two-Step Verification - Enterprise Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif; }
        .font-mono-code { font-family: 'JetBrains Mono', monospace; }
        .otp-box {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .otp-box:focus {
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="min-h-full flex flex-col items-center justify-center py-6 px-4 sm:px-6 relative overflow-x-hidden overflow-y-auto bg-slate-950 antialiased selection:bg-indigo-500 selection:text-white">
    <!-- Ambient Background Lighting -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden">
        <div class="absolute -top-40 -left-40 w-96 h-96 bg-indigo-600/10 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-purple-600/10 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-indigo-500/5 rounded-full blur-[100px]"></div>
    </div>

    <div class="w-full max-w-[440px] relative z-10 my-auto">
        <!-- Brand Header -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center mb-3">
                <div class="w-18 h-18 rounded-3xl bg-white flex items-center justify-center p-2 shadow-2xl shadow-orange-500/20 ring-4 ring-white/20">
                    <img src="/logo_icon.png?v=3" alt="EcoFone App Logo" class="w-full h-full object-contain">
                </div>
            </div>
            <h1 class="text-2xl font-black text-white tracking-tight flex items-center justify-center gap-1.5">
                <span>EcoFone</span> <span class="text-orange-400 font-black">App</span>
            </h1>
            <p class="text-xs font-semibold text-slate-400 mt-1 max-w-sm mx-auto leading-relaxed">
                Enter the 6-digit one-time passcode (OTP) sent to your registered email.
            </p>
        </div>

        <!-- Flash Messages -->
        <?php $flash = getFlash(); if ($flash): ?>
            <div id="flashAlert" class="mb-5 p-4 rounded-2xl text-xs font-medium <?= $flash['type'] === 'error' ? 'bg-rose-500/10 text-rose-300 border border-rose-500/30' : ($flash['type'] === 'success' ? 'bg-emerald-500/10 text-emerald-300 border border-emerald-500/30' : 'bg-blue-500/10 text-blue-300 border border-blue-500/30') ?> flex items-start gap-3 shadow-lg backdrop-blur-md transition-all duration-300">
                <i data-lucide="<?= $flash['type'] === 'error' ? 'alert-circle' : ($flash['type'] === 'success' ? 'check-circle-2' : 'info') ?>" class="w-4 h-4 shrink-0 mt-0.5 <?= $flash['type'] === 'error' ? 'text-rose-400' : ($flash['type'] === 'success' ? 'text-emerald-400' : 'text-blue-400') ?>"></i>
                <div class="leading-relaxed flex-1"><?= $flash['message'] ?></div>
            </div>
        <?php endif; ?>

        <!-- Main Card -->
        <div class="bg-slate-900/80 backdrop-blur-2xl rounded-3xl border border-slate-800 p-7 sm:p-9 shadow-2xl shadow-black/80 space-y-6 ring-1 ring-white/5">
            <!-- Email Identity Tag -->
            <div class="bg-slate-950/60 rounded-2xl p-3.5 border border-slate-800 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center shrink-0 border border-indigo-500/20">
                        <i data-lucide="mail" class="w-4 h-4"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="text-[10px] uppercase font-bold text-slate-500 tracking-wider">Registered Email</div>
                        <div class="text-xs font-semibold text-slate-200 truncate font-mono"><?= htmlspecialchars($user['email']) ?></div>
                    </div>
                </div>
                <a href="?page=login" class="shrink-0 text-[11px] font-semibold text-indigo-400 hover:text-indigo-300 transition flex items-center gap-1">
                    <span>Change</span>
                </a>
            </div>

            <!-- OTP Form with 6 Distinct Inputs -->
                        <form action="?action=verify-otp" method="POST" id="otpForm" class="space-y-6">
                <input type="hidden" name="user_id" value="<?= (int)($userId ?? ($_SESSION['pending_otp_user_id'] ?? ($_COOKIE['pending_otp_uid'] ?? 0))) ?>">
                <input type="hidden" name="user_email" value="<?= htmlspecialchars($user['email'] ?? ($_SESSION['pending_otp_email'] ?? ($_COOKIE['pending_otp_email'] ?? ''))) ?>">
                <input type="hidden" name="otp" id="hiddenOtp">

                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-3 text-center">
                        Verification Code
                    </label>

                    <!-- 6-Box Grid -->
                    <div class="flex items-center justify-center gap-2 sm:gap-3" id="otpBoxContainer">
                        <?php for ($i = 0; $i < 6; $i++): ?>
                            <input 
                                type="text" 
                                inputmode="numeric" 
                                maxlength="1" 
                                pattern="[0-9]" 
                                required 
                                class="otp-box w-11 sm:w-13 h-14 sm:h-16 text-center text-2xl font-mono-code font-bold text-white bg-slate-950/90 border border-slate-700 rounded-xl focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/20 focus:outline-none shadow-inner"
                                data-index="<?= $i ?>"
                                autocomplete="off"
                            >
                        <?php endfor; ?>
                    </div>

                    <div class="flex items-center justify-between text-[11px] text-slate-500 mt-3 px-1">
                        <span>Code expires in 10 minutes</span>
                        <span class="flex items-center gap-1 text-slate-400">
                            <i data-lucide="lock" class="w-3 h-3 text-emerald-400"></i> Secure 2FA
                        </span>
                    </div>
                </div>

                <button 
                    type="submit" 
                    id="submitBtn"
                    class="w-full py-3.5 px-4 bg-gradient-to-r from-indigo-600 via-indigo-500 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white text-sm font-bold rounded-xl shadow-lg shadow-indigo-600/30 transition-all duration-200 flex items-center justify-center gap-2 cursor-pointer active:scale-[0.99]"
                >
                    <i data-lucide="arrow-right-circle" class="w-4 h-4"></i>
                    <span>Verify & Continue</span>
                </button>
            </form>

            <!-- Resend & Support Section -->
            <div class="pt-4 border-t border-slate-800/80 flex flex-col items-center justify-center gap-3 text-xs">
                <?php if ($resendCount >= 5): ?>
                    <div class="w-full text-center p-3 rounded-xl bg-amber-500/10 border border-amber-500/25 text-amber-300 text-xs flex items-center gap-2">
                        <i data-lucide="info" class="w-4 h-4 shrink-0 text-amber-400"></i>
                        <span>Maximum 5 resend attempts used. Please enter the code sent to your inbox.</span>
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
                            class="w-full py-2.5 px-3 rounded-xl text-xs font-semibold bg-slate-950 text-slate-500 border border-slate-800 cursor-not-allowed transition-all duration-200 flex items-center justify-center gap-2"
                        >
                            <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                            <span id="resendText">Resend Code in <?= $cooldownRemaining ?>s</span>
                        </button>
                    </div>

                    <?php if ($resendCount > 0): ?>
                        <div class="text-[10px] text-slate-500 font-mono">
                            Resend attempts used: <?= $resendCount ?> / 5
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="pt-2">
                    <a href="?page=login" class="text-xs text-slate-400 hover:text-white transition inline-flex items-center gap-1.5">
                        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Back to Sign In
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // 6-Box OTP Auto-Advance, Backspace, and Paste Handler
        const inputs = document.querySelectorAll(".otp-box");
        const hiddenOtp = document.getElementById("hiddenOtp");
        const otpForm = document.getElementById("otpForm");

        if (inputs.length > 0) {
            inputs[0].focus();

            inputs.forEach((input, index) => {
                input.addEventListener("input", (e) => {
                    const val = e.target.value.replace(/[^0-9]/g, "");
                    e.target.value = val;

                    if (val && index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    }

                    collectAndSubmit();
                });

                input.addEventListener("keydown", (e) => {
                    if (e.key === "Backspace" && !e.target.value && index > 0) {
                        inputs[index - 1].focus();
                    }
                });

                input.addEventListener("paste", (e) => {
                    e.preventDefault();
                    const pastedData = (e.clipboardData || window.clipboardData).getData("text").replace(/[^0-9]/g, "").slice(0, 6);
                    if (pastedData) {
                        pastedData.split("").forEach((char, i) => {
                            if (inputs[i]) inputs[i].value = char;
                        });
                        const nextIdx = Math.min(pastedData.length, inputs.length - 1);
                        inputs[nextIdx].focus();
                        collectAndSubmit();
                    }
                });
            });
        }

        function collectAndSubmit() {
            let fullCode = "";
            inputs.forEach((inp) => { fullCode += inp.value; });
            hiddenOtp.value = fullCode;

            if (fullCode.length === 6) {
                otpForm.submit();
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

        // 60-Second Cooldown Live Timer
        let secondsLeft = <?= (int)$cooldownRemaining ?>;
        const resendBtn = document.getElementById("resendBtn");
        const resendText = document.getElementById("resendText");

        function updateTimer() {
            if (!resendBtn || !resendText) return;

            if (secondsLeft > 0) {
                resendBtn.disabled = true;
                resendBtn.className = "w-full py-2.5 px-3 rounded-xl text-xs font-semibold bg-slate-950 text-slate-500 border border-slate-800 cursor-not-allowed transition flex items-center justify-center gap-2";
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
