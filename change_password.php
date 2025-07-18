<?php
require_once "config.php";
session_start();


$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . $host;


$message = ""; 
$messageClass = ""; 
$passwordError = false;
$passwordChanged = false; 
$new_password = "";
$confirm_new_password = "";

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $token = mysqli_real_escape_string($conn, $token);

    $check_token_query = "SELECT * FROM ff_users WHERE reset_token='$token' AND reset_token_expiry > NOW()";
    $result = $conn->query($check_token_query);

    if ($result->num_rows > 0) {
        if (isset($_POST['submit'])) {
            $new_password = $_POST['newPassword'];
            $confirm_new_password = $_POST['confirmNewPassword'];

            if ($new_password !== $confirm_new_password) {
                $message = "Passwords do not match!";
                $messageClass = "error-message";
                $passwordError = true;
            } else {
                $new_password_hashed = password_hash($new_password, PASSWORD_BCRYPT);
                $update_query = "UPDATE ff_users SET password='$new_password_hashed', reset_token=NULL, reset_token_expiry=NULL WHERE reset_token='$token'";
                if ($conn->query($update_query) === TRUE) {
                    $passwordChanged = true; 
                    $new_password = '';
                    $confirm_new_password = '';
                } else {
                    $message = "Error updating password.";
                    $messageClass = "error-message";
                }
            }
        }
    } else {
        $message = "Invalid or expired token.";
        $messageClass = "error-message";
    }
} else {
    $message = "Invalid or expired token.";
    $messageClass = "error-message";
}
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="images/FFicon.ico">
    <title>Focus Finder</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <header>
        <nav>
        <a href="guestHome.php"><img src="images/FFv1.png" class="logo"></a>
        </nav>
        <div class="clearfix"></div>
    </header>
<div class="reset-main-content">
    <div class="reset-form-boxlog">
        <div class="reset-text">Change Password</div>
        <form method="post">
            <label for="newPassword">New Password:</label>
            <div class="form-group <?php if ($passwordError) echo 'error-border'; ?>">
                <div class="password-container">
                    <input type="password" id="newPassword" name="newPassword" required placeholder="Enter new password" value="<?php echo htmlspecialchars($new_password); ?>">
                    <i class="fas fa-eye toggle-password"></i>
                </div>
            </div>
            <label for="confirmNewPassword">Confirm New Password:</label>
            <div class="form-group <?php if ($passwordError) echo 'error-border'; ?>">
                <div class="password-container">
                    <input type="password" id="confirmNewPassword" name="confirmNewPassword" required placeholder="Confirm new password" value="<?php echo htmlspecialchars($confirm_new_password); ?>">
                    <i class="fas fa-eye toggle-password"></i>
                </div>
            </div>
            <button type="submit" class="btn" name="submit">Change Password</button>
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageClass; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div id="successModal" class="modal">
    <div class="sucess-modal">
        <h2>Password Successfully Changed</h2>
        <button onclick="redirectToLogin()">Go to Login</button>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var togglePasswordIcons = document.querySelectorAll(".toggle-password");

    togglePasswordIcons.forEach(function(icon) {
        icon.addEventListener("click", function() {
            var passwordInput = this.previousElementSibling;
            var type = passwordInput.getAttribute("type") === "password" ? "text" : "password";
            passwordInput.setAttribute("type", type);

            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    });

    var newPasswordInput = document.getElementById("newPassword");
    var confirmNewPasswordInput = document.getElementById("confirmNewPassword");

    function clearError() {
        var newPasswordInputGroup = newPasswordInput.closest('.form-group');
        var confirmNewPasswordInputGroup = confirmNewPasswordInput.closest('.form-group');
        newPasswordInputGroup.classList.remove('error-border');
        confirmNewPasswordInputGroup.classList.remove('error-border');

        var errorMessage = document.querySelector('.message.error-message');
        if (errorMessage) {
            errorMessage.style.display = 'none';
        }
    }

    newPasswordInput.addEventListener('input', clearError);
    confirmNewPasswordInput.addEventListener('input', clearError);

    <?php if ($passwordChanged): ?>
        showModal();
    <?php endif; ?>
});

function showModal() {
    document.getElementById('successModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('successModal').style.display = 'none';
}

function redirectToLogin() {
    window.location.href = '<?php echo $baseUrl; ?>/login.php';
}
</script>
</body>
</html>