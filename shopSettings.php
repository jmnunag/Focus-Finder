<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['email']) || $_SESSION['type'] !== 'owner') {
    header("Location: userHome.php");
    exit;
}

$email = $_SESSION['email'];
$query = "SELECT * FROM study_locations WHERE owner_email = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $email);
$stmt->execute();
$shop = $stmt->get_result()->fetch_assoc();

function getLocationImages($shopName) {
    error_log("Getting images for shop: " . $shopName);
    $path = "locations/" . str_replace(['/', '\\', '?', '*', ':', '|', '"', '<', '>'], '', $shopName);
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

function getMenuImages($shopName) {
    $folder_name = str_replace(['/', '\\', '?', '*', ':', '|', '"', '<', '>'], '', $shopName);
    $path = "menus/" . trim($folder_name);
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

$shopImages = [];
if ($shop) {
    $shopImages = getLocationImages($shop['name']);
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/x-icon" href="images/FFicon.ico">
    <title>Focus Finder</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <style>
    #map-container {
    width: 100%;
    height: 400px;
    margin: 15px 0;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid #ddd;
    }

    #location-map {
        width: 100%;
        height: 100%;
    }
    .settings-container {
        display: flex;
        margin: 20px;
    }

    .settings-sidebar {
        width: 200px;
        padding: 20px;
        background: #f5f5f5;
    }

    .settings-content {
        flex: 1;
        padding: 20px;
    }

    .tab {
        padding: 10px;
        margin: 5px 0;
        cursor: pointer;
    }

    .tab.active {
        background: #ddd;
    }

    .settings-container {
    margin: 20px 20px auto;
    background: white;
    border-radius: 10px;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
    overflow: hidden;
    text-align: left;
    }

    .settings-sidebar {
        width: 250px;
        background: #f8f9fa;
        border-right: 1px solid #dee2e6;
    }

    .tab {
        padding: 15px 20px;
        font-size: 16px;
        transition: all 0.3s ease;
        border-left: 4px solid transparent;
    }

    .tab:hover {
        background: #e9ecef;
    }

    .tab.active {
        background: #5db971;
        color: white;
        border-left: 4px solid #45a049;
    }

    .settings-content {
        padding: 30px;
        background: white;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        margin-bottom: 8px;
        font-weight: bold;
    }

    .form-group input[type="text"],
    .form-group input[type="number"],
    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        margin-bottom: 10px;
    }

    .form-group textarea {
        min-height: 100px;
        resize: vertical;
    }

    .btn-edit {
        background: #5db971;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 5px;
        cursor: pointer;
        margin-bottom: 20px;
    }

    .btn-edit:hover {
        background: #45a049;
    }

    /* Image preview styles */
    .image-preview-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
    margin-top: 10px;
    }

    .image-preview {
        position: relative;
        width: 100%;
        padding-bottom: 100%;
    }

    .image-preview img {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 4px;
    }

    .menu-preview, .image-preview {
        position: relative;
    }

    .menu-preview .remove-image,
    .image-preview .remove-image {
        position: absolute;
        top: 5px;
        right: 5px;
        background: rgba(255, 0, 0, 0.7);
        color: white;
        border: none;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        visibility: hidden;
    }

    .edit-mode .remove-image {
        visibility: visible;
    }

    /* Tags styling */
    .category-filter-box {
        margin: 20px 0;
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: 8px;
    }

    .filter-form {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 10px;
    }

    .filter-toggle {
        display: flex;
        align-items: center;
        cursor: pointer;
        padding: 5px;
    }

    .filter-box {
        width: 20px;
        height: 20px;
        border: 2px solid #666;
        margin-right: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
    }

    .image-preview-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
    margin-top: 10px;
}

.menu-preview .no-image {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    margin: 0;
    color: #999;
    font-style: italic;
}

.image-preview {
    position: relative;
    width: 100%;
    padding-bottom: 100%;
}

.image-preview img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 4px;
}

.remove-image {
    position: absolute;
    top: 5px;
    right: 5px;
    background: rgba(255, 0, 0, 0.7);
    color: white;
    border: none;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}
