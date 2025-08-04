<?php
/**
 * UniFi RADIUS Admin Website - MAC Prefix Management
 * 
 * This page manages bulk VLAN assignments based on MAC address prefixes (vendor OUIs).
 * MAC prefixes represent the first 3 octets of MAC addresses, which identify the
 * device manufacturer. This allows efficient bulk assignment of VLANs to all
 * devices from specific vendors.
 * 
 * Key features:
 * - Add/edit/delete MAC prefix to VLAN mappings
 * - Vendor lookup integration for prefix identification
 * - Bulk VLAN assignment for device categories
 * - Conflict detection with exact MAC assignments
 * - Prefix validation and normalization
 * - Description fields for management documentation
 * 
 * Use cases:
 * - Assign all Apple devices to employee VLAN
 * - Put all IoT devices (specific vendors) on isolated VLAN
 * - Automatically assign guest devices to guest network
 * - Segregate security cameras by manufacturer
 * - Bulk assignment for device procurement batches
 * 
 * For beginners:
 *   This is like setting access rules by company/manufacturer. Instead of
 *   programming each device individually, you can say "all Apple devices
 *   get employee access" or "all security cameras get camera network access."
 * 
 * @package UniFiRadius
 * @subpackage PrefixManagement
 */

require_once 'auth.php';
require_once 'db.php';

// Require authentication - only logged-in users can manage MAC prefixes
requireAuth();

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add') {
            $prefix_input = trim($_POST['prefix'] ?? '');
            $vlan = trim($_POST['vlan'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            // Validate inputs
            if (empty($prefix_input)) {
                $error = 'MAC prefix is required.';
            } elseif (empty($vlan)) {
                $error = 'VLAN ID is required.';
            } elseif (!isValidVlan($vlan)) {
                $error = 'VLAN ID must be a number between 1 and 4094.';
            } else {
                // Normalize MAC prefix
                $prefix = normalizeMacPrefix($prefix_input);
                
                if (!$prefix) {
                    $error = 'Invalid MAC prefix format. Please use format like AA:BB:CC or AABBCC.';
                } else {
                    // Check if prefix already exists
                    $existing_vlan = getVlanForMacPrefix($prefix);
                    if ($existing_vlan !== null) {
                        $error = "MAC prefix $prefix is already assigned to VLAN $existing_vlan.";
                    } else {
                        // Add the prefix
                        if (setVlanForMacPrefix($prefix, (int)$vlan, $description)) {
                            $success = "MAC prefix $prefix successfully added with VLAN $vlan assignment.";
                        } else {
                            $error = 'Failed to add MAC prefix. Please check your database connection and try again.';
                        }
                    }
                }
            }
        } elseif ($action === 'delete') {
            $prefix = $_POST['prefix'] ?? '';
            if (removeVlanForMacPrefix($prefix)) {
                $success = "MAC prefix $prefix successfully deleted.";
            } else {
                $error = "Failed to delete MAC prefix $prefix.";
            }
        }
    }
}

