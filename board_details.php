<?php
require_once "config.php";

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['fullname'] = $user['fullname'];
    $_SESSION['profile_picture'] = $user['profile_picture'];
} else {
    echo "User not found.";
    exit;
}

$stmt->close();

if (isset($_GET['id'])) {
    $post_id = $_GET['id'];
    
    
    $post_query = "
    SELECT bp.*, u.fullname, u.profile_picture 
    FROM bulletin_posts bp 
    JOIN ff_users u ON bp.email = u.email 
    WHERE bp.id = ?";
    $stmt = $conn->prepare($post_query);
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $post_result = $stmt->get_result();

    if ($post_result->num_rows > 0) {
        $post = $post_result->fetch_assoc();
    } else {
        echo "Post not found.";
        exit;
    }

    $stmt->close();

    $comments_query = "SELECT c.*, u.fullname, u.profile_picture, u.email  FROM comments c JOIN ff_users u ON c.user_id = u.id WHERE c.post_id = ? ORDER BY c.created_at DESC";
    $stmt = $conn->prepare($comments_query);
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $comments_result = $stmt->get_result();
    $comments = [];

    while ($row = $comments_result->fetch_assoc()) {
        error_log("Comment data: " . print_r($row, true));
        $comments[] = $row;
    }

$stmt->close();
} else {
    echo "Invalid request.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['submit_comment']) || isset($_POST['submit_reply'])) {

        $comment = $_POST['comment'];
        $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;
        $user_id = $_SESSION['user_id'];
        $post_id = $_GET['id'];


        if ($parent_id !== null) {
            $stmt = $conn->prepare("SELECT id FROM comments WHERE id = ?");
            $stmt->bind_param("i", $parent_id);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows == 0) {
                $parent_id = null; 
            }
            $stmt->close();
        }

        $return_url = ($_SESSION['type'] === 'moderator') ? 
            "modPost.php?id=" . $post_id : 
            "userPost.php?id=" . $post_id;

        $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, parent_id, comment) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $post_id, $user_id, $parent_id, $comment);
        $stmt->execute();
        $stmt->close();

        header("Location: " . $return_url);
        exit;
    }
}

