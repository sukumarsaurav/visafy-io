<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

// Include session management
require_once "includes/session.php";

// Include config files
require_once "config/db_connect.php";

$page_title = "Terms of Service";
require_once 'includes/header.php';
require_once 'includes/functions.php';
?>

<!-- Hero Section -->
<section class="hero terms-hero">
    <div class="container">
        <div class="hero-content text-center">
            <h1 class="hero-title">Terms of Service</h1>
            <p class="hero-subtitle">Please read these terms carefully before using our services</p>
        </div>
    </div>
</section>

<div class="content">
    <div class="container">
        <div class="terms-content">
            <div class="last-updated">
                <p>Last Updated: May 1, 2023</p>
            </div>
            
            <section class="terms-section">
                <h2>Introduction</h2>
                <p>Welcome to Visafy ("Company", "we", "our", "us"). These Terms of Service ("Terms", "Terms of Service") govern your use of our website and services operated by Visafy.</p>
                <p>By accessing or using the Service, you agree to be bound by these Terms. If you disagree with any part of the terms, then you may not access the Service.</p>
            </section>
            
            <section class="terms-section">
                <h2>Accounts</h2>
                <p>When you create an account with us, you must provide information that is accurate, complete, and current at all times. Failure to do so constitutes a breach of the Terms, which may result in immediate termination of your account on our Service.</p>
                <p>You are responsible for safeguarding the password that you use to access the Service and for any activities or actions under your password, whether your password is with our Service or a third-party service.</p>
                <p>You agree not to disclose your password to any third party. You must notify us immediately upon becoming aware of any breach of security or unauthorized use of your account.</p>
            </section>
            
            <section class="terms-section">
                <h2>Service Offerings</h2>
                <p>Visafy provides immigration consultancy services, including but not limited to:</p>
                <ul>
                    <li>Visa application assistance</li>
                    <li>Immigration consultation</li>
                    <li>Document verification and preparation</li>
                    <li>Application submission and tracking</li>
                    <li>Representational services before immigration authorities</li>
                </ul>
                <p>Our services are provided on an "as is" and "as available" basis. We reserve the right to modify, suspend, or discontinue our service (or any part or content thereof) at any time with or without notice.</p>
            </section>
            
            <section class="terms-section">
                <h2>Consultant Memberships</h2>
                <p>For consultants joining our platform:</p>
                <ul>
                    <li>You must provide true, accurate, and complete information about your qualifications and credentials.</li>
                    <li>You must maintain valid certifications and licenses as required by applicable laws.</li>
                    <li>You agree to adhere to all relevant immigration laws, regulations, and professional standards.</li>
                    <li>Membership plans are billed according to the terms specified at sign-up.</li>
                    <li>You may cancel your membership according to our cancellation policy.</li>
                </ul>
                <p>We reserve the right to verify your credentials and reject or terminate memberships that do not meet our standards or violate these Terms.</p>
            </section>
            
            <section class="terms-section">
                <h2>Fees and Payment</h2>
                <p>Certain services provided by Visafy may require payment of fees. All fees are stated in the applicable service description or membership plan.</p>
                <p>By providing a credit card or other payment method accepted by us, you represent and warrant that:</p>
                <ul>
                    <li>You are authorized to use the designated payment method</li>
                    <li>You authorize us to charge your payment method for the total amount of your purchase</li>
                    <li>If your payment method is declined, you remain responsible for any uncollected amounts</li>
                </ul>
                <p>We reserve the right to correct any errors or mistakes in pricing, even if we have already requested or received payment.</p>
            </section>
            
            <section class="terms-section">
                <h2>Refunds</h2>
                <p>All sales are final and no refund will be issued except in our sole discretion. If you believe you are entitled to a refund, please contact us with your order details and the reason for your refund request.</p>
            </section>
            
            <section class="terms-section">
                <h2>Intellectual Property</h2>
                <p>The Service and its original content, features, and functionality are and will remain the exclusive property of Visafy and its licensors. The Service is protected by copyright, trademark, and other laws of both Canada and foreign countries.</p>
                <p>Our trademarks and trade dress may not be used in connection with any product or service without the prior written consent of Visafy.</p>
            </section>
            
            <section class="terms-section">
                <h2>User Content</h2>
                <p>Our Service allows you to post, link, store, share and otherwise make available certain information, text, graphics, videos, or other material. You are responsible for the content that you post, including its legality, reliability, and appropriateness.</p>
                <p>By posting content on our Service, you grant us the right to use, modify, publicly perform, publicly display, reproduce, and distribute such content on and through the Service. You retain any and all of your rights to any content you submit, post, or display on or through the Service and you are responsible for protecting those rights.</p>
            </section>
            
            <section class="terms-section">
                <h2>Links To Other Web Sites</h2>
                <p>Our Service may contain links to third-party web sites or services that are not owned or controlled by Visafy.</p>
                <p>Visafy has no control over, and assumes no responsibility for, the content, privacy policies, or practices of any third-party web sites or services. You further acknowledge and agree that Visafy shall not be responsible or liable, directly or indirectly, for any damage or loss caused or alleged to be caused by or in connection with use of or reliance on any such content, goods or services available on or through any such web sites or services.</p>
            </section>
            
            <section class="terms-section">
                <h2>Termination</h2>
                <p>We may terminate or suspend your account immediately, without prior notice or liability, for any reason whatsoever, including without limitation if you breach the Terms.</p>
                <p>Upon termination, your right to use the Service will immediately cease. If you wish to terminate your account, you may simply discontinue using the Service or contact us to request account deletion.</p>
            </section>
            
            <section class="terms-section">
                <h2>Limitation of Liability</h2>
                <p>In no event shall Visafy, nor its directors, employees, partners, agents, suppliers, or affiliates, be liable for any indirect, incidental, special, consequential or punitive damages, including without limitation, loss of profits, data, use, goodwill, or other intangible losses, resulting from:</p>
                <ul>
                    <li>Your access to or use of or inability to access or use the Service;</li>
                    <li>Any conduct or content of any third party on the Service;</li>
                    <li>Any content obtained from the Service; and</li>
                    <li>Unauthorized access, use or alteration of your transmissions or content.</li>
                </ul>
            </section>
            
            <section class="terms-section">
                <h2>Disclaimer</h2>
                <p>Your use of the Service is at your sole risk. The Service is provided on an "AS IS" and "AS AVAILABLE" basis. The Service is provided without warranties of any kind, whether express or implied, including, but not limited to, implied warranties of merchantability, fitness for a particular purpose, non-infringement or course of performance.</p>
                <p>Visafy does not warrant that:</p>
                <ul>
                    <li>The Service will function uninterrupted, secure or available at any particular time or location;</li>
                    <li>Any errors or defects will be corrected;</li>
                    <li>The Service is free of viruses or other harmful components; or</li>
                    <li>The results of using the Service will meet your requirements.</li>
                </ul>
            </section>
            
            <section class="terms-section">
                <h2>Governing Law</h2>
                <p>These Terms shall be governed and construed in accordance with the laws of Ontario, Canada, without regard to its conflict of law provisions.</p>
                <p>Our failure to enforce any right or provision of these Terms will not be considered a waiver of those rights. If any provision of these Terms is held to be invalid or unenforceable by a court, the remaining provisions of these Terms will remain in effect.</p>
            </section>
            
            <section class="terms-section">
                <h2>Changes to Terms</h2>
                <p>We reserve the right, at our sole discretion, to modify or replace these Terms at any time. We will provide notice of any changes by posting the new Terms on this page and updating the "Last Updated" date.</p>
                <p>By continuing to access or use our Service after those revisions become effective, you agree to be bound by the revised terms. If you do not agree to the new terms, please stop using the Service.</p>
            </section>
            
            <section class="terms-section">
                <h2>Contact Us</h2>
                <p>If you have any questions about these Terms, please contact us at:</p>
                <ul class="contact-list">
                    <li><strong>Email:</strong> legal@visafy.com</li>
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

.terms-hero {
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

.terms-content {
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

.terms-section {
    margin-bottom: 40px;
}

.terms-section h2 {
    color: var(--dark-blue);
    font-size: 1.8rem;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
}

.terms-section p {
    margin-bottom: 15px;
    line-height: 1.6;
    color: var(--text-color);
}

.terms-section ul, .terms-section ol {
    margin-bottom: 20px;
    padding-left: 20px;
}

.terms-section li {
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
    .terms-content {
        padding: 30px 20px;
    }
    
    .hero-title {
        font-size: 2rem;
    }
    
    .terms-section h2 {
        font-size: 1.5rem;
    }
}
</style>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
