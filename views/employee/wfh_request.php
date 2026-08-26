<?php
// views/employee/wfh_request.php
$title = "Work From Home (WFH) - Ecofone HRMS";
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
$minAllowed = date('Y-m-d', strtotime('+2 days'));
?>
<main class="flex-1 min-w-0 overflow-y-auto bg-slate-900 text-slate-100 p-4 sm:p-8">
    <div class="max-w-4xl mx-auto space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center">
                        <i data-lucide="home" class="w-5 h-5"></i>
                    </div>
                    Work From Home (WFH) Requests
                </h1>
                <p class="text-xs text-slate-400 mt-1">Apply for planned WFH at least 2 days in advance. Same-day WFH is strictly prohibited.</p>
            </div>
            <button onclick="document.getElementById('applyWfhModal').classList.remove('hidden')" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs shadow-lg shadow-indigo-600/30">
                <i data-lucide="plus" class="w-4 h-4"></i> Apply for WFH
            </button>
        </div>

        <?php $flash = getFlash(); if ($flash): ?>
            <div class="p-4 rounded-2xl text-xs font-medium <?= $flash['type'] === 'error' ? 'bg-rose-500/10 text-rose-300 border border-rose-500/30' : 'bg-emerald-500/10 text-emerald-300 border border-emerald-500/30' ?>">
                <?= $flash['message'] ?>
            </div>
        <?php endif; ?>

        <!-- Policy Card -->
        <div class="bg-indigo-950/40 border border-indigo-500/20 rounded-2xl p-4 sm:p-5 flex items-start gap-3.5">
            <i data-lucide="info" class="w-5 h-5 text-indigo-400 shrink-0 mt-0.5"></i>
            <div class="text-xs text-indigo-200 leading-relaxed space-y-1">
                <p class="font-bold text-white">Ecofone Strict WFH Policy Guidelines:</p>
                <p>1. WFH must be requested at least <strong>2 days in advance</strong> (Earliest date available: <?= formatDate($minAllowed) ?>).</p>
                <p>2. Requests must be approved by TL/HR at least <strong>1 day before</strong>.</p>
                <p>3. If unable to come on the same day, you must apply for <strong>Leave</strong> instead.</p>
            </div>
        </div>

        <!-- Requests List -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
            <h2 class="text-sm font-bold text-white uppercase tracking-wider text-slate-400">My WFH Request History</h2>
            <?php if (empty($requests)): ?>
                <div class="text-center py-10 text-slate-500 text-xs">No WFH requests submitted yet.</div>
            <?php else: ?>
                <div class="divide-y divide-slate-800/80">
                    <?php foreach ($requests as $r): ?>
                        <div class="py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2.5">
                                    <span class="text-sm font-bold text-white"><?= formatDate($r['wfh_date']) ?></span>
                                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full <?= $r['status'] === 'approved' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : ($r['status'] === 'rejected' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20') ?>">
                                        <?= strtoupper($r['status']) ?>
                                    </span>
                                </div>
                                <p class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($r['reason']) ?></p>
                            </div>
                            <div class="text-[11px] text-slate-500">
                                Applied: <?= date('d M Y, h:i A', strtotime($r['applied_at'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<div id="applyWfhModal" class="hidden fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl space-y-5">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-bold text-white">Apply for Work From Home</h3>
            <button onclick="document.getElementById('applyWfhModal').classList.add('hidden')" class="text-slate-400 hover:text-white">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form action="?action=apply-wfh" method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1">Select WFH Date (Min 2 days ahead)</label>
                <input type="date" name="wfh_date" min="<?= $minAllowed ?>" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1">Reason for WFH</label>
                <textarea name="reason" rows="3" required placeholder="Describe your planned deliverables and reason..." class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"></textarea>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('applyWfhModal').classList.add('hidden')" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-400 hover:text-white">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs shadow-lg shadow-indigo-600/30">Submit WFH Request</button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>