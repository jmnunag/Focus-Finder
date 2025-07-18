<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['email'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = $_POST['post_id'];
    $subject = $_POST['subject'];
    $location = $_POST['location'];
    $description = $_POST['description'];

    $stmt = $conn->prepare("UPDATE bulletin_posts SET subject = ?, location = ?, description = ?, edited = 1 WHERE id = ? AND email = ?");
    $stmt->bind_param("sssss", $subject, $location, $description, $post_id, $_SESSION['email']);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }

    $stmt->close();
    $conn->close();
}