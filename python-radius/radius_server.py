#!/usr/bin/env python3
"""
Custom Python RADIUS Server for UniFi RADIUS Admin
Replaces FreeRADIUS with a lightweight Python implementation
Handles MAC-based authentication and VLAN assignment
"""

import os
import sys
import socket
import logging
import mysql.connector
from pyrad import dictionary, packet, server
from pyrad.server import RemoteHost
from pyrad.packet import AccessRequest, AccessAccept, AccessReject
import threading
import time
import signal
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

# Configure logging
log_file = os.getenv('LOG_FILE', '/var/log/radius-python.log')
log_handlers = [logging.StreamHandler(sys.stdout)]

# Try to add file handler if possible
try:
    os.makedirs(os.path.dirname(log_file), exist_ok=True)
    log_handlers.append(logging.FileHandler(log_file))
except (PermissionError, OSError):
    # If we can't write to the log file, just use stdout
    pass

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=log_handlers
)
logger = logging.getLogger(__name__)

class DatabaseManager:
    """
    Manages database connections and VLAN lookups for RADIUS authentication.
    
    This class handles all database operations needed for MAC-based authentication
    and VLAN assignment. It maintains connection pools for thread safety and
    provides methods for looking up VLAN assignments based on MAC addresses.
    
    Key responsibilities:
    - Maintain MySQL database connections with automatic reconnection
    - Normalize MAC addresses to a consistent format
    - Look up VLAN assignments (exact matches and manufacturer prefixes)
    - Provide fallback to default VLAN when configured
    - Log authentication attempts for troubleshooting
    
    For beginners:
        This class is like a librarian that helps find information about
        network devices. When a device tries to connect, it looks up what
        network permissions that device should have based on its unique
        identifier (MAC address).
    
    Attributes:
        config (dict): Database connection configuration
        connection_pool (dict): Thread-safe database connection pool
    """
    
    def __init__(self):
        """
        Initialize the DatabaseManager with configuration from environment variables.
        
        Sets up database connection parameters and initializes the connection pool.
        Configuration is read from environment variables to support different
        deployment environments (Docker, local development, production).
        
        Environment variables used:
        - DB_HOST: Database server hostname (default: localhost)
        - DB_NAME: Database name (default: radius)
        - DB_USER: Database username (default: radius_user) 
        - DB_PASS: Database password (default: radius_password)
        
        Args:
            None
        
        Returns:
            None
        """
        self.config = {
            'host': os.getenv('DB_HOST', 'localhost'),
            'database': os.getenv('DB_NAME', 'radius'),
            'user': os.getenv('DB_USER', 'radius_user'),
            'password': os.getenv('DB_PASS', 'radius_password'),
            'charset': 'utf8mb4',  # Full UTF-8 support for international characters
            'autocommit': True     # Automatically commit transactions
        }
        # Thread-safe connection pool to handle multiple simultaneous requests
        self.connection_pool = {}
        
    def get_connection(self):
        """
        Get a database connection from the thread-safe connection pool.
        
        Each thread gets its own database connection to prevent conflicts
        when multiple authentication requests are processed simultaneously.
        If a connection doesn't exist for the current thread, a new one
        is created and added to the pool.
        
        For beginners:
            Think of this like having multiple phone lines to a help desk.
            Each authentication request gets its own line so they don't
            interfere with each other.
        
        Args:
            None
        
        Returns:
            mysql.connector.connection: Database connection object, or None if connection fails
        
        Raises:
            mysql.connector.Error: If database connection cannot be established
        """
        thread_id = threading.get_ident()  # Get unique identifier for current thread
        
        # Check if this thread already has a connection
        if thread_id not in self.connection_pool:
            try:
                # Create new connection for this thread
                self.connection_pool[thread_id] = mysql.connector.connect(**self.config)
                logger.info(f"New database connection created for thread {thread_id}")
            except mysql.connector.Error as e:
                logger.error(f"Database connection failed: {e}")
                return None
                
        return self.connection_pool[thread_id]
    
    def normalize_mac(self, mac):
        """
        Normalize MAC address to a consistent lowercase format with colons.
        
        MAC addresses can come in many different formats from various network
        devices and systems. This function converts them all to a standard
        format for consistent database storage and lookup.
        
        Supported input formats:
        - AA:BB:CC:DD:EE:FF (uppercase with colons)
        - aa-bb-cc-dd-ee-ff (lowercase with dashes)  
        - aabbccddeeff (no separators)
        - AA-BB-CC-DD-EE-FF (uppercase with dashes)
        
        Output format: aa:bb:cc:dd:ee:ff (lowercase with colons)
        
        For beginners:
            This is like standardizing phone number formats. Whether someone
            writes (555) 123-4567 or 555-123-4567 or 5551234567, we convert
            them all to the same format for consistency.
        
        Args:
            mac (str): MAC address in any supported format
        
        Returns:
            str: Normalized MAC address in lowercase with colons, or None if invalid
        
        Example:
            >>> normalize_mac("AA:BB:CC:DD:EE:FF")
            "aa:bb:cc:dd:ee:ff"
            >>> normalize_mac("invalid-mac") 
            None
        """
        if not mac:
            return None
            
        # Remove any separators (colons, dashes, spaces) and convert to lowercase
        clean_mac = ''.join(c.lower() for c in mac if c.isalnum())
        
        # MAC address must be exactly 12 hexadecimal characters
        if len(clean_mac) != 12:
            return None
            
        # Verify all characters are valid hexadecimal
        try:
            int(clean_mac, 16)
        except ValueError:
            return None
            
        # Add colons every 2 characters: aabbccddeeff -> aa:bb:cc:dd:ee:ff
        return ':'.join(clean_mac[i:i+2] for i in range(0, 12, 2))
    
    def get_vlan_for_mac(self, mac):
        """
        Get VLAN assignment for a MAC address using hierarchical lookup strategy.
        
        This method implements a three-tier lookup strategy to find the appropriate
        VLAN assignment for a given MAC address:
        
        1. Exact Match: Look for the specific MAC address in the radreply table
        2. Prefix Match: Look for the manufacturer prefix (first 3 octets) in mac_prefixes table  
        3. Default VLAN: Use configured default VLAN if enabled and no matches found
        
        The hierarchical approach allows for:
        - Specific device assignments (highest priority)
        - Bulk manufacturer assignments (medium priority)  
        - Fallback default access (lowest priority)
        
        For beginners:
            This is like a security clearance system:
            1. First check if the person has individual clearance (exact match)
            2. Then check if their company has group clearance (prefix match)
            3. Finally give visitor access if default is enabled (default VLAN)
        
        Args:
            mac (str): MAC address in any supported format
        
        Returns:
            tuple: (vlan_id, match_type) where:
                - vlan_id (int): VLAN ID to assign, or None if no assignment
                - match_type (str): 'exact', 'prefix', 'default', or None
        
        Example:
            >>> get_vlan_for_mac("aa:bb:cc:dd:ee:ff")
            (100, 'exact')
            >>> get_vlan_for_mac("aa:bb:cc:11:22:33")  # Same manufacturer, different device
            (200, 'prefix')
        """
        # Step 1: Normalize the MAC address format
        normalized_mac = self.normalize_mac(mac)
        if not normalized_mac:
            logger.warning(f"Invalid MAC address format: {mac}")
            return None, None
            
        # Step 2: Get database connection
        conn = self.get_connection()
        if not conn:
            logger.error("Cannot perform VLAN lookup - no database connection")
            return None, None
            
        try:
            cursor = conn.cursor(dictionary=True)
            
            # Step 3: Try exact MAC match in radreply table (highest priority)
            # This table stores specific device assignments
            cursor.execute("""
                SELECT value FROM radreply 
                WHERE username = %s AND attribute = 'Tunnel-Private-Group-ID'
                LIMIT 1
            """, (normalized_mac,))
            
            result = cursor.fetchone()
            if result:
                vlan_id = int(result['value'])
                logger.info(f"Exact MAC match found: {normalized_mac} -> VLAN {vlan_id}")
                return vlan_id, 'exact'
            
            # Step 4: Try MAC prefix match (manufacturer-based assignment)
            # Extract first 3 octets (manufacturer identifier)
            mac_prefix = ':'.join(normalized_mac.split(':')[:3])
            cursor.execute("""
                SELECT vlan_id FROM mac_prefixes 
                WHERE prefix = %s
                LIMIT 1
            """, (mac_prefix,))
            
            result = cursor.fetchone()
            if result:
                vlan_id = int(result['vlan_id'])
                logger.info(f"MAC prefix match found: {mac_prefix} -> VLAN {vlan_id}")
                return vlan_id, 'prefix'
            
            # Step 5: Try default VLAN if enabled (fallback option)
            cursor.execute("""
                SELECT vlan_id FROM default_vlan_config 
                WHERE enabled = 1
                ORDER BY id DESC
                LIMIT 1
            """)
            
            result = cursor.fetchone()
            if result:
                vlan_id = int(result['vlan_id'])
                logger.info(f"Default VLAN assignment: {normalized_mac} -> VLAN {vlan_id}")
                return vlan_id, 'default'
            
            # No assignment found
            logger.info(f"No VLAN assignment found for MAC: {normalized_mac}")
            return None, None
            
        except mysql.connector.Error as e:
            logger.error(f"Database query error during VLAN lookup: {e}")
            return None, None
        finally:
            cursor.close()
    
    def log_radius_request(self, username, nas_ip=None, nas_port=None, called_station_id=None, 
                          calling_station_id=None, request_type='Access-Request', 
                          response_type='Access-Reject', reason=''):
        """
        Log RADIUS request to database for web interface
        """
        conn = self.get_connection()
        if not conn:
            logger.warning("Could not log RADIUS request - no database connection")
            return False
            
        try:
            cursor = conn.cursor()
            cursor.execute("""
                INSERT INTO radius_logs (
                    username, nas_ip_address, nas_port_id, called_station_id, 
                    calling_station_id, request_type, response_type, reason
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
            """, (
                username or '',
                nas_ip or '',
                nas_port or '',
                called_station_id or '',
                calling_station_id or '',
                request_type,
                response_type,
                reason
            ))
            return True
        except mysql.connector.Error as e:
            logger.error(f"Error logging RADIUS request: {e}")
            return False
        finally:
            cursor.close()

