<?php
/**
 * Admin Users Management Page
 * Manage administrator accounts
 */

require_once 'auth.php';

// Require authentication
requireAuth();

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add':
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                if (empty($username) || empty($password)) {
                    $error = 'Username and password are required';
                } elseif ($password !== $confirm_password) {
                    $error = 'Passwords do not match';
                } elseif (strlen($password) < 6) {
                    $error = 'Password must be at least 6 characters long';
                } else {
                    if (addAdminUser($username, $password)) {
                        $message = "Admin user '$username' added successfully";
                    } else {
                        $error = "Failed to add admin user. Username may already exist.";
                    }
                }
                break;
                
            case 'update_password':
                $username = $_POST['username'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_new_password = $_POST['confirm_new_password'] ?? '';
                
                if (empty($new_password)) {
                    $error = 'New password is required';
                } elseif ($new_password !== $confirm_new_password) {
                    $error = 'Passwords do not match';
                } elseif (strlen($new_password) < 6) {
                    $error = 'Password must be at least 6 characters long';
                } else {
                    if (updateAdminPassword($username, $new_password)) {
                        $message = "Password updated successfully for '$username'";
                    } else {
                        $error = "Failed to update password for '$username'";
                    }
                }
                break;
                
            case 'delete':
                $username = $_POST['username'] ?? '';
                if ($username === getCurrentUsername()) {
                    $error = 'You cannot delete your own account';
                } elseif (deleteAdminUser($username)) {
                    $message = "Admin user '$username' deleted successfully";
                } else {
                    $error = "Failed to delete admin user '$username'. Default admin cannot be deleted.";
                }
                break;
        }
    }
}

// Get all admin users
$admin_users = getAllAdminUsers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Users - UniFi RADIUS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .dashboard-card {
            border-left: 4px solid #0d6efd;
        }
    </style>
</head>
<body class="bg-light">

<?php $active_page = 'admin_users'; include 'header.php'; ?>

<!-- Main Content -->
<div class="container mt-4">
    <!-- Page Header -->
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">
                <i class="bi bi-people"></i> Admin Users Management
            </h1>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Add New Admin User -->
        <div class="col-md-6">
            <div class="card dashboard-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-person-plus"></i> Add New Admin User
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required minlength="6">
                            <div class="form-text">Minimum 6 characters</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Add Admin User
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Existing Admin Users -->
        <div class="col-md-6">
            <div class="card dashboard-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-people-fill"></i> Existing Admin Users
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($admin_users)): ?>
                        <p class="text-muted">No admin users found in database.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($admin_users as $user): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($user['username']) ?></strong>
                                                <?php if ($user['username'] === getCurrentUsername()): ?>
                                                    <small class="text-muted">(You)</small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-muted">
                                                <?= date('M j, Y', strtotime($user['created_at'])) ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <button type="button" class="btn btn-outline-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#passwordModal" 
                                                            data-username="<?= htmlspecialchars($user['username']) ?>">
                                                        <i class="bi bi-key"></i>
                                                    </button>
                                                    <?php if ($user['username'] !== getCurrentUsername() && $user['username'] !== 'admin'): ?>
                                                        <button type="button" class="btn btn-outline-danger" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#deleteModal" 
                                                                data-username="<?= htmlspecialchars($user['username']) ?>">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Password Update Modal -->
<div class="modal fade" id="passwordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_password">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="username" id="modal-username">
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_new_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Admin User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="username" id="delete-username">
                    
                    <p>Are you sure you want to delete admin user <strong id="delete-username-display"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Handle modal data passing
document.addEventListener('DOMContentLoaded', function() {
    // Password modal
    var passwordModal = document.getElementById('passwordModal');
    passwordModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var username = button.getAttribute('data-username');
        var modalUsername = passwordModal.querySelector('#modal-username');
        modalUsername.value = username;
        
        var modalTitle = passwordModal.querySelector('.modal-title');
        modalTitle.textContent = 'Update Password for ' + username;
    });
    
    // Delete modal
    var deleteModal = document.getElementById('deleteModal');
    deleteModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var username = button.getAttribute('data-username');
        var modalUsername = deleteModal.querySelector('#delete-username');
        var modalUsernameDisplay = deleteModal.querySelector('#delete-username-display');
        modalUsername.value = username;
        modalUsernameDisplay.textContent = username;
    });
});
</script>

</body>
</html>