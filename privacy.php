<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

// Include session management
require_once "includes/session.php";

// Include config files
require_once "config/db_connect.php";

$page_title = "Privacy Policy";
require_once 'includes/header.php';
require_once 'includes/functions.php';
?>

<!-- Hero Section -->
<section class="hero privacy-hero">
    <div class="container">
        <div class="hero-content text-center">
            <h1 class="hero-title">Privacy Policy</h1>
            <p class="hero-subtitle">How we collect, use, and protect your information</p>
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
                <h2>Introduction</h2>
                <p>At Visafy ("we", "our", or "us"), we are committed to protecting your privacy and the security of your personal information. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you visit our website or use our services.</p>
                <p>Please read this privacy policy carefully. If you do not agree with the terms of this privacy policy, please do not access the site or use our services.</p>
            </section>
            
            <section class="privacy-section">
                <h2>Information We Collect</h2>
                <h3>Personal Information</h3>
                <p>We may collect personal information that you voluntarily provide to us when you:</p>
                <ul>
                    <li>Register for an account</li>
                    <li>Express interest in obtaining information about our services</li>
                    <li>Participate in activities on our website</li>
                    <li>Contact us</li>
                </ul>
                <p>The personal information we collect may include:</p>
                <ul>
                    <li>Full name</li>
                    <li>Email address</li>
                    <li>Phone number</li>
                    <li>Mailing address</li>
                    <li>Payment information</li>
                    <li>Immigration history and documents</li>
                    <li>Educational and employment background</li>
                    <li>Any other information you choose to provide</li>
                </ul>
                
                <h3>Automatically Collected Information</h3>
                <p>When you access our website, we may automatically collect certain information about your device, including:</p>
                <ul>
                    <li>IP address</li>
                    <li>Browser type</li>
                    <li>Operating system</li>
                    <li>Pages you visit</li>
                    <li>Time and date of your visit</li>
                    <li>Time spent on those pages</li>
                    <li>Device identifiers</li>
                </ul>
                <p>This information is primarily needed to maintain the security and operation of our website, and for our internal analytics and reporting purposes.</p>
            </section>
            
            <section class="privacy-section">
                <h2>How We Use Your Information</h2>
                <p>We may use the information we collect for various purposes, including to:</p>
                <ul>
                    <li>Provide, operate, and maintain our services</li>
                    <li>Improve, personalize, and expand our services</li>
                    <li>Understand and analyze how you use our services</li>
                    <li>Develop new products, services, features, and functionality</li>
                    <li>Communicate with you about our services, updates, and other information</li>
                    <li>Process your transactions and manage your account</li>
                    <li>Find and prevent fraud</li>
                    <li>For compliance, legal process, and law enforcement purposes</li>
                    <li>For other purposes with your consent</li>
                </ul>
            </section>
            
            <section class="privacy-section">
                <h2>Sharing Your Information</h2>
                <p>We may share your information with third parties in certain situations, including:</p>
                <h3>Business Transfers</h3>
                <p>If we are involved in a merger, acquisition, or sale of all or a portion of our assets, your information may be transferred as part of that transaction.</p>
                
                <h3>Compliance with Laws</h3>
                <p>We may disclose your information where required to do so by law or subpoena, or if we believe such action is necessary to comply with the law and the reasonable requests of law enforcement.</p>
                
                <h3>Third-Party Service Providers</h3>
                <p>We may share your information with third-party vendors, service providers, contractors, or agents who perform services for us or on our behalf and require access to such information to do that work.</p>
                
                <h3>With Your Consent</h3>
                <p>We may share your information with your consent or at your direction.</p>
            </section>
            
            <section class="privacy-section">
                <h2>Data Security</h2>
                <p>We have implemented appropriate technical and organizational security measures designed to protect the security of any personal information we process. However, despite our safeguards, no security system is impenetrable. We cannot guarantee the security of our databases, nor can we guarantee that information you supply will not be intercepted while being transmitted to us over the Internet.</p>
            </section>
            
            <section class="privacy-section">
                <h2>Your Rights</h2>
                <p>Depending on your location, you may have certain rights regarding your personal information, including:</p>
                <ul>
                    <li>The right to access personal information we hold about you</li>
                    <li>The right to request that we correct inaccurate personal information</li>
                    <li>The right to request that we delete your personal information</li>
                    <li>The right to withdraw consent</li>
                    <li>The right to opt-out of marketing communications</li>
                    <li>The right to complain to a data protection authority</li>
                </ul>
                <p>To exercise these rights, please contact us using the information provided in the "Contact Us" section below.</p>
            </section>
            
            <section class="privacy-section">
                <h2>Cookies and Tracking Technologies</h2>
                <p>We use cookies and similar tracking technologies to track activity on our website and hold certain information. Cookies are files with a small amount of data which may include an anonymous unique identifier.</p>
                <p>You can instruct your browser to refuse all cookies or to indicate when a cookie is being sent. However, if you do not accept cookies, you may not be able to use some portions of our services.</p>
            </section>
            
            <section class="privacy-section">
                <h2>Children's Privacy</h2>
                <p>Our services are not intended for use by children under the age of 18. We do not knowingly collect personally identifiable information from children under 18. If you are a parent or guardian and you are aware that your child has provided us with personal information, please contact us so that we can take necessary action.</p>
            </section>
            
            <section class="privacy-section">
                <h2>Changes to This Privacy Policy</h2>
                <p>We may update our Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page and updating the "Last Updated" date at the top of this policy.</p>
                <p>You are advised to review this Privacy Policy periodically for any changes. Changes to this Privacy Policy are effective when they are posted on this page.</p>
            </section>
            
            <section class="privacy-section">
                <h2>Contact Us</h2>
                <p>If you have any questions about this Privacy Policy, please contact us at:</p>
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
