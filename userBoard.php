<?php
require_once "config.php";
session_start();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
    error_log("UserBoard Error: " . $e->getMessage());
}

$posts_query = "SELECT * FROM bulletin_posts WHERE status = 'active' ORDER BY post_date DESC";

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
}

$posts_per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $posts_per_page;

$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$total_query = "SELECT COUNT(*) AS total_posts FROM bulletin_posts WHERE subject LIKE '%$search%' OR description LIKE '%$search%'";
$total_result = mysqli_query($conn, $total_query);

if (!$total_result) {
    die("Query failed: " . mysqli_error($conn));
}

$total_posts = mysqli_fetch_assoc($total_result)['total_posts'];
$total_pages = ceil($total_posts / $posts_per_page);

$posts_query = "SELECT * FROM bulletin_posts 
                WHERE (subject LIKE '%$search%' OR description LIKE '%$search%') 
                AND status = 'active' 
                ORDER BY timestamp DESC 
                LIMIT $posts_per_page OFFSET $offset";
$posts_result = mysqli_query($conn, $posts_query);

if (!$posts_result) {
    die("Query failed: " . mysqli_error($conn));
}

$posts = mysqli_fetch_all($posts_result, MYSQLI_ASSOC);
mysqli_close($conn);
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
<?php if ($user['flagged']): ?>
        <div id="flaggedAlert" class="alert alert-warning popup-notification">
            Your account has been flagged. Some features may be restricted.
        </div>
    <?php endif; ?>

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

<div class="search-filter-bar">
    <div class="search-container">
        <img src="images/search-icon.png" class="icon" id="search-icon" onclick="toggleSearch()">
        <div class="search-barA" id="search-barA">
            <form method="GET" action="userBoard.php">
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
                            <?php if ($post['status'] == 'hidden'): ?>
                            <div class="hidden-notice">
                                <p>This post is no longer active</p>
                                <?php if (isset($_SESSION['type']) && $_SESSION['type'] === 'moderator'): ?>
                                    <p>Hidden on: <?php echo date('Y-m-d', strtotime($post['hidden_date'])); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="board-options">
                            <i class="fas fa-ellipsis-h" onclick="toggleDropdown(this)"></i>
                            <div class="dropdown-menu">
                            <?php if (isset($_SESSION['email']) && 
                                    ((isset($post['user_id']) && $post['user_id'] == $_SESSION['user_id']) || 
                                    $post['email'] == $_SESSION['email'])): ?>
                                <?php if ($post['flagged']): ?>
                                    <a href="#" onclick="showAppealModal('Post', <?php echo $post['id']; ?>); return false;">Appeal Unflag</a>
                                    <a href="#" onclick="showDeleteModal(<?php echo $post['id']; ?>); return false;">Delete</a>
                                <?php else: ?>
                                    <a href="#" onclick="toggleEditMode(<?php echo $post['id']; ?>); return false;">Edit</a>
                                    <a href="#" onclick="showDeleteModal(<?php echo $post['id']; ?>); return false;">Delete</a>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="#" onclick="showReportModal('post', '<?php echo $post['id']; ?>', this.closest('.board').innerHTML); return false;">Report</a>
                            <?php endif; ?>
                        </div>
                        </div>
                        <div class="board-details">
                        <?php if ($post['flagged']): ?>
                            <div class="flag-status">
                                <p>This post is flagged for <?php echo htmlspecialchars($post['flag_reason']); ?></p>
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
                        <a href="userPost.php?id=<?php echo $post['id']; ?>">View Details</a>
                        </div>
                        <?php endif; ?>
                        </div>
                <?php endforeach; ?>
                    <?php else: ?>
                        <p>No posts found.</p>
                    <?php endif; ?>
                </div>
            </div>
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

<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

<div id="postSuccessMessage" class="success-message" style="display: none;">
    Post deleted successfully.
</div>

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

<div class="pagination">
    <?php if ($total_pages > 1): ?>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="userBoard.php?page=<?php echo $i; ?>&search=<?php echo $search; ?>" class="<?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
    <?php endif; ?>
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
    var dropdown = element.nextElementSibling;
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
}


function toggleEditMode(postId) {
    const postElement = document.querySelector(`.board[data-id="${postId}"]`);
    const subject = postElement.querySelector('.board-details h2').textContent;
    const location = postElement.querySelector('.board-details p').textContent;
    const description = postElement.querySelectorAll('.board-details p')[1].textContent;

    document.getElementById('modalTitle').textContent = 'Edit Post';
    document.getElementById('action_type').value = 'edit';
    document.getElementById('post_id').value = postId;
    document.getElementById('submitBtn').textContent = 'Save Changes';

    document.getElementById('subject').value = subject;
    document.getElementById('location').value = location;
    document.getElementById('description').value = description;

    openModal();
}

function openModal() {
    <?php if (isset($_SESSION['flagged']) && $_SESSION['flagged']): ?>
        const flaggedNotification = document.createElement('div');
        flaggedNotification.id = 'flaggedAlert';
        flaggedNotification.className = 'alert alert-warning popup-notification';
        flaggedNotification.innerHTML = 'You cannot make a post while your account is flagged.';
        document.body.appendChild(flaggedNotification);
        
        setTimeout(() => {
            flaggedNotification.style.display = 'block';
            setTimeout(() => {
                flaggedNotification.remove();
            }, 3000);
        }, 100);
        return;
    <?php endif; ?>
    
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
    if (!csrfToken) {
        console.error('CSRF token not found');
        return;
    }

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
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.status === 'success') {
            const postElement = document.querySelector(`.board[data-id="${postId}"]`);
            document.getElementById('deleteModal').style.display = 'none';

            const successMsg = document.getElementById('postSuccessMessage');
            if (successMsg) {
                successMsg.style.display = 'block';

                setTimeout(function() {
                    successMsg.style.display = 'none';
                    location.reload();
                }, 1000);
            }
        } else {
            throw new Error(data.message || 'Delete failed');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting post: ' + error.message);
    });
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

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

    const boardOptions = tempDiv.querySelector('.board-options');
    if (boardOptions) {
        boardOptions.remove();
    }

    form.querySelector('[name="content_type"]').value = contentType;
    form.querySelector('[name="content_id"]').value = contentId;

    previewDiv.innerHTML = tempDiv.innerHTML;

    modal.style.display = 'block';
}

document.querySelector('.report-cancel').onclick = function() {
    document.getElementById('reportModal').style.display = 'none';
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

document.querySelectorAll('.delete-cancel, .report-cancel').forEach(button => {
    button.addEventListener('click', function() {
        this.closest('.delete-modal, .report-modal').style.display = 'none';
    });
});

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
