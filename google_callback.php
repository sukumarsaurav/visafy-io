<?php
// Include session management
require_once "includes/session.php";

// Include config file
require_once "config/db_connect.php";

// Google OAuth Configuration
$google_client_id = getenv('GOOGLE_CLIENT_ID');
$google_client_secret = getenv('GOOGLE_CLIENT_SECRET');
$google_redirect_url = "https://visafy.io/google_callback.php";

// Check if code parameter exists
if (isset($_GET['code'])) {
    // Get the authorization code
    $code = $_GET['code'];
    
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
            // Check if this Google ID exists in our database
            $sql = "SELECT id, first_name, last_name, email, user_type, email_verified, status FROM users WHERE google_id = ?";
            
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "s", $profile_data['id']);
                
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_store_result($stmt);
                    
                    // If user exists, log them in
                    if (mysqli_stmt_num_rows($stmt) == 1) {
                        mysqli_stmt_bind_result($stmt, $id, $first_name, $last_name, $email, $user_type, $email_verified, $status);
                        mysqli_stmt_fetch($stmt);
                        
                        // Check if account is active
                        if ($status != "active") {
                            $_SESSION["login_err"] = "Your account is suspended. Please contact support.";
                            header("location: login.php");
                            exit;
                        }
                        
                        // Create session
                        $_SESSION["loggedin"] = true;
                        $_SESSION["id"] = $id;
                        $_SESSION["email"] = $email;
                        $_SESSION["first_name"] = $first_name;
                        $_SESSION["last_name"] = $last_name;
                        $_SESSION["user_type"] = $user_type;
                        $_SESSION["last_activity"] = time();
                        $_SESSION["created_at"] = time();
                        
                        // Update OAuth token in database
                        $sql_token = "INSERT INTO oauth_tokens (user_id, provider, provider_user_id, access_token, token_expires) 
                                     VALUES (?, 'google', ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
                                     ON DUPLICATE KEY UPDATE 
                                     access_token = VALUES(access_token),
                                     token_expires = VALUES(token_expires)";
                        
                        if ($stmt_token = mysqli_prepare($conn, $sql_token)) {
                            mysqli_stmt_bind_param($stmt_token, "iss", $id, $profile_data['id'], $access_token);
                            mysqli_stmt_execute($stmt_token);
                            mysqli_stmt_close($stmt_token);
                        }
                        
                        // Redirect to appropriate dashboard based on user type
                        switch($user_type) {
                            case "applicant":
                                header("location: dashboard/applicant/index.php");
                                break;
                            case "consultant":
                            case "member":
                            case "custom":
                                header("location: dashboard/consultant/index.php");
                                break;
                            case "admin":
                                header("location: dashboard/admin/index.php");
                                break;
                            default:
                                // Fallback to applicant dashboard
                                header("location: dashboard/applicant/index.php");
                        }
                        exit;
                    } else {
                        // User doesn't exist, create a new account
                        
                        // First check if email already exists
                        $sql_email = "SELECT id FROM users WHERE email = ?";
                        
                        if ($stmt_email = mysqli_prepare($conn, $sql_email)) {
                            mysqli_stmt_bind_param($stmt_email, "s", $profile_data['email']);
                            
                            if (mysqli_stmt_execute($stmt_email)) {
                                mysqli_stmt_store_result($stmt_email);
                                
                                if (mysqli_stmt_num_rows($stmt_email) > 0) {
                                    // Email already exists, show error
                                    $_SESSION["login_err"] = "An account with this email already exists. Please log in with your password or reset it.";
                                    header("location: login.php");
                                    exit;
                                }
                            }
                            
                            mysqli_stmt_close($stmt_email);
                        }
                        
                        // Begin transaction
                        mysqli_begin_transaction($conn);
                        
                        try {
                            // Create new user account
                            $sql_insert = "INSERT INTO users (first_name, last_name, email, password, user_type, email_verified, google_id, auth_provider, profile_picture) 
                                        VALUES (?, ?, ?, '', 'applicant', 1, ?, 'google', ?)";
                            
                            if ($stmt_insert = mysqli_prepare($conn, $sql_insert)) {
                                // Extract first and last name from Google profile
                                $first_name = isset($profile_data['given_name']) ? $profile_data['given_name'] : '';
                                $last_name = isset($profile_data['family_name']) ? $profile_data['family_name'] : '';
                                $profile_picture = isset($profile_data['picture']) ? $profile_data['picture'] : '';
                                
                                mysqli_stmt_bind_param($stmt_insert, "sssss", $first_name, $last_name, $profile_data['email'], $profile_data['id'], $profile_picture);
                                
                                if (mysqli_stmt_execute($stmt_insert)) {
                                    $user_id = mysqli_insert_id($conn);
                                    
                                    // Insert into applicants table
                                    $sql_applicant = "INSERT INTO applicants (user_id) VALUES (?)";
                                    
                                    if ($stmt_applicant = mysqli_prepare($conn, $sql_applicant)) {
                                        mysqli_stmt_bind_param($stmt_applicant, "i", $user_id);
                                        
                                        if (!mysqli_stmt_execute($stmt_applicant)) {
                                            // If applicant creation fails, throw exception
                                            throw new Exception("Failed to create applicant profile");
                                        }
                                        
                                        mysqli_stmt_close($stmt_applicant);
                                    } else {
                                        // If prepare fails, throw exception
                                        throw new Exception("Failed to prepare applicant statement");
                                    }
                                    
                                    // Insert OAuth token
                                    $sql_token = "INSERT INTO oauth_tokens (user_id, provider, provider_user_id, access_token, token_expires) 
                                                VALUES (?, 'google', ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))";
                                    
                                    if ($stmt_token = mysqli_prepare($conn, $sql_token)) {
                                        mysqli_stmt_bind_param($stmt_token, "iss", $user_id, $profile_data['id'], $access_token);
                                        
                                        if (!mysqli_stmt_execute($stmt_token)) {
                                            // If OAuth token creation fails, throw exception
                                            throw new Exception("Failed to store OAuth token");
                                        }
                                        
                                        mysqli_stmt_close($stmt_token);
                                    } else {
                                        // If prepare fails, throw exception
                                        throw new Exception("Failed to prepare OAuth token statement");
                                    }
                                    
                                    // Commit transaction
                                    mysqli_commit($conn);
                                    
                                    // Create session
                                    $_SESSION["loggedin"] = true;
                                    $_SESSION["id"] = $user_id;
                                    $_SESSION["email"] = $profile_data['email'];
                                    $_SESSION["first_name"] = $first_name;
                                    $_SESSION["last_name"] = $last_name;
                                    $_SESSION["user_type"] = 'applicant';
                                    $_SESSION["last_activity"] = time();
                                    $_SESSION["created_at"] = time();
                                    
                                    // Redirect to applicant dashboard
                                    header("location: dashboard/applicant/index.php");
                                    exit;
                                } else {
                                    // If user creation fails, throw exception
                                    throw new Exception("Failed to create user account");
                                }
                                
                                mysqli_stmt_close($stmt_insert);
                            } else {
                                // If prepare fails, throw exception
                                throw new Exception("Failed to prepare user statement");
                            }
                        } catch (Exception $e) {
                            // Rollback transaction on error
                            mysqli_rollback($conn);
                            echo "Error: " . $e->getMessage() . ". Please try again later.";
                        }
                    }
                }
                
                mysqli_stmt_close($stmt);
            }
        } else {
            echo "Failed to get profile information from Google.";
        }
    } else {
        echo "Failed to get access token from Google.";
    }
} else {
    // No code parameter, redirect to login page
    header("location: login.php");
    exit;
}

// Close connection
mysqli_close($conn);
?>
