<?php
require_once "config.php";
header('Content-Type: application/json');
$response = array('available' => true, 'message' => '', 'userData' => null);

if(isset($_POST['email'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    $check_pending = "SELECT status FROM shop_signup WHERE email=? AND status='pending'";
    $stmt = $conn->prepare($check_pending);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows > 0) {
        $response['available'] = false;
        $response['message'] = "This email already has a pending owner application!";
        echo json_encode($response);
        exit();
    }

    $check_existing = "SELECT id, fullname, password, contact FROM ff_users WHERE email=?";
    $stmt = $conn->prepare($check_existing);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $check_owner = "SELECT type FROM ff_users WHERE email=? AND type='owner'";
        $stmt = $conn->prepare($check_owner);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $owner_result = $stmt->get_result();

        if($owner_result->num_rows > 0) {
            $response['available'] = false;
            $response['message'] = "Email already registered as an owner!";
        } else {
            $response['userData'] = [
                'fullname' => $user_data['fullname'],
                'password' => $user_data['password'],
                'contact' => $user_data['contact']
            ];
        }
    }

    if($response['available']) {
        $check_rejected = "SELECT id, rejection_date FROM shop_signup 
                          WHERE email=? AND status='rejected' 
                          ORDER BY rejection_date DESC LIMIT 1";
        $stmt = $conn->prepare($check_rejected);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if($result->num_rows > 0) {
            $rejection = $result->fetch_assoc();
            $rejection_date = new DateTime($rejection['rejection_date']);
            $current_date = new DateTime();
            $days_since_rejection = $current_date->diff($rejection_date)->days;

            if($days_since_rejection < 7) {
                $days_remaining = 7 - $days_since_rejection;
                $response['available'] = false;
                $response['message'] = "Your previous application was rejected. Please wait {$days_remaining} more days before reapplying.";
            } else {
                $delete_old = "DELETE FROM shop_signup WHERE id = ?";
                $stmt4 = $conn->prepare($delete_old);
                $stmt4->bind_param("i", $rejection['id']);
                $stmt4->execute();
            }
        }
    }
}

echo json_encode($response);
?>