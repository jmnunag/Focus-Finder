<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Please log in to report content']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $review_id = $_POST['content_id'];
    $reason = $_POST['reason'];
    $description = $_POST['description'];
    $location_id = $_POST['location_id'];

    try {
        $query = $conn->prepare("INSERT INTO reports (review_id, reason, description, location_id) VALUES (?, ?, ?, ?)");
        $query->bind_param("issi", $review_id, $reason, $description, $location_id);
        
        if ($query->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
exit;
?>