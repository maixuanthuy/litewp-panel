from fastapi import APIRouter, HTTPException, Depends, status
from sqlalchemy.orm import Session
import subprocess
import os
from datetime import datetime

from app.database.database import get_db
from app.models.models import WordPressSite
from app.config.settings import settings

router = APIRouter()

@router.post("/{site_id}/enable")
async def enable_ssl(site_id: int, db: Session = Depends(get_db)):
    """Enable SSL for WordPress site"""
    site = db.query(WordPressSite).filter(WordPressSite.id == site_id).first()
    if not site:
        raise HTTPException(status_code=404, detail="Site not found")
    
    try:
        # Check if certbot is installed
        if not subprocess.run(["which", "certbot"], capture_output=True).returncode == 0:
            raise HTTPException(status_code=500, detail="Certbot not installed")
        
        # Generate SSL certificate using Let's Encrypt
        result = subprocess.run([
            "certbot", "--nginx", "-d", site.domain,
            "--non-interactive", "--agree-tos",
            "--email", settings.ssl_email
        ], capture_output=True, text=True)
        
        if result.returncode == 0:
            site.ssl_enabled = True
            db.commit()
            return {"message": f"SSL enabled for {site.domain}"}
        else:
            raise HTTPException(status_code=500, detail=f"SSL certificate generation failed: {result.stderr}")
            
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.post("/{site_id}/disable")
async def disable_ssl(site_id: int, db: Session = Depends(get_db)):
    """Disable SSL for WordPress site"""
    site = db.query(WordPressSite).filter(WordPressSite.id == site_id).first()
    if not site:
        raise HTTPException(status_code=404, detail="Site not found")
    
    try:
        # Remove SSL certificate
        result = subprocess.run([
            "certbot", "delete", "--cert-name", site.domain
        ], capture_output=True, text=True)
        
        site.ssl_enabled = False
        db.commit()
        
        return {"message": f"SSL disabled for {site.domain}"}
        
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.get("/{site_id}/status")
async def get_ssl_status(site_id: int, db: Session = Depends(get_db)):
    """Get SSL status for WordPress site"""
    site = db.query(WordPressSite).filter(WordPressSite.id == site_id).first()
    if not site:
        raise HTTPException(status_code=404, detail="Site not found")
    
    try:
        # Check certificate status
        result = subprocess.run([
            "certbot", "certificates"
        ], capture_output=True, text=True)
        
        if site.domain in result.stdout:
            return {
                "domain": site.domain,
                "ssl_enabled": True,
                "status": "active"
            }
        else:
            return {
                "domain": site.domain,
                "ssl_enabled": False,
                "status": "inactive"
            }
            
    except Exception as e:
        return {
            "domain": site.domain,
            "ssl_enabled": site.ssl_enabled,
            "status": "unknown",
            "error": str(e)
        }

@router.post("/renew")
async def renew_ssl_certificates():
    """Renew all SSL certificates"""
    try:
        result = subprocess.run([
            "certbot", "renew", "--quiet"
        ], capture_output=True, text=True)
        
        if result.returncode == 0:
            return {"message": "SSL certificates renewed successfully"}
        else:
            raise HTTPException(status_code=500, detail=f"SSL renewal failed: {result.stderr}")
            
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.get("/certificates")
async def list_certificates():
    """List all SSL certificates"""
    try:
        result = subprocess.run([
            "certbot", "certificates"
        ], capture_output=True, text=True)
        
        return {"certificates": result.stdout}
        
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e)) 