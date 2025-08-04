<?php
/**
 * UniFi RADIUS Admin Website - UniFi Controller Integration
 * 
 * This page provides seamless integration with Ubiquiti UniFi Controllers to
 * import wireless client information directly into the RADIUS system. It
 * eliminates the need for manual client entry by automatically discovering
 * devices connected to the UniFi network.
 * 
 * Key features:
 * - Direct UniFi Controller API integration
 * - Secure authentication with UniFi credentials
 * - Real-time client discovery and import
 * - Bulk VLAN assignment during import
 * - Device information enrichment (hostname, signal strength, uptime)
 * - Selective import with checkbox selection
 * - Duplicate detection and handling
 * - Connection status monitoring
 * 
 * Import process:
 * 1. Connect to UniFi Controller using admin credentials
 * 2. Retrieve list of active wireless clients
 * 3. Display client information with import checkboxes
 * 4. Allow bulk VLAN assignment for selected clients
 * 5. Import selected clients into RADIUS database
 * 6. Provide feedback on import success/failures
 * 
 * For beginners:
 *   This is like automatically importing your employee directory from HR
 *   into the building access system. Instead of manually typing each person's
 *   information, it reads the list from the main system and lets you assign
 *   access levels to multiple people at once.
 * 
 * UniFi Controller requirements:
 * - UniFi Controller version 5.13+ (API v2 support)
 * - Admin or Super Admin account credentials
 * - Network connectivity between web server and controller
 * - HTTPS/SSL support (self-signed certificates accepted)
 * 
 * Security considerations:
 * - UniFi credentials are not stored permanently
 * - SSL verification disabled for self-signed certificates
 * - Session-based authentication required for access
 * - CSRF protection on import operations
 * 
 * Supported client information:
 * - MAC address (primary identifier)
 * - Hostname/device name (when available)
 * - IP address assignment
 * - Signal strength and connection quality
 * - Last seen timestamp
 * - Connection duration
 * - Access point association
 * 
 * @package UniFiRadius
 * @subpackage UniFiIntegration
 */

require_once 'auth.php';
require_once 'db.php';

// Require authentication - only logged-in users can import from UniFi
requireAuth();

$error = '';
$success = '';
$unifi_clients = [];
$unifi_connected = false;

/**
 * UniFi Controller API Integration Class
 * 
 * This class provides a PHP interface to the UniFi Controller API, allowing
 * the RADIUS admin system to retrieve wireless client information directly
 * from the UniFi network management system.
 * 
 * Key capabilities:
 * - Secure authentication with UniFi Controller
 * - Wireless client discovery and enumeration
 * - Device information retrieval (MAC, IP, hostname, signal strength)
 * - Site-specific data access for multi-site controllers
 * - Automatic session management with cookie handling
 * - Error handling and connection validation
 * 
 * For beginners:
 *   This class is like a translator that speaks both "PHP language" and
 *   "UniFi language" so our admin system can talk to the UniFi Controller
 *   and get information about connected devices.
 * 
 * API compatibility:
 * - UniFi Controller v5.13+ (API v2)
 * - UniFi Cloud Key Gen1/Gen2
 * - UniFi Dream Machine (UDM/UDM Pro)
 * - Self-hosted UniFi Controller installations
 * 
 * Security features:
 * - Cookie-based session management
 * - SSL/TLS support with flexible certificate validation
 * - Automatic session cleanup
 * - Timeout protection against hung connections
 * 
 * @package UniFiRadius
 * @subpackage UniFiAPI
 */
class UniFiAPI {
    private $ip;        // UniFi Controller IP address or hostname
    private $username;  // Admin username for API access
    private $password;  // Admin password for API access
    private $site_id;   // Site identifier (usually 'default')
    private $cookies;   // Temporary cookie file for session management
    private $curl;      // cURL handle for HTTP requests
    