function displayComments($comments, $parent_id = null) {
    $html = '';
    foreach ($comments as $comment) {
        if ($comment['parent_id'] == $parent_id) {
            $html .= '<div class="comment ' . ($comment['flagged'] ? 'flagged-comment' : '') . '" id="comment-' . $comment['id'] . '">';
            if ($comment['flagged']) {
                $html .= '<div class="flag-status">This comment is flagged for ' . htmlspecialchars($comment['flag_reason']) . '</div>';
            }
            
            $html .= '<div class="comment-options">';
            $html .= '<i class="fas fa-ellipsis-h" onclick="toggleDropdown(this)"></i>';
            $html .= '<div class="dropdown-menu">';
            
            if (isset($_SESSION['email']) && 
                ($comment['user_id'] == $_SESSION['user_id'])) {
                if ($comment['flagged']) {
                    $html .= '<a href="#" onclick="showAppealModal(\'Comment\', ' . $comment['id'] . '); return false;">Appeal Unflag</a>';
                } else {
                    $html .= '<a href="#" onclick="toggleCommentEditMode(' . $comment['id'] . '); return false;">Edit</a>';
                }
                $html .= '<a href="#" onclick="showDeleteModal(' . $comment['id'] . '); return false;">Delete</a>';
            } elseif (isset($_SESSION['type']) && $_SESSION['type'] == 'moderator') {
                if ($comment['flagged']) {
                    $html .= '<a href="#" onclick="showCommentUnflagModal(' . $comment['id'] . '); return false;">Unflag</a>';
                } else {
                    $html .= '<a href="#" onclick="showCommentFlagModal(' . $comment['id'] . '); return false;">Flag</a>';
                }
                $html .= '<a href="#" onclick="showDeleteModal(' . $comment['id'] . '); return false;">Delete</a>';
            } else {
                $html .= '<a href="#" onclick="showReportModal(\'comment\', \'' . $comment['id'] . '\', this.closest(\'.comment\').innerHTML); return false;">Report</a>';
            }
            $html .= '</div></div>';
            $html .= '<div class="comment-header">';
            $html .= '<img src="' . htmlspecialchars($comment['profile_picture']) . '" alt="Profile Picture" class="comment-profile-pic">';
            if (isset($_SESSION['email']) && $comment['email'] == $_SESSION['email']) {
            $html .= '<a href="userProfile.php"><strong>' . htmlspecialchars($comment['fullname']) . '</strong></a>';
            } else {
                if (isset($_SESSION['type']) && $_SESSION['type'] == 'moderator') {
                    $html .= '<a href="modotherProfile.php?email=' . urlencode($comment['email']) . '"><strong>' . htmlspecialchars($comment['fullname']) . '</strong></a>';
                } else {
                    $html .= '<a href="userotherProfile.php?email=' . urlencode($comment['email']) . '"><strong>' . htmlspecialchars($comment['fullname']) . '</strong></a>';
                }
            }
            $html .= '<span>' . htmlspecialchars($comment['created_at']) . '</span>';
            if ($comment['edited']) {
                $html .= '<span class="edited-notice">(edited)</span>';
            }
            $html .= '</div>';

            $html .= '<p class="comment-text" id="comment-text-' . $comment['id'] . '" contenteditable="false">' . nl2br(htmlspecialchars($comment['comment'])) . '</p>';
            $html .= '<textarea class="edit-comment-text editable-field" id="edit-comment-' . $comment['id'] . '" style="display:none;">' . htmlspecialchars($comment['comment']) . '</textarea>';
            $html .= '<div class="edit-buttons" style="display:none;" id="edit-buttons-' . $comment['id'] . '">';
            $html .= '<button class="save-comment-btn btn" onclick="saveComment(' . $comment['id'] . ')">Save</button>';
            $html .= '<button class="cancel-comment-btn btn" onclick="cancelCommentEdit(' . $comment['id'] . ')">Cancel</button>';
            $html .= '</div>';

            $html .= '<div class="reply-link-container">';
            $html .= '<h3><a href="javascript:void(0);" onclick="showReplyForm(' . $comment['id'] . ')">Reply</a></h3>';
            $html .= '</div>';
            
            $html .= '<div id="reply-form-' . $comment['id'] . '" class="reply-form" style="display:none;">';
            $html .= '<form action="board_details.php?id=' . $_GET['id'] . '#comment-' . $comment['id'] . '" method="post">';
            $html .= '<div class="reply-box-container" style="position: relative;">';
            $html .= '<textarea name="comment" required></textarea>';
            $html .= '<div class="reply-box-buttons">';
            $html .= '<button type="button" class="cancel-button" onclick="hideReplyForm(' . $comment['id'] . ')">Cancel</button>';
            $html .= '<button type="submit" name="submit_reply">Submit</button>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '<input type="hidden" name="parent_id" value="' . $comment['id'] . '">';
            $html .= '<input type="hidden" name="return_url" value="' . htmlspecialchars($_SERVER['REQUEST_URI']) . '#comment-' . $comment['id'] . '">';
            $html .= '</form>';
            $html .= '</div>';
            $html .= displayComments($comments, $comment['id']);
            $html .= '</div>';
        }
    }
    return $html;
}
?>

<div id="deleteModal" class="delete-modal">
    <div class="delete-modal-content">
        <h2>Delete Comment</h2>
        <p>Are you sure you want to delete this comment?</p>
        <div id="commentContent" class="comment-content">
            <!-- Comment content will be dynamically inserted here -->
        </div>
        <div class="modal-buttons">
            <button id="confirmDeleteBtn" class="btn btn-danger" onclick="deleteComment(commentIdToDelete)">Yes, Delete</button>
            <button type="button" class="btn btn-secondary delete-cancel">Cancel</button>
        </div>
    </div>
</div>

<div id="deletePostModal" class="delete-modal">
    <div class="delete-modal-content">
        <h2>Delete Post</h2>
        <p>Are you sure you want to delete this post?</p>
        <div id="postPreview" class="content-preview">
            <!-- Post preview will be inserted here -->
        </div>
        <div class="modal-buttons">
            <button id="confirmDeletePostBtn" class="btn btn-danger">Yes, Delete</button>
            <button type="button" class="btn btn-secondary delete-cancel">Cancel</button>
        </div>
    </div>
