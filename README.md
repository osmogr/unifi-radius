# UniFi RADIUS Admin Website

A complete LAMP-based admin website for managing UniFi WiFi clients and VLAN assignments. Now supports both FreeRADIUS and a lightweight Python RADIUS server implementation.

## Features

- **RADIUS Server: Python RADIUS server (lightweight)

- **VLAN Management**: Assign VLANs to MAC addresses using standard RADIUS attributes
- **MAC Prefix Support**: Assign VLANs to vendor prefixes (first 3 octets) for bulk device management
- **UniFi API Integration**: Import wireless clients directly from UniFi Controller
- **Secure Admin Interface**: Session-based authentication with CSRF protection
- **Responsive UI**: Bootstrap 5 interface with responsive design
- **Client Management**: Add, edit, delete, and view client assignments
- **Search & Filter**: Find clients by MAC address, description, or VLAN
- **Bulk Import**: Select and import multiple clients from UniFi with VLAN assignments

## Quick Start with Docker

The easiest way to get started is using Docker Compose:

```bash
# Clone the repository
git clone https://github.com/osmogr/unifi-radius.git
cd unifi-radius

# Start all services
docker compose up -d

# Access the web interface
# http://localhost:8080
# Username: admin, Password: admin123
```

For detailed Docker setup instructions, see [DOCKER.md](docs/DOCKER.md).

## Integration with UniFi

### UniFi Controller Configuration

1. **RADIUS Settings** (Settings → Profiles → RADIUS):
   - **Server**: Docker host IP
   - **Port**: 1812
   - **Secret**: Match `radius_server.py` (testing123 is the default)

2. **SSID Configuration**:
   - **Security**: WPA2/WPA3 Enterprise
   - **RADIUS Profile**: Select configured profile
   - **VLAN**: Enable "Use RADIUS assigned VLAN"

## Default Login

- **Username**: `admin`
- **Password**: `admin123`

**Important**: Change the default credentials in production by updating the `admin_users` table or modifying `website/auth.php`.

## UniFi Controller Integration

### API Access

To import clients from UniFi Controller, you need:

1. **Controller Access**: HTTPS access to UniFi Controller (port 8443)
2. **Admin Credentials**: Username/password with API access
3. **Site ID**: Usually "default" unless using multiple sites

### Supported Data

The import fetches the following data for wireless clients:
- MAC address
- Device name/hostname
- IP address
- Connected SSID
- Access Point MAC
- Last seen timestamp
- Traffic statistics

### Security Notes

- UniFi credentials are **not stored** permanently
- SSL certificate verification is disabled for self-signed certificates
- Only wireless clients are imported (wired clients are filtered out)
- Session data is cleared after import completion

## Usage

### Adding Clients Manually

1. Go to **Add Client**
2. Enter MAC address (any format: `aa:bb:cc:dd:ee:ff`, `aa-bb-cc-dd-ee-ff`, or `aabbccddeeff`)
3. Assign VLAN ID (1-4094)
4. Add optional description
5. Submit to create RADIUS assignment

### Managing MAC Vendor Prefixes

1. Go to **MAC Prefixes**
2. Enter vendor prefix (first 3 octets: `aa:bb:cc`, `aa-bb-cc`, or `aabbcc`)
3. Assign VLAN ID (1-4094) 
4. Add optional description (vendor name, device type, etc.)
5. Submit to create prefix assignment

**How Prefix Matching Works:**
- When a device connects, exact MAC matches are checked first
- If no exact match is found, the system checks for vendor prefix matches
- Prefix matches use the first 3 octets (vendor OUI) of the MAC address
- Exact matches always take precedence over prefix matches
- Useful for assigning all devices from a specific vendor to a VLAN

### Importing from UniFi

1. Go to **Import from UniFi**
2. Enter Controller IP/hostname
3. Provide admin credentials
4. Select site ID (default: "default")
5. Choose clients to import
6. Assign VLAN IDs
7. Add descriptions (optional)
8. Import selected clients

### Managing Existing Clients

- **View Clients**: See all assignments with search/filter
- **Edit Client**: Update VLAN assignment or description  
- **Delete Client**: Remove client and all associated data
- **MAC Prefixes**: Manage vendor prefix to VLAN assignments

## Security Features

- **Session Management**: Secure PHP sessions with timeout
- **CSRF Protection**: All forms include CSRF tokens
- **Input Validation**: MAC addresses and VLAN IDs are validated
- **Parameterized Queries**: All database interactions use prepared statements
- **Password Security**: Supports password hashing (update `auth.php` for production)

## File Structure

```
├── docker-compose.yml   # Docker Compose configuration
├── Dockerfile           # Web container build configuration
├── README.md            # This file
├── DOCKER.md           # Docker-specific documentation
├── test-docker-setup.sh # Test script for Docker setup
├── website/            # Web application files
│   ├── index.php           # Main dashboard and login
│   ├── view_clients.php    # View all client assignments
│   ├── add_client.php      # Add new client form
│   ├── edit_client.php     # Edit existing client
│   ├── delete_client.php   # Delete client confirmation
│   ├── mac_prefixes.php    # MAC vendor prefix management
│   ├── import_unifi.php    # UniFi Controller integration
│   ├── db.php              # Database connection and helpers
│   ├── auth.php            # Authentication system
│   ├── .htaccess           # Apache configuration
│   ├── apache.conf         # Apache virtual host configuration
│   └── php.ini             # PHP configuration
└── mysql-init/         # MySQL initialization scripts
    ├── 01-schema.sql      # Database schema
    └── 02-freeradius-tables.sh # FreeRADIUS table setup
```

## API Reference

### Database Functions

```php
// Get VLAN for MAC address
$vlan = getVlanForMac($mac);

// Set VLAN assignment
$success = setVlanForMac($mac, $vlan);

// Remove client completely
$success = removeVlanForMac($mac);

// Get all clients with details
$clients = getAllClients();

// Update client description
$success = updateClientNotes($mac, $description);
```

### MAC Prefix Functions

```php
// Get VLAN for MAC prefix
$vlan = getVlanForMacPrefix($prefix);

// Set VLAN assignment for prefix
$success = setVlanForMacPrefix($prefix, $vlan, $description);

// Remove prefix assignment
$success = removeVlanForMacPrefix($prefix);

// Get all MAC prefixes
$prefixes = getAllMacPrefixes();

// Enhanced lookup (exact + prefix)
$result = getVlanForMacEnhanced($mac);
// Returns: ['vlan' => 100, 'match_type' => 'exact|prefix', 'matched_value' => 'aa:bb:cc:dd:ee:ff']
```

### Validation Functions

```php
// Validate MAC address format
$valid = isValidMac($mac);

// Normalize MAC to lowercase with colons
$normalized = normalizeMac($mac);

// Validate VLAN ID range
$valid = isValidVlan($vlan);

// Validate MAC prefix format (3 octets)
$valid = isValidMacPrefix($prefix);

// Normalize MAC prefix
$normalized = normalizeMacPrefix($prefix);
```

## Troubleshooting

### UniFi Connection Issues

1. Verify Controller is accessible via HTTPS
2. Check firewall rules (port 8443)
3. Ensure admin credentials are correct
4. Try connecting from same network as web server

## License

This project is provided as-is for educational and production use. Modify as needed for your environment.

