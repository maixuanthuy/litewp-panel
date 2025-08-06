# LiteWP Server Architecture

## **Cách hoạt động trên server**

### **1. Installation Process**

```bash
# User chạy lệnh cài đặt
curl -sSL https://raw.githubusercontent.com/litewp/panel/main/install.sh | bash

# Hoặc
wget -O - https://raw.githubusercontent.com/litewp/panel/main/install.sh | bash
```

**Quá trình cài đặt:**
1. **System Check:** Kiểm tra OS (Debian/Ubuntu), quyền root
2. **Update System:** Cập nhật packages
3. **Install Dependencies:** 
   - OpenLiteSpeed (latest)
   - PHP 8.3 + extensions
   - MariaDB 11.4 (10.11 cho Debian 11/Ubuntu 22)
   - Redis
   - Node.js (cho build frontend)
4. **Setup Panel:**
   - Tạo thư mục `/usr/local/litewp/`
   - Copy files từ repository
   - Setup SQLite database
   - Build frontend
5. **Configure Services:**
   - Setup systemd services
   - Configure OpenLiteSpeed
   - Setup nginx proxy (optional)
6. **Security Setup:**
   - Generate SSL certificates
   - Setup firewall rules
   - Create admin user

### **2. Service Architecture**

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Frontend      │    │   Backend       │    │   OpenLiteSpeed │
│   (React)       │◄──►│   (PHP API)     │◄──►│   (Web Server)  │
│   Port: 3000    │    │   Port: 8080    │    │   Port: 80/443  │
└─────────────────┘    └─────────────────┘    └─────────────────┘
                                │
                                ▼
                       ┌─────────────────┐
                       │   SQLite DB     │
                       │   (Panel Data)  │
                       └─────────────────┘
                                │
                                ▼
                       ┌─────────────────┐
                       │   MariaDB       │
                       │   (WordPress)   │
                       └─────────────────┘
```

### **3. File Structure trên Server**

```
/usr/local/litewp/
├── panel/
│   ├── backend/                 # PHP API files
│   │   ├── api/                # API endpoints
│   │   ├── core/               # Core classes
│   │   ├── config/             # Configuration
│   │   └── vendor/             # Composer deps
│   ├── frontend/               # Built React app
│   │   ├── index.html
│   │   ├── assets/
│   │   └── dist/
│   ├── tools/                  # Adminer, FileManager
│   │   ├── adminer.php
│   │   └── filemanager.php
│   └── logs/                   # Panel logs
├── websites/                    # All websites
│   ├── example.com/
│   │   ├── public_html/        # Document root
│   │   ├── logs/              # Access/Error logs
│   │   ├── backups/           # Website backups
│   │   ├── ssl/              # SSL certificates
│   │   └── wp-config.php      # WordPress config
│   └── another-site.com/
├── config/
│   ├── panel.db               # SQLite database
│   ├── panel.conf             # Panel configuration
│   ├── ssl/                   # SSL certificates
│   └── backup/                # Backup configuration
├── scripts/                    # Management scripts
│   ├── website/
│   ├── wordpress/
│   ├── ssl/
│   └── database/
├── backups/                    # System backups
└── logs/                       # System logs
```

### **4. Process Management**

**Systemd Services:**
```ini
# /etc/systemd/system/litewp-panel.service
[Unit]
Description=LiteWP Panel
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/usr/local/litewp/panel/backend
ExecStart=/usr/bin/php -S localhost:8080 -t /usr/local/litewp/panel/backend
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

**Nginx Configuration:**
```nginx
# /etc/nginx/sites-available/litewp-panel
server {
    listen 80;
    server_name panel.yourdomain.com;
    
    location / {
        proxy_pass http://localhost:3000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
    
    location /api {
        proxy_pass http://localhost:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

### **5. Security Architecture**

**Authentication Flow:**
1. User login → JWT token generation
2. Token stored in localStorage
3. API calls include Authorization header
4. Backend validates token on each request

**File Permissions:**
```bash
# Panel files
chown -R www-data:www-data /usr/local/litewp/panel/
chmod -R 755 /usr/local/litewp/panel/

# Website files
chown -R www-data:www-data /usr/local/litewp/websites/
chmod -R 755 /usr/local/litewp/websites/

# Config files
chown root:root /usr/local/litewp/config/
chmod 600 /usr/local/litewp/config/panel.db
```

**Firewall Rules:**
```bash
# Allow panel access
iptables -A INPUT -p tcp --dport 80 -j ACCEPT
iptables -A INPUT -p tcp --dport 443 -j ACCEPT
iptables -A INPUT -p tcp --dport 22 -j ACCEPT

# Block other ports
iptables -A INPUT -j DROP
```

### **6. Backup Strategy**

**Automated Backups:**
```bash
# Daily website backups
0 2 * * * /usr/local/litewp/scripts/backup-website.sh

# Weekly system backups
0 3 * * 0 /usr/local/litewp/scripts/backup-system.sh

# Monthly full backups
0 4 1 * * /usr/local/litewp/scripts/backup-full.sh
```

**Backup Locations:**
- Local: `/usr/local/litewp/backups/`
- Remote: S3/Google Drive (optional)

### **7. Monitoring & Logging**

**System Monitoring:**
- CPU, RAM, Disk usage
- Service status (OpenLiteSpeed, MariaDB, Redis)
- Website uptime
- SSL certificate expiry

**Log Management:**
- Panel logs: `/usr/local/litewp/panel/logs/`
- Website logs: `/usr/local/litewp/websites/*/logs/`
- System logs: `/var/log/`

### **8. Update Process**

**Auto Update:**
```bash
# Weekly update check
0 5 * * 0 /usr/local/litewp/scripts/update-check.sh
```

**Manual Update:**
```bash
# Update panel
curl -sSL https://raw.githubusercontent.com/litewp/panel/main/update.sh | bash
```

### **9. Disaster Recovery**

**Recovery Process:**
1. Restore system from backup
2. Reinstall panel if needed
3. Restore websites from backups
4. Verify all services

**Backup Verification:**
- Test restore process monthly
- Verify backup integrity
- Monitor backup success/failure 