// Get all MAC prefixes
$prefixes = getAllMacPrefixes();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MAC Prefix Management - UniFi RADIUS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .form-card {
            border-left: 4px solid #6f42c1;
        }
        .prefix-example {
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        .vlan-badge {
            font-size: 0.8em;
        }
    </style>
</head>
<body class="bg-light">

<?php $active_page = 'mac_prefixes'; include 'header.php'; ?>

<!-- Main Content -->
<div class="container mt-4">
    <!-- Page Header -->
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">MAC Prefix Management</li>
                </ol>
            </nav>
            
            <h1 class="h3 mb-4">
                <i class="bi bi-diagram-3"></i> MAC Prefix Management
            </h1>
        </div>
    </div>

    <div class="row">
        <!-- Add Prefix Form -->
        <div class="col-md-6">
            <!-- Success/Error Messages -->
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <!-- Add Prefix Form -->
            <div class="card form-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-plus-circle"></i> Add MAC Prefix
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="add">
                        
                        <!-- MAC Prefix Field -->
                        <div class="mb-3">
                            <label for="prefix" class="form-label">
                                MAC Prefix (Vendor OUI) <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="prefix" 
                                   name="prefix" 
                                   placeholder="aa:bb:cc"
                                   pattern="[0-9a-fA-F]{2}[:-]?[0-9a-fA-F]{2}[:-]?[0-9a-fA-F]{2}"
                                   maxlength="8"
                                   required>
                            <div class="form-text">
                                Enter the first 3 octets (vendor prefix): 
                                <span class="prefix-example">aa:bb:cc</span> or 
                                <span class="prefix-example">aabbcc</span>
                            </div>
                        </div>

                        <!-- VLAN ID Field -->
                        <div class="mb-3">
                            <label for="vlan" class="form-label">
                                VLAN ID <span class="text-danger">*</span>
                            </label>
                            <input type="number" 
                                   class="form-control" 
                                   id="vlan" 
                                   name="vlan" 
                                   min="1" 
                                   max="4094" 
                                   placeholder="100"
                                   required>
                            <div class="form-text">
                                Valid VLAN IDs are between 1 and 4094
                            </div>
                        </div>

                        <!-- Description Field -->
                        <div class="mb-3">
                            <label for="description" class="form-label">
                                Description <span class="text-muted">(optional)</span>
                            </label>
                            <textarea class="form-control" 
                                      id="description" 
                                      name="description" 
                                      rows="3" 
                                      placeholder="Vendor name or device type..."></textarea>
                            <div class="form-text">
                                Add a description to identify this vendor/device type
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Add MAC Prefix
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Help Card -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-question-circle"></i> How MAC Prefixes Work
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled small">
                        <li><i class="bi bi-check text-success"></i> MAC prefixes match the first 3 octets (vendor OUI)</li>
                        <li><i class="bi bi-check text-success"></i> Used when no exact MAC address match is found</li>
                        <li><i class="bi bi-check text-success"></i> Exact MAC matches always take priority</li>
                        <li><i class="bi bi-check text-success"></i> Useful for assigning all devices from a vendor to a VLAN</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Current Prefixes -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-list"></i> 
                        Current MAC Prefixes 
                        <span class="badge bg-primary ms-2"><?= count($prefixes) ?></span>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($prefixes)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox text-muted" style="font-size: 2rem;"></i>
                            <p class="text-muted mt-3">No MAC prefixes configured</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Prefix</th>
                                        <th>VLAN</th>
                                        <th>Description</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($prefixes as $prefix): ?>
                                        <tr>
                                            <td>
                                                <code class="prefix-example"><?= htmlspecialchars($prefix['prefix']) ?></code>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary vlan-badge">
                                                    VLAN <?= htmlspecialchars($prefix['vlan_id']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($prefix['description']): ?>
                                                    <small><?= htmlspecialchars($prefix['description']) ?></small>
                                                <?php else: ?>
                                                    <small class="text-muted fst-italic">No description</small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="prefix" value="<?= htmlspecialchars($prefix['prefix']) ?>">
                                                    <button type="submit" 
                                                            class="btn btn-outline-danger btn-sm" 
                                                            title="Delete"
                                                            onclick="return confirm('Are you sure you want to delete this MAC prefix?')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($prefixes)): ?>
                    <div class="card-footer text-muted">
                        <small>
                            <i class="bi bi-info-circle"></i>
                            Prefix matching applies when no exact MAC address is found.
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-format MAC prefix as user types
document.getElementById('prefix').addEventListener('input', function(e) {
    let value = e.target.value.replace(/[^0-9a-fA-F]/g, '');
    if (value.length <= 6) {
        // Add colons every 2 characters, but only up to 6 characters
        value = value.match(/.{1,2}/g)?.join(':') || value;
        if (value.length > 8) {
            value = value.substring(0, 8);
        }
        e.target.value = value;
    }
});

// Validate VLAN input
document.getElementById('vlan').addEventListener('input', function(e) {
    let value = parseInt(e.target.value);
    if (value < 1) e.target.value = 1;
    if (value > 4094) e.target.value = 4094;
});
</script>
</body>
</html>