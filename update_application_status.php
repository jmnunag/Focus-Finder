<?php
require_once "config.php";
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $conn->begin_transaction();

        $id = $_POST['id'];
        $status = $_POST['status'];

        // Get application details
        $query = "SELECT * FROM shop_signup WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $application = $result->fetch_assoc();

        // Update application status
        $update_query = "UPDATE shop_signup SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();

        if ($status === 'approved') {
            // Debug user check
            $check_user = "SELECT id FROM ff_users WHERE email = ?";
            $stmt = $conn->prepare($check_user);
            $stmt->bind_param("s", $application['email']);
            $stmt->execute();
            $user_result = $stmt->get_result();
            error_log("Found user: " . $user_result->num_rows);
    
            if ($user_result->num_rows > 0) {
                // Update existing user
                $user = $user_result->fetch_assoc();
                $update_user = "UPDATE ff_users SET type = 'owner' WHERE id = ?";
                $stmt = $conn->prepare($update_user);
                $stmt->bind_param("i", $user['id']);
                if (!$stmt->execute()) {
                    throw new Exception("Error updating user type: " . $stmt->error);
                }
            } else {
                // Create new user - match ff_users table structure exactly
                $insert_user = "INSERT INTO ff_users (
                    fullname, email, password, type, contact, profile_picture
                ) VALUES (?, ?, ?, 'owner', ?, 'images/pfp.png')";
                $stmt = $conn->prepare($insert_user);
                $stmt->bind_param("ssss", 
                    $application['fullname'], 
                    $application['email'], 
                    $application['password'],
                    $application['contact_number']
                );
                if (!$stmt->execute()) {
                    throw new Exception("Error creating user: " . $stmt->error);
                }
            }
    
            // Insert into study_locations - match column names exactly
            $insert_location = "INSERT INTO study_locations (
                name, description, time, 
                latitude, longitude, owner_email, 
                min_price, max_price, tags, 
                image_url
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
            $default_image = 'images/FFv1.png';
            $stmt = $conn->prepare($insert_location);
            $stmt->bind_param("sssddsddss", 
                $application['shop_name'],
                $application['shop_details'],
                $application['operating_hours'],
                $application['latitude'],
                $application['longitude'],
                $application['email'],
                $application['min_price'],
                $application['max_price'],
                $application['tags'],
                $default_image
            );
            if (!$stmt->execute()) {
                throw new Exception("Error inserting location: " . $stmt->error);
            }
            

            // Create location folder with proper name
            $folder_name = $application['shop_name'];
            // Only remove characters that are invalid for Windows folders
            $folder_name = str_replace(['/', '\\', '?', '*', ':', '|', '"', '<', '>'], '', $folder_name);
            $new_location_dir = 'locations/' . trim($folder_name);

            if (!file_exists($new_location_dir)) {
                if (!mkdir($new_location_dir, 0777, true)) {
                    throw new Exception("Failed to create directory: " . $new_location_dir);
                }
            }

            // Move images with error checking
            $images = json_decode($application['images']);
            foreach ($images as $old_path) {
                $filename = basename($old_path);
                $new_path = $new_location_dir . '/' . $filename;
                if (!rename($old_path, $new_path)) {
                    throw new Exception("Failed to move file: " . $old_path);
                }
            }
        
        
        } elseif ($status === 'rejected') {
            // Set rejection date
            $update_rejection = "UPDATE shop_signup SET rejection_date = NOW() WHERE id = ?";
            $stmt = $conn->prepare($update_rejection);
            $stmt->bind_param("i", $id);
            $stmt->execute();
        }

        // Delete original application folder for both approved and rejected
        $images = json_decode($application['images']);
        if ($images && !empty($images)) {
            $app_folder = dirname($images[0]);
            if (is_dir($app_folder)) {
                array_map('unlink', glob("$app_folder/*.*"));
                rmdir($app_folder);
            }
        }

        $conn->commit();
        error_log("Transaction completed successfully");
        echo json_encode(['status' => 'success']);

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error in update_application_status: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}
?>