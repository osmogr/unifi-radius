#!/usr/bin/env python3
"""
Demo script showing Python RADIUS server MAC authentication logic.

This script demonstrates how the Python RADIUS server processes MAC-based
authentication requests for UniFi wireless clients. It works without requiring
a database connection, making it perfect for understanding the authentication
logic and testing the system components.

Purpose:
    - Show MAC address normalization (converting different formats to standard format)
    - Demonstrate VLAN lookup logic (exact matches and vendor prefix matches)
    - Illustrate RADIUS attribute generation for network access control
    - Provide complete authentication flow examples

Usage:
    python3 demo_radius.py

Requirements:
    - Python 3.6 or higher
    - Access to the radius_server module in python-radius directory

For beginners:
    This script is educational and shows step-by-step how network devices
    (like UniFi access points) authenticate wireless clients based on their
    MAC addresses and assign them to specific VLANs (network segments).
"""

import sys
import os

# Add the python-radius directory to the path so we can import the server modules
# This allows us to use the same code that the actual RADIUS server uses
sys.path.append('/home/runner/work/unifi-radius/unifi-radius/python-radius')

def demo_mac_normalization():
    """
    Demonstrate MAC address normalization functionality.
    
    MAC addresses can come in many different formats from different devices:
    - Uppercase with colons: AA:BB:CC:DD:EE:FF
    - Lowercase with dashes: aa-bb-cc-dd-ee-ff  
    - No separators: aabbccddeeff
    
    This function shows how the RADIUS server converts all these formats
    into a standard lowercase format with colons for consistent database storage.
    
    For beginners:
        A MAC address is a unique identifier for network devices, like a
        serial number. Different systems format them differently, but we
        need a consistent format for our database.
    
    Returns:
        None (prints results to console)
    """
    print("MAC Address Normalization Demo")
    print("-" * 40)
    
    # Import our database manager for MAC normalization
    from radius_server import DatabaseManager
    
    # Create an instance of the database manager to use its normalization function
    db_manager = DatabaseManager()
    
    # Test cases showing different MAC address formats that might be received
    test_macs = [
        "AA:BB:CC:DD:EE:FF",        # Uppercase with colons (common format)
        "aa-bb-cc-dd-ee-ff",        # Lowercase with dashes (some systems use this)
        "aabbccddeeff",             # No separators (compact format)
        "AA-BB-CC-DD-EE-FF",        # Uppercase with dashes (mixed format)
        "aa:bb:cc:dd:ee:ff",        # Already normalized (should stay the same)
        "invalid-mac",              # Invalid format (should be rejected)
        "AA:BB:CC:DD:EE",           # Too short (missing octets)
        "AA:BB:CC:DD:EE:FF:GG"      # Too long (extra octets)
    ]
    
    # Test each MAC address format
    for mac in test_macs:
        normalized = db_manager.normalize_mac(mac)
        status = "✓" if normalized else "✗"  # Success or failure indicator
        print(f"{status} {mac:<20} -> {normalized or 'INVALID'}")
    
    print()

