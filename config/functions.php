<?php
// config/functions.php

/**
 * Check if the user is logged in. If not, redirect to login page.
 */
function check_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../index.php");
        exit();
    }
}

/**
 * Check if the current user role has access to the given page URL
 * @param PDO $pdo Database connection
 * @param int $role_id User's role ID
 * @param string $page_url The URL of the page being accessed
 * @return bool True if allowed, False otherwise
 */
function has_access($pdo, $role_id, $page_url) {
    $stmt = $pdo->prepare("
        SELECT 1 
        FROM role_access ra
        JOIN sys_pages sp ON ra.page_id = sp.id
        WHERE ra.role_id = ? AND sp.page_url = ?
    ");
    $stmt->execute([$role_id, $page_url]);
    return $stmt->fetchColumn() ? true : false;
}

/**
 * Middleware function to enforce access control on pages
 * @param PDO $pdo
 * @param string $page_url
 */
function enforce_access($pdo, $page_url) {
    check_login();
    if (!has_access($pdo, $_SESSION['role_id'], $page_url)) {
        // You could redirect to a 403 Forbidden page here
        echo "<h3>403 Access Denied. You do not have permission to view this page.</h3>";
        exit();
    }
}

/**
 * Sanitize output to prevent XSS
 */
function sanitize($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Get dynamic sidebar menus based on user role
 */
function get_sidebar_menus($pdo, $role_id) {
    // Step 1: Get ALL page IDs this role can access
    $stmt = $pdo->prepare("SELECT page_id FROM role_access WHERE role_id = ?");
    $stmt->execute([$role_id]);
    $allowed_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($allowed_ids)) return [];

    // Step 2: Get allowed top-level pages (parent_id = 0) that are directly accessible
    // PLUS parent menus whose children are accessible
    $placeholders = implode(',', array_fill(0, count($allowed_ids), '?'));
    
    $stmt = $pdo->prepare("
        SELECT DISTINCT sp.* FROM sys_pages sp
        WHERE sp.parent_id = 0
          AND (
              sp.id IN ($placeholders)
              OR sp.id IN (SELECT parent_id FROM sys_pages WHERE id IN ($placeholders))
          )
        ORDER BY sp.sort_order ASC
    ");
    // Merge allowed_ids twice for the two IN clauses
    $stmt->execute(array_merge($allowed_ids, $allowed_ids));
    $parents = $stmt->fetchAll();

    $menus = [];
    foreach ($parents as $parent) {
        $parent['children'] = [];
        
        // Step 3: Get allowed child pages under this parent
        $stmt_child = $pdo->prepare("
            SELECT sp.* 
            FROM sys_pages sp
            JOIN role_access ra ON sp.id = ra.page_id
            WHERE ra.role_id = ? AND sp.parent_id = ?
            ORDER BY sp.sort_order ASC
        ");
        $stmt_child->execute([$role_id, $parent['id']]);
        $parent['children'] = $stmt_child->fetchAll();
        
        // Only include parent if it has children OR is directly accessible (like Dashboard)
        if (!empty($parent['children']) || in_array($parent['id'], $allowed_ids)) {
            $menus[] = $parent;
        }
    }
    
    return $menus;
}

/**
 * Create a new notification for a user
 * @param PDO $pdo
 * @param int $user_id
 * @param string $title
 * @param string $message
 * @param string $type (info, warning, success, danger)
 */
function create_notification($pdo, $user_id, $title, $message, $type = 'info') {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$user_id, $title, $message, $type]);
}

/**
 * Send email notification (Stub for PHPMailer)
 * @param string $to
 * @param string $subject
 * @param string $body
 */
function send_email_notification($to, $subject, $body) {
    // In a real production environment, you would require PHPMailer here:
    // require_once __DIR__ . '/../libs/PHPMailer/src/Exception.php';
    // require_once __DIR__ . '/../libs/PHPMailer/src/PHPMailer.php';
    // require_once __DIR__ . '/../libs/PHPMailer/src/SMTP.php';
    
    /* Example Implementation:
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.example.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'user@example.com';
        $mail->Password   = 'password';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('noreply@shiftpro.com', 'SHIFT Pro');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
    */
    
    // For localhost XAMPP testing without SMTP configured, just return true or log it.
    error_log("Email stub called for $to - $subject");
    return true;
}
?>
