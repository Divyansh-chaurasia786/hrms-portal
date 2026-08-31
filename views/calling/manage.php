<!-- views/calling/manage.php (Master Calling Data Sheet for TL / Admin) -->
<?php
$user = authUser() ?: ['id' => 1, 'role' => 'admin', 'name' => 'Admin'];
$db = getDBConnection();
$today = date('Y-m-d');

// 1. Fetch active BDA team executives
$executives = $db->query("
    SELECT id, name, emp_id, designation 
    FROM users 
    WHERE status = 'active' 
      AND (department_name = 'Business Development' OR department_name LIKE '%Calling%' OR designation LIKE '%BDA%')
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

// 2. Fetch all calling data entries
$allLeads = $db->query("
    SELECT l.*, u.name as executive_name, u.emp_id as executive_emp_id
    FROM calling_leads l
    LEFT JOIN users u ON l.assigned_to = u.id
    ORDER BY l.id DESC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$totalLeads = count($allLeads);
$totalInterested = 0;
$totalConverted = 0;
$totalFollowUp = 0;

foreach ($allLeads as $l) {
    if ($l['status'] === 'interested') $totalInterested++;
    elseif ($l['status'] === 'converted') $totalConverted++;
    elseif ($l['status'] === 'call_later') $totalFollowUp++;
}
?>

<div class="space-y-6" x-data="{
    searchQuery: '',
    statusFilter: 'all',
    execFilter: 'all',
    uploadModalOpen: <?= (isset($_GET['modal']) && $_GET['modal'] === 'upload') ? 'true' : 'false' ?>
}">

    <!-- Top Command Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white p-5 sm:p-6 rounded-3xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-indigo-600 to-purple-600 text-white flex items-center justify-center font-bold shrink-0 shadow-md shadow-indigo-600/20">
                <i data-lucide="database" class="w-6 h-6"></i>
            </div>
            <div>
                <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">BDA Team Calling & Data Master</h1>
                <p class="text-xs text-slate-500 font-medium mt-0.5">Centralized repository of all candidate inquiries, calling submissions, and follow-ups.</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap">
            <button type="button" @click="uploadModalOpen = true" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-600/25 transition flex items-center gap-1.5 cursor-pointer">
                <i data-lucide="upload-cloud" class="w-4 h-4"></i>
                <span>Bulk CSV Upload</span>
            </button>

            <a href="?action=export-calling-history" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md shadow-emerald-600/25 transition flex items-center gap-1.5 cursor-pointer">
                <i data-lucide="download" class="w-4 h-4"></i>
                <span>Export to Excel / CSV</span>
            </a>
        </div>
    </div>

    <!-- 4 KPI Metrics -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-2xs">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Data Records</span>
            <div class="text-2xl font-extrabold text-slate-900 mt-1"><?= $totalLeads ?></div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-2xs">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Interested Leads 🔥</span>
            <div class="text-2xl font-extrabold text-amber-600 mt-1"><?= $totalInterested ?></div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-2xs">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Follow-Up Required ⏰</span>
            <div class="text-2xl font-extrabold text-blue-600 mt-1"><?= $totalFollowUp ?></div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-2xs">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Converted Deals 🏆</span>
            <div class="text-2xl font-extrabold text-emerald-600 mt-1"><?= $totalConverted ?></div>
        </div>
    </div>

    <!-- 📋 MASTER CALLING DATA SHEET -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-100">
            <div>
                <h3 class="text-base font-extrabold text-slate-900 tracking-tight">Master Calling Submissions Roster</h3>
                <p class="text-xs text-slate-400">Filter by executive caller, inquiry status, or search candidate details.</p>
            </div>

            <!-- Search & Filters -->
            <div class="flex items-center gap-2 flex-wrap">
                <div class="relative">
                    <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5"></i>
                    <input type="text" x-model="searchQuery" placeholder="Search candidate, phone, city..." class="bg-slate-50 border border-slate-300 rounded-xl pl-8 pr-3 py-1.5 text-xs font-semibold text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-500">
                </div>

                <select x-model="execFilter" class="bg-slate-50 border border-slate-300 rounded-xl px-2.5 py-1.5 text-xs font-bold text-slate-700 focus:bg-white">
                    <option value="all">All Executives</option>
                    <?php foreach ($executives as $ex): ?>
                        <option value="<?= (int)$ex['id'] ?>"><?= htmlspecialchars($ex['name']) ?></option>
                    <?php endforeach; ?>
                </select>

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

        <?php if (empty($allLeads)): ?>
            <div class="text-center py-12 bg-slate-50/60 rounded-2xl">
                <p class="text-xs font-bold text-slate-600">No calling records found</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Upload a CSV or have executives log their data.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-extrabold uppercase text-slate-400 tracking-wider">
                            <th class="py-3 px-3">Candidate</th>
                            <th class="py-3 px-3">Phone / Contact</th>
                            <th class="py-3 px-3">Course / City</th>
                            <th class="py-3 px-3">Status</th>
                            <th class="py-3 px-3">Executive</th>
                            <th class="py-3 px-3">Remarks / Follow-up</th>
                            <th class="py-3 px-3 text-right">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($allLeads as $l): ?>
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
                                (execFilter === 'all' || execFilter === '<?= (int)$l['assigned_to'] ?>') &&
                                (!searchQuery || '<?= strtolower(addslashes($l['name'] . ' ' . $l['phone'] . ' ' . $l['city'] . ' ' . $l['course_service'] . ' ' . ($l['executive_name'] ?? ''))) ?>'.includes(searchQuery.toLowerCase()))
                            ">
                                <td class="py-3 px-3 align-top font-bold text-slate-900">
                                    <?= htmlspecialchars($l['name']) ?>
                                    <?php if (!empty($l['deal_value']) && (float)$l['deal_value'] > 0): ?>
                                        <div class="text-[10px] text-emerald-600 font-bold">₹<?= number_format((float)$l['deal_value'], 0) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3 align-top font-mono font-bold text-slate-800">
                                    <?= htmlspecialchars($l['phone']) ?>
                                    <?php if (!empty($l['email'])): ?>
                                        <div class="text-[10px] text-slate-400 font-sans font-normal truncate max-w-[120px]"><?= htmlspecialchars($l['email']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3 align-top">
                                    <div class="font-semibold text-slate-800"><?= htmlspecialchars($l['course_service'] ?: 'General') ?></div>
                                    <div class="text-[10px] text-slate-400"><?= htmlspecialchars($l['city'] ?: '-') ?></div>
                                </td>
                                <td class="py-3 px-3 align-top">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase border <?= $statusBadge ?>">
                                        <?= ucfirst(str_replace('_', ' ', $st)) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-3 align-top">
                                    <span class="font-bold text-indigo-700"><?= htmlspecialchars($l['executive_name'] ?: 'Unassigned') ?></span>
                                </td>
                                <td class="py-3 px-3 align-top max-w-xs text-slate-600">
                                    <div class="text-[11px] line-clamp-2"><?= htmlspecialchars($l['notes'] ?: '-') ?></div>
                                    <?php if (!empty($l['callback_datetime'])): ?>
                                        <div class="text-[10px] text-blue-600 font-semibold mt-0.5">
                                            ⏰ Callback: <?= date('d M, h:i A', strtotime($l['callback_datetime'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3 align-top text-right text-slate-400 font-mono text-[11px] whitespace-nowrap">
                                    <?= date('d M, Y', strtotime($l['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- 📤 BULK CSV UPLOAD MODAL -->
    <div x-show="uploadModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4" x-cloak>
        <div @click.away="uploadModalOpen = false" class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 text-left my-auto space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 class="text-base font-extrabold text-slate-900">Upload Leads CSV</h3>
                <button type="button" @click="uploadModalOpen = false" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>

            <form action="?action=upload-bda-leads" method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Select CSV File *</label>
                    <input type="file" name="lead_file" accept=".csv" required class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 text-xs">
                    <p class="text-[10px] text-slate-400 mt-1">Columns: Name, Phone, Email, City, Course, Budget</p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="uploadModalOpen = false" class="px-4 py-2 bg-slate-100 rounded-xl text-xs font-bold text-slate-600">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-md shadow-indigo-600/25 transition cursor-pointer">
                        Upload Leads
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>