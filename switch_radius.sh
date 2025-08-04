#!/bin/bash
# Switch between FreeRADIUS and Python RADIUS server

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
COMPOSE_FILE="$SCRIPT_DIR/docker-compose.yml"

usage() {
    echo "Usage: $0 [python|freeradius]"
    echo ""
    echo "Switch between RADIUS server implementations:"
    echo "  python     - Use lightweight Python RADIUS server (default)"
    echo "  freeradius - Use traditional FreeRADIUS server"
    echo ""
    echo "Current configuration:"
    if grep -q "python-radius:" "$COMPOSE_FILE"; then
        echo "  ✓ Python RADIUS server (active)"
    fi
    if grep -q "freeradius:" "$COMPOSE_FILE"; then
        echo "  ✓ FreeRADIUS server (active)"
    fi
    exit 1
}

switch_to_python() {
    echo "Switching to Python RADIUS server..."
    
    # Check if already using Python
    if grep -q "python-radius:" "$COMPOSE_FILE" && ! grep -q "freeradius:" "$COMPOSE_FILE"; then
        echo "Already using Python RADIUS server."
        return 0
    fi
    
    # Create backup
    cp "$COMPOSE_FILE" "$COMPOSE_FILE.backup"
    
    # Remove FreeRADIUS service if present
    if grep -q "freeradius:" "$COMPOSE_FILE"; then
        echo "Removing FreeRADIUS service..."
        # This is a simple approach - in practice you'd want more sophisticated YAML editing
        sed -i '/freeradius:/,/^  [a-zA-Z]/ { /^  [a-zA-Z]/!d; }' "$COMPOSE_FILE"
        sed -i '/freeradius:/d' "$COMPOSE_FILE"
    fi
    
    # Add Python RADIUS service if not present
    if ! grep -q "python-radius:" "$COMPOSE_FILE"; then
        echo "Adding Python RADIUS service..."
        cat >> "$COMPOSE_FILE" << 'EOF'

  python-radius:
    build:
      context: .
      dockerfile: Dockerfile.python-radius
    container_name: unifi-radius-python
    volumes:
      - ./logs/python-radius:/var/log
      - ./python-radius/.env:/app/.env:ro
    environment:
      - DB_HOST=db
      - DB_NAME=radius
      - DB_USER=radius_user
      - DB_PASS=radius_password
    depends_on:
      db:
        condition: service_healthy
    networks:
      - radius-network
    restart: unless-stopped
    labels:
      - traefik.enable=true
      - traefik.udp.routers.radius-auth.entrypoints=radius-auth
      - traefik.udp.routers.radius-auth.service=python-radius-auth
      - traefik.udp.services.python-radius-auth.loadbalancer.server.port=1812
      - traefik.udp.routers.radius-acct.entrypoints=radius-acct
      - traefik.udp.routers.radius-acct.service=python-radius-acct
      - traefik.udp.services.python-radius-acct.loadbalancer.server.port=1813
EOF
    fi
    
    echo "✓ Switched to Python RADIUS server"
    echo "Run 'docker compose up -d' to apply changes"
}

switch_to_freeradius() {
    echo "Switching to FreeRADIUS server..."
    
    # Check if already using FreeRADIUS
    if grep -q "freeradius:" "$COMPOSE_FILE" && ! grep -q "python-radius:" "$COMPOSE_FILE"; then
        echo "Already using FreeRADIUS server."
        return 0
    fi
    
    # Create backup
    cp "$COMPOSE_FILE" "$COMPOSE_FILE.backup"
    
    # Remove Python RADIUS service if present
    if grep -q "python-radius:" "$COMPOSE_FILE"; then
        echo "Removing Python RADIUS service..."
        sed -i '/python-radius:/,/^  [a-zA-Z]/ { /^  [a-zA-Z]/!d; }' "$COMPOSE_FILE"
        sed -i '/python-radius:/d' "$COMPOSE_FILE"
    fi
    
    # Add FreeRADIUS service if not present
    if ! grep -q "freeradius:" "$COMPOSE_FILE"; then
        echo "Adding FreeRADIUS service..."
        cat >> "$COMPOSE_FILE" << 'EOF'

  freeradius:
    image: freeradius/freeradius-server:latest
    container_name: unifi-radius-freeradius
    volumes:
      - ./freeradius:/etc/freeradius:ro
      - ./logs/freeradius:/var/log/freeradius
    environment:
      - DB_HOST=db
      - DB_NAME=radius
      - DB_USER=radius_user
      - DB_PASS=radius_password
    depends_on:
      db:
        condition: service_healthy
    networks:
      - radius-network
    restart: unless-stopped
    labels:
      - traefik.enable=true
      - traefik.udp.routers.radius-auth.entrypoints=radius-auth
      - traefik.udp.routers.radius-auth.service=freeradius-auth
      - traefik.udp.services.freeradius-auth.loadbalancer.server.port=1812
      - traefik.udp.routers.radius-acct.entrypoints=radius-acct
      - traefik.udp.routers.radius-acct.service=freeradius-acct
      - traefik.udp.services.freeradius-acct.loadbalancer.server.port=1813
EOF
    fi
    
    echo "✓ Switched to FreeRADIUS server"
    echo "Run 'docker compose up -d' to apply changes"
}

# Main logic
case "${1:-}" in
    python)
        switch_to_python
        ;;
    freeradius)
        switch_to_freeradius
        ;;
    *)
        usage
        ;;
esac