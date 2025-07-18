<?php
require_once "config.php";

// Check if a session is already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


if (!isset($_GET['email'])) {
    die("Error: email is not set or invalid.");
}

$email = $_GET['email'];

// Fetch user details
$query = "SELECT * FROM ff_users WHERE email = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    die("Query failed: " . $stmt->error);
}

$user = $result->fetch_assoc();

if (!$user) {
    die("Error: User not found.");
}

// Fetch reviews made by the user
$user_reviews_query = "SELECT r.*, l.name as location_name, u.profile_picture, u.fullname, r.flagged, r.flag_reason 
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
    // Format the created_at date to display only the date
    $date = new DateTime($review['created_at']);
    $review['formatted_date'] = $date->format('Y-m-d');

    $user_reviews[] = $review;
}

mysqli_close($conn);

function displayStars($rating) {
    $output = '<div class="star-rating-display">';
    
    // Loop to display full stars
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $output .= '<span class="star filled">★</span>';  // Filled star for the rating
        } else {
            $output .= '<span class="star">★</span>';  // Empty star
        }
    }

    $output .= '</div>';
    return $output;
}
?>

<div class="profile-menu <?php echo $user['flagged'] ? 'flagged-profile' : ''; ?>">
    <?php if ($user['flagged']): ?>
        <p class="profile-flag-message">Flagged for <?php echo htmlspecialchars($user['flag_reason']); ?></p>
    <?php endif; ?>
            <div class="profile-options">
    <i class="fas fa-ellipsis-h" onclick="toggleProfileDropdown(this)"></i>
    <div class="dropdown-menu">
        <?php if (isset($_SESSION['type']) && $_SESSION['type'] == 'moderator'): ?>
            <?php if ($user['flagged']): ?>
                <a href="#" onclick="showUnflagProfileModal(); return false;">Unflag Profile</a>
            <?php else: ?>
                <a href="#" onclick="showFlagProfileModal(); return false;">Flag Profile</a>
            <?php endif; ?>
            <?php elseif (isset($_SESSION['email'])): ?>
                <a href="#" onclick="showReportModal('User', '<?php echo $user['email']; ?>', document.querySelector('.profile-header').innerHTML); return false;">Report Profile</a>
            <?php endif; ?>
            </div>
        </div>
    <div class="profile-header">
        <form>
        <div class="profile-picture-container">
            <img src="<?php echo isset($user['profile_picture']) ? $user['profile_picture'] : ''; ?>" alt="Profile Picture" class="profile-picture">
        </div>
        <div class="profile-info">
            <div class="profile-info-text">
                <h1><?php echo isset($user['fullname']) ? $user['fullname'] : ''; ?></h1>
                <p><?php echo isset($user['profbio']) ? $user['profbio'] : "No bio available."; ?></p> 
            </div> 
        </div>
    </div>

    <div class="profile-details">
        <h3>Profile Details</h3>
        <ul>
            <li class="profile-detail-box">
                <label>Name:</label>
                <p><?php echo $user['fullname']; ?></p>
            </li>
            <li class="profile-detail-box">
                <label>Email:</label>
                <p><?php echo $user['email']; ?></p>
            </li>
            <li class="profile-detail-box">
                <label>School:</label>
                <p><?php echo $user['school']; ?></p>
            </li>
            <li class="profile-detail-box">
                <label>Course:</label>
                <p><?php echo $user['course']; ?></p>
            </li>
            <li class="profile-detail-box">
                <label>Year Level:</label>
                <p><?php echo $user['year_level']; ?></p>
            </li>
            <li class="profile-detail-box">
                <label>Date of Birth:</label>
                <p><?php echo $user['birthday']; ?></p>
            </li>
            <li class="profile-detail-box">
                <label>Contact:</label>
                <p><?php echo $user['contact']; ?></p>
            </li>
        </ul>
        </form>
    </div>
