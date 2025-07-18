<?php
require_once "config.php";
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['email'])) {
    echo '<div id="flaggedAlert" class="alert alert-warning popup-notification">
            Please login first
          </div>';
    exit;
}

$required_fields = ['content_type', 'content_id', 'reason', 'description'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

$reporter_email = $_SESSION['email'];
$content_type = $_POST['content_type'];
$content_id = $_POST['content_id'];
$reason = $_POST['reason'];
$description = $_POST['description'];

$query = "INSERT INTO reports (reporter_email, content_type, content_id, reason, description, report_date) 
          VALUES (?, ?, ?, ?, ?, NOW())";

$stmt = $conn->prepare($query);
$stmt->bind_param("sssss", $reporter_email, $content_type, $content_id, $reason, $description);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

$stmt->close();
$conn->close();