<?php
/**
 * Database connection for UniFi RADIUS Admin Website
 * Uses PDO for secure database interactions
 */

// Database configuration
// Support both Docker container and local environments
define('DB_HOST', $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'radius');
define('DB_USER', $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'radius_user');
define('DB_PASS', $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: 'radius_password');
define('DB_CHARSET', 'utf8mb4');

class Database {
    /**
     * Singleton Database class for secure and efficient database connections.
     * 
     * This class implements the Singleton design pattern to ensure only one
     * database connection instance exists throughout the application. This
     * prevents connection leaks and improves performance.
     * 
     * Key features:
     * - Thread-safe singleton implementation
     * - PDO with prepared statements for security
     * - UTF-8 support for international characters
     * - Error handling with detailed logging
     * - Connection pooling for efficiency
     * 
     * For beginners:
     *   Think of this class like a phone system for a company. Instead of
     *   everyone having their own phone line to call customers, there's one
     *   central system that everyone shares. This is more efficient and
     *   prevents problems.
     * 
     * Security features:
     * - Prepared statements prevent SQL injection attacks
     * - Connection reuse prevents resource exhaustion
     * - Error logging for troubleshooting without exposing sensitive data
     */
    private static $instance = null;
    private $pdo;
    
    /**
     * Private constructor to prevent direct instantiation.
     * 
     * This constructor establishes the database connection using PDO with
     * security-focused options. It's private to enforce the singleton pattern.
     * 
     * Connection features:
     * - Exception mode for proper error handling
     * - Associative array fetch mode for easy data access
     * - Disabled emulated prepares for better security
     * - UTF-8 character set for international support
     * 
     * For beginners:
     *   This is like setting up the phone system with all the right settings
     *   for security and reliability before anyone can use it.
     */
    private function __construct() {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,    // Throw exceptions on errors
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Return associative arrays
            PDO::ATTR_EMULATE_PREPARES => false,            // Use real prepared statements
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET // Set character encoding
        ];
        
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please check your configuration.");
        }
    }
    
    /**
     * Get the single instance of the Database class.
     * 
     * This method implements the singleton pattern by returning the same
     * instance every time it's called. If no instance exists, it creates one.
     * 
     * For beginners:
     *   This is like asking for "the company phone system" - there's only
     *   one, and everyone gets access to the same one.
     * 
     * @return Database The singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get the PDO database connection object.
     * 
     * This method provides access to the underlying PDO connection for
     * executing database queries. The connection is configured for security
     * and performance.
     * 
     * @return PDO The configured database connection
     */
    public function getConnection() {
        return $this->pdo;
    }
    
    /**
     * Prevent cloning of the singleton instance.
     * 
     * Cloning would create a second instance, breaking the singleton pattern.
     * This method prevents that by being private and empty.
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization of the singleton instance.
     * 
     * Unserialization could create a second instance, breaking the singleton
     * pattern. This method prevents that.
     */
    public function __wakeup() {}
}

/**
 * Get database connection instance.
 * 
 * This function provides a simple way to get the database connection
 * throughout the application. It uses the singleton pattern to ensure
 * efficient connection reuse.
 * 
 * For beginners:
 *   This is like asking for "the company database" - there's only one,
 *   and this function gives you access to it.
 * 
 * @return PDO The database connection object
 */
function getDB() {
    return Database::getInstance()->getConnection();
}

/**
 * Validate MAC address format.
 * 
 * Checks if a MAC address is in the correct format: xx:xx:xx:xx:xx:xx
 * where each x is a hexadecimal digit (0-9, a-f).
 * 
 * For beginners:
 *   A MAC address is like a unique serial number for network devices.
 *   This function checks if it's written in the correct format.
 * 
 * @param string $mac The MAC address to validate
 * @return bool True if valid format, false otherwise
 * 
 * @example
 *   isValidMac("aa:bb:cc:dd:ee:ff") returns true
 *   isValidMac("invalid-mac") returns false
 */
function isValidMac($mac) {
    return preg_match('/^([0-9a-f]{2}:){5}[0-9a-f]{2}$/i', $mac);
}

