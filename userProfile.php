<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

$email = $_SESSION['email'];
$query = "SELECT * FROM ff_users WHERE email = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    die("Query failed: " . $stmt->error);
}

$user = $result->fetch_assoc();

if ($user['flagged']) {
    $_SESSION['flagged'] = true;
    echo '<div id="flaggedAlert" class="alert alert-warning popup-notification">
            Your account has been flagged. Please submit an appeal to get unflagged.
          </div>';
} else {
    $_SESSION['flagged'] = false;
}

$user_reviews_query = "SELECT r.*, l.name as location_name, u.profile_picture, u.fullname, 
                      r.flagged, r.flag_reason, r.edited 
                      FROM reviews r 
                      JOIN study_locations l ON r.location_id = l.id 
                      JOIN ff_users u ON r.email = u.email
                      WHERE r.email = ?
                      ORDER BY r.created_at DESC";
$user_reviews_stmt = $conn->prepare($user_reviews_query);
$user_reviews_stmt->bind_param("s", $email);
$user_reviews_stmt->execute();
$user_reviews_result = $user_reviews_stmt->get_result();

$user_reviews = [];
while ($review = $user_reviews_result->fetch_assoc()) {
    $date = new DateTime($review['created_at']);
    $review['formatted_date'] = $date->format('Y-m-d');

    $user_reviews[] = $review;
}

$message = "";

if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
    $upload_dir = 'profiles/';
    $uploaded_file = $upload_dir . basename($_FILES['profile_picture']['name']);
    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploaded_file)) {
        $profile_picture = $uploaded_file;
        $_SESSION['profile_picture'] = $profile_picture;

        $update_query = "UPDATE ff_users SET profile_picture = ? WHERE email = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ss", $profile_picture, $email);

        if (!$update_stmt->execute()) {
            $message = "Error updating profile picture in the database: " . $update_stmt->error;
        }
    } else {
        $message = "Error uploading profile picture. Please check the directory permissions.";
    }
}

$query = "SELECT * FROM ff_users WHERE email = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    die("Query failed: " . $stmt->error);
}

$user = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $birthday = $_POST['birthday'] ?? '';
    $school = $_POST['school'] ?? '';
    $course = $_POST['course'] ?? '';
    $year_level = $_POST['year_level'] ?? '';
    $contact = $_POST['contact'] ?? '';
    $fullname = $_POST['fullname'] ?? '';
    $profbio = $_POST['profbio'] ?? '';

    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = 'profiles/';
        $uploaded_file = $upload_dir . basename($_FILES['profile_picture']['name']);
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploaded_file)) {
            $profile_picture = $uploaded_file;
        } else {
            $message = "";
        }
    } else {
        $profile_picture = isset($user['profile_picture']) ? $user['profile_picture'] : '';
    }

    $update_query = "UPDATE ff_users SET birthday = ?, school = ?, course = ?, year_level = ?, contact = ?, fullname = ?, profbio = ?, profile_picture = ? WHERE email = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("sssssssss", $birthday, $school, $course, $year_level, $contact, $fullname, $profbio, $profile_picture, $email);

    if ($update_stmt->execute()) {
        $_SESSION['profile_picture'] = $profile_picture; 
    } else {
        $message = "Error updating profile: " . $update_stmt->error;
    }

    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
}

$_SESSION['profile_picture'] = isset($user['profile_picture']) ? $user['profile_picture'] : ''; 
$conn->close();

function displayStars($rating) {
    $output = '<div class="star-rating-display">';
    
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $output .= '<span class="star filled">★</span>';
        } else {
            $output .= '<span class="star">★</span>';
        }
    }

    $output .= '</div>';
    return $output;
}
?>

<!DOCTYPE html>
<html>
<head>
  <link rel="icon" type="image/x-icon" href="images/FFicon.ico">
  <title>Focus Finder</title>
  <link rel="stylesheet" type="text/css" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
