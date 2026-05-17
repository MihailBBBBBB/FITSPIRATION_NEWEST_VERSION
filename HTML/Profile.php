<?php
session_start();
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'date_desc';
require_once '../includes/image_storage.inc.php';
include_once '../includes/Profile.inc.php';
include_once '../JS/headerFooter.php';

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
        <link rel="stylesheet" href="../CSS/Profile.css?v=22"/>
        <link rel="stylesheet" href="../CSS/Main.css?v=13"/>
        <link rel="stylesheet" href="../CSS/Home.css?v=25"/>
        <script src="../JS/csrf.js"></script>
        <script src="../JS/translator.js"></script>
    </head>
    <body data-csrf-token="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES); ?>">
        <special-header></special-header>
        
        <div class="layout">
            <special-aside></special-aside>
            
            <div class="profile-container" data-remove-liked-on-unlike="<?php echo (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $view_user_id) ? '1' : '0'; ?>">
                <?php if (isset($_GET['report_status'])): ?>
                    <div class="report-alert <?php echo $_GET['report_status'] === 'ok' ? 'is-ok' : 'is-error'; ?>">
                        <?php echo htmlspecialchars(urldecode($_GET['report_msg'] ?? 'Report action completed.')); ?>
                    </div>
                <?php endif; ?>
                <div class="profile-header">
                    <div class="profile-header-copy">
                        <p class="profile-eyebrow">Personal Space</p>
                    <img src="<?php echo htmlspecialchars(buildFitspirationAvatarUrl($users['img'] ?? '', (string) ($users['username'] ?? 'User'))); ?>" 
                    alt="Profile" 
                    class="profile-avatar" 
                    onclick="<?php echo isset($_SESSION['user_id']) && $_SESSION['user_id'] == $view_user_id ? 'openAvatarModal()' : ''; ?>"
                    style="<?php echo isset($_SESSION['user_id']) && $_SESSION['user_id'] == $view_user_id ? 'cursor: pointer;' : ''; ?>">
                    <div class="profile-info">
                        <h1 class="no-translate" data-user-content="true"><?php echo htmlspecialchars($users['username']); ?></h1>
                        <p class="no-translate" data-user-content="true"><?php echo htmlspecialchars((string) ($users['description'] ?? '')); ?></p>
                        <div class="profile-actions">
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $view_user_id): ?>
                            <button class="edit-button" onclick="openEditModal()">Edit Profile</button>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $view_user_id): ?>
                            <button class="create-button" onclick="showCreateModal()">Create</button>
                        <?php elseif (isset($_SESSION['user_id']) && isset($is_following)): ?>
                            <form method="POST" action="" class="profile-follow-form">
                                <?php echo csrfInput(); ?>
                                <input type="hidden" name="target_user_id" value="<?php echo htmlspecialchars($view_user_id); ?>">
                                <?php if ($is_following): ?>
                                    <button type="submit" name="follow_action" value="unfollow" class="follow-button">Unfollow</button>
                                <?php else: ?>
                                    <button type="submit" name="follow_action" value="follow" class="follow-button">Follow</button>
                                <?php endif; ?>
                            </form>
                            <button class="message-button" onclick="window.location.href='Messages.php?username=<?php echo htmlspecialchars($users['username']); ?>'">Message</button>
                        <?php endif; ?>
                        </div>
                            <div id="editModal" class="modal">
                                <div class="edit-modal-content">
                                    <span class="close-button" onclick="closeEditModal()">×</span>
                                    <h2>Edit Profile</h2>
                                    <form method="POST" action="">
                                        <?php echo csrfInput(); ?>
                                        <input type="text" name="username" value="<?php echo htmlspecialchars($users['username']); ?>" placeholder="Enter new username" required>
                                        <textarea name="description" placeholder="Enter new description"><?php echo htmlspecialchars((string) ($users['description'] ?? '')); ?></textarea>
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
                                        <?php echo csrfInput(); ?>
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

                            <div class="profile-challenge-badges">
                                <h3>Challenge Badges</h3>
                                <div class="profile-challenge-badge-grid">
                                    <span class="profile-challenge-badge <?php echo !empty($profileChallengeStats['badges']['weekly_participation']) ? 'earned' : ''; ?>">Weekly participation</span>
                                    <span class="profile-challenge-badge <?php echo !empty($profileChallengeStats['badges']['top3_finisher']) ? 'earned' : ''; ?>">Top 3 finisher</span>
                                    <span class="profile-challenge-badge <?php echo !empty($profileChallengeStats['badges']['first_win']) ? 'earned' : ''; ?>">First win</span>
                                    <span class="profile-challenge-badge <?php echo !empty($profileChallengeStats['badges']['voting_streak']) ? 'earned' : ''; ?>">Voting streak</span>
                                </div>
                                <p>
                                    Streaks: <?php echo (int) ($profileChallengeStats['participation_streak'] ?? 0); ?>w participation,
                                    <?php echo (int) ($profileChallengeStats['voting_streak'] ?? 0); ?>w voting
                                    · Top 3: <?php echo (int) ($profileChallengeStats['top3_finishes'] ?? 0); ?>
                                    · Wins: <?php echo (int) ($profileChallengeStats['wins_count'] ?? 0); ?>
                                </p>
                            </div>
                        </div>
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
                                        <div class="pin-item" data-pin-id="<?php echo is_numeric($pin['id']) ? htmlspecialchars($pin['id']) : ''; ?>" data-outfit-id="<?php echo !empty($pin['outfit_post_id']) ? (int) $pin['outfit_post_id'] : ''; ?>">
                                            <img
                                            src="<?php echo htmlspecialchars(buildFitspirationImageUrl($pin['img'] ?? '')); ?>"
                                            alt="<?php echo htmlspecialchars($pin['title'] ?? 'Pin'); ?>"
                                            class="pin-image"
                                            data-image="<?php echo htmlspecialchars(buildFitspirationImageUrl($pin['img'] ?? '')); ?>"
                                            data-title="<?php echo htmlspecialchars($pin['title'] ?? 'Pin'); ?>"
                                            data-pin-id="<?php echo is_numeric($pin['id']) ? htmlspecialchars($pin['id']) : ''; ?>"
                                            data-like-count="<?php echo htmlspecialchars($pin['like_count']); ?>"
                                            data-user-liked="<?php echo $pin['user_liked'] ? '1' : '0'; ?>"
                                            data-creator-id="<?php echo htmlspecialchars($pin['creator_id'] ?? ''); ?>"
                                            data-creator-name="<?php echo htmlspecialchars($pin['creator_name'] ?? 'Unknown'); ?>"
                                            data-creator-img="<?php echo htmlspecialchars(buildFitspirationAvatarUrl($pin['creator_img'] ?? '', (string) ($pin['creator_name'] ?? 'User'))); ?>"
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
                                                        <form method="POST" action="" class="like-toggle-form" style="margin: 0;">
                                                            <input type="hidden" name="pin_id" value="<?php echo htmlspecialchars($pin['id']); ?>">
                                                            <button type="submit" name="toggle_like" data-pin-id="<?php echo htmlspecialchars($pin['id']); ?>" class="like-button <?php echo $pin['user_liked'] ? 'liked' : ''; ?>">
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
                                                            <form method="POST" action="" class="like-toggle-form" style="margin: 0;">
                                                                <input type="hidden" name="pin_id" value="<?php echo isset($_GET['pin_id']) ? htmlspecialchars($_GET['pin_id']) : ''; ?>">
                                                                <button type="submit" id="modalLikeButton" data-pin-id="<?php echo isset($_GET['pin_id']) ? htmlspecialchars($_GET['pin_id']) : ''; ?>" name="toggle_like" class="like-button <?php echo $modal_pin_data['user_liked'] ? 'liked' : ''; ?>">
                                                                    <i class="fas fa-heart"></i>
                                                                    <span class="like-count" id="modalLikeCount"><?php echo $modal_pin_data['like_count']; ?></span>
                                                                </button>
                                                            </form>
                                                            <a id="modalRemixBtn" class="outfit-action-btn<?php echo !empty($modal_pin_data['outfit_post_id']) ? '' : ' hidden'; ?>" href="<?php echo !empty($modal_pin_data['outfit_post_id']) ? 'OutfitBuilder.php?remix_outfit_id=' . (int) $modal_pin_data['outfit_post_id'] : '#'; ?>">
                                                                <i class="fa-solid fa-shuffle"></i> Remix
                                                            </a>
                                                            <a id="modalFindSimilarBtn" class="outfit-action-btn" href="Home.php?visual_pin_id=<?php echo isset($_GET['pin_id']) ? (int) $_GET['pin_id'] : 0; ?>&content=all&search_scope=all">
                                                                <i class="fa-solid fa-magnifying-glass"></i> Find Similar
                                                            </a>
                                                            <button type="button" class="report-flag-btn" onclick="openReportModal('pin', '<?php echo isset($_GET['pin_id']) ? htmlspecialchars($_GET['pin_id']) : ''; ?>', '<?php echo isset($_GET['pin_id']) ? htmlspecialchars($_GET['pin_id']) : ''; ?>')" title="Report pin">
                                                                <i class="fa-solid fa-flag"></i>
                                                            </button>
                                                        </div>
                                                        <div class="modal-comment-section">
                                                            <ul id="modalCommentList" class="comment-list">
                                                                <?php if (!empty($comments)): ?>
                                                                    <?php foreach ($comments as $comment): ?>
                                                                        <li>
                                                                            <img src="<?php echo htmlspecialchars(buildFitspirationImageUrl($comment['user_img'] ?? '')); ?>" alt="User">
                                                                            <?php echo isset($comment['username']) ? htmlspecialchars($comment['username']) : 'Unknown'; ?>: <?php echo isset($comment['comment']) ? htmlspecialchars($comment['comment']) : ''; ?>
                                                                            <?php
                                                                            $user_can_delete = false;
                                                                            if (isset($_SESSION['user_id'])) {
                                                                                $sessionId = (int)$_SESSION['user_id'];
                                                                                $commentAuthor = (int)($comment['user_id'] ?? 0);
                                                                                $pinOwner = (int)($modal_pin_data['creator_id'] ?? 0);
                                                                                $user_can_delete = ($commentAuthor === $sessionId) || ($pinOwner === $sessionId);
                                                                            }
                                                                            if ($user_can_delete): ?>
                                                                                <button type="button" class="comment-delete-btn"
                                                                                    data-comment-id="<?php echo htmlspecialchars($comment['id']); ?>"
                                                                                    data-pin-id="<?php echo htmlspecialchars($_GET['pin_id'] ?? ''); ?>"
                                                                                    onclick="deleteComment(<?php echo htmlspecialchars($comment['id']); ?>, '<?php echo htmlspecialchars($_GET['pin_id'] ?? ''); ?>')">Delete</button>
                                                                            <?php endif; ?>
                                    </li>
                                    <?php endforeach; ?>
                                    <?php else: ?>
                                        <li>No comments yet.</li>
                                        <?php endif; ?>
                                    </ul>
                                    <form method="POST" action="" id="modalCommentForm">
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

                <div id="reportModal" class="report-modal" aria-hidden="true">
                    <div class="report-modal-content">
                        <button type="button" class="report-close" onclick="closeReportModal()">×</button>
                        <h3 class="report-title">Report Content</h3>
                        <p id="reportModalSubtitle" class="report-subtitle">Select a reason and tell us what happened.</p>
                        <form method="POST" action="" class="report-form-modern">
                            <input type="hidden" name="submit_report" value="1">
                            <input type="hidden" name="report_target_type" id="reportTargetType" value="pin">
                            <input type="hidden" name="report_target_id" id="reportTargetId" value="">
                            <input type="hidden" name="pin_id" id="reportPinId" value="<?php echo isset($_GET['pin_id']) ? htmlspecialchars($_GET['pin_id']) : ''; ?>">

                            <select name="report_category" class="report-select-modern" required>
                                <option value="">Select reason...</option>
                                <option value="spam">Spam</option>
                                <option value="harassment">Harassment</option>
                                <option value="nudity">Nudity/NSFW</option>
                                <option value="hate">Hate Speech</option>
                                <option value="misinformation">Misinformation</option>
                                <option value="copyright">Copyright Violation</option>
                                <option value="other">Other</option>
                            </select>

                            <textarea name="report_reason" class="report-textarea-modern" maxlength="255" placeholder="Describe the issue..." required></textarea>

                            <button type="submit" class="report-submit-modern">Submit Report</button>
                        </form>
                    </div>
                </div>
                
                <div class="delete-modal" id="deleteModal">
                    <div class="delete-modal-content">
                        <span class="delete-modal-close" onclick="closeDeleteModal()">×</span>
                        <h2 id="deleteModalTitle" data-translate="Delete Pin">Delete Pin</h2>
                        <p id="deleteModalText" data-translate="Do you really want to delete this pin? This action cannot be undone.">Do you really want to delete this pin? This action cannot be undone.</p>
                        <div class="delete-modal-buttons">
                            <button class="delete-modal-cancel" data-translate="Cancel" onclick="closeDeleteModal()">Cancel</button>
                            <button class="delete-modal-confirm" data-translate="Delete" onclick="confirmDelete()">Delete</button>
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
                                            src="<?php echo htmlspecialchars(buildFitspirationImageUrl($collection['img'] ?? '')); ?>" 
                                            alt="<?php echo htmlspecialchars($collection['title'] ?? 'Collection'); ?> Collection" 
                                            class="pin-image"
                                            >
                                            <div class="pin-info">
                                                <h3 class="pin-title"><?php echo htmlspecialchars($collection['title'] ?? 'Untitled'); ?></h3>
                                                <div class="pin-stats">
                                                    <span><?php echo htmlspecialchars($collection['pin_count'] ?? '0'); ?> Pins</span>
                                                </div>
                                                <div class="collection-collaboration-meta">
                                                    <div class="collection-role-label <?php echo htmlspecialchars((string) ($collection['access_role'] ?? 'viewer')); ?>">
                                                        <?php echo htmlspecialchars(ucfirst((string) ($collection['access_role'] ?? 'viewer'))); ?>
                                                    </div>
                                                    <div class="collection-owner-row">
                                                        <a class="collection-profile-link" href="Profile.php?user_id=<?php echo (int) ($collection['owner']['user_id'] ?? 0); ?>">
                                                            <img src="<?php echo htmlspecialchars((string) ($collection['owner']['user_img'] ?? '../images/no_image.jpg')); ?>" alt="Owner">
                                                            <span>Owner: <?php echo htmlspecialchars((string) ($collection['owner']['username'] ?? 'Unknown')); ?></span>
                                                        </a>
                                                    </div>

                                                    <?php if (!empty($collection['collaborators'])): ?>
                                                        <div class="collection-collaborator-list">
                                                            <?php foreach ($collection['collaborators'] as $collaborator): ?>
                                                                <a class="collection-collaborator-chip collection-profile-link" href="Profile.php?user_id=<?php echo (int) ($collaborator['user_id'] ?? 0); ?>" title="<?php echo htmlspecialchars((string) ($collaborator['username'] ?? 'Unknown')); ?>">
                                                                    <img src="<?php echo htmlspecialchars((string) ($collaborator['user_img'] ?? '../images/no_image.jpg')); ?>" alt="<?php echo htmlspecialchars((string) ($collaborator['username'] ?? 'User')); ?>">
                                                                    <span class="collection-collaborator-name"><?php echo htmlspecialchars((string) ($collaborator['username'] ?? 'Unknown')); ?></span>
                                                                </a>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="collection-collaborator-empty">No collaborators yet</div>
                                                    <?php endif; ?>
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
                                        <h2 id="deleteCollectionModalTitle" data-translate="Delete Collection">Delete Collection</h2>
                                        <p id="deleteCollectionModalText" data-translate="Do you really want to delete this collection? This action cannot be undone.">Do you really want to delete this collection? This action cannot be undone.</p>
                                        <div class="delete-modal-buttons">
                                            <button class="delete-modal-cancel" data-translate="Cancel" onclick="closeDeleteCollectionModal()">Cancel</button>
                                            <button class="delete-modal-confirm" data-translate="Delete" onclick="confirmDeleteCollection()">Delete</button>
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
                                                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $view_user_id): ?>
                                                    <a href="../HTML/OutfitBuilder.php?outfit_id=<?php echo (int) $outfit['id']; ?>" class="outfit-edit-link">
                                                        <span class="outfit-edit-badge">Edit Outfit</span>
                                                        <img
                                                            src="<?php echo htmlspecialchars(buildFitspirationImageUrl($outfit['img'] ?? '')); ?>"
                                                            alt="<?php echo htmlspecialchars($outfit['name']); ?>"
                                                            class="pin-image outfit-preview-image"
                                                        >
                                                    </a>
                                                <?php else: ?>
                                                    <img
                                                        src="<?php echo htmlspecialchars(buildFitspirationImageUrl($outfit['img'] ?? '')); ?>"
                                                        alt="<?php echo htmlspecialchars($outfit['name']); ?>"
                                                        class="pin-image outfit-preview-image"
                                                    >
                                                <?php endif; ?>
                                                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $view_user_id): ?>
                                                    <span class="delete-cross outfit-delete-cross"
                                                        data-outfit-id="<?php echo htmlspecialchars($outfit['id']); ?>">&times;</span>
                                                <?php endif; ?>
                                                <div class="pin-info">
                                                    <h3 class="pin-title no-translate" data-user-content="true">
                                                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $view_user_id): ?>
                                                            <a href="../HTML/OutfitBuilder.php?outfit_id=<?php echo (int) $outfit['id']; ?>" class="outfit-edit-link"><?php echo htmlspecialchars($outfit['name']); ?></a>
                                                        <?php else: ?>
                                                            <?php echo htmlspecialchars($outfit['name']); ?>
                                                        <?php endif; ?>
                                                    </h3>
                                                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $view_user_id): ?>
                                                        <a href="../HTML/OutfitBuilder.php?outfit_id=<?php echo (int) $outfit['id']; ?>" class="outfit-edit-cta">Open in Builder</a>
                                                    <?php endif; ?>
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
                                                    src="<?php echo htmlspecialchars(buildFitspirationImageUrl($pin['img'] ?? '')); ?>" 
                                                    alt="<?php echo htmlspecialchars($pin['title'] ?? 'Pin'); ?>" 
                                                    class="pin-image"
                                                    data-image="<?php echo htmlspecialchars(buildFitspirationImageUrl($pin['img'] ?? '')); ?>"
                                                    data-title="<?php echo htmlspecialchars($pin['title'] ?? 'Pin'); ?>"
                                                    data-pin-id="<?php echo is_numeric($pin['id']) ? htmlspecialchars($pin['id']) : ''; ?>"
                                                    data-like-count="<?php echo htmlspecialchars($pin['like_count']); ?>"
                                                    data-user-liked="<?php echo $pin['user_liked'] ? '1' : '0'; ?>"
                                                    data-creator-id="<?php echo htmlspecialchars($pin['creator_id'] ?? ''); ?>"
                                                    data-creator-name="<?php echo htmlspecialchars($pin['creator_name'] ?? 'Unknown'); ?>"
                                                    data-creator-img="<?php echo htmlspecialchars(buildFitspirationImageUrl($pin['creator_img'] ?? '')); ?>"
                                                    >
                                                    <div class="pin-info">
                                                        <h3 class="pin-title"><?php echo htmlspecialchars($pin['title'] ?? 'Untitled'); ?></h3>
                                                        <div class="pin-stats">
                                                            <form method="POST" action="" class="like-toggle-form" style="margin: 0;">
                                                                <input type="hidden" name="pin_id" value="<?php echo htmlspecialchars($pin['id']); ?>">
                                                                <button type="submit" name="toggle_like" data-pin-id="<?php echo htmlspecialchars($pin['id']); ?>" class="like-button <?php echo $pin['user_liked'] ? 'liked' : ''; ?>">
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
                            headers: typeof getCsrfHeaders === 'function'
                                ? getCsrfHeaders({ 'Content-Type': 'application/json' })
                                : { 'Content-Type': 'application/json' },
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

    <script></script>
    
    <script src="../JS/likes.js?v=1"></script>
    <script src="../JS/Profile.js?v=11"></script>
</body>
</html>
