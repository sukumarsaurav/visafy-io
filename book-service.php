<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'config/db_connect.php';


$page_title = "Book a Consultation";
require_once 'includes/header.php';

// Get all verified consultants with their profile details
$query = "SELECT 
    u.id AS consultant_id,
    CONCAT(u.first_name, ' ', u.last_name) AS consultant_name,
    u.email,
    u.phone,
    u.profile_picture,
    c.company_name,
    cp.bio,
    cp.specializations,
    cp.years_experience,
    cp.certifications,
    cp.languages,
    cp.is_featured,
    o.id AS organization_id,
    o.name AS organization_name,
    COALESCE(AVG(bf.rating), 0) AS average_rating,
    COUNT(DISTINCT bf.id) AS review_count,
    COUNT(DISTINCT vs.visa_service_id) AS services_count,
    cp.is_verified,
    GROUP_CONCAT(DISTINCT co.country_name) as countries,
    GROUP_CONCAT(DISTINCT v.visa_type) as visa_types
FROM 
    users u
JOIN 
    consultants c ON u.id = c.user_id
JOIN 
    organizations o ON u.organization_id = o.id
LEFT JOIN 
    consultant_profiles cp ON u.id = cp.consultant_id
LEFT JOIN 
    visa_services vs ON u.id = vs.consultant_id AND vs.is_active = 1
LEFT JOIN 
    visas v ON vs.visa_id = v.visa_id
LEFT JOIN 
    countries co ON v.country_id = co.country_id
LEFT JOIN 
    bookings b ON u.id = b.consultant_id
LEFT JOIN 
    booking_feedback bf ON b.id = bf.booking_id
WHERE 
    u.status = 'active' 
    AND u.deleted_at IS NULL
    AND u.user_type = 'consultant'
GROUP BY 
    u.id, u.first_name, u.last_name, u.email, u.phone, u.profile_picture,
    c.company_name, cp.bio, cp.specializations, cp.years_experience,
    cp.certifications, cp.languages, cp.is_featured, o.id, o.name, cp.is_verified
ORDER BY 
    cp.is_featured DESC, cp.is_verified DESC, average_rating DESC";

$result = $conn->query($query);
$consultants = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $consultants[] = $row;
    }
}

// Add this after the main query
$countries_query = "SELECT DISTINCT c.country_id, c.country_name 
                   FROM countries c 
                   JOIN visas v ON c.country_id = v.country_id 
                   JOIN visa_services vs ON v.visa_id = vs.visa_id 
                   WHERE c.is_active = 1 
                   ORDER BY c.country_name";
$countries_result = $conn->query($countries_query);
$countries = [];
if ($countries_result && $countries_result->num_rows > 0) {
    while ($row = $countries_result->fetch_assoc()) {
        $countries[] = $row;
    }
}

$visas_query = "SELECT DISTINCT v.visa_id, v.visa_type, c.country_name 
                FROM visas v 
                JOIN countries c ON v.country_id = c.country_id 
                JOIN visa_services vs ON v.visa_id = vs.visa_id 
                WHERE v.is_active = 1 
                ORDER BY c.country_name, v.visa_type";
$visas_result = $conn->query($visas_query);
$visas = [];
if ($visas_result && $visas_result->num_rows > 0) {
    while ($row = $visas_result->fetch_assoc()) {
        $visas[] = $row;
    }
}
?>

