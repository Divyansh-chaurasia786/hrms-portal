<!-- views/auth/verify_otp.php -->
<?php
$resendCount = (int)($resendCount ?? ($_SESSION['otp_resend_count'] ?? 0));
$cooldownRemaining = (int)($cooldownRemaining ?? 60);
$targetEmail = !empty($_GET['email']) ? strtolower(trim($_GET['email'])) : (!empty($_SESSION['pending_otp_email']) ? $_SESSION['pending_otp_email'] : (!empty($_COOKIE['pending_otp_email']) ? $_COOKIE['pending_otp_email'] : ''));
$user = $user ?? ['email' => $targetEmail];
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-[#05070f] text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Two-Step Verification • EcoFone</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: radial-gradient(circle at 50% 15%, #0f172a 0%, #030712 100%);
            min-height: 100vh;
        }
        .font-mono-code { font-family: 'JetBrains Mono', monospace; }

        /* 🌌 Ambient Floating Mesh */
        @keyframes floatMesh {
            0%, 100% { transform: scale(1) translateY(0); opacity: 0.35; }
            50% { transform: scale(1.15) translateY(-20px); opacity: 0.65; }
        }
        .ambient-mesh {
            position: fixed;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
        }
        .ambient-mesh-1 {
            position: absolute;
            top: -120px;
            left: 50%;
            transform: translateX(-50%);
            width: 520px;
            height: 520px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.25) 0%, rgba(236, 72, 153, 0.15) 50%, transparent 70%);
            border-radius: 50%;
            filter: blur(100px);
            animation: floatMesh 8s ease-in-out infinite;
        }

        /* 🎴 Glassmorphic Main Card */
        .auth-glass-card {
            background: rgba(17, 24, 39, 0.75);
            backdrop-filter: blur(35px);
            -webkit-backdrop-filter: blur(35px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 32px;
            box-shadow: 0 30px 80px -20px rgba(0, 0, 0, 0.95), 0 0 40px -10px rgba(99, 102, 241, 0.25);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* 🔢 OTP STAGE & WHEEL CONTAINER */
        #otpStage {
            position: relative;
            width: 100%;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: height 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        #otpWheel {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        /* 🔘 INDIVIDUAL DIGIT NODES */
        .otp-box-node {
            position: relative;
            width: 46px;
            height: 58px;
            background: rgba(3, 7, 18, 0.85);
            border: 1.5px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            color: #ffffff;
            font-size: 24px;
            font-weight: 800;
            text-align: center;
            outline: none;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.6);
            transition: all 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
            z-index: 10;
        }
        @media (min-width: 480px) {
            .otp-box-node {
                width: 50px;
                height: 62px;
                font-size: 26px;
            }
        }
        .otp-box-node:focus {
            border-color: #6366f1;
            background: rgba(30, 41, 59, 0.95);
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.5), inset 0 2px 4px rgba(0, 0, 0, 0.5);
            transform: translateY(-3px) scale(1.06);
        }
        .otp-box-node.filled {
            border-color: #a855f7;
            color: #ffffff;
            background: rgba(88, 28, 135, 0.3);
            box-shadow: 0 0 15px rgba(168, 85, 247, 0.4);
        }

        /* 🌀 PERFECT 100% CENTERED CIRCULAR ORBIT */
        @keyframes orbitSpinSmooth {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @keyframes counterRotateDigit {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(-360deg); }
        }
        @keyframes centerPulseGlow {
            0%, 100% { transform: translate(-50%, -50%) scale(0.92); opacity: 0.7; box-shadow: 0 0 25px rgba(99, 102, 241, 0.4); }
            50% { transform: translate(-50%, -50%) scale(1.08); opacity: 1; box-shadow: 0 0 45px rgba(99, 102, 241, 0.8); }
        }
        @keyframes shakeError {
            0%, 100% { transform: translate(-50%, -50%) translateX(0); }
            20%, 60% { transform: translate(-50%, -50%) translateX(-12px); }
            40%, 80% { transform: translate(-50%, -50%) translateX(12px); }
        }
        @keyframes successPop {
            0% { transform: scale(0.3); opacity: 0; }
            70% { transform: scale(1.15); }
            100% { transform: scale(1); opacity: 1; }
        }

        /* When circular mode is active */
        .is-circular-mode #otpStage {
            height: 240px;
        }
        .is-circular-mode #otpWheel {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 220px;
            height: 220px;
            margin-top: -110px;
            margin-left: -110px;
            border-radius: 50%;
            transform-origin: center center;
            animation: orbitSpinSmooth 2.4s linear infinite;
        }
        .is-circular-mode .otp-box-node {
            position: absolute;
            width: 44px;
            height: 44px;
            border-radius: 50% !important;
            font-size: 20px;
            pointer-events: none;
            background: linear-gradient(135deg, #6366f1 0%, #ec4899 100%);
            border: 2px solid #ffffff;
            box-shadow: 0 0 20px rgba(236, 72, 153, 0.7);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            margin: 0;
            line-height: 1;
            transform-origin: center center;
            animation: counterRotateDigit 2.4s linear infinite;
        }

        /* Center Shield Orb: Fixed exactly at center of stage */
        #centerVerificationOrb {
            display: none;
            position: absolute;
            top: 50%;
            left: 50%;
            width: 68px;
            height: 68px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.35) 0%, rgba(15, 23, 42, 0.95) 75%);
            border: 1.5px solid rgba(99, 102, 241, 0.5);
            align-items: center;
            justify-content: center;
            z-index: 5;
            pointer-events: none;
            animation: centerPulseGlow 2s ease-in-out infinite;
        }
        .is-circular-mode #centerVerificationOrb {
            display: flex;
        }

        /* Verification Outcome Badges */
        #verificationResultBadge {
            display: none;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 30;
            text-align: center;
            width: 100%;
        }

        /* State: VERIFIED (SUCCESS) */
        .is-verified #otpWheel {
            animation: none !important;
            opacity: 0;
            transform: scale(0.2) !important;
            transition: all 0.4s ease;
        }
        .is-verified #centerVerificationOrb {
            display: none;
        }
        .is-verified #verificationResultBadge {
            display: block;
            animation: successPop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        }

        /* State: WRONG OTP (FAILED) */
        .is-failed #otpWheel {
            animation: shakeError 0.5s ease-in-out !important;
        }
        .is-failed .otp-box-node {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
            border-color: #fee2e2 !important;
            box-shadow: 0 0 25px rgba(239, 68, 68, 0.8) !important;
        }
        .is-failed #centerVerificationOrb {
            border-color: #ef4444 !important;
            background: rgba(239, 68, 68, 0.25) !important;
            box-shadow: 0 0 30px rgba(239, 68, 68, 0.6) !important;
        }

        /* 🚀 Action Button */
        .submit-action-btn {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #ec4899 100%);
            border-radius: 18px;
            font-weight: 800;
            box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.5);
            transition: all 0.25s ease;
        }
        .submit-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px -5px rgba(99, 102, 241, 0.7);
        }
    </style>
