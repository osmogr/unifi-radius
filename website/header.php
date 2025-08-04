<?php
/**
 * Standardized Header Template for UniFi RADIUS Admin Interface
 * 
 * This template provides consistent navigation and branding across all pages
 * of the admin interface. It implements a responsive Bootstrap navbar with
 * role-based menu items and active page highlighting.
 * 
 * Key features:
 * - Responsive navigation that works on desktop and mobile
 * - Active page highlighting for user orientation
 * - Consistent branding with UniFi RADIUS logo/title
 * - Dropdown menus for grouped functionality
 * - Bootstrap Icons for visual navigation cues
 * - Mobile-friendly hamburger menu for small screens
 * 
 * Navigation structure:
 * - Dashboard: Main overview and statistics
 * - Client Management: View, add, edit client assignments
 * - MAC Prefixes: Bulk vendor-based VLAN assignments
 * - Default VLAN: Fallback access configuration
 * - UniFi Integration: Import clients from UniFi Controller
 * - Admin Users: User account management
 * - RADIUS Logs: Authentication monitoring and troubleshooting
 * 
 * For beginners:
 *   This is like the main menu bar in any software - it stays the same
 *   on every page and lets you navigate between different sections.
 *   The current page is highlighted so you know where you are.
 * 
 * Usage:
 *   Include this file at the top of each page's HTML section after setting
 *   the $active_page variable to highlight the current navigation item.
 * 
 * Example:
 *   $active_page = 'dashboard';
 *   include 'header.php';
 * 
 * Active page values:
 * - 'dashboard': Main dashboard/home page
 * - 'view_clients': Client listing page
 * - 'add_client': Add new client form
 * - 'mac_prefixes': MAC prefix management
 * - 'default_vlan': Default VLAN configuration
 * - 'import_unifi': UniFi Controller import
 * - 'admin_users': Admin user management
 * - 'radius_logs': RADIUS log viewer
 * 
 * @package UniFiRadius
 * @subpackage Templates
 */

// Ensure we have an active page variable for navigation highlighting
if (!isset($active_page)) {
    $active_page = '';
}

// Define navigation items with their URLs, titles, and Bootstrap icons
// This centralizes navigation management and makes it easy to add/modify menu items
$nav_items = [
    'dashboard' => ['url' => 'index.php', 'title' => 'Dashboard', 'icon' => 'speedometer2'],
    'view_clients' => ['url' => 'view_clients.php', 'title' => 'View Clients', 'icon' => 'list'],
    'add_client' => ['url' => 'add_client.php', 'title' => 'Add Client', 'icon' => 'plus-circle'],
    'mac_prefixes' => ['url' => 'mac_prefixes.php', 'title' => 'MAC Prefixes', 'icon' => 'tags'],
    'default_vlan' => ['url' => 'default_vlan.php', 'title' => 'Default VLAN', 'icon' => 'gear'],
    'import_unifi' => ['url' => 'import_unifi.php', 'title' => 'Import from UniFi', 'icon' => 'cloud-download'],
    'admin_users' => ['url' => 'admin_users.php', 'title' => 'Admin Users', 'icon' => 'people'],
    'radius_logs' => ['url' => 'radius_logs.php', 'title' => 'RADIUS Logs', 'icon' => 'activity']
];
?>
<!-- Bootstrap Responsive Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <!-- Brand/Logo Section -->
        <a class="navbar-brand" href="index.php">
            <i class="bi bi-router"></i> UniFi RADIUS Admin
        </a>
        
        <!-- Mobile Menu Toggle Button -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php foreach ($nav_items as $page_id => $item): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_page === $page_id ? 'active' : '' ?>" href="<?= $item['url'] ?>">
                            <?= $item['title'] ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?= htmlspecialchars(getCurrentUsername()) ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="index.php?logout=1">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>