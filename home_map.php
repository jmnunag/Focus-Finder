<?php
require_once "config.php";

$sql = "SELECT name, latitude, longitude, image_url, time, min_price, max_price FROM study_locations WHERE latitude IS NOT NULL AND longitude IS NOT NULL";
$result = $conn->query($sql);

$locations = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $locations[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Map</title>

  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
  <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
  <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
</head>
<body>

<div id="map"></div>

  <script>
    var map = L.map('map').setView([15.145, 120.588], 13);
    var userLocationMarker;
    var control;
    var markers = [];
    var selectedDestination = null;
    let isLocating = false;

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    var locations = <?php echo json_encode($locations); ?>;

    locations.forEach(function(location) {
      var lat = location.latitude;
      var lng = location.longitude;
      var name = location.name;

      var popupContent = `
        <div class="custom-popup">
            <img src="${location.image_url}" alt="${name}" class="popup-image">
            <div class="popup-content">
                <h3>${name}</h3>
                ${(location.min_price > 0 || location.max_price > 0) ? 
                    `<p><strong>Price Range:</strong> ₱${parseFloat(location.min_price).toFixed(2)} - ₱${parseFloat(location.max_price).toFixed(2)}</p>`
                    : ''
                }
                <p><i class="fas fa-clock"></i> ${location.time}</p>
                <button class="get-directions-btn" onclick="getDirections(${lat}, ${lng})">
                    <i class="fas fa-directions"></i> Get Directions
                </button>
            </div>
        </div>
    `;

      var marker = L.marker([lat, lng])
          .bindPopup(popupContent, {
              maxWidth: 300,
              className: 'custom-popup-wrapper'
          });
      
      markers.push(marker);
  });

    function showMarkers() {
      markers.forEach(function(marker) {
        marker.addTo(map);
      });
    }

    function hideMarkers() {
      markers.forEach(function(marker) {
        map.removeLayer(marker);
      });
    }

    function onLocationFound(e) {
    var userLat = e.latlng.lat;
    var userLng = e.latlng.lng;

    userLocation = { lat: userLat, lon: userLng };

    if (userLocationMarker) {
        map.removeLayer(userLocationMarker);
    }

    userLocationMarker = L.marker([userLat, userLng]).addTo(map)
        .bindPopup('You are here')
        .openPopup();

    map.setView([userLat, userLng], 13);
}

map.on('locationfound', onLocationFound);
map.on('locationerror', onLocationError);

    var userLocation = null;

function updateDirectionsButton(destinationLat, destinationLng, show) {
    markers.forEach(function(marker) {
        var markerLatLng = marker.getLatLng();
        if (Math.abs(markerLatLng.lat - destinationLat) < 1e-6 && 
            Math.abs(markerLatLng.lng - destinationLng) < 1e-6) {
            var popup = marker.getPopup();
            var container = popup.getContent();
            
            if (typeof container === 'string') {
                var div = document.createElement('div');
                div.innerHTML = container;
                container = div;
            }
            
            var button = container.querySelector('.get-directions-btn');
            if (button) {
                button.style.display = show ? 'block' : 'none';
            }
            
            marker.setPopupContent(container);
        }
    });
}

function useCurrentLocation() {
    if (isLocating) return;
    
    document.getElementById('Flocation').value = '';
    
    if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser');
        return;
    }

    isLocating = true;
    
    if (userLocationMarker) {
        map.removeLayer(userLocationMarker);
    }

    if (control) {
        map.removeControl(control);
        markers.forEach(function(marker) {
            updateDirectionsButton(marker.getLatLng().lat, marker.getLatLng().lng, true);
        });
    }

    map.off('locationfound');
    map.off('locationerror');

    map.on('locationfound', function(e) {
        isLocating = false;
        onLocationFound(e);
        userLocation = {
            lat: e.latlng.lat,
            lon: e.latlng.lng
        };
    });

    map.on('locationerror', function(e) {
        isLocating = false;
        
        switch(e.code) {
            case 1:
                alert('Location not found. Please allow geolocation');
                break;
            case 2:
                alert('Location information is unavailable');
                break;
            case 3:
                alert('Location request timed out');
                break;
            default:
                alert('Location not found. Please try again');
        }
    });

    map.locate({
        setView: true,
        maxZoom: 16,
        timeout: 5000,
        enableHighAccuracy: true
    });
}