<?php if (isset($_SESSION['flagged']) && $_SESSION['flagged']): ?>
        <div id="flaggedAlert" class="alert alert-warning popup-notification">
            Your account has been flagged. Please submit an appeal to get unflagged.
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const flaggedAlert = document.getElementById('flaggedAlert');
            if (flaggedAlert) {
                setTimeout(() => {
                    flaggedAlert.style.display = 'block';
                    setTimeout(() => {
                        flaggedAlert.remove();
                    }, 3000);
                }, 100);
            }
        });
        </script>
    <?php endif; ?>
<header>
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
</header>

<div class="main-content">
<div class="profile-menu <?php echo $user['flagged'] ? 'flagged-profile' : ''; ?>">
    <?php if ($user['flagged']): ?>
        <p class="profile-flag-message">Flagged for <?php echo htmlspecialchars($user['flag_reason']); ?></p>
    <?php endif; ?>
    <div class="profile-options">
    <?php if ($user['flagged']): ?>
        <i class="fas fa-ellipsis-h" onclick="toggleProfileDropdown(this)"></i>
        <div class="dropdown-menu">
            <a href="#" onclick="showAppealModal('User', <?php echo $_SESSION['user_id']; ?>); return false;">Appeal Unflag</a>
        </div>
    <?php endif; ?>
</div>
<div class="profile-header">
        <form method="POST" action="userProfile.php" enctype="multipart/form-data">
            <div class="profile-picture-container">
                <img src="<?php echo isset($user['profile_picture']) ? $user['profile_picture'] : ''; ?>" alt="Profile Picture" class="profile-picture">
                <button type="button" class="change-pic-btn" style="display: none;">Change Picture</button>
                <input type="file" name="profile_picture" id="profile_picture" style="display: none;">
            </div>
            <div class="profile-info">
                <div class="profile-info-text">
                    <h1><?php echo isset($user['fullname']) ? $user['fullname'] : ''; ?></h1>
                    <p class="editable" contenteditable="false"><?php echo isset($user['profbio']) ? $user['profbio'] : "add a bio..."; ?></p>
                    <input type="text" name="profbio" class="editable-field" style="display: none;" value="<?php echo isset($user['profbio']) ? $user['profbio'] : ''; ?>" placeholder="add a bio...">
                </div>
                <button type="button" class="btn">Edit Profile</button>
            </div>
            </div>

        <div class="profile-details">
            <h3>Profile Details</h3>
            <ul>
                <li class="profile-detail-box">
                    <label>Name:</label>
                    <p class="editable" contenteditable="false"><?php echo $user['fullname']; ?></p>
                    <input type="text" name="fullname" class="editable-field" style="display: none;" value="<?php echo $user['fullname']; ?>">
                </li>
                <li class="profile-detail-box">
                    <label>Email:</label>
                    <p><?php echo $user['email']; ?></p>
                </li>
                <li class="profile-detail-box">
                    <label>School:</label>
                    <p class="editable" contenteditable="false"><?php echo $user['school']; ?></p>
                    <input type="text" name="school" class="editable-field" style="display: none;" value="<?php echo $user['school']; ?>">
                </li>
                <li class="profile-detail-box">
                    <label>Course:</label>
                    <p class="editable" contenteditable="false"><?php echo $user['course']; ?></p>
                    <input type="text" name="course" class="editable-field" style="display: none;" value="<?php echo $user['course']; ?>">
                </li>
                <li class="profile-detail-box">
                    <label>Year Level:</label>
                    <p class="editable" contenteditable="false"><?php echo $user['year_level']; ?></p>
                    <select name="year_level" class="editable-field" style="display: none;">
                        <option value="" <?php echo $user['year_level'] == 'None' ? 'selected' : ''; ?>>None</option>
                        <option value="1st Year" <?php echo $user['year_level'] == '1st Year' ? 'selected' : ''; ?>>1st Year</option>
                        <option value="2nd Year" <?php echo $user['year_level'] == '2nd Year' ? 'selected' : ''; ?>>2nd Year</option>
                        <option value="3rd Year" <?php echo $user['year_level'] == '3rd Year' ? 'selected' : ''; ?>>3rd Year</option>
                        <option value="4th Year" <?php echo $user['year_level'] == '4th Year' ? 'selected' : ''; ?>>4th Year</option>
                    </select>
                </li>
                <li class="profile-detail-box">
                    <label>Date of Birth:</label>
                    <?php if (!empty($user['birthday']) && $user['birthday'] !== '0000-00-00'): ?>
                    <p class="editable" contenteditable="false"><?php echo $user['birthday']; ?></p>
                    <?php else: ?>
                    <p class="editable" contenteditable="false"></p>
                    <?php endif; ?>
                    <input type="date" name="birthday" class="editable-field" style="display: none;" value="<?php echo $user['birthday']; ?>">
                </li>
                <li class="profile-detail-box">
                <label>Contact:</label>
                  <?php if (!empty($user['contact']) && $user['contact'] !== '0'): ?>
                  <p class="editable" contenteditable="false"><?php echo $user['contact']; ?></p>
                  <?php else: ?>
                  <p class="editable" contenteditable="false"></p>
                  <?php endif; ?>
                  <input type="text" name="contact" class="editable-field" style="display: none;" value="<?php echo $user['contact']; ?>">
                </li>
            </ul>
        
        <input type="submit" class="btn" style="display: none;">
        </form>
    </div>
    </div>

    <div class="password-change">
    <button type="button" class="btn" onclick="openChangePasswordModal()">Change Password</button>
