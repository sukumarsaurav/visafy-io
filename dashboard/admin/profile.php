<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "My Profile";
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

$upload_base = '../../uploads/users/';
if (!is_dir($upload_base)) {
    mkdir($upload_base, 0755, true);
}

if (!is_dir($user_upload_dir)) {
    mkdir($user_upload_dir, 0755, true);
}
if (!is_dir($profile_dir)) {
    mkdir($profile_dir, 0755, true);
}

// Fetch user data
$query = "SELECT u.*, oauth.provider, oauth.provider_user_id
          FROM users u 
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
                // Store relative path from uploads directory
                $profile_picture = str_replace('../../uploads/', '', $upload_result['file_path']);
            } else {
                $validation_errors[] = $upload_result['message'];
            }
        }
        
        // Update profile if no validation errors
        if (empty($validation_errors)) {
            $update_query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, profile_picture = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param('ssssi', $first_name, $last_name, $email, $profile_picture, $user_id);
            
            if ($stmt->execute()) {
                $success_message = "Profile updated successfully";
                
                // Update session variables
                $_SESSION["first_name"] = $first_name;
                $_SESSION["last_name"] = $last_name;
                $_SESSION["email"] = $email;
                $_SESSION["profile_picture"] = $profile_picture;
                
                // Refresh user data
                $result = $conn->query("SELECT u.*, oauth.provider, oauth.provider_user_id 
                                      FROM users u 
                                      LEFT JOIN oauth_tokens oauth ON u.id = oauth.user_id AND oauth.provider = 'google'
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
}

// Get profile picture URL
$profile_img = '../../assets/images/default-profile.jpg';
if (!empty($user_data['profile_picture'])) {
    $profile_path = '../../uploads/' . $user_data['profile_picture'];
    if (file_exists($profile_path)) {
        $profile_img = $profile_path;
    }
}

// Handle Google profile data
if (isset($_SESSION['google_data']) && empty($user_data['profile_picture'])) {
    // Get Google profile picture
    $google_picture = $_SESSION['google_data']['picture'] ?? null;
    
    if ($google_picture) {
        // Download and save Google profile picture
        $img_content = file_get_contents($google_picture);
        if ($img_content !== false) {
            $file_extension = '.jpg'; // Most Google profile pics are JPG
            $new_filename = 'profile_' . time() . $file_extension;
            $relative_path = 'users/' . $user_id . '/profile/' . $new_filename;
            $absolute_path = $user_upload_dir . '/' . $new_filename;
            
            if (file_put_contents($absolute_path, $img_content)) {
                // Update database with new profile picture path
                $update_picture = "UPDATE users SET profile_picture = ? WHERE id = ?";
                $stmt = $conn->prepare($update_picture);
                $stmt->bind_param('si', $relative_path, $user_id);
                $stmt->execute();
                $stmt->close();
                
                // Update session
                $_SESSION['profile_picture'] = $relative_path;
            }
        }
    }
}
?>

<div class="content">
    <div class="dashboard-header">
        <h1>My Profile</h1>
        <p>Manage your personal information and account settings</p>
    </div>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <div class="dashboard-section">
        <div class="section-header">
            <h3>Profile Information</h3>
            <p>Update your personal details</p>
        </div>
        
        <form action="profile.php" method="POST" enctype="multipart/form-data">
            <div class="profile-image-container">
                <img src="<?php echo $profile_img; ?>" alt="Profile Picture" class="profile-image">
                <div class="change-photo-overlay">
                    <label for="profile-picture-upload" class="change-photo-btn">
                        <i class="fas fa-camera"></i>
                    </label>
                    <input type="file" id="profile-picture-upload" name="profile_picture" class="hidden-file-input" accept="image/jpeg, image/png, image/gif">
                </div>
            </div>
            
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
            
            <div class="form-buttons">
                <button type="submit" name="update_profile" class="btn primary-btn">Save Changes</button>
            </div>
        </form>
    </div>
    
    <div class="dashboard-section">
        <div class="section-header">
            <h3>Security Settings</h3>
            <p>Manage your account password</p>
        </div>
        
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
    
    <div class="dashboard-section">
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
                    <?php if ($user_data['auth_provider'] !== 'google'): ?>
                        <button type="submit" name="link_google" class="btn outline-btn">Connect</button>
                    <?php endif; ?>
                </form>
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
    --light-color: #f8f9fc;
    --dark-color: #5a5c69;
    --border-color: #e3e6f0;
}

.content {
    padding: 20px;
    margin: 0 auto;
}

.dashboard-header {
    margin-bottom: 20px;
}

.dashboard-header h1 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.8rem;
    font-weight: 700;
}

.dashboard-header p {
    margin: 5px 0 0;
    color: var(--secondary-color);
}

.dashboard-section {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 20px;
    margin-bottom: 30px;
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

.profile-image-container {
    position: relative;
    width: 150px;
    height: 150px;
    margin: 0 auto 20px;
}

.profile-image {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
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
}

.hidden-file-input {
    display: none;
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

.form-text {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    color: var(--secondary-color);
}

.form-buttons {
    margin-top: 20px;
}

.btn {
    padding: 10px 20px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.2s;
}

.primary-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
}

.outline-btn {
    background-color: white;
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
}

.connected-account-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border: 1px solid var(--border-color);
    border-radius: 5px;
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

@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .connected-account-item {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .account-info {
        flex-direction: column;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Profile picture change functionality
    const profilePicUpload = document.getElementById('profile-picture-upload');
    const profileImage = document.querySelector('.profile-image');
    
    profilePicUpload.addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                profileImage.src = e.target.result;
            }
            
            reader.readAsDataURL(this.files[0]);
        }
    });
});
</script>

<?php
require_once 'includes/footer.php';
ob_end_flush();
?>
