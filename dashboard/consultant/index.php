<?php
$page_title = "Consultant Dashboard";
$page_specific_css = "../assets/css/style.css";
require_once 'includes/header.php';

// Get booking stats
$booking_stats_query = "SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN bs.name = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
    SUM(CASE WHEN bs.name = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
    SUM(CASE WHEN bs.name = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
    SUM(CASE WHEN bs.name LIKE 'cancelled%' THEN 1 ELSE 0 END) as cancelled_bookings
    FROM bookings b
    JOIN booking_statuses bs ON b.status_id = bs.id
    WHERE b.consultant_id = ? AND b.deleted_at IS NULL";
$stmt = $conn->prepare($booking_stats_query);
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$booking_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Ensure we have values for all stats, not null
$booking_stats['total_bookings'] = $booking_stats['total_bookings'] ?? 0;
$booking_stats['pending_bookings'] = $booking_stats['pending_bookings'] ?? 0;  
$booking_stats['confirmed_bookings'] = $booking_stats['confirmed_bookings'] ?? 0;
$booking_stats['completed_bookings'] = $booking_stats['completed_bookings'] ?? 0;
$booking_stats['cancelled_bookings'] = $booking_stats['cancelled_bookings'] ?? 0;

// Get client stats
$client_stats_query = "SELECT 
    COUNT(DISTINCT acr.applicant_id) as total_clients
    FROM applicant_consultant_relationships acr
    JOIN applicants a ON acr.applicant_id = a.user_id 
    JOIN users u ON a.user_id = u.id
    WHERE acr.consultant_id = ? AND acr.status = 'active' AND u.deleted_at IS NULL";
$stmt = $conn->prepare($client_stats_query);
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$client_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Ensure client_stats is not null
$client_stats['total_clients'] = $client_stats['total_clients'] ?? 0;

// Get team member stats
$team_stats_query = "SELECT 
    COUNT(*) as total_team_members
    FROM team_members tm
    JOIN users u ON tm.member_user_id = u.id
    WHERE tm.consultant_id = ? AND tm.invitation_status = 'accepted' AND u.deleted_at IS NULL";
$stmt = $conn->prepare($team_stats_query);
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$team_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get visa services stats
$services_stats_query = "SELECT 
    COUNT(*) as total_services
    FROM visa_services
    WHERE consultant_id = ? AND is_active = 1";
$stmt = $conn->prepare($services_stats_query);
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$services_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get unread notifications count
$notifications_query = "SELECT 
    COUNT(*) as unread_notifications
    FROM notifications n
    WHERE n.user_id = ? AND n.is_read = 0 AND n.is_dismissed = 0";
$stmt = $conn->prepare($notifications_query);
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$notifications_result = $stmt->get_result()->fetch_assoc();
$unread_notifications = $notifications_result['unread_notifications'];
$stmt->close();

// Get unread messages count
$messages_query = "SELECT 
    get_unread_messages_count(?) as unread_messages";
$stmt = $conn->prepare($messages_query);
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$messages_result = $stmt->get_result()->fetch_assoc();
$unread_messages = $messages_result['unread_messages'];
$stmt->close();

// Get AI chat usage stats
$chat_usage_query = "SELECT 
    acu.month,
    acu.chat_count,
    acu.message_count,
    mp.name as plan_name,
    CASE 
        WHEN mp.name = 'Bronze' THEN 40
        WHEN mp.name = 'Silver' THEN 80
        WHEN mp.name = 'Gold' THEN 150
        ELSE 0
    END as monthly_limit
    FROM ai_chat_usage acu
    JOIN consultants c ON acu.consultant_id = c.user_id
    JOIN membership_plans mp ON c.membership_plan_id = mp.id
    WHERE acu.consultant_id = ? AND acu.month = DATE_FORMAT(NOW(), '%Y-%m')";
$stmt = $conn->prepare($chat_usage_query);
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$chat_usage_result = $stmt->get_result()->fetch_assoc();
$stmt->close();

// If no AI usage record for current month, get plan info anyway
if (!$chat_usage_result) {
    $plan_query = "SELECT 
        mp.name as plan_name,
        CASE 
            WHEN mp.name = 'Bronze' THEN 40
            WHEN mp.name = 'Silver' THEN 80
            WHEN mp.name = 'Gold' THEN 150
            ELSE 0
        END as monthly_limit
        FROM consultants c
        JOIN membership_plans mp ON c.membership_plan_id = mp.id
        WHERE c.user_id = ?";
    $stmt = $conn->prepare($plan_query);
    $stmt->bind_param("i", $_SESSION['id']);
    $stmt->execute();
    $plan_result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $chat_usage_result = [
        'month' => date('Y-m'),
        'chat_count' => 0,
        'message_count' => 0,
        'plan_name' => $plan_result['plan_name'],
        'monthly_limit' => $plan_result['monthly_limit']
    ];
}

$my_tasks_query = "SELECT t.id, t.name, t.priority, t.status, t.due_date,
                  ta.status as assignment_status
                  FROM tasks t 
                  JOIN task_assignments ta ON t.id = ta.task_id
                  WHERE ta.assignee_id = ? AND t.deleted_at IS NULL AND ta.deleted_at IS NULL
                  AND ta.status IN ('pending', 'in_progress')
                  ORDER BY t.due_date ASC, t.priority DESC
                  LIMIT 5";
$stmt = $conn->prepare($my_tasks_query);
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$my_tasks_result = $stmt->get_result();
$my_tasks = [];

if ($my_tasks_result && $my_tasks_result->num_rows > 0) {
    while ($row = $my_tasks_result->fetch_assoc()) {
        $my_tasks[] = $row;
    }
}
$stmt->close();

// Get team tasks (tasks assigned to team members in your organization)
$team_tasks_query = "SELECT t.id, t.name, t.priority, t.status, t.due_date,
                  ta.status as assignment_status,
                  u.id as assignee_id, CONCAT(u.first_name, ' ', u.last_name) as assignee_name
                  FROM tasks t 
                  JOIN task_assignments ta ON t.id = ta.task_id
                  JOIN users u ON ta.assignee_id = u.id
                  JOIN users creator ON t.creator_id = creator.id
                  WHERE t.creator_id = ? 
                  AND ta.assignee_id != ?
                  AND t.deleted_at IS NULL 
                  AND ta.deleted_at IS NULL
                  AND ta.status IN ('pending', 'in_progress')
                  ORDER BY t.due_date ASC, t.priority DESC
                  LIMIT 10";
$stmt = $conn->prepare($team_tasks_query);
$stmt->bind_param("ii", $_SESSION['id'], $_SESSION['id']);
$stmt->execute();
$team_tasks_result = $stmt->get_result();
$team_tasks = [];

if ($team_tasks_result && $team_tasks_result->num_rows > 0) {
    while ($row = $team_tasks_result->fetch_assoc()) {
        $team_tasks[] = $row;
    }
}
$stmt->close();

// Get recent notifications
$recent_notifications_query = "SELECT 
    n.id, n.title, n.message, n.created_at, n.action_link,
    nt.icon, nt.color, nt.type_name
    FROM notifications n
    JOIN notification_types nt ON n.type_id = nt.id
    WHERE n.user_id = ? AND n.is_dismissed = 0
    ORDER BY n.created_at DESC
    LIMIT 5";
$stmt = $conn->prepare($recent_notifications_query);
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$recent_notifications_result = $stmt->get_result();
$recent_notifications = [];

if ($recent_notifications_result && $recent_notifications_result->num_rows > 0) {
    while ($row = $recent_notifications_result->fetch_assoc()) {
        $recent_notifications[] = $row;
    }
}
$stmt->close();

// Get upcoming bookings (next 7 days)
$upcoming_bookings_query = "SELECT 
    b.id, b.reference_number, b.booking_datetime, bs.name as status_name, bs.color as status_color,
    CONCAT(u.first_name, ' ', u.last_name) as client_name,
    v.visa_type, st.service_name, cm.mode_name as consultation_mode
    FROM bookings b
    JOIN booking_statuses bs ON b.status_id = bs.id
    JOIN users u ON b.user_id = u.id
    JOIN visa_services vs ON b.visa_service_id = vs.visa_service_id
    JOIN service_consultation_modes scm ON b.service_consultation_id = scm.service_consultation_id
    JOIN consultation_modes cm ON scm.consultation_mode_id = cm.consultation_mode_id
    JOIN visas v ON vs.visa_id = v.visa_id
    JOIN service_types st ON vs.service_type_id = st.service_type_id
    WHERE b.consultant_id = ? AND b.deleted_at IS NULL
    AND bs.name IN ('pending', 'confirmed')
    AND b.booking_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
    ORDER BY b.booking_datetime ASC
    LIMIT 5";
$stmt = $conn->prepare($upcoming_bookings_query);
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$upcoming_bookings_result = $stmt->get_result();
$upcoming_bookings = [];

if ($upcoming_bookings_result && $upcoming_bookings_result->num_rows > 0) {
    while ($row = $upcoming_bookings_result->fetch_assoc()) {
        $upcoming_bookings[] = $row;
    }
}
$stmt->close();

// Get recent conversations
$recent_conversations_query = "SELECT 
    c.id, c.title, c.type, c.last_message_at,
    (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.deleted_at IS NULL) as message_count,
    CASE 
        WHEN c.type = 'direct' THEN 
            (SELECT CONCAT(u.first_name, ' ', u.last_name)
             FROM conversation_participants cp
             JOIN users u ON cp.user_id = u.id
             WHERE cp.conversation_id = c.id 
             AND cp.user_id <> ?
             AND cp.left_at IS NULL
             LIMIT 1)
        ELSE c.title
    END AS display_name,
    (SELECT COUNT(*) FROM messages m 
     JOIN conversation_participants cp ON m.conversation_id = cp.conversation_id
     LEFT JOIN message_read_status mrs ON m.id = mrs.message_id AND mrs.user_id = ?
     WHERE m.conversation_id = c.id 
     AND m.user_id != ?
     AND m.deleted_at IS NULL
     AND cp.user_id = ?
     AND cp.left_at IS NULL
     AND mrs.id IS NULL) as unread_count
    FROM conversations c
    JOIN conversation_participants cp ON c.id = cp.conversation_id
    WHERE cp.user_id = ? AND cp.left_at IS NULL AND c.deleted_at IS NULL
    ORDER BY c.last_message_at DESC
    LIMIT 5";
$stmt = $conn->prepare($recent_conversations_query);
$stmt->bind_param("iiiii", $_SESSION['id'], $_SESSION['id'], $_SESSION['id'], $_SESSION['id'], $_SESSION['id']);
$stmt->execute();
$recent_conversations_result = $stmt->get_result();
$recent_conversations = [];

if ($recent_conversations_result && $recent_conversations_result->num_rows > 0) {
    while ($row = $recent_conversations_result->fetch_assoc()) {
        $recent_conversations[] = $row;
    }
}
$stmt->close();

// Get recent AI chat conversations
$ai_chat_query = "SELECT 
    acc.id, acc.title, acc.chat_type, acc.updated_at,
    (SELECT COUNT(*) FROM ai_chat_messages acm WHERE acm.conversation_id = acc.id AND acm.deleted_at IS NULL) as message_count
    FROM ai_chat_conversations acc
    WHERE acc.consultant_id = ? AND acc.deleted_at IS NULL
    ORDER BY acc.updated_at DESC
    LIMIT 5";
$stmt = $conn->prepare($ai_chat_query);
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$ai_chat_result = $stmt->get_result();
$ai_chats = [];

if ($ai_chat_result && $ai_chat_result->num_rows > 0) {
    while ($row = $ai_chat_result->fetch_assoc()) {
        $ai_chats[] = $row;
    }
}
$stmt->close();

// Get monthly booking data for chart
$monthly_bookings_query = "SELECT 
    DATE_FORMAT(booking_datetime, '%Y-%m') as month,
    COUNT(*) as booking_count
    FROM bookings
    WHERE consultant_id = ? AND deleted_at IS NULL
    AND booking_datetime >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(booking_datetime, '%Y-%m')
    ORDER BY month ASC";
$stmt = $conn->prepare($monthly_bookings_query);
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$monthly_bookings_result = $stmt->get_result();
$monthly_labels = [];
$monthly_data = [];

if ($monthly_bookings_result && $monthly_bookings_result->num_rows > 0) {
    while ($row = $monthly_bookings_result->fetch_assoc()) {
        // Format the month for display
        $date = new DateTime($row['month'] . '-01');
        $monthly_labels[] = $date->format('M Y');
        $monthly_data[] = $row['booking_count'];
    }
}
$stmt->close();
?>

<div class="content">
    <div class="dashboard-header">
        <h1>Consultant Dashboard</h1>
        <p>Welcome back, <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></p>
    </div>

    <!-- Stats Cards -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon booking-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-info">
                <h3>Bookings</h3>
                <div class="stat-number"><?php echo number_format((int)$booking_stats['total_bookings']); ?></div>
                <div class="stat-detail">
                    <span class="pending"><?php echo number_format((int)$booking_stats['pending_bookings']); ?> Pending</span>
                    <span class="confirmed"><?php echo number_format((int)$booking_stats['confirmed_bookings']); ?> Confirmed</span>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon client-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <h3>Clients</h3>
                <div class="stat-number"><?php echo number_format($client_stats['total_clients']); ?></div>
                <div class="stat-detail">
                    <a href="clients.php" class="stat-link">View All Clients</a>
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
        <!-- Upcoming Bookings Section -->
        <div class="dashboard-section upcoming-bookings">
            <div class="section-header">
                <h2>Upcoming Bookings</h2>
                <a href="bookings.php" class="btn-link">View All</a>
            </div>

            <?php if (empty($upcoming_bookings)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-day"></i>
                <p>No upcoming bookings for the next 7 days</p>
            </div>
            <?php else: ?>
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>Ref #</th>
                        <th>Client</th>
                        <th>Date & Time</th>
                        <th>Service</th>
                        <th>Mode</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcoming_bookings as $booking): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($booking['reference_number']); ?></td>
                        <td><?php echo htmlspecialchars($booking['client_name']); ?></td>
                        <td>
                            <?php 
                                $date = new DateTime($booking['booking_datetime']);
                                echo $date->format('M d, Y');
                            ?>
                            <div class="time"><?php echo $date->format('h:i A'); ?></div>
                        </td>
                        <td>
                            <div><?php echo htmlspecialchars($booking['visa_type']); ?></div>
                            <div class="service-type"><?php echo htmlspecialchars($booking['service_name']); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($booking['consultation_mode']); ?></td>
                        <td>
                            <span class="status-badge" style="background-color: <?php echo $booking['status_color']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $booking['status_name'])); ?>
                            </span>
                        </td>
                        <td>
                            <a href="view_booking.php?id=<?php echo $booking['id']; ?>" class="btn-action btn-view" title="View Details">
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
        <!-- My Tasks Section -->
        <div class="dashboard-section my-tasks">
            <div class="section-header">
                <h2>My Tasks</h2>
                <a href="tasks.php" class="btn-link">View All</a>
            </div>

            <?php if (empty($my_tasks)): ?>
            <div class="empty-state">
                <i class="fas fa-tasks"></i>
                <p>No pending tasks assigned to you</p>
            </div>
            <?php else: ?>
            <div class="task-list">
                <?php foreach ($my_tasks as $task): ?>
                    <div class="team-task-item">
                        <div class="team-task-info">
                            <span class="task-priority-badge <?php echo $task['priority']; ?>">
                                <?php echo ucfirst($task['priority']); ?>
                            </span>
                            <div class="task-name">
                                <a href="task_detail.php?id=<?php echo $task['id']; ?>">
                                    <?php echo htmlspecialchars($task['name']); ?>
                                </a>
                            </div>
                            <?php if (!empty($task['due_date'])): ?>
                                <?php 
                                    $due_date = new DateTime($task['due_date']);
                                    $today = new DateTime();
                                    $interval = $today->diff($due_date);
                                    $is_overdue = $due_date < $today && $task['status'] !== 'completed';
                                    $date_class = $is_overdue ? 'overdue' : '';
                                ?>
                                <div class="due-date <?php echo $date_class; ?>">
                                    <i class="far fa-calendar-alt"></i>
                                    Due: <?php echo $due_date->format('M d, Y'); ?>
                                    <?php if ($is_overdue): ?>
                                        <span class="overdue-tag">Overdue</span>
                                    <?php elseif ($interval->days == 0): ?>
                                        <span class="today-tag">Today</span>
                                    <?php elseif ($interval->days == 1 && $due_date > $today): ?>
                                        <span class="tomorrow-tag">Tomorrow</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="team-task-status">
                            <span class="status-badge <?php echo $task['status']; ?>">
                                <?php echo ucwords(str_replace('_', ' ', $task['status'])); ?>
                            </span>
                            <div class="task-actions">
                                <a href="task_detail.php?id=<?php echo $task['id']; ?>" class="btn-task-action">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if ($task['status'] === 'pending'): ?>
                                    <a href="tasks.php?action=start&task_id=<?php echo $task['id']; ?>" class="btn-task-action start">
                                        <i class="fas fa-play"></i> Start
                                    </a>
                                <?php elseif ($task['status'] === 'in_progress'): ?>
                                    <a href="tasks.php?action=complete&task_id=<?php echo $task['id']; ?>" class="btn-task-action complete">
                                        <i class="fas fa-check"></i> Complete
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Team Tasks Section -->
        <div class="dashboard-section team-tasks">
            <div class="section-header">
                <h2>Team Tasks</h2>
                <a href="tasks.php" class="btn-link">Manage All</a>
            </div>

            <?php if (empty($team_tasks)): ?>
            <div class="empty-state">
                <i class="fas fa-user-friends"></i>
                <p>No pending tasks assigned to your team</p>
            </div>
            <?php else: ?>
            <div class="task-list">
                <?php foreach ($team_tasks as $task): ?>
                    <div class="team-task-item">
                        <div class="team-task-info">
                            <span class="task-priority-badge <?php echo $task['priority']; ?>">
                                <?php echo ucfirst($task['priority']); ?>
                            </span>
                            <div class="task-name">
                                <a href="task_detail.php?id=<?php echo $task['id']; ?>">
                                    <?php echo htmlspecialchars($task['name']); ?>
                                </a>
                            </div>
                            <div class="task-assignee">
                                <i class="fas fa-user"></i> Assigned to: <?php echo htmlspecialchars($task['assignee_name']); ?>
                            </div>
                            <?php if (!empty($task['due_date'])): ?>
                                <?php 
                                    $due_date = new DateTime($task['due_date']);
                                    $today = new DateTime();
                                    $interval = $today->diff($due_date);
                                    $is_overdue = $due_date < $today && $task['status'] !== 'completed';
                                    $date_class = $is_overdue ? 'overdue' : '';
                                ?>
                                <div class="due-date <?php echo $date_class; ?>">
                                    <i class="far fa-calendar-alt"></i>
                                    Due: <?php echo $due_date->format('M d, Y'); ?>
                                    <?php if ($is_overdue): ?>
                                        <span class="overdue-tag">Overdue</span>
                                    <?php elseif ($interval->days == 0): ?>
                                        <span class="today-tag">Today</span>
                                    <?php elseif ($interval->days == 1 && $due_date > $today): ?>
                                        <span class="tomorrow-tag">Tomorrow</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="team-task-status">
                            <span class="status-badge <?php echo $task['status']; ?>">
                                <?php echo ucwords(str_replace('_', ' ', $task['status'])); ?>
                            </span>
                            <a href="task_detail.php?id=<?php echo $task['id']; ?>" class="btn-task-action">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="dashboard-grid">
        <!-- AI Chat Section -->
        <div class="dashboard-section ai-chat">
            <div class="section-header">
                <h2>AI Chat Assistant</h2>
                <a href="ai-chat.php" class="btn-link">Open Chat</a>
            </div>
            
            <div class="ai-chat-stats">
                <div class="ai-stat-item">
                    <div class="ai-stat-label">Plan</div>
                    <div class="ai-stat-value"><?php echo htmlspecialchars($chat_usage_result['plan_name']); ?></div>
                </div>
                <div class="ai-stat-item">
                    <div class="ai-stat-label">Monthly Limit</div>
                    <div class="ai-stat-value"><?php echo number_format($chat_usage_result['monthly_limit']); ?> messages</div>
                </div>
                <div class="ai-stat-item">
                    <div class="ai-stat-label">Used This Month</div>
                    <div class="ai-stat-value">
                        <?php echo number_format($chat_usage_result['message_count']); ?> / 
                        <?php echo number_format($chat_usage_result['monthly_limit']); ?>
                    </div>
                </div>
                <div class="ai-stat-item">
                    <div class="ai-stat-label">Remaining</div>
                    <div class="ai-stat-value">
                        <?php echo number_format(max(0, $chat_usage_result['monthly_limit'] - $chat_usage_result['message_count'])); ?> messages
                    </div>
                </div>
            </div>
            
            <div class="usage-progress">
                <?php $percentage = min(100, ($chat_usage_result['message_count'] / $chat_usage_result['monthly_limit']) * 100); ?>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $percentage; ?>%;"></div>
                </div>
                <div class="progress-label"><?php echo round($percentage); ?>% used</div>
            </div>
            
            <?php if (!empty($ai_chats)): ?>
            <div class="recent-chats">
                <h3>Recent Conversations</h3>
                <div class="chat-list">
                    <?php foreach ($ai_chats as $chat): ?>
                    <a href="ai-chat.php?conversation_id=<?php echo $chat['id']; ?>" class="chat-item">
                        <div class="chat-icon">
                            <i class="fas fa-robot"></i>
                        </div>
                        <div class="chat-info">
                            <div class="chat-title"><?php echo htmlspecialchars($chat['title']); ?></div>
                            <div class="chat-meta">
                                <span class="chat-type"><?php echo ucfirst($chat['chat_type']); ?></span>
                                <span class="chat-count"><?php echo $chat['message_count']; ?> messages</span>
                                <span class="chat-time">
                                    <?php 
                                        $chat_time = new DateTime($chat['updated_at']);
                                        echo $chat_time->format('M d, h:i A'); 
                                    ?>
                                </span>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="action-buttons">
                <a href="ai-chat.php?type=ircc" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> New IRCC Chat
                </a>
                <a href="ai-chat.php?type=cases" class="btn btn-secondary">
                    <i class="fas fa-plus-circle"></i> New Case Chat
                </a>
            </div>
        </div>

        <!-- Recent Messages Section -->
        <div class="dashboard-section recent-messages">
            <div class="section-header">
                <h2>Recent Conversations</h2>
                <a href="messaging.php" class="btn-link">View All</a>
            </div>

            <?php if (empty($recent_conversations)): ?>
            <div class="empty-state">
                <i class="fas fa-comments"></i>
                <p>No recent conversations</p>
            </div>
            <?php else: ?>
            <div class="conversation-list">
                <?php foreach ($recent_conversations as $conversation): ?>
                <a href="messaging.php?conversation_id=<?php echo $conversation['id']; ?>" class="conversation-item <?php echo $conversation['unread_count'] > 0 ? 'unread' : ''; ?>">
                    <div class="conversation-avatar">
                        <?php if ($conversation['type'] == 'direct'): ?>
                            <i class="fas fa-user"></i>
                        <?php else: ?>
                            <i class="fas fa-users"></i>
                        <?php endif; ?>
                    </div>
                    <div class="conversation-info">
                        <div class="conversation-name"><?php echo htmlspecialchars($conversation['display_name']); ?></div>
                        <div class="conversation-meta">
                            <span class="message-count"><?php echo $conversation['message_count']; ?> messages</span>
                            <span class="conversation-time">
                                <?php 
                                    $conv_time = new DateTime($conversation['last_message_at']);
                                    $now = new DateTime();
                                    $diff = $conv_time->diff($now);
                                    
                                    if ($diff->days > 0) {
                                        echo $diff->days . 'd ago';
                                    } elseif ($diff->h > 0) {
                                        echo $diff->h . 'h ago';
                                    } elseif ($diff->i > 0) {
                                        echo $diff->i . 'm ago';
                                    } else {
                                        echo 'Just now';
                                    }
                                ?>
                            </span>
                        </div>
                    </div>
                    <?php if ($conversation['unread_count'] > 0): ?>
                    <div class="unread-badge"><?php echo $conversation['unread_count']; ?></div>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <div class="action-button">
                <a href="messaging.php?new=1" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> New Conversation
                </a>
            </div>
        </div>
    </div>

    <!-- Tasks Section -->
  

    <!-- Charts Section -->
    <div class="dashboard-charts">
        <div class="chart-container">
            <div class="chart-header">
                <h3>Bookings Over Time</h3>
            </div>
            <div class="chart-body">
                <canvas id="bookingsChart"></canvas>
            </div>
        </div>
        
        <!-- AI Chat Usage Chart -->
        <div class="chart-container">
            <div class="chart-header">
                <h3>AI Chat Usage</h3>
            </div>
            <div class="chart-body">
                <canvas id="aiChatChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Quick Actions Section -->
   
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

.team-icon {
    background-color: var(--success-color);
}

.service-icon {
    background-color: var(--warning-color);
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

.pending {
    color: var(--warning-color);
}

.confirmed {
    color: var(--success-color);
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

.time,
.service-type {
    font-size: 0.85rem;
    color: var(--secondary-color);
}

.not-assigned {
    font-style: italic;
    color: var(--secondary-color);
    font-size: 0.85rem;
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

/* Activity Feed */
.activity-feed {
    display: flex;
    flex-direction: column;
    gap: 15px;
    max-height: 500px;
    overflow-y: auto;
}

.activity-item {
    display: flex;
    gap: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border-color);
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background-color: var(--light-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 16px;
}

.activity-content {
    flex: 1;
}

.activity-info {
    margin-bottom: 5px;
    font-size: 0.9rem;
}

.activity-user {
    font-weight: 600;
    color: var(--dark-color);
}

.activity-action {
    color: var(--secondary-color);
}

.activity-reference {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 500;
}

.activity-description {
    font-size: 0.85rem;
    color: var(--dark-color);
    margin-bottom: 5px;
    line-height: 1.4;
}

.activity-time {
    font-size: 0.8rem;
    color: var(--secondary-color);
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

/* Charts */
.dashboard-charts {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.chart-container {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
}

.chart-header {
    margin-bottom: 15px;
}

.chart-header h3 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.1rem;
}

.chart-body {
    height: 300px;
}

/* Quick Actions */
.quick-actions {
    margin-bottom: 30px;
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
}

.action-card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
    text-decoration: none;
    transition: transform 0.2s;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    max-height: 250px;
    overflow-y: auto;
}

.action-card:hover {
    transform: translateY(-5px);
}

.action-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background-color: var(--light-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 24px;
    margin-bottom: 15px;
}

.action-title {
    font-weight: 600;
    color: var(--dark-color);
    margin-bottom: 5px;
    font-size: 1rem;
}

.action-description {
    color: var(--secondary-color);
    font-size: 0.85rem;
}

/* Add scrollbar styling for action cards */
.action-card::-webkit-scrollbar {
    width: 6px;
}

.action-card::-webkit-scrollbar-track {
    background: white;
    border-radius: 8px;
}

.action-card::-webkit-scrollbar-thumb {
    background-color: var(--border-color);
    border-radius: 8px;
}

.action-card::-webkit-scrollbar-thumb:hover {
    background-color: var(--secondary-color);
}

/* Task Cards Styling */
.task-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 15px;
}

.task-card {
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 15px;
    background-color: var(--light-color);
    transition: transform 0.2s, box-shadow 0.2s;
    max-height: 300px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

/* Add scrollbar styling */
.task-card::-webkit-scrollbar {
    width: 6px;
}

.task-card::-webkit-scrollbar-track {
    background: var(--light-color);
    border-radius: 8px;
}

.task-card::-webkit-scrollbar-thumb {
    background-color: var(--border-color);
    border-radius: 8px;
}

.task-card::-webkit-scrollbar-thumb:hover {
    background-color: var(--secondary-color);
}

.task-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.task-priority {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 10px;
}

.task-priority.high {
    background-color: rgba(231, 74, 59, 0.15);
    color: var(--danger-color);
}

.task-priority.medium {
    background-color: rgba(246, 194, 62, 0.15);
    color: var(--warning-color);
}

.task-priority.low {
    background-color: rgba(28, 200, 138, 0.15);
    color: var(--success-color);
}

.task-status {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 10px;
}

.task-status.pending {
    background-color: rgba(54, 185, 204, 0.15);
    color: var(--info-color);
}

.task-status.in-progress {
    background-color: rgba(246, 194, 62, 0.15);
    color: var(--warning-color);
}

.task-name {
    margin-bottom: 10px;
    font-weight: 600;
}

.task-name a {
    color: var(--dark-color);
    text-decoration: none;
}

.task-name a:hover {
    color: var(--primary-color);
}

.task-due-date {
    font-size: 0.85rem;
    color: var(--secondary-color);
    margin-bottom: 15px;
}

.task-due-date i {
    margin-right: 5px;
}

.overdue {
    color: var(--danger-color);
    font-weight: 500;
}

.due-today {
    color: var(--warning-color);
    font-weight: 500;
}

.due-soon {
    color: var(--info-color);
    font-weight: 500;
}

.task-actions {
    display: flex;
    justify-content: flex-start;
    gap: 10px;
}

.btn-task-action {
    font-size: 0.8rem;
    padding: 5px 10px;
    border-radius: 4px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: white;
    background-color: var(--primary-color);
}

.btn-task-action:hover {
    opacity: 0.9;
}

.btn-task-action.start {
    background-color: var(--warning-color);
}

.btn-task-action.complete {
    background-color: var(--success-color);
}

/* Team Tasks List Styling */
.task-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: 400px;
    overflow-y: auto;
}

/* Add scrollbar styling for task list */
.task-list::-webkit-scrollbar {
    width: 6px;
}

.task-list::-webkit-scrollbar-track {
    background: var(--light-color);
    border-radius: 8px;
}

.task-list::-webkit-scrollbar-thumb {
    background-color: var(--border-color);
    border-radius: 8px;
}

.task-list::-webkit-scrollbar-thumb:hover {
    background-color: var(--secondary-color);
}

.team-task-item {
    display: flex;
    justify-content: space-between;
    padding: 12px;
    border-radius: 8px;
    background-color: var(--light-color);
    border: 1px solid var(--border-color);
    max-height: 150px;
    overflow-y: auto;
}

/* Add scrollbar styling for team task items */
.team-task-item::-webkit-scrollbar {
    width: 6px;
}

.team-task-item::-webkit-scrollbar-track {
    background: var(--light-color);
    border-radius: 8px;
}

.team-task-item::-webkit-scrollbar-thumb {
    background-color: var(--border-color);
    border-radius: 8px;
}

.team-task-item::-webkit-scrollbar-thumb:hover {
    background-color: var(--secondary-color);
}

.team-task-info {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.task-priority-badge {
    font-size: 0.7rem;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 8px;
    display: inline-block;
    margin-bottom: 5px;
}

.task-priority-badge.high {
    background-color: rgba(231, 74, 59, 0.15);
    color: var(--danger-color);
}

.task-priority-badge.medium {
    background-color: rgba(246, 194, 62, 0.15);
    color: var(--warning-color);
}

.task-priority-badge.low {
    background-color: rgba(28, 200, 138, 0.15);
    color: var(--success-color);
}

.task-assignee {
    font-size: 0.8rem;
    color: var(--secondary-color);
}

.task-assignee i {
    margin-right: 5px;
}

.team-task-status {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 5px;
}

.due-date {
    font-size: 0.8rem;
    color: var(--secondary-color);
}

@media (max-width: 992px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }

    .dashboard-charts {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 576px) {
    .stat-card {
        flex-direction: column;
        text-align: center;
    }

    .actions-grid {
        grid-template-columns: 1fr;
    }
}

/* Additional styles for new features */
.message-icon {
    background-color: var(--message-color);
}

/* .notification-icon {
    background-color: var(--notification-color);
} */

.unread {
    color: var(--message-color);
    font-weight: 500;
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
    color: var(--primary-color);
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

/* AI Chat Section */
.ai-chat-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.ai-stat-item {
    background-color: var(--light-color);
    border-radius: 6px;
    padding: 12px;
    text-align: center;
}

.ai-stat-label {
    font-size: 0.8rem;
    color: var(--secondary-color);
    margin-bottom: 5px;
}

.ai-stat-value {
    font-size: 1rem;
    font-weight: 600;
    color: var(--dark-color);
}

.usage-progress {
    margin-bottom: 20px;
}

.progress-bar {
    height: 8px;
    background-color: var(--light-color);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 5px;
}

.progress-fill {
    height: 100%;
    background-color: var(--primary-color);
    border-radius: 4px;
}

.progress-label {
    font-size: 0.75rem;
    color: var(--secondary-color);
    text-align: right;
}

.recent-chats h3 {
    font-size: 1rem;
    color: var(--dark-color);
    margin-bottom: 10px;
}

.chat-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 20px;
}

.chat-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    border-radius: 8px;
    background-color: var(--light-color);
    text-decoration: none;
    transition: transform 0.2s;
}

.chat-item:hover {
    transform: translateY(-2px);
    background-color: rgba(4, 33, 103, 0.05);
}

.chat-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background-color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 14px;
}

.chat-info {
    flex: 1;
}

.chat-title {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--dark-color);
    margin-bottom: 3px;
}

.chat-meta {
    display: flex;
    gap: 10px;
    font-size: 0.75rem;
    color: var(--secondary-color);
}

.action-buttons {
    display: flex;
    gap: 10px;
}

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

.btn-secondary {
    background-color: var(--secondary-color);
    color: white;
}

.btn-secondary:hover {
    background-color: #757382;
}

/* Recent Messages/Conversations */
.conversation-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 20px;
}

.conversation-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-radius: 8px;
    background-color: var(--light-color);
    text-decoration: none;
    position: relative;
    transition: transform 0.2s;
}