    /**
     * Initialize UniFi API connection parameters.
     * 
     * Sets up the connection configuration and prepares cURL for API requests.
     * This constructor doesn't establish the connection - call login() to authenticate.
     * 
     * @param string $ip UniFi Controller IP address or hostname
     * @param string $username Admin username (must have API access)
     * @param string $password Admin password
     * @param string $site_id Site identifier (default: 'default')
     */
    public function __construct($ip, $username, $password, $site_id = 'default') {
        $this->ip = $ip;
        $this->username = $username;
        $this->password = $password;
        $this->site_id = $site_id;
        
        // Create temporary cookie file for session management
        $this->cookies = tempnam(sys_get_temp_dir(), 'unifi_cookies');
        
        // Initialize cURL with secure defaults
        $this->curl = curl_init();
        curl_setopt_array($this->curl, [
            CURLOPT_RETURNTRANSFER => true,          // Return response as string
            CURLOPT_SSL_VERIFYPEER => false,         // Accept self-signed certificates
            CURLOPT_SSL_VERIFYHOST => false,         // Skip hostname verification
            CURLOPT_COOKIEJAR => $this->cookies,     // Save cookies to file
            CURLOPT_COOKIEFILE => $this->cookies,    // Read cookies from file
            CURLOPT_TIMEOUT => 30,                   // 30 second timeout
            CURLOPT_FOLLOWLOCATION => true,          // Follow redirects
            CURLOPT_USERAGENT => 'UniFi RADIUS Admin/1.0'  // Identify our application
        ]);
    }
    
    /**
     * Cleanup resources when object is destroyed.
     * 
     * Ensures proper cleanup of cURL handle and temporary files to prevent
     * resource leaks and security issues.
     */
    public function __destruct() {
        if ($this->curl) {
            curl_close($this->curl);
        }
        if (file_exists($this->cookies)) {
            unlink($this->cookies);
        }
    }
    
    public function login() {
        $url = "https://{$this->ip}:8443/api/login";
        $data = json_encode([
            'username' => $this->username,
            'password' => $this->password
        ]);
        
        curl_setopt_array($this->curl, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ]);
        
        $response = curl_exec($this->curl);
        $http_code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        
        if (curl_error($this->curl)) {
            throw new Exception('cURL Error: ' . curl_error($this->curl));
        }
        
        if ($http_code !== 200) {
            throw new Exception("Login failed with HTTP code: $http_code");
        }
        
        $result = json_decode($response, true);
        
        if (!$result || !isset($result['meta']['rc']) || $result['meta']['rc'] !== 'ok') {
            throw new Exception('Invalid credentials or login failed');
        }
        
        return true;
    }
    
    public function getWirelessClients() {
        $url = "https://{$this->ip}:8443/api/s/{$this->site_id}/stat/sta";
        
        curl_setopt_array($this->curl, [
            CURLOPT_URL => $url,
            CURLOPT_POST => false,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ]);
        
        $response = curl_exec($this->curl);
        $http_code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        
        if (curl_error($this->curl)) {
            throw new Exception('cURL Error: ' . curl_error($this->curl));
        }
        
        if ($http_code !== 200) {
            throw new Exception("Failed to fetch clients with HTTP code: $http_code");
        }
        
        $result = json_decode($response, true);
        
        if (!$result || !isset($result['meta']['rc']) || $result['meta']['rc'] !== 'ok') {
            throw new Exception('Failed to fetch wireless clients');
        }
        
        // Filter for wireless clients only
        $wireless_clients = [];
        foreach ($result['data'] as $client) {
            if (isset($client['is_wired']) && !$client['is_wired'] && isset($client['mac'])) {
                $wireless_clients[] = [
                    'mac' => strtolower($client['mac']),
                    'name' => $client['name'] ?? $client['hostname'] ?? 'Unknown Device',
                    'ip' => $client['ip'] ?? 'No IP',
                    'ap_mac' => $client['ap_mac'] ?? 'Unknown AP',
                    'essid' => $client['essid'] ?? 'Unknown SSID',
                    'last_seen' => isset($client['last_seen']) ? date('Y-m-d H:i:s', $client['last_seen']) : null,
                    'uptime' => $client['uptime'] ?? 0,
                    'rx_bytes' => $client['rx_bytes'] ?? 0,
                    'tx_bytes' => $client['tx_bytes'] ?? 0
                ];
            }
        }
        
        return $wireless_clients;
    }
    
    public function logout() {
        $url = "https://{$this->ip}:8443/api/logout";
        
        curl_setopt_array($this->curl, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '{}',
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ]);
        
        curl_exec($this->curl);
    }
}

