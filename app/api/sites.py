from fastapi import APIRouter, HTTPException, Depends, status
from sqlalchemy.orm import Session
from typing import List, Optional
import subprocess
import os
import secrets
import string
from datetime import datetime

from app.database.database import get_db
from app.models.models import WordPressSite
from app.utils.wordpress import WordPressManager
from app.config.settings import settings

router = APIRouter()

@router.post("/add")
async def add_wordpress_site(domain: str, db: Session = Depends(get_db)):
    """Add new WordPress site"""
    try:
        # Validate domain
        if not domain or "." not in domain:
            raise HTTPException(status_code=400, detail="Invalid domain")
        
        # Check if site exists
        existing_site = db.query(WordPressSite).filter(WordPressSite.domain == domain).first()
        if existing_site:
            raise HTTPException(status_code=400, detail="Site already exists")
        
        # Create WordPress site
        wp_manager = WordPressManager()
        site_info = wp_manager.create_site(domain)
        
        # Save to database
        new_site = WordPressSite(
            domain=domain,
            wp_version=site_info['wp_version'],
            db_name=site_info['db_name'],
            db_user=site_info['db_user'],
            db_password=site_info['db_password']
        )
        
        db.add(new_site)
        db.commit()
        db.refresh(new_site)
        
        return {
            "message": f"WordPress site {domain} created successfully",
            "site_id": new_site.id,
            "domain": new_site.domain,
            "status": new_site.status
        }
        
    except Exception as e:
        db.rollback()
        raise HTTPException(status_code=500, detail=str(e))

@router.get("/list")
async def list_sites(db: Session = Depends(get_db)):
    """List all WordPress sites"""
    sites = db.query(WordPressSite).all()
    return {
        "sites": [
            {
                "id": site.id,
                "domain": site.domain,
                "wp_version": site.wp_version,
                "status": site.status,
                "ssl_enabled": site.ssl_enabled,
                "created_at": site.created_at
            } for site in sites
        ]
    }

@router.get("/{site_id}")
async def get_site(site_id: int, db: Session = Depends(get_db)):
    """Get specific WordPress site"""
    site = db.query(WordPressSite).filter(WordPressSite.id == site_id).first()
    if not site:
        raise HTTPException(status_code=404, detail="Site not found")
    
    return {
        "id": site.id,
        "domain": site.domain,
        "wp_version": site.wp_version,
        "status": site.status,
        "ssl_enabled": site.ssl_enabled,
        "created_at": site.created_at,
        "updated_at": site.updated_at
    }

@router.delete("/{site_id}")
async def delete_site(site_id: int, db: Session = Depends(get_db)):
    """Delete WordPress site"""
    site = db.query(WordPressSite).filter(WordPressSite.id == site_id).first()
    if not site:
        raise HTTPException(status_code=404, detail="Site not found")
    
    try:
        # Remove site files
        site_path = os.path.join(settings.wordpress_dir, site.domain)
        if os.path.exists(site_path):
            subprocess.run(["rm", "-rf", site_path], check=True)
        
        # Remove database
        subprocess.run([
            "mysql", "-e", f"DROP DATABASE IF EXISTS {site.db_name};"
        ], check=True)
        
        # Remove from database
        db.delete(site)
        db.commit()
        
        return {"message": f"Site {site.domain} deleted successfully"}
        
    except Exception as e:
        db.rollback()
        raise HTTPException(status_code=500, detail=str(e))

@router.put("/{site_id}/status")
async def update_site_status(site_id: int, status: str, db: Session = Depends(get_db)):
    """Update site status"""
    site = db.query(WordPressSite).filter(WordPressSite.id == site_id).first()
    if not site:
        raise HTTPException(status_code=404, detail="Site not found")
    
    if status not in ["active", "suspended", "maintenance"]:
        raise HTTPException(status_code=400, detail="Invalid status")
    
    site.status = status
    db.commit()
    
    return {"message": f"Site {site.domain} status updated to {status}"}

@router.post("/{site_id}/update")
async def update_wordpress(site_id: int, db: Session = Depends(get_db)):
    """Update WordPress to latest version"""
    site = db.query(WordPressSite).filter(WordPressSite.id == site_id).first()
    if not site:
        raise HTTPException(status_code=404, detail="Site not found")
    
    try:
        wp_manager = WordPressManager()
        wp_manager.update_site(site.domain)
        
        site.wp_version = "latest"
        db.commit()
        
        return {"message": f"WordPress updated for {site.domain}"}
        
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e)) 