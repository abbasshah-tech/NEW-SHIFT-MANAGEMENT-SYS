<?php
// pages/monthly_shifts.php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (function_exists('enforce_access')) {
    enforce_access($pdo, 'monthly_shifts.php');
}

$role_id = $_SESSION['role_id'];
$message = '';

// Fetch employees & shifts for dropdowns
$employees = $pdo->query("
    SELECT e.id, e.first_name, e.last_name, e.email, e.phone, e.date_joined,
           d.dept_name, u.username
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN users u ON e.user_id = u.id
    ORDER BY e.first_name ASC
")->fetchAll();

$shifts = $pdo->query("SELECT id, shift_name, start_time, end_time, color_code FROM shifts WHERE status = 'Active' ORDER BY start_time ASC")->fetchAll();

// Build shift code map for display
$shift_map = [];
foreach ($shifts as $s) {
    $shift_map[$s['id']] = $s;
}

// Handle SAVE monthly shifts
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_monthly'])) {
    $emp_id = (int)$_POST['employee_id'];
    $month = (int)$_POST['month'];
    $year = (int)$_POST['year'];
    $day_shifts = $_POST['day_shift'] ?? []; // array: day => shift_id

    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $saved = 0;
    $skipped = 0;

    $pdo->beginTransaction();
    try {
        for ($day = 1; $day <= $days_in_month; $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $shift_id = isset($day_shifts[$day]) ? (int)$day_shifts[$day] : 0;

            // Delete existing assignment for this date
            $pdo->prepare("DELETE FROM employee_shifts WHERE employee_id = ? AND roster_date = ?")->execute([$emp_id, $date]);

            if ($shift_id > 0) {
                $stmt = $pdo->prepare("INSERT INTO employee_shifts (employee_id, shift_id, roster_date, assigned_by) VALUES (?, ?, ?, ?)");
                $stmt->execute([$emp_id, $shift_id, $date, $_SESSION['user_id']]);
                $saved++;
            }
        }
        $pdo->commit();
        $message = "<div class='alert alert-success'>Monthly schedule saved! $saved shift(s) assigned.</div>";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message = "<div class='alert alert-danger'>Failed to save: " . $e->getMessage() . "</div>";
    }

    // Redirect back to calendar view
    $_GET['employee_id'] = $emp_id;
    $_GET['month'] = $month;
    $_GET['year'] = $year;
}

// Determine if we show the calendar (Step 2) or the search form (Step 1)
$show_calendar = isset($_GET['employee_id']) && isset($_GET['month']) && isset($_GET['year']);

$selected_emp = null;
$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$calendar_data = [];
$attendance_data = [];

