<?php
require_once "config.php";
require_once "average_rating.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$location_id = isset($_GET['location_id']) ? $_GET['location_id'] : null;

if (!$location_id) {
    die("Error: location_id is not set or invalid.");
}

$is_logged_in = isset($_SESSION['user_id']);

$location = null;
$reviews = [];

$reviews_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $reviews_per_page;

$query = $conn->prepare("SELECT * FROM study_locations WHERE id = ?");
$query->bind_param("i", $location_id);
$query->execute();
$result = $query->get_result();

if ($result) {
    $location = $result->fetch_assoc();
    $result->free();
} else {
    die("Error: Could not retrieve location details. " . $conn->error);
}

$total_reviews = 0;
$average_rating = getAverageRating($conn, $location_id);

$query = $conn->prepare("SELECT COUNT(*) as total_reviews FROM reviews WHERE location_id = ?");
$query->bind_param("i", $location_id);
$query->execute();
$result = $query->get_result();

$review_summary = $result->fetch_assoc();
$total_reviews = $review_summary['total_reviews'];
$total_pages = ceil($total_reviews / $reviews_per_page);

$query = $conn->prepare("SELECT r.*, 
    (SELECT COUNT(*) FROM review_votes WHERE review_id = r.id AND vote_type = 'upvote') as upvotes,
    (SELECT COUNT(*) FROM review_votes WHERE review_id = r.id AND vote_type = 'downvote') as downvotes
    FROM reviews r WHERE location_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$query->bind_param("iii", $location_id, $reviews_per_page, $offset);
$query->execute();
$result = $query->get_result();

if ($result) {
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $user_query = $conn->prepare("SELECT fullname, profile_picture FROM ff_users WHERE email = ?");
            $user_query->bind_param("s", $row['email']);
            $user_query->execute();
            $user_result = $user_query->get_result();

            if ($user_result && $user_result->num_rows > 0) {
                $user_info = $user_result->fetch_assoc();
                $row['fullname'] = $user_info['fullname'];
                $row['profile_picture'] = $user_info['profile_picture'];
            } else {
                $row['fullname'] = 'Unknown User';
                $row['profile_picture'] = 'images/pfp.png';
            }

            $date = new DateTime($row['created_at']);
            $row['formatted_date'] = $date->format('Y-m-d');

            $reviews[] = $row;
        }
    }
    $result->free();
} else {
    die("Error: Could not retrieve reviews. " . $conn->error);
}


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


<?php if ($location): ?>
    <div class="location-details-container">
        <div class="location-info">
            <h2><?php echo htmlspecialchars($location['name']); ?></h2> 
            <p><strong>Tags:</strong> <?php echo htmlspecialchars($location['tags']); ?></p>
            <p><strong>Time:</strong> <?php echo htmlspecialchars($location['time']); ?></p>
            <?php if ($location['min_price'] > 0 || $location['max_price'] > 0): ?>
                <p><strong>Price Range:</strong> ₱<?php echo number_format($location['min_price'], 2); ?> - ₱<?php echo number_format($location['max_price'], 2); ?></p>
            <?php endif; ?>
            <p><?php echo htmlspecialchars($location['description']); ?></p>
        </div>

        <div class="location-map">
            <img src="<?php echo htmlspecialchars($location['image_url']); ?>" alt="<?php echo htmlspecialchars($location['name']); ?>">
            <!-- Display the rating dynamically below the image -->
            <p>
                Rating (<?php echo $average_rating; ?> / 5.0, <?php echo $total_reviews; ?> reviews)
            </p>
        </div>
    </div>


    <div class="menu-images">
    <?php
        function getShopMenuImages($shopName) {
            $folder_name = str_replace(['/', '\\', '?', '*', ':', '|', '"', '<', '>'], '', $shopName);
            $path = "menus/" . $folder_name;
            $images = [];
            
            if (is_dir($path)) {
                $files = scandir($path);
                foreach ($files as $file) {
                    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif']) && $file != '.' && $file != '..') {
                        $images[] = $path . '/' . $file;
                    }
                }
            }
            return $images;
        }

        $menuImages = getShopMenuImages($location['name']);
        if (!empty($menuImages)): ?>
            <div class="slider-container menu-slider">
                <button class="slider-arrow prev" onclick="slideMenuImages('prev')">&lt;</button>
                <div class="menu-gallery">
                    <?php foreach ($menuImages as $index => $image): ?>
                        <div class="menu-gallery-item <?php echo $index < 2 ? 'active' : ''; ?>">
                            <img src="<?php echo htmlspecialchars($image); ?>" 
                                alt="Menu Image" 
                                onclick="showImage(this.src)">
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="slider-arrow next" onclick="slideMenuImages('next')">&gt;</button>
            </div>
    <?php endif; ?>
