<?php
require_once "config.php"; 
require_once "session.php"; 

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$message = ""; 
$messageClass = ""; 
$emailError = false;
$passwordError = false;
$unverified = false;
$email = "";
$verificationError = "";

if (isset($_GET['error'])) {
    if ($_GET['error'] == 'invalid_credentials') {
        $message = "Incorrect Credentials";
        $messageClass = "error-message";
        $emailError = true;
        $passwordError = true;
        $email = isset($_GET['email']) ? $_GET['email'] : '';
    } elseif ($_GET['error'] == 'unverified') {
        $message = "Please verify your email address.";
        $messageClass = "error-message";
        $unverified = true;
        $email = isset($_GET['email']) ? $_GET['email'] : '';
    } elseif ($_GET['error'] == 'invalid_verification_code') {
        $message = "Invalid verification code.";
        $messageClass = "error-message";
        $unverified = true;
        $email = isset($_GET['email']) ? $_GET['email'] : '';
        $verificationError = "Verification failed. Please try again.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="images/FFicon.ico">
    <title>Focus Finder</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <header>
        <nav>
        <a href="guestHome.php"><img src="images/FFv1.png" class="logo"></a>
            <ul class="iw-links">
                <li><a href="guestHome.php">Home</a></li>
                <li><a href="guestAdvanced.php">Locations</a></li>
            </ul>
            <div class="nav-right">
                <div class="register-container">
                    <a href="#" class="textsign" onclick="toggleRegisterMenu(event)">Sign up</a>
                    <div class="register-menu-wrap" id="registerMenu">
                    <div class="register-menu">
                        <a href="signup.php" class="register-menu-link">
                            <p>Sign up as User</p>
                            <span>></span>
                        </a>
                        <hr>
                        <a href="shopSignup.php" class="register-menu-link">
                            <p>Sign up as Shop Owner</p>
                            <span>></span>
                        </a>
                </div>
            </div>
        </nav>
        <div class="clearfix"></div>
    </header>

    <div class="main-content2">
        <div class="image-containerlog">
            <img src="images/FF2v1.png" class="limg2">
        </div>

        <div class="form-boxlog">
            <div class="login-text">Login</div>
            <form id="login" method="post" action="session.php">
                <label>Email</label>
                <div class="input-group <?php if ($emailError) echo 'error-border'; ?>">
                    <input type="text" name="email" placeholder="Enter your email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
                </div>  
                <label>Password</label>
                <div class="input-group <?php if ($passwordError) echo 'error-border'; ?>">
                    <div class="password-container">
                        <input type="password" name="password" id="password" placeholder="Enter your password" required>
                        <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                    </div>
                </div>
                <button type="submit" class="btn" name="submit">Login</button>
                <?php if (!empty($message)): ?>
                    <div class="message <?php echo $messageClass; ?>"><?php echo $message; ?></div>
                <?php endif; ?>
            </form>
            <a href="#" id="forgotPasswordLink">Forgot Password?</a>
        </div>
    </div>

    <div id="verificationModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Email Verification</h2>
            <form method="post" action="verify_code.php" id="verificationForm">
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="verificationEmail" name="email" value="<?php echo htmlspecialchars($email); ?>" required readonly>
                </div>
                <div class="form-group">
                <label for="verification_code">Verification Code:</label>
                <div class="verification-code-container">
                    <input type="text" id="verification_code" name="verification_code" required placeholder="Enter 6-digit code">
                    <a href="#" id="resendVerificationCode" class="btn-send">Send Code</a>
                    <div id="countdownOverlay" class="countdown-overlay" style="display: none;"></div>
                </div>
            </div>
            <button type="submit" class="btn-verify" name="verify">Verify</button>
            <?php if (!empty($verificationError)): ?>
                <div class="message error-message"><?php echo $verificationError; ?></div>
            <?php endif; ?>
        </form>
        <div class="error-message">
            <!-- Error message will be displayed here -->
        </div>
    </div>
</div>

<?php if(isset($_SESSION['message'])): ?>
        <div class="shop_alert shop-success-message" id="successAlert">
            <span><?php echo $_SESSION['message']; ?></span>
            <button type="button" class="shop-close-btn" onclick="closeAlert()">&times;</button>
        </div>
<?php endif; ?>

<div id="forgotPasswordModal" class="modal">
    <div class="modal-content">
        <span class="close" id="forgotPasswordClose">&times;</span>
        <h2>Forgot Password</h2>
        <form id="forgotPasswordForm">
            <div class="form-group">
                <label for="forgotEmail">Email:</label>
                <input type="email" id="forgotEmail" name="forgotEmail" required placeholder="Enter your email">
            </div>
            <button type="submit" class="btn">Submit</button>
        </form>
        <div class="message" id="forgotPasswordMessage"></div>
    </div>
</div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
    var inputs = document.querySelectorAll('.input-group input');
    inputs.forEach(function(input) {
        input.addEventListener('input', function() {
            this.closest('.input-group').classList.remove('error-border');
            var errorMessage = document.querySelector('.message.error-message');
            if (errorMessage) {
                errorMessage.style.display = 'none';
            }
        });
    });

    var togglePassword = document.getElementById("togglePassword");
    var passwordInput = document.getElementById("password");

    togglePassword.addEventListener("click", function() {
        var type = passwordInput.getAttribute("type") === "password" ? "text" : "password";
        passwordInput.setAttribute("type", type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });

    passwordInput.addEventListener('input', function() {
        var emailInputGroup = document.querySelector('.input-group input[name="email"]').closest('.input-group');
        emailInputGroup.classList.remove('error-border');
    });
});

    var modal = document.getElementById("verificationModal");

    var span = document.getElementsByClassName("close")[0];

    span.onclick = function() {
        modal.style.display = "none";
    }

    <?php if (isset($unverified) && $unverified): ?>
        modal.style.display = "block";
        document.getElementById('verificationEmail').value = "<?php echo htmlspecialchars($email); ?>";
    <?php endif; ?>

    document.querySelector('#verificationModal form').addEventListener('submit', function(event) {
    });

    document.addEventListener("DOMContentLoaded", function() {
        var resendLink = document.getElementById("resendVerificationCode");
        var emailInput = document.getElementById("verificationEmail");
        var countdownOverlay = document.getElementById("countdownOverlay");
        var verificationContainer = document.querySelector(".verification-code-container");

        resendLink.addEventListener("click", function(event) {
            event.preventDefault();
            var email = emailInput.value;

            fetch('send_verification_code.php?email=' + encodeURIComponent(email))
                .then(response => response.text())
                .then(data => {
                    const responseDiv = document.createElement('div');
                    responseDiv.innerHTML = data;

                    document.body.appendChild(responseDiv);

                    setTimeout(() => {
                        responseDiv.remove();
                    }, 5000);

                    resendLink.classList.add("disabled-button");
                    resendLink.style.pointerEvents = "none";
                    countdownOverlay.style.display = "flex";
                    verificationContainer.classList.add("disabled-container");

                    var countdown = 10;
                    var countdownInterval = setInterval(function() {
                        countdownOverlay.textContent = countdown;
                        countdown--;
                        if (countdown < 0) {
                            clearInterval(countdownInterval);
                            resendLink.classList.remove("disabled-button");
                            resendLink.style.pointerEvents = "auto";
                            countdownOverlay.style.display = "none";
                            verificationContainer.classList.remove("disabled-container");
                        }
                    }, 1000);
                })
                .catch(error => console.error('Error:', error));
        });
    });

    document.addEventListener("DOMContentLoaded", function() {
        var forgotPasswordLink = document.getElementById("forgotPasswordLink");
        var forgotPasswordModal = document.getElementById("forgotPasswordModal");
        var forgotPasswordClose = document.getElementById("forgotPasswordClose");
        var forgotPasswordForm = document.getElementById("forgotPasswordForm");
        var forgotPasswordMessage = document.getElementById("forgotPasswordMessage");

        forgotPasswordLink.onclick = function() {
            forgotPasswordModal.style.display = "block";
        }

        forgotPasswordClose.onclick = function() {
            forgotPasswordModal.style.display = "none";
            forgotPasswordForm.reset();
            forgotPasswordMessage.textContent = ''; 
            forgotPasswordMessage.className = ''; 
        }

        forgotPasswordForm.onsubmit = function(event) {
            event.preventDefault();
            var formData = new FormData(forgotPasswordForm);
            fetch('forgot_password.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                forgotPasswordMessage.textContent = data.message;
                forgotPasswordMessage.className = 'message ' + data.type;

                if (data.type === 'success-message') {
                    forgotPasswordForm.reset();
                }
            })
            .catch(error => console.error('Error:', error));
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.createElement('div');
    menuToggle.className = 'menu-toggle';
    menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
    
    const nav = document.querySelector('nav');
    const links = document.querySelector('.iw-links');
    
    nav.insertBefore(menuToggle, links);
    
    menuToggle.addEventListener('click', () => {
        links.classList.toggle('show');
    });
});

function toggleRegisterMenu(event) {
    event.preventDefault();
    let registerMenu = document.getElementById("registerMenu");
    registerMenu.classList.toggle("open-menu");
}

document.addEventListener('click', function(event) {
    let registerMenu = document.getElementById("registerMenu");
    let registerButton = document.querySelector('.register-container .textsign');
    
    if (!registerMenu.contains(event.target) && event.target !== registerButton) {
        registerMenu.classList.remove("open-menu");
    }
});

function closeAlert() {
    document.getElementById('successAlert').style.display = 'none';
}
</script>
</body>
</html>