<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

$servername = 'localhost'; 
$username = 'root'; 
$password = ''; 
$dbname = 'focus_finder'; 

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$verification_code = rand(100000, 999999);

$user_email = $_GET['email'];

$sql = "UPDATE ff_users SET verification_code='$verification_code' WHERE email='$user_email'";
if ($conn->query($sql) === TRUE) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'jonasmathewnunag@gmail.com';
        $mail->Password = 'ycocjctxpfgwkvud';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('your_gmail@gmail.com', 'Focus Finder');
        $mail->addAddress($user_email);

        $mail->isHTML(true);
        $mail->Subject = 'Email Verification Code';
        $mail->Body = "Your verification code is: <b>$verification_code</b>";

        $mail->send();
        echo '<div class="alert alert-success popup-notification" role="alert">
                Verification code has been sent to your email.
            </div>';
    } catch (Exception $e) {
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
} else {
    echo "Error: " . $sql . "<br>" . $conn->error;
}

$conn->close();
?>