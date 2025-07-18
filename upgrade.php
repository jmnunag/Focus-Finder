<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['email']) || !isset($_GET['plan'])) {
    header("Location: userHome.php");
    exit;
}

$email = $_SESSION['email'];
$plan_id = $_GET['plan'];

// Get plan details
$plan_query = $conn->prepare("SELECT * FROM membership_plans WHERE id = ?");
$plan_query->bind_param("i", $plan_id);
$plan_query->execute();
$plan = $plan_query->get_result()->fetch_assoc();

if (!$plan) {
    $_SESSION['error'] = "Invalid plan selected.";
    header("Location: userMember.php");
    exit;
}

try {
    $conn->begin_transaction();

    // Check if premium plan
    $is_premium = !str_contains($plan['name'], 'Basic');

    if ($is_premium) {
        // Check for existing subscription
        $check_query = $conn->prepare("
            SELECT id, end_date, amount_paid 
            FROM subscriptions 
            WHERE user_email = ? AND plan_id = ? 
            AND payment_status = 'completed'
            ORDER BY created_at DESC LIMIT 1
        ");
        $check_query->bind_param("si", $email, $plan_id);
        $check_query->execute();
        $existing = $check_query->get_result()->fetch_assoc();

        if ($existing) {
            // Update existing subscription
            $new_end_date = date('Y-m-d', strtotime($existing['end_date'] . ' +1 month'));
            $new_amount = $existing['amount_paid'] + $plan['price'];

            $update_query = $conn->prepare("
                UPDATE subscriptions 
                SET amount_paid = ?, end_date = ?
                WHERE id = ?
            ");
            $update_query->bind_param("dsi", $new_amount, $new_end_date, $existing['id']);
            $update_query->execute();

            // Record additional revenue
            $rev_query = $conn->prepare("
                INSERT INTO membership_revenue 
                (subscription_id, amount_paid, plan_price_at_time) 
                VALUES (?, ?, ?)
            ");
            $rev_query->bind_param("idd", $existing['id'], $plan['price'], $plan['price']);
            $rev_query->execute();
        } else {
            // Create new subscription
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d', strtotime('+1 month'));

            $sub_query = $conn->prepare("
                INSERT INTO subscriptions 
                (user_email, plan_id, amount_paid, payment_status, start_date, end_date) 
                VALUES (?, ?, ?, 'completed', ?, ?)
            ");
            $sub_query->bind_param("sidss", $email, $plan_id, $plan['price'], $start_date, $end_date);
            $sub_query->execute();
            $subscription_id = $conn->insert_id;

            // Record initial revenue
            $rev_query = $conn->prepare("
                INSERT INTO membership_revenue 
                (subscription_id, amount_paid, plan_price_at_time) 
                VALUES (?, ?, ?)
            ");
            $rev_query->bind_param("idd", $subscription_id, $plan['price'], $plan['price']);
            $rev_query->execute();
        }
    }

    // Update user subscription type
    $type_query = $conn->prepare("UPDATE ff_users SET subscription_type = ? WHERE email = ?");
    $subscription_type = ($plan['name'] === 'Basic User' || $plan['name'] === 'Basic Owner') ? 'free' : 'premium';
    $type_query->bind_param("ss", $subscription_type, $email);
    $type_query->execute();

    $conn->commit();
    
    $_SESSION['message'] = $is_premium ? "Successfully upgraded to " . $plan['name'] : "Successfully switched to " . $plan['name'];
    header("Location: " . ($_SESSION['type'] === 'owner' ? 'ownerMember.php' : 'userMember.php'));
    exit;

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "Error processing upgrade: " . $e->getMessage();
    header("Location: " . ($_SESSION['type'] === 'owner' ? 'ownerMember.php' : 'userMember.php'));
    exit;
}
?>