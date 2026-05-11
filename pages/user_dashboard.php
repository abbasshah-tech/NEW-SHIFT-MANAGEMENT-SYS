<?php
// pages/user_dashboard.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4) {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';
require_once '../config/functions.php';
include '../includes/header.php';
include '../includes/sidebar.php';

$username = $_SESSION['username'];
?>

<style>
    .widget-box {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border-radius: 10px;
    }
    .widget-box:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    .fade-in-up {
        animation: fadeInUp 0.5s ease-out forwards;
        opacity: 0;
    }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .delay-1 { animation-delay: 0.1s; }
    .delay-2 { animation-delay: 0.2s; }
    .delay-3 { animation-delay: 0.3s; }
</style>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 fw-bold">My Progress Dashboard</h1>
                </div>
            </div>
        </div>
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <!-- Welcome Banner -->
            <div class="row fade-in-up">
                <div class="col-12">
                    <div class="card bg-gradient-primary shadow-sm border-0 rounded-3 mb-4">
                        <div class="card-body py-4">
                            <h3 class="fw-bold mb-1">Welcome back, <?php echo htmlspecialchars($username); ?>! 👋</h3>
                            <p class="mb-0 text-light">Here is your schedule and progress overview.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3 Employee Widgets -->
            <div class="row">
                <div class="col-lg-4 col-12 fade-in-up delay-1">
                    <div class="small-box bg-info widget-box">
                        <div class="inner">
                            <h4 id="w-next-shift"><i class="fas fa-spinner fa-spin"></i></h4>
                            <p>Next Upcoming Shift</p>
                        </div>
                        <div class="icon"><i class="fas fa-clock"></i></div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-12 fade-in-up delay-2">
                    <div class="small-box bg-success widget-box">
                        <div class="inner">
                            <h3 id="w-monthly-present"><i class="fas fa-spinner fa-spin fs-4"></i></h3>
                            <p>Shifts Attended This Month</p>
                        </div>
                        <div class="icon"><i class="fas fa-calendar-check"></i></div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-12 fade-in-up delay-3">
                    <div class="small-box bg-warning widget-box">
                        <div class="inner">
                            <h3 id="w-pending-swaps"><i class="fas fa-spinner fa-spin fs-4"></i></h3>
                            <p>My Pending Swap Requests</p>
                        </div>
                        <div class="icon"><i class="fas fa-exchange-alt"></i></div>
                    </div>
                </div>
            </div>

            <!-- Detailed Section -->
            <div class="row mt-4 fade-in-up delay-2">
                <!-- Left Column -->
                <div class="col-lg-8">
                    <!-- Upcoming Schedule -->
                    <div class="card shadow-sm border-0 rounded-3 mb-4">
                        <div class="card-header bg-white border-0">
                            <h3 class="card-title fw-bold"><i class="fas fa-calendar-alt text-primary me-2"></i> Upcoming Schedule</h3>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-striped table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Shift Type</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $emp_id_query = $pdo->prepare("SELECT id, first_name, last_name, phone, email, department_id, date_joined FROM employees WHERE user_id = ?");
                                    $emp_id_query->execute([$_SESSION['user_id']]);
                                    $employee_data = $emp_id_query->fetch();
                                    $emp_id = $employee_data['id'] ?? 0;
                                    
                                    // Department Name
                                    $dept_name = "Not Assigned";
                                    if ($employee_data['department_id']) {
                                        $dept_query = $pdo->prepare("SELECT dept_name FROM departments WHERE id = ?");
                                        $dept_query->execute([$employee_data['department_id']]);
                                        $dept_name = $dept_query->fetchColumn() ?: "Not Assigned";
                                    }

                                    // Fetch upcoming shifts
                                    $stmt = $pdo->prepare("
                                        SELECT s.shift_name, s.start_time, s.end_time, es.roster_date, s.color_code
                                        FROM employee_shifts es
                                        JOIN shifts s ON es.shift_id = s.id
                                        WHERE es.employee_id = ? AND es.roster_date >= CURDATE()
                                        ORDER BY es.roster_date ASC
                                        LIMIT 5
                                    ");
                                    $stmt->execute([$emp_id]);
                                    $upcoming = $stmt->fetchAll();
                                    
                                    if ($upcoming) {
                                        foreach ($upcoming as $u) {
                                            $date = date('d M Y', strtotime($u['roster_date']));
                                            $time = date('H:i', strtotime($u['start_time'])) . ' - ' . date('H:i', strtotime($u['end_time']));
                                            $color = $u['color_code'] ?? '#0d6efd';
                                            echo "<tr>
                                                    <td>{$date}</td>
                                                    <td><span class='badge' style='background-color: {$color};'>{$u['shift_name']}</span></td>
                                                    <td>{$time}</td>
                                                  </tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='3' class='text-center py-4 text-muted'>No upcoming shifts scheduled.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Shift History -->
                    <div class="card shadow-sm border-0 rounded-3">
                        <div class="card-header bg-white border-0">
                            <h3 class="card-title fw-bold"><i class="fas fa-history text-secondary me-2"></i> Recent Shift History</h3>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Shift</th>
                                        <th>Attendance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = $pdo->prepare("
                                        SELECT s.shift_name, es.roster_date, a.status, s.color_code
                                        FROM employee_shifts es
                                        JOIN shifts s ON es.shift_id = s.id
                                        LEFT JOIN attendance a ON es.id = a.employee_shift_id
                                        WHERE es.employee_id = ? AND es.roster_date < CURDATE()
                                        ORDER BY es.roster_date DESC
                                        LIMIT 5
                                    ");
                                    $stmt->execute([$emp_id]);
                                    $history = $stmt->fetchAll();

                                    if ($history) {
                                        foreach ($history as $h) {
                                            $date = date('d M Y', strtotime($h['roster_date']));
                                            $status = $h['status'] ?? 'Absent';
                                            $badge_class = ($status == 'Present') ? 'success' : (($status == 'Late') ? 'warning' : 'danger');
                                            $color = $h['color_code'] ?? '#0d6efd';
                                            echo "<tr>
                                                    <td>{$date}</td>
                                                    <td><span class='badge' style='background-color: {$color};'>{$h['shift_name']}</span></td>
                                                    <td><span class='badge bg-{$badge_class}'>{$status}</span></td>
                                                  </tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='3' class='text-center py-4 text-muted'>No shift history found.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div class="col-lg-4">
                    <!-- Personal Info Card -->
                    <div class="card shadow-sm border-0 rounded-3 mb-4">
                        <div class="card-header bg-info text-white border-0">
                            <h3 class="card-title fw-bold"><i class="fas fa-id-card me-2"></i> Personal Info</h3>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-unbordered mb-3">
                                <li class="list-group-item">
                                    <b>Name</b> <a class="float-right"><?php echo htmlspecialchars(($employee_data['first_name'] ?? '') . ' ' . ($employee_data['last_name'] ?? '')); ?></a>
                                </li>
                                <li class="list-group-item">
                                    <b>Department</b> <a class="float-right"><?php echo htmlspecialchars($dept_name); ?></a>
                                </li>
                                <li class="list-group-item">
                                    <b>Phone</b> <a class="float-right"><?php echo htmlspecialchars($employee_data['phone'] ?? 'N/A'); ?></a>
                                </li>
                                <li class="list-group-item">
                                    <b>Joined</b> <a class="float-right"><?php echo $employee_data['date_joined'] ? date('M Y', strtotime($employee_data['date_joined'])) : 'N/A'; ?></a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Action Card -->
                    <div class="card shadow-sm border-0 rounded-3 bg-gradient-dark text-white text-center p-4">
                        <div class="card-body">
                            <i class="fas fa-user-check fa-4x mb-3 text-success"></i>
                            <h4 class="fw-bold">Ready to Work?</h4>
                            <p class="mb-4">Don't forget to mark your attendance for today's shift.</p>
                            <a href="attendance.php" class="btn btn-success btn-lg rounded-pill shadow-sm px-4">Mark Attendance</a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<?php include '../includes/footer.php'; ?>

<script>
$(function () {
    function fetchDashboardData() {
        $.ajax({
            url: '../api/dashboard_data.php',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                if(data.error) return;
                
                if (data.next_shift && typeof data.next_shift === 'object') {
                    $('#w-next-shift').html(data.next_shift.shift_name + '<br><small class="fs-6">' + data.next_shift.roster_date + '</small>');
                } else {
                    $('#w-next-shift').html('None<br><small class="fs-6">Enjoy your break</small>');
                }
                
                $('#w-monthly-present').text(data.monthly_present !== undefined ? data.monthly_present : '0');
                $('#w-pending-swaps').text(data.my_pending_swaps !== undefined ? data.my_pending_swaps : '0');
            }
        });
    }

    fetchDashboardData();
    setInterval(fetchDashboardData, 10000);
});
</script>
