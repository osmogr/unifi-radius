#!/usr/bin/env python3
"""
Basic syntax and import test for Python RADIUS server.

This test script verifies that all required dependencies are properly installed
and that the custom RADIUS server modules can be imported successfully. It's
designed to catch configuration issues early before attempting to run the
full RADIUS server.

Purpose:
    - Verify all Python dependencies are installed (mysql-connector, pyrad, dotenv)
    - Test that custom RADIUS server modules can be imported
    - Validate basic DatabaseManager functionality
    - Ensure the development environment is properly configured

Usage:
    python3 test/test_syntax.py

For beginners:
    This is like a "health check" for the software - it makes sure all the
    pieces are in place before trying to run the main program. Think of it
    like checking that you have all ingredients before starting to cook.

Exit codes:
    0 - All tests passed
    1 - Some tests failed
"""

import sys
import os

# Add the python-radius directory to the path so we can import modules
sys.path.append(os.path.join(os.path.dirname(__file__), '..', 'python-radius'))

def test_imports():
    """
    Test that all required modules can be imported successfully.
    
    This function attempts to import all dependencies required for the RADIUS
    server to function. If any imports fail, it indicates missing dependencies
    that need to be installed via pip.
    
    Dependencies tested:
    - mysql.connector: For database connectivity
    - pyrad: Python RADIUS library for packet handling  
    - dotenv: For environment variable management
    - Custom modules: Our RADIUS server implementation
    
    For beginners:
        Think of this like checking that all the tools are in your toolbox
        before starting a project. If any tools are missing, you need to
        get them before you can continue.
    
    Returns:
        bool: True if all imports succeed, False if any fail
    """
    try:
        print("Testing imports...")
        
        # Test external dependencies
        import mysql.connector
        print("✓ mysql.connector imported successfully")
        
        import pyrad
        print("✓ pyrad imported successfully")
        
        from pyrad import dictionary, packet, server
        print("✓ pyrad submodules imported successfully")
        
        from pyrad.client import Client
        print("✓ pyrad.client imported successfully")
        
        from dotenv import load_dotenv
        print("✓ python-dotenv imported successfully")
        
        # Test our custom RADIUS server modules
        from radius_server import DatabaseManager, UniFiRadiusServer
        print("✓ Custom RADIUS server modules imported successfully")
        
        return True
        
    except ImportError as e:
        print(f"✗ Import failed: {e}")
        print("   Fix: Run 'pip install -r requirements.txt' to install missing dependencies")
        return False
    except Exception as e:
        print(f"✗ Unexpected error during import: {e}")
        return False

def test_database_manager():
    """
    Test DatabaseManager class instantiation and basic functionality.
    
    This function tests the core DatabaseManager class without requiring
    an actual database connection. It focuses on testing functionality
    that doesn't require external dependencies.
    
    Tests performed:
    - Class instantiation with default configuration
    - MAC address normalization (core functionality)
    - Configuration parameter handling
    
    For beginners:
        This is like testing individual parts of a machine before trying
        to run the whole thing. We make sure the basic pieces work before
        connecting to external systems like databases.
    
    Returns:
        bool: True if all tests pass, False if any fail
    """
    try:
        print("\nTesting DatabaseManager...")
        
        # Set environment variables for testing (these won't connect to real DB)
        os.environ['DB_HOST'] = 'localhost'
        os.environ['DB_NAME'] = 'radius'
        os.environ['DB_USER'] = 'radius_user'
        os.environ['DB_PASS'] = 'radius_password'
        
        from radius_server import DatabaseManager
        
        # Test class instantiation
        db_manager = DatabaseManager()
        print("✓ DatabaseManager instantiated successfully")
        
        # Test MAC normalization functionality (doesn't require DB connection)
        test_cases = [
            ("AA:BB:CC:DD:EE:FF", "aa:bb:cc:dd:ee:ff"),
            ("aa-bb-cc-dd-ee-ff", "aa:bb:cc:dd:ee:ff"),
            ("aabbccddeeff", "aa:bb:cc:dd:ee:ff"),
            ("invalid-mac", None),
            ("", None)
        ]
        
        for input_mac, expected in test_cases:
            result = db_manager.normalize_mac(input_mac)
            if result == expected:
                if expected:
                    print(f"✓ MAC normalization works: {input_mac} -> {result}")
                else:
                    print(f"✓ Invalid MAC rejected: {input_mac}")
            else:
                print(f"✗ MAC normalization failed: {input_mac} -> {result} (expected {expected})")
                return False
        
        return True
        
    except Exception as e:
        print(f"✗ DatabaseManager test failed: {e}")
        return False

def main():
    """
    Main test function that orchestrates all syntax and import tests.
    
    This function runs all test modules in sequence and reports the overall
    result. It's designed to provide a quick pass/fail status for the
    development environment setup.
    
    Tests performed:
    1. Import tests - verify all dependencies are available
    2. DatabaseManager tests - verify core functionality works
    
    For beginners:
        This function acts like a "master switch" that runs all the individual
        tests and gives you a simple pass/fail result. Green means everything
        is working, red means something needs to be fixed.
    
    Returns:
        int: Exit code (0 for success, 1 for failure)
    """
    print("Python RADIUS Server - Syntax and Import Test")
    print("=" * 50)
    
    success = True
    
    # Test 1: Check all imports work
    if not test_imports():
        success = False
    
    # Test 2: Check DatabaseManager basic functionality  
    if not test_database_manager():
        success = False
    
    # Report overall results
    print("\n" + "=" * 50)
    if success:
        print("🎉 All tests passed! Python RADIUS server code is ready.")
        print("   You can now run the full server or integration tests.")
        return 0
    else:
        print("❌ Some tests failed! Please check the code.")
        print("   Review the error messages above and fix any issues.")
        return 1

if __name__ == "__main__":
    """
    Entry point when script is run directly.
    
    This allows the test script to be executed from the command line while
    also being importable as a module for use in other test scripts.
    
    Usage:
        python3 test/test_syntax.py
    """
    sys.exit(main())