<?php
require_once "config.php";
session_start();
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
</head>
<body>
<header>
    <nav>
    <a href="guestHome.php"><img src="images/FFv1.png" class="logo"></a>
      <ul class="iw-links">
        <li><a href="guestHome.php">Home</a></li>
        <li><a href="guestAdvanced.php">Locations</a></li>
      </ul>
      <div class="nav-right-guest">
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
      </div>
      <a href="login.php" class="textsign">Log in</a>
  </div>
        </nav>
    <div class="clearfix"></div>
  </header>

  <div class="main-content">
  <?php include 'search_filter.php'; ?>
  </div>
</body>
</html>
<script>

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
