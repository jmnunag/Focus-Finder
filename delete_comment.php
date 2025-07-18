<?php
require_once "config.php";
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['comment_id']) || empty($_POST['comment_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Comment ID is required']);
        exit;
    }

    $comment_id = intval($_POST['comment_id']);

    $conn->begin_transaction();

    try {
        $get_comments = $conn->prepare("
            WITH RECURSIVE CommentHierarchy AS (
                SELECT id, parent_id, 0 as level
                FROM comments
                WHERE id = ?
                
                UNION ALL
                
                SELECT c.id, c.parent_id, ch.level + 1
                FROM comments c
                INNER JOIN CommentHierarchy ch ON c.parent_id = ch.id
            )
            SELECT id
            FROM CommentHierarchy
            ORDER BY level DESC");
        
        $get_comments->bind_param("i", $comment_id);
        $get_comments->execute();
        $comments_result = $get_comments->get_result();

        while ($comment = $comments_result->fetch_assoc()) {
            $delete_comment = $conn->prepare("DELETE FROM comments WHERE id = ?");
            $delete_comment->bind_param("i", $comment['id']);
            $delete_comment->execute();
            $delete_comment->close();
        }

        $conn->commit();
        echo json_encode(['status' => 'success']);

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Delete comment error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }

    $conn->close();
    exit;
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}
?>