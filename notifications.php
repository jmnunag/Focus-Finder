<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

$email = $_SESSION['email'];
$query = $conn->prepare("SELECT * FROM ff_users WHERE email = ?");
$query->bind_param("s", $email);
$query->execute();
$result = $query->get_result();

if (!$result) {
    die("Query failed: " . $conn->error);
}

$user = $result->fetch_assoc();

// Fetch notifications for the logged-in user
$notifications_query = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC");
$notifications_query->bind_param("i", $user['id']);
$notifications_query->execute();
$notifications_result = $notifications_query->get_result();

$notifications = [];
while ($notification = $notifications_result->fetch_assoc()) {
    $notifications[] = $notification;
}

echo json_encode($notifications);
?>