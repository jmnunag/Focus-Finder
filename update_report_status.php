<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['email']) || $_SESSION['type'] !== 'moderator') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $type = $_POST['type'];
    $action = $_POST['status'];
    
    try {
        $conn->begin_transaction();

        if ($type == 'report') {
            $get_report = $conn->prepare("SELECT content_type, content_id, reason FROM reports WHERE id = ?");
            $get_report->bind_param("i", $id);
            $get_report->execute();
            $report = $get_report->get_result()->fetch_assoc();

            if ($action == 'flagged') {
                $status = 'reviewed';
            } else if ($action == 'deleted') {
                $status = 'deleted';
            } else {
                $status = 'dismissed';
            }
            $update_report = $conn->prepare("UPDATE reports SET status = ? WHERE id = ?");
            $update_report->bind_param("si", $status, $id);
            $update_report->execute();

            if ($action == 'flagged') {
                switch($report['content_type']) {
                    case 'User':
                        $query = "UPDATE ff_users SET flagged = ?, flag_reason = ? WHERE email = ?";
                        $param_type = "iss"; 
                        break;
                    case 'Review':
                        $query = "UPDATE reviews SET flagged = ?, flag_reason = ? WHERE id = ?";
                        $param_type = "isi"; 
                        break;
                    case 'Post':
                        $query = "UPDATE bulletin_posts SET flagged = ?, flag_reason = ? WHERE id = ?";
                        $param_type = "isi"; 
                        break;
                    case 'Comment':
                        $query = "UPDATE comments SET flagged = ?, flag_reason = ? WHERE id = ?";
                        $param_type = "isi";
                        break;
                }
                
                if (isset($query)) {
                    $content_flagged = 1;
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param($param_type, $content_flagged, $report['reason'], $report['content_id']);
                    $stmt->execute();
                }
            } else if  ($action == 'deleted') {
                switch($report['content_type']) {
                    case 'Review':
                        $delete_votes = $conn->prepare("DELETE FROM review_votes WHERE review_id = ?");
                        $delete_votes->bind_param("i", $report['content_id']);
                        $delete_votes->execute();
                        
                        $delete_review = $conn->prepare("DELETE FROM reviews WHERE id = ?");
                        $delete_review->bind_param("i", $report['content_id']);
                        $delete_review->execute();
                        break;

                    case 'Post':
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
                        
                        $get_comments->bind_param("i", $report['content_id']);
                        $get_comments->execute();
                        $comments_result = $get_comments->get_result();

                        while ($comment = $comments_result->fetch_assoc()) {
                            $delete_comment = $conn->prepare("DELETE FROM comments WHERE id = ?");
                            $delete_comment->bind_param("i", $comment['id']);
                            $delete_comment->execute();
                        }

                        $delete_post = $conn->prepare("DELETE FROM bulletin_posts WHERE id = ?");
                        $delete_post->bind_param("i", $report['content_id']);
                        $delete_post->execute();
                        break;

                    case 'Comment':
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
                        
                        $get_comments->bind_param("i", $report['content_id']);
                        $get_comments->execute();
                        $comments_result = $get_comments->get_result();

                        while ($comment = $comments_result->fetch_assoc()) {
                            $delete_comment = $conn->prepare("DELETE FROM comments WHERE id = ?");
                            $delete_comment->bind_param("i", $comment['id']);
                            $delete_comment->execute();
                        }
                        break;

                    case 'User':
                        $query = "UPDATE ff_users SET flagged = 1 WHERE email = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("s", $report['content_id']);
                        $stmt->execute();
                        break;           
                }
            }
        }

        $conn->commit();
        echo json_encode(['status' => 'success']);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>