.icon-preview {
    width: 200px;
    height: 200px;
    border: 2px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    margin: 10px 0;
}

.icon-preview img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.category-filter-box {
    margin: 20px 0;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
}

#selectedCategories {
    width: 100%;
    padding: 10px;
    margin-bottom: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background-color: #f8f8f8;
}
.btn-edit, .btn-cancel {
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    margin-left: 10px;
}


.btn-cancel {
    background: #6c757d;
    color: white;
}

.btn-edit:hover {
    background: #45a049;
}

.btn-cancel:hover {
    background: #5a6268;
}

.form-group input[readonly]:not([name="name"]),
.form-group textarea[readonly] {
    background-color: #f8f8f8;
}

.form-group input[name="name"][readonly] {
    background-color: #e9ecef;
    cursor: not-allowed;
}

.form-group input.error {
    border: 1px solid red !important;
}

.form-group input[type="file"],
.category-filter-box {
    display: none;
}

.edit-mode .form-group input[type="file"],
.edit-mode .category-filter-box {
    display: block;
}
.menu-preview {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
    margin-top: 10px;
}

.menu-image-preview {
    position: relative;
    width: 100%;
    padding-bottom: 100%;
}

.menu-image-preview img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 4px;
}

.existing-images-container,
.upload-preview-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
    margin-top: 10px;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 8px;
}

