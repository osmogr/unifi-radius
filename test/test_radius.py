#!/usr/bin/env python3
"""
Comprehensive test script for Python RADIUS Server functionality.

This script performs integration testing of the Python RADIUS server by
simulating real RADIUS authentication requests. It tests the complete
authentication flow including database operations, VLAN assignments,
and RADIUS protocol handling.

Key testing areas:
- Database connectivity and test data setup
- RADIUS client/server communication
- MAC-based authentication logic
- VLAN assignment (exact match, prefix match, default VLAN)
- Error handling and edge cases
- Integration with MySQL database

Usage:
    python3 test/test_radius.py

Prerequisites:
    - MySQL database running with RADIUS schema
    - Python dependencies installed (pip install -r requirements.txt)
    - RADIUS server configuration files available

For beginners:
    This is like a comprehensive quality assurance test that simulates
    real-world usage scenarios to make sure everything works correctly
    when wireless devices try to connect to the network.

Environment Variables:
    DB_HOST, DB_NAME, DB_USER, DB_PASS - Database connection settings
"""

import sys
import os
import time
import mysql.connector
from pyrad.client import Client
from pyrad.dictionary import Dictionary
from pyrad import packet

# Add the python-radius directory to path so we can import our modules
sys.path.append(os.path.join(os.path.dirname(__file__), '..', 'python-radius'))

def setup_test_data():
    """
    Setup test data in the database for comprehensive RADIUS testing.
    
    This function prepares the database with known test data that will be used
    to verify different authentication scenarios. It creates both exact MAC
    matches and prefix matches to test the hierarchical lookup logic.
    
    Test data created:
    1. Exact MAC match: aa:bb:cc:dd:ee:ff -> VLAN 100
    2. Prefix match: aa:bb:cc:* -> VLAN 200 (for bulk vendor assignment)
    3. Client description for web interface testing
    
    For beginners:
        This is like setting up a test environment with known "fake" data
        so we can predict what should happen when we run tests. It's like
        having practice questions with known answers.
    
    Returns:
        tuple: (test_mac, test_vlan) for use in subsequent tests
    
    Raises:
        mysql.connector.Error: If database operations fail
    """
    print("Setting up test data...")
    
    # Database connection configuration
    config = {
        'host': os.getenv('DB_HOST', 'localhost'),
        'database': os.getenv('DB_NAME', 'radius'),
        'user': os.getenv('DB_USER', 'radius_user'),
        'password': os.getenv('DB_PASS', 'radius_password'),
        'charset': 'utf8mb4',
        'autocommit': True
    }
    
    try:
        conn = mysql.connector.connect(**config)
        cursor = conn.cursor()
        
        # Clean up any existing test data to start fresh
        print("  Cleaning up existing test data...")
        cursor.execute("DELETE FROM radreply WHERE username LIKE 'aa:bb:cc:%'")
        cursor.execute("DELETE FROM client_notes WHERE mac LIKE 'aa:bb:cc:%'")
        cursor.execute("DELETE FROM mac_prefixes WHERE prefix = 'aa:bb:cc'")
        
        # Test case 1: Exact MAC address match
        test_mac = "aa:bb:cc:dd:ee:ff"
        test_vlan = 100
        
        print(f"  Creating exact match: {test_mac} -> VLAN {test_vlan}")
        # Insert RADIUS attributes for VLAN assignment
        cursor.execute("""
            INSERT INTO radreply (username, attribute, op, value) VALUES
            (%s, 'Tunnel-Type', '=', '13'),
            (%s, 'Tunnel-Medium-Type', '=', '6'),
            (%s, 'Tunnel-Private-Group-ID', '=', %s)
        """, (test_mac, test_mac, test_mac, str(test_vlan)))
        
        # Add descriptive note for web interface
        cursor.execute("""
            INSERT INTO client_notes (mac, description) VALUES (%s, %s)
        """, (test_mac, "Test device for Python RADIUS integration testing"))
        
        # Test case 2: MAC prefix match (manufacturer-based assignment)
        print("  Creating prefix match: aa:bb:cc:* -> VLAN 200")
        cursor.execute("""
            INSERT INTO mac_prefixes (prefix, vlan_id, description) VALUES
            ('aa:bb:cc', 200, 'Test vendor prefix for bulk device assignment')
        """)
        
        print("  Test data setup complete:")
        print(f"    ✓ Exact match: {test_mac} -> VLAN {test_vlan}")
        print(f"    ✓ Prefix match: aa:bb:cc:* -> VLAN 200")
        print(f"    ✓ Client notes added for web interface")
        
        cursor.close()
        conn.close()
        return test_mac, test_vlan
        
    except mysql.connector.Error as e:
        print(f"  ✗ Database setup failed: {e}")
        return None, None
        print(f"Database setup failed: {e}")
        return None, None

