<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

// Include session management
require_once "includes/session.php";

// Include config files
require_once "config/db_connect.php";

$page_title = "Become a Member";
require_once 'includes/header.php';
require_once 'includes/functions.php';

// Get membership plans
$query = "SELECT * FROM membership_plans ORDER BY price ASC";
$result = $conn->query($query);
$plans = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $plans[] = $row;
    }
}
?>

<!-- Hero Section -->
<section class="hero consultant-hero">
    <div class="container">
        <div class="hero-content">
            <div class="hero-text">
                <h1 class="hero-title">Manage your Immigration Practice. Grow your Business.</h1>
                <p class="hero-subtitle">Comprehensive practice management platform designed specifically for immigration consultants. Try it for free today.</p>
            </div>
            <div class="hero-image-container">
                <img src="assets/images/become-member-hero.png" alt="Main Consultant" class="hero-image">
            </div>
        </div>
    </div>
</section>

<div class="content">
    <!-- Consultant Benefits Section -->
    <div class="registration-container" id="membership-plans">
        <div class="membership-plans">
            <h2>Choose Your Membership Plan</h2>
            <p>Select the plan that best fits your business needs</p>
            
            <!-- Membership Plans -->
            <div class="plans-grid">
                <?php
                if (count($plans) > 0) {
                    foreach ($plans as $plan): 
                ?>
                    <div class="plan-card">
                        <div class="plan-header">
                            <h3 class="plan-name"><?php echo htmlspecialchars($plan['name']); ?></h3>
                            <div class="plan-price">$<?php echo number_format($plan['price'], 2); ?></div>
                            <div class="plan-billing">per month</div>
                        </div>
                        <div class="plan-features">
                            <div class="feature">
                                <i class="fas fa-users"></i>
                                <div>Up to <?php echo (int)$plan['max_team_members']; ?> team members</div>
                            </div>
                            <div class="feature">
                                <i class="fas fa-robot"></i>
                                <div><?php echo $plan['name'] === 'Bronze' ? '40' : ($plan['name'] === 'Silver' ? '80' : '150'); ?> AI chat messages/month</div>
                            </div>
                            <div class="feature">
                                <i class="fas fa-check-circle"></i>
                                <div>Full platform access</div>
                            </div>
                            <div class="feature">
                                <i class="fas fa-check-circle"></i>
                                <div>Priority support</div>
                            </div>
                        </div>
                        <div class="plan-action">
                            <a href="consultant-registration.php?plan_id=<?php echo $plan['id']; ?>" class="btn select-plan-btn">
                                Select Plan
                            </a>
                        </div>
                    </div>
                <?php 
                    endforeach; 
                } else {
                    echo '<div class="no-plans-message">No plans are currently available. Please check back later.</div>';
                }
                ?>
            </div>
        </div>
    </div>
    
    <section class="section platform-features-section bg-white">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Why Join Visafy as a Consultant</h2>
            <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">Our platform is designed to help you deliver exceptional immigration services</p>
            
            <div class="features-showcase-container">
                <!-- Client Management Section -->
                <div class="feature-showcase-item" data-aos="fade-up">
                    <div class="feature-showcase-content">
                        <div class="feature-text-content">
                            <h3>Comprehensive Client Management</h3>
                            <p class="feature-description">
                                Manage your entire client base efficiently with our powerful tools.
                            </p>
                            <ul class="feature-list">
                                <li>
                                    <strong>Client Profiles & History</strong>
                                    <p>Maintain detailed client profiles with complete application history and relationship tracking</p>
                                </li>
                                <li>
                                    <strong>Document Management</strong>
                                    <p>Secure document storage, version control, and easy sharing with clients</p>
                                </li>
                                <li>
                                    <strong>Direct Messaging</strong>
                                    <p>Integrated messaging system for direct communication with clients and team members</p>
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
                                <img src="assets/images/main-consultant.png" alt="Client Management">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Booking Management Section -->
                <div class="feature-showcase-item" data-aos="fade-up">
                    <div class="feature-showcase-content feature-reverse">
                        <div class="feature-image-wrapper">
                            <div class="svg-background">
                                <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" class="shape shape-3">
                                    <path d="M39.9,-68.1C52.6,-62.1,64.5,-53.1,72.7,-41C80.9,-28.8,85.4,-14.4,83.9,-0.9C82.3,12.7,74.8,25.4,66.4,37.8C58,50.3,48.7,62.5,36.5,70.1C24.2,77.7,9.1,80.7,-5.9,79.5C-20.9,78.3,-35.9,72.9,-47.5,64C-59.1,55,-67.3,42.5,-73.4,28.5C-79.5,14.5,-83.5,-1,-80.8,-15.2C-78.1,-29.4,-68.7,-42.3,-56.8,-48.9C-44.9,-55.5,-30.5,-55.8,-17.7,-61.8C-4.9,-67.8,6.3,-79.5,18.4,-80.5C30.5,-81.5,43.5,-71.8,39.9,-68.1Z" transform="translate(100 100)" />
                                </svg>
                            </div>
                            <div class="feature-img-container">
                                <img src="assets/images/meetings.png" alt="Booking Management">
                            </div>
                        </div>
                        <div class="feature-text-content">
                            <h3>Advanced Booking Management</h3>
                            <p class="feature-description">
                                Streamline your consultation scheduling and client management process.
                            </p>
                            <ul class="feature-list">
                                <li>
                                    <strong>Comprehensive Booking System</strong>
                                    <p>Manage all bookings with status filtering, team assignment, and detailed tracking</p>
                                </li>
                                <li>
                                    <strong>Team Assignment</strong>
                                    <p>Assign bookings to team members and track their performance</p>
                                </li>
                                <li>
                                    <strong>Advanced Filtering</strong>
                                    <p>Filter bookings by status, date ranges, and search through all records</p>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Service Management Section -->
                <div class="feature-showcase-item" data-aos="fade-up">
                    <div class="feature-showcase-content">
                        <div class="feature-text-content">
                            <h3>Service & Team Management</h3>
                            <p class="feature-description">
                                Optimize your service delivery and team collaboration.
                            </p>
                            <ul class="feature-list">
                                <li>
                                    <strong>Visa Service Management</strong>
                                    <p>Create and manage visa service offerings with flexible pricing and consultation modes</p>
                                </li>
                                <li>
                                    <strong>Team Collaboration</strong>
                                    <p>Invite team members, assign tasks, and manage team performance</p>
                                </li>
                                <li>
                                    <strong>Task Management</strong>
                                    <p>Create tasks, set priorities, assign deadlines, and track progress</p>
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
                                <img src="assets/images/team-management.png" alt="Service Management">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AI & Analytics Section -->
                <div class="feature-showcase-item" data-aos="fade-up">
                    <div class="feature-showcase-content feature-reverse">
                        <div class="feature-image-wrapper">
                            <div class="svg-background">
                                <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" class="shape shape-4">
                                    <path d="M47.3,-79.7C62.9,-71.9,78.5,-62.3,86.4,-48.3C94.3,-34.3,94.5,-15.7,90.3,0.9C86.1,17.4,77.5,31.8,67.2,44.7C56.9,57.6,44.9,69,30.7,76.2C16.5,83.4,0.1,86.4,-16.4,83.3C-32.9,80.2,-45.5,71,-57.8,59C-70.1,47,-80.1,32.2,-84.6,15.6C-89.1,-1,-88.1,-19.4,-81.5,-35.1C-74.9,-50.8,-62.7,-63.8,-48.1,-72.1C-33.5,-80.4,-16.7,-84,0.2,-84.4C17.2,-84.8,34.3,-82,47.3,-79.7Z" transform="translate(100 100)" />
                                </svg>
                            </div>
                            <div class="feature-img-container">
                                <img src="assets/images/ai-chat.png" alt="AI & Analytics">
                            </div>
                        </div>
                        <div class="feature-text-content">
                            <h3>AI Assistance & Analytics</h3>
                            <p class="feature-description">
                                Leverage AI technology and comprehensive analytics to grow your practice.
                            </p>
                            <ul class="feature-list">
                                <li>
                                    <strong>AI Chat System</strong>
                                    <p>Get AI assistance with monthly message limits based on your membership plan</p>
                                </li>
                                <li>
                                    <strong>Performance Analytics</strong>
                                    <p>Track booking statistics, client counts, team performance, and service metrics</p>
                                </li>
                                <li>
                                    <strong>Smart Notifications</strong>
                                    <p>Stay updated with real-time notifications and system alerts</p>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <section class="feature-categories">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Complete Platform Features</h2>
            <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">Everything you need to manage your immigration practice efficiently</p>
            
            <div class="categories-grid">
                <!-- Client Management -->
                <div class="category-card" data-category="client">
                    <div class="category-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Client Management</h3>
                    <ul class="feature-list">
                        <li>Client profiles & application history</li>
                        <li>Relationship tracking & management</li>
                        <li>Direct messaging system</li>
                        <li>Document storage & sharing</li>
                        <li>Client account activation/deactivation</li>
                    </ul>
                </div>

                <!-- Booking Management -->
                <div class="category-card" data-category="booking">
                    <div class="category-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3>Booking Management</h3>
                    <ul class="feature-list">
                        <li>Comprehensive booking system</li>
                        <li>Status filtering & tracking</li>
                        <li>Team assignment capabilities</li>
                        <li>Date range filtering</li>
                        <li>Advanced search functionality</li>
                    </ul>
                </div>

                <!-- Service Management -->
                <div class="category-card" data-category="service">
                    <div class="category-icon">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <h3>Service Management</h3>
                    <ul class="feature-list">
                        <li>Visa service customization</li>
                        <li>Service type management</li>
                        <li>Consultation mode options</li>
                        <li>Flexible pricing structure</li>
                        <li>Service availability settings</li>
                    </ul>
                </div>

                <!-- Team Management -->
                <div class="category-card" data-category="team">
                    <div class="category-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <h3>Team Management</h3>
                    <ul class="feature-list">
                        <li>Team member invitations</li>
                        <li>Role-based access control</li>
                        <li>Task assignment & tracking</li>
                        <li>Performance monitoring</li>
                        <li>Organization structure management</li>
                    </ul>
                </div>

                <!-- AI & Analytics -->
                <div class="category-card" data-category="ai">
                    <div class="category-icon">
                        <i class="fas fa-robot"></i>
                    </div>
                    <h3>AI & Analytics</h3>
                    <ul class="feature-list">
                        <li>AI chat assistance</li>
                        <li>Usage tracking & limits</li>
                        <li>Performance analytics</li>
                        <li>Real-time notifications</li>
                        <li>System monitoring</li>
                    </ul>
                </div>

                <!-- Document Management -->
                <div class="category-card" data-category="documents">
                    <div class="category-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h3>Document Management</h3>
                    <ul class="feature-list">
                        <li>Document categorization</li>
                        <li>Template management</li>
                        <li>Version control</li>
                        <li>Secure storage</li>
                        <li>Easy sharing & access</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
</div>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const categoryCards = document.querySelectorAll('.category-card');

    // Add hover effects and animations
    categoryCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = '0 10px 25px rgba(0,0,0,0.15)';
        });

        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 5px 15px rgba(0,0,0,0.1)';
        });
    });
});
</script>