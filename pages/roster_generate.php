<?php
// pages/roster_generate.php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (function_exists('enforce_access')) {
    enforce_access($pdo, 'roster_generate.php');
} else {
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2, 3])) {
        header("Location: ../login.php");
        exit();
    }
}

$message = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // ASSIGN SHIFT (Bulk)
    if (isset($_POST['assign_shift'])) {
        $employee_ids = $_POST['employee_ids'] ?? [];
        $shift_id = $_POST['shift_id'];
        $start_date = $_POST['start_date'];
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : $start_date;
        $assigned_by = $_SESSION['user_id'];
        
        if (empty($employee_ids) || empty($shift_id) || empty($start_date)) {
            $message = "<div class='alert alert-danger'>Please fill all required fields.</div>";
        } else {
            $success_count = 0;
            $skip_count = 0;
            $current_date = strtotime($start_date);
            $last_date = strtotime($end_date);
            
            $pdo->beginTransaction();
            try {
                while ($current_date <= $last_date) {
                    $roster_date = date('Y-m-d', $current_date);
                    foreach ($employee_ids as $emp_id) {
                        $stmt = $pdo->prepare("SELECT id FROM employee_shifts WHERE employee_id = ? AND roster_date = ?");
                        $stmt->execute([$emp_id, $roster_date]);
                        if ($stmt->fetch()) {
                            $skip_count++;
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO employee_shifts (employee_id, shift_id, roster_date, assigned_by) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$emp_id, $shift_id, $roster_date, $assigned_by]);
                            $success_count++;
                        }
                    }
                    $current_date = strtotime("+1 day", $current_date);
                }
                $pdo->commit();
                if ($success_count > 0) {
                    $message = "<div class='alert alert-success'>Successfully assigned $success_count shift(s). Skipped $skip_count overlap(s).</div>";
                } else {
                    $message = "<div class='alert alert-warning'>All assignments skipped due to overlaps ($skip_count).</div>";
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $message = "<div class='alert alert-danger'>Failed: " . $e->getMessage() . "</div>";
            }
        }
    }

    // EDIT ASSIGNMENT
    if (isset($_POST['edit_assignment'])) {
        $es_id = (int)$_POST['es_id'];
        $shift_id = (int)$_POST['shift_id'];
        $roster_date = $_POST['roster_date'];
        $status = $_POST['status'];

        $stmt = $pdo->prepare("UPDATE employee_shifts SET shift_id=?, roster_date=?, status=? WHERE id=?");
        if ($stmt->execute([$shift_id, $roster_date, $status, $es_id])) {
            $message = "<div class='alert alert-success'>Assignment updated successfully.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Failed to update assignment.</div>";
        }
    }

    // DELETE ASSIGNMENT
    if (isset($_POST['delete_assignment'])) {
        $es_id = (int)$_POST['es_id'];
        try {
            // Delete related attendance first
            $pdo->prepare("DELETE FROM attendance WHERE employee_shift_id = ?")->execute([$es_id]);
            $pdo->prepare("DELETE FROM employee_shifts WHERE id = ?")->execute([$es_id]);
            $message = "<div class='alert alert-success'>Assignment deleted successfully.</div>";
        } catch (Exception $e) {
            $message = "<div class='alert alert-danger'>Failed to delete assignment.</div>";
        }
    }
}

// Fetch lists
$employees = $pdo->query("SELECT id, first_name, last_name FROM employees")->fetchAll();
$shifts = $pdo->query("SELECT id, shift_name, start_time, end_time, color_code FROM shifts WHERE status = 'Active'")->fetchAll();