</div>

<div id="changePasswordModal" class="password-modal">
    <div class="password-modal-content">
        <span class="close" onclick="confirmCancel()">&times;</span>
        
        <div id="stage1">
            <form id="verifyPasswordForm">
                <label>Current Password:</label>
                <div class="password-container">
                    <input type="password" name="current_password" required>
                    <i class="fas fa-eye toggle-password"></i>
                </div>
                <button type="submit">Verify</button>
                <div class="message error-message" style="display: none;">Incorrect Credentials</div>
            </form>
        </div>
        
        <div id="stage2" style="display: none;">
            <form id="changePasswordForm">
                <label>New Password:</label>
                <div class="password-container">
                    <input type="password" name="new_password" required>
                    <i class="fas fa-eye toggle-password"></i>
                </div>
                <label>Confirm New Password:</label>
                <div class="password-container">
                    <input type="password" name="confirm_new_password" required>
                    <i class="fas fa-eye toggle-password"></i>
                </div>
                <button type="submit">Change Password</button>
                <div class="message error-message" style="display: none;">Passwords do not match!</div>
            </form>
        </div>
        
        <div id="stage3" style="display: none;">
            <p>Password Successfully Changed</p>
            <button type="button" onclick="closeChangePasswordModal()">Close</button>
        </div>
    </div>
</div>

<div id="cancelConfirmationModal" class="password-modal">
    <div class="password-modal-content">
        <p>Are you sure you want to cancel?</p>
        <button type="button" onclick="closeCancelConfirmation()">No</button>
        <button type="button" onclick="confirmCloseChangePasswordModal()">Yes</button>
    </div>
</div>

<div id="deleteModal" class="delete-modal">
    <div class="delete-modal-content">
        <h2>Delete Review</h2>
        <p>Are you sure you want to delete this review?</p>
        <div id="reviewContent" class="review-content">
            <!-- Review content will be dynamically inserted here -->
        </div>
        <div class="modal-buttons">
            <button id="confirmDeleteBtn" class="btn btn-danger" onclick="deleteReview(reviewIdToDelete)">Yes, Delete</button>
            <button type="button" class="btn btn-secondary delete-cancel">Cancel</button>
        </div>
    </div>
</div>

