<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="dashboard.php" class="brand-link">
        <span class="brand-text font-weight-light">SHIFT Pro</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar user panel (optional) -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="info">
                <a href="#" class="d-block"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></a>
            </div>
        </div>

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                
                <?php
                // Get dynamic menus based on role
                $menus = get_sidebar_menus($pdo, $_SESSION['role_id']);
                
                foreach ($menus as $menu) {
                    $has_children = !empty($menu['children']);
                    
                    // Is this menu or its children active?
                    $current_page = basename($_SERVER['PHP_SELF']);
                    $is_active = ($current_page == $menu['page_url']) ? 'active' : '';
                    $menu_open = '';
                    
                    if ($has_children) {
                        foreach ($menu['children'] as $child) {
                            if ($current_page == $child['page_url']) {
                                $is_active = 'active';
                                $menu_open = 'menu-open';
                                break;
                            }
                        }
                    }

                    ?>
                    <li class="nav-item <?php echo $menu_open; ?>">
                        <a href="<?php echo $has_children ? '#' : htmlspecialchars($menu['page_url']); ?>" class="nav-link <?php echo $is_active; ?>">
                            <i class="nav-icon <?php echo htmlspecialchars($menu['icon']); ?>"></i>
                            <p>
                                <?php echo htmlspecialchars($menu['page_name']); ?>
                                <?php if ($has_children): ?>
                                    <i class="right fas fa-angle-left"></i>
                                <?php endif; ?>
                            </p>
                        </a>
                        
                        <?php if ($has_children): ?>
                            <ul class="nav nav-treeview">
                                <?php foreach ($menu['children'] as $child): ?>
                                    <li class="nav-item">
                                        <a href="<?php echo htmlspecialchars($child['page_url']); ?>" class="nav-link <?php echo ($current_page == $child['page_url']) ? 'active' : ''; ?>">
                                            <i class="<?php echo htmlspecialchars($child['icon']); ?> nav-icon"></i>
                                            <p><?php echo htmlspecialchars($child['page_name']); ?></p>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                    <?php
                }
                ?>
            </ul>
        </nav>
        <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
</aside>