</div>

<div class="location-images">
    <h3>Location Images</h3>
    <?php
        function getLocationImages($locationName) {
            error_log("Looking for images in location: " . $locationName);
            $path = "locations/" . $locationName;
            error_log("Path being checked: " . $path);
            
            $images = [];
            if (is_dir($path)) {
                $files = scandir($path);
                foreach ($files as $file) {
                    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif']) && $file != '.' && $file != '..') {
                        $images[] = $path . '/' . $file;
                    }
                }
                error_log("Found " . count($images) . " images");
            } else {
                error_log("Directory not found: " . $path);
            }
            return $images;
        }

        $locationImages = getLocationImages($location['name']);
        if (!empty($locationImages)): ?>
           <div class="slider-container">
            <button class="slider-arrow prev" onclick="slideImages('prev')">&lt;</button>
            <div class="location-gallery">
                <?php foreach ($locationImages as $index => $image): ?>
                    <div class="gallery-item <?php echo $index < 3 ? 'active' : ''; ?>">
                        <img src="<?php echo htmlspecialchars($image); ?>" 
                            alt="<?php echo htmlspecialchars($location['name']); ?>" 
                            onclick="showImage(this.src)">
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="slider-arrow next" onclick="slideImages('next')">&gt;</button>
        </div>
    <?php endif; ?>
</div>


<div class="location-form-box">
      <button type="button" class="location-btn" onclick="expandMap()">Get Direction</button>
    </div>

    <div style="display: flex; justify-content: center;">
      <div class="map-box" id="map-container">
      <?php 
    $_SESSION['initial_lat'] = $location['latitude'];
    $_SESSION['initial_lng'] = $location['longitude'];

    include 'location_map.php';
    ?>
        <button class="close-button" id="close-button" onclick="minimizeMap()" style="display: none;">X</button>
        <button id="toggleRoutingControl" style="display: none;">
            <i class="fas fa-directions"></i>
        </button>
          <div id="route-table">
            <!-- Route table content here -->
          </div>
        <button id="toggleJeepRoutes" style="display: none;">Show Jeep Routes</button>
        <div id="routeControls" class="route-controls"></div>
      </div>
    </div>
  </div>

<div id="imageModal" class="image-modal">
    <span class="close">&times;</span>
    <img class="image-modal-content" id="modalImage">
</div>

<div id="successMessage" class="success-message" style="display: none;">
    Review deleted successfully.
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

