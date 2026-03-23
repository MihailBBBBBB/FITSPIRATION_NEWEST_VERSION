<?php
session_start();
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'date_desc';
include_once '../JS/headerFooter.php';
include_once '../includes/Profile.inc.php';

// Allow viewing profiles without login (for clicking creator links).
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
        <link rel="stylesheet" href="../CSS/Home.css"/>
        <script src="../JS/translator.js"></script>
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
                    onclick="<?php echo isset($_SESSION['user_id']) && $_SESSION['user_id'] == $view_user_id ? 'openAvatarModal()' : ''; ?>"
                    style="<?php echo isset($_SESSION['user_id']) && $_SESSION['user_id'] == $view_user_id ? 'cursor: pointer;' : ''; ?>">
                    <div class="profile-info">
                        <h1 class="no-translate" data-user-content="true"><?php echo htmlspecialchars($users['username']); ?></h1>
                        <p class="no-translate" data-user-content="true"><?php echo htmlspecialchars($users['description']); ?></p>
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $view_user_id): ?>
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
                                        <input type="hidden" name="update_avatar" value="1">
                                        <div class="upload-box">
                                            <label for="avatar-upload">
                                                <div style="font-size: 2rem;">⬆️</div>
                                                <p>Choose a file</p>
                                            </label>
                                            <input
                                                type="file"
                                                id="avatar-upload"
                                                class="preview-input"
                                                name="avatar"
                                                accept="image/jpeg, image/png"
                                                data-preview-width="140px"
                                                data-preview-max-width="140px"
                                                data-preview-height="140px"
                                                data-preview-radius="50%"
                                            />
                                            <p class="upload-hint" style="font-size: 0.85rem; margin-top: 20px;">We recommend using high-quality files in .jpg or .png format (less than 20 MB).</p>
                                        </div>
                                        <button type="submit" class="modal-option">Update Avatar</button>
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
                                    <button id="openFollowers" class="stat-link">
                                        <div class="stat-number"><?php echo isset($followers_count) ? htmlspecialchars($followers_count) : '0'; ?></div>
                                        <div class="stat-label">Followers</div>
                                    </button>
                                </div>
                                <div class="stat-item">
                                    <button id="openFollowing" class="stat-link">
                                        <div class="stat-number"><?php echo isset($following_count) ? htmlspecialchars($following_count) : '0'; ?></div>
                                        <div class="stat-label">Following</div>
                                    </button>
                                </div>
                            </div>
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $view_user_id): ?>
                                <button class="create-button" onclick="showCreateModal()">Create</button>
                            <?php elseif (isset($_SESSION['user_id']) && isset($is_following)): ?>
                                <form method="POST" action="" style="display:inline; margin-left: 1rem;">
                                    <input type="hidden" name="target_user_id" value="<?php echo htmlspecialchars($view_user_id); ?>">
                                    <?php if ($is_following): ?>
                                        <button type="submit" name="follow_action" value="unfollow" class="follow-button">Unfollow</button>
                                    <?php else: ?>
                                        <button type="submit" name="follow_action" value="follow" class="follow-button">Follow</button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div id="followListModal" class="modal">
                        <div class="modal-content follow-list-modal-content">
                            <span class="close-button" onclick="closeFollowListModal()">×</span>
                            <h2 id="followListModalTitle">Followers</h2>
                            <ul id="followList" class="follow-list"></ul>
                        </div>
                    </div>
                    
                    <div class="profile-tabs">
                        <button class="tab-button active" data-tab="pins">Your Pins</button>
                        <button class="tab-button" data-tab="collections">Collections</button>
                        <button class="tab-button" data-tab="liked">Liked</button>
                        <button class="tab-button" data-tab="outfits">Outfits</button>
                    </div>
                    
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $view_user_id): ?>
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
                    <?php endif; ?>
                    
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
                                        <div class="pin-item" data-pin-id="<?php echo is_numeric($pin['id']) ? htmlspecialchars($pin['id']) : ''; ?>">
                                            <img
                                            src="<?php echo $pin['img'] ? '../images/' . htmlspecialchars($pin['img']) : '../images/no_image.jpg'; ?>"
                                            alt="<?php echo htmlspecialchars($pin['title'] ?? 'Pin'); ?>"
                                            class="pin-image"
                                            data-image="<?php echo $pin['img'] ? '../images/' . htmlspecialchars($pin['img']) : '../images/no_image.jpg'; ?>"
                                            data-title="<?php echo htmlspecialchars($pin['title'] ?? 'Pin'); ?>"
                                            data-pin-id="<?php echo is_numeric($pin['id']) ? htmlspecialchars($pin['id']) : ''; ?>"
                                            data-like-count="<?php echo htmlspecialchars($pin['like_count']); ?>"
                                            data-user-liked="<?php echo $pin['user_liked'] ? '1' : '0'; ?>"
                                            data-creator-id="<?php echo htmlspecialchars($pin['creator_id'] ?? ''); ?>"
                                            data-creator-name="<?php echo htmlspecialchars($pin['creator_name'] ?? 'Unknown'); ?>"
                                            data-creator-img="<?php echo $pin['creator_img'] ? '../images/' . htmlspecialchars($pin['creator_img']) : '../images/no_image.jpg'; ?>"
                                            >
                                            <?php if (!empty($pin['id']) && isset($_SESSION['user_id']) && $_SESSION['user_id'] == $view_user_id): ?>
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
                                                        <div class="modal-creator-row">
                                                            <img id="modalCreatorAvatar" class="creator-avatar" src="<?php echo htmlspecialchars($modal_pin_data['creator_img'] ?? '../images/no_image.jpg'); ?>" alt="Creator">
                                                            <div>
                                                                <?php
                                                                $modal_creator_href = '#';
                                                                if (!empty($modal_pin_data['creator_id'])) {
                                                                    $modal_creator_href = 'Profile.php?user_id=' . urlencode($modal_pin_data['creator_id']);
                                                                }
                                                                $modal_creator_name = htmlspecialchars($modal_pin_data['creator_name'] ?? 'Unknown');
                                                                ?>
                                                                <a id="modalCreatorLink" class="creator-link" href="<?php echo !empty($modal_pin_data['creator_id']) ? 'Profile.php?user_id=' . urlencode($modal_pin_data['creator_id']) : '#'; ?>" style="<?php echo !empty($modal_pin_data['creator_id']) ? '' : 'pointer-events:none; color:#6b7280;'; ?>"><?php echo $modal_creator_name; ?></a>
                                                                <p class="creator-subtitle">Created this pin</p>
                                                                    <script src="../JS/CreateMediaPreview.js"></script>
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
                                        <form method="POST" action="" class="comment-delete-form" style="display:inline;">
                                            <input type="hidden" name="delete_comment" value="1">
                                            <input type="hidden" name="comment_id" value="<?php echo htmlspecialchars($comment['id']); ?>">
                                            <input type="hidden" name="pin_id" value="<?php echo isset($_GET['pin_id']) ? htmlspecialchars($_GET['pin_id']) : ''; ?>">
                                            <button type="submit" class="comment-delete">×</button>
                                        </form>
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
                                    <?php if (!empty($collection['collection_id']) && isset($_SESSION['user_id']) && $_SESSION['user_id'] == $view_user_id): ?>
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
                            
                            <div class="tab-content" id="outfits">
                                <div class="pins-grid">
                                    <?php if (empty($outfits)): ?>
                                        <p>No outfits saved yet. Build an outfit and save it to your profile!</p>
                                    <?php else: ?>
                                        <?php foreach ($outfits as $outfit): ?>
                                            <div class="pin-item outfit-card-item" data-outfit-id="<?php echo htmlspecialchars($outfit['id']); ?>">
                                                <img
                                                    src="<?php echo '../images/' . htmlspecialchars($outfit['img']); ?>"
                                                    alt="<?php echo htmlspecialchars($outfit['name']); ?>"
                                                    class="pin-image"
                                                >
                                                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $view_user_id): ?>
                                                    <span class="delete-cross outfit-delete-cross"
                                                        data-outfit-id="<?php echo htmlspecialchars($outfit['id']); ?>">&times;</span>
                                                <?php endif; ?>
                                                <div class="pin-info">
                                                    <h3 class="pin-title no-translate" data-user-content="true"><?php echo htmlspecialchars($outfit['name']); ?></h3>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
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
                                                <div class="pin-item" data-pin-id="<?php echo is_numeric($pin['id']) ? htmlspecialchars($pin['id']) : ''; ?>">
                                                    <img 
                                                    src="<?php echo $pin['img'] ? '../images/' . htmlspecialchars($pin['img']) : '../images/no_image.jpg'; ?>" 
                                                    alt="<?php echo htmlspecialchars($pin['title'] ?? 'Pin'); ?>" 
                                                    class="pin-image"
                                                    data-image="<?php echo $pin['img'] ? '../images/' . htmlspecialchars($pin['img']) : '../images/no_image.jpg'; ?>"
                                                    data-title="<?php echo htmlspecialchars($pin['title'] ?? 'Pin'); ?>"
                                                    data-pin-id="<?php echo is_numeric($pin['id']) ? htmlspecialchars($pin['id']) : ''; ?>"
                                                    data-like-count="<?php echo htmlspecialchars($pin['like_count']); ?>"
                                                    data-user-liked="<?php echo $pin['user_liked'] ? '1' : '0'; ?>"
                                                    data-creator-id="<?php echo htmlspecialchars($pin['creator_id'] ?? ''); ?>"
                                                    data-creator-name="<?php echo htmlspecialchars($pin['creator_name'] ?? 'Unknown'); ?>"
                                                    data-creator-img="<?php echo $pin['creator_img'] ? '../images/' . htmlspecialchars($pin['creator_img']) : '../images/no_image.jpg'; ?>"
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

    <script>
        const followerUsers = <?php echo json_encode($follower_users ?? []); ?>;
        const followingUsers = <?php echo json_encode($following_users ?? []); ?>;

        function renderFollowList(type) {
            const listEl = document.getElementById('followList');
            const titleEl = document.getElementById('followListModalTitle');
            const items = type === 'followers' ? followerUsers : followingUsers;
            titleEl.textContent = type === 'followers' ? 'Followers' : 'Following';
            listEl.innerHTML = '';

            if (items.length === 0) {
                listEl.innerHTML = '<li class="follow-list-item">No users yet.</li>';
                return;
            }

            items.forEach(user => {
                const li = document.createElement('li');
                li.className = 'follow-list-item';
                li.innerHTML = `
                    <div class="follow-user">
                        <img src="${user.img ? '../images/' + escapeHtml(user.img) : '../images/no_image.jpg'}" alt="${escapeHtml(user.username)}" class="follow-avatar" />
                        <a href="Profile.php?user_id=${encodeURIComponent(user.id)}" class="follow-username">${escapeHtml(user.username)}</a>
                    </div>
                `;
                listEl.appendChild(li);
            });
        }

        function openFollowListModal(type) {
            renderFollowList(type);
            const modal = document.getElementById('followListModal');
            if (modal) modal.style.display = 'flex';
        }

        function closeFollowListModal() {
            const modal = document.getElementById('followListModal');
            if (modal) modal.style.display = 'none';
        }

        function escapeHtml(string) {
            if (!string) return '';
            return String(string).replace(/[&<>"]/, function(s) {
                return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[s];
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const followersBtn = document.getElementById('openFollowers');
            const followingBtn = document.getElementById('openFollowing');

            if (followersBtn) {
                followersBtn.addEventListener('click', function(e){ e.preventDefault(); openFollowListModal('followers'); });
            }
            if (followingBtn) {
                followingBtn.addEventListener('click', function(e){ e.preventDefault(); openFollowListModal('following'); });
            }

            window.addEventListener('click', function(e) {
                const modal = document.getElementById('followListModal');
                if (e.target === modal) closeFollowListModal();
            });

            // Delete outfit cards
            document.querySelectorAll('.outfit-delete-cross').forEach(function(btn) {
                btn.addEventListener('click', async function(e) {
                    e.stopPropagation();
                    const outfitId = btn.dataset.outfitId;
                    if (!outfitId) return;
                    if (!confirm('Delete this outfit?')) return;

                    try {
                        const res = await fetch('../includes/deleteOutfit.inc.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ outfit_id: parseInt(outfitId, 10) })
                        });
                        const data = await res.json();
                        if (data.success) {
                            const card = btn.closest('.outfit-card-item');
                            if (card) card.remove();
                        } else {
                            alert(data.error || 'Could not delete outfit.');
                        }
                    } catch (err) {
                        alert('Network error. Please try again.');
                    }
                });
            });
        });
    </script>
    
    <script src="../JS/Profile.js"></script>
    <script src="../JS/Home.js"></script>
</body>
</html>