.section-label {
    display: block;
    margin-top: 20px;
    margin-bottom: 10px;
    font-weight: bold;
    color: #666;
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
                            <p>Membership</p>
                            <span>></span>
                        </a>
                    <?php else: ?>
                        <a href="userMember.php" class="sub-menu-link">
                            <p>Membership</p>
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

<div class="main-content2">

    <div class="settings-container">
        <div class="settings-sidebar">
            <div class="tab active" data-tab="establishment">Establishment Info</div>
            <div class="tab" data-tab="tags-images">Shop Tags & Images</div>
            <div class="tab" data-tab="reviews">Reviews</div>
            <div class="tab" data-tab="announcements">Shop Announcements</div>
        </div>

        <div class="settings-content">
            <div class="tab-content" id="establishment">
                <h2>Establishment Information</h2>
                <form id="establishmentForm">
                    <div class="form-group">
                        <label>Shop Name</label>
                        <input type="text" name="name" value="<?php echo $shop['name']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description"><?php echo $shop['description']; ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Operating Hours</label>
                        <input type="text" name="time" value="<?php echo $shop['time']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Price Range</label>
                        <input type="number" name="min_price" value="<?php echo $shop['min_price']; ?>">
                        <input type="number" name="max_price" value="<?php echo $shop['max_price']; ?>">
                        <div class="message error-message" style="display: none; color: red; margin-top: 5px;"></div>
                    </div>

                    <div class="form-group">
                        <label>Location</label>
                        <div id="map-container">
                            <div id="location-map"></div>
                        </div>
                        <input type="hidden" id="latitude" name="latitude" value="<?php echo $shop['latitude']; ?>">
                        <input type="hidden" id="longitude" name="longitude" value="<?php echo $shop['longitude']; ?>">
                    </div>
                </form>
            </div>

            <!-- Tags and Images -->
            <div class="tab-content" id="tags-images" style="display:none;">
                <h2>Shop Tags & Images</h2>
                <form id="tagsImagesForm">
                    <div class="form-group">
                        <label>Shop Icon</label>
                        <div class="icon-preview">
                            <img src="<?php echo $shop['image_url']; ?>" alt="Shop Icon">
                        </div>
                        <input type="file" accept="image/*" id="shopIcon">
                    </div>

                    <div class="form-group">
                    <label>Shop Tags</label>
                    <input type="text" id="selectedCategories" name="selectedCategories" readonly required value="<?php echo $shop['tags']; ?>">
                    <div class="category-filter-box">
                        <div class="filter-form">
                            <label class="filter-toggle" data-value="Air Conditioning">
                                <div class="filter-box" data-state="blank"></div> Air Conditioning
                            </label>
                            <label class="filter-toggle" data-value="Affordable Prices">
                                <div class="filter-box" data-state="blank"></div> Affordable Prices
                            </label>
                            <label class="filter-toggle" data-value="Books Available">
                                <div class="filter-box" data-state="blank"></div> Books Available
                            </label>
                            <label class="filter-toggle" data-value="Cafe">
                                <div class="filter-box" data-state="blank"></div> Cafe
                            </label>
                            <label class="filter-toggle" data-value="Coffee">
                                <div class="filter-box" data-state="blank"></div> Coffee
                            </label>
                            <label class="filter-toggle" data-value="Comfort Rooms">
                                <div class="filter-box" data-state="blank"></div> Comfort Rooms
                            </label>
                            <label class="filter-toggle" data-value="Breakfast Options">
                                <div class="filter-box" data-state="blank"></div> Complimentary Drinks
                            </label>
                            <label class="filter-toggle" data-value="Court">
                                <div class="filter-box" data-state="blank"></div> Court
                            </label>
                            <label class="filter-toggle" data-value="Co-Working">
                                <div class="filter-box" data-state="blank"></div> Co-Working
                            </label>
                            <label class="filter-toggle" data-value="Confectionaries">
                                <div class="filter-box" data-state="blank"></div> Confectionaries
                            </label>
                            <label class="filter-toggle" data-value="Cozy">
                                <div class="filter-box" data-state="blank"></div> Cozy
                            </label>
                            <label class="filter-toggle" data-value="Vendors">
                                <div class="filter-box" data-state="blank"></div> Vendors
                            </label>
                            <label class="filter-toggle" data-value="Fresh Air">
                                <div class="filter-box" data-state="blank"></div> Fresh Air
                            </label>
                            <label class="filter-toggle" data-value="Green Space">
                                <div class="filter-box" data-state="blank"></div> Green Space
                            </label>
                            <label class="filter-toggle" data-value="Indoor">
                                <div class="filter-box" data-state="blank"></div> Indoor
                            </label>
                            <label class="filter-toggle" data-value="Library">
                                <div class="filter-box" data-state="blank"></div> Library
                            </label>
                            <label class="filter-toggle" data-value="Open Area">
                                <div class="filter-box" data-state="blank"></div> Open Area
                            </label>
                            <label class="filter-toggle" data-value="Outdoor">
                                <div class="filter-box" data-state="blank"></div> Outdoor
                            </label>
                            <label class="filter-toggle" data-value="Outlets Available">
                                <div class="filter-box" data-state="blank"></div> Outlets Available
                            </label>
                            <label class="filter-toggle" data-value="Park">
                                <div class="filter-box" data-state="blank"></div> Park
                            </label>
                            <label class="filter-toggle" data-value="Parking Space">
                                <div class="filter-box" data-state="blank"></div> Parking Space
                            </label>
                            <label class="filter-toggle" data-value="Pet-friendly">
                                <div class="filter-box" data-state="blank"></div> Pet-friendly
                            </label>
                            <label class="filter-toggle" data-value="Picnic Tables">
                                <div class="filter-box" data-state="blank"></div> Picnic Tables
                            </label>
                            <label class="filter-toggle" data-value="Private Rooms">
                                <div class="filter-box" data-state="blank"></div> Private Rooms
                            </label>
                            <label class="filter-toggle" data-value="Quiet">
                                <div class="filter-box" data-state="blank"></div> Quiet
                            </label>
                            <label class="filter-toggle" data-value="Resto">
                                <div class="filter-box" data-state="blank"></div> Resto
                            </label>
                            <label class="filter-toggle" data-value="Shade">
                                <div class="filter-box" data-state="blank"></div> Shade
                            </label>
                            <label class="filter-toggle" data-value="Snacks">
                                <div class="filter-box" data-state="blank"></div> Snacks
                            </label>
                            <label class="filter-toggle" data-value="Spacious">
                                <div class="filter-box" data-state="blank"></div> Spacious
                            </label>
                            <label class="filter-toggle" data-value="Wi-Fi">
                                <div class="filter-box" data-state="blank"></div> Wi-Fi
                            </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Menu Image</label>
                        <div class="menu-preview">
                            <?php 
                            $menuImages = getMenuImages($shop['name']);
                            if (!empty($menuImages)): 
                                foreach($menuImages as $image): 
                            ?>
                                <div class="menu-image-preview">
                                    <img src="<?php echo htmlspecialchars($image); ?>" alt="Menu Image">
                                    <button type="button" class="remove-image" data-path="<?php echo htmlspecialchars($image); ?>">&times;</button>
                                </div>
                            <?php 
                                endforeach;
                            else:
                                echo "<p class='no-image'>No menu image found</p>";
                            endif;
                            ?>
                        </div>
                        <input type="file" multiple accept="image/*" id="menuImage">
                    </div>


                    <div class="form-group">
                        <label>Shop Images</label>
                        <input type="file" multiple accept="image/*" id="shopImages">
                        <div class="existing-images-container">
                            <?php 
                            if (!empty($shopImages)): 
                                foreach($shopImages as $image): 
                            ?>
                                <div class="image-preview">
                                    <img src="<?php echo htmlspecialchars($image); ?>" alt="Shop Image">
                                    <button type="button" class="remove-image" data-path="<?php echo htmlspecialchars($image); ?>">&times;</button>
                                </div>
                            <?php 
                                endforeach;
                            else:
                                echo "<p>No images found</p>";
                            endif;
                            ?>
                        </div>
                    </div>
                </form>
            </div>

            <div class="tab-content" id="reviews" style="display:none;">
    <h2>Shop Reviews</h2>
    <div class="reviews-section">
        <?php
        $owner_email = $_SESSION['email'];
        $shop_query = $conn->prepare("SELECT id FROM study_locations WHERE owner_email = ?");
        $shop_query->bind_param("s", $owner_email);
        $shop_query->execute();
        $shop_result = $shop_query->get_result();
        $shop_data = $shop_result->fetch_assoc();
        
        if ($shop_data) {
            $shop_id = $shop_data['id'];
            
            $reviews_per_page = 10;
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $offset = ($page - 1) * $reviews_per_page;
            
            $reviews_query = $conn->prepare("SELECT r.*, 
                (SELECT COUNT(*) FROM review_votes WHERE review_id = r.id AND vote_type = 'upvote') as upvotes,
                (SELECT COUNT(*) FROM review_votes WHERE review_id = r.id AND vote_type = 'downvote') as downvotes
                FROM reviews r WHERE location_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
            $reviews_query->bind_param("iii", $shop_id, $reviews_per_page, $offset);
            $reviews_query->execute();
            $reviews_result = $reviews_query->get_result();

            if ($reviews_result->num_rows > 0):
                while ($review = $reviews_result->fetch_assoc()):
                    $user_query = $conn->prepare("SELECT fullname, profile_picture FROM ff_users WHERE email = ?");
                    $user_query->bind_param("s", $review['email']);
                    $user_query->execute();
                    $user_info = $user_query->get_result()->fetch_assoc();
                    
                    $date = new DateTime($review['created_at']);
                    $formatted_date = $date->format('Y-m-d');
            ?>
                <div class="review <?php echo $review['flagged'] ? 'flagged-review' : ''; ?>" id="review-<?php echo $review['id']; ?>">
                    <?php if ($review['flagged']): ?>
                        <p class="flag-status">Flagged for <?php echo htmlspecialchars($review['flag_reason']); ?></p>
                    <?php endif; ?>
                    
                    <div class="review-header">
                        <div class="user-info">
                            <img src="<?php echo htmlspecialchars($user_info['profile_picture']); ?>" class="profile-pic">
                            <a href="userotherProfile.php?email=<?php echo urlencode($review['email']); ?>">
                                <h4><?php echo htmlspecialchars($user_info['fullname']); ?></h4>
                            </a>
                        </div>
                        <div class="review-stars">
                            <?php 
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $review['rating']) {
                                    echo '<span class="star filled">★</span>';
                                } else {
                                    echo '<span class="star">★</span>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                    
                    <div class="review-content">
                        <p class="review-text"><?php echo htmlspecialchars($review['review_text']); ?></p>
                        <?php if ($review['edited']): ?>
                            <small>(edited)</small>
                        <?php endif; ?>
                    </div>
                    
                    <small>Posted on: <?php echo $formatted_date; ?></small>
                    
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
            <?php 
                endwhile;
            else:
            ?>
                <p>No reviews yet.</p>
            <?php endif;
        } else {
            echo "<p>Shop not found.</p>";
        }
        ?>
    </div>
</div>

            <div class="tab-content" id="announcements" style="display:none;">
                <h2>Shop Announcements</h2>
                <p>Coming soon...</p>
            </div>
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

document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');

        tab.classList.add('active');
        const content = document.getElementById(tab.dataset.tab);
        if (content) {
            content.style.display = 'block';
        }
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('establishmentForm');
    const buttonContainer = document.createElement('div');
    buttonContainer.className = 'button-container';
    buttonContainer.style.cssText = 'text-align: right; margin-bottom: 20px;';

    const editBtn = document.createElement('button');
    editBtn.type = 'button';
    editBtn.className = 'btn-edit';
    editBtn.textContent = 'Edit';

    const cancelBtn = document.createElement('button');
    cancelBtn.type = 'button';
    cancelBtn.className = 'btn-cancel';
    cancelBtn.textContent = 'Cancel';
    cancelBtn.style.display = 'none';

    buttonContainer.appendChild(cancelBtn);
    buttonContainer.appendChild(editBtn);
    form.parentElement.insertBefore(buttonContainer, form);

    let originalValues = {};

    const editableInputs = form.querySelectorAll('input:not([name="name"]), textarea');
    editableInputs.forEach(input => {
        input.setAttribute('readonly', true);
        input.style.backgroundColor = '#f8f8f8';
    });

    const shopNameInput = form.querySelector('input[name="name"]');
    if (shopNameInput) {
        shopNameInput.setAttribute('readonly', true);
        shopNameInput.style.backgroundColor = '#e9ecef';
    }

    editBtn.addEventListener('click', function() {
        const isEditing = form.classList.toggle('edit-mode');
        if (isEditing) {
            editableInputs.forEach(input => {
                originalValues[input.name] = input.value;
                input.removeAttribute('readonly');
                input.style.backgroundColor = '#ffffff';
            });
            form.style.display = 'block';
            editBtn.textContent = 'Save';
            cancelBtn.style.display = 'inline-block';
        } else {
            const minPrice = parseFloat(form.querySelector('input[name="min_price"]').value);
            const maxPrice = parseFloat(form.querySelector('input[name="max_price"]').value);
            const errorMessage = form.querySelector('.error-message');
            const priceInputs = form.querySelectorAll('input[type="number"]');

            errorMessage.style.display = 'none';
            priceInputs.forEach(input => input.classList.remove('error'));

            if (maxPrice < minPrice) {
                priceInputs.forEach(input => input.classList.add('error'));
                errorMessage.textContent = 'Cannot have a lower Max. Price than the Min. Price';
                errorMessage.style.display = 'block';
                return; 
            }

            const formData = new FormData(form);
            fetch('update_shop_settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    editableInputs.forEach(input => {
                        input.setAttribute('readonly', true);
                        input.style.backgroundColor = '#f8f8f8';
                    });
                    editBtn.textContent = 'Edit';
                    cancelBtn.style.display = 'none';
                } else {
                    alert('Failed to save changes');
                }
            });
        }
    });

    cancelBtn.addEventListener('click', function() {
        editableInputs.forEach(input => {
            input.value = originalValues[input.name];
            input.setAttribute('readonly', true);
            input.style.backgroundColor = '#f8f8f8';
        });
        form.classList.remove('edit-mode');
        form.style.display = 'block'; 
        editBtn.textContent = 'Edit';
        cancelBtn.style.display = 'none';
    });
});

