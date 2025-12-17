<?php
// Include session management
require_once "includes/session.php";

// Get consultant ID from URL
$consultant_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Database connection
require_once "config/db_connect.php";

// Fetch consultant profile data
$query = "SELECT 
    u.id,
    u.first_name,
    u.last_name,
    u.email,
    u.phone,
    u.profile_picture,
    c.company_name,
    cp.bio,
    cp.specializations,
    cp.years_experience,
    cp.education,
    cp.certifications,
    cp.languages,
    cp.profile_image,
    cp.banner_image,
    cp.website,
    cp.social_linkedin,
    cp.social_twitter,
    cp.social_facebook,
    cp.is_verified,
    o.name as organization_name,
    o.id as organization_id,
    COUNT(DISTINCT b.id) as total_bookings,
    ROUND(AVG(bf.rating), 1) as average_rating,
    COUNT(DISTINCT bf.id) as total_reviews
FROM users u
JOIN consultants c ON u.id = c.user_id
LEFT JOIN consultant_profiles cp ON u.id = cp.consultant_id
LEFT JOIN organizations o ON u.organization_id = o.id
LEFT JOIN bookings b ON u.id = b.consultant_id AND b.deleted_at IS NULL
LEFT JOIN booking_feedback bf ON b.id = bf.booking_id
WHERE u.id = ? AND u.user_type = 'consultant' AND u.status = 'active'
GROUP BY u.id";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $consultant_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: /404.php");
    exit();
}

$consultant = $result->fetch_assoc();

// Set page title
$page_title = $consultant['first_name'] . " " . $consultant['last_name'] . " | Immigration Consultant";
include('includes/header.php');
?>

<!-- Profile Header Section -->
<section class="profile-header">
    <div class="profile-banner-flex">
        <div class="profile-header-flex">
            <div class="header-profile-image-overlap">
                <?php 
                // Fix profile picture path - add 'uploads/' if not present
                $profile_img = '/assets/images/default-profile.svg';
                if (!empty($consultant['profile_picture'])) {
                    $profile_picture = $consultant['profile_picture'];
                    if (strpos($profile_picture, 'users/') === 0) {
                        if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $profile_picture)) {
                            $profile_img = '/uploads/' . $profile_picture;
                        }
                    }
                }
                ?>
                <img src="<?php echo htmlspecialchars($profile_img); ?>"
                    alt="<?php echo htmlspecialchars($consultant['first_name'] . ' ' . $consultant['last_name']); ?>">
                <?php if ($consultant['is_verified']): ?>
                <div class="verified-badge" title="Verified Consultant">
                    <i class="fas fa-check-circle"></i>
                </div>
                <?php endif; ?>
            </div>
            <div class="profile-details">
                <div class="profile-header-row">
                    <div>
                        <h1><?php echo htmlspecialchars($consultant['first_name'] ?? '' . ' ' . $consultant['last_name'] ?? ''); ?></h1>
                        <p class="company-name"><?php echo htmlspecialchars($consultant['company_name'] ?? ''); ?></p>
                        <div class="header-rating">
                            <span class="star-rating">
                                <i class="fas fa-star" style="color:#FFC107;"></i>
                                <?php echo number_format($consultant['average_rating'] ?? 0, 1); ?>
                            </span>
                            <span class="review-count">
                                (<?php echo $consultant['total_reviews'] ?? 0; ?>)
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <?php if (!empty($consultant['social_linkedin']) || !empty($consultant['social_twitter']) || !empty($consultant['social_facebook'])): ?>
            <div class="social-links-header">
                <?php if (!empty($consultant['social_linkedin'])): ?>
                <a href="<?php echo htmlspecialchars($consultant['social_linkedin']); ?>" target="_blank" class="social-link">
                    <i class="fab fa-linkedin"></i>
                </a>
                <?php endif; ?>
                <?php if (!empty($consultant['social_twitter'])): ?>
                <a href="<?php echo htmlspecialchars($consultant['social_twitter']); ?>" target="_blank" class="social-link">
                    <i class="fab fa-twitter"></i>
                </a>
                <?php endif; ?>
                <?php if (!empty($consultant['social_facebook'])): ?>
                <a href="<?php echo htmlspecialchars($consultant['social_facebook']); ?>" target="_blank" class="social-link">
                    <i class="fab fa-facebook"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Main Content Section -->