if ($show_calendar) {
    $emp_id = (int)$_GET['employee_id'];

    // Get employee details
    foreach ($employees as $e) {
        if ($e['id'] == $emp_id) { $selected_emp = $e; break; }
    }

    if ($selected_emp) {
        $days_in_month = cal_days_in_month(CAL_GREGORIAN, $selected_month, $selected_year);

        // Fetch defined shifts for this employee/month
        $stmt = $pdo->prepare("
            SELECT DAY(roster_date) as day_num, shift_id, status
            FROM employee_shifts
            WHERE employee_id = ? AND MONTH(roster_date) = ? AND YEAR(roster_date) = ?
        ");
        $stmt->execute([$emp_id, $selected_month, $selected_year]);
        while ($row = $stmt->fetch()) {
            $calendar_data[$row['day_num']] = $row;
        }

        // Fetch attendance records for this employee/month
        $stmt = $pdo->prepare("
            SELECT DAY(es.roster_date) as day_num, a.status as att_status, a.clock_in, a.clock_out
            FROM attendance a
            JOIN employee_shifts es ON a.employee_shift_id = es.id
            WHERE es.employee_id = ? AND MONTH(es.roster_date) = ? AND YEAR(es.roster_date) = ?
        ");
        $stmt->execute([$emp_id, $selected_month, $selected_year]);
        while ($row = $stmt->fetch()) {
            $attendance_data[$row['day_num']] = $row;
        }
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
    .cal-table { width: 100%; border-collapse: collapse; }
    .cal-table th { background: #1a3a5c; color: white; text-align: center; padding: 8px 4px; font-size: 12px; }
    .cal-table td { border: 1px solid #dee2e6; text-align: center; padding: 4px; vertical-align: top; min-width: 55px; height: 60px; }
    .cal-table td .day-num { font-weight: bold; font-size: 13px; color: #333; }
    .cal-table td select { width: 100%; font-size: 11px; padding: 2px; border: 1px solid #ccc; border-radius: 3px; background: #f0f8ff; }
    .cal-table td.outside { background: #f5f5f5; }
    .cal-table td.today { background: #fff3cd; }
    .emp-info-table td { padding: 4px 12px; font-size: 14px; }
    .emp-info-table td:first-child { font-weight: bold; color: #555; }
    .shift-legend { font-size: 12px; }
    .shift-legend td { padding: 4px 10px; }
    .att-badge { display: inline-block; width: 22px; height: 22px; border-radius: 50%; font-size: 10px; line-height: 22px; color: white; font-weight: bold; }
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <h1 class="m-0 fw-bold">Define Monthly Shifts</h1>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <?php echo $message; ?>

            <?php if (!$show_calendar || !$selected_emp): ?>
            <!-- ============ STEP 1: Search Form ============ -->
            <div class="card shadow-sm border-0 rounded-3" style="max-width: 600px;">
                <div class="card-header bg-primary text-white border-0">
                    <h3 class="card-title fw-bold"><i class="fas fa-search me-2"></i> Select Employee & Month</h3>
                </div>
                <div class="card-body">
                    <form method="GET">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Employee</label>
                            <select name="employee_id" class="form-control" required>
                                <option value="">-- Select Employee --</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>">
                                        <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?> 
                                        (<?php echo htmlspecialchars($emp['dept_name'] ?? 'No Dept'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label fw-bold">Month</label>
                                <select name="month" class="form-control" required>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?php echo $m; ?>" <?php echo $m == date('m') ? 'selected' : ''; ?>>
                                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label fw-bold">Year</label>
                                <select name="year" class="form-control" required>
                                    <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                                        <option value="<?php echo $y; ?>" <?php echo $y == date('Y') ? 'selected' : ''; ?>>
                                            <?php echo $y; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-bold btn-lg">
                            <i class="fas fa-arrow-right me-2"></i> Next
                        </button>
                    </form>
                </div>
            </div>

            <?php else: ?>
            <!-- ============ STEP 2: Calendar View ============ -->
            <?php
                $days_in_month = cal_days_in_month(CAL_GREGORIAN, $selected_month, $selected_year);
                $first_day_of_week = date('w', mktime(0, 0, 0, $selected_month, 1, $selected_year)); // 0=Sun
                $month_name = date('F', mktime(0, 0, 0, $selected_month, 1, $selected_year));
                $today_day = (date('m') == $selected_month && date('Y') == $selected_year) ? (int)date('d') : 0;
            ?>

            <!-- Employee Info Card -->
            <div class="card shadow-sm border-0 rounded-3 mb-3">
                <div class="card-body py-3">
                    <div class="row align-items-center">
                        <div class="col-md-1 text-center">
                            <i class="fas fa-user-circle fa-4x text-primary"></i>
                        </div>
                        <div class="col-md-5">
                            <table class="emp-info-table">
                                <tr><td>Name</td><td><?php echo htmlspecialchars($selected_emp['first_name'] . ' ' . $selected_emp['last_name']); ?></td></tr>
                                <tr><td>Department</td><td><?php echo htmlspecialchars($selected_emp['dept_name'] ?? 'N/A'); ?></td></tr>
                            </table>
                        </div>
                        <div class="col-md-4">
                            <table class="emp-info-table">
                                <tr><td>Employee ID</td><td><?php echo $selected_emp['id']; ?></td></tr>
                                <tr><td>Date of Joining</td><td><?php echo $selected_emp['date_joined'] ? date('d/m/Y', strtotime($selected_emp['date_joined'])) : 'N/A'; ?></td></tr>
                            </table>
                        </div>
                        <div class="col-md-2 text-end">
                            <a href="monthly_shifts.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back</a>
                        </div>
                    </div>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="employee_id" value="<?php echo $selected_emp['id']; ?>">
                <input type="hidden" name="month" value="<?php echo $selected_month; ?>">
                <input type="hidden" name="year" value="<?php echo $selected_year; ?>">

                <div class="text-center mb-3">
                    <button type="submit" name="save_monthly" class="btn btn-success btn-lg fw-bold px-5 shadow-sm">
                        <i class="fas fa-save me-2"></i> Save Monthly Schedule
                    </button>
                </div>

                <div class="row">
                    <!-- Left: Attendance Schedule -->
                    <div class="col-md-6">
                        <div class="card shadow-sm border-0 rounded-3 mb-4">
                            <div class="card-header text-white border-0" style="background: #1a3a5c;">
                                <h5 class="card-title fw-bold mb-0 text-center">Attendance Schedule</h5>
                            </div>
                            <div class="card-body p-2">
                                <h6 class="fw-bold mb-2"><?php echo "$month_name $selected_year"; ?></h6>
                                <table class="cal-table">
                                    <thead>
                                        <tr>
                                            <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $day_counter = 1;
                                        $started = false;
                                        for ($week = 0; $week < 6; $week++) {
                                            if ($day_counter > $days_in_month) break;
                                            echo "<tr>";
                                            for ($dow = 0; $dow < 7; $dow++) {
                                                if (!$started && $dow < $first_day_of_week) {
                                                    echo "<td class='outside'></td>";
                                                } elseif ($day_counter > $days_in_month) {
                                                    echo "<td class='outside'></td>";
                                                } else {
                                                    $started = true;
                                                    $is_today = ($day_counter == $today_day) ? ' today' : '';
                                                    $att = $attendance_data[$day_counter] ?? null;
                                                    
                                                    echo "<td class='$is_today'>";
                                                    echo "<div class='day-num'>$day_counter</div>";
                                                    
                                                    if ($att) {
                                                        $att_color = '#6c757d';
                                                        if ($att['att_status'] == 'Present') $att_color = '#28a745';
                                                        elseif ($att['att_status'] == 'Late') $att_color = '#ffc107';
                                                        elseif ($att['att_status'] == 'Absent') $att_color = '#dc3545';
                                                        echo "<span class='att-badge' style='background:$att_color;' title='{$att['att_status']}'>" . substr($att['att_status'], 0, 1) . "</span>";
                                                    } else {
                                                        echo "<span class='text-muted' style='font-size:11px;'>-</span>";
                                                    }
                                                    
                                                    echo "</td>";
                                                    $day_counter++;
                                                }
                                            }
                                            echo "</tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Defined Schedule (Editable) -->
                    <div class="col-md-6">
                        <div class="card shadow-sm border-0 rounded-3 mb-4">
                            <div class="card-header text-white border-0" style="background: #1a3a5c;">
                                <h5 class="card-title fw-bold mb-0 text-center">Defined Schedule</h5>
                            </div>
                            <div class="card-body p-2">
                                <h6 class="fw-bold mb-2"><?php echo "$month_name $selected_year"; ?></h6>
                                <table class="cal-table">
                                    <thead>
                                        <tr>
                                            <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $day_counter = 1;
                                        $started = false;
                                        for ($week = 0; $week < 6; $week++) {
                                            if ($day_counter > $days_in_month) break;
                                            echo "<tr>";
                                            for ($dow = 0; $dow < 7; $dow++) {
                                                if (!$started && $dow < $first_day_of_week) {
                                                    echo "<td class='outside'></td>";
                                                } elseif ($day_counter > $days_in_month) {
                                                    echo "<td class='outside'></td>";
                                                } else {
                                                    $started = true;
                                                    $is_today = ($day_counter == $today_day) ? ' today' : '';
                                                    $current_shift = $calendar_data[$day_counter]['shift_id'] ?? 0;
                                                    
                                                    echo "<td class='$is_today'>";
                                                    echo "<div class='day-num'>$day_counter</div>";
                                                    echo "<select name='day_shift[$day_counter]'>";
                                                    echo "<option value='0'>-</option>";
                                                    foreach ($shifts as $s) {
                                                        $sel = ($current_shift == $s['id']) ? 'selected' : '';
                                                        $code = strtoupper(substr($s['shift_name'], 0, 3));
                                                        echo "<option value='{$s['id']}' $sel>$code</option>";
                                                    }
                                                    echo "</select>";
                                                    echo "</td>";
                                                    $day_counter++;
                                                }
                                            }
                                            echo "</tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Shift Legend Table -->
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white border-0">
                    <h5 class="card-title fw-bold mb-0"><i class="fas fa-info-circle text-primary me-2"></i> Shift Codes Reference</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-bordered table-striped mb-0 shift-legend">
                        <thead class="bg-dark text-white">
                            <tr>
                                <th>Index</th>
                                <th>Code</th>
                                <th>Description</th>
                                <th>Color</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($shifts as $s): 
                                $code = strtoupper(substr($s['shift_name'], 0, 3));
                                $time_desc = $s['shift_name'] . ' (' . date('H:i', strtotime($s['start_time'])) . ' to ' . date('H:i', strtotime($s['end_time'])) . ')';
                            ?>
                                <tr>
                                    <td><?php echo $s['id']; ?></td>
                                    <td class="fw-bold"><?php echo $code; ?></td>
                                    <td><?php echo htmlspecialchars($time_desc); ?></td>
                                    <td><div style="width:24px;height:24px;border-radius:50%;background:<?php echo $s['color_code'] ?? '#0d6efd'; ?>;border:1px solid #ddd;"></div></td>
                                    <td><span class="badge bg-success">Active</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php endif; ?>
        </div>
    </section>
</div>

<?php include '../includes/footer.php'; ?>
