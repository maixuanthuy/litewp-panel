import subprocess
import os
import secrets
import string
import requests
from datetime import datetime

from app.config.settings import settings

class WordPressManager:
    def __init__(self):
        self.wordpress_dir = settings.wordpress_dir
        
    def generate_password(self, length=16):
        """Generate a secure random password"""
        alphabet = string.ascii_letters + string.digits + "!@#$%^&*"
        return ''.join(secrets.choice(alphabet) for i in range(length))
    
    def create_site(self, domain):
        """Create a new WordPress site"""
        try:
            # Create site directory
            site_path = os.path.join(self.wordpress_dir, domain)
            os.makedirs(site_path, exist_ok=True)
            
            # Download WordPress
            wp_url = "https://wordpress.org/latest.zip"
            wp_zip = os.path.join(site_path, "wordpress.zip")
            
            # Download WordPress
            response = requests.get(wp_url, stream=True)
            with open(wp_zip, 'wb') as f:
                for chunk in response.iter_content(chunk_size=8192):
                    f.write(chunk)
            
            # Extract WordPress
            import zipfile
            with zipfile.ZipFile(wp_zip, 'r') as zip_ref:
                zip_ref.extractall(site_path)
            
            # Move files from wordpress subdirectory
            wp_subdir = os.path.join(site_path, "wordpress")
            if os.path.exists(wp_subdir):
                for item in os.listdir(wp_subdir):
                    src = os.path.join(wp_subdir, item)
                    dst = os.path.join(site_path, item)
                    if os.path.isdir(src):
                        os.rename(src, dst)
                    else:
                        os.rename(src, dst)
                os.rmdir(wp_subdir)
            
            # Remove zip file
            os.remove(wp_zip)
            
            # Create database
            db_name = f"wp_{domain.replace('.', '_')}"
            db_user = f"wp_{domain.replace('.', '_')}"
            db_password = self.generate_password()
            
            # Create MySQL database and user
            self._create_mysql_database(db_name, db_user, db_password)
            
            # Configure wp-config.php
            self._configure_wp_config(site_path, db_name, db_user, db_password)
            
            # Set proper permissions
            self._set_permissions(site_path)
            
            return {
                'wp_version': 'latest',
                'db_name': db_name,
                'db_user': db_user,
                'db_password': db_password
            }
            
        except Exception as e:
            raise Exception(f"Failed to create WordPress site: {str(e)}")
    
    def _create_mysql_database(self, db_name, db_user, db_password):
        """Create MySQL database and user"""
        try:
            # Create database
            subprocess.run([
                "mysql", "-e", f"CREATE DATABASE IF NOT EXISTS {db_name};"
            ], check=True)
            
            # Create user
            subprocess.run([
                "mysql", "-e", f"CREATE USER IF NOT EXISTS '{db_user}'@'localhost' IDENTIFIED BY '{db_password}';"
            ], check=True)
            
            # Grant privileges
            subprocess.run([
                "mysql", "-e", f"GRANT ALL PRIVILEGES ON {db_name}.* TO '{db_user}'@'localhost';"
            ], check=True)
            
            # Flush privileges
            subprocess.run([
                "mysql", "-e", "FLUSH PRIVILEGES;"
            ], check=True)
            
        except subprocess.CalledProcessError as e:
            raise Exception(f"Failed to create MySQL database: {str(e)}")
    
    def _configure_wp_config(self, site_path, db_name, db_user, db_password):
        """Configure wp-config.php file"""
        try:
            wp_config_sample = os.path.join(site_path, "wp-config-sample.php")
            wp_config = os.path.join(site_path, "wp-config.php")
            
            if not os.path.exists(wp_config_sample):
                raise Exception("wp-config-sample.php not found")
            
            # Read sample config
            with open(wp_config_sample, 'r') as f:
                content = f.read()
            
            # Generate authentication keys
            auth_keys = self._generate_auth_keys()
            
            # Replace placeholders
            content = content.replace("define( 'DB_NAME', 'database_name_here' );", f"define( 'DB_NAME', '{db_name}' );")
            content = content.replace("define( 'DB_USER', 'username_here' );", f"define( 'DB_USER', '{db_user}' );")
            content = content.replace("define( 'DB_PASSWORD', 'password_here' );", f"define( 'DB_PASSWORD', '{db_password}' );")
            content = content.replace("define( 'DB_HOST', 'localhost' );", "define( 'DB_HOST', 'localhost' );")
            
            # Add authentication keys
            content = content.replace("/* Add any other values to this file. */", auth_keys + "\n\n/* Add any other values to this file. */")
            
            # Write wp-config.php
            with open(wp_config, 'w') as f:
                f.write(content)
            
            # Remove sample file
            os.remove(wp_config_sample)
            
        except Exception as e:
            raise Exception(f"Failed to configure wp-config.php: {str(e)}")
    
    def _generate_auth_keys(self):
        """Generate WordPress authentication keys"""
        auth_keys = []
        key_names = [
            'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY',
            'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'
        ]
        
        for key_name in key_names:
            key_value = self.generate_password(64)
            auth_keys.append(f"define( '{key_name}', '{key_value}' );")
        
        return "\n".join(auth_keys)
    
    def _set_permissions(self, site_path):
        """Set proper file permissions"""
        try:
            # Set directory permissions
            subprocess.run(["chmod", "755", site_path], check=True)
            
            # Set file permissions
            for root, dirs, files in os.walk(site_path):
                for d in dirs:
                    os.chmod(os.path.join(root, d), 0o755)
                for f in files:
                    os.chmod(os.path.join(root, f), 0o644)
            
            # Set special permissions for wp-config.php
            wp_config = os.path.join(site_path, "wp-config.php")
            if os.path.exists(wp_config):
                os.chmod(wp_config, 0o600)
            
        except Exception as e:
            raise Exception(f"Failed to set permissions: {str(e)}")
    
    def update_site(self, domain):
        """Update WordPress site to latest version"""
        try:
            site_path = os.path.join(self.wordpress_dir, domain)
            
            if not os.path.exists(site_path):
                raise Exception(f"Site directory not found: {site_path}")
            
            # Backup current site
            backup_path = f"{site_path}_backup_{datetime.now().strftime('%Y%m%d_%H%M%S')}"
            subprocess.run(["cp", "-r", site_path, backup_path], check=True)
            
            # Download latest WordPress
            wp_url = "https://wordpress.org/latest.zip"
            wp_zip = os.path.join(site_path, "wordpress_update.zip")
            
            response = requests.get(wp_url, stream=True)
            with open(wp_zip, 'wb') as f:
                for chunk in response.iter_content(chunk_size=8192):
                    f.write(chunk)
            
            # Extract to temporary directory
            import zipfile
            temp_dir = os.path.join(site_path, "temp_update")
            with zipfile.ZipFile(wp_zip, 'r') as zip_ref:
                zip_ref.extractall(temp_dir)
            
            # Copy new files (excluding wp-config.php and wp-content)
            wp_new = os.path.join(temp_dir, "wordpress")
            for item in os.listdir(wp_new):
                src = os.path.join(wp_new, item)
                dst = os.path.join(site_path, item)
                
                if item not in ['wp-config.php', 'wp-content']:
                    if os.path.isdir(src):
                        if os.path.exists(dst):
                            subprocess.run(["rm", "-rf", dst], check=True)
                        os.rename(src, dst)
                    else:
                        if os.path.exists(dst):
                            os.remove(dst)
                        os.rename(src, dst)
            
            # Clean up
            subprocess.run(["rm", "-rf", temp_dir], check=True)
            os.remove(wp_zip)
            
            return True
            
        except Exception as e:
            raise Exception(f"Failed to update WordPress site: {str(e)}")
    
    def delete_site(self, domain):
        """Delete WordPress site"""
        try:
            site_path = os.path.join(self.wordpress_dir, domain)
            
            # Remove site files
            if os.path.exists(site_path):
                subprocess.run(["rm", "-rf", site_path], check=True)
            
            # Remove database
            db_name = f"wp_{domain.replace('.', '_')}"
            subprocess.run([
                "mysql", "-e", f"DROP DATABASE IF EXISTS {db_name};"
            ], check=True)
            
            return True
            
        except Exception as e:
            raise Exception(f"Failed to delete WordPress site: {str(e)}") 