</div>

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
                        <?php if (isset($_SESSION['type']) && $_SESSION['type'] == 'moderator'): ?>
                            <?php if ($review['flagged']): ?>
                                <a href="#" onclick="showUnflagModal(<?php echo $review['id']; ?>); return false;">Unflag</a>
                            <?php else: ?>
                                <a href="#" onclick="showFlagModal(<?php echo $review['id']; ?>); return false;">Flag</a>
                            <?php endif; ?>
                            <a href="#" onclick="showDeleteModal(<?php echo $review['id']; ?>); return false;">Delete</a>
                        <?php endif; ?>
                        <?php if (!isset($_SESSION['type']) || $_SESSION['type'] != 'moderator'): ?>
                            <a href="#" onclick="showReportModal('review', '<?php echo $review['id']; ?>', this.closest('.review').innerHTML); return false;">Report</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="review-header">
                    <div class="user-info">
                        <img src="<?php echo htmlspecialchars($review['profile_picture']); ?>" alt="<?php echo htmlspecialchars($review['fullname']); ?>" class="profile-pic">
                        <h4><?php echo htmlspecialchars($review['fullname']); ?></h4>
                    </div>
                    <div class="review-stars">
                        <?php echo displayStars($review['rating']); ?>
                    </div>
                </div>
                <p><?php echo htmlspecialchars($review['review_text']); ?></p>
                <?php if ($review['edited']): ?>
                    <small>(edited)</small>
                <?php endif; ?>
                <small>Posted on: <?php echo $review['formatted_date']; ?></small>
                <a href="<?php echo (isset($_SESSION['type']) && $_SESSION['type'] == 'moderator') ? 'modLocation.php' : 'userLocation.php'; ?>?location_id=<?php echo $review['location_id']; ?>" class="location-link">
                    View Location: <?php echo htmlspecialchars($review['location_name']); ?>
                </a>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No reviews have been made yet.</p>
    <?php endif; ?>
</div>

<!-- Universal Report Modal -->
<div id="reportModal" class="report-modal">
    <div class="report-modal-content">
        <h2>Report Content</h2>
        <div class="content-preview">
            <!-- Dynamic content preview here -->
        </div>
        <form id="reportForm">
            <input type="hidden" name="content_type">
            <input type="hidden" name="content_id">
            <div class="form-group">
                <label for="reportReason">Reason:</label>
                <select name="reason" required class="form-control">
                    <option value="">Select a reason</option>
                    <option value="Inappropriate">Inappropriate Content</option>
                    <option value="Spam">Spam</option>
                    <option value="Harassment">Harassment</option>
                    <option value="False Information">False Information</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label for="reportDescription">Details:</label>
                <textarea name="description" required class="form-control"></textarea>
            </div>
            <div class="modal-buttons">
                <button type="submit" class="btn btn-danger">Submit Report</button>
                <button type="button" class="btn btn-secondary report-cancel">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div id="flagProfileModal" class="flag-modal">
    <div class="flag-modal-content">
        <p>Are you sure you want to flag this profile?</p>
        <div id="flagProfileContent" class="profile-preview">
            <img src="<?php echo $user['profile_picture']; ?>" alt="Profile Picture" class="profile-picture">
            <h1><?php echo $user['fullname']; ?></h1>
            <p><?php echo $user['profbio']; ?></p>
        </div>
        <label for="flagProfileReason" class="flag-label">Reason:</label>
        <select id="flagProfileReason" class="flag-select">
            <option value="">-</option>
            <option value="Fake Account">Fake Account</option>
            <option value="Inappropriate">Inappropriate Content</option>
            <option value="Spam">Spam</option>
            <option value="Harassment">Harassment</option>
            <option value="False Information">False Information</option>
            <option value="Other">Other</option>
        </select>
        <button id="confirmFlagProfileBtn" class="btn btn-danger" onclick="flagProfile('<?php echo $user['email']; ?>')">Yes, Flag</button>
        <button type="button" class="btn btn-secondary flag-cancel">Cancel</button>
    </div>
</div>

<div id="unflagProfileModal" class="unflag-modal">
    <div class="unflag-modal-content">
        <h2>Unflag Profile</h2>
        <p>Are you sure you want to unflag this profile?</p>
        <div id="unflagProfileContent" class="profile-preview">
            <img src="<?php echo $user['profile_picture']; ?>" alt="Profile Picture" class="profile-picture">
            <h1><?php echo $user['fullname']; ?></h1>
            <p><?php echo $user['profbio']; ?></p>
        </div>
        <div class="unflag-buttons">
            <button id="confirmUnflagProfileBtn" class="btn btn-danger" onclick="unflagProfile('<?php echo $user['email']; ?>')">Yes, Unflag</button>
            <button type="button" class="btn btn-secondary unflag-cancel">Cancel</button>
        </div>
    </div>
</div>

<div id="deleteModal" class="delete-modal">
    <div class="delete-modal-content">
        <p>Are you sure you want to delete this review?</p>
        <div id="reviewContent" class="review-content">
            <!-- Review content will be dynamically inserted here -->
        </div>
        <button id="confirmDeleteBtn" class="btn btn-danger" onclick="deleteReview(reviewIdToDelete)">Yes, Delete</button>
        <button type="button" class="btn btn-secondary delete-cancel">Cancel</button>
    </div>
</div>

