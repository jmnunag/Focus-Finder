<?php
require_once "config.php";

header('Content-Type: application/json');

if (!$conn) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$sql = "SELECT jeep_routes.id, jeep_routes.route_name, route_points.latitude AS lat, route_points.longitude AS lng 
        FROM jeep_routes 
        INNER JOIN route_points ON jeep_routes.id = route_points.jeep_route_id 
        ORDER BY jeep_routes.route_name, route_points.point_order";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode(['error' => 'Query failed: ' . $conn->error]);
    exit;
}

$jeepRoutes = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $jeepRoutes[$row['route_name']][] = ['lat' => $row['lat'], 'lng' => $row['lng']];
    }
} else {
    echo json_encode([]);
    exit;
}

$conn->close();

echo json_encode($jeepRoutes);
?>