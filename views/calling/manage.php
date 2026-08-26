<?php
// views/calling/manage.php
$title = "Lead Distribution & Calling Team Analytics - Ecofone HRMS";
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>
<main class="flex-1 min-w-0 overflow-y-auto bg-slate-900 text-slate-100 p-4 sm:p-8">
    <div class="max-w-6xl mx-auto space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center">
                        <i data-lucide="upload-cloud" class="w-5 h-5"></i>
                    </div>
                    Calling Team Management & Lead Distributor
                </h1>
                <p class="text-xs text-slate-400 mt-1">Upload lead sheets, auto-distribute equally across callers, and monitor performance.</p>
            </div>
            <button onclick="document.getElementById('uploadLeadModal').classList.remove('hidden')" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs shadow-lg shadow-indigo-600/30">
                <i data-lucide="plus" class="w-4 h-4"></i> Upload & Distribute Leads
            </button>
        </div>

        <?php $flash = getFlash(); if ($flash): ?>
            <div class="p-4 rounded-2xl text-xs font-medium <?= $flash['type'] === 'error' ? 'bg-rose-500/10 text-rose-300 border border-rose-500/30' : 'bg-emerald-500/10 text-emerald-300 border border-emerald-500/30' ?>">
                <?= $flash['message'] ?>
            </div>
        <?php endif; ?>

        <!-- Caller Performance Leaderboard -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
            <h2 class="text-sm font-bold text-white uppercase tracking-wider text-slate-400">Caller Performance Leaderboard</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-800 text-slate-400">
                            <th class="pb-3">Caller Name</th>
                            <th class="pb-3">Total Assigned</th>
                            <th class="pb-3">Calls Made</th>
                            <th class="pb-3">Interested</th>
                            <th class="pb-3">Closed / Converted</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <?php foreach ($stats as $s): ?>
                            <tr>
                                <td class="py-3.5 font-bold text-white"><?= htmlspecialchars($s['name']) ?> (<?= $s['emp_id'] ?>)</td>
                                <td class="py-3.5 text-indigo-300 font-bold"><?= (int)$s['total_leads'] ?></td>
                                <td class="py-3.5 text-slate-300"><?= (int)$s['called_count'] ?></td>
                                <td class="py-3.5 text-emerald-400 font-bold"><?= (int)$s['interested'] ?></td>
                                <td class="py-3.5 text-purple-400 font-extrabold"><?= (int)$s['converted'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<div id="uploadLeadModal" class="hidden fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 max-w-lg w-full shadow-2xl space-y-4">
        <h3 class="text-lg font-bold text-white">Upload & Distribute Leads</h3>
        <p class="text-xs text-slate-400">Upload CSV file with columns: <code>Name, Phone, City, Notes</code></p>
        <form action="?action=upload-calling-leads" method="POST" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1">Select CSV Lead Sheet</label>
                <input type="file" name="lead_sheet" accept=".csv" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-xs text-white">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1">Select Callers to Distribute Leads Across</label>
                <div class="max-h-40 overflow-y-auto space-y-2 bg-slate-950 p-3 rounded-xl border border-slate-800">
                    <?php foreach ($callers as $c): ?>
                        <label class="flex items-center gap-2 text-xs text-slate-300 cursor-pointer">
                            <input type="checkbox" name="callers[]" value="<?= $c['id'] ?>" checked class="w-4 h-4 text-indigo-600 rounded bg-slate-900 border-slate-700">
                            <span><?= htmlspecialchars($c['name']) ?> (<?= $c['emp_id'] ?>)</span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('uploadLeadModal').classList.add('hidden')" class="px-4 py-2 rounded-xl text-xs text-slate-400">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs">Distribute Leads</button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>