/**
 * Normalize MAC address to lowercase with colons.
 * 
 * Converts various MAC address formats to a standard format for consistent
 * database storage. Handles formats with/without separators and different cases.
 * 
 * Supported input formats:
 * - AA:BB:CC:DD:EE:FF (uppercase with colons)
 * - aa-bb-cc-dd-ee-ff (lowercase with dashes)
 * - aabbccddeeff (no separators)
 * 
 * Output format: aa:bb:cc:dd:ee:ff (lowercase with colons)
 * 
 * For beginners:
 *   This is like standardizing phone number formats. Whether someone writes
 *   (555) 123-4567 or 555-123-4567, we convert them all to the same format.
 * 
 * @param string $mac MAC address in any supported format
 * @return string|false Normalized MAC address, or false if invalid
 * 
 * @example
 *   normalizeMac("AA:BB:CC:DD:EE:FF") returns "aa:bb:cc:dd:ee:ff"
 *   normalizeMac("invalid") returns false
 */
function normalizeMac($mac) {
    // Remove any non-alphanumeric characters (colons, dashes, spaces)
    $mac = preg_replace('/[^0-9a-f]/i', '', $mac);
    
    // Check if we have exactly 12 hexadecimal characters
    if (strlen($mac) !== 12) {
        return false;
    }
    
    // Convert to lowercase and add colons every 2 characters
    $mac = strtolower($mac);
    return substr($mac, 0, 2) . ':' . 
           substr($mac, 2, 2) . ':' . 
           substr($mac, 4, 2) . ':' . 
           substr($mac, 6, 2) . ':' . 
           substr($mac, 8, 2) . ':' . 
           substr($mac, 10, 2);
}

/**
 * Validate VLAN ID range.
 * 
 * Checks if a VLAN ID is within the valid range according to IEEE 802.1Q
 * standard. Valid VLANs are 1-4094 (0 and 4095 are reserved).
 * 
 * For beginners:
 *   VLANs are like different floors in a building. Floor numbers must be
 *   in a valid range - you can't have floor 0 or floor 9999.
 * 
 * @param int $vlan The VLAN ID to validate
 * @return bool True if valid VLAN ID, false otherwise
 * 
 * @example
 *   isValidVlan(100) returns true
 *   isValidVlan(5000) returns false
 */
function isValidVlan($vlan) {
    return is_numeric($vlan) && $vlan >= 1 && $vlan <= 4094;
}

/**
 * Get VLAN assignment for a specific MAC address.
 * 
 * Looks up the VLAN assignment for a device based on its MAC address.
 * This is used for exact device matching in the RADIUS authentication process.
 * 
 * For beginners:
 *   This is like looking up someone's access card to see which floors
 *   they're allowed to visit in a building.
 * 
 * @param string $mac MAC address to look up (should be normalized format)
 * @return int|null VLAN ID if found, null if no assignment exists
 * 
 * @example
 *   getVlanForMac("aa:bb:cc:dd:ee:ff") might return 100
 */
function getVlanForMac($mac) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT value 
        FROM radreply 
        WHERE username = ? AND attribute = 'Tunnel-Private-Group-ID'
    ");
    $stmt->execute([$mac]);
    $result = $stmt->fetch();
    
    return $result ? (int)$result['value'] : null;
}

/**
 * Set VLAN assignment for a MAC address.
 * 
 * Creates or updates the VLAN assignment for a device. This function handles
 * all the RADIUS attributes needed for proper VLAN assignment in network equipment.
 * 
 * The function uses database transactions to ensure data consistency - either
 * all changes succeed or none are applied.
 * 
 * For beginners:
 *   This is like programming an access card to allow someone to visit
 *   specific floors in a building. We set up all the necessary permissions
 *   at once to avoid partial configurations.
 * 
 * @param string $mac MAC address (will be validated and normalized)
 * @param int $vlan VLAN ID to assign (1-4094)
 * @return bool True if successful, false if validation fails or database error
 * 
 * @example
 *   setVlanForMac("aa:bb:cc:dd:ee:ff", 100) assigns device to VLAN 100
 */
