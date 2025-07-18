<?php
require_once "config.php";
session_start();

$message = ""; 
$messageClass = ""; 

$fullname = "";
$email = "";
$password = "";
$cpassword = "";
$emailError = false;
$passwordError = false;

if (isset($_POST['submits'])) {
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $cpassword = $_POST['cpassword'];
    $type = "user";
    $profile_picture = 'images/pfp.png'; 

    if ($password !== $cpassword) {
        $message = "Passwords do not match!";
        $messageClass = "error-message";
        $passwordError = true;
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*(),.?":{}|<>])[A-Za-z\d!@#$%^&*(),.?":{}|<>]{8,16}$/', $password)) {
        $message = "Password must contain 8-16 characters, at least one uppercase letter, one lowercase letter, one number, and one special character";
        $messageClass = "error-message";
        $passwordError = true;
    } else {
        $email = mysqli_real_escape_string($conn, $email);
        $password = mysqli_real_escape_string($conn, $password);

        $check_query = "SELECT * FROM ff_users WHERE email='$email'";
        $check_result = $conn->query($check_query);

        if ($check_result->num_rows > 0) {
            $message = "Email already exists!";
            $messageClass = "error-message";
            $emailError = true;
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            $insert_query = "INSERT INTO ff_users (fullname, email, password, type, profile_picture, verified) VALUES ('$fullname','$email', '$hashed_password', '$type', '$profile_picture', 0)";
            if ($conn->query($insert_query) === TRUE) {
                $message = "Successfully registered! Please log in to verify your email.";
                $messageClass = "success-message";
                $fullname = $email = $password = $cpassword = "";
            } else {
                $message = "Error: " . $insert_query . "<br>" . $conn->error;
                $messageClass = "error-message";
            }
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html>
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
    height: 300px;
    margin: 10px 0;
    border-radius: 8px;
    overflow: hidden;
    }

    #location-map {
        width: 100%;
        height: 100%;
    }

.category-filter-box {
    margin-bottom: 20px;
}

.category-filter-box input[type="text"] {
    width: 100%;
    padding: 10px;
    margin-bottom: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background-color: #f8f8f8;
}

