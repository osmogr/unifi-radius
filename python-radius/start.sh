#!/bin/bash
# Startup script for Python RADIUS Server
# Handles environment setup and health checks

set -e

# Environment variables with defaults
DB_HOST=${DB_HOST:-localhost}
DB_NAME=${DB_NAME:-radius}
DB_USER=${DB_USER:-radius_user}
DB_PASS=${DB_PASS:-radius_password}
RADIUS_AUTH_PORT=${RADIUS_AUTH_PORT:-1812}
RADIUS_ACCT_PORT=${RADIUS_ACCT_PORT:-1813}

echo "Starting UniFi Python RADIUS Server..."
echo "Database: ${DB_USER}@${DB_HOST}/${DB_NAME}"
echo "Ports: AUTH=${RADIUS_AUTH_PORT}, ACCT=${RADIUS_ACCT_PORT}"

# Wait for database to be ready
echo "Waiting for database connection..."
timeout=60
count=0
while ! python3 -c "
import mysql.connector
import sys
import os
try:
    conn = mysql.connector.connect(
        host='${DB_HOST}',
        database='${DB_NAME}',
        user='${DB_USER}',
        password='${DB_PASS}',
        connect_timeout=5
    )
    conn.close()
    print('Database connection successful')
except Exception as e:
    print(f'Database connection failed: {e}')
    sys.exit(1)
" 2>/dev/null; do
    count=$((count + 1))
    if [ $count -gt $timeout ]; then
        echo "Database connection timeout after ${timeout} seconds"
        exit 1
    fi
    echo "Waiting for database... (${count}/${timeout})"
    sleep 1
done

echo "Database is ready!"

# Check if required tables exist
echo "Verifying database schema..."
python3 -c "
import mysql.connector
import sys

config = {
    'host': '${DB_HOST}',
    'database': '${DB_NAME}', 
    'user': '${DB_USER}',
    'password': '${DB_PASS}'
}

try:
    conn = mysql.connector.connect(**config)
    cursor = conn.cursor()
    
    # Check required tables
    required_tables = ['radreply', 'mac_prefixes']
    cursor.execute('SHOW TABLES')
    existing_tables = [table[0] for table in cursor.fetchall()]
    
    missing_tables = [table for table in required_tables if table not in existing_tables]
    if missing_tables:
        print(f'Missing required tables: {missing_tables}')
        sys.exit(1)
    
    print('Database schema verification successful')
    cursor.close()
    conn.close()
    
except Exception as e:
    print(f'Database schema verification failed: {e}')
    sys.exit(1)
"

if [ $? -ne 0 ]; then
    echo "Database schema verification failed"
    exit 1
fi

echo "Starting RADIUS server..."
exec python3 radius_server.py