<section class="profile-content">
    <div class="container">
        <div class="content-grid">
            <!-- Left Column -->
            <div class="main-content">
                <!-- Tabs moved here -->
                <ul class="profile-tabs">
                    <li class="active" data-tab="about">About</li>
                    <li data-tab="education">Education & Certification</li>
                    <li data-tab="specializations">Specializations & Languages</li>
                    <li data-tab="contact">Contact Details</li>
                    <li data-tab="reviews">Reviews & Ratings</li>
                </ul>
                
                <div class="profile-tab-content">
                    <div class="tab-panel active" id="about">
                        <div class="content-section">
                            <h2>About</h2>
                            <div class="bio">
                                <?php echo nl2br(htmlspecialchars($consultant['bio'])); ?>
                            </div>
                        </div>
                    </div>
                    <div class="tab-panel" id="education">
                        <div class="content-section">
                            <h2>Education & Certification</h2>
                            <div class="experience-details">
                                <div class="detail-item">
                                    <i class="fas fa-briefcase"></i>
                                    <div>
                                        <h3>Years of Experience</h3>
                                        <p><?php echo $consultant['years_experience']; ?> years</p>
                                    </div>
                                </div>
                                <?php if (!empty($consultant['education'])): ?>
                                <div class="detail-item">
                                    <i class="fas fa-graduation-cap"></i>
                                    <div>
                                        <h3>Education</h3>
                                        <p><?php echo nl2br(htmlspecialchars($consultant['education'])); ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($consultant['certifications'])): ?>
                                <div class="detail-item">
                                    <i class="fas fa-certificate"></i>
                                    <div>
                                        <h3>Certifications</h3>
                                        <p><?php echo nl2br(htmlspecialchars($consultant['certifications'])); ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="tab-panel" id="specializations">
                        <div class="content-section">
                            <h2>Specializations & Languages</h2>
                            <div class="specializations-grid">
                                <?php if (!empty($consultant['specializations'])): ?>
                                <div class="specialization-section">
                                    <h3><i class="fas fa-star"></i> Specializations</h3>
                                    <div class="specialization-list">
                                        <?php 
                                        $specializations = explode(',', $consultant['specializations']);
                                        foreach ($specializations as $spec): 
                                        ?>
                                        <div class="specialization-item">
                                            <span><?php echo trim(htmlspecialchars($spec)); ?></span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($consultant['languages'])): ?>
                                <div class="languages-section">
                                    <h3><i class="fas fa-language"></i> Languages</h3>
                                    <div class="languages-list">
                                        <?php 
                                        $languages = explode(',', $consultant['languages']);
                                        foreach ($languages as $lang): 
                                        ?>
                                        <div class="language-item">
                                            <span><?php echo trim(htmlspecialchars($lang)); ?></span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="tab-panel" id="contact">
                        <div class="content-section">
                            <h2>Contact Details</h2>
                            <div class="contact-info-tab">
                                <?php if (!empty($consultant['email'])): ?>
                                <div class="contact-item">
                                    <i class="fas fa-envelope"></i>
                                    <a href="mailto:<?php echo htmlspecialchars($consultant['email']); ?>">
                                        <?php echo htmlspecialchars($consultant['email']); ?>
                                    </a>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($consultant['phone'])): ?>
                                <div class="contact-item">
                                    <i class="fas fa-phone"></i>
                                    <a href="tel:<?php echo htmlspecialchars($consultant['phone']); ?>">
                                        <?php echo htmlspecialchars($consultant['phone']); ?>
                                    </a>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($consultant['website'])): ?>
                                <div class="contact-item">
                                    <i class="fas fa-globe"></i>
                                    <a href="<?php echo htmlspecialchars($consultant['website']); ?>" target="_blank">
                                        Visit Website
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="tab-panel" id="reviews">
                        <div class="content-section">
                            <h2>Reviews & Ratings</h2>
                            <div class="reviews-section">
                                <div class="rating-summary">
                                    <div class="rating-average">
                                        <div class="number"><?php echo number_format($consultant['average_rating'] ?? 0, 1); ?></div>
                                        <div class="stars">
                                            <?php
                                            $rating = round($consultant['average_rating'] * 2) / 2;
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($rating >= $i) {
                                                    echo '<i class="fas fa-star"></i>';
                                                } elseif ($rating >= $i - 0.5) {
                                                    echo '<i class="fas fa-star-half-alt"></i>';
                                                } else {
                                                    echo '<i class="far fa-star"></i>';
                                                }
                                            }
                                            ?>
                                        </div>
                                        <div class="total-reviews">
                                            <?php echo $consultant['total_reviews'] ?? 0; ?> reviews
                                        </div>
                                    </div>
                                    <div class="rating-bars">
                                        <?php
                                        // You would need to fetch this data from your database
                                        $rating_distribution = [
                                            5 => 0,
                                            4 => 0,
                                            3 => 0,
                                            2 => 0,
                                            1 => 0
                                        ];
                                        $total_reviews = $consultant['total_reviews'] ?? 0;
                                        
                                        for ($i = 5; $i >= 1; $i--) {
                                            $percentage = $total_reviews > 0 ? ($rating_distribution[$i] / $total_reviews) * 100 : 0;
                                            ?>
                                            <div class="rating-bar">
                                                <div class="stars"><?php echo $i; ?> <i class="fas fa-star"></i></div>
                                                <div class="progress">
                                                    <div class="progress-bar" style="width: <?php echo $percentage; ?>%"></div>
                                                </div>
                                                <div class="count"><?php echo $rating_distribution[$i]; ?></div>
                                            </div>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                </div>
                                
                                <!-- Individual reviews would go here -->
                                <div class="reviews-list">
                                    <!-- Add your reviews loop here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Right Column - Booking Form -->
            <div class="sidebar">
                <div class="booking-form">
                    <h3>Book a Consultation</h3>
                    <form id="bookingForm" action="process-booking.php" method="POST">
                        <input type="hidden" name="consultant_id" value="<?php echo $consultant_id; ?>">
                        <?php if (!empty($consultant['organization_id'])): ?>
                        <input type="hidden" name="organization_id" value="<?php echo $consultant['organization_id']; ?>">
                        <?php endif; ?>

                        <!-- Service Selection -->
                        <div class="form-group">
                            <label for="service">Select Service</label>
                            <select name="visa_service_id" id="service" required>
                                <option value="">Choose a service...</option>
                                <?php 
                                // Fetch available services for this consultant
                                $services_query = "SELECT 
                                    vs.visa_service_id,
                                    v.visa_type,
                                    st.service_name,
                                    vs.base_price,
                                    vs.description,
                                    GROUP_CONCAT(DISTINCT cm.mode_name) as consultation_modes
                                FROM visa_services vs
                                JOIN visas v ON vs.visa_id = v.visa_id
                                JOIN service_types st ON vs.service_type_id = st.service_type_id
                                LEFT JOIN service_consultation_modes scm ON vs.visa_service_id = scm.visa_service_id
                                LEFT JOIN consultation_modes cm ON scm.consultation_mode_id = cm.consultation_mode_id
                                WHERE vs.consultant_id = ? AND vs.is_active = 1
                                GROUP BY vs.visa_service_id";
                                
                                $stmt = $conn->prepare($services_query);
                                $stmt->bind_param("i", $consultant_id);
                                $stmt->execute();
                                $services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                
                                foreach ($services as $service): 
                                ?>
                                <option value="<?php echo $service['visa_service_id']; ?>"
                                    data-price="<?php echo $service['base_price']; ?>"
                                    data-modes="<?php echo htmlspecialchars($service['consultation_modes']); ?>">
                                    <?php echo htmlspecialchars($service['visa_type'] . ' - ' . $service['service_name']); ?>
                                    (<?php echo number_format($service['base_price'], 2); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Consultation Mode -->
                        <div class="form-group">
                            <label for="consultation_mode">Consultation Mode</label>
                            <select name="consultation_mode" id="consultation_mode" required>
                                <option value="">Select mode...</option>
                            </select>
                        </div>

                        <!-- Date Selection -->
                        <div class="form-group">
                            <label for="booking_date">Select Date</label>
                            <input type="date" id="booking_date" name="booking_date" required
                                min="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <!-- Time Selection -->
                        <div class="form-group">
                            <label for="booking_time">Select Time</label>
                            <select name="booking_time" id="booking_time" required>
                                <option value="">Select time...</option>
                            </select>
                        </div>

                        <!-- Duration -->
                        <div class="form-group">
                            <label for="duration">Duration</label>
                            <select name="duration_minutes" id="duration" required>
                                <option value="30">30 minutes</option>
                                <option value="60" selected>1 hour</option>
                                <option value="90">1.5 hours</option>
                                <option value="120">2 hours</option>
                            </select>
                        </div>

                        <!-- Notes -->
                        <div class="form-group">
                            <label for="notes">Additional Notes</label>
                            <textarea name="client_notes" id="notes" rows="4"
                                placeholder="Any specific questions or topics you'd like to discuss?"></textarea>
                        </div>
                        
                        <!-- Terms and Conditions -->
                        <div class="form-group terms-checkbox">
                            <input type="checkbox" id="terms" name="terms" required>
                            <label for="terms">I agree to the <a href="terms.php" target="_blank">terms and conditions</a></label>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn btn-primary">Book Consultation</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* Updated styles */
.profile-banner-flex {
    background: url('<?php echo !empty($consultant['banner_image']) ? '/uploads/' . $consultant['banner_image'] : '/assets/images/default-banner.jpg'; ?>') center/cover no-repeat;
    width: 100%;
    display: flex;
    align-items: flex-end;
    min-height: 180px; /* Reduced height */
    position: relative;
    padding-bottom: 0;
}

.profile-header-flex {
    display: flex;
    align-items: center; /* Changed to center alignment */
    width: 100%;
    position: relative;
    padding: 2rem;
}

.header-profile-image-overlap {
    position: relative;
    z-index: 2;
    width: 150px;
    height: 150px;
    border-radius: 50%;
    overflow: hidden;
    border: 4px solid white;
    background: #fff;
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
    margin-bottom: 0; /* Removed negative margin */
}
.header-profile-image-overlap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-details {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-left: 2rem;
}

.profile-header-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 2rem;
}

.social-links-header {
    position: absolute;
    bottom: 1rem;
    right: 1rem;
    display: flex;
    gap: 0.5rem;
}

.social-link {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: var(--primary-color);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 1.2rem;
}

.social-link:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
}