</div>

<input type="hidden" id="csrf_token" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

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

<div id="flagModal" class="flag-modal">
    <div class="flag-modal-content">
        <h2>Flag Comment</h2>
        <p>Are you sure you want to flag this comment?</p>
        <div id="flagCommentContent">
            <!-- Comment preview will be inserted here -->
        </div>
        <label for="flagReason" class="flag-label">Reason for flagging:</label>
        <select id="flagReason" class="flag-select">
            <option value="">-</option>
            <option value="Inappropriate">Inappropriate Content</option>
            <option value="Spam">Spam</option>
            <option value="Harassment">Harassment</option>
            <option value="False Information">False Information</option>
            <option value="Other">Other</option>
        </select>
        <div class="modal-buttons">
            <button id="confirmFlagBtn" class="btn btn-danger" onclick="flagComment(commentIdToFlag)">Flag Comment</button>
            <button type="button" class="btn btn-secondary flag-cancel">Cancel</button>
        </div>
    </div>
</div>

<div id="unflagModal" class="unflag-modal">
    <div class="unflag-modal-content">
        <h2>Unflag Comment</h2>
        <p>Are you sure you want to unflag this comment?</p>
        <div id="unflagCommentContent" class="comment-preview">
            <!-- Comment preview will be inserted here -->
        </div>
        <div class="unflag-buttons">
            <button id="confirmUnflagBtn" class="btn btn-danger" onclick="unflagComment(commentIdToUnflag)">Yes, Unflag</button>
            <button type="button" class="btn btn-secondary unflag-cancel">Cancel</button>
        </div>
    </div>
</div>

<div id="flagPostModal" class="flag-modal">
    <div class="flag-modal-content">
        <h2>Flag Post</h2>
        <p>Are you sure you want to flag this post?</p>
        <div id="postContent" class="content-preview">
            <!-- Post preview will be inserted here -->
        </div>
        <label for="flagPostReason" class="flag-label">Reason for flagging:</label>
        <select id="flagPostReason" class="flag-select">
            <option value="">-</option>
            <option value="Inappropriate">Inappropriate Content</option>
            <option value="Spam">Spam</option>
            <option value="Harassment">Harassment</option>
            <option value="False Information">False Information</option>
            <option value="Other">Other</option>
        </select>
        <div class="modal-buttons">
            <button id="confirmFlagBtn" class="btn btn-danger" onclick="flagPost(postIdToFlag)">Flag Post</button>
            <button type="button" class="btn btn-secondary flag-cancel">Cancel</button>
        </div>
    </div>
</div>

<div id="unflagPostModal" class="unflag-modal">
    <div class="unflag-modal-content">
        <h2>Unflag Post</h2>
        <p>Are you sure you want to unflag this post?</p>
        <div id="unflagPostContent" class="content-preview">
            <!-- Post preview will be inserted here -->
        </div>
        <div class="unflag-buttons">
            <button id="confirmUnflagBtn" class="btn btn-danger" onclick="unflagPost(postIdToUnflag)">Yes, Unflag</button>
            <button type="button" class="btn btn-secondary unflag-cancel">Cancel</button>
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

