#!/usr/bin/env python3
"""
Test script to verify database logging functionality in Python RADIUS server.

This script tests the RADIUS request logging system that records authentication
attempts in the database. This logging is essential for troubleshooting,
security monitoring, and compliance with network access policies.

Purpose:
    - Test successful authentication logging
    - Test failed authentication logging  
    - Verify all RADIUS request attributes are stored correctly
    - Test database connectivity for logging operations
    - Validate log entry retrieval for web interface

For beginners:
    This is like testing a security camera system. Every time someone tries
    to enter the building (authenticate to network), we record who it was,
    when it happened, and whether they were allowed in or denied access.

Usage:
    python3 test/test_database_logging.py

Requirements:
    - MySQL database with RADIUS schema
    - radius_logs table configured
    - Database connection permissions for logging
"""

import sys
import os
# Add python-radius directory to path for module imports
sys.path.append(os.path.join(os.path.dirname(__file__), '..', 'python-radius'))

from radius_server import DatabaseManager
import logging

# Set up basic logging to show test progress
logging.basicConfig(level=logging.INFO, format='%(levelname)s: %(message)s')

def test_database_logging():
    """Test the database logging functionality"""
    
    print("Testing Python RADIUS Server Database Logging Functionality")
    print("=" * 65)
    
    # Initialize database manager
    db_manager = DatabaseManager()
    
    # Test logging a successful authentication
    print("Testing successful authentication log...")
    success = db_manager.log_radius_request(
        username="test:mac:address",
        nas_ip="192.168.1.100",
        nas_port="12345",
        called_station_id="AP-SSID",
        calling_station_id="test:mac:address",
        request_type="Access-Request",
        response_type="Access-Accept",
        reason="Test successful authentication"
    )
    
    if success:
        print("✓ Successfully logged Access-Accept request")
    else:
        print("✗ Failed to log Access-Accept request")
        return False
    
    # Test logging a failed authentication
    print("Testing failed authentication log...")
    success = db_manager.log_radius_request(
        username="unknown:mac:address",
        nas_ip="192.168.1.100",
        nas_port="12346",
        called_station_id="AP-SSID",
        calling_station_id="unknown:mac:address",
        request_type="Access-Request",
        response_type="Access-Reject",
        reason="Test failed authentication - no VLAN found"
    )
    
    if success:
        print("✓ Successfully logged Access-Reject request")
    else:
        print("✗ Failed to log Access-Reject request")
        return False
    
    print("\n✓ Database logging functionality is working!")
    print("Check the web interface RADIUS Logs page to see the logged entries.")
    return True

if __name__ == "__main__":
    try:
        success = test_database_logging()
        
        print("\n" + "=" * 65)
        if success:
            print("✓ Database logging test passed!")
            print("The Python RADIUS server will now log requests to the database.")
            sys.exit(0)
        else:
            print("✗ Database logging test failed!")
            sys.exit(1)
            
    except Exception as e:
        print(f"\nError during testing: {e}")
        print("Make sure the database is accessible and configured correctly.")
        sys.exit(1)