<div id="flagModal" class="flag-modal">
    <div class="flag-modal-content">
        <p>Are you sure you want to flag this review?</p>
        <div id="flagReviewContent" class="review-content">
            <!-- Review content will be dynamically inserted here -->
        </div>
        <label for="flagReason" class="flag-label">Reason:</label>
        <select id="flagReason" class="flag-select">
            <option value="">-</option>
            <option value="Misinformation">Misinformation</option>
            <option value="False Review">False Review</option>
            <option value="Inappropriate">Inappropriate Content</option>
            <option value="Spam">Spam</option>
            <option value="Harassment">Harassment</option>
            <option value="Other">Other</option>
        </select>
        <button id="confirmFlagBtn" class="btn btn-danger" onclick="flagReview(reviewIdToFlag)">Yes, Flag</button>
        <button type="button" class="btn btn-secondary flag-cancel">Cancel</button>
    </div>
</div>

<div id="unflagModal" class="unflag-modal">
    <div class="unflag-modal-content">
        <h2>Unflag Review</h2>
        <p>Are you sure you want to unflag this review?</p>
        <div id="unflagReviewContent" class="review-content">
            <!-- Review content will be dynamically inserted here -->
        </div>
        <div class="unflag-buttons">
            <button id="confirmUnflagBtn" class="btn btn-danger" onclick="unflagReview(reviewIdToUnflag)">Yes, Unflag</button>
            <button type="button" class="btn btn-secondary unflag-cancel">Cancel</button>
        </div>
    </div>
</div>


<script>
function toggleMenu() {
    var subMenu = document.getElementById("subMenu");
    subMenu.classList.toggle("open-menu");
}

let reviewIdToDelete = null;
let reviewIdToFlag = null;
let reviewIdToUnflag = null;

function toggleDropdown(element) {
    var dropdown = element.nextElementSibling;
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
}

window.onclick = function(event) {
    if (!event.target.matches('.fas.fa-ellipsis-h')) {
        var dropdowns = document.getElementsByClassName("dropdown-menu");
        for (var i = 0; i < dropdowns.length; i++) {
            var openDropdown = dropdowns[i];
            if (openDropdown.style.display === 'block') {
                openDropdown.style.display = 'none';
            }
        }
    }
}

// Add this at the top of your script
const isLoggedIn = <?php echo isset($_SESSION['email']) ? 'true' : 'false'; ?>;

function showReportModal(contentType, contentId, contentPreview) {
    if (!isLoggedIn) {
        window.location.href = 'login.php';
        return;
    }

    <?php if (isset($_SESSION['flagged']) && $_SESSION['flagged']): ?>
        const flaggedNotification = document.createElement('div');
        flaggedNotification.id = 'flaggedAlert';
        flaggedNotification.className = 'alert alert-warning popup-notification';
        flaggedNotification.innerHTML = 'You cannot report content while your account is flagged.';
        document.body.appendChild(flaggedNotification);
        
        // Show and fade out
        setTimeout(() => {
            flaggedNotification.style.display = 'block';
            setTimeout(() => {
                flaggedNotification.remove();
            }, 3000);
        }, 100);
        return;
    <?php endif; ?>
    
    const modal = document.getElementById('reportModal');
    const previewDiv = modal.querySelector('.content-preview');
    const form = document.getElementById('reportForm');
    
    // Reset form
    form.reset();
    
    // Create a temporary container
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = contentPreview;
    
    // Remove review options (ellipsis and dropdown)
    const reviewOptions = tempDiv.querySelector('.review-options');
    if (reviewOptions) {
        reviewOptions.remove();
    }
    
    // Update hidden fields
    form.querySelector('[name="content_type"]').value = contentType;
    form.querySelector('[name="content_id"]').value = contentId;
    
    // Update preview with cleaned content
    previewDiv.innerHTML = tempDiv.innerHTML;
    
    // Show modal
    modal.style.display = 'block';
}

document.querySelector('.report-cancel').onclick = function() {
    document.getElementById('reportModal').style.display = 'none';
}

// Universal report submission handler
document.getElementById('reportForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    <?php if (isset($_SESSION['flagged']) && $_SESSION['flagged']): ?>
        const flaggedNotification = document.createElement('div');
        flaggedNotification.id = 'flaggedAlert';
        flaggedNotification.className = 'alert alert-warning popup-notification';
        flaggedNotification.innerHTML = 'You cannot report content while your account is flagged.';
        document.body.appendChild(flaggedNotification);
        
        setTimeout(() => {
            flaggedNotification.style.display = 'block';
            setTimeout(() => {
                flaggedNotification.remove();
            }, 3000);
        }, 100);
        return;
    <?php endif; ?>

    fetch('report_content.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(new FormData(this))
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const successNotification = document.createElement('div');
            successNotification.id = 'successAlert';
            successNotification.className = 'alert alert-success popup-notification';
            successNotification.innerHTML = 'Report submitted successfully';
            document.body.appendChild(successNotification);
            
            setTimeout(() => {
                successNotification.style.display = 'block';
                setTimeout(() => {
                    successNotification.remove();
                }, 3000);
            }, 100);

            document.getElementById('reportModal').style.display = 'none';
        } else {
            alert('Error: ' + (data.message || 'Failed to submit report'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while submitting the report');
    });
});

