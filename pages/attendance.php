<?php
// pages/attendance.php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role_id = $_SESSION['role_id'];
$message = '';

// Get Employee ID if user is an employee
$emp_id = null;
if ($role_id == 4) {
    $stmt = $pdo->prepare("SELECT id FROM employees WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $emp_id = $stmt->fetchColumn();
}

// ===== CSV EXPORT HANDLER (must be before any HTML output) =====
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    $csv_query = "
        SELECT e.first_name, e.last_name, es.roster_date, s.shift_name, a.clock_in, a.clock_out, a.status 
        FROM attendance a
        JOIN employee_shifts es ON a.employee_shift_id = es.id
        JOIN shifts s ON es.shift_id = s.id
        JOIN employees e ON es.employee_id = e.id
    ";
    if ($role_id == 4 && $emp_id) {
        $csv_query .= " WHERE es.employee_id = ? ORDER BY es.roster_date DESC";
        $csv_stmt = $pdo->prepare($csv_query);
        $csv_stmt->execute([$emp_id]);
    } else {
        $csv_query .= " ORDER BY es.roster_date DESC";
        $csv_stmt = $pdo->query($csv_query);
    }
    $csv_data = $csv_stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=attendance_report_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Employee Name', 'Date', 'Shift', 'Check In', 'Check Out', 'Status']);
    foreach ($csv_data as $row) {
        fputcsv($output, [
            $row['first_name'] . ' ' . $row['last_name'],
            date('Y-m-d', strtotime($row['roster_date'])),
            $row['shift_name'],
            $row['clock_in'] ? date('H:i:s', strtotime($row['clock_in'])) : 'N/A',
            $row['clock_out'] ? date('H:i:s', strtotime($row['clock_out'])) : 'N/A',
            $row['status']
        ]);
    }
    fclose($output);
    exit();
}
// ===== END CSV EXPORT =====

