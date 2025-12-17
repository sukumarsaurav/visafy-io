<?php
// Include session management
require_once "includes/session.php";
require_once "includes/email_function.php";

// Include config file
require_once "config/db_connect.php";

// Define variables
$email = $message = "";
$email_err = "";
$success = false;

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate email
    if(empty(trim($_POST["email"]))) {
        $email_err = "Please enter your email.";
    } else {
        $email = trim($_POST["email"]);
    }
    
    // Check input errors
    if(empty($email_err)) {
        // Prepare a select statement to check if user exists and email is not verified
        $sql = "SELECT id, first_name, last_name, email, email_verified FROM users WHERE email = ?";
        
        if($stmt = mysqli_prepare($conn, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "s", $email);
            
            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)) {
                // Store result
                mysqli_stmt_store_result($stmt);
                
                // Check if user exists
                if(mysqli_stmt_num_rows($stmt) == 1) {
                    // Bind result variables
                    mysqli_stmt_bind_result($stmt, $id, $first_name, $last_name, $email, $email_verified);
                    mysqli_stmt_fetch($stmt);
                    
                    // Check if email is already verified
                    if($email_verified == 1) {
                        $message = "This email is already verified. Please <a href='login.php'>login</a> to your account.";
                    } else {
                        // Generate new verification token
                        $token = bin2hex(random_bytes(32));
                        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                        
                        // Update user with new verification token
                        $update_sql = "UPDATE users SET email_verification_token = ?, email_verification_expires = ? WHERE id = ?";
                        
                        if($update_stmt = mysqli_prepare($conn, $update_sql)) {
                            // Bind variables to the prepared statement as parameters
                            mysqli_stmt_bind_param($update_stmt, "ssi", $token, $expires, $id);
                            
                            // Attempt to execute the prepared statement
                            if(mysqli_stmt_execute($update_stmt)) {
                                // Send verification email
                                $verification_link = "https://visafy.io/verify_email.php?token=" . $token;
                                $email_subject = "Verify Your Email Address";
                                $email_body = "Hi $first_name,\n\nPlease click the following link to verify your email address:\n$verification_link\n\nThis link will expire in 24 hours.\n\nThank you,\nThe Visafy Team";
                                
                                if(send_email($email, $email_subject, $email_body)) {
                                    $success = true;
                                    $message = "A verification email has been sent to your email address. Please check your inbox and spam folder.";
                                } else {
                                    $message = "Error sending verification email. Please try again later.";
                                }
                            } else {
                                $message = "Oops! Something went wrong. Please try again later.";
                            }
                            
                            // Close statement
                            mysqli_stmt_close($update_stmt);
                        }
                    }
                } else {
                    $message = "No account found with that email address.";
                }
            } else {
                $message = "Oops! Something went wrong. Please try again later.";
            }
            
            // Close statement
            mysqli_stmt_close($stmt);
        }
    }
    
    // Close connection
    mysqli_close($conn);
}

// Set page title and include header
$page_title = "Resend Verification Email - Visafy";
include('includes/header.php');
?>

<div class="wrapper">
    <h2>Resend Verification Email</h2>
    <p>Please enter your email address to resend the verification email.</p>
    
    <?php if(!empty($message)): ?>
        <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?>" role="alert">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>">
            <span class="invalid-feedback"><?php echo $email_err; ?></span>
        </div>
        <div class="form-group">
            <input type="submit" class="btn btn-primary" value="Resend Verification Email">
        </div>
        <div class="form-group">
            <a href="login.php" class="btn btn-link">Back to Login</a>
        </div>
    </form>
</div>

<?php include('includes/footer.php'); ?>
