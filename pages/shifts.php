<?php
// pages/shifts.php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Assuming enforcing access logic handles redirecting if unauthorized
if (function_exists('enforce_access')) {
    enforce_access($pdo, 'shifts.php');
} else {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();
    }
}

$message = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'add') {
            $shift_name = $_POST['shift_name'];
            $start_time = $_POST['start_time'];
            $end_time = $_POST['end_time'];
            $color_code = $_POST['color_code'];
            
            $stmt = $pdo->prepare("INSERT INTO shifts (shift_name, start_time, end_time, color_code) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$shift_name, $start_time, $end_time, $color_code])) {
                $message = "<div class='alert alert-success'>Shift added successfully.</div>";
            } else {
                $message = "<div class='alert alert-danger'>Failed to add shift.</div>";
            }
        } elseif ($action == 'edit') {
            $id = $_POST['shift_id'];
            $shift_name = $_POST['shift_name'];
            $start_time = $_POST['start_time'];
            $end_time = $_POST['end_time'];
            $color_code = $_POST['color_code'];
            $status = $_POST['status'];
            
            $stmt = $pdo->prepare("UPDATE shifts SET shift_name=?, start_time=?, end_time=?, color_code=?, status=? WHERE id=?");
            if ($stmt->execute([$shift_name, $start_time, $end_time, $color_code, $status, $id])) {
                $message = "<div class='alert alert-success'>Shift updated successfully.</div>";
            } else {
                $message = "<div class='alert alert-danger'>Failed to update shift.</div>";
            }
        } elseif ($action == 'delete') {
            $id = $_POST['shift_id'];
            $stmt = $pdo->prepare("DELETE FROM shifts WHERE id=?");
            if ($stmt->execute([$id])) {
                $message = "<div class='alert alert-success'>Shift deleted successfully.</div>";
            } else {
                $message = "<div class='alert alert-danger'>Failed to delete shift.</div>";
            }
        }
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';

// Fetch all shifts
$stmt = $pdo->query("SELECT * FROM shifts ORDER BY start_time ASC");
$shifts = $stmt->fetchAll();
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 fw-bold">Manage Shifts</h1>
                </div>
                <div class="col-sm-6 text-right text-end">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addShiftModal">
                        <i class="fas fa-plus"></i> Add New Shift
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <?php echo $message; ?>
            
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body p-0">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>ID</th>
                                <th>Color</th>
                                <th>Shift Name</th>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($shifts as $shift): ?>
                                <tr>
                                    <td><?php echo $shift['id']; ?></td>
                                    <td>
                                        <div style="width:20px;height:20px;border-radius:50%;background-color:<?php echo htmlspecialchars($shift['color_code']); ?>; border:1px solid #ddd;"></div>
                                    </td>
                                    <td class="fw-bold"><?php echo htmlspecialchars($shift['shift_name']); ?></td>
                                    <td><?php echo date('h:i A', strtotime($shift['start_time'])); ?></td>
                                    <td><?php echo date('h:i A', strtotime($shift['end_time'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $shift['status'] == 'Active' ? 'success' : 'secondary'; ?>">
                                            <?php echo $shift['status']; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-info edit-btn" 
                                            data-id="<?php echo $shift['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($shift['shift_name']); ?>"
                                            data-start="<?php echo $shift['start_time']; ?>"
                                            data-end="<?php echo $shift['end_time']; ?>"
                                            data-color="<?php echo htmlspecialchars($shift['color_code'] ?? '#0d6efd'); ?>"
                                            data-status="<?php echo $shift['status']; ?>"
                                            data-bs-toggle="modal" data-bs-target="#editShiftModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this shift?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="shift_id" value="<?php echo $shift['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if(empty($shifts)): ?>
                                <tr><td colspan="6" class="text-center py-4">No shifts found. Please add a new shift.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Add Shift Modal -->
<div class="modal fade" id="addShiftModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Shift</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <div class="row">
                    <div class="col-8 mb-3">
                        <label>Shift Name</label>
                        <input type="text" name="shift_name" class="form-control" required placeholder="e.g., Morning Shift">
                    </div>
                    <div class="col-4 mb-3">
                        <label>Color Tag</label>
                        <input type="color" name="color_code" class="form-control form-control-color w-100" value="#0d6efd" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label>Start Time</label>
                        <input type="time" name="start_time" class="form-control" required>
                    </div>
                    <div class="col-6 mb-3">
                        <label>End Time</label>
                        <input type="time" name="end_time" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary">Save Shift</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Shift Modal -->
<div class="modal fade" id="editShiftModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Shift</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="shift_id" id="edit_shift_id">
                <div class="row">
                    <div class="col-8 mb-3">
                        <label>Shift Name</label>
                        <input type="text" name="shift_name" id="edit_shift_name" class="form-control" required>
                    </div>
                    <div class="col-4 mb-3">
                        <label>Color Tag</label>
                        <input type="color" name="color_code" id="edit_color_code" class="form-control form-control-color w-100" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label>Start Time</label>
                        <input type="time" name="start_time" id="edit_start_time" class="form-control" required>
                    </div>
                    <div class="col-6 mb-3">
                        <label>End Time</label>
                        <input type="time" name="end_time" id="edit_end_time" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label>Status</label>
                    <select name="status" id="edit_status" class="form-control">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary">Update Shift</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('edit_shift_id').value = this.dataset.id;
        document.getElementById('edit_shift_name').value = this.dataset.name;
        document.getElementById('edit_start_time').value = this.dataset.start;
        document.getElementById('edit_end_time').value = this.dataset.end;
        document.getElementById('edit_color_code').value = this.dataset.color;
        document.getElementById('edit_status').value = this.dataset.status;
    });
});
</script>
