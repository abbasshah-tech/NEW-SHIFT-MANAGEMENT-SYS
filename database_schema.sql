-- Employee Shift & Duty Roster Management System Database Schema

-- Disable foreign key checks temporarily during creation
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Core Skeleton Tables

CREATE TABLE IF NOT EXISTS sys_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES sys_roles(id)
);

CREATE TABLE IF NOT EXISTS sys_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_name VARCHAR(100) NOT NULL,
    page_url VARCHAR(255) NOT NULL,
    icon VARCHAR(100) DEFAULT 'far fa-circle',
    parent_id INT DEFAULT 0,
    sort_order INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS role_access (
    role_id INT NOT NULL,
    page_id INT NOT NULL,
    PRIMARY KEY (role_id, page_id),
    FOREIGN KEY (role_id) REFERENCES sys_roles(id) ON DELETE CASCADE,
    FOREIGN KEY (page_id) REFERENCES sys_pages(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT
);

-- 2. Domain Specific Tables

CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dept_name VARCHAR(100) NOT NULL,
    manager_id INT NULL,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    department_id INT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    date_joined DATE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shift_name VARCHAR(50) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    color_code VARCHAR(20) DEFAULT '#0d6efd',
    status ENUM('Active', 'Inactive') DEFAULT 'Active'
);

CREATE TABLE IF NOT EXISTS employee_shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    shift_id INT NOT NULL,
    roster_date DATE NOT NULL,
    assigned_by INT NOT NULL,
    status ENUM('Scheduled', 'Completed', 'Cancelled') DEFAULT 'Scheduled',
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_shift_id INT NOT NULL,
    clock_in DATETIME NULL,
    clock_out DATETIME NULL,
    status ENUM('Present', 'Late', 'Absent') DEFAULT 'Absent',
    FOREIGN KEY (employee_shift_id) REFERENCES employee_shifts(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS shift_swap_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requester_id INT NOT NULL,
    target_id INT NOT NULL,
    requester_shift_id INT NOT NULL,
    target_shift_id INT NOT NULL,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    manager_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requester_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (target_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (requester_shift_id) REFERENCES employee_shifts(id) ON DELETE CASCADE,
    FOREIGN KEY (target_shift_id) REFERENCES employee_shifts(id) ON DELETE CASCADE,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 3. Initial Data Seed

-- Insert Roles
INSERT INTO sys_roles (id, role_name) VALUES 
(1, 'Super Admin'),
(2, 'Admin'),
(3, 'Manager'),
(4, 'Employee') ON DUPLICATE KEY UPDATE role_name=VALUES(role_name);

-- Insert Super Admin (Password is 'admin123')
-- password_hash generated via password_hash('admin123', PASSWORD_DEFAULT)
INSERT INTO users (id, username, password_hash, role_id, status) VALUES 
(1, 'admin', '$2y$10$QO51qI/M6Z/A1D4z7Ue/AONXjE6T/8V8U.uVwzGj0D5R2gK.BvT5O', 1, 'Active') ON DUPLICATE KEY UPDATE username=VALUES(username);

-- Insert System Pages
INSERT INTO sys_pages (id, page_name, page_url, icon, parent_id, sort_order) VALUES 
(1, 'Dashboard', 'dashboard.php', 'fas fa-tachometer-alt', 0, 1),
(2, 'System Admin', '#', 'fas fa-cogs', 0, 90),
(3, 'Manage Users', 'sys_users.php', 'fas fa-users', 2, 1),
(4, 'Manage Roles', 'sys_roles.php', 'fas fa-user-shield', 2, 2),
(5, 'Permissions', 'sys_permissions.php', 'fas fa-key', 2, 3),
(6, 'Organization', '#', 'fas fa-building', 0, 10),
(7, 'Departments', 'departments.php', 'fas fa-sitemap', 6, 1),
(8, 'Employees', 'employees.php', 'fas fa-id-badge', 6, 2),
(9, 'Scheduling', '#', 'fas fa-calendar-alt', 0, 20),
(10, 'Shifts', 'shifts.php', 'fas fa-clock', 9, 1),
(11, 'Generate Roster', 'roster_generate.php', 'fas fa-calendar-plus', 9, 2),
(12, 'View Roster', 'roster_view.php', 'fas fa-calendar-check', 9, 3),
(13, 'Operations', '#', 'fas fa-briefcase', 0, 30),
(14, 'Attendance', 'attendance.php', 'fas fa-user-check', 13, 1),
(15, 'Swap Requests', 'swap_requests.php', 'fas fa-exchange-alt', 13, 2)
ON DUPLICATE KEY UPDATE page_name=VALUES(page_name);

-- Assign all pages to Super Admin (Role 1)
INSERT IGNORE INTO role_access (role_id, page_id) SELECT 1, id FROM sys_pages;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;
