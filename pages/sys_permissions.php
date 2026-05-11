<?php
// pages/sys_permissions.php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Enforce access control if function exists
if (function_exists('enforce_access')) {
    enforce_access($pdo, 'sys_permissions.php');
} else {
    if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
        header("Location: ../login.php");
        exit();
    }
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_permissions'])) {
    $permissions = $_POST['permissions'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        // Clear all permissions first (DELETE instead of TRUNCATE to stay inside transaction)
        $pdo->exec("DELETE FROM role_access");
        
        // Insert new permissions
        if (!empty($permissions)) {
            $stmt = $pdo->prepare("INSERT INTO role_access (role_id, page_id) VALUES (?, ?)");
            foreach ($permissions as $role_id => $pages) {
                foreach ($pages as $page_id) {
                    $stmt->execute([(int)$role_id, (int)$page_id]);
                }
            }
        }
        $pdo->commit();
        $message = "<div class='alert alert-success'>Permissions updated successfully!</div>";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = "<div class='alert alert-danger'>Failed to update permissions: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Fetch all roles
$roles = $pdo->query("SELECT * FROM sys_roles ORDER BY id ASC")->fetchAll();
// Fetch all pages
$pages = $pdo->query("SELECT * FROM sys_pages ORDER BY sort_order ASC")->fetchAll();
// Fetch current access
$access_stmt = $pdo->query("SELECT role_id, page_id FROM role_access");
$current_access = [];
while ($row = $access_stmt->fetch()) {
    $current_access[$row['role_id']][] = $row['page_id'];
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
    .perm-checkbox {
        width: 20px;
        height: 20px;
        cursor: pointer;
    }
    .table-sticky th {
        position: sticky;
        top: 0;
        background: #f8f9fa;
        z-index: 10;
    }
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <h1 class="m-0 fw-bold">Role Permissions Matrix</h1>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <?php echo $message; ?>
            
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-dark text-white border-0">
                    <h3 class="card-title fw-bold"><i class="fas fa-shield-alt me-2"></i> Access Control</h3>
                </div>
                <form method="POST" class="card-body p-0">
                    <div class="table-responsive" style="max-height: 600px;">
                        <table class="table table-bordered table-hover table-sticky mb-0 text-center">
                            <thead class="bg-light">
                                <tr>
                                    <th class="text-start" style="width: 250px;">Page / Module</th>
                                    <?php foreach ($roles as $role): ?>
                                        <th class="fw-bold text-primary">
                                            <?php echo htmlspecialchars($role['role_name']); ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pages as $page): ?>
                                    <tr>
                                        <td class="text-start fw-bold">
                                            <i class="<?php echo htmlspecialchars($page['icon']); ?> text-muted me-2"></i> 
                                            <?php echo htmlspecialchars($page['page_name']); ?>
                                            <br><small class="text-muted fw-normal"><?php echo htmlspecialchars($page['page_url']); ?></small>
                                        </td>
                                        
                                        <?php foreach ($roles as $role): 
                                            $checked = isset($current_access[$role['id']]) && in_array($page['id'], $current_access[$role['id']]) ? 'checked' : '';
                                        ?>
                                            <td class="align-middle">
                                                <div class="form-check d-flex justify-content-center m-0">
                                                    <input type="checkbox" class="form-check-input perm-checkbox" 
                                                           name="permissions[<?php echo $role['id']; ?>][]" 
                                                           value="<?php echo $page['id']; ?>" 
                                                           <?php echo $checked; ?>>
                                                </div>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-white text-end border-0 p-3">
                        <button type="submit" name="update_permissions" class="btn btn-success btn-lg px-5 fw-bold shadow-sm">
                            <i class="fas fa-save me-2"></i> Save Permissions
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>

<?php include '../includes/footer.php'; ?>
