<!-- views/calling/queue.php -->
<?php
$user = authUser();
$db = getDBConnection();
$today = date('Y-m-d');

// Safe Data Initialization
if (!isset($leads)) {
    $leads = $db->query("
        SELECT * FROM calling_leads 
        WHERE assigned_to = {$user['id']} 
        ORDER BY FIELD(status, 'new', 'call_later', 'interested', 'not_interested', 'converted'), id DESC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

if (!isset($counts)) {
    $counts = [
        'total' => count($leads),
        'new' => 0,
        'interested' => 0,
        'call_later' => 0,
        'converted' => 0,
        'not_interested' => 0,
        'today_calls' => 0
    ];
    foreach ($leads as $l) {
        if (isset($counts[$l['status']])) $counts[$l['status']]++;
    }
    $counts['today_calls'] = (int)$db->query("SELECT COUNT(*) FROM call_logs WHERE caller_id = {$user['id']} AND call_date = '{$today}'")->fetchColumn();
}
?>

<div class="space-y-6">
    <!-- Header Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-600 text-white flex items-center justify-center font-bold shrink-0 shadow-sm">
                <i data-lucide="phone-call" class="w-5 h-5"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">My Calling Queue & Lead Pipeline</h1>
                <p class="text-xs text-slate-500 mt-0.5">1-Tap direct calling, record dispositions, client feedback notes, and follow-ups.</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <span class="px-3 py-1.5 rounded-xl bg-indigo-50 text-indigo-700 text-xs font-bold border border-indigo-100">
                <?= (int)$counts['total'] ?> Assigned Leads
            </span>
            <span class="px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 text-xs font-bold border border-emerald-100">
                <?= (int)$counts['today_calls'] ?> Calls Made Today
            </span>
        </div>
    </div>

    <!-- 4 Stats Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">New / Fresh</span>
            <div class="text-2xl font-extrabold text-indigo-600 mt-0.5"><?= (int)$counts['new'] ?></div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Interested</span>
            <div class="text-2xl font-extrabold text-blue-600 mt-0.5"><?= (int)$counts['interested'] ?></div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Follow-Up Required</span>
            <div class="text-2xl font-extrabold text-amber-600 mt-0.5"><?= (int)$counts['call_later'] ?></div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Deals Converted</span>
            <div class="text-2xl font-extrabold text-emerald-600 mt-0.5"><?= (int)$counts['converted'] ?></div>
        </div>
    </div>

    <!-- Assigned Prospects List -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-4">
        <h2 class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center justify-between">
            <span>My Assigned Leads Roster</span>
            <span class="text-[10px] text-slate-400"><?= count($leads) ?> Total Leads</span>
        </h2>

        <?php if (empty($leads)): ?>
            <div class="text-center py-12 bg-slate-50 rounded-2xl text-slate-400 text-xs">
                No calling leads currently assigned to your queue. Please approach your Team Lead!
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($leads as $l): ?>
                    <div class="bg-slate-50/70 border border-slate-200 rounded-2xl p-4 space-y-3 hover:border-indigo-300 hover:shadow-xs transition">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900"><?= htmlspecialchars($l['lead_name']) ?></h3>
                                <p class="text-[11px] text-slate-400 font-medium"><?= htmlspecialchars($l['city'] ?: 'General') ?></p>
                            </div>
                            <span class="text-[10px] font-extrabold uppercase px-2.5 py-0.5 rounded-full <?= $l['status'] === 'converted' ? 'bg-emerald-100 text-emerald-800' : ($l['status'] === 'interested' ? 'bg-blue-100 text-blue-800' : ($l['status'] === 'call_later' ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700')) ?>">
                                <?= str_replace('_', ' ', strtoupper($l['status'])) ?>
                            </span>
                        </div>

                        <div class="bg-white p-2.5 rounded-xl border border-slate-200/80 flex items-center justify-between">
                            <div class="text-xs font-mono text-indigo-900 font-bold tracking-wide">
                                📞 <?= htmlspecialchars($l['phone']) ?>
                            </div>
                            <a href="tel:<?= urlencode($l['phone']) ?>" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs transition">
                                <i data-lucide="phone" class="w-3.5 h-3.5"></i> Call Now
                            </a>
                        </div>

                        <?php if (!empty($l['notes'])): ?>
                            <p class="text-[11px] text-slate-600 italic bg-white p-2 rounded-lg border border-slate-100">"<?= htmlspecialchars($l['notes']) ?>"</p>
                        <?php endif; ?>

                        <button type="button" onclick="openDispositionModal(<?= $l['id'] ?>, '<?= htmlspecialchars(addslashes($l['lead_name'])) ?>', '<?= $l['status'] ?>', '<?= htmlspecialchars(addslashes($l['notes'] ?? '')) ?>')" class="w-full py-2 rounded-xl bg-white hover:bg-indigo-50 border border-slate-200 hover:border-indigo-300 text-slate-700 hover:text-indigo-700 text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer shadow-2xs">
                            <i data-lucide="edit-3" class="w-3.5 h-3.5"></i> Update Call Outcome
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Outcome Modal -->
<div id="dispositionModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" onclick="closeDispositionModal()"></div>
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative bg-white border border-slate-200 rounded-3xl p-6 max-w-md w-full shadow-2xl space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 class="font-bold text-sm text-slate-900" id="modalLeadTitle">Update Call Outcome</h3>
                <button type="button" onclick="closeDispositionModal()" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <input type="hidden" id="dispLeadId">

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Call Outcome Status *</label>
                <select id="dispStatus" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-indigo-500">
                    <option value="interested">🟢 Interested Prospect</option>
                    <option value="call_later">🟡 Call Back Later / Busy</option>
                    <option value="converted">🏆 Converted / Deal Closed</option>
                    <option value="not_interested">🔴 Not Interested</option>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Call Notes / Feedback Summary</label>
                <textarea id="dispNotes" rows="3" placeholder="e.g. Client interested in pricing plan, requested callback at 4 PM..." class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Next Follow-Up (Optional)</label>
                <input type="datetime-local" id="dispFollowup" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeDispositionModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition cursor-pointer">Cancel</button>
                <button type="button" onclick="saveDisposition()" id="btnSaveOutcome" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm transition cursor-pointer">Save Outcome</button>
            </div>
        </div>
    </div>
</div>

<script>
function openDispositionModal(id, name, status, notes) {
    document.getElementById('dispLeadId').value = id;
    document.getElementById('modalLeadTitle').innerText = 'Call Outcome: ' + name;
    document.getElementById('dispStatus').value = status;
    document.getElementById('dispNotes').value = notes;
    document.getElementById('dispositionModal').classList.remove('hidden');
}

function closeDispositionModal() {
    document.getElementById('dispositionModal').classList.add('hidden');
}

function saveDisposition() {
    const id = document.getElementById('dispLeadId').value;
    const status = document.getElementById('dispStatus').value;
    const notes = document.getElementById('dispNotes').value;
    const followup = document.getElementById('dispFollowup').value;
    const btn = document.getElementById('btnSaveOutcome');

    btn.innerText = 'Saving...';
    btn.disabled = true;

    fetch('?action=update-calling-disposition', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `lead_id=${id}&status=${status}&notes=${encodeURIComponent(notes)}&next_followup_at=${followup}`
    })
    .then(r => r.json())
    .then(d => {
        window.location.reload();
    })
    .catch(() => {
        window.location.reload();
    });
}
</script>