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

// Create user upload directories if they don't exist
$user_upload_dir = '../../uploads/users/' . $user_id;
$profile_dir = $user_upload_dir . '/profile';
$documents_dir = $user_upload_dir . '/documents';

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

// Update query to include applicant data
$query = "SELECT u.*, a.passport_number, a.nationality, a.date_of_birth, a.place_of_birth, 
          a.marital_status, a.current_country, a.current_visa_status, a.visa_expiry_date, 
          a.target_country, a.immigration_purpose, a.education_level, a.occupation, 
          a.english_proficiency, a.has_previous_refusals, a.refusal_details, 
          a.has_family_in_target_country, a.family_relation_details, a.net_worth, 
          a.documents_folder_url, a.application_stage, a.notes, oauth.provider, oauth.provider_user_id
          FROM users u 
          LEFT JOIN applicants a ON u.id = a.user_id
          LEFT JOIN oauth_tokens oauth ON u.id = oauth.user_id AND oauth.provider = 'google'
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
        $phone = trim($_POST['phone']);
        
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
            $update_query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, profile_picture = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param('sssssi', $first_name, $last_name, $email, $phone, $profile_picture, $user_id);
            
            if ($stmt->execute()) {
                $success_message = "Profile updated successfully";
                
                // Update session variables
                $_SESSION["first_name"] = $first_name;
                $_SESSION["last_name"] = $last_name;
                $_SESSION["email"] = $email;
                $_SESSION["profile_picture"] = $profile_picture;
                
                // Refresh user data
                $result = $conn->query("SELECT u.*, a.passport_number, a.nationality, a.date_of_birth, a.place_of_birth, 
                           a.marital_status, a.current_country, a.current_visa_status, a.visa_expiry_date, 
                           a.target_country, a.immigration_purpose, a.education_level, a.occupation, 
                           a.english_proficiency, a.has_previous_refusals, a.refusal_details, 
                           a.has_family_in_target_country, a.family_relation_details, a.net_worth, 
                           a.documents_folder_url, a.application_stage, a.notes
                           FROM users u 
                           LEFT JOIN applicants a ON u.id = a.user_id
                           WHERE u.id = $user_id");
                $user_data = $result->fetch_assoc();
            } else {
                $error_message = "Error updating profile: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error_message = implode("<br>", $validation_errors);
        }
    }
    
    // Update applicant info
    if (isset($_POST['update_applicant_info'])) {
        $passport_number = trim($_POST['passport_number']);
        $nationality = trim($_POST['nationality']);
        $date_of_birth = trim($_POST['date_of_birth']);
        $place_of_birth = trim($_POST['place_of_birth']);
        $marital_status = trim($_POST['marital_status']);
        $current_country = trim($_POST['current_country']);
        $current_visa_status = trim($_POST['current_visa_status']);
        $visa_expiry_date = !empty($_POST['visa_expiry_date']) ? $_POST['visa_expiry_date'] : null;
        $target_country = trim($_POST['target_country']);
        $immigration_purpose = trim($_POST['immigration_purpose']);
        $education_level = trim($_POST['education_level']);
        $occupation = trim($_POST['occupation']);
        $english_proficiency = trim($_POST['english_proficiency']);
        $has_previous_refusals = isset($_POST['has_previous_refusals']) ? 1 : 0;
        $refusal_details = trim($_POST['refusal_details']);
        $has_family_in_target_country = isset($_POST['has_family_in_target_country']) ? 1 : 0;
        $family_relation_details = trim($_POST['family_relation_details']);
        $net_worth = !empty($_POST['net_worth']) ? $_POST['net_worth'] : null;
        
        // Check if applicant record exists
        $check_query = "SELECT user_id FROM applicants WHERE user_id = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $check_result = $stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Update existing record
            $update_query = "UPDATE applicants SET 
                            passport_number = ?, 
                            nationality = ?, 
                            date_of_birth = ?, 
                            place_of_birth = ?, 
                            marital_status = ?, 
                            current_country = ?, 
                            current_visa_status = ?, 
                            visa_expiry_date = ?, 
                            target_country = ?, 
                            immigration_purpose = ?, 
                            education_level = ?, 
                            occupation = ?, 
                            english_proficiency = ?, 
                            has_previous_refusals = ?, 
                            refusal_details = ?, 
                            has_family_in_target_country = ?, 
                            family_relation_details = ?, 
                            net_worth = ?
                            WHERE user_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param('sssssssssssssisiidi', 
                                $passport_number, $nationality, $date_of_birth, $place_of_birth, 
                                $marital_status, $current_country, $current_visa_status, $visa_expiry_date, 
                                $target_country, $immigration_purpose, $education_level, $occupation, 
                                $english_proficiency, $has_previous_refusals, $refusal_details, 
                                $has_family_in_target_country, $family_relation_details, $net_worth, $user_id);
        } else {
            // Insert new record
            $insert_query = "INSERT INTO applicants 
                            (user_id, passport_number, nationality, date_of_birth, place_of_birth, 
                             marital_status, current_country, current_visa_status, visa_expiry_date, 
                             target_country, immigration_purpose, education_level, occupation, 
                             english_proficiency, has_previous_refusals, refusal_details, 
                             has_family_in_target_country, family_relation_details, net_worth)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param('isssssssssssssisisi', 
                              $user_id, $passport_number, $nationality, $date_of_birth, $place_of_birth, 
                              $marital_status, $current_country, $current_visa_status, $visa_expiry_date, 
                              $target_country, $immigration_purpose, $education_level, $occupation, 
                              $english_proficiency, $has_previous_refusals, $refusal_details, 
                              $has_family_in_target_country, $family_relation_details, $net_worth);
        }
        
        if ($stmt->execute()) {
            $success_message = "Applicant information updated successfully";
            
            // Refresh user data
            $result = $conn->query("SELECT u.*, a.passport_number, a.nationality, a.date_of_birth, a.place_of_birth, 
                           a.marital_status, a.current_country, a.current_visa_status, a.visa_expiry_date, 
                           a.target_country, a.immigration_purpose, a.education_level, a.occupation, 
                           a.english_proficiency, a.has_previous_refusals, a.refusal_details, 
                           a.has_family_in_target_country, a.family_relation_details, a.net_worth, 
                           a.documents_folder_url, a.application_stage, a.notes
                           FROM users u 
                           LEFT JOIN applicants a ON u.id = a.user_id
                           WHERE u.id = $user_id");
            $user_data = $result->fetch_assoc();
        } else {
            $error_message = "Error updating applicant information: " . $conn->error;
        }
        $stmt->close();
    }
    
    // Update just the profile picture (Ajax request)
    if (isset($_POST['update_profile_picture']) && isset($_FILES['profile_picture'])) {
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
                
                // Success - redirect to refresh the page
                header('Location: profile.php?success=profile_picture_updated');
                exit;
            } else {
                $validation_errors[] = "Error updating profile picture in database";
            }
            $stmt->close();
        } else {
            $validation_errors[] = $upload_result['message'];
        }
        
        if (!empty($validation_errors)) {
            $error_message = implode("<br>", $validation_errors);
        }
    }
    
    // Password Update
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Skip password validation for OAuth users
        if (isset($user_data['auth_provider']) && $user_data['auth_provider'] === 'google') {
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
                <?php if (!empty($user_data['application_stage'])): ?>
                <p class="application-stage">Application Stage: <?php echo str_replace('_', ' ', ucfirst($user_data['application_stage'])); ?></p>
                <?php endif; ?>
                <div class="account-status">
                    <span class="status-badge <?php echo $user_data['status'] === 'active' ? 'active' : 'inactive'; ?>">
                        <i class="fas fa-circle"></i> <?php echo ucfirst($user_data['status']); ?>
                    </span>
                </div>
            </div>
            
            <div class="profile-tabs">
                <button class="tab-btn active" data-tab="personal-info"><i class="fas fa-user"></i> Personal Info</button>
                <button class="tab-btn" data-tab="immigration-info"><i class="fas fa-passport"></i> Immigration Info</button>
                <button class="tab-btn" data-tab="security"><i class="fas fa-lock"></i> Security</button>
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
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" 
                            value="<?php echo htmlspecialchars($user_data['phone']); ?>">
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
            
            <!-- Immigration Info Tab -->
            <div class="tab-content" id="immigration-info">
                <div class="section-header">
                    <h3>Immigration Information</h3>
                    <p>Your immigration details for consultants to review</p>
                </div>
                
                <form action="profile.php" method="POST">
                    <div class="section-subheader">
                        <h4>Personal Details</h4>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="passport_number">Passport Number</label>
                            <input type="text" id="passport_number" name="passport_number" class="form-control" 
                                value="<?php echo htmlspecialchars($user_data['passport_number'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="nationality">Nationality</label>
                            <input type="text" id="nationality" name="nationality" class="form-control" 
                                value="<?php echo htmlspecialchars($user_data['nationality'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" 
                                value="<?php echo htmlspecialchars($user_data['date_of_birth'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="place_of_birth">Place of Birth</label>
                            <input type="text" id="place_of_birth" name="place_of_birth" class="form-control" 
                                value="<?php echo htmlspecialchars($user_data['place_of_birth'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="marital_status">Marital Status</label>
                        <select id="marital_status" name="marital_status" class="form-control">
                            <option value="">Select one</option>
                            <option value="single" <?php if (isset($user_data['marital_status']) && $user_data['marital_status'] == 'single') echo 'selected'; ?>>Single</option>
                            <option value="married" <?php if (isset($user_data['marital_status']) && $user_data['marital_status'] == 'married') echo 'selected'; ?>>Married</option>
                            <option value="divorced" <?php if (isset($user_data['marital_status']) && $user_data['marital_status'] == 'divorced') echo 'selected'; ?>>Divorced</option>
                            <option value="widowed" <?php if (isset($user_data['marital_status']) && $user_data['marital_status'] == 'widowed') echo 'selected'; ?>>Widowed</option>
                        </select>
                    </div>
                    
                    <div class="section-divider"></div>
                    
                    <div class="section-subheader">
                        <h4>Current Status</h4>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="current_country">Current Country of Residence</label>
                            <input type="text" id="current_country" name="current_country" class="form-control" 
                                value="<?php echo htmlspecialchars($user_data['current_country'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="current_visa_status">Current Visa Status</label>
                            <input type="text" id="current_visa_status" name="current_visa_status" class="form-control" 
                                value="<?php echo htmlspecialchars($user_data['current_visa_status'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="visa_expiry_date">Visa Expiry Date (if applicable)</label>
                        <input type="date" id="visa_expiry_date" name="visa_expiry_date" class="form-control" 
                            value="<?php echo htmlspecialchars($user_data['visa_expiry_date'] ?? ''); ?>">
                    </div>
                    
                    <div class="section-divider"></div>
                    
                    <div class="section-subheader">
                        <h4>Immigration Goals</h4>
                    </div>
                    
                    <div class="form-group">
                        <label for="target_country">Target Country</label>
                        <input type="text" id="target_country" name="target_country" class="form-control" 
                            value="<?php echo htmlspecialchars($user_data['target_country'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="immigration_purpose">Immigration Purpose</label>
                        <select id="immigration_purpose" name="immigration_purpose" class="form-control">
                            <option value="">Select one</option>
                            <option value="study" <?php if (isset($user_data['immigration_purpose']) && $user_data['immigration_purpose'] == 'study') echo 'selected'; ?>>Study</option>
                            <option value="work" <?php if (isset($user_data['immigration_purpose']) && $user_data['immigration_purpose'] == 'work') echo 'selected'; ?>>Work</option>
                            <option value="business" <?php if (isset($user_data['immigration_purpose']) && $user_data['immigration_purpose'] == 'business') echo 'selected'; ?>>Business</option>
                            <option value="family" <?php if (isset($user_data['immigration_purpose']) && $user_data['immigration_purpose'] == 'family') echo 'selected'; ?>>Family</option>
                            <option value="refugee" <?php if (isset($user_data['immigration_purpose']) && $user_data['immigration_purpose'] == 'refugee') echo 'selected'; ?>>Refugee</option>
                            <option value="permanent_residence" <?php if (isset($user_data['immigration_purpose']) && $user_data['immigration_purpose'] == 'permanent_residence') echo 'selected'; ?>>Permanent Residence</option>
                        </select>
                    </div>
                    
                    <div class="section-divider"></div>
                    
                    <div class="section-subheader">
                        <h4>Educational & Professional Background</h4>
                    </div>
                    
                    <div class="form-group">
                        <label for="education_level">Highest Education Level</label>
                        <select id="education_level" name="education_level" class="form-control">
                            <option value="">Select one</option>
                            <option value="high_school" <?php if (isset($user_data['education_level']) && $user_data['education_level'] == 'high_school') echo 'selected'; ?>>High School</option>
                            <option value="bachelors" <?php if (isset($user_data['education_level']) && $user_data['education_level'] == 'bachelors') echo 'selected'; ?>>Bachelor's Degree</option>
                            <option value="masters" <?php if (isset($user_data['education_level']) && $user_data['education_level'] == 'masters') echo 'selected'; ?>>Master's Degree</option>
                            <option value="phd" <?php if (isset($user_data['education_level']) && $user_data['education_level'] == 'phd') echo 'selected'; ?>>PhD</option>
                            <option value="other" <?php if (isset($user_data['education_level']) && $user_data['education_level'] == 'other') echo 'selected'; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="occupation">Current Occupation</label>
                        <input type="text" id="occupation" name="occupation" class="form-control" 
                            value="<?php echo htmlspecialchars($user_data['occupation'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="english_proficiency">English Proficiency</label>
                        <select id="english_proficiency" name="english_proficiency" class="form-control">
                            <option value="">Select one</option>
                            <option value="basic" <?php if (isset($user_data['english_proficiency']) && $user_data['english_proficiency'] == 'basic') echo 'selected'; ?>>Basic</option>
                            <option value="intermediate" <?php if (isset($user_data['english_proficiency']) && $user_data['english_proficiency'] == 'intermediate') echo 'selected'; ?>>Intermediate</option>
                            <option value="advanced" <?php if (isset($user_data['english_proficiency']) && $user_data['english_proficiency'] == 'advanced') echo 'selected'; ?>>Advanced</option>
                            <option value="native" <?php if (isset($user_data['english_proficiency']) && $user_data['english_proficiency'] == 'native') echo 'selected'; ?>>Native</option>
                            <option value="none" <?php if (isset($user_data['english_proficiency']) && $user_data['english_proficiency'] == 'none') echo 'selected'; ?>>None</option>
                        </select>
                    </div>
                    
                    <div class="section-divider"></div>
                    
                    <div class="section-subheader">
                        <h4>Additional Information</h4>
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <div class="checkbox-container">
                            <input type="checkbox" id="has_previous_refusals" name="has_previous_refusals" 
                                   <?php if (isset($user_data['has_previous_refusals']) && $user_data['has_previous_refusals']) echo 'checked'; ?>>
                            <label for="has_previous_refusals">Have you had any previous visa/immigration refusals?</label>
                        </div>
                    </div>
                    
                    <div class="form-group" id="refusal_details_group" style="<?php echo (isset($user_data['has_previous_refusals']) && $user_data['has_previous_refusals']) ? 'display:block;' : 'display:none;'; ?>">
                        <label for="refusal_details">Refusal Details</label>
                        <textarea id="refusal_details" name="refusal_details" class="form-control" rows="3"><?php echo htmlspecialchars($user_data['refusal_details'] ?? ''); ?></textarea>
                        <small class="form-text">Please provide details about any previous refusals, including dates, countries, and reasons.</small>
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <div class="checkbox-container">
                            <input type="checkbox" id="has_family_in_target_country" name="has_family_in_target_country" 
                                   <?php if (isset($user_data['has_family_in_target_country']) && $user_data['has_family_in_target_country']) echo 'checked'; ?>>
                            <label for="has_family_in_target_country">Do you have family members in your target country?</label>
                        </div>
                    </div>
                    
                    <div class="form-group" id="family_relation_details_group" style="<?php echo (isset($user_data['has_family_in_target_country']) && $user_data['has_family_in_target_country']) ? 'display:block;' : 'display:none;'; ?>">
                        <label for="family_relation_details">Family Relation Details</label>
                        <textarea id="family_relation_details" name="family_relation_details" class="form-control" rows="3"><?php echo htmlspecialchars($user_data['family_relation_details'] ?? ''); ?></textarea>
                        <small class="form-text">Please provide details about your family members in the target country, including their relationship to you and immigration status.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="net_worth">Net Worth (USD)</label>
                        <input type="number" id="net_worth" name="net_worth" class="form-control" step="0.01" 
                            value="<?php echo htmlspecialchars($user_data['net_worth'] ?? ''); ?>">
                        <small class="form-text">Optional: This information may be relevant for certain immigration pathways.</small>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="submit" name="update_applicant_info" class="btn primary-btn">Save Immigration Info</button>
                    </div>
                </form>
            </div>
            
            <!-- Security Tab -->
            <div class="tab-content" id="security">
                <div class="section-header">
                    <h3>Security Settings</h3>
                    <p>Manage your account password</p>
                </div>
                
                <?php if (isset($user_data['auth_provider']) && $user_data['auth_provider'] === 'google'): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> You're signed in with Google. Set a password below to be able to log in directly.
                    </div>
                <?php endif; ?>
                
                <form action="profile.php" method="POST">
                    <?php if (!isset($user_data['auth_provider']) || $user_data['auth_provider'] !== 'google' || !empty($user_data['password'])): ?>
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
                                <?php if (isset($user_data['auth_provider']) && $user_data['auth_provider'] === 'google'): ?>
                                    Connected to <?php echo htmlspecialchars($user_data['email']); ?>
                                <?php else: ?>
                                    Not connected
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="account-actions">
                        <form action="profile.php" method="POST">
                            <?php if (isset($user_data['auth_provider']) && $user_data['auth_provider'] === 'google'): ?>
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

.profile-info .application-stage {
    margin: 5px 0;
    color: var(--primary-color);
    font-size: 0.9rem;
    font-weight: 500;
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

.checkbox-group {
    margin-bottom: 15px;
}

.checkbox-container {
    display: flex;
    align-items: center;
    gap: 10px;
}

.checkbox-container input[type="checkbox"] {
    width: 18px;
    height: 18px;
}

.checkbox-container label {
    margin: 0;
    font-weight: 500;
    color: var(--dark-color);
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
// Tab switching functionality
document.addEventListener('DOMContentLoaded', function() {
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
    
    // Toggle display of refusal details when checkbox is checked
    const hasRefusalsCheckbox = document.getElementById('has_previous_refusals');
    const refusalDetailsGroup = document.getElementById('refusal_details_group');
    
    if (hasRefusalsCheckbox && refusalDetailsGroup) {
        hasRefusalsCheckbox.addEventListener('change', function() {
            refusalDetailsGroup.style.display = this.checked ? 'block' : 'none';
        });
    }
    
    // Toggle display of family relation details when checkbox is checked
    const hasFamilyCheckbox = document.getElementById('has_family_in_target_country');
    const familyDetailsGroup = document.getElementById('family_relation_details_group');
    
    if (hasFamilyCheckbox && familyDetailsGroup) {
        hasFamilyCheckbox.addEventListener('change', function() {
            familyDetailsGroup.style.display = this.checked ? 'block' : 'none';
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>
