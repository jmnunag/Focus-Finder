<?php
require_once "config.php"; // Include your database configuration file

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Fetch all users' passwords
$sql = "SELECT id, password FROM ff_users";
$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $user_id = $row['id'];
        $plain_password = $row['password'];

        // Check if the password is already hashed
        if (password_get_info($plain_password)['algo'] == 0) {
            // Hash the password
            $hashed_password = password_hash($plain_password, PASSWORD_BCRYPT);

            // Update the database with the hashed password
            $update_sql = "UPDATE ff_users SET password='$hashed_password' WHERE id='$user_id'";
            if (mysqli_query($conn, $update_sql)) {
                echo "Password for user ID $user_id has been hashed successfully.<br>";
            } else {
                echo "Error updating password for user ID $user_id: " . mysqli_error($conn) . "<br>";
            }
        } else {
            echo "Password for user ID $user_id is already hashed.<br>";
        }
    }
} else {
    echo "Error fetching users: " . mysqli_error($conn);
}

mysqli_close($conn);
?>