<div id="AppealModal" class="appeal-modal">
    <div class="appeal-modal-content">
        <h2>Appeal Unflag Request</h2>
        <form id="appealForm" action="submit_appeal.php" method="POST">
            <input type="hidden" name="content_type" id="appealContentType">
            <input type="hidden" name="content_id" id="appealContentId">
            <div class="form-group">
                <label for="appeal_reason">Appeal Reason:</label>
                <textarea 
                    id="appeal_reason" 
                    name="appeal_reason" 
                    placeholder="Please explain in detail why your account should be unflagged..." 
                    required
                ></textarea>
            </div>
            <div class="modal-buttons">
                <button type="submit" class="btn btn-primary">Submit Appeal</button>
                <button type="button" class="btn btn-secondary" onclick="closeAppealModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div id="successMessage" class="success-message" style="display: none;">
    Review deleted successfully.
</div>

    <?php if ($message): ?>
        <div class="alert">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

<div class="user-reviews">
    <h3>User Reviews</h3>
    <?php if (!empty($user_reviews)): ?>
        <?php foreach ($user_reviews as $review): ?>
            <div class="review <?php echo $review['flagged'] ? 'flagged-review' : ''; ?>" id="review-<?php echo $review['id']; ?>">
                <?php if ($review['flagged']): ?>
                    <div class="flag-status">This review is flagged for: <?php echo htmlspecialchars($review['flag_reason']); ?></div>
                <?php endif; ?>
                <div class="review-options">
                    <i class="fas fa-ellipsis-h" onclick="toggleDropdown(this)"></i>
                    <div class="dropdown-menu">
                        <a href="#" onclick="toggleEditMode(<?php echo $review['id']; ?>); return false;">Edit</a>
                        <a href="#" onclick="showDeleteModal(<?php echo $review['id']; ?>); return false;">Delete</a>
                    </div>
                </div>
                <div class="review-header">
                    <div class="user-info">
                        <img src="<?php echo htmlspecialchars($review['profile_picture']); ?>" alt="<?php echo htmlspecialchars($review['fullname']); ?>" class="profile-pic">
                        <h4><?php echo htmlspecialchars($review['fullname']); ?></h4>
                    </div>
                    <div class="review-stars">
                        <div class="stars-display star-rating-display" data-rating="<?php echo $review['rating']; ?>">
                            <?php echo displayStars($review['rating']); ?>
                        </div>
                        <div class="stars-edit star-rating-display editable" style="display:none;" data-rating="<?php echo $review['rating']; ?>">
                            <?php echo displayStars($review['rating']); ?>
                        </div>
                    </div>
                </div>
                <div class="review-content">
                        <p class="review-text editable" contenteditable="false"><?php echo htmlspecialchars($review['review_text']); ?></p>
                        <textarea class="edit-review-text editable-field" style="display:none;"><?php echo htmlspecialchars($review['review_text']); ?></textarea>
                        <div class="edit-buttons" style="display:none;">
                            <button class="save-review-btn btn" onclick="saveReview(<?php echo $review['id']; ?>)">Save</button>
                            <button class="cancel-review-btn btn" onclick="cancelEdit(<?php echo $review['id']; ?>)">Cancel</button>
                        </div>
                        <?php if ($review['edited']): ?>
                            <small>(edited)</small>
                        <?php endif; ?>
                    </div>
                    <small>Posted on: <?php echo $review['formatted_date']; ?></small>
                    <a href="userLocation.php?location_id=<?php echo $review['location_id']; ?>" class="location-link">
                        View Location: <?php echo htmlspecialchars($review['location_name']); ?>
                    </a>
                </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No reviews have been made yet.</p>
    <?php endif; ?>
