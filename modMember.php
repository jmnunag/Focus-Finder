<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

$email = $_SESSION['email'];
$query = $conn->prepare("SELECT *, flagged FROM ff_users WHERE email = ?");
$query->bind_param("s", $email);
$query->execute();
$result = $query->get_result();

if (!$result) {
    die("Query failed: " . $conn->error);
}

$user = $result->fetch_assoc();

if ($user['flagged']) {
    $_SESSION['flagged'] = true;
    echo '<div id="flaggedAlert" class="alert alert-warning popup-notification">
            Your account has been flagged. Some features may be restricted.
          </div>';
} else {
    $_SESSION['flagged'] = false;
}

function createNotification($conn, $user_id, $message, $link) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $message, $link);
    $stmt->execute();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_plan'])) {
        $plan_id = $_POST['plan_id'];
        $price = $_POST['price'];
        $features = $_POST['features'];
        
        $stmt = $conn->prepare("UPDATE membership_plans SET price = ?, features = ? WHERE id = ?");
        $stmt->bind_param("dsi", $price, $features, $plan_id);
        $stmt->execute();
    }
}

$stats_query = "
    SELECT 
        mp.name as plan_name,
        mp.type,
        COUNT(DISTINCT s.user_email) as total_subscribers,
        COALESCE(SUM(s.amount_paid), 0) as total_revenue
    FROM membership_plans mp
    LEFT JOIN subscriptions s ON mp.id = s.plan_id AND s.payment_status = 'completed'
    WHERE mp.name NOT LIKE 'Basic%'
    GROUP BY mp.id, mp.name, mp.type
    ORDER BY mp.type, mp.name
";

$stats = $conn->query($stats_query);

if (!$stats) {
    echo "Query error: " . $conn->error;
    die();
}

if ($stats->num_rows === 0) {
    echo "<div class='stat-card'><p>No premium subscription data available</p></div>";
}

$plans = $conn->query("SELECT * FROM membership_plans");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/x-icon" href="images/FFicon.ico">
    <title>Focus Finder</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .admin-panel {
    max-width: 1200px;
    margin: 40px auto;
    padding: 20px;
}

.admin-panel h1 {
    text-align: center;
    color: #333;
    margin-bottom: 40px;
}

.stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
    cursor: pointer;
    transition: transform 0.2s;
}

.stat-card h3 {
    color: #333;
    margin-bottom: 15px;
}

.stat-card p {
    color: #666;
    margin: 10px 0;
}

.plans-container {
    display: flex;
    flex-direction: column;
    gap: 30px;
    margin-top: 30px;
}

.plans {
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
}

.plans-wrapper {
    display: flex;
    gap: 20px;
    overflow-x: auto;
    padding: 10px 0;
}

.plans h2 {
    color: #333;
    margin-bottom: 30px;
    text-align: center;
}

.plan-form {
    flex: 0 0 300px;
    border: 1px solid #eee;
    padding: 20px;
    border-radius: 8px;
    background: white;
}

.plan-form h3 {
    color: #333;
    margin-bottom: 15px;
}

.plan-form label {
    display: block;
    margin: 15px 0 5px;
}

.plan-form input[type="number"],
.plan-form textarea {
    width: 93%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-bottom: 10px;
}

.plan-form textarea {
    height: 100px;
    resize: vertical;
}

.plan-form button {
    background: #5db971;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    transition: background 0.3s ease;
}

.plan-form button:hover {
    background: #45a049;
}

.plan-form.basic {
    background: #f9f9f9;
}

@media screen and (max-width: 768px) {
    .plans-wrapper {
        flex-direction: column;
    }
    
    .plan-form {
        flex: 1;
        width: 100%;
    }
}

.plan-form.basic input,
.plan-form.basic textarea {
    background: #eee;
    cursor: not-allowed;
}

.plan-form.basic button {
    display: none;
}


.stat-card:hover {
    transform: translateY(-5px);
}

.modal {
    display: none;
    position: fixed;
    z-index: 1;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.4);
}

.modal-content {
    background-color: #fefefe;
    margin: 15% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 80%;
    max-width: 600px;
    border-radius: 10px;
}

.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: black;
}

.subscriber-item {
    padding: 10px;
    margin: 5px 0;
    border-bottom: 1px solid #eee;
}

.subscriber-item:last-child {
    border-bottom: none;
}
    </style>
