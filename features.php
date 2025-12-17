<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

// Include session management
require_once "includes/session.php";

// Include config files
require_once "config/db_connect.php";

$page_title = "Platform Features";
require_once 'includes/header.php';
require_once 'includes/functions.php';
?>

<!-- Hero Section -->
<section class="hero privacy-hero">
    <div class="container">
        <div class="hero-content text-center">
            <h1 class="hero-title">Platform Features</h1>
            <p class="hero-subtitle">Discover what makes Visafy the leading visa consultation platform</p>
        </div>
    </div>
</section>

<div class="content">
    <div class="container">
        <!-- Key Features Section -->
        <section class="features-section">
            <h2 class="section-title">Key Features</h2>
            <div class="features-grid">
                <!-- Verified Consultants -->
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3>Verified Consultants</h3>
                    <p>All consultants undergo thorough verification of their credentials and experience</p>
                    <ul class="feature-list">
                        <li>Background checks</li>
                        <li>License verification</li>
                        <li>Experience validation</li>
                        <li>Client reviews</li>
                    </ul>
                </div>

                <!-- Secure Platform -->
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Secure Platform</h3>
                    <p>Your data is protected with enterprise-grade security</p>
                    <ul class="feature-list">
                        <li>End-to-end encryption</li>
                        <li>Secure file sharing</li>
                        <li>Private messaging</li>
                        <li>Data protection</li>
                    </ul>
                </div>

                <!-- Easy Booking -->
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3>Easy Booking</h3>
                    <p>Schedule consultations with just a few clicks</p>
                    <ul class="feature-list">
                        <li>Real-time availability</li>
                        <li>Instant confirmation</li>
                        <li>Flexible scheduling</li>
                        <li>Calendar integration</li>
                    </ul>
                </div>

                <!-- Expert Support -->
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3>Expert Support</h3>
                    <p>Get help whenever you need it</p>
                    <ul class="feature-list">
                        <li>24/7 customer service</li>
                        <li>Multi-language support</li>
                        <li>Quick response time</li>
                        <li>Dedicated team</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Advanced Features Section -->
        <section class="features-section">
            <h2 class="section-title">Advanced Features</h2>
            <div class="advanced-features">
                <!-- Document Management -->
                <div class="advanced-feature">
                    <div class="feature-content">
                        <h3>Smart Document Management</h3>
                        <p>Organize and manage your visa application documents efficiently</p>
                        <ul class="feature-list">
                            <li>Secure document storage</li>
                            <li>Document checklist</li>
                            <li>Version control</li>
                            <li>Easy sharing</li>
                        </ul>
                    </div>
                    <div class="feature-image">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>

                <!-- Progress Tracking -->
                <div class="advanced-feature">
                    <div class="feature-content">
                        <h3>Application Progress Tracking</h3>
                        <p>Monitor your visa application status in real-time</p>
                        <ul class="feature-list">
                            <li>Status updates</li>
                            <li>Timeline view</li>
                            <li>Milestone tracking</li>
                            <li>Email notifications</li>
                        </ul>
                    </div>
                    <div class="feature-image">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>

                <!-- Communication Tools -->
                <div class="advanced-feature">
                    <div class="feature-content">
                        <h3>Integrated Communication</h3>
                        <p>Stay connected with your consultant throughout the process</p>
                        <ul class="feature-list">
                            <li>In-app messaging</li>
                            <li>Video consultations</li>
                            <li>File sharing</li>
                            <li>Message history</li>
                        </ul>
                    </div>
                    <div class="feature-image">
                        <i class="fas fa-comments"></i>
                    </div>
                </div>
            </div>
        </section>
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

/* Features specific styles */
.features-section {
    margin-bottom: 60px;
}

.section-title {
    text-align: center;
    color: var(--dark-blue);
    font-size: 2rem;
    margin-bottom: 40px;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
    margin-bottom: 50px;
}

.feature-card {
    background-color: var(--white);
    border-radius: var(--border-radius);
    padding: 30px;
    box-shadow: var(--shadow);
    transition: transform 0.3s ease;
}

.feature-card:hover {
    transform: translateY(-5px);
}

.feature-icon {
    font-size: 2.5rem;
    color: var(--primary-color);
    margin-bottom: 20px;
    text-align: center;
}

.feature-card h3 {
    color: var(--dark-blue);
    font-size: 1.3rem;
    margin-bottom: 15px;
    text-align: center;
}

.feature-card p {
    color: var(--text-light);
    margin-bottom: 20px;
    line-height: 1.6;
}

.feature-list {
    list-style: none;
    padding: 0;
}

.feature-list li {
    margin-bottom: 10px;
    color: var(--text-color);
    padding-left: 25px;
    position: relative;
}

.feature-list li:before {
    content: "✓";
    color: var(--primary-color);
    position: absolute;
    left: 0;
}

/* Advanced Features */
.advanced-features {
    display: flex;
    flex-direction: column;
    gap: 30px;
}

.advanced-feature {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
    background-color: var(--white);
    border-radius: var(--border-radius);
    padding: 40px;
    box-shadow: var(--shadow);
    align-items: center;
}

.feature-content h3 {
    color: var(--dark-blue);
    font-size: 1.5rem;
    margin-bottom: 15px;
}

.feature-content p {
    color: var(--text-light);
    margin-bottom: 20px;
    line-height: 1.6;
}

.feature-image {
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 4rem;
    color: var(--primary-color);
}

@media (max-width: 768px) {
    .advanced-feature {
        grid-template-columns: 1fr;
        padding: 30px;
    }

    .feature-image {
        order: -1;
    }

    .features-grid {
        grid-template-columns: 1fr;
    }

    .section-title {
        font-size: 1.8rem;
    }
}
</style>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
