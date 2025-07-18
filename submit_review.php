<?php
require_once "config.php";
session_start();

if (isset($_SESSION['is_flagged']) && $_SESSION['is_flagged']) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'You cannot submit reviews while your account is flagged.']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $location_id = isset($_POST['location_id']) ? $_POST['location_id'] : null;
    $email = isset($_SESSION['email']) ? $_SESSION['email'] : '';
    $rating = isset($_POST['rating']) ? $_POST['rating'] : '';
    $comment = isset($_POST['comment']) ? $_POST['comment'] : '';

    if ($location_id && $email && $rating && $comment) {
        $query = $conn->prepare("SELECT fullname, profile_picture FROM ff_users WHERE email = ?");
        $query->bind_param("s", $email);
        $query->execute();
        $result = $query->get_result();
        $user = $result->fetch_assoc();

        if ($user) {
            $fullname = $user['fullname'];
            $profile_picture = $user['profile_picture'];

            $query = $conn->prepare("INSERT INTO reviews (location_id, email, rating, review_text, created_at) VALUES (?, ?, ?, ?, NOW())");
            $query->bind_param("isis", $location_id, $email, $rating, $comment);

            if ($query->execute()) {
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
                $host = $_SERVER['HTTP_HOST'];
                $baseUrl = $protocol . $host;
                header("Location: " . $baseUrl . "/userLocation.php?location_id=" . $location_id . "&review_submitted=true");
                exit();
            } else {
                die("Error: Could not submit review. " . $conn->error);
            }
        } else {
            die("Error: User not found.");
        }
    } else {
        die("Error: All fields are required.");
    }
}

$conn->close();
?>