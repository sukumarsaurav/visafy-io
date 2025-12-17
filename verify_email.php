<?php
// Include session management
require_once "includes/session.php";

// Include config file
require_once "config/db_connect.php";

// Check if token parameter exists
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Prepare a select statement
    $sql = "SELECT id, email_verification_token, email_verification_expires FROM users WHERE email_verification_token = ?";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        // Bind variables to the prepared statement as parameters
        mysqli_stmt_bind_param($stmt, "s", $token);
        
        // Attempt to execute the prepared statement
        if (mysqli_stmt_execute($stmt)) {
            // Store result
            mysqli_stmt_store_result($stmt);
            
            // Check if token exists
            if (mysqli_stmt_num_rows($stmt) == 1) {
                // Bind result variables
                mysqli_stmt_bind_result($stmt, $id, $db_token, $expires);
                
                if (mysqli_stmt_fetch($stmt)) {
                    // Check if token is expired
                    $current_time = date('Y-m-d H:i:s');
                    
                    if ($expires < $current_time) {
                        $error_message = "This verification link has expired. Please request a new one.";
                    } else {
                        // Token is valid, update the user's email_verified status
                        $update_sql = "UPDATE users SET email_verified = 1, email_verification_token = NULL, email_verification_expires = NULL WHERE id = ?";
                        
                        if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
                            mysqli_stmt_bind_param($update_stmt, "i", $id);
                            
                            if (mysqli_stmt_execute($update_stmt)) {
                                $success_message = "Your email has been verified successfully. You can now <a href='login.php'>login</a> to your account.";
                            } else {
                                $error_message = "Oops! Something went wrong. Please try again later.";
                            }
                            
                            mysqli_stmt_close($update_stmt);
                        }
                    }
                }
            } else {
                $error_message = "Invalid verification token.";
            }
        } else {
            $error_message = "Oops! Something went wrong. Please try again later.";
        }
        
        mysqli_stmt_close($stmt);
    }
} else {
    $error_message = "No verification token provided.";
}

// Close connection
mysqli_close($conn);

// Set page title and include header
$page_title = "Email Verification - Visafy";
include('includes/header.php');
?>

<div class="wrapper">
    <h2>Email Verification</h2>
    
    <?php if(isset($error_message)): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo $error_message; ?>
        </div>
        <a href="resend_verification.php" class="btn btn-primary">Resend Verification Email</a>
    <?php endif; ?>
    
    <?php if(isset($success_message)): ?>
        <div class="alert alert-success" role="alert">
            <?php echo $success_message; ?>
        </div>
        <a href="login.php" class="btn btn-primary">Login Now</a>
    <?php endif; ?>
</div>

<?php include('includes/footer.php'); ?>
