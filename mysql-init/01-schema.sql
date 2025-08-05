-- FreeRADIUS Database Schema with UniFi RADIUS Admin Website
-- Compatible with FreeRADIUS default schema

-- Create database (uncomment if needed)
-- CREATE DATABASE radius DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE radius;

-- FreeRADIUS radcheck table
CREATE TABLE IF NOT EXISTS radcheck (
    id int(11) NOT NULL AUTO_INCREMENT,
    username varchar(64) NOT NULL DEFAULT '',
    attribute varchar(64) NOT NULL DEFAULT '',
    op char(2) NOT NULL DEFAULT '==',
    value varchar(253) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FreeRADIUS radreply table  
CREATE TABLE IF NOT EXISTS radreply (
    id int(11) NOT NULL AUTO_INCREMENT,
    username varchar(64) NOT NULL DEFAULT '',
    attribute varchar(64) NOT NULL DEFAULT '',
    op char(2) NOT NULL DEFAULT '=',
    value varchar(253) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Additional table for client notes and descriptions
CREATE TABLE IF NOT EXISTS client_notes (
    id int(11) NOT NULL AUTO_INCREMENT,
    mac varchar(17) NOT NULL,
    description text,
    last_seen timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_mac (mac),
    KEY idx_mac (mac)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create an admin user for the web interface (optional)
-- Password: admin123 (change this in production!)
-- You can create this manually or use the web interface
CREATE TABLE IF NOT EXISTS admin_users (
    id int(11) NOT NULL AUTO_INCREMENT,
    username varchar(50) NOT NULL,
    password_hash varchar(255) NOT NULL,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user (username: admin, password: admin123)
-- Password hash for 'admin123' using PHP password_hash()
INSERT IGNORE INTO admin_users (username, password_hash) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Sample data for testing
-- Example MAC address entries with VLAN assignments
INSERT IGNORE INTO radreply (username, attribute, op, value) VALUES
-- VLAN 10 assignment for MAC aa:bb:cc:dd:ee:f1
('aa:bb:cc:dd:ee:f1', 'Tunnel-Type', ':=', 'VLAN'),
('aa:bb:cc:dd:ee:f1', 'Tunnel-Medium-Type', ':=', 'IEEE-802'),
('aa:bb:cc:dd:ee:f1', 'Tunnel-Private-Group-ID', ':=', '10'),

-- VLAN 20 assignment for MAC aa:bb:cc:dd:ee:f2  
('aa:bb:cc:dd:ee:f2', 'Tunnel-Type', ':=', 'VLAN'),
('aa:bb:cc:dd:ee:f2', 'Tunnel-Medium-Type', ':=', 'IEEE-802'),
('aa:bb:cc:dd:ee:f2', 'Tunnel-Private-Group-ID', ':=', '20');

-- Sample client notes
INSERT IGNORE INTO client_notes (mac, description) VALUES
('aa:bb:cc:dd:ee:f1', 'Test Device 1 - Development'),
('aa:bb:cc:dd:ee:f2', 'Test Device 2 - Guest Network');

-- RADIUS request logs table for tracking authentication requests
CREATE TABLE IF NOT EXISTS radius_logs (
    id int(11) NOT NULL AUTO_INCREMENT,
    timestamp timestamp DEFAULT CURRENT_TIMESTAMP,
    username varchar(64) NOT NULL,
    nas_ip_address varchar(45),
    nas_port_id varchar(32),
    called_station_id varchar(64),
    calling_station_id varchar(64),
    framed_ip_address varchar(15),
    request_type enum('Access-Request', 'Accounting-Request', 'CoA-Request') DEFAULT 'Access-Request',
    response_type enum('Access-Accept', 'Access-Reject', 'Access-Challenge', 'Accounting-Response') DEFAULT 'Access-Reject',
    reason varchar(255),
    PRIMARY KEY (id),
    KEY idx_timestamp (timestamp),
    KEY idx_username (username),
    KEY idx_nas_ip (nas_ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample RADIUS logs for demonstration
INSERT IGNORE INTO radius_logs (username, nas_ip_address, nas_port_id, called_station_id, calling_station_id, request_type, response_type, reason) VALUES
('aa:bb:cc:dd:ee:f1', '192.168.1.10', '1234567890', '00:11:22:33:44:55:Guest-WiFi', 'aa:bb:cc:dd:ee:f1', 'Access-Request', 'Access-Accept', 'User authenticated successfully'),
('aa:bb:cc:dd:ee:f2', '192.168.1.10', '1234567891', '00:11:22:33:44:55:Guest-WiFi', 'aa:bb:cc:dd:ee:f2', 'Access-Request', 'Access-Accept', 'User authenticated successfully'),
('bb:cc:dd:ee:ff:01', '192.168.1.10', '1234567892', '00:11:22:33:44:55:Guest-WiFi', 'bb:cc:dd:ee:ff:01', 'Access-Request', 'Access-Reject', 'User not found in database');

-- MAC vendor prefix assignments table for prefix-based VLAN assignment
CREATE TABLE IF NOT EXISTS mac_prefixes (
    id int(11) NOT NULL AUTO_INCREMENT,
    prefix varchar(8) NOT NULL COMMENT 'MAC prefix in format aa:bb:cc',
    vlan_id int(11) NOT NULL,
    description text,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_prefix (prefix),
    KEY idx_prefix (prefix),
    KEY idx_vlan (vlan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample MAC prefix data for testing
INSERT IGNORE INTO mac_prefixes (prefix, vlan_id, description) VALUES
('aa:bb:cc', 100, 'Sample Vendor A - Development Devices'),
('dd:ee:ff', 200, 'Sample Vendor B - Guest Devices');

-- Default VLAN configuration table for fallback assignment
CREATE TABLE IF NOT EXISTS default_vlan_config (
    id int(11) NOT NULL AUTO_INCREMENT,
    vlan_id int(11) NOT NULL,
    description text,
    enabled tinyint(1) NOT NULL DEFAULT 1,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_enabled (enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default configuration (VLAN 999 for unconfigured devices)
INSERT IGNORE INTO default_vlan_config (vlan_id, description, enabled) VALUES 
(999, 'Default VLAN for unconfigured devices', 1);
