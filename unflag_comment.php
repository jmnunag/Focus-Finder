<?php
require_once "config.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['id'])) {
        echo json_encode(["status" => "error", "message" => "Missing required parameters."]);
        exit;
    }

    $comment_id = $_POST['id'];

    $checkQuery = $conn->prepare("SELECT * FROM comments WHERE id = ?");
    $checkQuery->bind_param("i", $comment_id);
    $checkQuery->execute();
    $result = $checkQuery->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Review ID not found."]);
        exit;
    }

    $query = $conn->prepare("UPDATE comments SET flagged = 0, flag_reason = NULL WHERE id = ?");
    if ($query === false) {
        echo json_encode(["status" => "error", "message" => "Failed to prepare the SQL statement: " . $conn->error]);
        exit;
    }

    $bind = $query->bind_param("i", $comment_id);
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