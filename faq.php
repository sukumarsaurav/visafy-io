<?php
// Include session management
require_once "includes/session.php";

$page_title = "FAQ | Visafy - Canadian Immigration Consultancy";
include('includes/header.php');
?>

<!-- Hero Section -->
<section class="hero faq-hero">
    <div class="container">
        <div class="hero-content text-center">
            <h1 class="hero-title">Frequently Asked Questions</h1>
            <p class="hero-subtitle">Find answers to common questions about immigration and our services</p>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="section faq">
    <div class="container">
        <div class="faq-container">
            <!-- FAQ Categories -->
            <div class="faq-categories" data-aos="fade-right">
                <h3>Categories</h3>
                <ul class="category-list">
                    <li class="active" data-category="general">General Questions</li>
                    <li data-category="services">Our Services</li>
                    <li data-category="process">Immigration Process</li>
                    <li data-category="documents">Documentation</li>
                    <li data-category="fees">Fees & Payments</li>
                    <li data-category="platform">Platform Usage</li>
                </ul>
            </div>

            <!-- FAQ Content -->
            <div class="faq-content" data-aos="fade-left">
                <!-- General Questions -->
                <div class="faq-group active" id="general">
                    <div class="faq-item">
                        <div class="faq-question">
                            <h3>What is Visafy and how can it help me?</h3>
                            <span class="toggle-icon"></span>
                        </div>
                        <div class="faq-answer">
                            <p>Visafy is a comprehensive immigration platform that connects applicants with licensed immigration consultants. We simplify the immigration process by providing digital tools, expert guidance, and real-time application tracking to ensure a smooth journey towards your immigration goals.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <h3>Are your immigration consultants licensed?</h3>
                            <span class="toggle-icon"></span>
                        </div>
                        <div class="faq-answer">
                            <p>Yes, all our immigration consultants are licensed by ICCRC (Immigration Consultants of Canada Regulatory Council) and maintain good standing. You can verify their credentials through the ICCRC registry using their membership numbers provided on their profiles.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <h3>How do I get started with Visafy?</h3>
                            <span class="toggle-icon"></span>
                        </div>
                        <div class="faq-answer">
                            <p>Getting started is easy:</p>
                            <ol>
                                <li>Create a free account</li>
                                <li>Complete our eligibility assessment</li>
                                <li>Book a consultation with an expert</li>
                                <li>Begin your immigration journey with our guidance</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <!-- Services -->
                <div class="faq-group" id="services">
                    <div class="faq-item">
                        <div class="faq-question">
                            <h3>What immigration services do you offer?</h3>
                            <span class="toggle-icon"></span>
                        </div>
                        <div class="faq-answer">
                            <p>We offer comprehensive immigration services including:</p>
                            <ul>
                                <li>Express Entry applications</li>
                                <li>Study permits</li>
                                <li>Work permits</li>
                                <li>Family sponsorship</li>
                                <li>Provincial Nominee Programs</li>
                                <li>Business immigration</li>
                                <li>Visitor visas</li>
                            </ul>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <h3>Do you provide document translation services?</h3>
                            <span class="toggle-icon"></span>
                        </div>
                        <div class="faq-answer">
                            <p>Yes, we provide certified translation services for all immigration-related documents through our network of authorized translators. We ensure all translations meet IRCC requirements.</p>
                        </div>
                    </div>
                </div>

                <!-- Process -->
                <div class="faq-group" id="process">
                    <div class="faq-item">
                        <div class="faq-question">
                            <h3>How long does the immigration process take?</h3>
                            <span class="toggle-icon"></span>
                        </div>
                        <div class="faq-answer">
                            <p>Processing times vary depending on the type of application and current IRCC processing times. During your consultation, your immigration consultant will provide an estimated timeline based on your specific case and the latest processing times.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <h3>Can I track my application status?</h3>
                            <span class="toggle-icon"></span>
                        </div>
                        <div class="faq-answer">
                            <p>Yes, through our platform you can track your application status in real-time. You'll receive notifications for important updates and can view detailed progress through your personalized dashboard.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Still Have Questions Section -->
<section class="section contact-cta">
    <div class="container">
        <div class="cta-content text-center" data-aos="fade-up">
            <h2>Still Have Questions?</h2>
            <p>Can't find the answer you're looking for? Our team is here to help!</p>
            <div class="cta-buttons">
                <a href="contact.php" class="btn btn-primary">Contact Us</a>
                <a href="book-consultation.php" class="btn btn-secondary">Book a Consultation</a>
            </div>
        </div>
    </div>
