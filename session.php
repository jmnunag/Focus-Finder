<?php
require_once "config.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Validate email and password
    $query = $conn->prepare("SELECT id, email, fullname, profile_picture, password, type, verified FROM ff_users WHERE email = ?");
    $query->bind_param("s", $email);
    $query->execute();
    $result = $query->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // Get flagged status
            $flag_query = $conn->prepare("SELECT flagged FROM ff_users WHERE email = ?");
            $flag_query->bind_param("s", $email);
            $flag_query->execute();
            $flag_result = $flag_query->get_result();
            $flag_data = $flag_result->fetch_assoc();
            
            // Store user information in session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['profile_picture'] = $user['profile_picture'] ?: 'images/pfp.png';
            $_SESSION['type'] = $user['type'];
            $_SESSION['flagged'] = $flag_data['flagged']; // Add flagged status to session
            $_SESSION['subscription_type'] = $user['subscription_type'];

            // Check if the user is verified
            if ($user['verified'] == 1) {
                // Redirect based on user type
                if ($user['type'] == 'moderator') {
                    header("Location: modHome.php");
                } else {
                    header("Location: userHome.php");
                }
                exit;
            } else {
                // Handle unverified user
                header("Location: login.php?error=unverified&email=" . urlencode($email));
                exit;
            }
        } else {
            // If authentication fails, redirect back to login page with an error message
            header("Location: login.php?error=invalid_credentials&email=" . urlencode($email));
            exit;
        }
    } else {
        // If authentication fails, redirect back to login page with an error message
        header("Location: login.php?error=invalid_credentials&email=" . urlencode($email));
        exit;
    }
}
?>