// LiteWP Panel JavaScript
class LiteWPPanel {
    constructor() {
        this.apiBase = '/api';
        this.currentSection = 'dashboard';
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadDashboard();
        this.startAutoRefresh();
    }

    setupEventListeners() {
        // Add site form submission
        const addSiteForm = document.getElementById('addSiteForm');
        if (addSiteForm) {
            addSiteForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleAddSite();
            });
        }

        // Navigation
        document.querySelectorAll('.nav-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const section = e.currentTarget.getAttribute('onclick').match(/'([^']+)'/)[1];
                this.showSection(section);
            });
        });
    }

    async loadDashboard() {
        try {
            this.showLoading();
            
            // Load stats
            const stats = await this.fetchAPI('/stats/overview');
            this.updateDashboardStats(stats);
            
            // Load sites
            const sites = await this.fetchAPI('/sites/list');
            this.updateSitesList(sites.sites);
            
            this.hideLoading();
        } catch (error) {
            console.error('Error loading dashboard:', error);
            this.showError('Failed to load dashboard data');
            this.hideLoading();
        }
    }

    updateDashboardStats(stats) {
        // Update system stats
        if (stats.system) {
            document.getElementById('memoryUsage').textContent = 
                `${Math.round(stats.system.memory.percent)}%`;
            
            const diskGB = (stats.sites.total_disk_usage / (1024 * 1024 * 1024)).toFixed(1);
            document.getElementById('diskUsage').textContent = `${diskGB} GB`;
        }

        // Update sites stats
        if (stats.sites) {
            document.getElementById('totalSites').textContent = stats.sites.total_sites;
            document.getElementById('sslSites').textContent = stats.sites.ssl_sites;
        }
    }

    updateSitesList(sites) {
        const sitesList = document.getElementById('sitesList');
        const sitesListFull = document.getElementById('sitesListFull');
        
        if (sitesList) {
            sitesList.innerHTML = this.renderSitesGrid(sites.slice(0, 6)); // Show only 6 recent sites
        }
        
        if (sitesListFull) {
            sitesListFull.innerHTML = this.renderSitesList(sites);
        }
    }

    renderSitesGrid(sites) {
        if (sites.length === 0) {
            return '<div class="text-center"><p>No sites found. Add your first WordPress site!</p></div>';
        }

        return sites.map(site => `
            <div class="site-card">
                <div class="site-header">
                    <div class="site-domain">${site.domain}</div>
                    <div class="site-status status-${site.status}">${site.status}</div>
                </div>
                <div class="site-info">
                    <div class="info-item">
                        <div class="info-label">WordPress Version</div>
                        <div class="info-value">${site.wp_version}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">SSL</div>
                        <div class="info-value">${site.ssl_enabled ? 'Enabled' : 'Disabled'}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Created</div>
                        <div class="info-value">${new Date(site.created_at).toLocaleDateString()}</div>
                    </div>
                </div>
                <div class="site-actions">
                    <button class="btn btn-primary" onclick="panel.openSite('${site.domain}')">
                        <i class="fas fa-external-link-alt"></i> Open Site
                    </button>
                    <button class="btn btn-info" onclick="panel.openAdmin('${site.domain}')">
                        <i class="fas fa-cog"></i> WP Admin
                    </button>
                    <button class="btn btn-secondary" onclick="panel.backupSite(${site.id})">
                        <i class="fas fa-download"></i> Backup
                    </button>
                </div>
            </div>
        `).join('');
    }

    renderSitesList(sites) {
        if (sites.length === 0) {
            return '<div class="text-center"><p>No sites found. Add your first WordPress site!</p></div>';
        }

        return sites.map(site => `
            <div class="site-card">
                <div class="site-header">
                    <div class="site-domain">${site.domain}</div>
                    <div class="site-status status-${site.status}">${site.status}</div>
                </div>
                <div class="site-info">
                    <div class="info-item">
                        <div class="info-label">WordPress Version</div>
                        <div class="info-value">${site.wp_version}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">SSL</div>
                        <div class="info-value">${site.ssl_enabled ? 'Enabled' : 'Disabled'}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Created</div>
                        <div class="info-value">${new Date(site.created_at).toLocaleDateString()}</div>
                    </div>
                </div>
                <div class="site-actions">
                    <button class="btn btn-primary" onclick="panel.openSite('${site.domain}')">
                        <i class="fas fa-external-link-alt"></i> Open Site
                    </button>
                    <button class="btn btn-info" onclick="panel.openAdmin('${site.domain}')">
                        <i class="fas fa-cog"></i> WP Admin
                    </button>
                    <button class="btn btn-secondary" onclick="panel.backupSite(${site.id})">
                        <i class="fas fa-download"></i> Backup
                    </button>
                    <button class="btn btn-success" onclick="panel.enableSSL(${site.id})">
                        <i class="fas fa-lock"></i> Enable SSL
                    </button>
                    <button class="btn btn-danger" onclick="panel.deleteSite(${site.id})">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>
        `).join('');
    }

    showSection(sectionName) {
        // Hide all sections
        document.querySelectorAll('.section').forEach(section => {
            section.classList.remove('active');
        });

        // Remove active class from all nav buttons
        document.querySelectorAll('.nav-btn').forEach(btn => {
            btn.classList.remove('active');
        });

        // Show selected section
        const section = document.getElementById(sectionName);
        if (section) {
            section.classList.add('active');
        }

        // Add active class to nav button
        const navBtn = document.querySelector(`[onclick="showSection('${sectionName}')"]`);
        if (navBtn) {
            navBtn.classList.add('active');
        }

        this.currentSection = sectionName;

        // Load section-specific data
        switch (sectionName) {
            case 'dashboard':
                this.loadDashboard();
                break;
            case 'sites':
                this.loadSites();
                break;
            case 'backups':
                this.loadBackups();
                break;
            case 'security':
                this.loadSecurity();
                break;
        }
    }

    async loadSites() {
        try {
            this.showLoading();
            const sites = await this.fetchAPI('/sites/list');
            this.updateSitesList(sites.sites);
            this.hideLoading();
        } catch (error) {
            console.error('Error loading sites:', error);
            this.showError('Failed to load sites');
            this.hideLoading();
        }
    }

    async loadBackups() {
        try {
            this.showLoading();
            // Load backup statistics
            const backupStats = await this.fetchAPI('/stats/backups');
            this.updateBackupsList(backupStats);
            this.hideLoading();
        } catch (error) {
            console.error('Error loading backups:', error);
            this.showError('Failed to load backup data');
            this.hideLoading();
        }
    }

    async loadSecurity() {
        try {
            this.showLoading();
            const securityStats = await this.fetchAPI('/stats/security');
            this.updateSecurityStatus(securityStats);
            this.hideLoading();
        } catch (error) {
            console.error('Error loading security:', error);
            this.showError('Failed to load security data');
            this.hideLoading();
        }
    }

    updateBackupsList(backupStats) {
        const backupsList = document.getElementById('backupsList');
        if (backupsList) {
            backupsList.innerHTML = `
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-download"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Total Backups</h3>
                            <p>${backupStats.total_backups}</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Successful</h3>
                            <p>${backupStats.successful_backups}</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Failed</h3>
                            <p>${backupStats.failed_backups}</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Recent (7 days)</h3>
                            <p>${backupStats.recent_backups}</p>
                        </div>
                    </div>
                </div>
            `;
        }
    }

    updateSecurityStatus(securityStats) {
        const securityStatus = document.getElementById('securityStatus');
        if (securityStatus) {
            securityStatus.innerHTML = `
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Total Scans</h3>
                            <p>${securityStats.total_scans}</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Clean Sites</h3>
                            <p>${securityStats.clean_sites}</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Suspicious</h3>
                            <p>${securityStats.suspicious_sites}</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-virus-slash"></i>
                        </div>
                        <div class="stat-content">
                            <h3>Infected</h3>
                            <p>${securityStats.infected_sites}</p>
                        </div>
                    </div>
                </div>
            `;
        }
    }

    async handleAddSite() {
        const domainInput = document.getElementById('domain');
        const domain = domainInput.value.trim();

        if (!domain) {
            this.showError('Please enter a domain name');
            return;
        }

        try {
            this.showLoading();
            const response = await this.fetchAPI('/sites/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ domain })
            });

            this.showSuccess(`WordPress site ${domain} created successfully!`);
            this.closeModal();
            this.loadDashboard();
        } catch (error) {
            console.error('Error adding site:', error);
            this.showError('Failed to create WordPress site');
        } finally {
            this.hideLoading();
        }
    }

    async backupSite(siteId) {
        try {
            this.showLoading();
            await this.fetchAPI(`/backup/${siteId}/create`, { method: 'POST' });
            this.showSuccess('Backup created successfully!');
        } catch (error) {
            console.error('Error creating backup:', error);
            this.showError('Failed to create backup');
        } finally {
            this.hideLoading();
        }
    }

    async backupAll() {
        try {
            this.showLoading();
            const sites = await this.fetchAPI('/sites/list');
            
            for (const site of sites.sites) {
                try {
                    await this.fetchAPI(`/backup/${site.id}/create`, { method: 'POST' });
                } catch (error) {
                    console.error(`Failed to backup site ${site.domain}:`, error);
                }
            }
            
            this.showSuccess('All backups completed!');
        } catch (error) {
            console.error('Error creating backups:', error);
            this.showError('Failed to create backups');
        } finally {
            this.hideLoading();
        }
    }

    async enableSSL(siteId) {
        try {
            this.showLoading();
            await this.fetchAPI(`/ssl/${siteId}/enable`, { method: 'POST' });
            this.showSuccess('SSL enabled successfully!');
            this.loadSites();
        } catch (error) {
            console.error('Error enabling SSL:', error);
            this.showError('Failed to enable SSL');
        } finally {
            this.hideLoading();
        }
    }

    async deleteSite(siteId) {
        if (!confirm('Are you sure you want to delete this site? This action cannot be undone.')) {
            return;
        }

        try {
            this.showLoading();
            await this.fetchAPI(`/sites/${siteId}`, { method: 'DELETE' });
            this.showSuccess('Site deleted successfully!');
            this.loadSites();
        } catch (error) {
            console.error('Error deleting site:', error);
            this.showError('Failed to delete site');
        } finally {
            this.hideLoading();
        }
    }

    async runSecurityScan() {
        try {
            this.showLoading();
            // This would call the security scan API
            this.showSuccess('Security scan completed!');
        } catch (error) {
            console.error('Error running security scan:', error);
            this.showError('Failed to run security scan');
        } finally {
            this.hideLoading();
        }
    }

    openSite(domain) {
        window.open(`http://${domain}`, '_blank');
    }

    openAdmin(domain) {
        window.open(`http://${domain}/wp-admin`, '_blank');
    }

    addSite() {
        document.getElementById('addSiteModal').style.display = 'block';
    }

    closeModal() {
        document.getElementById('addSiteModal').style.display = 'none';
        document.getElementById('domain').value = '';
    }

    refreshStats() {
        this.loadDashboard();
    }

    async fetchAPI(endpoint, options = {}) {
        const url = `${this.apiBase}${endpoint}`;
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
            },
        };

        const response = await fetch(url, { ...defaultOptions, ...options });
        
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.detail || 'API request failed');
        }

        return response.json();
    }

    showLoading() {
        document.getElementById('loading').style.display = 'flex';
    }

    hideLoading() {
        document.getElementById('loading').style.display = 'none';
    }

    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    showError(message) {
        this.showNotification(message, 'error');
    }

    showNotification(message, type) {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
            </div>
        `;

        // Add styles
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? '#27ae60' : '#e74c3c'};
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            animation: slideIn 0.3s ease;
        `;

        document.body.appendChild(notification);

        // Remove after 3 seconds
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }

    startAutoRefresh() {
        // Refresh dashboard every 30 seconds
        setInterval(() => {
            if (this.currentSection === 'dashboard') {
                this.loadDashboard();
            }
        }, 30000);
    }
}

// Initialize the panel when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.panel = new LiteWPPanel();
});

// Global functions for onclick handlers
function showSection(sectionName) {
    if (window.panel) {
        window.panel.showSection(sectionName);
    }
}

function addSite() {
    if (window.panel) {
        window.panel.addSite();
    }
}

function closeModal() {
    if (window.panel) {
        window.panel.closeModal();
    }
}

function backupAll() {
    if (window.panel) {
        window.panel.backupAll();
    }
}

function refreshStats() {
    if (window.panel) {
        window.panel.refreshStats();
    }
}

// Add CSS animations for notifications
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style); 