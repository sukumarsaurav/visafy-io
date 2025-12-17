<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

// Include session management
require_once "includes/session.php";

// Include config files
require_once "config/db_connect.php";

$page_title = "Visa Guides";
require_once 'includes/header.php';
require_once 'includes/functions.php';
?>

<!-- Hero Section -->
<section class="hero privacy-hero">
    <div class="container">
        <div class="hero-content text-center">
            <h1 class="hero-title">Visa Application Guides</h1>
            <p class="hero-subtitle">Comprehensive resources to help you understand the visa application process</p>
        </div>
    </div>
</section>

<div class="content">
    <div class="container">
        <!-- Guide Categories -->
        <div class="guide-categories">
            <button class="category-btn active" data-category="all">All Guides</button>
            <button class="category-btn" data-category="student">Student Visas</button>
            <button class="category-btn" data-category="work">Work Visas</button>
            <button class="category-btn" data-category="business">Business Visas</button>
            <button class="category-btn" data-category="family">Family Visas</button>
        </div>

        <!-- Search Bar -->
        <div class="guide-search">
            <input type="text" id="guideSearch" placeholder="Search guides..." class="search-input">
        </div>

        <!-- Guides Grid -->
        <div class="guides-grid">
            <!-- Student Visa Guides -->
            <div class="guide-card" data-category="student">
                <div class="guide-image">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="guide-content">
                    <h3>Student Visa Application Guide</h3>
                    <p>Complete guide to applying for student visas, including requirements and documentation.</p>
                    <div class="guide-meta">
                        <span><i class="far fa-clock"></i> 15 min read</span>
                        <span><i class="far fa-calendar"></i> Updated: May 2023</span>
                    </div>
                    <a href="#" class="guide-link">Read More <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>

            <!-- Work Visa Guides -->
            <div class="guide-card" data-category="work">
                <div class="guide-image">
                    <i class="fas fa-briefcase"></i>
                </div>
                <div class="guide-content">
                    <h3>Work Permit Guide</h3>
                    <p>Essential information about obtaining work permits and employment visas.</p>
                    <div class="guide-meta">
                        <span><i class="far fa-clock"></i> 20 min read</span>
                        <span><i class="far fa-calendar"></i> Updated: June 2023</span>
                    </div>
                    <a href="#" class="guide-link">Read More <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>

            <!-- Business Visa Guides -->
            <div class="guide-card" data-category="business">
                <div class="guide-image">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="guide-content">
                    <h3>Business Immigration Guide</h3>
                    <p>Comprehensive guide for entrepreneurs and business investors.</p>
                    <div class="guide-meta">
                        <span><i class="far fa-clock"></i> 25 min read</span>
                        <span><i class="far fa-calendar"></i> Updated: July 2023</span>
                    </div>
                    <a href="#" class="guide-link">Read More <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>

            <!-- Family Visa Guides -->
            <div class="guide-card" data-category="family">
                <div class="guide-image">
                    <i class="fas fa-users"></i>
                </div>
                <div class="guide-content">
                    <h3>Family Sponsorship Guide</h3>
                    <p>Information about sponsoring family members and dependents.</p>
                    <div class="guide-meta">
                        <span><i class="far fa-clock"></i> 18 min read</span>
                        <span><i class="far fa-calendar"></i> Updated: August 2023</span>
                    </div>
                    <a href="#" class="guide-link">Read More <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>

            <!-- More guide cards can be added here -->
        </div>

        <!-- Newsletter Signup -->
        <div class="newsletter-section">
            <h2>Stay Updated</h2>
            <p>Subscribe to receive the latest visa guides and immigration updates</p>
            <form class="newsletter-form">
                <input type="email" placeholder="Enter your email" required>
                <button type="submit" class="btn-primary">Subscribe</button>
            </form>
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

/* Guide specific styles */
.guide-categories {
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
    flex-wrap: wrap;
    justify-content: center;
}

.category-btn {
    padding: 10px 20px;
    border: 2px solid var(--primary-color);
    border-radius: 25px;
    background: none;
    color: var(--primary-color);
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
}

.category-btn.active {
    background-color: var(--primary-color);
    color: white;
}

.guide-search {
    margin-bottom: 40px;
}

.search-input {
    width: 100%;
    max-width: 600px;
    margin: 0 auto;
    display: block;
    padding: 15px 25px;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    font-size: 1rem;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px var(--primary-light);
}

.guides-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    margin-bottom: 60px;
}

.guide-card {
    background-color: var(--white);
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: var(--shadow);
    transition: transform 0.3s ease;
}

.guide-card:hover {
    transform: translateY(-5px);
}

.guide-image {
    background-color: var(--primary-light);
    height: 150px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.guide-image i {
    font-size: 3rem;
    color: var(--primary-color);
}

.guide-content {
    padding: 25px;
}

.guide-content h3 {
    color: var(--dark-blue);
    font-size: 1.3rem;
    margin-bottom: 15px;
}

.guide-content p {
    color: var(--text-light);
    margin-bottom: 20px;
    line-height: 1.6;
}

.guide-meta {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
    color: var(--text-light);
    font-size: 0.9rem;
}

.guide-meta span {
    display: flex;
    align-items: center;
    gap: 5px;
}

.guide-link {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 5px;
    transition: gap 0.3s ease;
}

.guide-link:hover {
    gap: 10px;
}

/* Newsletter Section */
.newsletter-section {
    text-align: center;
    padding: 60px 0;
    background-color: var(--background-light);
    border-radius: var(--border-radius);
    margin-top: 60px;
}

.newsletter-section h2 {
    color: var(--dark-blue);
    margin-bottom: 15px;
}

.newsletter-section p {
    color: var(--text-light);
    margin-bottom: 30px;
}

.newsletter-form {
    display: flex;
    gap: 15px;
    max-width: 500px;
    margin: 0 auto;
}

.newsletter-form input {
    flex: 1;
    padding: 12px 20px;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
    padding: 12px 30px;
    border: none;
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.btn-primary:hover {
    background-color: var(--dark-blue);
}

@media (max-width: 768px) {
    .guide-categories {
        flex-direction: column;
    }
    
    .category-btn {
        width: 100%;
    }
    
    .newsletter-form {
        flex-direction: column;
        padding: 0 20px;
    }
    
    .guides-grid {
        grid-template-columns: 1fr;
        padding: 0 20px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Category filtering
    const categoryBtns = document.querySelectorAll('.category-btn');
    const guideCards = document.querySelectorAll('.guide-card');
    
    categoryBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            // Remove active class from all buttons
            categoryBtns.forEach(b => b.classList.remove('active'));
            // Add active class to clicked button
            btn.classList.add('active');
            
            const category = btn.dataset.category;
            
            // Show/hide cards based on category
            guideCards.forEach(card => {
                if (category === 'all' || card.dataset.category === category) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
    
    // Search functionality
    const searchInput = document.getElementById('guideSearch');
    
    searchInput.addEventListener('input', () => {
        const searchTerm = searchInput.value.toLowerCase();
        
        guideCards.forEach(card => {
            const title = card.querySelector('h3').textContent.toLowerCase();
            const description = card.querySelector('p').textContent.toLowerCase();
            
            if (title.includes(searchTerm) || description.includes(searchTerm)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    });
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>