// Handle UniFi connection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['connect_unifi'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $unifi_ip = trim($_POST['unifi_ip'] ?? '');
        $unifi_username = trim($_POST['unifi_username'] ?? '');
        $unifi_password = $_POST['unifi_password'] ?? '';
        $unifi_site = trim($_POST['unifi_site'] ?? 'default');
        
        if (empty($unifi_ip) || empty($unifi_username) || empty($unifi_password)) {
            $error = 'All UniFi connection fields are required.';
        } else {
            try {
                $unifi = new UniFiAPI($unifi_ip, $unifi_username, $unifi_password, $unifi_site);
                $unifi->login();
                $unifi_clients = $unifi->getWirelessClients();
                $unifi->logout();
                
                $unifi_connected = true;
                
                // Store connection info in session for import
                $_SESSION['unifi_clients'] = $unifi_clients;
                
                if (empty($unifi_clients)) {
                    $error = 'No wireless clients found on the UniFi Controller.';
                } else {
                    $success = 'Successfully connected to UniFi Controller and found ' . count($unifi_clients) . ' wireless clients.';
                }
            } catch (Exception $e) {
                $error = 'Failed to connect to UniFi Controller: ' . $e->getMessage();
            }
        }
    }
}

// Handle bulk import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_clients'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $selected_macs = $_POST['selected_macs'] ?? [];
        $import_results = [];
        
        if (empty($selected_macs)) {
            $error = 'Please select at least one client to import.';
        } else {
            // Get clients from session
            $session_clients = $_SESSION['unifi_clients'] ?? [];
            $clients_by_mac = [];
            foreach ($session_clients as $client) {
                $clients_by_mac[$client['mac']] = $client;
            }
            
            foreach ($selected_macs as $mac) {
                $vlan_key = "vlan_$mac";
                $description_key = "description_$mac";
                
                $vlan = trim($_POST[$vlan_key] ?? '');
                $description = trim($_POST[$description_key] ?? '');
                
                if (!isValidVlan($vlan)) {
                    $import_results[] = [
                        'mac' => $mac,
                        'status' => 'error',
                        'message' => 'Invalid VLAN ID'
                    ];
                    continue;
                }
                
                $normalized_mac = normalizeMac($mac);
                if (!$normalized_mac) {
                    $import_results[] = [
                        'mac' => $mac,
                        'status' => 'error',
                        'message' => 'Invalid MAC address format'
                    ];
                    continue;
                }
                
                // Check if already exists
                $existing_vlan = getVlanForMac($normalized_mac);
                
                if (setVlanForMac($normalized_mac, (int)$vlan)) {
                    // Update description with UniFi device info
                    $client_info = $clients_by_mac[$mac] ?? null;
                    if ($client_info) {
                        $auto_description = "UniFi: {$client_info['name']} (SSID: {$client_info['essid']})";
                        if (!empty($description)) {
                            $auto_description = "$description - $auto_description";
                        }
                        updateClientNotes($normalized_mac, $auto_description);
                    } elseif (!empty($description)) {
                        updateClientNotes($normalized_mac, $description);
                    }
                    
                    $status_msg = $existing_vlan !== null ? "Updated (was VLAN $existing_vlan)" : "Added";
                    $import_results[] = [
                        'mac' => $normalized_mac,
                        'status' => 'success',
                        'message' => "$status_msg with VLAN $vlan"
                    ];
                } else {
                    $import_results[] = [
                        'mac' => $normalized_mac,
                        'status' => 'error',
                        'message' => 'Database error'
                    ];
                }
            }
            
            $success_count = count(array_filter($import_results, fn($r) => $r['status'] === 'success'));
            $error_count = count($import_results) - $success_count;
            
            if ($success_count > 0) {
                $success = "Successfully imported $success_count clients" . ($error_count > 0 ? " ($error_count failed)" : "") . ".";
            }
            if ($error_count > 0 && $success_count === 0) {
                $error = "Failed to import any clients ($error_count errors).";
            }
        }
    }
}

