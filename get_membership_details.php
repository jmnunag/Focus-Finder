<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['type']) || $_SESSION['type'] !== 'moderator') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'Missing request ID']);
    exit;
}

$id = $_GET['id'];

try {
    $query = $conn->prepare("
        SELECT 
            ms.*,
            mp.name as plan_name,
            u.fullname,
            u.email as user_email,
            u.profile_picture,
            u.contact
        FROM membership_signup ms
        JOIN ff_users u ON ms.user_email = u.email
        JOIN membership_plans mp ON ms.plan_id = mp.id
        WHERE ms.id = ?
    ");
    
    $query->bind_param("i", $id);
    $query->execute();
    $result = $query->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    } else {
        echo json_encode(['error' => 'Request not found']);
    }

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>