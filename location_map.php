<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Location Map</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
</head>
<body>
    <div id="map"></div>

    <script>
    var studyLocation = {
        lat: <?php echo $_SESSION['initial_lat']; ?>,
        lng: <?php echo $_SESSION['initial_lng']; ?>,
        name: "<?php echo addslashes($location['name']); ?>"
    };

    var map = L.map('map').setView([studyLocation.lat, studyLocation.lng], 15);
    var userLocationMarker;
    var control;
    var markers = [];
    window.userLocation = null;

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    var studyMarker = L.marker([studyLocation.lat, studyLocation.lng])
        .addTo(map)
        .bindPopup(studyLocation.name)
        .openPopup();

    function getRouteBounds(userLocation, studyLocation) {
        var bounds = L.latLngBounds(
            [userLocation.lat, userLocation.lon],
            [studyLocation.lat, studyLocation.lng]
        );
        return bounds.pad(0.2);
    }

    function centerOnRoute(userLoc, studyLoc) {
        if (!userLoc) return;
        var routeBounds = getRouteBounds(userLoc, studyLoc);
        map.fitBounds(routeBounds);
    }

    function onLocationFound(e) {
        var userLat = e.latlng.lat;
        var userLng = e.latlng.lng;
        userLocation = { lat: userLat, lon: userLng };
        
        if (userLocationMarker) {
            map.removeLayer(userLocationMarker);
        }
        
        userLocationMarker = L.marker([userLat, userLng])
            .addTo(map)
            .bindPopup('You are here');

        if (document.getElementById('map-container').classList.contains('full-screen-map')) {
            centerOnRoute(userLocation, studyLocation);
        }
    }

    function onLocationError(e) {
        console.error("Error getting location:", e.message);
    }

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

    map.on('locationfound', onLocationFound);
    map.on('locationerror', onLocationError);
    window.addEventListener('resize', function() {
        map.invalidateSize();
    });

    window.map = map;
    window.studyLocation = studyLocation;
    window.showMarkers = showMarkers;
    window.hideMarkers = hideMarkers;
    window.userLocationMarker = userLocationMarker;
    window.centerOnRoute = centerOnRoute;
    </script>
</body>
</html>