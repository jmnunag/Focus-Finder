<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

if (isset($_SESSION['email'])) {
    $email = $_SESSION['email'];
    $query = "SELECT * FROM ff_users WHERE email = '$email'";
    $result = mysqli_query($conn, $query);

    if (!$result) {
        die("Query failed: " . mysqli_error($conn));
    }

    $user = mysqli_fetch_assoc($result);

    if ($user['type'] !== 'moderator') {
        header("Location: login.php");
        exit;
    }
}
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
                    <a href="modDashboard.php" class="sub-menu-link">
                        <p>Dashboard</p>
                        <span>></span>
                    </a>
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
    <div class="index-text1">Find the Nearest Study</div>
    <div class="index-text2">Location</div>
    <div class="index-text3">Explore and Discover the nearest study locations for your needs.</div>

    <div class="search-form-box">
    <input type="text" id="Flocation" class="search-input" placeholder="Enter your location">
    <button onclick="searchUserLocation()" class="btn">Search Location</button>
    <button onclick="useCurrentLocation()" class="btn">Use Current Location</button>
    </div>

    <div class="form-box">
      <button type="button" class="location-btn" onclick="expandMap()">Get Direction</button>
    </div>

    <div style="display: flex; justify-content: center;">
    <div class="map-box" id="map-container">
      <?php include 'home_map.php'; ?>
      <button class="close-button" id="close-button" onclick="minimizeMap()" style="display: none;">X</button>
      <button id="toggleRoutingControl" style="display: none;">
          <i class="fas fa-directions"></i>
      </button>
      <div id="route-table">
        <!-- Route table content here -->
      </div>
      <button id="toggleJeepRoutes" style="display: none;">Show Jeep Routes</button>
      <button id="toggleRouteControls" style="display: none;">
            <i class="fas fa-list"></i>
        </button>
      <div id="routeControls" class="route-controls"></div>
    </div>
    </div>
    </div>

    <div class="footer">
        <div class="footernav">
            <div class="col">
                <span style="color:#000000; font-size:30px">Focus</span>
                <span style="color:#a6fcb9; font-size:30px">Finder</span>
                <p>Focus Finder: A Review Platform for Student-Friendly Study Locations in Angeles City. It is designed to help students find the nearest study location and help students find the best rated place to study! This innovative platform is designed to help students find the nearest study locations and identify the best-rated places to study based on user reviews and ratings. By leveraging location-based services, Focus Finder provides real-time information on various study spots, including libraries, cafes, and quiet zones, ensuring that students can easily discover and access the most conducive environments for their academic needs. </p>
            </div>
            <div class="col">
                <h3>LOCATION</h3>
                <p>City College of Angeles, Arayat Boulevard, Barangay pampang, Angeles City</p>
            </div>
            <div class="col">
                <h3>CONTACT US</h3>
                <p>0975 784 9845</p>
                <p><a href="https://www.facebook.com/geatsss"></i>facebook.com/geatssss</p>
                <p><a href="https://mail.google.com/mail/u/2/#inbox"></i>jamusni@cca.edu.ph</a></p>
            </div>
        </div>
      </div>
  </body>
</html>

<script>
function getUserLocation(callback) {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(position) {
      var userLatLng = [position.coords.latitude, position.coords.longitude];
      callback(userLatLng);
    }, function(error) {
      console.error("Error getting user's location: ", error);
      callback(null);
    });
  } else {
    console.error("Geolocation is not supported by this browser.");
    callback(null);
  }
}

function expandMap() {
    document.querySelector('nav').classList.add('hidden');

    previousZoom = map.getZoom();
    previousCenter = map.getCenter();

    var mapContainer = document.getElementById('map-container');
    mapContainer.classList.add('full-screen-map');

    document.getElementById('close-button').style.display = 'block';
    document.getElementById('toggleJeepRoutes').style.display = 'block';
    document.getElementById('toggleRoutingControl').style.display = 'block';

    if (typeof showMarkers === 'function') {
        showMarkers();
    }

    if (typeof map !== 'undefined') {
        map.invalidateSize();
    }

    if (userLocation) {
        map.setView([userLocation.lat, userLocation.lon], 16);
    } else {
        map.setView(previousCenter, 16);
    }

    if (selectedDestination) {
        getDirections(selectedDestination.lat, selectedDestination.lng);
    } else {
        if (typeof showClosestRoute === 'function') {
            showClosestRoute();
        }
    }

    var routingToggle = document.getElementById('toggleRoutingControl');
    routingToggle.style.display = 'block';

    var routingControls = document.getElementsByClassName('leaflet-routing-container');
    for (var i = 0; i < routingControls.length; i++) {
        routingControls[i].style.display = 'none';
        routingControls[i].classList.add('routing-control');
    }
}

