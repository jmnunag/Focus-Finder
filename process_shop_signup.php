<?php
require_once "config.php";
session_start();


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $fullname = $_POST['fullname'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        $contact_number = $_POST['Cnumber'];
        $shop_name = $_POST['Nlocation'];
        $shop_details = $_POST['details'];
        $operating_hours = $_POST['Ohours'];
        $min_price = floatval($_POST['minPrice']);
        $max_price = floatval($_POST['maxPrice']);
        $tags = $_POST['selectedCategories'];
        $address = $_POST['Laddress'];
        $latitude = floatval($_POST['latitude']);
        $longitude = floatval($_POST['longitude']);

        $image_paths = array();
        if (isset($_FILES['shopImages'])) {
            $base_dir = 'shop_applications/';
            if (!file_exists($base_dir)) {
                mkdir($base_dir, 0777, true);
            }

            $shop_folder = $shop_name;
            $shop_folder = str_replace(['/', '\\', '?', '*', ':', '|', '"', '<', '>'], '', $shop_folder);
            $establishment_dir = $base_dir . trim($shop_folder) . '/';

            if (file_exists($establishment_dir)) {
                $establishment_dir = $base_dir . trim($shop_folder) . '_' . time() . '/';
            }

            if (!file_exists($establishment_dir)) {
                mkdir($establishment_dir, 0777, true);
            }

            foreach ($_FILES['shopImages']['tmp_name'] as $key => $tmp_name) {
                $file_name = $_FILES['shopImages']['name'][$key];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $new_file_name = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9-_.]/', '_', $file_name);
                $destination = $establishment_dir . $new_file_name;

                if (move_uploaded_file($tmp_name, $destination)) {
                    $image_paths[] = $destination;
                }
            }
        }

        $images_json = json_encode($image_paths);

        $query = "INSERT INTO shop_signup (
            fullname, email, password, contact_number,
            shop_name, shop_details, operating_hours, min_price, max_price,
            tags, address, latitude, longitude, images
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            "sssssssddssdds",
            $fullname, $email, $password, $contact_number,
            $shop_name, $shop_details, $operating_hours, $min_price, $max_price,
            $tags, $address, $latitude, $longitude, $images_json
        );

        if ($stmt->execute()) {
            $_SESSION['message'] = "Application submitted successfully! Please wait for admin approval.";
            header("Location: login.php");
            exit();
        } else {
            throw new Exception("Error submitting application");
        }

    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
        header("Location: shopSignup.php");
        exit();
    }
} else {
    header("Location: shopSignup.php");
    exit();
}
?>