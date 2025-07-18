<?php
require_once "config.php";

date_default_timezone_set('Asia/Manila');

$query = "DELETE FROM bulletin_posts WHERE DATE(post_date) < DATE_SUB(CURDATE(), INTERVAL 7 DAY)";

try {
    if ($conn->query($query)) {
        error_log("Successfully deleted posts older than 7 days - " . date('Y-m-d H:i:s'));
    } else {
        error_log("Error deleting old posts: " . $conn->error);
    }
} catch (Exception $e) {
    error_log("Exception when deleting old posts: " . $e->getMessage());
}

$conn->close();