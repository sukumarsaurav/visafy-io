<?php
// Set session cookie parameters before starting the session
$session_timeout = 1800; // 30 minutes
$current_cookie_params = session_get_cookie_params();

// Only set cookie parameters if the session is not yet active
if (session_status() == PHP_SESSION_NONE) {
    session_set_cookie_params(
        $session_timeout,
        $current_cookie_params['path'],
        $current_cookie_params['domain'],
        isset($_SERVER['HTTPS']), // Secure flag based on HTTPS
        true // HttpOnly flag
    );
    
    // Start the session
    session_start();
    
    // Initialize session variables if new session
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
        $_SESSION['created_at'] = time();
    }
}

// Check if the user's last activity is set
if (isset($_SESSION['last_activity'])) {
    // Calculate the time passed since the user's last activity
    $time_passed = time() - $_SESSION['last_activity'];
    
    // If more time has passed than the session timeout, destroy the session
    if ($time_passed > $session_timeout) {
        session_unset();
        session_destroy();
        
        // Redirect to login page if this is accessed directly
        if (basename($_SERVER['PHP_SELF']) != 'login.php' && 
            basename($_SERVER['PHP_SELF']) != 'index.php' && 
            basename($_SERVER['PHP_SELF']) != 'logout.php') {
            header("Location: /login.php?session_expired=1");
            exit();
        }
    }
}

// Update the last activity time
$_SESSION['last_activity'] = time();

// Regenerate session ID periodically to prevent session fixation
if (!isset($_SESSION['created_at'])) {
    $_SESSION['created_at'] = time();
} else if (time() - $_SESSION['created_at'] > 600) { // Regenerate every 10 minutes
    session_regenerate_id(true);
    $_SESSION['created_at'] = time();
}

// Helper functions for session management
function is_logged_in() {
    return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
}

function get_user_id() {
    return is_logged_in() ? $_SESSION['id'] : null;
}

function get_user_type() {
    return is_logged_in() ? $_SESSION['user_type'] : null;
}

function get_user_name() {
    if (is_logged_in()) {
        return $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
    }
    return '';
}

function has_role($roles) {
    if (!is_logged_in()) {
        return false;
    }
    
    if (is_array($roles)) {
        return in_array($_SESSION['user_type'], $roles);
    } else {
        return $_SESSION['user_type'] === $roles;
    }
}

function require_login($redirect_url = 'login.php') {
    if (!is_logged_in()) {
        header("Location: $redirect_url");
        exit();
    }
}

function require_role($roles, $redirect_url = 'login.php') {
    require_login($redirect_url);
    
    if (!has_role($roles)) {
        header("Location: $redirect_url");
        exit();
    }
}

// Check if the user is logged in and authorize access
function authorize_admin() {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['user_type'] !== 'admin') {
        header('Location: /login.php');
        exit();
    }
}

function authorize_team_member() {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['user_type'] !== 'member') {
        header('Location: /login.php');
        exit();
    }
}

function authorize_applicant() {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['user_type'] !== 'applicant') {
        header('Location: /login.php');
        exit();
    }
}

function authorize_any_user() {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        header('Location: /login.php');
        exit();
    }
} 