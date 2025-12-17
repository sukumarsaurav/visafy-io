<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once '../../config/db_connect.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['loggedin']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION["id"];

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: ../login.php");
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Prepare profile image
$profile_img = '../assets/images/default-profile.jpg';
// Check for profile image
$profile_image = !empty($user['profile_picture']) ? $user['profile_picture'] : '';

if (!empty($profile_image)) {
    // Check if the path already contains 'uploads/'
    $profile_path = strpos($profile_image, 'uploads/') === 0 
        ? '../../' . $profile_image
        : '../../uploads/' . $profile_image;
        
    if (file_exists($profile_path)) {
        $profile_img = $profile_path;
    }
}

// Get the current page name
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Admin Dashboard'; ?> - Visafy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                <div class="user-dropdown">
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION["first_name"] . ' ' . $_SESSION["last_name"]); ?></span>
                    <img src="<?php echo $profile_img; ?>" alt="Profile" class="profile-img-header" style="width: 32px; height: 32px;">
                    <div class="user-dropdown-menu">
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i> Profile
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
                
            
                
                <a href="eligibility-calculator.php" class="nav-item <?php echo $current_page == 'eligibility-calculator' ? 'active' : ''; ?>">
                    <i class="fas fa-calculator"></i>
                    <span class="nav-item-text">Eligibility Calculator</span>
                </a>
                
              
                
             
                <a href="consultants.php" class="nav-item <?php echo $current_page == 'consultants' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span class="nav-item-text">All Consultants</span>
                </a>
                
                <a href="manage-promocodes.php" class="nav-item <?php echo $current_page == 'manage-promocodes' ? 'active' : ''; ?>">
                    <i class="fas fa-tags"></i>
                    <span class="nav-item-text">Manage Promo Codes</span>
                </a>
              
                <a href="../../logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="nav-item-text">Logout</span>
                </a>
            </nav>
            
            <div class="user-profile sidebar-footer">
                <img src="<?php echo $profile_img; ?>" alt="Profile" class="profile-img">
                <div class="profile-info">
                    <h3 class="profile-name"><?php echo htmlspecialchars($_SESSION["first_name"] . ' ' . $_SESSION["last_name"]); ?></h3>
                    <span class="role-badge">Admin</span>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content <?php echo $main_content_class; ?>">
            <div class="content-wrapper">
                <!-- Page content will be inserted here -->
