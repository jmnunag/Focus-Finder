<?php
require_once "config.php";
session_start();

if (isset($_POST['submit'])) {
    $email = $_SESSION['email'];
    $fullname = $_SESSION['fullname'];
    $user_id = $_SESSION['user_id'];
    $subject = $_POST['subject'];
    $location = $_POST['location'];
    $description = $_POST['description'];
    $image_url = null;

    if ($_POST['action_type'] === 'create') {
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $target_dir = "uploads/";
            $target_file = $target_dir . basename($_FILES["image"]["name"]);
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

            if (getimagesize($_FILES["image"]["tmp_name"]) !== false) {
                if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                    $image_url = $target_file;
                }
            }
        }

        $stmt = $conn->prepare("INSERT INTO bulletin_posts (user_id, email, fullname, post_date, location, description, subject, image_url, timestamp, status) VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, NOW(), 'active')");
        $stmt->bind_param("issssss", $user_id, $email, $fullname, $location, $description, $subject, $image_url);
        $stmt->execute();
        $stmt->close();

    } elseif ($_POST['action_type'] === 'edit') {
        $post_id = $_POST['post_id'];
        
        $get_current = $conn->prepare("SELECT image_url FROM bulletin_posts WHERE id = ?");
        $get_current->bind_param("i", $post_id);
        $get_current->execute();
        $result = $get_current->get_result();
        $current_data = $result->fetch_assoc();
        $image_url = $current_data['image_url'];
        
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $target_dir = "uploads/";
            $target_file = $target_dir . basename($_FILES["image"]["name"]);
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $image_url = $target_file;
            }
        }
    
        $stmt = $conn->prepare("UPDATE bulletin_posts SET subject=?, location=?, description=?, image_url=? WHERE id=?");
        $stmt->bind_param("ssssi", $subject, $location, $description, $image_url, $post_id);
        $stmt->execute();
        $stmt->close();
    }

    if (isset($_SESSION['type']) && $_SESSION['type'] === 'moderator') {
        header("Location: modBoard.php");
    } else {
        header("Location: userBoard.php");
    }
    exit;
}
?>