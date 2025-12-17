<?php
$page_title = "Notifications";
$page_specific_css = "assets/css/notifications.css";
require_once 'includes/header.php';

// Process marking notifications as read if requested
if (isset($_GET['action']) && $_GET['action'] == 'mark_read' && isset($_GET['id'])) {
    $notification_id = intval($_GET['id']);
    $stmt = $conn->prepare("CALL mark_notification_read(?, ?)");
    $stmt->bind_param("ii", $notification_id, $_SESSION['id']);
    $stmt->execute();
    $stmt->close();
    
    // Redirect to remove the action parameter
    header("Location: notification.php");
    exit;
}

// Process marking all notifications as read
if (isset($_GET['action']) && $_GET['action'] == 'mark_all_read') {
    $stmt = $conn->prepare("CALL mark_all_notifications_read(?)");
    $stmt->bind_param("i", $_SESSION['id']);
    $stmt->execute();
    $stmt->close();
    
    // Redirect to remove the action parameter
    header("Location: notification.php");
    exit;
}

// Process dismissing a notification
if (isset($_GET['action']) && $_GET['action'] == 'dismiss' && isset($_GET['id'])) {
    $notification_id = intval($_GET['id']);
    $stmt = $conn->prepare("CALL dismiss_notification(?, ?)");
    $stmt->bind_param("ii", $notification_id, $_SESSION['id']);
    $stmt->execute();
    $stmt->close();
    
    // Redirect to remove the action parameter
    header("Location: notification.php");
    exit;
}

// Get notification type filters (for filter dropdown)
$notification_types_query = "SELECT id, type_name, icon, color FROM notification_types ORDER BY type_name ASC";
$notification_types_result = $conn->query($notification_types_query);
$notification_types = [];

if ($notification_types_result && $notification_types_result->num_rows > 0) {
    while ($row = $notification_types_result->fetch_assoc()) {
        $notification_types[] = $row;
    }
}

// Set up pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 20; // Notifications per page
$offset = ($page - 1) * $limit;

// Build the query with filters
$where_clauses = ["n.user_id = ?"]; // Always filter by current user
$params = [$_SESSION['id']];
$param_types = "i";

// Add filter for notification type
if (isset($_GET['type']) && !empty($_GET['type'])) {
    $where_clauses[] = "n.type_id = ?";
    $params[] = intval($_GET['type']);
    $param_types .= "i";
}

// Add filter for read/unread
if (isset($_GET['read_status']) && in_array($_GET['read_status'], ['read', 'unread'])) {
    $is_read = $_GET['read_status'] === 'read' ? 1 : 0;
    $where_clauses[] = "n.is_read = ?";
    $params[] = $is_read;
    $param_types .= "i";
}

// Add filter for dismissed
$show_dismissed = isset($_GET['show_dismissed']) && $_GET['show_dismissed'] === '1';
if (!$show_dismissed) {
    $where_clauses[] = "n.is_dismissed = 0";
}

// Prepare the WHERE clause
$where_clause = implode(" AND ", $where_clauses);

// Get notifications with pagination
$notifications_query = "SELECT 
    n.id, n.title, n.message, n.created_at, n.is_read, n.is_dismissed, n.action_link,
    nt.type_name, nt.icon, nt.color
    FROM notifications n
    JOIN notification_types nt ON n.type_id = nt.id
    WHERE $where_clause
    ORDER BY n.created_at DESC
    LIMIT ? OFFSET ?";

$stmt = $conn->prepare($notifications_query);
$param_types .= "ii"; // For LIMIT and OFFSET
$params[] = $limit;
$params[] = $offset;
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$notifications_result = $stmt->get_result();
$notifications = [];

if ($notifications_result && $notifications_result->num_rows > 0) {
    while ($row = $notifications_result->fetch_assoc()) {
        $notifications[] = $row;
    }
}
$stmt->close();

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM notifications n WHERE $where_clause";
$stmt = $conn->prepare($count_query);
$stmt->bind_param(substr($param_types, 0, -2), ...array_slice($params, 0, -2)); // Remove limit and offset
$stmt->execute();
$count_result = $stmt->get_result()->fetch_assoc();
$total_notifications = $count_result['total'];
$total_pages = ceil($total_notifications / $limit);
$stmt->close();

