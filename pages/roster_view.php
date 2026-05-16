<?php
// pages/roster_view.php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (function_exists('enforce_access')) {
    enforce_access($pdo, 'roster_view.php');
}

$role_id = $_SESSION['role_id'];
$user_id = $_SESSION['user_id'];

// Default Filters
$filter_date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$filter_date_to = $_GET['date_to'] ?? date('Y-m-d', strtotime('+14 days'));
$filter_dept = $_GET['department_id'] ?? '';
$filter_emp = $_GET['employee_id'] ?? '';

// Role-based restrictions
$dept_restriction = "";
$emp_restriction = "";

if ($role_id == 3) {
    // Manager: only see their department
    $stmt = $pdo->prepare("SELECT department_id FROM employees WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $manager_dept = $stmt->fetchColumn();
    if ($manager_dept) {
        $dept_restriction = " AND e.department_id = " . intval($manager_dept);
        $filter_dept = $manager_dept; // Force filter to their dept
    }
} elseif ($role_id == 4) {
    // Employee: only see themselves
    $stmt = $pdo->prepare("SELECT id FROM employees WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $emp_id = $stmt->fetchColumn();
    if ($emp_id) {
        $emp_restriction = " AND e.id = " . intval($emp_id);
        $filter_emp = $emp_id; // Force filter to them
    }
}

// Build query
$query = "
    SELECT es.id, es.roster_date, es.status, 
           e.first_name, e.last_name, 
           d.dept_name, 
           s.shift_name, s.start_time, s.end_time, s.color_code
    FROM employee_shifts es
    JOIN employees e ON es.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    JOIN shifts s ON es.shift_id = s.id
    WHERE es.roster_date >= ? AND es.roster_date <= ?
    $dept_restriction
    $emp_restriction
";

$params = [$filter_date_from, $filter_date_to];

if (!empty($filter_dept) && $role_id != 3) {
    $query .= " AND e.department_id = ?";
    $params[] = $filter_dept;
}
if (!empty($filter_emp) && $role_id != 4) {
    $query .= " AND e.id = ?";
    $params[] = $filter_emp;
}

$query .= " ORDER BY es.roster_date ASC, s.start_time ASC, e.first_name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$roster = $stmt->fetchAll();

// Fetch Dropdowns for Filters
$departments = $pdo->query("SELECT id, dept_name FROM departments")->fetchAll();

$emp_query = "SELECT id, first_name, last_name FROM employees e WHERE 1=1 $dept_restriction";
$employees = $pdo->query($emp_query)->fetchAll();

// ===== CSV EXPORT HANDLER =====
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=roster_report_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Employee', 'Department', 'Shift', 'Start Time', 'End Time', 'Status']);
    foreach ($roster as $row) {
        fputcsv($output, [
            date('Y-m-d', strtotime($row['roster_date'])),
            $row['first_name'] . ' ' . $row['last_name'],
            $row['dept_name'] ?? 'N/A',
            $row['shift_name'],
            date('H:i', strtotime($row['start_time'])),
            date('H:i', strtotime($row['end_time'])),
            $row['status']
        ]);
    }
    fclose($output);
    exit();
}
// ===== END CSV EXPORT =====

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <h1 class="m-0 fw-bold">View Duty Roster</h1>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <!-- Filter Box -->
            <div class="card shadow-sm border-0 rounded-3 mb-4">
                <div class="card-body bg-light">
                    <form method="GET" class="row align-items-end">
                        <div class="col-md-2 mb-3">
                            <label>From Date</label>
                            <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label>To Date</label>
                            <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                        </div>
                        
                        <?php if ($role_id == 1 || $role_id == 2): ?>
                        <div class="col-md-3 mb-3">
                            <label>Department</label>
                            <select name="department_id" class="form-control">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" <?php echo $filter_dept == $dept['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['dept_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if ($role_id != 4): ?>
                        <div class="col-md-3 mb-3">
                            <label>Employee</label>
                            <select name="employee_id" class="form-control">
                                <option value="">All Employees</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>" <?php echo $filter_emp == $emp['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="col-md-2 mb-3">
                            <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="fas fa-filter"></i> Apply Filters</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Roster Table -->
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <h3 class="card-title fw-bold mb-0"><i class="fas fa-calendar-check text-success me-2"></i> Roster Details</h3>
                    <a href="roster_view.php?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-success btn-sm fw-bold">
                        <i class="fas fa-file-csv me-1"></i> Export CSV
                    </a>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="bg-dark text-white">
                            <tr>
                                <th>Date</th>
                                <th>Employee Name</th>
                                <?php if ($role_id != 3 && $role_id != 4): ?><th>Department</th><?php endif; ?>
                                <th>Shift Details</th>
                                <th>Timing</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roster as $row): 
                                $color = $row['color_code'] ?? '#0d6efd';
                            ?>
                                <tr>
                                    <td class="fw-bold"><?php echo date('D, d M Y', strtotime($row['roster_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                    
                                    <?php if ($role_id != 3 && $role_id != 4): ?>
                                    <td><?php echo htmlspecialchars($row['dept_name'] ?? 'None'); ?></td>
                                    <?php endif; ?>
                                    
                                    <td>
                                        <span class="badge" style="background-color: <?php echo $color; ?>; font-size: 14px;">
                                            <?php echo htmlspecialchars($row['shift_name']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <i class="far fa-clock text-muted"></i> 
                                        <?php echo date('H:i', strtotime($row['start_time'])) . ' - ' . date('H:i', strtotime($row['end_time'])); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $row['status'] == 'Active' ? 'success' : 'secondary'; ?>">
                                            <?php echo $row['status']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($roster)): ?>
                                <tr><td colspan="6" class="text-center py-5 text-muted"><h4>No shifts scheduled for this period.</h4></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </section>
</div>

<?php include '../includes/footer.php'; ?>
