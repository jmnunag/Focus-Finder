<?php
require_once "config.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['id']) || !isset($_POST['reason'])) {
        echo json_encode(["status" => "error", "message" => "Missing required parameters."]);
        exit;
    }

    $comment_id = $_POST['id'];
    $reason = $_POST['reason'];

    $checkQuery = $conn->prepare("SELECT * FROM comments WHERE id = ?");
    $checkQuery->bind_param("i", $comment_id);
    $checkQuery->execute();
    $result = $checkQuery->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Comment ID not found."]);
        exit;
    }

    $query = $conn->prepare("UPDATE comments SET flagged = 1, flag_reason = ? WHERE id = ?");
    $query->bind_param("si", $reason, $comment_id);
    
    if ($query->execute()) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to update comment."]);
    }
    exit;
}
?>