// Fetch recent assignments
$stmt = $pdo->query("
    SELECT es.id, es.employee_id, es.shift_id, es.roster_date, es.status,
           e.first_name, e.last_name, s.shift_name, s.color_code
    FROM employee_shifts es
    JOIN employees e ON es.employee_id = e.id
    JOIN shifts s ON es.shift_id = s.id
    ORDER BY es.id DESC LIMIT 15
");
$recent_assignments = $stmt->fetchAll();

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <h1 class="m-0 fw-bold">Generate Roster / Bulk Assignment</h1>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <?php echo $message; ?>
            <div class="row">
                <!-- Left: Assignment Form -->
                <div class="col-md-5">
                    <div class="card shadow-sm border-0 rounded-3">
                        <div class="card-header bg-primary text-white border-0">
                            <h3 class="card-title fw-bold"><i class="fas fa-calendar-plus me-2"></i> Bulk Assign Shifts</h3>
                        </div>
                        <form method="POST" class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Select Employees (Hold Ctrl to multi-select)</label>
                                <select name="employee_ids[]" class="form-control" multiple required style="height: 120px;">
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Select Shift Template</label>
                                <select name="shift_id" class="form-control" required>
                                    <option value="">-- Select Shift --</option>
                                    <?php foreach ($shifts as $shift): ?>
                                        <option value="<?php echo $shift['id']; ?>">
                                            <?php echo htmlspecialchars($shift['shift_name']); ?> 
                                            (<?php echo date('H:i', strtotime($shift['start_time'])) . ' - ' . date('H:i', strtotime($shift['end_time'])); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" name="start_date" class="form-control" required>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">End Date (Optional)</label>
                                    <input type="date" name="end_date" class="form-control">
                                </div>
                            </div>
                            <button type="submit" name="assign_shift" class="btn btn-success w-100 fw-bold">Generate Schedule</button>
                        </form>
                    </div>
                </div>

                <!-- Right: Recent Assignments with Edit/Delete -->
                <div class="col-md-7">
                    <div class="card shadow-sm border-0 rounded-3">
                        <div class="card-header bg-white border-0">
                            <h3 class="card-title fw-bold"><i class="fas fa-history text-primary me-2"></i> Recent Assignments</h3>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Employee</th>
                                        <th>Shift</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_assignments as $ra): 
                                        $color = $ra['color_code'] ?? '#0d6efd';
                                    ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo htmlspecialchars($ra['first_name'] . ' ' . $ra['last_name']); ?></td>
                                        <td><span class="badge" style="background-color: <?php echo $color; ?>;"><?php echo htmlspecialchars($ra['shift_name']); ?></span></td>
                                        <td><?php echo date('d M Y', strtotime($ra['roster_date'])); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo $ra['status']; ?></span></td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-info edit-assign-btn"
                                                data-id="<?php echo $ra['id']; ?>"
                                                data-shift="<?php echo $ra['shift_id']; ?>"
                                                data-date="<?php echo $ra['roster_date']; ?>"
                                                data-status="<?php echo $ra['status']; ?>"
                                                data-emp="<?php echo htmlspecialchars($ra['first_name'] . ' ' . $ra['last_name']); ?>"
                                                data-bs-toggle="modal" data-bs-target="#editAssignModal">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this assignment?');">
                                                <input type="hidden" name="es_id" value="<?php echo $ra['id']; ?>">
                                                <button type="submit" name="delete_assignment" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if(empty($recent_assignments)): ?>
                                        <tr><td colspan="5" class="text-center py-4 text-muted">No recent assignments.</td></tr>
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

<!-- Edit Assignment Modal -->
<div class="modal fade" id="editAssignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white border-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-edit"></i> Edit Assignment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="es_id" id="edit_es_id">
                    <div class="mb-3">
                        <label>Employee</label>
                        <input type="text" id="edit_emp_name" class="form-control" disabled>
                    </div>
                    <div class="mb-3">
                        <label>Shift</label>
                        <select name="shift_id" id="edit_es_shift" class="form-control" required>
                            <?php foreach ($shifts as $shift): ?>
                                <option value="<?php echo $shift['id']; ?>"><?php echo htmlspecialchars($shift['shift_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Date</label>
                        <input type="date" name="roster_date" id="edit_es_date" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Status</label>
                        <select name="status" id="edit_es_status" class="form-control">
                            <option value="Active">Active</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_assignment" class="btn btn-info text-white">Update Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
document.querySelectorAll('.edit-assign-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('edit_es_id').value = this.dataset.id;
        document.getElementById('edit_emp_name').value = this.dataset.emp;
        document.getElementById('edit_es_shift').value = this.dataset.shift;
        document.getElementById('edit_es_date').value = this.dataset.date;
        document.getElementById('edit_es_status').value = this.dataset.status;
    });
});
</script>
