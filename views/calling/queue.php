<!-- views/calling/queue.php (BDA Executive Calling Roster with 1-Click Copy, Call, & Status Fill) -->
<?php
$user = authUser();
$db = getDBConnection();
$today = date('Y-m-d');

// Fetch all assigned leads for this BDA executive
$leads = $db->query("
    SELECT * FROM calling_leads 
    WHERE assigned_to = {$user['id']} 
    ORDER BY FIELD(status, 'new', 'call_later', 'interested', 'converted', 'not_interested'), id DESC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$totalAssigned = count($leads);
$pendingCalls = 0;
$totalInterested = 0;
$totalFollowUp = 0;
$totalConverted = 0;

foreach ($leads as $l) {
    if ($l['status'] === 'new') $pendingCalls++;
    elseif ($l['status'] === 'interested') $totalInterested++;
    elseif ($l['status'] === 'call_later') $totalFollowUp++;
    elseif ($l['status'] === 'converted') $totalConverted++;
}
?>

<div class="space-y-6" x-data="{
    searchQuery: '',
    statusFilter: 'all',
    copiedId: null,
    statusModalOpen: false,
    selectedLead: { id: 0, name: '', phone: '', status: 'interested', notes: '', callback_datetime: '', deal_value: 0 },
    copyNumber(phone, id) {
        navigator.clipboard.writeText(phone);
        this.copiedId = id;
        setTimeout(() => { this.copiedId = null; }, 2000);
    },
    openStatusModal(lead) {
        this.selectedLead = { ...lead };
        this.statusModalOpen = true;
    }
}">

    <!-- Top Executive Header Strip -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 sm:p-6 rounded-3xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold shrink-0 border border-indigo-100 shadow-2xs">
                <i data-lucide="phone-call" class="w-6 h-6"></i>
            </div>
            <div>
                <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">My Assigned Calling Sheet</h1>
                <p class="text-xs text-slate-500 font-medium mt-0.5">Click to copy number, dial directly, and fill status after call.</p>
            </div>
        </div>

        <!-- Quick Summary Pills -->
        <div class="flex items-center gap-2 flex-wrap">
            <span class="px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 text-xs font-bold border border-slate-200">
                Assigned: <?= $totalAssigned ?>
            </span>
            <span class="px-3 py-1.5 rounded-xl bg-purple-50 text-purple-800 text-xs font-bold border border-purple-200">
                Pending: <?= $pendingCalls ?>
            </span>
            <span class="px-3 py-1.5 rounded-xl bg-amber-50 text-amber-800 text-xs font-bold border border-amber-200">
                Interested: <?= $totalInterested ?>
            </span>
            <span class="px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 text-xs font-bold border border-emerald-200">
                Converted: <?= $totalConverted ?>
            </span>
        </div>
    </div>

    <!-- 📋 CALLING NUMBERS SHEET -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-100">
            <div>
                <h3 class="text-base font-extrabold text-slate-900 tracking-tight">Assigned Numbers Queue</h3>
                <p class="text-xs text-slate-400">Copy number or tap Call ➔ Update status after conversation.</p>
            </div>

            <!-- Search & Filters -->
            <div class="flex items-center gap-2 flex-wrap">
                <div class="relative">
                    <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5"></i>
                    <input type="text" x-model="searchQuery" placeholder="Search candidate, phone, city..." class="bg-slate-50 border border-slate-300 rounded-xl pl-8 pr-3 py-1.5 text-xs font-semibold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-500">
                </div>

                <select x-model="statusFilter" class="bg-slate-50 border border-slate-300 rounded-xl px-2.5 py-1.5 text-xs font-bold text-slate-700 focus:bg-white">
                    <option value="all">All Statuses</option>
                    <option value="new">🆕 Pending / New</option>
                    <option value="interested">🔥 Interested</option>
                    <option value="call_later">⏰ Follow Up</option>
                    <option value="converted">🏆 Converted</option>
                    <option value="not_interested">❌ Not Interested</option>
                </select>
            </div>
        </div>

        <?php if (empty($leads)): ?>
            <div class="text-center py-12 bg-slate-50/60 rounded-2xl">
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-400 flex items-center justify-center mx-auto mb-2">
                    <i data-lucide="inbox" class="w-6 h-6"></i>
                </div>
                <h4 class="text-xs font-bold text-slate-700">No leads assigned yet</h4>
                <p class="text-[11px] text-slate-400 mt-0.5">When Team Lead uploads and divides numbers, they will appear here automatically.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-extrabold uppercase text-slate-400 tracking-wider">
                            <th class="py-3 px-3">Candidate</th>
                            <th class="py-3 px-3">Phone Number & Copy</th>
                            <th class="py-3 px-3">Course / City</th>
                            <th class="py-3 px-3">Call Status</th>
                            <th class="py-3 px-3">Remarks / Follow-up</th>
                            <th class="py-3 px-3 text-right">Fill Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($leads as $l): ?>
                            <?php
                            $st = $l['status'] ?? 'new';
                            $statusBadge = match($st) {
                                'new' => 'bg-purple-50 text-purple-700 border-purple-200',
                                'interested' => 'bg-amber-50 text-amber-800 border-amber-200',
                                'call_later' => 'bg-blue-50 text-blue-700 border-blue-200',
                                'converted' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'not_interested' => 'bg-rose-50 text-rose-700 border-rose-200',
                                default => 'bg-slate-100 text-slate-600 border-slate-200'
                            };
                            $waPhone = preg_replace('/[^0-9]/', '', $l['phone']);
                            ?>
                            <tr class="hover:bg-slate-50/80 transition" x-show="
                                (statusFilter === 'all' || statusFilter === '<?= $st ?>') &&
                                (!searchQuery || '<?= strtolower(addslashes($l['name'] . ' ' . $l['phone'] . ' ' . $l['city'] . ' ' . $l['course_service'])) ?>'.includes(searchQuery.toLowerCase()))
                            ">
                                <td class="py-3.5 px-3 align-middle font-bold text-slate-900">
                                    <div class="flex items-center gap-2">
                                        <span><?= htmlspecialchars($l['name']) ?></span>
                                    </div>
                                    <?php if (!empty($l['deal_value']) && (float)$l['deal_value'] > 0): ?>
                                        <div class="text-[10px] text-emerald-600 font-bold">₹<?= number_format((float)$l['deal_value'], 0) ?></div>
                                    <?php endif; ?>
                                </td>

                                <!-- 📋 COPYABLE PHONE NUMBER & QUICK ACTIONS -->
                                <td class="py-3.5 px-3 align-middle">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-mono font-extrabold text-slate-900 text-sm tracking-wide bg-slate-100 px-2 py-0.5 rounded-lg border border-slate-200">
                                            <?= htmlspecialchars($l['phone']) ?>
                                        </span>

                                        <!-- 1-Click Copy Number Button -->
                                        <button type="button" @click="copyNumber('<?= htmlspecialchars($l['phone']) ?>', <?= (int)$l['id'] ?>)" class="px-2 py-1 bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white border border-indigo-200 rounded-lg text-[11px] font-bold transition flex items-center gap-1 cursor-pointer shadow-2xs" :title="copiedId === <?= (int)$l['id'] ?> ? 'Copied!' : 'Copy Phone Number'">
                                            <i data-lucide="copy" class="w-3.5 h-3.5" x-show="copiedId !== <?= (int)$l['id'] ?>"></i>
                                            <i data-lucide="check" class="w-3.5 h-3.5 text-emerald-400" x-show="copiedId === <?= (int)$l['id'] ?>" style="display: none;"></i>
                                            <span x-text="copiedId === <?= (int)$l['id'] ?> ? 'Copied! ✓' : 'Copy'"></span>
                                        </button>

                                        <!-- 1-Click Native Dial -->
                                        <a href="tel:<?= htmlspecialchars($l['phone']) ?>" class="p-1 bg-emerald-50 hover:bg-emerald-600 text-emerald-600 hover:text-white border border-emerald-200 rounded-lg transition" title="Dial from SIM">
                                            <i data-lucide="phone" class="w-3.5 h-3.5"></i>
                                        </a>

                                        <!-- 1-Click WhatsApp -->
                                        <?php if (!empty($waPhone)): ?>
                                            <a href="https://api.whatsapp.com/send?phone=<?= $waPhone ?>" target="_blank" class="p-1 bg-emerald-50 hover:bg-emerald-600 text-emerald-600 hover:text-white border border-emerald-200 rounded-lg transition" title="WhatsApp Chat">
                                                <i data-lucide="message-circle" class="w-3.5 h-3.5"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td class="py-3.5 px-3 align-middle">
                                    <div class="font-semibold text-slate-800"><?= htmlspecialchars($l['course_service'] ?: 'General') ?></div>
                                    <div class="text-[10px] text-slate-400"><?= htmlspecialchars($l['city'] ?: '-') ?></div>
                                </td>

                                <td class="py-3.5 px-3 align-middle">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase border <?= $statusBadge ?>">
                                        <?= ucfirst(str_replace('_', ' ', $st)) ?>
                                    </span>
                                </td>

                                <td class="py-3.5 px-3 align-middle max-w-xs text-slate-600">
                                    <div class="text-[11px] line-clamp-2"><?= htmlspecialchars($l['notes'] ?: 'No notes added yet.') ?></div>
                                    <?php if (!empty($l['callback_datetime'])): ?>
                                        <div class="text-[10px] text-blue-600 font-semibold mt-0.5">
                                            ⏰ Callback: <?= date('d M, h:i A', strtotime($l['callback_datetime'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- 📝 FILL STATUS BUTTON -->
                                <td class="py-3.5 px-3 align-middle text-right whitespace-nowrap">
                                    <button type="button" @click="openStatusModal(<?= htmlspecialchars(json_encode($l)) ?>)" class="px-3 py-1.5 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white rounded-xl text-xs font-bold shadow-xs transition flex items-center gap-1.5 cursor-pointer ml-auto">
                                        <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                                        <span>Fill Status</span>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- 📝 FILL CALL STATUS MODAL (FAST POST-CALL LOGGING) -->
    <div x-show="statusModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4" x-cloak>
        <div @click.away="statusModalOpen = false" class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 text-left my-auto space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div>
                    <h3 class="text-base font-extrabold text-slate-900">Post-Call Status & Notes</h3>
                    <p class="text-xs text-slate-400">Candidate: <strong class="text-slate-800" x-text="selectedLead.name + ' (' + selectedLead.phone + ')'"></strong></p>
                </div>
                <button type="button" @click="statusModalOpen = false" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>

            <form action="?action=update-call-status" method="POST" class="space-y-4">
                <input type="hidden" name="lead_id" :value="selectedLead.id">

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Call Outcome / Status *</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 hover:bg-amber-50/50 cursor-pointer font-bold text-xs">
                            <input type="radio" name="status" value="interested" x-model="selectedLead.status" class="text-amber-600">
                            <span>🔥 Interested</span>
                        </label>
                        <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 hover:bg-blue-50/50 cursor-pointer font-bold text-xs">
                            <input type="radio" name="status" value="call_later" x-model="selectedLead.status" class="text-blue-600">
                            <span>⏰ Follow Up</span>
                        </label>
                        <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 hover:bg-emerald-50/50 cursor-pointer font-bold text-xs">
                            <input type="radio" name="status" value="converted" x-model="selectedLead.status" class="text-emerald-600">
                            <span>🏆 Converted</span>
                        </label>
                        <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 hover:bg-rose-50/50 cursor-pointer font-bold text-xs">
                            <input type="radio" name="status" value="not_interested" x-model="selectedLead.status" class="text-rose-600">
                            <span>❌ Not Interested</span>
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-700 uppercase mb-1">Follow-up Date / Time</label>
                        <input type="datetime-local" name="callback_datetime" x-model="selectedLead.callback_datetime" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-700 uppercase mb-1">Deal / Fee Value (₹)</label>
                        <input type="number" name="deal_value" x-model="selectedLead.deal_value" step="500" placeholder="e.g. 25000" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-700 uppercase mb-1">Discussion Remarks / Notes</label>
                    <textarea name="notes" x-model="selectedLead.notes" rows="3" required placeholder="What was discussed on the call? (e.g. Interested in weekday batch, requested syllabus on WhatsApp)" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs font-medium focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="statusModalOpen = false" class="px-4 py-2 bg-slate-100 rounded-xl text-xs font-bold text-slate-600">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-600/30 transition flex items-center gap-1.5 cursor-pointer">
                        <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                        <span>Save Call Status</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>