</head>
<body class="min-h-full flex flex-col items-center justify-center py-6 px-4 relative overflow-x-hidden antialiased">
    <!-- Ambient Light Mesh -->
    <div class="ambient-mesh">
        <div class="ambient-mesh-1"></div>
    </div>

    <div class="w-full max-w-[420px] relative z-10 my-auto">
        <!-- Brand Header -->
        <div class="text-center mb-6">
            <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-white flex items-center justify-center p-2 shadow-2xl ring-4 ring-white/10">
                <img src="/logo_icon.png?v=<?= time() ?>" alt="EcoFone Logo" width="40" height="40" class="w-full h-full object-contain">
            </div>

            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-indigo-500/15 border border-indigo-500/30 text-indigo-300 text-[11px] font-extrabold uppercase tracking-wider mb-2">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>Two-Factor Authentication</span>
            </div>

            <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight" id="mainTitle">
                Enter Verification Code
            </h1>
            <p class="text-xs text-slate-400 mt-1 max-w-xs mx-auto leading-relaxed" id="subTitle">
                Sent to <span class="font-bold text-slate-200"><?= htmlspecialchars($user['email']) ?></span>
            </p>
        </div>

        <!-- 🎴 Main Interactive Card -->
        <div class="auth-glass-card p-6 sm:p-8 space-y-6" id="authCard">
            <!-- OTP Form & Stage -->
            <form id="otpForm" class="space-y-6" onsubmit="return false;">
                <input type="hidden" id="userEmail" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                <input type="hidden" id="userId" value="<?= (int)($userId ?? ($_SESSION['pending_otp_user_id'] ?? ($_COOKIE['pending_otp_uid'] ?? 0))) ?>">

                <!-- Dynamic Morphing Stage (Horizontal Row ➔ Rotating Orbital Circle) -->
                <div id="otpStage">
                    <!-- Center Shield Orb: Fixed at exact center -->
                    <div id="centerVerificationOrb">
                        <i data-lucide="shield-check" class="w-8 h-8 text-indigo-300"></i>
                    </div>

                    <!-- Outcome Result Badge -->
                    <div id="verificationResultBadge">
                        <div id="successContent" class="hidden space-y-2">
                            <div class="w-16 h-16 mx-auto rounded-full bg-emerald-500/20 border-2 border-emerald-400 text-emerald-400 flex items-center justify-center shadow-2xl shadow-emerald-500/50">
                                <i data-lucide="check-circle-2" class="w-10 h-10"></i>
                            </div>
                            <h2 class="text-xl font-black text-emerald-400">VERIFIED!</h2>
                            <p class="text-xs text-slate-300" id="welcomeUserText">Signing into your portal...</p>
                        </div>

                        <div id="failedContent" class="hidden space-y-2">
                            <div class="w-16 h-16 mx-auto rounded-full bg-rose-500/20 border-2 border-rose-400 text-rose-400 flex items-center justify-center shadow-2xl shadow-rose-500/50">
                                <i data-lucide="x-circle" class="w-10 h-10"></i>
                            </div>
                            <h2 class="text-xl font-black text-rose-400">WRONG OTP</h2>
                            <p class="text-xs text-rose-300" id="failedReasonText">Please check your email code and try again.</p>
                        </div>
                    </div>

                    <!-- The 6 Boxes that move in perfect circular symmetry -->
                    <div id="otpWheel">
                        <?php for ($i = 0; $i < 6; $i++): ?>
                            <input 
                                type="text" 
                                inputmode="numeric" 
                                maxlength="1" 
                                pattern="[0-9]" 
                                required 
                                class="otp-box-node font-mono-code"
                                data-index="<?= $i ?>"
                                autocomplete="one-time-code"
                            >
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="flex items-center justify-between text-[11px] text-slate-400 px-1 font-medium" id="codeMetaRow">
                    <span class="flex items-center gap-1.5 text-slate-400">
                        <i data-lucide="clock" class="w-3.5 h-3.5 text-amber-400"></i> Valid for 30m
                    </span>
                    <span class="text-indigo-400 font-bold flex items-center gap-1">
                        <i data-lucide="sparkles" class="w-3.5 h-3.5"></i> Auto-Verifies in Circle
                    </span>
                </div>

                <!-- Submit Button -->
                <button 
                    type="button" 
                    id="submitBtn"
                    onclick="triggerVerification()"
                    class="submit-action-btn w-full py-3.5 px-6 text-sm text-white flex items-center justify-center gap-2 cursor-pointer active:scale-95"
                >
                    <i data-lucide="lock" class="w-4 h-4"></i>
                    <span id="submitBtnText">Verify Identity</span>
                </button>
            </form>

            <!-- Resend / Change Account -->
            <div class="pt-4 border-t border-white/10 flex flex-col items-center justify-center gap-3 text-xs" id="footerSection">
                <?php if ($resendCount >= 5): ?>
                    <div class="text-amber-400 text-center text-xs">
                        Maximum 5 resend attempts used. Please check your email inbox.
                    </div>
                <?php else: ?>
                    <button 
                        id="resendBtn" 
                        disabled 
                        onclick="window.location.href='?action=resend-otp'"
                        class="text-xs font-bold text-slate-500 cursor-not-allowed transition flex items-center gap-1.5"
                    >
                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                        <span id="resendText">Resend Code (<?= $cooldownRemaining ?>s)</span>
                    </button>
                <?php endif; ?>

                <a href="?page=login" class="text-xs font-bold text-slate-400 hover:text-white transition flex items-center gap-1">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Back to Email Login
                </a>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        const authCard = document.getElementById("authCard");
        const inputs = document.querySelectorAll(".otp-box-node");
        const userEmail = document.getElementById("userEmail").value;
        const userId = document.getElementById("userId").value;
        const submitBtn = document.getElementById("submitBtn");
        const submitBtnText = document.getElementById("submitBtnText");
        const successContent = document.getElementById("successContent");
        const failedContent = document.getElementById("failedContent");
        const welcomeUserText = document.getElementById("welcomeUserText");
        const failedReasonText = document.getElementById("failedReasonText");

        let isVerifying = false;

        // Auto-focus 1st input
        setTimeout(() => inputs[0]?.focus(), 200);

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

                checkAllFilled();
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
                    checkAllFilled();
                }
            });
        });

        function checkAllFilled() {
            let fullOtp = "";
            inputs.forEach(inp => { fullOtp += inp.value; });
            if (fullOtp.length === 6 && !isVerifying) {
                triggerVerification();
            }
        }

        // 🌀 PERFECT SYMMETRIC RADIAL ALIGNMENT (Center: 110px, 110px, Radius: 75px)
        function morphToCircle() {
            authCard.classList.add("is-circular-mode");
            submitBtn.disabled = true;
            submitBtnText.innerText = "Verifying in Orbit...";

            const wheelSize = 220; // 220px wheel
            const center = wheelSize / 2; // 110px
            const radius = 75; // 75px radius
            const nodeHalf = 22; // 44px / 2

            inputs.forEach((box, i) => {
                // Symmetrical 60 degree distribution starting top: -90°
                const angleDeg = -90 + (i * 60);
                const angleRad = angleDeg * (Math.PI / 180);
                const leftPos = center + (radius * Math.cos(angleRad)) - nodeHalf;
                const topPos = center + (radius * Math.sin(angleRad)) - nodeHalf;
                
                box.style.left = `${leftPos}px`;
                box.style.top = `${topPos}px`;
            });
        }

        // ↩️ MORPH BACK TO HORIZONTAL ROW (ON FAILURE)
        function morphBackToHorizontal() {
            authCard.classList.remove("is-circular-mode", "is-failed");
            inputs.forEach(box => {
                box.style.left = '';
                box.style.top = '';
                box.value = '';
                box.classList.remove('filled');
            });
            submitBtn.disabled = false;
            submitBtnText.innerText = "Verify Identity";
            isVerifying = false;
            setTimeout(() => inputs[0]?.focus(), 200);
        }

        // 🚀 TRIGGER ASYNCHRONOUS VERIFICATION
        function triggerVerification() {
            let fullOtp = "";
            inputs.forEach(inp => { fullOtp += inp.value; });

            if (fullOtp.length !== 6) {
                alert("Please enter all 6 digits first.");
                return;
            }

            isVerifying = true;

            // 1. Morph boxes into rotating circle!
            morphToCircle();

            // 2. Perform AJAX verification in background while circle is spinning
            const formData = new FormData();
            formData.append("user_email", userEmail);
            formData.append("user_id", userId);
            formData.append("otp", fullOtp);
            formData.append("is_ajax", "1");

            fetch("?action=verify-otp", {
                method: "POST",
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    "Accept": "application/json"
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                // Wait at least 1.4s for full circular spinning thrill
                setTimeout(() => {
                    if (data.success) {
                        // ✅ CASE 1: VERIFIED (SUCCESS)
                        authCard.classList.add("is-verified");
                        successContent.classList.remove("hidden");
                        if (data.user_name) {
                            welcomeUserText.innerText = `Welcome back, ${data.user_name}!`;
                        }

                        // Redirect to role-based portal after 1.2s
                        setTimeout(() => {
                            window.location.href = data.redirect_url || "?page=employee-dashboard";
                        }, 1200);
                    } else {
                        // ❌ CASE 2: WRONG OTP / UNVERIFIED (FAILED)
                        authCard.classList.add("is-failed");
                        failedContent.classList.remove("hidden");
                        failedReasonText.innerText = data.message || "Invalid or expired code.";

                        // Morph back to row after 1.8s so user can re-try
                        setTimeout(() => {
                            failedContent.classList.add("hidden");
                            morphBackToHorizontal();
                        }, 1800);
                    }
                }, 1400);
            })
            .catch(err => {
                setTimeout(() => {
                    authCard.classList.add("is-failed");
                    failedContent.classList.remove("hidden");
                    failedReasonText.innerText = "Network error. Please try again.";
                    setTimeout(() => {
                        failedContent.classList.add("hidden");
                        morphBackToHorizontal();
                    }, 1800);
                }, 1400);
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
                resendBtn.className = "text-xs font-bold text-slate-500 cursor-not-allowed transition flex items-center gap-1.5";
                resendText.innerText = `Resend Code (${secondsLeft}s)`;
                secondsLeft--;
                setTimeout(updateTimer, 1000);
            } else {
                resendBtn.disabled = false;
                resendBtn.className = "text-xs font-bold text-indigo-400 hover:text-indigo-300 cursor-pointer transition flex items-center gap-1.5";
                resendText.innerText = "Resend Verification Code";
            }
        }

        if (secondsLeft >= 0) {
            updateTimer();
        }
    </script>
</body>
</html>