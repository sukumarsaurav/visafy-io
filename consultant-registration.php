<?php
// File: consultant-registration.php

// Start output buffering to prevent 'headers already sent' errors
ob_start();

// Include required files
require_once "includes/session.php";
require_once "config/db_connect.php";
require_once "config/email_config.php";
require_once "includes/email_function.php";

// Load Composer's autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Load environment variables from .env file
if (file_exists(__DIR__ . '/config/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/config');
    $dotenv->load();
}

// Get Stripe API keys from environment
$stripe_publishable_key = $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? '';
$stripe_secret_key = $_ENV['STRIPE_SECRET_KEY'] ?? '';

// Initialize Stripe with API key
if (class_exists('\Stripe\Stripe')) {
    \Stripe\Stripe::setApiKey($stripe_secret_key);
}

$page_title = "Consultant Registration";
require_once 'includes/header.php';
require_once 'includes/functions.php';

// Get membership plans
$query = "SELECT * FROM membership_plans WHERE billing_cycle = 'monthly' ORDER BY price ASC";
$result = $conn->query($query);
$plans = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $plans[] = $row;
    }
}

// Get selected plan from URL if available
$selected_plan_id = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 0;
$selected_plan = null;

if ($selected_plan_id > 0) {
    foreach ($plans as $plan) {
        if ($plan['id'] == $selected_plan_id) {
            $selected_plan = $plan;
            break;
        }
    }
}

// If no plan is selected and we have plans, select the first one
if (!$selected_plan && !empty($plans)) {
    $selected_plan = $plans[0];
    $selected_plan_id = $selected_plan['id'];
}

// Initialize form variables
$first_name = $last_name = $email = $phone = $company_name = $address_line1 = $address_line2 = $city = $state = $postal_code = $country = '';

