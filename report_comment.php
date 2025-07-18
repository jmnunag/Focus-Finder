<?php
require_once "config.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $comment_id = $_POST['comment_id'];
    $reason = $_POST['reason'];

    $query = $conn->prepare("INSERT INTO comments (comment_id, reason) VALUES (?, ?)");
    $query->bind_param("is", $comment_id, $reason);
    $query->execute();

    header("Location: board_details.php?location_id=" . $_POST['post_id']);
    exit;
}
?>