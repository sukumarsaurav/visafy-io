<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

// Include session management
require_once "includes/session.php";

// Include config files
require_once "config/db_connect.php";

$page_title = "Visa Applicants";
require_once 'includes/header.php';
require_once 'includes/functions.php';
?>

<!-- Hero Section -->
<section class="hero applicant-hero">
    <div class="container">
        <div class="hero-content">
            <div class="hero-text">
                <h1 class="hero-title">Your Immigration Journey Made Simple</h1>
                <p class="hero-subtitle">Connect with licensed immigration professionals, track your application progress, and get expert guidance throughout your visa process</p>
                <div class="hero-buttons">
                    <a href="register.php?type=applicant" class="btn btn-primary">Join as Applicant</a>
                    <a href="eligibility-test.php" class="btn btn-secondary">Check Eligibility</a>
                </div>
            </div>
            <div class="hero-image-container">
                <img src="assets/images/Booking-review.png" alt="Main Consultant" class="hero-image">
            </div>
        </div>
    </div>
</section>

<div class="content">
    <!-- Platform Features Section -->
    <section class="section platform-features-section bg-white">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Why Choose Visafy as an Applicant</h2>
            <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">Our platform is designed to simplify your immigration journey</p>
            
            <div class="features-showcase-container">
                <!-- Expert Access Section -->
                <div class="feature-showcase-item" data-aos="fade-up">
                    <div class="feature-showcase-content">
                        <div class="feature-text-content">
                            <h3>Access to Verified Professionals</h3>
                            <p class="feature-description">
                                Connect with licensed and verified immigration consultants specializing in your destination country.
                            </p>
                            <ul class="feature-list">
                                <li>
                                    <strong>Verified Credentials</strong>
                                    <p>All consultants on our platform are verified for their credentials and licensing</p>
                                </li>
                                <li>
                                    <strong>Transparent Reviews</strong>
                                    <p>Read genuine reviews from other applicants before choosing your consultant</p>
                                </li>
                                <li>
                                    <strong>Specialized Expertise</strong>
                                    <p>Find consultants who specialize in your specific visa category or destination</p>
                                </li>
                            </ul>
                        </div>
                        <div class="feature-image-wrapper">
                            <div class="svg-background">
                                <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" class="shape shape-1">
                                    <path d="M42.7,-73.4C55.9,-67.1,67.7,-57.2,75.9,-44.6C84.1,-32,88.7,-16,88.1,-0.3C87.5,15.3,81.8,30.6,73.1,43.9C64.4,57.2,52.8,68.5,39.1,75.3C25.4,82.1,9.7,84.4,-5.9,83.1C-21.5,81.8,-37,76.9,-50.9,68.5C-64.8,60.1,-77.1,48.3,-83.3,33.8C-89.5,19.3,-89.6,2.2,-85.1,-13.2C-80.6,-28.6,-71.5,-42.3,-59.8,-51.6C-48.1,-60.9,-33.8,-65.8,-20.4,-70.3C-7,-74.8,5.5,-78.9,18.8,-79.1C32.1,-79.3,46.2,-75.6,42.7,-73.4Z" transform="translate(100 100)" />
                                </svg>
                            </div>
                            <div class="feature-img-container">
                                <img src="assets/images/applicant-maindashboard.png" alt="Expert Access">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Application Tracking Section -->
                <div class="feature-showcase-item" data-aos="fade-up">
                    <div class="feature-showcase-content feature-reverse">
                        <div class="feature-image-wrapper">
                            <div class="svg-background">
                                <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" class="shape shape-3">
                                    <path d="M39.9,-68.1C52.6,-62.1,64.5,-53.1,72.7,-41C80.9,-28.8,85.4,-14.4,83.9,-0.9C82.3,12.7,74.8,25.4,66.4,37.8C58,50.3,48.7,62.5,36.5,70.1C24.2,77.7,9.1,80.7,-5.9,79.5C-20.9,78.3,-35.9,72.9,-47.5,64C-59.1,55,-67.3,42.5,-73.4,28.5C-79.5,14.5,-83.5,-1,-80.8,-15.2C-78.1,-29.4,-68.7,-42.3,-56.8,-48.9C-44.9,-55.5,-30.5,-55.8,-17.7,-61.8C-4.9,-67.8,6.3,-79.5,18.4,-80.5C30.5,-81.5,43.5,-71.8,39.9,-68.1Z" transform="translate(100 100)" />
                                </svg>
                            </div>
                            <div class="feature-img-container">
                                <img src="assets/images/applicant-meetings.png" alt="Application Tracking">
                            </div>
                        </div>
                        <div class="feature-text-content">
                            <h3>Real-Time Application Tracking</h3>
                            <p class="feature-description">
                                Stay informed about your visa application status with our comprehensive tracking system.
                            </p>
                            <ul class="feature-list">
                                <li>
                                    <strong>Progress Timeline</strong>
                                    <p>Visual timeline showing each stage of your application process</p>
                                </li>
                                <li>
                                    <strong>Instant Updates</strong>
                                    <p>Receive notifications when your application status changes or requires action</p>
                                </li>
                                <li>
                                    <strong>Milestone Tracking</strong>
                                    <p>Clear tracking of completed steps and upcoming requirements</p>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Document Management Section -->
                <div class="feature-showcase-item" data-aos="fade-up">
                    <div class="feature-showcase-content">
                        <div class="feature-text-content">
                            <h3>Secure Document Management</h3>
                            <p class="feature-description">
                                Manage all your important documents in one secure, centralized location.
                            </p>
                            <ul class="feature-list">
                                <li>
                                    <strong>Cloud Storage</strong>
                                    <p>All your documents safely stored and accessible from anywhere</p>
                                </li>
                                <li>
                                    <strong>Document Checklist</strong>
                                    <p>Customized checklists of required documents based on your visa type</p>
                                </li>
                                <li>
                                    <strong>Secure Sharing</strong>
                                    <p>Share documents with your consultant securely with detailed access control</p>
                                </li>
                            </ul>
                        </div>
                        <div class="feature-image-wrapper">
                            <div class="svg-background">
                                <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" class="shape shape-5">
                                    <path d="M48.2,-76.1C63.3,-69.2,77.2,-58.4,84.6,-44.2C92,-30,92.8,-12.5,89.6,3.7C86.3,19.9,78.9,34.8,68.9,47.9C58.9,61,46.2,72.3,31.5,77.8C16.8,83.2,0.1,82.8,-16.4,79.7C-32.9,76.6,-49.2,70.8,-62.7,60.3C-76.2,49.8,-87,34.6,-90.9,17.8C-94.8,0.9,-91.9,-17.5,-84.2,-32.8C-76.5,-48.1,-64,-60.2,-49.5,-67.5C-35,-74.8,-18.5,-77.3,-1.2,-75.5C16.1,-73.7,33.1,-83,48.2,-76.1Z" transform="translate(100 100)" />
                                </svg>
                            </div>
                            <div class="feature-img-container">
                                <img src="assets/images/documet-applicant.png" alt="Document Management">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Communication Section -->
                <div class="feature-showcase-item" data-aos="fade-up">
                    <div class="feature-showcase-content feature-reverse">
                        <div class="feature-image-wrapper">
                            <div class="svg-background">
                                <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" class="shape shape-7">
                                    <path d="M39.9,-68.1C52.6,-62.1,64.5,-53.1,72.7,-41C80.9,-28.8,85.4,-14.4,83.9,-0.9C82.3,12.7,74.8,25.4,66.4,37.8C58,50.3,48.7,62.5,36.5,70.1C24.2,77.7,9.1,80.7,-5.9,79.5C-20.9,78.3,-35.9,72.9,-47.5,64C-59.1,55,-67.3,42.5,-73.4,28.5C-79.5,14.5,-83.5,-1,-80.8,-15.2C-78.1,-29.4,-68.7,-42.3,-56.8,-48.9C-44.9,-55.5,-30.5,-55.8,-17.7,-61.8C-4.9,-67.8,6.3,-79.5,18.4,-80.5C30.5,-81.5,43.5,-71.8,39.9,-68.1Z" transform="translate(100 100)" />
                                </svg>
                            </div>
                            <div class="feature-img-container">
                                <img src="assets/images/applicant-message.png" alt="Direct Communication">
                            </div>
                        </div>
                        <div class="feature-text-content">
                            <h3>Direct Communication Channels</h3>
                            <p class="feature-description">
                                Stay connected with your immigration consultant through our integrated messaging system.
                            </p>
                            <ul class="feature-list">
                                <li>
                                    <strong>Real-Time Messaging</strong>
                                    <p>Chat directly with your consultant about urgent questions and updates</p>
                                </li>
                                <li>
                                    <strong>Message History</strong>
                                    <p>Full access to your complete conversation history for reference</p>
                                </li>
                                <li>
                                    <strong>File Sharing</strong>
                                    <p>Easily share documents and information within the messaging system</p>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    
    <!-- Feature Categories -->
    <section class="feature-categories">
        <div class="container">
            <h2 class="section-title">Key Features for Applicants</h2>
            <div class="categories-grid">
                <!-- Application Management -->
                <div class="category-card" data-category="application">
                    <div class="category-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h3>Application Management</h3>
                    <ul class="feature-list">
                        <li>Real-time status tracking</li>
                        <li>Document checklists</li>
                        <li>Application history</li>
                        <li>Timeline visualization</li>
                    </ul>
                </div>

                <!-- Consultant Connection -->
                <div class="category-card" data-category="consultant">
                    <div class="category-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <h3>Consultant Connection</h3>
                    <ul class="feature-list">
                        <li>Browse verified consultants</li>
                        <li>View ratings and reviews</li>
                        <li>Direct messaging</li>
                        <li>Consultation scheduling</li>
                    </ul>
                </div>

                <!-- Resource Access -->
                <div class="category-card" data-category="resources">
                    <div class="category-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <h3>Resource Center</h3>
                    <ul class="feature-list">
                        <li>Country-specific guides</li>
                        <li>Visa requirement updates</li>
                        <li>Process explainers</li>
                        <li>FAQ library</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Steps Section -->