<div class="main-content2">
    <div class="posts">
        <div class="post-info <?php echo $post['flagged'] ? 'flagged-post' : ''; ?>">
            <?php if ($post): ?>
                <?php if ($post['flagged']): ?>
                    <div class="flag-status">This post is flagged for <?php echo htmlspecialchars($post['flag_reason']); ?></div>
                <?php endif; ?>
                <div class="poster-info">
                <div class="post-options">
                    <i class="fas fa-ellipsis-h" onclick="toggleDropdown(this)"></i>
                    <div class="dropdown-menu">
                        <?php if (isset($_SESSION['email']) && $post['email'] == $_SESSION['email']): ?>
                            <?php if ($post['flagged']): ?>
                                <a href="#" onclick="showAppealModal('Post', <?php echo $post['id']; ?>); return false;">Appeal Unflag</a>
                            <?php else: ?>
                                <a href="#" onclick="togglePostEditMode(); return false;">Edit</a>
                            <?php endif; ?>
                            <a href="#" onclick="showDeletePostModal(<?php echo $post['id']; ?>); return false;">Delete</a>
                        <?php elseif (isset($_SESSION['type']) && $_SESSION['type'] == 'moderator'): ?>
                            <?php if ($post['flagged']): ?>
                                <a href="#" onclick="showPostUnflagModal(<?php echo $post['id']; ?>); return false;">Unflag</a>
                            <?php else: ?>
                                <a href="#" onclick="showPostFlagModal(<?php echo $post['id']; ?>); return false;">Flag</a>
                            <?php endif; ?>
                            <a href="#" onclick="showDeletePostModal(<?php echo $post['id']; ?>); return false;">Delete</a>
                        <?php else: ?>
                            <a href="#" onclick="showReportModal('post', '<?php echo $post['id']; ?>', document.querySelector('.post-info').innerHTML); return false;">Report</a>
                        <?php endif; ?>
                    </div>
                </div>
                    
                    <?php if (isset($_SESSION['email']) && $post['email'] == $_SESSION['email']): ?>
                        <a href="userProfile.php">
                            <img src="<?php echo htmlspecialchars($post['profile_picture']); ?>" alt="Profile Picture">
                            <h2><?php echo htmlspecialchars($post['fullname']); ?></h2>
                        </a>
                    <?php else: ?>
                        <?php if (isset($_SESSION['type']) && $_SESSION['type'] == 'moderator'): ?>
                            <a href="modotherProfile.php?email=<?php echo urlencode($post['email']); ?>">
                                <img src="<?php echo htmlspecialchars($post['profile_picture']); ?>" alt="Profile Picture">
                                <h2><?php echo htmlspecialchars($post['fullname']); ?></h2>
                            </a>
                        <?php else: ?>
                            <a href="userotherProfile.php?email=<?php echo urlencode($post['email']); ?>">
                                <img src="<?php echo htmlspecialchars($post['profile_picture']); ?>" alt="Profile Picture">
                                <h2><?php echo htmlspecialchars($post['fullname']); ?></h2>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php if ($post['edited']): ?>
                        <div class="edit-status">
                            <span class="edited-notice">(edited)</span>
                        </div>
                    <?php endif; ?>

                <div class="post-details" id="post-details">
                    <div class="view-mode">
                        <p>Subject: <span id="post-subject"><?php echo htmlspecialchars($post['subject']); ?></span></p>
                        <p>Location: <span id="post-location"><?php echo htmlspecialchars($post['location']); ?></p>
                        <br>
                        <p id="post-description"><?php echo nl2br(htmlspecialchars($post['description'])); ?></p>
                    </div>
                    <div class="edit-mode" style="display: none;">
                        <p>Subject: <input type="text" id="edit-subject" value="<?php echo htmlspecialchars($post['subject']); ?>" class="edit-field"></p>
                        <p>Location: <input type="text" id="edit-location" value="<?php echo htmlspecialchars($post['location']); ?>" class="edit-field"></p>
                        <br>
                        <p>Description:</p>
                        <textarea id="edit-description" class="edit-field"><?php echo htmlspecialchars($post['description']); ?></textarea>
                        <div class="edit-buttons">
                            <button onclick="savePost(<?php echo $post['id']; ?>)" class="btn save-btn">Save Changes</button>
                            <button onclick="cancelPostEdit()" class="btn cancel-btn">Cancel</button>
                        </div>
                    </div>
                </div>

                <?php if (!empty($post['image_url'])): ?>
                    <img src="<?php echo htmlspecialchars($post['image_url']); ?>" alt="Post Image" class="post-image">
                <?php endif; ?>

                <div class="comment-section" id="comments">
                    <h3>Comments</h3>
                    <form action="board_details.php?id=<?php echo $_GET['id']; ?>#comments" method="post">
                        <div class="comment-box-container" style="position: relative;">
                            <textarea name="comment" class="comment-box" required></textarea>
                            <div class="comment-box-buttons">
                                <button type="button" class="cancel-button">Cancel</button>
                                <button type="submit" name="submit_comment" class="submit-button">Submit</button>
                            </div>
                        </div>
                        <input type="hidden" name="parent_id" value="">
                        <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']) . '#comments'; ?>">
                    </form>
                    <?php echo displayComments($comments); ?>
                </div>
            <?php else: ?>
                <p>No details available for this post.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
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

