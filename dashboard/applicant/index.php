<?php
// Set page title
$page_title = "Dashboard - Applicant";

// Include header
include('includes/header.php');

// Check if user has any active applications
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications WHERE user_id = ? AND status_id IN (SELECT id FROM application_statuses WHERE name != 'completed')");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$active_applications = $result->fetch_assoc()['count'];
$stmt->close();

// Check if user has any upcoming meetings
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE user_id = ? AND booking_datetime > NOW()");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$upcoming_meetings = $result->fetch_assoc()['count'];
$stmt->close();

// Check if user has any unread messages
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM messages m 
                       JOIN conversation_participants cp ON m.conversation_id = cp.conversation_id
                       LEFT JOIN message_read_status mrs ON m.id = mrs.message_id AND mrs.user_id = ?
                       WHERE cp.user_id = ? AND m.user_id != ? AND mrs.id IS NULL");
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$unread_messages = $result->fetch_assoc()['count'];
$stmt->close();

// Check if user has any unread notifications
$notifications_query = "SELECT 
    COUNT(*) as unread_notifications
    FROM notifications n
    WHERE n.user_id = ? AND n.is_read = 0 AND n.is_dismissed = 0";
$stmt = $conn->prepare($notifications_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications_result = $stmt->get_result()->fetch_assoc();
$unread_notifications = $notifications_result['unread_notifications'];
$stmt->close();

// Get recent applications
$recent_applications_query = "SELECT a.id, a.reference_number, v.visa_type, s.name AS status, s.color, a.updated_at 
                           FROM applications a 
                           JOIN visas v ON a.visa_id = v.visa_id
                           JOIN application_statuses s ON a.status_id = s.id
                           WHERE a.user_id = ? AND a.deleted_at IS NULL
                           ORDER BY a.updated_at DESC LIMIT 5";
$stmt = $conn->prepare($recent_applications_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_applications_result = $stmt->get_result();
$recent_applications = [];
if ($recent_applications_result && $recent_applications_result->num_rows > 0) {
    while ($row = $recent_applications_result->fetch_assoc()) {
        $recent_applications[] = $row;
    }
}
$stmt->close();

// Get upcoming meetings
$upcoming_meetings_query = "SELECT b.id, b.reference_number, vs.visa_service_id, b.booking_datetime,
                          b.end_datetime, b.meeting_link, st.service_name,
                          CONCAT(u.first_name, ' ', u.last_name) AS consultant_name,
                          u.profile_picture, bs.name as status_name, bs.color as status_color
                          FROM bookings b 
                          JOIN visa_services vs ON b.visa_service_id = vs.visa_service_id
                          JOIN service_types st ON vs.service_type_id = st.service_type_id
                          JOIN users u ON b.consultant_id = u.id
                          JOIN booking_statuses bs ON b.status_id = bs.id
                          WHERE b.user_id = ? AND bs.name IN ('pending', 'confirmed')
                          AND b.booking_datetime > NOW() AND b.deleted_at IS NULL
                          ORDER BY b.booking_datetime ASC LIMIT 5";
$stmt = $conn->prepare($upcoming_meetings_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$upcoming_meetings_result = $stmt->get_result();
$upcoming_meetings_list = [];
if ($upcoming_meetings_result && $upcoming_meetings_result->num_rows > 0) {
    while ($row = $upcoming_meetings_result->fetch_assoc()) {
        $upcoming_meetings_list[] = $row;
    }
}
$stmt->close();

// Get recent notifications
$recent_notifications_query = "SELECT 
    n.id, n.title, n.message, n.created_at, n.action_link, n.is_read,
    nt.icon, nt.color, nt.type_name
    FROM notifications n
    JOIN notification_types nt ON n.type_id = nt.id
    WHERE n.user_id = ? AND n.is_dismissed = 0
    ORDER BY n.created_at DESC
    LIMIT 5";
$stmt = $conn->prepare($recent_notifications_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_notifications_result = $stmt->get_result();
$recent_notifications = [];
if ($recent_notifications_result && $recent_notifications_result->num_rows > 0) {
    while ($row = $recent_notifications_result->fetch_assoc()) {
        $recent_notifications[] = $row;
    }
}
$stmt->close();
?>

<div class="content">
    <div class="dashboard-header">
        <h1>Applicant Dashboard</h1>
        <p>Welcome back, <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></p>
    </div>

    <!-- Stats Cards -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon booking-icon">
                <i class="fas fa-folder-open"></i>
            </div>
            <div class="stat-info">
                <h3>Applications</h3>
                <div class="stat-number"><?php echo number_format($active_applications); ?></div>
                <div class="stat-detail">
                    <a href="applications.php" class="stat-link">View All Applications</a>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon client-icon">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stat-info">
                <h3>Meetings</h3>
                <div class="stat-number"><?php echo number_format($upcoming_meetings); ?></div>
                <div class="stat-detail">
                    <a href="meetings.php" class="stat-link">View Schedule</a>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon message-icon">
                <i class="fas fa-envelope"></i>
            </div>
            <div class="stat-info">
                <h3>Messages</h3>
                <div class="stat-number"><?php echo number_format($unread_messages); ?></div>
                <div class="stat-detail">
                    <span class="unread">Unread Messages</span>
                    <a href="messages.php" class="stat-link">View All</a>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon notification-icon">
                <i class="fas fa-bell"></i>
            </div>
            <div class="stat-info">
                <h3>Notifications</h3>
                <div class="stat-number"><?php echo number_format($unread_notifications); ?></div>
                <div class="stat-detail">
                    <a href="notifications.php" class="stat-link">View All</a>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <!-- Recent Applications Section -->
        <div class="dashboard-section upcoming-bookings">
            <div class="section-header">
                <h2>Recent Applications</h2>
                <a href="applications.php" class="btn-link">View All</a>
            </div>

            <?php if (empty($recent_applications)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <p>No applications found</p>
            </div>
            <?php else: ?>
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Visa Type</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_applications as $app): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($app['reference_number']); ?></td>
                        <td><?php echo htmlspecialchars($app['visa_type']); ?></td>
                        <td>
                            <span class="status-badge" style="background-color: <?php echo $app['color']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $app['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($app['updated_at'])); ?></td>
                        <td>
                            <a href="application_details.php?id=<?php echo $app['id']; ?>" class="btn-action btn-view" title="View Details">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Recent Notifications Section -->
        <div class="dashboard-section recent-notifications">
            <div class="section-header">
                <h2>Recent Notifications</h2>
                <a href="notifications.php" class="btn-link">View All</a>
            </div>

            <?php if (empty($recent_notifications)): ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <p>No recent notifications</p>
            </div>
            <?php else: ?>
            <div class="notification-feed">
                <?php foreach ($recent_notifications as $notification): ?>
                <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                    <div class="notification-icon" style="background-color: <?php echo $notification['color']; ?>">
                        <i class="<?php echo $notification['icon']; ?>"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                        <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                        <div class="notification-time">
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
                        </div>
                    </div>
                    <?php if (!empty($notification['action_link'])): ?>
                    <a href="<?php echo $notification['action_link']; ?>" class="notification-action">
                        <i class="fas fa-arrow-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-grid">
        <!-- Upcoming Meetings Section -->
        <div class="dashboard-section upcoming-bookings">
            <div class="section-header">
                <h2>Upcoming Meetings</h2>
                <a href="meetings.php" class="btn-link">View All</a>
            </div>

            <?php if (empty($upcoming_meetings_list)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-day"></i>
                <p>No upcoming meetings scheduled</p>
            </div>
            <?php else: ?>
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Date & Time</th>
                        <th>Consultant</th>
                        <th>Service</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcoming_meetings_list as $meeting): 
                        $booking_datetime = new DateTime($meeting['booking_datetime']);
                        $end_datetime = new DateTime($meeting['end_datetime']);
                        
                        // Get consultant profile picture
                        $profile_img = '../../assets/images/default-profile.jpg';
                        if (!empty($meeting['profile_picture'])) {
                            if (file_exists('../../uploads/profiles/' . $meeting['profile_picture'])) {
                                $profile_img = '../../uploads/profiles/' . $meeting['profile_picture'];
                            }
                        }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($meeting['reference_number']); ?></td>
                        <td>
                            <?php echo $booking_datetime->format('M d, Y'); ?>
                            <div class="time"><?php echo $booking_datetime->format('h:i A') . ' - ' . $end_datetime->format('h:i A'); ?></div>
                        </td>
                        <td>
                            <div class="consultant-info d-flex align-items-center">
                                <img src="<?php echo $profile_img; ?>" alt="Profile" class="consultant-img me-2">
                                <?php echo htmlspecialchars($meeting['consultant_name']); ?>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($meeting['service_name']); ?></td>
                        <td>
                            <span class="status-badge" style="background-color: <?php echo $meeting['status_color']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $meeting['status_name'])); ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group">
                                <a href="meeting_details.php?id=<?php echo $meeting['id']; ?>" class="btn-action btn-view" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (!empty($meeting['meeting_link']) && (abs(strtotime($meeting['booking_datetime']) - time()) < 3600)): ?>
                                <a href="<?php echo $meeting['meeting_link']; ?>" target="_blank" class="btn-action btn-success" title="Join Meeting">
                                    <i class="fas fa-video"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            
            <div class="action-button mt-3">
                <a href="book_consultation.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Book New Meeting
                </a>
            </div>
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
    --message-color: #4e73df;
    --notification-color: #f6c23e;
}

.content {
    padding: 20px;
}

.dashboard-header {
    margin-bottom: 20px;
}

.dashboard-header h1 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.8rem;
}

.dashboard-header p {
    margin: 5px 0 0;
    color: var(--secondary-color);
}

/* Stats Cards */
.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 20px;
    max-height: 200px;
    overflow-y: auto;
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.booking-icon {
    background-color: var(--primary-color);
}

.client-icon {
    background-color: var(--info-color);
}

.message-icon {
    background-color: var(--message-color);
}

.notification-icon {
    background-color: var(--notification-color);
}

.stat-info {
    flex: 1;
}

.stat-info h3 {
    margin: 0 0 5px 0;
    color: var(--secondary-color);
    font-size: 0.85rem;
    font-weight: 600;
}

.stat-number {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--dark-color);
    margin-bottom: 5px;
}

.stat-detail {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    font-size: 0.8rem;
}

.unread {
    color: var(--message-color);
    font-weight: 500;
}

.stat-link {
    color: var(--primary-color);
    text-decoration: none;
}

.stat-link:hover {
    text-decoration: underline;
}

/* Dashboard Grid */
.dashboard-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.dashboard-section {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
}

.section-header h2 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.2rem;
}

