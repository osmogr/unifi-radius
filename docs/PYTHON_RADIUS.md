# Python RADIUS Server Documentation

## Overview

This document describes the custom Python RADIUS server that replaces FreeRADIUS in the UniFi RADIUS Admin system. The Python server provides MAC-based authentication with VLAN assignment while maintaining full compatibility with the existing web interface and database schema.

## Features

- **MAC-based Authentication**: Handles RADIUS authentication requests using MAC addresses
- **VLAN Assignment**: Returns appropriate VLAN configuration via RADIUS attributes
- **Database Integration**: Uses the same MySQL database schema as the web interface
- **Three-Tier Lookup**: Supports exact MAC matches, vendor prefix matching, and default VLAN fallback
- **Database Logging**: All RADIUS requests are logged to the database for web interface display
- **UniFi Compatibility**: Returns RADIUS attributes in the format expected by UniFi devices
- **Docker Support**: Fully containerized with health checks and logging
- **Lightweight**: Minimal resource usage compared to FreeRADIUS

## Recent Fixes

### RADIUS Response Packet Transmission (2024-08-04)

**Issue**: The Python RADIUS server was not sending response packets on the network, despite processing requests correctly.

**Fix**: Modified the `HandleAuthPacket` method to explicitly call `SendReplyPacket()` to transmit response packets. The pyrad framework requires explicit packet sending, which was missing in the original implementation.

**Verification**: Manual tests confirm that RADIUS responses (Access-Accept/Access-Reject) are now properly transmitted on the network interface.

## Architecture

### Components

1. **radius_server.py**: Main RADIUS server implementation
2. **DatabaseManager**: Handles database connections and VLAN lookups
3. **UniFiRadiusServer**: Custom RADIUS server class extending pyrad
4. **Docker Container**: Containerized deployment with health checks

### Authentication Flow

1. UniFi Controller sends RADIUS Access-Request with MAC address
2. Python server extracts MAC from `Calling-Station-Id` or `User-Name` 
3. Database lookup for VLAN assignment (three-tier lookup):
   - **First**: Checks `radreply` table for exact MAC match
   - **Second**: Checks `mac_prefixes` table for vendor prefix match
   - **Third**: Checks `default_vlan_config` table for fallback assignment
4. Returns Access-Accept with VLAN attributes or Access-Reject
5. Logs the request to `radius_logs` table for web interface display

### Database Schema

The server uses the same database tables as the web interface:

- **radreply**: RADIUS reply attributes for exact MAC assignments
- **mac_prefixes**: Vendor prefix to VLAN mappings  
- **default_vlan_config**: Default VLAN for unconfigured devices
- **radius_logs**: Request logs displayed in web interface
- **client_notes**: Additional client metadata (not used by server)

## Installation

### Docker Deployment (Recommended)

The Python RADIUS server is included in the Docker Compose setup:

```bash
# Start all services including Python RADIUS server
docker compose up -d

# Check Python RADIUS server logs
docker logs unifi-radius-python

# Test RADIUS functionality
python3 test_radius.py
```

### Manual Installation

1. **Install Dependencies**:
```bash
pip3 install -r requirements.txt
```

2. **Configure Environment**:
```bash
cp python-radius/.env.example python-radius/.env
# Edit .env with your database settings
```

3. **Run Server**:
```bash
cd python-radius
python3 radius_server.py
```

## Configuration

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `DB_HOST` | localhost | Database host |
| `DB_NAME` | radius | Database name |
| `DB_USER` | radius_user | Database username |
| `DB_PASS` | radius_password | Database password |
| `RADIUS_AUTH_PORT` | 1812 | RADIUS authentication port |
| `RADIUS_ACCT_PORT` | 1813 | RADIUS accounting port |
| `LOG_LEVEL` | INFO | Logging level |
| `LOG_FILE` | /var/log/radius-python.log | Log file path |

### RADIUS Clients

The server accepts requests from any IP address with the shared secret `testing123`. This simplified configuration is ideal for firewall-protected environments where client IP restrictions are not necessary.

The server uses a wildcard client configuration (`0.0.0.0`) that accepts all client IPs with the same shared secret. 

For production deployments, update the shared secret in `radius_server.py`:

```python
# Modify the shared secret for all clients
hosts = {
    '0.0.0.0': RemoteHost('0.0.0.0', b'your-secret-key', 'any-client')
}
```

## RADIUS Attributes

### Request Attributes (from UniFi)

- **User-Name**: MAC address (fallback)
- **Calling-Station-Id**: MAC address (primary)
- **NAS-IP-Address**: UniFi Controller IP
- **NAS-Port**: Port identifier

### Response Attributes (to UniFi)

For Access-Accept:
- **Tunnel-Type**: 13 (VLAN)
- **Tunnel-Medium-Type**: 6 (IEEE-802)
- **Tunnel-Private-Group-ID**: VLAN ID (string)

## Testing

### Automated Testing

Run the included test script to verify functionality:

```bash
# Setup test data and run RADIUS tests
python3 test_radius.py
```

