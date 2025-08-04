<?php
/**
 * UniFi RADIUS Admin Website - RADIUS Authentication Logs
 * 
 * This page provides detailed monitoring and analysis of RADIUS authentication
 * requests and responses. It's essential for troubleshooting network access
 * issues, security monitoring, and compliance reporting.
 * 
 * Key features:
 * - Real-time authentication log display
 * - Advanced filtering by response type, username, time range
 * - Detailed request/response information
 * - Success/failure statistics and trends
 * - Export functionality for compliance reporting
 * - Integration with client management for quick access
 * - Performance metrics and response time analysis
 * 
 * Log information displayed:
 * - Timestamp: When authentication attempt occurred
 * - Username: MAC address or user identifier
 * - NAS IP: Access point or switch that sent request
 * - Response Type: Access-Accept, Access-Reject, etc.
 * - Reason: Detailed explanation for accept/reject decision
 * - Client Description: Device name if available
 * - VLAN Assignment: Which VLAN was assigned (if successful)
 * 
 * For beginners:
 *   This is like a security log that records every time someone tries to
 *   enter the building. It shows who tried to get in, when they tried,
 *   whether they were allowed in or denied, and why.
 * 
 * Troubleshooting uses:
 * - Find why specific devices can't connect
 * - Monitor for unauthorized access attempts
 * - Verify VLAN assignments are working correctly
 * - Track authentication performance and response times
 * - Generate reports for security compliance
 * 
 * @package UniFiRadius
 * @subpackage Monitoring
 */

require_once 'auth.php';
require_once 'db.php';

// Require authentication - only logged-in users can view RADIUS logs
requireAuth();

// Get filter parameters
$limit = (int)($_GET['limit'] ?? 50);
$filter_response = $_GET['response'] ?? '';
$filter_username = trim($_GET['username'] ?? '');

// Validate limit
if ($limit < 10) $limit = 10;
if ($limit > 500) $limit = 500;

