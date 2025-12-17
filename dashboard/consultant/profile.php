<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "My Profile";
$page_specific_css = "assets/css/profile.css";
require_once 'includes/header.php';

// Google OAuth Configuration
$google_client_id = getenv('GOOGLE_CLIENT_ID');
$google_client_secret = getenv('GOOGLE_CLIENT_SECRET');
$google_redirect_url = "https://visafy.io/profile_google_callback.php";

// Get the current user data
$user_id = isset($_SESSION["user_id"]) ? $_SESSION["user_id"] : (isset($_SESSION["id"]) ? $_SESSION["id"] : 0);
$organization_id = isset($_SESSION["organization_id"]) ? $_SESSION["organization_id"] : null;

// Create user upload directories if they don't exist
$user_upload_dir = '../../uploads/users/' . $user_id;
$profile_dir = $user_upload_dir . '/profile';
$documents_dir = $user_upload_dir . '/documents';
$banners_dir = $user_upload_dir . '/banners';

// Create directories if they don't exist
if (!is_dir($user_upload_dir)) {
    mkdir($user_upload_dir, 0755, true);
}
if (!is_dir($profile_dir)) {
    mkdir($profile_dir, 0755, true);
}
if (!is_dir($documents_dir)) {
    mkdir($documents_dir, 0755, true);
}
if (!is_dir($banners_dir)) {
    mkdir($banners_dir, 0755, true);
}

// Update query to include consultant_profiles table data
$query = "SELECT u.*, c.company_name, o.name as organization_name, c.membership_plan_id,
          mp.name as membership_plan, mp.max_team_members, c.team_members_count, oauth.provider, oauth.provider_user_id,
          cp.bio, cp.specializations, cp.years_experience, cp.education, cp.certifications, cp.languages,
          cp.website, cp.social_linkedin, cp.social_twitter, cp.social_facebook, cp.is_featured, 
          cp.is_verified, cp.verified_by, cp.verified_at, cp.banner_image
          FROM users u 
          LEFT JOIN consultants c ON u.id = c.user_id
          LEFT JOIN organizations o ON u.organization_id = o.id
          LEFT JOIN membership_plans mp ON c.membership_plan_id = mp.id
          LEFT JOIN oauth_tokens oauth ON u.id = oauth.user_id AND oauth.provider = 'google'
          LEFT JOIN consultant_profiles cp ON u.id = cp.consultant_id
          WHERE u.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();

