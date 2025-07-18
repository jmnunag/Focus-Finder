<?php
function getAverageRating($conn, $location_id) {
    $query = $conn->prepare("SELECT AVG(rating) as average_rating FROM reviews WHERE location_id = ?");
    $query->bind_param("i", $location_id);
    $query->execute();
    $result = $query->get_result();
    $row = $result->fetch_assoc();
    return number_format($row['average_rating'], 1); // Format the average rating to 1 decimal place
}
?>