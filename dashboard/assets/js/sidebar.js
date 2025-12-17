document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    
    // Check for stored sidebar state
    const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    
    // Initialize sidebar state based on stored preference
    if (sidebarCollapsed) {
        sidebar.classList.add('collapsed');
        mainContent.classList.add('expanded');
    }
    
    // Toggle sidebar on menu button click
    sidebarToggle.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
        
        // Store sidebar state in localStorage
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    });
    
    // On small screens, show sidebar on hover and hide when mouse leaves
    if (window.innerWidth <= 768) {
        sidebar.addEventListener('mouseenter', function() {
            if (sidebar.classList.contains('collapsed')) {
                sidebar.classList.add('show');
            }
        });
        
        sidebar.addEventListener('mouseleave', function() {
            if (sidebar.classList.contains('collapsed')) {
                sidebar.classList.remove('show');
            }
        });
    }
            // User dropdown toggle
            document.querySelector('.user-dropdown').addEventListener('click', function(e) {
                e.stopPropagation();
                this.querySelector('.user-dropdown-menu').classList.toggle('show');
            });
            
            // Close user dropdown when clicking outside
            document.addEventListener('click', function(e) {
                const dropdown = document.querySelector('.user-dropdown-menu');
                if (dropdown && dropdown.classList.contains('show') && !dropdown.contains(e.target) && !e.target.closest('.user-dropdown')) {
                    dropdown.classList.remove('show');
                }
            });
}); 
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle functionality
    document.getElementById('sidebar-toggle').addEventListener('click', function() {
        document.querySelector('.sidebar').classList.toggle('collapsed');
        document.querySelector('.main-content').classList.toggle('expanded');
        
        // Save preference in cookie
        const isCollapsed = document.querySelector('.sidebar').classList.contains('collapsed');
        document.cookie = `sidebar_collapsed=${isCollapsed}; path=/; max-age=31536000`;
    });
    
    // Notification dropdown toggle
    const notificationToggle = document.getElementById('notification-toggle');
    const notificationMenu = document.getElementById('notification-menu');

    if (notificationToggle && notificationMenu) {
        notificationToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationMenu.classList.toggle('active');
        });
        
        // Close notification dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (notificationMenu.classList.contains('active') && 
                !notificationMenu.contains(e.target) && 
                e.target !== notificationToggle) {
                notificationMenu.classList.remove('active');
            }
        });
        
        // Prevent dropdown from closing when clicking inside menu
        notificationMenu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
    
    // Handle mark as read links
    document.querySelectorAll('.notification-mark-read').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const notificationId = this.getAttribute('href').split('id=')[1];
            const notificationItem = this.closest('.notification-item');
            
            // AJAX request to mark notification as read
            fetch('ajax/mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'notification_id=' + notificationId + '&user_id=' + userId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    notificationItem.classList.remove('unread');
                    notificationItem.classList.add('read');
                    
                    // Update badge count
                    const badge = document.querySelector('.notification-badge');
                    if (badge) {
                        const currentCount = parseInt(badge.textContent);
                        if (currentCount > 1) {
                            badge.textContent = currentCount - 1;
                        } else {
                            badge.remove();
                        }
                    }
                    
                    // Hide the mark as read button
                    this.style.display = 'none';
                }
            });
        });
    });
    
    // Mark all as read functionality
    const markAllReadBtn = document.querySelector('.mark-all-read');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // AJAX request to mark all notifications as read
            fetch('ajax/mark_all_notifications_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'user_id=' + userId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                        item.classList.add('read');
                        
                        // Hide mark as read buttons
                        const markReadBtn = item.querySelector('.notification-mark-read');
                        if (markReadBtn) {
                            markReadBtn.style.display = 'none';
                        }
                    });
                    
                    // Remove notification badge
                    const badge = document.querySelector('.notification-badge');
                    if (badge) {
                        badge.remove();
                    }
                }
            });
        });
    }
});