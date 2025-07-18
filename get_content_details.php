<?php
require_once "config.php";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$type = $_GET['type'];
$id = $_GET['id'];

$data = [];

if ($type == 'report') {
    $query = "SELECT r.*, u.fullname as reporter_name 
              FROM reports r 
              LEFT JOIN ff_users u ON r.reporter_email = u.email 
              WHERE r.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    switch($data['content_type']) {
        case 'Review':
            $content_query = "SELECT r.*, 
                                    u.fullname, 
                                    u.profile_picture, 
                                    u.email as reviewer_email,
                                    l.name as location_name,
                                    l.id as location_id
                             FROM reviews r 
                             LEFT JOIN ff_users u ON r.email = u.email 
                             LEFT JOIN study_locations l ON r.location_id = l.id 
                             WHERE r.id = ?";
            $stmt = $conn->prepare($content_query);
            $stmt->bind_param("i", $data['content_id']);
            $stmt->execute();
            $content_result = $stmt->get_result();
            $content_data = $content_result->fetch_assoc();
            
            if ($content_data) {
                $data['original_content'] = $content_data;
                $data['original_link'] = "modLocation.php?location_id=" . $content_data['location_id'] . "#review-" . $data['content_id'];
            }
            break;

        case 'Post':
            $content_query = "SELECT p.*, u.fullname, u.profile_picture 
                            FROM bulletin_posts p 
                            LEFT JOIN ff_users u ON p.email = u.email 
                            WHERE p.id = ?";
            $stmt = $conn->prepare($content_query);
            $stmt->bind_param("i", $data['content_id']);
            $stmt->execute();
            $content_result = $stmt->get_result();
            $content_data = $content_result->fetch_assoc();
            
            if ($content_data) {
                $data['original_content'] = $content_data;
                $data['original_link'] = "modPost.php?id=" . $data['content_id'];
            }
            break;

        case 'Comment':
            $content_query = "SELECT c.*, u.fullname, u.profile_picture, 
                                p.id as post_id, p.subject as post_subject
                                    FROM comments c 
                                    LEFT JOIN ff_users u ON c.user_id = u.id 
                                    LEFT JOIN bulletin_posts p ON c.post_id = p.id 
                                    WHERE c.id = ?";
            $stmt = $conn->prepare($content_query);
            $stmt->bind_param("i", $data['content_id']);
            $stmt->execute();
            $content_result = $stmt->get_result();
            $content_data = $content_result->fetch_assoc();
            
            if ($content_data) {
                $data['original_content'] = $content_data;
                $data['original_link'] = "modPost.php?id=" . $content_data['post_id'] . "#comment-" . $data['content_id'];
            }
            break;

        case 'User':
            $content_query = "SELECT u.* FROM ff_users u WHERE u.email = ?";
            $stmt = $conn->prepare($content_query);
            $stmt->bind_param("s", $data['content_id']);
            $stmt->execute();
            $content_result = $stmt->get_result();
            $content_data = $content_result->fetch_assoc();
            
            if ($content_data) {
                $data['original_content'] = $content_data;
                $data['original_link'] = "modotherProfile.php?email=" . urlencode($data['content_id']);
            }
            break;
    }

} else if ($type == 'application') {
    $query = "SELECT * FROM shop_signup WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    if ($data) {
        $data['images'] = json_decode($data['images']);
    }
}

echo json_encode($data);
?>