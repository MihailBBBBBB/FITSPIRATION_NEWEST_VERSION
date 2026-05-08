<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../HTML/LogIn.php?error=notloggedin");
    exit();
}

include_once '../includes/Home.inc.php';
include_once '../JS/headerFooter.php';
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
        <link rel="stylesheet" href="../CSS/Main.css?v=13"/>
        <link rel="stylesheet" href="../CSS/Home.css?v=25"/>
        <script src="../JS/csrf.js"></script>
        <script src="../JS/translator.js?v=2"></script>
    </head>
    <body data-csrf-token="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES); ?>">
        <special-header></special-header>
        
        <div class="layout">
            <special-aside></special-aside>
            
            <div class="home-container">
                <?php if (isset($_GET['report_status'])): ?>
                    <div class="report-alert <?php echo $_GET['report_status'] === 'ok' ? 'is-ok' : 'is-error'; ?>">
                        <?php echo htmlspecialchars(urldecode($_GET['report_msg'] ?? 'Report action completed.')); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($_GET['smart_feed_status'])): ?>
                    <div class="report-alert <?php echo $_GET['smart_feed_status'] === 'saved' ? 'is-ok' : 'is-error'; ?>">
                        <?php echo $_GET['smart_feed_status'] === 'saved' ? 'Smart feed saved.' : 'Could not save smart feed. Select at least one filter.'; ?>
                    </div>
                <?php endif; ?>
                <div class="feed-hero">
                    <div class="feed-hero-copy">
                        <p class="feed-eyebrow">Community Feed</p>
                        <h1>Explore Public Pins <?php echo $searchTerm ? '<span class="search-context">for “' . htmlspecialchars($searchTerm) . '”</span>' : ''; ?></h1>
                        <p class="feed-subtitle">Browse the latest looks, trending items, and saved inspirations from the FITSPIRATION community.</p>
                    </div>
                        <div class="feed-tabs">
                            <a href="Home.php?feed=for_you&content=<?php echo urlencode((string) ($contentType ?? 'all')); ?>&search_scope=<?php echo urlencode((string) ($searchScope ?? 'all')); ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo !empty($activeDiscoveryFilters['dominant_color']) ? '&color=' . urlencode((string) $activeDiscoveryFilters['dominant_color']) : ''; ?><?php echo !empty($activeDiscoveryFilters['style_tag']) ? '&style=' . urlencode((string) $activeDiscoveryFilters['style_tag']) : ''; ?><?php echo !empty($activeDiscoveryFilters['season']) ? '&season=' . urlencode((string) $activeDiscoveryFilters['season']) : ''; ?><?php echo !empty($activeDiscoveryFilters['category']) ? '&category=' . urlencode((string) $activeDiscoveryFilters['category']) : ''; ?>" class="feed-tab <?php echo isset($feedType) && $feedType === 'for_you' ? 'active' : ''; ?>">
                                <i class="fas fa-sparkles"></i> For You
                            </a>
                            <a href="Home.php?feed=trending&content=<?php echo urlencode((string) ($contentType ?? 'all')); ?>&search_scope=<?php echo urlencode((string) ($searchScope ?? 'all')); ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo !empty($activeDiscoveryFilters['dominant_color']) ? '&color=' . urlencode((string) $activeDiscoveryFilters['dominant_color']) : ''; ?><?php echo !empty($activeDiscoveryFilters['style_tag']) ? '&style=' . urlencode((string) $activeDiscoveryFilters['style_tag']) : ''; ?><?php echo !empty($activeDiscoveryFilters['season']) ? '&season=' . urlencode((string) $activeDiscoveryFilters['season']) : ''; ?><?php echo !empty($activeDiscoveryFilters['category']) ? '&category=' . urlencode((string) $activeDiscoveryFilters['category']) : ''; ?>" class="feed-tab <?php echo isset($feedType) && $feedType === 'trending' ? 'active' : ''; ?>">
                                <i class="fas fa-fire"></i> Trending
                            </a>
                            <a href="Home.php?feed=following&content=<?php echo urlencode((string) ($contentType ?? 'all')); ?>&search_scope=<?php echo urlencode((string) ($searchScope ?? 'all')); ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo !empty($activeDiscoveryFilters['dominant_color']) ? '&color=' . urlencode((string) $activeDiscoveryFilters['dominant_color']) : ''; ?><?php echo !empty($activeDiscoveryFilters['style_tag']) ? '&style=' . urlencode((string) $activeDiscoveryFilters['style_tag']) : ''; ?><?php echo !empty($activeDiscoveryFilters['season']) ? '&season=' . urlencode((string) $activeDiscoveryFilters['season']) : ''; ?><?php echo !empty($activeDiscoveryFilters['category']) ? '&category=' . urlencode((string) $activeDiscoveryFilters['category']) : ''; ?>" class="feed-tab <?php echo isset($feedType) && $feedType === 'following' ? 'active' : ''; ?>">
                                <i class="fas fa-heart"></i> Following
                            </a>
                            <a href="Home.php?feed=new&content=<?php echo urlencode((string) ($contentType ?? 'all')); ?>&search_scope=<?php echo urlencode((string) ($searchScope ?? 'all')); ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo !empty($activeDiscoveryFilters['dominant_color']) ? '&color=' . urlencode((string) $activeDiscoveryFilters['dominant_color']) : ''; ?><?php echo !empty($activeDiscoveryFilters['style_tag']) ? '&style=' . urlencode((string) $activeDiscoveryFilters['style_tag']) : ''; ?><?php echo !empty($activeDiscoveryFilters['season']) ? '&season=' . urlencode((string) $activeDiscoveryFilters['season']) : ''; ?><?php echo !empty($activeDiscoveryFilters['category']) ? '&category=' . urlencode((string) $activeDiscoveryFilters['category']) : ''; ?>" class="feed-tab <?php echo !isset($feedType) || $feedType === 'new' ? 'active' : ''; ?>">
                                <i class="fas fa-clock"></i> New
                            </a>
                        </div>

                        <div class="content-type-tabs">
                            <a class="content-type-tab <?php echo (($contentType ?? 'all') === 'all') ? 'active' : ''; ?>" data-translate="All" href="Home.php?feed=<?php echo urlencode((string) $feedType); ?>&content=all&search_scope=<?php echo urlencode((string) ($searchScope ?? 'all')); ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo !empty($activeDiscoveryFilters['dominant_color']) ? '&color=' . urlencode((string) $activeDiscoveryFilters['dominant_color']) : ''; ?><?php echo !empty($activeDiscoveryFilters['style_tag']) ? '&style=' . urlencode((string) $activeDiscoveryFilters['style_tag']) : ''; ?><?php echo !empty($activeDiscoveryFilters['season']) ? '&season=' . urlencode((string) $activeDiscoveryFilters['season']) : ''; ?><?php echo !empty($activeDiscoveryFilters['category']) ? '&category=' . urlencode((string) $activeDiscoveryFilters['category']) : ''; ?>">All</a>
                            <a class="content-type-tab <?php echo (($contentType ?? 'all') === 'pieces') ? 'active' : ''; ?>" data-translate="Clothing pieces" href="Home.php?feed=<?php echo urlencode((string) $feedType); ?>&content=pieces&search_scope=<?php echo urlencode((string) ($searchScope ?? 'all')); ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo !empty($activeDiscoveryFilters['dominant_color']) ? '&color=' . urlencode((string) $activeDiscoveryFilters['dominant_color']) : ''; ?><?php echo !empty($activeDiscoveryFilters['style_tag']) ? '&style=' . urlencode((string) $activeDiscoveryFilters['style_tag']) : ''; ?><?php echo !empty($activeDiscoveryFilters['season']) ? '&season=' . urlencode((string) $activeDiscoveryFilters['season']) : ''; ?><?php echo !empty($activeDiscoveryFilters['category']) ? '&category=' . urlencode((string) $activeDiscoveryFilters['category']) : ''; ?>">Clothing pieces</a>
                            <a class="content-type-tab <?php echo (($contentType ?? 'all') === 'outfits') ? 'active' : ''; ?>" data-translate="Outfit looks" href="Home.php?feed=<?php echo urlencode((string) $feedType); ?>&content=outfits&search_scope=<?php echo urlencode((string) ($searchScope ?? 'all')); ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo !empty($activeDiscoveryFilters['dominant_color']) ? '&color=' . urlencode((string) $activeDiscoveryFilters['dominant_color']) : ''; ?><?php echo !empty($activeDiscoveryFilters['style_tag']) ? '&style=' . urlencode((string) $activeDiscoveryFilters['style_tag']) : ''; ?><?php echo !empty($activeDiscoveryFilters['season']) ? '&season=' . urlencode((string) $activeDiscoveryFilters['season']) : ''; ?><?php echo !empty($activeDiscoveryFilters['category']) ? '&category=' . urlencode((string) $activeDiscoveryFilters['category']) : ''; ?>">Outfit looks</a>
                            <a class="content-type-tab <?php echo (($contentType ?? 'all') === 'people') ? 'active' : ''; ?>" data-translate="People" href="Home.php?feed=<?php echo urlencode((string) $feedType); ?>&content=people&search_scope=<?php echo urlencode((string) ($searchScope ?? 'all')); ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo !empty($activeDiscoveryFilters['dominant_color']) ? '&color=' . urlencode((string) $activeDiscoveryFilters['dominant_color']) : ''; ?><?php echo !empty($activeDiscoveryFilters['style_tag']) ? '&style=' . urlencode((string) $activeDiscoveryFilters['style_tag']) : ''; ?><?php echo !empty($activeDiscoveryFilters['season']) ? '&season=' . urlencode((string) $activeDiscoveryFilters['season']) : ''; ?><?php echo !empty($activeDiscoveryFilters['category']) ? '&category=' . urlencode((string) $activeDiscoveryFilters['category']) : ''; ?>">People</a>
                            <a class="content-type-tab <?php echo (($contentType ?? 'all') === 'boards') ? 'active' : ''; ?>" data-translate="Boards" href="Home.php?feed=<?php echo urlencode((string) $feedType); ?>&content=boards&search_scope=<?php echo urlencode((string) ($searchScope ?? 'all')); ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo !empty($activeDiscoveryFilters['dominant_color']) ? '&color=' . urlencode((string) $activeDiscoveryFilters['dominant_color']) : ''; ?><?php echo !empty($activeDiscoveryFilters['style_tag']) ? '&style=' . urlencode((string) $activeDiscoveryFilters['style_tag']) : ''; ?><?php echo !empty($activeDiscoveryFilters['season']) ? '&season=' . urlencode((string) $activeDiscoveryFilters['season']) : ''; ?><?php echo !empty($activeDiscoveryFilters['category']) ? '&category=' . urlencode((string) $activeDiscoveryFilters['category']) : ''; ?>">Boards</a>
                        </div>

                        <div class="discovery-panel minimal">
                            <details class="discovery-shell" <?php echo !empty($hasActiveDiscoveryFilters) ? 'open' : ''; ?>>
                                <summary class="discovery-summary">
                                    <span class="discovery-summary-btn">Filters</span>
                                    <?php if (!empty($hasActiveDiscoveryFilters)): ?>
                                        <span class="active-filter-chips">
                                            <?php if (!empty($activeDiscoveryFilters['dominant_color'])): ?><span><?php echo htmlspecialchars(ucfirst((string) $activeDiscoveryFilters['dominant_color'])); ?></span><?php endif; ?>
                                            <?php if (!empty($activeDiscoveryFilters['style_tag'])): ?><span><?php echo htmlspecialchars(ucfirst((string) $activeDiscoveryFilters['style_tag'])); ?></span><?php endif; ?>
                                            <?php if (!empty($activeDiscoveryFilters['season'])): ?><span><?php echo htmlspecialchars(ucfirst((string) $activeDiscoveryFilters['season'])); ?></span><?php endif; ?>
                                            <?php if (!empty($activeDiscoveryFilters['category'])): ?><span><?php echo htmlspecialchars(ucfirst((string) $activeDiscoveryFilters['category'])); ?></span><?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </summary>

                                <form method="GET" action="" class="discovery-filters-form">
                                    <input type="hidden" name="feed" value="<?php echo htmlspecialchars((string) $feedType); ?>">
                                    <input type="hidden" name="content" value="<?php echo htmlspecialchars((string) ($contentType ?? 'all')); ?>">
                                    <input type="hidden" name="search_scope" value="<?php echo htmlspecialchars((string) ($searchScope ?? 'all')); ?>">
                                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars((string) $sort); ?>">
                                    <?php if ($searchTerm !== ''): ?>
                                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>">
                                    <?php endif; ?>

                                    <select name="color">
                                        <option value="" data-translate="All colors">All colors</option>
                                        <?php foreach (($discoveryOptionSets['colors'] ?? []) as $colorOption): ?>
                                            <option value="<?php echo htmlspecialchars($colorOption); ?>" data-translate="<?php echo htmlspecialchars(ucfirst($colorOption)); ?>" <?php echo (($activeDiscoveryFilters['dominant_color'] ?? '') === $colorOption) ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($colorOption)); ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                    <select name="style">
                                        <option value="" data-translate="All styles">All styles</option>
                                        <?php foreach (($discoveryOptionSets['styles'] ?? []) as $styleOption): ?>
                                            <option value="<?php echo htmlspecialchars($styleOption); ?>" data-translate="<?php echo htmlspecialchars(ucfirst($styleOption)); ?>" <?php echo (($activeDiscoveryFilters['style_tag'] ?? '') === $styleOption) ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($styleOption)); ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                    <select name="season">
                                        <option value="" data-translate="All seasons">All seasons</option>
                                        <?php foreach (($discoveryOptionSets['seasons'] ?? []) as $seasonOption): ?>
                                            <option value="<?php echo htmlspecialchars($seasonOption); ?>" data-translate="<?php echo htmlspecialchars(ucfirst($seasonOption)); ?>" <?php echo (($activeDiscoveryFilters['season'] ?? '') === $seasonOption) ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($seasonOption)); ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                    <select name="category">
                                        <option value="" data-translate="All categories">All categories</option>
                                        <?php foreach (($discoveryOptionSets['categories'] ?? []) as $categoryOption): ?>
                                            <option value="<?php echo htmlspecialchars($categoryOption); ?>" data-translate="<?php echo htmlspecialchars(ucfirst($categoryOption)); ?>" <?php echo (($activeDiscoveryFilters['category'] ?? '') === $categoryOption) ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($categoryOption)); ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                    <button type="submit" class="discovery-filter-btn" data-translate="Apply filters">Apply filters</button>
                                    <?php if (!empty($hasActiveDiscoveryFilters)): ?>
                                        <a class="discovery-reset-btn" data-translate="Reset" href="Home.php?feed=<?php echo urlencode((string) $feedType); ?>&content=<?php echo urlencode((string) ($contentType ?? 'all')); ?>&search_scope=<?php echo urlencode((string) ($searchScope ?? 'all')); ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>">Reset</a>
                                    <?php endif; ?>
                                </form>
                            </details>

                            <div class="smart-feed-row compact">
                                <form method="POST" action="" class="smart-feed-save-form compact">
                                    <?php echo csrfInput(); ?>
                                    <input type="hidden" name="feed" value="<?php echo htmlspecialchars((string) $feedType); ?>">
                                    <input type="hidden" name="content" value="<?php echo htmlspecialchars((string) ($contentType ?? 'all')); ?>">
                                    <input type="hidden" name="search_scope" value="<?php echo htmlspecialchars((string) ($searchScope ?? 'all')); ?>">
                                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars((string) $sort); ?>">
                                    <input type="hidden" name="search" value="<?php echo htmlspecialchars((string) $searchTerm); ?>">
                                    <input type="hidden" name="dominant_color" value="<?php echo htmlspecialchars((string) ($activeDiscoveryFilters['dominant_color'] ?? '')); ?>">
                                    <input type="hidden" name="style_tag" value="<?php echo htmlspecialchars((string) ($activeDiscoveryFilters['style_tag'] ?? '')); ?>">
                                    <input type="hidden" name="season" value="<?php echo htmlspecialchars((string) ($activeDiscoveryFilters['season'] ?? '')); ?>">
                                    <input type="hidden" name="category" value="<?php echo htmlspecialchars((string) ($activeDiscoveryFilters['category'] ?? '')); ?>">
                                    <button type="submit" name="save_smart_feed" class="smart-feed-save-btn">Save current filters</button>
                                </form>

                                <?php if (!empty($savedSmartFeeds)): ?>
                                    <div class="smart-feed-list">
                                        <?php foreach ($savedSmartFeeds as $smartFeed): ?>
                                            <a class="smart-feed-chip <?php echo ((int) ($smartFeed['id'] ?? 0) === (int) ($selectedSmartFeedId ?? 0)) ? 'active' : ''; ?>" href="Home.php?feed=<?php echo urlencode((string) $feedType); ?>&content=<?php echo urlencode((string) ($contentType ?? 'all')); ?>&search_scope=<?php echo urlencode((string) ($searchScope ?? 'all')); ?>&sort=<?php echo urlencode((string) $sort); ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>&smart_feed_id=<?php echo (int) ($smartFeed['id'] ?? 0); ?>">
                                                <?php echo htmlspecialchars((string) ($smartFeed['feed_name'] ?? 'Smart Feed')); ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                </div>
                <div class="pins-grid">
                    <?php if (empty($pins1)): ?>
                        <div class="feed-empty-state">
                            <?php if ($feedType === 'for_you'): ?>
                                <h3>Nothing in For You yet</h3>
                                <p>Like a few pins to train recommendations<?php echo $searchTerm ? ' for "' . htmlspecialchars($searchTerm) . '"' : ''; ?>.</p>
                                <a class="empty-state-cta" href="Home.php?feed=trending&content=<?php echo urlencode((string) ($contentType ?? 'all')); ?>&search_scope=<?php echo urlencode((string) ($searchScope ?? 'all')); ?>">Explore Trending</a>
                            <?php elseif ($feedType === 'following'): ?>
                                <h3>Your Following feed is empty</h3>
                                <p>Follow creators to populate this feed<?php echo $searchTerm ? ' for "' . htmlspecialchars($searchTerm) . '"' : ''; ?>.</p>
                                <?php if (!empty($suggestedUsers)): ?>
                                    <div class="suggested-users">
                                        <p class="suggested-title">Suggested creators</p>
                                        <ul class="suggested-users-list">
                                            <?php foreach ($suggestedUsers as $suggestedUser): ?>
                                                <li>
                                                    <a href="Profile.php?user_id=<?php echo (int) $suggestedUser['id']; ?>">
                                                        <img src="<?php echo htmlspecialchars(buildFitspirationImageUrl($suggestedUser['img'] ?? '')); ?>" alt="<?php echo htmlspecialchars($suggestedUser['username']); ?>">
                                                        <span class="no-translate" data-user-content="true"><?php echo htmlspecialchars($suggestedUser['username']); ?></span>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                <a class="empty-state-cta" href="Home.php?feed=new&content=<?php echo urlencode((string) ($contentType ?? 'all')); ?>&search_scope=<?php echo urlencode((string) ($searchScope ?? 'all')); ?>">Browse New Pins</a>
                            <?php elseif ($feedType === 'trending'): ?>
                                <h3>No trending pins yet</h3>
                                <p>Not enough engagement data yet<?php echo $searchTerm ? ' for "' . htmlspecialchars($searchTerm) . '"' : ''; ?>. Check back soon.</p>
                                <a class="empty-state-cta" href="Home.php?feed=new&content=<?php echo urlencode((string) ($contentType ?? 'all')); ?>&search_scope=<?php echo urlencode((string) ($searchScope ?? 'all')); ?>">Browse New Pins</a>
                            <?php else: ?>
                                <h3>No public pins found</h3>
                                <p>No results<?php echo $searchTerm ? ' for "' . htmlspecialchars($searchTerm) . '"' : ''; ?>. Try another search or switch feed.</p>
                                <a class="empty-state-cta" href="Home.php?feed=trending&content=<?php echo urlencode((string) ($contentType ?? 'all')); ?>&search_scope=<?php echo urlencode((string) ($searchScope ?? 'all')); ?>">Explore Trending</a>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                            <?php foreach ($pins1 as $pin): ?>
                                <div class="pin-item" data-pin-id="<?php echo htmlspecialchars($pin['id'] ?? ''); ?>">
                                    <img 
                                    src="<?php echo htmlspecialchars(buildFitspirationImageUrl($pin['img'] ?? '')); ?>" 
                                    alt="<?php echo htmlspecialchars($pin['title'] ?? 'Pin'); ?>" 
                                    class="pin-image pin-open-modal" 
                                    data-image="<?php echo htmlspecialchars(buildFitspirationImageUrl($pin['img'] ?? '')); ?>"
                                    data-title="<?php echo htmlspecialchars($pin['title'] ?? 'Pin'); ?>"
                                    data-pin-id="<?php echo htmlspecialchars($pin['id'] ?? ''); ?>"
                                    data-like-count="<?php echo htmlspecialchars($pin['like_count']); ?>"
                                    data-user-liked="<?php echo $pin['user_liked'] ? '1' : '0'; ?>"
                                    data-creator-id="<?php echo htmlspecialchars($pin['creator_id'] ?? ''); ?>"
                                    data-creator-name="<?php echo htmlspecialchars($pin['creator_name'] ?? 'Unknown'); ?>"
                                    data-creator-img="<?php echo htmlspecialchars(buildFitspirationImageUrl($pin['creator_img'] ?? '')); ?>"
                                    data-outfit-id="<?php echo !empty($pin['outfit_post_id']) ? (int) $pin['outfit_post_id'] : ''; ?>"
                                    >
                                    <div class="pin-info">
                                        <?php if (!empty($pin['feed_badge'])): ?>
                                            <span class="feed-badge"><?php echo htmlspecialchars($pin['feed_badge']); ?></span>
                                        <?php endif; ?>
                                        <h3 class="pin-title"><?php echo htmlspecialchars($pin['title'] ?? 'Untitled'); ?></h3>
                                        <p class="pin-creator">By <a href="Profile.php?user_id=<?php echo htmlspecialchars($pin['creator_id']); ?>"><?php echo htmlspecialchars($pin['creator_name'] ?? 'Unknown'); ?></a></p>
                                        <?php if (!empty($pin['outfit_post_id'])): ?>
                                            <a class="pin-remix-link" href="OutfitBuilder.php?remix_outfit_id=<?php echo (int) $pin['outfit_post_id']; ?>">Remix Outfit</a>
                                        <?php endif; ?>
                                        <a class="pin-remix-link pin-similar-link" href="Home.php?visual_pin_id=<?php echo (int) $pin['id']; ?>&feed=<?php echo urlencode((string) $feedType); ?>&content=all&search_scope=all">Find similar</a>
                                        <div class="pin-discovery-tags">
                                            <?php if (!empty($pin['dominant_color'])): ?><span>#<?php echo htmlspecialchars((string) $pin['dominant_color']); ?></span><?php endif; ?>
                                            <?php if (!empty($pin['style_tag'])): ?><span>#<?php echo htmlspecialchars((string) $pin['style_tag']); ?></span><?php endif; ?>
                                            <?php if (!empty($pin['season'])): ?><span>#<?php echo htmlspecialchars((string) $pin['season']); ?></span><?php endif; ?>
                                            <?php if (!empty($pin['category'])): ?><span>#<?php echo htmlspecialchars((string) $pin['category']); ?></span><?php endif; ?>
                                        </div>
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
                                                <img id="modalCreatorAvatar" class="creator-avatar" src="<?php echo htmlspecialchars(buildFitspirationImageUrl($modal_pin_data['creator_img'] ?? '')); ?>" alt="Creator">
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
                    </div>
                </div>
            </div>
        </div>
            
    <special-footer></special-footer>
        
    <script src="../JS/likes.js?v=1"></script>
    <script src="../JS/Home.js?v=9"></script>
</body>
</html>