def test_radius_auth(server_host='localhost', server_port=1812, secret='testing123'):
    """Test RADIUS authentication requests"""
    print(f"\nTesting RADIUS server at {server_host}:{server_port}")
    
    # Create dictionary
    dict_content = """
ATTRIBUTE	User-Name		1	string
ATTRIBUTE	User-Password		2	string
ATTRIBUTE	NAS-IP-Address		4	ipaddr
ATTRIBUTE	NAS-Port		5	integer
ATTRIBUTE	Service-Type		6	integer
ATTRIBUTE	Called-Station-Id	30	string
ATTRIBUTE	Calling-Station-Id	31	string
ATTRIBUTE	NAS-Identifier		32	string
ATTRIBUTE	Tunnel-Type		64	integer
ATTRIBUTE	Tunnel-Medium-Type	65	integer
ATTRIBUTE	Tunnel-Private-Group-ID	81	string

VALUE	Tunnel-Type		VLAN		13
VALUE	Tunnel-Medium-Type	IEEE-802	6
VALUE	Service-Type		Login-User	1
VALUE	Service-Type		Framed-User	2
"""
    
    # Write dictionary to temporary file
    dict_file = '/tmp/test_dictionary'
    with open(dict_file, 'w') as f:
        f.write(dict_content)
    
    try:
        # Create RADIUS client
        client = Client(server=server_host, authport=server_port, secret=secret.encode(),
                       dict=Dictionary(dict_file))
        
        # Test cases
        test_cases = [
            {
                'name': 'Exact MAC match',
                'mac': 'aa:bb:cc:dd:ee:ff',
                'expected_vlan': 100
            },
            {
                'name': 'MAC prefix match',
                'mac': 'aa:bb:cc:11:22:33',
                'expected_vlan': 200
            },
            {
                'name': 'No match (should reject)',
                'mac': 'ff:ff:ff:ff:ff:ff',
                'expected_vlan': None
            }
        ]
        
        results = []
        
        for test_case in test_cases:
            print(f"\nTest: {test_case['name']}")
            print(f"MAC: {test_case['mac']}")
            
            # Create authentication request
            req = client.CreateAuthPacket(code=packet.AccessRequest)
            req["User-Name"] = test_case['mac']
            req["Calling-Station-Id"] = test_case['mac']
            req["NAS-IP-Address"] = "192.168.1.1"
            req["NAS-Port"] = 1
            req["Service-Type"] = "Framed-User"
            
            try:
                # Send request
                reply = client.SendPacket(req)
                
                if reply.code == packet.AccessAccept:
                    vlan = reply.get('Tunnel-Private-Group-ID')
                    vlan_id = int(vlan[0]) if vlan else None
                    
                    print(f"Result: ACCESS-ACCEPT")
                    print(f"VLAN ID: {vlan_id}")
                    
                    # Check if result matches expected
                    if vlan_id == test_case['expected_vlan']:
                        print("✓ Test PASSED")
                        results.append(True)
                    else:
                        print(f"✗ Test FAILED - Expected VLAN {test_case['expected_vlan']}, got {vlan_id}")
                        results.append(False)
                        
                elif reply.code == packet.AccessReject:
                    print(f"Result: ACCESS-REJECT")
                    
                    if test_case['expected_vlan'] is None:
                        print("✓ Test PASSED")
                        results.append(True)
                    else:
                        print(f"✗ Test FAILED - Expected VLAN {test_case['expected_vlan']}, got reject")
                        results.append(False)
                else:
                    print(f"Unexpected response code: {reply.code}")
                    results.append(False)
                    
            except Exception as e:
                print(f"✗ Test FAILED - Request failed: {e}")
                results.append(False)
                
            time.sleep(0.5)  # Small delay between tests
        
        # Summary
        passed = sum(results)
        total = len(results)
        print(f"\n{'='*50}")
        print(f"Test Results: {passed}/{total} tests passed")
        print(f"{'='*50}")
        
        return passed == total
        
    except Exception as e:
        print(f"RADIUS client setup failed: {e}")
        return False
    finally:
        # Clean up
        if os.path.exists(dict_file):
            os.remove(dict_file)

def main():
    """Main test function"""
    print("UniFi Python RADIUS Server Test")
    print("=" * 40)
    
    # Setup test data
    test_mac, test_vlan = setup_test_data()
    if not test_mac:
        print("Failed to setup test data")
        sys.exit(1)
    
    # Wait a moment for server to be ready
    print("Waiting for RADIUS server to be ready...")
    time.sleep(2)
    
    # Run tests
    success = test_radius_auth()
    
    if success:
        print("\n🎉 All tests passed!")
        sys.exit(0)
    else:
        print("\n❌ Some tests failed!")
        sys.exit(1)

if __name__ == "__main__":
    main()