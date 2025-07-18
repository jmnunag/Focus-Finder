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

$plans_query = $conn->prepare("SELECT * FROM membership_plans WHERE type = ?");
$user_type = $_SESSION['type'];
$plans_query->bind_param("s", $user_type);
$plans_query->execute();
$plans = $plans_query->get_result();
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
        .modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: white;
    margin: 15% auto;
    padding: 20px;
    border-radius: 10px;
    width: 80%;
    max-width: 500px;
    position: relative;
}

.close {
    position: absolute;
    right: 20px;
    top: 10px;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

#confirmInput {
    width: 100%;
    padding: 10px;
    margin: 10px 0;
    border: 1px solid #ddd;
    border-radius: 5px;
}

.error-message {
    color: red;
    margin: 10px 0;
    display: none;
}
.slider-container {
    width: 100%;
    padding: 20px 0;
}

.slider {
    background: #eee;
    height: 40px;
    border-radius: 20px;
    position: relative;
    cursor: pointer;
}

.slider-button {
    background: #5db971;
    width: 120px;
    height: 40px;
    border-radius: 20px;
    position: absolute;
    left: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    cursor: grab;
    transition: background-color 0.3s;
}

.slider-button.right {
    left: calc(100% - 120px);
    background: #1e4526;
}

.slider-button span {
    white-space: nowrap;
    font-size: 14px;
}

.membership-alert {
    position: fixed;
    padding: 15px 25px;
    background: #4CAF50;
    color: white;
    border-radius: 4px;
    display: flex;
    align-items: center;
    gap: 15px;
    z-index: 1000;
    animation: slideIn 0.5s ease;
}

.membership-success-message {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
    bottom: 20px;
    left: 20px;
}

.close-btn {
    background: none;
    border: none;
    color: white;
    font-size: 20px;
    cursor: pointer;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}
    </style>
</head>
<body>
    <div class="header">
        <nav>
            <a href="userHome.php"><img src="images/FFv1.png" class="logo"></a>
            <ul class="iw-links">
                <li><a href="userHome.php">Home</a></li>
                <li><a href="userAdvanced.php">Locations</a></li>
                <li><a href="userBoard.php">Board</a></li>
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
                    <?php if ($_SESSION['type'] === 'owner'): ?>
                    <a href="shopSettings.php" class="sub-menu-link">
                        <p>Shop Settings</p>
                        <span>></span>
                    </a>
                    <?php endif; ?>
                    <a href="userProfile.php" class="sub-menu-link">
                        <p>Profile</p>
                        <span>></span>
                    </a>
                    <?php if ($_SESSION['type'] === 'owner'): ?>
                        <a href="ownerMember.php" class="sub-menu-link">
                            <p>Establishment Membership</p>
                            <span>></span>
                        </a>
                    <?php else: ?>
                        <a href="userMember.php" class="sub-menu-link">
                            <p>User Membership</p>
                            <span>></span>
                        </a>
                    <?php endif; ?>
                    <a href="logout.php" class="sub-menu-link">
                        <p>Log Out</p>
                        <span>></span>
                    </a>
                </div>
            </div>
    <div class="clearfix"></div>
</div>

<div class="main-content">
    <div class="membership-plans">
    <?php while($plan = $plans->fetch_assoc()): ?>
        <div class="plan">
            <h2><?php echo htmlspecialchars($plan['name']); ?></h2>
            <?php if(explode(' ', $plan['name'])[0] === 'Basic'): ?>
                <p>Free</p>
            <?php else: ?>
                <p>₱<?php echo number_format($plan['price'], 2); ?>/Month</p>
            <?php endif; ?>
            <ul>
                <?php foreach(explode(',', $plan['features']) as $feature): ?>
                    <li><?php echo htmlspecialchars($feature); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php 
            $current_plan = ($user['subscription_type'] === 'free') ? 'Basic' : 'Premium';
            $plan_name = explode(' ', $plan['name'])[0];
            
            if($plan_name === $current_plan): 
        ?>
            <a href="#" class="btn current">Current Plan</a>
        <?php else: ?>
            <?php
                $is_downgrade = $current_plan === 'Premium' && $plan_name === 'Basic';
                $button_text = $is_downgrade ? 'Switch' : 'Upgrade';
            ?>
            <button class="btn" data-plan="<?php echo $plan['id']; ?>">
                <?php echo $button_text; ?>
            </button>
        <?php endif; ?>
        </div>
    <?php endwhile; ?>
    </div>

<div id="paymentModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Payment Confirmation</h2>
        <p>You are about to upgrade to <span id="planName"></span>.</p>
        <p>The amount of <span id="planPrice"></span> will be charged to your account.</p>
        <p>This subscription will be renewed monthly unless cancelled.</p>
        <p>Please type "CONFIRM" to proceed with the payment:</p>
        <input type="text" id="confirmInput" placeholder="Type CONFIRM here">
        <div id="errorMessage" class="error-message" style="display: none; color: red; margin: 10px 0;"></div>
        <p>If confirmed, slide to proceed:</p>
        <div class="slider-container">
            <div id="paymentSlider" class="slider">
                <div id="paymentSlideButton" class="slider-button payment-slider">
                    <span>Slide to confirm</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="unsubscribeModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Unsubscribing Premium</h2>
        <p>You are about to unsubscribe from the premium plan.</p>
        <p>Your premium features will remain active until the end of your current billing period.</p>
        <p>Slide to confirm unsubscribe:</p>
        <div class="slider-container">
            <div id="unsubscribeSlider" class="slider">
                <div id="unsubscribeSlideButton" class="slider-button unsubscribe-slider">
                    <span>Slide to confirm</span>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="membership-alert membership-success-message" id="membershipAlert" style="display: none;">
    <span id="membershipAlertMessage"></span>
    <button type="button" class="close-btn" onclick="closeMembershipAlert()">&times;</button>
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


