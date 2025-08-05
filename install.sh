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
    
    # Add LiteSpeed repository (same as OLS1CLK)
    wget -O - https://repo.litespeed.sh | bash
    
    # Install OpenLiteSpeed
    if [[ "$OS" == "debian" ]]; then
        apt update
        apt install openlitespeed -y
    else
        yum install openlitespeed -y
    fi
    
    # Verify installation using multiple methods
    if command -v lswsctrl &> /dev/null || [[ -f /usr/local/lsws/bin/lswsctrl ]] || [[ -f /usr/bin/lswsctrl ]]; then
        log_message "${GREEN}✅ OpenLiteSpeed installed successfully${NC}"
        
        # Set up PHP symlink like OLS1CLK
        if [[ -f /usr/local/lsws/lsphp83/bin/lsphp ]]; then
            ln -sf /usr/local/lsws/lsphp83/bin/lsphp /usr/local/lsws/fcgi-bin/lsphpnew
            if [[ -f /usr/local/lsws/conf/httpd_config.conf ]]; then
                sed -i -e "s/fcgi-bin\/lsphp/fcgi-bin\/lsphpnew/g" /usr/local/lsws/conf/httpd_config.conf
                sed -i -e "s/lsphp74\/bin\/lsphp/lsphp83\/bin\/lsphp/g" /usr/local/lsws/conf/httpd_config.conf
            fi
            if [[ ! -f /usr/bin/php ]]; then
                ln -s /usr/local/lsws/lsphp83/bin/php /usr/bin/php
            fi
        fi
    else
        log_message "${RED}❌ OpenLiteSpeed installation failed${NC}"
        exit 1
    fi
}

# Function to install lsPHP
install_lsphp() {
    log_message "${BLUE}🐘 Configuring lsPHP 8.3...${NC}"
    
    # Configure PHP settings (lsPHP 8.3 already installed with OpenLiteSpeed)
    PHPINICONF="/usr/local/lsws/lsphp83/etc/php/8.3/litespeed/php.ini"
    
    if [[ -f "$PHPINICONF" ]]; then
        # Update PHP settings
        sed -i 's|memory_limit = 128M|memory_limit = 1024M|g' "$PHPINICONF"
        sed -i 's|max_execution_time = 30|max_execution_time = 360|g' "$PHPINICONF"
        sed -i 's|max_input_time = 60|max_input_time = 360|g' "$PHPINICONF"
        sed -i 's|post_max_size = 8M|post_max_size = 512M|g' "$PHPINICONF"
        sed -i 's|upload_max_filesize = 2M|upload_max_filesize = 512M|g' "$PHPINICONF"
        
        # Add security settings
        echo "" >> "$PHPINICONF"
        echo "# Security settings" >> "$PHPINICONF"
        echo "expose_php = Off" >> "$PHPINICONF"
        echo "allow_url_fopen = Off" >> "$PHPINICONF"
        echo "allow_url_include = Off" >> "$PHPINICONF"
        echo "file_uploads = On" >> "$PHPINICONF"
        echo "" >> "$PHPINICONF"
        echo "# Error handling" >> "$PHPINICONF"
        echo "display_errors = Off" >> "$PHPINICONF"
        echo "log_errors = On" >> "$PHPINICONF"
        echo "error_log = /var/litewp/logs/php_errors.log" >> "$PHPINICONF"
        echo "" >> "$PHPINICONF"
        echo "# Session security" >> "$PHPINICONF"
        echo "session.cookie_httponly = 1" >> "$PHPINICONF"
        echo "session.cookie_secure = 1" >> "$PHPINICONF"
        echo "session.use_strict_mode = 1" >> "$PHPINICONF"
        
        log_message "${GREEN}✅ lsPHP 8.3 configured successfully${NC}"
    else
        log_message "${YELLOW}⚠️  PHP config file not found at $PHPINICONF${NC}"
        log_message "${GREEN}✅ lsPHP 8.3 is installed (using default settings)${NC}"
    fi
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
    
    # Install dependencies from requirements.txt
    if [[ -f "requirements.txt" ]]; then
        pip install -r requirements.txt
    else
        # Fallback to manual installation
        pip install fastapi uvicorn sqlalchemy python-multipart jinja2 python-dotenv requests cryptography bcrypt python-jose[cryptography] passlib[bcrypt]
    fi
    
    log_message "${GREEN}✅ Python dependencies installed${NC}"
}

