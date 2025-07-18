<?php
require_once "config.php";

function getRatingData($conn, $location_id) {
    $query = $conn->prepare("SELECT AVG(rating) as average_rating, COUNT(rating) as total_reviews FROM reviews WHERE location_id = ?");
    $query->bind_param("i", $location_id);
    $query->execute();
    $result = $query->get_result();
    $row = $result->fetch_assoc();
    return [
        'average_rating' => number_format($row['average_rating'], 1),
        'total_reviews' => $row['total_reviews']
    ];
}

$locations_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $locations_per_page;

$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$selectedTags = isset($_POST['selectedTags']) ? json_decode($_POST['selectedTags'], true) : [];

$locations_query = "SELECT * FROM study_locations WHERE 1";

if (!empty($search)) {
    $locations_query .= " AND name LIKE '%$search%'";
}

if (!empty($selectedTags) && is_array($selectedTags)) {
    foreach ($selectedTags as $tagData) {
        $tag = mysqli_real_escape_string($conn, $tagData['tag']);
        $state = $tagData['state'];
        if ($state === 'check') {
            $locations_query .= " AND FIND_IN_SET('$tag', tags)";
        } elseif ($state === 'x') {
            $locations_query .= " AND NOT FIND_IN_SET('$tag', tags)";
        }
    }
}

$min_price_filter = isset($_POST['min_price_filter']) ? $_POST['min_price_filter'] : '';
$max_price_filter = isset($_POST['max_price_filter']) ? $_POST['max_price_filter'] : '';

if (!empty($min_price_filter)) {
    if (strpos($min_price_filter, '+') !== false) {
        $price = intval(str_replace('+', '', $min_price_filter));
        $locations_query .= " AND min_price > $price AND min_price IS NOT NULL";
    } else {
        list($min, $max) = explode('-', $min_price_filter);
        $locations_query .= " AND min_price <= $max AND min_price IS NOT NULL";
    }
}

if (!empty($max_price_filter)) {
    list($min, $max) = explode('-', $max_price_filter);
    $locations_query .= " AND max_price <= $max AND max_price IS NOT NULL";
}

$locations_query .= " ORDER BY name ASC";

$total_query = "SELECT COUNT(*) AS total_locations FROM ($locations_query) AS filtered_locations";
$total_result = mysqli_query($conn, $total_query);

if (!$total_result) {
    die("Query failed: " . mysqli_error($conn));
}

$total_locations = mysqli_fetch_assoc($total_result)['total_locations'];
$total_pages = ceil($total_locations / $locations_per_page);

$locations_query .= " LIMIT $locations_per_page OFFSET $offset";
$locations_result = mysqli_query($conn, $locations_query);

if (!$locations_result) {
    die("Query failed: " . mysqli_error($conn));
}

$locations = mysqli_fetch_all($locations_result, MYSQLI_ASSOC) ?: [];

if (isset($_SESSION['email'])) {
    if (isset($_SESSION['type']) && $_SESSION['type'] == 'moderator') {
        $base_url = 'modLocation.php';
    } else {
        $base_url = 'userLocation.php';
    }
} else {
    $base_url = 'guestLocation.php';
}
?>



<div class="search-filter-bar">
    <div class="search-container">
        <img src="images/search-icon.png" class="icon" id="search-icon" onclick="toggleSearch()">
        <div class="search-barA" id="search-barA">
            <input type="text" id="search-input" placeholder="Search for a location...">
            <button id="search-button" onclick="search()">Search</button>
        </div>
    </div>
    <div class="filter-container">
        <button id="quick-filter-button">Quick Filter</button>
        <img src="images/filter-icon.png" class="icon" id="filter-icon" onclick="toggleFormbox()">
    </div>
</div>

    <div class="filter-formbox" id="filter-formbox">
    <form action="<?php echo isset($_SESSION['email']) ? 'userAdvanced.php' : 'guestAdvanced.php'; ?>" method="post" class="filter-form">
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

        <div class="price-filters">
        <label>Price Range</label>
            <div class="price-filter">
                <label>Minimum Price:</label>
                <select name="min_price_filter" id="min_price_filter">
                    <option value="">Any</option>
                    <option value="0-49">49₱ below</option>
                    <option value="50+">above 50₱</option>
                    <option value="100+">above 100₱</option>
                    <option value="200+">above 200₱</option>
                    <option value="500+">above 500₱</option>
                    <option value="500+">above 1000₱</option>
                </select>
            </div>
            <div class="price-filter">
                <label>Maximum Price:</label>
                <select name="max_price_filter" id="max_price_filter">
                    <option value="">Any</option>
                    <option value="0-50">50₱ below</option>
                    <option value="0-100">100₱ below</option>
                    <option value="0-200">200₱ below</option>
                    <option value="0-500">500₱ below</option>
                    <option value="0-500">1000₱ below</option>
                </select>
            </div>
        </div>

        
        <div class="form-buttons">
            <button type="submit" class="apply-button">Apply Filters</button>
            <button type="reset" class="clear-button">Clear Filters</button>
        </div>

        <input type="hidden" name="selectedTags" id="selectedTags"> 
    </form>
