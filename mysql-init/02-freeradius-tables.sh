#!/bin/bash
# Additional MySQL initialization for FreeRADIUS

# Wait for MySQL to be ready
while ! mysqladmin ping -h"localhost" --silent; do
    echo "Waiting for MySQL to be ready..."
    sleep 2
done

echo "MySQL is ready, creating additional tables..."

# Create additional FreeRADIUS tables that might be needed
mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" <<EOF

-- Additional FreeRADIUS tables for complete functionality

-- User groups table (for group-based policies)
CREATE TABLE IF NOT EXISTS radusergroup (
    username varchar(64) NOT NULL DEFAULT '',
    groupname varchar(64) NOT NULL DEFAULT '',
    priority int(11) NOT NULL DEFAULT '1',
    KEY username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Group check attributes
CREATE TABLE IF NOT EXISTS radgroupcheck (
    id int(11) NOT NULL AUTO_INCREMENT,
    groupname varchar(64) NOT NULL DEFAULT '',
    attribute varchar(64) NOT NULL DEFAULT '',
    op char(2) NOT NULL DEFAULT '==',
    value varchar(253) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY groupname (groupname)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Group reply attributes  
CREATE TABLE IF NOT EXISTS radgroupreply (
    id int(11) NOT NULL AUTO_INCREMENT,
    groupname varchar(64) NOT NULL DEFAULT '',
    attribute varchar(64) NOT NULL DEFAULT '',
    op char(2) NOT NULL DEFAULT '=',
    value varchar(253) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY groupname (groupname)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Accounting table
CREATE TABLE IF NOT EXISTS radacct (
    radacctid bigint(21) NOT NULL AUTO_INCREMENT,
    acctsessionid varchar(64) NOT NULL DEFAULT '',
    acctuniqueid varchar(32) NOT NULL DEFAULT '',
    username varchar(64) NOT NULL DEFAULT '',
    groupname varchar(64) NOT NULL DEFAULT '',
    realm varchar(64) DEFAULT '',
    nasipaddress varchar(15) NOT NULL DEFAULT '',
    nasportid varchar(15) DEFAULT NULL,
    nasporttype varchar(32) DEFAULT NULL,
    acctstarttime datetime NULL DEFAULT NULL,
    acctupdatetime datetime NULL DEFAULT NULL,
    acctstoptime datetime NULL DEFAULT NULL,
    acctsessiontime int(12) DEFAULT NULL,
    acctauthentic varchar(32) DEFAULT NULL,
    connectinfo_start varchar(50) DEFAULT NULL,
    connectinfo_stop varchar(50) DEFAULT NULL,
    acctinputoctets bigint(20) DEFAULT NULL,
    acctoutputoctets bigint(20) DEFAULT NULL,
    calledstationid varchar(50) NOT NULL DEFAULT '',
    callingstationid varchar(50) NOT NULL DEFAULT '',
    acctterminatecause varchar(32) NOT NULL DEFAULT '',
    servicetype varchar(32) DEFAULT NULL,
    framedprotocol varchar(32) DEFAULT NULL,
    framedipaddress varchar(15) NOT NULL DEFAULT '',
    PRIMARY KEY (radacctid),
    KEY username (username),
    KEY framedipaddress (framedipaddress),
    KEY acctsessionid (acctsessionid),
    KEY acctuniquekey (acctuniqueid),
    KEY acctstarttime (acctstarttime),
    KEY acctstoptime (acctstoptime),
    KEY nasipaddress (nasipaddress)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Post authentication log
CREATE TABLE IF NOT EXISTS radpostauth (
    id int(11) NOT NULL AUTO_INCREMENT,
    username varchar(64) NOT NULL DEFAULT '',
    pass varchar(64) NOT NULL DEFAULT '',
    reply varchar(32) NOT NULL DEFAULT '',
    authdate timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create indexes for better performance
CREATE INDEX idx_radreply_username ON radreply(username);
CREATE INDEX idx_radcheck_username ON radcheck(username);
CREATE INDEX idx_client_notes_mac ON client_notes(mac);

GRANT SELECT,INSERT,UPDATE,DELETE ON radius.* TO 'radius_user'@'%';
FLUSH PRIVILEGES;

EOF

echo "Additional FreeRADIUS tables created successfully!"