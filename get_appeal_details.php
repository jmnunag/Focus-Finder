<?php
require_once "config.php";
session_start();

// Prevent any output before headers
ob_start();

// Set JSON header
header('Content-Type: application/json');

// Check if moderator
if (!isset($_SESSION['email']) || $_SESSION['type'] !== 'moderator') {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_GET['id'])) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Appeal ID not provided']);
    exit;
}

$appeal_id = (int)$_GET['id'];

try {
    // First get the appeal details
    $query = "SELECT a.*, 
              u.fullname, 
              u.profile_picture, 
              u.email,
              u.id as user_id
              FROM appeals a 
              JOIN ff_users u ON a.user_id = u.id 
              WHERE a.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $appeal_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    // Then get the original content based on content_type
    switch($data['content_type']) {
        case 'Review':
            $content_query = "SELECT r.*, 
                                    u.fullname as content_fullname, 
                                    u.profile_picture as content_profile_picture, 
                                    u.email as reviewer_email,
                                    l.name as location_name,
                                    l.id as location_id
                             FROM reviews r 
                             LEFT JOIN ff_users u ON r.email = u.email 
                             LEFT JOIN study_locations l ON r.location_id = l.id 
                             WHERE r.id = ?";
            break;

        case 'Post':
            $content_query = "SELECT p.*, 
                                    u.fullname as content_fullname, 
                                    u.profile_picture as content_profile_picture 
                            FROM bulletin_posts p 
                            LEFT JOIN ff_users u ON p.email = u.email 
                            WHERE p.id = ?";
            break;

        case 'Comment':
            $content_query = "SELECT c.*, 
                                   u.fullname as content_fullname, 
                                   u.profile_picture as content_profile_picture, 
                                   p.id as post_id, 
                                   p.subject as post_subject
                            FROM comments c 
                            LEFT JOIN ff_users u ON c.user_id = u.id 
                            LEFT JOIN bulletin_posts p ON c.post_id = p.id 
                            WHERE c.id = ?";
            break;

            case 'User':
                $content_query = "SELECT 
                    u.*,
                    u.fullname as content_fullname,
                    u.profile_picture as content_profile_picture
                FROM ff_users u 
                WHERE u.email = ?";
                break;
    }

    if (isset($content_query)) {
        $stmt = $conn->prepare($content_query);
        if ($data['content_type'] == 'User') {
            $stmt->bind_param("s", $data['content_id']); 
        } else {
            $stmt->bind_param("i", $data['content_id']);
        }
        $stmt->execute();
        $content_result = $stmt->get_result();
        $content_data = $content_result->fetch_assoc();
        
        if ($content_data) {
            // Set the content data once
            $data['content_fullname'] = $content_data['content_fullname'] ?? null;
            $data['content_profile_picture'] = $content_data['content_profile_picture'] ?? null;
            $data['original_content'] = $content_data;
            
            // Generate original_link based on content type
            switch($data['content_type']) {
                case 'Review':
                    $data['original_link'] = "modLocation.php?location_id=" . 
                        $content_data['location_id'] . "#review-" . $data['content_id'];
                    break;
                    
                case 'Post':
                    $data['original_link'] = "modPost.php?id=" . $data['content_id'];
                    break;
                    
                case 'Comment':
                    $data['original_link'] = "modPost.php?id=" . 
                        $content_data['post_id'] . "#comment-" . $data['content_id'];
                    break;
                    
                case 'User':
                    $data['original_link'] = "modotherProfile.php?email=" . 
                        urlencode($content_data['email']); // Use email from content_data
                    break;
            }
        }
    }

    ob_clean();
    echo json_encode($data);

    $stmt->close();
    $conn->close();
    exit;
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    
    if(isset($stmt)) $stmt->close();
    if(isset($conn)) $conn->close();
    exit;
}
?>