.category-filters {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.category-filters .filter-toggle {
    display: flex;
    align-items: center;
    cursor: pointer;
    padding: 5px;
}

.category-filters .filter-box {
    width: 20px;
    height: 20px;
    border: 2px solid #666;
    margin-right: 8px;
    position: relative;
}

.category-filters .filter-box[data-state="checked"]::after {
    content: "✓";
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: #4CAF50;
}

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

.price-range-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.price-input {
    flex: 1;
}

.price-input input[type="number"] {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.price-separator {
    color: #666;
    font-weight: bold;
}

/* Remove spinner buttons */
.price-input input[type="number"]::-webkit-inner-spin-button,
.price-input input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.price-input input[type="number"] {
    -moz-appearance: textfield;
}

/* Current problematic styling */
.input-group.error-border {
    border: none !important;
}

/* Fix: Make it more specific to target only the input */
.input-group.error-border input,
.input-group.error-border .password-container {
    border: 1px solid #ff0000 !important;
    border-radius: 5px;
}

/* Keep label styling separate */
.input-group label {
    display: block;
    margin-bottom: 5px;
    color: #333;
}

.personal-info-field {
    display: none;
    transition: all 0.3s ease;
}

.personal-info-field.show {
    display: block;
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

<div class="main-content2">
<div class="form-container">
        <div class="form-section" id="shop-info-form">
            <div class="suggestion-form-box">
                <div class="signup-text">Establishment Registration</div>
                <form action="process_suggestion.php" method="post" enctype="multipart/form-data" onsubmit="return validateForm()">
                    <!-- Add hidden fields for user info -->
                    <input type="hidden" name="fullname" value="<?php echo $_SESSION['fullname']; ?>">
                    <input type="hidden" name="email" value="<?php echo $_SESSION['email']; ?>">
                    
                    <!-- Keep existing shop registration fields -->
                    <label for="Cnumber">Contact Number</label>
                    <div class="input-group">
                        <input type="text" id="Cnumber" name="Cnumber" value="<?php echo isset($_SESSION['contact']) ? $_SESSION['contact'] : ''; ?>" required>
                    </div>

                    <label for="location">Establishment Name</label>
                    <div class="input-group">
                        <input type="text" id="Nlocation" name="Nlocation" required>
                    </div>

                    <label for="details">Establishment Details</label>
                    <div class="input-group">
                        <textarea id="details" name="details" rows="4" required 
                            placeholder="Describe your establishment, amenities, etc."></textarea>
                    </div>

                    <label for="Ohours">Operating Hours</label>
                    <div class="input-group">
                        <input type="text" id="Ohours" name="Ohours"  placeholder="Mon-Fri: 8AM - 10PM | Sat-Sun: 10AM - 9PM" required>
                    </div>
                    
                    <label for="priceRange">Price Range (₱)</label>
                        <div class="input-group price-range-group">
                            <div class="price-input">
                                <input type="number" 
                                    id="minPrice" 
                                    name="minPrice" 
                                    min="0" 
                                    step="1" 
                                    placeholder="Min Price">
                            </div>
                            <span class="price-separator">-</span>
                            <div class="price-input">
                                <input type="number" 
                                    id="maxPrice" 
                                    name="maxPrice" 
                                    min="0" 
                                    step="1" 
                                    placeholder="Max Price">
                            </div>
                        </div>

                    <label>Select Tags</label>
                    <div class="category-filter-box">
                        <input type="text" id="selectedCategories" name="selectedCategories" readonly required>
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

                    <label for="address">Address (Select on map)</label>
                    <div class="input-group">
                        <input type="text" id="Laddress" name="Laddress" required readonly>
                        <input type="hidden" id="latitude" name="latitude" required>
                        <input type="hidden" id="longitude" name="longitude" required>
                    </div>
                    <div id="map-container">
                        <div id="location-map"></div>
                    </div>
                        

                    <label for="shopImages">Shop Images</label>
                    <div class="input-group">
                        <input type="file" id="shopImages" name="shopImages[]" accept="image/*" multiple>
                        <div id="imagePreviewContainer" class="image-preview-container"></div>
                    </div>

                    <div class="button-group">
                        <button type="button" class="btn back-btn" onclick="showPreviousForm()">Back</button>
                        <button type="submit" class="btn">Submit</button>
                    </div>
                </form>
                <div id="error-messages" class="message error-message"></div>
            </div>
        </div>
    </div>
</div>

<div class="progress-indicator">
    <div class="step active" id="step1" data-title="Personal Info">1</div>
    <div class="step" id="step2" data-title="Shop Details">2</div>
</div>

<script>

document.addEventListener('DOMContentLoaded', function() {
    const map = L.map('location-map').setView([15.145, 120.588], 13);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    let marker = null;

    map.on('click', function(e) {
        const lat = e.latlng.lat;
        const lng = e.latlng.lng;

        document.getElementById('latitude').value = lat;
        document.getElementById('longitude').value = lng;

        if (marker) {
            marker.setLatLng(e.latlng);
        } else {
            marker = L.marker(e.latlng).addTo(map);
        }

        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
            .then(response => response.json())
            .then(data => {
                const address = data.display_name;
                document.getElementById('Laddress').value = address;
            })
            .catch(error => console.error('Error:', error));
    });

    document.querySelector('.btn[onclick="showNextForm()"]').addEventListener('click', function() {
        setTimeout(() => {
            map.invalidateSize();
        }, 100);
    });
});

document.addEventListener('DOMContentLoaded', function() {
    let selectedTags = [];
    const selectedCategoriesInput = document.getElementById('selectedCategories');

    document.querySelectorAll('.filter-box').forEach(function(box) {
        box.addEventListener('click', function() {
            const tag = this.parentElement.getAttribute('data-value');
            
            if (this.textContent === '') {
                this.textContent = '✔️';
                this.style.color = 'green';
                selectedTags.push(tag);
            } else {
                this.textContent = '';
                selectedTags = selectedTags.filter(t => t !== tag);
            }
            
            selectedCategoriesInput.value = selectedTags.join(',');
        });
    });
});

document.getElementById('shopImages').addEventListener('change', function(e) {
    const container = document.getElementById('imagePreviewContainer');
    const files = Array.from(e.target.files);
    
    files.forEach(file => {
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            const preview = document.createElement('div');
            preview.className = 'image-preview';
            
            reader.onload = function(e) {
                preview.innerHTML = `
                    <img src="${e.target.result}" alt="Preview">
                    <button type="button" class="remove-image">&times;</button>
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
                };
            };
            
            reader.readAsDataURL(file);
            container.appendChild(preview);
        }
    });
});

function showError(message) {
    const errorDiv = document.getElementById('error-messages');
    const errorMessage = document.createElement('div');
    errorMessage.className = 'error-message';
    errorMessage.textContent = message;
    errorDiv.innerHTML = ''; 
    errorDiv.appendChild(errorMessage);
}

function validateForm() {
    const minPrice = document.getElementById('minPrice');
    const maxPrice = document.getElementById('maxPrice');
    const tags = document.getElementById('selectedCategories');
    const address = document.getElementById('Laddress');
    
    document.getElementById('error-messages').innerHTML = '';
    
    if (!tags.value) {
        showError('Please select at least one tag');
        return false;
    }
    
    if (!address.value) {
        showError('Please select a location on the map');
        return false;
    }
    
    if (minPrice.value && maxPrice.value) {
        const minVal = Number(minPrice.value);
        const maxVal = Number(maxPrice.value);
        
        if (maxVal < minVal) {
            showError('Maximum price cannot be less than minimum price');
            return false;
        }
    }
    
    return true;
}
    </script>
</body>
</html>