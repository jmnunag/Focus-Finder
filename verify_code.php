<?php
require_once "config.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $verification_code = $_POST['verification_code'];

    $query = $conn->prepare("SELECT id, fullname, profile_picture, type FROM ff_users WHERE email = ? AND verification_code = ?");
    $query->bind_param("ss", $email, $verification_code);
    $query->execute();
    $result = $query->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $update_query = $conn->prepare("UPDATE ff_users SET verified = 1 WHERE email = ?");
        $update_query->bind_param("s", $email);
        $update_query->execute();

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $email;
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['profile_picture'] = $user['profile_picture'] ?: 'images/pfp.png';

        if ($user['type'] == 'admin') {
            header("Location: modHome.php");
        } else {
            header("Location: userHome.php");
        }
        exit;
    } else {
        header("Location: login.php?error=invalid_verification_code&email=" . urlencode($email));
        exit;
    }
}
?>