</div>
</div>
</div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function () {
    const editButton = document.querySelector(".profile-menu .btn");
    const editableFields = document.querySelectorAll(".profile-details .editable, .profile-info-text .editable");
    const inputFields = document.querySelectorAll(".profile-details .editable-field, .profile-info-text .editable-field");
    const saveButton = document.querySelector("input[type='submit']");
    const changePicButton = document.querySelector(".change-pic-btn");
    const fileInput = document.querySelector("input[type='file']");
    let isEditing = false;

    editButton.addEventListener("click", function () {
        isEditing = !isEditing;

        if (isEditing) {
            editableFields.forEach(field => {
                field.style.display = "none";
            });
            inputFields.forEach(input => {
                input.style.display = "inline-block";
            });
            editButton.textContent = "Save Changes";
            saveButton.style.display = "none";
            changePicButton.style.display = "inline-block";
        } else {
            editableFields.forEach(field => {
                field.style.display = "block";
            });
            inputFields.forEach(input => {
                input.style.display = "none";
            });
            editButton.textContent = "Edit Profile";
            saveButton.style.display = "none";
            changePicButton.style.display = "none";

            document.querySelector("form").submit();
        }
    });


    changePicButton.addEventListener("click", function () {
        fileInput.click();
    });


    fileInput.addEventListener("change", function () {
        const profilePicture = document.querySelector(".profile-picture");
        const reader = new FileReader();

        reader.onload = function (e) {
            profilePicture.src = e.target.result;
        };

        reader.readAsDataURL(fileInput.files[0]);
    });
});

document.addEventListener("DOMContentLoaded", function () {
    const contactInput = document.querySelector("input[name='contact']");

    contactInput.addEventListener("input", function () {
        let cleaned = this.value.replace(/\D/g, '').substring(0, 11);
        this.value = cleaned;
    });
});


    function toggleMenu() {
        var subMenu = document.getElementById("subMenu");
        subMenu.classList.toggle("open-menu");
    }


    document.addEventListener("DOMContentLoaded", function () {
    const fileInput = document.querySelector("input[type='file']");
    const profilePicture = document.querySelector(".profile-picture");

    fileInput.addEventListener("change", function () {
        const reader = new FileReader();

        reader.onload = function (e) {
            profilePicture.src = e.target.result;
            document.querySelectorAll(".review .profile-pic").forEach(function (img) {
                img.src = e.target.result;
            });
        };

        reader.readAsDataURL(fileInput.files[0]);
    });
});

function openChangePasswordModal() {
    document.getElementById('changePasswordModal').style.display = 'block';
}

function closeChangePasswordModal() {
    document.getElementById('changePasswordModal').style.display = 'none';

    document.getElementById('verifyPasswordForm').reset();
    document.getElementById('changePasswordForm').reset();

    document.getElementById('stage1').style.display = 'block';
    document.getElementById('stage2').style.display = 'none';
    document.getElementById('stage3').style.display = 'none';

    let errorMessages = document.querySelectorAll('.error-message');
    errorMessages.forEach(function(message) {
        message.style.display = 'none';
    });
}

function confirmCancel() {
    document.getElementById('cancelConfirmationModal').style.display = 'block';
}

function closeCancelConfirmation() {
    document.getElementById('cancelConfirmationModal').style.display = 'none';
}

function confirmCloseChangePasswordModal() {
    closeCancelConfirmation();
    closeChangePasswordModal();
}




document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('verifyPasswordForm').addEventListener('submit', function(event) {
        event.preventDefault();
        const currentPassword = event.target.current_password.value;

        fetch('verify_password.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                current_password: currentPassword
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('stage1').style.display = 'none';
                document.getElementById('stage2').style.display = 'block';
            } else {
                document.querySelector('#stage1 .error-message').style.display = 'block';
            }
        })
        .catch(error => console.error('Error:', error));
    });

    document.getElementById('changePasswordForm').addEventListener('submit', function(event) {
        event.preventDefault();
        const newPassword = event.target.new_password.value;
        const confirmNewPassword = event.target.confirm_new_password.value;

        if (newPassword === confirmNewPassword) {
            fetch('verify_password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    new_password: newPassword,
                    confirm_password: confirmNewPassword
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    document.getElementById('stage2').style.display = 'none';
                    document.getElementById('stage3').style.display = 'block';
                } else {
                    document.querySelector('#stage2 .error-message').style.display = 'block';
                }
            })
            .catch(error => console.error('Error:', error));
        } else {
            document.querySelector('#stage2 .error-message').style.display = 'block';
        }
    });
});

