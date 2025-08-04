<?php
/**
 * UniFi RADIUS Admin Website - Add Client Functionality
 * 
 * This page provides a form-based interface for manually adding new network
 * clients with specific MAC address to VLAN assignments. It's used when you
 * need to pre-configure access for specific devices before they connect.
 * 
 * Key features:
 * - Secure form with CSRF protection
 * - MAC address format validation and normalization
 * - VLAN ID validation (1-4094 range)
 * - Duplicate client detection
 * - Optional device description for management
 * - Real-time form validation feedback
 * 
 * Use cases:
 * - Pre-configuring access for new employee devices
 * - Setting up guest device access with specific VLANs
 * - Manually adding devices that don't appear in UniFi import
 * - Creating test entries for network configuration validation
 * 
 * For beginners:
 *   This is like creating a new employee badge. You specify the person's
 *   ID (MAC address), which areas they can access (VLAN), and their name
 *   (description) for record keeping.
 * 
 * Security features:
 * - CSRF token validation to prevent cross-site request forgery
 * - Input sanitization and validation
 * - Authentication requirement (must be logged in)
 * - Database transaction safety for data consistency
 * 
 * Form validation:
 * - MAC address: Accepts various formats, normalizes to aa:bb:cc:dd:ee:ff
 * - VLAN ID: Must be numeric, range 1-4094 (IEEE 802.1Q standard)
 * - Description: Optional text field for device identification
 * 
 * @package UniFiRadius
 * @subpackage ClientManagement
 */

require_once 'auth.php';
require_once 'db.php';

// Require authentication - only logged-in users can add clients
requireAuth();

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $mac_input = trim($_POST['mac'] ?? '');
        $vlan = trim($_POST['vlan'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        // Validate inputs
        if (empty($mac_input)) {
            $error = 'MAC address is required.';
        } elseif (empty($vlan)) {
            $error = 'VLAN ID is required.';
        } elseif (!isValidVlan($vlan)) {
            $error = 'VLAN ID must be a number between 1 and 4094.';
        } else {
            // Normalize MAC address
            $mac = normalizeMac($mac_input);
            
            if (!$mac) {
                $error = 'Invalid MAC address format. Please use format like AA:BB:CC:DD:EE:FF or AABBCCDDEEFF.';
            } else {
                // Check if MAC already exists
                $existing_vlan = getVlanForMac($mac);
                if ($existing_vlan !== null) {
                    $error = "MAC address $mac is already assigned to VLAN $existing_vlan. Use the edit function to change it.";
                } else {
                    // Add the client
                    if (setVlanForMac($mac, (int)$vlan)) {
                        // Add client notes if description provided
                        if (!empty($description)) {
                            updateClientNotes($mac, $description);
                        }
                        
                        $success = "Client $mac successfully added with VLAN $vlan assignment.";
                        
                        // Clear form data
                        $mac_input = '';
                        $vlan = '';
                        $description = '';
                    } else {
                        $error = 'Failed to add client. Please check your database connection and try again.';
                    }
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Client - UniFi RADIUS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .form-card {
            border-left: 4px solid #198754;
        }
        .mac-example {
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
    </style>
</head>
<body class="bg-light">

<?php $active_page = 'add_client'; include 'header.php'; ?>

<!-- Main Content -->
<div class="container mt-4">
    <!-- Page Header -->
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="view_clients.php">Clients</a></li>
                    <li class="breadcrumb-item active">Add Client</li>
                </ol>
            </nav>
            
            <h1 class="h3 mb-4">
                <i class="bi bi-plus-circle"></i> Add New Client
            </h1>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8">
            
            <!-- Success/Error Messages -->
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
                    <div class="mt-2">
                        <a href="view_clients.php" class="btn btn-sm btn-outline-success">View All Clients</a>
                        <a href="add_client.php" class="btn btn-sm btn-success">Add Another</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Add Client Form -->
            <div class="card form-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-device-ssd"></i> Client Information
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <!-- MAC Address Field -->
                        <div class="mb-3">
                            <label for="mac" class="form-label">
                                MAC Address <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="mac" 
                                   name="mac" 
                                   value="<?= htmlspecialchars($mac_input ?? '') ?>"
                                   placeholder="aa:bb:cc:dd:ee:ff"
                                   pattern="[0-9a-fA-F]{2}[:-]?[0-9a-fA-F]{2}[:-]?[0-9a-fA-F]{2}[:-]?[0-9a-fA-F]{2}[:-]?[0-9a-fA-F]{2}[:-]?[0-9a-fA-F]{2}"
                                   required>
                            <div class="form-text">
                                Enter MAC address in any format: 
                                <span class="mac-example">aa:bb:cc:dd:ee:ff</span>, 
                                <span class="mac-example">aa-bb-cc-dd-ee-ff</span>, or 
                                <span class="mac-example">aabbccddeeff</span>
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
                                   value="<?= htmlspecialchars($vlan ?? '') ?>"
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
                                      placeholder="Device name, location, or purpose..."><?= htmlspecialchars($description ?? '') ?></textarea>
                            <div class="form-text">
                                Add a description to help identify this device later
                            </div>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="d-flex justify-content-between">
                            <a href="view_clients.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Clients
                            </a>
                            <div>
                                <button type="reset" class="btn btn-outline-secondary me-2">
                                    <i class="bi bi-x-circle"></i> Clear
                                </button>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-plus-circle"></i> Add Client
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Help Card -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-question-circle"></i> How it works
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>MAC Address Storage</h6>
                            <ul class="list-unstyled small">
                                <li><i class="bi bi-check text-success"></i> Stored as FreeRADIUS username</li>
                                <li><i class="bi bi-check text-success"></i> Normalized to lowercase with colons</li>
                                <li><i class="bi bi-check text-success"></i> Automatically validated format</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>VLAN Assignment</h6>
                            <ul class="list-unstyled small">
                                <li><i class="bi bi-check text-success"></i> Uses Tunnel-Private-Group-ID</li>
                                <li><i class="bi bi-check text-success"></i> Sets Tunnel-Type = VLAN</li>
                                <li><i class="bi bi-check text-success"></i> Sets Tunnel-Medium-Type = IEEE-802</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-format MAC address as user types
document.getElementById('mac').addEventListener('input', function(e) {
    let value = e.target.value.replace(/[^0-9a-fA-F]/g, '');
    if (value.length <= 12) {
        // Add colons every 2 characters
        value = value.match(/.{1,2}/g)?.join(':') || value;
        if (value.length > 17) {
            value = value.substring(0, 17);
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