/* Updated tab styles */
.profile-tabs {
    list-style: none;
    display: flex;
    border-bottom: 2px solid #eee;
    padding: 1rem 0;
    margin: 0 0 2rem 0;
    background: white;
    border-radius: 8px 8px 0 0;
}

.profile-tabs li {
    padding: 0.5rem 1rem;
    cursor: pointer;
    font-weight: 500;
    color: #888;
    border-bottom: 3px solid transparent;
    transition: all 0.3s ease;
}

.profile-tabs li:hover {
    color: var(--primary-color);
}

.profile-tabs li.active {
    color: var(--primary-color);
    border-bottom: 3px solid var(--primary-color);
}

.tab-panel { 
    display: none;
    animation: fadeIn 0.3s ease-in-out;
}

.tab-panel.active { 
    display: block;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.header-rating {
    margin-top: 0.5rem;
    font-size: 1.2rem;
    color: #222;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #333;
}

.form-group select,
.form-group input,
.form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 1rem;
}

.form-group textarea {
    resize: vertical;
}

/* Responsive styles */
@media (max-width: 900px) {
    .profile-header-flex {
        flex-direction: column;
        align-items: center;
        text-align: center;
        padding: 2rem 1rem;
    }
    
    .profile-details {
        margin-left: 0;
        margin-top: 1rem;
    }
    
    .profile-header-row {
        flex-direction: column;
        align-items: center;
    }
    
    .social-links-header {
        position: static;
        margin-top: 1rem;
        justify-content: center;
    }
    
    .profile-tabs {
        flex-wrap: wrap;
        justify-content: center;
        gap: 1rem;
    }
}

