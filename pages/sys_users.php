<?php
// pages/sys_users.php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (function_exists('enforce_access')) {
    enforce_access($pdo, 'sys_users.php');
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action == 'add') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $role_id = $_POST['role_id'];
        
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $message = "<div class='alert alert-danger'>Username already exists.</div>";
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role_id, status) VALUES (?, ?, ?, 'Active')");
            if ($stmt->execute([$username, $password_hash, $role_id])) {
                $message = "<div class='alert alert-success'>User added successfully.</div>";
            } else {
                $message = "<div class='alert alert-danger'>Failed to add user.</div>";
            }
        }
    } elseif ($action == 'edit') {
        $id = $_POST['user_id'];
        $username = trim($_POST['username']);
        $role_id = $_POST['role_id'];
        $status = $_POST['status'];
        $password = $_POST['password'];
        
        // Check if updating super admin
        if ($id == 1 && $status == 'Inactive') {
            $message = "<div class='alert alert-danger'>Cannot deactivate the primary Super Admin.</div>";
        } else {
            if (!empty($password)) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username=?, password_hash=?, role_id=?, status=? WHERE id=?");
                $result = $stmt->execute([$username, $password_hash, $role_id, $status, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username=?, role_id=?, status=? WHERE id=?");
                $result = $stmt->execute([$username, $role_id, $status, $id]);
            }

            if ($result) {
                $message = "<div class='alert alert-success'>User updated successfully.</div>";
            } else {
                $message = "<div class='alert alert-danger'>Failed to update user.</div>";
            }
        }
    } elseif ($action == 'delete') {
        $id = $_POST['user_id'];
        if ($id == 1) {
            $message = "<div class='alert alert-danger'>Cannot delete the primary Super Admin account.</div>";
        } else {
            // Might have FK constraints
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
                $stmt->execute([$id]);
                $message = "<div class='alert alert-success'>User deleted successfully.</div>";
            } catch (Exception $e) {
                $message = "<div class='alert alert-danger'>Cannot delete user. They are linked to other records. Deactivate them instead.</div>";
            }
        }
    }
}

// Fetch users
$query = "
    SELECT u.id, u.username, u.status, r.role_name, u.role_id, u.created_at, e.first_name, e.last_name
    FROM users u 
    LEFT JOIN sys_roles r ON u.role_id = r.id
    LEFT JOIN employees e ON u.id = e.user_id
    ORDER BY u.id ASC
";
$users = $pdo->query($query)->fetchAll();

// Fetch roles
$roles = $pdo->query("SELECT id, role_name FROM sys_roles")->fetchAll();

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 fw-bold">Manage Users</h1>
                </div>
                <div class="col-sm-6 text-end">
                    <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-user-plus"></i> Add User
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
                                <th>Username</th>
                                <th>Linked Employee</th>
                                <th>Role</th>
                                <th>Created</th>
                                <th>Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td class="fw-bold">@<?php echo htmlspecialchars($user['username']); ?></td>
                                    <td>
                                        <?php if ($user['first_name']): ?>
                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">Standalone User</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-primary"><?php echo htmlspecialchars($user['role_name']); ?></span></td>
                                    <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['status'] == 'Active' ? 'success' : 'danger'; ?>">
                                            <?php echo $user['status']; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-info edit-btn" 
                                            data-id="<?php echo $user['id']; ?>"
                                            data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                            data-role="<?php echo $user['role_id']; ?>"
                                            data-status="<?php echo $user['status']; ?>"
                                            data-bs-toggle="modal" data-bs-target="#editUserModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($user['id'] != 1): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this user?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </form>
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
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-plus"></i> Add User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>System Role</label>
                        <select name="role_id" class="form-control" required>
                            <option value="">-- Select Role --</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white border-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-edit"></i> Edit User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="mb-3">
                        <label>Username</label>
                        <input type="text" name="username" id="edit_username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>New Password <small class="text-muted">(Leave blank to keep current)</small></label>
                        <input type="password" name="password" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label>System Role</label>
                        <select name="role_id" id="edit_role_id" class="form-control" required>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
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
                    <button type="submit" class="btn btn-info text-white">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('edit_user_id').value = this.dataset.id;
        document.getElementById('edit_username').value = this.dataset.username;
        document.getElementById('edit_role_id').value = this.dataset.role;
        document.getElementById('edit_status').value = this.dataset.status;
    });
});
</script>
