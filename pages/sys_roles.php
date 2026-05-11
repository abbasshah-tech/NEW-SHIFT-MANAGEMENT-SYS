<?php
// pages/sys_roles.php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (function_exists('enforce_access')) {
    enforce_access($pdo, 'sys_roles.php');
}

$message = '';

// Core roles that cannot be deleted or modified
$core_roles = [1, 2, 3, 4];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action == 'add') {
        $role_name = trim($_POST['role_name']);
        
        $stmt = $pdo->prepare("SELECT id FROM sys_roles WHERE role_name = ?");
        $stmt->execute([$role_name]);
        if ($stmt->fetch()) {
            $message = "<div class='alert alert-danger'>Role name already exists.</div>";
        } else {
            $stmt = $pdo->prepare("INSERT INTO sys_roles (role_name) VALUES (?)");
            if ($stmt->execute([$role_name])) {
                $message = "<div class='alert alert-success'>Role added successfully.</div>";
            } else {
                $message = "<div class='alert alert-danger'>Failed to add role.</div>";
            }
        }
    } elseif ($action == 'edit') {
        $id = $_POST['role_id'];
        $role_name = trim($_POST['role_name']);
        
        if (in_array($id, $core_roles)) {
            $message = "<div class='alert alert-danger'>Core system roles cannot be modified.</div>";
        } else {
            $stmt = $pdo->prepare("UPDATE sys_roles SET role_name=? WHERE id=?");
            if ($stmt->execute([$role_name, $id])) {
                $message = "<div class='alert alert-success'>Role updated successfully.</div>";
            } else {
                $message = "<div class='alert alert-danger'>Failed to update role.</div>";
            }
        }
    } elseif ($action == 'delete') {
        $id = $_POST['role_id'];
        if (in_array($id, $core_roles)) {
            $message = "<div class='alert alert-danger'>Core system roles cannot be deleted.</div>";
        } else {
            // Check if users exist with this role
            $stmt = $pdo->prepare("SELECT id FROM users WHERE role_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetch()) {
                $message = "<div class='alert alert-danger'>Cannot delete role. There are users assigned to it.</div>";
            } else {
                $stmt = $pdo->prepare("DELETE FROM sys_roles WHERE id=?");
                if ($stmt->execute([$id])) {
                    $message = "<div class='alert alert-success'>Role deleted successfully.</div>";
                } else {
                    $message = "<div class='alert alert-danger'>Failed to delete role.</div>";
                }
            }
        }
    }
}

// Fetch roles
$roles = $pdo->query("SELECT * FROM sys_roles ORDER BY id ASC")->fetchAll();

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 fw-bold">Manage Roles</h1>
                </div>
                <div class="col-sm-6 text-end">
                    <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                        <i class="fas fa-plus"></i> Add Role
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
                                <th>Role ID</th>
                                <th>Role Name</th>
                                <th>Type</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roles as $role): 
                                $is_core = in_array($role['id'], $core_roles);
                            ?>
                                <tr>
                                    <td class="fw-bold"><?php echo $role['id']; ?></td>
                                    <td><span class="badge bg-primary fs-6"><?php echo htmlspecialchars($role['role_name']); ?></span></td>
                                    <td>
                                        <?php if ($is_core): ?>
                                            <span class="badge bg-dark"><i class="fas fa-lock"></i> Core System Role</span>
                                        <?php else: ?>
                                            <span class="badge bg-info">Custom Role</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if (!$is_core): ?>
                                            <button class="btn btn-sm btn-info edit-btn" 
                                                data-id="<?php echo $role['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($role['role_name']); ?>"
                                                data-bs-toggle="modal" data-bs-target="#editRoleModal">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this role?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-secondary" disabled title="Core role cannot be modified"><i class="fas fa-ban"></i> Protected</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-plus"></i> Add Custom Role</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label>Role Name</label>
                        <input type="text" name="role_name" class="form-control" required placeholder="e.g., Intern">
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white border-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-edit"></i> Edit Custom Role</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="role_id" id="edit_role_id">
                    <div class="mb-3">
                        <label>Role Name</label>
                        <input type="text" name="role_name" id="edit_role_name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white">Update Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('edit_role_id').value = this.dataset.id;
        document.getElementById('edit_role_name').value = this.dataset.name;
    });
});
</script>