def demo_vlan_lookup_logic():
    """
    Demonstrate VLAN lookup logic with mock data.
    
    This function shows how the RADIUS server determines which VLAN (network segment)
    a device should be assigned to based on its MAC address. There are two types
    of matches:
    
    1. Exact Match: The complete MAC address is found in the database
    2. Prefix Match: Only the first 3 octets (manufacturer identifier) match
    
    For beginners:
        VLANs are like separate network segments that provide different levels
        of access. For example:
        - VLAN 100 might be for trusted employee devices
        - VLAN 200 might be for guest devices with limited access
        - VLAN 300 might be for IoT devices with restricted internet access
    
    Args:
        None
    
    Returns:
        None (prints results to console)
    """
    print("VLAN Lookup Logic Demo")
    print("-" * 40)
    
    # Mock database data - this simulates what would be stored in the real database
    # In production, this data comes from the MySQL radreply table
    mock_radreply = {
        "aa:bb:cc:dd:ee:ff": 100,  # Specific device assigned to VLAN 100
        "11:22:33:44:55:66": 200,  # Specific device assigned to VLAN 200
        "aa:bb:cc:77:88:99": 150,  # Another specific device on VLAN 150
    }
    
    # Mock MAC prefix data - assigns VLANs based on manufacturer
    # The first 3 octets of a MAC address identify the device manufacturer
    mock_mac_prefixes = {
        "aa:bb:cc": 300,  # All devices from this manufacturer get VLAN 300
        "11:22:33": 400,  # All devices from this manufacturer get VLAN 400
        "ff:ee:dd": 500,  # All devices from this manufacturer get VLAN 500
    }
    
    def mock_vlan_lookup(mac):
        """
        Mock version of database VLAN lookup functionality.
        
        This function simulates how the real RADIUS server looks up VLAN
        assignments. It first tries to find an exact MAC address match,
        then falls back to checking manufacturer prefixes.
        
        Args:
            mac (str): MAC address to look up (any format)
        
        Returns:
            tuple: (vlan_id, match_type) or (None, None) if no match found
                - vlan_id: Integer VLAN ID to assign
                - match_type: 'exact' or 'prefix' indicating match type
        """
        from radius_server import DatabaseManager
        
        db_manager = DatabaseManager()
        normalized_mac = db_manager.normalize_mac(mac)
        
        # If MAC address is invalid, return no match
        if not normalized_mac:
            return None, None
        
        # Check exact match first (highest priority)
        if normalized_mac in mock_radreply:
            return mock_radreply[normalized_mac], 'exact'
        
        # Check prefix match (fallback for bulk manufacturer assignments)
        mac_prefix = ':'.join(normalized_mac.split(':')[:3])  # Get first 3 octets
        if mac_prefix in mock_mac_prefixes:
            return mock_mac_prefixes[mac_prefix], 'prefix'
        
        # No match found
        return None, None
    
    # Test cases demonstrating different lookup scenarios
    test_cases = [
        "aa:bb:cc:dd:ee:ff",  # Exact match (should return VLAN 100)
        "AA:BB:CC:77:88:99",  # Different exact match (should return VLAN 150)
        "aa:bb:cc:11:22:33",  # Prefix match only (should return VLAN 300)
        "ff:ee:dd:11:22:33",  # Different prefix match (should return VLAN 500)
        "99:88:77:66:55:44",  # No match (should return None)
        "invalid-mac",        # Invalid MAC (should return None)
    ]
    
    # Test each case and display results
    for mac in test_cases:
        vlan_id, match_type = mock_vlan_lookup(mac)
        
        if vlan_id:
            print(f"✓ {mac:<20} -> VLAN {vlan_id} ({match_type} match)")
        else:
            print(f"✗ {mac:<20} -> No VLAN assignment")
    
    print()

def demo_radius_attributes():
    """
    Demonstrate RADIUS attribute generation for network access control.
    
    RADIUS attributes are key-value pairs that tell network equipment how to
    handle authenticated devices. When a device is authenticated, the RADIUS
    server responds with specific attributes that configure network access.
    
    Key RADIUS attributes for VLAN assignment:
    - Tunnel-Type: Specifies the tunneling protocol (13 = VLAN)
    - Tunnel-Medium-Type: Specifies the medium (6 = IEEE-802 for Ethernet/WiFi)
    - Tunnel-Private-Group-ID: The actual VLAN ID number
    
    For beginners:
        Think of RADIUS attributes like instructions sent to the wireless
        access point telling it which network segment (VLAN) to put the
        device on, and what level of access to provide.
    
    Args:
        None
    
    Returns:
        None (prints results to console)
    """
    print("RADIUS Attribute Generation Demo")
    print("-" * 40)
    
    def create_mock_radius_response(vlan_id):
        """
        Create mock RADIUS response attributes.
        
        This function simulates how the RADIUS server creates response packets
        with the appropriate attributes for VLAN assignment.
        
        Args:
            vlan_id (int or None): VLAN ID to assign, or None for rejection
        
        Returns:
            dict: RADIUS response packet structure with code and attributes
        """
        if vlan_id is None:
            # No VLAN found = deny access
            return {"code": "Access-Reject"}
        
        # VLAN found = grant access with VLAN assignment
        return {
            "code": "Access-Accept",
            "attributes": {
                "Tunnel-Type": 13,  # VLAN tunneling protocol
                "Tunnel-Medium-Type": 6,  # IEEE-802 (Ethernet/WiFi)
                "Tunnel-Private-Group-ID": str(vlan_id)  # The actual VLAN number
            }
        }
    
    # Test different scenarios
    test_vlans = [100, 200, None]  # None represents no VLAN found (reject)
    
    for vlan in test_vlans:
        response = create_mock_radius_response(vlan)
        print(f"VLAN {vlan or 'None'} (No VLAN = Reject Access):")
        print(f"  Code: {response['code']}")
        
        # Only show attributes for accepted connections
        if 'attributes' in response:
            print("  Attributes sent to access point:")
            for attr, value in response['attributes'].items():
                print(f"    {attr}: {value}")
        print()

