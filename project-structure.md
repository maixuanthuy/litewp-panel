# LiteWP Project Structure

## Repository Structure

```
litewp-panel/
├── README.md
├── LICENSE
├── install.sh                    # Main installation script
├── uninstall.sh                  # Uninstallation script
├── update.sh                     # Update script
├── .github/
│   └── workflows/
│       ├── release.yml          # Auto release workflow
│       └── test.yml             # CI/CD workflow
├── docs/
│   ├── installation.md
│   ├── configuration.md
│   ├── api-reference.md
│   └── troubleshooting.md
├── scripts/
│   ├── setup/
│   │   ├── install-ols.sh       # OpenLiteSpeed installation
│   │   ├── install-php.sh       # PHP 8.3 setup
│   │   ├── install-mariadb.sh   # MariaDB setup
│   │   ├── install-redis.sh     # Redis setup
│   │   └── configure-system.sh  # System configuration
│   ├── website/
│   │   ├── create-website.sh    # Create new website
│   │   ├── delete-website.sh    # Delete website
│   │   ├── backup-website.sh    # Backup website
│   │   └── restore-website.sh   # Restore website
│   ├── wordpress/
│   │   ├── install-wp.sh        # Install WordPress
│   │   ├── update-wp.sh         # Update WordPress
│   │   ├── install-plugin.sh    # Install plugin
│   │   └── install-theme.sh     # Install theme
│   ├── ssl/
│   │   ├── install-letsencrypt.sh # Let's Encrypt SSL
│   │   ├── install-custom-ssl.sh  # Custom SSL
│   │   └── renew-ssl.sh        # SSL renewal
│   ├── database/
│   │   ├── create-db.sh         # Create database
│   │   ├── delete-db.sh         # Delete database
│   │   ├── backup-db.sh         # Backup database
│   │   └── restore-db.sh        # Restore database
│   └── firewall/
│       ├── configure-iptables.sh # Configure iptables
│       ├── add-rule.sh          # Add firewall rule
│       └── remove-rule.sh       # Remove firewall rule
├── backend/
│   ├── api/
│   │   ├── index.php            # Main API entry point
│   │   ├── auth.php             # Authentication
│   │   ├── dashboard.php        # Dashboard API
│   │   ├── websites.php         # Websites management
│   │   ├── wordpress.php        # WordPress tools
│   │   ├── database.php         # Database management
│   │   ├── ssl.php              # SSL management
│   │   ├── firewall.php         # Firewall management
│   │   ├── cron.php             # Cron job management
│   │   ├── settings.php         # Panel settings
│   │   └── logs.php             # Log management
│   ├── core/
│   │   ├── Database.php         # Database wrapper
│   │   ├── Auth.php             # Authentication class
│   │   ├── Website.php          # Website management
│   │   ├── WordPress.php        # WordPress tools
│   │   ├── SSL.php              # SSL management
│   │   ├── Firewall.php         # Firewall management
│   │   ├── Backup.php           # Backup system
│   │   └── Logger.php           # Logging system
│   ├── config/
│   │   ├── config.php           # Main configuration
│   │   ├── database.php         # Database config
│   │   └── security.php         # Security settings
│   ├── utils/
│   │   ├── FileManager.php      # File operations
│   │   ├── SystemInfo.php       # System information
│   │   ├── ProcessManager.php   # Process management
│   │   └── Validator.php        # Input validation
│   └── vendor/                  # Composer dependencies
├── frontend/
│   ├── src/
│   │   ├── components/
│   │   │   ├── ui/              # Radix UI components
│   │   │   ├── layout/
│   │   │   │   ├── Header.tsx
│   │   │   │   ├── Sidebar.tsx
│   │   │   │   └── Footer.tsx
│   │   │   ├── dashboard/
│   │   │   │   ├── SystemInfo.tsx
│   │   │   │   ├── ServiceStatus.tsx
│   │   │   │   └── ResourceUsage.tsx
│   │   │   ├── websites/
│   │   │   │   ├── WebsiteList.tsx
│   │   │   │   ├── WebsiteCard.tsx
│   │   │   │   ├── WebsiteForm.tsx
│   │   │   │   └── WebsiteSettings.tsx
│   │   │   ├── wordpress/
│   │   │   │   ├── WordPressTools.tsx
│   │   │   │   ├── PluginManager.tsx
│   │   │   │   └── ThemeManager.tsx
│   │   │   ├── database/
│   │   │   │   ├── DatabaseList.tsx
│   │   │   │   ├── DatabaseForm.tsx
│   │   │   │   └── Adminer.tsx
│   │   │   ├── ssl/
│   │   │   │   ├── SSLCertificates.tsx
│   │   │   │   ├── LetsEncrypt.tsx
│   │   │   │   └── CustomSSL.tsx
│   │   │   ├── firewall/
│   │   │   │   ├── FirewallRules.tsx
│   │   │   │   ├── PortManager.tsx
│   │   │   │   └── IPWhitelist.tsx
│   │   │   ├── cron/
│   │   │   │   ├── CronJobs.tsx
│   │   │   │   └── CronForm.tsx
│   │   │   ├── settings/
│   │   │   │   ├── PanelSettings.tsx
│   │   │   │   ├── SecuritySettings.tsx
│   │   │   │   └── BackupSettings.tsx
│   │   │   └── logs/
│   │   │       ├── AccessLogs.tsx
│   │   │       └── ErrorLogs.tsx
│   │   ├── pages/
│   │   │   ├── Dashboard.tsx
│   │   │   ├── Websites.tsx
│   │   │   ├── WordPress.tsx
│   │   │   ├── Database.tsx
│   │   │   ├── SSL.tsx
│   │   │   ├── Firewall.tsx
│   │   │   ├── Cron.tsx
│   │   │   ├── Settings.tsx
│   │   │   └── Logs.tsx
│   │   ├── hooks/
│   │   │   ├── useAuth.ts
│   │   │   ├── useWebsites.ts
│   │   │   ├── useWordPress.ts
│   │   │   └── useSystem.ts
│   │   ├── stores/
│   │   │   ├── authStore.ts
│   │   │   ├── websiteStore.ts
│   │   │   └── systemStore.ts
│   │   ├── services/
│   │   │   ├── api.ts
│   │   │   ├── auth.ts
│   │   │   ├── websites.ts
│   │   │   └── system.ts
│   │   ├── utils/
│   │   │   ├── constants.ts
│   │   │   ├── helpers.ts
│   │   │   └── validators.ts
│   │   ├── types/
│   │   │   ├── website.ts
│   │   │   ├── wordpress.ts
│   │   │   └── system.ts
│   │   ├── styles/
│   │   │   └── globals.css
│   │   ├── App.tsx
│   │   ├── main.tsx
│   │   └── vite-env.d.ts
│   ├── public/
│   │   ├── index.html
│   │   ├── favicon.ico
│   │   └── assets/
│   ├── package.json
│   ├── vite.config.ts
│   ├── tailwind.config.js
│   ├── tsconfig.json
│   └── .eslintrc.js
├── tools/
│   ├── filemanager/
│   │   └── tinyfilemanager.php   # File manager
│   ├── database/
│   │   └── adminer.php          # Database admin
│   └── wordpress/
│       └── wp-cli.phar          # WordPress CLI
├── templates/
│   ├── vhost/
│   │   ├── default.conf         # Default vhost template
│   │   ├── wordpress.conf       # WordPress vhost template
│   │   └── ssl.conf            # SSL vhost template
│   ├── php/
│   │   └── php.ini             # PHP configuration template
│   └── nginx/
│       └── default.conf         # Nginx template (if needed)
├── composer.json
├── package.json
└── .gitignore
```

## Installation Structure (After Installation)

```
/usr/local/litewp/
├── panel/                       # Panel files
│   ├── backend/                 # Backend files
│   ├── frontend/                # Frontend build
│   ├── tools/                   # Adminer, FileManager
│   └── logs/                    # Panel logs
├── websites/                    # All websites
│   ├── example.com/
│   │   ├── public_html/         # Document root
│   │   ├── logs/               # Website logs
│   │   ├── backups/            # Website backups
│   │   └── ssl/               # SSL certificates
│   └── another-site.com/
├── config/
│   ├── panel.db                # SQLite database
│   ├── panel.conf              # Panel configuration
│   └── ssl/                    # SSL certificates
├── scripts/                     # Management scripts
├── backups/                     # System backups
└── logs/                        # System logs

/etc/systemd/system/
├── litewp-panel.service         # Panel service
└── litewp-backup.service        # Backup service

/etc/nginx/sites-available/
└── litewp-panel                # Panel nginx config

/etc/nginx/sites-enabled/
└── litewp-panel -> ../sites-available/litewp-panel
``` 