function searchUserLocation() {
    var userInput = document.getElementById('Flocation').value;

    if (!userInput) {
        alert('Please enter a location');
        return;
    }

    var nominatimUrl = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(userInput)}`;

    fetch(nominatimUrl)
        .then(response => response.json())
        .then(data => {
            if (data.length > 0) {
                var lat = parseFloat(data[0].lat);
                var lon = parseFloat(data[0].lon);

                userLocation = { lat: lat, lon: lon };

                map.setView([lat, lon], 13);

                if (userLocationMarker) {
                    map.removeLayer(userLocationMarker);
                }

                userLocationMarker = L.marker([lat, lon]).addTo(map)
                    .bindPopup('Your Input Location')
                    .openPopup();

            } else {
                alert('Location not found! Please try again.');
            }
        })
        .catch(error => {
            console.error('Error fetching location:', error);
            alert('Error searching for location. Please try again.');
        });
}

function getDirections(destinationLat, destinationLng) {
    if (!userLocation) {
        console.error('Location not found. Please allow geolocation or enter a location.');
        return;
    }

    selectedDestination = {
        lat: destinationLat,
        lng: destinationLng
    };

    if (control) {
        map.removeControl(control);
        markers.forEach(function(marker) {
            updateDirectionsButton(marker.getLatLng().lat, marker.getLatLng().lng, true);
        });
    }

    updateDirectionsButton(destinationLat, destinationLng, false);

    control = L.Routing.control({
        waypoints: [
            L.latLng(userLocation.lat, userLocation.lon),
            L.latLng(destinationLat, destinationLng)
        ],
        routeWhileDragging: true,
        lineOptions: {
            styles: [
                { color: 'blue', opacity: 0.6, weight: 4 }
            ]
        },
        createMarker: function() { return null; } 
    }).addTo(map);

    var bounds = L.latLngBounds([
        [userLocation.lat, userLocation.lon],
        [destinationLat, destinationLng]
    ]);
    map.fitBounds(bounds, { padding: [50, 50] });

    setTimeout(function() {
        var routingControls = document.getElementsByClassName('leaflet-routing-container');
        for (var i = 0; i < routingControls.length; i++) {
            routingControls[i].style.display = 'none';
            routingControls[i].classList.add('routing-control');
        }
    }, 100);

    markers.forEach(function(marker) {
        var markerLatLng = marker.getLatLng();
        if (Math.abs(markerLatLng.lat - destinationLat) < 1e-6 && Math.abs(markerLatLng.lng - destinationLng) < 1e-6) {
            marker.setZIndexOffset(1000);
            marker.openPopup();
        }
    });
}

function clearSelectedRoute() {
    selectedDestination = null;
    if (control) {
        map.removeControl(control);
        markers.forEach(function(marker) {
            updateDirectionsButton(marker.getLatLng().lat, marker.getLatLng().lng, true);
        });
    }
}

    function searchLocation() {
      var userInput = document.getElementById('Flocation').value;

      var nominatimUrl = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(userInput)}`;

      fetch(nominatimUrl)
        .then(response => response.json())
        .then(data => {
          if (data.length > 0) {
            var lat = data[0].lat;
            var lon = data[0].lon;

            userLocation = { lat: lat, lon: lon };

            map.setView([lat, lon], 13);

            if (userLocationMarker) {
              map.removeLayer(userLocationMarker);
            }

            userLocationMarker = L.marker([lat, lon]).addTo(map)
              .bindPopup('Your Input Location')
              .openPopup();

            var closestLocation = null;
            var minDistance = Infinity;

            locations.forEach(function(location) {
              var distance = calculateDistance(lat, lon, location.latitude, location.longitude);

              if (distance < minDistance) {
                minDistance = distance;
                closestLocation = location;
              }
            });

            if (closestLocation) {
              getDirections(closestLocation.latitude, closestLocation.longitude);
            }
          } else {
            console.error('Location not found! Please try again.');
          }
        })
        .catch(error => {
          console.error('Error fetching location:', error);
        });
    }

    function showClosestRoute() {
    if (!userLocation) {
        alert('Location not found. Please input a location or allow geolocation.');
        return;
    }

    if (control) {
        map.removeControl(control);
        markers.forEach(function(marker) {
            updateDirectionsButton(marker.getLatLng().lat, marker.getLatLng().lng, true);
        });
    }

    var closestLocation = null;
    var closestDistance = Infinity;

    locations.forEach(function(location) {
        var distance = map.distance([userLocation.lat, userLocation.lon], [location.latitude, location.longitude]);
        if (distance < closestDistance) {
            closestDistance = distance;
            closestLocation = location;
        }
    });

    if (closestLocation) {
        updateDirectionsButton(closestLocation.latitude, closestLocation.longitude, false);
        if (control) {
            map.removeControl(control);
        }

        control = L.Routing.control({
            waypoints: [
                L.latLng(userLocation.lat, userLocation.lon),
                L.latLng(closestLocation.latitude, closestLocation.longitude)
            ],
            routeWhileDragging: true,
            lineOptions: {
                styles: [
                    { color: 'blue', opacity: 0.6, weight: 4 }
                ]
            },
            createMarker: function() { return null; }
        }).addTo(map);

        var bounds = L.latLngBounds([
            [userLocation.lat, userLocation.lon],
            [closestLocation.latitude, closestLocation.longitude]
        ]);
        map.fitBounds(bounds, { padding: [50, 50] });

        setTimeout(function() {
            var routingControls = document.getElementsByClassName('leaflet-routing-container');
            for (var i = 0; i < routingControls.length; i++) {
                routingControls[i].style.display = 'none';
                routingControls[i].classList.add('routing-control');
            }
        }, 100);

        markers.forEach(function(marker) {
            var markerLatLng = marker.getLatLng();
            if (Math.abs(markerLatLng.lat - closestLocation.latitude) < 1e-6 && 
                Math.abs(markerLatLng.lng - closestLocation.longitude) < 1e-6) {
                marker.setZIndexOffset(1000);
                marker.openPopup();
            }
        });
    }
}

    function calculateDistance(lat1, lon1, lat2, lon2) {
      var R = 6371;
      var dLat = (lat2 - lat1) * Math.PI / 180;
      var dLon = (lon2 - lon1) * Math.PI / 180;
      var a =
        0.5 - Math.cos(dLat) / 2 +
        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
        (1 - Math.cos(dLon)) / 2;

      return R * 2 * Math.asin(Math.sqrt(a));
    }

    window.addEventListener('resize', function() {
      map.invalidateSize();
    });

  </script>
</body>
</html>