function minimizeMap() {
  document.querySelector('nav').classList.remove('hidden');

  document.getElementById('toggleRouteControls').style.display = 'none';
  document.getElementById('routeControls').style.display = 'none';
  
  if (!userLocation) {
    getUserLocation(function(userLatLng) {
      if (userLatLng) {
        map.setView(userLatLng, previousZoom);
      } else {
        map.setView(previousCenter, previousZoom);
      }
    });
  } else {
    map.setView([userLocation.lat, userLocation.lon], previousZoom);
  }

  if (typeof hideMarkers === 'function') {
      hideMarkers();
  }

  if (jeepRoutesVisible) {
      removeJeepRoutes();
      jeepRoutesVisible = false;
      document.getElementById('toggleJeepRoutes').textContent = 'Show Jeep Routes';
  }

  var mapContainer = document.getElementById('map-container');
  mapContainer.classList.remove('full-screen-map');

  var closeButton = document.getElementById('close-button');
  closeButton.style.display = 'none';

  var jeepRoutesButton = document.getElementById('toggleJeepRoutes');
  jeepRoutesButton.style.display = 'none';

  if (typeof control !== 'undefined' && control) {
    map.removeControl(control);
    control = null;
  }

  if (typeof map !== 'undefined') {
    map.invalidateSize();
  }

  var routingToggle = document.getElementById('toggleRoutingControl');
    routingToggle.style.display = 'none';

    var routingControls = document.getElementsByClassName('leaflet-routing-container');
    for (var i = 0; i < routingControls.length; i++) {
        routingControls[i].style.display = 'none';
    }
}

document.getElementById('toggleRoutingControl').addEventListener('click', function() {
    var routingControls = document.getElementsByClassName('leaflet-routing-container');
    for (var i = 0; i < routingControls.length; i++) {
        if (routingControls[i].style.display === 'none') {
            routingControls[i].style.display = 'block';
        } else {
            routingControls[i].style.display = 'none';
        }
    }
});

document.getElementById('toggleRouteControls').addEventListener('click', function() {
    var routeControlsPanel = document.getElementById('routeControls');
    if (routeControlsPanel.style.display === 'none') {
        routeControlsPanel.style.display = 'block';
    } else {
        routeControlsPanel.style.display = 'none';
    }
});

window.addEventListener('load', function() {
  if (typeof control !== 'undefined' && control) {
    map.removeControl(control);
    control = null; 
  }
});

var jeepRouteLayers = [];
var routeControls = {};

function loadJeepRoutes() {
    console.log('loadJeepRoutes function called');
    
    document.getElementById('routeControls').style.display = 'block';
    
    fetch('jeep_routes.php')
        .then(response => response.text())
        .then(text => JSON.parse(text))
        .then(data => {
            const controlsContainer = document.getElementById('routeControls');
            controlsContainer.innerHTML = '<h4>Jeepney Routes</h4>';
            
            var colorMapping = {
                'Villa': 'gold',
                'Pandan': 'blue',
                'Marisol': 'green',
                'Checkpoint': 'purple',
                'Sapang Bato': 'maroon',
                'Plaridel': 'pink',
                'Hensonville': 'white',
                'Sunset': 'orange',
                'Manibaug': 'grey'
            };

            for (var routeName in data) {
                if (data.hasOwnProperty(routeName)) {
                    const color = colorMapping[routeName] || 'green';

                    const routeItem = document.createElement('div');
                    routeItem.className = 'route-item';

                    const colorIndicator = document.createElement('div');
                    colorIndicator.className = 'route-color';
                    colorIndicator.style.backgroundColor = color;

                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.className = 'route-checkbox';
                    checkbox.checked = true;

                    const label = document.createElement('span');
                    label.textContent = routeName;

                    routeItem.appendChild(checkbox);
                    routeItem.appendChild(colorIndicator);
                    routeItem.appendChild(label);
                    controlsContainer.appendChild(routeItem);
                    
                    const latLngs = data[routeName].map(point => [point.lat, point.lng]);
                    const polyline = L.polyline(latLngs, { color: color }).bindPopup(routeName);
                    polyline.addTo(map);
                    jeepRouteLayers.push(polyline);
                    
                    routeControls[routeName] = polyline;
                    checkbox.addEventListener('change', function() {
                        if (this.checked) {
                            map.addLayer(polyline);
                        } else {
                            map.removeLayer(polyline);
                        }
                    });
                }
            }
        })
        .catch(error => console.error('Error loading jeepney routes:', error));
}

function removeJeepRoutes() {
    jeepRouteLayers.forEach(layer => {
        if (map.hasLayer(layer)) {
            map.removeLayer(layer);
        }
    });
    jeepRouteLayers = [];
    document.getElementById('routeControls').style.display = 'none';
    document.getElementById('toggleRouteControls').style.display = 'none';
}

if (document.getElementById('routeControls').style.display === 'block') {
    document.getElementById('routeControls').style.display = 'none';
}

var jeepRoutesVisible = false;

document.getElementById('toggleJeepRoutes').addEventListener('click', function() {
    if (!jeepRoutesVisible) {
        loadJeepRoutes(); 
        this.textContent = 'Hide Jeep Routes';
        document.getElementById('toggleRouteControls').style.display = 'block';
    } else {
        removeJeepRoutes();
        this.textContent = 'Show Jeep Routes';
        document.getElementById('toggleRouteControls').style.display = 'none';
    }
    jeepRoutesVisible = !jeepRoutesVisible;
});

function toggleMenu() {
            let subMenu = document.getElementById("subMenu");
            subMenu.classList.toggle("open-menu");
        }

    function toggleNotifications() {
    let dropdown = document.getElementById("notificationsDropdown");
    dropdown.classList.toggle("show");

    if (dropdown.classList.contains("show")) {
        fetchNotifications();
    }
}

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