<!-- Hero Section -->
<!-- Hero Section -->
<section class="hero book-consultation-hero">
    <div class="container">
        <div class="book-hero-content text-center">
            <h1 class="book-hero-title">Book a Professional Consultation</h1>
            <p class="book-hero-subtitle">Connect with our network of experienced visa consultants to guide your immigration
                journey</p>
        </div>

        <!-- Search and Filter Controls -->
        <div class="book-search-filters">
            <div class="book-filter-container">
                <div class="book-filter-item">
                    <input type="text" id="search-consultant" class="book-form-control"
                        placeholder="Search by name or specialization">
                </div>
                <div class="book-filter-item">
                    <select id="filter-country" class="book-form-control">
                        <option value="">All Countries</option>
                        <?php foreach ($countries as $country): ?>
                        <option value="<?php echo htmlspecialchars($country['country_name']); ?>">
                            <?php echo htmlspecialchars($country['country_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="book-filter-item">
                    <select id="filter-visa" class="book-form-control">
                        <option value="">All Visa Types</option>
                        <?php foreach ($visas as $visa): ?>
                        <option value="<?php echo htmlspecialchars($visa['visa_type']); ?>">
                            <?php echo htmlspecialchars($visa['country_name'] . ' - ' . $visa['visa_type']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="book-filter-item">
                    <select id="filter-rating" class="book-form-control">
                        <option value="">All Ratings</option>
                        <option value="no-rating">No Ratings</option>
                        <option value="4">4+ Stars</option>
                        <option value="3">3+ Stars</option>
                        <option value="2">2+ Stars</option>
                    </select>
                </div>
                <div class="book-filter-item">
                    <select id="filter-verified" class="book-form-control">
                        <option value="">All Consultants</option>
                        <option value="1">Verified by Visafy</option>
                    </select>
                </div>
                <div class="book-filter-item">
                    <button id="reset-filters" class="book-btn book-btn-secondary">Reset</button>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="container">
    <!-- Consultants List -->
    <div class="consultants-list">
        <?php if (empty($consultants)): ?>
        <div class="empty-state">
            <i class="fas fa-user-tie"></i>
            <p>No consultants found. Please try different search criteria.</p>
        </div>
        <?php else: ?>
        <div class="row">
            <?php foreach ($consultants as $consultant): ?>
            <div class="consultant-card-wrapper"
                data-name="<?php echo strtolower(htmlspecialchars($consultant['consultant_name'])); ?>"
                data-specializations="<?php echo strtolower(htmlspecialchars($consultant['specializations'] ?? '')); ?>"
                data-countries="<?php echo strtolower(htmlspecialchars($consultant['countries'] ?? '')); ?>"
                data-visa-types="<?php echo strtolower(htmlspecialchars($consultant['visa_types'] ?? '')); ?>"
                data-rating="<?php echo htmlspecialchars($consultant['average_rating']); ?>"
                data-has-rating="<?php echo $consultant['review_count'] > 0 ? '1' : '0'; ?>"
                data-verified="<?php echo !empty($consultant['is_verified']) ? '1' : '0'; ?>">

                <div class="consultant-card horizontal">
                    <?php if (!empty($consultant['is_verified'])): ?>
                    <div class="verified-badge">
                        <i class="fas fa-check-circle"></i> Verified by Visafy
                    </div>
                    <?php endif; ?>

                    <div class="consultant-img">
                        <?php if (!empty($consultant['profile_picture'])): ?>
                        <?php 
                                    // Fix profile picture path - add 'uploads/' if not present
                                    $profile_picture = $consultant['profile_picture'];
                                    if (strpos($profile_picture, 'users/') === 0) {
                                        if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $profile_picture)) {
                                            $profile_img = '/uploads/' . $profile_picture;
                                        }
                                    }
                                    ?>
                        <img src="<?php echo htmlspecialchars($profile_img); ?>"
                            alt="<?php echo htmlspecialchars($consultant['consultant_name']); ?>">
                        <?php else: ?>
                        <div class="default-avatar">
                            <?php echo strtoupper(substr($consultant['consultant_name'], 0, 1)); ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="consultant-info">
                        <h3><?php echo htmlspecialchars($consultant['consultant_name']); ?></h3>
                        <p class="company-name"><?php echo htmlspecialchars($consultant['company_name']); ?></p>
                        <div class="rating">
                            <?php
                                    $rating = round($consultant['average_rating'] * 2) / 2; // Round to nearest 0.5
                                    for ($i = 1; $i <= 5; $i++):
                                        if ($rating >= $i):
                                            echo '<i class="fas fa-star"></i>';
                                        elseif ($rating >= $i - 0.5):
                                            echo '<i class="fas fa-star-half-alt"></i>';
                                        else:
                                            echo '<i class="far fa-star"></i>';
                                        endif;
                                    endfor;
                                    ?>
                            <span>(<?php echo $consultant['review_count']; ?> reviews)</span>
                        </div>

                        <div class="specializations">
                            <strong>Specializations:</strong>
                            <?php if (!empty($consultant['specializations'])): ?>
                            <div class="specialization-preview"></div>
                            <a href="#" class="see-more-link"
                                data-consultant-id="<?php echo $consultant['consultant_id']; ?>">See more</a>
                            <?php else: ?>
                            <span>General visa services</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="consultant-meta">
                        <div class="meta-item">
                            <i class="fas fa-briefcase"></i>
                            <span><?php echo !empty($consultant['years_experience']) ? $consultant['years_experience'] . '+ years exp.' : 'Experience not specified'; ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-globe"></i>
                            <span><?php echo !empty($consultant['languages']) ? htmlspecialchars($consultant['languages']) : 'Languages not specified'; ?></span>
                        </div>
                    </div>

                    <div class="consultant-action">
                        <a href="consultant-profile.php?id=<?php echo $consultant['consultant_id']; ?>"
                            class="btn btn-primary">Book Consultation</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Specializations Modal -->
<div id="specializationsModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2 class="modal-title">Specializations</h2>
        <div class="specialization-tabs">
            <div class="tab-buttons"></div>
            <div class="tab-content"></div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search and filter functionality
    const searchInput = document.getElementById('search-consultant');
    const ratingFilter = document.getElementById('filter-rating');
    const verifiedFilter = document.getElementById('filter-verified');
    const resetButton = document.getElementById('reset-filters');
    const consultantCards = document.querySelectorAll('.consultant-card-wrapper');

    // Function to filter consultants
    function filterConsultants() {
        const searchTerm = searchInput.value.toLowerCase();
        const ratingValue = ratingFilter.value;
        const verifiedValue = verifiedFilter.value;
        const countryValue = document.getElementById('filter-country').value.toLowerCase();
        const visaValue = document.getElementById('filter-visa').value.toLowerCase();

        consultantCards.forEach(card => {
            const name = card.dataset.name;
            const specializations = card.dataset.specializations;
            const countries = card.dataset.countries;
            const visaTypes = card.dataset.visaTypes;
            const rating = parseFloat(card.dataset.rating);
            const hasRating = card.dataset.hasRating === '1';
            const verified = card.dataset.verified;

            // Check if card matches all filters
            const matchesSearch = searchTerm === '' ||
                name.includes(searchTerm) ||
                specializations.includes(searchTerm);

            const matchesCountry = countryValue === '' ||
                countries.includes(countryValue);

            const matchesVisa = visaValue === '' ||
                visaTypes.includes(visaValue);

            let matchesRating = true;
            if (ratingValue !== '') {
                if (ratingValue === 'no-rating') {
                    matchesRating = !hasRating;
                } else {
                    matchesRating = hasRating && rating >= parseFloat(ratingValue);
                }
            }

            const matchesVerified = verifiedValue === '' ||
                (verifiedValue === '1' && verified === '1');

            // Show or hide card based on all filter results
            if (matchesSearch && matchesRating && matchesVerified &&
                matchesCountry && matchesVisa) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });

        // Check if any cards are visible
        updateEmptyState();
    }

    // Add event listeners
    searchInput.addEventListener('input', filterConsultants);
    ratingFilter.addEventListener('change', filterConsultants);
    verifiedFilter.addEventListener('change', filterConsultants);

    // Add event listeners for new filters
    document.getElementById('filter-country').addEventListener('change', filterConsultants);
    document.getElementById('filter-visa').addEventListener('change', filterConsultants);

    // Update reset functionality
    resetButton.addEventListener('click', function() {
        searchInput.value = '';
        ratingFilter.value = '';
        verifiedFilter.value = '';
        document.getElementById('filter-country').value = '';
        document.getElementById('filter-visa').value = '';

        consultantCards.forEach(card => {
            card.style.display = 'block';
        });

        const emptyState = document.querySelector('.empty-state');
        if (emptyState) {
            emptyState.remove();
        }
    });

    // Add this inside your DOMContentLoaded event listener
    const modal = document.getElementById('specializationsModal');
    const closeBtn = document.querySelector('.close');
    const consultantCards = document.querySelectorAll('.consultant-card-wrapper');

    // Function to create specialization preview
    function createSpecializationPreview(specializations) {
        const specializationArray = specializations.split(',').map(s => s.trim());
        const preview = specializationArray[0];
        return preview + (specializationArray.length > 1 ? '' : '');
    }

    // Initialize specialization previews
    consultantCards.forEach(card => {
        const specializationsDiv = card.querySelector('.specialization-preview');
        if (specializationsDiv) {
            const specializations = card.dataset.specializations;
            specializationsDiv.textContent = createSpecializationPreview(specializations);
        }
    });

    // Handle "See more" clicks
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('see-more-link')) {
            e.preventDefault();
            const consultantId = e.target.dataset.consultantId;
            const card = e.target.closest('.consultant-card-wrapper');
            const specializations = card.dataset.specializations;
            
            showSpecializationsModal(specializations);
        }
    });

    // Modal functionality
    function showSpecializationsModal(specializations) {
        const specializationArray = specializations.split(',').map(s => s.trim());
        const categories = groupSpecializations(specializationArray);
        
        // Clear existing content
        const tabButtons = modal.querySelector('.tab-buttons');
        const tabContent = modal.querySelector('.tab-content');
        tabButtons.innerHTML = '';
        tabContent.innerHTML = '';
        
        // Create tabs and content
        Object.keys(categories).forEach((category, index) => {
            // Create tab button
            const button = document.createElement('button');
            button.className = `tab-button ${index === 0 ? 'active' : ''}`;
            button.textContent = category;
            button.onclick = () => switchTab(category);
            tabButtons.appendChild(button);
            
            // Create tab content
            const pane = document.createElement('div');
            pane.className = `tab-pane ${index === 0 ? 'active' : ''}`;
            pane.id = `tab-${category.toLowerCase().replace(/\s+/g, '-')}`;
            pane.innerHTML = `<ul>${categories[category].map(item => `<li>${item}</li>`).join('')}</ul>`;
            tabContent.appendChild(pane);
        });
        
        modal.style.display = 'block';
    }

    // Group specializations into categories
    function groupSpecializations(specializations) {
        const categories = {
            'Visa Types': [],
            'Countries': [],
            'Services': []
        };
        
        specializations.forEach(spec => {
            if (spec.toLowerCase().includes('visa')) {
                categories['Visa Types'].push(spec);
            } else if (spec.toLowerCase().includes('immigration') || 
                       spec.toLowerCase().includes('consultation')) {
                categories['Services'].push(spec);
            } else {
                categories['Countries'].push(spec);
            }
        });
        
        // Remove empty categories
        Object.keys(categories).forEach(key => {
            if (categories[key].length === 0) {
                delete categories[key];
            }
        });
        
        return categories;
    }

    // Switch between tabs
    function switchTab(category) {
        const buttons = document.querySelectorAll('.tab-button');
        const panes = document.querySelectorAll('.tab-pane');
        
        buttons.forEach(button => {
            button.classList.remove('active');
            if (button.textContent === category) {
                button.classList.add('active');
            }
        });
        
        panes.forEach(pane => {
            pane.classList.remove('active');
            if (pane.id === `tab-${category.toLowerCase().replace(/\s+/g, '-')}`) {
                pane.classList.add('active');
            }
        });
    }

    // Close modal
    closeBtn.onclick = function() {
        modal.style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>