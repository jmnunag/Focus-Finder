<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['email']) || $_SESSION['type'] !== 'moderator') {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $id = $_POST['id'];
    $type = $_POST['type'];
    $status = $_POST['status'];
    
    if ($type == 'report') {
        $query = "UPDATE reports SET status = ? WHERE id = ?";
    } else {
        $query = "UPDATE location_suggestions SET status = ? WHERE id = ?";
    }
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "si", $status, $id);
    mysqli_stmt_execute($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/x-icon" href="images/FFicon.ico">
    <title>Focus Finder - Admin Mailbox</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <style>
        .mailbox-container {
            width: 1200px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        @media screen and (min-width: 320px) {
            .mailbox-container{
                width: 90%;
            }
        }

        @media screen and (min-width: 375px) {
            .mailbox-container{
                width: 90%;
            }
        }

        @media screen and (min-width: 480px) {
            .mailbox-container{
                width: 90%;
            }
        }

        @media screen and (min-width: 768px) {
            .mailbox-container{
                width: 90%;
            }
        }

        @media screen and (min-width: 1080px) {
            .mailbox-container{
                width: 90%;
            }
        }

        @media screen and (min-width: 1280px) {
            .mailbox-container{
                width: 1000px;
            }
        }

        @media screen and (min-width: 1440px) {
            .mailbox-container{
                width: 1200px;
            }
        }

        .mail-item {
            background: white;
            border-radius: 8px;
            margin: 8px 0;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            cursor: pointer;
        }

        .mail-item:hover {
            box-shadow: 0 3px 6px rgba(0,0,0,0.16);
            transform: translateY(-1px);
        }

        .mail-content {
            flex-grow: 1;
        }

        .mail-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .mail-sender {
            font-weight: 600;
            color: #202124;
        }

        .mail-date {
            color: #5f6368;
            font-size: 0.9em;
        }

        .mail-subject {
            color: #202124;
            margin-bottom: 4px;
        }

        .mail-preview {
            color: #5f6368;
            font-size: 0.9em;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .status-label {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8em;
            margin-left: 12px;
        }

        .status-pending { background: #fff0c0; color: #963; }
        .status-reviewed { background: #c8e6c9; color: #2e7d32; }
        .status-dismissed { background: #ffcdd2; color: #c62828; }

        .floating-action-button {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #1a73e8;
            color: white;
            border: none;
            box-shadow: 0 3px 6px rgba(0,0,0,0.16);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .floating-action-button:hover {
            background: #1557b0;
            box-shadow: 0 6px 12px rgba(0,0,0,0.23);
        }

        .tab-container {
            margin: 20px 0;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .tab-button {
            padding: 10px 20px;
            border: none;
            background: #f0f0f0;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .tab-button.active {
            background: #4CAF50;
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .action-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            background: #4CAF50;
            color: white;
            transition: background 0.3s ease;
        }

        .action-btn:hover {
            background: #45a049;
        }

        /* Modal styles */
        .mail-modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgb(0,0,0);
            background-color: rgba(0,0,0,0.4);
            padding-top: 60px;
        }

        .mail-modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        .mail-modal-close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .mail-modal-close:hover,
        .mail-modal-close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

        .mail-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .mail-modal-body {
            margin-top: 10px;
        }

        .mail-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .mail-modal-footer a {
            text-decoration: none;
            color: #1a73e8;
        }

        .mail-modal-footer a:hover {
            text-decoration: underline;
        }

        .status-flagged { 
            background: #ffb74d; 
            color: #e65100; 
        }

        .status-deleted { 
            background: #ef5350; 
            color: #b71c1c; 
        }

        .application-map {
            height: 300px;
            width: 100%;
            margin: 10px 0;
            border-radius: 4px;
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
    <h1>Admin Mailbox</h1>
    
    <div class="tab-container">
    <div class="tabs">
        <button class="tab-button active" onclick="showTab('reports')">Reports</button>
        <button class="tab-button" onclick="showTab('appeals')">Appeals</button>
        <button class="tab-button" onclick="showTab('applications')">Applications</button>
        <button class="tab-button" onclick="showTab('memberships')">Memberships</button>
    </div>

        <div class="mailbox-container">
            <div id="reports" class="tab-content active">
                <?php
                $query = "SELECT * FROM reports ORDER BY report_date DESC";
                $result = mysqli_query($conn, $query);
                while ($row = mysqli_fetch_assoc($result)) {
                    ?>
                    <div class="mail-item" onclick="openModal('report', <?php echo $row['id']; ?>)">
                        <div class="mail-content">
                            <div class="mail-header">
                                <span class="mail-sender"><?php echo htmlspecialchars($row['reporter_email']); ?></span>
                                <span class="mail-date"><?php echo date('M d', strtotime($row['report_date'])); ?></span>
                            </div>
                            <div class="mail-subject">
                                <?php echo htmlspecialchars($row['content_type'] . ' Report'); ?>
                                <span class="status-label status-<?php echo $row['status']; ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </div>
                            <div class="mail-preview">
                                <?php echo htmlspecialchars($row['description']); ?>
                            </div>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>

            <div id="appeals" class="tab-content">
                <?php
                $query = "SELECT a.*, u.fullname, u.profile_picture FROM appeals a 
                        JOIN ff_users u ON a.user_id = u.id 
                        ORDER BY a.submitted_at DESC";
                $result = mysqli_query($conn, $query);
                while ($row = mysqli_fetch_assoc($result)) {
                    ?>
                    <div class="mail-item" onclick="openAppealModal(<?php echo $row['id']; ?>)">
                        <div class="mail-content">
                            <div class="mail-header">
                                <span class="mail-sender"><?php echo htmlspecialchars($row['fullname']); ?></span>
                                <span class="mail-date"><?php echo date('M d', strtotime($row['submitted_at'])); ?></span>
                            </div>
                            <div class="mail-subject">
                            <?php echo htmlspecialchars($row['content_type'] . ' Appeal Request'); ?>
                                <span class="status-label status-<?php echo $row['status']; ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </div>
                            <div class="mail-preview">
                                <?php echo htmlspecialchars($row['reason']); ?>
                            </div>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>

            <div id="applications" class="tab-content">
                <?php
                $query = "SELECT * FROM shop_signup ORDER BY submission_date DESC";
                $result = mysqli_query($conn, $query);
                while ($row = mysqli_fetch_assoc($result)) {
                    ?>
                    <div class="mail-item" onclick="showApplicationModal(<?php echo $row['id']; ?>)">
                        <div class="mail-content">
                            <div class="mail-header">
                                <span class="mail-sender"><?php echo htmlspecialchars($row['shop_name']); ?></span>
                                <span class="mail-date"><?php echo date('M d', strtotime($row['submission_date'])); ?></span>
                            </div>
                            <div class="mail-subject">
                                <?php echo htmlspecialchars($row['fullname']); ?>
                                <span class="status-label status-<?php echo $row['status']; ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </div>
                            <div class="mail-preview">
                                <?php echo htmlspecialchars($row['shop_details']); ?>
                            </div>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>

            

            <div id="memberships" class="tab-content">
                <?php
                $query = "SELECT ms.*, mp.name as plan_name, u.fullname, u.profile_picture 
                        FROM membership_signup ms 
                        JOIN ff_users u ON ms.user_email = u.email
                        JOIN membership_plans mp ON ms.plan_id = mp.id
                        ORDER BY ms.request_date DESC";
                $result = mysqli_query($conn, $query);
                while ($row = mysqli_fetch_assoc($result)) {
                    ?>
                    <div class="mail-item" onclick="showMembershipModal(<?php echo $row['id']; ?>)">
                        <div class="mail-content">
                            <div class="mail-header">
                                <span class="mail-sender"><?php echo htmlspecialchars($row['fullname']); ?></span>
                                <span class="mail-date"><?php echo date('M d', strtotime($row['request_date'])); ?></span>
                            </div>
                            <div class="mail-subject">
                                <?php echo htmlspecialchars($row['plan_name']); ?> Membership Request
                                <span class="status-label status-<?php echo $row['status']; ?>">
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </div>
                            <div class="mail-preview">
                                Amount: ₱<?php echo number_format($row['amount_to_pay'], 2); ?>
                            </div>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>


        </div>
    </div>
</div>

<div id="previewModal" class="mail-modal">
    <div class="mail-modal-content">
        <div class="mail-modal-header">
            <h2 id="modalTitle">Preview</h2>
            <span class="mail-modal-close" onclick="closeModal()">&times;</span>
        </div>
        <div class="mail-modal-body" id="modalBody">
            <!-- Content will be loaded here -->
        </div>
        <div class="mail-modal-footer">
            <a href="#" id="originalContentLink">View Original Content</a>
            <form method="POST" id="flagForm">
                <input type="hidden" name="id" id="modalContentId">
                <input type="hidden" name="type" id="modalContentType">
                <select name="status" class="action-btn">
                    <option value="dismiss">Dismiss</option>
                    <option value="flagged">Flag</option>
                    <option value="deleted">Delete</option>
                </select>
                <button type="submit" name="action" value="update" class="action-btn">Update</button>
            </form>
        </div>
    </div>
</div>

<div id="appealModal" class="mail-modal">
    <div class="mail-modal-content">
        <div class="mail-modal-header">
            <h2 id="appealModalTitle">Appeal Preview</h2>
            <span class="mail-modal-close" onclick="closeAppealModal()">&times;</span>
        </div>
        <div class="mail-modal-body" id="appealModalBody">
            <!-- Appeal content will be loaded here -->
        </div>
        <div class="mail-modal-footer">
            <a href="#" id="appealContentLink">View Original Content</a>
            <form method="POST" id="appealForm">
                <input type="hidden" name="id" id="appealModalContentId">
                <input type="hidden" name="type" value="appeal">
                <select name="status" class="action-btn">
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
                <button type="submit" name="action" value="update" class="action-btn">Update</button>
            </form>
        </div>
    </div>
</div>

<div id="applicationModal" class="mail-modal">
    <div class="mail-modal-content">
        <div class="mail-modal-header">
            <h2 id="applicationModalTitle">Application Preview</h2>
            <span class="mail-modal-close" onclick="closeApplicationModal()">&times;</span>
        </div>
        <div class="mail-modal-body" id="applicationModalBody">
            <!-- Application content will be loaded here -->
        </div>
        <div class="mail-modal-footer">
            <form method="POST" id="applicationForm">
                <input type="hidden" name="id" id="applicationModalContentId">
                <input type="hidden" name="type" value="application">
                <select name="status" class="action-btn">
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
                <button type="submit" name="action" value="update" class="action-btn">Update</button>
            </form>
        </div>
    </div>
</div>

<div id="membershipModal" class="mail-modal">
    <div class="mail-modal-content">
        <div class="mail-modal-header">
            <h2>Membership Request</h2>
            <span class="mail-modal-close" onclick="closeMembershipModal()">&times;</span>
        </div>
        <div class="mail-modal-body" id="membershipModalBody">
            <!-- Content will be loaded here -->
        </div>
        <div class="mail-modal-footer">
            <form method="POST" id="membershipForm">
                <input type="hidden" name="id" id="membershipModalContentId">
                <input type="hidden" name="type" value="membership">
                <select name="status" class="action-btn">
                    <option value="pending">Pending</option>
                    <option value="approved">Approve</option>
                    <option value="rejected">Reject</option>
                </select>
                <button type="submit" name="action" value="update" class="action-btn">Update</button>
            </form>
        </div>
    </div>
</div>


<script>
function toggleMenu() {
    let subMenu = document.getElementById("subMenu");
    subMenu.classList.toggle("open-menu");
}

function showTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active');
    });

    document.getElementById(tabName).classList.add('active');
    event.currentTarget.classList.add('active');
}

function openModal(type, id) {
    fetch(`get_content_details.php?type=${type}&id=${id}`)
        .then(response => response.json())
        .then(data => {
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = document.getElementById('modalBody');
            const originalLink = document.getElementById('originalContentLink');

            const statusSelect = document.querySelector('select[name="status"]');
            statusSelect.innerHTML = '';

            statusSelect.add(new Option('Dismiss', 'dismiss'));

            if (data.content_type === 'User') {
                statusSelect.add(new Option('Flag', 'flagged'));
            } else if (['Post', 'Review', 'Comment'].includes(data.content_type)) {
                statusSelect.add(new Option('Flag', 'flagged'));
                statusSelect.add(new Option('Delete', 'deleted'));
            }

            if (type === 'report') {
                modalTitle.textContent = `Report: ${data.content_type.charAt(0).toUpperCase() + data.content_type.slice(1)}`;
                
                let contentPreview = '';
                if (data.original_content) {
                    switch(data.content_type) {
                        case 'Review':
                            contentPreview = `
                                <div class="report-section">
                                    <h3>Report Details</h3>
                                    <p><strong>Reported by:</strong> ${data.reporter_name || 'Unknown'}</p>
                                    <p><strong>Date:</strong> ${new Date(data.report_date).toLocaleDateString()}</p>
                                    <p><strong>Reason:</strong> ${data.reason || 'Not specified'}</p>
                                    <p><strong>Description:</strong></p>
                                    <div class="appeal-reason">${data.description || ''}</div>
                                </div>
                                <div class="content-section">
                                    <h3>Reported Review</h3>
                                    <div class="review" id="review-${data.content_id}">
                                        <div class="review-header">
                                            <div class="user-info">
                                                <img src="${data.original_content.profile_picture || 'images/pfp.png'}" class="profile-pic">
                                                <h4>${data.original_content.fullname}</h4>
                                            </div>
                                            <div class="review-stars">
                                                <div class="stars-display star-rating-display" data-rating="${data.original_content.rating}">
                                                    ${generateStars(data.original_content.rating)}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="review-content">
                                            <p class="review-text">${data.original_content.review_text}</p>
                                            ${data.original_content.edited ? '<small>(edited)</small>' : ''}
                                        </div>
                                        <small>Posted on: ${new Date(data.original_content.created_at).toLocaleDateString()}</small>
                                    </div>
                                </div>`;
                            break;
                            
                            case 'Post':
                                contentPreview = `
                                    <div class="report-section">
                                        <h3>Report Details</h3>
                                        <p><strong>Reported by:</strong> ${data.reporter_name || 'Unknown'}</p>
                                        <p><strong>Date:</strong> ${new Date(data.report_date).toLocaleDateString()}</p>
                                        <p><strong>Reason:</strong> ${data.reason || 'Not specified'}</p>
                                        <p><strong>Description:</strong></p>
                                        <div class="appeal-reason">${data.description || ''}</div>
                                    </div>
                                    <div class="content-section">
                                        <h3>Reported Post</h3>
                                        <div class="board">
                                            <div class="board-details"> 
                                                <div class="poster-info">
                                                    <img src="${data.original_content.profile_picture || 'images/pfp.png'}" alt="Profile Picture">
                                                    <h4>${data.original_content.fullname}</h4>
                                                </div>
                                                <h2>${data.original_content.subject}</h2>
                                                <p>${data.original_content.location}</p>
                                                <br>
                                                <p>${data.original_content.description}</p>
                                                <br>
                                                <p>${new Date(data.original_content.post_date).toLocaleDateString()}</p>
                                            </div>
                                        </div>
                                    </div>`;
                                break;

                                case 'Comment':
                                    contentPreview = `
                                        <div class="report-section">
                                            <h3>Report Details</h3>
                                            <p><strong>Reported by:</strong> ${data.reporter_name || 'Unknown'}</p>
                                            <p><strong>Date:</strong> ${new Date(data.report_date).toLocaleDateString()}</p>
                                            <p><strong>Reason:</strong> ${data.reason || 'Not specified'}</p>
                                            <p><strong>Description:</strong></p>
                                            <div class="appeal-reason">${data.description || ''}</div>
                                        </div>
                                        <div class="content-section">
                                            <h3>Reported Comment</h3>
                                            <div class="comment">
                                                <div class="comment-header">
                                                    <img src="${data.original_content?.profile_picture || 'images/pfp.png'}" class="comment-profile-pic">
                                                    <div>
                                                        <strong>${data.original_content?.fullname || 'Unknown User'}</strong>
                                                        <span>${data.original_content?.created_at ? new Date(data.original_content.created_at).toLocaleDateString() : 'Unknown date'}</span>
                                                    </div>
                                                </div>
                                                <p class="comment-text">${data.original_content?.comment || 'Comment not found'}</p>
                                                <p><small>On post: ${data.original_content?.post_subject || 'Unknown post'}</small></p>
                                            </div>
                                        </div>`;
                                    break;

                            case 'User':
                                contentPreview = `
                                    <div class="report-section">
                                        <h3>Report Details</h3>
                                        <p><strong>Reported by:</strong> ${data.reporter_name || 'Unknown'}</p>
                                        <p><strong>Date:</strong> ${new Date(data.report_date).toLocaleDateString()}</p>
                                        <p><strong>Reason:</strong> ${data.reason || 'Not specified'}</p>
                                        <p><strong>Description:</strong></p>
                                        <div class="appeal-reason">${data.description || ''}</div>
                                    </div>
                                    <div class="content-section">
                                        <h3>Reported User</h3>
                                        <div class="user-info">
                                            <img src="${data.original_content.profile_picture || 'images/pfp.png'}" class="profile-pic">
                                            <div class="profile-details">
                                                <h4>${data.original_content.fullname}</h4>
                                                <p>${data.original_content.email}</p>
                                            </div>
                                        </div>
                                    </div>`;
                                break;
                    }
                }
                modalBody.innerHTML = contentPreview;
            } else {
                modalTitle.textContent = 'Location Suggestion';
                modalBody.innerHTML = `
                    <div class="suggestion-preview">
                        <p><strong>Suggested by:</strong> ${data.suggester_name}</p>
                        <p><strong>Location Name:</strong> ${data.location_name}</p>
                        <p><strong>Details:</strong> ${data.details}</p>
                        <p><strong>Date:</strong> ${new Date(data.created_at).toLocaleDateString()}</p>
                    </div>`;
            }

            document.getElementById('originalContentLink').href = data.original_link;
            document.getElementById('modalContentId').value = id;
            document.getElementById('modalContentType').value = type;
            document.getElementById('previewModal').style.display = 'block';
        });
}

function generateStars(rating) {
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        stars += `<span class="star ${i <= rating ? 'filled' : ''}">★</span>`;
    }
    return stars;
}

document.getElementById('flagForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('update_report_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            closeModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to update status'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the status');
    });
});

function closeModal() {
    document.getElementById('previewModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('previewModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}


function openAppealModal(id) {
    fetch(`get_appeal_details.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            const modalBody = document.getElementById('appealModalBody');
            const originalLink = document.getElementById('appealContentLink');
            let contentPreview = '';

            console.log('Appeal data:', data);

            if (data.original_link) {
                originalLink.href = data.original_link;
                originalLink.style.display = 'inline-block';
            } else {
                originalLink.style.display = 'none';
            }

            contentPreview += `
                <div class="appeal-section">
                    <h3>Appeal Details</h3>
                    <div class="user-info">
                        <img src="${data.profile_picture || 'images/pfp.png'}" class="profile-pic">
                        <h4>${data.fullname}</h4>
                    </div>
                    <p><strong>Appeal Type:</strong> ${data.content_type}</p>
                    <p><strong>Submitted:</strong> ${new Date(data.submitted_at).toLocaleDateString()}</p>
                    <p><strong>Status:</strong> ${data.status}</p>
                    <p><strong>Appeal Reason:</strong></p>
                    <div class="appeal-reason">${data.reason}</div>
                </div>`;

            if (data.original_content) {
                switch(data.content_type) {
                    case 'Review':
                        contentPreview += `
                            <div class="review">
                                <div class="review-header">
                                    <div class="user-info">
                                        <img src="${data.content_profile_picture || 'images/pfp.png'}" class="profile-pic">
                                        <h4>${data.content_fullname}</h4>
                                    </div>
                                    <div class="review-stars">
                                        <div class="stars-display star-rating-display" data-rating="${data.original_content.rating}">
                                            ${generateStars(data.original_content.rating)}
                                        </div>
                                    </div>
                                </div>
                                <div class="review-content">
                                    <p class="review-text">${data.original_content.review_text}</p>
                                    ${data.original_content.edited ? '<small>(edited)</small>' : ''}
                                </div>
                                <small>Posted on: ${new Date(data.original_content.created_at).toLocaleDateString()}</small>
                            </div>`;
                        break;

                    case 'Post':
                        contentPreview += `
                            <div class="board">
                                <div class="board-details"> 
                                    <div class="poster-info">
                                        <img src="${data.content_profile_picture || 'images/pfp.png'}" alt="Profile Picture">
                                        <h4>${data.content_fullname}</h4>
                                    </div>
                                    <h2>${data.original_content.subject}</h2>
                                    <p>${data.original_content.location}</p>
                                    <br>
                                    <p>${data.original_content.description}</p>
                                    <br>
                                    <p>${new Date(data.original_content.post_date).toLocaleDateString()}</p>
                                </div>
                            </div>`;
                        break;

                    case 'Comment':
                        contentPreview += `
                            <div class="comment">
                                <div class="comment-header">
                                    <img src="${data.content_profile_picture || 'images/pfp.png'}" class="comment-profile-pic">
                                    <div>
                                        <strong>${data.content_fullname}</strong>
                                        <span>${new Date(data.original_content.created_at).toLocaleDateString()}</span>
                                    </div>
                                </div>
                                <p class="comment-text">${data.original_content.comment}</p>
                                <p><small>On post: ${data.original_content.post_subject}</small></p>
                            </div>`;
                        break;

                        case 'User':
                        contentPreview = `
                            <div class="user-info">
                                <img src="${data.content_profile_picture}" alt="Profile Picture">
                                <h4>${data.content_fullname}</h4>
                                <p>${data.original_content.email}</p>
                            </div>`;
                        break;
                }
            }

            modalBody.innerHTML = contentPreview;
            document.getElementById('appealModalContentId').value = id;
            document.getElementById('appealModal').style.display = 'block';
        })
        .catch(error => {
            console.error('Error details:', error);
            alert('Error fetching appeal details: ' + error.message);
        });
}

function closeAppealModal() {
    document.getElementById('appealModal').style.display = 'none';
}

function loadAppealContent(appealId) {
    fetch(`get_appeal_content.php?id=${appealId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('appealModalBody').innerHTML = data.appeal_reason;
            document.getElementById('contentTypeBadge').textContent = data.content_type;
            document.getElementById('originalContent').innerHTML = data.original_content;
            document.getElementById('appealModal').style.display = 'block';
        });
}

window.onclick = function(event) {
    const appealModal = document.getElementById('appealModal');
    if (event.target == appealModal) {
        appealModal.style.display = 'none';
    }
}

document.getElementById('appealForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('update_appeal_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            closeAppealModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to update status'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the status');
    });
});


function showApplicationModal(id) {
    fetch(`get_application_details.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            const modalBody = document.getElementById('applicationModalBody');
            const modalContentId = document.getElementById('applicationModalContentId');

            const imagesHTML = data.images ? data.images.map(img => 
                `<img src="${img}" alt="Shop Image" style="width: 200px; height: 200px; object-fit: cover; margin: 5px;">`
            ).join('') : '';

            modalBody.innerHTML = `
                <div class="application-preview">
                    <h3>Personal Information</h3>
                    <p><strong>Full Name:</strong> ${data.fullname}</p>
                    <p><strong>Email:</strong> ${data.email}</p>
                    <p><strong>Contact Number:</strong> ${data.contact_number}</p>

                    <h3>Shop Information</h3>
                    <p><strong>Shop Name:</strong> ${data.shop_name}</p>
                    <p><strong>Operating Hours:</strong> ${data.operating_hours}</p>
                    <p><strong>Price Range:</strong> ₱${data.min_price} - ₱${data.max_price}</p>
                    <p><strong>Address:</strong> ${data.address}</p>
                    <p><strong>Tags:</strong> ${data.tags}</p>
                    <p><strong>Details:</strong> ${data.shop_details}</p>
                    
                    <h3>Location</h3>
                    <div id="applicationMap" class="application-map"></div>

                    <h3>Shop Images</h3>
                    <div class="image-gallery" style="display: flex; flex-wrap: wrap; gap: 10px;">
                        ${imagesHTML}
                    </div>

                    <p><strong>Submission Date:</strong> ${new Date(data.submission_date).toLocaleDateString()}</p>
                    <p><strong>Status:</strong> ${data.status}</p>
                </div>`;

            setTimeout(() => {
                const map = L.map('applicationMap').setView([data.latitude, data.longitude], 15);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);
                
                L.marker([data.latitude, data.longitude])
                    .addTo(map)
                    .bindPopup(data.shop_name);
                
                map.invalidateSize();
            }, 100);

            modalContentId.value = id;
            document.getElementById('applicationModal').style.display = 'block';
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while fetching the application details');
        });
}

function closeApplicationModal() {
    document.getElementById('applicationModal').style.display = 'none';
}

document.getElementById('applicationForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('update_application_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            closeApplicationModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to update application status'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the application status');
    });
});

function showMembershipModal(id) {
    fetch(`get_membership_details.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            const modalBody = document.getElementById('membershipModalBody');
            modalBody.innerHTML = `
                <div class="membership-preview">
                    <div class="user-info">
                        <img src="${data.profile_picture || 'images/pfp.png'}" alt="Profile Picture">
                        <h4>${data.fullname}</h4>                        
                    </div>
                    <div class="plan-info">
                        <h3>Plan Details</h3>
                        <p><strong>Email:</strong> ${data.user_email}</p>
                        <p><strong>Contact Number:</strong> ${data.contact || 'Not provided'}</p>
                        <p><strong>Plan:</strong> ${data.plan_name}</p>
                        <p><strong>Amount:</strong> ₱${data.amount_to_pay}</p>
                        <p><strong>Request Date:</strong> ${new Date(data.request_date).toLocaleString()}</p>
                        <p><strong>Status:</strong> ${data.status}</p>
                    </div>
                </div>`;
            
            document.getElementById('membershipModalContentId').value = id;
            document.getElementById('membershipModal').style.display = 'block';
        });
}

function closeMembershipModal() {
    document.getElementById('membershipModal').style.display = 'none';
}

document.getElementById('membershipForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const id = document.getElementById('membershipModalContentId').value;
    const status = formData.get('status');
    
    fetch('update_member.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${id}&status=${status}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeMembershipModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to process membership request'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while processing the membership request');
    });
});
</script>
</body>
</html>