</div>

<div id="quick-filter-popup" class="popup">
    <div class="popup-content">
        <h2 id="popup-question">What are you looking for?</h2>
        <div id="popup-options" class="popup-options">
            <button class="option-button" onclick="handleOptionClick('Indoor')">Indoor</button>
            <button class="option-button" onclick="handleOptionClick('Outdoor')">Outdoor</button>
        </div>
        <div class="popup-buttons">
            <button id="next-button" onclick="nextQuestion()">Next</button>
            <button class="btn" id="apply-button" onclick="applyFilters()" style="display: none;">Apply</button>
            <button class="btn" onclick="closePopup()">Cancel</button>
        </div>
    </div>
</div>


<div class="main-content">
    <div class="locations">
        <?php if (!empty($locations)): ?>
            <?php foreach ($locations as $location): ?>
                <?php 
                $rating_data = getRatingData($conn, $location['id']); 
                $avg_rating = $rating_data['average_rating'];
                $total_reviews = $rating_data['total_reviews'];
                ?>
                <div class="location">
                <div class="location-image-rating">
                    <a href="<?php echo $base_url; ?>?location_id=<?php echo $location['id']; ?>">
                        <img src="<?php echo $location['image_url']; ?>" alt="<?php echo htmlspecialchars($location['name']); ?>">
                    </a>
                    <p class="location-rating">Rating (<?php echo $avg_rating; ?> / 5.0, <?php echo $total_reviews; ?> reviews)</p> <!-- Display rating below the image -->
                </div>
                <div class="location-details">
                    <h2>
                        <a href="<?php echo $base_url; ?>?location_id=<?php echo $location['id']; ?>" class="location-name">
                            <?php echo htmlspecialchars($location['name']); ?>
                        </a>
                    </h2>
                    <p><strong>Tags:</strong> <?php echo htmlspecialchars($location['tags']); ?></p>
                    <p><strong>Time:</strong> <?php echo htmlspecialchars($location['time']); ?></p>
                    <?php if ($location['min_price'] > 0 || $location['max_price'] > 0): ?>
                        <p><strong>Price Range:</strong> ₱<?php echo number_format($location['min_price'], 2); ?> - ₱<?php echo number_format($location['max_price'], 2); ?></p>
                    <?php endif; ?>
                    <p><?php echo htmlspecialchars($location['description']); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No locations found.</p>
        <?php endif; ?>
    </div>

    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>">Previous</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?php echo $i; ?>" <?php if ($i == $page) echo 'class="active"'; ?>><?php echo $i; ?></a>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?>">Next</a>
        <?php endif; ?>
    </div>
</div>

<?php
mysqli_close($conn);
?>

<div class="suggestion">
    <p>Are you an Establishment Owner?</p>
    <a href="check_suggestion_eligibility.php">Register Now</a>
</div>
    </div>




<script>
         document.querySelectorAll('.filter-box').forEach(function(box) {
        box.addEventListener('click', function() {
            let state = this.getAttribute('data-state');
            const tag = this.parentElement.getAttribute('data-value');

            if (state === 'blank') {
                this.setAttribute('data-state', 'check');
                this.textContent = '✔️';
                this.style.color = 'green';
                addTag(tag, 'check');
            } else if (state === 'check') {
                this.setAttribute('data-state', 'x');
                this.textContent = '❌';
                this.style.color = 'red';
                addTag(tag, 'x');
            } else {
                this.setAttribute('data-state', 'blank');
                this.textContent = '';
                removeTag(tag);
            }
        });
    });

    let selectedTags = [];

    function addTag(tag, state) {
        const existingTag = selectedTags.find(t => t.tag === tag);
        if (existingTag) {
            existingTag.state = state;
        } else {
            selectedTags.push({ tag: tag, state: state });
        }
        document.getElementById('selectedTags').value = JSON.stringify(selectedTags);
    }

    function removeTag(tag) {
        selectedTags = selectedTags.filter(t => t.tag !== tag);
        document.getElementById('selectedTags').value = JSON.stringify(selectedTags);
    }
        


        function toggleMenu() {
            let subMenu = document.getElementById("subMenu");
            subMenu.classList.toggle("open-menu");
        }

        function toggleSearch() {
            const searchBar = document.getElementById('search-barA');
            if (searchBar.style.width === '300px') {
                searchBar.style.width = '0';
            } else {
                searchBar.style.width = '300px';
            }
}


    function toggleFormbox() {
        const formbox = document.getElementById('filter-formbox');
        formbox.style.display = formbox.style.display === 'block' ? 'none' : 'block';
    }

    function search() {
    const searchInput = document.getElementById('search-input').value;
    const selectedTags = document.getElementById('selectedTags').value;

    const params = new URLSearchParams();
    params.append('search', searchInput);

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '?' + params.toString();

    const hiddenField = document.createElement('input');
    hiddenField.type = 'hidden';
    hiddenField.name = 'selectedTags';
    hiddenField.value = selectedTags;

    form.appendChild(hiddenField);
    document.body.appendChild(form);
    form.submit();
}

