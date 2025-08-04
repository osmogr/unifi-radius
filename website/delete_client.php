<?php
/**
 * UniFi RADIUS Admin Website - Delete Client
 * Remove a client and all associated VLAN assignments
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

// Handle form submission (deletion)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $confirm = $_POST['confirm'] ?? '';
        
        if ($confirm !== 'DELETE') {
            $error = 'You must type "DELETE" to confirm deletion.';
        } else {
            // Delete the client
            if (removeVlanForMac($mac)) {
                $success = "Client $mac has been successfully deleted from the database.";
                
                // Clear client data since it's deleted
                $current_vlan = null;
                $notes = null;
            } else {
                $error = 'Failed to delete client. Please check your database connection and try again.';
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
    <title>Delete Client - UniFi RADIUS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .danger-card {
            border-left: 4px solid #dc3545;
        }
        .mac-display {
            font-family: 'Courier New', monospace;
            font-size: 1.1em;
            background: #f8f9fa;
            padding: 0.5rem;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
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
                    <li class="breadcrumb-item active">Delete Client</li>
                </ol>
            </nav>
            
            <h1 class="h3 mb-4">
                <i class="bi bi-trash text-danger"></i> Delete Client
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
                        <a href="add_client.php" class="btn btn-sm btn-success">Add New Client</a>
                    </div>
                </div>
            <?php else: ?>

                <!-- Warning Box -->
                <div class="warning-box mb-4">
                    <div class="d-flex align-items-start">
                        <i class="bi bi-exclamation-triangle-fill text-warning me-3" style="font-size: 1.5rem;"></i>
                        <div>
                            <h5 class="text-warning mb-2">Warning: Permanent Deletion</h5>
                            <p class="mb-0">
                                This action will permanently delete the client and all associated data from the database. 
                                This includes all VLAN assignments and client notes. This action cannot be undone.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Client Details -->
                <?php if ($current_vlan !== null): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-info-circle"></i> Client Details
                            </h5>
                        </div>
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
                                    <div class="col-12">
                                        <h6 class="text-muted mb-2">Description</h6>
                                        <p class="mb-0"><?= htmlspecialchars($notes['description'] ?: 'No description') ?></p>
                                    </div>
                                </div>
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

                    <!-- Delete Confirmation Form -->
                    <div class="card danger-card">
                        <div class="card-header bg-danger text-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-exclamation-triangle"></i> Confirm Deletion
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-4">
                                To confirm deletion of this client, please type <strong>DELETE</strong> in the field below and click the delete button.
                            </p>
                            
                            <form method="POST" id="deleteForm">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                
                                <div class="mb-3">
                                    <label for="confirm" class="form-label">
                                        Type "DELETE" to confirm <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="confirm" 
                                           name="confirm" 
                                           placeholder="DELETE"
                                           required
                                           autocomplete="off">
                                </div>

                                <div class="d-flex justify-content-between">
                                    <div>
                                        <a href="view_clients.php" class="btn btn-secondary">
                                            <i class="bi bi-arrow-left"></i> Cancel
                                        </a>
                                        <a href="edit_client.php?mac=<?= urlencode($mac) ?>" class="btn btn-outline-warning ms-2">
                                            <i class="bi bi-pencil"></i> Edit Instead
                                        </a>
                                    </div>
                                    <button type="submit" class="btn btn-danger" id="deleteButton" disabled>
                                        <i class="bi bi-trash"></i> Delete Client
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- What Will Be Deleted -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="bi bi-list-check"></i> What Will Be Deleted
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>FreeRADIUS Data</h6>
                                    <ul class="list-unstyled small">
                                        <li><i class="bi bi-x text-danger"></i> Tunnel-Type assignment</li>
                                        <li><i class="bi bi-x text-danger"></i> Tunnel-Medium-Type assignment</li>
                                        <li><i class="bi bi-x text-danger"></i> Tunnel-Private-Group-ID (VLAN <?= htmlspecialchars($current_vlan) ?>)</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6>Client Notes</h6>
                                    <ul class="list-unstyled small">
                                        <li><i class="bi bi-x text-danger"></i> Description: "<?= htmlspecialchars($notes['description'] ?? 'No description') ?>"</li>
                                        <li><i class="bi bi-x text-danger"></i> Creation timestamp</li>
                                        <li><i class="bi bi-x text-danger"></i> Last seen timestamp</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Enable delete button only when "DELETE" is typed
document.getElementById('confirm').addEventListener('input', function(e) {
    const deleteButton = document.getElementById('deleteButton');
    const confirmText = e.target.value.trim();
    
    if (confirmText === 'DELETE') {
        deleteButton.disabled = false;
        deleteButton.classList.remove('btn-outline-danger');
        deleteButton.classList.add('btn-danger');
    } else {
        deleteButton.disabled = true;
        deleteButton.classList.remove('btn-danger');
        deleteButton.classList.add('btn-outline-danger');
    }
});

// Add extra confirmation on form submit
document.getElementById('deleteForm').addEventListener('submit', function(e) {
    const confirmText = document.getElementById('confirm').value.trim();
    
    if (confirmText !== 'DELETE') {
        e.preventDefault();
        alert('You must type "DELETE" to confirm deletion.');
        return false;
    }
    
    if (!confirm('Are you absolutely sure you want to delete this client? This action cannot be undone.')) {
        e.preventDefault();
        return false;
    }
});
</script>
</body>
</html>