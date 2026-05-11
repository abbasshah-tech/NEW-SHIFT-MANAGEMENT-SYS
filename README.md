# SHIFT Pro - Employee Shift & Duty Roster Management System

SHIFT Pro is a comprehensive, production-level workforce management solution built with PHP, MySQL, and AdminLTE 4. It provides a robust Role-Based Access Control (RBAC) system to manage employees, departments, complex shift schedules, and real-time attendance tracking.

## 🚀 Existing Modules & Pages

| Page | Description |
| :--- | :--- |
| **Login/Logout** | Secure authentication system with session management and password hashing. |
| **Admin Dashboard** | High-level overview of organization stats (Employees, Departments, Active Shifts, Pending Swaps). |
| **Employee Dashboard** | Personalized portal for employees to view their upcoming shifts, attendance history, and swap statuses. |
| **Manage Employees** | Full CRUD for employee profiles including department assignment and system user linking. |
| **Manage Departments** | Module to organize the workforce into functional units with designated managers. |
| **Shift Management** | Define shift timings (Start/End), names, and color codes for visual distinction in rosters. |
| **Roster Generation** | Dynamic interface to assign shifts to employees for specific dates or date ranges. |
| **Roster View** | Comprehensive calendar and list views of all assigned shifts across the organization. |
| **Monthly Shifts** | Enhanced monthly calendar view for better long-term scheduling visibility. |
| **Attendance Tracking** | Monitor clock-in/out times, calculate work hours, and track status (Present, Late, Absent). |
| **Swap Requests** | Employee-to-employee shift exchange system with a multi-step approval workflow. |
| **System Users** | Administrator portal to manage system access, user statuses, and account types. |
| **Roles & Permissions** | Granular RBAC system to define what pages and features each role (Admin, Manager, Employee) can access. |
| **API Endpoints** | Backend logic (e.g., `dashboard_data.php`) for dynamic, real-time data fetching without page reloads. |

---

## 🛠️ Recommended Enhancements & New Features

To elevate this project to a "Full-Fledged" enterprise solution, the following additions are recommended:

### 1. Leave Management System (LMS)
*   **Feature:** Employees can apply for leaves (Annual, Sick, Casual).
*   **Workflow:** Managers approve/reject leaves; approved leaves automatically mark the employee as "on leave" in the roster.

### 2. Automated Roster Generation
*   **Feature:** An "Auto-Fill" button that uses an algorithm to assign shifts based on employee availability, department requirements, and labor laws.

### 3. Notification & Alert System
*   **Feature:** Real-time in-app and email notifications for shift assignments, swap request updates, and attendance alerts.

### 4. Advanced Reporting & Exports
*   **Feature:** Export Rosters and Attendance records to PDF, Excel (XLSX), or CSV for payroll and auditing purposes.

### 5. Audit Logging
*   **Feature:** A "System Logs" page for Super Admins to track every action (who added which employee, who changed a shift, etc.) for security.

### 6. Profile & Settings
*   **Feature:** A dedicated profile page for users to update their contact info, upload a profile picture, and change their password.
*   **Feature:** System-wide settings to change Company Name, Logo, and default shift rules.

---

## 🔍 Missing Features from Current Dashboards

### Admin/Manager Dashboard
*   **Recent Activity Feed:** A list showing the latest 5-10 actions in the system.
*   **Under-Staffing Alerts:** Warnings if a department has fewer than the required employees scheduled for a shift.
*   **Quick Actions:** Shortcuts to "Add Employee" or "Generate Roster" directly from the dashboard cards.

### Employee Dashboard
*   **Direct Clock-In/Out:** A prominent button on the dashboard to record attendance without navigating to a separate page.
*   **Shift Reminders:** A "Next Shift" countdown timer to help employees stay on schedule.
*   **Peer View:** Ability to see which colleagues are working the same shift (team-building/coordination).

---

## 💻 Tech Stack
*   **Backend:** PHP 8.x (PDO for database security)
*   **Database:** MySQL / MariaDB
*   **Frontend:** AdminLTE v4 (Bootstrap 5), jQuery, DataTables
*   **Aesthetics:** Google Fonts (Source Sans Pro), FontAwesome 6 icons

---

## ⚙️ Installation (Localhost)
1. Clone the repository into your `htdocs` or `www` directory.
2. Create a database named `shift_management`.
3. Import `database_schema.sql` into your MySQL server.
4. Update `config/database.php` with your database credentials.
5. Default Login: **Username:** `admin` | **Password:** `admin123`
