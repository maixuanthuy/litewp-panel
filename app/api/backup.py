from fastapi import APIRouter, HTTPException, Depends, status
from sqlalchemy.orm import Session
import subprocess
import os
import zipfile
from datetime import datetime
import shutil

from app.database.database import get_db
from app.models.models import WordPressSite, BackupLog
from app.config.settings import settings

router = APIRouter()

@router.post("/{site_id}/create")
async def create_backup(site_id: int, db: Session = Depends(get_db)):
    """Create backup for WordPress site"""
    site = db.query(WordPressSite).filter(WordPressSite.id == site_id).first()
    if not site:
        raise HTTPException(status_code=404, detail="Site not found")
    
    try:
        # Create backup directory
        backup_dir = os.path.join(settings.backup_dir, site.domain)
        os.makedirs(backup_dir, exist_ok=True)
        
        # Create backup filename with timestamp
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        backup_file = os.path.join(backup_dir, f"{site.domain}_{timestamp}.zip")
        
        # Backup site files
        site_path = os.path.join(settings.wordpress_dir, site.domain)
        if os.path.exists(site_path):
            with zipfile.ZipFile(backup_file, 'w', zipfile.ZIP_DEFLATED) as zipf:
                for root, dirs, files in os.walk(site_path):
                    for file in files:
                        file_path = os.path.join(root, file)
                        arcname = os.path.relpath(file_path, site_path)
                        zipf.write(file_path, arcname)
        
        # Backup database
        db_backup_file = os.path.join(backup_dir, f"{site.domain}_{timestamp}_db.sql")
        subprocess.run([
            "mysqldump", site.db_name
        ], stdout=open(db_backup_file, 'w'), check=True)
        
        # Log backup
        backup_log = BackupLog(
            site_id=site.id,
            backup_file=backup_file,
            backup_size=os.path.getsize(backup_file),
            status="success"
        )
        db.add(backup_log)
        db.commit()
        
        return {
            "message": f"Backup created for {site.domain}",
            "backup_file": backup_file,
            "db_backup_file": db_backup_file
        }
        
    except Exception as e:
        # Log failed backup
        backup_log = BackupLog(
            site_id=site.id,
            backup_file="",
            backup_size=0,
            status="failed"
        )
        db.add(backup_log)
        db.commit()
        
        raise HTTPException(status_code=500, detail=str(e))

@router.get("/{site_id}/list")
async def list_backups(site_id: int, db: Session = Depends(get_db)):
    """List backups for WordPress site"""
    site = db.query(WordPressSite).filter(WordPressSite.id == site_id).first()
    if not site:
        raise HTTPException(status_code=404, detail="Site not found")
    
    backup_dir = os.path.join(settings.backup_dir, site.domain)
    backups = []
    
    if os.path.exists(backup_dir):
        for file in os.listdir(backup_dir):
            if file.endswith('.zip'):
                file_path = os.path.join(backup_dir, file)
                backups.append({
                    "filename": file,
                    "size": os.path.getsize(file_path),
                    "created_at": datetime.fromtimestamp(os.path.getctime(file_path))
                })
    
    return {"backups": backups}

@router.post("/{site_id}/restore")
async def restore_backup(site_id: int, backup_file: str, db: Session = Depends(get_db)):
    """Restore WordPress site from backup"""
    site = db.query(WordPressSite).filter(WordPressSite.id == site_id).first()
    if not site:
        raise HTTPException(status_code=404, detail="Site not found")
    
    try:
        backup_path = os.path.join(settings.backup_dir, site.domain, backup_file)
        if not os.path.exists(backup_path):
            raise HTTPException(status_code=404, detail="Backup file not found")
        
        # Stop site temporarily
        site.status = "maintenance"
        db.commit()
        
        # Restore files
        site_path = os.path.join(settings.wordpress_dir, site.domain)
        if os.path.exists(site_path):
            shutil.rmtree(site_path)
        
        with zipfile.ZipFile(backup_path, 'r') as zipf:
            zipf.extractall(site_path)
        
        # Restore database
        db_backup_file = backup_path.replace('.zip', '_db.sql')
        if os.path.exists(db_backup_file):
            subprocess.run([
                "mysql", site.db_name
            ], stdin=open(db_backup_file, 'r'), check=True)
        
        # Restore site status
        site.status = "active"
        db.commit()
        
        return {"message": f"Site {site.domain} restored successfully"}
        
    except Exception as e:
        # Restore site status on error
        site.status = "active"
        db.commit()
        raise HTTPException(status_code=500, detail=str(e))

@router.delete("/{site_id}/delete")
async def delete_backup(site_id: int, backup_file: str, db: Session = Depends(get_db)):
    """Delete backup file"""
    site = db.query(WordPressSite).filter(WordPressSite.id == site_id).first()
    if not site:
        raise HTTPException(status_code=404, detail="Site not found")
    
    try:
        backup_path = os.path.join(settings.backup_dir, site.domain, backup_file)
        if not os.path.exists(backup_path):
            raise HTTPException(status_code=404, detail="Backup file not found")
        
        # Delete backup file
        os.remove(backup_path)
        
        # Delete database backup if exists
        db_backup_file = backup_path.replace('.zip', '_db.sql')
        if os.path.exists(db_backup_file):
            os.remove(db_backup_file)
        
        return {"message": f"Backup {backup_file} deleted successfully"}
        
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.post("/cleanup")
async def cleanup_old_backups(db: Session = Depends(get_db)):
    """Clean up old backups based on retention policy"""
    try:
        # Get retention days from settings
        retention_days = settings.backup_retention_days
        
        # Find and delete old backups
        backup_dir = settings.backup_dir
        deleted_count = 0
        
        for root, dirs, files in os.walk(backup_dir):
            for file in files:
                if file.endswith('.zip') or file.endswith('.sql'):
                    file_path = os.path.join(root, file)
                    file_age = (datetime.now() - datetime.fromtimestamp(os.path.getctime(file_path))).days
                    
                    if file_age > retention_days:
                        os.remove(file_path)
                        deleted_count += 1
        
        return {"message": f"Cleaned up {deleted_count} old backup files"}
        
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e)) 