<div id="reportModal" class="report-modal">
    <div class="report-modal-content">
        <h2>Report Content</h2>
        <div class="content-preview">
            <!-- Dynamic content preview here -->
        </div>
        <form id="reportForm">
            <input type="hidden" name="content_type" value="">
            <input type="hidden" name="content_id" value="">
            <input type="hidden" name="location_id" value="<?php echo $location_id; ?>">
            <div class="form-group">
                <label for="reportReason">Reason:</label>
                <select name="reason" required class="form-control">
                    <option value="">Select a reason</option>
                    <option value="Misinformation">Misinformation</option>
                    <option value="False Review">False Review</option>
                    <option value="Inappropriate">Inappropriate Content</option>
                    <option value="Spam">Spam</option>
                    <option value="Harassment">Harassment</option>
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

    <div class="reviews-section">
    <h3>Reviews</h3>
    <?php if (!empty($reviews)): ?>
        <?php foreach ($reviews as $review): ?>
            <div class="review <?php echo $review['flagged'] ? 'flagged-review' : ''; ?>" id="review-<?php echo $review['id']; ?>">
            <div>
                <?php if ($review['flagged']): ?>
                    <p class="flag-status" style="color: red;">Flagged for <?php echo htmlspecialchars($review['flag_reason']); ?></p>
                <?php endif; ?>
            </div>
            <div class="review-options">
                <i class="fas fa-ellipsis-h" onclick="toggleDropdown(this)"></i>
                <div class="dropdown-menu">
                    <?php if (isset($_SESSION['email']) && $review['email'] == $_SESSION['email']): ?>
                        <?php if ($review['flagged']): ?>
                            <a href="#" onclick="showAppealModal('Review', <?php echo $review['id']; ?>); return false;">Appeal Unflag</a>
                        <?php else: ?>
                            <a href="#" onclick="toggleEditMode(<?php echo $review['id']; ?>); return false;">Edit</a>
                        <?php endif; ?>
                        <a href="#" onclick="showDeleteModal(<?php echo $review['id']; ?>); return false;">Delete</a>
                    <?php elseif (isset($_SESSION['type']) && $_SESSION['type'] == 'moderator'): ?>
                        <?php if ($review['flagged']): ?>
                            <a href="#" onclick="showUnflagModal(<?php echo $review['id']; ?>); return false;">Unflag</a>
                        <?php else: ?>
                            <a href="#" onclick="showFlagModal(<?php echo $review['id']; ?>); return false;">Flag</a>
                        <?php endif; ?>
                        <a href="#" onclick="showDeleteModal(<?php echo $review['id']; ?>); return false;">Delete</a>
                    <?php else: ?>
                        <a href="#" onclick="showReportModal('review', '<?php echo $review['id']; ?>', document.querySelector('.review').innerHTML); return false;">Report</a>
                    <?php endif; ?>
                </div>
            </div>
                <div class="review-header">
                    <div class="user-info">
                        <img src="<?php echo htmlspecialchars($review['profile_picture']); ?>" alt="<?php echo htmlspecialchars($review['fullname']); ?>" class="profile-pic">
                        <?php if (isset($_SESSION['email']) && $review['email'] == $_SESSION['email']): ?>
                            <a href="userProfile.php"><h4><?php echo htmlspecialchars($review['fullname']); ?></h4></a>
                            <?php else: ?>
                                <?php if (isset($_SESSION['type']) && $_SESSION['type'] == 'moderator'): ?>
                                    <a href="modotherProfile.php?email=<?php echo urlencode($review['email']); ?>"><h4><?php echo htmlspecialchars($review['fullname']); ?></h4></a>
                                <?php else: ?>
                                    <a href="userotherProfile.php?email=<?php echo urlencode($review['email']); ?>"><h4><?php echo htmlspecialchars($review['fullname']); ?></h4></a>
                                <?php endif; ?>
                            <?php endif; ?>
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
                <div class="vote-buttons">
                    <button class="upvote" onclick="vote(<?php echo $review['id']; ?>, 'upvote')">
                        <i class="fas fa-arrow-up custom-arrow"></i>
                    </button>
                    <span><?php echo $review['upvotes']; ?></span>
                    <button class="downvote" onclick="vote(<?php echo $review['id']; ?>, 'downvote')">
                        <i class="fas fa-arrow-down custom-arrow"></i>
                    </button>
                    <span><?php echo $review['downvotes']; ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No reviews yet.</p>
    <?php endif; ?>
</div>

    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?location_id=<?php echo $location_id; ?>&page=<?php echo $page - 1; ?>">Previous</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?location_id=<?php echo $location_id; ?>&page=<?php echo $i; ?>" <?php if ($i == $page) echo 'class="active"'; ?>><?php echo $i; ?></a>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <a href="?location_id=<?php echo $location_id; ?>&page=<?php echo $page + 1; ?>">Next</a>
        <?php endif; ?>
    </div>

    <div class="leave-review-section">
        <h3>Write a Review</h3>
        <?php if (!isset($_SESSION['email'])): ?>
            <div class="alert alert-warning">
                You must be logged in to rate and post a review.
            </div>
        <?php elseif (isset($_SESSION['flagged']) && $_SESSION['flagged']): ?>
            <div class="alert alert-warning">
                You cannot submit reviews while your account is flagged.
            </div>
        <?php else: ?>
        <form id="review-form" action="submit_review.php" method="post">
            <input type="hidden" name="location_id" value="<?php echo htmlspecialchars($location['id']); ?>">
            <?php if (isset($_SESSION['fullname'])): ?>
                <input type="hidden" name="fullname" value="<?php echo htmlspecialchars($_SESSION['fullname']); ?>">
            <?php endif; ?>
            <div class="review-input-group">
                <label for="rating">Location Rating</label>
                <div class="star-rating">
                    <input type="radio" id="star5" name="rating" value="5"><label for="star5" title="5 stars">★</label>
                    <input type="radio" id="star4" name="rating" value="4"><label for="star4" title="4 stars">★</label>
                    <input type="radio" id="star3" name="rating" value="3"><label for="star3" title="3 stars">★</label>
                    <input type="radio" id="star2" name="rating" value="2"><label for="star2" title="2 stars">★</label>
                    <input type="radio" id="star1" name="rating" value="1"><label for="star1" title="1 star">★</label>
                </div>
            </div>
            <div class="review-input-group">
                <label for="comment">Review Summary</label>
                <textarea id="comment" name="comment" placeholder="Summarize your experience" required></textarea>
            </div>
            <button type="submit" class="btn">Submit</button>
            <div id="login-message" class="alert alert-warning popup-notification" style="display:none;">
                You must be logged in to rate and post a review.
            </div>
        <?php if (isset($_GET['review_submitted']) && $_GET['review_submitted'] == 'true'): ?>
            <p style="color: green;">Your review has been submitted successfully!</p>
        <?php endif; ?>
    </form>
    <?php endif; ?>
