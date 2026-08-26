<!-- views/employee/payslips.php -->
<?php
$user = authUser();
$db = getDBConnection();

$stmt = $db->prepare("SELECT * FROM payroll WHERE user_id = ? ORDER BY year DESC, month DESC");
$stmt->execute([$user['id']]);
$payslips = $stmt->fetchAll();
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">My Payslips</h1>
    <p class="text-sm text-slate-500 mt-0.5">Download or view your monthly salary breakdowns and deductions.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <?php foreach ($payslips as $p): ?>
        <?php $monthName = date('F', mktime(0, 0, 0, $p['month'], 10)); ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <div>
                    <span class="text-xs font-bold text-indigo-600 uppercase tracking-wider">Salary Slip</span>
                    <h3 class="text-base font-bold text-slate-900"><?= $monthName ?> <?= $p['year'] ?></h3>
                </div>
                <span class="px-2.5 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-semibold rounded-full">Paid</span>
            </div>

            <div class="py-4 space-y-2 text-xs">
                <div class="flex justify-between text-slate-600">
                    <span>Basic Salary:</span>
                    <span class="font-semibold text-slate-800">₹<?= number_format($p['basic_salary']) ?></span>
                </div>
                <div class="flex justify-between text-slate-600">
                    <span>Allowances & Bonus:</span>
                    <span class="font-semibold text-emerald-600">+ ₹<?= number_format($p['allowances']) ?></span>
                </div>
                <div class="flex justify-between text-slate-600">
                    <span>Taxes & Deductions:</span>
                    <span class="font-semibold text-rose-600">- ₹<?= number_format($p['deductions']) ?></span>
                </div>
                <div class="pt-3 border-t border-slate-100 flex justify-between text-sm font-bold text-slate-900">
                    <span>Net Disbursed:</span>
                    <span class="text-indigo-600 font-mono">₹<?= number_format($p['net_salary']) ?></span>
                </div>
            </div>

            <div class="pt-3 border-t border-slate-100 flex justify-end">
                <button onclick="window.print()" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg flex items-center gap-1.5 transition">
                    <i data-lucide="printer" class="w-3.5 h-3.5"></i> Print / Download Slip
                </button>
            </div>
        </div>
    <?php endforeach; ?>
</div>
