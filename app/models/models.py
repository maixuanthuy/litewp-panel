from sqlalchemy import Column, Integer, String, Boolean, DateTime, Text
from sqlalchemy.sql import func
from app.database.database import Base

class WordPressSite(Base):
    __tablename__ = "wordpress_sites"
    
    id = Column(Integer, primary_key=True, index=True)
    domain = Column(String, unique=True, nullable=False, index=True)
    wp_version = Column(String, default="latest")
    db_name = Column(String, nullable=False)
    db_user = Column(String, nullable=False)
    db_password = Column(String, nullable=False)
    status = Column(String, default="active")
    ssl_enabled = Column(Boolean, default=False)
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())

class AdminSettings(Base):
    __tablename__ = "admin_settings"
    
    id = Column(Integer, primary_key=True, index=True)
    admin_email = Column(String)
    backup_retention = Column(Integer, default=7)
    auto_ssl = Column(Boolean, default=True)
    security_level = Column(String, default="medium")
    created_at = Column(DateTime(timezone=True), server_default=func.now())

class BackupLog(Base):
    __tablename__ = "backup_logs"
    
    id = Column(Integer, primary_key=True, index=True)
    site_id = Column(Integer)
    backup_file = Column(String)
    backup_size = Column(Integer)
    status = Column(String)  # success, failed
    created_at = Column(DateTime(timezone=True), server_default=func.now())

class SecurityLog(Base):
    __tablename__ = "security_logs"
    
    id = Column(Integer, primary_key=True, index=True)
    site_id = Column(Integer)
    scan_type = Column(String)  # malware, permission, hidden_files
    findings = Column(Text)
    status = Column(String)  # clean, suspicious, infected
    created_at = Column(DateTime(timezone=True), server_default=func.now()) 