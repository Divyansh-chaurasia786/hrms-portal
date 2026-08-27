<!-- views/profile.php -->
<?php
$user = authUser();
$db = getDBConnection();

$stmt = $db->prepare("
    SELECT u.*, tl.name as tl_name 
    FROM users u 
    LEFT JOIN users tl ON u.reporting_tl_id = tl.id 
    WHERE u.id = ?
");
$profile = $stmt->fetch() ?: $user;

$emailChangeReq = $_SESSION['email_change_request'] ?? null;
?>

<div class="space-y-6 max-w-5xl mx-auto" x-data="{ emailModalOpen: <?= $emailChangeReq ? 'true' : 'false' ?> }">
    <!-- Header Card -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-4">
            <div class="relative">
                <img src="<?= ($profile['avatar'] ?? null) ?: 'https://ui-avatars.com/api/?name=' . urlencode($profile['name'] ?? 'User') ?>" class="w-16 h-16 rounded-2xl object-cover ring-4 ring-indigo-500/20 shadow-md" alt="Avatar">
                <span class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full bg-emerald-500 ring-2 ring-white"></span>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-bold text-slate-900"><?= htmlspecialchars($profile['name'] ?? 'User') ?></h1>
                    <span class="px-2.5 py-0.5 bg-purple-100 text-purple-800 text-[10px] font-bold rounded-full uppercase tracking-wider">
                        <?= strtoupper(str_replace('_', ' ', $profile['role'] ?? 'employee')) ?>
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($profile['designation'] ?? 'HR Administrator') ?> • <span class="font-mono text-indigo-600 font-semibold"><?= htmlspecialchars($profile['emp_id'] ?? 'EMP') ?></span></p>
            </div>
        </div>

        <div class="text-xs text-slate-400 font-mono">
            Joined: <strong><?= formatDate($profile['joining_date'] ?? null) ?></strong>
        </div>
    </div>

    <!-- Active Email Verification Alert Banner -->
    <?php if ($emailChangeReq): ?>
        <div class="p-4 bg-amber-50 rounded-2xl border-2 border-amber-300 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <div class="p-2 rounded-xl bg-amber-200 text-amber-900 shrink-0">
                    <i data-lucide="mail-warning" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-xs font-bold text-amber-900 uppercase tracking-wider">Dual Email Verification In Progress</h3>
                    <p class="text-xs text-amber-800 mt-0.5">
                        Requesting change from <strong><?= htmlspecialchars($emailChangeReq['current_email']) ?></strong> to <strong><?= htmlspecialchars($emailChangeReq['new_email']) ?></strong>.
                    </p>
                    <p class="text-[11px] text-amber-700 font-mono mt-1">Verification OTP: <span class="px-2 py-0.5 bg-amber-200 text-amber-900 font-bold rounded"><?= htmlspecialchars($emailChangeReq['otp']) ?></span></p>
                </div>
            </div>
            <button @click="emailModalOpen = true" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-xl shadow-sm transition shrink-0">
                Enter Verification OTP
            </button>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left: Edit Personal Details & Photo (Col span 2) -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6">
            <div class="flex items-center gap-2.5 pb-4 border-b border-slate-100">
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                    <i data-lucide="user-cog" class="w-4 h-4"></i>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Personal Information & Photo</h2>
                    <p class="text-[11px] text-slate-500">Corporate identity details and profile photo management.</p>
                </div>
            </div>

            <!-- Profile Photo Update Form -->
            <form action="?action=update-profile" method="POST" enctype="multipart/form-data" class="space-y-4" x-data="{ 
                previewUrl: '<?= $profile['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($profile['name']) ?>',
                hasSelectedFile: false,
                previewFile(e) {
                    const file = e.target.files[0];
                    if (file) {
                        this.previewUrl = URL.createObjectURL(file);
                        this.hasSelectedFile = true;
                    }
                }
            }">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-2">Profile Photo</label>
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-3">
                        <div class="flex items-center gap-4">
                            <div class="relative shrink-0">
                                <img :src="previewUrl" class="w-14 h-14 rounded-2xl object-cover ring-2 ring-indigo-500/30 shadow-md transition-all" alt="Preview Photo">
                                <span x-show="hasSelectedFile" class="absolute -top-1 -right-1 w-3.5 h-3.5 bg-emerald-500 rounded-full ring-2 ring-white" title="New image selected"></span>
                            </div>
                            <div class="flex-1">
                                <label class="block text-xs font-bold text-slate-800 mb-1">Choose Photo File (PNG, JPG, WEBP)</label>
                                <input type="file" name="avatar_file" accept="image/*" @change="previewFile($event)" class="w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700 cursor-pointer">
                                <p x-show="hasSelectedFile" class="text-[11px] text-emerald-600 font-semibold mt-1">✓ New image chosen! Click "Save & Update Profile Picture" below.</p>
                            </div>
                        </div>
                        <div class="pt-2 border-t border-slate-200/80">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Or Paste Image URL</label>
                            <input type="url" name="avatar_url" value="<?= htmlspecialchars($profile['avatar'] ?? '') ?>" placeholder="https://images.unsplash.com/photo-..." class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-xs text-slate-700 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                    </div>
                </div>

                <div class="pt-1 flex justify-end">
                    <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition flex items-center gap-2 shadow-sm cursor-pointer">
                        <i data-lucide="check" class="w-4 h-4"></i> Save & Update Profile Picture
                    </button>
                </div>
            </form>

            <div class="pt-4 border-t border-slate-100 space-y-4">
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Corporate Identity</div>

                <!-- Display Name (Fixed) & Designation (Fixed) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-[11px] font-bold text-slate-700 uppercase">Full Name</label>
                            <span class="text-[10px] text-slate-400 font-semibold bg-slate-100 px-1.5 py-0.2 rounded">Locked</span>
                        </div>
                        <input type="text" value="<?= htmlspecialchars($profile['name']) ?>" disabled class="w-full bg-slate-100 border border-slate-200 rounded-xl px-3.5 py-2 text-xs font-semibold text-slate-500 cursor-not-allowed">
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-[11px] font-bold text-slate-700 uppercase">Job Designation</label>
                            <span class="text-[10px] text-slate-400 font-semibold bg-slate-100 px-1.5 py-0.2 rounded">Locked</span>
                        </div>
                        <input type="text" value="<?= htmlspecialchars($profile['designation'] ?? '') ?>" disabled class="w-full bg-slate-100 border border-slate-200 rounded-xl px-3.5 py-2 text-xs font-semibold text-slate-500 cursor-not-allowed">
                    </div>
                </div>

                <!-- Official Email Row with Verified Badge and Change Action -->
                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Official Registered Email</span>
                        <div class="text-sm font-bold text-slate-900 font-mono mt-0.5 flex items-center gap-2">
                            <span><?= htmlspecialchars($profile['email']) ?></span>
                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.2 rounded-full border border-emerald-200 font-sans">
                                <i data-lucide="check" class="w-3 h-3 text-emerald-600"></i> Verified
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1">Requires dual-verification (confirming current email + new email verification code).</p>
                    </div>
                    <button @click="emailModalOpen = true" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition shrink-0">
                        <i data-lucide="mail-check" class="w-3.5 h-3.5"></i> Change Email Address
                    </button>
                </div>
            </div>
        </div>

        <!-- Right: Enterprise Security & Auth Architecture -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-6 flex flex-col justify-between">
            <div class="space-y-5">
                <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                    <div class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold shadow-sm">
                        <i data-lucide="shield-check" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Security & Authentication</h2>
                        <p class="text-[11px] text-slate-500">Corporate identity and access governance.</p>
                    </div>
                </div>

                <!-- Auth Protocol Info Card -->
                <div class="p-4 rounded-2xl bg-indigo-50/70 border border-indigo-100 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-indigo-900">Login Protocol</span>
                        <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-bold text-[10px] flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Active
                        </span>
                    </div>
                    <div class="text-xs font-bold text-slate-900 flex items-center gap-1.5">
                        <i data-lucide="key-round" class="w-4 h-4 text-indigo-600"></i>
                        <span>100% Passwordless Email OTP</span>
                    </div>
                    <p class="text-[11px] text-slate-600 leading-relaxed">
                        Passwords are eliminated for maximum corporate security. Your login authentication is performed via a dynamic 6-digit one-time passcode (OTP) dispatched exclusively to your registered work email.
                    </p>
                </div>

                <!-- Multi-Factor Delivery Gateway Card -->
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-2 text-xs">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Authorized Gateway</span>
                    <div class="flex items-center gap-2 text-slate-800 font-semibold">
                        <i data-lucide="mail-check" class="w-4 h-4 text-emerald-600"></i>
                        <span>Ecovista Global Private Limited</span>
                    </div>
                    <p class="text-[11px] text-slate-500 leading-relaxed">
                        Codes are sent under verified corporate sender. OTP verification expires in 10 minutes with strict anti-bruteforce protection.
                    </p>
                </div>
            </div>

            <!-- Role & Access Level Badge -->
            <div class="p-4 bg-slate-900 text-white rounded-2xl space-y-2 shadow-inner">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Access Level</span>
                    <span class="px-2.5 py-0.5 rounded-full bg-indigo-500/30 text-indigo-300 border border-indigo-400/30 font-extrabold uppercase text-[10px]"><?= htmlspecialchars($profile['role']) ?></span>
                </div>
                <div class="text-xs font-semibold text-slate-200">
                    Designation: <span class="text-white font-bold"><?= htmlspecialchars($profile['designation']) ?></span>
                </div>
                <div class="text-[10px] text-slate-400 pt-1 border-t border-slate-800">
                    Corporate identity parameters are locked & verified by HR Administration.
                </div>
            </div>
        </div>
        <!-- 🔒 SUPER ADMIN MASTER DATABASE VAULT (STRICTLY ADMIN ONLY & DISCRETE) -->
    <?php if (($user['role'] ?? '') === 'admin'): ?>
        <?php $storageStats = getDatabaseStorageStats(); ?>
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 text-white shadow-xl space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-800">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-purple-500/20 text-purple-400 flex items-center justify-center font-extrabold border border-purple-500/30">
                        <i data-lucide="shield-alert" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-sm font-extrabold text-white tracking-wide">Super Admin Database Vault & Automated Archival</h2>
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
                                80% Auto-Archival Active
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-400">Discrete master vault for database health monitoring, automated 3-year cleanup, and offsite dumps.</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a href="?action=admin-download-archive-backup" class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-md shadow-indigo-600/30">
                        <i data-lucide="download" class="w-3.5 h-3.5"></i> Download Master Backup
                    </a>
                    <a href="?action=admin-run-archival" onclick="return confirm('Execute 3-year data archival check now?');" class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition flex items-center gap-1.5 border border-slate-700">
                        <i data-lucide="archive" class="w-3.5 h-3.5"></i> Run Archival Check
                    </a>
                </div>
            </div>

            <!-- Health Bar & Telemetry -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                <div class="bg-white/5 p-3 rounded-2xl border border-white/5 space-y-1.5 sm:col-span-2">
                    <div class="flex items-center justify-between text-[11px]">
                        <span class="text-slate-300">TiDB Cloud Free Tier: <strong class="text-white"><?= $storageStats['used_mb'] ?> MB</strong> used of <?= $storageStats['max_mb'] ?> MB</span>
                        <span class="font-mono text-emerald-400 font-bold"><?= $storageStats['usage_percent'] ?>%</span>
                    </div>
                    <div class="w-full bg-slate-800 rounded-full h-2 overflow-hidden border border-slate-700">
                        <div class="bg-gradient-to-r from-emerald-500 to-indigo-500 h-full rounded-full" style="width: <?= max(1, min(100, $storageStats['usage_percent'])) ?>%;"></div>
                    </div>
                    <div class="flex items-center justify-between text-[9px] text-slate-400 font-mono">
                        <span>0%</span>
                        <span class="text-amber-400">80% Auto-Archival Threshold</span>
                        <span>5.0 GB</span>
                    </div>
                </div>

                <div class="bg-white/5 p-3 rounded-2xl border border-white/5 text-center flex flex-col justify-center">
                    <span class="text-[9px] uppercase font-bold text-slate-400 block">Archive Policy</span>
                    <strong class="text-amber-300 font-mono mt-0.5 text-xs">Auto-Prune > 3 Yrs</strong>
                    <span class="text-[9px] text-slate-400 mt-0.5">Triggers at 80% capacity</span>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<!-- Dual Email Verification Modal -->
    <div x-show="emailModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" x-cloak>
        <div @click.away="emailModalOpen = false" class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                        <i data-lucide="shield-check" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Dual Email Address Verification</h3>
                        <p class="text-[11px] text-slate-500">Authenticate current email and verify new email with security code.</p>
                    </div>
                </div>
                <button @click="emailModalOpen = false" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <?php if (!$emailChangeReq): ?>
                <!-- Step 1: Submit Change Request Form -->
                <form action="?action=request-email-change" method="POST" class="space-y-3.5">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">1. Confirm Current Email</label>
                        <input type="email" name="current_email" required placeholder="<?= htmlspecialchars($profile['email']) ?>" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono text-slate-800">
                    </div>

                    

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">3. New Email Address</label>
                            <input type="email" name="new_email" required placeholder="new.email@company.com" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono text-slate-800">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Confirm New Email</label>
                            <input type="email" name="confirm_new_email" required placeholder="new.email@company.com" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono text-slate-800">
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button type="button" @click="emailModalOpen = false" class="px-3.5 py-1.5 text-xs font-semibold text-slate-500">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition flex items-center gap-1.5">
                            <i data-lucide="send" class="w-3.5 h-3.5"></i> Request Verification Code
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <!-- Step 2: Enter 6-Digit OTP -->
                <form action="?action=verify-email-change" method="POST" class="space-y-4">
                    <div class="p-3.5 bg-indigo-50 rounded-xl border border-indigo-200 text-xs text-indigo-900 space-y-1">
                        <div>A 6-digit verification code has been dispatched to:</div>
                        <div class="font-bold font-mono text-indigo-950"><?= htmlspecialchars($emailChangeReq['new_email']) ?></div>
                        <p class="text-[11px] text-slate-500 mt-1">Please check your inbox for the code. Code expires in 10 minutes.</p>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1 text-center">Enter 6-Digit Verification OTP</label>
                        <input type="text" name="otp" required maxlength="6" autofocus placeholder="• • • • • •" class="w-full text-center tracking-[0.5em] font-mono text-lg font-bold bg-slate-50 border-2 border-indigo-300 rounded-xl py-2.5 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>

                    <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
                        <a href="?action=cancel-email-change" class="text-xs text-rose-600 hover:text-rose-700 font-semibold">Cancel Request</a>
                        <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-sm transition flex items-center gap-1.5">
                            <i data-lucide="check" class="w-4 h-4"></i> Verify & Confirm New Email
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