.conversation-item:hover {
    transform: translateY(-2px);
    background-color: rgba(4, 33, 103, 0.05);
}

.conversation-item.unread {
    background-color: rgba(78, 115, 223, 0.05);
    border-left: 3px solid var(--message-color);
}

.conversation-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background-color: var(--message-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 14px;
}

.conversation-info {
    flex: 1;
}

.conversation-name {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--dark-color);
    margin-bottom: 3px;
}

.conversation-meta {
    display: flex;
    gap: 10px;
    font-size: 0.75rem;
    color: var(--secondary-color);
}

.unread-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background-color: var(--message-color);
    color: white;
    font-size: 0.7rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
}

.action-button {
    margin-top: 15px;
}

/* Responsive adjustments for AI and Message sections */
@media (max-width: 992px) {
    .ai-chat-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 576px) {
    .ai-chat-stats {
        grid-template-columns: 1fr;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Bookings Chart
const bookingsCtx = document.getElementById('bookingsChart').getContext('2d');
const bookingsChart = new Chart(bookingsCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($monthly_labels); ?>,
        datasets: [{
            label: 'Bookings',
            data: <?php echo json_encode($monthly_data); ?>,
            backgroundColor: 'rgba(4, 33, 103, 0.1)',
            borderColor: '#042167',
            borderWidth: 2,
            tension: 0.3,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});

// AI Chat Usage Chart
const aiChatCtx = document.getElementById('aiChatChart').getContext('2d');
const aiChatChart = new Chart(aiChatCtx, {
    type: 'doughnut',
    data: {
        labels: ['Used', 'Remaining'],
        datasets: [{
            data: [
                <?php echo $chat_usage_result['message_count']; ?>,
                <?php echo max(0, $chat_usage_result['monthly_limit'] - $chat_usage_result['message_count']); ?>
            ],
            backgroundColor: [
                '#042167',
                '#e3e6f0'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        },
        cutout: '70%'
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>