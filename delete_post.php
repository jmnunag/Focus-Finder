<?php
require_once "config.php";
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['email'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
    exit;
}

if (!isset($_SESSION['csrf_token']) || !isset($_POST['csrf_token']) || 
    $_SESSION['csrf_token'] !== $_POST['csrf_token']) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid security token'
    ]);
    exit;
}

if (!isset($_POST['post_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Post ID is required']);
    exit;
}

$post_id = $_POST['post_id'];

try {
    $check_query = $conn->prepare("SELECT user_id, email FROM bulletin_posts WHERE id = ?");
    $check_query->bind_param("i", $post_id);
    $check_query->execute();
    $result = $check_query->get_result();
    $post = $result->fetch_assoc();
    
    $is_moderator = isset($_SESSION['type']) && $_SESSION['type'] === 'moderator';
    $is_owner = $post && ($post['user_id'] == $_SESSION['user_id'] || $post['email'] == $_SESSION['email']);
    
    if (!$post || (!$is_owner && !$is_moderator)) {
        throw new Exception('Not authorized to delete this post');
    }

    $conn->begin_transaction();

    $get_comments = $conn->prepare("
        WITH RECURSIVE CommentHierarchy AS (
            SELECT id, parent_id, 0 as level
            FROM comments
            WHERE post_id = ? AND parent_id IS NULL
            
            UNION ALL
            
            SELECT c.id, c.parent_id, ch.level + 1
            FROM comments c
            INNER JOIN CommentHierarchy ch ON c.parent_id = ch.id
        )
        SELECT id
        FROM CommentHierarchy
        ORDER BY level DESC");
    
    $get_comments->bind_param("i", $post_id);
    $get_comments->execute();
    $comments_result = $get_comments->get_result();

    while ($comment = $comments_result->fetch_assoc()) {
        $delete_comment = $conn->prepare("DELETE FROM comments WHERE id = ?");
        $delete_comment->bind_param("i", $comment['id']);
        $delete_comment->execute();
        $delete_comment->close();
    }

    $delete_post = $conn->prepare("DELETE FROM bulletin_posts WHERE id = ?");
    $delete_post->bind_param("i", $post_id);
    $delete_post->execute();
    $delete_post->close();

    $conn->commit();
    echo json_encode(['status' => 'success']);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Delete Post Error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}