function showReplyForm(commentId) {
const replyForm = document.getElementById('reply-form-' + commentId);
replyForm.style.display = 'block';

document.addEventListener('click', function(event) {
    if (!replyForm.contains(event.target) && event.target.tagName !== 'A') {
        replyForm.style.display = 'none';
    }
});
}

function hideReplyForm(commentId) {
    var replyForm = document.getElementById('reply-form-' + commentId);
    if (replyForm) {
        replyForm.style.display = 'none';
    }
}

    document.addEventListener('DOMContentLoaded', function() {
        const replyTextareas = document.querySelectorAll('.reply-box-container textarea');
        replyTextareas.forEach(textarea => {
            textarea.addEventListener('focus', function() {
                textarea.classList.add('expanded');
                const buttons = textarea.parentElement.querySelector('.reply-box-buttons');
                buttons.style.display = 'flex';
            });
        });
    });


document.addEventListener('DOMContentLoaded', function() {
    const replyTextareas = document.querySelectorAll('.reply-box-container textarea');
    replyTextareas.forEach(textarea => {
        textarea.addEventListener('focus', function() {
            textarea.classList.add('expanded');
            const buttons = textarea.parentElement.querySelector('.reply-box-buttons');
            buttons.style.display = 'flex';
        });
    });
});

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.flag-cancel').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('flagPostModal').style.display = 'none';
        });
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const commentBox = document.querySelector('.comment-box');
    const cancelButton = document.querySelector('.cancel-button');

    commentBox.addEventListener('focus', function() {
        commentBox.classList.add('expanded');
        const buttons = commentBox.parentElement.querySelector('.comment-box-buttons');
        buttons.style.display = 'flex';
    });

    cancelButton.addEventListener('click', function(event) {
        event.preventDefault();
        commentBox.classList.remove('expanded');
        commentBox.value = '';
        const buttons = commentBox.parentElement.querySelector('.comment-box-buttons');
        buttons.style.display = 'none';
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const commentBox = document.querySelector('.comment-box');
    const commentButtons = document.querySelector('.comment-box-buttons');
    const replyTextareas = document.querySelectorAll('.reply-box-container textarea');

    commentBox.addEventListener('focus', function() {
        commentBox.classList.add('expanded');
        commentButtons.style.display = 'flex';
    });

    replyTextareas.forEach(textarea => {
        textarea.addEventListener('focus', function() {
            textarea.classList.add('expanded');
            const buttons = textarea.parentElement.querySelector('.reply-box-buttons');
            buttons.style.display = 'flex';
        });
    });

    document.addEventListener('click', function(event) {
        if (!commentBox.contains(event.target) && 
            !commentButtons.contains(event.target)) {
            commentBox.classList.remove('expanded');
            commentButtons.style.display = 'none';
        }

        replyTextareas.forEach(textarea => {
            const buttons = textarea.parentElement.querySelector('.reply-box-buttons');
            if (!textarea.contains(event.target) && 
                !buttons.contains(event.target)) {
                textarea.classList.remove('expanded');
                buttons.style.display = 'none';
            }
        });
    });

    commentBox.addEventListener('click', function(event) {
        event.stopPropagation();
    });
    commentButtons.addEventListener('click', function(event) {
        event.stopPropagation();
    });

    replyTextareas.forEach(textarea => {
        textarea.addEventListener('click', function(event) {
            event.stopPropagation();
        });
        const buttons = textarea.parentElement.querySelector('.reply-box-buttons');
        buttons.addEventListener('click', function(event) {
            event.stopPropagation();
        });
    });
});

function toggleDropdown(element) {
var dropdowns = document.getElementsByClassName("dropdown-menu");
for (var i = 0; i < dropdowns.length; i++) {
    if (dropdowns[i] !== element.nextElementSibling) {
        dropdowns[i].style.display = 'none';
    }
}
var dropdown = element.nextElementSibling;
dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
}