</div>
<?php else: ?>
<p>Location not found.</p>
<?php endif; ?>


<script>
    document.getElementById('review-form').addEventListener('submit', function(event) {
        <?php if (!isset($_SESSION['email'])): ?>
            event.preventDefault();
            document.getElementById('login-message').style.display = 'block';
        <?php else: ?>
            <?php if (isset($_SESSION['flagged']) && $_SESSION['flagged']): ?>
                event.preventDefault();
                alert('You cannot submit reviews while your account is flagged.');
            <?php endif; ?>
        <?php endif; ?>
    });

    function vote(reviewId, voteType) {
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "vote.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onreadystatechange = function() {
            if (xhr.readyState === XMLHttpRequest.DONE) {
                if (xhr.status === 200) {
                    console.log(xhr.responseText);
                    location.reload();
                } else {
                    console.error("Error: " + xhr.status);
                }
            }
        };
        xhr.send("review_id=" + reviewId + "&vote_type=" + voteType);
    }

    function editReview(reviewId) {
    document.getElementById('editReviewId').value = reviewId;
    document.getElementById('editReviewModal').style.display = 'block';
    }

    function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}


document.querySelector('.btn.btn-secondary.delete-cancel').addEventListener('click', function() {
    closeModal('deleteModal');
});


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
        });
    });

    function updateStars(container, rating) {
        const stars = container.querySelectorAll('.star');
        stars.forEach((star, index) => {
            if (index < rating) {
                star.classList.add('filled');
            } else {
                star.classList.remove('filled');
            }
        });
    }
});

function toggleEditMode(reviewId) {
    const reviewElement = document.getElementById('review-' + reviewId);
    const starsDisplay = reviewElement.querySelector('.stars-display');
    const starsEdit = reviewElement.querySelector('.stars-edit');
    const reviewText = reviewElement.querySelector('.review-text');
    const editReviewText = reviewElement.querySelector('.edit-review-text');
    const editButtons = reviewElement.querySelector('.edit-buttons');
    const stars = starsEdit.querySelectorAll('.star');

    if (starsEdit.style.display === 'none') {
        starsEdit.style.display = 'block';
        starsDisplay.style.display = 'none';
        reviewText.style.display = 'none';
        editReviewText.style.display = 'block';
        editButtons.style.display = 'block';
        stars.forEach(star => star.classList.add('editable'));
    } else {
        starsEdit.style.display = 'none';
        starsDisplay.style.display = 'block';
        reviewText.style.display = 'block';
        editReviewText.style.display = 'none';
        editButtons.style.display = 'none';
        stars.forEach(star => star.classList.remove('editable'));
    }
}

function cancelEdit(reviewId) {
    const reviewElement = document.getElementById('review-' + reviewId);
    const starsDisplay = reviewElement.querySelector('.stars-display');
    const starsEdit = reviewElement.querySelector('.stars-edit');
    const reviewText = reviewElement.querySelector('.review-text');
    const editReviewText = reviewElement.querySelector('.edit-review-text');
    const editButtons = reviewElement.querySelector('.edit-buttons');
    
    editReviewText.value = reviewText.innerText;
    
    const originalRating = starsDisplay.getAttribute('data-rating');
    starsEdit.setAttribute('data-rating', originalRating);
    
    starsEdit.style.display = 'none';
    starsDisplay.style.display = 'block';
    reviewText.style.display = 'block';
    editReviewText.style.display = 'none';
    editButtons.style.display = 'none';
}

