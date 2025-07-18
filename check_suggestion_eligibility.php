<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['email'];
$response = array('eligible' => true, 'message' => '', 'redirect' => '');

$check_pending = "SELECT status FROM shop_signup WHERE email=? AND status='pending'";
$stmt = $conn->prepare($check_pending);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $_SESSION['error'] = "You already have a pending owner application!";
    header("Location: userHome.php");
    exit();
}

$check_owner = "SELECT type FROM ff_users WHERE email=? AND type='owner'";
$stmt = $conn->prepare($check_owner);
$stmt->bind_param("s", $email);
$stmt->execute();
$owner_result = $stmt->get_result();

if ($owner_result->num_rows > 0) {
    $_SESSION['error'] = "You are already registered as an owner!";
    header("Location: userHome.php");
    exit();
}

$check_rejected = "SELECT rejection_date FROM shop_signup 
                  WHERE email=? AND status='rejected' 
                  ORDER BY rejection_date DESC LIMIT 1";
$stmt = $conn->prepare($check_rejected);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $rejection = $result->fetch_assoc();
    $rejection_date = new DateTime($rejection['rejection_date']);
    $current_date = new DateTime();
    $days_since_rejection = $current_date->diff($rejection_date)->days;

    if ($days_since_rejection < 7) {
        $days_remaining = 7 - $days_since_rejection;
        $_SESSION['error'] = "Your previous application was rejected. Please wait {$days_remaining} more days before reapplying.";
        header("Location: userHome.php");
        exit();
    }
}

$get_contact = "SELECT contact FROM ff_users WHERE email=?";
$stmt = $conn->prepare($get_contact);
$stmt->bind_param("s", $email);
$stmt->execute();
$contact_result = $stmt->get_result();
$contact_info = $contact_result->fetch_assoc();

$_SESSION['contact'] = $contact_info['contact'];
header("Location: suggestion.php");
exit();
?>