function toggleCommentEditMode(commentId) {
    const commentText = document.getElementById('comment-text-' + commentId);
    const editText = document.getElementById('edit-comment-' + commentId);
    const editButtons = document.getElementById('edit-buttons-' + commentId);

    if (commentText.style.display !== 'none') {
        editText.value = commentText.innerText.trim();
        commentText.style.display = 'none';
        editText.style.display = 'block';
        editButtons.style.display = 'block';
    } else {
        commentText.style.display = 'block';
        editText.style.display = 'none';
        editButtons.style.display = 'none';
    }
}

function cancelCommentEdit(commentId) {
    const commentText = document.getElementById('comment-text-' + commentId);
    const editText = document.getElementById('edit-comment-' + commentId);
    const editButtons = document.getElementById('edit-buttons-' + commentId);

    commentText.style.display = 'block';
    editText.style.display = 'none';
    editButtons.style.display = 'none';
    editText.value = commentText.innerText.trim(); 
}

function saveComment(commentId) {
    const editText = document.getElementById('edit-comment-' + commentId).value.trim();
    if (!editText) {
        alert('Comment cannot be empty');
        return;
    }
    
    fetch('edit_comment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `comment_id=${commentId}&comment_text=${encodeURIComponent(editText)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            const commentText = document.getElementById('comment-text-' + commentId);
            commentText.innerHTML = editText.replace(/\n/g, '<br>');
            
            const commentHeader = commentText.parentElement.querySelector('.comment-header');
            if (!commentHeader.querySelector('.edited-notice')) {
                const editedSpan = document.createElement('span');
                editedSpan.className = 'edited-notice';
                editedSpan.textContent = '(edited)';
                commentHeader.appendChild(editedSpan);
            }
            
            toggleCommentEditMode(commentId);
        } else {
            alert(data.message || 'Error saving comment');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving comment');
    });
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
    
    if (contentType === 'post') {
        const posterInfo = tempDiv.querySelector('.poster-info');
        const postDetails = tempDiv.querySelector('.post-details');
        
        const dropdowns = posterInfo.querySelectorAll('.post-options, .dropdown-menu');
        dropdowns.forEach(el => el.remove());

        previewDiv.innerHTML = '';
        if (posterInfo) previewDiv.appendChild(posterInfo.cloneNode(true));
        if (postDetails) previewDiv.appendChild(postDetails.cloneNode(true));
        
        const editMode = previewDiv.querySelector('.edit-mode');
        if (editMode) editMode.remove();
    } else {
        const elementsToRemove = [
            '.comment-options',
            '.dropdown-menu',
            '.edit-buttons',
            '.reply-link-container',
            '.reply-form'
        ];
        
        elementsToRemove.forEach(selector => {
            const element = tempDiv.querySelector(selector);
            if (element) element.remove();
        });
        
        previewDiv.innerHTML = tempDiv.innerHTML;
    }

    form.querySelector('[name="content_type"]').value = contentType;
    form.querySelector('[name="content_id"]').value = contentId;

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

let commentIdToDelete = null;
let commentIdToFlag = null;
let commentIdToUnflag = null;


function deleteComment(commentId) {
    if (!commentId) {
        console.error('No comment ID provided');
        return;
    }

    fetch('delete_comment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `comment_id=${commentId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            document.getElementById('deleteModal').style.display = 'none';
            
            const successMessage = document.createElement('div');
            successMessage.className = 'success-message';
            successMessage.innerHTML = 'Comment deleted successfully.';
            document.body.appendChild(successMessage);

            setTimeout(() => {
                successMessage.remove();
                location.reload();
            }, 1000);
        } else {
            throw new Error(data.message || 'Failed to delete comment');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting comment: ' + error.message);
    });
}

function showDeleteModal(commentId) {
    commentIdToDelete = commentId;
    const commentElement = document.getElementById('comment-' + commentId);

    const previewDiv = document.createElement('div');
    previewDiv.className = 'comment-preview';
    
    const headerClone = commentElement.querySelector('.comment-header').cloneNode(true);
    const textClone = commentElement.querySelector('.comment-text').cloneNode(true);
    
    previewDiv.appendChild(headerClone);
    previewDiv.appendChild(textClone);
    
    const commentContentDiv = document.getElementById('commentContent');
    commentContentDiv.innerHTML = '';
    commentContentDiv.appendChild(previewDiv);
    
    document.getElementById('deleteModal').style.display = 'block';
}

