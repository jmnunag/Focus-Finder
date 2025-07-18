<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['email']) || !isset($_POST['plan'])) {
    exit(json_encode(['success' => false, 'message' => 'Invalid request']));
}

$email = $_SESSION['email'];
$plan_id = $_POST['plan'];

try {
    $check_pending = $conn->prepare("
        SELECT id FROM membership_signup 
        WHERE user_email = ? AND status = 'pending'
    ");
    $check_pending->bind_param("s", $email);
    $check_pending->execute();
    $pending = $check_pending->get_result()->fetch_assoc();

    if ($pending) {
        exit(json_encode([
            'success' => false, 
            'message' => 'You already have a pending membership request. Please wait for admin approval.'
        ]));
    }

    $plan_query = $conn->prepare("SELECT * FROM membership_plans WHERE id = ?");
    $plan_query->bind_param("i", $plan_id);
    $plan_query->execute();
    $plan = $plan_query->get_result()->fetch_assoc();

    $signup_query = $conn->prepare("
        INSERT INTO membership_signup 
        (user_email, plan_id, amount_to_pay) 
        VALUES (?, ?, ?)
    ");
    $signup_query->bind_param("sid", $email, $plan_id, $plan['price']);
    $signup_query->execute();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>