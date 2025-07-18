<?php
require_once "config.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['id'])) {
        echo json_encode(["status" => "error", "message" => "Missing required parameters."]);
        exit;
    }

    $post_id = $_POST['id'];

    $checkQuery = $conn->prepare("SELECT * FROM bulletin_posts WHERE id = ?");
    $checkQuery->bind_param("i", $post_id);
    $checkQuery->execute();
    $result = $checkQuery->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Post ID not found."]);
        exit;
    }

    $query = $conn->prepare("UPDATE bulletin_posts SET flagged = 0, flag_reason = NULL, flag_date = NULL WHERE id = ?");
    $query->bind_param("i", $post_id);
    
    if ($query->execute()) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to update post."]);
    }
    exit;
}
?>