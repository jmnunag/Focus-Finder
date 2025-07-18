<?php
require_once "config.php";
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['email']) || $_SESSION['type'] !== 'moderator') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_POST['id']) || !isset($_POST['status'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

$appeal_id = (int)$_POST['id'];
$new_status = $_POST['status'];
$resolver_id = $_SESSION['user_id'];

try {
    $conn->begin_transaction();

    $appeal_query = "SELECT user_id, content_type, content_id FROM appeals WHERE id = ?";
    $stmt = $conn->prepare($appeal_query);
    $stmt->bind_param("i", $appeal_id);
    $stmt->execute();
    $appeal = $stmt->get_result()->fetch_assoc();

    $update_query = "UPDATE appeals 
                    SET status = ?, 
                        resolver_id = ?, 
                        resolved_at = NOW() 
                    WHERE id = ?";
    
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("sii", $new_status, $resolver_id, $appeal_id);
    $stmt->execute();

    if ($new_status === 'approved') {
        switch($appeal['content_type']) {
            case 'User':
                $unflag_user_query = "UPDATE ff_users SET flagged = 0 WHERE id = ?";
                $stmt = $conn->prepare($unflag_user_query);
                $stmt->bind_param("i", $appeal['user_id']);
                $stmt->execute();
                break;
                
            case 'Review':
                $content_table = 'reviews';
                break;
            case 'Post':
                $content_table = 'bulletin_posts';
                break;
            case 'Comment':
                $content_table = 'comments';
                break;
        }

        if ($appeal['content_type'] !== 'User' && isset($content_table)) {
            $unflag_content_query = "UPDATE $content_table SET flagged = 0, flag_reason = NULL WHERE id = ?";
            $stmt = $conn->prepare($unflag_content_query);
            $stmt->bind_param("i", $appeal['content_id']);
            $stmt->execute();
        }
    }

    $conn->commit();
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$stmt->close();
$conn->close();