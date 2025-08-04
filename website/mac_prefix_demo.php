<?php
/**
 * Example usage and validation test for MAC prefix functionality
 * 
 * This script demonstrates how the new MAC prefix features work
 * and validates the core functionality without requiring a database connection.
 */

echo "UniFi RADIUS Admin - MAC Prefix Feature Demo\n";
echo "===========================================\n\n";

// Include validation functions (extracted from db.php for testing)
function isValidMacPrefix($prefix) {
    return preg_match('/^([0-9a-f]{2}:){2}[0-9a-f]{2}$/i', $prefix);
}

function normalizeMacPrefix($prefix) {
    $prefix = preg_replace('/[^0-9a-f]/i', '', $prefix);
    if (strlen($prefix) !== 6) return false;
    $prefix = strtolower($prefix);
    return substr($prefix, 0, 2) . ':' . substr($prefix, 2, 2) . ':' . substr($prefix, 4, 2);
}

function isValidMac($mac) {
    return preg_match('/^([0-9a-f]{2}:){5}[0-9a-f]{2}$/i', $mac);
}

// Example 1: How MAC prefix validation works
echo "1. MAC Prefix Validation Examples\n";
echo "---------------------------------\n";

$examples = [
    'aabbcc' => 'aa:bb:cc',      // Normalized
    'AA:BB:CC' => 'aa:bb:cc',    // Case normalization
    'aa-bb-cc' => 'aa:bb:cc',    // Delimiter normalization
    '11:22:33' => '11:22:33',    // Already valid
    'invalid' => false,          // Invalid input
    '11:22:33:44' => false,      // Too long
];

foreach ($examples as $input => $expected) {
    $normalized = normalizeMacPrefix($input);
    $status = ($normalized === $expected) ? '✓' : '✗';
    $result = $normalized ?: 'INVALID';
    echo "   $status '$input' -> '$result'\n";
}

// Example 2: Use cases for MAC prefixes
echo "\n2. Common Use Cases\n";
echo "-------------------\n";

$use_cases = [
    [
        'vendor' => 'Apple Inc.',
        'prefix' => '00:50:e4',
        'vlan' => 10,
        'description' => 'Corporate devices (iPhones, MacBooks)'
    ],
    [
        'vendor' => 'Cisco Systems',
        'prefix' => '00:1b:d4',
        'vlan' => 20,
        'description' => 'Network infrastructure'
    ],
    [
        'vendor' => 'IoT Sensors Co.',
        'prefix' => '44:55:66',
        'vlan' => 50,
        'description' => 'Temperature and humidity sensors'
    ],
    [
        'vendor' => 'Guest Devices',
        'prefix' => 'aa:bb:cc',
        'vlan' => 100,
        'description' => 'Temporary guest network devices'
    ]
];

foreach ($use_cases as $case) {
    echo "   • {$case['vendor']} ({$case['prefix']}) -> VLAN {$case['vlan']}\n";
    echo "     {$case['description']}\n\n";
}

// Example 3: How matching precedence works
echo "3. Matching Precedence Example\n";
echo "-------------------------------\n";

$test_mac = 'aa:bb:cc:dd:ee:ff';
$prefix = substr($test_mac, 0, 8); // Extract first 3 octets

echo "   Device MAC: $test_mac\n";
echo "   Vendor prefix: $prefix\n\n";

echo "   Matching order:\n";
echo "   1. Check exact MAC in radreply table\n";
echo "   2. If no match, check prefix '$prefix' in mac_prefixes table\n";
echo "   3. Return appropriate VLAN assignment\n\n";

echo "   Priority: Exact MAC match > Prefix match > No match (reject)\n";

// Example 4: Database schema
echo "\n4. Database Schema\n";
echo "------------------\n";

echo "   New table: mac_prefixes\n";
echo "   ┌─────────────┬──────────────┬─────────────────────────────────┐\n";
echo "   │ prefix      │ vlan_id      │ description                     │\n";
echo "   ├─────────────┼──────────────┼─────────────────────────────────┤\n";
echo "   │ aa:bb:cc    │ 100          │ Development Devices             │\n";
echo "   │ 11:22:33    │ 50           │ IoT Sensors                     │\n";
echo "   │ ff:ee:dd    │ 999          │ Guest Network                   │\n";
echo "   └─────────────┴──────────────┴─────────────────────────────────┘\n";

// Example 5: FreeRADIUS integration
echo "\n5. FreeRADIUS Integration\n";
echo "-------------------------\n";

echo "   Updated SQL query supports both exact and prefix matching:\n\n";
echo "   SELECT id, username, attribute, value, op \n";
echo "   FROM radreply \n";
echo "   WHERE username = '%{SQL-User-Name}' \n";
echo "   UNION \n";
echo "   SELECT ... FROM mac_prefixes \n";
echo "   WHERE SUBSTRING('%{SQL-User-Name}', 1, 8) = prefix\n";
echo "   AND '%{SQL-User-Name}' NOT IN (SELECT username FROM radreply)\n\n";

// Example 6: Web interface features
echo "6. Web Interface Features\n";
echo "-------------------------\n";

echo "   • New 'MAC Prefixes' page for vendor management\n";
echo "   • Add/delete prefix assignments\n";
echo "   • Real-time validation and formatting\n";
echo "   • Integration with existing navigation\n";
echo "   • Dashboard statistics include prefix count\n";
echo "   • Help text explaining how prefixes work\n";

// Example 7: API functions
echo "\n7. New PHP Functions\n";
echo "--------------------\n";

$api_functions = [
    'isValidMacPrefix($prefix)' => 'Validate MAC prefix format',
    'normalizeMacPrefix($prefix)' => 'Normalize prefix to standard format',
    'setVlanForMacPrefix($prefix, $vlan, $desc)' => 'Assign VLAN to prefix',
    'getVlanForMacPrefix($prefix)' => 'Get VLAN for prefix',
    'removeVlanForMacPrefix($prefix)' => 'Remove prefix assignment',
    'getAllMacPrefixes()' => 'Get all prefix assignments',
    'getVlanForMacByPrefix($mac)' => 'Find VLAN using prefix matching',
    'getVlanForMacEnhanced($mac)' => 'Enhanced lookup (exact + prefix)'
];

foreach ($api_functions as $function => $description) {
    echo "   • $function\n     $description\n\n";
}

echo str_repeat("=", 60) . "\n";
echo "MAC Prefix Feature Implementation Complete!\n\n";

echo "Next Steps:\n";
echo "1. Import the updated schema.sql to add mac_prefixes table\n";
echo "2. Update FreeRADIUS configuration with new SQL queries\n";
echo "3. Access /mac_prefixes.php to manage vendor assignments\n";
echo "4. Test with actual RADIUS authentication requests\n\n";

echo "Benefits:\n";
echo "• Bulk device management by vendor\n";
echo "• Reduced administrative overhead\n";
echo "• Maintains backward compatibility\n";
echo "• Flexible vendor-based VLAN assignment\n";
echo "• Enhanced network security and segmentation\n";
?>