// Get notification statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_read = 0 AND is_dismissed = 0 THEN 1 ELSE 0 END) as unread,
    SUM(CASE WHEN is_read = 1 AND is_dismissed = 0 THEN 1 ELSE 0 END) as read,
    SUM(CASE WHEN is_dismissed = 1 THEN 1 ELSE 0 END) as dismissed
    FROM notifications
    WHERE user_id = ?";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get unread counts by notification type
$type_counts_query = "SELECT 
    nt.id, nt.type_name, nt.icon, nt.color,
    COUNT(n.id) as count
    FROM notification_types nt
    LEFT JOIN notifications n ON nt.id = n.type_id AND n.user_id = ? AND n.is_read = 0 AND n.is_dismissed = 0
    GROUP BY nt.id, nt.type_name, nt.icon, nt.color
    ORDER BY count DESC, nt.type_name ASC";
$stmt = $conn->prepare($type_counts_query);
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$type_counts_result = $stmt->get_result();
$type_counts = [];

if ($type_counts_result && $type_counts_result->num_rows > 0) {
    while ($row = $type_counts_result->fetch_assoc()) {
        $type_counts[] = $row;
    }
}
$stmt->close();
?>

<div class="content">
    <div class="page-header">
        <div class="page-title">
            <h1>Notifications</h1>
            <p>View and manage your notifications</p>
        </div>
        <div class="page-actions">
            <?php if ($stats['unread'] > 0): ?>
            <a href="notification.php?action=mark_all_read" class="btn btn-outline">
                <i class="fas fa-check-double"></i> Mark All as Read
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="notification-container">
        <div class="notification-sidebar">
            <div class="sidebar-section">
                <h3>Status</h3>
                <ul class="sidebar-menu">
                    <li class="<?php echo !isset($_GET['read_status']) ? 'active' : ''; ?>">
                        <a href="notification.php">
                            <span class="menu-icon"><i class="fas fa-bell"></i></span>
                            <span class="menu-text">All Notifications</span>
                            <span class="menu-badge"><?php echo $stats['total'] - $stats['dismissed']; ?></span>
                        </a>
                    </li>
                    <li class="<?php echo isset($_GET['read_status']) && $_GET['read_status'] === 'unread' ? 'active' : ''; ?>">
                        <a href="notification.php?read_status=unread">
                            <span class="menu-icon"><i class="fas fa-envelope"></i></span>
                            <span class="menu-text">Unread</span>
                            <span class="menu-badge"><?php echo $stats['unread']; ?></span>
                        </a>
                    </li>
                    <li class="<?php echo isset($_GET['read_status']) && $_GET['read_status'] === 'read' ? 'active' : ''; ?>">
                        <a href="notification.php?read_status=read">
                            <span class="menu-icon"><i class="fas fa-envelope-open"></i></span>
                            <span class="menu-text">Read</span>
                            <span class="menu-badge"><?php echo $stats['read']; ?></span>
                        </a>
                    </li>
                    <li class="<?php echo isset($_GET['show_dismissed']) && $_GET['show_dismissed'] === '1' ? 'active' : ''; ?>">
                        <a href="notification.php?show_dismissed=1">
                            <span class="menu-icon"><i class="fas fa-trash-alt"></i></span>
                            <span class="menu-text">Dismissed</span>
                            <span class="menu-badge"><?php echo $stats['dismissed']; ?></span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="sidebar-section">
                <h3>Filter by Type</h3>
                <ul class="sidebar-menu">
                    <?php foreach ($type_counts as $type): ?>
                    <li class="<?php echo isset($_GET['type']) && $_GET['type'] == $type['id'] ? 'active' : ''; ?>">
                        <a href="notification.php?type=<?php echo $type['id']; ?>">
                            <span class="menu-icon" style="color: <?php echo $type['color']; ?>">
                                <i class="<?php echo $type['icon']; ?>"></i>
                            </span>
                            <span class="menu-text"><?php echo htmlspecialchars($type['type_name']); ?></span>
                            <?php if ($type['count'] > 0): ?>
                            <span class="menu-badge"><?php echo $type['count']; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="sidebar-section">
                <h3>Settings</h3>
                <ul class="sidebar-menu">
                    <li>
                        <a href="notification_settings.php">
                            <span class="menu-icon"><i class="fas fa-cog"></i></span>
                            <span class="menu-text">Notification Settings</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="notification-content">
            <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-bell-slash"></i>
                </div>
                <h3>No notifications found</h3>
                <p>You don't have any notifications matching your current filters.</p>
                <a href="notification.php" class="btn btn-primary">View All Notifications</a>
            </div>
            <?php else: ?>
            <div class="notification-list">
                <?php foreach ($notifications as $notification): ?>
                <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?> <?php echo $notification['is_dismissed'] ? 'dismissed' : ''; ?>">
                    <div class="notification-icon" style="background-color: <?php echo $notification['color']; ?>">
                        <i class="<?php echo $notification['icon']; ?>"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-header">
                            <h4 class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></h4>
                            <div class="notification-meta">
                                <span class="notification-type"><?php echo htmlspecialchars($notification['type_name']); ?></span>
                                <span class="notification-time">
                                    <?php 
                                        $notification_time = new DateTime($notification['created_at']);
                                        $now = new DateTime();
                                        $diff = $notification_time->diff($now);
                                        
                                        if ($diff->days > 0) {
                                            echo $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
                                        } elseif ($diff->h > 0) {
                                            echo $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
                                        } elseif ($diff->i > 0) {
                                            echo $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
                                        } else {
                                            echo 'Just now';
                                        }
                                    ?>
                                </span>
                            </div>
                        </div>
                        <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                        <div class="notification-actions">
                            <?php if (!empty($notification['action_link'])): ?>
                            <a href="<?php echo $notification['action_link']; ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-external-link-alt"></i> View
                            </a>
                            <?php endif; ?>
                            
                            <?php if (!$notification['is_read']): ?>
                            <a href="notification.php?action=mark_read&id=<?php echo $notification['id']; ?>" class="btn btn-sm btn-outline">
                                <i class="fas fa-check"></i> Mark as Read
                            </a>
                            <?php endif; ?>
                            
                            <?php if (!$notification['is_dismissed']): ?>
                            <a href="notification.php?action=dismiss&id=<?php echo $notification['id']; ?>" class="btn btn-sm btn-outline btn-danger">
                                <i class="fas fa-times"></i> Dismiss
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="notification.php?page=<?php echo $page - 1; ?><?php echo isset($_GET['type']) ? '&type=' . $_GET['type'] : ''; ?><?php echo isset($_GET['read_status']) ? '&read_status=' . $_GET['read_status'] : ''; ?><?php echo isset($_GET['show_dismissed']) ? '&show_dismissed=' . $_GET['show_dismissed'] : ''; ?>" class="page-link">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
                <?php endif; ?>
                
                <div class="page-numbers">
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="notification.php?page=<?php echo $i; ?><?php echo isset($_GET['type']) ? '&type=' . $_GET['type'] : ''; ?><?php echo isset($_GET['read_status']) ? '&read_status=' . $_GET['read_status'] : ''; ?><?php echo isset($_GET['show_dismissed']) ? '&show_dismissed=' . $_GET['show_dismissed'] : ''; ?>" class="page-number <?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                </div>
                
                <?php if ($page < $total_pages): ?>
                <a href="notification.php?page=<?php echo $page + 1; ?><?php echo isset($_GET['type']) ? '&type=' . $_GET['type'] : ''; ?><?php echo isset($_GET['read_status']) ? '&read_status=' . $_GET['read_status'] : ''; ?><?php echo isset($_GET['show_dismissed']) ? '&show_dismissed=' . $_GET['show_dismissed'] : ''; ?>" class="page-link">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
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

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.page-title h1 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.8rem;
}

