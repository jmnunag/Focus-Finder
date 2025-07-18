<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

$email = $_SESSION['email'];
$query = "SELECT * FROM ff_users WHERE email = '$email'";
$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

$user = mysqli_fetch_assoc($result);
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
    <nav style="background: linear-gradient(360deg, rgb(93, 185, 113), rgb(30 69 38));">
    <a href="modHome.php"><img src="images/FFv1.png" class="logo"></a>
        <ul class="iw-links">
            <li><a href="modHome.php">Home</a></li>
            <li><a href="modAdvanced.php">Locations</a></li>
            <li><a href="modBoard.php">Board</a></li>
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
                <a href="modProfile.php" class="sub-menu-link">
                    <p>Profile</p>
                    <span>></span>
                </a>
                <a href="modMailbox.php" class="sub-menu-link">
                    <p>Mailbox</p>
                    <span>></span>
                </a>
                <a href="modMember.php" class="sub-menu-link">
                    <p>Membership Settings</p>
                    <span>></span>
                </a>
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