function showDeleteModal(reviewId) {
    reviewIdToDelete = reviewId;
    const reviewElement = document.getElementById('review-' + reviewId);
    const reviewClone = reviewElement.cloneNode(true);

    // Remove the ellipsis element
    const ellipsisElement = reviewClone.querySelector('.fas.fa-ellipsis-h');
    if (ellipsisElement) {
        ellipsisElement.remove();
    }

    const reviewContentDiv = document.getElementById('reviewContent');
    reviewContentDiv.innerHTML = '';
    reviewContentDiv.appendChild(reviewClone);

    document.getElementById('deleteModal').style.display = 'block';
}

function showFlagModal(reviewId) {
    reviewIdToFlag = reviewId;
    const reviewElement = document.getElementById('review-' + reviewId);
    const reviewClone = reviewElement.cloneNode(true);

    const ellipsisElement = reviewClone.querySelector('.fas.fa-ellipsis-h');
    if (ellipsisElement) {
        ellipsisElement.remove();
    }

    const reviewContentDiv = document.getElementById('flagReviewContent');
    reviewContentDiv.innerHTML = '';
    reviewContentDiv.appendChild(reviewClone);

    document.getElementById('flagModal').style.display = 'block';
}

function showUnflagModal(reviewId) {
    reviewIdToUnflag = reviewId;
    const reviewElement = document.getElementById('review-' + reviewId);
    const reviewClone = reviewElement.cloneNode(true);

    const reviewContentDiv = document.getElementById('unflagReviewContent');
    reviewContentDiv.innerHTML = '';
    reviewContentDiv.appendChild(reviewClone);

    document.getElementById('unflagModal').style.display = 'block';
}

function deleteReview(reviewId) {
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "delete_review.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === XMLHttpRequest.DONE) {
            if (xhr.status === 200) {
                location.reload();
            }
        }
    };
    xhr.send("review_id=" + reviewId);
}

function flagReview(reviewId) {
    const reason = document.getElementById('flagReason').value;
    if (!reason) {
        alert('Please select a reason.');
        return;
    }

    var xhr = new XMLHttpRequest();
    xhr.open("POST", "flag_review.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === XMLHttpRequest.DONE) {
            if (xhr.status === 200) {
                location.reload();
            }
        }
    };
    xhr.send("id=" + reviewId + "&reason=" + encodeURIComponent(reason));
}

function unflagReview(reviewId) {
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "unflag_review.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === XMLHttpRequest.DONE) {
            if (xhr.status === 200) {
                location.reload();
            }
        }
    };
    xhr.send("id=" + reviewId);
}


function toggleProfileDropdown(element) {
    var dropdown = element.nextElementSibling;
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
}

function showFlagProfileModal() {
    document.getElementById('flagProfileModal').style.display = 'block';
}

function showUnflagProfileModal() {
    document.getElementById('unflagProfileModal').style.display = 'block';
}

function flagProfile(email) {
    const reason = document.getElementById('flagProfileReason').value;
    if (!reason) {
        alert('Please select a reason.');
        return;
    }

    var xhr = new XMLHttpRequest();
    xhr.open("POST", "flag_profile.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === XMLHttpRequest.DONE) {
            if (xhr.status === 200) {
                location.reload();
            }
        }
    };
    xhr.send("email=" + encodeURIComponent(email) + "&reason=" + encodeURIComponent(reason));
}

function unflagProfile(email) {
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "unflag_profile.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === XMLHttpRequest.DONE) {
            if (xhr.status === 200) {
                location.reload();
            }
        }
    };
    xhr.send("email=" + encodeURIComponent(email));
}

// Add event listeners for the Cancel buttons
document.querySelectorAll('.btn.btn-secondary.delete-cancel').forEach(button => {
    button.addEventListener('click', () => closeModal('deleteModal'));
});

document.querySelectorAll('.btn.btn-secondary.flag-cancel').forEach(button => {
    button.addEventListener('click', () => closeModal('flagModal'));
});

document.querySelectorAll('.btn.btn-secondary.unflag-cancel').forEach(button => {
    button.addEventListener('click', () => closeModal('unflagModal'));
});

document.querySelectorAll('.btn.btn-secondary.report-cancel').forEach(button => {
    button.addEventListener('click', () => closeModal('reportReviewModal'));
});

document.querySelectorAll('.btn.btn-secondary.flag-cancel').forEach(button => {
    button.addEventListener('click', () => {
        document.getElementById('flagProfileModal').style.display = 'none';
    });
});

document.querySelectorAll('.btn.btn-secondary.unflag-cancel').forEach(button => {
    button.addEventListener('click', () => {
        document.getElementById('unflagProfileModal').style.display = 'none';
    });
});

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}
</script>