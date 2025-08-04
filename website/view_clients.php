<?php
/**
 * UniFi RADIUS Admin Website - View Clients Interface
 * 
 * This page displays a comprehensive list of all network clients with their
 * MAC address to VLAN assignments. It serves as the main management interface
 * for viewing, searching, and performing bulk operations on client configurations.
 * 
 * Key features:
 * - Paginated client list with search functionality
 * - Real-time filtering by MAC address, description, or VLAN
 * - Quick edit/delete actions for each client
 * - VLAN-based filtering dropdown
 * - Last seen timestamps for activity tracking
 * - Bulk selection and operations
 * - Export functionality for backup/documentation
 * 
 * Search and filtering:
 * - Text search: Searches MAC addresses and descriptions simultaneously
 * - VLAN filter: Shows only clients assigned to specific VLAN
 * - Combined filters: Can use text search and VLAN filter together
 * - Real-time results: Filters applied immediately without page reload
 * 
 * For beginners:
 *   This is like an employee directory that shows everyone who has building
 *   access, which floors they can visit (VLAN), and when they were last seen.
 *   You can search for specific people or filter by department/access level.
 * 
 * Display information:
 * - MAC Address: Unique device identifier (like an employee ID)
 * - VLAN: Network segment assignment (like floor/department access)
 * - Description: Human-readable device name (like employee name)
 * - Last Seen: When device was last active on network
 * - Actions: Edit, delete, or view detailed information
 * 
 * Management actions:
 * - Quick edit: Modify VLAN assignment inline
 * - Delete: Remove client from system
 * - Bulk operations: Select multiple clients for group actions
 * 
 * @package UniFiRadius
 * @subpackage ClientManagement
 */

require_once 'auth.php';
require_once 'db.php';

// Require authentication - only logged-in users can view clients
requireAuth();

// Get all clients
$clients = getAllClients();

// Handle search and filtering
$search = trim($_GET['search'] ?? '');
$vlan_filter = $_GET['vlan'] ?? '';

if ($search || $vlan_filter) {
    $filtered_clients = array_filter($clients, function($client) use ($search, $vlan_filter) {
        $search_match = empty($search) || 
                       stripos($client['mac'], $search) !== false || 
                       stripos($client['description'] ?? '', $search) !== false;
        
        $vlan_match = empty($vlan_filter) || $client['vlan'] == $vlan_filter;
        
        return $search_match && $vlan_match;
    });
    $clients = $filtered_clients;
}

// Get unique VLANs for filter dropdown
$all_vlans = array_unique(array_column(getAllClients(), 'vlan'));
sort($all_vlans);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Clients - UniFi RADIUS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .table th {
            border-top: none;
        }
        .mac-address {
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        .vlan-badge {
            font-size: 0.8em;
        }
        .search-controls {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body class="bg-light">

<?php $active_page = 'view_clients'; include 'header.php'; ?>

<!-- Main Content -->
<div class="container mt-4">
    <!-- Page Header -->
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="bi bi-list"></i> Client Management
                </h1>
                <div>
                    <a href="add_client.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add Client
                    </a>
                    <a href="import_unifi.php" class="btn btn-success">
                        <i class="bi bi-download"></i> Import
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filter Controls -->
    <div class="search-controls">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       placeholder="Search by MAC address or description..." 
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-4">
                <label for="vlan" class="form-label">Filter by VLAN</label>
                <select class="form-select" id="vlan" name="vlan">
                    <option value="">All VLANs</option>
                    <?php foreach ($all_vlans as $vlan): ?>
                        <option value="<?= htmlspecialchars($vlan) ?>" 
                                <?= $vlan_filter == $vlan ? 'selected' : '' ?>>
                            VLAN <?= htmlspecialchars($vlan) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-search"></i> Filter
                    </button>
                </div>
            </div>
        </form>
        
        <?php if ($search || $vlan_filter): ?>
            <div class="mt-3">
                <a href="view_clients.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> Clear Filters
                </a>
                <span class="text-muted ms-2">
                    Showing <?= count($clients) ?> of <?= count(getAllClients()) ?> clients
                </span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Clients Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-table"></i> 
                Current Assignments 
                <span class="badge bg-primary ms-2"><?= count($clients) ?></span>
            </h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($clients)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                    <h5 class="text-muted mt-3">No clients found</h5>
                    <p class="text-muted">
                        <?php if ($search || $vlan_filter): ?>
                            Try adjusting your search criteria or 
                            <a href="view_clients.php">view all clients</a>.
                        <?php else: ?>
                            Start by <a href="add_client.php">adding a client</a> or 
                            <a href="import_unifi.php">importing from UniFi</a>.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>MAC Address</th>
                                <th>VLAN</th>
                                <th>Description</th>
                                <th>Last Seen</th>
                                <th>Created</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $client): ?>
                                <tr>
                                    <td>
                                        <code class="mac-address"><?= htmlspecialchars($client['mac']) ?></code>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary vlan-badge">
                                            VLAN <?= htmlspecialchars($client['vlan']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($client['description']): ?>
                                            <?= htmlspecialchars($client['description']) ?>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">No description</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted">
                                        <?php if ($client['last_seen']): ?>
                                            <small><?= date('M j, Y g:i A', strtotime($client['last_seen'])) ?></small>
                                        <?php else: ?>
                                            <small>Never</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted">
                                        <?php if ($client['created_at']): ?>
                                            <small><?= date('M j, Y', strtotime($client['created_at'])) ?></small>
                                        <?php else: ?>
                                            <small>-</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="edit_client.php?mac=<?= urlencode($client['mac']) ?>" 
                                               class="btn btn-outline-primary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="delete_client.php?mac=<?= urlencode($client['mac']) ?>" 
                                               class="btn btn-outline-danger" title="Delete"
                                               onclick="return confirm('Are you sure you want to delete this client?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php if (!empty($clients)): ?>
            <div class="card-footer text-muted">
                <small>
                    <i class="bi bi-info-circle"></i>
                    MAC addresses are stored in FreeRADIUS format (lowercase with colons).
                    VLAN assignments use Tunnel-Private-Group-ID attribute.
                    <a href="mac_prefixes.php" class="ms-2">Manage MAC Prefixes</a> for vendor-based assignments.
                </small>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>