class UniFiRadiusServer(server.Server):
    """Custom RADIUS server for UniFi MAC authentication"""
    
    def __init__(self, database_manager, auth_port=1812, acct_port=1813):
        # Initialize RADIUS dictionary
        dict_path = os.path.join(os.path.dirname(__file__), 'dictionary')
        if not os.path.exists(dict_path):
            self.create_dictionary(dict_path)
        
        # Define RADIUS clients (NAS devices) that can connect
        # Use 0.0.0.0 as wildcard to accept any client IP with shared secret
        # This is appropriate for firewall-protected environments
        hosts = {
            '0.0.0.0': RemoteHost('0.0.0.0', b'testing123', 'any-client')
        }
        
        # Define addresses to listen on (all interfaces for Docker)
        addresses = ['0.0.0.0']  # Listen on all IPv4 addresses
        
        super().__init__(
            addresses=addresses,
            dict=dictionary.Dictionary(dict_path), 
            authport=auth_port, 
            acctport=acct_port, 
            hosts=hosts
        )
        
        self.database = database_manager
        logger.info(f"RADIUS server initialized on addresses {addresses} ports {auth_port}/{acct_port}")
    
    def create_dictionary(self, dict_path):
        """Create a basic RADIUS dictionary file"""
        dictionary_content = """
# Basic RADIUS dictionary for UniFi VLAN assignment
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

# Tunnel-Type values
VALUE	Tunnel-Type		VLAN		13

# Tunnel-Medium-Type values  
VALUE	Tunnel-Medium-Type	IEEE-802	6

# Service-Type values
VALUE	Service-Type		Login-User	1
VALUE	Service-Type		Framed-User	2
"""
        with open(dict_path, 'w') as f:
            f.write(dictionary_content)
        logger.info(f"Created RADIUS dictionary at {dict_path}")
    
    def HandleAuthPacket(self, pkt):
        """Handle RADIUS authentication packets"""
        reply = None
        try:
            # Extract MAC address from Calling-Station-Id or User-Name
            mac_address = None
            if 'Calling-Station-Id' in pkt:
                mac_address = pkt['Calling-Station-Id'][0]
            elif 'User-Name' in pkt:
                mac_address = pkt['User-Name'][0]
            
            # Extract additional packet information for logging
            nas_ip = pkt.get('NAS-IP-Address', [None])[0] if 'NAS-IP-Address' in pkt else str(pkt.source[0])
            nas_port = pkt.get('NAS-Port', [None])[0] if 'NAS-Port' in pkt else None
            called_station_id = pkt.get('Called-Station-Id', [None])[0] if 'Called-Station-Id' in pkt else None
            calling_station_id = pkt.get('Calling-Station-Id', [None])[0] if 'Calling-Station-Id' in pkt else None
            
            if not mac_address:
                logger.warning("No MAC address found in RADIUS request")
                # Log the failed request
                self.database.log_radius_request(
                    username='unknown',
                    nas_ip=nas_ip,
                    nas_port=nas_port,
                    called_station_id=called_station_id,
                    calling_station_id=calling_station_id,
                    request_type='Access-Request',
                    response_type='Access-Reject',
                    reason='No MAC address found in request'
                )
                reply = self.create_reject_packet(pkt)
            else:
                logger.info(f"Authentication request for MAC: {mac_address}")
                
                # Lookup VLAN assignment
                vlan_id, match_type = self.database.get_vlan_for_mac(mac_address)
                
                if vlan_id is None:
                    logger.info(f"Access denied for MAC {mac_address} - no VLAN assignment")
                    # Log the failed request
                    self.database.log_radius_request(
                        username=mac_address,
                        nas_ip=nas_ip,
                        nas_port=nas_port,
                        called_station_id=called_station_id,
                        calling_station_id=calling_station_id,
                        request_type='Access-Request',
                        response_type='Access-Reject',
                        reason=f'No VLAN assignment found for MAC {mac_address}'
                    )
                    reply = self.create_reject_packet(pkt)
                else:
                    # Create Access-Accept with VLAN assignment
                    reply = self.create_accept_packet(pkt, vlan_id)
                    logger.info(f"Access granted for MAC {mac_address} - VLAN {vlan_id} ({match_type} match)")
                    
                    # Log the successful request
                    self.database.log_radius_request(
                        username=mac_address,
                        nas_ip=nas_ip,
                        nas_port=nas_port,
                        called_station_id=called_station_id,
                        calling_station_id=calling_station_id,
                        request_type='Access-Request',
                        response_type='Access-Accept',
                        reason=f'VLAN {vlan_id} assigned via {match_type} match'
                    )
            
        except Exception as e:
            logger.error(f"Error handling auth packet: {e}")
            # Log the error
            self.database.log_radius_request(
                username=mac_address if 'mac_address' in locals() else 'unknown',
                nas_ip=nas_ip if 'nas_ip' in locals() else None,
                nas_port=nas_port if 'nas_port' in locals() else None,
                called_station_id=called_station_id if 'called_station_id' in locals() else None,
                calling_station_id=calling_station_id if 'calling_station_id' in locals() else None,
                request_type='Access-Request',
                response_type='Access-Reject',
                reason=f'Server error: {str(e)}'
            )
            reply = self.create_reject_packet(pkt)
        
        # Send the response packet
        if reply is not None and self.authfds:
            try:
                self.SendReplyPacket(self.authfds[0], reply)
                logger.info(f"Response packet sent: code={reply.code} to {reply.source}")
            except Exception as e:
                logger.error(f"Failed to send reply packet: {e}")
        else:
            logger.error(f"Unable to send response: reply={reply is not None}, authfds={len(self.authfds) if self.authfds else 0}")
    
    def create_accept_packet(self, request_pkt, vlan_id):
        """Create Access-Accept packet with VLAN assignment"""
        reply = request_pkt.CreateReply()
        reply.code = packet.AccessAccept
        reply.source = request_pkt.source  # Ensure source is set for sending
        
        # Add VLAN assignment attributes for UniFi
        reply.AddAttribute('Tunnel-Type', 13)  # VLAN
        reply.AddAttribute('Tunnel-Medium-Type', 6)  # IEEE-802
        reply.AddAttribute('Tunnel-Private-Group-ID', str(vlan_id))
        
        return reply
    
    def create_reject_packet(self, request_pkt):
        """Create Access-Reject packet"""
        reply = request_pkt.CreateReply()
        reply.code = packet.AccessReject
        reply.source = request_pkt.source  # Ensure source is set for sending
        return reply
    
    def HandleAcctPacket(self, pkt):
        """Handle RADIUS accounting packets (minimal implementation)"""
        # For now, just accept all accounting packets
        reply = pkt.CreateReply()
        return reply

def signal_handler(signum, frame):
    """Handle shutdown signals"""
    logger.info(f"Received signal {signum}, shutting down...")
    sys.exit(0)

def main():
    """Main server function"""
    # Register signal handlers
    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)
    
    logger.info("Starting UniFi Python RADIUS Server...")
    
    # Initialize database manager
    db_manager = DatabaseManager()
    
    # Test database connection
    if not db_manager.get_connection():
        logger.error("Failed to connect to database. Exiting.")
        sys.exit(1)
    
    # Initialize RADIUS server
    try:
        radius_server = UniFiRadiusServer(db_manager)
        logger.info("RADIUS server started successfully")
        
        # Run server
        radius_server.Run()
        
    except Exception as e:
        logger.error(f"Failed to start RADIUS server: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()