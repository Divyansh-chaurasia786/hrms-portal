<?php
// views/calling/queue.php
$title = "My Calling Queue - Ecofone HRMS";
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>
<main class="flex-1 min-w-0 overflow-y-auto bg-slate-900 text-slate-100 p-4 sm:p-8">
    <div class="max-w-6xl mx-auto space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center">
                        <i data-lucide="phone-call" class="w-5 h-5"></i>
                    </div>
                    My Calling Queue & Lead Tracker
                </h1>
                <p class="text-xs text-slate-400 mt-1">1-Tap calling, status dispositions, and follow-up management.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="px-3 py-1.5 rounded-xl bg-indigo-500/20 text-indigo-300 text-xs font-bold border border-indigo-500/30">
                    <?= $counts['total'] ?> Assigned Leads
                </span>
            </div>
        </div>

        <!-- Metrics Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl">
                <div class="text-[11px] font-bold text-slate-500 uppercase">New Leads</div>
                <div class="text-xl font-extrabold text-indigo-400 mt-1"><?= $counts['new'] ?></div>
            </div>
            <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl">
                <div class="text-[11px] font-bold text-slate-500 uppercase">Interested</div>
                <div class="text-xl font-extrabold text-emerald-400 mt-1"><?= $counts['interested'] ?></div>
            </div>
            <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl">
                <div class="text-[11px] font-bold text-slate-500 uppercase">Call Later</div>
                <div class="text-xl font-extrabold text-amber-400 mt-1"><?= $counts['call_later'] ?></div>
            </div>
            <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl">
                <div class="text-[11px] font-bold text-slate-500 uppercase">Converted</div>
                <div class="text-xl font-extrabold text-purple-400 mt-1"><?= $counts['converted'] ?></div>
            </div>
        </div>

        <!-- Calling List -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
            <h2 class="text-sm font-bold text-white uppercase tracking-wider text-slate-400">Assigned Prospects</h2>
            <?php if (empty($leads)): ?>
                <div class="text-center py-12 text-slate-500 text-xs">No leads assigned in your queue right now. Contact your Team Lead!</div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($leads as $l): ?>
                        <div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-5 space-y-3 hover:border-slate-700 transition">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h3 class="text-base font-bold text-white"><?= htmlspecialchars($l['lead_name']) ?></h3>
                                    <p class="text-xs text-slate-400"><?= htmlspecialchars($l['city']) ?></p>
                                </div>
                                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full <?= $l['status'] === 'converted' ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' : ($l['status'] === 'interested' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : ($l['status'] === 'call_later' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-slate-800 text-slate-400')) ?>">
                                    <?= str_replace('_', ' ', strtoupper($l['status'])) ?>
                                </span>
                            </div>

                            <div class="bg-slate-900 p-3 rounded-xl border border-slate-800/80 flex items-center justify-between">
                                <div class="text-xs font-mono text-indigo-300 font-bold"><?= htmlspecialchars($l['phone']) ?></div>
                                <a href="tel:<?= urlencode($l['phone']) ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md shadow-emerald-600/30 transition">
                                    <i data-lucide="phone" class="w-3.5 h-3.5"></i> Call Now
                                </a>
                            </div>

                            <?php if (!empty($l['notes'])): ?>
                                <p class="text-[11px] text-slate-400 italic bg-slate-900/50 p-2 rounded-lg">"<?= htmlspecialchars($l['notes']) ?>"</p>
                            <?php endif; ?>

                            <button onclick="openDispositionModal(<?= $l['id'] ?>, '<?= htmlspecialchars($l['lead_name']) ?>', '<?= $l['status'] ?>', '<?= htmlspecialchars($l['notes'] ?? '') ?>')" class="w-full py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold transition flex items-center justify-center gap-1.5">
                                <i data-lucide="edit-3" class="w-3.5 h-3.5"></i> Update Call Outcome
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Outcome Modal -->
<div id="dispositionModal" class="hidden fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl space-y-4">
        <h3 class="text-base font-bold text-white" id="modalLeadTitle">Update Call Outcome</h3>
        <input type="hidden" id="dispLeadId">
        <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">Call Outcome Status</label>
            <select id="dispStatus" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white">
                <option value="interested">🟢 Interested</option>
                <option value="call_later">🟡 Call Back / Busy</option>
                <option value="not_interested">🔴 Not Interested</option>
                <option value="converted">🏆 Converted / Closed</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">Call Notes / Discussion Summary</label>
            <textarea id="dispNotes" rows="2" placeholder="e.g. Client requested callback at 4 PM..." class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white"></textarea>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-300 mb-1">Next Follow-up (Optional)</label>
            <input type="datetime-local" id="dispFollowup" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white">
        </div>
        <div class="flex items-center justify-end gap-3 pt-2">
            <button onclick="document.getElementById('dispositionModal').classList.add('hidden')" class="px-4 py-2 rounded-xl text-xs text-slate-400">Cancel</button>
            <button onclick="saveDisposition()" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs">Save Outcome</button>
        </div>
    </div>
</div>

<script>
function openDispositionModal(id, name, status, notes) {
    document.getElementById('dispLeadId').value = id;
    document.getElementById('modalLeadTitle').innerText = 'Update: ' + name;
    document.getElementById('dispStatus').value = status;
    document.getElementById('dispNotes').value = notes;
    document.getElementById('dispositionModal').classList.remove('hidden');
}
function saveDisposition() {
    const id = document.getElementById('dispLeadId').value;
    const status = document.getElementById('dispStatus').value;
    const notes = document.getElementById('dispNotes').value;
    const followup = document.getElementById('dispFollowup').value;

    fetch('?action=update-calling-disposition', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `lead_id=${id}&status=${status}&notes=${encodeURIComponent(notes)}&next_followup_at=${followup}`
    }).then(() => window.location.reload());
}
</script>
<?php require __DIR__ . '/../layouts/footer.php'; ?>