<?php
require_once "config.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $review_id = $_POST['review_id'];
    $review_text = $_POST['review_text'];
    $rating = $_POST['rating'];

    $query = $conn->prepare("UPDATE reviews SET review_text = ?, rating = ?, edited = 1 WHERE id = ?");
    $query->bind_param("sii", $review_text, $rating, $review_id);
    $query->execute();

    if ($query->affected_rows > 0) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update review.']);
    }
    exit;
}
?>