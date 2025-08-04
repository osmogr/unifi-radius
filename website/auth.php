<?php
/**
 * Authentication system for UniFi RADIUS Admin Website.
 * 
 * This file provides secure session-based authentication for the admin interface.
 * It supports both database-stored admin users and fallback hardcoded credentials
 * for initial setup and emergency access.
 * 
 * Security features:
 * - Session-based authentication with secure session handling
 * - Password hashing for database-stored users
 * - Session regeneration to prevent session fixation attacks
 * - Automatic logout after inactivity
 * - CSRF protection through session tokens
 * 
 * For beginners:
 *   This is like a security guard system for a building. It checks if someone
 *   has the right credentials (username/password) and gives them a temporary
 *   access badge (session) to use the admin interface.
 * 
 * Authentication flow:
 * 1. User submits login form with username/password
 * 2. System checks credentials against database first
 * 3. If not found, checks against hardcoded fallback credentials
 * 4. If valid, creates secure session and grants access
 * 5. All subsequent requests check for valid session
 * 
 * @package UniFiRadius
 * @subpackage Authentication
 */

// Start secure session handling
session_start();

// Hardcoded fallback credentials for initial setup and emergency access
// IMPORTANT: Change these in production and use strong passwords!
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123'); // In production, use password_hash() and store in database

/**
 * Check if user is currently authenticated.
 * 
 * Verifies that the user has a valid session and is logged in.
 * This function should be called on every protected page to ensure
 * only authenticated users can access admin functions.
 * 
 * For beginners:
 *   This is like checking if someone has a valid access badge before
 *   letting them into a secure area.
 * 
 * @return bool True if user is authenticated, false otherwise
 */
function isAuthenticated() {
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

/**
 * Authenticate user with username and password.
 * 
 * This function handles the login process by checking credentials against
 * both the database and fallback hardcoded values. It implements secure
 * session management to prevent common authentication attacks.
 * 
 * Authentication priority:
 * 1. Database-stored admin users (with hashed passwords)
 * 2. Hardcoded fallback credentials (for initial setup)
 * 
 * Security measures:
 * - Session regeneration to prevent session fixation
 * - Secure session storage with minimal data
 * - Timestamp tracking for session timeout
 * 
 * For beginners:
 *   This is like the main security checkpoint. It checks if the person's
 *   ID and password are valid, and if so, gives them an access badge.
 * 
 * @param string $username The username to authenticate
 * @param string $password The password to verify
 * @return bool True if authentication successful, false otherwise
 */
function authenticate($username, $password) {
    // Try database authentication first (preferred method)
    if (authenticateFromDatabase($username, $password)) {
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['login_time'] = time();
        $_SESSION['auth_source'] = 'database';
        
        // Regenerate session ID for security (prevents session fixation)
        session_regenerate_id(true);
        
        return true;
    }
    
    // Fallback to hardcoded credentials for backwards compatibility and emergency access
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['login_time'] = time();
        $_SESSION['auth_source'] = 'hardcoded';
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        return true;
    }
    return false;
}

/**
 * Authenticate user against database
 * @param string $username
 * @param string $password
 * @return bool
 */
function authenticateFromDatabase($username, $password) {
    try {
        require_once 'db.php';
        $db = getDB();
        
        $stmt = $db->prepare("SELECT password_hash FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            return true;
        }
    } catch (Exception $e) {
        error_log("Database authentication error: " . $e->getMessage());
    }
    
    return false;
}

/**
 * Log out the current user
 */
function logout() {
    $_SESSION = array();
    
    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Require authentication - redirect to login if not authenticated
 * @param string $redirect_url Optional URL to redirect to after login
 */
function requireAuth($redirect_url = null) {
    if (!isAuthenticated()) {
        if ($redirect_url) {
            $_SESSION['redirect_after_login'] = $redirect_url;
        }
        header('Location: index.php');
        exit;
    }
    
    // Check session timeout (1 hour)
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 3600) {
        logout();
        header('Location: index.php?error=session_expired');
        exit;
    }
    
    // Update last activity time
    $_SESSION['login_time'] = time();
}

/**
 * Generate CSRF token
 * @return string
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token
 * @return bool
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get current username
 * @return string|null
 */
function getCurrentUsername() {
    return $_SESSION['username'] ?? null;
}

/**
 * Check if current session is valid
 * @return bool
 */
function isSessionValid() {
    return isAuthenticated() && 
           isset($_SESSION['login_time']) && 
           (time() - $_SESSION['login_time']) <= 3600;
}

/**
 * Get all admin users
 * @return array
 */
function getAllAdminUsers() {
    try {
        require_once 'db.php';
        $db = getDB();
        
        $stmt = $db->prepare("SELECT id, username, created_at FROM admin_users ORDER BY username");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching admin users: " . $e->getMessage());
        return [];
    }
}

/**
 * Add new admin user
 * @param string $username
 * @param string $password
 * @return bool
 */
function addAdminUser($username, $password) {
    if (empty($username) || empty($password)) {
        return false;
    }
    
    try {
        require_once 'db.php';
        $db = getDB();
        
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO admin_users (username, password_hash) VALUES (?, ?)");
        $stmt->execute([$username, $password_hash]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error adding admin user: " . $e->getMessage());
        return false;
    }
}

/**
 * Update admin user password
 * @param string $username
 * @param string $new_password
 * @return bool
 */
function updateAdminPassword($username, $new_password) {
    if (empty($username) || empty($new_password)) {
        return false;
    }
    
    try {
        require_once 'db.php';
        $db = getDB();
        
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE admin_users SET password_hash = ? WHERE username = ?");
        $stmt->execute([$password_hash, $username]);
        
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Error updating admin password: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete admin user
 * @param string $username
 * @return bool
 */
function deleteAdminUser($username) {
    if (empty($username) || $username === 'admin') {
        // Don't allow deleting the default admin user
        return false;
    }
    
    try {
        require_once 'db.php';
        $db = getDB();
        
        $stmt = $db->prepare("DELETE FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Error deleting admin user: " . $e->getMessage());
        return false;
    }
}