<!-- views/admin/holidays.php -->
<?php
$user = authUser();
$db = getDBConnection();
$holidays = $db->query("SELECT * FROM holidays ORDER BY holiday_date ASC")->fetchAll();
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Holidays & Company Policies</h1>
    <p class="text-sm text-slate-500 mt-0.5">Annual public holidays and organizational leave guidelines.</p>
</div>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden max-w-2xl">
    <div class="overflow-x-auto no-scrollbar">
        <table class="w-full text-left text-sm text-slate-600 min-w-[500px]">
            <thead class="bg-slate-50 text-slate-400 text-[11px] uppercase font-semibold border-b border-slate-200">
                <tr>
                    <th class="min-w-[160px] px-6 py-3.5 whitespace-nowrap">Holiday</th>
                    <th class="min-w-[140px] px-6 py-3.5 whitespace-nowrap">Date</th>
                    <th class="min-w-[180px] px-6 py-3.5">Description</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($holidays as $h): ?>
                    <tr>
                        <td class="px-6 py-4 font-bold text-slate-900"><?= htmlspecialchars($h['title']) ?></td>
                        <td class="px-6 py-4 text-xs font-semibold text-indigo-600"><?= formatDate($h['holiday_date']) ?></td>
                        <td class="px-6 py-4 text-xs text-slate-500"><?= htmlspecialchars($h['description'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
