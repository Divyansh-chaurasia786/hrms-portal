<?php
// database/schema.php

function initDatabaseSchema(PDO $pdo): void {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $isMysql = ($driver === 'mysql');

    $queries = [];

    if ($isMysql) {
        $queries = [
            "CREATE TABLE IF NOT EXISTS departments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(191) NOT NULL UNIQUE,
                description TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                emp_id VARCHAR(100) NOT NULL UNIQUE,
                name VARCHAR(191) NOT NULL,
                email VARCHAR(191) NOT NULL UNIQUE,
                password VARCHAR(255),
                role ENUM('admin', 'team_lead', 'employee') NOT NULL,
                department_id INT,
                reporting_tl_id INT,
                designation VARCHAR(191) NOT NULL,
                phone VARCHAR(50),
                joining_date DATE,
                salary_basic DECIMAL(12,2) DEFAULT 0,
                avatar TEXT,
                status ENUM('active', 'inactive') DEFAULT 'active',
                is_dismissed TINYINT(1) DEFAULT 0,
                dismissal_reason TEXT,
                is_escalated_locked TINYINT(1) DEFAULT 0,
                escalated_by_tl_id INT,
                escalated_lock_reason TEXT,
                otp_sent_count_today INT DEFAULT 0,
                is_otp_blocked_today TINYINT(1) DEFAULT 0,
                otp_last_sent_date DATE,
                login_otp VARCHAR(20),
                login_otp_expires_at DATETIME,
                login_otp_last_sent_at DATETIME,
                force_logout_at DATETIME,
                assigned_office_location INT DEFAULT 2,
                hr_warning_message TEXT,
                new_tl_notice TEXT,
                last_seen_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX (department_id),
                INDEX (reporting_tl_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS attendance (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                date DATE NOT NULL,
                clock_in DATETIME,
                clock_out DATETIME,
                total_hours DECIMAL(6,2) DEFAULT 0,
                status VARCHAR(50) DEFAULT 'present',
                notes TEXT,
                tl_approved TINYINT(1) DEFAULT 0,
                hr_corrected TINYINT(1) DEFAULT 0,
                locked_by_hr TINYINT(1) DEFAULT 0,
                force_logged_out_by INT,
                force_logout_at DATETIME,
                force_logout_acknowledged TINYINT(1) DEFAULT 0,
                UNIQUE KEY user_date_unique (user_id, date),
                INDEX (user_id),
                INDEX (date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS attendance_sessions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                attendance_id INT NOT NULL,
                user_id INT NOT NULL,
                session_number INT NOT NULL DEFAULT 1,
                clock_in DATETIME NOT NULL,
                clock_out DATETIME,
                hours DECIMAL(6,2) DEFAULT 0,
                ended_by VARCHAR(50) DEFAULT 'self',
                ended_by_user_id INT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX (attendance_id),
                INDEX (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS leave_types (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(191) NOT NULL UNIQUE,
                days_per_year INT NOT NULL DEFAULT 12
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS leave_applications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                leave_type_id INT NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                total_days DECIMAL(4,1) NOT NULL DEFAULT 1.0,
                reason TEXT NOT NULL,
                status VARCHAR(50) DEFAULT 'pending_tl_review',
                tl_reviewed TINYINT(1) DEFAULT 0,
                tl_recommendation VARCHAR(50) DEFAULT 'neutral',
                tl_remarks TEXT,
                tl_reviewed_at DATETIME,
                hr_action_by INT,
                hr_action_at DATETIME,
                hr_remarks TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX (user_id),
                INDEX (leave_type_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS holidays (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(191) NOT NULL,
                holiday_date DATE NOT NULL,
                description TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS projects (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(191) NOT NULL,
                description TEXT,
                tl_id INT NOT NULL,
                status VARCHAR(50) DEFAULT 'active',
                deadline DATE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX (tl_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS tasks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                assigned_to INT NOT NULL,
                created_by INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                priority VARCHAR(50) DEFAULT 'medium',
                status VARCHAR(50) DEFAULT 'todo',
                due_date DATE,
                original_due_date DATE,
                is_extended TINYINT(1) DEFAULT 0,
                extension_reason TEXT,
                estimated_hours DECIMAL(6,2) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX (project_id),
                INDEX (assigned_to)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS task_submissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                task_id INT NOT NULL,
                submitted_by INT NOT NULL,
                notes TEXT,
                attachment_url TEXT,
                attachment_file TEXT,
                attachment_type VARCHAR(50),
                review_status VARCHAR(50) DEFAULT 'pending',
                tl_feedback TEXT,
                is_auto_submitted TINYINT(1) DEFAULT 0,
                submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX (task_id),
                INDEX (submitted_by)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS daily_work_reports (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                user_role VARCHAR(50) DEFAULT 'employee',
                submitted_to_id INT,
                report_date DATE NOT NULL,
                title VARCHAR(255) NOT NULL,
                tasks_completed TEXT NOT NULL,
                tasks_in_progress TEXT,
                blockers TEXT,
                plan_for_tomorrow TEXT,
                total_hours_logged DECIMAL(6,2) DEFAULT 0,
                attachment_url TEXT,
                tasks_snapshot_json LONGTEXT,
                status VARCHAR(50) DEFAULT 'submitted',
                is_auto_submitted TINYINT(1) DEFAULT 0,
                reviewed_by_id INT,
                reviewed_at DATETIME,
                review_notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX (user_id),
                INDEX (report_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS tl_feedbacks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tl_id INT NOT NULL,
                hr_id INT,
                sentiment VARCHAR(50) NOT NULL,
                feedback_text TEXT NOT NULL,
                status VARCHAR(50) DEFAULT 'unread',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX (tl_id),
                INDEX (hr_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS employee_escalations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                tl_id INT NOT NULL,
                reason TEXT NOT NULL,
                status VARCHAR(50) DEFAULT 'pending',
                hr_action_by INT,
                hr_action_at DATETIME,
                hr_remarks TEXT,
                hr_action VARCHAR(50),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX (employee_id),
                INDEX (tl_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS drive_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_id TEXT,
                client_secret TEXT,
                refresh_token TEXT,
                access_token TEXT,
                access_token_expires_at DATETIME,
                is_connected TINYINT(1) DEFAULT 0,
                connected_account_email VARCHAR(191),
                root_folder_id VARCHAR(255),
                root_folder_name VARCHAR(191) DEFAULT 'HRMS Tech Drive',
                total_storage_bytes BIGINT DEFAULT 16106127360,
                used_storage_bytes BIGINT DEFAULT 0,
                last_synced_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS drive_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                google_file_id VARCHAR(255),
                name VARCHAR(255) NOT NULL,
                type VARCHAR(50) NOT NULL,
                mime_type VARCHAR(191),
                size_bytes BIGINT DEFAULT 0,
                parent_folder_id INT DEFAULT 0,
                google_parent_id VARCHAR(255),
                web_view_link TEXT,
                web_content_link TEXT,
                icon_link TEXT,
                thumbnail_link TEXT,
                uploaded_by INT,
                is_deleted TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX (parent_folder_id),
                INDEX (type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS session_terminations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                terminated_by INT NOT NULL,
                reason TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS payroll (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                month INT NOT NULL,
                year INT NOT NULL,
                basic_salary DECIMAL(12,2) NOT NULL,
                allowances DECIMAL(12,2) DEFAULT 0,
                deductions DECIMAL(12,2) DEFAULT 0,
                net_salary DECIMAL(12,2) NOT NULL,
                status VARCHAR(50) DEFAULT 'paid',
                payment_date DATE,
                UNIQUE KEY user_month_year_unique (user_id, month, year),
                INDEX (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
        ];
    } else {
        // SQLite
        $queries = [
            "CREATE TABLE IF NOT EXISTS departments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                description TEXT
            );",
            
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                emp_id TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password TEXT,
                role TEXT NOT NULL CHECK(role IN ('admin', 'team_lead', 'employee')),
                department_id INTEGER,
                reporting_tl_id INTEGER,
                designation TEXT NOT NULL,
                phone TEXT,
                joining_date TEXT,
                salary_basic REAL DEFAULT 0,
                avatar TEXT,
                status TEXT DEFAULT 'active' CHECK(status IN ('active', 'inactive')),
                is_dismissed INTEGER DEFAULT 0,
                dismissal_reason TEXT,
                is_escalated_locked INTEGER DEFAULT 0,
                escalated_by_tl_id INTEGER,
                escalated_lock_reason TEXT,
                otp_sent_count_today INTEGER DEFAULT 0,
                is_otp_blocked_today INTEGER DEFAULT 0,
                otp_last_sent_date TEXT,
                login_otp TEXT,
                login_otp_expires_at DATETIME,
                login_otp_last_sent_at DATETIME,
                force_logout_at DATETIME,
                assigned_office_location INTEGER DEFAULT 2,
                hr_warning_message TEXT,
                new_tl_notice TEXT,
                last_seen_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
                FOREIGN KEY (reporting_tl_id) REFERENCES users(id) ON DELETE SET NULL
            );",

            "CREATE TABLE IF NOT EXISTS attendance (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                date TEXT NOT NULL,
                clock_in DATETIME,
                clock_out DATETIME,
                total_hours REAL DEFAULT 0,
                status TEXT DEFAULT 'present' CHECK(status IN ('present', 'half_day', 'wfh', 'absent')),
                notes TEXT,
                tl_approved INTEGER DEFAULT 0,
                hr_corrected INTEGER DEFAULT 0,
                locked_by_hr INTEGER DEFAULT 0,
                force_logged_out_by INTEGER,
                force_logout_at DATETIME,
                force_logout_acknowledged INTEGER DEFAULT 0,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE(user_id, date)
            );",

            "CREATE TABLE IF NOT EXISTS attendance_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                attendance_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                session_number INTEGER NOT NULL DEFAULT 1,
                clock_in DATETIME NOT NULL,
                clock_out DATETIME,
                hours REAL DEFAULT 0,
                ended_by TEXT DEFAULT 'self',
                ended_by_user_id INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (attendance_id) REFERENCES attendance(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );",

            "CREATE TABLE IF NOT EXISTS leave_types (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                days_per_year INTEGER NOT NULL DEFAULT 12
            );",

            "CREATE TABLE IF NOT EXISTS leave_applications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                leave_type_id INTEGER NOT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT NOT NULL,
                total_days REAL NOT NULL DEFAULT 1,
                reason TEXT NOT NULL,
                status TEXT DEFAULT 'pending_tl_review',
                tl_reviewed INTEGER DEFAULT 0,
                tl_recommendation TEXT DEFAULT 'neutral',
                tl_remarks TEXT,
                tl_reviewed_at DATETIME,
                hr_action_by INTEGER,
                hr_action_at DATETIME,
                hr_remarks TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (leave_type_id) REFERENCES leave_types(id),
                FOREIGN KEY (hr_action_by) REFERENCES users(id)
            );",

            "CREATE TABLE IF NOT EXISTS holidays (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                holiday_date TEXT NOT NULL,
                description TEXT
            );",

            "CREATE TABLE IF NOT EXISTS projects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT,
                tl_id INTEGER NOT NULL,
                status TEXT DEFAULT 'active' CHECK(status IN ('planning', 'active', 'completed')),
                deadline TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (tl_id) REFERENCES users(id) ON DELETE CASCADE
            );",

            "CREATE TABLE IF NOT EXISTS tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id INTEGER NOT NULL,
                assigned_to INTEGER NOT NULL,
                created_by INTEGER NOT NULL,
                title TEXT NOT NULL,
                description TEXT,
                priority TEXT DEFAULT 'medium' CHECK(priority IN ('low', 'medium', 'high', 'urgent')),
                status TEXT DEFAULT 'todo' CHECK(status IN ('todo', 'in_progress', 'review', 'completed')),
                due_date TEXT,
                original_due_date TEXT,
                is_extended INTEGER DEFAULT 0,
                extension_reason TEXT,
                estimated_hours REAL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
            );",

            "CREATE TABLE IF NOT EXISTS task_submissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                task_id INTEGER NOT NULL,
                submitted_by INTEGER NOT NULL,
                notes TEXT,
                attachment_url TEXT,
                attachment_file TEXT,
                attachment_type TEXT,
                review_status TEXT DEFAULT 'pending' CHECK(review_status IN ('pending', 'approved', 'changes_requested')),
                tl_feedback TEXT,
                is_auto_submitted INTEGER DEFAULT 0,
                submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
                FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE CASCADE
            );",

            "CREATE TABLE IF NOT EXISTS daily_work_reports (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                user_role TEXT DEFAULT 'employee',
                submitted_to_id INTEGER,
                report_date TEXT NOT NULL,
                title TEXT NOT NULL,
                tasks_completed TEXT NOT NULL,
                tasks_in_progress TEXT,
                blockers TEXT,
                plan_for_tomorrow TEXT,
                total_hours_logged REAL DEFAULT 0,
                attachment_url TEXT,
                tasks_snapshot_json TEXT,
                status TEXT DEFAULT 'submitted' CHECK(status IN ('draft', 'submitted', 'reviewed', 'acknowledged')),
                is_auto_submitted INTEGER DEFAULT 0,
                reviewed_by_id INTEGER,
                reviewed_at DATETIME,
                review_notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );",

            "CREATE TABLE IF NOT EXISTS tl_feedbacks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tl_id INTEGER NOT NULL,
                sentiment TEXT NOT NULL,
                feedback_text TEXT NOT NULL,
                status TEXT DEFAULT 'unread',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (tl_id) REFERENCES users(id) ON DELETE CASCADE
            );",

            "CREATE TABLE IF NOT EXISTS employee_escalations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_id INTEGER NOT NULL,
                tl_id INTEGER NOT NULL,
                reason TEXT NOT NULL,
                status TEXT DEFAULT 'pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (tl_id) REFERENCES users(id) ON DELETE CASCADE
            );",

            "CREATE TABLE IF NOT EXISTS session_terminations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                terminated_by INTEGER NOT NULL,
                reason TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (terminated_by) REFERENCES users(id) ON DELETE CASCADE
            );",

            "CREATE TABLE IF NOT EXISTS payroll (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                month INTEGER NOT NULL,
                year INTEGER NOT NULL,
                basic_salary REAL NOT NULL,
                allowances REAL DEFAULT 0,
                deductions REAL DEFAULT 0,
                net_salary REAL NOT NULL,
                status TEXT DEFAULT 'paid' CHECK(status IN ('draft', 'paid')),
                payment_date TEXT,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE(user_id, month, year)
            );"
        ];
    }

    foreach ($queries as $sql) {
        $pdo->exec($sql);
    }
}
