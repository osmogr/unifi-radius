# Docker Deployment Guide

This document provides instructions for deploying the UniFi RADIUS Admin Website using Docker containers.

## Architecture

The Docker setup consists of three containers:

1. **Web Container** (`unifi-radius-web`): Apache + PHP 8.1 hosting the admin interface
2. **Database Container** (`unifi-radius-db`): MySQL 8.0 with FreeRADIUS schema
3. **RADIUS Container** (`unifi-radius-python-radius`): Lite Python RADIUS with MySQL authentication

## Quick Start

### Prerequisites

- Docker Engine 20.10+
- Docker Compose 2.0+
- 2GB+ RAM available
- Ports 8555, 3306, 1812, 1813 available

### 1. Clone and Start

```bash
git clone <repository-url>
cd unifi-radius
docker-compose up -d
```

### 2. Access the Web Interface

- **URL**: http://dockerhost:8555
- **Username**: admin
- **Password**: admin123

### 3. Test RADIUS

```bash
# Test authentication (from host machine)
echo "User-Name=aa:bb:cc:dd:ee:f1,User-Password=test" | radclient localhost:1812 auth testing123

# Check logs
docker-compose logs python-radius
```

## Configuration

### Environment Variables

Create a `.env` file to customize database settings:

```env
# Database Configuration
DB_HOST=db
DB_NAME=radius
DB_USER=radius_user
DB_PASS=radius_password
MYSQL_ROOT_PASSWORD=rootpassword

# Web Interface Port
WEB_PORT=8555

# RADIUS Ports
RADIUS_AUTH_PORT=1812
RADIUS_ACCT_PORT=1813
```

### Custom FreeRADIUS Clients

Edit `freeradius/clients.conf` to add your UniFi Controller and Access Points:

```conf
# Your UniFi Controller
client unifi-controller {
    ipaddr = 192.168.1.1
    secret = your-radius-secret
    require_message_authenticator = no
    nastype = other
    shortname = unifi
}

# Your Access Points
client ap-range {
    ipaddr = 192.168.1.0/24
    secret = your-radius-secret
    require_message_authenticator = no
    nastype = other
}
```

### Database Persistence

The MySQL data is stored in a Docker volume `unifi-radius_db_data`. To backup:

```bash
# Backup database
docker-compose exec db mysqldump -u root -prootpassword radius > backup.sql

# Restore database
docker-compose exec -T db mysql -u root -prootpassword radius < backup.sql
```

## Management Commands

### Start/Stop Services

```bash
# Start all services
docker-compose up -d

# Stop all services
docker-compose down

# Restart a specific service
docker-compose restart web
docker-compose restart freeradius
```

### View Logs

```bash
# All logs
docker-compose logs

# Specific service logs
docker-compose logs web
docker-compose logs db
docker-compose logs freeradius

# Follow logs in real-time
docker-compose logs -f freeradius
```

### Database Access

```bash
# MySQL shell
docker-compose exec db mysql -u root -prootpassword radius

# View current VLAN assignments
docker-compose exec db mysql -u root -prootpassword radius -e "
SELECT r.username as mac_address, r.value as vlan_id, n.description 
FROM radreply r 
LEFT JOIN client_notes n ON r.username = n.mac 
WHERE r.attribute = 'Tunnel-Private-Group-ID';"
```

### FreeRADIUS Testing

```bash
# Test authentication with radtest (if available)
radtest aa:bb:cc:dd:ee:f1 test localhost:1812 0 testing123

# Test with netcat
echo -e "User-Name=aa:bb:cc:dd:ee:f1\nUser-Password=test\n" | \
  radclient localhost:1812 auth testing123

# View FreeRADIUS debug output
docker-compose exec freeradius freeradius -X
```

## Troubleshooting

### Common Issues

1. **Port conflicts**: Change ports in `docker-compose.yml`
2. **Database connection failed**: Check network connectivity between containers
3. **FreeRADIUS not starting**: Check client configuration and SQL connectivity
4. **Web interface timeout**: Increase PHP memory limit in `php.ini`

### Debug Commands

```bash
# Check container status
docker-compose ps

# Check network connectivity
docker-compose exec web ping db
docker-compose exec freeradius ping db

# Check database connection from web container
docker-compose exec web php -r "
new PDO('mysql:host=db;dbname=radius', 'radius_user', 'radius_password');
echo 'Database connection successful\n';"

# FreeRADIUS configuration test
docker-compose exec freeradius freeradius -C
```

### Log Locations

- **Web logs**: `logs/apache/`
- **FreeRADIUS logs**: `logs/freeradius/`
- **Database logs**: View with `docker-compose logs db`

