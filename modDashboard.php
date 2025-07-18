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

$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM ff_users) as total_users,
        (SELECT COUNT(*) FROM ff_users WHERE type='user' AND subscription_type='premium') as premium_users,
        (SELECT COUNT(*) FROM ff_users WHERE type='owner' AND subscription_type='premium') as premium_owners";

$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

$users_query = "SELECT fullname, email, type, subscription_type FROM ff_users ORDER BY type, fullname";
$users_result = $conn->query($users_query);

if (!$users_result) {
    die("Query failed: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/x-icon" href="images/FFicon.ico">
    <title>Focus Finder</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
  .charts-wrapper {
    display: flex;
    justify-content: center;
    gap: 30px;
    flex-wrap: wrap;
}

.chart-container {
    width: 70%;
    min-width: 500px;
    max-width: 800px;
    margin: 20px;
    padding: 20px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 0 20px rgba(0,0,0,0.1);
}
.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    padding: 20px;
    max-width: 1600px;
    margin: 0 auto;
}

.users-container {
    background: white;
    border-radius: 10px;
    box-shadow: 0 0 20px rgba(0,0,0,0.1);
    padding: 20px;
}

.users-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

#userSearch {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    width: 200px;
}

.users-list {
    max-height: 600px;
    overflow-y: auto;
}

.user-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid #eee;
}

.user-card:hover {
    background: #f9f9f9;
}

.user-info {
    margin: 0 5px 0 0;
}

.user-info h3 {
    margin: 0 12px 0 0;
    font-size: 16px;
    color: #333;
}

.user-info p {
    margin: 0 0 0 12px;
    color: #666;
    font-size: 14px;
}

.user-status {
    display: flex;
    gap: 10px;
}

.type-badge, .subscription-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}

.type-badge.user { background: #e3f2fd; color: #1976d2; }
.type-badge.owner { background: #e8f5e9; color: #2e7d32; }
.subscription-badge.premium { background: #fff3e0; color: #f57c00; }
.subscription-badge.free { background: #f5f5f5; color: #616161; }
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
    <div class="dashboard-grid">
        <div class="chart-container">
            <canvas id="userDistribution"></canvas>
        </div>
        <div class="users-container">
            <div class="users-header">
                <h2>User List</h2>
                <input type="text" id="userSearch" placeholder="Search users...">
            </div>
            <div class="users-list">
                <?php while($user = $users_result->fetch_assoc()): ?>
                    <div class="user-card">
                        <div class="user-info">
                            <h3><?php echo htmlspecialchars($user['fullname']); ?></h3>
                            <p><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>
                        <div class="user-status">
                            <span class="type-badge <?php echo $user['type']; ?>"><?php echo ucfirst($user['type']); ?></span>
                            <span class="subscription-badge <?php echo $user['subscription_type']; ?>"><?php echo ucfirst($user['subscription_type']); ?></span>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

    <script>
        function toggleMenu() {
            let subMenu = document.getElementById("subMenu");
            subMenu.classList.toggle("open-menu");
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

const ctx = document.getElementById('userDistribution').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Basic Users', 'Premium Users', 'Premium Owners'],
        datasets: [{
            data: [
                <?php echo $stats['total_users']; ?>,
                <?php echo $stats['premium_users']; ?>,
                <?php echo $stats['premium_owners']; ?>
            ],
            backgroundColor: [
                'rgba(93, 185, 113, 0.3)',
                'rgba(30, 69, 38, 0.5)',
                'rgba(30, 69, 38, 0.8)'
            ],
            borderColor: [
                'rgba(93, 185, 113, 1)',
                'rgba(30, 69, 38, 1)',
                'rgba(30, 69, 38, 1)'
            ],
            borderWidth: 1
        }]
    },
        options: {
        responsive: true,
        cutout: '60%',
        plugins: {
            title: {
                display: true,
                text: 'User Distribution Overview',
                font: { size: 18 }
            },
            legend: {
                position: 'right',
                labels: {
                    generateLabels: function(chart) {
                        const data = chart.data;
                        const total = data.datasets[0].data[0];
                        const premium = data.datasets[0].data[1];
                        const premiumOwners = data.datasets[0].data[2];
                        
                        return [
                            { text: `Total Users: ${total}`, fillStyle: data.datasets[0].backgroundColor[0] },
                            { text: `Active Premium Users: ${premium}`, fillStyle: data.datasets[0].backgroundColor[1] },
                            { text: `Active Premium Owners: ${premiumOwners}`, fillStyle: data.datasets[0].backgroundColor[2] }
                        ];
                    }
                }
            }
        }
    }
});

document.getElementById('userSearch').addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    document.querySelectorAll('.user-card').forEach(card => {
        const text = card.textContent.toLowerCase();
        card.style.display = text.includes(search) ? '' : 'none';
    });
});
</script>
</body>
</html>