function showCommentFlagModal(commentId) {
    commentIdToFlag = commentId;
    const commentElement = document.getElementById('comment-' + commentId);
    
    const previewDiv = document.createElement('div');
    previewDiv.className = 'comment-preview';
    
    const headerClone = commentElement.querySelector('.comment-header').cloneNode(true);
    const textClone = commentElement.querySelector('.comment-text').cloneNode(true);

    previewDiv.appendChild(headerClone);
    previewDiv.appendChild(textClone);
    
    const commentContentDiv = document.getElementById('flagCommentContent');
    commentContentDiv.innerHTML = '';
    commentContentDiv.appendChild(previewDiv);
    
    document.getElementById('flagReason').value = '';
    document.getElementById('flagModal').style.display = 'block';
}

function showPostFlagModal(postId) {
    postIdToFlag = postId;
    const postElement = document.querySelector('.post-info');
    
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = postElement.innerHTML;
    
    const elementsToRemove = [
        '.post-options',
        '.dropdown-menu',
        '.edit-mode',
        '.comment-section'
    ];
    
    elementsToRemove.forEach(selector => {
        const elements = tempDiv.querySelectorAll(selector);
        elements.forEach(element => element.remove());
    });
    
    const previewDiv = document.getElementById('postContent');
    previewDiv.innerHTML = tempDiv.innerHTML;
    
    document.getElementById('flagPostReason').value = '';
    document.getElementById('flagPostModal').style.display = 'block';
}

