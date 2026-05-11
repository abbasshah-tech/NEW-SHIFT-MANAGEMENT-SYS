<?php
// pages/swap_requests.php
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

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Employee creating a swap request
    if ($action == 'request_swap' && $role_id == 4) {
        $requester_shift_id = $_POST['my_shift'];
        $target_shift_id = $_POST['target_shift'];
        
        // Find target employee id from target shift
        $stmt = $pdo->prepare("SELECT employee_id FROM employee_shifts WHERE id = ?");
        $stmt->execute([$target_shift_id]);
        $target_id = $stmt->fetchColumn();

        if ($target_id && $requester_shift_id != $target_shift_id) {
            $stmt = $pdo->prepare("INSERT INTO shift_swap_requests (requester_id, target_id, requester_shift_id, target_shift_id) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$emp_id, $target_id, $requester_shift_id, $target_shift_id])) {
                $message = "<div class='alert alert-success'>Swap request submitted successfully! Pending manager approval.</div>";
            } else {
                $message = "<div class='alert alert-danger'>Failed to submit request.</div>";
            }
        }
    } 
    // Manager/Admin approving or rejecting
    elseif (in_array($action, ['approve', 'reject']) && in_array($role_id, [1, 2, 3])) {
        $request_id = $_POST['request_id'];
        $status = $action == 'approve' ? 'Approved' : 'Rejected';

        $pdo->beginTransaction();
        try {
            // Update request status
            $stmt = $pdo->prepare("UPDATE shift_swap_requests SET status = ?, manager_id = ? WHERE id = ?");
            $stmt->execute([$status, $user_id, $request_id]);

            if ($status == 'Approved') {
                // Perform the actual swap
                $stmt = $pdo->prepare("SELECT requester_id, target_id, requester_shift_id, target_shift_id FROM shift_swap_requests WHERE id = ?");
                $stmt->execute([$request_id]);
                $req = $stmt->fetch();

                // Swap the employee_id in employee_shifts
                $stmt = $pdo->prepare("UPDATE employee_shifts SET employee_id = ? WHERE id = ?");
                $stmt->execute([$req['target_id'], $req['requester_shift_id']]);
                
                $stmt = $pdo->prepare("UPDATE employee_shifts SET employee_id = ? WHERE id = ?");
                $stmt->execute([$req['requester_id'], $req['target_shift_id']]);
            }
            $pdo->commit();
            $message = "<div class='alert alert-success'>Request $status successfully.</div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert alert-danger'>Error processing request.</div>";
        }
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <h1 class="m-0 fw-bold">Shift Swap Requests</h1>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <?php echo $message; ?>

            <?php if ($role_id == 4): ?>
            <!-- Request a Swap Form (Employee Only) -->
            <div class="card shadow-sm border-0 rounded-3 mb-4">
                <div class="card-header bg-primary text-white border-0">
                    <h3 class="card-title fw-bold"><i class="fas fa-exchange-alt me-2"></i> Request a Shift Swap</h3>
                </div>
                <div class="card-body">
                    <form method="POST" class="row align-items-end">
                        <input type="hidden" name="action" value="request_swap">
                        
                        <div class="col-md-5 mb-3">
                            <label class="form-label">My Upcoming Shift</label>
                            <select name="my_shift" class="form-control" required>
                                <option value="">-- Select Your Shift --</option>
                                <?php
                                $stmt = $pdo->prepare("SELECT es.id, s.shift_name, es.roster_date FROM employee_shifts es JOIN shifts s ON es.shift_id = s.id WHERE es.employee_id = ? AND es.roster_date > CURDATE() ORDER BY es.roster_date ASC");
                                $stmt->execute([$emp_id]);
                                while($row = $stmt->fetch()) {
                                    echo "<option value='{$row['id']}'>" . date('d M Y', strtotime($row['roster_date'])) . " - {$row['shift_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-5 mb-3">
                            <label class="form-label">Target Shift (Colleague's Shift)</label>
                            <select name="target_shift" class="form-control" required>
                                <option value="">-- Select Colleague's Shift --</option>
                                <?php
                                $stmt = $pdo->prepare("SELECT es.id, e.first_name, e.last_name, s.shift_name, es.roster_date FROM employee_shifts es JOIN employees e ON es.employee_id = e.id JOIN shifts s ON es.shift_id = s.id WHERE es.employee_id != ? AND es.roster_date > CURDATE() ORDER BY es.roster_date ASC LIMIT 50");
                                $stmt->execute([$emp_id]);
                                while($row = $stmt->fetch()) {
                                    echo "<option value='{$row['id']}'>{$row['first_name']} {$row['last_name']} on " . date('d M Y', strtotime($row['roster_date'])) . " - {$row['shift_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-2 mb-3">
                            <button type="submit" class="btn btn-success w-100 fw-bold">Submit Request</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Swap Requests Table -->
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white border-0">
                    <h3 class="card-title fw-bold">
                        <i class="fas fa-list text-primary me-2"></i> 
                        <?php echo ($role_id == 4) ? "My Swap Requests" : "All Swap Requests"; ?>
                    </h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Requester</th>
                                <th>Requester Shift</th>
                                <th>Target Employee</th>
                                <th>Target Shift</th>
                                <th>Status</th>
                                <?php if ($role_id != 4): ?><th class="text-center">Action</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $query = "
                                SELECT r.id, r.status,
                                    req_e.first_name as req_fname, req_e.last_name as req_lname,
                                    tar_e.first_name as tar_fname, tar_e.last_name as tar_lname,
                                    req_s.shift_name as req_sname, req_es.roster_date as req_date,
                                    tar_s.shift_name as tar_sname, tar_es.roster_date as tar_date
                                FROM shift_swap_requests r
                                JOIN employees req_e ON r.requester_id = req_e.id
                                JOIN employees tar_e ON r.target_id = tar_e.id
                                JOIN employee_shifts req_es ON r.requester_shift_id = req_es.id
                                JOIN shifts req_s ON req_es.shift_id = req_s.id
                                JOIN employee_shifts tar_es ON r.target_shift_id = tar_es.id
                                JOIN shifts tar_s ON tar_es.shift_id = tar_s.id
                            ";
                            
                            if ($role_id == 4) {
                                $query .= " WHERE r.requester_id = ? OR r.target_id = ? ORDER BY r.created_at DESC";
                                $stmt = $pdo->prepare($query);
                                $stmt->execute([$emp_id, $emp_id]);
                            } else {
                                $query .= " ORDER BY r.status = 'Pending' DESC, r.created_at DESC";
                                $stmt = $pdo->query($query);
                            }
                            
                            $requests = $stmt->fetchAll();
                            foreach ($requests as $req):
                                $badge = 'secondary';
                                if ($req['status'] == 'Approved') $badge = 'success';
                                if ($req['status'] == 'Pending') $badge = 'warning';
                                if ($req['status'] == 'Rejected') $badge = 'danger';
                            ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($req['req_fname'] . ' ' . $req['req_lname']); ?></td>
                                <td><span class="badge bg-info"><?php echo $req['req_sname']; ?></span> <br><small><?php echo date('d M Y', strtotime($req['req_date'])); ?></small></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($req['tar_fname'] . ' ' . $req['tar_lname']); ?></td>
                                <td><span class="badge bg-info"><?php echo $req['tar_sname']; ?></span> <br><small><?php echo date('d M Y', strtotime($req['tar_date'])); ?></small></td>
                                <td><span class="badge bg-<?php echo $badge; ?>"><?php echo $req['status']; ?></span></td>
                                
                                <?php if ($role_id != 4): ?>
                                <td class="text-center">
                                    <?php if ($req['status'] == 'Pending'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Approve</button>
                                    </form>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-times"></i> Reject</button>
                                    </form>
                                    <?php else: ?>
                                        <span class="text-muted"><i class="fas fa-lock"></i> Processed</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if(empty($requests)): ?>
                                <tr><td colspan="<?php echo ($role_id == 4) ? 5 : 6; ?>" class="text-center py-4 text-muted">No swap requests found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include '../includes/footer.php'; ?>