// Handle Check In / Check Out
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $role_id == 4) {
    $action = $_POST['action'];
    $es_id = $_POST['employee_shift_id'];

    if ($action == 'clock_in') {
        // Check if already clocked in
        $stmt = $pdo->prepare("SELECT id FROM attendance WHERE employee_shift_id = ?");
        $stmt->execute([$es_id]);
        if (!$stmt->fetch()) {
            // Compare current time with shift start time to determine 'Present' or 'Late'
            $stmt = $pdo->prepare("SELECT s.start_time FROM employee_shifts es JOIN shifts s ON es.shift_id = s.id WHERE es.id = ?");
            $stmt->execute([$es_id]);
            $shift_start = $stmt->fetchColumn();
            
            $current_time = date('H:i:s');
            $status = ($current_time > date('H:i:s', strtotime($shift_start . ' +15 minutes'))) ? 'Late' : 'Present';

            $stmt = $pdo->prepare("INSERT INTO attendance (employee_shift_id, clock_in, status) VALUES (?, NOW(), ?)");
            if ($stmt->execute([$es_id, $status])) {
                $message = "<div class='alert alert-success'>Checked in successfully. Status: $status.</div>";
            }
        }
    } elseif ($action == 'clock_out') {
        $stmt = $pdo->prepare("UPDATE attendance SET clock_out = NOW() WHERE employee_shift_id = ? AND clock_out IS NULL");
        if ($stmt->execute([$es_id])) {
            $message = "<div class='alert alert-success'>Checked out successfully.</div>";
        }
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <h1 class="m-0 fw-bold">Attendance</h1>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <?php echo $message; ?>
            
            <?php if ($role_id == 4): ?>
            <!-- Employee View: Today's Shift -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card shadow-sm border-0 rounded-3">
                        <div class="card-header bg-primary text-white border-0">
                            <h3 class="card-title fw-bold"><i class="fas fa-clock me-2"></i> Today's Shift</h3>
                        </div>
                        <div class="card-body text-center py-5">
                            <?php
                            // Fetch today's shift for this employee
                            $stmt = $pdo->prepare("
                                SELECT es.id, s.shift_name, s.start_time, s.end_time, a.clock_in, a.clock_out, a.status 
                                FROM employee_shifts es 
                                JOIN shifts s ON es.shift_id = s.id 
                                LEFT JOIN attendance a ON es.id = a.employee_shift_id
                                WHERE es.employee_id = ? AND es.roster_date = CURDATE()
                            ");
                            $stmt->execute([$emp_id]);
                            $today_shift = $stmt->fetch();

                            if ($today_shift) {
                                echo "<h4 class='fw-bold mb-3'>" . htmlspecialchars($today_shift['shift_name']) . "</h4>";
                                echo "<p class='text-muted mb-4'>" . date('H:i', strtotime($today_shift['start_time'])) . " to " . date('H:i', strtotime($today_shift['end_time'])) . "</p>";

                                if (!$today_shift['clock_in']) {
                                    echo "<form method='POST'>
                                            <input type='hidden' name='action' value='clock_in'>
                                            <input type='hidden' name='employee_shift_id' value='{$today_shift['id']}'>
                                            <button type='submit' class='btn btn-success btn-lg px-5 rounded-pill fw-bold shadow'><i class='fas fa-sign-in-alt'></i> CHECK IN</button>
                                          </form>";
                                } elseif (!$today_shift['clock_out']) {
                                    echo "<div class='text-success mb-3 fw-bold'><i class='fas fa-check-circle'></i> Checked In at " . date('H:i:s', strtotime($today_shift['clock_in'])) . "</div>";
                                    echo "<form method='POST'>
                                            <input type='hidden' name='action' value='clock_out'>
                                            <input type='hidden' name='employee_shift_id' value='{$today_shift['id']}'>
                                            <button type='submit' class='btn btn-danger btn-lg px-5 rounded-pill fw-bold shadow'><i class='fas fa-sign-out-alt'></i> CHECK OUT</button>
                                          </form>";
                                } else {
                                    echo "<div class='alert alert-info d-inline-block px-4 py-2 rounded-pill fw-bold'>
                                            <i class='fas fa-info-circle'></i> Shift Completed
                                          </div>";
                                    echo "<p class='mt-2 mb-0 small text-muted'>Check In: " . date('H:i', strtotime($today_shift['clock_in'])) . " | Check Out: " . date('H:i', strtotime($today_shift['clock_out'])) . "</p>";
                                }
                            } else {
                                echo "<div class='text-muted my-4'><i class='fas fa-mug-hot fa-3x mb-3'></i><br><h4>No shifts assigned for today.</h4></div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Attendance History -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm border-0 rounded-3">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h3 class="card-title fw-bold mb-0">
                                <i class="fas fa-list text-primary me-2"></i> 
                                <?php echo ($role_id == 4) ? "My Attendance History" : "Company Attendance Log"; ?>
                            </h3>
                            <a href="attendance.php?export=csv" class="btn btn-success btn-sm fw-bold">
                                <i class="fas fa-file-csv me-1"></i> Export CSV
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-hover table-striped mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <?php if ($role_id != 4): ?><th>Employee</th><?php endif; ?>
                                        <th>Date</th>
                                        <th>Shift</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $query = "
                                        SELECT e.first_name, e.last_name, es.roster_date, s.shift_name, a.clock_in, a.clock_out, a.status 
                                        FROM attendance a
                                        JOIN employee_shifts es ON a.employee_shift_id = es.id
                                        JOIN shifts s ON es.shift_id = s.id
                                        JOIN employees e ON es.employee_id = e.id
                                    ";
                                    if ($role_id == 4) {
                                        $query .= " WHERE es.employee_id = ? ORDER BY es.roster_date DESC LIMIT 30";
                                        $stmt = $pdo->prepare($query);
                                        $stmt->execute([$emp_id]);
                                    } else {
                                        $query .= " ORDER BY a.clock_in DESC LIMIT 50";
                                        $stmt = $pdo->query($query);
                                    }
                                    
                                    $records = $stmt->fetchAll();
                                    
                                    foreach ($records as $row): 
                                        $status_color = 'secondary';
                                        if ($row['status'] == 'Present') $status_color = 'success';
                                        if ($row['status'] == 'Late') $status_color = 'warning';
                                        if ($row['status'] == 'Absent') $status_color = 'danger';
                                    ?>
                                    <tr>
                                        <?php if ($role_id != 4): ?>
                                            <td class="fw-bold"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                        <?php endif; ?>
                                        <td><?php echo date('d M Y', strtotime($row['roster_date'])); ?></td>
                                        <td><span class="badge bg-info"><?php echo htmlspecialchars($row['shift_name']); ?></span></td>
                                        <td><?php echo $row['clock_in'] ? date('H:i:s', strtotime($row['clock_in'])) : '-'; ?></td>
                                        <td><?php echo $row['clock_out'] ? date('H:i:s', strtotime($row['clock_out'])) : '-'; ?></td>
                                        <td><span class="badge bg-<?php echo $status_color; ?>"><?php echo $row['status']; ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if(empty($records)): ?>
                                        <tr><td colspan="<?php echo ($role_id == 4) ? 5 : 6; ?>" class="text-center py-4 text-muted">No attendance records found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<?php include '../includes/footer.php'; ?>
