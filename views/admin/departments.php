<!-- views/admin/departments.php -->
<?php
$user = authUser();
$db = getDBConnection();
$depts = $db->query("SELECT d.*, COUNT(u.id) as emp_count FROM departments d LEFT JOIN users u ON d.id = u.department_id GROUP BY d.id")->fetchAll();
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Departments</h1>
    <p class="text-sm text-slate-500 mt-0.5">Overview of company divisions and team allocations.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($depts as $d): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-base font-bold text-slate-900"><?= htmlspecialchars($d['name']) ?></h3>
                    <span class="text-xs font-bold px-2.5 py-0.5 bg-indigo-50 text-indigo-700 rounded-full border border-indigo-100"><?= $d['emp_count'] ?> Members</span>
                </div>
                <p class="text-xs text-slate-500"><?= htmlspecialchars($d['description'] ?: 'No description provided.') ?></p>
            </div>
        </div>
    <?php endforeach; ?>
</div>
