<?php
// pages/employees.php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

if (function_exists('enforce_access')) {
    enforce_access($pdo, 'employees.php');
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action == 'add') {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $department_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
        $role_id = $_POST['role_id'];
        $date_joined = !empty($_POST['date_joined']) ? $_POST['date_joined'] : date('Y-m-d');
        $weekly_off_day = !empty($_POST['weekly_off_day']) ? $_POST['weekly_off_day'] : null;
        
        // Auto-generate username from email or name
        $username = strtolower($first_name . '.' . $last_name);
        // Add random number to ensure uniqueness
        $username .= rand(100, 999);
        
        $password = "password123"; // Default password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $pdo->beginTransaction();
        try {
            // 1. Create User Account
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role_id, status) VALUES (?, ?, ?, 'Active')");
            $stmt->execute([$username, $password_hash, $role_id]);
            $user_id = $pdo->lastInsertId();
            
            // 2. Create Employee Record
            $stmt = $pdo->prepare("INSERT INTO employees (user_id, first_name, last_name, email, phone, department_id, date_joined, weekly_off_day) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $first_name, $last_name, $email, $phone, $department_id, $date_joined, $weekly_off_day]);
            
            $pdo->commit();
            $message = "<div class='alert alert-success'>Employee added successfully. Default Login -> Username: <b>$username</b> | Password: <b>$password</b></div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Failed to add employee: " . $e->getMessage() . "</div>";
        }
    } elseif ($action == 'edit') {
        $emp_id = $_POST['emp_id'];
        $user_id = $_POST['user_id'];
        
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $department_id = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
        $role_id = $_POST['role_id'];
        $date_joined = $_POST['date_joined'];
        $weekly_off_day = !empty($_POST['weekly_off_day']) ? $_POST['weekly_off_day'] : null;
        
        $pdo->beginTransaction();
        try {
            // Update User Role
            $stmt = $pdo->prepare("UPDATE users SET role_id = ? WHERE id = ?");
            $stmt->execute([$role_id, $user_id]);
            
            // Update Employee Record
            $stmt = $pdo->prepare("UPDATE employees SET first_name=?, last_name=?, email=?, phone=?, department_id=?, date_joined=?, weekly_off_day=? WHERE id=?");
            $stmt->execute([$first_name, $last_name, $email, $phone, $department_id, $date_joined, $weekly_off_day, $emp_id]);
            
            $pdo->commit();
            $message = "<div class='alert alert-success'>Employee updated successfully.</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Failed to update employee.</div>";
        }
    } elseif ($action == 'delete') {
        $emp_id = $_POST['emp_id'];
        $user_id = $_POST['user_id'];
        
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("DELETE FROM employees WHERE id=?");
            $stmt->execute([$emp_id]);
            
            $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
            $stmt->execute([$user_id]);
            
            $pdo->commit();
            $message = "<div class='alert alert-success'>Employee deleted successfully.</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Failed to delete employee. They might have dependent records (shifts/attendance). Try deactivating their user account instead.</div>";
        }
    }
}

// Fetch employees with their department and role info
$query = "
    SELECT e.*, d.dept_name, u.role_id, u.username, u.status as user_status, r.role_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN users u ON e.user_id = u.id
    LEFT JOIN sys_roles r ON u.role_id = r.id
    ORDER BY e.id DESC
";
$employees_list = $pdo->query($query)->fetchAll();

