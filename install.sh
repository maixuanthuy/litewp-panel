#!/bin/bash

# LiteWP Installation Script
# Simple WordPress Hosting Panel with OpenLiteSpeed
# Version: 1.0.0

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Variables
LITEWP_DIR="/usr/local/litewp"
PANEL_DIR="$LITEWP_DIR/panel"
WEBSITES_DIR="$LITEWP_DIR/websites"
CONFIG_DIR="$LITEWP_DIR/config"
BACKUP_DIR="$LITEWP_DIR/backups"
LOGS_DIR="$LITEWP_DIR/logs"
OLS_ROOT="/usr/local/lsws"

# Functions
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

check_root() {
    if [[ $EUID -ne 0 ]]; then
        print_error "This script must be run as root"
        exit 1
    fi
}

check_os() {
    if [[ -f /etc/debian_version ]]; then
        OS="debian"
        print_success "Detected Debian/Ubuntu system"
    else
        print_error "This script only supports Debian/Ubuntu"
        exit 1
    fi
}

update_system() {
    print_status "Updating system packages..."
    apt update -qq
    apt upgrade -y -qq
    print_success "System updated"
}

install_dependencies() {
    print_status "Installing dependencies..."
    
    # Install required packages
    apt install -y -qq \
        curl \
        wget \
        unzip \
        git \
        sqlite3 \
        redis-server \
        certbot \
        python3-certbot
    
    print_success "Dependencies installed"
}

install_openlitespeed() {
    print_status "Installing OpenLiteSpeed..."
    
    # Add LiteSpeed repository
    wget -O - http://rpms.litespeedtech.com/debian/enable_lst_debian_repo.sh | bash
    
    # Install OpenLiteSpeed
    apt install -y openlitespeed
    
    # Install LSPHP 8.3 and available extensions
    apt install -y lsphp83 lsphp83-mysql lsphp83-common lsphp83-curl
    
    # Try to install additional extensions (skip if not available)
    apt install -y lsphp83-gd 2>/dev/null || print_warning "lsphp83-gd not available"
    apt install -y lsphp83-mbstring 2>/dev/null || print_warning "lsphp83-mbstring not available"
    apt install -y lsphp83-xml 2>/dev/null || print_warning "lsphp83-xml not available"
    apt install -y lsphp83-zip 2>/dev/null || print_warning "lsphp83-zip not available"
    
    print_success "OpenLiteSpeed installed"
}

install_mariadb() {
    print_status "Installing MariaDB..."
    
    # Install MariaDB
    apt install -y mariadb-server mariadb-client
    
    # Secure MariaDB installation
    mysql -e "DELETE FROM mysql.user WHERE User='';"
    mysql -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');"
    mysql -e "DROP DATABASE IF EXISTS test;"
    mysql -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';"
    mysql -e "FLUSH PRIVILEGES;"
    
    print_success "MariaDB installed and secured"
}

create_directories() {
    print_status "Creating LiteWP directories..."
    
    mkdir -p "$PANEL_DIR"
    mkdir -p "$WEBSITES_DIR"
    mkdir -p "$CONFIG_DIR"
    mkdir -p "$BACKUP_DIR"
    mkdir -p "$LOGS_DIR"
    mkdir -p "$PANEL_DIR/backend"
    mkdir -p "$PANEL_DIR/frontend"
    mkdir -p "$PANEL_DIR/tools"
    
    print_success "Directories created"
}

