<?php
// Include session management
require_once "includes/session.php";

// Include config file
require_once "config/db_connect.php";

// Google OAuth Configuration
$google_client_id = getenv('GOOGLE_CLIENT_ID');
$google_client_secret = getenv('GOOGLE_CLIENT_SECRET');
$google_redirect_url = "https://visafy.io/profile_google_callback.php";

// Check if code parameter exists
if (isset($_GET['code'])) {
    // Get the authorization code
    $code = $_GET['code'];
    
    // Get state parameter
    $state = isset($_GET['state']) ? json_decode(base64_decode($_GET['state']), true) : null;
    
    if (!$state || !isset($state['action']) || $state['action'] !== 'link') {
        // Determine redirect based on user type
        $user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'applicant';
        $redirect_path = $user_type === 'consultant' ? 'dashboard/consultant/profile.php' : 'dashboard/applicant/profile.php';
        header("location: $redirect_path?error=invalid_state");
        exit;
    }
    
    $user_id = $state['user_id'];
    
    // Get user type from database
    $user_type_query = "SELECT user_type FROM users WHERE id = ?";
    $stmt = $conn->prepare($user_type_query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user_result = $stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    $user_type = $user_data['user_type'];
    $stmt->close();
    
    // Set redirect path based on user type
    $redirect_path = $user_type === 'consultant' ? 'dashboard/consultant/profile.php' : 'dashboard/applicant/profile.php';
    
    // Exchange authorization code for access token
    $token_url = "https://oauth2.googleapis.com/token";
    $token_data = [
        'code' => $code,
        'client_id' => $google_client_id,
        'client_secret' => $google_client_secret,
        'redirect_uri' => $google_redirect_url,
        'grant_type' => 'authorization_code'
    ];
    
    // Use cURL to request access token
    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
    curl_setopt($ch, CURLOPT_POST, true);
    $token_response = curl_exec($ch);
    curl_close($ch);
    
    $token_data = json_decode($token_response, true);
    
    if (isset($token_data['access_token'])) {
        // Get user profile information
        $access_token = $token_data['access_token'];
        $profile_url = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $access_token;
        
        $ch = curl_init($profile_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $profile_response = curl_exec($ch);
        curl_close($ch);
        
        $profile_data = json_decode($profile_response, true);
        
        if (isset($profile_data['id'])) {
            // Check if this Google ID is already linked to another account
            $check_sql = "SELECT id FROM users WHERE google_id = ? AND id != ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param('si', $profile_data['id'], $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                header("location: $redirect_path?error=google_already_linked");
                exit;
            }
            
            // Begin transaction
            mysqli_begin_transaction($conn);
            
            try {
                // Update user record with Google ID and auth provider
                $update_sql = "UPDATE users SET google_id = ?, auth_provider = 'google' WHERE id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param('si', $profile_data['id'], $user_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update user record");
                }
                
                // Insert OAuth token
                $token_sql = "INSERT INTO oauth_tokens (user_id, provider, provider_user_id, access_token, token_expires) 
                            VALUES (?, 'google', ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
                            ON DUPLICATE KEY UPDATE 
                            access_token = VALUES(access_token),
                            token_expires = VALUES(token_expires)";
                
                $stmt = $conn->prepare($token_sql);
                $stmt->bind_param('iss', $user_id, $profile_data['id'], $access_token);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to store OAuth token");
                }
                
                // Commit transaction
                mysqli_commit($conn);
                
                // Redirect back to appropriate profile with success message
                header("location: $redirect_path?success=google_linked");
                exit;
                
            } catch (Exception $e) {
                // Rollback transaction on error
                mysqli_rollback($conn);
                header("location: $redirect_path?error=link_failed");
                exit;
            }
        }
    }
}

// If we get here, something went wrong
// Determine redirect based on user type
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'applicant';
$redirect_path = $user_type === 'consultant' ? 'dashboard/consultant/profile.php' : 'dashboard/applicant/profile.php';
header("location: $redirect_path?error=unknown");
exit;
