<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

// Include session management
require_once "includes/session.php";

// Include config files
require_once "config/db_connect.php";

$page_title = "Support";
require_once 'includes/header.php';
require_once 'includes/functions.php';
?>

<!-- Hero Section -->
<section class="hero privacy-hero">
    <div class="container">
        <div class="hero-content text-center">
            <h1 class="hero-title">Support</h1>
            <p class="hero-subtitle">We're here to help you with any questions or issues</p>
        </div>
    </div>
</section>

<div class="content">
    <div class="container">
        <div class="support-content">
            <h2>Contact Us</h2>
            <p>If you have any questions or need assistance, please reach out to us:</p>
            <ul class="contact-list">
                <li><strong>Email:</strong> support@visafy.com</li>
                <li><strong>Phone:</strong> +1 (647) 226-7436</li>
                <li><strong>Address:</strong> 2233 Argentina Rd, Mississauga ON L5N 2X7, Canada</li>
            </ul>

            <h2>Frequently Asked Questions</h2>
            <p>Check our <a href="faq.php">FAQ</a> page for common questions and answers.</p>
        </div>
    </div>
</div>

<style>
:root {
    --primary-color: #eaaa34;
    --primary-light: rgba(234, 170, 52, 0.1);
    --dark-blue: #042167;
    --text-color: #333;
    --text-light: #666;
    --background-light: #f8f9fa;
    --white: #fff;
    --border-color: #e5e7eb;
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --border-radius: 0.5rem;
}

/* Inherit existing styles */
.privacy-hero {
    background-color: rgba(234, 170, 52, 0.05);
    padding: 60px 0;
    text-align: center;
}

.hero-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--dark-blue);
    margin-bottom: 15px;
}

.hero-subtitle {
    font-size: 1.2rem;
    color: var(--text-light);
}

.content {
    padding: 50px 0;
}

.support-content {
    max-width: 900px;
    margin: 0 auto;
    background-color: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    padding: 40px;
}

.contact-list {
    list-style: none;
    padding: 0;
}

.contact-list li {
    margin-bottom: 15px;
}
</style>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>