<section class="section steps">
    <div class="container">
        <h2 class="section-title" data-aos="fade-up">Start Your Visa Journey in 4 Simple Steps</h2>
        <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">Our streamlined process gets you from application to approval with expert guidance every step of the way</p>

        <div class="steps-container">
            <!-- Step 1 -->
            <div class="step-card" data-aos="fade-up" data-aos-delay="200">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h3>Create Your Profile</h3>
                    <p>Sign up and complete your applicant profile with personal details, education history, work experience, and your immigration goals. The more complete your profile, the better guidance we can provide.</p>
                    <a href="register.php?type=applicant" class="btn btn-outline">Sign Up Now</a>
                </div>
            </div>

            <!-- Step 2 -->
            <div class="step-card" data-aos="fade-up" data-aos-delay="300">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h3>Take Eligibility Assessment</h3>
                    <p>Complete our comprehensive eligibility assessment to determine which immigration programs you may qualify for. Receive a detailed report with your options and recommended next steps.</p>
                    <a href="eligibility-test.php" class="btn btn-outline">Check Eligibility</a>
                </div>
            </div>

            <!-- Step 3 -->
            <div class="step-card" data-aos="fade-up" data-aos-delay="400">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h3>Connect with a Consultant</h3>
                    <p>Browse our network of verified immigration consultants. Filter by specialization, language, location, and client ratings to find the perfect match for your needs. Book a consultation with your chosen expert.</p>
                    <a href="book-consultation.php" class="btn btn-outline">Find Consultants</a>
                </div>
            </div>

            <!-- Step 4 -->
            <div class="step-card" data-aos="fade-up" data-aos-delay="500">
                <div class="step-number">4</div>
                <div class="step-content">
                    <h3>Start Your Application</h3>
                    <p>With your consultant's guidance, begin the application process. Upload required documents, complete forms, and track your progress through our intuitive dashboard. Receive updates at every stage of your journey.</p>
                    <a href="book-service.php" class="btn btn-outline">Get Started</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
