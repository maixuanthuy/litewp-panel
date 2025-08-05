from fastapi import APIRouter, HTTPException, Depends, status
from sqlalchemy.orm import Session
import subprocess
import os
import psutil
from datetime import datetime

from app.database.database import get_db
from app.models.models import WordPressSite, BackupLog, SecurityLog
from app.config.settings import settings

router = APIRouter()

@router.get("/system")
async def get_system_stats():
    """Get system statistics"""
    try:
        # CPU usage
        cpu_percent = psutil.cpu_percent(interval=1)
        
        # Memory usage
        memory = psutil.virtual_memory()
        
        # Disk usage
        disk = psutil.disk_usage('/')
        
        # Network usage
        network = psutil.net_io_counters()
        
        return {
            "cpu": {
                "usage_percent": cpu_percent,
                "count": psutil.cpu_count()
            },
            "memory": {
                "total": memory.total,
                "available": memory.available,
                "used": memory.used,
                "percent": memory.percent
            },
            "disk": {
                "total": disk.total,
                "used": disk.used,
                "free": disk.free,
                "percent": (disk.used / disk.total) * 100
            },
            "network": {
                "bytes_sent": network.bytes_sent,
                "bytes_recv": network.bytes_recv
            }
        }
        
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.get("/sites")
async def get_sites_stats(db: Session = Depends(get_db)):
    """Get WordPress sites statistics"""
    try:
        # Total sites
        total_sites = db.query(WordPressSite).count()
        
        # Active sites
        active_sites = db.query(WordPressSite).filter(WordPressSite.status == "active").count()
        
        # SSL enabled sites
        ssl_sites = db.query(WordPressSite).filter(WordPressSite.ssl_enabled == True).count()
        
        # Sites by status
        sites_by_status = db.query(WordPressSite.status, db.func.count(WordPressSite.id)).group_by(WordPressSite.status).all()
        
        # Total disk usage for WordPress sites
        total_size = 0
        for site in db.query(WordPressSite).all():
            site_path = os.path.join(settings.wordpress_dir, site.domain)
            if os.path.exists(site_path):
                for root, dirs, files in os.walk(site_path):
                    for file in files:
                        file_path = os.path.join(root, file)
                        if os.path.exists(file_path):
                            total_size += os.path.getsize(file_path)
        
        return {
            "total_sites": total_sites,
            "active_sites": active_sites,
            "ssl_sites": ssl_sites,
            "sites_by_status": dict(sites_by_status),
            "total_disk_usage": total_size
        }
        
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.get("/backups")
async def get_backup_stats(db: Session = Depends(get_db)):
    """Get backup statistics"""
    try:
        # Total backups
        total_backups = db.query(BackupLog).count()
        
        # Successful backups
        successful_backups = db.query(BackupLog).filter(BackupLog.status == "success").count()
        
        # Failed backups
        failed_backups = db.query(BackupLog).filter(BackupLog.status == "failed").count()
        
        # Total backup size
        total_size = db.query(db.func.sum(BackupLog.backup_size)).scalar() or 0
        
        # Recent backups (last 7 days)
        from datetime import timedelta
        week_ago = datetime.now() - timedelta(days=7)
        recent_backups = db.query(BackupLog).filter(BackupLog.created_at >= week_ago).count()
        
        return {
            "total_backups": total_backups,
            "successful_backups": successful_backups,
            "failed_backups": failed_backups,
            "total_size": total_size,
            "recent_backups": recent_backups
        }
        
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.get("/security")
async def get_security_stats(db: Session = Depends(get_db)):
    """Get security statistics"""
    try:
        # Total security scans
        total_scans = db.query(SecurityLog).count()
        
        # Clean sites
        clean_sites = db.query(SecurityLog).filter(SecurityLog.status == "clean").count()
        
        # Suspicious sites
        suspicious_sites = db.query(SecurityLog).filter(SecurityLog.status == "suspicious").count()
        
        # Infected sites
        infected_sites = db.query(SecurityLog).filter(SecurityLog.status == "infected").count()
        
        # Recent scans (last 24 hours)
        from datetime import timedelta
        day_ago = datetime.now() - timedelta(hours=24)
        recent_scans = db.query(SecurityLog).filter(SecurityLog.created_at >= day_ago).count()
        
        return {
            "total_scans": total_scans,
            "clean_sites": clean_sites,
            "suspicious_sites": suspicious_sites,
            "infected_sites": infected_sites,
            "recent_scans": recent_scans
        }
        
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.get("/overview")
async def get_overview_stats(db: Session = Depends(get_db)):
    """Get overview statistics"""
    try:
        # System stats
        system_stats = await get_system_stats()
        
        # Sites stats
        sites_stats = await get_sites_stats(db)
        
        # Backup stats
        backup_stats = await get_backup_stats(db)
        
        # Security stats
        security_stats = await get_security_stats(db)
        
        return {
            "system": system_stats,
            "sites": sites_stats,
            "backups": backup_stats,
            "security": security_stats,
            "timestamp": datetime.now().isoformat()
        }
        
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e)) 