// Form Dropdown Data
$departments = $pdo->query("SELECT id, dept_name FROM departments")->fetchAll();
$roles = $pdo->query("SELECT id, role_name FROM sys_roles WHERE role_name != 'Super Admin'")->fetchAll();

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 fw-bold">Manage Employees</h1>
                </div>
                <div class="col-sm-6 text-end">
                    <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#addEmpModal">
                        <i class="fas fa-plus"></i> Add Employee
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
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Department</th>
                                <th>Joining Date</th>
                                <th>Weekly Off</th>
                                <th>System Role</th>
                                <th>Account Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees_list as $emp): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></div>
                                        <small class="text-muted">@<?php echo htmlspecialchars($emp['username'] ?? 'N/A'); ?></small>
                                    </td>
                                    <td>
                                        <i class="fas fa-envelope text-muted me-1"></i> <?php echo htmlspecialchars($emp['email'] ?? 'N/A'); ?><br>
                                        <i class="fas fa-phone text-muted me-1"></i> <?php echo htmlspecialchars($emp['phone'] ?? 'N/A'); ?>
                                    </td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($emp['dept_name'] ?? 'Not Assigned'); ?></span></td>
                                    <td><?php echo $emp['date_joined'] ? date('d M Y', strtotime($emp['date_joined'])) : 'N/A'; ?></td>
                                    <td><span class="badge bg-info"><?php echo htmlspecialchars($emp['weekly_off_day'] ?? 'None'); ?></span></td>
                                    <td><span class="badge bg-primary"><?php echo htmlspecialchars($emp['role_name'] ?? 'None'); ?></span></td>
                                    <td>
                                        <span class="badge bg-<?php echo ($emp['user_status'] == 'Active') ? 'success' : 'danger'; ?>">
                                            <?php echo htmlspecialchars($emp['user_status'] ?? 'Unknown'); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-info edit-btn" 
                                            data-id="<?php echo $emp['id']; ?>"
                                            data-userid="<?php echo $emp['user_id']; ?>"
                                            data-fname="<?php echo htmlspecialchars($emp['first_name']); ?>"
                                            data-lname="<?php echo htmlspecialchars($emp['last_name']); ?>"
                                            data-email="<?php echo htmlspecialchars($emp['email']); ?>"
                                            data-phone="<?php echo htmlspecialchars($emp['phone']); ?>"
                                            data-dept="<?php echo $emp['department_id']; ?>"
                                            data-role="<?php echo $emp['role_id']; ?>"
                                            data-joined="<?php echo $emp['date_joined']; ?>"
                                            data-weeklyoff="<?php echo $emp['weekly_off_day']; ?>"
                                            data-bs-toggle="modal" data-bs-target="#editEmpModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('WARNING: This deletes the employee and their login account. Proceed?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="emp_id" value="<?php echo $emp['id']; ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $emp['user_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($employees_list)): ?>
                                <tr><td colspan="6" class="text-center py-4">No employees found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addEmpModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-user-plus"></i> Add Employee</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>First Name</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Last Name</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Phone</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Date of Joining</label>
                            <input type="date" name="date_joined" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Weekly Off Day</label>
                            <select name="weekly_off_day" class="form-control">
                                <option value="">-- No Off Day --</option>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                                <option value="Sunday">Sunday</option>
                            </select>
                        </div>
                    </div>
                    <div class="row border-top pt-3 mt-2">
                        <div class="col-md-6 mb-3">
                            <label>Department</label>
                            <select name="department_id" class="form-control">
                                <option value="">-- Select Department --</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['dept_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>System Role (Access Level)</label>
                            <select name="role_id" class="form-control" required>
                                <option value="">-- Select Role --</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>" <?php echo $role['id']==4 ? 'selected':''; ?>>
                                        <?php echo htmlspecialchars($role['role_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Employee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editEmpModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white border-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-edit"></i> Edit Employee</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="emp_id" id="edit_emp_id">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>First Name</label>
                            <input type="text" name="first_name" id="edit_fname" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Last Name</label>
                            <input type="text" name="last_name" id="edit_lname" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Email</label>
                            <input type="email" name="email" id="edit_email" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Phone</label>
                            <input type="text" name="phone" id="edit_phone" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Date of Joining</label>
                            <input type="date" name="date_joined" id="edit_joined" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Weekly Off Day</label>
                            <select name="weekly_off_day" id="edit_weeklyoff" class="form-control">
                                <option value="">-- No Off Day --</option>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                                <option value="Sunday">Sunday</option>
                            </select>
                        </div>
                    </div>
                    <div class="row border-top pt-3 mt-2">
                        <div class="col-md-6 mb-3">
                            <label>Department</label>
                            <select name="department_id" id="edit_dept" class="form-control">
                                <option value="">-- Select Department --</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['dept_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>System Role (Access Level)</label>
                            <select name="role_id" id="edit_role" class="form-control" required>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>">
                                        <?php echo htmlspecialchars($role['role_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white">Update Employee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('edit_emp_id').value = this.dataset.id;
        document.getElementById('edit_user_id').value = this.dataset.userid;
        document.getElementById('edit_fname').value = this.dataset.fname;
        document.getElementById('edit_lname').value = this.dataset.lname;
        document.getElementById('edit_email').value = this.dataset.email;
        document.getElementById('edit_phone').value = this.dataset.phone;
        document.getElementById('edit_dept').value = this.dataset.dept || '';
        document.getElementById('edit_role').value = this.dataset.role || '';
        document.getElementById('edit_joined').value = this.dataset.joined || '';
        document.getElementById('edit_weeklyoff').value = this.dataset.weeklyoff || '';
    });
});
</script>