document.addEventListener("DOMContentLoaded", function() {
    var togglePasswordIcons = document.querySelectorAll(".toggle-password");

    togglePasswordIcons.forEach(function(icon) {
        icon.addEventListener("click", function() {
            var passwordInput = this.previousElementSibling;
            var type = passwordInput.getAttribute("type") === "password" ? "text" : "password";
            passwordInput.setAttribute("type", type);

            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    });
});

let reviewIdToDelete = null;

function toggleDropdown(element) {
    const dropdowns = document.getElementsByClassName("dropdown-menu");
    Array.from(dropdowns).forEach(dropdown => {
        if (dropdown !== element.nextElementSibling) {
            dropdown.style.display = 'none';
        }
    });
    const dropdown = element.nextElementSibling;
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
}

function showDeleteModal(reviewId) {
    reviewIdToDelete = reviewId;
    const reviewElement = document.getElementById('review-' + reviewId);
    const reviewClone = reviewElement.cloneNode(true);

    const dropdownMenu = reviewClone.querySelector('.review-options');
    if (dropdownMenu) {
        dropdownMenu.remove();
    }

    const reviewContentDiv = document.getElementById('reviewContent');
    reviewContentDiv.innerHTML = '';
    reviewContentDiv.appendChild(reviewClone);

    document.getElementById('deleteModal').style.display = 'block';
}

function deleteReview(reviewId) {
    fetch('delete_review.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `review_id=${reviewId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            document.getElementById('deleteModal').style.display = 'none';
            document.getElementById('successMessage').style.display = 'block';
            setTimeout(() => {
                document.getElementById('successMessage').style.display = 'none';
                location.reload();
            }, 1000);
        } else {
            alert('Error deleting review: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting review');
    });
}

document.addEventListener('click', function(event) {
    if (!event.target.matches('.fas.fa-ellipsis-h')) {
        var dropdowns = document.getElementsByClassName("dropdown-menu");
        for (var i = 0; i < dropdowns.length; i++) {
            var openDropdown = dropdowns[i];
            if (openDropdown.style.display === 'block') {
                openDropdown.style.display = 'none';
            }
        }
    }
});

document.querySelector('.delete-cancel').addEventListener('click', function() {
    document.getElementById('deleteModal').style.display = 'none';
});

function toggleEditMode(reviewId) {
    const reviewElement = document.getElementById('review-' + reviewId);
    const starsDisplay = reviewElement.querySelector('.stars-display');
    const starsEdit = reviewElement.querySelector('.stars-edit');
    const reviewText = reviewElement.querySelector('.review-text');
    const editReviewText = reviewElement.querySelector('.edit-review-text');
    const editButtons = reviewElement.querySelector('.edit-buttons');
    const dropdown = reviewElement.querySelector('.dropdown-menu');

    if (dropdown) dropdown.style.display = 'none';

    if (reviewText.style.display !== 'none') {
        starsDisplay.style.display = 'none';
        starsEdit.style.display = 'block';
        reviewText.style.display = 'none';
        editReviewText.style.display = 'block';
        editButtons.style.display = 'block';
    }
}

function cancelEdit(reviewId) {
    const reviewElement = document.getElementById('review-' + reviewId);
    const starsDisplay = reviewElement.querySelector('.stars-display');
    const starsEdit = reviewElement.querySelector('.stars-edit');
    const reviewText = reviewElement.querySelector('.review-text');
    const editReviewText = reviewElement.querySelector('.edit-review-text');
    const editButtons = reviewElement.querySelector('.edit-buttons');

    starsDisplay.style.display = 'block';
    starsEdit.style.display = 'none';
    reviewText.style.display = 'block';
    editReviewText.style.display = 'none';
    editButtons.style.display = 'none';
    
    editReviewText.value = reviewText.innerText.trim();
}

function saveReview(reviewId) {
    const reviewElement = document.getElementById('review-' + reviewId);
    const newText = reviewElement.querySelector('.edit-review-text').value.trim();
    const newRating = reviewElement.querySelector('.stars-edit').getAttribute('data-rating');

    if (!newText) {
        alert('Review text cannot be empty');
        return;
    }

    fetch('edit_review.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `review_id=${reviewId}&review_text=${encodeURIComponent(newText)}&rating=${newRating}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            const reviewText = reviewElement.querySelector('.review-text');
            reviewText.innerHTML = newText;
            
            const starsDisplay = reviewElement.querySelector('.stars-display');
            starsDisplay.innerHTML = generateStars(newRating);
            starsDisplay.setAttribute('data-rating', newRating);
            
            if (!reviewElement.querySelector('small')) {
                const editedIndicator = document.createElement('small');
                editedIndicator.textContent = '(edited)';
                reviewElement.querySelector('.review-content').appendChild(editedIndicator);
            }
            
            cancelEdit(reviewId);
            
            const successMsg = document.createElement('div');
            successMsg.className = 'success-message';
            successMsg.textContent = 'Review updated successfully';
            reviewElement.appendChild(successMsg);
            setTimeout(() => successMsg.remove(), 2000);
        } else {
            alert('Error saving review: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving review');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const starContainers = document.querySelectorAll('.star-rating-display.editable');
    
    starContainers.forEach(container => {
        const stars = container.querySelectorAll('.star');
        stars.forEach((star, index) => {
            star.addEventListener('click', function() {
                const rating = index + 1;
                container.setAttribute('data-rating', rating);
                updateStars(container, rating);
            });
            
            star.addEventListener('mouseover', function() {
                updateStars(container, index + 1, true);
            });
            
            star.addEventListener('mouseout', function() {
                const currentRating = container.getAttribute('data-rating');
                updateStars(container, currentRating);
            });
        });
    });
});

function updateStars(container, rating, isHover = false) {
    const stars = container.querySelectorAll('.star');
    stars.forEach((star, index) => {
        if (index < rating) {
            star.classList.add('filled');
            if (isHover) star.classList.add('hover');
        } else {
            star.classList.remove('filled');
            if (isHover) star.classList.remove('hover');
        }
    });
}

function generateStars(rating) {
    let html = '<div class="star-rating-display">';
    for (let i = 1; i <= 5; i++) {
        html += `<span class="star${i <= rating ? ' filled' : ''}">★</span>`;
    }
    html += '</div>';
    return html;
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

function toggleProfileDropdown(element) {
    const allDropdowns = document.querySelectorAll('.dropdown-menu');
    allDropdowns.forEach(dropdown => {
        if (dropdown !== element.nextElementSibling) {
            dropdown.style.display = 'none';
        }
    });

    const dropdown = element.nextElementSibling;
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
}


function showAppealModal(contentType, contentId) {
    const contentTypeInput = document.getElementById('appealContentType');
    const contentIdInput = document.getElementById('appealContentId');
    const textarea = document.getElementById('appeal_reason');
    const modal = document.getElementById('AppealModal');

    if (!contentTypeInput || !contentIdInput || !textarea || !modal) {
        console.error('Required modal elements not found');
        return;
    }

    contentTypeInput.value = contentType;
    contentIdInput.value = contentId;
    
    if (contentType === 'User') {
        textarea.placeholder = "Please explain in detail why your account should be unflagged...";
    } else {
        textarea.placeholder = `Please explain in detail why your ${contentType.toLowerCase()} should be unflagged...`;
    }
    
    modal.style.display = 'block';
}

function closeAppealModal() {
    document.getElementById('AppealModal').style.display = 'none';
    document.getElementById('appealForm').reset();
}

function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.id = `${type}Alert`;
    notification.className = `alert alert-${type} popup-notification`;
    notification.innerHTML = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.display = 'block';
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }, 100);
}

document.getElementById('appealForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('submit_appeal.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Appeal submitted successfully', 'success');
            closeAppealModal();
            setTimeout(() => {
                location.reload();
            }, 3000);
        } else {
            showNotification(data.message || 'Failed to submit appeal', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while submitting the appeal', 'error');
    });
});
</script>
</body>
</html>
