#!/bin/bash
# Test script for UniFi RADIUS Docker setup

set -e

echo "=== UniFi RADIUS Docker Setup Test ==="
echo

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print status
print_status() {
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓${NC} $1"
    else
        echo -e "${RED}✗${NC} $1"
        exit 1
    fi
}

echo "1. Testing Docker Compose configuration..."
docker compose config --quiet
print_status "Docker Compose configuration is valid"

echo "2. Testing PHP syntax of core files..."
php -l website/db.php > /dev/null
print_status "db.php syntax is valid"

php -l website/index.php > /dev/null  
print_status "index.php syntax is valid"

php -l website/auth.php > /dev/null
print_status "auth.php syntax is valid"

php -l website/add_client.php > /dev/null
print_status "add_client.php syntax is valid"

php -l website/view_clients.php > /dev/null
print_status "view_clients.php syntax is valid"

echo "3. Testing database helper functions..."
php -r "
require_once 'website/db.php';
if (function_exists('isValidMac')) {
    echo 'MAC validation function exists\n';
    if (isValidMac('aa:bb:cc:dd:ee:ff')) {
        echo 'Valid MAC test passed\n';
    } else {
        echo 'Valid MAC test failed\n';
        exit(1);
    }
    if (!isValidMac('invalid-mac')) {
        echo 'Invalid MAC test passed\n';
    } else {
        echo 'Invalid MAC test failed\n';
        exit(1);
    }
} else {
    echo 'MAC validation function missing\n';
    exit(1);
}

if (function_exists('isValidVlan')) {
    echo 'VLAN validation function exists\n';
    if (isValidVlan(100) && !isValidVlan(5000)) {
        echo 'VLAN validation tests passed\n';
    } else {
        echo 'VLAN validation tests failed\n';
        exit(1);
    }
} else {
    echo 'VLAN validation function missing\n';
    exit(1);
}

if (function_exists('normalizeMac')) {
    \$normalized = normalizeMac('aabbccddeeff');
    if (\$normalized === 'aa:bb:cc:dd:ee:ff') {
        echo 'MAC normalization test passed\n';
    } else {
        echo 'MAC normalization test failed\n';
        exit(1);
    }
} else {
    echo 'MAC normalization function missing\n';
    exit(1);
}
"
print_status "Database helper functions are working"

echo "4. Building Docker web container..."
docker compose build web > /dev/null
print_status "Web container builds successfully"

echo "5. Testing FreeRADIUS configuration files..."
if [ -f "freeradius/clients.conf" ] && [ -f "freeradius/mods-available/sql" ]; then
    echo -e "${GREEN}✓${NC} FreeRADIUS config files exist"
else
    echo -e "${RED}✗${NC} FreeRADIUS config files missing"
    exit 1
fi

echo "6. Testing database schema..."
if [ -f "website/schema.sql" ] && grep -q "radcheck" website/schema.sql && grep -q "radreply" website/schema.sql; then
    echo -e "${GREEN}✓${NC} Database schema includes required FreeRADIUS tables"
else
    echo -e "${RED}✗${NC} Database schema missing required tables"
    exit 1
fi

echo "7. Testing MySQL initialization scripts..."
if [ -f "mysql-init/02-freeradius-tables.sh" ] && [ -x "mysql-init/02-freeradius-tables.sh" ]; then
    echo -e "${GREEN}✓${NC} MySQL initialization script is executable"
else
    echo -e "${RED}✗${NC} MySQL initialization script missing or not executable"
    exit 1
fi

echo
echo -e "${GREEN}=== All tests passed! ===${NC}"
echo
echo "To start the full Docker environment:"
echo "  docker compose up -d"
echo
echo "To access the web interface:"
echo "  http://localhost:8080"
echo "  Username: admin"
echo "  Password: admin123"
echo
echo "To test FreeRADIUS (once running):"
echo "  echo 'User-Name=aa:bb:cc:dd:ee:f1,User-Password=test' | radclient localhost:1812 auth testing123"
echo
echo "To view logs:"
echo "  docker compose logs -f"