function flagPost(postId) {
    const reason = document.getElementById('flagPostReason').value;
    const csrfToken = document.getElementById('csrf_token').value;

    console.log('Flagging post:', postId, 'with reason:', reason);

    fetch('flag_post.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${postId}&reason=${encodeURIComponent(reason)}&csrf_token=${csrfToken}`
    })
    .then(response => response.json())
    .then(data => {
        console.log('Response:', data); 
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

function showDeletePostModal(postId) {
    const postInfo = document.querySelector('.post-info');
    
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = postInfo.innerHTML;
    
    const elementsToRemove = [
        '.post-options',
        '.dropdown-menu',
        '.edit-mode',
        '.comment-section'
    ];
    
    elementsToRemove.forEach(selector => {
        const elements = tempDiv.querySelectorAll(selector);
        elements.forEach(element => element.remove());
    });
    
    const previewDiv = document.getElementById('postPreview');
    previewDiv.innerHTML = tempDiv.innerHTML;
    
    const confirmBtn = document.getElementById('confirmDeletePostBtn');
    confirmBtn.onclick = () => deletePost(postId);
    
    document.getElementById('deletePostModal').style.display = 'block';
}

function deletePost(postId) {
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    if (!csrfToken) {
        console.error('CSRF token not found');
        return;
    }

    const isModPage = window.location.pathname.includes('modPost.php');
    const redirectUrl = isModPage ? 'modBoard.php' : 'userBoard.php';

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
            document.getElementById('deleteModal').style.display = 'none';
            
            const successMsg = document.getElementById('postSuccessMessage');
            if (successMsg) {
                successMsg.style.display = 'block';
                
                setTimeout(function() {
                    successMsg.style.display = 'none';
                    window.location.href = redirectUrl;
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

document.querySelectorAll('.delete-cancel').forEach(button => {
    button.addEventListener('click', function() {
        document.getElementById('deletePostModal').style.display = 'none';
    });
});

window.onclick = function(event) {
    const deleteModal = document.getElementById('deletePostModal');
    if (event.target == deleteModal) {
        deleteModal.style.display = 'none';
    }
}

function flagComment(commentId) {
    const reason = document.getElementById('flagReason').value;
    if (!reason) {
        alert('Please select a reason.');
        return;
    }

    fetch('flag_comment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${commentId}&reason=${encodeURIComponent(reason)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            document.getElementById('flagModal').style.display = 'none';
            location.reload();
        } else {
            alert('Error flagging comment: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error flagging comment');
    });
}

function showCommentUnflagModal(commentId) {
    commentIdToUnflag = commentId;
    const commentElement = document.getElementById('comment-' + commentId);
    
    const previewDiv = document.createElement('div');
    previewDiv.className = 'comment-preview';

    const headerClone = commentElement.querySelector('.comment-header').cloneNode(true);
    const textClone = commentElement.querySelector('.comment-text').cloneNode(true);

    previewDiv.appendChild(headerClone);
    previewDiv.appendChild(textClone);

    const commentContentDiv = document.getElementById('unflagCommentContent');
    commentContentDiv.innerHTML = '';
    commentContentDiv.appendChild(previewDiv);
    
    document.getElementById('unflagModal').style.display = 'block';
}

function unflagComment(commentId) {
    fetch('unflag_comment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${commentId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            document.getElementById('unflagModal').style.display = 'none';
            location.reload();
        } else {
            alert('Error unflagging comment: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error unflagging comment');
    });
}

function showPostUnflagModal(postId) {
    postIdToUnflag = postId;
    const postElement = document.querySelector('.post-info');

    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = postElement.innerHTML;

    const elementsToRemove = [
        '.post-options',
        '.dropdown-menu',
        '.edit-mode',
        '.comment-section'
    ];
    
    elementsToRemove.forEach(selector => {
        const elements = tempDiv.querySelectorAll(selector);
        elements.forEach(element => element.remove());
    });
    
    const previewDiv = document.getElementById('unflagPostContent');
    previewDiv.innerHTML = tempDiv.innerHTML;

    document.getElementById('unflagPostModal').style.display = 'block';
}

function unflagPost(postId) {
    const csrfToken = document.getElementById('csrf_token').value;

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

document.querySelectorAll('.delete-cancel, .flag-cancel, .unflag-cancel').forEach(button => {
    button.addEventListener('click', function() {
        document.getElementById('deleteModal').style.display = 'none';
        document.getElementById('flagModal').style.display = 'none';
        document.getElementById('unflagModal').style.display = 'none';
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

    function togglePostEditMode() {
    const viewMode = document.querySelector('.view-mode');
    const editMode = document.querySelector('.edit-mode');
    const saveBtn = document.querySelector('.save-btn');

    if (viewMode.style.display !== 'none') {
        viewMode.style.display = 'none';
        editMode.style.display = 'block';
        saveBtn.style.display = 'inline-block';
    } else {
        viewMode.style.display = 'block';
        editMode.style.display = 'none';
        saveBtn.style.display = 'none';
    }
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
            document.getElementById('post-subject').textContent = subject;
            document.getElementById('post-location').textContent = location;
            document.getElementById('post-description').innerHTML = description.replace(/\n/g, '<br>');
            
            if (!document.querySelector('.edit-status')) {
                const editStatus = document.createElement('div');
                editStatus.className = 'edit-status';
                editStatus.innerHTML = '<span class="edited-notice">(edited)</span>';

                const posterInfo = document.querySelector('.poster-info');
                posterInfo.insertAdjacentElement('afterend', editStatus);
            }
            
            togglePostEditMode();
        } else {
            alert('Error saving changes');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving changes');
    });
}

function cancelPostEdit() {
    togglePostEditMode();
}

document.addEventListener('DOMContentLoaded', function() {
    const flaggedAlert = document.getElementById('flaggedAlert');
    if (flaggedAlert) {
        flaggedAlert.style.display = 'block';
        setTimeout(() => {
            flaggedAlert.style.display = 'none';
        }, 3000);

        const submitButtons = document.querySelectorAll('button[type="submit"]');
        submitButtons.forEach(button => {
            if (button.form && 
                (button.form.id === 'comment-form' || button.form.classList.contains('reply-form'))) {
                button.disabled = true;
                button.title = 'You cannot comment while your account is flagged';
            }
        });

        const textareas = document.querySelectorAll('textarea[name="comment"]');
        textareas.forEach(textarea => {
            textarea.disabled = true;
            textarea.placeholder = 'Comments are restricted while your account is flagged';
        });
    }
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