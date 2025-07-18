<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['type']) || $_SESSION['type'] !== 'moderator') {
    header("Location: login.php");
    exit;
}

try {
    $conn->begin_transaction();

    $hide_query = "UPDATE bulletin_posts 
                   SET status = 'hidden', hidden_date = NOW() 
                   WHERE status = 'active' 
                   AND DATE(post_date) < DATE_SUB(CURDATE(), INTERVAL 7 DAY)";

    if (!$conn->query($hide_query)) {
        throw new Exception("Error hiding old posts: " . $conn->error);
    }

    $get_old_posts = "SELECT id FROM bulletin_posts 
                      WHERE status = 'hidden' 
                      AND DATE(hidden_date) < DATE_SUB(CURDATE(), INTERVAL 14 DAY)";
    
    $old_posts_result = $conn->query($get_old_posts);

    while ($post = $old_posts_result->fetch_assoc()) {
        $post_id = $post['id'];

        $get_comments = $conn->prepare("
            WITH RECURSIVE CommentHierarchy AS (
                SELECT id, parent_id, 0 as level
                FROM comments
                WHERE post_id = ? AND parent_id IS NULL
                
                UNION ALL
                
                SELECT c.id, c.parent_id, ch.level + 1
                FROM comments c
                INNER JOIN CommentHierarchy ch ON c.parent_id = ch.id
            )
            SELECT id
            FROM CommentHierarchy
            ORDER BY level DESC");
        
        $get_comments->bind_param("i", $post_id);
        $get_comments->execute();
        $comments_result = $get_comments->get_result();

        while ($comment = $comments_result->fetch_assoc()) {
            $delete_comment = $conn->prepare("DELETE FROM comments WHERE id = ?");
            $delete_comment->bind_param("i", $comment['id']);
            $delete_comment->execute();
            $delete_comment->close();
        }

        $delete_post = $conn->prepare("DELETE FROM bulletin_posts WHERE id = ?");
        $delete_post->bind_param("i", $post_id);
        $delete_post->execute();
        $delete_post->close();
    }

    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    error_log("ModBoard Error: " . $e->getMessage());
}

if (isset($_SESSION['email'])) {
    $email = $_SESSION['email'];
    $query = "SELECT * FROM ff_users WHERE email = '$email'";
    $result = mysqli_query($conn, $query);

    if (!$result) {
        die("Query failed: " . mysqli_error($conn));
    }

    $user = mysqli_fetch_assoc($result);
}

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$where_clause = $search ? "WHERE (subject LIKE '%$search%' OR description LIKE '%$search%')" : "";

$total_query = "SELECT COUNT(*) as total_posts FROM bulletin_posts $where_clause";
$total_result = $conn->query($total_query);

$posts_per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $posts_per_page;

$posts_query = "SELECT * FROM bulletin_posts $where_clause ORDER BY timestamp DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($posts_query);
$stmt->bind_param("ii", $posts_per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();
$posts = $result->fetch_all(MYSQLI_ASSOC);


$total_posts = mysqli_fetch_assoc($total_result)['total_posts'];
$total_pages = ceil($total_posts / $posts_per_page);


$stmt = $conn->prepare($posts_query);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
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
    <style>
    .hidden {
        opacity: 0.7;
    }

    .hidden-notice {
        background-color: #fff3cd;
        padding: 10px;
        margin-bottom: 10px;
        border-radius: 4px;
    }

    .mod-actions {
        margin-top: 10px;
        padding: 5px;
        background-color: #f8f9fa;
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

<div class="search-filter-bar">
    <div class="search-container">
        <img src="images/search-icon.png" class="icon" id="search-icon" onclick="toggleSearch()">
        <div class="search-barA" id="search-barA">
            <form method="GET" action="modBoard.php">
                <input type="text" id="search-input" name="search" placeholder="Search for a post...">
                <button type="submit" id="search-button">Search</button>
            </form>
        </div>
    </div>
    <div class="post-container">
        <a href="javascript:void(0);" class="board-post" onclick="openModal()">Post</a>
    </div>
</div>

<div id="postModal" class="board-modal">
    <div class="board-modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2 id="modalTitle">Create a Post</h2>
        <form id="postForm" action="post_handler.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" id="post_id" name="post_id" value="">
            <input type="hidden" id="action_type" name="action_type" value="create">
            
            <label for="subject">Subject:</label>
            <input type="text" id="subject" name="subject" required>
            
            <label for="location">Location:</label>
            <input type="text" id="location" name="location" required>
            
            <label for="description">Description:</label>
            <textarea id="description" name="description" required></textarea>
            
            <label for="image">Upload Image (optional):</label>
            <input type="file" id="image" name="image">
            
            <button type="submit" name="submit" id="submitBtn">Post</button>
        </form>
    </div>
</div>
    
<div class="main-content2">
   
    <div class="bulletin-board">
        <h2>Study Group Bulletin Board</h2>
        <p>See and join study groups posted by others</p>
 
    <div class="bulletin-board-content">
    <div class="boards">
    <?php if (!empty($posts)): ?>
        <?php foreach ($posts as $post): ?>
            <div class="board <?php echo $post['status']; ?> <?php echo $post['flagged'] ? 'flagged-post' : ''; ?>" data-id="<?php echo $post['id']; ?>"> 
                <div class="board-options">
                    <i class="fas fa-ellipsis-h" onclick="toggleDropdown(this)"></i>
                    <div class="dropdown-menu">
                    <?php if (isset($_SESSION['email']) && 
                        ($_SESSION['type'] === 'moderator' || 
                        (isset($post['user_id']) && $post['user_id'] == $_SESSION['user_id']) || 
                        $post['email'] == $_SESSION['email'])): ?>
                        
                        <?php if (isset($_SESSION['email']) && 
                                ((isset($post['user_id']) && $post['user_id'] == $_SESSION['user_id']) || 
                                $post['email'] == $_SESSION['email'])): ?>
                            <a href="#" onclick="toggleEditMode(<?php echo $post['id']; ?>); return false;">Edit</a>
                        <?php endif; ?>
                        
                        <?php if ($_SESSION['type'] === 'moderator' && 
                                $post['email'] !== $_SESSION['email'] && 
                                (!isset($post['user_id']) || $post['user_id'] != $_SESSION['user_id'])): ?>
                            <?php if ($post['flagged']): ?>
                                <a href="#" onclick="showUnflagPostModal(<?php echo $post['id']; ?>); return false;">Unflag</a>
                            <?php else: ?>
                                <a href="#" onclick="showFlagPostModal(<?php echo $post['id']; ?>); return false;">Flag</a>
                            <?php endif; ?>
                        <?php endif; ?>

                        <a href="#" onclick="showDeleteModal(<?php echo $post['id']; ?>); return false;">Delete</a>
                    <?php endif; ?>
                    </div>
                </div>
                <div class="board-details">
                    <?php if ($post['flagged']): ?>
                        <div class="flag-status">
                            <p>This post is flagged for <?php echo htmlspecialchars($post['flag_reason']); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ($post['status'] == 'hidden'): ?>
                        <div class="hidden-notice">
                            <p>This post is no longer active (Hidden on: <?php echo date('Y-m-d', strtotime($post['hidden_date'])); ?>)</p>
                        </div>
                    <?php endif; ?>
                    <h2><?php echo htmlspecialchars($post['subject']); ?></h2>
                    <p><?php echo htmlspecialchars($post['location']); ?></p>
                    <br>
                    <p><?php echo htmlspecialchars($post['description']); ?></p>
                    <br>
                    <p><?php echo htmlspecialchars($post['post_date']); ?></p>
                    <h2><?php echo htmlspecialchars($post['fullname']); ?></h2>
                    <?php if ($post['edited']): ?>
                        <div class="edit-status">
                            <span class="edited-notice">(edited)</span>
                        </div>
                    <?php endif; ?>
                    <br>
                    <a href="modPost.php?id=<?php echo $post['id']; ?>">View Details</a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No posts found.</p>
    <?php endif; ?>
</div>

    <div id="flagPostModal" class="flag-modal">
        <div class="flag-modal-content">
            <h2>Flag Post</h2>
            <div id="postContent" class="content-preview">
                <!-- Post preview will be inserted here -->
            </div>
            <div class="form-group">
                <label for="flagReason" class="flag-label">Reason for flagging:</label>
                <select id="flagReason" class="flag-select" required>
                    <option value="">Select a reason</option>
                    <option value="Inappropriate">Inappropriate Content</option>
                    <option value="Spam">Spam</option>
                    <option value="Harassment">Harassment</option>
                    <option value="False Information">False Information</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="modal-buttons">
                <button id="confirmFlagBtn" class="btn btn-danger">Flag Post</button>
                <button type="button" class="btn btn-secondary flag-cancel">Cancel</button>
            </div>
        </div>
    </div>

    <div id="unflagPostModal" class="unflag-modal">
    <div class="unflag-modal-content">
        <h2>Unflag Post</h2>
        <p>Are you sure you want to unflag this post?</p>
        <div id="unflagPostPreview" class="content-preview">
            <!-- Post preview will be inserted here -->
        </div>
        <div class="unflag-buttons">
            <button id="confirmUnflagBtn" class="btn btn-danger">Yes, Unflag</button>
            <button type="button" class="btn btn-secondary unflag-cancel">Cancel</button>
        </div>
    </div>
</div>

    <div id="deleteModal" class="delete-modal">
        <div class="delete-modal-content">
            <p>Are you sure you want to delete this post?</p>
            <div id="postContent" class="post-content">
                <!-- Post content will be dynamically inserted here -->
            </div>
            <button id="confirmDeleteBtn" class="btn btn-danger">Yes, Delete</button>
            <button type="button" class="btn btn-secondary delete-cancel" onclick="closeDeleteModal()">Cancel</button>
        </div>
    </div>

    <div id="postSuccessMessage" class="success-message" style="display: none;">
        Post deleted successfully.
    </div>

            <div class="pagination">
                <?php if ($total_pages > 1): ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="modBoard.php?page=<?php echo $i; ?>&search=<?php echo $search; ?>" class="<?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function toggleSearch() {
    const searchBar = document.getElementById('search-barA');
    if (searchBar.style.width === '300px') {
        searchBar.style.width = '0';
    } else {
        searchBar.style.width = '300px';
    }
}

let subMenu = document.getElementById("subMenu");

function toggleMenu(){
    subMenu.classList.toggle("open-menu");
}

function openModal() {
    document.getElementById('postModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('postModal').style.display = 'none';
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

function toggleDropdown(element) {
    const dropdowns = document.querySelectorAll('.dropdown-menu');
    dropdowns.forEach(dropdown => {
        if (dropdown !== element.nextElementSibling) {
            dropdown.style.display = 'none';
        }
    });

    const dropdown = element.nextElementSibling;
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
}

function toggleEditMode(postId) {
    const postElement = document.querySelector(`.board[data-id="${postId}"]`);
    const subject = postElement.querySelector('.board-details h2').textContent;
    const location = postElement.querySelector('.board-details p').textContent;
    const description = postElement.querySelectorAll('.board-details p')[1].textContent;
    const currentImageUrl = postElement.querySelector('img') ? postElement.querySelector('img').src : '';

    document.getElementById('modalTitle').textContent = 'Edit Post';
    document.getElementById('action_type').value = 'edit';
    document.getElementById('post_id').value = postId;
    document.getElementById('submitBtn').textContent = 'Save Changes';

    if (!document.getElementById('current_image_url')) {
        const hiddenField = document.createElement('input');
        hiddenField.type = 'hidden';
        hiddenField.id = 'current_image_url';
        hiddenField.name = 'current_image_url';
        document.getElementById('postForm').appendChild(hiddenField);
    }
    document.getElementById('current_image_url').value = currentImageUrl;

    document.getElementById('subject').value = subject;
    document.getElementById('location').value = location;
    document.getElementById('description').value = description;

    openModal();
}

function openModal() {
    const modal = document.getElementById('postModal');
    const form = document.getElementById('postForm');
    const title = document.getElementById('modalTitle');
    
    if (document.getElementById('action_type').value === 'create') {
        form.reset();
        title.textContent = 'Create a Post';
        document.getElementById('post_id').value = '';
        document.getElementById('submitBtn').textContent = 'Post';
    }
    
    modal.style.display = 'block';
}

function savePost(postId) {
    const subject = document.getElementById('edit-subject').value;
    const location = document.getElementById('edit-location').value;
    const description = document.getElementById('edit-description').value;

    fetch('edit_post.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `post_id=${postId}&subject=${encodeURIComponent(subject)}&location=${encodeURIComponent(location)}&description=${encodeURIComponent(description)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            const postElement = document.querySelector(`.board[data-id="${postId}"]`);

            postElement.querySelector('h2').textContent = subject;
            postElement.querySelector('p').textContent = location;
            postElement.querySelectorAll('p')[1].textContent = description;

            if (!postElement.querySelector('.edit-status')) {
                const editStatus = document.createElement('div');
                editStatus.className = 'edit-status';
                editStatus.innerHTML = '<span class="edited-notice">(edited)</span>';
                
                const fullnameElement = postElement.querySelector('h2:last-of-type');
                fullnameElement.insertAdjacentElement('afterend', editStatus);
            }
            
            closeModal();
        } else {
            alert('Error saving changes');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving changes');
    });
}

function showDeleteModal(postId) {
    const postElement = document.querySelector(`.board[data-id="${postId}"]`);
    const postClone = postElement.cloneNode(true);

    const boardOptions = postClone.querySelector('.board-options');
    if (boardOptions) {
        boardOptions.remove();
    }
    
    document.getElementById('postContent').innerHTML = '';
    document.getElementById('postContent').appendChild(postClone);
    document.getElementById('deleteModal').style.display = 'block';
    
    document.getElementById('confirmDeleteBtn').onclick = () => deletePost(postId);
}

function deletePost(postId) {
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    const formData = new FormData();
    formData.append('post_id', postId);
    formData.append('csrf_token', csrfToken);
    
    fetch('delete_post.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            const postElement = document.querySelector(`.board[data-id="${postId}"]`);
            document.getElementById('deleteModal').style.display = 'none';

            document.getElementById('postSuccessMessage').style.display = 'block';

            setTimeout(function() {
                document.getElementById('postSuccessMessage').style.display = 'none';
                location.reload();
            }, 1000);
        } else {
            throw new Error(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert(error.message);
    });
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

let postIdToFlag;

function showFlagPostModal(postId) {
    postIdToFlag = postId;
    const postElement = document.querySelector(`.board[data-id="${postId}"]`);

    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = postElement.innerHTML;

    const elementsToRemove = [
        '.board-options',
        '.dropdown-menu',
        '.edit-mode',
        '.comment-section'
    ];
    
    elementsToRemove.forEach(selector => {
        const elements = tempDiv.querySelectorAll(selector);
        elements.forEach(element => element.remove());
    });

    document.getElementById('postContent').innerHTML = tempDiv.innerHTML;
    document.getElementById('flagPostModal').style.display = 'block';
}

function flagPost(postId) {
    const reason = document.getElementById('flagReason').value;
    if (!reason) {
        alert('Please select a reason for flagging');
        return;
    }

    const csrfToken = document.querySelector('input[name="csrf_token"]').value;

    fetch('flag_post.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${postId}&reason=${encodeURIComponent(reason)}&csrf_token=${csrfToken}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            document.getElementById('flagPostModal').style.display = 'none';
            location.reload();
        } else {
            alert('Error flagging post: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error flagging post');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('confirmFlagBtn').addEventListener('click', function() {
        flagPost(postIdToFlag);
    });

    document.querySelectorAll('.flag-cancel').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('flagPostModal').style.display = 'none';
        });
    });
});

let postIdToUnflag;

function showUnflagPostModal(postId) {
    postIdToUnflag = postId;
    const postElement = document.querySelector(`.board[data-id="${postId}"]`);
    
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = postElement.innerHTML;
    
    const elementsToRemove = [
        '.board-options',
        '.dropdown-menu',
        '.edit-mode',
        '.comment-section'
    ];
    
    elementsToRemove.forEach(selector => {
        const elements = tempDiv.querySelectorAll(selector);
        elements.forEach(element => element.remove());
    });
    
    document.getElementById('unflagPostPreview').innerHTML = tempDiv.innerHTML;
    document.getElementById('unflagPostModal').style.display = 'block';
}

function unflagPost(postId) {
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;

    fetch('unflag_post.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${postId}&csrf_token=${csrfToken}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            document.getElementById('unflagPostModal').style.display = 'none';
            location.reload();
        } else {
            alert('Error unflagging post: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error unflagging post');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('confirmUnflagBtn').addEventListener('click', function() {
        unflagPost(postIdToUnflag);
    });

    document.querySelectorAll('.unflag-cancel').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('unflagPostModal').style.display = 'none';
        });
    });
});

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
</script>
</body>
</html>