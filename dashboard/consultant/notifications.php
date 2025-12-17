<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Notifications";
$page_specific_css = "assets/css/notifications.css";
require_once 'includes/header.php';

// Get notification settings for the user
$settings_query = "SELECT ns.*, nt.type_name, nt.description, nt.icon, nt.color 
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

// Get notification channels
$channels_query = "SELECT * FROM notification_channels WHERE user_id = ?";
$stmt = $conn->prepare($channels_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$channels_result = $stmt->get_result();
$notification_channels = [];
while ($row = $channels_result->fetch_assoc()) {
    $notification_channels[$row['channel_type']][] = $row;
}
$stmt->close();

// Get recent notifications
$notifications_query = "SELECT n.*, nt.type_name, nt.icon, nt.color 
                       FROM notifications n 
                       JOIN notification_types nt ON n.type_id = nt.id 
                       WHERE n.user_id = ? AND n.is_dismissed = 0 
                       ORDER BY n.created_at DESC 
                       LIMIT 50";
$stmt = $conn->prepare($notifications_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications_result = $stmt->get_result();
$notifications = [];
while ($row = $notifications_result->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt->close();

// Handle notification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_read'])) {
        $notification_id = $_POST['notification_id'];
        $stmt = $conn->prepare("CALL mark_notification_read(?, ?)");
        $stmt->bind_param("ii", $notification_id, $user_id);
        $stmt->execute();
        $stmt->close();
        header("Location: notifications.php?success=1");
        exit;
    }
    
    if (isset($_POST['dismiss'])) {
        $notification_id = $_POST['notification_id'];
        $stmt = $conn->prepare("CALL dismiss_notification(?, ?)");
        $stmt->bind_param("ii", $notification_id, $user_id);
        $stmt->execute();
        $stmt->close();
        header("Location: notifications.php?success=2");
        exit;
    }
    
    if (isset($_POST['mark_all_read'])) {
        $stmt = $conn->prepare("CALL mark_all_notifications_read(?)");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        header("Location: notifications.php?success=3");
        exit;
    }
    
    if (isset($_POST['update_settings'])) {
        foreach ($_POST['settings'] as $type_id => $settings) {
            $email = isset($settings['email']) ? 1 : 0;
            $push = isset($settings['push']) ? 1 : 0;
            $sms = isset($settings['sms']) ? 1 : 0;
            $in_app = isset($settings['in_app']) ? 1 : 0;
            
            $update_query = "UPDATE notification_settings 
                           SET email_enabled = ?, push_enabled = ?, sms_enabled = ?, in_app_enabled = ? 
                           WHERE user_id = ? AND type_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("iiiiii", $email, $push, $sms, $in_app, $user_id, $type_id);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: notifications.php?success=4");
        exit;
    }
}

// Handle success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 1:
            $success_message = "Notification marked as read";
            break;
        case 2:
            $success_message = "Notification dismissed";
            break;
        case 3:
            $success_message = "All notifications marked as read";
            break;
        case 4:
            $success_message = "Notification settings updated";
            break;
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Notifications</h1>
            <p>Manage your notifications and preferences</p>
        </div>
        <div class="action-buttons">
            <form method="POST" style="display: inline;">
                <button type="submit" name="mark_all_read" class="btn primary-btn">
                    <i class="fas fa-check-double"></i> Mark All as Read
                </button>
            </form>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <div class="notifications-container">
        <div class="notifications-sidebar">
            <div class="sidebar-section">
                <h3>Notification Settings</h3>
                <form method="POST" id="settingsForm">
                    <?php foreach ($notification_settings as $type_name => $setting): ?>
                        <div class="setting-group">
                            <div class="setting-header">
                                <i class="fas fa-<?php echo $setting['icon']; ?>" style="color: <?php echo $setting['color']; ?>"></i>
                                <span><?php echo ucwords(str_replace('_', ' ', $type_name)); ?></span>
                            </div>
                            <div class="setting-options">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="settings[<?php echo $setting['type_id']; ?>][email]" 
                                           <?php echo $setting['email_enabled'] ? 'checked' : ''; ?>>
                                    Email
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="settings[<?php echo $setting['type_id']; ?>][push]" 
                                           <?php echo $setting['push_enabled'] ? 'checked' : ''; ?>>
                                    Push
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="settings[<?php echo $setting['type_id']; ?>][sms]" 
                                           <?php echo $setting['sms_enabled'] ? 'checked' : ''; ?>>
                                    SMS
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="settings[<?php echo $setting['type_id']; ?>][in_app]" 
                                           <?php echo $setting['in_app_enabled'] ? 'checked' : ''; ?>>
                                    In-App
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <button type="submit" name="update_settings" class="btn primary-btn">Save Settings</button>
                </form>
            </div>
            
            <div class="sidebar-section">
                <h3>Notification Channels</h3>
                <?php foreach ($notification_channels as $channel_type => $channels): ?>
                    <div class="channel-group">
                        <h4><?php echo ucfirst($channel_type); ?></h4>
                        <?php foreach ($channels as $channel): ?>
                            <div class="channel-item">
                                <span class="channel-value"><?php echo $channel['channel_value']; ?></span>
                                <?php if ($channel['is_verified']): ?>
                                    <span class="verified-badge"><i class="fas fa-check-circle"></i> Verified</span>
                                <?php else: ?>
                                    <span class="unverified-badge"><i class="fas fa-exclamation-circle"></i> Unverified</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="notifications-main">
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <p>No notifications found</p>
                </div>
            <?php else: ?>
                <div class="notification-list">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                            <div class="notification-icon" style="background-color: <?php echo $notification['color']; ?>">
                                <i class="fas fa-<?php echo $notification['icon']; ?>"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-header">
                                    <h4><?php echo htmlspecialchars($notification['title']); ?></h4>
                                    <span class="notification-time">
                                        <?php 
                                            $notification_time = new DateTime($notification['created_at']);
                                            $now = new DateTime();
                                            $diff = $notification_time->diff($now);
                                            
                                            if ($diff->days > 0) {
                                                echo $diff->days . ' days ago';
                                            } elseif ($diff->h > 0) {
                                                echo $diff->h . ' hours ago';
                                            } elseif ($diff->i > 0) {
                                                echo $diff->i . ' minutes ago';
                                            } else {
                                                echo 'Just now';
                                            }
                                        ?>
                                    </span>
                                </div>
                                <div class="notification-message">
                                    <?php echo htmlspecialchars($notification['message']); ?>
                                </div>
                                <div class="notification-actions">
                                    <?php if (!$notification['is_read']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                            <button type="submit" name="mark_read" class="btn-action btn-read">
                                                <i class="fas fa-check"></i> Mark as Read
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                        <button type="submit" name="dismiss" class="btn-action btn-dismiss">
                                            <i class="fas fa-times"></i> Dismiss
                                        </button>
                                    </form>
                                    <?php if (!empty($notification['action_link'])): ?>
                                        <a href="<?php echo $notification['action_link']; ?>" class="btn-action btn-view">
                                            <i class="fas fa-arrow-right"></i> View Details
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
:root {
    --primary-color: #042167;
    --secondary-color: #858796;
    --success-color: #1cc88a;
    --danger-color: #e74a3b;
    --warning-color: #f6c23e;
    --info-color: #36b9cc;
    --light-color: #f8f9fc;
    --dark-color: #5a5c69;
    --border-color: #e3e6f0;
}

.content {
    padding: 20px;
}

.header-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.header-container h1 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.8rem;
}

.header-container p {
    margin: 5px 0 0;
    color: var(--secondary-color);
}

.notifications-container {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 20px;
}

.notifications-sidebar {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
}

.sidebar-section {
    margin-bottom: 30px;
}

.sidebar-section h3 {
    margin: 0 0 15px 0;
    color: var(--primary-color);
    font-size: 1.2rem;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
}

.setting-group {
    margin-bottom: 15px;
}

.setting-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    font-weight: 500;
}

.setting-options {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    padding-left: 25px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.9rem;
    color: var(--dark-color);
}

.channel-group {
    margin-bottom: 20px;
}

.channel-group h4 {
    margin: 0 0 10px 0;
    color: var(--dark-color);
    font-size: 1rem;
}

.channel-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px;
    background-color: var(--light-color);
    border-radius: 4px;
    margin-bottom: 5px;
}

