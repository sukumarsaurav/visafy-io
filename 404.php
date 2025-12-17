<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'config/db_connect.php';
require_once 'includes/functions.php';

// Set page title
$page_title = "404 - Page Not Found";

// Include header
require_once 'includes/header.php';
?>

<div class="error-page">
    <div class="error-content">
        <div class="error-icon">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <h1>404</h1>
        <h2>Page Not Found</h2>
        <p>Oops! The page you're looking for doesn't exist or has been moved.</p>
        <div class="error-actions">
            <a href="/" class="btn btn-primary">Go to Homepage</a>
            <a href="javascript:history.back()" class="btn btn-secondary">Go Back</a>
        </div>
    </div>
</div>

<style>
.error-page {
    min-height: calc(100vh - var(--header-height));
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    background-color: var(--light-color);
    margin-top: var(--header-height);
}

.error-content {
    text-align: center;
    max-width: 600px;
    padding: 40px;
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.error-icon {
    font-size: 4rem;
    color: var(--primary-color);
    margin-bottom: 20px;
}

.error-content h1 {
    font-size: 6rem;
    color: var(--primary-color);
    margin: 0;
    line-height: 1;
    font-weight: 700;
}

.error-content h2 {
    font-size: 2rem;
    color: var(--dark-color);
    margin: 10px 0 20px;
    font-weight: 600;
}

.error-content p {
    color: var(--secondary-color);
    font-size: 1.1rem;
    margin-bottom: 30px;
}

.error-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
}

.error-actions .btn {
    padding: 12px 25px;
    font-weight: 600;
    border-radius: 4px;
    transition: all 0.3s ease;
}

.error-actions .btn-primary {
    background-color: var(--primary-color);
    color: #fff;
    border: 2px solid var(--primary-color);
}

.error-actions .btn-primary:hover {
    background-color: transparent;
    color: var(--primary-color);
}

.error-actions .btn-secondary {
    background-color: transparent;
    color: var(--primary-color);
    border: 2px solid var(--primary-color);
}

.error-actions .btn-secondary:hover {
    background-color: var(--primary-color);
    color: #fff;
}

@media (max-width: 576px) {
    .error-content {
        padding: 30px 20px;
    }

    .error-content h1 {
        font-size: 4rem;
    }

    .error-content h2 {
        font-size: 1.5rem;
    }

    .error-content p {
        font-size: 1rem;
    }

    .error-actions {
        flex-direction: column;
    }

    .error-actions .btn {
        width: 100%;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>
