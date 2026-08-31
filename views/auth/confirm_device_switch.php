<!-- views/auth/confirm_device_switch.php -->
<?php
$user = $user ?? ['name' => 'User', 'email' => 'user@company.com'];
$prevDevice = $prevDevice ?? 'Windows PC (Chrome)';
$prevLoginAt = $prevLoginAt ?? date('d M Y, h:i A');
$hasActiveShift = $hasActiveShift ?? false;
$activeSessionNum = $activeSessionNum ?? 1;
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Active Session Detected - EcoFone App</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif; }
    </style>
</head>
<body class="min-h-full flex flex-col items-center justify-center py-6 px-4 sm:px-6 relative overflow-x-hidden bg-slate-950 antialiased selection:bg-indigo-500 selection:text-white">
    <!-- Ambient Background Lighting -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden">
        <div class="absolute -top-40 -left-40 w-96 h-96 bg-amber-600/10 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-rose-600/10 rounded-full blur-3xl"></div>
    </div>

    <div class="w-full max-w-[460px] relative z-10 my-auto">
        <!-- Header Alert Icon -->
        <div class="text-center mb-5">
            <div class="inline-flex w-14 h-14 rounded-3xl bg-gradient-to-tr from-amber-500 via-rose-500 to-rose-600 items-center justify-center text-white shadow-2xl shadow-rose-600/30 ring-4 ring-rose-500/20 mb-3 animate-pulse">
                <i data-lucide="shield-alert" class="w-7 h-7"></i>
            </div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">Active Session Detected</h1>
            <p class="text-xs font-medium text-slate-400 mt-1 max-w-sm mx-auto leading-relaxed">
                Your account is already logged in on another device or browser.
            </p>
        </div>

        <!-- Main Confirmation Card -->
        <div class="bg-slate-900/90 backdrop-blur-2xl rounded-3xl border border-slate-800 p-7 sm:p-9 shadow-2xl shadow-black/80 space-y-5 ring-1 ring-white/5">
            
            <!-- User Identifier -->
            <div class="bg-slate-950/60 rounded-2xl p-3.5 border border-slate-800 flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center shrink-0 border border-indigo-500/20 font-bold text-sm">
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                </div>
                <div class="min-w-0">
                    <h3 class="text-xs font-bold text-white truncate"><?= htmlspecialchars($user['name']) ?></h3>
                    <p class="text-[11px] text-slate-400 truncate font-mono"><?= htmlspecialchars($user['email']) ?></p>
                </div>
            </div>

            <!-- Existing Device Details Box -->
            <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 space-y-3">
                <div class="flex items-center gap-2 text-amber-300 text-xs font-bold">
                    <i data-lucide="laptop" class="w-4 h-4 text-amber-400"></i>
                    <span>Currently Active On:</span>
                </div>
                <div class="space-y-1.5 pl-6 text-xs text-slate-300">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400 text-[11px]">Device:</span>
                        <strong class="font-semibold text-white"><?= htmlspecialchars($prevDevice) ?></strong>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-400 text-[11px]">Logged In At:</span>
                        <span class="font-mono text-slate-300 text-[11px]"><?= htmlspecialchars($prevLoginAt) ?></span>
                    </div>
                    <div class="flex items-center justify-between pt-1 border-t border-amber-500/20">
                        <span class="text-slate-400 text-[11px]">Active Shift Status:</span>
                        <?php if ($hasActiveShift): ?>
                            <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-400">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span> Punched In (Session #<?= $activeSessionNum ?>)
                            </span>
                        <?php else: ?>
                            <span class="text-[11px] font-bold text-slate-400">Punched Out / Idle</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Notice Text -->
            <p class="text-[11px] text-slate-400 leading-relaxed text-center">
                To sign in on <strong>this device</strong>, your previous device session will be terminated and any running shift will be automatically <strong class="text-rose-400">Punched Out</strong>.
            </p>

            <!-- Actions -->
            <div class="space-y-2.5 pt-2">
                <form action="?action=confirm-device-switch" method="POST">
                    <button type="submit" class="w-full py-3.5 bg-gradient-to-r from-rose-600 to-rose-700 hover:from-rose-500 hover:to-rose-600 text-white font-bold text-xs rounded-2xl shadow-xl shadow-rose-600/30 transition flex items-center justify-center gap-2 cursor-pointer">
                        <i data-lucide="log-out" class="w-4 h-4"></i>
                        <span>Logout Previous Device & Sign In Here</span>
                    </button>
                </form>

                <a href="?page=login" class="block w-full py-2.5 bg-slate-800/60 hover:bg-slate-800 text-slate-400 hover:text-white font-semibold text-xs rounded-2xl transition text-center border border-slate-700/50">
                    Cancel (Stay on Previous Device)
                </a>
            </div>

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof lucide !== 'undefined') lucide.createIcons();
        });
    </script>
</body>
</html>