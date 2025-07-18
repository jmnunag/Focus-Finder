<?php
require_once "config.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['id']) || !isset($_POST['reason'])) {
        echo json_encode(["status" => "error", "message" => "Missing required parameters."]);
        exit;
    }

    $review_id = $_POST['id'];
    $reason = $_POST['reason'];

    $checkQuery = $conn->prepare("SELECT * FROM reviews WHERE id = ?");
    $checkQuery->bind_param("i", $review_id);
    $checkQuery->execute();
    $result = $checkQuery->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Review ID not found."]);
        exit;
    }

    $query = $conn->prepare("UPDATE reviews SET flagged = 1, flag_reason = ? WHERE id = ?");
    if ($query === false) {
        echo json_encode(["status" => "error", "message" => "Failed to prepare the SQL statement: " . $conn->error]);
        exit;
    }

    $bind = $query->bind_param("si", $reason, $review_id);
    if ($bind === false) {
        echo json_encode(["status" => "error", "message" => "Failed to bind parameters: " . $query->error]);
        exit;
    }

    $execute = $query->execute();
    if ($execute === false) {
        echo json_encode(["status" => "error", "message" => "Failed to execute the query: " . $query->error]);
        exit;
    }

    if ($query->affected_rows > 0) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => "No rows were updated."]);
    }
    exit;
}
?>