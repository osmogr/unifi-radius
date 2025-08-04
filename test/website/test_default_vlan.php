<?php
/**
 * Test script for DEFAULT VLAN functionality
 * Validates database functions and integration
 */

require_once dirname(__FILE__) . '/../../website/db.php';

echo "DEFAULT VLAN Feature Test\n";
echo "========================\n\n";

// Test 1: Database connection
echo "1. Testing database connection...\n";
try {
    $db = getDB();
    echo "   ✓ Database connection successful\n";
} catch (Exception $e) {
    echo "   ✗ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Check if default_vlan_config table exists
echo "\n2. Testing default_vlan_config table...\n";
try {
    $db->query("SELECT COUNT(*) FROM default_vlan_config LIMIT 1");
    echo "   ✓ default_vlan_config table exists\n";
    
    // Check if default data exists
    $stmt = $db->query("SELECT * FROM default_vlan_config LIMIT 1");
    $config = $stmt->fetch();
    if ($config) {
        echo "   ✓ Default configuration found: VLAN {$config['vlan_id']}, enabled: " . ($config['enabled'] ? 'yes' : 'no') . "\n";
    } else {
        echo "   ⚠ No default configuration found in database\n";
    }
} catch (Exception $e) {
    echo "   ✗ default_vlan_config table not found: " . $e->getMessage() . "\n";
    echo "   Please run the updated schema.sql\n";
}

// Test 3: Test DEFAULT VLAN functions
echo "\n3. Testing DEFAULT VLAN functions...\n";

// Test getDefaultVlanConfig
try {
    $config = getDefaultVlanConfig();
    if ($config) {
        echo "   ✓ getDefaultVlanConfig() works - VLAN: {$config['vlan_id']}\n";
    } else {
        echo "   ⚠ getDefaultVlanConfig() returned null - no configuration found\n";
    }
} catch (Exception $e) {
    echo "   ✗ getDefaultVlanConfig() failed: " . $e->getMessage() . "\n";
}

// Test setDefaultVlanConfig
try {
    $result = setDefaultVlanConfig(500, 'Test default VLAN for testing', true);
    if ($result) {
        echo "   ✓ setDefaultVlanConfig() works\n";
        
        // Verify it was set
        $config = getDefaultVlanConfig();
        if ($config && $config['vlan_id'] == 500) {
            echo "   ✓ Default VLAN configuration verified - VLAN 500\n";
        } else {
            echo "   ✗ Default VLAN configuration not set correctly\n";
        }
    } else {
        echo "   ✗ setDefaultVlanConfig() failed\n";
    }
} catch (Exception $e) {
    echo "   ✗ setDefaultVlanConfig() error: " . $e->getMessage() . "\n";
}

// Test isDefaultVlanEnabled
try {
    $enabled = isDefaultVlanEnabled();
    echo "   ✓ isDefaultVlanEnabled() works - " . ($enabled ? 'enabled' : 'disabled') . "\n";
} catch (Exception $e) {
    echo "   ✗ isDefaultVlanEnabled() failed: " . $e->getMessage() . "\n";
}

// Test 4: Test enhanced VLAN lookup with default fallback
echo "\n4. Testing enhanced VLAN lookup...\n";

// Test cases
$test_cases = [
    'aa:bb:cc:dd:ee:f1', // Should find exact match (from sample data)
    'aa:bb:cc:11:22:33', // Should find prefix match (from sample data)
    'ff:ff:ff:11:22:33', // Should find default fallback
];

foreach ($test_cases as $mac) {
    try {
        $result = getVlanForMacEnhancedWithDefault($mac);
        if ($result) {
            echo "   ✓ MAC $mac -> VLAN {$result['vlan']} ({$result['match_type']} match)\n";
        } else {
            echo "   ✗ MAC $mac -> No VLAN assignment found\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Enhanced lookup for $mac failed: " . $e->getMessage() . "\n";
    }
}

// Test 5: Verify existing functionality still works
echo "\n5. Testing existing functionality...\n";

try {
    $clients = getAllClients();
    echo "   ✓ getAllClients() works - found " . count($clients) . " clients\n";
    
    $prefixes = getAllMacPrefixes();
    echo "   ✓ getAllMacPrefixes() works - found " . count($prefixes) . " prefixes\n";
    
} catch (Exception $e) {
    echo "   ✗ Existing functions failed: " . $e->getMessage() . "\n";
}

// Test 6: Test invalid inputs
echo "\n6. Testing input validation...\n";

$invalid_vlans = [0, 5000, -1, 'invalid'];
foreach ($invalid_vlans as $vlan) {
    $result = setDefaultVlanConfig($vlan, 'Test invalid');
    if (!$result) {
        echo "   ✓ Correctly rejected invalid VLAN: $vlan\n";
    } else {
        echo "   ✗ Incorrectly accepted invalid VLAN: $vlan\n";
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "DEFAULT VLAN test completed!\n\n";

echo "Next steps to test:\n";
echo "1. Start Docker environment: docker compose up -d\n";
echo "2. Access web interface: http://localhost:8080\n";
echo "3. Navigate to Default VLAN page\n";
echo "4. Configure default VLAN settings\n";
echo "5. Test FreeRADIUS with unknown MAC address\n\n";

// Reset to a reasonable default for testing
try {
    setDefaultVlanConfig(999, 'Default VLAN for unconfigured devices', true);
    echo "Reset default VLAN to 999 for testing.\n";
} catch (Exception $e) {
    echo "Could not reset default VLAN: " . $e->getMessage() . "\n";
}
?>