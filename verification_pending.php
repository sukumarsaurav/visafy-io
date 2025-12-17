<?php
// Include session management
require_once "includes/session.php";

// Set page title and include header
$page_title = "Email Verification Pending - Visafy";
include('includes/header.php');
?>

<div class="wrapper">
    <div class="text-center">
        <div class="check-icon mb-4">
            <i class="fas fa-envelope fa-3x" style="color: #4e73df;"></i>
        </div>
        <h2>Email Verification Required</h2>
        <p class="lead">We've sent an email verification link to your email address.</p>
        <div class="alert alert-info" role="alert">
            <p>Please check your inbox and click on the verification link to complete your registration.</p>
            <p>If you don't see the email, please check your spam folder.</p>
        </div>
        <div class="mt-4">
            <p>Didn't receive the email? <a href="resend_verification.php" class="btn btn-primary btn-sm">Resend Verification Email</a></p>
            <p class="mt-3"><a href="login.php">Back to Login</a></p>
        </div>
    </div>
</div>

<style>
    .wrapper {
        max-width: 600px;
        margin: 80px auto;
        padding: 30px;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .check-icon {
        height: 80px;
        width: 80px;
        background-color: rgba(78, 115, 223, 0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
    }
</style>

<?php include('includes/footer.php'); ?> 