function saveReview(reviewId) {
    var reviewElement = document.getElementById('review-' + reviewId);
    var editReviewText = reviewElement.querySelector('.edit-review-text').value;
    var rating = reviewElement.querySelector('.stars-edit').getAttribute('data-rating');

    var xhr = new XMLHttpRequest();
    xhr.open("POST", "edit_review.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === XMLHttpRequest.DONE) {
            if (xhr.status === 200) {
                var response = JSON.parse(xhr.responseText);
                if (response.status === 'success') {
                    location.reload();
                } else {
                    console.error("Error: " + response.message);
                }
            } else {
                console.error("Error: " + xhr.status);
            }
        }
    };
    xhr.send("review_id=" + reviewId + "&review_text=" + encodeURIComponent(editReviewText) + "&rating=" + rating);
}


let reviewIdToDelete = null;

function showDeleteModal(reviewId) {
    reviewIdToDelete = reviewId;
    const reviewElement = document.getElementById('review-' + reviewId);
    const reviewClone = reviewElement.cloneNode(true);

    const userNameLink = reviewClone.querySelector('a h4');
    if (userNameLink) {
        const userName = userNameLink.textContent;
        userNameLink.parentElement.outerHTML = `<h4>${userName}</h4>`;
    }

    const ellipsisElement = reviewClone.querySelector('.fas.fa-ellipsis-h');
    if (ellipsisElement) {
        ellipsisElement.remove();
    }

    const reviewContentDiv = document.getElementById('reviewContent');
    reviewContentDiv.innerHTML = '';
    reviewContentDiv.appendChild(reviewClone);

    const voteButtons = reviewClone.querySelectorAll('.vote-buttons button');
    voteButtons.forEach(button => {
        button.disabled = true;
        button.classList.add('disabled');
    });

    document.getElementById('deleteModal').style.display = 'block';
}

document.querySelector('.btn.btn-secondary.delete-cancel').addEventListener('click', function() {
    closeModal('deleteModal');
});

function deleteReview(reviewId) {
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "delete_review.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === XMLHttpRequest.DONE) {
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.status === 'success') {
                        document.getElementById('successMessage').style.display = 'block';
                        setTimeout(function() {
                            document.getElementById('successMessage').style.display = 'none';
                            location.reload();
                        }, 1000);
                    } else {
                        console.error("Error: " + response.message);
                    }
                } catch (e) {
                    console.error("Invalid JSON response: " + xhr.responseText);
                }
            } else {
                console.error("Error: " + xhr.status);
            }
        }
    };
    xhr.send("review_id=" + reviewId);
}


let reviewIdToFlag = null;

function showFlagModal(reviewId) {
    reviewIdToFlag = reviewId;
    const reviewElement = document.getElementById('review-' + reviewId);
    const reviewClone = reviewElement.cloneNode(true);

    const userNameLink = reviewClone.querySelector('a h4');
    if (userNameLink) {
        const userName = userNameLink.textContent;
        userNameLink.parentElement.outerHTML = `<h4>${userName}</h4>`;
    }

    const ellipsisElement = reviewClone.querySelector('.fas.fa-ellipsis-h');
    if (ellipsisElement) {
        ellipsisElement.remove();
    }

    const reviewContentDiv = document.getElementById('flagReviewContent');
    reviewContentDiv.innerHTML = '';
    reviewContentDiv.appendChild(reviewClone);

    document.getElementById('flagModal').style.display = 'block';
    console.log('Flag modal shown for review ID:', reviewId);
}

document.querySelector('.btn.btn-secondary.flag-cancel').addEventListener('click', function() {
    closeModal('flagModal');
});

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
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.status === 'success') {
                        location.reload();
                    } else {
                        alert("Error: " + response.message);
                    }
                } catch (e) {
                    alert("Invalid JSON response: " + xhr.responseText);
                }
            } else {
                alert("Error: " + xhr.status);
            }
        }
    };
    xhr.send("id=" + reviewId + "&reason=" + encodeURIComponent(reason));
}

let reviewIdToUnflag;