setup_database() {
    print_status "Setting up SQLite database..."
    
    # Create SQLite database
    sqlite3 "$CONFIG_DIR/panel.db" "
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        email TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    
    CREATE TABLE IF NOT EXISTS websites (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        domain TEXT UNIQUE NOT NULL,
        document_root TEXT NOT NULL,
        php_version TEXT DEFAULT '8.3',
        ssl_enabled BOOLEAN DEFAULT 0,
        wordpress_installed BOOLEAN DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    
    CREATE TABLE IF NOT EXISTS databases (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE NOT NULL,
        username TEXT NOT NULL,
        password TEXT NOT NULL,
        website_id INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (website_id) REFERENCES websites(id)
    );
    
    CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    "
    
    # Insert default admin user (password: admin123)
    sqlite3 "$CONFIG_DIR/panel.db" "
    INSERT OR IGNORE INTO users (username, password, email) 
    VALUES ('admin', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@litewp.local');
    "
    
    # Insert default settings
    sqlite3 "$CONFIG_DIR/panel.db" "
    INSERT OR IGNORE INTO settings (key, value) VALUES 
    ('panel_name', 'LiteWP'),
    ('panel_url', 'http://localhost:8080'),
    ('backup_retention', '30'),
    ('ssl_provider', 'letsencrypt');
    "
    
    print_success "Database setup complete"
}

configure_openlitespeed() {
    print_status "Configuring OpenLiteSpeed..."
    
    # Stop OpenLiteSpeed to configure
    systemctl stop lsws
    
    # Create necessary directories
    mkdir -p "$OLS_ROOT/conf/vhosts/litewp-panel"
    mkdir -p "$OLS_ROOT/Example/litewp-panel"
    mkdir -p "$PANEL_DIR/logs"
    
    # Create panel virtual host configuration
    cat > "$OLS_ROOT/conf/vhosts/litewp-panel/vhconf.conf" << 'EOF'
docRoot                   $VH_ROOT/frontend

accesslog  {
  useServer               0
  logFile                  $VH_ROOT/logs/access.log
}

errorlog  {
  useServer               0
  logFile                  $VH_ROOT/logs/error.log
}

index  {
  useServer               0
  indexFiles              index.html index.php
}

context / {
  location                $VH_ROOT/frontend
  allowBrowse             1
  indexFiles              index.html index.php
}

context /api {
  location                $VH_ROOT/backend
  allowBrowse             1
  indexFiles              index.php
  addDefaultCharset       off
  
  rewrite  {
    enable                1
    rules                 REWRITERULE ^(.*)$ /api/index.php?$1 [QSA,L]
  }
}

context /tools {
  location                $VH_ROOT/tools
  allowBrowse             1
  indexFiles              index.php
  addDefaultCharset       off
}
EOF
    
    # Create panel virtual host directory
    mkdir -p "$OLS_ROOT/conf/vhosts/litewp-panel"
    mkdir -p "$OLS_ROOT/Example/litewp-panel"
    
    # Add panel virtual host to main config
    cat >> "$OLS_ROOT/conf/httpd_config.conf" << 'EOF'

virtualhost litewp-panel {
  vhRoot                  /usr/local/litewp/panel
  configFile              $SERVER_ROOT/conf/vhosts/litewp-panel/vhconf.conf
  allowSymbolLink         1
  enableScript            1
  restrained              0
  setUIDMode              2
}

listener litewp-panel {
  address                 *:8080
  secure                  0
  map                     litewp-panel *
}
EOF
    
    # Create symbolic link for panel
    ln -sf "$PANEL_DIR" "$OLS_ROOT/Example/litewp-panel"
    
    print_success "OpenLiteSpeed configured"
}

setup_php() {
    print_status "Configuring PHP..."
    
    # Create PHP configuration for panel
    cat > "$OLS_ROOT/lsphp83/etc/php.ini" << 'EOF'
[PHP]
upload_max_filesize = 512M
post_max_size = 512M
memory_limit = 1024M
max_execution_time = 300
max_input_time = 300
display_errors = Off
log_errors = On
error_log = /usr/local/litewp/logs/php_errors.log
session.cookie_httponly = 1
session.use_strict_mode = 1
EOF
    
    print_success "PHP configured"
}

setup_firewall() {
    print_status "Setting up firewall..."
    
    # Install ufw if not present
    apt install -y ufw
    
    # Configure firewall
    ufw --force reset
    ufw default deny incoming
    ufw default allow outgoing
    ufw allow ssh
    ufw allow 80/tcp
    ufw allow 443/tcp
    ufw allow 8080/tcp
    ufw allow 7080/tcp  # OpenLiteSpeed admin
    ufw --force enable
    
    print_success "Firewall configured"
}

create_services() {
    print_status "Creating systemd services..."
    
    # Create backup service
    cat > /etc/systemd/system/litewp-backup.service << 'EOF'
[Unit]
Description=LiteWP Backup Service
After=network.target

[Service]
Type=oneshot
User=root
ExecStart=/usr/local/litewp/scripts/backup-system.sh
EOF
    
    # Create backup timer
    cat > /etc/systemd/system/litewp-backup.timer << 'EOF'
[Unit]
Description=Run LiteWP backup daily
Requires=litewp-backup.service

[Timer]
OnCalendar=daily
Persistent=true

[Install]
WantedBy=timers.target
EOF
    
    # Enable services
    systemctl enable litewp-backup.timer
    systemctl start litewp-backup.timer
    
    print_success "Services created"
}

setup_permissions() {
    print_status "Setting up file permissions..."
    
    # Set ownership
    chown -R lsadm:lsadm "$LITEWP_DIR"
    chown -R lsadm:lsadm "$OLS_ROOT"
    
    # Set permissions
    chmod -R 755 "$LITEWP_DIR"
    chmod 600 "$CONFIG_DIR/panel.db"
    
    print_success "Permissions set"
}

install_panel_files() {
    print_status "Installing panel files..."
    
    # Create basic panel structure
    mkdir -p "$PANEL_DIR/backend/api"
    mkdir -p "$PANEL_DIR/backend/includes"
    mkdir -p "$PANEL_DIR/frontend"
    mkdir -p "$PANEL_DIR/tools"
    mkdir -p "$PANEL_DIR/logs"
    
    # Create basic files
    cat > "$PANEL_DIR/backend/api/index.php" << 'EOF'
<?php
header('Content-Type: application/json');
echo json_encode(['status' => 'LiteWP API is running']);
EOF
    
    cat > "$PANEL_DIR/frontend/index.html" << 'EOF'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LiteWP - WordPress Hosting Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-md">
            <h1 class="text-2xl font-bold text-gray-800 mb-4">LiteWP</h1>
            <p class="text-gray-600">WordPress Hosting Panel</p>
            <p class="text-sm text-gray-500 mt-2">Installation complete!</p>
            <div class="mt-4">
                <a href="/login.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Login to Panel</a>
            </div>
        </div>
    </div>
</body>
</html>
EOF
    
    print_success "Panel files installed"
}

finalize_installation() {
    print_status "Finalizing installation..."
    
    # Start OpenLiteSpeed
    systemctl start lsws
    systemctl enable lsws
    
    # Start MariaDB
    systemctl start mariadb
    systemctl enable mariadb
    
    # Start Redis (handle different service names)
    if systemctl list-unit-files | grep -q "redis-server.service"; then
        systemctl start redis-server
        systemctl enable redis-server
    elif systemctl list-unit-files | grep -q "redis.service"; then
        systemctl start redis
        systemctl enable redis
    else
        print_warning "Redis service not found, skipping"
    fi
    
    print_success "Installation completed!"
    
    echo ""
    echo -e "${GREEN}================================${NC}"
    echo -e "${GREEN}    LiteWP Installation Complete${NC}"
    echo -e "${GREEN}================================${NC}"
    echo ""
    echo -e "Panel URL: ${BLUE}http://$(hostname -I | awk '{print $1}'):8080${NC}"
    echo -e "OLS Admin: ${BLUE}http://$(hostname -I | awk '{print $1}'):7080${NC}"
    echo -e "Default Login: ${BLUE}admin${NC}"
    echo -e "Default Password: ${BLUE}admin123${NC}"
    echo ""
    echo -e "Next steps:"
    echo -e "1. Access the panel at the URL above"
    echo -e "2. Change the default password"
    echo -e "3. Add your first website"
    echo ""
}

# Main installation
main() {
    echo -e "${BLUE}================================${NC}"
    echo -e "${BLUE}    LiteWP Installation Script${NC}"
    echo -e "${BLUE}================================${NC}"
    echo ""
    
    check_root
    check_os
    update_system
    install_dependencies
    install_openlitespeed
    install_mariadb
    create_directories
    setup_database
    configure_openlitespeed
    setup_php
    setup_firewall
    create_services
    install_panel_files
    setup_permissions
    finalize_installation
}

# Run installation
main "$@" 