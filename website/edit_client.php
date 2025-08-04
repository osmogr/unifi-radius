<?php
/**
 * UniFi RADIUS Admin Website - Edit Client
 * Update VLAN assignment and description for an existing client
 */

require_once 'auth.php';
require_once 'db.php';

// Require authentication
requireAuth();

$error = '';
$success = '';
$client = null;

// Get MAC address from URL
$mac = $_GET['mac'] ?? '';

if (empty($mac) || !isValidMac($mac)) {
    header('Location: view_clients.php?error=invalid_mac');
    exit;
}

// Get current client data
$current_vlan = getVlanForMac($mac);
if ($current_vlan === null) {
    header('Location: view_clients.php?error=client_not_found');
    exit;
}

// Get client notes
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT description, last_seen, created_at FROM client_notes WHERE mac = ?");
    $stmt->execute([$mac]);
    $notes = $stmt->fetch();
} catch (Exception $e) {
    error_log("Error fetching client notes: " . $e->getMessage());
    $notes = null;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $new_vlan = trim($_POST['vlan'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        // Validate inputs
        if (empty($new_vlan)) {
            $error = 'VLAN ID is required.';
        } elseif (!isValidVlan($new_vlan)) {
            $error = 'VLAN ID must be a number between 1 and 4094.';
        } else {
            $new_vlan = (int)$new_vlan;
            
            // Update VLAN assignment
            if (setVlanForMac($mac, $new_vlan)) {
                // Update client notes
                updateClientNotes($mac, $description);
                
                $success = "Client $mac successfully updated with VLAN $new_vlan.";
                $current_vlan = $new_vlan;
                
                // Refresh notes data
                try {
                    $stmt = $db->prepare("SELECT description, last_seen, created_at FROM client_notes WHERE mac = ?");
                    $stmt->execute([$mac]);
                    $notes = $stmt->fetch();
                } catch (Exception $e) {
                    error_log("Error refreshing client notes: " . $e->getMessage());
                }
            } else {
                $error = 'Failed to update client. Please check your database connection and try again.';
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
    <title>Edit Client - UniFi RADIUS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .form-card {
            border-left: 4px solid #ffc107;
        }
        .mac-display {
            font-family: 'Courier New', monospace;
            font-size: 1.1em;
            background: #f8f9fa;
            padding: 0.5rem;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        .info-card {
            background: #f8f9fa;
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
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="view_clients.php">Clients</a></li>
                    <li class="breadcrumb-item active">Edit Client</li>
                </ol>
            </nav>
            
            <h1 class="h3 mb-4">
                <i class="bi bi-pencil"></i> Edit Client
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
                        <a href="view_clients.php" class="btn btn-sm btn-outline-success">Back to Clients</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Client Info Card -->
            <div class="card info-card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">MAC Address</h6>
                            <div class="mac-display"><?= htmlspecialchars($mac) ?></div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Current VLAN</h6>
                            <span class="badge bg-primary fs-6">VLAN <?= htmlspecialchars($current_vlan) ?></span>
                        </div>
                    </div>
                    
                    <?php if ($notes): ?>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2">Created</h6>
                                <small class="text-muted">
                                    <?= $notes['created_at'] ? date('M j, Y g:i A', strtotime($notes['created_at'])) : 'Unknown' ?>
                                </small>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2">Last Seen</h6>
                                <small class="text-muted">
                                    <?= $notes['last_seen'] ? date('M j, Y g:i A', strtotime($notes['last_seen'])) : 'Never' ?>
                                </small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Edit Form -->
            <div class="card form-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-gear"></i> Update Client Configuration
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <!-- VLAN ID Field -->
                        <div class="mb-3">
                            <label for="vlan" class="form-label">
                                VLAN ID <span class="text-danger">*</span>
                            </label>
                            <input type="number" 
                                   class="form-control" 
                                   id="vlan" 
                                   name="vlan" 
                                   value="<?= htmlspecialchars($current_vlan) ?>"
                                   min="1" 
                                   max="4094" 
                                   required>
                            <div class="form-text">
                                Valid VLAN IDs are between 1 and 4094. Current assignment: VLAN <?= htmlspecialchars($current_vlan) ?>
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
                                      placeholder="Device name, location, or purpose..."><?= htmlspecialchars($notes['description'] ?? '') ?></textarea>
                            <div class="form-text">
                                Update the description to help identify this device
                            </div>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="d-flex justify-content-between">
                            <a href="view_clients.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Clients
                            </a>
                            <div>
                                <a href="delete_client.php?mac=<?= urlencode($mac) ?>" 
                                   class="btn btn-outline-danger me-2"
                                   onclick="return confirm('Are you sure you want to delete this client?')">
                                    <i class="bi bi-trash"></i> Delete Client
                                </a>
                                <button type="submit" class="btn btn-warning">
                                    <i class="bi bi-check-circle"></i> Update Client
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Technical Details Card -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-gear-wide-connected"></i> Technical Details
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>FreeRADIUS Attributes</h6>
                            <ul class="list-unstyled small">
                                <li><strong>Username:</strong> <code><?= htmlspecialchars($mac) ?></code></li>
                                <li><strong>Tunnel-Type:</strong> <code>VLAN</code></li>
                                <li><strong>Tunnel-Medium-Type:</strong> <code>IEEE-802</code></li>
                                <li><strong>Tunnel-Private-Group-ID:</strong> <code><?= htmlspecialchars($current_vlan) ?></code></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Database Tables</h6>
                            <ul class="list-unstyled small">
                                <li><strong>radreply:</strong> VLAN assignment attributes</li>
                                <li><strong>client_notes:</strong> Description and timestamps</li>
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
// Validate VLAN input
document.getElementById('vlan').addEventListener('input', function(e) {
    let value = parseInt(e.target.value);
    if (value < 1) e.target.value = 1;
    if (value > 4094) e.target.value = 4094;
});

// Highlight changes
document.getElementById('vlan').addEventListener('change', function(e) {
    const currentVlan = <?= $current_vlan ?>;
    const newVlan = parseInt(e.target.value);
    
    if (newVlan !== currentVlan) {
        e.target.classList.add('border-warning');
    } else {
        e.target.classList.remove('border-warning');
    }
});
</script>
</body>
</html>