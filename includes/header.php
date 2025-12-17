<?php
// Start session only if one isn't already active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set default page title if not set
$page_title = isset($page_title) ? $page_title : "Visayfy | Canadian Immigration Consultancy";

// Check if user is logged in
$is_logged_in = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;

// Prepare profile image if user is logged in
$profile_img = '/assets/images/default-profile.svg';

if ($is_logged_in) {
    // Check for profile image
    $profile_image = !empty($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : '';
    $user_id = $_SESSION['id'];

    // Debug: Output session values
    error_log('Session user_id: ' . print_r($user_id, true));
    error_log('Session profile_picture: ' . print_r($profile_image, true));
    echo '<!-- Session user_id: ' . htmlspecialchars(print_r($user_id, true)) . ' -->';
    echo '<!-- Session profile_picture: ' . htmlspecialchars(print_r($profile_image, true)) . ' -->';

    $found_profile_img = false;
    $checked_paths = [];

    if (!empty($profile_image)) {
        // Check for new structure first (users/{user_id}/profile/...)
        if (strpos($profile_image, 'users/') === 0) {
            $profile_path = $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $profile_image;
            $checked_paths[] = $profile_path;
            if (file_exists($profile_path)) {
                $profile_img = '/uploads/' . $profile_image;
                $found_profile_img = $profile_img;
            }
        } else {
            // Legacy structure
            $legacy_paths = [
                '/uploads/profiles/' . $profile_image,
                '/uploads/profile/' . $profile_image,
                '/uploads/users/' . $user_id . '/profile/' . $profile_image
            ];
            foreach ($legacy_paths as $path) {
                $full_path = $_SERVER['DOCUMENT_ROOT'] . $path;
                $checked_paths[] = $full_path;
                if (file_exists($full_path)) {
                    $profile_img = $path;
                    $found_profile_img = $profile_img;
                    break;
                }
            }
        }
    }

    // Debug: Output all checked paths and which one (if any) was found
    error_log('Checked profile image paths: ' . print_r($checked_paths, true));
    echo '<!-- Checked profile image paths: ' . htmlspecialchars(print_r($checked_paths, true)) . ' -->';
    if ($found_profile_img) {
        error_log('Profile image found at: ' . $found_profile_img);
        echo '<!-- Profile image found at: ' . htmlspecialchars($found_profile_img) . ' -->';
    } else {
        error_log('No profile image found, using default.');
        echo '<!-- No profile image found, using default. -->';
    }
}

// Change this line
error_log('Profile picture in session: ' . print_r($_SESSION['profile_picture'] ?? 'not set', true));

// Remove or comment out these debug lines
// echo '<!-- Profile picture in session: ' . htmlspecialchars(print_r($_SESSION['profile_picture'], true)) . ' -->';
// echo '<!-- Checking file: ' . htmlspecialchars($_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $_SESSION['profile_picture']) . ' -->';
// echo '<!-- File exists: ' . (file_exists($_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $_SESSION['profile_picture']) ? 'yes' : 'no') . ' -->';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Visafy Immigration Consultancy'; ?></title>
    <meta name="description" content="Expert Canadian immigration consultancy services for study permits, work permits, express entry, and more.">
    
    <!-- Favicon -->
    <link rel="icon" href="<?php echo $base; ?>/favicon.ico" type="image/x-icon">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Lora:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    
    <!-- Swiper CSS for Sliders -->
    <link rel="stylesheet" href="https://unpkg.com/swiper@8/swiper-bundle.min.css">
    
    <!-- AOS Animation CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    <!-- Move JS libraries to the end of head to ensure they load before other scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/css/page.css">

    <!-- Load utils.js before other scripts -->
    <script src="/assets/js/utils.js"></script>

    <!-- Your custom scripts should come after utils.js -->
    <script src="/assets/js/main.js" defer></script>
    <script src="/assets/js/resources.js" defer></script>
    <script src="/assets/js/notifications.js" defer></script>
</head>
<body>
    <!-- Drawer Overlay -->
    <div class="drawer-overlay"></div>
    
    <!-- Side Drawer -->
    <div class="side-drawer">
        <div class="drawer-header">
            <a href="/" class="drawer-logo">
                <img src="/assets/images/logo-Visafy-light.png" alt="Visafy Logo" class="mobile-logo">
            </a>
            <button class="drawer-close"><i class="fas fa-times"></i></button>
        </div>
        <nav class="drawer-nav">
            <a href="/about-us.php" class="drawer-item">About Us</a>
            <a href="/services.php" class="drawer-item">Services</a>
            <a href="/eligibility-test.php" class="drawer-item">Eligibility Check</a>
            <a href="/applicant.php" class="drawer-item">For Applicant</a>
            <a href="/become-member.php" class="drawer-item"> For Immigration Professionals</a>

        </nav>
    </div>

    <!-- Header Section -->
    <header class="header">
        <div class="container header-container">
            <!-- Left Section: Mobile Menu Toggle and Logo -->
            <div class="header-left">
                <button class="mobile-menu-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="logo">
                    <a href="/">
                        <img src="/assets/images/logo-Visafy-light.png" alt="Visafy Logo" class="desktop-logo">
                    </a>
                </div>
            </div>
            
            <!-- Right Section: Navigation and Header Actions -->
            <div class="header-right">
                <!-- Main Navigation -->
                <nav class="main-nav">
                    <ul class="nav-menu">
                        <li class="nav-item"><a href="/about-us.php">About Us</a></li>
                        <li class="nav-item"><a href="/eligibility-test.php">Eligibility Check</a></li>
                        <a href="/applicant.php" class="drawer-item">For Applicant</a>
                        <a href="/become-member.php" class="drawer-item"> For Immigration Professionals</a>
                    </ul>
                </nav>
                
                <!-- Header Actions: Book Service Button or User Profile -->
                <div class="header-actions">
                    <!-- Book Service button - only visible for non-logged in users -->
                    <?php if(!$is_logged_in): ?>
                    <div class="consultation-btn">
                        <a href="/book-service.php" class="btn btn-primary">Book Service</a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($is_logged_in): ?>
                    <!-- User is logged in - show profile dropdown -->
                    <div class="action-buttons">
                        <div class="user-profile-dropdown">
                            <button class="profile-toggle">
                                <span class="username"><?php echo htmlspecialchars($_SESSION["first_name"] . ' ' . $_SESSION["last_name"]); ?></span>
                                <img src="<?php echo $profile_img; ?>" alt="Profile" class="profile-image">
                            </button>
                            <div class="profile-dropdown-menu">
                                <?php if($_SESSION["user_type"] == 'consultant'): ?>
                                <a href="/dashboard/consultant/index.php" class="dropdown-item">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                                <?php elseif($_SESSION["user_type"] == 'member'): ?>
                                <a href="/dashboard/memberconsultant/index.php" class="dropdown-item">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                                <?php elseif($_SESSION["user_type"] == 'applicant'): ?>
                                <a href="/dashboard/applicant/index.php" class="dropdown-item">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                                <?php elseif($_SESSION["user_type"] == 'admin'): ?>
                                <a href="/dashboard/admin/index.php" class="dropdown-item">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                                <?php else: ?>
                                <a href="/dashboard.php" class="dropdown-item">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                                <?php endif; ?>
                                <?php if($_SESSION["user_type"] == 'consultant'): ?>
                                <a href="/dashboard/consultant/profile.php" class="dropdown-item">
                                    <i class="fas fa-user"></i> Profile
                                </a>
                                <?php elseif($_SESSION["user_type"] == 'member'): ?>
                                <a href="/dashboard/consultant/profile.php" class="dropdown-item">
                                    <i class="fas fa-user"></i> Profile
                                </a>
                                <?php elseif($_SESSION["user_type"] == 'applicant'): ?>
                                <a href="/dashboard/applicant/profile.php" class="dropdown-item">
                                    <i class="fas fa-user"></i> Profile
                                </a>
                                <!-- Add Book Service button only for applicant users -->
                                <a href="/book-service.php" class="dropdown-item">
                                    <i class="fas fa-calendar-check"></i> Book Service
                                </a>
                                <?php elseif($_SESSION["user_type"] == 'admin'): ?>
                                <a href="/dashboard/admin/profile.php" class="dropdown-item">
                                    <i class="fas fa-user"></i> Profile
                                </a>
                                <?php else: ?>
                                <a href="/profile.php" class="dropdown-item">
                                    <i class="fas fa-user"></i> Profile
                                </a>
                                <?php endif; ?>
                                <a href="/notifications.php" class="dropdown-item">
                                    <i class="fas fa-bell"></i> Notifications
                                </a>
                                <div class="dropdown-divider"></div>
                                <a href="/logout.php" class="dropdown-item">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- User is not logged in - show login button -->
                    <div class="action-buttons">
                        <div class="auth-button">
                            <a href="/login.php" class="btn btn-secondary">Login</a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Mobile Menu JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle functionality
            const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
            const drawerOverlay = document.querySelector('.drawer-overlay');
            const sideDrawer = document.querySelector('.side-drawer');
            const drawerClose = document.querySelector('.drawer-close');
            
            // Function to open the drawer
            function openDrawer() {
                document.body.classList.add('drawer-active');
                sideDrawer.style.left = '0';
                drawerOverlay.style.display = 'block';
            }
            
            // Function to close the drawer
            function closeDrawer() {
                document.body.classList.remove('drawer-active');
                sideDrawer.style.left = '-280px';
                drawerOverlay.style.display = 'none';
            }
            
            // Toggle drawer when mobile menu button is clicked
            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', openDrawer);
            }
            
            // Close drawer when close button or overlay is clicked
            if (drawerClose) {
                drawerClose.addEventListener('click', closeDrawer);
            }
            
            if (drawerOverlay) {
                drawerOverlay.addEventListener('click', closeDrawer);
            }
            
            // Close drawer when ESC key is pressed
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    closeDrawer();
                }
            });
            
            // Profile dropdown functionality for mobile (supplements CSS hover)
            const profileToggle = document.querySelector('.profile-toggle');
            const profileDropdown = document.querySelector('.profile-dropdown-menu');
            
            if (profileToggle && profileDropdown) {
                // Add click event for mobile devices
                profileToggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    profileDropdown.classList.toggle('show');
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function() {
                    if (profileDropdown.classList.contains('show')) {
                        profileDropdown.classList.remove('show');
                    }
                });
            }
        });
    </script>
</body>
</html>