</head>
<body>
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
    <div class="admin-panel">
        <h1>Membership Management</h1>
        
        <section class="stats">
            <h2>Subscription Statistics</h2>
            <?php while($stat = $stats->fetch_assoc()): ?>
                <div class="stat-card">
                    <h3><?php echo htmlspecialchars($stat['plan_name']); ?></h3>
                    <p>Total Subscribers: <?php echo $stat['total_subscribers']; ?></p>
                    <p>Total Revenue: ₱<?php echo number_format($stat['total_revenue'], 2); ?></p>
                </div>
            <?php endwhile; ?>
        </section>

        <div class="plans-container">
            <section class="plans">
                <h2>User Plans</h2>
                <div class="plans-wrapper">
                    <?php 
                    $plans->data_seek(0);
                    while($plan = $plans->fetch_assoc()): 
                        if($plan['type'] === 'user'):
                    ?>
                        <form class="plan-form <?php echo strpos($plan['name'], 'Basic') !== false ? 'basic' : ''; ?>" method="POST">
                            <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                            <h3><?php echo htmlspecialchars($plan['name']); ?></h3>
                            
                            <?php if(strpos($plan['name'], 'Basic') !== false): ?>
                                <p>Price: Free</p>
                                <p>Features: <?php echo htmlspecialchars($plan['features']); ?></p>
                            <?php else: ?>
                                <label>
                                    Price:
                                    <input type="number" name="price" step="0.01" value="<?php echo $plan['price']; ?>">
                                </label>
                                <label>
                                    Features (comma-separated):
                                    <textarea name="features"><?php echo htmlspecialchars($plan['features']); ?></textarea>
                                </label>
                                <button type="submit" name="update_plan">Update Plan</button>
                            <?php endif; ?>
                        </form>
                    <?php 
                        endif;
                    endwhile; 
                    ?>
                </div>
            </section>

            <div id="subscriberModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Current Subscribers</h2>
        <div id="subscribersList"></div>
    </div>
</div>

            <section class="plans">
                <h2>Owner Plans</h2>
                <div class="plans-wrapper">
                    <?php 
                    $plans->data_seek(0);
                    while($plan = $plans->fetch_assoc()): 
                        if($plan['type'] === 'owner'):
                    ?>
                        <form class="plan-form <?php echo strpos($plan['name'], 'Basic') !== false ? 'basic' : ''; ?>" method="POST">
                            <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                            <h3><?php echo htmlspecialchars($plan['name']); ?></h3>
                            
                            <?php if(strpos($plan['name'], 'Basic') !== false): ?>
                                <p>Price: Free</p>
                                <p>Features: <?php echo htmlspecialchars($plan['features']); ?></p>
                            <?php else: ?>
                                <label>
                                    Price:
                                    <input type="number" name="price" step="0.01" value="<?php echo $plan['price']; ?>">
                                </label>
                                <label>
                                    Features (comma-separated):
                                    <textarea name="features"><?php echo htmlspecialchars($plan['features']); ?></textarea>
                                </label>
                                <button type="submit" name="update_plan">Update Plan</button>
                            <?php endif; ?>
                        </form>
                    <?php 
                        endif;
                    endwhile; 
                    ?>
                </div>
            </section>
        </div>
    </div>
</div>

    <script>
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

document.addEventListener('DOMContentLoaded', function() {
    const flaggedAlert = document.getElementById('flaggedAlert');
    if (flaggedAlert) {
        flaggedAlert.style.display = 'block';
        setTimeout(() => {
            flaggedAlert.style.display = 'none';
        }, 3000);
    }
});


document.querySelectorAll('.stat-card').forEach(card => {
    card.addEventListener('click', function() {
        const planName = this.querySelector('h3').textContent;
        fetchSubscribers(planName);
    });
});

function fetchSubscribers(planName) {
    fetch(`get_subscribers.php?plan=${encodeURIComponent(planName)}`)
        .then(response => response.json())
        .then(data => {
            const modal = document.getElementById('subscriberModal');
            const subscribersList = document.getElementById('subscribersList');
            subscribersList.innerHTML = '';
            
            data.forEach(subscriber => {
                const item = document.createElement('div');
                item.className = 'subscriber-item';
                item.innerHTML = `
                    <p><strong>Name:</strong> ${subscriber.fullname}</p>
                    <p><strong>Email:</strong> ${subscriber.email}</p>
                    <p><strong>Subscription End:</strong> ${subscriber.end_date}</p>
                `;
                subscribersList.appendChild(item);
            });
            
            modal.style.display = 'block';
        });
}

const modal = document.getElementById('subscriberModal');
const span = document.getElementsByClassName('close')[0];

span.onclick = function() {
    modal.style.display = 'none';
}

    </script>
</body>
</html>
