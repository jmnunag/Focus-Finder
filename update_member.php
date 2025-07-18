<?php
require_once "config.php";
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['type']) || $_SESSION['type'] !== 'moderator') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$signup_id = $_POST['id'];
$new_status = $_POST['status'];

try {
    $conn->begin_transaction();

    $signup_query = $conn->prepare("
        SELECT ms.*, mp.name as plan_name, mp.price 
        FROM membership_signup ms
        JOIN membership_plans mp ON ms.plan_id = mp.id 
        WHERE ms.id = ? AND ms.status = 'pending'
    ");
    $signup_query->bind_param("i", $signup_id);
    $signup_query->execute();
    $signup = $signup_query->get_result()->fetch_assoc();

    if (!$signup) {
        throw new Exception("Invalid signup request or already processed");
    }

    if ($new_status === 'approved') {
        $check_query = $conn->prepare("
            SELECT id, end_date, amount_paid 
            FROM subscriptions 
            WHERE user_email = ? 
            AND payment_status = 'completed'
            ORDER BY end_date DESC 
            LIMIT 1
        ");
        $check_query->bind_param("s", $signup['user_email']);
        $check_query->execute();
        $existing = $check_query->get_result()->fetch_assoc();
    
        if ($existing) {
            $new_end_date = date('Y-m-d', strtotime($existing['end_date'] . ' +1 month'));
            $new_amount = $existing['amount_paid'] + $signup['amount_to_pay'];
    
            $update_query = $conn->prepare("
                UPDATE subscriptions 
                SET end_date = ?, 
                    amount_paid = ?
                WHERE id = ?
            ");
            $update_query->bind_param("sdi", $new_end_date, $new_amount, $existing['id']);
            $update_query->execute();
            
            $subscription_id = $existing['id'];
        } else {
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d', strtotime('+1 month'));
    
            $sub_query = $conn->prepare("
                INSERT INTO subscriptions 
                (user_email, plan_id, amount_paid, payment_status, start_date, end_date) 
                VALUES (?, ?, ?, 'completed', ?, ?)
            ");
            $sub_query->bind_param("sidss", 
                $signup['user_email'], 
                $signup['plan_id'], 
                $signup['amount_to_pay'], 
                $start_date, 
                $end_date
            );
            $sub_query->execute();
            $subscription_id = $conn->insert_id;
        }

        $type_query = $conn->prepare("UPDATE ff_users SET subscription_type = ? WHERE email = ?");
        $subscription_type = (strpos($signup['plan_name'], 'Basic') !== false) ? 'free' : 'premium';
        $type_query->bind_param("ss", $subscription_type, $signup['user_email']);
        $type_query->execute();

        $rev_query = $conn->prepare("
            INSERT INTO membership_revenue 
            (subscription_id, amount_paid, plan_price_at_time) 
            VALUES (?, ?, ?)
        ");
        $rev_query->bind_param("idd", $subscription_id, $signup['amount_to_pay'], $signup['price']);
        $rev_query->execute();
    }

    $status_query = $conn->prepare("
        UPDATE membership_signup 
        SET status = ?,
            admin_email = ?,
            processed_date = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    $status_query->bind_param("ssi", $new_status, $_SESSION['email'], $signup_id);
    $status_query->execute();

    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => "Membership request " . ($new_status === 'approved' ? 'approved' : 'rejected') . " successfully"
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => "Error processing request: " . $e->getMessage()
    ]);
}

exit;
?>