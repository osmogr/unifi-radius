<?php
/**
 * Setup Test Script for UniFi RADIUS Admin Website
 * Quick test to verify the application functionality
 */

// Test the database connection and helper functions
require_once dirname(__FILE__) . '/../../website/db.php';

echo "UniFi RADIUS Admin Website - Setup Test\n";
echo "=====================================\n\n";

// Test 1: Database connection
echo "1. Testing database connection...\n";
try {
    $db = getDB();
    echo "   ✓ Database connection successful\n";
} catch (Exception $e) {
    echo "   ✗ Database connection failed: " . $e->getMessage() . "\n";
    echo "   Please check your db.php configuration\n";
    exit(1);
}

// Test 2: MAC address validation
echo "\n2. Testing MAC address validation...\n";
$test_macs = [
    'aa:bb:cc:dd:ee:ff' => true,
    'AA:BB:CC:DD:EE:FF' => true,
    'aabbccddeeff' => false, // Will be normalized
    'aa-bb-cc-dd-ee-ff' => false, // Will be normalized
    'invalid-mac' => false,
    '11:22:33:44:55' => false, // Too short
];

foreach ($test_macs as $mac => $expected) {
    $result = isValidMac($mac);
    $normalized = normalizeMac($mac);
    
    if ($result) {
        echo "   ✓ $mac is valid -> $normalized\n";
    } else {
        if ($normalized) {
            echo "   ✓ $mac normalized to -> $normalized\n";
        } else {
            echo "   ✗ $mac is invalid\n";
        }
    }
}

// Test 3: VLAN validation
echo "\n3. Testing VLAN validation...\n";
$test_vlans = [1, 100, 4094, 0, 4095, 'invalid', 50];

foreach ($test_vlans as $vlan) {
    $result = isValidVlan($vlan);
    echo "   " . ($result ? '✓' : '✗') . " VLAN $vlan is " . ($result ? 'valid' : 'invalid') . "\n";
}

// Test 4: Database operations (if tables exist)
echo "\n4. Testing database operations...\n";
try {
    // Test if tables exist
    $db->query("SELECT COUNT(*) FROM radreply LIMIT 1");
    echo "   ✓ radreply table exists\n";
    
    $db->query("SELECT COUNT(*) FROM client_notes LIMIT 1");
    echo "   ✓ client_notes table exists\n";
    
    // Get current clients
    $clients = getAllClients();
    echo "   ✓ Found " . count($clients) . " existing clients\n";
    
    if (count($clients) > 0) {
        echo "   Example clients:\n";
        foreach (array_slice($clients, 0, 3) as $client) {
            echo "     - {$client['mac']} -> VLAN {$client['vlan']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "   ✗ Database tables not found: " . $e->getMessage() . "\n";
    echo "   Please import schema.sql into your database\n";
}

// Test 5: Authentication functions
echo "\n5. Testing authentication functions...\n";
session_start();
echo "   ✓ Session started successfully\n";

$csrf_token = generateCSRFToken();
echo "   ✓ CSRF token generated: " . substr($csrf_token, 0, 16) . "...\n";

$token_valid = verifyCSRFToken($csrf_token);
echo "   " . ($token_valid ? '✓' : '✗') . " CSRF token validation " . ($token_valid ? 'passed' : 'failed') . "\n";

// Test 6: Check PHP requirements
echo "\n6. Checking PHP requirements...\n";
$required_extensions = ['pdo', 'pdo_mysql', 'curl', 'json', 'session'];

foreach ($required_extensions as $ext) {
    $loaded = extension_loaded($ext);
    echo "   " . ($loaded ? '✓' : '✗') . " $ext extension " . ($loaded ? 'loaded' : 'missing') . "\n";
}

echo "\nPHP Version: " . PHP_VERSION . "\n";

// Test 7: File permissions (basic check)
echo "\n7. Checking file permissions...\n";
$files = ['index.php', 'db.php', 'auth.php', 'schema.sql'];

foreach ($files as $file) {
    if (file_exists($file)) {
        $perms = substr(sprintf('%o', fileperms($file)), -4);
        echo "   ✓ $file permissions: $perms\n";
    } else {
        echo "   ✗ $file not found\n";
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Setup test completed!\n\n";

echo "Next steps:\n";
echo "1. Import schema.sql into your MySQL/MariaDB database\n";
echo "2. Update database credentials in db.php\n";
echo "3. Configure your web server to serve the PHP files\n";
echo "4. Access index.php in your browser\n";
echo "5. Login with admin/admin123\n";
echo "6. Change default credentials for production use\n\n";

echo "Access the application at: http://your-server/path/to/index.php\n";
?>