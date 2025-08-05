#!/bin/bash

# LiteWP Panel Installation Script
# Single Admin WordPress Hosting Panel

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Log file
LOG_FILE="/tmp/litewp_install.log"

# Function to log messages
log_message() {
    echo -e "$1" | tee -a "$LOG_FILE"
}

# Function to check if running as root
check_root() {
    if [[ $EUID -ne 0 ]]; then
        log_message "${RED}❌ This script must be run as root${NC}"
        exit 1
    fi
}

# Function to detect OS
detect_os() {
    log_message "${BLUE}🔍 Detecting operating system...${NC}"
    
    if [[ -f /etc/debian_version ]]; then
        OS="debian"
        PACKAGE_MANAGER="apt"
        log_message "${GREEN}✅ Detected: Debian/Ubuntu${NC}"
    elif [[ -f /etc/redhat-release ]]; then
        OS="redhat"
        PACKAGE_MANAGER="yum"
        log_message "${GREEN}✅ Detected: CentOS/RHEL${NC}"
    else
        log_message "${RED}❌ Unsupported operating system${NC}"
        exit 1
    fi
}

# Function to install system dependencies
install_dependencies() {
    log_message "${BLUE}📦 Installing system dependencies...${NC}"
    
    # Update package list
    $PACKAGE_MANAGER update -y
    
    # Install basic dependencies
    $PACKAGE_MANAGER install -y wget curl unzip git python3 python3-pip python3-venv
    
    log_message "${GREEN}✅ System dependencies installed${NC}"
}

# Function to install OpenLiteSpeed
install_openlitespeed() {
    log_message "${BLUE}🚀 Installing OpenLiteSpeed...${NC}"
    
    # Download and install OpenLiteSpeed
    wget -O openlitespeed.sh https://repo.litespeed.sh
    bash openlitespeed.sh
    
    if [[ "$OS" == "debian" ]]; then
        apt install openlitespeed -y
    else
        yum install openlitespeed -y
    fi
    
    # Verify installation
    if command -v lswsctrl &> /dev/null; then
        log_message "${GREEN}✅ OpenLiteSpeed installed successfully${NC}"
    else
        log_message "${RED}❌ OpenLiteSpeed installation failed${NC}"
        exit 1
    fi
}

# Function to install lsPHP
install_lsphp() {
    log_message "${BLUE}🐘 Installing lsPHP 8.1...${NC}"
    
    # Install lsPHP 8.1
    /usr/local/lsws/bin/lsphpctl install 8.1
    
    # Configure PHP settings
    cat > /usr/local/lsws/lsphp81/etc/php.ini << 'EOF'
[PHP]
memory_limit = 256M
max_execution_time = 300
upload_max_filesize = 64M
post_max_size = 64M
max_input_vars = 3000

# Security settings
expose_php = Off
allow_url_fopen = Off
allow_url_include = Off
file_uploads = On

# Error handling
display_errors = Off
log_errors = On
error_log = /var/litewp/logs/php_errors.log

# Session security
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1
EOF
    
    log_message "${GREEN}✅ lsPHP 8.1 installed and configured${NC}"
}

# Function to setup directory structure
setup_directories() {
    log_message "${BLUE}📁 Setting up directory structure...${NC}"
    
    # Create main directories
    mkdir -p /var/litewp/{panel,wordpress,backups,logs,ssl}
    
    # Create panel subdirectories
    mkdir -p /var/litewp/panel/{app,database,logs,config,scripts,static}
    mkdir -p /var/litewp/panel/app/{api,utils,static}
    mkdir -p /var/litewp/panel/app/static/{css,js,images}
    
    # Set permissions
    chown -R litewp-panel:litewp-panel /var/litewp/panel/ 2>/dev/null || true
    chown -R litewp-www:litewp-www /var/litewp/wordpress/ 2>/dev/null || true
    chown -R root:root /var/litewp/logs/ 2>/dev/null || true
    chown -R root:root /var/litewp/backups/ 2>/dev/null || true
    
    chmod 755 /var/litewp/wordpress/
    chmod 700 /var/litewp/panel/
    
    log_message "${GREEN}✅ Directory structure created${NC}"
}

# Function to install Python dependencies
install_python_deps() {
    log_message "${BLUE}🐍 Installing Python dependencies...${NC}"
    
    # Create virtual environment
    python3 -m venv /var/litewp/venv
    source /var/litewp/venv/bin/activate
    
    # Upgrade pip
    pip install --upgrade pip
    
    # Install dependencies
    pip install fastapi uvicorn sqlalchemy python-multipart jinja2 python-dotenv
    
    log_message "${GREEN}✅ Python dependencies installed${NC}"
}