</section>

<style>
/* FAQ Page Styles */
.faq-hero {
    background-color: var(--primary-light);
    padding: 4rem 0;
}

.faq-container {
    display: grid;
    grid-template-columns: 250px 1fr;
    gap: 2rem;
    margin-top: 2rem;
}

/* Categories Sidebar */
.faq-categories {
    background: var(--white);
    padding: 1.5rem;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
}

.faq-categories h3 {
    margin-bottom: 1rem;
    color: var(--dark-color);
    font-size: 1.25rem;
}

.category-list {
    list-style: none;
    padding: 0;
}

.category-list li {
    padding: 0.75rem 1rem;
    margin-bottom: 0.5rem;
    border-radius: var(--radius);
    cursor: pointer;
    transition: var(--transition);
    color: var(--text-color);
}

.category-list li:hover {
    background-color: var(--primary-light);
    color: var(--primary-color);
}

.category-list li.active {
    background-color: var(--primary-color);
    color: var(--white);
}

/* FAQ Content */
.faq-content {
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
}

.faq-group {
    display: none;
}

.faq-group.active {
    display: block;
}

.faq-item {
    border-bottom: 1px solid var(--border-color);
}

.faq-question {
    padding: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
}

.faq-question h3 {
    margin: 0;
    font-size: 1.1rem;
    color: var(--dark-color);
}

.toggle-icon {
    width: 24px;
    height: 24px;
    position: relative;
}

.toggle-icon::before,
.toggle-icon::after {
    content: '';
    position: absolute;
    background-color: var(--primary-color);
    transition: var(--transition);
}

.toggle-icon::before {
    width: 2px;
    height: 16px;
    top: 4px;
    left: 11px;
}

.toggle-icon::after {
    width: 16px;
    height: 2px;
    top: 11px;
    left: 4px;
}

.faq-item.active .toggle-icon::before {
    transform: rotate(90deg);
}

.faq-answer {
    padding: 0 1.5rem;
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease-out;
}

.faq-item.active .faq-answer {
    padding: 0 1.5rem 1.5rem;
    max-height: 500px;
}

.faq-answer p {
    margin-bottom: 1rem;
    color: var(--text-color);
    line-height: 1.6;
}

.faq-answer ul,
.faq-answer ol {
    padding-left: 1.5rem;
    margin-bottom: 1rem;
}

.faq-answer li {
    margin-bottom: 0.5rem;
    color: var(--text-color);
}

/* Contact CTA Section */
.contact-cta {
    background-color: var(--primary-light);
    padding: 4rem 0;
}

.cta-content {
    max-width: 600px;
    margin: 0 auto;
}

.cta-content h2 {
    margin-bottom: 1rem;
    color: var(--dark-color);
}

.cta-content p {
    margin-bottom: 2rem;
    color: var(--text-color);
}

.cta-buttons {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

/* Responsive Design */
@media (max-width: 768px) {
    .faq-container {
        grid-template-columns: 1fr;
    }

    .faq-categories {
        margin-bottom: 1rem;
    }

    .category-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .category-list li {
        margin-bottom: 0;
    }

    .cta-buttons {
        flex-direction: column;
    }

    .btn {
        width: 100%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // FAQ Category Switching
    const categoryButtons = document.querySelectorAll('.category-list li');
    const faqGroups = document.querySelectorAll('.faq-group');

    categoryButtons.forEach(button => {
        button.addEventListener('click', () => {
            // Remove active class from all buttons and groups
            categoryButtons.forEach(btn => btn.classList.remove('active'));
            faqGroups.forEach(group => group.classList.remove('active'));

            // Add active class to clicked button and corresponding group
            button.classList.add('active');
            const category = button.getAttribute('data-category');
            document.getElementById(category).classList.add('active');
        });
    });

    // FAQ Item Toggle
    const faqItems = document.querySelectorAll('.faq-item');

    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question');
        question.addEventListener('click', () => {
            const isActive = item.classList.contains('active');
            
            // Close all other items
            faqItems.forEach(otherItem => {
                if (otherItem !== item) {
                    otherItem.classList.remove('active');
                }
            });

            // Toggle current item
            item.classList.toggle('active');
        });
    });

    // Initialize AOS
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true
        });
    }
});
</script>

<?php include('includes/footer.php'); ?>
