# 🚀 LiteWP Panel - Single Admin WordPress Hosting

`LiteWP Panel` is a **lightweight, single-admin WordPress hosting control panel** designed for simplicity and performance. Built with OpenLiteSpeed, lsPHP, and FastAPI.

---

## 📌 Features
✅ **WordPress Site Management** – Create, manage, and monitor WordPress sites  
✅ **OpenLiteSpeed Web Server** – High-performance web server with lsPHP  
✅ **Auto SSL Management** – Automatic Let's Encrypt SSL certificates  
✅ **Database Management** – MySQL database for each WordPress site  
✅ **Backup System** – Automated daily backups with retention  
✅ **Security Features** – Basic security scanning and isolation  
✅ **Single Admin Interface** – Simple, clean web interface  
✅ **Lightweight** – Minimal resource usage, fast setup  

---

## 🖥️ Supported Operating Systems
`LiteWP Panel` is currently supported on:

- ✅ **Ubuntu 20.04 (Focal Fossa)**
- ✅ **Ubuntu 22.04 (Jammy Jellyfish)**
- ✅ **Ubuntu 24.04 (Noble Numbat)**
- ✅ **CentOS 8/9**
- ✅ **AlmaLinux 8/9**
- ✅ **Rocky Linux 8/9**
- ✅ **Debian 11/12**

---

## 🏗️ Architecture

```
LiteWP Panel Architecture:
├── Backend: FastAPI (Python)
├── Database: SQLite (Panel) + MySQL (WordPress)
├── Web Server: OpenLiteSpeed + lsPHP 8.1
├── Frontend: HTML5 + CSS3 + Vanilla JavaScript
├── SSL: Let's Encrypt (Auto)
└── Security: Basic isolation + scanning
```

---

## 📥 Installation

### Quick Install
```bash
bash <(curl -fsSL https://raw.githubusercontent.com/your-repo/litewp-panel/main/install.sh)
```

### Manual Install
```bash
# 1. Clone repository
git clone https://github.com/your-repo/litewp-panel.git
cd litewp-panel

# 2. Run installation script
bash install.sh

# 3. Access panel
# URL: https://your-server-ip:7080
# Username: admin
# Password: (displayed after installation)
```

---

## 🎯 Core Features

### WordPress Site Management
- One-click WordPress installation
- Multiple site support
- Version management
- Site status monitoring

### SSL Certificate Management
- Automatic Let's Encrypt SSL
- Manual SSL certificate upload
- SSL status monitoring

### Backup System
- Daily automated backups
- Manual backup triggers
- Backup retention management
- Restore functionality

### Security Features
- File system isolation
- Security scanning
- Basic firewall rules
- Error logging

---

## 📁 Directory Structure

```
/var/litewp/
├── panel/                    # LiteWP Panel core
│   ├── app/                 # FastAPI application
│   ├── database/            # SQLite database
│   ├── logs/                # Panel logs
│   └── scripts/             # System scripts
├── wordpress/               # WordPress sites
├── backups/                 # Backup storage
├── logs/                    # System logs
└── ssl/                     # SSL certificates
```

---

## 🔧 Configuration

### Panel Configuration
```bash
# Edit panel settings
nano /var/litewp/panel/config/settings.py

# View logs
tail -f /var/litewp/panel/logs/panel.log
```

### OpenLiteSpeed Configuration
```bash
# Edit OpenLiteSpeed config
nano /usr/local/lsws/conf/vhosts/litewp.conf

# Restart OpenLiteSpeed
/usr/local/lsws/bin/lswsctrl restart
```

---

## 🛠️ Maintenance

### Backup Management
```bash
# Manual backup
/var/litewp/panel/scripts/backup.sh

# View backup logs
tail -f /var/litewp/panel/logs/backup.log
```

### Security Scanning
```bash
# Run security scan
/var/litewp/panel/scripts/security_scan.sh

# View security logs
tail -f /var/litewp/panel/logs/security.log
```

### Update Panel
```bash
# Update LiteWP Panel
/var/litewp/panel/scripts/update.sh
```

---

## 📊 Monitoring

### System Resources
- Disk usage monitoring
- Memory usage tracking
- CPU usage statistics
- Network traffic monitoring

### WordPress Sites
- Site status monitoring
- SSL certificate status
- Database size tracking
- Error log monitoring

---

## 🔒 Security

### Isolation Features
- File system isolation per site
- Database isolation per site
- Process isolation
- Network isolation

### Security Measures
- Basic firewall rules
- Security header implementation
- File permission restrictions
- Regular security scanning

---

## 🐛 Troubleshooting

### Common Issues

#### Panel not accessible
```bash
# Check service status
systemctl status litewp-panel

# Check logs
tail -f /var/litewp/panel/logs/error.log
```

#### WordPress site not loading
```bash
# Check OpenLiteSpeed status
/usr/local/lsws/bin/lswsctrl status

# Check site configuration
ls -la /var/litewp/wordpress/
```

#### SSL certificate issues
```bash
# Check SSL status
certbot certificates

# Renew certificates
certbot renew
```

---

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

---

## 📞 Support

- **Documentation**: [Wiki](https://github.com/your-repo/litewp-panel/wiki)
- **Issues**: [GitHub Issues](https://github.com/your-repo/litewp-panel/issues)
- **Discussions**: [GitHub Discussions](https://github.com/your-repo/litewp-panel/discussions)

---

## 🙏 Acknowledgments

- [OpenLiteSpeed](https://openlitespeed.org/) - High-performance web server
- [FastAPI](https://fastapi.tiangolo.com/) - Modern Python web framework
- [Let's Encrypt](https://letsencrypt.org/) - Free SSL certificates
- [WordPress](https://wordpress.org/) - Content management system 