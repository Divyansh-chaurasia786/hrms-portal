<!-- views/admin/super_admin_control.php -->
<?php
// Exclusive Super Admin Command Center
$user = authUser();
?>

<div class="space-y-6 max-w-7xl mx-auto pb-12" x-data="{
    currentMode: '<?= htmlspecialchars($rolloutMode) ?>',
    broadcastOpen: false
}">

    <!-- 👑 TOP MASTER COMMAND HEADER -->
    <div class="relative bg-gradient-to-r from-slate-950 via-indigo-950 to-slate-900 rounded-3xl p-6 sm:p-8 text-white border border-indigo-500/30 shadow-2xl overflow-hidden">
        <div class="absolute -right-12 -bottom-12 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute top-0 right-0 p-6 flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
            <span class="px-3 py-1 rounded-full bg-indigo-500/20 border border-indigo-400/40 text-[10px] font-mono font-bold text-indigo-200">
                MASTER SUPER ADMIN ACCESS ONLY
            </span>
        </div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-amber-500 to-indigo-600 text-white flex items-center justify-center text-2xl shadow-xl shadow-indigo-950/60 font-black border border-amber-300/40 shrink-0">
                    👑
                </div>
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight">Master Super Admin Control</h1>
                    </div>
                    <p class="text-xs text-slate-300 font-medium max-w-xl">
                        Exclusive portal to control company-wide app rollout phases, IT testing sandboxes, weekly sprint feature unlocks, and system security.
                    </p>
                    <div class="text-[11px] text-amber-300/90 font-mono font-semibold pt-1">
                        Logged in as: <?= htmlspecialchars($user['email'] ?? 'Super Admin') ?> (Access Level: Tier-1 Master)
                    </div>
                </div>
            </div>

            <!-- Global Status Badges -->
            <div class="flex flex-wrap gap-2.5 shrink-0">
                <div class="bg-slate-900/80 border border-slate-700/80 rounded-2xl p-3 text-center min-w-[90px]">
                    <span class="text-[10px] text-slate-400 font-bold block uppercase">Active Mode</span>
                    <strong class="text-xs text-amber-400 font-mono uppercase" x-text="currentMode.replace('_', ' ')"></strong>
                </div>
                <div class="bg-slate-900/80 border border-slate-700/80 rounded-2xl p-3 text-center min-w-[90px]">
                    <span class="text-[10px] text-slate-400 font-bold block uppercase">Sprint Build</span>
                    <strong class="text-xs text-emerald-400 font-mono"><?= htmlspecialchars($sprintVersion) ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- 📊 LIVE WORKFORCE DEMOGRAPHICS AT A GLANCE -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white p-4 sm:p-5 rounded-3xl border border-slate-200 shadow-sm space-y-1">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Active Staff</span>
            <div class="text-2xl font-black text-slate-900 font-mono"><?= $totalUsers ?></div>
            <span class="text-[10px] text-slate-500">Across all departments</span>
        </div>
        <div class="bg-white p-4 sm:p-5 rounded-3xl border border-indigo-200 bg-indigo-50/20 shadow-sm space-y-1">
            <span class="text-[11px] font-bold text-indigo-600 uppercase tracking-wider">💻 IT & Testing Team</span>
            <div class="text-2xl font-black text-indigo-700 font-mono"><?= $itUsers ?></div>
            <span class="text-[10px] text-indigo-600 font-semibold">Beta Pilots & Sandbox</span>
        </div>
        <div class="bg-white p-4 sm:p-5 rounded-3xl border border-emerald-200 bg-emerald-50/20 shadow-sm space-y-1">
            <span class="text-[11px] font-bold text-emerald-600 uppercase tracking-wider">🛵 Field Operations</span>
            <div class="text-2xl font-black text-emerald-700 font-mono"><?= $fieldUsers ?></div>
            <span class="text-[10px] text-emerald-600 font-semibold">24/7 Live GPS Telemetry</span>
        </div>
        <div class="bg-white p-4 sm:p-5 rounded-3xl border border-purple-200 bg-purple-50/20 shadow-sm space-y-1">
            <span class="text-[11px] font-bold text-purple-600 uppercase tracking-wider">📞 BDA Calling Team</span>
            <div class="text-2xl font-black text-purple-700 font-mono"><?= $salesUsers ?></div>
            <span class="text-[10px] text-purple-600 font-semibold">Lead CRM & Dialers</span>
        </div>
    </div>

    <!-- 🎛️ MAIN MASTER ROLLOUT FORM -->
    <form action="?action=update-rollout-settings" method="POST" class="space-y-6">

        <!-- 🚀 1. ROLLOUT PHASE SELECTOR -->
        <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-sm space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h2 class="text-base sm:text-lg font-extrabold text-slate-900">1. Select Application Rollout Mode</h2>
                    <p class="text-xs text-slate-500">Choose who can access the full portal and who sees the 'Coming Soon' sandbox.</p>
                </div>
                <span class="px-3 py-1 bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-full text-xs font-bold font-mono">
                    1-Click Switch
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Option 1: IT Testing Only -->
                <label class="relative flex flex-col p-5 rounded-2xl border-2 transition cursor-pointer select-none"
                       :class="currentMode === 'it_testing' ? 'border-indigo-600 bg-indigo-50/40 shadow-md ring-2 ring-indigo-600/20' : 'border-slate-200 hover:border-slate-300 bg-slate-50/50'">
                    <input type="radio" name="rollout_mode" value="it_testing" x-model="currentMode" class="sr-only">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-2xl">🧪</span>
                        <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full"
                              :class="currentMode === 'it_testing' ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-600'">
                            Phase 1 (Default)
                        </span>
                    </div>
                    <strong class="text-sm font-black text-slate-900">IT Beta Testing Only</strong>
                    <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                        Only IT Department & Admins get full access. Other employees can only use basic profile & punch-in; other modules show 'Coming Soon'.
                    </p>
                </label>

                <!-- Option 2: Phased Department Rollout -->
                <label class="relative flex flex-col p-5 rounded-2xl border-2 transition cursor-pointer select-none"
                       :class="currentMode === 'phased_rollout' ? 'border-indigo-600 bg-indigo-50/40 shadow-md ring-2 ring-indigo-600/20' : 'border-slate-200 hover:border-slate-300 bg-slate-50/50'">
                    <input type="radio" name="rollout_mode" value="phased_rollout" x-model="currentMode" class="sr-only">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-2xl">🏢</span>
                        <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full"
                              :class="currentMode === 'phased_rollout' ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-600'">
                            Phase 2 (Selective)
                        </span>
                    </div>
                    <strong class="text-sm font-black text-slate-900">Phased Dept Rollout</strong>
                    <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                        Unlock the app department-by-department (e.g. IT + Field Ops this week, Sales next week).
                    </p>
                </label>

                <!-- Option 3: Full Company Live -->
                <label class="relative flex flex-col p-5 rounded-2xl border-2 transition cursor-pointer select-none"
                       :class="currentMode === 'full_live' ? 'border-emerald-600 bg-emerald-50/40 shadow-md ring-2 ring-emerald-600/20' : 'border-slate-200 hover:border-slate-300 bg-slate-50/50'">
                    <input type="radio" name="rollout_mode" value="full_live" x-model="currentMode" class="sr-only">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-2xl">🎉</span>
                        <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full"
                              :class="currentMode === 'full_live' ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-600'">
                            Phase 3 (Grand Launch)
                        </span>
                    </div>
                    <strong class="text-sm font-black text-slate-900">100% Live (All Staff)</strong>
                    <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                        All modules, routes, and features are completely unlocked for every employee in the company.
                    </p>
                </label>
            </div>
        </div>

        <!-- 🏢 2. ACTIVE DEPARTMENTS IN PHASED MODE -->
        <div x-show="currentMode === 'phased_rollout'" class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-sm space-y-4 animate-in fade-in duration-200">
            <div class="border-b border-slate-100 pb-3">
                <h3 class="text-sm sm:text-base font-extrabold text-slate-900">2. Select Unlocked Departments (Phase 2)</h3>
                <p class="text-xs text-slate-500">Tick which departments are currently participating in the live rollout.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <?php foreach ($departments as $dept): ?>
                    <?php $isChecked = in_array($dept, $allowedDepts); ?>
                    <label class="flex items-center gap-3 p-3.5 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer transition select-none">
                        <input type="checkbox" name="allowed_departments[]" value="<?= htmlspecialchars($dept) ?>" <?= $isChecked ? 'checked' : '' ?> class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500">
                        <span class="text-xs font-bold text-slate-800"><?= htmlspecialchars($dept) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 🔐 3. GRANULAR FEATURE FLAGS FOR NON-IT STAFF -->
        <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-sm space-y-4">
            <div class="border-b border-slate-100 pb-3">
                <h3 class="text-sm sm:text-base font-extrabold text-slate-900">3. General Feature Flag Matrix</h3>
                <p class="text-xs text-slate-500">Control which specific modules are globally accessible vs locked under 'Coming Soon'.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <label class="flex items-start gap-3 p-4 rounded-2xl bg-slate-50 border border-slate-200 cursor-pointer">
                    <input type="checkbox" name="feature_punch" value="1" <?= !empty($unlockedFeatures['punch']) ? 'checked' : '' ?> class="w-4 h-4 mt-0.5 rounded text-indigo-600 focus:ring-indigo-500">
                    <div>
                        <strong class="text-xs font-bold text-slate-900 block">🏢 Geofenced Office Punch In/Out</strong>
                        <span class="text-[11px] text-slate-500">Allows employees to mark attendance via office geofence.</span>
                    </div>
                </label>

                <label class="flex items-start gap-3 p-4 rounded-2xl bg-slate-50 border border-slate-200 cursor-pointer">
                    <input type="checkbox" name="feature_gps_radar" value="1" <?= !empty($unlockedFeatures['gps_radar']) ? 'checked' : '' ?> class="w-4 h-4 mt-0.5 rounded text-indigo-600 focus:ring-indigo-500">
                    <div>
                        <strong class="text-xs font-bold text-slate-900 block">🛵 24/7 Field Live Travel Radar</strong>
                        <span class="text-[11px] text-slate-500">Active background route tracking for field executives.</span>
                    </div>
                </label>

                <label class="flex items-start gap-3 p-4 rounded-2xl bg-slate-50 border border-slate-200 cursor-pointer">
                    <input type="checkbox" name="feature_bda_crm" value="1" <?= !empty($unlockedFeatures['bda_crm']) ? 'checked' : '' ?> class="w-4 h-4 mt-0.5 rounded text-indigo-600 focus:ring-indigo-500">
                    <div>
                        <strong class="text-xs font-bold text-slate-900 block">📞 BDA Lead Calling CRM</strong>
                        <span class="text-[11px] text-slate-500">Lead distribution & round-robin calling dialer.</span>
                    </div>
                </label>

                <label class="flex items-start gap-3 p-4 rounded-2xl bg-slate-50 border border-slate-200 cursor-pointer">
                    <input type="checkbox" name="feature_smart_sheet" value="1" <?= !empty($unlockedFeatures['smart_sheet']) ? 'checked' : '' ?> class="w-4 h-4 mt-0.5 rounded text-indigo-600 focus:ring-indigo-500">
                    <div>
                        <strong class="text-xs font-bold text-slate-900 block">📊 Smart Sheet & AI Hub</strong>
                        <span class="text-[11px] text-slate-500">Excel multi-grid analytics and automated sheets.</span>
                    </div>
                </label>

                <label class="flex items-start gap-3 p-4 rounded-2xl bg-slate-50 border border-slate-200 cursor-pointer">
                    <input type="checkbox" name="feature_leaves" value="1" <?= !empty($unlockedFeatures['leaves']) ? 'checked' : '' ?> class="w-4 h-4 mt-0.5 rounded text-indigo-600 focus:ring-indigo-500">
                    <div>
                        <strong class="text-xs font-bold text-slate-900 block">🏖️ Leave Approvals & WFH</strong>
                        <span class="text-[11px] text-slate-500">Full leave request pipeline and policies.</span>
                    </div>
                </label>
            </div>
        </div>

        <!-- 🗓️ 4. WEEKLY SPRINT VERSION & BROADCAST NOTIFICATION -->
        <div class="bg-white p-6 sm:p-8 rounded-3xl border border-slate-200 shadow-sm space-y-4">
            <div class="border-b border-slate-100 pb-3">
                <h3 class="text-sm sm:text-base font-extrabold text-slate-900">4. Weekly Sprint Release & In-App Announcement</h3>
                <p class="text-xs text-slate-500">Bump the sprint build version to notify mobile APK and PWA users of newly unlocked features.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-700">Sprint Release Version</label>
                    <input type="text" name="weekly_sprint_version" value="<?= htmlspecialchars($sprintVersion) ?>" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs font-mono font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-700">Push Announcement Message (Optional)</label>
                    <input type="text" name="broadcast_message" placeholder="e.g. Sprint 2 Unlocked: Field Radar & Travel Logs are now live!" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs font-medium text-slate-900 focus:bg-white focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
        </div>

        <!-- Save Button -->
        <div class="flex items-center justify-end gap-3 pt-2">
            <button type="submit" class="px-8 py-3.5 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-extrabold text-sm rounded-2xl shadow-xl shadow-indigo-600/30 transition active:scale-95 flex items-center gap-2 cursor-pointer">
                <span>💾</span>
                <span>Apply Super Admin Rollout Settings</span>
            </button>
        </div>
    </form>
</div>