def demo_authentication_flow():
    """
    Demonstrate complete authentication flow from request to response.
    
    This function shows the complete process that happens when a wireless device
    tries to connect to a UniFi network:
    
    1. Device connects to WiFi
    2. Access point sends RADIUS authentication request
    3. RADIUS server looks up device MAC address
    4. Server responds with accept/reject and VLAN assignment
    5. Access point places device on appropriate network segment
    
    For beginners:
        This is like showing your ID card at a building entrance. The security
        guard (access point) checks with the main office (RADIUS server) to see
        if you're allowed in and which floor/area you can access (VLAN assignment).
    
    Args:
        None
    
    Returns:
        None (prints results to console)
    """
    print("Complete Authentication Flow Demo")
    print("-" * 40)
    
    # Mock RADIUS request data - this simulates real authentication requests
    # that would come from UniFi access points
    mock_requests = [
        {
            "name": "UniFi Client - Exact Match",
            "calling_station_id": "aa:bb:cc:dd:ee:ff",  # Device MAC address
            "nas_ip": "192.168.1.10",  # Access point IP address
            "expected_result": "Access-Accept with VLAN 100"
        },
        {
            "name": "UniFi Client - Prefix Match", 
            "calling_station_id": "aa:bb:cc:11:22:33",  # Different device, same manufacturer
            "nas_ip": "192.168.1.10",
            "expected_result": "Access-Accept with VLAN 300"
        },
        {
            "name": "Unknown Device",
            "calling_station_id": "99:88:77:66:55:44",  # Unrecognized device
            "nas_ip": "192.168.1.10",
            "expected_result": "Access-Reject"
        }
    ]
    
    # Process each mock request to show authentication flow
    for i, request in enumerate(mock_requests, 1):
        print(f"Request {i}: {request['name']}")
        print(f"  MAC Address: {request['calling_station_id']}")
        print(f"  NAS IP: {request['nas_ip']}")
        print(f"  Expected: {request['expected_result']}")
        
        # Simulate processing (using our previous demo functions)
        from radius_server import DatabaseManager
        db_manager = DatabaseManager()
        
        # Mock database data (same as before) - in production this comes from MySQL
        mock_radreply = {
            "aa:bb:cc:dd:ee:ff": 100,  # Specific device assignments
            "11:22:33:44:55:66": 200,
        }
        mock_mac_prefixes = {
            "aa:bb:cc": 300,  # Manufacturer-based assignments
            "11:22:33": 400,
        }
        
        # Step 1: Normalize the MAC address from the request
        normalized_mac = db_manager.normalize_mac(request['calling_station_id'])
        vlan_id = None
        match_type = None
        
        # Step 2: Look up VLAN assignment if MAC is valid
        if normalized_mac:
            # Check for exact match first
            if normalized_mac in mock_radreply:
                vlan_id = mock_radreply[normalized_mac]
                match_type = 'exact'
            else:
                # Fall back to prefix match
                mac_prefix = ':'.join(normalized_mac.split(':')[:3])
                if mac_prefix in mock_mac_prefixes:
                    vlan_id = mock_mac_prefixes[mac_prefix]
                    match_type = 'prefix'
        
        # Step 3: Generate response based on lookup results
        if vlan_id:
            print(f"  ✓ Result: Access-Accept with VLAN {vlan_id} ({match_type} match)")
        else:
            print(f"  ✗ Result: Access-Reject (no VLAN assignment)")
        
        print()

def main():
    """
    Main demo function that orchestrates all demonstration modules.
    
    This function runs through all the demo modules in a logical order to
    provide a complete understanding of how the Python RADIUS server works.
    It's designed to be educational for users with limited coding experience.
    
    Demo modules included:
    1. MAC address normalization - how different formats are standardized
    2. VLAN lookup logic - how devices get assigned to network segments  
    3. RADIUS attributes - how network configuration is communicated
    4. Authentication flow - complete end-to-end process
    
    Args:
        None
    
    Returns:
        None (prints educational output to console)
    """
    print("Python RADIUS Server - Functionality Demo")
    print("=" * 50)
    print("This demo shows how the Python RADIUS server processes")
    print("MAC authentication requests without requiring a database.")
    print()
    print("What you'll learn:")
    print("• How MAC addresses are normalized for consistent storage")
    print("• How VLAN assignments are determined (exact vs prefix matching)")
    print("• How RADIUS attributes control network access")
    print("• Complete authentication flow from request to response")
    print("=" * 50)
    print()
    
    # Run demos in logical order
    demo_mac_normalization()
    demo_vlan_lookup_logic()
    demo_radius_attributes()
    demo_authentication_flow()
    
    print("Demo Complete!")
    print("-" * 50)
    print("The Python RADIUS server provides:")
    print("✓ MAC address normalization and validation")
    print("✓ Exact MAC address matching for specific device assignments") 
    print("✓ Vendor prefix matching for bulk manufacturer assignments")
    print("✓ Standard RADIUS attribute responses for VLAN assignment")
    print("✓ UniFi Controller compatibility for seamless integration")
    print()
    print("Next steps for full testing:")
    print("1. Run 'docker compose up -d' to start all services with database")
    print("2. Run 'python3 test/test_radius.py' for comprehensive integration tests")
    print("3. Access web interface at http://localhost:8080 to manage clients")
    print()
    print("For more information, see the README.md file.")

if __name__ == "__main__":
    main()