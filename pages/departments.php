<?php
// pages/departments.php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (function_exists('enforce_access')) {
    enforce_access($pdo, 'departments.php');
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action == 'add') {
        $dept_name = $_POST['dept_name'];
        $manager_id = !empty($_POST['manager_id']) ? $_POST['manager_id'] : null;
        
        $stmt = $pdo->prepare("INSERT INTO departments (dept_name, manager_id) VALUES (?, ?)");
        if ($stmt->execute([$dept_name, $manager_id])) {
            $message = "<div class='alert alert-success'>Department added successfully.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Failed to add department.</div>";
        }
    } elseif ($action == 'edit') {
        $id = $_POST['dept_id'];
        $dept_name = $_POST['dept_name'];
        $manager_id = !empty($_POST['manager_id']) ? $_POST['manager_id'] : null;
        $status = $_POST['status'];
        
        $stmt = $pdo->prepare("UPDATE departments SET dept_name=?, manager_id=?, status=? WHERE id=?");
        if ($stmt->execute([$dept_name, $manager_id, $status, $id])) {
            $message = "<div class='alert alert-success'>Department updated successfully.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Failed to update department.</div>";
        }
    } elseif ($action == 'delete') {
        $id = $_POST['dept_id'];
        // Note: Real system might prevent deletion if employees are assigned.
        $stmt = $pdo->prepare("DELETE FROM departments WHERE id=?");
        if ($stmt->execute([$id])) {
            $message = "<div class='alert alert-success'>Department deleted successfully.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Failed to delete department (it might be in use).</div>";
        }
    }
}

// Fetch departments
$query = "
    SELECT d.*, e.first_name as m_first, e.last_name as m_last 
    FROM departments d 
    LEFT JOIN employees e ON d.manager_id = e.id
";
$departments = $pdo->query($query)->fetchAll();

// Fetch employees for manager dropdown
$employees = $pdo->query("SELECT id, first_name, last_name FROM employees")->fetchAll();

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 fw-bold">Manage Departments</h1>
                </div>
                <div class="col-sm-6 text-end">
                    <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#addDeptModal">
                        <i class="fas fa-plus"></i> Add Department
                    </button>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <?php echo $message; ?>
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body p-0">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>ID</th>
                                <th>Department Name</th>
                                <th>Manager</th>
                                <th>Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($departments as $dept): ?>
                                <tr>
                                    <td><?php echo $dept['id']; ?></td>
                                    <td class="fw-bold text-primary"><?php echo htmlspecialchars($dept['dept_name']); ?></td>
                                    <td>
                                        <?php 
                                            if ($dept['manager_id']) {
                                                echo htmlspecialchars($dept['m_first'] . ' ' . $dept['m_last']);
                                            } else {
                                                echo "<span class='text-muted'>No Manager</span>";
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $dept['status'] == 'Active' ? 'success' : 'danger'; ?>">
                                            <?php echo $dept['status']; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-info edit-btn" 
                                            data-id="<?php echo $dept['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($dept['dept_name']); ?>"
                                            data-manager="<?php echo $dept['manager_id']; ?>"
                                            data-status="<?php echo $dept['status']; ?>"
                                            data-bs-toggle="modal" data-bs-target="#editDeptModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this department?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="dept_id" value="<?php echo $dept['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($departments)): ?>
                                <tr><td colspan="5" class="text-center py-4">No departments found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addDeptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-plus"></i> Add Department</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label>Department Name</label>
                        <input type="text" name="dept_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Assign Manager</label>
                        <select name="manager_id" class="form-control">
                            <option value="">-- No Manager --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editDeptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white border-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-edit"></i> Edit Department</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="dept_id" id="edit_dept_id">
                    <div class="mb-3">
                        <label>Department Name</label>
                        <input type="text" name="dept_name" id="edit_dept_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Assign Manager</label>
                        <select name="manager_id" id="edit_manager_id" class="form-control">
                            <option value="">-- No Manager --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Status</label>
                        <select name="status" id="edit_status" class="form-control">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white">Update Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('edit_dept_id').value = this.dataset.id;
        document.getElementById('edit_dept_name').value = this.dataset.name;
        document.getElementById('edit_manager_id').value = this.dataset.manager || '';
        document.getElementById('edit_status').value = this.dataset.status;
    });
});
</script>
