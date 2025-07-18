<?php
require_once "config.php";
session_start();

// Clean any previous output
ob_clean();

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to submit an appeal']);
    exit;
}

// Validate required parameters
if (!isset($_POST['content_type']) || !isset($_POST['content_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$content_type = $_POST['content_type'];
$content_id = (int)$_POST['content_id'];

// Validate content type
$valid_types = ['User', 'Review', 'Post', 'Comment'];
if (!in_array($content_type, $valid_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid content type']);
    exit;
}

// Check if content is actually flagged
$flagged = false;
switch ($content_type) {
    case 'User':
        $check_query = "SELECT flagged FROM ff_users WHERE id = ?";
        break;
    case 'Review':
        $check_query = "SELECT flagged FROM reviews WHERE id = ?";
        break;
    case 'Post':
        $check_query = "SELECT flagged FROM bulletin_posts WHERE id = ?";
        break;
    case 'Comment':
        $check_query = "SELECT flagged FROM comments WHERE id = ?";
        break;
}

$stmt = $conn->prepare($check_query);
$stmt->bind_param("i", $content_id);
$stmt->execute();
$result = $stmt->get_result();
$content = $result->fetch_assoc();

if (!$content || !$content['flagged']) {
    echo json_encode(['success' => false, 'message' => 'Content is not flagged']);
    exit;
}

// Check for existing pending appeals
$pending_check = $conn->prepare("SELECT id FROM appeals WHERE user_id = ? AND content_type = ? AND content_id = ? AND status = 'pending'");
$pending_check->bind_param("isi", $_SESSION['user_id'], $content_type, $content_id);
$pending_check->execute();
$pending_result = $pending_check->get_result();

if ($pending_result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'You already have a pending appeal for this content']);
    exit;
}

// Validate appeal reason
if (!isset($_POST['appeal_reason']) || empty(trim($_POST['appeal_reason']))) {
    echo json_encode(['success' => false, 'message' => 'Please provide a reason for your appeal']);
    exit;
}

$reason = trim($_POST['appeal_reason']);

// Insert appeal
try {
    $insert_query = $conn->prepare("INSERT INTO appeals (user_id, email, content_type, content_id, reason) VALUES (?, ?, ?, ?, ?)");
    $insert_query->bind_param("issis", $_SESSION['user_id'], $_SESSION['email'], $content_type, $content_id, $reason);
    
    if ($insert_query->execute()) {
        echo json_encode(['success' => true, 'message' => 'Appeal submitted successfully']);
    } else {
        throw new Exception('Failed to submit appeal');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error submitting appeal: ' . $e->getMessage()]);
}

$conn->close();