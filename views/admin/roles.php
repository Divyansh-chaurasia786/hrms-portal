<?php
// views/admin/roles.php
$title = "Role Management & Hierarchy - Enterprise HRMS";
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>
<main class="flex-1 min-w-0 overflow-y-auto bg-slate-900 text-slate-100 p-4 sm:p-8">
    <div class="max-w-6xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-white flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center">
                        <i data-lucide="shield-alert" class="w-5 h-5"></i>
                    </div>
                    Role Management & Authority Hierarchy
                </h1>
                <p class="text-xs text-slate-400 mt-1">Configure company designations and designate who can act as a Reporting Authority (Manager / Lead).</p>
            </div>
            <button onclick="document.getElementById('addRoleModal').classList.remove('hidden')" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs transition shadow-lg shadow-indigo-600/30">
                <i data-lucide="plus" class="w-4 h-4"></i> Add Custom Role
            </button>
        </div>

        <?php $flash = getFlash(); if ($flash): ?>
            <div class="p-4 rounded-2xl text-xs font-medium <?= $flash['type'] === 'error' ? 'bg-rose-500/10 text-rose-300 border border-rose-500/30' : 'bg-emerald-500/10 text-emerald-300 border border-emerald-500/30' ?>">
                <?= $flash['message'] ?>
            </div>
        <?php endif; ?>

        <!-- Roles Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($roles as $r): ?>
                <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4 hover:border-slate-700 transition">
                    <div class="flex items-start justify-between">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 border border-slate-700">
                                <?= htmlspecialchars($r['department_name'] ?? 'General') ?>
                            </span>
                            <h3 class="text-base font-bold text-white mt-2"><?= htmlspecialchars($r['name']) ?></h3>
                            <p class="text-[11px] font-mono text-slate-500">Code: <?= htmlspecialchars($r['code']) ?></p>
                        </div>
                        <?php if ($r['code'] !== 'head_hr'): ?>
                            <form action="?action=delete-role" method="POST" onsubmit="return confirm('Delete role <?= htmlspecialchars($r['name']) ?>?');">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <button type="submit" class="p-1.5 rounded-lg text-slate-500 hover:text-rose-400 hover:bg-rose-500/10 transition">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <!-- Authority Badge -->
                    <div class="pt-3 border-t border-slate-800/80 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full <?= !empty($r['can_be_reporting_authority']) ? 'bg-emerald-400 shadow-sm shadow-emerald-400/50' : 'bg-slate-600' ?>"></span>
                            <span class="text-xs font-semibold <?= !empty($r['can_be_reporting_authority']) ? 'text-emerald-300' : 'text-slate-400' ?>">
                                <?= !empty($r['can_be_reporting_authority']) ? '👑 Reporting Authority' : '👤 Team Member' ?>
                            </span>
                        </div>
                        <span class="text-[11px] text-slate-500 font-medium">
                            <?= (int)($roleCounts[$r['name']] ?? 0) ?> active
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>

<!-- Add Role Modal -->
<div id="addRoleModal" class="hidden fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl space-y-5">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-bold text-white">Add Custom Role / Designation</h3>
            <button onclick="document.getElementById('addRoleModal').classList.add('hidden')" class="text-slate-400 hover:text-white">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form action="?action=create-role" method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1">Role / Designation Title</label>
                <input type="text" name="name" required placeholder="e.g. Operations Lead, SDE-2" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1">Department</label>
                <select name="department_name" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
                    <option value="Tech / Development">Tech / Development</option>
                    <option value="Calling / Sales">Calling / Sales</option>
                    <option value="Field Operations">Field Operations</option>
                    <option value="HR & Administration">HR & Administration</option>
                    <option value="Management">Management</option>
                    <option value="General">General</option>
                </select>
            </div>
            <div class="bg-slate-950/60 p-3.5 rounded-xl border border-slate-800 flex items-center gap-3">
                <input type="checkbox" id="can_be_reporting_authority" name="can_be_reporting_authority" value="1" class="w-4 h-4 text-indigo-600 rounded bg-slate-900 border-slate-700">
                <label for="can_be_reporting_authority" class="text-xs font-semibold text-slate-200 cursor-pointer">
                    Can be a Reporting Authority (Appears in "Reports To" list)
                </label>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('addRoleModal').classList.add('hidden')" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-400 hover:text-white">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs shadow-lg shadow-indigo-600/30">Save Role</button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>