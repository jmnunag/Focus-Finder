<?php
require_once "config.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $comment_id = $_POST['comment_id'];
    $comment_text = $_POST['comment_text'];

    $query = $conn->prepare("UPDATE comments SET comment = ?, edited = 1 WHERE id = ?");
    $query->bind_param("si", $comment_text, $comment_id);
    $result = $query->execute();

    if ($result) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update comment: ' . $conn->error]);
    }
    exit;
}
?>