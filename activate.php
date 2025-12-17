<?php
// Include session management
require_once "includes/session.php";

// Include config files
require_once "config/db_connect.php";
require_once "config/email_config.php";
require_once "includes/email_function.php";

// Check if user is already logged in
if(is_logged_in()) {
    // Redirect to dashboard based on user type
    if($_SESSION["user_type"] === "consultant") {
        header("location: dashboard/consultant/index.php");
    } elseif($_SESSION["user_type"] === "member") {
        header("location: dashboard/consultant/index.php");
    } elseif($_SESSION["user_type"] === "applicant") {
        header("location: dashboard/applicant/index.php");
    } else {
        header("location: dashboard.php");
    }
    exit;
}

// Initialize variables
$token = $email = $password = $confirm_password = "";
$token_err = $password_err = $confirm_password_err = $general_err = "";
$user_data = null;
$show_form = false;

// Check if token parameter exists in URL
if(isset($_GET['token']) && !empty($_GET['token'])) {
    $token = trim($_GET['token']);
    
    // Verify token exists and is valid
    $sql = "SELECT id, first_name, last_name, email, user_type, email_verification_token, email_verification_expires, organization_id 
            FROM users 
            WHERE email_verification_token = ? AND status = 'suspended' AND email_verified = 0";
    
    if($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $token);
        
        if($stmt->execute()) {
            $result = $stmt->get_result();
            
            if($result->num_rows === 1) {
                $user_data = $result->fetch_assoc();
                
                // Check if token is expired
                $current_time = date('Y-m-d H:i:s');
                if($user_data['email_verification_expires'] < $current_time) {
                    $token_err = "This activation link has expired. Please contact your team administrator to send a new invitation.";
                } else {
                    // Token is valid, show password form
                    $show_form = true;
                    $email = $user_data['email'];
                }
            } else {
                $token_err = "Invalid activation token or account already activated.";
            }
        } else {
            $general_err = "Oops! Something went wrong. Please try again later.";
        }
        
        $stmt->close();
    }
} elseif($_SERVER["REQUEST_METHOD"] === "POST") {
    // Form submission to set password
    
    // Get hidden inputs
    $token = trim($_POST["token"]);
    $email = trim($_POST["email"]);
    
    // Validate password
    if(empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";     
    } elseif(strlen(trim($_POST["password"])) < 8) {
        $password_err = "Password must have at least 8 characters.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate confirm password
    if(empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";     
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if(empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Passwords do not match.";
        }
    }
    
    // Check input errors before updating the database
    if(empty($password_err) && empty($confirm_password_err)) {
        // Verify token again for security
        $sql = "SELECT id, first_name, last_name, email, user_type, organization_id 
                FROM users 
                WHERE email = ? AND email_verification_token = ? AND status = 'suspended' AND email_verified = 0";
        
        if($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ss", $email, $token);
            
            if($stmt->execute()) {
                $result = $stmt->get_result();
                
                if($result->num_rows === 1) {
                    $user_data = $result->fetch_assoc();
                    
                    // Begin transaction
                    $conn->begin_transaction();
                    
                    try {
                        // Update user account - set password, activate account, and mark as verified
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        
                        $update_sql = "UPDATE users 
                                      SET password = ?, 
                                          email_verified = 1, 
                                          email_verification_token = NULL, 
                                          email_verification_expires = NULL, 
                                          status = 'active' 
                                      WHERE id = ?";
                        
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bind_param("si", $hashed_password, $user_data['id']);
                        $update_stmt->execute();
                        $update_stmt->close();
                        
                        // Commit transaction
                        $conn->commit();
                        
                        // Store data in session variables
                        $_SESSION["loggedin"] = true;
                        $_SESSION["id"] = $user_data['id'];
                        $_SESSION["email"] = $user_data['email'];  
                        $_SESSION["first_name"] = $user_data['first_name'];
                        $_SESSION["last_name"] = $user_data['last_name'];
                        $_SESSION["user_type"] = $user_data['user_type'];
                        $_SESSION["organization_id"] = $user_data['organization_id'];
                        $_SESSION["last_activity"] = time();
                        $_SESSION["created_at"] = time();
                        
                        // Redirect user to appropriate dashboard
                        if($user_data["user_type"] === "consultant") {
                            header("location: dashboard/consultant/index.php");
                        } elseif($user_data["user_type"] === "member") {
                            header("location: dashboard/team/index.php");
                        } else {
                            header("location: dashboard.php");
                        }
                        exit;
                        
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        $conn->rollback();
                        $general_err = "Error activating account: " . $e->getMessage();
                    }
                    
                } else {
                    $general_err = "Invalid activation details or account already activated.";
                }
            } else {
                $general_err = "Oops! Something went wrong. Please try again later.";
            }
            
            $stmt->close();
        }
    }
}

// Set page title and include header
$page_title = "Activate Your Account";
include('includes/header.php');
?>

<div class="container">
    <div class="card account-card">
        <div class="card-header text-center">
            <h2>Activate Your Account</h2>
            <p>Set your password to complete your team member account setup</p>
        </div>
        <div class="card-body">
        
            <?php if(!empty($token_err)): ?>
                <div class="alert alert-danger">
                    <p><?php echo $token_err; ?></p>
                </div>
                <div class="text-center mt-4">
                    <a href="login.php" class="btn btn-primary">Go to Login</a>
                </div>
            <?php elseif(!empty($general_err)): ?>
                <div class="alert alert-danger">
                    <p><?php echo $general_err; ?></p>
                </div>
                <div class="text-center mt-4">
                    <a href="login.php" class="btn btn-primary">Go to Login</a>
                </div>
            <?php elseif($show_form): ?>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="text" class="form-control" value="<?php echo $email; ?>" disabled>
                    </div>    
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $password; ?>">
                        <span class="invalid-feedback"><?php echo $password_err; ?></span>
                        <small class="form-text text-muted">Password must be at least 8 characters long</small>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $confirm_password; ?>">
                        <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
                    </div>
                    <input type="hidden" name="token" value="<?php echo $token; ?>">
                    <input type="hidden" name="email" value="<?php echo $email; ?>">
                    <div class="form-group text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-block">Activate Account</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-danger">
                    <p>No activation token provided or invalid token.</p>
                </div>
                <div class="text-center mt-4">
                    <a href="login.php" class="btn btn-primary">Go to Login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .container {
        max-width: 500px;
        margin: 50px auto;
    }
    
    .account-card {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        border-radius: 10px;
        border: none;
    }
    
    .card-header {
        background-color: #f8f9fc;
        border-bottom: 1px solid #e3e6f0;
        padding: 20px;
    }
    
    .card-header h2 {
        color: #042167;
        margin-bottom: 10px;
    }
    
    .card-header p {
        color: #858796;
        margin-bottom: 0;
    }
    
    .card-body {
        padding: 30px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-control {
        height: 45px;
        border-radius: 5px;
    }
    
    .btn-primary {
        background-color: #042167;
        border-color: #042167;
        padding: 10px 20px;
        font-weight: 600;
    }
    
    .btn-primary:hover {
        background-color: #031c56;
        border-color: #031c56;
    }
    
    .alert {
        border-radius: 5px;
    }
</style>

<?php include('includes/footer.php'); ?>
