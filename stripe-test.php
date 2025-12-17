<?php
// Load Composer's autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables from .env file
if (file_exists(__DIR__ . '/config/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/config');
    $dotenv->load();
}

// Stripe API Keys - get from environment variables
$stripe_publishable_key = $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? '';
$stripe_secret_key = $_ENV['STRIPE_SECRET_KEY'] ?? '';

// Initialize Stripe
\Stripe\Stripe::setApiKey($stripe_secret_key);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stripe Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #4CAF50;
        }
        .success {
            background-color: #DFF2BF;
            color: #4F8A10;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .error {
            background-color: #FFBABA;
            color: #D8000C;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Stripe Integration Test</h1>
        
        <?php
        try {
            // Test Stripe API by retrieving account information
            $account = \Stripe\Account::retrieve();
            echo '<div class="success">
                <strong>Success!</strong> Stripe is properly configured and connected.
                <p>Account ID: ' . $account->id . '</p>
                <p>Account Name: ' . ($account->business_profile->name ?? 'Not set') . '</p>
            </div>';
        } catch (\Exception $e) {
            echo '<div class="error">
                <strong>Error:</strong> ' . $e->getMessage() . '
            </div>';
        }
        ?>
        
        <h2>Environment Information</h2>
        <ul>
            <li>PHP Version: <?php echo phpversion(); ?></li>
            <li>Stripe SDK Version: <?php echo \Stripe\Stripe::VERSION; ?></li>
            <li>Publishable Key: <?php echo substr($stripe_publishable_key, 0, 10) . '...'; ?></li>
        </ul>
        
        <h2>Next Steps</h2>
        <p>If you see a success message above, your Stripe integration is working correctly. You can now return to the membership registration page.</p>
        <p><a href="become-member.php">Go to Membership Registration</a></p>
    </div>
</body>
</html> 