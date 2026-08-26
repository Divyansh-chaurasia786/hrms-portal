<?php
// controllers/PayrollController.php

class PayrollController {
    public static function issuePayslip(): void {
        requireRole(['admin']);
        $userId = (int)($_POST['user_id'] ?? 0);
        $month = (int)($_POST['month'] ?? date('m'));
        $year = (int)($_POST['year'] ?? date('Y'));
        $basic = (float)($_POST['basic_salary'] ?? 0);
        $allowances = (float)($_POST['allowances'] ?? 0);
        $deductions = (float)($_POST['deductions'] ?? 0);
        $net = ($basic + $allowances) - $deductions;

        if ($userId <= 0 || $basic <= 0) {
            setFlash('error', 'Please select an employee and specify a valid basic salary.');
        } else {
            $db = getDBConnection();
            try {
                $stmt = $db->prepare("
                    INSERT INTO payroll (user_id, month, year, basic_salary, allowances, deductions, net_salary, status, payment_date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'paid', ?)
                    ON CONFLICT(user_id, month, year) DO UPDATE SET
                    basic_salary = excluded.basic_salary,
                    allowances = excluded.allowances,
                    deductions = excluded.deductions,
                    net_salary = excluded.net_salary,
                    payment_date = excluded.payment_date
                ");
                $stmt->execute([$userId, $month, $year, $basic, $allowances, $deductions, $net, date('Y-m-d')]);
                setFlash('success', 'Salary slip generated & issued successfully!');
            } catch (Exception $e) {
                setFlash('error', 'Error generating payslip: ' . $e->getMessage());
            }
        }

        header('Location: ?page=admin-payroll');
        exit;
    }
}
