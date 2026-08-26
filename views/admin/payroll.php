<!-- views/admin/payroll.php -->
<?php
$user = authUser();
$db = getDBConnection();

$employees = $db->query("SELECT id, name, emp_id, salary_basic, designation FROM users WHERE status = 'active' ORDER BY name ASC")->fetchAll();

$stmt = $db->query("
    SELECT p.*, u.name, u.emp_id, u.designation, d.name as dept_name
    FROM payroll p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN departments d ON u.department_id = d.id
    ORDER BY p.year DESC, p.month DESC, u.name ASC
");
$issuedPayslips = $stmt->fetchAll();
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6" x-data="{ issueModalOpen: false }">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Salary & Payslip Management</h1>
        <p class="text-sm text-slate-500 mt-0.5">Generate monthly salary slips, configure allowances/deductions, and disburse to employees.</p>
    </div>
    <button @click="issueModalOpen = true" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl shadow-sm transition">
        <i data-lucide="receipt" class="w-4 h-4"></i> Issue / Generate Salary Slip
    </button>

    <!-- Issue Salary Slip Modal -->
    <div x-show="issueModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" x-cloak>
        <div @click.away="issueModalOpen = false" class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                <h3 class="text-base font-bold text-slate-900">Generate Salary Slip</h3>
                <button @click="issueModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form action="?action=issue-payslip" method="POST" class="space-y-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Select Employee</label>
                    <select name="user_id" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs">
                        <?php foreach ($employees as $e): ?>
                            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?> (<?= htmlspecialchars($e['emp_id']) ?> - ₹<?= number_format($e['salary_basic']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Month</label>
                        <select name="month" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= (int)date('m') === $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 10)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Year</label>
                        <input type="number" name="year" value="<?= date('Y') ?>" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Basic Pay (₹)</label>
                        <input type="number" name="basic_salary" value="50000" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Allowances (₹)</label>
                        <input type="number" name="allowances" value="4000" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Deductions (₹)</label>
                        <input type="number" name="deductions" value="1500" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs">
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button type="button" @click="issueModalOpen = false" class="px-4 py-2 text-xs font-semibold text-slate-500">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl">Issue Slip</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto no-scrollbar">
        <table class="w-full text-left text-sm text-slate-600 min-w-[780px]">
            <thead class="bg-slate-50 text-slate-400 text-[11px] uppercase font-semibold border-b border-slate-200">
                <tr>
                    <th class="min-w-[180px] px-6 py-3.5">Employee</th>
                    <th class="min-w-[130px] px-6 py-3.5 whitespace-nowrap">Month / Year</th>
                    <th class="min-w-[100px] px-6 py-3.5 whitespace-nowrap">Basic</th>
                    <th class="min-w-[100px] px-6 py-3.5 whitespace-nowrap">Allowances</th>
                    <th class="min-w-[100px] px-6 py-3.5 whitespace-nowrap">Deductions</th>
                    <th class="min-w-[120px] px-6 py-3.5 whitespace-nowrap">Net Disbursed</th>
                    <th class="min-w-[100px] px-6 py-3.5 whitespace-nowrap">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($issuedPayslips as $p): ?>
                    <tr class="hover:bg-slate-50/70 transition">
                        <td class="px-6 py-4 flex items-center gap-3">
                            <div>
                                <div class="font-semibold text-slate-900"><?= htmlspecialchars($p['name']) ?></div>
                                <div class="text-xs text-slate-400"><?= htmlspecialchars($p['emp_id']) ?> • <?= htmlspecialchars($p['designation']) ?></div>
                            </div>
                        </td>
                        <td class="px-6 py-4 font-bold text-slate-800"><?= date('F', mktime(0, 0, 0, $p['month'], 10)) ?> <?= $p['year'] ?></td>
                        <td class="px-6 py-4 font-mono text-xs">₹<?= number_format($p['basic_salary']) ?></td>
                        <td class="px-6 py-4 font-mono text-xs text-emerald-600">+₹<?= number_format($p['allowances']) ?></td>
                        <td class="px-6 py-4 font-mono text-xs text-rose-600">-₹<?= number_format($p['deductions']) ?></td>
                        <td class="px-6 py-4 font-mono font-bold text-indigo-600">₹<?= number_format($p['net_salary']) ?></td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-0.5 bg-emerald-50 text-emerald-700 text-xs font-semibold rounded-full border border-emerald-200">Paid</span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