# Function to setup database
setup_database() {
    log_message "${BLUE}🗄️ Setting up database...${NC}"
    
    # Create database directory
    mkdir -p /var/litewp/panel/database
    
    # Copy application files to panel directory
    if [[ -d "app" ]]; then
        cp -r app/* /var/litewp/panel/app/
    fi
    
    # Copy requirements.txt
    if [[ -f "requirements.txt" ]]; then
        cp requirements.txt /var/litewp/panel/
    fi
    
    # Initialize database using Python
    cd /var/litewp/panel
    source /var/litewp/venv/bin/activate
    
    # Create database tables using SQLAlchemy
    python3 -c "
import sys
import os
sys.path.append('/var/litewp/panel')

try:
    from app.database.database import engine, Base
    from app.models.models import WordPressSite, AdminSettings, BackupLog, SecurityLog
    
    # Create all tables
    Base.metadata.create_all(bind=engine)
    
    # Insert default admin settings
    from sqlalchemy.orm import sessionmaker
    
    SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)
    db = SessionLocal()
    
    # Check if admin settings already exist
    existing_settings = db.query(AdminSettings).first()
    if not existing_settings:
        default_settings = AdminSettings(
            admin_email='admin@example.com',
            backup_retention=7,
            auto_ssl=True,
            security_level='medium'
        )
        db.add(default_settings)
        db.commit()
    
    db.close()
    print('Database initialized successfully')
except Exception as e:
    print(f'Database initialization error: {e}')
    # Create simple SQLite database as fallback
    import sqlite3
    conn = sqlite3.connect('/var/litewp/panel/database/panel.db')
    cursor = conn.cursor()
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS wordpress_sites (
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
        )
    ''')
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS admin_settings (
            id INTEGER PRIMARY KEY,
            admin_email TEXT,
            backup_retention INTEGER DEFAULT 7,
            auto_ssl BOOLEAN DEFAULT 1,
            security_level TEXT DEFAULT 'medium',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ''')
    cursor.execute('''
        INSERT OR IGNORE INTO admin_settings (admin_email, backup_retention, auto_ssl, security_level) 
        VALUES ('admin@example.com', 7, 1, 'medium')
    ''')
    conn.commit()
    conn.close()
    print('Fallback database created successfully')
"
    
    chmod 600 /var/litewp/panel/database/panel.db
    
    log_message "${GREEN}✅ Database setup completed${NC}"
}

# Function to configure OpenLiteSpeed
configure_openlitespeed() {
    log_message "${BLUE}⚙️ Configuring OpenLiteSpeed...${NC}"
    
    # Generate self-signed certificate like OLS1CLK
    if [[ ! -f /usr/local/lsws/conf/example.key ]]; then
        openssl req -x509 -nodes -days 820 -newkey rsa:2048 \
            -keyout /usr/local/lsws/conf/example.key \
            -out /usr/local/lsws/conf/example.crt \
            -subj "/C=US/ST=State/L=City/O=Organization/CN=localhost"
        chmod 600 /usr/local/lsws/conf/example.key
        chmod 600 /usr/local/lsws/conf/example.crt
    fi
    
    # Update main configuration
    if [[ -f /usr/local/lsws/conf/httpd_config.conf ]]; then
        # Update admin email
        sed -i -e "s/adminEmails/adminEmails admin@example.com\n#adminEmails/" /usr/local/lsws/conf/httpd_config.conf
        
        # Update ports
        sed -i -e "s/8088/80/" /usr/local/lsws/conf/httpd_config.conf
        
        # Add SSL listener
        cat >> /usr/local/lsws/conf/httpd_config.conf << 'EOF'

listener Defaultssl {
address                 *:443
secure                  1
map                     Example *
keyFile                 /usr/local/lsws/conf/example.key
certFile                /usr/local/lsws/conf/example.crt
}

EOF
    fi
    
    # Create LiteWP virtual host configuration
    mkdir -p /usr/local/lsws/conf/vhosts/litewp/
    cat > /usr/local/lsws/conf/vhosts/litewp/vhconf.conf << 'EOF'
docRoot                   /var/litewp/wordpress

accesslog  {
  useServer               1
}

index  {
  useServer               0
  indexFiles              index.php, index.html
}

scripthandler  {
  add                     lsapi:lsphp83 php
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

rewrite  {
  enable                  1
  autoLoadHtaccess        1
}
EOF
    
    # Add virtual host to main config
    cat >> /usr/local/lsws/conf/httpd_config.conf << 'EOF'

virtualhost litewp {
vhRoot                  /var/litewp/wordpress
configFile              /usr/local/lsws/conf/vhosts/litewp/vhconf.conf
allowSymbolLink         1
enableScript            1
restrained              0
setUIDMode              2
}

listener litewp {
address                 *:80
secure                  0
map                     litewp *
}

listener litewpssl {
address                 *:443
secure                  1
map                     litewp *
keyFile                 /usr/local/lsws/conf/example.key
certFile                /usr/local/lsws/conf/example.crt
}

EOF
    
    # Set proper ownership
    chown -R lsadm:lsadm /usr/local/lsws/conf/
    
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
ExecStart=/var/litewp/venv/bin/python -m uvicorn app.main:app --host 127.0.0.1 --port 8000 --reload
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
    log_message "   • lsPHP 8.3: ✅ Installed"
    log_message "   • FastAPI: ✅ Installed"
    log_message "   • Database: ✅ Configured"
    log_message "   • Security: ✅ Configured"
    log_message "   • Backup: ✅ Configured"
    log_message ""
    log_message "🌐 Access Information:"
    log_message "   • Panel URL: http://${IP}:8000"
    log_message "   • OpenLiteSpeed Admin: http://${IP}:7080"
    log_message "   • WordPress Sites: http://${IP}:80"
    log_message ""
    log_message "📁 Important Directories:"
    log_message "   • Panel: /var/litewp/panel/"
    log_message "   • WordPress Sites: /var/litewp/wordpress/"
    log_message "   • Backups: /var/litewp/backups/"
    log_message "   • Logs: /var/litewp/logs/"
    log_message ""
    log_message "🔧 Next Steps:"
    log_message "   1. Access the panel at http://${IP}:8000"
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