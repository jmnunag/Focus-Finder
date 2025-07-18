<?php
require_once "config.php";
header('Content-Type: application/json');

if(isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    $query = "SELECT * FROM shop_signup WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($row = $result->fetch_assoc()) {
        // Decode stored images JSON
        $row['images'] = json_decode($row['images']);
        echo json_encode($row);
    } else {
        echo json_encode(['error' => 'Application not found']);
    }
} else {
    echo json_encode(['error' => 'No ID provided']);
}
?>