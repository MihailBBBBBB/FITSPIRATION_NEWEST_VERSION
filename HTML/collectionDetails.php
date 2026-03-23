<?php
session_start();
require_once "../includes/dbh.inc.php";
include_once "../JS/headerFooter.php";
include_once "../includes/collectionDetails.inc.php";
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($collection['title']); ?> - Collection Details</title>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
        <link rel="stylesheet" href="../CSS/Main.css"/>
        <link rel="stylesheet" href="../CSS/collectionDetails.css"/>
        <script src="../JS/translator.js"></script>
    </head>
    <body>
        <script src="../JS/collectionDetails.js"></script>
        <special-header></special-header>
        
        <div class="layout">
            <special-aside></special-aside>
            
            <div class="home-container">
                <div class="collection-header">
                    <h1 class="collection-title"><?php echo htmlspecialchars($collection['title']); ?></h1>
                    <h2 class="collection-status">Status: <?php echo htmlspecialchars($collection['privacy']); ?></h2>
                    <p class="collection-description"><?php echo htmlspecialchars($collection['description'] ?: 'No description available.'); ?></p>
                </div>
                
                <div class="sort-container">
                    <label for="sort">Sort by: </label>
                    <select id="sort" onchange="applySort()">
                        <option value="date_desc" <?php echo $sort === 'date_desc' ? 'selected' : ''; ?>>Newest</option>
                        <option value="date_asc" <?php echo $sort === 'date_asc' ? 'selected' : ''; ?>>Oldest</option>
                        <option value="likes_desc" <?php echo $sort === 'likes_desc' ? 'selected' : ''; ?>>Most Liked</option>
                        <option value="likes_asc" <?php echo $sort === 'likes_asc' ? 'selected' : ''; ?>>Least Liked</option>
                    </select>
                </div>
                
                <div class="pins-grid">
                    <?php if (empty($pins)): ?>
                        <p>No pins in this collection yet. Add some pins to get started!</p>
                        <?php else: ?>
                            <?php foreach ($pins as $pin): ?>
                                <?php
                        // Determine image path and check if file exists
                        $image_path = $pin['img'] ? '../images/' . htmlspecialchars($pin['img']) : '../images/no_image.jpg';
                        if ($pin['img'] && !file_exists($image_path)) {
                            error_log("Pin image not found: {$image_path}");
                            $image_path = '../images/no_image.jpg';
                        }
                        ?>
                        <div class="pin-item" data-pin-id="<?php echo htmlspecialchars($pin['id'] ?? ''); ?>">
                            <img 
                            src="<?php echo $image_path; ?>" 
                            alt="<?php echo htmlspecialchars($pin['title'] ?? 'Pin'); ?>" 
                            class="pin-image"
                            data-image="<?php echo $image_path; ?>"
                            data-title="<?php echo htmlspecialchars($pin['title'] ?? 'Pin'); ?>"
                            data-pin-id="<?php echo htmlspecialchars($pin['id'] ?? ''); ?>"
                            data-like-count="<?php echo htmlspecialchars($pin['like_count']); ?>"
                            data-user-liked="<?php echo $pin['user_liked'] ? '1' : '0'; ?>"
                            data-creator-id="<?php echo htmlspecialchars($pin['creator_id'] ?? ''); ?>"
                            data-creator-name="<?php echo htmlspecialchars($pin['creator_name'] ?? 'Unknown'); ?>"
                            data-creator-img="<?php echo !empty($pin['creator_img']) ? '../images/' . htmlspecialchars($pin['creator_img']) : '../images/no_image.jpg'; ?>"
                            >
                            <?php if (!empty($pin['id']) && $_SESSION['user_id'] == $user_id): ?>
                                <span class="delete-cross" 
                                data-pin-id="<?php echo htmlspecialchars($pin['id']); ?>" 
                                onclick="openDeleteModal('pin', '<?php echo htmlspecialchars($pin['id']); ?>', event)">×</span>
                                <?php endif; ?>
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
                                        <div class="modal-creator-row">
                                            <img id="modalCreatorAvatar" class="creator-avatar" src="<?php echo htmlspecialchars($modal_pin_data['creator_img'] ?? '../images/no_image.jpg'); ?>" alt="Creator">
                                            <div>
                                                <a id="modalCreatorLink" class="creator-link" href="<?php echo !empty($modal_pin_data['creator_id']) ? 'Profile.php?user_id=' . urlencode($modal_pin_data['creator_id']) : '#'; ?>" style="<?php echo !empty($modal_pin_data['creator_id']) ? '' : 'pointer-events:none; color:#6b7280;'; ?>"><?php echo htmlspecialchars($modal_pin_data['creator_name'] ?? 'Unknown'); ?></a>
                                                <p class="creator-subtitle">Created this pin</p>
                                            </div>
                                        </div>
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
                                                <?php foreach ($comments as $comment): ?>
                                                    <li>
                                                        <img src="<?php echo $comment['user_img'] ? '../images/' . htmlspecialchars($comment['user_img']) : '../images/no_image.jpg'; ?>" alt="User">
                                                        <?php echo htmlspecialchars($comment['username']); ?>: <?php echo htmlspecialchars($comment['comment']); ?>
                                                        <?php if ($comment['user_id'] == $user_id || $_SESSION['user_id'] == $user_id): ?>
                                                            <span class="comment-delete" 
                                                            data-comment-id="<?php echo htmlspecialchars($comment['id']); ?>" 
                                                            data-pin-id="<?php echo htmlspecialchars($_GET['pin_id'] ?? ''); ?>"
                                                            onclick="deleteComment(<?php echo htmlspecialchars($comment['id']); ?>, '<?php echo htmlspecialchars($_GET['pin_id'] ?? ''); ?>')">×</span>
                                                            <?php endif; ?>
                                                        </li>
                                                        <?php endforeach; ?>
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
                                
                                <div id="deleteModal" class="delete-modal">
                                    <div class="delete-modal-content">
                                        <span class="delete-modal-close" onclick="closeDeleteModal()">×</span>
                                        <h2 id="deleteModalTitle">Delete Pin</h2>
                                        <p id="deleteModalText">Do you really want to delete this pin? This action cannot be undone.</p>
                                        <div class="delete-modal-buttons">
                                            <button class="delete-modal-cancel" onclick="closeDeleteModal()">Cancel</button>
                                            <button class="delete-modal-confirm" onclick="confirmDelete()">Delete</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <special-footer></special-footer>
                        
</body>
</html>