function showUnflagModal(reviewId) {
    reviewIdToUnflag = reviewId;

    const reviewElement = document.getElementById('review-' + reviewId);
    const reviewClone = reviewElement.cloneNode(true);
    const reviewContentDiv = document.getElementById('unflagReviewContent');
    reviewContentDiv.innerHTML = '';
    reviewContentDiv.appendChild(reviewClone);

    document.getElementById('unflagModal').style.display = 'block';
}

document.querySelectorAll('.unflag-cancel').forEach(button => {
    button.addEventListener('click', () => {
        document.getElementById('unflagModal').style.display = 'none';
    });
});

function unflagReview(reviewId) {
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "unflag_review.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === XMLHttpRequest.DONE) {
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.status === 'success') {
                        location.reload();
                    } else {
                        alert("Error: " + response.message);
                    }
                } catch (e) {
                    alert("Invalid JSON response: " + xhr.responseText);
                }
            } else {
                alert("Error: " + xhr.status);
            }
        }
    };
    xhr.send("id=" + reviewId);
}


function showReportModal(contentType, contentId, contentPreview) {
    <?php if (!isset($_SESSION['email'])): ?>
        const loginNotification = document.createElement('div');
        loginNotification.id = 'flaggedAlert';
        loginNotification.className = 'alert alert-warning popup-notification';
        loginNotification.innerHTML = 'You must be logged in to submit a report.';
        document.body.appendChild(loginNotification);
        
        setTimeout(() => {
            loginNotification.style.display = 'block';
            setTimeout(() => {
                loginNotification.remove();
            }, 3000);
        }, 100);
        return;
    <?php endif; ?>
    
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

    const modal = document.getElementById('reportModal');
    const previewDiv = modal.querySelector('.content-preview');
    const form = document.getElementById('reportForm');
    
    form.reset();
    
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = contentPreview;
    
    const reviewOptions = tempDiv.querySelector('.review-options');
    if (reviewOptions) {
        reviewOptions.remove();
    }
    
    form.querySelector('[name="content_type"]').value = contentType;
    form.querySelector('[name="content_id"]').value = contentId;
    
    previewDiv.innerHTML = tempDiv.innerHTML;
    
    modal.style.display = 'block';
}

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

document.querySelector('.report-cancel').addEventListener('click', function() {
    document.getElementById('reportModal').style.display = 'none';
});

function showImage(src) {
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImage');
    modalImg.src = src;
    modal.style.display = "block";

    let touchStartX = 0;
    let touchEndX = 0;
    
    modal.addEventListener('touchstart', e => {
        touchStartX = e.changedTouches[0].screenX;
    });
    
    modal.addEventListener('touchend', e => {
        touchEndX = e.changedTouches[0].screenX;
        if (touchEndX < touchStartX) {
            slideImages('next');
        } else if (touchEndX > touchStartX) {
            slideImages('prev');
        }
    });
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('imageModal').style.display = "none";
    }
});

document.querySelector('.image-modal .close').onclick = function() {
    document.getElementById('imageModal').style.display = "none";
}

document.getElementById('imageModal').onclick = function(e) {
    if (e.target === this) {
        this.style.display = "none";
    }
}

let currentIndex = 0;

function slideImages(direction) {
    const items = document.querySelectorAll('.gallery-item');
    const isMobile = window.innerWidth <= 768;
    const visibleItems = isMobile ? 1 : 3;
    const maxIndex = items.length - visibleItems;
    
    items.forEach(item => item.classList.remove('active'));
    
    if (direction === 'next' && currentIndex < maxIndex) {
        currentIndex++;
    } else if (direction === 'prev' && currentIndex > 0) {
        currentIndex--;
    }
    
    const endIndex = Math.min(currentIndex + visibleItems, items.length);
    for (let i = currentIndex; i < endIndex; i++) {
        items[i].classList.add('active');
    }
    
    document.querySelector('.prev').style.display = currentIndex === 0 ? 'none' : 'block';
    document.querySelector('.next').style.display = currentIndex === maxIndex ? 'none' : 'block';
}

function initializeGallery() {
    const items = document.querySelectorAll('.gallery-item');
    const isMobile = window.innerWidth <= 768;
    const visibleItems = isMobile ? 1 : 3;

    if (items.length <= visibleItems) {
        document.querySelector('.prev').style.display = 'none';
        document.querySelector('.next').style.display = 'none';
    } else {
        document.querySelector('.prev').style.display = 'none';
        document.querySelector('.next').style.display = 'block';
        
        currentIndex = 0;
        items.forEach(item => item.classList.remove('active'));
        for (let i = 0; i < visibleItems; i++) {
            items[i].classList.add('active');
        }
    }
}

