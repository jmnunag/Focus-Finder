<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

$email = $_SESSION['email'];
$query = $conn->prepare("SELECT *, flagged FROM ff_users WHERE email = ?");
$query->bind_param("s", $email);
$query->execute();
$result = $query->get_result();

if (!$result) {
    die("Query failed: " . $conn->error);
}

$user = $result->fetch_assoc();

if ($user['flagged']) {
    $_SESSION['flagged'] = true;
    echo '<div id="flaggedAlert" class="alert alert-warning popup-notification">
            Your account has been flagged. Some features may be restricted.
          </div>';
} else {
    $_SESSION['flagged'] = false;
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/x-icon" href="images/FFicon.ico">
    <title>Focus Finder</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
<div class="header">
    <nav>
    <a href="userHome.php"><img src="images/FFv1.png" class="logo"></a>
        <ul class="iw-links">
            <li><a href="userHome.php">Home</a></li>
            <li><a href="userAdvanced.php">Locations</a></li>
            <li><a href="userBoard.php">Board</a></li>
        </ul>
        <div class="nav-right">
            <div class="notification-container">
                <i class="fas fa-bell notification-bell" onclick="toggleNotifications()"></i>
                <div class="notifications-dropdown" id="notificationsDropdown"></div>
            </div>
            <img src="<?php echo $_SESSION['profile_picture']; ?>" class="user-pic" onclick="toggleMenu()">
        </div>
        </nav>

        <div class="sub-menu-wrap" id="subMenu">
            <div class="sub-menu">
                <div class="user-info">
                    <img src="<?php echo $_SESSION['profile_picture']; ?>">
                    <h3><?php echo $_SESSION['fullname']; ?></h3>
                </div>
                <hr>
                <?php if ($_SESSION['type'] === 'owner'): ?>
                <a href="shopSettings.php" class="sub-menu-link">
                    <p>Shop Settings</p>
                    <span>></span>
                </a>
                <?php endif; ?>
                <a href="userProfile.php" class="sub-menu-link">
                    <p>Profile</p>
                    <span>></span>
                </a>
                <?php if ($_SESSION['type'] === 'owner'): ?>
                    <a href="ownerMember.php" class="sub-menu-link">
                        <p>Establishment Membership</p>
                        <span>></span>
                    </a>
                <?php else: ?>
                    <a href="userMember.php" class="sub-menu-link">
                        <p>User Membership</p>
                        <span>></span>
                    </a>
                <?php endif; ?>
                <a href="logout.php" class="sub-menu-link">
                    <p>Log Out</p>
                    <span>></span>
                </a>
            </div>
        </div>
    <div class="clearfix"></div>
</div>

    <div class="main-content">
        <?php include 'board_details.php'; ?>
    </div>

    <script>
        function fetchNotifications() {
    fetch('notifications.php')
        .then(response => response.json())
        .then(data => {
            let dropdown = document.getElementById("notificationsDropdown");
            dropdown.innerHTML = '';
            data.forEach(notification => {
                let item = document.createElement('div');
                item.classList.add('notification-item');
                item.innerHTML = `<a href="${notification.link}">${notification.message}</a>`;
                dropdown.appendChild(item);
            });
        });
}

document.addEventListener('DOMContentLoaded', function() {
    fetchNotifications();
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
    </script>
</body>
</html>