<?php
// database/seed.php

function seedDatabase(PDO $pdo): void {
    // 1. Departments
    $deptStmt = $pdo->prepare("INSERT INTO departments (name, description) VALUES (?, ?)");
    $deptStmt->execute(['Engineering', 'Software architecture, backend, frontend, and DevOps']);
    $deptStmt->execute(['UI/UX Design', 'Product design, prototypes, design systems']);
    $deptStmt->execute(['Quality Assurance', 'Testing, automation, quality control']);
    $deptStmt->execute(['Operations & HR', 'People management, recruitment, payroll']);

    // 2. Leave Types
    $ltStmt = $pdo->prepare("INSERT INTO leave_types (name, days_per_year) VALUES (?, ?)");
    $ltStmt->execute(['Casual Leave (CL)', 12]);
    $ltStmt->execute(['Sick Leave (SL)', 10]);
    $ltStmt->execute(['Earned Leave (EL)', 15]);

    // 3. Holidays
    $hStmt = $pdo->prepare("INSERT INTO holidays (title, holiday_date, description) VALUES (?, ?, ?)");
    $hStmt->execute(['New Year Day', date('Y') . '-01-01', 'Official holiday for New Year']);
    $hStmt->execute(['Republic Day', date('Y') . '-01-26', 'National Republic Day']);
    $hStmt->execute(['Independence Day', date('Y') . '-08-15', 'National Independence Day']);
    $hStmt->execute(['Diwali', date('Y') . '-11-01', 'Festival of Lights']);
    $hStmt->execute(['Christmas', date('Y') . '-12-25', 'Christmas Celebration']);

    // 4. Users (Password: password123)
    $hash = password_hash('password123', PASSWORD_BCRYPT);
    $uStmt = $pdo->prepare("INSERT INTO users (emp_id, name, email, password, role, department_id, reporting_tl_id, designation, phone, joining_date, salary_basic, avatar, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    // Admin / HR
    $uStmt->execute(['EMP001', 'Sarah Jenkins', 'admin@company.com', $hash, 'admin', 4, null, 'HR Director', '+91 9876543210', '2022-01-15', 85000, 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150', 'active']);
    
    // Team Lead (TL)
    $uStmt->execute(['EMP002', 'Vikram Sharma', 'tl@company.com', $hash, 'team_lead', 1, null, 'Engineering Team Lead', '+91 9876543211', '2022-03-01', 75000, 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150', 'active']);
    $tlId = 2;

    // Employees
    $uStmt->execute(['EMP003', 'Rahul Verma', 'rahul@company.com', $hash, 'employee', 1, $tlId, 'Senior Backend Engineer', '+91 9876543212', '2023-02-10', 55000, 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150', 'active']);
    $uStmt->execute(['EMP004', 'Priya Patel', 'priya@company.com', $hash, 'employee', 2, $tlId, 'Lead UI/UX Designer', '+91 9876543213', '2023-04-15', 52000, 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150', 'active']);
    $uStmt->execute(['EMP005', 'Amit Kumar', 'amit@company.com', $hash, 'employee', 1, $tlId, 'Frontend Developer', '+91 9876543214', '2023-06-01', 45000, 'https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?w=150', 'active']);

    // 5. Projects
    $pStmt = $pdo->prepare("INSERT INTO projects (title, description, tl_id, status, deadline) VALUES (?, ?, ?, ?, ?)");
    $pStmt->execute(['Core Platform Redesign & API v2', 'Rebuilding authentication, dashboard views and REST API endpoints for high concurrency.', $tlId, 'active', date('Y-m-d', strtotime('+30 days'))]);
    $pStmt->execute(['Mobile App Design System', 'Creating unified Figma tokens, component guidelines and micro-interactions.', $tlId, 'active', date('Y-m-d', strtotime('+15 days'))]);

    // 6. Tasks
    $tStmt = $pdo->prepare("INSERT INTO tasks (project_id, assigned_to, created_by, title, description, priority, status, due_date, estimated_hours) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    // Rahul's tasks
    $tStmt->execute([1, 3, $tlId, 'Design database schema & migration script for Auth module', 'Implement bcrypt auth, session validation and role middleware with unit tests.', 'high', 'review', date('Y-m-d', strtotime('+2 days')), 8]);
    $tStmt->execute([1, 3, $tlId, 'Build RESTful API endpoints for employee attendance', 'Endpoints for punch-in, punch-out, status retrieval, and geo-ip logging.', 'urgent', 'in_progress', date('Y-m-d', strtotime('+5 days')), 12]);
    
    // Priya's tasks
    $tStmt->execute([2, 4, $tlId, 'Complete Dark Mode tokens for Design System', 'Define contrast ratios (WCAG AAA) for secondary typography, card borders and charts.', 'medium', 'todo', date('Y-m-d', strtotime('+7 days')), 6]);
    $tStmt->execute([2, 4, $tlId, 'Deliver interactive prototype for TL Review dashboard', 'High fidelity Figma click-through mockup for task submission and review flow.', 'high', 'completed', date('Y-m-d', strtotime('-1 days')), 10]);

    // Amit's tasks
    $tStmt->execute([1, 5, $tlId, 'Implement responsive Kanban Board with drag-and-drop', 'Use Tailwind CSS and Alpine.js to allow status transitions with confirmation modal.', 'high', 'in_progress', date('Y-m-d', strtotime('+3 days')), 14]);

    // 7. Task Submission under review
    $subStmt = $pdo->prepare("INSERT INTO task_submissions (task_id, submitted_by, notes, attachment_url, review_status, tl_feedback) VALUES (?, ?, ?, ?, ?, ?)");
    $subStmt->execute([1, 3, 'Completed Auth schema and PDO wrapper. Ready for your review, Vikram!', 'https://github.com/company/hrms-core/pull/14', 'pending', null]);

    // 8. Attendance (Today & past days)
    $today = date('Y-m-d');
    $attStmt = $pdo->prepare("INSERT INTO attendance (user_id, date, clock_in, clock_out, total_hours, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
    // TL clocked in today
    $attStmt->execute([2, $today, $today . ' 09:00:00', null, 0, 'present', 'Morning team standup conducted']);
    // Rahul clocked in today
    $attStmt->execute([3, $today, $today . ' 09:15:00', null, 0, 'present', 'Working on Auth and Attendance APIs']);
    // Priya WFH today
    $attStmt->execute([4, $today, $today . ' 09:30:00', null, 0, 'wfh', 'WFH - Design sprint']);
    // Amit clocked in
    $attStmt->execute([5, $today, $today . ' 09:45:00', null, 0, 'present', 'Working on UI components']);

    // 9. Leave Applications
    $leaveStmt = $pdo->prepare("INSERT INTO leave_applications (user_id, leave_type_id, start_date, end_date, total_days, reason, status, hr_action_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $leaveStmt->execute([5, 1, date('Y-m-d', strtotime('+5 days')), date('Y-m-d', strtotime('+6 days')), 2, 'Family function in hometown', 'pending_tl_review', null]);
    $leaveStmt->execute([3, 2, date('Y-m-d', strtotime('-10 days')), date('Y-m-d', strtotime('-9 days')), 2, 'Recovering from viral fever', 'approved', 1]);

    // 10. Sample Payroll Records
    $payStmt = $pdo->prepare("INSERT INTO payroll (user_id, month, year, basic_salary, allowances, deductions, net_salary, status, payment_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $lastMonth = (int)date('m', strtotime('-1 month'));
    $lastYear = (int)date('Y', strtotime('-1 month'));
    $payStmt->execute([2, $lastMonth, $lastYear, 75000, 5000, 2500, 77500, 'paid', date('Y-m-d', strtotime('-15 days'))]);
    $payStmt->execute([3, $lastMonth, $lastYear, 55000, 4000, 1500, 57500, 'paid', date('Y-m-d', strtotime('-15 days'))]);
    $payStmt->execute([4, $lastMonth, $lastYear, 52000, 3500, 1200, 54300, 'paid', date('Y-m-d', strtotime('-15 days'))]);
    $payStmt->execute([5, $lastMonth, $lastYear, 45000, 3000, 1000, 47000, 'paid', date('Y-m-d', strtotime('-15 days'))]);
}
