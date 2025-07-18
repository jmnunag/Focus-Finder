<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['email']) || !isset($_SESSION['user_id'])) {
    die("Error: User not logged in.");
}

$review_id = isset($_POST['review_id']) ? intval($_POST['review_id']) : null;
$vote_type = isset($_POST['vote_type']) ? $_POST['vote_type'] : null;
$user_id = $_SESSION['user_id'];

if (!$review_id || !$vote_type || !in_array($vote_type, ['upvote', 'downvote'])) {
    die("Error: Invalid input.");
}

error_log("Review ID: $review_id, Vote Type: $vote_type, User ID: $user_id");

$query = $conn->prepare("SELECT vote_type FROM review_votes WHERE review_id = ? AND user_id = ?");
$query->bind_param("ii", $review_id, $user_id);
$query->execute();
$result = $query->get_result();

if ($result->num_rows > 0) {
    $existing_vote = $result->fetch_assoc();
    if ($existing_vote['vote_type'] == $vote_type) {
        $query = $conn->prepare("DELETE FROM review_votes WHERE review_id = ? AND user_id = ?");
        $query->bind_param("ii", $review_id, $user_id);
    } else {
        $query = $conn->prepare("UPDATE review_votes SET vote_type = ? WHERE review_id = ? AND user_id = ?");
        $query->bind_param("sii", $vote_type, $review_id, $user_id);
    }
} else {
    $query = $conn->prepare("INSERT INTO review_votes (review_id, user_id, vote_type) VALUES (?, ?, ?)");
    $query->bind_param("iis", $review_id, $user_id, $vote_type);
}

if ($query->execute()) {
    echo "Success";
} else {
    echo "Error: " . $query->error;
    error_log("Error: " . $query->error);
}

$conn->close();
?>