document.querySelectorAll('.btn:not(.current)').forEach(button => {
    button.addEventListener('click', function(e) {
        if (!button.classList.contains('current')) {
            e.preventDefault();
            const planId = this.dataset.plan;
            const planName = this.closest('.plan').querySelector('h2').textContent;
            const isDowngrade = this.textContent.trim() === 'Switch';
            
            if (isDowngrade) {
                const modal = document.getElementById('unsubscribeModal');
                modal.style.display = 'block';
                modal.dataset.planId = planId;
            } else {
                const planPrice = this.closest('.plan').querySelector('p').textContent;
                document.getElementById('planName').textContent = planName;
                document.getElementById('planPrice').textContent = planPrice;
                document.getElementById('confirmInput').value = '';
                document.getElementById('errorMessage').style.display = 'none';
                
                const modal = document.getElementById('paymentModal');
                modal.style.display = 'block';
                modal.dataset.planId = planId;
            }
        }
    });
});

document.querySelectorAll('.close').forEach(closeBtn => {
    closeBtn.addEventListener('click', function() {
        this.closest('.modal').style.display = 'none';
        const slider = this.closest('.modal').querySelector('.slider-button');
        if (slider) {
            slider.classList.remove('right');
            slider.style.left = '0px';
        }
    });
});


let isPaymentDragging = false;
let isUnsubscribeDragging = false;
let paymentStartX, unsubscribeStartX;

const paymentSlider = document.querySelector('#paymentSlideButton');
const paymentSliderTrack = document.querySelector('#paymentSlider');

const unsubscribeSlider = document.querySelector('#unsubscribeSlideButton');
const unsubscribeSliderTrack = document.querySelector('#unsubscribeSlider');

function startPaymentDrag(e) {
    const confirmInput = document.getElementById('confirmInput').value;
    if (confirmInput !== 'CONFIRM') {
        document.getElementById('errorMessage').textContent = 'Please type "CONFIRM" first';
        document.getElementById('errorMessage').style.display = 'block';
        return;
    }
    isPaymentDragging = true;
    paymentStartX = e.clientX - paymentSlider.offsetLeft;
}

function closeMembershipAlert() {
    document.getElementById('membershipAlert').style.display = 'none';
}

function dragPayment(e) {
    if (!isPaymentDragging) return;
    e.preventDefault();
    const walk = e.clientX - paymentStartX;
    const maxWidth = paymentSliderTrack.offsetWidth - paymentSlider.offsetWidth;
    
    if (walk < 0) {
        paymentSlider.style.left = '0px';
    } else if (walk > maxWidth) {
        paymentSlider.classList.add('right');
        isPaymentDragging = false;
        const planId = document.getElementById('paymentModal').dataset.planId;
        fetch('process_member.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `plan=${planId}`
        })
        .then(response => response.json())
        .then(data => {
            const alertDiv = document.getElementById('membershipAlert');
            const alertMessage = document.getElementById('membershipAlertMessage');
            
            if (data.success) {
                alertMessage.textContent = 'Membership request submitted! Waiting for admin approval.';
                alertDiv.style.display = 'flex';
                alertDiv.style.backgroundColor = '#4CAF50';
                setTimeout(() => {
                    closeMembershipAlert();
                    window.location.reload();
                }, 3000);
            } else {
                alertMessage.textContent = data.message;
                alertDiv.style.display = 'flex';
                alertDiv.style.backgroundColor = '#f44336';
                setTimeout(() => {
                    closeMembershipAlert();
                }, 3000);
                paymentSlider.classList.remove('right');
                paymentSlider.style.left = '0px';
            }
        });
    } else {
        paymentSlider.style.left = `${walk}px`;
    }
}

function stopPaymentDrag() {
    isPaymentDragging = false;
    if (!paymentSlider.classList.contains('right')) {
        paymentSlider.style.left = '0px';
    }
}

function startUnsubscribeDrag(e) {
    isUnsubscribeDragging = true;
    unsubscribeStartX = e.clientX - unsubscribeSlider.offsetLeft;
}

function dragUnsubscribe(e) {
    if (!isUnsubscribeDragging) return;
    e.preventDefault();
    const walk = e.clientX - unsubscribeStartX;
    const maxWidth = unsubscribeSliderTrack.offsetWidth - unsubscribeSlider.offsetWidth;
    
    if (walk < 0) {
        unsubscribeSlider.style.left = '0px';
    } else if (walk > maxWidth) {
        unsubscribeSlider.classList.add('right');
        const planId = document.getElementById('unsubscribeModal').dataset.planId;
        window.location.href = 'upgrade.php?plan=' + planId;
        isUnsubscribeDragging = false;
    } else {
        unsubscribeSlider.style.left = `${walk}px`;
    }
}

function stopUnsubscribeDrag() {
    isUnsubscribeDragging = false;
    if (!unsubscribeSlider.classList.contains('right')) {
        unsubscribeSlider.style.left = '0px';
    }
}

if (paymentSlider && paymentSliderTrack) {
    paymentSlider.addEventListener('mousedown', startPaymentDrag);
    document.addEventListener('mousemove', dragPayment);
    document.addEventListener('mouseup', stopPaymentDrag);
}

if (unsubscribeSlider && unsubscribeSliderTrack) {
    unsubscribeSlider.addEventListener('mousedown', startUnsubscribeDrag);
    document.addEventListener('mousemove', dragUnsubscribe);
    document.addEventListener('mouseup', stopUnsubscribeDrag);
}
    </script>
</body>
</html>
