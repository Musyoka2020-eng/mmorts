/**
 * Global Mail Notifications - Updates unread mail count across all pages
 * Integrates with the topnav mail link badge
 */

class MailNotifications {
    constructor() {
        this.unreadCount = 0;
        this.updateInterval = null;
        
        // Only initialize if user is logged in
        if (window.mailConfig && window.mailConfig.userId) {
            this.init();
        }
    }
    
    init() {
        this.updateUnreadCount();
        this.startUpdateTimer();
        
        // Update when window gets focus
        window.addEventListener('focus', () => {
            this.updateUnreadCount();
        });
        
        // Listen for storage events (when mail is read in another tab)
        window.addEventListener('storage', (e) => {
            if (e.key === 'mailUnreadCount') {
                this.setUnreadCount(parseInt(e.newValue) || 0);
            }
        });
    }
    
    async updateUnreadCount() {
        try {
            const response = await fetch(`${window.mailConfig.apiBase}mail_actions.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'get_unread_count'
                })
            });
            
            const data = await response.json();
            if (data.success) {
                this.setUnreadCount(data.unread_count);
                
                // Store in localStorage for cross-tab sync
                localStorage.setItem('mailUnreadCount', data.unread_count.toString());
            }
        } catch (error) {
            console.error('Error updating mail count:', error);
        }
    }
    
    setUnreadCount(count) {
        this.unreadCount = count;
        
        const badge = document.getElementById('mailUnreadBadge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count.toString();
                badge.style.display = 'inline-block';
                
                // Add pulse animation for new messages
                badge.classList.add('mail-notification-pulse');
                setTimeout(() => {
                    badge.classList.remove('mail-notification-pulse');
                }, 2000);
            } else {
                badge.style.display = 'none';
            }
        }
        
        // Update browser title
        this.updatePageTitle(count);
        
        // Update favicon if possible
        this.updateFavicon(count > 0);
    }
    
    updatePageTitle(count) {
        const baseTitle = document.title.replace(/^\(\d+\)\s*/, '');
        
        if (count > 0) {
            document.title = `(${count}) ${baseTitle}`;
        } else {
            document.title = baseTitle;
        }
    }
    
    updateFavicon(hasUnread) {
        // Try to update favicon to show notification
        const link = document.querySelector("link[rel*='icon']") || document.createElement('link');
        link.type = 'image/x-icon';
        link.rel = 'shortcut icon';
        
        if (hasUnread) {
            // Use a red dot favicon for notifications (would need to create this)
            // For now, just keep the original
            link.href = 'frontend/images/logo.png';
        } else {
            link.href = 'frontend/images/logo.png';
        }
        
        document.getElementsByTagName('head')[0].appendChild(link);
    }
    
    startUpdateTimer() {
        // Update every 30 seconds
        this.updateInterval = setInterval(() => {
            this.updateUnreadCount();
        }, 30000);
    }
    
    stopUpdateTimer() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
            this.updateInterval = null;
        }
    }
    
    // Method to manually trigger update (called when message is read)
    refresh() {
        this.updateUnreadCount();
    }
    
    // Method to decrement count when message is marked as read
    decrementCount(amount = 1) {
        this.setUnreadCount(Math.max(0, this.unreadCount - amount));
    }
    
    // Method to increment count when new message arrives
    incrementCount(amount = 1) {
        this.setUnreadCount(this.unreadCount + amount);
    }
}

// Initialize mail notifications when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if mailConfig exists (user is logged in)
    if (typeof window.mailConfig !== 'undefined') {
        window.mailNotifications = new MailNotifications();
    }
});

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (window.mailNotifications) {
        window.mailNotifications.stopUpdateTimer();
    }
});