// Initialize variables for form handling
$success_message = '';
$error_message = '';
$validation_errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Profile Update
    if (isset($_POST['update_profile'])) {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        
        // Validate inputs
        if (empty($first_name)) {
            $validation_errors[] = "First name is required";
        }
        if (empty($last_name)) {
            $validation_errors[] = "Last name is required";
        }
        if (empty($email)) {
            $validation_errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $validation_errors[] = "Invalid email format";
        }
        
        // Check if email already exists (if changing email)
        if ($email !== $user_data['email']) {
            $email_check = "SELECT id FROM users WHERE email = ? AND id != ? AND deleted_at IS NULL";
            $stmt = $conn->prepare($email_check);
            $stmt->bind_param('si', $email, $user_id);
            $stmt->execute();
            $check_result = $stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $validation_errors[] = "Email already in use by another account";
            }
            $stmt->close();
        }
        
        // Handle profile picture upload
        $profile_picture = $user_data['profile_picture'];
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            // Delete old profile picture if exists
            if (!empty($profile_picture) && file_exists('../../uploads/' . $profile_picture)) {
                unlink('../../uploads/' . $profile_picture);
            }
            
            $upload_result = handle_user_file_upload(
                $user_id, 
                $_FILES['profile_picture'],
                'profile',
                [
                    'max_size' => 2 * 1024 * 1024, // 2MB
                    'allowed_types' => ['image/jpeg', 'image/png', 'image/gif'],
                    'filename_prefix' => 'profile_'
                ]
            );
            
            if ($upload_result['status']) {
                $profile_picture = $upload_result['file_path'];
            } else {
                $validation_errors[] = $upload_result['message'];
            }
        }
        
        // Update profile if no validation errors
        if (empty($validation_errors)) {
            try {
                // Start transaction
                $conn->begin_transaction();
                
                // Update users table
                $update_query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, profile_picture = ? WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param('ssssi', $first_name, $last_name, $email, $profile_picture, $user_id);
                $stmt->execute();
                
                // Update consultant_profiles table if user is a consultant
                if ($user_data['user_type'] === 'consultant') {
                    // Check if consultant profile exists
                    $check_profile = "SELECT consultant_id FROM consultant_profiles WHERE consultant_id = ?";
                    $stmt = $conn->prepare($check_profile);
                    $stmt->bind_param('i', $user_id);
                    $stmt->execute();
                    $profile_result = $stmt->get_result();
                    
                    if ($profile_result->num_rows > 0) {
                        // Update existing profile
                        $update_profile = "UPDATE consultant_profiles SET profile_image = ? WHERE consultant_id = ?";
                        $stmt = $conn->prepare($update_profile);
                        $stmt->bind_param('si', $profile_picture, $user_id);
                        $stmt->execute();
                    } else {
                        // Insert new profile
                        $insert_profile = "INSERT INTO consultant_profiles (consultant_id, profile_image) VALUES (?, ?)";
                        $stmt = $conn->prepare($insert_profile);
                        $stmt->bind_param('is', $user_id, $profile_picture);
                        $stmt->execute();
                    }
                }
                
                // Commit transaction
                $conn->commit();
                
                $success_message = "Profile updated successfully";
                
                // Update session variables
                $_SESSION["first_name"] = $first_name;
                $_SESSION["last_name"] = $last_name;
                $_SESSION["email"] = $email;
                $_SESSION["profile_picture"] = $profile_picture;
                
                // Refresh user data
                $result = $conn->query("SELECT u.*, c.company_name, o.name as organization_name, c.membership_plan_id,
                                    mp.name as membership_plan, mp.max_team_members, c.team_members_count,
                                    cp.bio, cp.specializations, cp.years_experience, cp.education, cp.certifications, cp.languages,
                                    cp.website, cp.social_linkedin, cp.social_twitter, cp.social_facebook, cp.is_featured, 
                                    cp.is_verified, cp.verified_by, cp.verified_at, cp.banner_image, cp.profile_image 
                                    FROM users u 
                                    LEFT JOIN consultants c ON u.id = c.user_id
                                    LEFT JOIN organizations o ON u.organization_id = o.id
                                    LEFT JOIN membership_plans mp ON c.membership_plan_id = mp.id
                                    LEFT JOIN consultant_profiles cp ON u.id = cp.consultant_id
                                    WHERE u.id = $user_id");
                $user_data = $result->fetch_assoc();
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $error_message = "Error updating profile: " . $e->getMessage();
            }
        } else {
            $error_message = implode("<br>", $validation_errors);
        }
    }
    
    // Update professional info
    if (isset($_POST['update_professional_info'])) {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            $bio = trim($_POST['bio'] ?? '');
            $specializations = trim($_POST['specializations'] ?? '');
            $years_experience = intval($_POST['years_experience'] ?? 0);
            $education = trim($_POST['education'] ?? '');
            $certifications = trim($_POST['certifications'] ?? '');
            $languages = trim($_POST['languages'] ?? '');
            $website = trim($_POST['website'] ?? '');
            $social_linkedin = trim($_POST['social_linkedin'] ?? '');
            $social_twitter = trim($_POST['social_twitter'] ?? '');
            $social_facebook = trim($_POST['social_facebook'] ?? '');
            
            // Validate URLs if provided
            $validation_errors = [];
            if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
                $validation_errors[] = "Website URL is invalid";
            }
            if (!empty($social_linkedin) && !filter_var($social_linkedin, FILTER_VALIDATE_URL)) {
                $validation_errors[] = "LinkedIn URL is invalid";
            }
            if (!empty($social_twitter) && !filter_var($social_twitter, FILTER_VALIDATE_URL)) {
                $validation_errors[] = "Twitter URL is invalid";
            }
            if (!empty($social_facebook) && !filter_var($social_facebook, FILTER_VALIDATE_URL)) {
                $validation_errors[] = "Facebook URL is invalid";
            }
            
            // Handle banner image upload
            $banner_image = $user_data['banner_image'] ?? '';
            if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] == 0) {
                $upload_result = handle_user_file_upload(
                    $user_id, 
                    $_FILES['banner_image'],
                    'banners',
                    [
                        'max_size' => 5 * 1024 * 1024, // 5MB
                        'allowed_types' => ['image/jpeg', 'image/png', 'image/gif'],
                        'filename_prefix' => 'banner_'
                    ]
                );
                
                if ($upload_result['status']) {
                    $banner_image = $upload_result['file_path'];
                } else {
                    $validation_errors[] = $upload_result['message'];
                }
            }
            
            // Update professional info if no validation errors
            if (empty($validation_errors)) {
                // Check if consultant profile exists
                $check_query = "SELECT consultant_id FROM consultant_profiles WHERE consultant_id = ?";
                $stmt = $conn->prepare($check_query);
                if (!$stmt) {
                    throw new Exception("Error preparing check query: " . $conn->error);
                }
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $check_result = $stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    // Update existing profile
                    $update_query = "UPDATE consultant_profiles SET 
                                    bio = ?, 
                                    specializations = ?, 
                                    years_experience = ?, 
                                    education = ?, 
                                    certifications = ?, 
                                    languages = ?, 
                                    website = ?, 
                                    social_linkedin = ?, 
                                    social_twitter = ?, 
                                    social_facebook = ?, 
                                    banner_image = ?
                                    WHERE consultant_id = ?";
                    $stmt = $conn->prepare($update_query);
                    if (!$stmt) {
                        throw new Exception("Error preparing update query: " . $conn->error);
                    }
                    $stmt->bind_param('ssissssssssi', $bio, $specializations, $years_experience, $education, $certifications, 
                                    $languages, $website, $social_linkedin, $social_twitter, $social_facebook, $banner_image, $user_id);
                } else {
                    // Insert new profile
                    $insert_query = "INSERT INTO consultant_profiles 
                                    (consultant_id, bio, specializations, years_experience, education, certifications, 
                                    languages, website, social_linkedin, social_twitter, social_facebook, banner_image)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($insert_query);
                    if (!$stmt) {
                        throw new Exception("Error preparing insert query: " . $conn->error);
                    }
                    $stmt->bind_param('ississssssss', $user_id, $bio, $specializations, $years_experience, $education, $certifications, 
                                    $languages, $website, $social_linkedin, $social_twitter, $social_facebook, $banner_image);
                }
                
                if (!$stmt->execute()) {
                    throw new Exception("Error executing query: " . $stmt->error);
                }
                
                // Commit transaction
                $conn->commit();
                
                $success_message = "Professional information updated successfully";
                
                // Refresh user data
                $refresh_query = "SELECT u.*, c.company_name, o.name as organization_name, c.membership_plan_id,
                                mp.name as membership_plan, mp.max_team_members, c.team_members_count,
                                cp.bio, cp.specializations, cp.years_experience, cp.education, cp.certifications, cp.languages,
                                cp.website, cp.social_linkedin, cp.social_twitter, cp.social_facebook, cp.is_featured, 
                                cp.is_verified, cp.verified_by, cp.verified_at, cp.banner_image, cp.profile_image
                                FROM users u 
                                LEFT JOIN consultants c ON u.id = c.user_id
                                LEFT JOIN organizations o ON u.organization_id = o.id
                                LEFT JOIN membership_plans mp ON c.membership_plan_id = mp.id
                                LEFT JOIN consultant_profiles cp ON u.id = cp.consultant_id
                                WHERE u.id = ?";
                $stmt = $conn->prepare($refresh_query);
                if (!$stmt) {
                    throw new Exception("Error preparing refresh query: " . $conn->error);
                }
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user_data = $result->fetch_assoc();
                
            } else {
                throw new Exception(implode("<br>", $validation_errors));
            }
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = "Error updating professional information: " . $e->getMessage();
        }
    }
    
    // Update just the profile picture (Ajax request)
    if (isset($_POST['update_profile_picture']) && isset($_FILES['profile_picture'])) {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Delete old profile picture if exists
            if (!empty($user_data['profile_picture']) && file_exists('../../uploads/' . $user_data['profile_picture'])) {
                unlink('../../uploads/' . $user_data['profile_picture']);
            }
            
            $upload_result = handle_user_file_upload(
                $user_id, 
                $_FILES['profile_picture'],
                'profile',
                [
                    'max_size' => 2 * 1024 * 1024, // 2MB
                    'allowed_types' => ['image/jpeg', 'image/png', 'image/gif'],
                    'filename_prefix' => 'profile_'
                ]
            );
            
            if ($upload_result['status']) {
                // Update database
                $update_query = "UPDATE users SET profile_picture = ? WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param('si', $upload_result['file_path'], $user_id);
                
                if ($stmt->execute()) {
                    // Update session
                    $_SESSION['profile_picture'] = $upload_result['file_path'];
                    
                    // Commit transaction
                    $conn->commit();
                    
                    // Success - redirect to refresh the page
                    header('Location: profile.php?success=profile_picture_updated');
                    exit;
                } else {
                    throw new Exception("Error updating profile picture in database");
                }
            } else {
                throw new Exception($upload_result['message']);
            }
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = "Error updating profile picture: " . $e->getMessage();
        }
    }
    
    // Password Update
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Skip password validation for OAuth users
        if ($user_data['auth_provider'] === 'google') {
            $validation_errors[] = "Password cannot be changed for Google-linked accounts";
        } else {
            // Validate inputs
            if (empty($current_password)) {
                $validation_errors[] = "Current password is required";
            }
            if (empty($new_password)) {
                $validation_errors[] = "New password is required";
            } elseif (strlen($new_password) < 8) {
                $validation_errors[] = "New password must be at least 8 characters long";
            }
            if ($new_password !== $confirm_password) {
                $validation_errors[] = "New passwords do not match";
            }
            
            // Verify current password
            if (empty($validation_errors)) {
                if (!password_verify($current_password, $user_data['password'])) {
                    $validation_errors[] = "Current password is incorrect";
                }
            }
            
            // Update password if no validation errors
            if (empty($validation_errors)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param('si', $hashed_password, $user_id);
                
                if ($stmt->execute()) {
                    $success_message = "Password updated successfully";
                } else {
                    $error_message = "Error updating password: " . $conn->error;
                }
                $stmt->close();
            } else {
                $error_message = implode("<br>", $validation_errors);
            }
        }
    }
    
    // Account link with Google
    if (isset($_POST['link_google'])) {
        // Redirect to Google OAuth consent screen
        $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'scope' => 'email profile',
            'redirect_uri' => $google_redirect_url,
            'response_type' => 'code',
            'client_id' => $google_client_id,
            'state' => base64_encode(json_encode([
                'action' => 'link',
                'user_id' => $user_id
            ]))
        ]);
        
        header('Location: ' . $auth_url);
        exit;
    }
    
    // Account unlink from Google
    if (isset($_POST['unlink_google'])) {
        // Only unlink if there's a password set
        if (empty($user_data['password']) || $user_data['password'] === '') {
            $error_message = "You must set a password first before unlinking your Google account";
        } else {
            $delete_query = "DELETE FROM oauth_tokens WHERE user_id = ? AND provider = 'google'";
            $stmt = $conn->prepare($delete_query);
            $stmt->bind_param('i', $user_id);
            
            if ($stmt->execute()) {
                $update_query = "UPDATE users SET auth_provider = 'local' WHERE id = ?";
                $stmt_update = $conn->prepare($update_query);
                $stmt_update->bind_param('i', $user_id);
                $stmt_update->execute();
                $stmt_update->close();
                
                $success_message = "Google account unlinked successfully";
                
                // Refresh user data
                $result = $conn->query("SELECT * FROM users WHERE id = $user_id");
                $user_data = $result->fetch_assoc();
            } else {
                $error_message = "Error unlinking Google account: " . $conn->error;
            }
            $stmt->close();
        }
    }
    
    // Organization Info Update
    if (isset($_POST['update_organization'])) {
        $organization_id = trim($_POST['organization_id']);
        $organization_name = trim($_POST['organization_name']);
        
        // Validate inputs
        if (empty($organization_id)) {
            $validation_errors[] = "Organization ID is required";
        }
        if (empty($organization_name)) {
            $validation_errors[] = "Organization name is required";
        }
        
        // Update organization if no validation errors
        if (empty($validation_errors)) {
            // First check if organization exists
            $check_query = "SELECT id FROM organizations WHERE id = ? AND deleted_at IS NULL";
            $stmt = $conn->prepare($check_query);
            $stmt->bind_param('i', $organization_id);
            $stmt->execute();
            $check_result = $stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                // Update organization name
                $update_org_query = "UPDATE organizations SET name = ? WHERE id = ?";
                $stmt = $conn->prepare($update_org_query);
                $stmt->bind_param('si', $organization_name, $organization_id);
                $stmt->execute();
                
                // Update user's organization ID
                $update_user_query = "UPDATE users SET organization_id = ? WHERE id = ?";
                $stmt = $conn->prepare($update_user_query);
                $stmt->bind_param('ii', $organization_id, $user_id);
                
                if ($stmt->execute()) {
                    $success_message = "Organization information updated successfully";
                    
                    // Update session variables
                    $_SESSION["organization_id"] = $organization_id;
                    
                    // Refresh user data
                    $result = $conn->query("SELECT u.*, c.company_name, o.name as organization_name, c.membership_plan_id,
                                            mp.name as membership_plan, mp.max_team_members, c.team_members_count 
                                            FROM users u 
                                            LEFT JOIN consultants c ON u.id = c.user_id
                                            LEFT JOIN organizations o ON u.organization_id = o.id
                                            LEFT JOIN membership_plans mp ON c.membership_plan_id = mp.id
                                            WHERE u.id = $user_id");
                    $user_data = $result->fetch_assoc();
                } else {
                    $error_message = "Error updating organization information: " . $conn->error;
                }
            } else {
                $error_message = "Organization with this ID does not exist";
            }
            $stmt->close();
        } else {
            $error_message = implode("<br>", $validation_errors);
        }
    }
    
    // Company Info Update
    if (isset($_POST['update_company'])) {
        $company_name = trim($_POST['company_name']);
        
        // Validate inputs
        if (empty($company_name)) {
            $validation_errors[] = "Company name is required";
        }
        
        // Update company info if no validation errors
        if (empty($validation_errors)) {
            // Check if consultant record exists
            $check_query = "SELECT user_id FROM consultants WHERE user_id = ?";
            $stmt = $conn->prepare($check_query);
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $check_result = $stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                // Update consultant record
                $update_query = "UPDATE consultants SET company_name = ? WHERE user_id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param('si', $company_name, $user_id);
                
                if ($stmt->execute()) {
                    $success_message = "Company information updated successfully";
                    
                    // Refresh user data
                    $result = $conn->query("SELECT u.*, c.company_name, o.name as organization_name, c.membership_plan_id,
                                           mp.name as membership_plan, mp.max_team_members, c.team_members_count 
                                           FROM users u 
                                           LEFT JOIN consultants c ON u.id = c.user_id
                                           LEFT JOIN organizations o ON u.organization_id = o.id
                                           LEFT JOIN membership_plans mp ON c.membership_plan_id = mp.id
                                           WHERE u.id = $user_id");
                    $user_data = $result->fetch_assoc();
                } else {
                    $error_message = "Error updating company information: " . $conn->error;
                }
            } else {
                $error_message = "Consultant record not found";
            }
            $stmt->close();
        } else {
            $error_message = implode("<br>", $validation_errors);
        }
    }

    // Verification documents upload
    if (isset($_POST['upload_verification'])) {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            $document_types = ['business_license', 'id_proof', 'certifications', 'additional_docs'];
            $upload_success = true;
            
            foreach ($document_types as $doc_type) {
                if (isset($_FILES[$doc_type]) && $_FILES[$doc_type]['error'] == 0) {
                    $upload_result = handle_user_file_upload(
                        $user_id,
                        $_FILES[$doc_type],
                        'verification',
                        [
                            'max_size' => 5 * 1024 * 1024, // 5MB
                            'allowed_types' => ['application/pdf', 'image/jpeg', 'image/png'],
                            'filename_prefix' => 'verification_' . $doc_type . '_'
                        ]
                    );
                    
                    if ($upload_result['status']) {
                        // Insert into consultant_verifications table
                        $insert_query = "INSERT INTO consultant_verifications 
                                       (consultant_id, document_type, document_path) 
                                       VALUES (?, ?, ?)";
                        $stmt = $conn->prepare($insert_query);
                        $stmt->bind_param('iss', $user_id, $doc_type, $upload_result['file_path']);
                        
                        if (!$stmt->execute()) {
                            throw new Exception("Error saving verification document: " . $stmt->error);
                        }
                    } else {
                        $upload_success = false;
                        throw new Exception($upload_result['message']);
                    }
                }
            }
            
            if ($upload_success) {
                // Commit transaction
                $conn->commit();
                $success_message = "Verification documents uploaded successfully. They will be reviewed by our team.";
            }
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = "Error uploading verification documents: " . $e->getMessage();
        }
    }
}

