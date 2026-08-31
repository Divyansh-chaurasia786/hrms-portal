<!-- views/calling/queue.php (Simple BDA Data Entry Form & Calling Sheet) -->
<?php
$user = authUser();
$db = getDBConnection();
$today = date('Y-m-d');

// Fetch all entries for this BDA executive
$leads = $db->query("
    SELECT * FROM calling_leads 
    WHERE assigned_to = {$user['id']} 
    ORDER BY id DESC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$totalEntries = count($leads);
$totalInterested = 0;
$totalConverted = 0;
$totalFollowUp = 0;

foreach ($leads as $l) {
    if ($l['status'] === 'interested') $totalInterested++;
    elseif ($l['status'] === 'converted') $totalConverted++;
    elseif ($l['status'] === 'call_later') $totalFollowUp++;
}
?>

<div class="space-y-6" x-data="{
    searchQuery: '',
    statusFilter: 'all',
    editModalOpen: false,
    editLead: { id: 0, name: '', phone: '', email: '', city: '', course_service: '', status: 'interested', deal_value: 0, callback_datetime: '', notes: '' },
    openEdit(l) {
        this.editLead = { ...l };
        this.editModalOpen = true;
    }
}">

    <!-- Top Executive Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 sm:p-6 rounded-3xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold shrink-0 border border-indigo-100 shadow-2xs">
                <i data-lucide="clipboard-pen" class="w-6 h-6"></i>
            </div>
            <div>
                <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">BDA Calling Data Sheet</h1>
                <p class="text-xs text-slate-500 font-medium mt-0.5">Quickly log and record calling leads, follow-up notes, and conversion status.</p>
            </div>
        </div>

        <!-- Metric Badges -->
        <div class="flex items-center gap-2 flex-wrap">
            <span class="px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 text-xs font-bold border border-slate-200">
                Total: <?= $totalEntries ?>
            </span>
            <span class="px-3 py-1.5 rounded-xl bg-amber-50 text-amber-800 text-xs font-bold border border-amber-200">
                Interested: <?= $totalInterested ?>
            </span>
            <span class="px-3 py-1.5 rounded-xl bg-blue-50 text-blue-700 text-xs font-bold border border-blue-200">
                Follow-Up: <?= $totalFollowUp ?>
            </span>
            <span class="px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 text-xs font-bold border border-emerald-200">
                Converted: <?= $totalConverted ?>
            </span>
        </div>
    </div>

    <!-- 📝 FAST DATA ENTRY FORM CARD -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4">
        <div class="flex items-center gap-2 pb-3 border-b border-slate-100">
            <div class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-xs">
                ➕
            </div>
            <h3 class="text-sm font-extrabold text-slate-900 tracking-tight">Add New Calling / Lead Entry</h3>
        </div>

        <form action="?action=save-lead-data" method="POST" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Candidate Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" required placeholder="e.g. Ankit Sharma" class="w-full bg-slate-50/80 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-indigo-500 shadow-2xs">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Phone Number <span class="text-rose-500">*</span></label>
                    <input type="tel" name="phone" required placeholder="e.g. 9876543210" class="w-full bg-slate-50/80 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-indigo-500 shadow-2xs">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Email Address</label>
                    <input type="email" name="email" placeholder="ankit@gmail.com" class="w-full bg-slate-50/80 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-indigo-500 shadow-2xs">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">City / Location</label>
                    <input type="text" name="city" placeholder="e.g. Lucknow, Delhi" class="w-full bg-slate-50/80 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-indigo-500 shadow-2xs">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Course / Program Interested</label>
                    <input type="text" name="course_service" placeholder="e.g. Full Stack Development" class="w-full bg-slate-50/80 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-indigo-500 shadow-2xs">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Call Status / Outcome <span class="text-rose-500">*</span></label>
                    <select name="status" required class="w-full bg-slate-50/80 border border-slate-300 rounded-xl px-3 py-2 text-xs font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-500 shadow-2xs">
                        <option value="interested">🔥 Interested (Positive Lead)</option>
                        <option value="call_later">⏰ Call Later / Follow Up</option>
                        <option value="converted">🏆 Converted / Enrolled</option>
                        <option value="new">🆕 New Prospect</option>
                        <option value="not_interested">❌ Not Interested</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Follow-up Date / Time</label>
                    <input type="datetime-local" name="callback_datetime" class="w-full bg-slate-50/80 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-indigo-500 shadow-2xs">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Deal / Fee Amount (₹)</label>
                    <input type="number" name="deal_value" step="500" placeholder="e.g. 25000" class="w-full bg-slate-50/80 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-indigo-500 shadow-2xs">
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Discussion Summary / Remarks</label>
                <input type="text" name="notes" placeholder="e.g. Shared course syllabus on WhatsApp, requested callback on Saturday after 4 PM." class="w-full bg-slate-50/80 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-indigo-500 shadow-2xs">
            </div>

            <div class="flex justify-end pt-1">
                <button type="submit" class="px-5 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-600/25 transition flex items-center gap-1.5 cursor-pointer">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    <span>Save Lead Entry</span>
                </button>
            </div>
        </form>
    </div>

    <!-- 📋 CALLING DATA LOG SHEET (TABLE) -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden space-y-3 p-5 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-100">
            <div>
                <h3 class="text-base font-extrabold text-slate-900 tracking-tight">My Calling Log Sheet</h3>
                <p class="text-xs text-slate-400">All submitted records with 1-click WhatsApp and Edit options.</p>
            </div>

            <!-- Filters -->
            <div class="flex items-center gap-2 flex-wrap">
                <div class="relative">
                    <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5"></i>
                    <input type="text" x-model="searchQuery" placeholder="Search candidate, phone, city..." class="bg-slate-50 border border-slate-300 rounded-xl pl-8 pr-3 py-1.5 text-xs font-semibold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-500">
                </div>

                <select x-model="statusFilter" class="bg-slate-50 border border-slate-300 rounded-xl px-2.5 py-1.5 text-xs font-bold text-slate-700 focus:bg-white">
                    <option value="all">All Statuses</option>
                    <option value="interested">🔥 Interested</option>
                    <option value="call_later">⏰ Follow Up</option>
                    <option value="converted">🏆 Converted</option>
                    <option value="new">🆕 New</option>
                    <option value="not_interested">❌ Not Interested</option>
                </select>
            </div>
        </div>

        <?php if (empty($leads)): ?>
            <div class="text-center py-12 bg-slate-50/60 rounded-2xl">
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-400 flex items-center justify-center mx-auto mb-2">
                    <i data-lucide="file-plus" class="w-6 h-6"></i>
                </div>
                <h4 class="text-xs font-bold text-slate-700">No calling records yet</h4>
                <p class="text-[11px] text-slate-400 mt-0.5">Use the form above to add your first calling entry.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-extrabold uppercase text-slate-400 tracking-wider">
                            <th class="py-3 px-3">Candidate</th>
                            <th class="py-3 px-3">Contact</th>
                            <th class="py-3 px-3">Course / City</th>
                            <th class="py-3 px-3">Status</th>
                            <th class="py-3 px-3">Remarks</th>
                            <th class="py-3 px-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($leads as $l): ?>
                            <?php
                            $st = $l['status'] ?? 'new';
                            $statusBadge = match($st) {
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
                                <td class="py-3 px-3 align-top font-bold text-slate-900">
                                    <?= htmlspecialchars($l['name']) ?>
                                    <?php if (!empty($l['callback_datetime'])): ?>
                                        <div class="text-[10px] text-blue-600 font-semibold mt-0.5 flex items-center gap-1">
                                            <span>⏰ Callback: <?= date('d M, h:i A', strtotime($l['callback_datetime'])) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3 align-top">
                                    <div class="font-mono font-bold text-slate-800"><?= htmlspecialchars($l['phone']) ?></div>
                                    <?php if (!empty($l['email'])): ?>
                                        <div class="text-[10px] text-slate-400 truncate max-w-[130px]"><?= htmlspecialchars($l['email']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3 align-top">
                                    <div class="font-semibold text-slate-800"><?= htmlspecialchars($l['course_service'] ?: 'General') ?></div>
                                    <div class="text-[10px] text-slate-400"><?= htmlspecialchars($l['city'] ?: 'Not Specified') ?></div>
                                </td>
                                <td class="py-3 px-3 align-top">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase border <?= $statusBadge ?>">
                                        <?= ucfirst(str_replace('_', ' ', $st)) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-3 align-top text-slate-600 max-w-xs">
                                    <div class="text-[11px] line-clamp-2"><?= htmlspecialchars($l['notes'] ?: '-') ?></div>
                                </td>
                                <td class="py-3 px-3 align-top text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <?php if (!empty($waPhone)): ?>
                                            <a href="https://api.whatsapp.com/send?phone=<?= $waPhone ?>" target="_blank" class="w-7 h-7 rounded-lg bg-emerald-50 hover:bg-emerald-600 text-emerald-600 hover:text-white flex items-center justify-center transition" title="Message on WhatsApp">
                                                <i data-lucide="message-circle" class="w-3.5 h-3.5"></i>
                                            </a>
                                        <?php endif; ?>

                                        <button type="button" @click="openEdit(<?= htmlspecialchars(json_encode($l)) ?>)" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-indigo-50 text-slate-600 hover:text-indigo-600 flex items-center justify-center transition cursor-pointer" title="Edit Entry">
                                            <i data-lucide="edit-2" class="w-3.5 h-3.5"></i>
                                        </button>

                                        <form action="?action=delete-bda-lead" method="POST" class="inline m-0" onsubmit="return confirm('Delete this record?');">
                                            <input type="hidden" name="lead_id" value="<?= (int)$l['id'] ?>">
                                            <button type="submit" class="w-7 h-7 rounded-lg bg-rose-50 hover:bg-rose-600 text-rose-600 hover:text-white flex items-center justify-center transition cursor-pointer" title="Delete">
                                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- ✏️ EDIT LEAD MODAL -->
    <div x-show="editModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4" x-cloak>
        <div @click.away="editModalOpen = false" class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 text-left my-auto space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 class="text-base font-extrabold text-slate-900">Edit Calling Entry</h3>
                <button type="button" @click="editModalOpen = false" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>

            <form action="?action=save-lead-data" method="POST" class="space-y-3.5">
                <input type="hidden" name="lead_id" :value="editLead.id">

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-700 uppercase mb-1">Candidate Name *</label>
                        <input type="text" name="name" x-model="editLead.name" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-700 uppercase mb-1">Phone Number *</label>
                        <input type="tel" name="phone" x-model="editLead.phone" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-700 uppercase mb-1">Status</label>
                        <select name="status" x-model="editLead.status" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-bold">
                            <option value="interested">🔥 Interested</option>
                            <option value="call_later">⏰ Follow Up</option>
                            <option value="converted">🏆 Converted</option>
                            <option value="new">🆕 New</option>
                            <option value="not_interested">❌ Not Interested</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-700 uppercase mb-1">Follow-up Date/Time</label>
                        <input type="datetime-local" name="callback_datetime" x-model="editLead.callback_datetime" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-700 uppercase mb-1">Remarks / Discussion</label>
                    <textarea name="notes" x-model="editLead.notes" rows="2" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 text-xs font-medium"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="editModalOpen = false" class="px-4 py-2 bg-slate-100 rounded-xl text-xs font-bold text-slate-600">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-md shadow-indigo-600/30 transition cursor-pointer">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>