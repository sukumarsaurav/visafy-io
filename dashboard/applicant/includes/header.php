<?php
// Start session only if one isn't already active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/db_connect.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is an applicant
if (!isset($_SESSION["loggedin"]) || !isset($_SESSION["user_type"]) || $_SESSION["user_type"] != 'applicant') {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION["id"];

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: ../../login.php");
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Fetch applicant data
$stmt = $conn->prepare("SELECT * FROM applicants WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$applicant_result = $stmt->get_result();
$applicant_data = [];

if ($applicant_result->num_rows > 0) {
    $applicant_data = $applicant_result->fetch_assoc();
}
$stmt->close();

// Check for unread notifications
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0 AND is_dismissed = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notif_result = $stmt->get_result();
$notification_count = $notif_result->fetch_assoc()['count'];
$stmt->close();

// Determine if sidebar should be collapsed based on user preference or default
$sidebar_collapsed = isset($_COOKIE['sidebar_collapsed']) && $_COOKIE['sidebar_collapsed'] === 'true';
$sidebar_class = $sidebar_collapsed ? 'collapsed' : '';
$main_content_class = $sidebar_collapsed ? 'expanded' : '';

// Prepare profile image
$profile_img = '../../assets/images/default-profile.jpg';
// Check for profile image
$profile_image = !empty($user['profile_picture']) ? $user['profile_picture'] : '';

if (!empty($profile_image)) {
    // Check if file exists - supports both old and new directory structure
    if (strpos($profile_image, 'users/') === 0) {
        // New structure - user specific directory
        if (file_exists('../../uploads/' . $profile_image)) {
            $profile_img = '../../uploads/' . $profile_image;
        }
    } else {
        // Legacy structure
        if (file_exists('../../uploads/profiles/' . $profile_image)) {
            $profile_img = '../../uploads/profiles/' . $profile_image;
        } else if (file_exists('../../uploads/profile/' . $profile_image)) {
            $profile_img = '../../uploads/profile/' . $profile_image;
        }
    }
}

// Get the current page name
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Set dashboard title
$dashboard_title = 'Applicant Dashboard';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : $dashboard_title; ?> - Visafy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="assets/css/applicant.css">
    <?php if (isset($page_specific_css)): ?>
    <link rel="stylesheet" href="<?php echo $page_specific_css; ?>">
    <?php endif; ?>
</head>

<body>
    <div class="dashboard-container">
        <!-- Header -->
        <header class="header">
            <div class="header-left">
                <button id="sidebar-toggle" class="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <a href="index.php" class="header-logo">
                    <img src="../../assets/images/logo-Visafy-light.png" alt="Visafy Logo" class="desktop-logo">
                </a>
            </div>
            <div class="header-right">
                <div class="notification-dropdown">
                    <div class="notification-icon" id="notification-toggle">
                        <i class="fas fa-bell"></i>
                        <?php if ($notification_count > 0): ?>
                        <span class="notification-badge"><?php echo $notification_count; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="notification-menu" id="notification-menu">
                        <div class="notification-header">
                            <h3>Notifications</h3>
                            <?php if ($notification_count > 0): ?>
                            <a href="notification.php?action=mark_all_read" class="mark-all-read">Mark all as read</a>
                            <?php endif; ?>
                        </div>
                        <div class="notification-list">
                            <?php
                            // Get recent notifications
                            $recent_notifications_query = "SELECT 
                                n.id, n.title, n.message, n.created_at, n.is_read, n.action_link,
                                nt.icon, nt.color, nt.type_name
                                FROM notifications n
                                JOIN notification_types nt ON n.type_id = nt.id
                                WHERE n.user_id = ? AND n.is_dismissed = 0
                                ORDER BY n.created_at DESC
                                LIMIT 5";
                            $stmt = $conn->prepare($recent_notifications_query);
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $notifications_result = $stmt->get_result();
                            $notifications_list = [];
                            
                            if ($notifications_result && $notifications_result->num_rows > 0) {
                                while ($row = $notifications_result->fetch_assoc()) {
                                    $notifications_list[] = $row;
                                }
                            }
                            $stmt->close();
                            
                            if (empty($notifications_list)):
                            ?>
                            <div class="notification-item empty">
                                <p>No new notifications</p>
                            </div>
                            <?php else: ?>
                            <?php foreach ($notifications_list as $notification): ?>
                            <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                                <div class="notification-icon-small" style="background-color: <?php echo $notification['color']; ?>">
                                    <i class="<?php echo $notification['icon']; ?>"></i>
                                </div>
                                <div class="notification-details">
                                    <h4><?php echo htmlspecialchars($notification['title']); ?></h4>
                                    <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                    <span class="notification-time">
                                        <?php 
                                        $notification_time = new DateTime($notification['created_at']);
                                        $now = new DateTime();
                                        $diff = $notification_time->diff($now);
                                        
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
                                <?php if (!empty($notification['action_link'])): ?>
                                <a href="<?php echo $notification['action_link']; ?>" class="notification-action">
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (!$notification['is_read']): ?>
                                <a href="notification.php?action=mark_read&id=<?php echo $notification['id']; ?>" 
                                   class="notification-mark-read" title="Mark as read">
                                    <i class="fas fa-check"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="notification-footer">
                            <a href="notification.php">View all notifications</a>
                        </div>
                    </div>
                </div>
                <div class="user-dropdown">
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION["first_name"] . ' ' . $_SESSION["last_name"]); ?></span>
                    <img src="<?php echo $profile_img; ?>" alt="Profile" class="profile-img-header"
                        style="width: 32px; height: 32px;">
                    <div class="user-dropdown-menu">
                        <a href="../../index.php" class="dropdown-item">
                            <i class="fas fa-globe"></i>
                            Back to Website
                        </a>
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i> Profile
                        </a>
                        <a href="settings.php" class="dropdown-item">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="../../logout.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Sidebar -->
        <aside class="sidebar <?php echo $sidebar_class; ?>">
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item <?php echo $current_page == 'index' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="nav-item-text">Dashboard</span>
                </a>

                <div class="sidebar-divider"></div>
                
                <a href="meetings.php" class="nav-item <?php echo $current_page == 'meetings' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i>
                    <span class="nav-item-text">My Meetings</span>
                </a>
                
                <a href="applications.php" class="nav-item <?php echo $current_page == 'applications' ? 'active' : ''; ?>">
                    <i class="fas fa-folder-open"></i>
                    <span class="nav-item-text">Applications</span>
                </a>
                
               
                <div class="sidebar-divider"></div>
                
                <a href="messages.php" class="nav-item <?php echo $current_page == 'messages' ? 'active' : ''; ?>">
                    <i class="fas fa-envelope"></i>
                    <span class="nav-item-text">Messages</span>
                </a>
                
                <div class="sidebar-divider"></div>
                
                <a href="profile.php" class="nav-item <?php echo $current_page == 'profile' ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i>
                    <span class="nav-item-text">My Profile</span>
                </a>
                
                <a href="../../logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="nav-item-text">Logout</span>
                </a>
            </nav>
            
            <div class="user-profile sidebar-footer">
                <img src="<?php echo $profile_img; ?>" alt="Profile" class="profile-img">
                <div class="profile-info">
                    <h3 class="profile-name">
                        <?php echo htmlspecialchars($_SESSION["first_name"] . ' ' . $_SESSION["last_name"]); ?></h3>
                    <span class="role-badge">Applicant</span>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content <?php echo $main_content_class; ?>">
            <div class="content-wrapper">
                <!-- Page content will be inserted here -->
  