// Check for success/error messages from redirect
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'google_linked':
            $success_message = "Google account linked successfully";
            break;
        case 'profile_picture_updated':
            $success_message = "Profile picture updated successfully";
            break;
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'google_already_linked':
            $error_message = "This Google account is already linked to another user";
            break;
        case 'link_failed':
            $error_message = "Failed to link Google account. Please try again.";
            break;
        case 'invalid_state':
            $error_message = "Invalid request. Please try again.";
            break;
        case 'unknown':
            $error_message = "An unknown error occurred. Please try again.";
            break;
    }
}

// Get profile picture URL
$profile_img = '../../assets/images/default-profile.jpg';
if (!empty($user_data['profile_picture'])) {
    if (strpos($user_data['profile_picture'], 'users/') === 0) {
        // New path structure
        $profile_path = '../../uploads/' . $user_data['profile_picture'];
        if (file_exists($profile_path)) {
            $profile_img = $profile_path;
        }
    } else {
        // Legacy path structure
        $profile_path = '../../uploads/profiles/' . $user_data['profile_picture'];
        if (file_exists($profile_path)) {
            $profile_img = $profile_path;
        } else {
            $profile_path = '../../uploads/profile/' . $user_data['profile_picture'];
            if (file_exists($profile_path)) {
                $profile_img = $profile_path;
            }
        }
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>My Profile</h1>
            <p>Manage your personal information and account settings</p>
        </div>
    </div>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <div class="profile-container">
        <div class="profile-sidebar">
            <div class="profile-image-container">
                <img src="<?php echo $profile_img; ?>" alt="Profile Picture" class="profile-image" id="profile-image-preview">
                <div class="change-photo-overlay">
                    <label for="profile-picture-upload" class="change-photo-btn">
                        <i class="fas fa-camera"></i>
                    </label>
                </div>
            </div>
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></h2>
                <p class="role"><?php echo ucfirst($user_data['user_type']); ?></p>
                <?php if (!empty($user_data['organization_name'])): ?>
                <p class="organization"><?php echo htmlspecialchars($user_data['organization_name']); ?></p>
                <?php endif; ?>
                <?php if (!empty($user_data['membership_plan'])): ?>
                <p class="membership">Plan: <?php echo htmlspecialchars($user_data['membership_plan']); ?> 
                (<?php echo $user_data['team_members_count']; ?>/<?php echo $user_data['max_team_members']; ?> team members)</p>
                <?php endif; ?>
                <div class="account-status">
                    <span class="status-badge <?php echo $user_data['status'] === 'active' ? 'active' : 'inactive'; ?>">
                        <i class="fas fa-circle"></i> <?php echo ucfirst($user_data['status']); ?>
                    </span>
                </div>
                <?php if (!empty($user_data['is_verified']) && $user_data['is_verified'] == 1): ?>
                <div class="verification-status">
                    <span class="verified-badge">
                        <i class="fas fa-check-circle"></i> Verified Consultant
                    </span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="profile-tabs">
                <button class="tab-btn active" data-tab="personal-info"><i class="fas fa-user"></i> Personal Info</button>
                <button class="tab-btn" data-tab="professional-info"><i class="fas fa-briefcase"></i> Professional Info</button>
                <button class="tab-btn" data-tab="security"><i class="fas fa-lock"></i> Security</button>
                <button class="tab-btn" data-tab="organization-info"><i class="fas fa-building"></i> Organization</button>
                <button class="tab-btn" data-tab="connected-accounts"><i class="fas fa-link"></i> Connected Accounts</button>
            </div>
        </div>
        
        <div class="profile-content">
            <!-- Personal Info Tab -->
            <div class="tab-content active" id="personal-info">
                <div class="section-header">
                    <h3>Personal Information</h3>
                    <p>Update your personal details</p>
                </div>
                
                <form action="profile.php" method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" 
                                value="<?php echo htmlspecialchars($user_data['first_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" 
                                value="<?php echo htmlspecialchars($user_data['last_name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" 
                            value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="profile_picture">Profile Picture</label>
                        <div class="file-upload-container">
                            <input type="file" id="profile_picture" name="profile_picture" class="form-control file-upload" accept="image/jpeg, image/png, image/gif">
                            <div class="file-upload-text">
                                <i class="fas fa-upload"></i> Choose a file...
                            </div>
                        </div>
                        <small class="form-text">Maximum size: 2MB. Allowed types: JPG, PNG, GIF</small>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="submit" name="update_profile" class="btn primary-btn">Save Changes</button>
                    </div>
                </form>
            </div>
            
            <!-- Professional Info Tab -->
            <div class="tab-content" id="professional-info">
                <div class="section-header">
                    <h3>Professional Information</h3>
                    <p>Showcase your expertise and professional background</p>
                </div>
                
                <?php if ($user_data['user_type'] === 'consultant'): ?>
                <form action="profile.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="bio">Professional Bio</label>
                        <textarea id="bio" name="bio" class="form-control" rows="4"><?php echo htmlspecialchars($user_data['bio'] ?? ''); ?></textarea>
                        <small class="form-text">Describe your professional background and expertise (500 characters max)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="specializations">Specializations</label>
                        <textarea id="specializations" name="specializations" class="form-control" rows="3"><?php echo htmlspecialchars($user_data['specializations'] ?? ''); ?></textarea>
                        <small class="form-text">List your areas of expertise, separated by commas</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="years_experience">Years of Experience</label>
                            <input type="number" id="years_experience" name="years_experience" class="form-control" 
                                value="<?php echo htmlspecialchars($user_data['years_experience'] ?? 0); ?>" min="0" max="50">
                        </div>
                        <div class="form-group">
                            <label for="languages">Languages</label>
                            <input type="text" id="languages" name="languages" class="form-control" 
                                value="<?php echo htmlspecialchars($user_data['languages'] ?? ''); ?>">
                            <small class="form-text">E.g., English (Fluent), Spanish (Intermediate)</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="education">Education</label>
                        <textarea id="education" name="education" class="form-control" rows="3"><?php echo htmlspecialchars($user_data['education'] ?? ''); ?></textarea>
                        <small class="form-text">List your educational qualifications (degrees, institutions, years)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="certifications">Certifications</label>
                        <textarea id="certifications" name="certifications" class="form-control" rows="3"><?php echo htmlspecialchars($user_data['certifications'] ?? ''); ?></textarea>
                        <small class="form-text">List relevant professional certifications or accreditations</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="banner_image">Profile Banner Image</label>
                        <div class="file-upload-container">
                            <input type="file" id="banner_image" name="banner_image" class="form-control file-upload" accept="image/jpeg, image/png, image/gif">
                            <div class="file-upload-text">
                                <i class="fas fa-upload"></i> Choose a file...
                            </div>
                        </div>
                        <small class="form-text">Maximum size: 5MB. Recommended size: 1200x300px. This image will appear at the top of your public profile.</small>
                    </div>
                    
                    <div class="section-divider"></div>
                    
                    <div class="section-subheader">
                        <h4>Online Presence</h4>
                        <p>Share your professional websites and social media profiles</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="website">Website</label>
                        <input type="url" id="website" name="website" class="form-control" 
                            value="<?php echo htmlspecialchars($user_data['website'] ?? ''); ?>" placeholder="https://example.com">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="social_linkedin">LinkedIn</label>
                            <input type="url" id="social_linkedin" name="social_linkedin" class="form-control" 
                                value="<?php echo htmlspecialchars($user_data['social_linkedin'] ?? ''); ?>" placeholder="https://linkedin.com/in/username">
                        </div>
                        <div class="form-group">
                            <label for="social_twitter">Twitter</label>
                            <input type="url" id="social_twitter" name="social_twitter" class="form-control" 
                                value="<?php echo htmlspecialchars($user_data['social_twitter'] ?? ''); ?>" placeholder="https://twitter.com/username">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="social_facebook">Facebook</label>
                        <input type="url" id="social_facebook" name="social_facebook" class="form-control" 
                            value="<?php echo htmlspecialchars($user_data['social_facebook'] ?? ''); ?>" placeholder="https://facebook.com/username">
                    </div>
                    
                    <?php if (isset($user_data['is_verified']) && $user_data['is_verified'] == 1): ?>
                    <div class="verification-info">
                        <div class="verification-status">
                            <i class="fas fa-check-circle"></i>
                            <span>Your profile is verified</span>
                        </div>
                        <p>Verified on: <?php echo date('F j, Y', strtotime($user_data['verified_at'])); ?></p>
                    </div>
                    <?php else: ?>
                    <div class="verification-info">
                        <div class="verification-status not-verified">
                            <i class="fas fa-exclamation-circle"></i>
                            <span>Your profile is not verified</span>
                        </div>
                        <p>Upload your verification documents to get your profile verified.</p>
                        
                        <div class="verification-documents">
                            <div class="form-group">
                                <label>Business License</label>
                                <div class="file-upload-container">
                                    <input type="file" name="business_license" class="form-control file-upload" accept=".pdf,.jpg,.jpeg,.png">
                                    <div class="file-upload-text">
                                        <i class="fas fa-upload"></i> Choose a file...
                                    </div>
                                </div>
                                <small class="form-text">Upload your business license or registration document</small>
                            </div>
                            
                            <div class="form-group">
                                <label>ID Proof</label>
                                <div class="file-upload-container">
                                    <input type="file" name="id_proof" class="form-control file-upload" accept=".pdf,.jpg,.jpeg,.png">
                                    <div class="file-upload-text">
                                        <i class="fas fa-upload"></i> Choose a file...
                                    </div>
                                </div>
                                <small class="form-text">Upload a government-issued ID (passport, driver's license)</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Professional Certifications</label>
                                <div class="file-upload-container">
                                    <input type="file" name="certifications" class="form-control file-upload" accept=".pdf,.jpg,.jpeg,.png">
                                    <div class="file-upload-text">
                                        <i class="fas fa-upload"></i> Choose a file...
                                    </div>
                                </div>
                                <small class="form-text">Upload your professional certifications or accreditations</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Additional Documents</label>
                                <div class="file-upload-container">
                                    <input type="file" name="additional_docs" class="form-control file-upload" accept=".pdf,.jpg,.jpeg,.png">
                                    <div class="file-upload-text">
                                        <i class="fas fa-upload"></i> Choose a file...
                                    </div>
                                </div>
                                <small class="form-text">Any additional documents that support your verification</small>
                            </div>
                            
                            <div class="form-buttons">
                                <button type="submit" name="upload_verification" class="btn primary-btn">Upload Verification Documents</button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-buttons">
                        <button type="submit" name="update_professional_info" class="btn primary-btn">Save Professional Info</button>
                    </div>
                </form>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Professional information is only available for consultants.
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Security Tab -->
            <div class="tab-content" id="security">
                <div class="section-header">
                    <h3>Security Settings</h3>
                    <p>Manage your account password</p>
                </div>
                
                <?php if ($user_data['auth_provider'] === 'google'): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> You're signed in with Google. Set a password below to be able to log in directly.
                    </div>
                <?php endif; ?>
                
                <form action="profile.php" method="POST">
                    <?php if ($user_data['auth_provider'] !== 'google' || !empty($user_data['password'])): ?>
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" required>
                        <small class="form-text">Minimum 8 characters, include numbers and special characters for better security</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="submit" name="update_password" class="btn primary-btn">Update Password</button>
                    </div>
                </form>
            </div>
            
            <!-- Organization Info Tab -->
            <div class="tab-content" id="organization-info">
                <div class="section-header">
                    <h3>Organization Information</h3>
                    <p>Your organization details</p>
                </div>
                
                <?php if ($user_data['user_type'] === 'consultant'): ?>
                <form action="profile.php" method="POST">
                    <div class="form-group">
                        <label for="organization_name">Organization Name</label>
                        <input type="text" id="organization_name" name="organization_name" class="form-control" 
                            value="<?php echo htmlspecialchars($user_data['organization_name'] ?? ''); ?>" <?php echo $user_data['user_type'] !== 'admin' ? 'readonly' : ''; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label for="company_name">Company Name</label>
                        <input type="text" id="company_name" name="company_name" class="form-control" 
                            value="<?php echo htmlspecialchars($user_data['company_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Membership Plan</label>
                        <div class="membership-details">
                            <div class="plan-name"><?php echo htmlspecialchars($user_data['membership_plan'] ?? 'No plan'); ?></div>
                            <div class="team-count">
                                <span class="count-label">Team Members:</span>
                                <span class="count-value"><?php echo ($user_data['team_members_count'] ?? '0') . ' / ' . ($user_data['max_team_members'] ?? '0'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($user_data['user_type'] === 'consultant'): ?>
                    <div class="form-buttons">
                        <button type="submit" name="update_company" class="btn primary-btn">Save Company Details</button>
                    </div>
                    <?php endif; ?>
                </form>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Organization information is only available for consultants.
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Connected Accounts Tab -->
            <div class="tab-content" id="connected-accounts">
                <div class="section-header">
                    <h3>Connected Accounts</h3>
                    <p>Manage connections to other services</p>
                </div>
                
                <div class="connected-account-item">
                    <div class="account-info">
                        <div class="account-logo google">
                            <i class="fab fa-google"></i>
                        </div>
                        <div class="account-details">
                            <h4>Google</h4>
                            <p>
                                <?php if ($user_data['auth_provider'] === 'google'): ?>
                                    Connected to <?php echo htmlspecialchars($user_data['email']); ?>
                                <?php else: ?>
                                    Not connected
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="account-actions">
                        <form action="profile.php" method="POST">
                            <?php if ($user_data['auth_provider'] === 'google'): ?>
                                <button type="submit" name="unlink_google" class="btn outline-btn">Disconnect</button>
                            <?php else: ?>
                                <button type="submit" name="link_google" class="btn outline-btn">Connect</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden form for profile picture upload -->
<form id="profile-picture-form" action="profile.php" method="POST" enctype="multipart/form-data" style="display: none;">
    <input type="file" id="profile-picture-upload" name="profile_picture" accept="image/jpeg, image/png, image/gif">
    <input type="hidden" name="update_profile_picture" value="1">
</form>

<style>
:root {
    --primary-color: #042167;
    --secondary-color: #858796;
    --success-color: #1cc88a;
    --danger-color: #e74a3b;
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

.alert-info {
    background-color: rgba(78, 115, 223, 0.1);
    color: #4e73df;
    border: 1px solid rgba(78, 115, 223, 0.2);
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}

.profile-container {
    display: flex;
    gap: 30px;
    background: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.profile-sidebar {
    width: 280px;
    border-right: 1px solid var(--border-color);
    padding: 30px 0;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.profile-image-container {
    position: relative;
    width: 150px;
    height: 150px;
    margin-bottom: 20px;
}

.profile-image {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--light-color);
}

.change-photo-overlay {
    position: absolute;
    bottom: 0;
    right: 0;
    background: var(--primary-color);
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    justify-content: center;
    align-items: center;
    cursor: pointer;
    color: white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.change-photo-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    cursor: pointer;
}

.hidden-file-input {
    display: none;
}

.profile-info {
    text-align: center;
    margin-bottom: 30px;
}

.profile-info h2 {
    margin: 0;
    font-size: 1.4rem;
    color: var(--dark-color);
}

.profile-info .role {
    margin: 5px 0;
    color: var(--secondary-color);
    font-size: 0.9rem;
}

.profile-info .organization {
    margin: 5px 0;
    color: var(--primary-color);
    font-size: 0.9rem;
    font-weight: 500;
}

.profile-info .membership {
    margin: 5px 0;
    color: var(--dark-color);
    font-size: 0.8rem;
    background-color: rgba(4, 33, 103, 0.1);
    padding: 4px 8px;
    border-radius: 12px;
    display: inline-block;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.status-badge.active {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.status-badge.inactive {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.status-badge i {
    font-size: 8px;
}

.profile-tabs {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 5px;
    padding: 0 15px;
}

.tab-btn {
    text-align: left;
    padding: 12px 15px;
    background: none;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    color: var(--dark-color);
    display: flex;
    align-items: center;
    gap: 10px;
    transition: background-color 0.2s;
}

.tab-btn:hover {
    background-color: var(--light-color);
}

.tab-btn.active {
    background-color: var(--primary-color);
    color: white;
}

.profile-content {
    flex: 1;
    padding: 30px;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.section-header {
    margin-bottom: 25px;
}

.section-header h3 {
    margin: 0 0 5px;
    color: var(--primary-color);
    font-size: 1.2rem;
}

.section-header p {
    margin: 0;
    color: var(--secondary-color);
    font-size: 0.9rem;
}

.form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.form-group {
    flex: 1;
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: var(--dark-color);
}

.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(4, 33, 103, 0.1);
}

.form-text {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    color: var(--secondary-color);
}

.file-upload-container {
    position: relative;
    overflow: hidden;
}

.file-upload {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    cursor: pointer;
    z-index: 2;
}

.file-upload-text {
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    text-align: center;
    color: var(--secondary-color);
    position: relative;
    z-index: 1;
}

.form-buttons {
    display: flex;
    justify-content: flex-start;
    gap: 10px;
    margin-top: 25px;
}

.btn {
    padding: 10px 20px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
    transition: background-color 0.2s;
}

.primary-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
}

.primary-btn:hover {
    background-color: #031c56;
}

.outline-btn {
    background-color: white;
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
}

.outline-btn:hover {
    background-color: var(--light-color);
}

.connected-account-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border: 1px solid var(--border-color);
    border-radius: 5px;
    margin-bottom: 15px;
}

.account-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.account-logo {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: white;
}

.account-logo.google {
    background-color: #DB4437;
}

.account-details h4 {
    margin: 0;
    font-size: 1rem;
}

.account-details p {
    margin: 3px 0 0;
    font-size: 0.9rem;
    color: var(--secondary-color);
}

.membership-details {
    background-color: #f8f9fc;
    border: 1px solid var(--border-color);
    border-radius: 5px;
    padding: 15px;
}

.plan-name {
    font-weight: 600;
    font-size: 1.1rem;
    color: var(--primary-color);
    margin-bottom: 10px;
}

.team-count {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background-color: white;
    border-radius: 4px;
    padding: 8px 12px;
    border: 1px solid var(--border-color);
}

.count-label {
    color: var(--dark-color);
}

.count-value {
    font-weight: 600;
}

.verification-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
    margin-top: 8px;
}

.verification-badge i {
    font-size: 12px;
}

.verification-info {
    margin-top: 20px;
    padding: 15px;
    background-color: #f8f9fc;
    border: 1px solid var(--border-color);
    border-radius: 4px;
}

.verification-status {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 500;
    color: var(--success-color);
}

.verification-status.not-verified {
    color: var(--secondary-color);
}

.verification-status i {
    font-size: 18px;
}

.verification-info p {
    margin: 10px 0 0;
    font-size: 14px;
    color: var(--secondary-color);
}

.verification-info a {
    color: var(--primary-color);
    text-decoration: none;
}

.verification-info a:hover {
    text-decoration: underline;
}

.section-divider {
    height: 1px;
    background-color: var(--border-color);
    margin: 30px 0;
}

.section-subheader {
    margin-bottom: 20px;
}

.section-subheader h4 {
    margin: 0 0 5px;
    color: var(--primary-color);
    font-size: 1.1rem;
}

.section-subheader p {
    margin: 0;
    color: var(--secondary-color);
    font-size: 0.9rem;
}

.verification-documents {
    margin-top: 20px;
    padding: 20px;
    background-color: #f8f9fc;
    border: 1px solid var(--border-color);
    border-radius: 4px;
}

.verification-documents .form-group {
    margin-bottom: 20px;
}

.verification-documents .file-upload-container {
    margin-top: 5px;
}

.verification-documents .form-text {
    margin-top: 5px;
    color: var(--secondary-color);
}

.verification-documents .form-buttons {
    margin-top: 30px;
}

@media (max-width: 768px) {
    .profile-container {
        flex-direction: column;
    }
    
    .profile-sidebar {
        width: 100%;
        border-right: none;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 20px;
    }
    
    .profile-tabs {
        flex-direction: row;
        overflow-x: auto;
        padding: 10px 0;
    }
    
    .form-row {
        flex-direction: column;
        gap: 0;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching functionality
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons and contents
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Add active class to clicked button
            this.classList.add('active');
            
            // Show corresponding content
            const tabId = this.getAttribute('data-tab');
            document.getElementById(tabId).classList.add('active');
        });
    });
    
    // Profile picture change via the camera icon
    const profilePicForm = document.getElementById('profile-picture-form');
    const profilePicUpload = document.getElementById('profile-picture-upload');
    const profileImagePreview = document.getElementById('profile-image-preview');
    const changePhotoBtn = document.querySelector('.change-photo-btn');
    
    // Prevent event bubbling and default behavior
    changePhotoBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        profilePicUpload.click();
    });
    
    // Handle file selection
    profilePicUpload.addEventListener('change', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        if (this.files && this.files[0]) {
            // First show a preview
            const reader = new FileReader();
            
            reader.onload = function(e) {
                profileImagePreview.src = e.target.result;
                
                // Submit the form to update the profile picture
                profilePicForm.submit();
            }
            
            reader.readAsDataURL(this.files[0]);
        }
    });
    
    // Handle the file upload in the main form
    const fileUpload = document.getElementById('profile_picture');
    const fileText = document.querySelector('.file-upload-text');
    
    fileUpload.addEventListener('change', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        if (this.files && this.files[0]) {
            fileText.innerHTML = '<i class="fas fa-file"></i> ' + this.files[0].name;
        } else {
            fileText.innerHTML = '<i class="fas fa-upload"></i> Choose a file...';
        }
    });

    // Handle file upload text display for verification documents
    const verificationFileInputs = document.querySelectorAll('.verification-documents .file-upload');
    
    verificationFileInputs.forEach(input => {
        const fileText = input.nextElementSibling;
        
        input.addEventListener('change', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (this.files && this.files[0]) {
                fileText.innerHTML = '<i class="fas fa-file"></i> ' + this.files[0].name;
            } else {
                fileText.innerHTML = '<i class="fas fa-upload"></i> Choose a file...';
            }
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>