document.querySelectorAll('.remove-image').forEach(button => {
    button.addEventListener('click', function() {
        const imagePath = this.dataset.path;
        if(confirm('Are you sure you want to remove this image?')) {
            fetch('update_shop_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'path=' + encodeURIComponent(imagePath)
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    this.closest('.image-preview').remove();
                }
            });
        }
    });
});

document.getElementById('shopImages').addEventListener('change', function(e) {
    const container = document.querySelector('.existing-images-container');
    const files = Array.from(e.target.files);
    
    files.forEach(file => {
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            const preview = document.createElement('div');
            preview.className = 'image-preview';
            
            reader.onload = function(e) {
                preview.innerHTML = `
                    <img src="${e.target.result}" alt="Preview">
                    <button type="button" class="remove-image" data-new="true">&times;</button>
                `;
                
                preview.querySelector('.remove-image').onclick = function() {
                    preview.remove();
                    const dt = new DataTransfer();
                    const input = document.getElementById('shopImages');
                    const { files } = input;
                    
                    for (let i = 0; i < files.length; i++) {
                        const file = files[i];
                        if (file !== files[i]) dt.items.add(file);
                    }
                    
                    input.files = dt.files;
                    
                    if (container.children.length === 0) {
                        container.innerHTML = '<p>No images found</p>';
                    }
                };
            };
            
            reader.readAsDataURL(file);
            
            const noImagesMessage = container.querySelector('p');
            if (noImagesMessage) {
                noImagesMessage.remove();
            }
            
            container.appendChild(preview);
        }
    });
});