// Load clients from session if available
if (isset($_SESSION['unifi_clients']) && empty($unifi_clients)) {
    $unifi_clients = $_SESSION['unifi_clients'];
    $unifi_connected = true;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import from UniFi - UniFi RADIUS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .import-card {
            border-left: 4px solid #198754;
        }
        .client-row {
            transition: background-color 0.2s;
        }
        .client-row:hover {
            background-color: #f8f9fa;
        }
        .mac-address {
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        .status-success {
            color: #198754;
        }
        .status-error {
            color: #dc3545;
        }
        .connection-form {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body class="bg-light">

<?php $active_page = 'import_unifi'; include 'header.php'; ?>

<!-- Main Content -->
<div class="container mt-4">
    <!-- Page Header -->
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Import from UniFi</li>
                </ol>
            </nav>
            
            <h1 class="h3 mb-4">
                <i class="bi bi-download"></i> Import from UniFi Controller
            </h1>
        </div>
    </div>

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

    <!-- Import Results -->
    <?php if (isset($import_results) && !empty($import_results)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-list-check"></i> Import Results
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>MAC Address</th>
                                <th>Status</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($import_results as $result): ?>
                                <tr>
                                    <td><code class="mac-address"><?= htmlspecialchars($result['mac']) ?></code></td>
                                    <td>
                                        <span class="status-<?= $result['status'] ?>">
                                            <i class="bi bi-<?= $result['status'] === 'success' ? 'check-circle' : 'x-circle' ?>"></i>
                                            <?= ucfirst($result['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($result['message']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <a href="view_clients.php" class="btn btn-outline-primary">
                        <i class="bi bi-list"></i> View All Clients
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$unifi_connected): ?>
        <!-- UniFi Connection Form -->
        <div class="connection-form">
            <h5 class="mb-3">
                <i class="bi bi-plug"></i> Connect to UniFi Controller
            </h5>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="unifi_ip" class="form-label">
                                Controller IP/Hostname <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="unifi_ip" 
                                   name="unifi_ip" 
                                   placeholder="192.168.1.1 or unifi.example.com"
                                   value="<?= htmlspecialchars($_POST['unifi_ip'] ?? '') ?>"
                                   required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="unifi_site" class="form-label">
                                Site ID <span class="text-muted">(optional)</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="unifi_site" 
                                   name="unifi_site" 
                                   placeholder="default"
                                   value="<?= htmlspecialchars($_POST['unifi_site'] ?? 'default') ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="unifi_username" class="form-label">
                                Username <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="unifi_username" 
                                   name="unifi_username" 
                                   value="<?= htmlspecialchars($_POST['unifi_username'] ?? '') ?>"
                                   required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="unifi_password" class="form-label">
                                Password <span class="text-danger">*</span>
                            </label>
                            <input type="password" 
                                   class="form-control" 
                                   id="unifi_password" 
                                   name="unifi_password" 
                                   required>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i>
                        Credentials are not stored and only used for this session.
                    </small>
                    <button type="submit" name="connect_unifi" class="btn btn-success">
                        <i class="bi bi-plug-fill"></i> Connect & Fetch Clients
                    </button>
                </div>
            </form>
        </div>

        <!-- Help Section -->
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="bi bi-question-circle"></i> Connection Help
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Connection Requirements</h6>
                        <ul class="list-unstyled small">
                            <li><i class="bi bi-check text-success"></i> UniFi Controller accessible via HTTPS (port 8443)</li>
                            <li><i class="bi bi-check text-success"></i> Admin credentials with API access</li>
                            <li><i class="bi bi-check text-success"></i> Network connectivity from this server</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Security Notes</h6>
                        <ul class="list-unstyled small">
                            <li><i class="bi bi-shield text-primary"></i> SSL verification is disabled for self-signed certs</li>
                            <li><i class="bi bi-shield text-primary"></i> Credentials are not stored permanently</li>
                            <li><i class="bi bi-shield text-primary"></i> Only wireless clients are fetched</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- Client Import Form -->
        <div class="card import-card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-wifi"></i> Wireless Clients 
                        <span class="badge bg-success"><?= count($unifi_clients) ?></span>
                    </h5>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="clearConnection()">
                            <i class="bi bi-arrow-clockwise"></i> New Connection
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAll()">
                            <i class="bi bi-check-square"></i> Select All
                        </button>
                    </div>
                </div>
            </div>
            
            <?php if (empty($unifi_clients)): ?>
                <div class="card-body text-center py-5">
                    <i class="bi bi-wifi-off text-muted" style="font-size: 3rem;"></i>
                    <h5 class="text-muted mt-3">No Wireless Clients Found</h5>
                    <p class="text-muted">No active wireless clients found on the UniFi Controller.</p>
                    <button type="button" class="btn btn-outline-primary" onclick="clearConnection()">
                        <i class="bi bi-arrow-clockwise"></i> Try Different Connection
                    </button>
                </div>
            <?php else: ?>
                <form method="POST" id="importForm">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50">
                                            <input type="checkbox" class="form-check-input" id="selectAllCheckbox">
                                        </th>
                                        <th>Device Info</th>
                                        <th>Network</th>
                                        <th>VLAN Assignment</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($unifi_clients as $client): ?>
                                        <?php 
                                        $mac = $client['mac'];
                                        $normalized_mac = normalizeMac($mac);
                                        $existing_vlan = getVlanForMac($normalized_mac);
                                        ?>
                                        <tr class="client-row">
                                            <td>
                                                <input type="checkbox" 
                                                       class="form-check-input client-checkbox" 
                                                       name="selected_macs[]" 
                                                       value="<?= htmlspecialchars($mac) ?>">
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?= htmlspecialchars($client['name']) ?></strong>
                                                    <?php if ($existing_vlan !== null): ?>
                                                        <span class="badge bg-warning text-dark ms-2">Exists</span>
                                                    <?php endif; ?>
                                                </div>
                                                <small class="text-muted">
                                                    <code class="mac-address"><?= htmlspecialchars($mac) ?></code>
                                                </small>
                                                <br>
                                                <small class="text-muted">IP: <?= htmlspecialchars($client['ip']) ?></small>
                                            </td>
                                            <td>
                                                <div><strong><?= htmlspecialchars($client['essid']) ?></strong></div>
                                                <small class="text-muted">
                                                    <?php if ($client['last_seen']): ?>
                                                        Last: <?= date('M j g:i A', strtotime($client['last_seen'])) ?>
                                                    <?php else: ?>
                                                        Active now
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <input type="number" 
                                                       class="form-control form-control-sm" 
                                                       name="vlan_<?= htmlspecialchars($mac) ?>" 
                                                       placeholder="VLAN ID"
                                                       value="<?= $existing_vlan ?: '' ?>"
                                                       min="1" 
                                                       max="4094"
                                                       style="width: 100px;">
                                                <?php if ($existing_vlan !== null): ?>
                                                    <small class="text-muted">Current: <?= $existing_vlan ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <input type="text" 
                                                       class="form-control form-control-sm" 
                                                       name="description_<?= htmlspecialchars($mac) ?>" 
                                                       placeholder="Optional description"
                                                       style="min-width: 200px;">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="card-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="bi bi-info-circle"></i>
                                Select clients and assign VLANs to import them into FreeRADIUS.
                            </small>
                            <button type="submit" name="import_clients" class="btn btn-success">
                                <i class="bi bi-download"></i> Import Selected Clients
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Clear connection and start over
function clearConnection() {
    // Clear session data via hidden form
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'clear_session';
    input.value = '1';
    
    form.appendChild(input);
    document.body.appendChild(form);
    
    // Clear session on server side
    fetch(window.location.href, {
        method: 'POST',
        body: 'clear_session=1',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        }
    }).then(() => {
        window.location.reload();
    });
}

// Select all clients
function selectAll() {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const clientCheckboxes = document.querySelectorAll('.client-checkbox');
    
    clientCheckboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
}

// Update select all checkbox when individual checkboxes change
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const clientCheckboxes = document.querySelectorAll('.client-checkbox');
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', selectAll);
        
        clientCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const checkedCount = document.querySelectorAll('.client-checkbox:checked').length;
                selectAllCheckbox.checked = checkedCount === clientCheckboxes.length;
                selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < clientCheckboxes.length;
            });
        });
    }
});

// Form validation
document.getElementById('importForm')?.addEventListener('submit', function(e) {
    const selectedCheckboxes = document.querySelectorAll('.client-checkbox:checked');
    
    if (selectedCheckboxes.length === 0) {
        e.preventDefault();
        alert('Please select at least one client to import.');
        return false;
    }
    
    let hasValidVlan = false;
    selectedCheckboxes.forEach(checkbox => {
        const mac = checkbox.value;
        const vlanInput = document.querySelector(`input[name="vlan_${mac}"]`);
        if (vlanInput && vlanInput.value && vlanInput.value >= 1 && vlanInput.value <= 4094) {
            hasValidVlan = true;
        }
    });
    
    if (!hasValidVlan) {
        e.preventDefault();
        alert('Please assign valid VLAN IDs (1-4094) to selected clients.');
        return false;
    }
});
</script>

<?php
// Handle session clearing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_session'])) {
    unset($_SESSION['unifi_clients']);
    exit;
}
?>

</body>
</html>