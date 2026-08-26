<!-- views/admin/roles.php -->
<?php
$user = authUser();
$db = getDBConnection();

$roles = $db->query("SELECT * FROM roles_master ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Count active users per role designation
$roleCounts = $db->query("SELECT LOWER(designation) as desig, COUNT(*) as cnt FROM users WHERE status = 'active' GROUP BY LOWER(designation)")->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
?>

<div class="space-y-6" x-data="{ addModalOpen: false, editModalOpen: false, selectedRole: null }">
    <!-- Header Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="shield-check" class="w-5 h-5"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Role & Hierarchy Management</h1>
                <p class="text-xs text-slate-500 mt-0.5">Configure corporate designations and control who can act as a Reporting Authority (Manager).</p>
            </div>
        </div>

        <button type="button" @click="addModalOpen = true" class="px-4 py-2 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white text-xs font-bold rounded-xl shadow-sm transition flex items-center gap-1.5 cursor-pointer">
            <i data-lucide="plus-circle" class="w-4 h-4"></i> Add Custom Role
        </button>
    </div>

    <!-- Roles Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($roles as $r): 
            $userCnt = $roleCounts[strtolower($r['name'])] ?? 0;
        ?>
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between space-y-4 hover:border-indigo-300 transition">
                <div>
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h3 class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($r['name']) ?></h3>
                            <span class="text-[10px] font-mono text-slate-400 block mt-0.5"><?= htmlspecialchars($r['slug']) ?></span>
                        </div>
                        <?php if ($r['can_be_reporting_authority']): ?>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center gap-1">
                                <i data-lucide="crown" class="w-3 h-3"></i> Authority
                            </span>
                        <?php else: ?>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-semibold text-slate-500 bg-slate-100 border border-slate-200">
                                Staff / Individual
                            </span>
                        <?php endif; ?>
                    </div>

                    <p class="text-xs text-slate-500 mt-2.5 line-clamp-2">
                        <?= htmlspecialchars($r['description'] ?: 'Standard organizational designation.') ?>
                    </p>
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500">
                        <strong class="text-slate-800"><?= $userCnt ?></strong> active member(s)
                    </span>
                    <div class="flex items-center gap-1.5">
                        <button type="button" @click="selectedRole = <?= htmlspecialchars(json_encode($r)) ?>; editModalOpen = true" class="p-1.5 text-slate-400 hover:text-indigo-600 rounded-lg hover:bg-slate-50 transition" title="Edit Role">
                            <i data-lucide="edit-2" class="w-4 h-4"></i>
                        </button>
                        <form action="?action=delete-role" method="POST" onsubmit="return confirm('Are you sure you want to delete this role?');" class="inline">
                            <input type="hidden" name="role_id" value="<?= $r['id'] ?>">
                            <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition" title="Delete Role">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Add Role Modal -->
    <div x-show="addModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" @click="addModalOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="font-bold text-sm text-slate-900">Add New Designation / Role</h3>
                    <button type="button" @click="addModalOpen = false" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-4 h-4"></i></button>
                </div>
                <form action="?action=create-role" method="POST" class="space-y-4 pt-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Role / Designation Title *</label>
                        <input type="text" name="name" required placeholder="e.g. Operations Manager" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Description</label>
                        <textarea name="description" rows="2" placeholder="Responsibilities and scope..." class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs"></textarea>
                    </div>
                    <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
                        <label class="flex items-center gap-2.5 cursor-pointer">
                            <input type="checkbox" name="can_be_reporting_authority" value="1" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500">
                            <span class="text-xs font-bold text-slate-800">Can be a Reporting Authority?</span>
                        </label>
                        <p class="text-[11px] text-slate-400 mt-1 pl-6.5">If checked, employees with this role can be selected as a Reporting Manager during onboarding.</p>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="addModalOpen = false" class="px-4 py-2 bg-slate-100 rounded-xl text-xs font-bold text-slate-600">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold shadow-sm">Save Role</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Role Modal -->
    <div x-show="editModalOpen && selectedRole" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" @click="editModalOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="font-bold text-sm text-slate-900">Edit Designation / Role</h3>
                    <button type="button" @click="editModalOpen = false" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-4 h-4"></i></button>
                </div>
                <form action="?action=update-role" method="POST" class="space-y-4 pt-4">
                    <input type="hidden" name="role_id" :value="selectedRole ? selectedRole.id : ''">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Role Title *</label>
                        <input type="text" name="name" :value="selectedRole ? selectedRole.name : ''" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Description</label>
                        <textarea name="description" rows="2" :value="selectedRole ? selectedRole.description : ''" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs"></textarea>
                    </div>
                    <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
                        <label class="flex items-center gap-2.5 cursor-pointer">
                            <input type="checkbox" name="can_be_reporting_authority" value="1" :checked="selectedRole && Number(selectedRole.can_be_reporting_authority) === 1" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500">
                            <span class="text-xs font-bold text-slate-800">Can be a Reporting Authority?</span>
                        </label>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="editModalOpen = false" class="px-4 py-2 bg-slate-100 rounded-xl text-xs font-bold text-slate-600">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold shadow-sm">Update Role</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>