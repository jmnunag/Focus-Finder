<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['type']) || $_SESSION['type'] !== 'moderator') {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

if (isset($_POST['email'])) {
    $email = $_POST['email'];
    
    $query = "UPDATE ff_users SET flagged = 0, flag_reason = NULL WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }
    
    $stmt->close();
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing email parameter']);
}

$conn->close();
?>