.btn-link {
    color: var(--primary-color);
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 500;
}

.btn-link:hover {
    text-decoration: underline;
}

/* Tables */
.dashboard-table {
    width: 100%;
    border-collapse: collapse;
}

.dashboard-table th {
    text-align: left;
    padding: 10px;
    font-weight: 600;
    color: var(--primary-color);
    font-size: 0.85rem;
}

.dashboard-table td {
    padding: 10px;
    border-top: 1px solid var(--border-color);
    font-size: 0.9rem;
}

.time {
    font-size: 0.85rem;
    color: var(--secondary-color);
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    color: white;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 4px;
    font-size: 14px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    color: white;
}

.btn-view {
    background-color: var(--primary-color);
}

.btn-view:hover {
    background-color: #031c56;
}

.btn-success {
    background-color: var(--success-color);
}

.btn-success:hover {
    background-color: #169b6b;
}

/* Empty States */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--secondary-color);
}

.empty-state i {
    font-size: 36px;
    margin-bottom: 10px;
    opacity: 0.5;
}

.empty-state p {
    margin: 0;
    font-size: 0.9rem;
}

/* Notification Feed */
.notification-feed {
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-height: 500px;
    overflow-y: auto;
}

.notification-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px;
    border-radius: 8px;
    background-color: var(--light-color);
    position: relative;
}

.notification-item.unread {
    background-color: rgba(78, 115, 223, 0.05);
    border-left: 3px solid var(--message-color);
}

.notification-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 14px;
    flex-shrink: 0;
}

.notification-content {
    flex: 1;
}

.notification-title {
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 4px;
    color: var(--dark-color);
}

.notification-message {
    font-size: 0.85rem;
    margin-bottom: 5px;
    color: var(--secondary-color);
    line-height: 1.4;
}

.notification-time {
    font-size: 0.75rem;
    color: var(--secondary-color);
}

.notification-action {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background-color: var(--light-color);
    color: var(--primary-color);
    text-decoration: none;
    flex-shrink: 0;
}

.notification-action:hover {
    background-color: var(--primary-color);
    color: white;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 0.85rem;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: background-color 0.2s;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background-color: #031c56;
}

.action-button {
    margin-top: 15px;
}

.consultant-img {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 8px;
}

.consultant-info {
    display: flex;
    align-items: center;
}

/* Responsive adjustments */
@media (max-width: 992px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 576px) {
    .stat-card {
        flex-direction: column;
        text-align: center;
    }
    
    .stats-container {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
// Include footer
include('includes/footer.php');
?>