window.addEventListener('load', initializeGallery);
window.addEventListener('resize', initializeGallery);

let menuCurrentIndex = 0;

function slideMenuImages(direction) {
    const items = document.querySelectorAll('.menu-gallery-item');
    const isMobile = window.innerWidth <= 768;
    const visibleItems = isMobile ? 1 : 2;
    const maxIndex = items.length - visibleItems;
    
    items.forEach(item => item.classList.remove('active'));
    
    if (direction === 'next' && menuCurrentIndex < maxIndex) {
        menuCurrentIndex++;
    } else if (direction === 'prev' && menuCurrentIndex > 0) {
        menuCurrentIndex--;
    }
    
    const endIndex = Math.min(menuCurrentIndex + visibleItems, items.length);
    for (let i = menuCurrentIndex; i < endIndex; i++) {
        items[i].classList.add('active');
    }
    
    const menuSlider = document.querySelector('.menu-slider');
    menuSlider.querySelector('.prev').style.display = menuCurrentIndex === 0 ? 'none' : 'block';
    menuSlider.querySelector('.next').style.display = menuCurrentIndex === maxIndex ? 'none' : 'block';
}

function initializeMenuGallery() {
    const items = document.querySelectorAll('.menu-gallery-item');
    const isMobile = window.innerWidth <= 768;
    const visibleItems = isMobile ? 1 : 2;
    const menuSlider = document.querySelector('.menu-slider');

    if (items.length <= visibleItems) {
        menuSlider.querySelector('.prev').style.display = 'none';
        menuSlider.querySelector('.next').style.display = 'none';
    } else {
        menuSlider.querySelector('.prev').style.display = 'none';
        menuSlider.querySelector('.next').style.display = 'block';
        
        menuCurrentIndex = 0;
        items.forEach(item => item.classList.remove('active'));
        for (let i = 0; i < visibleItems; i++) {
            items[i].classList.add('active');
        }
    }
}

window.addEventListener('load', initializeMenuGallery);
window.addEventListener('resize', initializeMenuGallery);


function getUserLocation(callback) {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(position) {
      var userLatLng = [position.coords.latitude, position.coords.longitude];
      callback(userLatLng);
    }, function(error) {
      console.error("Error getting user's location: ", error);
      callback(null);
    });
  } else {
    console.error("Geolocation is not supported by this browser.");
    callback(null);
  }
}

var studyMarker = L.marker([studyLocation.lat, studyLocation.lng])
.addTo(map)
.bindPopup(studyLocation.name)
.openPopup();

var lastUserLocation = null;

function expandMap() {
    var mapContainer = document.getElementById('map-container');
    mapContainer.classList.add('full-screen-map');

    document.getElementById('close-button').style.display = 'block';
    document.getElementById('toggleJeepRoutes').style.display = 'block';
    document.getElementById('toggleRoutingControl').style.display = 'block';

    map.invalidateSize();

    if (lastUserLocation) {
        showRouteToStudyLocation(lastUserLocation);
        if (userLocationMarker) {
            userLocationMarker.setLatLng(lastUserLocation);
        } else {
            userLocationMarker = L.marker(lastUserLocation)
                .addTo(map)
                .bindPopup('Your Location');
        }
        centerOnRoute({lat: lastUserLocation[0], lon: lastUserLocation[1]}, studyLocation);
    } else {
        getUserLocation(function(userLatLng) {
            if (userLatLng) {
                lastUserLocation = userLatLng;
                showRouteToStudyLocation(userLatLng);
                if (userLocationMarker) {
                    userLocationMarker.setLatLng(userLatLng);
                } else {
                    userLocationMarker = L.marker(userLatLng)
                        .addTo(map)
                        .bindPopup('Your Location');
                }
                centerOnRoute({lat: userLatLng[0], lon: userLatLng[1]}, studyLocation);
            }
        });
    }
}

