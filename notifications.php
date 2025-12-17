<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: /login.php");
    exit;
}

// Include database connection
require_once 'config/db_connect.php';

$page_title = "Notifications";
require_once 'includes/header.php';

// Get user's notifications
$user_id = $_SESSION['id'];
$user_type = $_SESSION['user_type'];

// Mark notifications as read if requested
if (isset($_POST['mark_read']) && !empty($_POST['notification_id'])) {
    $notification_id = intval($_POST['notification_id']);
    $stmt = $conn->prepare("CALL mark_notification_read(?, ?)");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

// Mark all notifications as read if requested
if (isset($_POST['mark_all_read'])) {
    $stmt = $conn->prepare("CALL mark_all_notifications_read(?)");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

// Dismiss notification if requested
if (isset($_POST['dismiss']) && !empty($_POST['notification_id'])) {
    $notification_id = intval($_POST['notification_id']);
    $stmt = $conn->prepare("CALL dismiss_notification(?, ?)");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

// Get user's notifications with type information
$query = "SELECT 
    n.id,
    n.title,
    n.message,
    n.is_read,
    n.created_at,
    n.action_link,
    nt.type_name,
    nt.icon,
    nt.color,
    TIMESTAMPDIFF(SECOND, n.created_at, NOW()) as seconds_ago
FROM notifications n
JOIN notification_types nt ON n.type_id = nt.id
WHERE n.user_id = ? AND n.is_dismissed = 0
ORDER BY n.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$notifications = [];

while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt->close();

// Get notification settings
$settings_query = "SELECT 
    nt.type_name,
    ns.email_enabled,
    ns.push_enabled,
    ns.sms_enabled,
    ns.in_app_enabled
FROM notification_settings ns
JOIN notification_types nt ON ns.type_id = nt.id
WHERE ns.user_id = ?";

$stmt = $conn->prepare($settings_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$settings_result = $stmt->get_result();
$notification_settings = [];

while ($row = $settings_result->fetch_assoc()) {
    $notification_settings[$row['type_name']] = $row;
}
$stmt->close();
?>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <div class="hero-content text-center">
            <h1 class="hero-title">Notifications</h1>
            <p class="hero-subtitle">Stay updated with your latest activities and updates</p>
        </div>
    </div>
</section>

<div class="container">
    <!-- Notification Controls -->
    <div class="notification-controls">
        <div class="control-buttons">
            <form method="post" class="d-inline">
                <button type="submit" name="mark_all_read" class="btn btn-secondary">
                    <i class="fas fa-check-double"></i> Mark All as Read
                </button>
            </form>
            <button type="button" class="btn btn-secondary" id="showSettings">
                <i class="fas fa-cog"></i> Notification Settings
            </button>
        </div>
        <div class="notification-filters">
            <select id="filter-type" class="form-control">
                <option value="">All Notifications</option>
                <option value="unread">Unread Only</option>
                <option value="read">Read Only</option>
            </select>
        </div>
    </div>

    <!-- Notifications List -->
    <div class="notifications-list">
        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <p>No notifications to display</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notification): ?>
                <div class="notification-card <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>" 
                     data-status="<?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                    <div class="notification-icon" style="background-color: <?php echo $notification['color']; ?>">
                        <i class="fas fa-<?php echo $notification['icon']; ?>"></i>
                    </div>
                    <div class="notification-content">
                        <h3><?php echo htmlspecialchars($notification['title']); ?></h3>
                        <p><?php echo htmlspecialchars($notification['message']); ?></p>
                        <div class="notification-meta">
                            <span class="timestamp" data-timestamp="<?php echo $notification['seconds_ago']; ?>">
                                <?php echo formatTimeAgo($notification['seconds_ago']); ?>
                            </span>
                            <span class="notification-type"><?php echo $notification['type_name']; ?></span>
                        </div>
                    </div>
                    <div class="notification-actions">
                        <?php if (!empty($notification['action_link'])): ?>
                            <a href="<?php echo $notification['action_link']; ?>" class="btn btn-primary btn-sm">
                                View Details
                            </a>
                        <?php endif; ?>
                        <?php if (!$notification['is_read']): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                <button type="submit" name="mark_read" class="btn btn-secondary btn-sm">
                                    Mark as Read
                                </button>
                            </form>
                        <?php endif; ?>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                            <button type="submit" name="dismiss" class="btn btn-danger btn-sm">
                                Dismiss
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Settings Modal -->
<div id="settingsModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2 class="modal-title">Notification Settings</h2>
        <div class="settings-content">
            <form method="post" id="notificationSettingsForm">
                <?php foreach ($notification_settings as $type => $settings): ?>
                    <div class="setting-group">
                        <h3><?php echo htmlspecialchars($type); ?></h3>
                        <div class="setting-options">
                            <label class="setting-option">
                                <input type="checkbox" name="settings[<?php echo $type; ?>][email]" 
                                       <?php echo $settings['email_enabled'] ? 'checked' : ''; ?>>
                                Email
                            </label>
                            <label class="setting-option">
                                <input type="checkbox" name="settings[<?php echo $type; ?>][push]" 
                                       <?php echo $settings['push_enabled'] ? 'checked' : ''; ?>>
                                Push
                            </label>
                            <label class="setting-option">
                                <input type="checkbox" name="settings[<?php echo $type; ?>][sms]" 
                                       <?php echo $settings['sms_enabled'] ? 'checked' : ''; ?>>
                                SMS
                            </label>
                            <label class="setting-option">
                                <input type="checkbox" name="settings[<?php echo $type; ?>][in_app]" 
                                       <?php echo $settings['in_app_enabled'] ? 'checked' : ''; ?>>
                                In-App
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </form>
        </div>
    </div>
</div>

<style>
/* Inherit existing styles from book-service.php */
:root {
    --primary-color: #eaaa34;
    --primary-light: rgba(234, 170, 52, 0.1);
    --primary-medium: rgba(234, 170, 52, 0.2);
    --dark-blue: #042167;
    --text-color: #333;
    --text-light: #666;
    --background-light: #f8f9fa;
    --white: #fff;
    --border-color: #e5e7eb;
    --danger-color: #dc3545;
    --success-color: #28a745;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --border-radius: 0.5rem;
    --transition: all 0.3s ease;
}

/* Notification specific styles */
.notification-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding: 20px;
    background-color: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
}

.control-buttons {
    display: flex;
    gap: 10px;
}

.notification-card {
    display: grid;
    grid-template-columns: 60px 1fr auto;
    gap: 20px;
    padding: 20px;
    background-color: var(--white);
    border-radius: var(--border-radius);
    margin-bottom: 15px;
    box-shadow: var(--shadow);
    transition: var(--transition);
}

.notification-card.unread {
    background-color: var(--primary-light);
    border-left: 4px solid var(--primary-color);
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
}

.notification-content h3 {
    margin: 0 0 5px 0;
    color: var(--dark-blue);
    font-size: 16px;
}

.notification-content p {
    margin: 0 0 10px 0;
    color: var(--text-color);
    font-size: 14px;
}

.notification-meta {
    display: flex;
    gap: 15px;
    color: var(--text-light);
    font-size: 12px;
}

.notification-type {
    background-color: var(--primary-light);
    padding: 2px 8px;
    border-radius: 12px;
    color: var(--primary-color);
}

.notification-actions {
    display: flex;
    gap: 10px;
    align-items: flex-start;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

.btn-danger {
    background-color: var(--danger-color);
    color: var(--white);
}

/* Settings Modal Styles */
.setting-group {
    margin-bottom: 20px;
    padding: 15px;
    background-color: var(--background-light);
    border-radius: var(--border-radius);
}

.setting-group h3 {
    margin: 0 0 10px 0;
    color: var(--dark-blue);
    font-size: 16px;
}

.setting-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
}

.setting-option {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 14px;
    color: var(--text-color);
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .notification-card {
        grid-template-columns: 40px 1fr;
    }

    .notification-actions {
        grid-column: 1 / -1;
        justify-content: flex-end;
    }

    .control-buttons {
        flex-direction: column;
    }

    .notification-controls {
        flex-direction: column;
        gap: 15px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Filter functionality
    const filterSelect = document.getElementById('filter-type');
    const notificationCards = document.querySelectorAll('.notification-card');

    filterSelect.addEventListener('change', function() {
        const filterValue = this.value;
        notificationCards.forEach(card => {
            if (filterValue === '' || card.dataset.status === filterValue) {
                card.style.display = 'grid';
            } else {
                card.style.display = 'none';
            }
        });
    });

    // Modal functionality
    const modal = document.getElementById('settingsModal');
    const showSettingsBtn = document.getElementById('showSettings');
    const closeBtn = document.querySelector('.close');

    showSettingsBtn.onclick = function() {
        modal.style.display = 'block';
    }

    closeBtn.onclick = function() {
        modal.style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }

    // Settings form submission
    const settingsForm = document.getElementById('notificationSettingsForm');
    settingsForm.addEventListener('submit', function(e) {
        e.preventDefault();
        // Add AJAX submission logic here
        const formData = new FormData(this);
        // Example AJAX call:
        fetch('/api/update-notification-settings.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Settings updated successfully');
                modal.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error updating settings');
        });
    });

    // Time ago functionality
    function updateTimestamps() {
        document.querySelectorAll('.timestamp').forEach(timestamp => {
            const seconds = parseInt(timestamp.dataset.timestamp);
            timestamp.textContent = formatTimeAgo(seconds);
        });
    }

    function formatTimeAgo(seconds) {
        const intervals = {
            year: 31536000,
            month: 2592000,
            week: 604800,
            day: 86400,
            hour: 3600,
            minute: 60
        };

        if (seconds < 60) return 'just now';

        for (let [unit, secondsInUnit] of Object.entries(intervals)) {
            const interval = Math.floor(seconds / secondsInUnit);
            if (interval >= 1) {
                return interval === 1 ? `1 ${unit} ago` : `${interval} ${unit}s ago`;
            }
        }
    }

    // Update timestamps every minute
    setInterval(updateTimestamps, 60000);
    updateTimestamps();
});
</script>

<?php
function formatTimeAgo($seconds) {
    $intervals = array(
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute'
    );
    
    if ($seconds < 60) {
        return "just now";
    }
    
    foreach ($intervals as $secs => $str) {
        $d = $seconds / $secs;
        if ($d >= 1) {
            $r = round($d);
            return $r . ' ' . $str . ($r > 1 ? 's' : '') . ' ago';
        }
    }
}

require_once 'includes/footer.php';
?>
