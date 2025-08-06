# LiteWP - Simple WordPress Hosting Panel

LiteWP là một WordPress Hosting Panel đơn giản, tập trung vào UX và dễ sử dụng. Được xây dựng trên OpenLiteSpeed với giao diện web đơn giản.

## 🚀 Tính năng

### Core Features
- ✅ **Dashboard** - Thông tin server, CPU, RAM, Disk
- ✅ **Website Management** - Tạo, xóa, quản lý website
- ✅ **WordPress Tools** - Cài đặt, cập nhật WordPress
- ✅ **Database Management** - Quản lý MariaDB với Adminer
- ✅ **SSL Management** - Let's Encrypt + Custom SSL
- ✅ **File Manager** - TinyFileManager integration
- ✅ **Firewall** - iptables management
- ✅ **Backup System** - Tự động backup
- ✅ **Logs** - Access/Error logs

### Technical Stack
- **Backend:** PHP 8.3 (native)
- **Frontend:** HTML + CSS + JavaScript + Tailwind CSS
- **Database:** SQLite (panel) + MariaDB (websites)
- **Web Server:** OpenLiteSpeed
- **PHP:** LSPHP 8.3
- **Security:** JWT + bcrypt + iptables

## 📦 Cài đặt

### Yêu cầu hệ thống
- Debian 11+ hoặc Ubuntu 20.04+
- Root access
- Tối thiểu 1GB RAM
- Tối thiểu 10GB disk space

### Cài đặt nhanh
```bash
# Chạy script cài đặt
curl -sSL https://raw.githubusercontent.com/litewp/panel/main/install.sh | bash

# Hoặc
wget -O - https://raw.githubusercontent.com/litewp/panel/main/install.sh | bash
```

### Cài đặt thủ công
```bash
# Clone repository
git clone https://github.com/litewp/panel.git
cd panel

# Chạy script cài đặt
chmod +x install.sh
./install.sh
```

## 🔧 Cấu hình

### Truy cập Panel
- **Panel URL:** `http://your-server-ip:8080`
- **OLS Admin:** `http://your-server-ip:7080`
- **Default Login:** `admin`
- **Default Password:** `admin123`

### Thay đổi mật khẩu
1. Đăng nhập vào panel
2. Vào Settings > Security
3. Thay đổi mật khẩu

## 📁 Cấu trúc thư mục

```
/usr/local/litewp/
├── panel/                    # Panel files
│   ├── backend/             # PHP API
│   ├── frontend/            # Web interface
│   └── tools/               # Adminer, FileManager
├── websites/                # All websites
│   └── example.com/
│       ├── public_html/     # Document root
│       ├── logs/           # Website logs
│       └── backups/        # Website backups
├── config/                  # Configuration
│   ├── panel.db            # SQLite database
│   └── panel.conf          # Panel config
├── backups/                 # System backups
└── logs/                    # System logs
```

## 🎯 Sử dụng

### 1. Thêm Website mới
1. Vào **Websites** > **Add New Website**
2. Nhập domain name
3. Chọn PHP version
4. Click **Create Website**

### 2. Cài đặt WordPress
1. Vào **WordPress** > **Install WordPress**
2. Chọn website
3. Nhập thông tin admin
4. Click **Install**

### 3. Cài đặt SSL
1. Vào **SSL** > **Let's Encrypt**
2. Chọn domain
3. Nhập email
4. Click **Install SSL**

### 4. Quản lý Database
1. Vào **Database** > **Manage**
2. Tạo database mới
3. Sử dụng Adminer để quản lý

## 🔒 Bảo mật

### Firewall Rules
- Port 22 (SSH)
- Port 80 (HTTP)
- Port 443 (HTTPS)
- Port 8080 (Panel)
- Port 7080 (OLS Admin)

### File Permissions
```bash
# Panel files
chown -R lsadm:lsadm /usr/local/litewp/panel/
chmod -R 755 /usr/local/litewp/panel/

# Website files
chown -R lsadm:lsadm /usr/local/litewp/websites/
chmod -R 755 /usr/local/litewp/websites/

# Config files
chown root:root /usr/local/litewp/config/
chmod 600 /usr/local/litewp/config/panel.db
```

## 📊 Monitoring

### System Monitoring
- CPU usage
- Memory usage
- Disk usage
- Uptime
- Service status

### Website Monitoring
- Website uptime
- SSL certificate expiry
- Backup status
- Error logs

## 🔄 Backup

### Automated Backups
- **Daily:** Website files + databases
- **Weekly:** System configuration
- **Monthly:** Full system backup

### Backup Locations
- Local: `/usr/local/litewp/backups/`
- Retention: 30 days (configurable)

## 🛠️ Troubleshooting

### Common Issues

#### 1. Panel không load
```bash
# Kiểm tra service
systemctl status lsws

# Restart service
systemctl restart lsws

# Kiểm tra logs
tail -f /usr/local/litewp/logs/panel.log
```

#### 2. Website không hoạt động
```bash
# Kiểm tra virtual host
ls -la /usr/local/lsws/conf/vhosts/

# Restart OpenLiteSpeed
systemctl restart lsws

# Kiểm tra website logs
tail -f /usr/local/litewp/websites/your-domain/logs/error.log
```

#### 3. SSL không hoạt động
```bash
# Kiểm tra SSL certificate
certbot certificates

# Renew SSL
certbot renew

# Kiểm tra SSL config
openssl s_client -connect your-domain:443
```

### Log Files
- **Panel logs:** `/usr/local/litewp/logs/panel.log`
- **Website logs:** `/usr/local/litewp/websites/*/logs/`
- **System logs:** `/var/log/`

## 🔄 Updates

### Auto Update
```bash
# Check for updates
curl -sSL https://raw.githubusercontent.com/litewp/panel/main/update.sh | bash
```

### Manual Update
```bash
# Download latest version
wget https://github.com/litewp/panel/archive/main.zip
unzip main.zip
cd panel-main

# Run update script
./update.sh
```

## 🤝 Contributing

1. Fork repository
2. Create feature branch
3. Make changes
4. Test thoroughly
5. Submit pull request

## 📄 License

MIT License - see [LICENSE](LICENSE) file for details.

## 🆘 Support

- **Documentation:** [docs.litewp.com](https://docs.litewp.com)
- **Issues:** [GitHub Issues](https://github.com/litewp/panel/issues)
- **Discussions:** [GitHub Discussions](https://github.com/litewp/panel/discussions)

## 🙏 Credits

- **OpenLiteSpeed** - Web server
- **Tailwind CSS** - UI framework
- **Adminer** - Database management
- **TinyFileManager** - File management

---

**LiteWP** - Simple WordPress Hosting Panel | Made with ❤️ for the WordPress community 