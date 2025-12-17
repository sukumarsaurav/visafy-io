<?php
// Include session management
require_once "includes/session.php";

// Check if the user is already logged in, if yes then redirect to dashboard
if(is_logged_in()) {
    // Redirect based on user type
    if(isset($_SESSION["user_type"])) {
        switch($_SESSION["user_type"]) {
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
    } else {
        // Default to applicant dashboard if user_type is not set
        header("location: dashboard/applicant/index.php");
    }
    exit;
}

// Include config files
require_once "config/db_connect.php";
require_once "config/email_config.php";
require_once "includes/email_function.php";

// Define variables and initialize with empty values
$first_name = $last_name = $email = $phone = $password = $confirm_password = "";
$first_name_err = $last_name_err = $email_err = $phone_err = $password_err = $confirm_password_err = "";

// Google OAuth Configuration
$google_client_id = getenv('GOOGLE_CLIENT_ID');
$google_client_secret = getenv('GOOGLE_CLIENT_SECRET');
$google_redirect_url = "https://visafy.io/google_callback.php";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate first name
    if(empty(trim($_POST["first_name"]))) {
        $first_name_err = "Please enter your first name.";
    } else {
        $first_name = trim($_POST["first_name"]);
    }
    
    // Validate last name
    if(empty(trim($_POST["last_name"]))) {
        $last_name_err = "Please enter your last name.";
    } else {
        $last_name = trim($_POST["last_name"]);
    }
    
    // Validate email
    if(empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email.";
    } else {
        // Prepare a select statement
        $sql = "SELECT id FROM users WHERE email = ?";
        
        if($stmt = mysqli_prepare($conn, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "s", $param_email);
            
            // Set parameters
            $param_email = trim($_POST["email"]);
            
            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)) {
                // Store result
                mysqli_stmt_store_result($stmt);
                
                if(mysqli_stmt_num_rows($stmt) > 0) {
                    $email_err = "This email is already taken.";
                } else {
                    $email = trim($_POST["email"]);
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }
    
    // Validate phone
    if(empty(trim($_POST["phone"]))) {
        $phone_err = "Please enter a phone number.";     
    } else {
        $phone = trim($_POST["phone"]);
    }
    
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
            $confirm_password_err = "Password did not match.";
        }
    }
    
    // Check input errors before inserting in database
    if(empty($first_name_err) && empty($last_name_err) && empty($email_err) && empty($phone_err) && empty($password_err) && empty($confirm_password_err)) {
        
        // Generate verification token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Prepare an insert statement for the users table
        $sql = "INSERT INTO users (first_name, last_name, email, phone, password, user_type, email_verification_token, email_verification_expires) VALUES (?, ?, ?, ?, ?, 'applicant', ?, ?)";
         
        if($stmt = mysqli_prepare($conn, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "sssssss", $param_first_name, $param_last_name, $param_email, $param_phone, $param_password, $param_token, $param_expires);
            
            // Set parameters
            $param_first_name = $first_name;
            $param_last_name = $last_name;
            $param_email = $email;
            $param_phone = $phone;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // Creates a password hash
            $param_token = $token;
            $param_expires = $expires;
            
            // Attempt to execute the prepared statement for users table
            if(mysqli_stmt_execute($stmt)) {
                // Get the new user's ID
                $user_id = mysqli_insert_id($conn);
                
                // Insert into applicants table
                $sql2 = "INSERT INTO applicants (user_id) VALUES (?)";
                
                if($stmt2 = mysqli_prepare($conn, $sql2)) {
                    mysqli_stmt_bind_param($stmt2, "i", $user_id);
                    
                    if(mysqli_stmt_execute($stmt2)) {
                        // Send verification email
                        $verification_link = "https://visafy.io/verify_email.php?token=" . $token;
                        $email_subject = "Verify Your Email Address";
                        $email_body = "Hi $first_name,\n\nPlease click the following link to verify your email address:\n$verification_link\n\nThis link will expire in 24 hours.\n\nThank you,\nThe Visafy Team";
                        
                        // Use your email function to send verification email
                        if(send_email($email, $email_subject, $email_body)) {
                            // Redirect to verification pending page
                            header("location: verification_pending.php");
                            exit;
                        } else {
                            echo "Error sending verification email. Please try again later.";
                        }
                    } else {
                        echo "Something went wrong with creating your profile. Please try again later.";
                    }
                    
                    // Close statement
                    mysqli_stmt_close($stmt2);
                }
            } else {
                echo "Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }
    
    // Close connection
    mysqli_close($conn);
}

// Set page title and include header
$page_title = "Register as Applicant - Visafy";
include('includes/header.php');
?>

<div class="wrapper">
    <h2 class="text-center mb-4">Create Your Applicant Account</h2>
    <p class="text-center mb-4">Register as an applicant to start your visa journey</p>
    
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" class="form-control <?php echo (!empty($first_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $first_name; ?>">
                    <span class="invalid-feedback"><?php echo $first_name_err; ?></span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" class="form-control <?php echo (!empty($last_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $last_name; ?>">
                    <span class="invalid-feedback"><?php echo $last_name_err; ?></span>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>">
            <span class="invalid-feedback"><?php echo $email_err; ?></span>
        </div>
        <div class="form-group">
            <label>Phone</label>
            <input type="tel" name="phone" class="form-control <?php echo (!empty($phone_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $phone; ?>">
            <span class="invalid-feedback"><?php echo $phone_err; ?></span>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $password; ?>">
            <span class="invalid-feedback"><?php echo $password_err; ?></span>
            <small class="form-text text-muted">Your password must be at least 8 characters long.</small>
        </div>
        <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $confirm_password; ?>">
            <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
        </div>
        <div class="form-group">
            <input type="submit" class="btn btn-primary btn-block" value="Sign Up as Applicant">
        </div>
        
        <div class="divider">
            <span>OR</span>
        </div>
        
        <div class="form-group">
            <a href="<?php echo 'https://accounts.google.com/o/oauth2/v2/auth?scope=email%20profile&redirect_uri='.$google_redirect_url.'&response_type=code&client_id='.$google_client_id; ?>" class="btn btn-google btn-block">
                <img src="assets/images/google.svg" alt="Google logo"> 
                Sign up with Google
            </a>
        </div>
    </form>
    
    <div class="form-footer">
        Already have an account? <a href="login.php">Login here</a>
    </div>
</div>

<?php include('includes/footer.php'); ?>