function showRouteToStudyLocation(userLatLng) {
        if (control) {
            map.removeControl(control);
        }

        control = L.Routing.control({
            waypoints: [
                L.latLng(userLatLng[0], userLatLng[1]),
                L.latLng(studyLocation.lat, studyLocation.lng)
            ],
            routeWhileDragging: true,
            lineOptions: {
                styles: [{ color: 'blue', opacity: 0.6, weight: 4 }]
            }
        }).addTo(map);

        setTimeout(function() {
            var routingControls = document.getElementsByClassName('leaflet-routing-container');
            for (var i = 0; i < routingControls.length; i++) {
                routingControls[i].style.display = 'none';
                routingControls[i].classList.add('routing-control');
            }
        }, 100);

    
}

function minimizeMap() {
    map.setView([studyLocation.lat, studyLocation.lng], 15);

    if (control) {
        map.removeControl(control);
        control = null;
    }

    var mapContainer = document.getElementById('map-container');
    mapContainer.classList.remove('full-screen-map');

    document.getElementById('close-button').style.display = 'none';
    document.getElementById('toggleJeepRoutes').style.display = 'none';
    document.getElementById('toggleRoutingControl').style.display = 'none';
    document.getElementById('routeControls').style.display = 'none';

    map.invalidateSize();
}

document.getElementById('toggleRoutingControl').addEventListener('click', function() {
    var routingControls = document.getElementsByClassName('leaflet-routing-container');
    for (var i = 0; i < routingControls.length; i++) {
        if (routingControls[i].style.display === 'none') {
            routingControls[i].style.display = 'block';
        } else {
            routingControls[i].style.display = 'none';
        }
    }
});

window.addEventListener('load', function() {
  if (typeof control !== 'undefined' && control) {
    map.removeControl(control);
    control = null; 
  }
});

var jeepRouteLayers = []; 
var routeControls = {};

function loadJeepRoutes() {
    console.log('loadJeepRoutes function called');

    document.getElementById('routeControls').style.display = 'block';
    
    fetch('jeep_routes.php')
        .then(response => response.text())
        .then(text => JSON.parse(text))
        .then(data => {
            const controlsContainer = document.getElementById('routeControls');
            controlsContainer.innerHTML = '<h4>Jeepney Routes</h4>';
            
            var colorMapping = {
                'Villa': 'gold',
                'Pandan': 'blue',
                'Marisol': 'green',
                'Checkpoint': 'purple',
                'Sapang Bato': 'maroon',
                'Plaridel': 'pink',
                'Hensonville': 'white',
                'Sunset': 'orange',
                'Manibaug': 'grey'
            };

            for (var routeName in data) {
                if (data.hasOwnProperty(routeName)) {
                    const color = colorMapping[routeName] || 'green';

                    const routeItem = document.createElement('div');
                    routeItem.className = 'route-item';

                    const colorIndicator = document.createElement('div');
                    colorIndicator.className = 'route-color';
                    colorIndicator.style.backgroundColor = color;

                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.className = 'route-checkbox';
                    checkbox.checked = true;

                    const label = document.createElement('span');
                    label.textContent = routeName;

                    routeItem.appendChild(checkbox);
                    routeItem.appendChild(colorIndicator);
                    routeItem.appendChild(label);
                    controlsContainer.appendChild(routeItem);

                    const latLngs = data[routeName].map(point => [point.lat, point.lng]);
                    const polyline = L.polyline(latLngs, { color: color }).bindPopup(routeName);
                    polyline.addTo(map);
                    jeepRouteLayers.push(polyline);

                    routeControls[routeName] = polyline;
                    checkbox.addEventListener('change', function() {
                        if (this.checked) {
                            map.addLayer(polyline);
                        } else {
                            map.removeLayer(polyline);
                        }
                    });
                }
            }
        })
        .catch(error => console.error('Error loading jeepney routes:', error));
}

function removeJeepRoutes() {
    jeepRouteLayers.forEach(layer => {
        if (map.hasLayer(layer)) {
            map.removeLayer(layer);
        }
    });
    jeepRouteLayers = [];
    document.getElementById('routeControls').style.display = 'none';
}

if (document.getElementById('routeControls').style.display === 'block') {
    document.getElementById('routeControls').style.display = 'none';
}

var jeepRoutesVisible = false;

document.getElementById('toggleJeepRoutes').addEventListener('click', function() {
    if (!jeepRoutesVisible) {
        loadJeepRoutes();
        this.textContent = 'Hide Jeep Routes'; 
    } else {
        removeJeepRoutes(); 
        this.textContent = 'Show Jeep Routes'; 
    }
    jeepRoutesVisible = !jeepRoutesVisible; 
});

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
<?php
$conn->close();
?>