.page-title p {
    margin: 5px 0 0;
    color: var(--secondary-color);
}

.page-actions {
    display: flex;
    gap: 10px;
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
    transition: all 0.2s;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 0.8rem;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background-color: #031c56;
}

.btn-outline {
    background-color: white;
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
}

.btn-outline:hover {
    background-color: var(--primary-color);
    color: white;
}

.btn-danger {
    color: var(--danger-color);
    border-color: var(--danger-color);
}

.btn-danger:hover {
    background-color: var(--danger-color);
    color: white;
}

.notification-container {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 20px;
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.notification-sidebar {
    background-color: var(--light-color);
    padding: 20px;
    border-right: 1px solid var(--border-color);
}

.sidebar-section {
    margin-bottom: 30px;
}

.sidebar-section h3 {
    font-size: 1rem;
    color: var(--dark-color);
    margin-bottom: 15px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border-color);
}

.sidebar-menu {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sidebar-menu li {
    margin-bottom: 5px;
}

.sidebar-menu li a {
    display: flex;
    align-items: center;
    padding: 10px;
    border-radius: 4px;
    text-decoration: none;
    color: var(--dark-color);
    transition: background-color 0.2s;
}

.sidebar-menu li a:hover {
    background-color: rgba(4, 33, 103, 0.05);
}

.sidebar-menu li.active a {
    background-color: var(--primary-color);
    color: white;
}

.sidebar-menu li.active .menu-icon,
.sidebar-menu li.active .menu-badge {
    color: white !important;
}

.menu-icon {
    margin-right: 10px;
    font-size: 1rem;
    width: 20px;
    text-align: center;
}

.menu-text {
    flex: 1;
    font-size: 0.9rem;
}

.menu-badge {
    background-color: var(--light-color);
    color: var(--secondary-color);
    padding: 3px 8px;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 600;
}

.notification-content {
    padding: 20px;
}

.empty-state {
    text-align: center;
    padding: 50px 20px;
}

.empty-icon {
    font-size: 3rem;
    color: var(--secondary-color);
    opacity: 0.3;
    margin-bottom: 15px;
}

.empty-state h3 {
    margin-bottom: 10px;
    color: var(--dark-color);
}

.empty-state p {
    color: var(--secondary-color);
    margin-bottom: 20px;
}

.notification-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.notification-item {
    display: flex;
    gap: 15px;
    padding: 20px;
    border-radius: 8px;
    background-color: white;
    border: 1px solid var(--border-color);
}

.notification-item.unread {
    background-color: rgba(4, 33, 103, 0.03);
    border-left: 3px solid var(--primary-color);
}

.notification-item.dismissed {
    opacity: 0.7;
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
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
    margin-bottom: 10px;
}

.notification-title {
    margin: 0;
    font-size: 1.1rem;
    color: var(--dark-color);
}

.notification-meta {
    display: flex;
    align-items: center;
    gap: 10px;
}

.notification-type {
    background-color: var(--light-color);
    padding: 3px 8px;
    border-radius: 10px;
    font-size: 0.75rem;
    color: var(--secondary-color);
}

.notification-time {
    font-size: 0.8rem;
    color: var(--secondary-color);
}

.notification-message {
    margin-bottom: 15px;
    color: var(--dark-color);
    font-size: 0.95rem;
    line-height: 1.5;
}

.notification-actions {
    display: flex;
    gap: 10px;
}

.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
}

.page-link {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 8px 16px;
    border-radius: 4px;
    background-color: var(--light-color);
    color: var(--primary-color);
    text-decoration: none;
    transition: background-color 0.2s;
}

.page-link:hover {
    background-color: var(--border-color);
}

.page-numbers {
    display: flex;
    gap: 5px;
}

.page-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 4px;
    background-color: var(--light-color);
    color: var(--primary-color);
    text-decoration: none;
    transition: all 0.2s;
}

.page-number:hover {
    background-color: var(--border-color);
}

.page-number.active {
    background-color: var(--primary-color);
    color: white;
}

@media (max-width: 992px) {
    .notification-container {
        grid-template-columns: 1fr;
    }
    
    .notification-sidebar {
        border-right: none;
        border-bottom: 1px solid var(--border-color);
    }
}

@media (max-width: 576px) {
    .notification-header {
        flex-direction: column;
        gap: 5px;
    }
    
    .notification-actions {
        flex-wrap: wrap;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>
