<?php
/**
 * Default VLAN Configuration Page
 * Manage default VLAN assignment for unconfigured devices
 */

require_once 'auth.php';
require_once 'db.php';

// Require authentication
requireAuth();

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_config') {
            $vlan_id = (int)($_POST['vlan_id'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $enabled = isset($_POST['enabled']);
            
            if (!isValidVlan($vlan_id)) {
                $error = 'VLAN ID must be between 1 and 4094.';
            } else {
                if (setDefaultVlanConfig($vlan_id, $description, $enabled)) {
                    $success = 'Default VLAN configuration updated successfully.';
                } else {
                    $error = 'Failed to update default VLAN configuration.';
                }
            }
        } elseif ($action === 'toggle_enabled') {
            $enabled = isset($_POST['enabled']);
            if (setDefaultVlanEnabled($enabled)) {
                $success = 'Default VLAN ' . ($enabled ? 'enabled' : 'disabled') . ' successfully.';
            } else {
                $error = 'Failed to update default VLAN status.';
            }
        }
    }
}

// Get current configuration
$config = getDefaultVlanConfig();

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Default VLAN Configuration - UniFi RADIUS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php $active_page = 'default_vlan'; include 'header.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="bi bi-gear"></i> Default VLAN Configuration</h1>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-gear"></i> Default VLAN Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    <strong>About Default VLAN:</strong> When enabled, devices that don't have exact MAC address matches or vendor prefix matches will be assigned to the default VLAN. This allows unconfigured WiFi clients to connect to your network with a fallback VLAN assignment.
                                </div>

                                <form method="POST" action="default_vlan.php">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="action" value="update_config">

                                    <div class="mb-3">
                                        <label for="vlan_id" class="form-label">Default VLAN ID</label>
                                        <input type="number" 
                                               class="form-control" 
                                               id="vlan_id" 
                                               name="vlan_id" 
                                               min="1" 
                                               max="4094" 
                                               value="<?= $config ? htmlspecialchars($config['vlan_id']) : '999' ?>" 
                                               required>
                                        <div class="form-text">VLAN ID for devices not found in database (1-4094)</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" 
                                                  id="description" 
                                                  name="description" 
                                                  rows="3" 
                                                  placeholder="Optional description for this default VLAN assignment"><?= $config ? htmlspecialchars($config['description']) : '' ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   id="enabled" 
                                                   name="enabled" 
                                                   <?= $config && $config['enabled'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="enabled">
                                                Enable Default VLAN Assignment
                                            </label>
                                        </div>
                                        <div class="form-text">When disabled, devices without exact or prefix matches will be rejected</div>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle"></i> Update Configuration
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="bi bi-info-circle"></i> Current Status
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if ($config): ?>
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <td><strong>Status:</strong></td>
                                            <td>
                                                <?php if ($config['enabled']): ?>
                                                    <span class="badge bg-success">Enabled</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Disabled</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>VLAN ID:</strong></td>
                                            <td><?= htmlspecialchars($config['vlan_id']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Created:</strong></td>
                                            <td><?= date('M j, Y g:i A', strtotime($config['created_at'])) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Updated:</strong></td>
                                            <td><?= date('M j, Y g:i A', strtotime($config['updated_at'])) ?></td>
                                        </tr>
                                    </table>
                                <?php else: ?>
                                    <p class="text-muted">No default VLAN configuration found.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="bi bi-diagram-3"></i> VLAN Assignment Order
                                </h6>
                            </div>
                            <div class="card-body">
                                <ol class="list-group list-group-numbered list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between align-items-start">
                                        <div class="ms-2 me-auto">
                                            <div class="fw-bold">Exact MAC Match</div>
                                            Specific device configuration
                                        </div>
                                        <span class="badge bg-primary rounded-pill">1st</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-start">
                                        <div class="ms-2 me-auto">
                                            <div class="fw-bold">Vendor Prefix Match</div>
                                            MAC vendor prefix rules
                                        </div>
                                        <span class="badge bg-secondary rounded-pill">2nd</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-start">
                                        <div class="ms-2 me-auto">
                                            <div class="fw-bold">Default VLAN</div>
                                            Fallback for all other devices
                                        </div>
                                        <span class="badge bg-warning rounded-pill">3rd</span>
                                    </li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>