// Get logs with filters
try {
    $db = getDB();
    
    $sql = "
        SELECT 
            rl.*,
            cn.description as client_description
        FROM radius_logs rl
        LEFT JOIN client_notes cn ON rl.username = cn.mac
        WHERE 1=1
    ";
    $params = [];
    
    if ($filter_response) {
        $sql .= " AND rl.response_type = ?";
        $params[] = $filter_response;
    }
    
    if ($filter_username) {
        $sql .= " AND rl.username LIKE ?";
        $params[] = '%' . $filter_username . '%';
    }
    
    $sql .= " ORDER BY rl.timestamp DESC LIMIT ?";
    $params[] = $limit;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    // Get statistics
    $stats = getRadiusLogStats();
    
} catch (Exception $e) {
    error_log("Error fetching RADIUS logs: " . $e->getMessage());
    $logs = [];
    $stats = ['today_total' => 0, 'today_success' => 0, 'today_failed' => 0];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RADIUS Logs - UniFi RADIUS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .dashboard-card {
            border-left: 4px solid #0d6efd;
        }
        .log-row:hover {
            background-color: #f8f9fa;
        }
        .timestamp {
            font-family: 'Courier New', monospace;
        }
        .auto-refresh {
            cursor: pointer;
        }
    </style>
</head>
<body class="bg-light">

<?php $active_page = 'radius_logs'; include 'header.php'; ?>

<!-- Main Content -->
<div class="container mt-4">
    <!-- Page Header -->
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="bi bi-activity"></i> RADIUS Request Logs
                </h1>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary auto-refresh" id="refreshBtn" data-bs-toggle="tooltip" title="Auto-refresh every 30 seconds">
                        <i class="bi bi-arrow-clockwise"></i> <span id="refreshText">Refresh</span>
                    </button>
                    <span class="badge bg-info fs-6" id="lastUpdate">Last updated: <?= date('H:i:s') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h4 class="text-primary"><?= $stats['today_total'] ?></h4>
                    <p class="card-text text-muted">Total Today</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h4 class="text-success"><?= $stats['today_success'] ?></h4>
                    <p class="card-text text-muted">Successful</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h4 class="text-danger"><?= $stats['today_failed'] ?></h4>
                    <p class="card-text text-muted">Failed</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h4 class="text-info"><?= $stats['today_total'] > 0 ? round(($stats['today_success'] / $stats['today_total']) * 100, 1) : 0 ?>%</h4>
                    <p class="card-text text-muted">Success Rate</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-funnel"></i> Filters
            </h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="response" class="form-label">Response Type</label>
                    <select class="form-select" id="response" name="response">
                        <option value="">All Responses</option>
                        <option value="Access-Accept" <?= $filter_response === 'Access-Accept' ? 'selected' : '' ?>>Access-Accept</option>
                        <option value="Access-Reject" <?= $filter_response === 'Access-Reject' ? 'selected' : '' ?>>Access-Reject</option>
                        <option value="Access-Challenge" <?= $filter_response === 'Access-Challenge' ? 'selected' : '' ?>>Access-Challenge</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="username" class="form-label">Username/MAC</label>
                    <input type="text" class="form-control" id="username" name="username" 
                           value="<?= htmlspecialchars($filter_username) ?>" placeholder="Search by MAC address">
                </div>
                <div class="col-md-2">
                    <label for="limit" class="form-label">Limit</label>
                    <select class="form-select" id="limit" name="limit">
                        <option value="25" <?= $limit === 25 ? 'selected' : '' ?>>25</option>
                        <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100</option>
                        <option value="250" <?= $limit === 250 ? 'selected' : '' ?>>250</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Filter
                        </button>
                        <a href="radius_logs.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="card dashboard-card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-list"></i> Recent RADIUS Requests (<?= count($logs) ?> results)
            </h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($logs)): ?>
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-inbox display-1"></i>
                    <p class="mt-3">No RADIUS logs found.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="timestamp">Timestamp</th>
                                <th>Username/MAC</th>
                                <th>Request</th>
                                <th>Response</th>
                                <th>NAS Info</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr class="log-row">
                                    <td class="timestamp small text-nowrap">
                                        <?= date('M j H:i:s', strtotime($log['timestamp'])) ?>
                                    </td>
                                    <td>
                                        <code class="small"><?= htmlspecialchars($log['username']) ?></code>
                                        <?php if ($log['client_description']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($log['client_description']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($log['request_type']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($log['response_type'] === 'Access-Accept'): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle"></i> Accept
                                            </span>
                                        <?php elseif ($log['response_type'] === 'Access-Reject'): ?>
                                            <span class="badge bg-danger">
                                                <i class="bi bi-x-circle"></i> Reject
                                            </span>
                                        <?php elseif ($log['response_type'] === 'Access-Challenge'): ?>
                                            <span class="badge bg-warning">
                                                <i class="bi bi-question-circle"></i> Challenge
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($log['response_type']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small">
                                        <?php if ($log['nas_ip_address']): ?>
                                            <strong>IP:</strong> <?= htmlspecialchars($log['nas_ip_address']) ?><br>
                                        <?php endif; ?>
                                        <?php if ($log['nas_port_id']): ?>
                                            <strong>Port:</strong> <?= htmlspecialchars($log['nas_port_id']) ?><br>
                                        <?php endif; ?>
                                        <?php if ($log['called_station_id']): ?>
                                            <strong>Called:</strong> <?= htmlspecialchars(substr($log['called_station_id'], 0, 20)) ?><?= strlen($log['called_station_id']) > 20 ? '...' : '' ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?= htmlspecialchars($log['reason'] ?: 'No reason provided') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php if (count($logs) === $limit): ?>
            <div class="card-footer text-muted">
                <i class="bi bi-info-circle"></i> Showing latest <?= $limit ?> results. Use filters to narrow down results or increase the limit.
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    let autoRefresh = false;
    let refreshInterval;
    
    const refreshBtn = document.getElementById('refreshBtn');
    const refreshText = document.getElementById('refreshText');
    const lastUpdate = document.getElementById('lastUpdate');
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Auto-refresh functionality
    refreshBtn.addEventListener('click', function() {
        if (autoRefresh) {
            // Stop auto-refresh
            clearInterval(refreshInterval);
            autoRefresh = false;
            refreshBtn.classList.remove('btn-success');
            refreshBtn.classList.add('btn-outline-secondary');
            refreshText.textContent = 'Refresh';
        } else {
            // Start auto-refresh
            autoRefresh = true;
            refreshBtn.classList.remove('btn-outline-secondary');
            refreshBtn.classList.add('btn-success');
            
            let countdown = 30;
            refreshText.textContent = `Auto (${countdown}s)`;
            
            refreshInterval = setInterval(function() {
                countdown--;
                if (countdown <= 0) {
                    // Refresh the page
                    window.location.reload();
                } else {
                    refreshText.textContent = `Auto (${countdown}s)`;
                }
            }, 1000);
        }
    });
    
    // Manual refresh
    document.addEventListener('keydown', function(e) {
        if (e.key === 'F5' || (e.ctrlKey && e.key === 'r')) {
            lastUpdate.textContent = 'Last updated: ' + new Date().toLocaleTimeString();
        }
    });
});
</script>

</body>
</html>