document.getElementById('menuImage').addEventListener('change', function(e) {
    const container = document.querySelector('.menu-preview');
    const files = Array.from(e.target.files);
    
    if (container.querySelector('.no-image')) {
        container.innerHTML = '';
    }
    
    files.forEach(file => {
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            const preview = document.createElement('div');
            preview.className = 'menu-image-preview';
            
            reader.onload = function(e) {
                preview.innerHTML = `
                    <img src="${e.target.result}" alt="Menu Preview">
                    <button type="button" class="remove-image">&times;</button>
                `;
                
                preview.querySelector('.remove-image').onclick = function() {
                    preview.remove();
                    const dt = new DataTransfer();
                    const input = document.getElementById('menuImage');
                    const { files } = input;
                    
                    for (let i = 0; i < files.length; i++) {
                        const file = files[i];
                        if (file !== files[i]) dt.items.add(file);
                    }
                    
                    input.files = dt.files;

                    if (container.children.length === 0) {
                        container.innerHTML = '<p class="no-image">No menu image found</p>';
                    }
                };
            };
            
            reader.readAsDataURL(file);
            container.appendChild(preview);
        }
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const currentTags = '<?php echo $shop['tags']; ?>'.split(',');
    const selectedCategoriesInput = document.getElementById('selectedCategories');
    selectedCategoriesInput.value = currentTags.join(',');

    document.querySelectorAll('.filter-toggle').forEach(toggle => {
        if (currentTags.includes(toggle.getAttribute('data-value'))) {
            const checkbox = toggle.querySelector('.filter-box');
            checkbox.textContent = '✔️';
            checkbox.style.color = 'green';
        }
    });

    const tagsForm = document.getElementById('tagsImagesForm');
    const buttonContainer = document.createElement('div');
    buttonContainer.className = 'button-container';
    buttonContainer.style.cssText = 'text-align: right; margin-bottom: 20px;';

    const editBtn = document.createElement('button');
    editBtn.type = 'button';
    editBtn.className = 'btn-edit';
    editBtn.textContent = 'Edit';

    const cancelBtn = document.createElement('button');
    cancelBtn.type = 'button';
    cancelBtn.className = 'btn-cancel';
    cancelBtn.textContent = 'Cancel';
    cancelBtn.style.display = 'none';

    buttonContainer.appendChild(cancelBtn);
    buttonContainer.appendChild(editBtn);
    tagsForm.parentElement.insertBefore(buttonContainer, tagsForm);

    let originalTags = selectedCategoriesInput.value;
    let originalImages = Array.from(document.querySelectorAll('.existing-images-container .image-preview')).map(preview => preview.innerHTML);
    let originalIcon = document.querySelector('.icon-preview img').src;
    let originalMenuImage = document.querySelector('.menu-preview').innerHTML;

    document.querySelectorAll('.filter-box').forEach(function(box) {
        box.addEventListener('click', function() {
            if (!tagsForm.classList.contains('edit-mode')) return;
            
            const tag = this.parentElement.getAttribute('data-value');
            const selectedTags = selectedCategoriesInput.value.split(',').filter(t => t !== '');
            
            if (this.textContent === '') {
                this.textContent = '✔️';
                this.style.color = 'green';
                selectedTags.push(tag);
            } else {
                this.textContent = '';
                const index = selectedTags.indexOf(tag);
                if (index > -1) {
                    selectedTags.splice(index, 1);
                }
            }
            
            selectedCategoriesInput.value = selectedTags.join(',');
        });
    });

    editBtn.addEventListener('click', function() {
    const isEditing = tagsForm.classList.toggle('edit-mode');
    if (isEditing) {
        tagsForm.style.display = 'block';
        editBtn.textContent = 'Save';
        cancelBtn.style.display = 'inline-block';
        } else {
            const formData = new FormData();
            
            formData.append('selectedCategories', document.getElementById('selectedCategories').value);
            
            const shopIcon = document.getElementById('shopIcon').files[0];
            if (shopIcon) {
                formData.append('shopIcon', shopIcon);
            }
            
            const menuImages = document.getElementById('menuImage').files;
            for (let i = 0; i < menuImages.length; i++) {
                formData.append('menuImage[]', menuImages[i]);
            }
            
            const shopImages = document.getElementById('shopImages').files;
            for (let i = 0; i < shopImages.length; i++) {
                formData.append('shopImages[]', shopImages[i]);
            }

            fetch('update_shop_settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    tagsForm.style.display = 'block';
                    editBtn.textContent = 'Edit';
                    cancelBtn.style.display = 'none';
                } else {
                    alert('Failed to save changes: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to save changes');
            });
        }
    });

    cancelBtn.addEventListener('click', function() {
        tagsForm.classList.remove('edit-mode');
        tagsForm.style.display = 'block';
        selectedCategoriesInput.value = originalTags;
        
        document.querySelectorAll('.filter-box').forEach(box => {
            box.textContent = '';
            box.style.color = '';
        });
        
        const currentTags = originalTags.split(',');
        document.querySelectorAll('.filter-toggle').forEach(toggle => {
            if (currentTags.includes(toggle.getAttribute('data-value'))) {
                const checkbox = toggle.querySelector('.filter-box');
                checkbox.textContent = '✔️';
                checkbox.style.color = 'green';
            }
        });

        const imagesContainer = document.querySelector('.existing-images-container');
        imagesContainer.innerHTML = '';
        originalImages.forEach(html => {
            const div = document.createElement('div');
            div.className = 'image-preview';
            div.innerHTML = html;
            imagesContainer.appendChild(div);
        });

        document.querySelector('.icon-preview img').src = originalIcon;

        document.querySelector('.menu-preview').innerHTML = originalMenuImage;

        editBtn.textContent = 'Edit';
        cancelBtn.style.display = 'none';
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const lat = parseFloat(document.getElementById('latitude').value);
    const lng = parseFloat(document.getElementById('longitude').value);
    
    const map = L.map('location-map').setView([lat, lng], 15);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    L.marker([lat, lng]).addTo(map)
        .bindPopup('<?php echo $shop["name"]; ?>');

    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', () => {
            setTimeout(() => {
                map.invalidateSize();
            }, 100);
        });
    });
});

document.getElementById('shopIcon').addEventListener('change', function(e) {
    const file = this.files[0];
    if (file && file.type.startsWith('image/')) {
        const reader = new FileReader();
        const iconPreview = document.querySelector('.icon-preview img');
        
        reader.onload = function(e) {
            iconPreview.src = e.target.result;
        };
        
        reader.readAsDataURL(file);
    }
});

    </script>
</body>
</html>