## Production Deployment

### Security Hardening

1. **Change default passwords**:
   ```bash
   # Update database passwords
   vim .env
   
   # Update web admin password
   docker-compose exec web php -r "
   echo password_hash('new-password', PASSWORD_DEFAULT);"
   ```

2. **Use HTTPS**:
   - Add SSL certificate to Apache configuration
   - Update `apache.conf` for HTTPS redirect

3. **Firewall configuration**:
   ```bash
   # Allow only necessary ports
   ufw allow 8080/tcp  # Web interface
   ufw allow 1812/udp  # RADIUS auth
   ufw allow 1813/udp  # RADIUS accounting
   ```

4. **Update FreeRADIUS secrets**:
   - Generate strong shared secrets
   - Use different secrets per client/AP

### Monitoring

Add monitoring with Docker health checks:

```yaml
# In docker-compose.yml
healthcheck:
  test: ["CMD", "curl", "-f", "http://localhost/"]
  interval: 30s
  timeout: 10s
  retries: 3
```

### Backup Strategy

```bash
#!/bin/bash
# backup.sh - Daily backup script

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups"

# Database backup
docker-compose exec -T db mysqldump -u root -prootpassword radius > \
  "$BACKUP_DIR/radius_$DATE.sql"

# Configuration backup
tar -czf "$BACKUP_DIR/config_$DATE.tar.gz" \
  freeradius/ mysql-init/ docker-compose.yml

# Cleanup old backups (keep 30 days)
find "$BACKUP_DIR" -name "*.sql" -mtime +30 -delete
find "$BACKUP_DIR" -name "*.tar.gz" -mtime +30 -delete
```

## Integration with UniFi

### UniFi Controller Configuration

1. **RADIUS Settings** (Settings → Profiles → RADIUS):
   - **Server**: Docker host IP
   - **Port**: 1812
   - **Secret**: Match `clients.conf`

2. **SSID Configuration**:
   - **Security**: WPA2/WPA3 Enterprise
   - **RADIUS Profile**: Select configured profile
   - **VLAN**: Enable "Use RADIUS assigned VLAN"

3. **Guest Portal** (optional):
   - **Authentication**: RADIUS
   - **RADIUS Server**: Same as above

### Network Configuration

Ensure UniFi Controller can reach FreeRADIUS:

```bash
# Test from UniFi Controller server
telnet <docker-host-ip> 1812
```

## Performance Tuning

### Database Optimization

```sql
-- Add indexes for better performance
CREATE INDEX idx_radreply_username_attr ON radreply(username, attribute);
CREATE INDEX idx_radacct_session ON radacct(acctsessionid);

-- Optimize MySQL configuration
SET GLOBAL innodb_buffer_pool_size = 256M;
SET GLOBAL max_connections = 200;
```

### FreeRADIUS Optimization

Edit `freeradius/sites-available/default`:

```conf
# Increase connection limits
limit {
    max_connections = 32
    lifetime = 0
    idle_timeout = 60
}
```

### PHP/Apache Optimization

Update `php.ini`:

```ini
opcache.enable = 1
opcache.memory_consumption = 256
max_execution_time = 60
memory_limit = 512M
```

## Advanced Configuration

### Multiple RADIUS Servers

For high availability, deploy multiple FreeRADIUS containers:

```yaml
# docker-compose.yml
freeradius1:
  # ... same config
  container_name: unifi-radius-freeradius-1
  
freeradius2:
  # ... same config  
  container_name: unifi-radius-freeradius-2
  ports:
    - "1814:1812/udp"
    - "1815:1813/udp"
```

### Custom SQL Queries

Modify `freeradius/mods-available/sql` for custom authentication logic:

```sql
authorize_check_query = "
  SELECT id, username, attribute, value, op
  FROM radcheck 
  WHERE username = '%{SQL-User-Name}'
  AND custom_field = 'active'
  ORDER BY id"
```

### External Database

To use an external MySQL server, update `docker-compose.yml`:

```yaml
# Remove db service and update web/freeradius
environment:
  - DB_HOST=external-mysql-server.com
  - DB_NAME=radius
  - DB_USER=radius_user
  - DB_PASS=radius_password
```

## Support

For issues and questions:

1. Check container logs: `docker-compose logs`
2. Verify network connectivity between containers
3. Test individual components (web, database, FreeRADIUS)
4. Review FreeRADIUS debug output: `docker-compose exec freeradius freeradius -X`
5. Check UniFi Controller RADIUS logs

## References

- [FreeRADIUS Documentation](https://freeradius.org/documentation/)
- [UniFi RADIUS Configuration](https://help.ui.com/hc/en-us/articles/115000166827)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
