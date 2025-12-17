<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

// Include session management
require_once "includes/session.php";

// Include config files
require_once "config/db_connect.php";

$page_title = "Sitemap";
require_once 'includes/header.php';
require_once 'includes/functions.php';
?>

<!-- Hero Section -->
<section class="hero privacy-hero">
    <div class="container">
        <div class="hero-content text-center">
            <h1 class="hero-title">Sitemap</h1>
            <p class="hero-subtitle">Navigate through our website structure</p>
        </div>
    </div>
</section>

<div class="content">
    <div class="container">
        <div class="sitemap-content">
            <!-- Main Navigation -->
            <div class="sitemap-section">
                <h2>Main Pages</h2>
                <ul class="sitemap-list">
                    <li>
                        <a href="index.php">Home</a>
                    </li>
                    <li>
                        <a href="about-us.php">About Us</a>
                    </li>
                    <li>
                        <a href="features.php">Features</a>
                    </li>
                    <li>
                        <a href="guides.php">Visa Guides</a>
                    </li>
                </ul>
            </div>

            <!-- Visa Services -->
            <div class="sitemap-section">
                <h2>Visa Services</h2>
                <ul class="sitemap-list">
                    <li>
                        <a href="book-service.php">Book a Consultation</a>
                        <ul>
                            <li><a href="consultant-profile.php">Consultant Profiles</a></li>
                            <li><a href="book-consultation.php">Consultation Booking</a></li>
                            <li><a href="booking-confirmation.php">Booking Confirmation</a></li>
                        </ul>
                    </li>
                    <li>
                        <a href="eligibility-test.php">Eligibility Test</a>
                    </li>
                </ul>
            </div>

            <!-- User Account -->
            <div class="sitemap-section">
                <h2>User Account</h2>
                <ul class="sitemap-list">
                    <li>
                        <a href="login.php">Login</a>
                    </li>
                    <li>
                        <a href="register.php">Register</a>
                    </li>
                    <li>
                        <a href="dashboard/">Dashboard</a>
                        <ul>
                            <li><a href="dashboard/profile.php">My Profile</a></li>
                            <li><a href="dashboard/bookings.php">My Bookings</a></li>
                            <li><a href="dashboard/documents.php">Documents</a></li>
                            <li><a href="dashboard/messages.php">Messages</a></li>
                        </ul>
                    </li>
                </ul>
            </div>

            <!-- Consultant Section -->
            <div class="sitemap-section">
                <h2>For Consultants</h2>
                <ul class="sitemap-list">
                    <li>
                        <a href="consultant-registration.php">Become a Consultant</a>
                    </li>
                    <li>
                        <a href="dashboard/consultant/">Consultant Dashboard</a>
                        <ul>
                            <li><a href="dashboard/consultant/appointments.php">Appointments</a></li>
                            <li><a href="dashboard/consultant/clients.php">Clients</a></li>
                            <li><a href="dashboard/consultant/services.php">Services</a></li>
                            <li><a href="dashboard/consultant/earnings.php">Earnings</a></li>
                        </ul>
                    </li>
                </ul>
            </div>

            <!-- Support & Help -->
            <div class="sitemap-section">
                <h2>Support & Help</h2>
                <ul class="sitemap-list">
                    <li>
                        <a href="faq.php">FAQ</a>
                    </li>
                    <li>
                        <a href="support.php">Contact Support</a>
                    </li>
                </ul>
            </div>

            <!-- Legal -->
            <div class="sitemap-section">
                <h2>Legal</h2>
                <ul class="sitemap-list">
                    <li>
                        <a href="terms.php">Terms of Service</a>
                    </li>
                    <li>
                        <a href="privacy.php">Privacy Policy</a>
                    </li>
                    <li>
                        <a href="cookies.php">Cookie Policy</a>
                    </li>
                </ul>
            </div>
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

/* Sitemap specific styles */
.sitemap-content {
    max-width: 900px;
    margin: 0 auto;
    background-color: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    padding: 40px;
}

.sitemap-section {
    margin-bottom: 40px;
}

.sitemap-section:last-child {
    margin-bottom: 0;
}

.sitemap-section h2 {
    color: var(--dark-blue);
    font-size: 1.5rem;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--primary-light);
}

.sitemap-list {
    list-style: none;
    padding: 0;
}

.sitemap-list li {
    margin-bottom: 15px;
}

.sitemap-list a {
    color: var(--text-color);
    text-decoration: none;
    transition: color 0.3s ease;
    display: inline-block;
}

.sitemap-list a:hover {
    color: var(--primary-color);
}

.sitemap-list ul {
    list-style: none;
    padding-left: 20px;
    margin-top: 10px;
}

.sitemap-list ul li {
    margin-bottom: 8px;
    position: relative;
}

.sitemap-list ul li:before {
    content: "›";
    color: var(--primary-color);
    position: absolute;
    left: -15px;
}

@media (max-width: 768px) {
    .sitemap-content {
        padding: 20px;
        margin: 0 15px;
    }
    
    .sitemap-section h2 {
        font-size: 1.3rem;
    }
    
    .sitemap-list ul {
        padding-left: 15px;
    }
}
</style>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
