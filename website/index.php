<?php
/**
 * UniFi RADIUS Admin Website - Main Dashboard and Login Page
 * 
 * This is the main entry point for the UniFi RADIUS Admin interface.
 * It serves dual purposes:
 * 1. Login page for unauthenticated users
 * 2. Dashboard with statistics and recent activity for authenticated users
 * 
 * Key features:
 * - Secure login form with CSRF protection
 * - Real-time statistics dashboard showing:
 *   - Total managed clients and VLANs
 *   - MAC prefix assignments for bulk management
 *   - Recent authentication activity
 *   - RADIUS server performance metrics
 * - Responsive Bootstrap 5 interface
 * - Quick access navigation to all admin functions
 * 
 * Security features:
 * - Session-based authentication
 * - Session regeneration on login
 * - Secure logout with session cleanup
 * - Input validation and sanitization
 * 
 * For beginners:
 *   This is like the main control panel for a security system. If you're
 *   not logged in, it shows a login screen. If you are logged in, it shows
 *   an overview of everything that's happening with network access control.
 * 
 * Dashboard sections:
 * - System Statistics: Overall counts and metrics
 * - Recent Activity: Latest client connections and changes
 * - Quick Actions: Fast access to common admin tasks
 * - RADIUS Logs: Authentication attempt monitoring
 * 
 * @package UniFiRadius
 * @subpackage WebInterface
 */

require_once 'auth.php';
require_once 'db.php';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Extract and sanitize login credentials
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Attempt authentication using the auth system
    if (authenticate($username, $password)) {
        // Login successful - redirect to requested page or dashboard
        $redirect = $_SESSION['redirect_after_login'] ?? 'index.php';
        unset($_SESSION['redirect_after_login']); // Clean up redirect URL
        header("Location: $redirect");
        exit;
    } else {
        // Login failed - show error message
        $error = 'Invalid username or password';
    }
}

// Handle logout request
if (isset($_GET['logout'])) {
    logout(); // Clear session and authentication data
    header('Location: index.php?message=logged_out');
    exit;
}

// Check if user is authenticated for dashboard access
$authenticated = isAuthenticated();

// Initialize variables for dashboard data
$stats = [];
$radius_stats = [];
$recent_logs = [];