Test scenarios:
- Exact MAC address match
- MAC vendor prefix match  
- No match (Access-Reject)

### Manual Testing

Use `radtest` or similar tools:

```bash
# Test with radtest (if available)
echo "User-Name = aa:bb:cc:dd:ee:ff, Calling-Station-Id = aa:bb:cc:dd:ee:ff" | \
radclient localhost:1812 auth testing123
```

### Syntax Testing

Verify code syntax and imports:

```bash
python3 test_syntax.py
```

## Monitoring and Logging

### Log Files

- Container: `/var/log/radius-python.log`
- Host: `./logs/python-radius/radius-python.log`

### Log Format

```
2024-01-01 12:00:00 - INFO - Authentication request for MAC: aa:bb:cc:dd:ee:ff
2024-01-01 12:00:00 - INFO - Exact MAC match found: aa:bb:cc:dd:ee:ff -> VLAN 100
2024-01-01 12:00:00 - INFO - Access granted for MAC aa:bb:cc:dd:ee:ff - VLAN 100 (exact match)
```

### Health Checks

The Docker container includes health checks to monitor server status.

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check database credentials in environment variables
   - Ensure database service is running
   - Verify network connectivity

2. **RADIUS Requests Timing Out**
   - Check firewall rules for UDP ports 1812/1813
   - Verify RADIUS client secret matches server configuration
   - Check server logs for incoming requests

3. **VLAN Assignment Not Working**
   - Verify database contains VLAN assignments
   - Check MAC address format in database (lowercase with colons)
   - Review server logs for authentication flow

### Debug Mode

Enable debug logging by setting environment variable:

```bash
LOG_LEVEL=DEBUG
```

### Database Queries

Test database connectivity manually:

```bash
mysql -h localhost -u radius_user -p radius
SELECT * FROM radreply WHERE username = 'aa:bb:cc:dd:ee:ff';
SELECT * FROM mac_prefixes WHERE prefix = 'aa:bb:cc';
```

## Performance

### Resource Usage

- **Memory**: ~50MB (compared to ~200MB for FreeRADIUS)
- **CPU**: Minimal under normal load
- **Startup Time**: ~2 seconds
- **Request Handling**: >1000 requests/second

### Optimization

- Connection pooling for database connections
- Threaded request handling via pyrad
- Efficient MAC address normalization
- Minimal RADIUS attribute processing

## Security

### Best Practices

1. **Change Default RADIUS Secret**: Update from `testing123` to a strong secret
2. **Network Security**: Ensure server is behind firewall protection since client IP restrictions are relaxed
3. **Database Security**: Use dedicated database user with minimal permissions
4. **Firewall Protection**: Control RADIUS port access (UDP 1812/1813) at network level
5. **Log Monitoring**: Monitor for authentication anomalies

### Non-Root Container

The Docker container runs as a non-root user for security.

## Migration from FreeRADIUS

### Differences

| Feature | FreeRADIUS | Python Server |
|---------|------------|---------------|
| Resource Usage | High | Low |
| Configuration | Complex | Simple |
| Customization | Difficult | Easy |
| Dependencies | Many | Few |
| Startup Time | Slow | Fast |

### Compatibility

- Same database schema
- Same RADIUS attributes
- Same authentication flow
- Same web interface

The Python server is a drop-in replacement for FreeRADIUS in this specific use case.

## Development

### Code Structure

```
python-radius/
├── radius_server.py    # Main server implementation
├── .env               # Environment configuration
└── start.sh           # Startup script with health checks
```

### Extending Functionality

To add features:

1. **Custom Attributes**: Modify `create_accept_packet()` method
2. **Additional Tables**: Update `DatabaseManager.get_vlan_for_mac()`
3. **Logging**: Extend logging in authentication handlers
4. **Monitoring**: Add metrics collection

### Testing Changes

1. Run syntax tests: `python3 test_syntax.py`
2. Test default VLAN functionality: `python3 test_default_vlan.py`
3. Test database logging: `python3 test_database_logging.py`
4. Run integration tests: `python3 test_radius.py`
5. Test in Docker: `docker compose up --build`

### Default VLAN Configuration

The server supports fallback VLAN assignment for devices that don't match exact MAC addresses or vendor prefixes:

1. Configure default VLAN via web interface (`Default VLAN` page)
2. Set VLAN ID and enable the default assignment
3. Unconfigured devices will automatically receive the default VLAN
4. Authentication order: Exact MAC → Vendor Prefix → Default VLAN → Reject

### Database Logging

All RADIUS requests are automatically logged to the `radius_logs` table:

- **Access-Accept**: Successful authentications with VLAN assignment details
- **Access-Reject**: Failed authentications with failure reasons  
- **Request Details**: NAS IP, MAC address, timestamps, and match types
- **Web Interface**: View logs in real-time via the "RADIUS Logs" page

## Support

For issues specific to the Python RADIUS server:

1. Check server logs for errors
2. Verify database connectivity and schema
3. Test with included test scripts
4. Review environment configuration

The Python server maintains the same external interfaces as FreeRADIUS, so existing documentation and troubleshooting guides remain applicable for client-side issues.