document.getElementById("quick-filter-button").addEventListener("click", function() {
    document.getElementById("quick-filter-popup").style.display = "flex";
});

let currentStep = 0;
const selections = {
    question1: null,
    question2: null,
    question3: [],
};

function handleOptionClick(option) {
    if (currentStep === 0) {
        selections.question1 = option;
        setNextOptions(option === 'Indoor' ? ['Cafe', 'Coworking Space', 'Resto', 'Library'] : ['Cafe', 'Park']);
        nextQuestion();
    } else if (currentStep === 1) {
        selections.question2 = option;
        setNextOptions(
            selections.question1 === 'Indoor'
                ? ['Wi-Fi', 'Outlets', 'Pet-Friendly', 'Air Conditioning', 'Private Rooms', 'Spacious', 'Snacks', 'Books Available']
                : ['Wi-Fi', 'Outlets', 'Fresh Air', 'Green Space', 'Shade', 'Picnic Tables']
        );
        document.getElementById('next-button').style.display = "block";
        nextQuestion();
    } else if (currentStep === 2) {
        const button = document.querySelector(`.option-button[data-option="${option}"]`);
        if (selections.question3.includes(option)) {
            selections.question3 = selections.question3.filter(item => item !== option);
            button.classList.remove('highlight'); 
        } else if (selections.question3.length < 3) {
            selections.question3.push(option);
            button.classList.add('highlight'); 
        } else {
            alert('You can only select up to 3 options.');
        }
    }
}

function setNextOptions(options) {
    const optionsContainer = document.getElementById('popup-options');
    optionsContainer.innerHTML = '';
    options.forEach(opt => {
        const button = document.createElement('button');
        button.className = 'option-button';
        button.textContent = opt;
        button.setAttribute('data-option', opt);
        button.onclick = () => handleOptionClick(opt);
        optionsContainer.appendChild(button);
    });
}

function nextQuestion() {
    currentStep++;
    if (currentStep === 1) {
        document.getElementById('popup-question').textContent = 'What kind of place?';
    } else if (currentStep === 2) {
        document.getElementById('popup-question').textContent = 'What would you prefer? (Select up to 3)';
        document.getElementById('next-button').style.display = 'none';
        document.getElementById('apply-button').style.display = 'block';
    } else {
        applyFilters();
        closePopup();
    }
}

function closePopup() {
    document.getElementById("quick-filter-popup").style.display = "none";
    currentStep = 0;
    selections.question1 = selections.question2 = null;
    selections.question3 = [];
    document.getElementById('popup-question').textContent = 'What are you looking for?';
    setNextOptions(['Indoor', 'Outdoor']);
    document.getElementById('next-button').style.display = "none";
    document.getElementById('apply-button').style.display = "none";
}

function applyFilters() {
    const selectedTags = [];

    if (selections.question1) selectedTags.push({ tag: selections.question1, state: 'check' });
    if (selections.question2) selectedTags.push({ tag: selections.question2, state: 'check' });
    if (selections.question3.length > 0) {
        selections.question3.forEach(pref => {
            selectedTags.push({ tag: pref, state: 'check' });
        });
    }

    document.getElementById('selectedTags').value = JSON.stringify(selectedTags);

    document.querySelector('.filter-form').addEventListener('submit', function(e) {
    const formData = new FormData(this);
    const selectedTags = document.getElementById('selectedTags').value;
    formData.append('selectedTags', selectedTags);
    
    const minPrice = document.getElementById('min_price_filter').value;
    const maxPrice = document.getElementById('max_price_filter').value;
    
    if (minPrice !== '') formData.append('min_price_filter', minPrice);
    if (maxPrice !== '') formData.append('max_price_filter', maxPrice);
});
}

document.getElementById('min_price_filter').addEventListener('change', function() {
    const maxFilter = document.getElementById('max_price_filter');
    const selectedValue = this.value;
    
    if (selectedValue.includes('+')) {
        const minPrice = parseInt(selectedValue.replace('+', ''));
        if (maxFilter.value !== '') {
            const [, maxPrice] = maxFilter.value.split('-');
            if (parseInt(maxPrice) <= minPrice) {
                maxFilter.value = '';
            }
        }
    }
});

document.getElementById('max_price_filter').addEventListener('change', function() {
    const minFilter = document.getElementById('min_price_filter');
    const selectedValue = this.value;
    
    if (selectedValue !== '') {
        const [, maxPrice] = selectedValue.split('-');
        if (minFilter.value.includes('+')) {
            const minPrice = parseInt(minFilter.value.replace('+', ''));
            if (minPrice >= parseInt(maxPrice)) {
                minFilter.value = '';
            }
        }
    }
});
    </script>