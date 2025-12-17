<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

// Include session management
require_once "includes/session.php";

// Include config files
require_once "config/db_connect.php";

$page_title = "Cookie Policy";
require_once 'includes/header.php';
require_once 'includes/functions.php';
?>

<!-- Hero Section -->
<section class="hero privacy-hero">
    <div class="container">
        <div class="hero-content text-center">
            <h1 class="hero-title">Cookie Policy</h1>
            <p class="hero-subtitle">Understanding how we use cookies to improve your experience</p>
        </div>
    </div>
</section>

<div class="content">
    <div class="container">
        <div class="privacy-content">
            <div class="last-updated">
                <p>Last Updated: May 1, 2023</p>
            </div>
            
            <section class="privacy-section">
                <h2>What Are Cookies?</h2>
                <p>Cookies are small text files that are placed on your computer or mobile device when you visit our website. They are widely used to make websites work more efficiently and provide a better browsing experience.</p>
            </section>
            
            <section class="privacy-section">
                <h2>How We Use Cookies</h2>
                <p>We use cookies for several purposes, including:</p>
                <ul>
                    <li><strong>Essential Cookies:</strong> Required for the website to function properly</li>
                    <li><strong>Functionality Cookies:</strong> Remember your preferences and settings</li>
                    <li><strong>Analytics Cookies:</strong> Help us understand how visitors use our website</li>
                    <li><strong>Authentication Cookies:</strong> Manage your logged-in session</li>
                </ul>
            </section>
            
            <section class="privacy-section">
                <h2>Types of Cookies We Use</h2>
                <h3>Essential Cookies</h3>
                <p>These cookies are necessary for the website to function and cannot be switched off. They are usually set in response to actions you take such as logging in or filling in forms.</p>
                
                <h3>Performance Cookies</h3>
                <p>These cookies allow us to count visits and traffic sources so we can measure and improve the performance of our site.</p>
                
                <h3>Functionality Cookies</h3>
                <p>These cookies enable enhanced functionality and personalization, such as remembering your preferences.</p>
                
                <h3>Targeting Cookies</h3>
                <p>These cookies may be set through our site by our advertising partners to build a profile of your interests.</p>
            </section>
            
            <section class="privacy-section">
                <h2>Managing Cookies</h2>
                <p>Most web browsers allow you to control cookies through their settings. You can:</p>
                <ul>
                    <li>View cookies stored on your computer</li>
                    <li>Delete all or specific cookies</li>
                    <li>Block cookies from being set</li>
                    <li>Allow or block cookies from specific websites</li>
                </ul>
                <p>Please note that blocking cookies may impact the functionality of our website.</p>
            </section>
            
            <section class="privacy-section">
                <h2>Third-Party Cookies</h2>
                <p>We may use third-party services that also set cookies on our website, including:</p>
                <ul>
                    <li>Google Analytics for website analytics</li>
                    <li>Social media plugins for sharing content</li>
                    <li>Payment processors for secure transactions</li>
                </ul>
            </section>
            
            <section class="privacy-section">
                <h2>Updates to This Policy</h2>
                <p>We may update this Cookie Policy from time to time. Any changes will be posted on this page with an updated revision date.</p>
            </section>
            
            <section class="privacy-section">
                <h2>Contact Us</h2>
                <p>If you have any questions about our Cookie Policy, please contact us at:</p>
                <ul class="contact-list">
                    <li><strong>Email:</strong> privacy@visafy.com</li>
                    <li><strong>Phone:</strong> +1 (647) 226-7436</li>
                    <li><strong>Address:</strong> 2233 Argentina Rd, Mississauga ON L5N 2X7, Canada</li>
                </ul>
            </section>
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

.privacy-content {
    max-width: 900px;
    margin: 0 auto;
    background-color: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    padding: 40px;
}

.last-updated {
    margin-bottom: 30px;
    color: var(--text-light);
    font-style: italic;
}

.privacy-section {
    margin-bottom: 40px;
}

.privacy-section h2 {
    color: var(--dark-blue);
    font-size: 1.8rem;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
}

.privacy-section h3 {
    color: var(--primary-color);
    font-size: 1.3rem;
    margin: 20px 0 15px;
}

.privacy-section p {
    margin-bottom: 15px;
    line-height: 1.6;
    color: var(--text-color);
}

.privacy-section ul, .privacy-section ol {
    margin-bottom: 20px;
    padding-left: 20px;
}

.privacy-section li {
    margin-bottom: 10px;
    line-height: 1.6;
    color: var(--text-color);
}

.contact-list {
    list-style: none;
    padding: 0;
}

.contact-list li {
    margin-bottom: 15px;
}

@media (max-width: 768px) {
    .privacy-content {
        padding: 30px 20px;
    }
    
    .hero-title {
        font-size: 2rem;
    }
    
    .privacy-section h2 {
        font-size: 1.5rem;
    }
    
    .privacy-section h3 {
        font-size: 1.2rem;
    }
}
</style>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
