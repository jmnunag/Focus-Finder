<?php
require_once "config.php";
session_start();

$message = ""; 
$messageClass = ""; 

$fullname = "";
$email = "";
$password = "";
$cpassword = "";
$emailError = false;
$passwordError = false;

if (isset($_POST['submits'])) {
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $cpassword = $_POST['cpassword'];
    $type = "user";
    $profile_picture = 'images/pfp.png'; 

    if ($password !== $cpassword) {
        $message = "Passwords do not match!";
        $messageClass = "error-message";
        $passwordError = true;
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*(),.?":{}|<>])[A-Za-z\d!@#$%^&*(),.?":{}|<>]{8,16}$/', $password)) {
        $message = "Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character";
        $messageClass = "error-message";
        $passwordError = true;
    } else {
        $email = mysqli_real_escape_string($conn, $email);
        $password = mysqli_real_escape_string($conn, $password);

        $check_query = "SELECT * FROM ff_users WHERE email='$email'";
        $check_result = $conn->query($check_query);

        if ($check_result->num_rows > 0) {
            $message = "Email already exists!";
            $messageClass = "error-message";
            $emailError = true;
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            $insert_query = "INSERT INTO ff_users (fullname, email, password, type, profile_picture, verified) VALUES ('$fullname','$email', '$hashed_password', '$type', '$profile_picture', 0)";
            if ($conn->query($insert_query) === TRUE) {
                $message = "Successfully registered! Please log in to verify your email.";
                $messageClass = "success-message";
                $fullname = $email = $password = $cpassword = "";
            } else {
                $message = "Error: " . $insert_query . "<br>" . $conn->error;
                $messageClass = "error-message";
            }
        }
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
                <a href="login.php" class="textsign">Log in</a>
            </div>
        </nav>
        <div class="clearfix"></div>
    </header>

    <div class="main-content2">
        <div class="image-container2">
            <img src="images/FF2v1.png" class="limg2">
        </div>

        <div class="form-box2">
            <div class="signup-text">User Sign Up</div>
            <form action="signup.php" method="post">

            <label>Full Name</label>
                <div class="input-group">
                <input type="text" id="fullname" name="fullname" placeholder="Enter your name" value="<?php echo htmlspecialchars($fullname); ?>" required>
                </div>
            
            <label>Email</label>
                <div class="input-group <?php if ($emailError) echo 'error-border'; ?>">
                <input type="email" id="email" name="email" placeholder="Enter your email" value="<?php echo htmlspecialchars($email); ?>" required>
            </div>
                
            <label>Password</label>
                <div class="input-group <?php if ($passwordError) echo 'error-border'; ?>">
                <div class="password-container">
                    <input type="password" id="password" name="password" placeholder="Enter your password" 
                        value="<?php echo htmlspecialchars($password); ?>" 
                        required minlength="8" maxlength="16">
                    <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                </div>
                <i class="fas fa-exclamation-circle password-warning" style="display: none;" 
                    data-requirements="• 8-16 characters
• At least one uppercase letter
• At least one lowercase letter
• At least one number
• At least one special character (!@#$%^&*(),.?':{}|<>)"></i>
            </div>
                
            <label>Confirm Password</label>
                <div class="input-group <?php if ($passwordError) echo 'error-border'; ?>">
                <div class="password-container">
                    <input type="password" id="cpassword" name="cpassword" placeholder="Confirm your password" value="<?php echo htmlspecialchars($cpassword); ?>" required>
                    <i class="fas fa-eye toggle-password" id="toggleCPassword"></i>
                </div>
            </div>
            <button type="submit" class="btn" name="submits">Sign Up</button>
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageClass; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
        </form>
        <a href="shopSignup.php">Are you a shop owner? Sign up as an Owner</a>
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

            var toggleCPassword = document.getElementById("toggleCPassword");
            var cpasswordInput = document.getElementById("cpassword");

            toggleCPassword.addEventListener("click", function() {
                var type = cpasswordInput.getAttribute("type") === "password" ? "text" : "password";
                cpasswordInput.setAttribute("type", type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });

            passwordInput.addEventListener('input', function() {
                var passwordInputGroup = passwordInput.closest('.input-group');
                var cpasswordInputGroup = cpasswordInput.closest('.input-group');
                passwordInputGroup.classList.remove('error-border');
                cpasswordInputGroup.classList.remove('error-border');
            });

            cpasswordInput.addEventListener('input', function() {
                var passwordInputGroup = passwordInput.closest('.input-group');
                var cpasswordInputGroup = cpasswordInput.closest('.input-group');
                passwordInputGroup.classList.remove('error-border');
                cpasswordInputGroup.classList.remove('error-border');
            });
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

document.addEventListener("DOMContentLoaded", function() {
    const passwordInput = document.getElementById("password");
    const warningIcon = document.querySelector('.password-warning');
    
    function validatePassword(password) {
        const minLength = 8;
        const maxLength = 16;
        const hasUpperCase = /[A-Z]/.test(password);
        const hasLowerCase = /[a-z]/.test(password);
        const hasNumbers = /\d/.test(password);
        const hasSpecialChar = /[!@#$%^&*(),.?":{}|<>]/.test(password);
        
        if (password.length === 0) {
            return { valid: true, message: '' }; 
        }
        
        if (password.length < minLength || password.length > maxLength) {
            return { valid: false, message: 'Password must be 8-16 characters' };
        }
        
        if (!hasUpperCase) {
            return { valid: false, message: 'Include at least one uppercase letter' };
        }
        
        if (!hasLowerCase) {
            return { valid: false, message: 'Include at least one lowercase letter' };
        }
        
        if (!hasNumbers) {
            return { valid: false, message: 'Include at least one number' };
        }
        
        if (!hasSpecialChar) {
            return { valid: false, message: 'Include at least one special character' };
        }
        
        return { valid: true, message: '' };
    }
    
    passwordInput.addEventListener('input', function() {
        const validation = validatePassword(this.value);
        
        if (this.value.length > 0) {
            if (!validation.valid) {
                warningIcon.style.display = 'block';
                warningIcon.setAttribute('title', validation.message);
                this.setCustomValidity(validation.message);
            } else {
                warningIcon.style.display = 'none';
                this.setCustomValidity('');
            }
        } else {
            warningIcon.style.display = 'none';
            this.setCustomValidity('');
        }
    });
    
    passwordInput.addEventListener('blur', function() {
        if (this.value.length === 0) {
            warningIcon.style.display = 'none';
        }
    });
});

    </script>
</body>
</html>