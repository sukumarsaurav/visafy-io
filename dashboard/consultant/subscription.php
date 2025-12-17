<?php
$page_title = "Manage Subscription";
$page_specific_css = "../assets/css/subscription.css";
require_once 'includes/header.php';

// Get consultant data
$user_id = $_SESSION['id'];

// Current subscription
$subscription_query = "SELECT s.*, mp.name, mp.price, mp.billing_cycle, mp.max_team_members
                      FROM subscriptions s 
                      JOIN membership_plans mp ON s.membership_plan_id = mp.id
                      WHERE s.user_id = ? AND s.status = 'active'
                      ORDER BY s.end_date DESC LIMIT 1";
$stmt = $conn->prepare($subscription_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_subscription = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch consultant data
$consultant_query = "SELECT c.*, mp.name as current_plan, mp.max_team_members 
                    FROM consultants c 
                    JOIN membership_plans mp ON c.membership_plan_id = mp.id 
                    WHERE c.user_id = ?";
$stmt = $conn->prepare($consultant_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$consultant = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get available plans
$plans_query = "SELECT mp.id, mp.name, mp.price, mp.billing_cycle, mp.max_team_members 
                FROM membership_plans mp 
                ORDER BY mp.price ASC";
$stmt = $conn->prepare($plans_query);
$stmt->execute();
$available_plans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get payment history
$payments_query = "SELECT p.*, pm.method_type, pm.account_number, s.membership_plan_id, mp.name as plan_name
                  FROM payments p
                  LEFT JOIN payment_methods pm ON p.payment_method_id = pm.id
                  LEFT JOIN subscriptions s ON p.subscription_id = s.id
                  LEFT JOIN membership_plans mp ON s.membership_plan_id = mp.id
                  WHERE p.user_id = ?
                  ORDER BY p.payment_date DESC LIMIT 10";
$stmt = $conn->prepare($payments_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$payment_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get saved payment methods
$payment_methods_query = "SELECT * FROM payment_methods WHERE user_id = ? AND is_default = 1";
$stmt = $conn->prepare($payment_methods_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$default_payment_method = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle subscription changes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Upgrade subscription
    if (isset($_POST['upgrade_plan'])) {
        $new_plan_id = $_POST['new_plan_id'];
        
        // Get new plan details
        $plan_query = "SELECT * FROM membership_plans WHERE id = ?";
        $stmt = $conn->prepare($plan_query);
        $stmt->bind_param("i", $new_plan_id);
        $stmt->execute();
        $new_plan = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($new_plan) {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Update consultant record
                $update_consultant = "UPDATE consultants SET membership_plan_id = ? WHERE user_id = ?";
                $stmt = $conn->prepare($update_consultant);
                $stmt->bind_param("ii", $new_plan_id, $user_id);
                $stmt->execute();
                $stmt->close();
                
                // Calculate subscription dates based on billing cycle
                $start_date = date('Y-m-d H:i:s');
                $end_date = date('Y-m-d H:i:s', strtotime('+1 month'));
                
                if ($new_plan['billing_cycle'] == 'quarterly') {
                    $end_date = date('Y-m-d H:i:s', strtotime('+3 months'));
                } elseif ($new_plan['billing_cycle'] == 'annually') {
                    $end_date = date('Y-m-d H:i:s', strtotime('+1 year'));
                }
                
                // Update current subscription to expired if exists
                if ($current_subscription) {
                    $update_current = "UPDATE subscriptions SET status = 'expired' WHERE id = ?";
                    $stmt = $conn->prepare($update_current);
                    $stmt->bind_param("i", $current_subscription['id']);
                    $stmt->execute();
                    $stmt->close();
                }
                
                // Create new subscription record
                $insert_subscription = "INSERT INTO subscriptions (user_id, membership_plan_id, payment_method_id, status, start_date, end_date, auto_renew) 
                                      VALUES (?, ?, ?, 'active', ?, ?, 1)";
                $stmt = $conn->prepare($insert_subscription);
                $payment_method_id = $default_payment_method ? $default_payment_method['id'] : null;
                $stmt->bind_param("iiiss", $user_id, $new_plan_id, $payment_method_id, $start_date, $end_date);
                $stmt->execute();
                $new_subscription_id = $conn->insert_id;
                $stmt->close();
                
                // Record payment
                $insert_payment = "INSERT INTO payments (user_id, subscription_id, payment_method_id, amount, currency, status, payment_date, description) 
                                  VALUES (?, ?, ?, ?, 'USD', 'completed', NOW(), ?)";
                $description = "Subscription payment for " . $new_plan['name'] . " plan (" . $new_plan['billing_cycle'] . ")";
                $stmt = $conn->prepare($insert_payment);
                $stmt->bind_param("iiids", $user_id, $new_subscription_id, $payment_method_id, $new_plan['price'], $description);
                $stmt->execute();
                $stmt->close();
                
                // Commit transaction
                $conn->commit();
                
                // Set success message
                $success_message = "Your subscription has been successfully upgraded to the " . $new_plan['name'] . " plan.";
                
                // Redirect to refresh page data
                header("Location: subscription.php?success=1");
                exit;
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $error_message = "Error updating subscription: " . $e->getMessage();
            }
        } else {
            $error_message = "Selected plan is not valid.";
        }
    }
    
    // Cancel subscription
    if (isset($_POST['cancel_subscription']) && $current_subscription) {
        $update_subscription = "UPDATE subscriptions SET status = 'canceled', auto_renew = 0 WHERE id = ?";
        $stmt = $conn->prepare($update_subscription);
        $stmt->bind_param("i", $current_subscription['id']);
        
        if ($stmt->execute()) {
            $success_message = "Your subscription has been canceled. You will still have access until " . date('F j, Y', strtotime($current_subscription['end_date'])) . ".";
        } else {
            $error_message = "Error canceling subscription: " . $conn->error;
        }
        $stmt->close();
    }
    
    // Add payment method
    if (isset($_POST['add_payment_method'])) {
        $method_type = $_POST['method_type'];
        $provider = $_POST['provider'];
        $account_number = $_POST['account_number'];
        $expiry_date = isset($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        $is_default = isset($_POST['is_default']) ? 1 : 0;
        
        // If setting as default, update all existing methods to non-default
        if ($is_default) {
            $update_defaults = "UPDATE payment_methods SET is_default = 0 WHERE user_id = ?";
            $stmt = $conn->prepare($update_defaults);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        }
        
        // Insert new payment method
        $insert_method = "INSERT INTO payment_methods (user_id, method_type, provider, account_number, expiry_date, is_default) 
                         VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_method);
        $stmt->bind_param("issssi", $user_id, $method_type, $provider, $account_number, $expiry_date, $is_default);
        
        if ($stmt->execute()) {
            $success_message = "Payment method added successfully.";
            // Redirect to refresh page data
            header("Location: subscription.php?payment_added=1");
            exit;
        } else {
            $error_message = "Error adding payment method: " . $conn->error;
        }
        $stmt->close();
    }
}
?>

<div class="content">
    <div class="page-header">
        <h1>Subscription Management</h1>
        <p>Manage your subscription plan and billing details</p>
    </div>

    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <?php echo $error_message; ?>
    </div>
    <?php endif; ?>

    <?php if (isset($success_message) || isset($_GET['success']) || isset($_GET['payment_added'])): ?>
    <div class="alert alert-success">
        <?php 
        if (isset($success_message)) {
            echo $success_message;
        } elseif (isset($_GET['success'])) {
            echo "Your subscription has been updated successfully.";
        } elseif (isset($_GET['payment_added'])) {
            echo "Your payment method has been added successfully.";
        }
        ?>
    </div>
    <?php endif; ?>

    <div class="subscription-container">
        <!-- Current Plan Section -->
        <div class="card">
            <div class="card-header">
                <h2>Current Subscription</h2>
            </div>
            <div class="card-body">
                <?php if ($current_subscription): ?>
                <div class="current-plan">
                    <div class="plan-badge <?php echo strtolower($current_subscription['name']); ?>">
                        <?php echo $current_subscription['name']; ?>
                    </div>
                    <div class="plan-details">
                        <h3><?php echo $current_subscription['name']; ?> Plan</h3>
                        <div class="plan-info">
                            <p><strong>Billing Cycle:</strong> <?php echo ucfirst($current_subscription['billing_cycle']); ?></p>
                            <p><strong>Price:</strong> $<?php echo number_format($current_subscription['price'], 2); ?> / <?php echo $current_subscription['billing_cycle']; ?></p>
                            <p><strong>Team Members:</strong> <?php echo $consultant['team_members_count']; ?> / <?php echo $current_subscription['max_team_members']; ?></p>
                            <p><strong>Status:</strong> <span class="status <?php echo $current_subscription['status']; ?>"><?php echo ucfirst($current_subscription['status']); ?></span></p>
                            <p><strong>Auto-renew:</strong> <?php echo $current_subscription['auto_renew'] ? 'Yes' : 'No'; ?></p>
                            <p><strong>Current Period:</strong> 
                                <?php echo date('M d, Y', strtotime($current_subscription['start_date'])); ?> - 
                                <?php echo date('M d, Y', strtotime($current_subscription['end_date'])); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <?php if ($current_subscription['status'] == 'active'): ?>
                <div class="plan-actions">
                    <button type="button" class="btn btn-primary" id="upgradeBtn">Upgrade/Change Plan</button>
                    <?php if ($current_subscription['auto_renew']): ?>
                    <form method="post" class="inline-form">
                        <input type="hidden" name="cancel_subscription" value="1">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to cancel auto-renewal? Your subscription will remain active until the end date.')">
                            Cancel Auto-Renewal
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php elseif ($current_subscription['status'] == 'canceled'): ?>
                <div class="plan-actions">
                    <button type="button" class="btn btn-primary" id="upgradeBtn">Reactivate Subscription</button>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <div class="no-subscription">
                    <p>You don't have an active subscription. Please select a plan to continue.</p>
                    <button type="button" class="btn btn-primary" id="upgradeBtn">Select a Plan</button>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payment History Section -->
        <div class="card">
            <div class="card-header">
                <h2>Payment History</h2>
            </div>
            <div class="card-body">
                <?php if (!empty($payment_history)): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Method</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payment_history as $payment): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                <td><?php echo $payment['description']; ?></td>
                                <td><span class="payment-status <?php echo $payment['status']; ?>"><?php echo ucfirst($payment['status']); ?></span></td>
                                <td>
                                    <?php if ($payment['method_type']): ?>
                                    <?php echo ucfirst($payment['method_type']); ?> 
                                    <?php if ($payment['account_number']): ?>
                                    (<?php echo $payment['account_number']; ?>)
                                    <?php endif; ?>
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p>No payment history available.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payment Methods Section -->
        <div class="card">
            <div class="card-header">
                <h2>Payment Methods</h2>
            </div>
            <div class="card-body">
                <?php if ($default_payment_method): ?>
                <div class="payment-method">
                    <div class="payment-icon">
                        <i class="fas <?php 
                            echo $default_payment_method['method_type'] == 'credit_card' ? 'fa-credit-card' : 
                                 ($default_payment_method['method_type'] == 'paypal' ? 'fa-paypal' : 'fa-university'); 
                        ?>"></i>
                    </div>
                    <div class="payment-details">
                        <h4><?php echo ucfirst($default_payment_method['method_type']); ?> <?php echo $default_payment_method['provider']; ?></h4>
                        <p>
                            <?php if ($default_payment_method['account_number']): ?>
                            Ending in <?php echo $default_payment_method['account_number']; ?>
                            <?php endif; ?>
                            
                            <?php if ($default_payment_method['expiry_date']): ?>
                            | Expires: <?php echo $default_payment_method['expiry_date']; ?>
                            <?php endif; ?>
                            
                            <span class="default-badge">Default</span>
                        </p>
                    </div>
                </div>
                <?php else: ?>
                <p>No payment methods added yet.</p>
                <?php endif; ?>
                <div class="mt-3">
                    <button type="button" class="btn btn-secondary" id="addPaymentBtn">Add Payment Method</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upgrade Plan Modal -->
<div class="modal" id="upgradePlanModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Choose a Plan</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" id="upgradePlanForm">
                    <input type="hidden" name="upgrade_plan" value="1">
                    <div class="plan-options">
                        <?php foreach ($available_plans as $plan): ?>
                        <div class="plan-option">
                            <input type="radio" name="new_plan_id" id="plan-<?php echo $plan['id']; ?>" value="<?php echo $plan['id']; ?>" <?php 
                                echo (isset($consultant['membership_plan_id']) && $consultant['membership_plan_id'] == $plan['id']) ? 'checked' : ''; 
                            ?>>
                            <label for="plan-<?php echo $plan['id']; ?>" class="plan-card <?php echo strtolower($plan['name']); ?>">
                                <h4><?php echo $plan['name']; ?></h4>
                                <div class="plan-price">
                                    <span class="price">$<?php echo number_format($plan['price'], 2); ?></span>
                                    <span class="cycle">/ <?php echo $plan['billing_cycle']; ?></span>
                                </div>
                                <ul class="plan-features">
                                    <li><i class="fas fa-check"></i> Up to <?php echo $plan['max_team_members']; ?> team members</li>
                                    <li><i class="fas fa-check"></i> Full access to all features</li>
                                    <li><i class="fas fa-check"></i> Priority support</li>
                                </ul>
                                <?php if (isset($consultant['membership_plan_id']) && $consultant['membership_plan_id'] == $plan['id']): ?>
                                <div class="current-plan-badge">Current Plan</div>
                                <?php endif; ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-group mt-4">
                        <div class="payment-method-select">
                            <label>Payment Method:</label>
                            <?php if ($default_payment_method): ?>
                            <div class="selected-payment-method">
                                <i class="fas <?php 
                                    echo $default_payment_method['method_type'] == 'credit_card' ? 'fa-credit-card' : 
                                         ($default_payment_method['method_type'] == 'paypal' ? 'fa-paypal' : 'fa-university'); 
                                ?>"></i>
                                <?php echo ucfirst($default_payment_method['method_type']); ?> 
                                <?php if ($default_payment_method['account_number']): ?>
                                ending in <?php echo $default_payment_method['account_number']; ?>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <p class="text-warning">No payment method available. Please add a payment method first.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" <?php echo !$default_payment_method ? 'disabled' : ''; ?>>
                            Confirm Subscription
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Payment Method Modal -->
<div class="modal" id="addPaymentModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Payment Method</h3>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" id="addPaymentForm">
                    <input type="hidden" name="add_payment_method" value="1">
                    
                    <div class="form-group">
                        <label for="method_type">Payment Method Type</label>
                        <select name="method_type" id="method_type" class="form-control" required>
                            <option value="credit_card">Credit Card</option>
                            <option value="paypal">PayPal</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="provider">Provider</label>
                        <input type="text" name="provider" id="provider" class="form-control" placeholder="Visa, Mastercard, PayPal, etc." required>
                    </div>
                    
                    <div class="form-group">
                        <label for="account_number">Account Number (Last 4 digits)</label>
                        <input type="text" name="account_number" id="account_number" class="form-control" placeholder="Last 4 digits only" maxlength="4" pattern="[0-9]{4}">
                    </div>
                    
                    <div id="creditCardFields">
                        <div class="form-group">
                            <label for="expiry_date">Expiry Date</label>
                            <input type="text" name="expiry_date" id="expiry_date" class="form-control" placeholder="MM/YY" pattern="[0-9]{2}/[0-9]{2}">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-container">
                            <input type="checkbox" name="is_default" id="is_default" value="1" checked>
                            <span class="checkmark"></span>
                            Set as default payment method
                        </label>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Payment Method</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
:root {
    --primary-color: #042167;
    --secondary-color: #858796;
    --success-color: #1cc88a;
    --danger-color: #e74a3b;
    --warning-color: #f6c23e;
    --info-color: #36b9cc;
    --light-color: #f8f9fc;
    --dark-color: #5a5c69;
    --bronze-color: #cd7f32;
    --silver-color: #c0c0c0;
    --gold-color: #ffd700;
}

.subscription-container {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.card-header {
    padding: 15px 20px;
    border-bottom: 1px solid var(--light-color);
    background-color: white;
}

.card-header h2 {
    margin: 0;
    font-size: 1.25rem;
    color: var(--primary-color);
}

.card-body {
    padding: 20px;
}

.current-plan {
    display: flex;
    align-items: flex-start;
    gap: 20px;
}

.plan-badge {
    padding: 8px 16px;
    border-radius: 20px;
    color: white;
    font-weight: bold;
    text-align: center;
    min-width: 80px;
}

.plan-badge.bronze {
    background-color: var(--bronze-color);
}

.plan-badge.silver {
    background-color: var(--silver-color);
    color: #333;
}

.plan-badge.gold {
    background-color: var(--gold-color);
    color: #333;
}

.plan-details {
    flex: 1;
}

.plan-details h3 {
    margin: 0 0 10px 0;
    color: var(--dark-color);
    font-size: 1.2rem;
}

.plan-info {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.plan-info p {
    margin: 0;
}

.status {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status.active {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.status.canceled {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.status.expired {
    background-color: rgba(133, 135, 150, 0.1);
    color: var(--secondary-color);
}

.plan-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
}

.btn {
    padding: 8px 16px;
    border-radius: 4px;
    border: none;
    font-size: 0.9rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
}

.btn-secondary {
    background-color: var(--secondary-color);
    color: white;
}

.btn-danger {
    background-color: var(--danger-color);
    color: white;
}

.inline-form {
    display: inline;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th, .table td {
    padding: 12px;
    border-bottom: 1px solid var(--light-color);
}

.table th {
    text-align: left;
    color: var(--primary-color);
    font-weight: 600;
}

.payment-status {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
}

.payment-status.completed {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.payment-status.pending {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--warning-color);
}

.payment-status.failed {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.payment-status.refunded {
    background-color: rgba(54, 185, 204, 0.1);
    color: var(--info-color);
}

.payment-method {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    border: 1px solid var(--light-color);
    border-radius: 8px;
}

.payment-icon {
    width: 40px;
    height: 40px;
    background-color: var(--light-color);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: var(--primary-color);
}

.payment-details h4 {
    margin: 0 0 5px 0;
    font-size: 1rem;
    color: var(--dark-color);
}

.payment-details p {
    margin: 0;
    color: var(--secondary-color);
    font-size: 0.9rem;
}

.default-badge {
    background-color: var(--success-color);
    color: white;
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 10px;
    margin-left: 5px;
}

.mt-3 {
    margin-top: 15px;
}

.alert {
    padding: 12px 20px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.alert-success {
    background-color: rgba(28, 200, 138, 0.1);
    border: 1px solid rgba(28, 200, 138, 0.2);
    color: var(--success-color);
}

.alert-danger {
    background-color: rgba(231, 74, 59, 0.1);
    border: 1px solid rgba(231, 74, 59, 0.2);
    color: var(--danger-color);
}

.no-subscription {
    text-align: center;
    padding: 30px 0;
}

.no-subscription p {
    margin-bottom: 20px;
    color: var(--secondary-color);
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1000;
}

.modal-dialog {
    margin: 60px auto;
    max-width: 600px;
    width: calc(100% - 40px);
}

.modal-content {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    overflow: hidden;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid var(--light-color);
}

.modal-title {
    margin: 0;
    color: var(--dark-color);
    font-size: 1.2rem;
}

.close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--secondary-color);
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    padding: 15px 20px;
    border-top: 1px solid var(--light-color);
    gap: 10px;
}

/* Plan Options in Modal */
.plan-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
}

.plan-option {
    position: relative;
}

.plan-option input[type="radio"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.plan-card {
    display: block;
    border: 2px solid var(--light-color);
    border-radius: 8px;
    padding: 15px;
    cursor: pointer;
    text-align: center;
    height: 100%;
    position: relative;
    transition: all 0.2s;
}

.plan-card:hover {
    border-color: var(--primary-color);
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.plan-option input[type="radio"]:checked + .plan-card {
    border-color: var(--primary-color);
    background-color: rgba(4, 33, 103, 0.05);
}

.plan-card.bronze .plan-price {
    color: var(--bronze-color);
}

.plan-card.silver .plan-price {
    color: var(--silver-color);
}

.plan-card.gold .plan-price {
    color: var(--gold-color);
}

.plan-card h4 {
    margin: 0 0 10px 0;
    color: var(--dark-color);
}

.plan-price {
    margin-bottom: 15px;
    font-weight: bold;
}

.price {
    font-size: 1.5rem;
}

.cycle {
    font-size: 0.9rem;
    color: var(--secondary-color);
}

.plan-features {
    list-style: none;
    padding: 0;
    margin: 0;
    text-align: left;
}

.plan-features li {
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.85rem;
}

.plan-features i {
    color: var(--success-color);
}

.current-plan-badge {
    position: absolute;
    top: -10px;
    right: -10px;
    background-color: var(--success-color);
    color: white;
    font-size: 0.7rem;
    padding: 3px 8px;
    border-radius: 10px;
}

.payment-method-select {
    border: 1px solid var(--light-color);
    border-radius: 8px;
    padding: 15px;
}

.selected-payment-method {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 5px;
    font-size: 0.9rem;
}

.selected-payment-method i {
    color: var(--primary-color);
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--secondary-color);
    border-radius: 4px;
    font-size: 0.9rem;
}

.checkbox-container {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

.text-warning {
    color: var(--warning-color);
}

@media (max-width: 768px) {
    .current-plan {
        flex-direction: column;
    }
    
    .plan-info {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Upgrade Plan Modal
    const upgradeBtn = document.getElementById('upgradeBtn');
    const upgradePlanModal = document.getElementById('upgradePlanModal');
    
    if (upgradeBtn) {
        upgradeBtn.addEventListener('click', function() {
            upgradePlanModal.style.display = 'block';
        });
    }
    
    // Add Payment Method Modal
    const addPaymentBtn = document.getElementById('addPaymentBtn');
    const addPaymentModal = document.getElementById('addPaymentModal');
    
    if (addPaymentBtn) {
        addPaymentBtn.addEventListener('click', function() {
            addPaymentModal.style.display = 'block';
        });
    }
    
    // Close Modals
    const closeButtons = document.querySelectorAll('.close, [data-dismiss="modal"]');
    
    closeButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
            }
        });
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    });
    
    // Payment Method Type Change
    const methodTypeSelect = document.getElementById('method_type');
    const creditCardFields = document.getElementById('creditCardFields');
    
    if (methodTypeSelect && creditCardFields) {
        methodTypeSelect.addEventListener('change', function() {
            if (this.value === 'credit_card') {
                creditCardFields.style.display = 'block';
            } else {
                creditCardFields.style.display = 'none';
            }
        });
    }
    
    // Format expiry date input (MM/YY)
    const expiryInput = document.getElementById('expiry_date');
    if (expiryInput) {
        expiryInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
