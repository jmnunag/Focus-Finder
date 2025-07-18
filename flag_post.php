<?php
require_once "config.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['id']) || !isset($_POST['reason'])) {
        echo json_encode(["status" => "error", "message" => "Missing required parameters."]);
        exit;
    }

    $post_id = $_POST['id'];
    $reason = $_POST['reason'];

    error_log("Flagging post ID: " . $post_id . " with reason: " . $reason);

    $checkQuery = $conn->prepare("SELECT * FROM bulletin_posts WHERE id = ?");
    $checkQuery->bind_param("i", $post_id);
    $checkQuery->execute();
    $result = $checkQuery->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Post ID not found."]);
        exit;
    }

    $query = $conn->prepare("UPDATE bulletin_posts SET flagged = 1, flag_reason = ?, flag_date = NOW() WHERE id = ?");
    if (!$query) {
        error_log("Query preparation failed: " . $conn->error);
        echo json_encode(["status" => "error", "message" => "Query preparation failed"]);
        exit;
    }

    $query->bind_param("si", $reason, $post_id);
    
    if ($query->execute()) {
        error_log("Successfully flagged post " . $post_id);
        echo json_encode(["status" => "success"]);
    } else {
        error_log("Failed to flag post: " . $query->error);
        echo json_encode(["status" => "error", "message" => "Failed to update post."]);
    }
    exit;
}
?>