// Check if form is submitted
$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_member'])) {
    // Personal Information
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Company Information
    $company_name = trim($_POST['company_name'] ?? '');
    
    // Billing Address Information
    $address_line1 = trim($_POST['address_line1'] ?? '');
    $address_line2 = trim($_POST['address_line2'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $country = trim($_POST['country'] ?? '');
    
    // Plan Selection
    $membership_plan_id = isset($_POST['membership_plan_id']) ? (int)$_POST['membership_plan_id'] : 0;
    
    // Payment Information
    $payment_method_id = isset($_POST['payment_method_id']) ? $_POST['payment_method_id'] : '';
    
    // Validation
    $errors = [];
    
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (empty($email)) $errors[] = "Email is required";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email is not valid";
    if (empty($phone)) $errors[] = "Phone number is required";
    if (empty($password)) $errors[] = "Password is required";
    elseif (strlen($password) < 8) $errors[] = "Password must be at least 8 characters";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match";
    if ($membership_plan_id === 0) $errors[] = "Please select a membership plan";
    if (empty($payment_method_id)) $errors[] = "Payment information is required";
    
    // Validate address fields
    if (empty($address_line1)) $errors[] = "Address Line 1 is required";
    if (empty($city)) $errors[] = "City is required";
    if (empty($state)) $errors[] = "State/Province is required";
    if (empty($postal_code)) $errors[] = "Postal Code is required";
    if (empty($country)) $errors[] = "Country is required";
    
    // Check if email already exists
    $check_email_query = "SELECT id FROM users WHERE email = ?";
    $stmt = $conn->prepare($check_email_query);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $errors[] = "Email is already registered. Please use a different email or login.";
    }
    $stmt->close();
    
    if (empty($errors)) {
        // Get the selected plan details
        $plan_query = "SELECT * FROM membership_plans WHERE id = ?";
        $stmt = $conn->prepare($plan_query);
        $stmt->bind_param('i', $membership_plan_id);
        $stmt->execute();
        $plan_result = $stmt->get_result();
        $plan = $plan_result->fetch_assoc();
        $stmt->close();
        
        if (!$plan) {
            $errors[] = "Selected plan not found.";
        } else {
            try {
                // Start transaction
                $conn->begin_transaction();
                
                // Check if promo code was applied
                $final_price = $plan['price'];
                $promo_code_id = null;
                $discount_amount = 0;
                
                if (isset($_POST['applied_promo_code_id']) && !empty($_POST['applied_promo_code_id'])) {
                    $promo_code_id = intval($_POST['applied_promo_code_id']);
                    
                    // Validate promo code again to prevent tampering
                    $stmt = $conn->prepare("CALL validate_promo_code(
                        (SELECT code FROM promo_codes WHERE id = ?), 
                        ?, NULL, @is_valid, @discount_type, @discount_value, @error_message)");
                    $stmt->bind_param("ii", $promo_code_id, $membership_plan_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    $result = $conn->query("SELECT @is_valid as is_valid, @discount_type as discount_type, 
                                                  @discount_value as discount_value, @error_message as error_message");
                    $validation = $result->fetch_assoc();
                    
                    if ($validation['is_valid']) {
                        if ($validation['discount_type'] === 'percentage') {
                            $discount_amount = $plan['price'] * ($validation['discount_value'] / 100);
                        } else {
                            $discount_amount = $validation['discount_value'];
                        }
                        $discount_amount = min($discount_amount, $plan['price']);
                        $final_price = $plan['price'] - $discount_amount;
                    } else {
                        $promo_code_id = null;
                        $discount_amount = 0;
                    }
                }

                // Stripe payment processing
                try {
                    $payment_method = \Stripe\PaymentMethod::retrieve($payment_method_id);
                    
                    // Create Stripe customer with adjusted price if promo code applied
                    $stripe_customer = \Stripe\Customer::create([
                        'email' => $email,
                        'name' => $first_name . ' ' . $last_name,
                        'phone' => $phone,
                        'description' => 'Visafy Consultant - ' . ($company_name ?: ($first_name . ' ' . $last_name)),
                        'address' => [
                            'line1' => $address_line1,
                            'line2' => $address_line2,
                            'city' => $city,
                            'state' => $state,
                            'postal_code' => $postal_code,
                            'country' => $country,
                        ],
                        'metadata' => [
                            'membership_plan_id' => $membership_plan_id,
                            'company_name' => $company_name,
                            'promo_code_id' => $promo_code_id,
                            'original_price' => $plan['price'],
                            'discount_amount' => $discount_amount,
                            'final_price' => $final_price
                        ]
                    ]);
                    
                    // Handle payment method attachment
                    $is_indian_customer = $country === 'IN';
                    $is_fallback_method = isset($_POST['is_fallback_method']) && $_POST['is_fallback_method'] === 'true';
                    
                    if ($is_fallback_method) {
                        $setup_intent = \Stripe\SetupIntent::create([
                            'customer' => $stripe_customer->id,
                            'payment_method' => $payment_method->id,
                            'usage' => 'off_session',
                            'automatic_payment_methods' => [
                                'enabled' => true,
                                'allow_redirects' => 'never'
                            ],
                            'return_url' => 'https://beige-antelope-215732.hostingersite.com/consultant-registration.php',
                            'metadata' => [
                                'is_fallback' => 'true',
                                'email' => $email
                            ]
                        ]);
                        
                        try {
                            \Stripe\Customer::update(
                                $stripe_customer->id,
                                ['invoice_settings' => ['default_payment_method' => $payment_method->id]]
                            );
                        } catch (\Exception $e) {
                            // Continue anyway - we'll fix payment methods later
                        }
                    } else if (!$is_indian_customer) {
                        $setup_intent = \Stripe\SetupIntent::create([
                            'customer' => $stripe_customer->id,
                            'payment_method' => $payment_method->id,
                            'payment_method_types' => ['card'],
                            'usage' => 'off_session',
                            'automatic_payment_methods' => [
                                'enabled' => true,
                                'allow_redirects' => 'never'
                            ],
                            'metadata' => [
                                'user_email' => $email,
                                'registration_flow' => 'consultant'
                            ],
                            'confirm' => true,
                            'return_url' => 'https://visafy.io/consultant-registration.php'
                        ]);
                        
                        if ($setup_intent->status === 'requires_action' && $setup_intent->next_action) {
                            throw new \Exception("3D Secure authentication is required for this card. Please try again and complete the authentication when prompted.");
                        }
                        
                        if ($setup_intent->status !== 'succeeded') {
                            throw new \Exception("Failed to save payment method. Status: " . $setup_intent->status);
                        }
                        
                        \Stripe\Customer::update(
                            $stripe_customer->id,
                            ['invoice_settings' => ['default_payment_method' => $payment_method->id]]
                        );
                    } else {
                        // For Indian customers, the payment method should already be attached via 3DS flow
                        // Just set it as the default payment method
                        try {
                            \Stripe\Customer::update(
                                $stripe_customer->id,
                                ['invoice_settings' => ['default_payment_method' => $payment_method->id]]
                            );
                        } catch (\Exception $e) {
                            // If setting default payment method fails, continue anyway
                            // The payment method is still valid and attached
                        }
                    }
                    
                    // Create organization
                    $org_name = !empty($company_name) ? $company_name : $first_name . ' ' . $last_name . "'s Organization";
                    $org_description = "Organization for " . $first_name . " " . $last_name;
                    
                    $insert_org_query = "INSERT INTO organizations (name, description) VALUES (?, ?)";
                    $stmt = $conn->prepare($insert_org_query);
                    $stmt->bind_param('ss', $org_name, $org_description);
                    $stmt->execute();
                    $organization_id = $conn->insert_id;
                    
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Create user
                    $insert_user_query = "INSERT INTO users (first_name, last_name, email, phone, password, user_type, email_verified, organization_id) 
                                         VALUES (?, ?, ?, ?, ?, 'consultant', 0, ?)";
                    $stmt = $conn->prepare($insert_user_query);
                    $stmt->bind_param('sssssi', $first_name, $last_name, $email, $phone, $hashed_password, $organization_id);
                    $stmt->execute();
                    $user_id = $conn->insert_id;
                    
                    // Create consultant
                    $insert_consultant_query = "INSERT INTO consultants (user_id, membership_plan_id, company_name) 
                                               VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($insert_consultant_query);
                    $stmt->bind_param('iis', $user_id, $membership_plan_id, $company_name);
                    $stmt->execute();
                    
                    // Create consultant profile
                    $insert_profile_query = "INSERT INTO consultant_profiles (consultant_id) VALUES (?)";
                    $stmt = $conn->prepare($insert_profile_query);
                    $stmt->bind_param('i', $user_id);
                    $stmt->execute();
                    
                    // Create payment method record
                    $insert_payment_query = "INSERT INTO payment_methods (user_id, method_type, provider, account_number, token, billing_address_line1, billing_address_line2, billing_city, billing_state, billing_postal_code, billing_country, is_default) 
                                            VALUES (?, 'credit_card', 'stripe', ?, ?, ?, ?, ?, ?, ?, ?, 1)";
                    $last_four = $payment_method->card->last4 ?? substr($payment_method_id, -4);
                    $stmt = $conn->prepare($insert_payment_query);
                    $stmt->bind_param('issssssss', $user_id, $last_four, $stripe_customer->id, $address_line1, $address_line2, $city, $state, $postal_code, $country);
                    $stmt->execute();
                    $payment_method_id_db = $conn->insert_id;
                    
                    // Create subscription
                    $insert_subscription_query = "INSERT INTO subscriptions (user_id, membership_plan_id, payment_method_id, status, start_date, end_date, auto_renew) 
                                                 VALUES (?, ?, ?, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH), 1)";
                    $stmt = $conn->prepare($insert_subscription_query);
                    $stmt->bind_param('iii', $user_id, $membership_plan_id, $payment_method_id_db);
                    $stmt->execute();
                    $subscription_id = $conn->insert_id;
                    
                    // Generate verification token
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                    
                    $update_token_query = "UPDATE users SET email_verification_token = ?, email_verification_expires = ? WHERE id = ?";
                    $stmt = $conn->prepare($update_token_query);
                    $stmt->bind_param('ssi', $token, $expires, $user_id);
                    $stmt->execute();
                    
                    // After successful subscription creation, record promo code usage if applicable
                    if ($promo_code_id) {
                        $insert_promo_usage_query = "INSERT INTO promo_code_usage 
                            (promo_code_id, user_id, subscription_id, original_price, discount_amount, final_price)
                            VALUES (?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($insert_promo_usage_query);
                        $stmt->bind_param("iiiddd", $promo_code_id, $user_id, $subscription_id, 
                                        $plan['price'], $discount_amount, $final_price);
                        $stmt->execute();
                    }
                    
                    // Commit transaction
                    $conn->commit();
                    
                    // Send verification email
                    $verification_link = "https://visafy.io/verify_email.php?token=" . $token;
                    $email_subject = "Verify Your Email Address";
                    $email_body = "Hi $first_name,\n\nPlease click the following link to verify your email address:\n$verification_link\n\nThis link will expire in 24 hours.\n\nThank you,\nThe Visafy Team";
                    
                    if (function_exists('send_email')) {
                        send_email($email, $email_subject, $email_body);
                    }
                    
                    $success_message = "Registration successful! Please check your email to verify your account.";
                    
                    // Clear form data
                    $first_name = $last_name = $email = $phone = $company_name = $address_line1 = $address_line2 = $city = $state = $postal_code = $country = '';
                    
                } catch (\Stripe\Exception\CardException $e) {
                    throw $e;
                } catch (\Stripe\Exception\RateLimitException $e) {
                    throw $e;
                } catch (\Stripe\Exception\InvalidRequestException $e) {
                    throw $e;
                } catch (\Stripe\Exception\AuthenticationException $e) {
                    throw $e;
                } catch (\Stripe\Exception\ApiConnectionException $e) {
                    throw $e;
                } catch (\Stripe\Exception\ApiErrorException $e) {
                    throw $e;
                }
                
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Registration failed: " . $e->getMessage();
            }
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Function to generate Stripe client secret for 3DS authentication
function generate_setup_intent($email, $name, $phone) {
    global $stripe_secret_key;
    
    try {
        \Stripe\Stripe::setApiKey($stripe_secret_key);
        
        $customer = \Stripe\Customer::create([
            'email' => $email,
            'name' => $name,
            'phone' => $phone,
            'description' => 'Temporary customer for 3DS authentication',
            'metadata' => [
                'temp_for_3ds' => 'true',
                'registration_flow' => 'consultant',
                'created_at' => date('Y-m-d H:i:s')
            ]
        ]);
        
        $intent = \Stripe\SetupIntent::create([
            'customer' => $customer->id,
            'payment_method_types' => ['card'],
            'usage' => 'off_session',
            'return_url' => 'https://beige-antelope-215732.hostingersite.com/consultant-registration.php',
            'metadata' => [
                'customer_email' => $email,
                'customer_name' => $name,
                'phone' => $phone,
                'for_registration' => 'true'
            ]
        ]);
        
        return [
            'success' => true,
            'clientSecret' => $intent->client_secret,
            'customer' => $customer->id,
            'setup_intent_id' => $intent->id
        ];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Function to safely handle payment method attachment
function safe_attach_payment_method($payment_method_id, $customer_id) {
    try {
        $payment_method = \Stripe\PaymentMethod::retrieve($payment_method_id);
        
        // Check if payment method is already attached to customer
        $is_attached = false;
        try {
            $payment_methods = \Stripe\PaymentMethod::all([
                'customer' => $customer_id,
                'type' => 'card'
            ]);
            
            foreach ($payment_methods->data as $pm) {
                if ($pm->id === $payment_method_id) {
                    $is_attached = true;
                    break;
                }
            }
        } catch (\Exception $e) {
            // If we can't check existing payment methods, assume it's not attached
            $is_attached = false;
        }
        
        if (!$is_attached) {
            $setup_intent = \Stripe\SetupIntent::create([
                'customer' => $customer_id,
                'payment_method' => $payment_method_id,
                'confirm' => true,
                'usage' => 'off_session',
                'automatic_payment_methods' => [
                    'enabled' => true,
                    'allow_redirects' => 'never'
                ],
                'return_url' => 'https://visafy.io/consultant-registration.php'
            ]);
            
            if ($setup_intent->status === 'requires_action') {
                return [
                    'success' => false,
                    'requires_action' => true,
                    'client_secret' => $setup_intent->client_secret
                ];
            }
        }
        
        \Stripe\Customer::update(
            $customer_id,
            ['invoice_settings' => ['default_payment_method' => $payment_method_id]]
        );
        
        return ['success' => true];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Handle setup intent creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_setup_intent') {
    header('Content-Type: application/json');
    
    $email = $_POST['email'] ?? '';
    $name = $_POST['name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    
    if (empty($email) || empty($name)) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }
    
    $result = generate_setup_intent($email, $name, $phone);
    echo json_encode($result);
    exit;
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ... Rest of the HTML and JavaScript code remains unchanged ...
?>

<div class="content">
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success_message)): ?>
        <div class="registration-success">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2>Registration Successful!</h2>
            <p>Thank you for registering. Please check your email to verify your account.</p>
            <p>Once verified, you'll be able to log in and set up your profile.</p>
            <div class="success-actions">
                <a href="login.php" class="btn primary-btn">Go to Login</a>
            </div>
        </div>
    <?php else: ?>
        <div class="registration-page">
            <div class="registration-container">
                <h1>Consultant Registration</h1>
                <p class="subtitle">Complete your registration to join Visafy as a consultant</p>
                
                <div class="registration-grid">
                    <!-- Registration Form Section -->
                    <div class="registration-form-container">
                        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?plan_id=' . $selected_plan_id; ?>" method="POST" id="registrationForm" enctype="multipart/form-data">
                            <div class="form-section">
                                <h3>Personal Information</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="first_name">First Name*</label>
                                        <input type="text" name="first_name" id="first_name" class="form-control" required value="<?php echo htmlspecialchars($first_name ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="last_name">Last Name*</label>
                                        <input type="text" name="last_name" id="last_name" class="form-control" required value="<?php echo htmlspecialchars($last_name ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="email">Email Address*</label>
                                        <input type="email" name="email" id="email" class="form-control" required value="<?php echo htmlspecialchars($email ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="phone">Phone Number*</label>
                                        <input type="tel" name="phone" id="phone" class="form-control" required value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="password">Password*</label>
                                        <input type="password" name="password" id="password" class="form-control" required>
                                        <small class="form-text">Must be at least 8 characters long</small>
                                    </div>
                                    <div class="form-group">
                                        <label for="confirm_password">Confirm Password*</label>
                                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <h3>Company Information</h3>
                                <div class="form-group">
                                    <label for="company_name">Company Name (Optional)</label>
                                    <input type="text" name="company_name" id="company_name" class="form-control" value="<?php echo htmlspecialchars($company_name ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <h3>Billing Address</h3>
                                <div class="form-group">
                                    <label for="address_line1">Address Line 1*</label>
                                    <input type="text" name="address_line1" id="address_line1" class="form-control" required value="<?php echo htmlspecialchars($address_line1 ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="address_line2">Address Line 2</label>
                                    <input type="text" name="address_line2" id="address_line2" class="form-control" value="<?php echo htmlspecialchars($address_line2 ?? ''); ?>">
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="city">City*</label>
                                        <input type="text" name="city" id="city" class="form-control" required value="<?php echo htmlspecialchars($city ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="state">State/Province*</label>
                                        <input type="text" name="state" id="state" class="form-control" required value="<?php echo htmlspecialchars($state ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="postal_code">Postal Code*</label>
                                        <input type="text" name="postal_code" id="postal_code" class="form-control" required value="<?php echo htmlspecialchars($postal_code ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="country">Country*</label>
                                        <select name="country" id="country" class="form-control" required>
                                            <option value="">Select Country</option>
                                            <option value="US" <?php echo (($country ?? '') === 'US') ? 'selected' : ''; ?>>United States</option>
                                            <option value="CA" <?php echo (($country ?? '') === 'CA') ? 'selected' : ''; ?>>Canada</option>
                                            <option value="GB" <?php echo (($country ?? '') === 'GB') ? 'selected' : ''; ?>>United Kingdom</option>
                                            <option value="AU" <?php echo (($country ?? '') === 'AU') ? 'selected' : ''; ?>>Australia</option>
                                            <option value="IN" <?php echo (($country ?? '') === 'IN') ? 'selected' : ''; ?>>India</option>
                                            <!-- Add more countries as needed -->
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <h3>Payment Information</h3>
                                <div class="payment-form">
                                    <div class="form-group">
                                        <label for="card-element">Credit or Debit Card*</label>
                                        <div id="card-element" class="form-control">
                                            <!-- Stripe Element will be inserted here -->
                                        </div>
                                        <!-- Used to display form errors -->
                                        <div id="card-errors" role="alert" class="payment-error-message"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <h3>Promo Code</h3>
                                <div class="promo-code-container">
                                    <div class="form-group">
                                        <label for="promo_code">Have a promo code?</label>
                                        <div class="promo-input-group">
                                            <input type="text" name="promo_code" id="promo_code" class="form-control" placeholder="Enter promo code">
                                            <button type="button" id="apply_promo" class="btn btn-secondary">Apply</button>
                                        </div>
                                        <div id="promo_message" class="promo-message"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <input type="hidden" name="membership_plan_id" id="membership_plan_id" value="<?php echo $selected_plan ? $selected_plan['id'] : ''; ?>">
                            <input type="hidden" name="payment_method_id" id="payment_method_id" value="">
                            <input type="hidden" name="applied_promo_code_id" id="applied_promo_code_id" value="">
                            <input type="hidden" name="discounted_price" id="discounted_price" value="">
                            
                            <div class="terms-privacy">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="terms_agree" id="terms_agree" required>
                                    <label for="terms_agree">I agree to the <a href="terms.php" target="_blank">Terms of Service</a> and <a href="privacy.php" target="_blank">Privacy Policy</a></label>
                                </div>
                            </div>
                            
                            <div class="form-buttons">
                                <a href="become-member.php" class="btn cancel-btn">Back to Plans</a>
                                <button type="submit" name="register_member" id="register_member_btn" class="btn submit-btn" value="1">Register Now</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Plan Summary Section -->
                    <div class="plan-summary-container">
                        <div class="plan-summary">
                            <h3>Selected Plan</h3>
                            
                            <?php if ($selected_plan): ?>
                                <div class="selected-plan">
                                    <div class="plan-header">
                                        <h4 class="plan-name"><?php echo htmlspecialchars($selected_plan['name']); ?></h4>
                                        <div class="plan-price">$<?php echo number_format($selected_plan['price'], 2); ?></div>
                                        <div class="plan-billing">per month</div>
                                    </div>
                                    <div class="plan-features">
                                        <div class="feature">
                                            <i class="fas fa-users"></i>
                                            <div>Up to <?php echo (int)$selected_plan['max_team_members']; ?> team members</div>
                                        </div>
                                        <div class="feature">
                                            <i class="fas fa-check-circle"></i>
                                            <div>Client management tools</div>
                                        </div>
                                        <div class="feature">
                                            <i class="fas fa-check-circle"></i>
                                            <div>Document management</div>
                                        </div>
                                        <div class="feature">
                                            <i class="fas fa-check-circle"></i>
                                            <div>Visa tracking system</div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="no-plan-selected">
                                    <p>You haven't selected a plan yet.</p>
                                    <a href="become-member.php#membership-plans" class="btn btn-primary">Choose a Plan</a>
                                </div>
                            <?php endif; ?>
                            
                            <div class="change-plan">
                                <h4>Want a different plan?</h4>
                                <div class="plan-options">
                                    <?php foreach ($plans as $plan): ?>
                                        <div class="plan-option <?php echo $selected_plan && $selected_plan['id'] == $plan['id'] ? 'selected' : ''; ?>">
                                            <input type="radio" name="plan_selection" id="plan-<?php echo $plan['id']; ?>" value="<?php echo $plan['id']; ?>" 
                                                <?php echo $selected_plan && $selected_plan['id'] == $plan['id'] ? 'checked' : ''; ?>
                                                onchange="updateSelectedPlan(<?php echo $plan['id']; ?>)">
                                            <label for="plan-<?php echo $plan['id']; ?>">
                                                <span class="plan-name"><?php echo htmlspecialchars($plan['name']); ?></span>
                                                <span class="plan-price">$<?php echo number_format($plan['price'], 2); ?>/month</span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="registration-help">
                            <h4>Need Help?</h4>
                            <p>If you have any questions about our membership plans or the registration process, please contact us.</p>
                            <a href="contact.php" class="btn btn-secondary btn-small">Contact Support</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Include Stripe.js -->
<script src="https://js.stripe.com/v3/"></script>

<script>
// Function to handle 3D Secure (3DS) authentication if needed
function handle3dsAuthentication(stripe, clientSecret, card, submitButton) {
    return stripe.confirmCardSetup(clientSecret, {
        payment_method: {
            card: card
        }
    }).then(function(result) {
        if (result.error) {
            throw result.error;
        } else {
            return result.setupIntent;
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Check if the form exists before doing anything else
    const registrationForm = document.getElementById('registrationForm');
    if (!registrationForm) {
        return;
    }
    
    // Get Stripe publishable key
    const stripePublishableKey = '<?php echo $stripe_publishable_key; ?>';
    
    // Only initialize Stripe if key exists
    if (!stripePublishableKey) {
        // Add visual warning to the payment section
        const cardElement = document.getElementById('card-element');
        if (cardElement) {
            cardElement.innerHTML = '<div style="color: #721c24; padding: 10px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px;">Stripe API key not configured. Please check server configuration.</div>';
        }
        return;
    }
    
    // Check for card element before initializing Stripe
    const cardElement = document.getElementById('card-element');
    if (!cardElement) {
        return;
    }
    
    try {
        // Initialize Stripe using the publishable key
        const stripe = Stripe(stripePublishableKey);
        const elements = stripe.elements();
        
        // Create card Element
        const card = elements.create('card', {
            style: {
                base: {
                    color: '#32325d',
                    fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
                    fontSmoothing: 'antialiased',
                    fontSize: '16px',
                    '::placeholder': {
                        color: '#aab7c4'
                    }
                },
                invalid: {
                    color: '#e74a3b',
                    iconColor: '#e74a3b'
                }
            }
        });
        
        // Mount the card element
        card.mount(cardElement);
        
        // Handle real-time validation errors
        card.addEventListener('change', function(event) {
            const displayError = document.getElementById('card-errors');
            if (displayError) {
                if (event.error) {
                    displayError.textContent = event.error.message;
                } else {
                    displayError.textContent = '';
                }
            }
        });
        
        // Check if all required form elements exist
        const submitButton = document.getElementById('register_member_btn');
        const firstNameInput = document.getElementById('first_name');
        const lastNameInput = document.getElementById('last_name');
        const hiddenInput = document.getElementById('payment_method_id');
        
        if (!submitButton || !firstNameInput || !lastNameInput || !hiddenInput) {
            return;
        }
        
        // Handle form submission - directly attach to the form
        if (registrationForm) {
            // Add a global variable to track if the form is already being submitted
            let isSubmitting = false;
            
            registrationForm.addEventListener('submit', function(event) {
                // Prevent double submission
                if (isSubmitting) {
                    event.preventDefault();
                    return false;
                }
                
                event.preventDefault();
                isSubmitting = true;
                
                // Disable the submit button to prevent multiple submissions
                submitButton.disabled = true;
                submitButton.classList.add('disabled');
                submitButton.innerHTML = 'Processing Payment... <span class="processing-payment"><i class="fas fa-spinner fa-spin"></i></span>';
                
                const cardholderName = firstNameInput.value + ' ' + lastNameInput.value;
                
                // Get billing address details
                const addressLine1Input = document.getElementById('address_line1');
                const addressLine2Input = document.getElementById('address_line2');
                const cityInput = document.getElementById('city');
                const stateInput = document.getElementById('state');
                const postalCodeInput = document.getElementById('postal_code');
                const countryInput = document.getElementById('country');
                
                const addressLine1 = addressLine1Input ? addressLine1Input.value : '';
                const addressLine2 = addressLine2Input ? addressLine2Input.value : '';
                const city = cityInput ? cityInput.value : '';
                const state = stateInput ? stateInput.value : '';
                const postalCode = postalCodeInput ? postalCodeInput.value : '';
                const country = countryInput ? countryInput.value : '';
                
                // For India or other countries that may require 3DS, we need to handle it differently
                const isIndiaCard = country === 'IN';
                
                // If it's an Indian card, we need to use SetupIntent with 3DS
                if (isIndiaCard) {
                    // First, create a setup intent on the server
                    const formData = new FormData();
                    formData.append('action', 'create_setup_intent');
                    formData.append('email', document.getElementById('email').value);
                    formData.append('name', cardholderName);
                    formData.append('phone', document.getElementById('phone').value);
                    
                    // Create a direct endpoint instead of using window.location.href
                    const currentUrl = window.location.href.split('?')[0]; // Remove any query parameters
                    
                    fetch(currentUrl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        // Check if the response is JSON
                        const contentType = response.headers.get('content-type');
                        if (!contentType || !contentType.includes('application/json')) {
                            return response.text().then(text => {
                                throw new Error('Server returned non-JSON response');
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (!data.success) {
                            const errorElement = document.getElementById('card-errors');
                            if (errorElement) {
                                errorElement.textContent = data.error || 'An error occurred. Please try again.';
                            }
                            
                            // Re-enable the submit button
                            submitButton.disabled = false;
                            submitButton.classList.remove('disabled');
                            submitButton.innerHTML = 'Register Now';
                            return;
                        }
                        
                        // We have the client secret, now handle 3DS authentication
                        handle3dsAuthentication(stripe, data.clientSecret, card, submitButton)
                            .then(setupIntent => {
                                // 3DS authentication succeeded, now get the payment method ID
                                const paymentMethodId = setupIntent.payment_method;
                                
                                // Send the payment method ID to the server
                                hiddenInput.value = paymentMethodId;
                                document.getElementById('payment_method_id').value = paymentMethodId;
                                
                                // Add the customer ID from the setup intent
                                const customerIdInput = document.createElement('input');
                                customerIdInput.type = 'hidden';
                                customerIdInput.name = 'customer_id';
                                customerIdInput.value = data.customer;
                                registrationForm.appendChild(customerIdInput);
                                
                                // Complete form submission
                                submitFormWithExtras();
                            })
                            .catch(error => {
                                const errorElement = document.getElementById('card-errors');
                                if (errorElement) {
                                    errorElement.textContent = error.message || 'Authentication failed. Please try again.';
                                }
                                
                                // Re-enable the submit button
                                submitButton.disabled = false;
                                submitButton.classList.remove('disabled');
                                submitButton.innerHTML = 'Register Now';
                            });
                    })
                    .catch(error => {
                        const errorElement = document.getElementById('card-errors');
                        if (errorElement) {
                            errorElement.textContent = 'Network error. Please try again.';
                        }
                        
                        // Re-enable the submit button
                        submitButton.disabled = false;
                        submitButton.classList.remove('disabled');
                        submitButton.innerHTML = 'Register Now';
                        
                        // Fallback: If fetch fails completely, try a direct payment method creation
                        stripe.createPaymentMethod({
                            type: 'card',
                            card: card,
                            billing_details: {
                                name: cardholderName,
                                email: document.getElementById('email').value,
                                phone: document.getElementById('phone').value,
                                address: {
                                    line1: addressLine1,
                                    line2: addressLine2,
                                    city: city,
                                    state: state,
                                    postal_code: postalCode,
                                    country: country
                                }
                            }
                        }).then(function(result) {
                            if (result.error) {
                                if (errorElement) {
                                    errorElement.textContent = result.error.message;
                                }
                                return;
                            }
                            
                            // Add a flag to indicate this is a fallback method that may need special handling
                            hiddenInput.value = result.paymentMethod.id;
                            document.getElementById('payment_method_id').value = result.paymentMethod.id;
                            
                            submitFormWithExtras();
                        }).catch(function(error) {
                            if (errorElement) {
                                errorElement.textContent = 'Payment processing failed. Please try again.';
                            }
                        });
                    });
                } else {
                    // For non-Indian cards, proceed with the regular flow
                    stripe.createPaymentMethod({
                        type: 'card',
                        card: card,
                        billing_details: {
                            name: cardholderName,
                            address: {
                                line1: addressLine1,
                                line2: addressLine2,
                                city: city,
                                state: state,
                                postal_code: postalCode,
                                country: country
                            }
                        }
                    }).then(function(result) {
                        const errorElement = document.getElementById('card-errors');
                        
                        if (result.error) {
                            if (errorElement) {
                                errorElement.textContent = result.error.message;
                            }
                            
                            // Re-enable the submit button
                            submitButton.disabled = false;
                            submitButton.classList.remove('disabled');
                            submitButton.innerHTML = 'Register Now';
                        } else {
                            // Send the payment method ID to the server
                            hiddenInput.value = result.paymentMethod.id;
                            document.getElementById('payment_method_id').value = result.paymentMethod.id;
                            
                            submitFormWithExtras();
                        }
                    }).catch(function(error) {
                        const errorElement = document.getElementById('card-errors');
                        if (errorElement) {
                            errorElement.textContent = 'An error occurred with the payment processor. Please try again later.';
                        }
                        
                        // Re-enable the submit button
                        submitButton.disabled = false;
                        submitButton.classList.remove('disabled');
                        submitButton.innerHTML = 'Register Now';
                    });
                }
                
                // Function to submit the form with additional hidden fields
                function submitFormWithExtras() {
                    try {
                                               // Before submitting the form, ensure the register_member field is included
                                               if (!document.querySelector('input[name="register_member"]')) {
                            const hiddenRegisterField = document.createElement('input');
                            hiddenRegisterField.type = 'hidden';
                            hiddenRegisterField.name = 'register_member';
                            hiddenRegisterField.value = '1';
                            registrationForm.appendChild(hiddenRegisterField);
                        }
                        
                        // Make sure submit button gets included in form data too
                        const submitBtn = document.getElementById('register_member_btn');
                        if (submitBtn) {
                            const hiddenBtnField = document.createElement('input');
                            hiddenBtnField.type = 'hidden';
                            hiddenBtnField.name = submitBtn.name;
                            hiddenBtnField.value = submitBtn.value || '1';
                            registrationForm.appendChild(hiddenBtnField);
                        }
                        
                        registrationForm.submit();
                    } catch (err) {
                        isSubmitting = false;
                        
                        // Re-enable the submit button
                        submitButton.disabled = false;
                        submitButton.classList.remove('disabled');
                        submitButton.innerHTML = 'Register Now';
                    }
                }
            });
        }
    } catch (error) {
        const errorElement = document.getElementById('card-errors');
        if (errorElement) {
            errorElement.textContent = 'An error occurred while setting up the payment form. Please try again later.';
        }
    }
});

// Function to update selected plan
function updateSelectedPlan(planId) {
    const planIdInput = document.getElementById('membership_plan_id');
    if (planIdInput) {
        planIdInput.value = planId;
    }
    
    // Redirect to same page with plan_id parameter to refresh the view
    window.location.href = 'consultant-registration.php?plan_id=' + planId;
}

// Promo code handling
document.getElementById('apply_promo').addEventListener('click', function() {
    const promoCode = document.getElementById('promo_code').value.trim();
    const planId = document.getElementById('membership_plan_id').value;
    const messageDiv = document.getElementById('promo_message');
    
    if (!promoCode) {
        messageDiv.textContent = 'Please enter a promo code';
        messageDiv.className = 'promo-message error';
        return;
    }
    
    // Clear previous messages
    messageDiv.textContent = 'Validating...';
    messageDiv.className = 'promo-message';
    
    // Make API call to validate promo code
    fetch('/api/validate-promo.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            code: promoCode,
            plan_id: planId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            messageDiv.textContent = 'Promo code applied successfully!';
            messageDiv.className = 'promo-message success';
            
            // Update price display
            const originalPrice = parseFloat(data.original_price);
            const discountAmount = parseFloat(data.discount_amount);
            const finalPrice = parseFloat(data.final_price);
            
            // Update hidden fields
            document.getElementById('applied_promo_code_id').value = data.promo_code_id;
            document.getElementById('discounted_price').value = finalPrice;
            
            // Update price display in the plan summary
            const priceDisplay = document.querySelector('.plan-price');
            if (priceDisplay) {
                const priceBreakdown = document.createElement('div');
                priceBreakdown.className = 'price-breakdown';
                priceBreakdown.innerHTML = `
                    <div class="item">
                        <span>Original Price:</span>
                        <span>$${originalPrice.toFixed(2)}</span>
                    </div>
                    <div class="item">
                        <span>Discount:</span>
                        <span>-$${discountAmount.toFixed(2)}</span>
                    </div>
                    <div class="item total">
                        <span>Final Price:</span>
                        <span>$${finalPrice.toFixed(2)}</span>
                    </div>
                `;
                
                // Replace existing price breakdown if any
                const existingBreakdown = priceDisplay.nextElementSibling;
                if (existingBreakdown && existingBreakdown.className === 'price-breakdown') {
                    existingBreakdown.remove();
                }
                priceDisplay.insertAdjacentElement('afterend', priceBreakdown);
            }
        } else {
            messageDiv.textContent = data.message || 'Invalid promo code';
            messageDiv.className = 'promo-message error';
            
            // Clear any applied promo code
            document.getElementById('applied_promo_code_id').value = '';
            document.getElementById('discounted_price').value = '';
            
            // Remove price breakdown if exists
            const priceDisplay = document.querySelector('.plan-price');
            if (priceDisplay) {
                const existingBreakdown = priceDisplay.nextElementSibling;
                if (existingBreakdown && existingBreakdown.className === 'price-breakdown') {
                    existingBreakdown.remove();
                }
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        messageDiv.textContent = 'Error validating promo code. Please try again.';
        messageDiv.className = 'promo-message error';
    });
});
</script>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>