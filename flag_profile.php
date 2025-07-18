<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['type']) || $_SESSION['type'] !== 'moderator') {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

if (isset($_POST['email']) && isset($_POST['reason'])) {
    $email = $_POST['email'];
    $reason = $_POST['reason'];
    
    $query = "UPDATE ff_users SET flagged = 1, flag_reason = ? WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $reason, $email);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }
    
    $stmt->close();
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
}

$conn->close();
?>