.channel-value {
    font-size: 0.9rem;
    color: var(--dark-color);
}

.verified-badge {
    color: var(--success-color);
    font-size: 0.8rem;
}

.unverified-badge {
    color: var(--warning-color);
    font-size: 0.8rem;
}

.notifications-main {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
}

.notification-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.notification-item {
    display: flex;
    gap: 15px;
    padding: 15px;
    border-radius: 8px;
    background-color: var(--light-color);
    transition: background-color 0.2s;
}

.notification-item.unread {
    background-color: rgba(78, 115, 223, 0.05);
    border-left: 3px solid var(--primary-color);
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 16px;
    flex-shrink: 0;
}

.notification-content {
    flex: 1;
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 5px;
}

.notification-header h4 {
    margin: 0;
    color: var(--dark-color);
    font-size: 1rem;
}

.notification-time {
    font-size: 0.8rem;
    color: var(--secondary-color);
}

.notification-message {
    font-size: 0.9rem;
    color: var(--dark-color);
    margin-bottom: 10px;
    line-height: 1.4;
}

.notification-actions {
    display: flex;
    gap: 10px;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 0.85rem;
    border: none;
    cursor: pointer;
    text-decoration: none;
    background-color: var(--secondary-color);
    color: white;
}

.btn-read {
    background-color: var(--success-color);
}

.btn-dismiss {
    background-color: var(--danger-color);
}

.btn-view {
    background-color: var(--primary-color);
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--secondary-color);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

.empty-state p {
    margin: 0;
    font-size: 1rem;
}

.alert {
    padding: 12px 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.alert-danger {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
    border: 1px solid rgba(231, 74, 59, 0.2);
}

.alert-success {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
    border: 1px solid rgba(28, 200, 138, 0.2);
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 0.9rem;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: background-color 0.2s;
}

.primary-btn {
    background-color: var(--primary-color);
    color: white;
}

.primary-btn:hover {
    background-color: #031c56;
}

@media (max-width: 992px) {
    .notifications-container {
        grid-template-columns: 1fr;
    }
    
    .notifications-sidebar {
        order: 2;
    }
    
    .notifications-main {
        order: 1;
    }
}

@media (max-width: 576px) {
    .setting-options {
        grid-template-columns: 1fr;
    }
    
    .notification-actions {
        flex-direction: column;
    }
    
    .btn-action {
        width: 100%;
        justify-content: center;
    }
}
</style>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
