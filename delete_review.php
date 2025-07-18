<?php
require_once "config.php";
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $review_id = $_POST['review_id'];

    $conn->begin_transaction();

    try {
        $query = $conn->prepare("DELETE FROM review_votes WHERE review_id = ?");
        if (!$query) {
            throw new Exception("Prepare failed: (" . $conn->errno . ") " . $conn->error);
        }
        $query->bind_param("i", $review_id);
        $query->execute();

        $query = $conn->prepare("DELETE FROM reviews WHERE id = ?");
        if (!$query) {
            throw new Exception("Prepare failed: (" . $conn->errno . ") " . $conn->error);
        }
        $query->bind_param("i", $review_id);
        $query->execute();

        $conn->commit();

        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        $conn->rollback();
        error_log($e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete review.']);
    }
    exit;
}
?>