// Gather dashboard statistics if user is authenticated
if ($authenticated) {
    try {
        $db = getDB();
        
        // Count total clients with VLAN assignments
        $stmt = $db->query("SELECT COUNT(DISTINCT username) as total FROM radreply WHERE attribute = 'Tunnel-Private-Group-ID'");
        $stats['total_clients'] = $stmt->fetch()['total'] ?? 0;
        
        // Count unique VLANs in use
        $stmt = $db->query("SELECT COUNT(DISTINCT value) as total FROM radreply WHERE attribute = 'Tunnel-Private-Group-ID'");
        $stats['total_vlans'] = $stmt->fetch()['total'] ?? 0;
        
        // Count MAC prefix assignments for bulk management
        $stmt = $db->query("SELECT COUNT(*) as total FROM mac_prefixes");
        $stats['total_prefixes'] = $stmt->fetch()['total'] ?? 0;
        
        // Get recent client activity for dashboard overview
        $stmt = $db->query("
            SELECT cn.mac, cn.description, cn.last_seen, r.value as vlan
            FROM client_notes cn
            LEFT JOIN radreply r ON cn.mac = r.username AND r.attribute = 'Tunnel-Private-Group-ID'
            ORDER BY cn.last_seen DESC 
            LIMIT 5
        ");
        $stats['recent_clients'] = $stmt->fetchAll();
        
        // Get RADIUS authentication statistics
        $radius_stats = getRadiusLogStats();
        
        // Get recent RADIUS authentication logs for monitoring
        $recent_logs = getRecentRadiusLogs(10);
        
    } catch (Exception $e) {
        // Handle database errors gracefully
        error_log("Error fetching dashboard stats: " . $e->getMessage());
        $stats = ['total_clients' => 0, 'total_vlans' => 0, 'total_prefixes' => 0, 'recent_clients' => []];
        $radius_stats = ['today_total' => 0, 'today_success' => 0, 'today_failed' => 0];
        $recent_logs = [];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniFi RADIUS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .dashboard-card {
            border-left: 4px solid #0d6efd;
        }
        .stats-card {
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-2px);
        }
        .navbar-brand {
            font-weight: 600;
        }
    </style>
</head>
<body class="bg-light">

<?php if ($authenticated): ?>
    <?php $active_page = 'dashboard'; include 'header.php'; ?>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Page Header -->
        <div class="row">
            <div class="col-12">
                <h1 class="h3 mb-4">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </h1>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card h-100">
                    <div class="card-body text-center">
                        <div class="text-primary mb-3">
                            <i class="bi bi-devices display-4"></i>
                        </div>
                        <h3 class="card-title"><?= $stats['total_clients'] ?></h3>
                        <p class="card-text text-muted">Total Clients</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card h-100">
                    <div class="card-body text-center">
                        <div class="text-success mb-3">
                            <i class="bi bi-diagram-3 display-4"></i>
                        </div>
                        <h3 class="card-title"><?= $stats['total_vlans'] ?></h3>
                        <p class="card-text text-muted">VLANs in Use</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card h-100">
                    <div class="card-body text-center">
                        <div class="text-info mb-3">
                            <i class="bi bi-tags display-4"></i>
                        </div>
                        <h3 class="card-title"><?= $stats['total_prefixes'] ?></h3>
                        <p class="card-text text-muted">MAC Prefixes</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card h-100">
                    <div class="card-body text-center">
                        <div class="text-success mb-3">
                            <i class="bi bi-check-circle display-4"></i>
                        </div>
                        <h3 class="card-title"><?= $radius_stats['today_success'] ?></h3>
                        <p class="card-text text-muted">Successful Today</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-clock-history"></i> Recent Clients
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($stats['recent_clients'])): ?>
                            <p class="text-muted">No recent client activity.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>MAC Address</th>
                                            <th>VLAN</th>
                                            <th>Description</th>
                                            <th>Last Seen</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stats['recent_clients'] as $client): ?>
                                            <tr>
                                                <td><code><?= htmlspecialchars($client['mac']) ?></code></td>
                                                <td>
                                                    <?php if ($client['vlan']): ?>
                                                        <span class="badge bg-primary"><?= htmlspecialchars($client['vlan']) ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">No VLAN</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($client['description'] ?: 'No description') ?></td>
                                                <td class="text-muted">
                                                    <?= $client['last_seen'] ? date('M j, Y g:i A', strtotime($client['last_seen'])) : 'Never' ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <a href="view_clients.php" class="btn btn-outline-primary btn-sm">
                            View All Clients <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-activity"></i> Recent RADIUS Requests
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_logs)): ?>
                            <p class="text-muted">No RADIUS requests logged yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>User</th>
                                            <th>Status</th>
                                            <th>NAS IP</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_logs as $log): ?>
                                            <tr>
                                                <td class="text-nowrap">
                                                    <?= date('H:i:s', strtotime($log['timestamp'])) ?>
                                                </td>
                                                <td>
                                                    <code class="small"><?= htmlspecialchars(substr($log['username'], 0, 12)) ?></code>
                                                    <?php if ($log['client_description']): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars(substr($log['client_description'], 0, 20)) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($log['response_type'] === 'Access-Accept'): ?>
                                                        <span class="badge bg-success">Accept</span>
                                                    <?php elseif ($log['response_type'] === 'Access-Reject'): ?>
                                                        <span class="badge bg-danger">Reject</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary"><?= htmlspecialchars($log['response_type']) ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-muted small">
                                                    <?= htmlspecialchars($log['nas_ip_address'] ?: 'N/A') ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <a href="radius_logs.php" class="btn btn-outline-primary btn-sm">
                            View All Logs <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-lightning"></i> Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="add_client.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Add New Client
                            </a>
                            <a href="import_unifi.php" class="btn btn-success">
                                <i class="bi bi-download"></i> Import from UniFi
                            </a>
                            <a href="view_clients.php" class="btn btn-outline-secondary">
                                <i class="bi bi-list"></i> Manage Clients
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Info -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-info-circle"></i> System Info
                        </h6>
                    </div>
                    <div class="card-body small">
                        <p class="mb-1"><strong>PHP Version:</strong> <?= PHP_VERSION ?></p>
                        <p class="mb-1"><strong>Session:</strong> <?= substr(session_id(), 0, 8) ?>...</p>
                        <p class="mb-0"><strong>Login Time:</strong> <?= date('g:i A', $_SESSION['login_time']) ?></p>
                    </div>
                </div>
            </div>

            <!-- RADIUS Stats Summary -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-graph-up"></i> Today's Summary
                        </h6>
                    </div>
                    <div class="card-body small">
                        <div class="row">
                            <div class="col-6">
                                <div class="text-center">
                                    <div class="h5 text-success mb-0"><?= $radius_stats['today_success'] ?></div>
                                    <small class="text-muted">Success</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center">
                                    <div class="h5 text-danger mb-0"><?= $radius_stats['today_failed'] ?></div>
                                    <small class="text-muted">Failed</small>
                                </div>
                            </div>
                        </div>
                        <hr class="my-2">
                        <div class="text-center">
                            <small class="text-muted">Total: <?= $radius_stats['today_total'] ?> requests</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Login Form -->
    <div class="container">
        <div class="row justify-content-center min-vh-100 align-items-center">
            <div class="col-md-4">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <i class="bi bi-router text-primary" style="font-size: 3rem;"></i>
                            <h3 class="mt-2">UniFi RADIUS Admin</h3>
                            <p class="text-muted">Please sign in to continue</p>
                        </div>

                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['error']) && $_GET['error'] === 'session_expired'): ?>
                            <div class="alert alert-warning" role="alert">
                                <i class="bi bi-clock"></i> Your session has expired. Please log in again.
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['message']) && $_GET['message'] === 'logged_out'): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="bi bi-check-circle"></i> You have been successfully logged out.
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?= htmlspecialchars($username ?? '') ?>" required autofocus>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" name="login" class="btn btn-primary">
                                    <i class="bi bi-box-arrow-in-right"></i> Sign In
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-3">
                            <small class="text-muted">
                                Default: admin / admin123
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>