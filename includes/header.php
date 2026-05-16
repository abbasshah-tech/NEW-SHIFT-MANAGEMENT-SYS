<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SHIFT Pro | Management System</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap 5 (AdminLTE 4 depends on it) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- AdminLTE v3/4 fallback (Using adminlte@3.2.0 as v4 might still be alpha, but sticking to the v3/v4 hybrid classes requested) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    
    <style>
        .wrapper { min-height: 100vh; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="dashboard.php" class="nav-link">Home</a>
            </li>
        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto">
            <!-- Notifications Dropdown Menu -->
            <li class="nav-item dropdown">
                <a class="nav-link" data-bs-toggle="dropdown" href="#" id="notificationBell">
                    <i class="far fa-bell"></i>
                    <span class="badge bg-warning navbar-badge" id="notificationCount" style="display:none;">0</span>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end shadow" style="min-width: 300px;">
                    <span class="dropdown-item dropdown-header border-bottom">
                        <strong id="notificationHeaderCount">0 Notifications</strong>
                        <a href="#" class="float-end text-sm" id="markAllRead" style="display:none;">Mark all read</a>
                    </span>
                    <div id="notificationList" style="max-height: 300px; overflow-y: auto;">
                        <div class="dropdown-item text-center text-muted py-3">No new notifications</div>
                    </div>
                </div>
            </li>

            <!-- User Dropdown Menu -->
            <li class="nav-item dropdown">
                <a class="nav-link" data-bs-toggle="dropdown" href="#">
                    <i class="far fa-user"></i> <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                    <span class="dropdown-item dropdown-header">Account Options</span>
                    <div class="dropdown-divider"></div>
                    <a href="../logout.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </div>
            </li>
        </ul>
    </nav>
    <!-- /.navbar -->

    <!-- Notifications AJAX Script -->
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const fetchUrl = "<?php echo strpos($_SERVER['PHP_SELF'], '/pages/') !== false ? '../api/fetch_notifications.php' : 'api/fetch_notifications.php'; ?>";
        const markUrl = "<?php echo strpos($_SERVER['PHP_SELF'], '/pages/') !== false ? '../api/mark_notification_read.php' : 'api/mark_notification_read.php'; ?>";

        function fetchNotifications() {
            fetch(fetchUrl)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        updateNotificationUI(data.count, data.notifications);
                    }
                })
                .catch(error => console.error('Error fetching notifications:', error));
        }

        function updateNotificationUI(count, notifications) {
            const countBadge = document.getElementById('notificationCount');
            const headerCount = document.getElementById('notificationHeaderCount');
            const markAllBtn = document.getElementById('markAllRead');
            const list = document.getElementById('notificationList');

            if (count > 0) {
                countBadge.textContent = count;
                countBadge.style.display = 'inline';
                headerCount.textContent = count + ' New Notification' + (count > 1 ? 's' : '');
                markAllBtn.style.display = 'inline';
                
                let html = '';
                notifications.forEach(notif => {
                    let icon = 'fa-info-circle text-info';
                    if (notif.type === 'success') icon = 'fa-check-circle text-success';
                    if (notif.type === 'warning') icon = 'fa-exclamation-triangle text-warning';
                    if (notif.type === 'danger') icon = 'fa-times-circle text-danger';

                    html += `
                        <div class="dropdown-divider"></div>
                        <a href="#" class="dropdown-item notification-item" data-id="${notif.id}">
                            <div class="d-flex">
                                <div class="flex-shrink-0 mt-1"><i class="fas ${icon}"></i></div>
                                <div class="flex-grow-1 ms-2">
                                    <h6 class="mb-0 text-sm fw-bold">${notif.title}</h6>
                                    <p class="mb-0 text-sm text-muted text-wrap" style="white-space: normal;">${notif.message}</p>
                                    <small class="text-muted"><i class="far fa-clock me-1"></i>${notif.created_at}</small>
                                </div>
                            </div>
                        </a>
                    `;
                });
                list.innerHTML = html;

                // Add click events to mark as read
                document.querySelectorAll('.notification-item').forEach(item => {
                    item.addEventListener('click', function(e) {
                        e.preventDefault();
                        markAsRead('mark_one', this.dataset.id);
                    });
                });

            } else {
                countBadge.style.display = 'none';
                headerCount.textContent = '0 Notifications';
                markAllBtn.style.display = 'none';
                list.innerHTML = '<div class="dropdown-item text-center text-muted py-3">No new notifications</div>';
            }
        }

        function markAsRead(action, id = null) {
            fetch(markUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: action, notification_id: id})
            }).then(() => {
                fetchNotifications(); // Refresh list
            });
        }

        document.getElementById('markAllRead').addEventListener('click', function(e) {
            e.preventDefault();
            markAsRead('mark_all');
        });

        // Initial fetch and poll every 10 seconds
        fetchNotifications();
        setInterval(fetchNotifications, 10000);
    });
    </script>