function setVlanForMac($mac, $vlan) {
    if (!isValidMac($mac) || !isValidVlan($vlan)) {
        return false;
    }
    
    $db = getDB();
    
    try {
        $db->beginTransaction();
        
        // Delete existing VLAN assignments to avoid conflicts
        $stmt = $db->prepare("
            DELETE FROM radreply 
            WHERE username = ? AND attribute IN ('Tunnel-Type', 'Tunnel-Medium-Type', 'Tunnel-Private-Group-ID')
        ");
        $stmt->execute([$mac]);
        
        // Insert new VLAN assignment with all required RADIUS attributes
        $stmt = $db->prepare("
            INSERT INTO radreply (username, attribute, op, value) VALUES 
            (?, 'Tunnel-Type', ':=', 'VLAN'),
            (?, 'Tunnel-Medium-Type', ':=', 'IEEE-802'),
            (?, 'Tunnel-Private-Group-ID', ':=', ?)
        ");
        $stmt->execute([$mac, $mac, $mac, $vlan]);
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error setting VLAN for MAC $mac: " . $e->getMessage());
        return false;
    }
}

/**
 * Remove VLAN assignment for a MAC address
 * @param string $mac
 * @return bool
 */
function removeVlanForMac($mac) {
    if (!isValidMac($mac)) {
        return false;
    }
    
    $db = getDB();
    
    try {
        $db->beginTransaction();
        
        // Delete VLAN assignments
        $stmt = $db->prepare("
            DELETE FROM radreply 
            WHERE username = ? AND attribute IN ('Tunnel-Type', 'Tunnel-Medium-Type', 'Tunnel-Private-Group-ID')
        ");
        $stmt->execute([$mac]);
        
        // Delete client notes
        $stmt = $db->prepare("DELETE FROM client_notes WHERE mac = ?");
        $stmt->execute([$mac]);
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error removing VLAN for MAC $mac: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all clients with their VLAN assignments and notes
 * @return array
 */
function getAllClients() {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT DISTINCT 
            r.username as mac,
            r.value as vlan,
            cn.description,
            cn.last_seen,
            cn.created_at
        FROM radreply r
        LEFT JOIN client_notes cn ON r.username = cn.mac
        WHERE r.attribute = 'Tunnel-Private-Group-ID'
        ORDER BY r.username
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Update or insert client notes
 * @param string $mac
 * @param string $description
 * @return bool
 */
function updateClientNotes($mac, $description) {
    if (!isValidMac($mac)) {
        return false;
    }
    
    $db = getDB();
    
    try {
        $stmt = $db->prepare("
            INSERT INTO client_notes (mac, description) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE 
            description = VALUES(description),
            last_seen = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$mac, $description]);
        return true;
    } catch (Exception $e) {
        error_log("Error updating client notes for MAC $mac: " . $e->getMessage());
        return false;
    }
}

/**
 * Log RADIUS request
 * @param array $logData
 * @return bool
 */
function logRadiusRequest($logData) {
    $db = getDB();
    
    try {
        $stmt = $db->prepare("
            INSERT INTO radius_logs (
                username, nas_ip_address, nas_port_id, called_station_id, 
                calling_station_id, framed_ip_address, request_type, 
                response_type, reason
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $logData['username'] ?? '',
            $logData['nas_ip_address'] ?? '',
            $logData['nas_port_id'] ?? '',
            $logData['called_station_id'] ?? '',
            $logData['calling_station_id'] ?? '',
            $logData['framed_ip_address'] ?? '',
            $logData['request_type'] ?? 'Access-Request',
            $logData['response_type'] ?? 'Access-Reject',
            $logData['reason'] ?? ''
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error logging RADIUS request: " . $e->getMessage());
        return false;
    }
}

/**
 * Get recent RADIUS logs
 * @param int $limit
 * @return array
 */
function getRecentRadiusLogs($limit = 50) {
    $db = getDB();
    
    try {
        $stmt = $db->prepare("
            SELECT 
                rl.*,
                cn.description as client_description
            FROM radius_logs rl
            LEFT JOIN client_notes cn ON rl.username = cn.mac
            ORDER BY rl.timestamp DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching RADIUS logs: " . $e->getMessage());
        return [];
    }
}

/**
 * Get RADIUS logs statistics
 * @return array
 */
function getRadiusLogStats() {
    $db = getDB();
    
    try {
        // Total requests today
        $stmt = $db->prepare("
            SELECT COUNT(*) as total 
            FROM radius_logs 
            WHERE DATE(timestamp) = CURDATE()
        ");
        $stmt->execute();
        $today_total = $stmt->fetch()['total'] ?? 0;
        
        // Successful requests today
        $stmt = $db->prepare("
            SELECT COUNT(*) as total 
            FROM radius_logs 
            WHERE DATE(timestamp) = CURDATE() 
            AND response_type = 'Access-Accept'
        ");
        $stmt->execute();
        $today_success = $stmt->fetch()['total'] ?? 0;
        
        // Failed requests today
        $stmt = $db->prepare("
            SELECT COUNT(*) as total 
            FROM radius_logs 
            WHERE DATE(timestamp) = CURDATE() 
            AND response_type = 'Access-Reject'
        ");
        $stmt->execute();
        $today_failed = $stmt->fetch()['total'] ?? 0;
        
        return [
            'today_total' => $today_total,
            'today_success' => $today_success,
            'today_failed' => $today_failed
        ];
    } catch (Exception $e) {
        error_log("Error fetching RADIUS log stats: " . $e->getMessage());
        return [
            'today_total' => 0,
            'today_success' => 0,
            'today_failed' => 0
        ];
    }
}

// ===================================================================
// MAC PREFIX MANAGEMENT FUNCTIONS
// ===================================================================

/**
 * Validate MAC prefix format (first 3 octets)
 * @param string $prefix
 * @return bool
 */
function isValidMacPrefix($prefix) {
    return preg_match('/^([0-9a-f]{2}:){2}[0-9a-f]{2}$/i', $prefix);
}

/**
 * Normalize MAC prefix to lowercase with colons
 * @param string $prefix
 * @return string|false
 */
function normalizeMacPrefix($prefix) {
    // Remove any non-alphanumeric characters
    $prefix = preg_replace('/[^0-9a-f]/i', '', $prefix);
    
    // Check if we have exactly 6 characters (3 octets)
    if (strlen($prefix) !== 6) {
        return false;
    }
    
    // Convert to lowercase and add colons
    $prefix = strtolower($prefix);
    return substr($prefix, 0, 2) . ':' . 
           substr($prefix, 2, 2) . ':' . 
           substr($prefix, 4, 2);
}

/**
 * Get VLAN assignment for a MAC prefix
 * @param string $prefix
 * @return int|null
 */
function getVlanForMacPrefix($prefix) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT vlan_id 
        FROM mac_prefixes 
        WHERE prefix = ?
    ");
    $stmt->execute([$prefix]);
    $result = $stmt->fetch();
    
    return $result ? (int)$result['vlan_id'] : null;
}

/**
 * Set VLAN assignment for a MAC prefix
 * @param string $prefix
 * @param int $vlan
 * @param string $description
 * @return bool
 */
function setVlanForMacPrefix($prefix, $vlan, $description = '') {
    if (!isValidMacPrefix($prefix) || !isValidVlan($vlan)) {
        return false;
    }
    
    $db = getDB();
    
    try {
        $stmt = $db->prepare("
            INSERT INTO mac_prefixes (prefix, vlan_id, description) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            vlan_id = VALUES(vlan_id),
            description = VALUES(description),
            updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$prefix, $vlan, $description]);
        return true;
    } catch (Exception $e) {
        error_log("Error setting VLAN for MAC prefix $prefix: " . $e->getMessage());
        return false;
    }
}

/**
 * Remove VLAN assignment for a MAC prefix
 * @param string $prefix
 * @return bool
 */
function removeVlanForMacPrefix($prefix) {
    if (!isValidMacPrefix($prefix)) {
        return false;
    }
    
    $db = getDB();
    
    try {
        $stmt = $db->prepare("DELETE FROM mac_prefixes WHERE prefix = ?");
        $stmt->execute([$prefix]);
        return true;
    } catch (Exception $e) {
        error_log("Error removing VLAN for MAC prefix $prefix: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all MAC prefix assignments
 * @return array
 */
function getAllMacPrefixes() {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT 
            prefix,
            vlan_id,
            description,
            created_at,
            updated_at
        FROM mac_prefixes
        ORDER BY prefix
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Find VLAN for MAC address using prefix matching
 * This function checks for vendor prefix match when exact MAC match fails
 * @param string $mac Full MAC address
 * @return int|null
 */
function getVlanForMacByPrefix($mac) {
    if (!isValidMac($mac)) {
        return null;
    }
    
    // Extract the first 3 octets (vendor prefix)
    $prefix = substr($mac, 0, 8); // aa:bb:cc
    
    return getVlanForMacPrefix($prefix);
}

/**
 * Enhanced VLAN lookup that checks exact MAC first, then prefix
 * @param string $mac
 * @return array|null Returns array with vlan, match_type, and matched_value
 */
function getVlanForMacEnhanced($mac) {
    // First try exact MAC match
    $exact_vlan = getVlanForMac($mac);
    if ($exact_vlan !== null) {
        return [
            'vlan' => $exact_vlan,
            'match_type' => 'exact',
            'matched_value' => $mac
        ];
    }
    
    // Then try prefix match
    $prefix_vlan = getVlanForMacByPrefix($mac);
    if ($prefix_vlan !== null) {
        $prefix = substr($mac, 0, 8);
        return [
            'vlan' => $prefix_vlan,
            'match_type' => 'prefix',
            'matched_value' => $prefix
        ];
    }
    
    return null;
}

// ===================================================================
// DEFAULT VLAN CONFIGURATION FUNCTIONS
// ===================================================================

/**
 * Get the current default VLAN configuration
 * @return array|null
 */
function getDefaultVlanConfig() {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT 
            id,
            vlan_id,
            description,
            enabled,
            created_at,
            updated_at
        FROM default_vlan_config 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute();
    return $stmt->fetch();
}

/**
 * Update or create default VLAN configuration
 * @param int $vlan
 * @param string $description
 * @param bool $enabled
 * @return bool
 */
function setDefaultVlanConfig($vlan, $description = '', $enabled = true) {
    if (!isValidVlan($vlan)) {
        return false;
    }
    
    $db = getDB();
    
    try {
        // Clear existing default config (only one should exist)
        $stmt = $db->prepare("DELETE FROM default_vlan_config");
        $stmt->execute();
        
        // Insert new config
        $stmt = $db->prepare("
            INSERT INTO default_vlan_config (vlan_id, description, enabled) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$vlan, $description, $enabled ? 1 : 0]);
        return true;
    } catch (Exception $e) {
        error_log("Error setting default VLAN config: " . $e->getMessage());
        return false;
    }
}

/**
 * Enable or disable default VLAN assignment
 * @param bool $enabled
 * @return bool
 */
function setDefaultVlanEnabled($enabled) {
    $db = getDB();
    
    try {
        $stmt = $db->prepare("
            UPDATE default_vlan_config 
            SET enabled = ?, updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$enabled ? 1 : 0]);
        return true;
    } catch (Exception $e) {
        error_log("Error updating default VLAN enabled status: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if default VLAN is enabled
 * @return bool
 */
function isDefaultVlanEnabled() {
    $config = getDefaultVlanConfig();
    return $config && $config['enabled'];
}

/**
 * Enhanced VLAN lookup with default fallback information
 * @param string $mac
 * @return array|null Returns array with vlan, match_type, matched_value, and description
 */
function getVlanForMacEnhancedWithDefault($mac) {
    // First try exact MAC match
    $exact_vlan = getVlanForMac($mac);
    if ($exact_vlan !== null) {
        return [
            'vlan' => $exact_vlan,
            'match_type' => 'exact',
            'matched_value' => $mac,
            'description' => 'Exact MAC address match'
        ];
    }
    
    // Then try prefix match
    $prefix_vlan = getVlanForMacByPrefix($mac);
    if ($prefix_vlan !== null) {
        $prefix = substr($mac, 0, 8);
        return [
            'vlan' => $prefix_vlan,
            'match_type' => 'prefix',
            'matched_value' => $prefix,
            'description' => 'MAC vendor prefix match'
        ];
    }
    
    // Finally try default VLAN
    $default_config = getDefaultVlanConfig();
    if ($default_config && $default_config['enabled']) {
        return [
            'vlan' => $default_config['vlan_id'],
            'match_type' => 'default',
            'matched_value' => 'DEFAULT',
            'description' => $default_config['description'] ?: 'Default VLAN assignment'
        ];
    }
    
    return null;
}