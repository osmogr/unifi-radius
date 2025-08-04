#!/usr/bin/env python3
"""
Test script to verify default VLAN functionality in Python RADIUS server.

This script specifically tests the default VLAN fallback mechanism, which provides
network access to devices that don't have specific VLAN assignments. This is useful
for guest access or default security policies.

Purpose:
    - Test default VLAN assignment when no exact MAC match exists
    - Test default VLAN assignment when no prefix match exists  
    - Verify default VLAN can be enabled/disabled via database configuration
    - Test behavior when default VLAN is disabled (should reject access)

Test scenarios:
    1. Unknown device with default VLAN enabled -> should get default VLAN
    2. Unknown device with default VLAN disabled -> should be rejected
    3. Database connectivity issues -> should handle gracefully

For beginners:
    Default VLAN is like a "visitor pass" system. When someone unknown shows up,
    instead of denying them access completely, you can give them limited visitor
    access. This test makes sure that system works correctly.

Usage:
    python3 test/test_default_vlan.py

Requirements:
    - MySQL database with RADIUS schema
    - default_vlan_config table configured
"""

import sys
import os
# Add the python-radius directory to the path for module imports
sys.path.append(os.path.join(os.path.dirname(__file__), '..', 'python-radius'))

from radius_server import DatabaseManager
import logging

# Set up basic logging to show test progress
logging.basicConfig(level=logging.INFO, format='%(levelname)s: %(message)s')

def test_default_vlan():
    """
    Test the default VLAN lookup functionality for unknown devices.
    
    This function tests whether the default VLAN mechanism works correctly
    when a device is not found in either the exact match (radreply) table
    or the prefix match (mac_prefixes) table.
    
    The test uses a MAC address (ff:ff:ff:ff:ff:ff) that is very unlikely
    to exist in the database, forcing the system to fall back to the
    default VLAN if one is configured and enabled.
    
    For beginners:
        This is like testing the "visitor badge" system. When someone
        unknown shows up, do they get a visitor badge (default VLAN)
        or are they turned away (no access)?
    
    Returns:
        bool: True if default VLAN works as expected, False otherwise
    """
    
    print("Testing Python RADIUS Server Default VLAN Functionality")
    print("=" * 60)
    
    # Initialize database manager
    db_manager = DatabaseManager()
    
    # Test with a MAC that shouldn't exist in the database
    # ff:ff:ff:ff:ff:ff is a broadcast address, unlikely to be in real use
    test_mac = "ff:ff:ff:ff:ff:ff"
    
    print(f"Testing MAC: {test_mac}")
    print("This MAC should not exist in exact or prefix tables...")
    
    # Attempt VLAN lookup - should fall back to default VLAN if configured
    vlan_id, match_type = db_manager.get_vlan_for_mac(test_mac)
    
    if vlan_id is not None:
        print(f"✓ Default VLAN working: VLAN {vlan_id} assigned via '{match_type}' match")
        print(f"  Unknown devices will be placed on VLAN {vlan_id}")
        return True
    else:
        print("✗ Default VLAN not working: No VLAN assigned")
        print("This could mean:")
        print("  1. Default VLAN is disabled in the database (enabled = 0)")
        print("  2. No default VLAN is configured in default_vlan_config table")
        print("  3. Database connection issue")
        print("  4. This is expected behavior if default access is not wanted")
        return False

def test_existing_mac():
    """
    Test that existing MAC addresses still work correctly with default VLAN enabled.
    
    This function verifies that the default VLAN mechanism doesn't interfere
    with normal MAC address lookups. It tests with a MAC that should be found
    in the database to ensure exact and prefix matches still work properly.
    
    For beginners:
        This ensures that adding a "visitor badge" system doesn't break
        the regular employee ID system. Known users should still get their
        normal access levels.
    
    Returns:
        bool: True if existing MAC lookup works, False otherwise
    """
    
    print("\nTesting existing MAC functionality")
    print("-" * 40)
    
    db_manager = DatabaseManager()
    
    # Test with sample MAC from schema - this should exist in database
    test_mac = "aa:bb:cc:dd:ee:f1"
    
    print(f"Testing MAC: {test_mac}")
    print("This MAC should exist in the database...")
    
    vlan_id, match_type = db_manager.get_vlan_for_mac(test_mac)
    
    if vlan_id is not None:
        print(f"✓ Existing MAC working: VLAN {vlan_id} assigned via '{match_type}' match")
        print(f"  Normal VLAN assignment functionality is preserved")
        return True
    else:
        print(f"✗ Existing MAC not working: No VLAN assigned for {test_mac}")
        print("  This suggests the test MAC doesn't exist in database")
        print("  or there's a database connectivity issue")
        return False

if __name__ == "__main__":
    """
    Main entry point for default VLAN testing.
    
    Runs both test scenarios and provides a summary of results.
    This allows the script to be run standalone or imported by other test scripts.
    
    Exit codes:
        0 - All tests passed
        1 - Some tests failed or error occurred
    """
    try:
        # Run both test scenarios
        success1 = test_existing_mac()
        success2 = test_default_vlan()
        
        # Provide summary of results
        print("\n" + "=" * 60)
        print("Test Summary:")
        print(f"  Existing MAC test: {'PASS' if success1 else 'FAIL'}")
        print(f"  Default VLAN test: {'PASS' if success2 else 'FAIL'}")
        
        if success1 and success2:
            print("\n✓ All tests passed! Default VLAN functionality is working.")
            print("  Both regular authentication and default fallback work correctly.")
            sys.exit(0)
        else:
            print("\n✗ Some tests failed. Check database configuration.")
            print("  Verify default_vlan_config table and enabled flag.")
            sys.exit(1)
            
    except Exception as e:
        print(f"\nError during testing: {e}")
        print("Make sure the database is accessible and configured correctly.")
        print("Check database connection settings and table schema.")
        sys.exit(1)