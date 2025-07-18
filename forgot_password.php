<?php
require_once "config.php";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

$response = ['type' => 'error-message', 'message' => ''];

if (isset($_POST['forgotEmail'])) {
    $email = $_POST['forgotEmail'];
    $email = mysqli_real_escape_string($conn, $email);

    $check_query = "SELECT * FROM ff_users WHERE email='$email'";
    $check_result = $conn->query($check_query);

    if ($check_result->num_rows > 0) {
        $token = bin2hex(random_bytes(50));

        $update_query = "UPDATE ff_users SET reset_token='$token', reset_token_expiry=DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE email='$email'";
        if ($conn->query($update_query) === TRUE) {
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'jonasmathewnunag@gmail.com';
                $mail->Password = 'ycocjctxpfgwkvud';
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                $mail->setFrom('Focus_Finder@gmail.com', 'Focus Finder');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Link';
                
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
                $host = $_SERVER['HTTP_HOST'];
                $baseUrl = $protocol . $host;
                $mail->Body = "Please use the following link to reset your password: <a href='" . $baseUrl . "/change_password.php?token=$token'>Reset Password</a>";

                $mail->send();
                $response['type'] = 'success-message';
                $response['message'] = 'A mail will be sent to your Email Address, Please wait a moment.';
            } catch (Exception $e) {
                $response['message'] = "Error sending mail. Mailer Error: {$mail->ErrorInfo}";
            }
        } else {
            $response['message'] = "Error updating database.";
        }
    } else {
        $response['message'] = "Email Address not found.";
    }
}

echo json_encode($response);
?>