# Function to setup database
setup_database() {
    log_message "${BLUE}🗄️ Setting up database...${NC}"
    
    # Create SQLite database
    sqlite3 /var/litewp/panel/database/panel.db << 'EOF'
CREATE TABLE wordpress_sites (
    id INTEGER PRIMARY KEY,
    domain TEXT UNIQUE,
    wp_version TEXT,
    db_name TEXT,
    db_user TEXT,
    db_password TEXT,
    status TEXT DEFAULT 'active',
    ssl_enabled BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE admin_settings (
    id INTEGER PRIMARY KEY,
    admin_email TEXT,
    backup_retention INTEGER DEFAULT 7,
    auto_ssl BOOLEAN DEFAULT 1,
    security_level TEXT DEFAULT 'medium',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin settings
INSERT INTO admin_settings (admin_email, backup_retention, auto_ssl, security_level) 
VALUES ('admin@example.com', 7, 1, 'medium');
EOF
    
    chmod 600 /var/litewp/panel/database/panel.db
    
    log_message "${GREEN}✅ Database setup completed${NC}"
}

# Function to configure OpenLiteSpeed
configure_openlitespeed() {
    log_message "${BLUE}⚙️ Configuring OpenLiteSpeed...${NC}"
    
    # Create LiteWP virtual host configuration
    cat > /usr/local/lsws/conf/vhosts/litewp.conf << 'EOF'
docRoot                   /var/litewp/wordpress
enableGzip               1
enableBr                  1

index  {
  useServer               0
  indexFiles              index.php, index.html
}

scripthandler  {
  add                     lsapi:lsphp81 php
}

extprocessor lsphp81 {
  type                    lsapi
  address                 uds://tmp/lshttpd/lsphp.sock
  maxConns                35
  env                     PHP_LSAPI_MAX_REQUESTS=500
  env                     PHP_LSAPI_CHILDREN=35
  initTimeout             60
  retryTimeout            0
  pcKeepAliveTimeout      300
  respBuffer              0
  autoStart               1
  path                    lsphp81/bin/lsphp
  backlog                 100
  instances               1
}

rewrite  {
  enable                  1
  rules                   <<<END_rules
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
END_rules
}

context / {
  location                /
  allowBrowse            1
  indexFiles             index.php, index.html
  addDefaultCharset      off
  
  # Security headers
  addHeader              X-Frame-Options SAMEORIGIN
  addHeader              X-Content-Type-Options nosniff
  addHeader              X-XSS-Protection "1; mode=block"
  addHeader              Referrer-Policy "strict-origin-when-cross-origin"
}
EOF
    
    # Enable the virtual host
    ln -sf /usr/local/lsws/conf/vhosts/litewp.conf /usr/local/lsws/conf/vhosts/
    
    log_message "${GREEN}✅ OpenLiteSpeed configured${NC}"
}

# Function to setup security
setup_security() {
    log_message "${BLUE}🔒 Setting up security...${NC}"
    
    # Basic firewall rules
    if command -v ufw &> /dev/null; then
        ufw allow 22/tcp
        ufw allow 80/tcp
        ufw allow 443/tcp
        ufw allow 7080/tcp
        ufw --force enable
    fi
    
    # Create security scanning script
    cat > /var/litewp/panel/scripts/security_scan.sh << 'EOF'
#!/bin/bash
# Security scanning script

SCAN_DIR="/var/litewp/wordpress"
LOG_FILE="/var/litewp/panel/logs/security.log"

echo "$(date): Starting security scan..." >> "$LOG_FILE"

# Scan for suspicious files
find "$SCAN_DIR" -name "*.php" -exec grep -l "eval\|base64_decode\|system\|shell_exec" {} \; >> "$LOG_FILE" 2>&1

# Scan for hidden files
find "$SCAN_DIR" -name ".*" -type f >> "$LOG_FILE" 2>&1

# Check file permissions
find "$SCAN_DIR" -type f -perm /111 >> "$LOG_FILE" 2>&1

# Scan for large files (potential uploads)
find "$SCAN_DIR" -type f -size +10M >> "$LOG_FILE" 2>&1

echo "$(date): Security scan completed" >> "$LOG_FILE"
EOF
    
    chmod +x /var/litewp/panel/scripts/security_scan.sh
    
    log_message "${GREEN}✅ Security setup completed${NC}"
}

# Function to setup services
setup_services() {
    log_message "${BLUE}🔧 Setting up services...${NC}"
    
    # Create systemd service for LiteWP Panel
    cat > /etc/systemd/system/litewp-panel.service << 'EOF'
[Unit]
Description=LiteWP Panel FastAPI Application
After=network.target

[Service]
Type=simple
User=root
Group=root
WorkingDirectory=/var/litewp/panel
Environment=PATH=/var/litewp/venv/bin
ExecStart=/var/litewp/venv/bin/python -m uvicorn app.main:app --host 127.0.0.1 --port 8000
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF
    
    # Enable and start services
    systemctl daemon-reload
    systemctl enable litewp-panel
    systemctl enable lsws
    
    log_message "${GREEN}✅ Services configured${NC}"
}

# Function to create backup script
create_backup_script() {
    log_message "${BLUE}💾 Creating backup script...${NC}"
    
    cat > /var/litewp/panel/scripts/backup.sh << 'EOF'
#!/bin/bash
# Backup script for LiteWP Panel

BACKUP_DIR="/var/litewp/backups"
RETENTION_DAYS=7
LOG_FILE="/var/litewp/panel/logs/backup.log"

echo "$(date): Starting backup process..." >> "$LOG_FILE"

# Create backup directory if not exists
mkdir -p "$BACKUP_DIR"

# Backup all WordPress sites
for site in /var/litewp/wordpress/*; do
    if [ -d "$site" ]; then
        site_name=$(basename "$site")
        backup_file="$BACKUP_DIR/${site_name}_$(date +%Y%m%d_%H%M%S).zip"
        
        # Backup files
        zip -r "$backup_file" "$site" >> "$LOG_FILE" 2>&1
        
        # Backup database
        db_name="wp_${site_name//./_}"
        mysqldump "$db_name" > "${backup_file%.zip}_db.sql" 2>> "$LOG_FILE"
        
        echo "$(date): Backed up $site_name" >> "$LOG_FILE"
    fi
done

# Clean old backups
find "$BACKUP_DIR" -name "*.zip" -mtime +$RETENTION_DAYS -delete >> "$LOG_FILE" 2>&1
find "$BACKUP_DIR" -name "*.sql" -mtime +$RETENTION_DAYS -delete >> "$LOG_FILE" 2>&1

echo "$(date): Backup process completed" >> "$LOG_FILE"
EOF
    
    chmod +x /var/litewp/panel/scripts/backup.sh
    
    # Setup cron job for daily backup
    (crontab -l 2>/dev/null; echo "0 2 * * * /var/litewp/panel/scripts/backup.sh") | crontab -
    
    log_message "${GREEN}✅ Backup script created${NC}"
}

# Function to display success message
display_success() {
    local IP=$(hostname -I | awk '{print $1}')
    
    log_message "${GREEN}"
    log_message "🎉 LiteWP Panel installed successfully!"
    log_message ""
    log_message "📊 Installation Summary:"
    log_message "   • OpenLiteSpeed: ✅ Installed"
    log_message "   • lsPHP 8.1: ✅ Installed"
    log_message "   • FastAPI: ✅ Installed"
    log_message "   • Database: ✅ Configured"
    log_message "   • Security: ✅ Configured"
    log_message "   • Backup: ✅ Configured"
    log_message ""
    log_message "🌐 Access Information:"
    log_message "   • Panel URL: https://${IP}:7080"
    log_message "   • Username: admin"
    log_message "   • Password: admin"
    log_message ""
    log_message "📁 Important Directories:"
    log_message "   • Panel: /var/litewp/panel/"
    log_message "   • WordPress Sites: /var/litewp/wordpress/"
    log_message "   • Backups: /var/litewp/backups/"
    log_message "   • Logs: /var/litewp/logs/"
    log_message ""
    log_message "🔧 Next Steps:"
    log_message "   1. Access the panel at https://${IP}:7080"
    log_message "   2. Add your first WordPress site"
    log_message "   3. Configure SSL certificates"
    log_message "   4. Set up regular backups"
    log_message ""
    log_message "📝 Installation log: $LOG_FILE"
    log_message "${NC}"
}

# Main installation function
main() {
    log_message "${BLUE}🚀 Starting LiteWP Panel installation...${NC}"
    
    # Check if running as root
    check_root
    
    # Detect OS
    detect_os
    
    # Install dependencies
    install_dependencies
    
    # Install OpenLiteSpeed
    install_openlitespeed
    
    # Install lsPHP
    install_lsphp
    
    # Setup directories
    setup_directories
    
    # Install Python dependencies
    install_python_deps
    
    # Setup database
    setup_database
    
    # Configure OpenLiteSpeed
    configure_openlitespeed
    
    # Setup security
    setup_security
    
    # Setup services
    setup_services
    
    # Create backup script
    create_backup_script
    
    # Start services
    systemctl start lsws
    systemctl start litewp-panel
    
    # Display success message
    display_success
}

# Run main function
main "$@" 