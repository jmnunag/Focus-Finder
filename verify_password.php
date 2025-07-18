<?php
session_start();
header('Content-Type: application/json');

$servername = "localhost"; 
$username = "root"; 
$password = ""; 
$dbname = "focus_finder"; 

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Connection failed: ' . $conn->connect_error]));
}

$email = $_SESSION['email'];

if (isset($_POST['current_password'])) {
    $current_password = $_POST['current_password'];

    $stmt = $conn->prepare("SELECT password FROM ff_users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $hashed_current_password = $user['password'];

        if (password_verify($current_password, $hashed_current_password)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Current password is incorrect!']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User not found!']);
    }
} elseif (isset($_POST['new_password']) && isset($_POST['confirm_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password === $confirm_password) {
        $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);

        $update_stmt = $conn->prepare("UPDATE ff_users SET password = ? WHERE email = ?");
        $update_stmt->bind_param("ss", $hashed_new_password, $email);

        if ($update_stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error updating password!']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'New passwords do not match!']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request!']);
}

$conn->close();
?>