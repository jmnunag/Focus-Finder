<?php
require_once "config.php";
session_start();

// Check if the user is logged in
$is_logged_in = isset($_SESSION['email']);
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
    <?php include 'location_details.php'; ?>
    </div>

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

    // Pass the login status to JavaScript
    var isLoggedIn = <?php echo json_encode($is_logged_in); ?>;

    function vote(reviewId, voteType) {
        if (!isLoggedIn) {
            // Redirect to login page if the user is not logged in
            window.location.href = 'login.php';
            return;
        }

        var xhr = new XMLHttpRequest();
        xhr.open("POST", "vote.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onreadystatechange = function() {
            if (xhr.readyState === XMLHttpRequest.DONE && xhr.status === 200) {
                console.log(xhr.responseText); // Debugging: log the response
                location.reload(); // Reload the page to update the vote counts
            } else {
                console.error("Error: " + xhr.status); // Debugging: log the error
            }
        };
        xhr.send("review_id=" + reviewId + "&vote_type=" + voteType);
    }


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

document.addEventListener('DOMContentLoaded', function() {
    // Close modal handler
    document.querySelector('.image-modal .close').addEventListener('click', function() {
        document.getElementById('imageModal').style.display = "none";
    });

    // Close on clicking outside the image
    document.getElementById('imageModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = "none";
        }
    });

    function initializeGalleries() {
        // Location images gallery
        initializeGallery();
        // Menu images gallery
        initializeMenuGallery();
    }

    // Location images gallery initialization
    function initializeGallery() {
        const items = document.querySelectorAll('.gallery-item');
        const isMobile = window.innerWidth <= 768;
        const visibleItems = isMobile ? 1 : 3;

        if (items.length <= visibleItems) {
            document.querySelector('.location-images .prev').style.display = 'none';
            document.querySelector('.location-images .next').style.display = 'none';
        } else {
            document.querySelector('.location-images .prev').style.display = 'none';
            document.querySelector('.location-images .next').style.display = 'block';
            
            currentIndex = 0;
            items.forEach(item => item.classList.remove('active'));
            for (let i = 0; i < visibleItems; i++) {
                items[i].classList.add('active');
            }
        }
    }

    // Menu images gallery initialization
    function initializeMenuGallery() {
        const items = document.querySelectorAll('.menu-gallery-item');
        const isMobile = window.innerWidth <= 768;
        const visibleItems = isMobile ? 1 : 2; // Show 2 menu images

        if (items.length <= visibleItems) {
            document.querySelector('.menu-slider .prev').style.display = 'none';
            document.querySelector('.menu-slider .next').style.display = 'none';
        } else {
            document.querySelector('.menu-slider .prev').style.display = 'none';
            document.querySelector('.menu-slider .next').style.display = 'block';
            
            menuCurrentIndex = 0;
            items.forEach(item => item.classList.remove('active'));
            for (let i = 0; i < visibleItems; i++) {
                items[i].classList.add('active');
            }
        }
    }

    // Add event listeners for both galleries
    window.addEventListener('load', initializeGalleries);
    window.addEventListener('resize', initializeGalleries)
    
    // Add ESC key support
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.getElementById('imageModal').style.display = "none";
        }
    });

    // Fix image slider currentIndex variable
    let currentIndex = 0;

    // Fix slideImages function
    window.slideImages = function(direction) {
        const items = document.querySelectorAll('.gallery-item');
        const isMobile = window.innerWidth <= 768;
        const visibleItems = isMobile ? 1 : 3;
        const maxIndex = items.length - visibleItems;
        
        items.forEach(item => item.classList.remove('active'));
        
        if (direction === 'next' && currentIndex < maxIndex) {
            currentIndex++;
        } else if (direction === 'prev' && currentIndex > 0) {
            currentIndex--;
        }
        
        const endIndex = Math.min(currentIndex + visibleItems, items.length);
        for (let i = currentIndex; i < endIndex; i++) {
            items[i].classList.add('active');
        }
        
        // Update selectors to target location-images specifically
        document.querySelector('.location-images .prev').style.display = currentIndex === 0 ? 'none' : 'block';
        document.querySelector('.location-images .next').style.display = currentIndex === maxIndex ? 'none' : 'block';
    };

    let menuCurrentIndex = 0;
    window.slideMenuImages = function(direction) {
        const items = document.querySelectorAll('.menu-gallery-item');
        const isMobile = window.innerWidth <= 768;
        const visibleItems = isMobile ? 1 : 2;
        const maxIndex = items.length - visibleItems;
        
        items.forEach(item => item.classList.remove('active'));
        
        if (direction === 'next' && menuCurrentIndex < maxIndex) {
            menuCurrentIndex++;
        } else if (direction === 'prev' && menuCurrentIndex > 0) {
            menuCurrentIndex--;
        }
        
        const endIndex = Math.min(menuCurrentIndex + visibleItems, items.length);
        for (let i = menuCurrentIndex; i < endIndex; i++) {
            items[i].classList.add('active');
        }
        
        const menuSlider = document.querySelector('.menu-slider');
        menuSlider.querySelector('.prev').style.display = menuCurrentIndex === 0 ? 'none' : 'block';
        menuSlider.querySelector('.next').style.display = menuCurrentIndex === maxIndex ? 'none' : 'block';
    };
});
</script>
</body>
</html>