/* Sidebar and Booking Form Styles */
.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    align-items: flex-start;
}

.sidebar {
    position: relative;
}

.booking-form {
    background: white;
    border-radius: 10px;
    padding: 2rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.10);
    position: sticky;
    top: 2rem;
}

@media (max-width: 900px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
    .sidebar {
        position: static;
        margin-top: 2rem;
    }
    .booking-form {
        position: static;
        width: 100%;
        margin-top: 2rem;
    }
}

/* Specializations & Languages Styles */
.specializations-grid {
    display: grid;
    gap: 2rem;
}

.specialization-section,
.languages-section {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1.5rem;
}

.specialization-section h3,
.languages-section h3 {
    color: #333;
    margin-bottom: 1rem;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.specialization-section h3 i,
.languages-section h3 i {
    color: var(--primary-color);
}

.specialization-list,
.languages-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.specialization-item,
.language-item {
    background: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    color: #555;
    border: 1px solid #e0e0e0;
    transition: all 0.3s ease;
}

.specialization-item:hover,
.language-item:hover {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
    transform: translateY(-2px);
}

/* Responsive styles */
@media (max-width: 900px) {
    .content-section {
        padding: 1.5rem;
    }
    
    .specialization-list,
    .languages-list {
        justify-content: center;
    }
}

/* Tab Content Styling */
.profile-tab-content {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-top: 20px;
}

/* About Tab */
.content-section {
    padding: 25px;
}

.content-section h2 {
    color: var(--primary-color);
    font-size: 1.5rem;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--primary-light);
}

