<?php
session_start();
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'date_desc';
include_once '../JS/headerFooter.php';
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
        <title>Profile - <?php echo htmlspecialchars($users['username']); ?></title>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
        <link rel="stylesheet" href="../CSS/Profile.css"/>
        <link rel="stylesheet" href="../CSS/Main.css"/>
    </head>
    <body>
        <special-header></special-header>
        
        <div class="layout">
            <special-aside></special-aside>
            
            <div class="profile-container">
                <div class="profile-header">
                    <img src="<?php echo $users['img'] ? '../images/' . htmlspecialchars($users['img']) : '../images/no_image.jpg'; ?>" 
                    alt="Profile" 
                    class="profile-avatar" 
                    onclick="<?php echo isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id ? 'openAvatarModal()' : ''; ?>"
                    style="<?php echo isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id ? 'cursor: pointer;' : ''; ?>">
                    <div class="profile-info">
                        <h1><?php echo htmlspecialchars($users['username']); ?></h1>
                        <p><?php echo htmlspecialchars($users['description']); ?></p>
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id): ?>
                            <button class="edit-button" onclick="openEditModal()">Edit Profile</button>
                            <?php endif; ?>
                            <div id="editModal" class="modal">
                                <div class="edit-modal-content">
                                    <span class="close-button" onclick="closeEditModal()">×</span>
                                    <h2>Edit Profile</h2>
                                    <form method="POST" action="">
                                        <input type="text" name="username" value="<?php echo htmlspecialchars($users['username']); ?>" placeholder="Enter new username" required>
                                        <textarea name="description" placeholder="Enter new description"><?php echo htmlspecialchars($users['description']); ?></textarea>
                                        <button type="submit" name="update_profile">Save Changes</button>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- Avatar Change Modal -->
                            <div id="avatarModal" class="modal">
                                <div class="edit-modal-content">
                                    <span class="close-button" onclick="closeAvatarModal()">×</span>
                                    <h2>Change Profile Avatar</h2>
                                    <form method="POST" action="" enctype="multipart/form-data">
                                        <div class="upload-box">
                                            <label for="avatar-upload">
                                                <div style="font-size: 2rem;">⬆️</div>
                                                <p>Choose a file</p>
                                            </label>
                                            <input type="file" id="avatar-upload" name="avatar" accept="image/jpeg, image/png" />
                                            <p style="font-size: 0.85rem; margin-top: 20px;">We recommend using high-quality files in .jpg or .png format (less than 20 MB).</p>
                                        </div>
                                        <button type="submit" name="update_avatar" class="modal-option">Update Avatar</button>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="stats">
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo count($pins); ?></div>
                                    <div class="stat-label">Pins</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo count($collections); ?></div>
                                    <div class="stat-label">Boards</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number">5.7k</div>
                                    <div class="stat-label">Followers</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number">482</div>
                                    <div class="stat-label">Following</div>
                                </div>
                            </div>
                            <button class="create-button" onclick="showCreateModal()">Create</button>
                        </div>
                    </div>
                    
                    <div class="profile-tabs">
                        <button class="tab-button active" data-tab="pins">Your Pins</button>
                        <button class="tab-button" data-tab="collections">Collections</button>
                        <button class="tab-button" data-tab="liked">Liked</button>
                    </div>
                    
                   <!-- Modal for Create Options  -->
                    <div id="createModal" class="modal">
                        <div class="modal-content">
                            <span class="close-button" onclick="closeCreateModal()">×</span>
                            <h2>Create</h2>
                            <div class="modal-options">
                                <button class="modal-option" onclick="window.location.href='CreatePin.php'">Create Pin</button>
                                <button class="modal-option" onclick="window.location.href='CreateCollection.php'">Create Collection</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-content active" id="pins">
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
                            <?php if (empty($pins)): ?>
                                <p>No pins found. Create some pins to get started!</p>
                                <?php else: ?>
                                    <?php foreach ($pins as $pin): ?>
                                        <div class="pin-item" data-pin-id="<?php echo htmlspecialchars($pin['id'] ?? ''); ?>">
                                            <img 
                                            src="<?php echo $pin['img'] ? '../images/' . htmlspecialchars($pin['img']) : '../images/no_image.jpg'; ?>" 
                                            alt="<?php echo htmlspecialchars($pin['title'] ?? 'Pin'); ?>" 
                                            class="pin-image" 
                                            onclick="openPinModal('<?php echo $pin['img'] ? '../images/' . htmlspecialchars($pin['img']) : '../images/no_image.jpg'; ?>', '<?php echo htmlspecialchars($pin['title'] ?? 'Pin'); ?>', '<?php echo htmlspecialchars($pin['id'] ?? ''); ?>', <?php echo $pin['like_count']; ?>, <?php echo $pin['user_liked'] ? 'true' : 'false'; ?>)"
                                            >
                                            <?php if (!empty($pin['id']) && $_SESSION['user_id'] == $user_id): ?>
                                                <span class="delete-cross" 
                                                id="delete-cr"
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
                
                <div class="delete-modal" id="deleteModal">
                    <div class="delete-modal-content">
                        <span class="delete-modal-close" onclick="closeDeleteModal()">×</span>
                        <h2 id="deleteModalTitle">Delete Pin</h2>
                        <p id="deleteModalText">Do you really want to delete this pin? This action cannot be undone.</p>
                        <div class="delete-modal-buttons">
                            <button class="delete-modal-cancel" onclick="closeDeleteModal()">Cancel</button>
                            <button class="delete-modal-confirm" onclick="confirmDelete(); closeDeleteModal(); location.reload()">Delete</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="tab-content" id="collections">
                <div class="pins-grid">
                    <?php if (empty($collections)): ?>
                        <p>No collections found. Create some collections to get started!</p>
                        <?php else: ?>
                            <?php foreach ($collections as $collection): ?>
                                <div class="pin-item" data-collection-id="<?php echo htmlspecialchars($collection['collection_id'] ?? ''); ?>">
                                    <?php if (!empty($collection['collection_id']) && $_SESSION['user_id'] == $user_id): ?>
                                        <span class="delete-cross" 
                                        data-collection-id="<?php echo htmlspecialchars($collection['collection_id']); ?>" 
                                        onclick="openDeleteCollectionModal('<?php echo htmlspecialchars($collection['collection_id']); ?>', event)">×</span>
                                        <?php endif; ?>
                                        <a href="collectionDetails.php?collection_id=<?php echo htmlspecialchars($collection['collection_id'] ?? ''); ?>" class="collection-link">
                                            <img 
                                            src="<?php echo $collection['img'] ? '../images/' . htmlspecialchars($collection['img']) : '../images/no_image.jpg'; ?>" 
                                            alt="<?php echo htmlspecialchars($collection['title'] ?? 'Collection'); ?> Collection" 
                                            class="pin-image"
                                            >
                                            <div class="pin-info">
                                                <h3 class="pin-title"><?php echo htmlspecialchars($collection['title'] ?? 'Untitled'); ?></h3>
                                                <div class="pin-stats">
                                                    <span><?php echo htmlspecialchars($collection['pin_count'] ?? '0'); ?> Pins</span>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="delete-collection-modal" id="deleteCollectionModal">
                                    <div class="delete-collection-modal-content">
                                        <span class="delete-modal-close" onclick="closeDeleteCollectionModal()">×</span>
                                        <h2 id="deleteCollectionModalTitle">Delete Collection</h2>
                                        <p id="deleteCollectionModalText">Do you really want to delete this collection? This action cannot be undone.</p>
                                        <div class="delete-modal-buttons">
                                            <button class="delete-modal-cancel" onclick="closeDeleteCollectionModal()">Cancel</button>
                                            <button class="delete-modal-confirm" onclick="confirmDeleteCollection(); closeDeleteModal(); location.reload()">Delete</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="tab-content" id="liked">
                                <div class="sort-container">
                                    <label for="sort_liked">Sort by: </label>
                                    <select id="sort_liked" onchange="applySort(this.value)">
                                        <option value="date_desc" <?php echo $sort === 'date_desc' ? 'selected' : ''; ?>>Newest</option>
                                        <option value="date_asc" <?php echo $sort === 'date_asc' ? 'selected' : ''; ?>>Oldest</option>
                                        <option value="likes_desc" <?php echo $sort === 'likes_desc' ? 'selected' : ''; ?>>Most Liked</option>
                                        <option value="likes_asc" <?php echo $sort === 'likes_asc' ? 'selected' : ''; ?>>Least Liked</option>
                                    </select>
                                </div>
                                <div class="pins-grid">
                                    <?php if (empty($liked_pins)): ?>
                                        <p>No liked pins found.</p>
                                        <?php else: ?>
                                            <?php foreach ($liked_pins as $pin): ?>
                                                <div class="pin-item" data-pin-id="<?php echo htmlspecialchars($pin['id'] ?? ''); ?>">
                                                    <img 
                                                    src="<?php echo $pin['img'] ? '../images/' . htmlspecialchars($pin['img']) : '../images/no_image.jpg'; ?>" 
                                                    alt="<?php echo htmlspecialchars($pin['title'] ?? 'Pin'); ?>" 
                                                    class="pin-image" 
                                                    onclick="openPinModal('<?php echo $pin['img'] ? '../images/' . htmlspecialchars($pin['img']) : '../images/no_image.jpg'; ?>', '<?php echo htmlspecialchars($pin['title'] ?? 'Pin'); ?>', '<?php echo htmlspecialchars($pin['id'] ?? ''); ?>', <?php echo $pin['like_count']; ?>, <?php echo $pin['user_liked'] ? 'true' : 'false'; ?>, )"
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
                                        </div>
                                    </div>
                                </div>
                                
    <special-footer></special-footer>
    
    <script src="../JS/Profile.js"></script>
</body>
</html>