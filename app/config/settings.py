import os
from pydantic_settings import BaseSettings

class Settings(BaseSettings):
    # Application settings
    app_name: str = "LiteWP Panel"
    app_version: str = "1.0.0"
    debug: bool = False
    
    # Database settings
    database_url: str = "sqlite:////var/litewp/panel/database/panel.db"
    
    # Security settings
    secret_key: str = "your-secret-key-change-this"
    algorithm: str = "HS256"
    access_token_expire_minutes: int = 30
    
    # File paths
    wordpress_dir: str = "/var/litewp/wordpress"
    backup_dir: str = "/var/litewp/backups"
    logs_dir: str = "/var/litewp/logs"
    ssl_dir: str = "/var/litewp/ssl"
    
    # Backup settings
    backup_retention_days: int = 7
    auto_backup: bool = True
    
    # SSL settings
    auto_ssl: bool = True
    ssl_email: str = "admin@example.com"
    
    # Security settings
    security_level: str = "medium"  # low, medium, high
    scan_frequency: int = 24  # hours
    
    # OpenLiteSpeed settings
    ols_admin_port: int = 7080
    ols_admin_user: str = "admin"
    ols_admin_password: str = "admin"
    
    class Config:
        env_file = ".env"

# Create settings instance
settings = Settings() 