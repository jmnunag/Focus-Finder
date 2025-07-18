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
    <div class="index-text1">Find the Nearest Study</div>
    <div class="index-text2">Location</div>
    <div class="index-text3">Explore and Discover the nearest study locations for your needs.</div>

    <div class="search-form-box">
    <input type="text" id="Flocation" class="search-input" placeholder="Enter your location">
    <button onclick="searchUserLocation()" class="btn">Search Location</button>
    <button onclick="useCurrentLocation()" class="btn">Use Current Location</button>
    </div>

    <div class="form-box">
      <!-- The button will now trigger the map expansion and search function in home_map.php -->
      <button type="button" class="location-btn" onclick="expandMap()">Get Direction</button>
    </div>


    <!-- Center the map-box using a flex container -->
    <div style="display: flex; justify-content: center;">
      <div class="map-box" id="map-container">
        <?php include 'home_map.php'; ?>
        <!-- Close button for full-screen map -->
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

function expandMap() {
    document.querySelector('nav').classList.add('hidden');

    // Store current zoom and center before expanding
    previousZoom = map.getZoom();
    previousCenter = map.getCenter();

    // Toggle full-screen map
    var mapContainer = document.getElementById('map-container');
    mapContainer.classList.add('full-screen-map');

    // Show buttons
    document.getElementById('close-button').style.display = 'block';
    document.getElementById('toggleJeepRoutes').style.display = 'block';
    document.getElementById('toggleRoutingControl').style.display = 'block';

    // Call the function to show markers
    if (typeof showMarkers === 'function') {
        showMarkers();
    }

    // Invalidate the map size
    if (typeof map !== 'undefined') {
        map.invalidateSize();
    }

    // Only set view based on existing locations
    if (userLocation) {
        // If we have a user location from previous input or geolocation
        map.setView([userLocation.lat, userLocation.lon], 16);
    } else {
        map.setView(previousCenter, 16);
    }
    
    // Check if there's a selected destination
    if (selectedDestination) {
        // Restore the previous route
        getDirections(selectedDestination.lat, selectedDestination.lng);
    } else {
        // Only show the closest route if no destination was previously selected
        if (typeof showClosestRoute === 'function') {
            showClosestRoute();
        }
    }

    // Show the routing control toggle button
    var routingToggle = document.getElementById('toggleRoutingControl');
    routingToggle.style.display = 'block';

    // Initially hide the routing control
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
  
  // Only update view if no user input location exists
  if (!userLocation) {
    getUserLocation(function(userLatLng) {
      if (userLatLng) {
        map.setView(userLatLng, previousZoom);
      } else {
        map.setView(previousCenter, previousZoom);
      }
    });
  } else {
    // Keep the view centered on the user's inputted location
    map.setView([userLocation.lat, userLocation.lon], previousZoom);
  }

  // Hide the markers but don't clear the selected destination
  if (typeof hideMarkers === 'function') {
      hideMarkers();
  }

  // Remove jeep routes if they're visible
  if (jeepRoutesVisible) {
      removeJeepRoutes();
      jeepRoutesVisible = false;
      document.getElementById('toggleJeepRoutes').textContent = 'Show Jeep Routes';
  }
  
  // Toggle full-screen map
  var mapContainer = document.getElementById('map-container');
  mapContainer.classList.remove('full-screen-map');

  // Hide the close button
  var closeButton = document.getElementById('close-button');
  closeButton.style.display = 'none';

  // Hide the "Show Jeep Routes" button
  var jeepRoutesButton = document.getElementById('toggleJeepRoutes');
  jeepRoutesButton.style.display = 'none';

  // Remove the routing control if it exists
  if (typeof control !== 'undefined' && control) {
    map.removeControl(control);
    control = null; // Reset the control variable
  }

  // Invalidate the map size to ensure it resizes correctly
  if (typeof map !== 'undefined') {
    map.invalidateSize();
  }

  // Hide the routing control toggle button
  var routingToggle = document.getElementById('toggleRoutingControl');
    routingToggle.style.display = 'none';

    // Hide any visible routing controls
    var routingControls = document.getElementsByClassName('leaflet-routing-container');
    for (var i = 0; i < routingControls.length; i++) {
        routingControls[i].style.display = 'none';
    }
}

// Add event listener for the toggle button
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

document.getElementById('routeControls').style.display = 'none';

document.getElementById('toggleRouteControls').addEventListener('click', function() {
    var routeControlsPanel = document.getElementById('routeControls');
    if (routeControlsPanel.style.display === 'none') {
        routeControlsPanel.style.display = 'block';
    } else {
        routeControlsPanel.style.display = 'none';
    }
});


// Ensure the routing control is removed when the page is refreshed
window.addEventListener('load', function() {
  if (typeof control !== 'undefined' && control) {
    map.removeControl(control);
    control = null; // Reset the control variable
  }
});

var jeepRouteLayers = []; // Store the polylines for each jeepney route
var routeControls = {};

// Function to fetch and display jeepney routes
function loadJeepRoutes() {
    console.log('loadJeepRoutes function called');
    
    // Show route controls container
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

            // Create controls for each route
            for (var routeName in data) {
                if (data.hasOwnProperty(routeName)) {
                    const color = colorMapping[routeName] || 'green';
                    
                    // Create route control item
                    const routeItem = document.createElement('div');
                    routeItem.className = 'route-item';
                    
                    // Create color indicator
                    const colorIndicator = document.createElement('div');
                    colorIndicator.className = 'route-color';
                    colorIndicator.style.backgroundColor = color;
                    
                    // Create checkbox
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.className = 'route-checkbox';
                    checkbox.checked = true;
                    
                    // Create label
                    const label = document.createElement('span');
                    label.textContent = routeName;
                    
                    // Add elements to route item
                    routeItem.appendChild(checkbox);
                    routeItem.appendChild(colorIndicator);
                    routeItem.appendChild(label);
                    controlsContainer.appendChild(routeItem);
                    
                    // Create polyline
                    const latLngs = data[routeName].map(point => [point.lat, point.lng]);
                    const polyline = L.polyline(latLngs, { color: color }).bindPopup(routeName);
                    polyline.addTo(map);
                    jeepRouteLayers.push(polyline);
                    
                    // Store reference and add toggle handler
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

// Modify removeJeepRoutes to also hide controls
function removeJeepRoutes() {
    jeepRouteLayers.forEach(layer => {
        if (map.hasLayer(layer)) {
            map.removeLayer(layer);
        }
    });
    jeepRouteLayers = [];
    document.getElementById('routeControls').style.display = 'none';
    document.getElementById('toggleRouteControls').style.display = 'none'; // Hide the toggle button
}

// Add to minimizeMap function
if (document.getElementById('routeControls').style.display === 'block') {
    document.getElementById('routeControls').style.display = 'none';
}

var jeepRoutesVisible = false;

// Function to toggle jeepney routes
document.getElementById('toggleJeepRoutes').addEventListener('click', function() {
    if (!jeepRoutesVisible) {
        loadJeepRoutes(); // Load and show the routes
        this.textContent = 'Hide Jeep Routes';
        document.getElementById('toggleRouteControls').style.display = 'block'; // Show the toggle button
    } else {
        removeJeepRoutes(); // Remove the routes
        this.textContent = 'Show Jeep Routes';
        document.getElementById('toggleRouteControls').style.display = 'none'; // Hide the toggle button
    }
    jeepRoutesVisible = !jeepRoutesVisible;
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

