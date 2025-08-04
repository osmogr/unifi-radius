# UniFi RADIUS Admin Website

A complete LAMP-based admin website for managing UniFi WiFi clients and VLAN assignments. Now supports both FreeRADIUS and a lightweight Python RADIUS server implementation.

## Features

- **RADIUS Server Options**: Choose between FreeRADIUS (full-featured) or Python RADIUS server (lightweight)
- **FreeRADIUS Integration**: Compatible with FreeRADIUS default schema using `radcheck` and `radreply` tables
- **VLAN Management**: Assign VLANs to MAC addresses using standard RADIUS attributes
- **MAC Prefix Support**: Assign VLANs to vendor prefixes (first 3 octets) for bulk device management
- **UniFi API Integration**: Import wireless clients directly from UniFi Controller
- **Secure Admin Interface**: Session-based authentication with CSRF protection
- **Responsive UI**: Bootstrap 5 interface with responsive design
- **Client Management**: Add, edit, delete, and view client assignments
- **Search & Filter**: Find clients by MAC address, description, or VLAN
- **Bulk Import**: Select and import multiple clients from UniFi with VLAN assignments

## Requirements

- **Web Server**: Apache/Nginx with PHP 8.0+
- **Database**: MySQL/MariaDB 5.7+
- **PHP Extensions**: PDO, cURL, JSON
- **RADIUS Server**: FreeRADIUS or Python RADIUS server (included)
- **UniFi Controller**: For API integration (optional)

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

For detailed Docker setup instructions, see [DOCKER.md](DOCKER.md).

For Python RADIUS server setup and configuration, see [PYTHON_RADIUS.md](PYTHON_RADIUS.md).

## Manual Installation

### 1. Database Setup

Create a MySQL/MariaDB database and import the schema:

```sql
CREATE DATABASE radius DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Import the schema:
```bash
mysql -u root -p radius < website/schema.sql
```

### 2. Database Configuration

Edit `website/db.php` and update the database connection settings:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'radius');
define('DB_USER', 'radius_user');
define('DB_PASS', 'radius_password');
```

### 3. Web Server Configuration

Place all files from the `website/` directory in your web server document root or a subdirectory.

**Apache .htaccess** (optional):
```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

<Files "db.php">
    Require all denied
</Files>

<Files "auth.php">
    Require all denied
</Files>
```

### 4. PHP Configuration

Ensure the following PHP extensions are enabled:
- `pdo_mysql`
- `curl`
- `json`
- `session`

### 5. File Permissions

Set appropriate permissions:
```bash
chmod 644 website/*.php
chmod 600 website/db.php  # Protect database config
```

## Default Login

- **Username**: `admin`
- **Password**: `admin123`

**Important**: Change the default credentials in production by updating the `admin_users` table or modifying `website/auth.php`.

## RADIUS Server Integration

This system supports two RADIUS server options:

### Python RADIUS Server (Default)

A lightweight, custom Python implementation that provides:
- Low resource usage (~50MB memory vs ~200MB for FreeRADIUS)
- Fast startup (~2 seconds)
- Easy customization and debugging
- Same database schema and compatibility

See [PYTHON_RADIUS.md](PYTHON_RADIUS.md) for detailed documentation.

### FreeRADIUS Integration (Alternative)

For traditional FreeRADIUS deployment, the application uses the standard FreeRADIUS schema with these key tables:

- **`radcheck`**: User authentication (not used for MAC-based auth)
- **`radreply`**: RADIUS reply attributes for VLAN assignments
- **`client_notes`**: Additional table for client descriptions and timestamps  
- **`mac_prefixes`**: MAC vendor prefix to VLAN assignments

### VLAN Assignment Attributes

For each MAC address (stored as username), the following attributes are set in `radreply`:

```
Tunnel-Type := VLAN
Tunnel-Medium-Type := IEEE-802  
Tunnel-Private-Group-ID := <vlan_id>
```

### MAC Prefix Matching

When no exact MAC match is found, the system automatically checks for vendor prefix matches:

1. **Exact Match**: First checks `radreply` table for exact MAC address
2. **Prefix Match**: If no exact match, checks `mac_prefixes` table for vendor OUI match
3. **Response**: Returns appropriate VLAN assignment based on match type

The SQL query supports both exact and prefix matching with proper precedence.

### FreeRADIUS Configuration

Ensure your FreeRADIUS configuration includes:

**sites-available/default**:
```
authorize {
    sql
}
```

**mods-available/sql** (MySQL configuration):
```
sql {
    driver = "mysql"
    server = "localhost"
    port = 3306
    login = "radius_user"
    password = "radius_password"
    radius_db = "radius"
}
```

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
│   ├── schema.sql          # Database schema
│   ├── .htaccess           # Apache configuration
│   ├── apache.conf         # Apache virtual host configuration
│   └── php.ini             # PHP configuration
├── freeradius/         # FreeRADIUS configuration files
│   ├── clients.conf        # RADIUS clients configuration
│   ├── sql.conf           # SQL module configuration
│   ├── mods-available/    # Available modules
│   └── sites-available/   # Available sites
└── mysql-init/         # MySQL initialization scripts
    ├── 01-schema.sql      # Database schema (symlinked)
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

### Database Connection Issues

1. Verify MySQL/MariaDB service is running
2. Check database credentials in `db.php`
3. Ensure database user has proper permissions
4. Test connection: `mysql -u radius_user -p radius`

### UniFi Connection Issues

1. Verify Controller is accessible via HTTPS
2. Check firewall rules (port 8443)
3. Ensure admin credentials are correct
4. Try connecting from same network as web server

### FreeRADIUS Integration Issues

1. Check SQL module is enabled in FreeRADIUS
2. Verify database connection in FreeRADIUS config
3. Test with `radtest` command
4. Check FreeRADIUS logs: `/var/log/freeradius/radius.log`

### Common Error Messages

- **"Database connection failed"**: Check `db.php` configuration
- **"Invalid MAC address format"**: Use format `aa:bb:cc:dd:ee:ff`
- **"VLAN ID must be between 1 and 4094"**: Check VLAN range
- **"Failed to connect to UniFi Controller"**: Check network connectivity and credentials

## Production Deployment

### Security Hardening

1. **Change Default Credentials**:
   ```sql
   UPDATE admin_users SET password_hash = PASSWORD_HASH('new_password') WHERE username = 'admin';
   ```

2. **Use HTTPS**: Configure SSL/TLS certificate

3. **Database Security**: 
   - Use dedicated database user with minimal permissions
   - Enable MySQL SSL connections
   - Regular database backups

4. **File Permissions**:
   ```bash
   chmod 640 db.php
   chown www-data:www-data *.php
   ```

5. **Web Server Security**:
   - Hide PHP version headers
   - Disable directory browsing
   - Configure proper error handling

### Performance Optimization

1. **PHP OpCache**: Enable PHP OpCache for better performance
2. **Database Indexing**: Ensure proper indexes on `radreply.username`
3. **Session Storage**: Consider Redis/Memcached for session storage
4. **CDN**: Use CDN for Bootstrap CSS/JS if needed

## License

This project is provided as-is for educational and production use. Modify as needed for your environment.

## Support

For issues and questions:
1. Check this README for common solutions
2. Review FreeRADIUS documentation
3. Verify UniFi Controller API documentation
4. Test with simplified configurations first