<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['email']) || !isset($_GET['plan'])) {
    exit(json_encode([]));
}

$plan_name = $_GET['plan'];

$query = "
    SELECT 
        u.fullname,
        u.email,
        s.end_date
    FROM subscriptions s
    JOIN ff_users u ON s.user_email = u.email
    JOIN membership_plans mp ON s.plan_id = mp.id
    INNER JOIN (
        SELECT user_email, MAX(created_at) as latest_subscription
        FROM subscriptions 
        GROUP BY user_email
    ) latest ON s.user_email = latest.user_email 
        AND s.created_at = latest.latest_subscription
    WHERE mp.name = ?
    AND s.payment_status = 'completed'
    AND s.end_date >= CURRENT_DATE()
    GROUP BY u.email
    ORDER BY s.end_date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $plan_name);
$stmt->execute();
$result = $stmt->get_result();

$subscribers = [];
while ($row = $result->fetch_assoc()) {
    $subscribers[] = [
        'fullname' => $row['fullname'],
        'email' => $row['email'],
        'end_date' => $row['end_date']
    ];
}

header('Content-Type: application/json');
echo json_encode($subscribers);
?>