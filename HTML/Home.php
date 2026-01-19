<?php
session_start();
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'date_desc'; 
include_once '../JS/headerFooter.php';
include_once '../includes/Home.inc.php';
include_once '../includes/Profile.inc.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../HTML/Login.php?error=notloggedin");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Home</title>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
        <link rel="stylesheet" href="../CSS/Main.css"/>
        <link rel="stylesheet" href="../CSS/Home.css"/>
    </head>
    <body>
        <special-header></special-header>
        
        <div class="layout">
            <special-aside></special-aside>
            
            <div class="home-container">
                <h1>Explore Public Pins <?php echo $searchTerm ? '- Search: "' . htmlspecialchars($searchTerm) . '"' : ''; ?></h1>
                <div class="sort-container">
                    <label for="sort">Sort by: </label>
                    <select id="sort" onchange="applySort(this.value)">
                        <option value="date_desc" <?php echo $sort === 'date_desc' ? 'selected' : ''; ?>>Newest</option>
                        <option value="date_asc" <?php echo $sort === 'date_asc' ? 'selected' : ''; ?>>Oldest</option>
                        <option value="likes_desc" <?php echo $sort === 'likes_desc' ? 'selected' : ''; ?>>Most Liked</option>
                        <option value="likes_asc" <?php echo $sort === 'likes_asc' ? 'selected' : ''; ?>>Least Liked</option>
                    </select>
                </div>
                <div class="pins-grid">
                    <?php if (empty($pins1)): ?>
                        <p>No public pins found<?php echo $searchTerm ? ' for "' . htmlspecialchars($searchTerm) . '"' : ''; ?>. Check if there are public collections with pins.</p>
                        <?php else: ?>
                            <?php foreach ($pins1 as $pin): ?>
                                <div class="pin-item" data-pin-id="<?php echo htmlspecialchars($pin['id'] ?? ''); ?>">
                                    <img 
                                    src="<?php echo $pin['img'] ? '../images/' . htmlspecialchars($pin['img']) : '../images/no_image.jpg'; ?>" 
                                    alt="<?php echo htmlspecialchars($pin['title'] ?? 'Pin'); ?>" 
                                    class="pin-image" 
                                    onclick="openPinModal('<?php echo $pin['img'] ? '../images/' . htmlspecialchars($pin['img']) : '../images/no_image.jpg'; ?>', '<?php echo htmlspecialchars($pin['title'] ?? 'Pin'); ?>', '<?php echo htmlspecialchars($pin['id'] ?? ''); ?>', <?php echo $pin['like_count']; ?>, <?php echo $pin['user_liked'] ? 'true' : 'false'; ?>)"
                                    >
                                    <div class="pin-info">
                                        <h3 class="pin-title"><?php echo htmlspecialchars($pin['title'] ?? 'Untitled'); ?></h3>
                                        <div class="pin-stats">
                                            <form method="POST" action="" style="margin: 0;">
                                                <input type="hidden" name="pin_id" value="<?php echo htmlspecialchars($pin['id']); ?>">
                                                <button type="submit" name="toggle_like" class="like-button <?php echo $pin['user_liked'] ? 'liked' : ''; ?>">
                                                    <i class="fas fa-heart"></i>
                                                    <span class="like-count"><?php echo htmlspecialchars($pin['like_count']); ?></span>
                                                </button>
                                            </form>
                                            <span class="comment-count" data-pin-id="<?php echo htmlspecialchars($pin['id'] ?? ''); ?>">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                                </svg>
                                                <span><?php echo htmlspecialchars($pin['comment_count']); ?></span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <div id="pinModal" class="modal">
                                <div class="pin-modal-content">
                                    <span class="close-button" onclick="closePinModal()">×</span>
                                    <div class="modal-layout">
                                        <div class="modal-image">
                                            <img id="modalPinImage" src="<?php echo $modal_pin_data['image']; ?>" alt="Pin Image" class="modal-pin-image">
                                        </div>
                                        <div class="modal-details">
                                            <h3 id="modalPinTitle" class="pin-title"><?php echo $modal_pin_data['title']; ?></h3>
                                            <div class="modal-pin-stats">
                                                <form method="POST" action="" style="margin: 0;">
                                                    <input type="hidden" name="pin_id" value="<?php echo isset($_GET['pin_id']) ? htmlspecialchars($_GET['pin_id']) : ''; ?>">
                                                    <button type="submit" id="modalLikeButton" name="toggle_like" class="like-button <?php echo $modal_pin_data['user_liked'] ? 'liked' : ''; ?>">
                                                        <i class="fas fa-heart"></i>
                                                        <span class="like-count" id="modalLikeCount"><?php echo $modal_pin_data['like_count']; ?></span>
                                                    </button>
                                                </form>
                                            </div>
                                            <div class="modal-comment-section">
                                                <ul id="modalCommentList" class="comment-list">
                                                    <?php if (!empty($comments)): ?>
                                                        <?php foreach ($comments as $comment): ?>
                                                            <li>
                                                                <img src="<?php echo isset($comment['user_img']) && $comment['user_img'] ? '../images/' . htmlspecialchars($comment['user_img']) : '../images/no_image.jpg'; ?>" alt="User">
                                                                <?php echo isset($comment['username']) ? htmlspecialchars($comment['username']) : 'Unknown'; ?>: <?php echo isset($comment['comment']) ? htmlspecialchars($comment['comment']) : ''; ?>
                                                                <?php 
                                    $user_can_delete = false;
                                    if (isset($comment['user_id']) && isset($_SESSION['user_id']) && $comment['user_id'] == $_SESSION['user_id']) {
                                        $user_can_delete = true;
                                    } elseif (isset($pin_data['user_id']) && isset($_SESSION['user_id']) && $pin_data['user_id'] == $_SESSION['user_id']) {
                                        $user_can_delete = true;
                                    }
                                    if ($user_can_delete): ?>
                                        <span class="comment-delete" 
                                        data-comment-id="<?php echo isset($comment['id']) ? htmlspecialchars($comment['id']) : ''; ?>" 
                                        data-pin-id="<?php echo isset($_GET['pin_id']) ? htmlspecialchars($_GET['pin_id']) : ''; ?>"
                                        onclick="deleteComment(<?php echo isset($comment['id']) ? htmlspecialchars($comment['id']) : ''; ?>, '<?php echo isset($_GET['pin_id']) ? htmlspecialchars($_GET['pin_id']) : ''; ?>')">×</span>
                                        <?php endif; ?>
                                    </li>
                                    <?php endforeach; ?>
                                    <?php else: ?>
                                        <li>No comments yet.</li>
                                        <?php endif; ?>
                                    </ul>
                                    <form method="POST" action="">
                                        <input type="hidden" name="pin_id" value="<?php echo isset($_GET['pin_id']) ? htmlspecialchars($_GET['pin_id']) : ''; ?>">
                                        <div class="comment-input">
                                            <input type="text" name="comment" id="modalCommentInput" placeholder="Add a comment..." required>
                                            <button type="submit" name="add_comment" class="modal-option">Post</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
            
    <special-footer></special-footer>
        
    <script src="../JS/Home.js"></script>
</body>
</html>