.bio {
    line-height: 1.8;
    color: #555;
    font-size: 1.1rem;
    white-space: pre-line;
}

/* Education & Certification Tab */
.experience-details {
    display: flex;
    flex-direction: column;
    gap: 25px;
}

.detail-item {
    display: flex;
    gap: 20px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    transition: transform 0.2s ease;
}

.detail-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.detail-item i {
    font-size: 24px;
    color: var(--primary-color);
    margin-top: 5px;
}

.detail-item div {
    flex: 1;
}

.detail-item h3 {
    color: #333;
    font-size: 1.2rem;
    margin-bottom: 8px;
}

.detail-item p {
    color: #666;
    line-height: 1.6;
    white-space: pre-line;
}

/* Specializations & Languages Tab */
.specializations-grid {
    display: grid;
    gap: 25px;
}

.specialization-section,
.languages-section {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 25px;
}

.specialization-section h3,
.languages-section h3 {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #333;
    font-size: 1.2rem;
    margin-bottom: 20px;
}

.specialization-section h3 i,
.languages-section h3 i {
    color: var(--primary-color);
}

.specialization-list,
.languages-list {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.specialization-item,
.language-item {
    background: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.9rem;
    color: #555;
    border: 1px solid #e0e0e0;
    transition: all 0.3s ease;
}

.specialization-item:hover,
.language-item:hover {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
    transform: translateY(-2px);
}

/* Contact Details Tab */
.contact-info-tab {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.contact-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.contact-item i {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--primary-color);
    color: white;
    border-radius: 50%;
    font-size: 1.2rem;
}

.contact-item a {
    color: #333;
    text-decoration: none;
    font-size: 1.1rem;
    transition: color 0.3s ease;
}

.contact-item a:hover {
    color: var(--primary-color);
}

/* Reviews & Ratings Tab */
.reviews-section {
    display: flex;
    flex-direction: column;
    gap: 25px;
}

.rating-summary {
    display: flex;
    align-items: center;
    gap: 30px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 20px;
}

.rating-average {
    text-align: center;
    padding-right: 30px;
    border-right: 2px solid #e0e0e0;
}

.rating-average .number {
    font-size: 3rem;
    font-weight: bold;
    color: var(--primary-color);
    line-height: 1;
    margin-bottom: 5px;
}

.rating-average .stars {
    color: #ffc107;
    font-size: 1.2rem;
}

.rating-average .total-reviews {
    color: #666;
    font-size: 0.9rem;
    margin-top: 5px;
}

.rating-bars {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.rating-bar {
    display: flex;
    align-items: center;
    gap: 10px;
}

.rating-bar .stars {
    width: 100px;
    white-space: nowrap;
}

.rating-bar .progress {
    flex: 1;
    height: 8px;
    background: #e0e0e0;
    border-radius: 4px;
    overflow: hidden;
}

.rating-bar .progress-bar {
    height: 100%;
    background: var(--primary-color);
    border-radius: 4px;
}

.rating-bar .count {
    width: 50px;
    text-align: right;
    color: #666;
}

/* Responsive Design */
@media (max-width: 768px) {
    .rating-summary {
        flex-direction: column;
        text-align: center;
    }
    
    .rating-average {
        padding-right: 0;
        padding-bottom: 20px;
        border-right: none;
        border-bottom: 2px solid #e0e0e0;
        margin-bottom: 20px;
    }
    
    .detail-item {
        flex-direction: column;
        text-align: center;
    }
    
    .detail-item i {
        margin: 0 auto;
    }
    
    .specialization-list,
    .languages-list {
        justify-content: center;
    }
    
    .contact-item {
        flex-direction: column;
        text-align: center;
    }
    
    .contact-item i {
        margin: 0 auto;
    }
}

/* Animation for tab transitions */
.tab-panel {
    opacity: 0;
    transform: translateY(10px);
    transition: opacity 0.3s ease, transform 0.3s ease;
}

.tab-panel.active {
    opacity: 1;
    transform: translateY(0);
}

/* Custom scrollbar for tab content */
.profile-tab-content {
    scrollbar-width: thin;
    scrollbar-color: var(--primary-color) #f0f0f0;
}

.profile-tab-content::-webkit-scrollbar {
    width: 8px;
}

.profile-tab-content::-webkit-scrollbar-track {
    background: #f0f0f0;
    border-radius: 4px;
}

.profile-tab-content::-webkit-scrollbar-thumb {
    background: var(--primary-color);
    border-radius: 4px;
}

/* Add hover effects for interactive elements */
.detail-item,
.contact-item,
.specialization-item,
.language-item {
    cursor: pointer;
    will-change: transform;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const serviceSelect = document.getElementById('service');
    const modeSelect = document.getElementById('consultation_mode');
    const dateInput = document.getElementById('booking_date');
    const timeSelect = document.getElementById('booking_time');

    // Update consultation modes when service is selected
    serviceSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const modes = selectedOption.dataset.modes.split(',');

        modeSelect.innerHTML = '<option value="">Select mode...</option>';
        modes.forEach(mode => {
            const option = document.createElement('option');
            option.value = mode.trim();
            option.textContent = mode.trim();
            modeSelect.appendChild(option);
        });
    });

    // Fetch available time slots when date is selected
    dateInput.addEventListener('change', function() {
        const date = this.value;
        const serviceId = serviceSelect.value;
        const consultantId = <?php echo $consultant_id; ?>;

        if (date && serviceId) {
            // Fetch available time slots from the server
            fetch(
                    `get-available-slots.php?date=${date}&service_id=${serviceId}&consultant_id=${consultantId}`
                )
                .then(response => response.json())
                .then(slots => {
                    timeSelect.innerHTML = '<option value="">Select time...</option>';
                    slots.forEach(slot => {
                        const option = document.createElement('option');
                        option.value = slot.time;
                        option.textContent = slot.time;
                        timeSelect.appendChild(option);
                    });
                })
                .catch(error => console.error('Error fetching time slots:', error));
        }
    });

    // Form submission handling
    document.getElementById('bookingForm').addEventListener('submit', function(e) {
        e.preventDefault();

        // Validate form
        if (!this.checkValidity()) {
            e.stopPropagation();
            this.classList.add('was-validated');
            return;
        }

        // Add missing fields to form data
        const formData = new FormData(this);
        
        // Ensure user_id is set
        if (!formData.has('user_id')) {
            formData.append('user_id', <?php echo $_SESSION['id'] ?? 0; ?>);
        }
        
        // Fix consultation_mode_id - we need to get the actual ID, not the name
        if (formData.has('consultation_mode')) {
            // Remove the incorrect consultation_mode_id if it exists
            if (formData.has('consultation_mode_id')) {
                formData.delete('consultation_mode_id');
            }
            
            // Get the mode name
            const modeName = formData.get('consultation_mode');
            
            // Here we should map the mode name to its ID
            // This is a simplified example - you should replace with actual IDs from your database
            const modeMap = {
                'In-Person': 1,
                'Video': 2,
                'Phone': 3,
                'Chat': 4
            };
            
            const modeId = modeMap[modeName] || 1; // Default to 1 if not found
            formData.append('consultation_mode_id', modeId);
        }
        
        // Add organization_id if missing
        if (!formData.has('organization_id')) {
            formData.append('organization_id', <?php echo $consultant['organization_id'] ?? 1; ?>);
        }
        
        // Debug: Log all form data after modifications
        console.log('Form data after modifications:');
        for (let pair of formData.entries()) {
            console.log(pair[0] + ': ' + pair[1]);
        }
        
        // Submit form
        fetch('process-booking.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Check if response is OK before trying to parse JSON
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error('Server error: ' + text);
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    window.location.href = `booking-confirmation.php?reference=${data.reference}`;
                } else {
                    alert(data.message || 'An error occurred while processing your booking.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while processing your booking: ' + error.message);
            });
    });

    // Tab switching
    document.querySelectorAll('.profile-tabs li').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.profile-tabs li').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.remove('active'));
            this.classList.add('active');
            document.getElementById(this.getAttribute('data-tab')).classList.add('active');
        });
    });
});
</script>

<?php include('includes/footer.php'); ?>

