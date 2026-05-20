<?php
session_start();
require_once '../includes/OutfitChallenge.inc.php';
include_once '../JS/headerFooter.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outfit Challenge - Fitspiration</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <link rel="stylesheet" href="../CSS/Main.css?v=14">
    <link rel="stylesheet" href="../CSS/OutfitChallenge.css?v=34">
    <script src="../JS/csrf.js"></script>
    <script src="../JS/translator.js?v=3"></script>
</head>
<body data-csrf-token="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES); ?>">
    <special-header></special-header>

    <div class="layout">
        <special-aside></special-aside>

        <main class="main-content challenge-page">
            <section class="challenge-hero">
                <p class="challenge-eyebrow">Community Event</p>
                <h1><?php echo htmlspecialchars((string) ($activeChallenge['theme'] ?? 'Weekly Challenge')); ?></h1>
                <p class="challenge-subtitle"><?php echo htmlspecialchars((string) ($activeChallenge['description'] ?? 'Submit your best outfit and vote for the strongest looks this week.')); ?></p>
                <div class="challenge-meta">
                    <span><i class="fa-solid fa-calendar-week"></i> <?php echo htmlspecialchars((string) ($activeChallenge['week_key'] ?? '')); ?></span>
                    <span><i class="fa-regular fa-clock"></i> <?php echo htmlspecialchars((string) ($activeChallenge['starts_at'] ?? '')); ?> - <?php echo htmlspecialchars((string) ($activeChallenge['ends_at'] ?? '')); ?></span>
                    <span class="challenge-deadline js-challenge-countdown" data-challenge-ends="<?php echo htmlspecialchars((string) ($activeChallenge['ends_at'] ?? ''), ENT_QUOTES); ?>"><i class="fa-solid fa-hourglass-half"></i> Ends soon</span>
                    <span><i class="fa-solid fa-users"></i> <?php echo count($challengeEntries); ?> entries</span>
                </div>
                <div class="challenge-hero-actions">
                    <button type="button" class="participate-btn" id="openParticipateModalBtn">
                        <i class="fa-solid fa-medal"></i>
                        <span id="challengeParticipateBtnLabel" data-label-key="<?php echo htmlspecialchars($myEntry ? 'Update Participation' : 'Participate', ENT_QUOTES); ?>"><?php echo $myEntry ? 'Update Participation' : 'Participate'; ?></span>
                    </button>
                </div>
            </section>

            <section class="challenge-badges-panel">
                <div class="challenge-badges-head">
                    <h2 id="challengeBadgesTitle">Your Challenge Badges</h2>
                    <p id="challengeBadgesSubtitle">Progress updates automatically from your challenge activity.</p>
                </div>
                <div class="challenge-badge-chip-grid">
                    <span class="challenge-badge-chip <?php echo !empty($challengeBadgeStats['badges']['weekly_participation']) ? 'earned' : ''; ?>">
                        <i class="fa-solid fa-calendar-check"></i> Weekly participation
                    </span>
                    <span class="challenge-badge-chip <?php echo !empty($challengeBadgeStats['badges']['top3_finisher']) ? 'earned' : ''; ?>">
                        <i class="fa-solid fa-trophy"></i> Top 3 finisher
                    </span>
                    <span class="challenge-badge-chip <?php echo !empty($challengeBadgeStats['badges']['first_win']) ? 'earned' : ''; ?>">
                        <i class="fa-solid fa-crown"></i> First win
                    </span>
                    <span class="challenge-badge-chip <?php echo !empty($challengeBadgeStats['badges']['voting_streak']) ? 'earned' : ''; ?>">
                        <i class="fa-solid fa-fire"></i> Voting streak
                    </span>
                </div>
                <div class="challenge-badge-stats">
                    <span><strong><?php echo (int) ($challengeBadgeStats['participation_streak'] ?? 0); ?></strong> week participation streak</span>
                    <span><strong><?php echo (int) ($challengeBadgeStats['voting_streak'] ?? 0); ?></strong> week voting streak</span>
                    <span><strong><?php echo (int) ($challengeBadgeStats['top3_finishes'] ?? 0); ?></strong> top 3 finishes</span>
                    <span><strong><?php echo (int) ($challengeBadgeStats['wins_count'] ?? 0); ?></strong> wins</span>
                </div>
            </section>

            <?php if (!empty($previousWeekWinner)): ?>
                <section class="winner-spotlight">
                    <div class="winner-spotlight-head">
                        <p class="winner-eyebrow">Previous Week Winner</p>
                        <h2><?php echo htmlspecialchars((string) ($previousWeekWinner['challenge']['theme'] ?? 'Last Week')); ?></h2>
                        <p>
                            <span><?php echo htmlspecialchars((string) ($previousWeekWinner['challenge']['week_key'] ?? '')); ?></span>
                            <span> • </span>
                            <span><?php echo htmlspecialchars((string) ($previousWeekWinner['challenge']['starts_at'] ?? '')); ?> - <?php echo htmlspecialchars((string) ($previousWeekWinner['challenge']['ends_at'] ?? '')); ?></span>
                        </p>
                    </div>
                    <div class="winner-spotlight-card">
                        <img src="<?php echo !empty($previousWeekWinner['entry']['outfit_img']) ? '../images/' . rawurlencode((string) $previousWeekWinner['entry']['outfit_img']) : '../images/no_image.jpg'; ?>" alt="Previous week winner">
                        <div class="winner-spotlight-copy">
                            <h3><?php echo htmlspecialchars((string) ($previousWeekWinner['entry']['outfit_name'] ?? 'Winning Outfit')); ?></h3>
                            <p class="winner-author no-translate" data-user-content="true">by <a href="Profile.php?user_id=<?php echo (int) ($previousWeekWinner['entry']['user_id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($previousWeekWinner['entry']['username'] ?? 'Unknown')); ?></a></p>
                            <?php if (!empty($previousWeekWinner['entry']['caption'])): ?>
                                <p class="winner-caption"><?php echo htmlspecialchars((string) $previousWeekWinner['entry']['caption']); ?></p>
                            <?php endif; ?>
                            <div class="winner-stats">
                                <span><i class="fa-solid fa-bolt"></i> <?php echo (int) ($previousWeekWinner['entry']['vote_count'] ?? 0); ?> votes</span>
                                <span><i class="fa-solid fa-heart"></i> <?php echo (int) ($previousWeekWinner['entry']['like_count'] ?? 0); ?> likes</span>
                            </div>
                            <button
                                type="button"
                                class="winner-view-btn js-open-outfit-preview"
                                data-preview-image="<?php echo !empty($previousWeekWinner['entry']['outfit_img']) ? '../images/' . rawurlencode((string) $previousWeekWinner['entry']['outfit_img']) : '../images/no_image.jpg'; ?>"
                                data-preview-title="<?php echo htmlspecialchars((string) ($previousWeekWinner['entry']['outfit_name'] ?? 'Winning Outfit'), ENT_QUOTES); ?>"
                                data-preview-author="<?php echo htmlspecialchars((string) ($previousWeekWinner['entry']['username'] ?? 'Unknown'), ENT_QUOTES); ?>"
                                data-preview-author-id="<?php echo (int) ($previousWeekWinner['entry']['user_id'] ?? 0); ?>"
                                data-preview-author-avatar="<?php echo htmlspecialchars(buildFitspirationAvatarUrl($previousWeekWinner['entry']['user_img'] ?? '', (string) ($previousWeekWinner['entry']['username'] ?? 'Unknown'))); ?>"
                                data-preview-caption="<?php echo htmlspecialchars((string) ($previousWeekWinner['entry']['caption'] ?? ''), ENT_QUOTES); ?>"
                                data-preview-votes="<?php echo (int) ($previousWeekWinner['entry']['vote_count'] ?? 0); ?>"
                                    data-preview-likes="0"
                                    data-preview-outfit-id="<?php echo (int) ($previousWeekWinner['entry']['outfit_id'] ?? 0); ?>"
                                    data-preview-entry-id="0"
                                    data-preview-user-voted="0"
                                    data-preview-is-own-entry="0"
                                    data-preview-comments="[]"
                                    data-preview-interactive="0">
                                View Outfit
                            </button>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!empty($challengeNotice)): ?>
                <div class="challenge-notice <?php echo $challengeNoticeType === 'error' ? 'is-error' : 'is-ok'; ?>">
                    <?php echo htmlspecialchars($challengeNotice); ?>
                </div>
            <?php endif; ?>

            <section class="challenge-feed">
                <div class="challenge-feed-head">
                    <h2>Leaderboard</h2>
                    <p><span id="challengeShowingLabel">Showing:</span> <?php echo htmlspecialchars($challengeSortLabel); ?></p>
                    <div class="challenge-filter-bar" role="group" aria-label="Challenge filters" id="challengeFilterBar">
                        <a href="OutfitChallenge.php?sort=most_voted" class="challenge-filter-pill <?php echo $challengeSort === 'most_voted' ? 'active' : ''; ?>" id="challengeMostVotedLink">Most Voted</a>
                        <a href="OutfitChallenge.php?sort=newest" class="challenge-filter-pill <?php echo $challengeSort === 'newest' ? 'active' : ''; ?>">Newest</a>
                        <a href="OutfitChallenge.php?sort=followed" class="challenge-filter-pill <?php echo $challengeSort === 'followed' ? 'active' : ''; ?>">Followed</a>
                    </div>
                </div>

                <?php if (empty($challengeEntries)): ?>
                    <article class="challenge-empty">
                        <h3>No entries yet</h3>
                        <p>Be the first to submit an outfit for this week.</p>
                    </article>
                <?php else: ?>
                    <div class="challenge-grid">
                        <?php foreach ($challengeEntries as $index => $entry): ?>
                            <article class="challenge-entry-card">
                                <div class="entry-rank">#<?php echo $index + 1; ?></div>
                                <img src="<?php echo !empty($entry['outfit_img']) ? '../images/' . rawurlencode((string) $entry['outfit_img']) : '../images/no_image.jpg'; ?>" alt="Outfit entry">
                                <div class="entry-copy">
                                    <h3><?php echo htmlspecialchars((string) ($entry['outfit_name'] ?: ('Outfit #' . (int) $entry['outfit_id']))); ?></h3>
                                    <p class="entry-author no-translate" data-user-content="true">by <a class="entry-author-link" href="Profile.php?user_id=<?php echo (int) $entry['user_id']; ?>"><?php echo htmlspecialchars((string) $entry['username']); ?></a></p>
                                    <?php if (!empty($entry['caption'])): ?>
                                        <p class="entry-caption"><?php echo htmlspecialchars((string) $entry['caption']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="entry-stats">
                                    <span><i class="fa-solid fa-bolt"></i> <?php echo (int) $entry['vote_count']; ?> votes</span>
                                    <span><i class="fa-solid fa-heart"></i> <?php echo (int) $entry['like_count']; ?> likes</span>
                                    <span class="entry-deadline js-challenge-countdown" data-challenge-ends="<?php echo htmlspecialchars((string) ($activeChallenge['ends_at'] ?? ''), ENT_QUOTES); ?>"><i class="fa-solid fa-hourglass-half"></i> Ends soon</span>
                                </div>
                                <div class="entry-actions">
                                    <button
                                        type="button"
                                        class="entry-view-btn js-open-outfit-preview"
                                        data-preview-image="<?php echo !empty($entry['outfit_img']) ? '../images/' . rawurlencode((string) $entry['outfit_img']) : '../images/no_image.jpg'; ?>"
                                        data-preview-title="<?php echo htmlspecialchars((string) ($entry['outfit_name'] ?: ('Outfit #' . (int) $entry['outfit_id'])), ENT_QUOTES); ?>"
                                        data-preview-author="<?php echo htmlspecialchars((string) $entry['username'], ENT_QUOTES); ?>"
                                        data-preview-author-id="<?php echo (int) $entry['user_id']; ?>"
                                        data-preview-author-avatar="<?php echo htmlspecialchars(buildFitspirationAvatarUrl($entry['user_img'] ?? '', (string) ($entry['username'] ?? 'Unknown'))); ?>"
                                        data-preview-caption="<?php echo htmlspecialchars((string) ($entry['caption'] ?? ''), ENT_QUOTES); ?>"
                                        data-preview-votes="<?php echo (int) $entry['vote_count']; ?>"
                                        data-preview-likes="0"
                                        data-preview-outfit-id="<?php echo (int) $entry['outfit_id']; ?>"
                                        data-preview-entry-id="<?php echo (int) $entry['id']; ?>"
                                        data-preview-user-voted="<?php echo !empty($entry['user_voted']) ? '1' : '0'; ?>"
                                        data-preview-is-own-entry="<?php echo ((int) $entry['user_id'] === (int) ($_SESSION['user_id'] ?? 0)) ? '1' : '0'; ?>"
                                        data-preview-comments="<?php echo htmlspecialchars(json_encode($entryCommentsMap[(int) $entry['id']] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES); ?>"
                                        data-preview-interactive="1">
                                        View
                                    </button>
                                    <a href="OutfitBuilder.php?remix_outfit_id=<?php echo (int) $entry['outfit_id']; ?>" class="entry-remix-btn">Remix</a>
                                    <?php if ((int) $entry['user_id'] === (int) ($_SESSION['user_id'] ?? 0)): ?>
                                        <button type="button" class="entry-vote-btn disabled" disabled>Your entry</button>
                                    <?php else: ?>
                                        <form method="POST">
                                            <?php echo csrfInput(); ?>
                                            <input type="hidden" name="entry_id" value="<?php echo (int) $entry['id']; ?>">
                                            <button type="submit" name="toggle_challenge_vote" class="entry-vote-btn <?php echo !empty($entry['user_voted']) ? 'active' : ''; ?>">
                                                <?php echo !empty($entry['user_voted']) ? 'Voted' : 'Vote'; ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <div class="participate-modal" id="participateModal" aria-hidden="true">
        <div class="participate-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="participateModalTitle">
            <button type="button" class="participate-modal-close" id="closeParticipateModalBtn" aria-label="Close participate modal">&times;</button>
            <h2 id="participateModalTitle">Submit Your Outfit</h2>
            <p class="participate-modal-subtitle">One submission per week. You can update it any time before this challenge ends.</p>

            <form method="POST" class="challenge-submit-form">
                <?php echo csrfInput(); ?>
                <div class="field-row">
                    <label for="participate_outfit_id">Choose an outfit</label>
                    <select id="participate_outfit_id" name="outfit_id" required>
                        <option value="">Select outfit...</option>
                        <?php foreach ($myOutfits as $outfit): ?>
                            <option value="<?php echo (int) $outfit['id']; ?>" <?php echo $myEntry && (int) $myEntry['outfit_id'] === (int) $outfit['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) ($outfit['name'] ?: ('Outfit #' . (int) $outfit['id']))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-row">
                    <label for="participate_entry_caption">Caption (optional)</label>
                    <input type="text" id="participate_entry_caption" name="entry_caption" maxlength="255" placeholder="Describe your styling concept..." value="<?php echo htmlspecialchars((string) ($myEntry['caption'] ?? '')); ?>">
                </div>
                <button type="submit" name="submit_outfit_to_challenge" class="challenge-submit-btn">
                    <?php echo $myEntry ? 'Update Submission' : 'Submit Outfit'; ?>
                </button>
            </form>

            <?php if (!empty($myEntry)): ?>
                <form method="POST" class="delete-participation-form" onsubmit="return confirm('Delete your participation for this week?');">
                    <?php echo csrfInput(); ?>
                    <button type="submit" name="delete_challenge_participation" class="challenge-delete-btn">Delete Participation</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="outfit-preview-modal" id="outfitPreviewModal" aria-hidden="true">
        <div class="outfit-preview-dialog" role="dialog" aria-modal="true" aria-labelledby="outfitPreviewTitle">
            <button type="button" class="outfit-preview-close" id="closeOutfitPreviewModalBtn" aria-label="Close outfit preview">&times;</button>
            <div class="outfit-preview-layout">
                <div class="outfit-preview-image-wrap">
                    <img id="outfitPreviewImage" src="../images/no_image.jpg" alt="Outfit preview image">
                </div>
                <div class="outfit-preview-copy">
                    <h3 id="outfitPreviewTitle">Outfit</h3>
                    <div class="outfit-preview-author-row">
                        <img id="outfitPreviewAuthorAvatar" src="../images/default_avatar.svg" alt="Creator avatar" class="outfit-preview-author-avatar">
                        <p class="outfit-preview-author">by <a id="outfitPreviewAuthorLink" href="#">Creator</a></p>
                    </div>
                    <p id="outfitPreviewCaption" class="outfit-preview-caption"></p>
                    <div class="outfit-preview-stats">
                        <span><i class="fa-solid fa-bolt"></i> <span id="outfitPreviewVotes">0</span> votes</span>
                        <span id="outfitPreviewDeadline" class="outfit-preview-deadline js-challenge-countdown" data-challenge-ends="<?php echo htmlspecialchars((string) ($activeChallenge['ends_at'] ?? ''), ENT_QUOTES); ?>"><i class="fa-solid fa-hourglass-half"></i> Ends soon</span>
                    </div>
                    <div class="outfit-preview-actions">
                        <a id="outfitPreviewRemixBtn" href="#" class="entry-remix-btn">Remix Outfit</a>
                        <button type="button" id="outfitPreviewCommentsToggleBtn" class="entry-remix-btn outfit-preview-comments-toggle" aria-expanded="false">Comments</button>
                        <form method="POST" id="outfitPreviewVoteForm">
                            <?php echo csrfInput(); ?>
                            <input type="hidden" name="entry_id" id="outfitPreviewVoteEntryId" value="0">
                            <input type="hidden" name="return_modal" value="1">
                            <button type="submit" name="toggle_challenge_vote" id="outfitPreviewVoteBtn" class="entry-vote-btn">Vote</button>
                        </form>
                    </div>
                    <div class="outfit-preview-comments is-collapsed" id="outfitPreviewCommentsSection">
                        <div class="outfit-preview-comments-head">
                            <h4>Comments</h4>
                            <button type="button" id="outfitPreviewCommentsHideBtn" class="outfit-preview-comments-hide-btn">Hide comments</button>
                        </div>
                        <ul id="outfitPreviewCommentsList" class="outfit-preview-comments-list">
                            <li class="empty">No comments yet.</li>
                        </ul>
                        <form method="POST" id="outfitPreviewCommentForm" class="outfit-preview-comment-form">
                            <?php echo csrfInput(); ?>
                            <input type="hidden" name="entry_id" id="outfitPreviewCommentEntryId" value="0">
                            <input type="text" name="challenge_comment" id="outfitPreviewCommentInput" maxlength="1000" placeholder="Write a comment..." required>
                            <button type="submit" name="add_challenge_comment" class="challenge-submit-btn">Post</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <special-footer></special-footer>
    <script>
        (function () {
            function t(str) {
                return (window.translator && typeof window.translator.t === 'function')
                    ? window.translator.t(str)
                    : str;
            }

            var autoOpenPreviewEntryId = <?php echo (int) ($autoOpenPreviewEntryId ?? 0); ?>;
            var modal = document.getElementById('participateModal');
            var openBtn = document.getElementById('openParticipateModalBtn');
            var closeBtn = document.getElementById('closeParticipateModalBtn');
            var outfitModal = document.getElementById('outfitPreviewModal');
            var closeOutfitBtn = document.getElementById('closeOutfitPreviewModalBtn');

            var previewImage = document.getElementById('outfitPreviewImage');
            var previewTitle = document.getElementById('outfitPreviewTitle');
            var previewAuthorLink = document.getElementById('outfitPreviewAuthorLink');
            var previewAuthorAvatar = document.getElementById('outfitPreviewAuthorAvatar');
            var previewCaption = document.getElementById('outfitPreviewCaption');
            var previewVotes = document.getElementById('outfitPreviewVotes');
            var previewRemixBtn = document.getElementById('outfitPreviewRemixBtn');
            var previewVoteForm = document.getElementById('outfitPreviewVoteForm');
            var previewVoteBtn = document.getElementById('outfitPreviewVoteBtn');
            var previewVoteEntryId = document.getElementById('outfitPreviewVoteEntryId');
            var previewCommentForm = document.getElementById('outfitPreviewCommentForm');
            var previewCommentEntryId = document.getElementById('outfitPreviewCommentEntryId');
            var previewCommentsList = document.getElementById('outfitPreviewCommentsList');
            var previewCommentInput = document.getElementById('outfitPreviewCommentInput');
            var previewCopyPanel = document.querySelector('.outfit-preview-copy');
            var previewCommentsSection = document.getElementById('outfitPreviewCommentsSection');
            var previewCommentsToggleBtn = document.getElementById('outfitPreviewCommentsToggleBtn');
            var previewCommentsHideBtn = document.getElementById('outfitPreviewCommentsHideBtn');

            function applyOutfitChallengeUiTranslations() {
                var participateLabel = document.getElementById('challengeParticipateBtnLabel');
                var badgesTitle = document.getElementById('challengeBadgesTitle');
                var badgesSubtitle = document.getElementById('challengeBadgesSubtitle');
                var showingLabel = document.getElementById('challengeShowingLabel');
                var filterBar = document.getElementById('challengeFilterBar');
                var mostVotedLink = document.getElementById('challengeMostVotedLink');

                if (participateLabel) {
                    participateLabel.textContent = t(participateLabel.getAttribute('data-label-key') || 'Participate');
                }

                if (badgesTitle) {
                    badgesTitle.textContent = t('Your Challenge Badges');
                }

                if (badgesSubtitle) {
                    badgesSubtitle.textContent = t('Progress updates automatically from your challenge activity.');
                }

                if (showingLabel) {
                    showingLabel.textContent = t('Showing:');
                }

                if (filterBar) {
                    filterBar.setAttribute('aria-label', t('Challenge filters'));
                }

                if (mostVotedLink) {
                    mostVotedLink.textContent = t('Most Voted');
                }
            }

            function setCommentsSectionCollapsed(collapsed) {
                if (!previewCommentsSection || !previewCommentsToggleBtn) {
                    return;
                }

                previewCommentsSection.classList.toggle('is-collapsed', !!collapsed);
                if (outfitModal) {
                    outfitModal.classList.toggle('comments-focus', !collapsed);
                }
                previewCommentsToggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                previewCommentsToggleBtn.textContent = collapsed ? 'Comments' : 'Back to outfit';

                if (!collapsed && previewCommentInput && previewCommentForm && previewCommentForm.style.display !== 'none') {
                    window.setTimeout(function () {
                        previewCommentInput.focus();
                    }, 60);
                }
            }

            function resolveChallengeDeadline(endsAtText) {
                if (!endsAtText) {
                    return null;
                }

                var normalized = String(endsAtText).trim();
                if (!normalized) {
                    return null;
                }

                var withTime = normalized.length <= 10 ? normalized + 'T23:59:59' : normalized;
                var deadline = new Date(withTime);

                if (Number.isNaN(deadline.getTime())) {
                    return null;
                }

                return deadline;
            }

            function formatChallengeCountdown(deadlineDate) {
                if (!deadlineDate) {
                    return 'Deadline unavailable';
                }

                var now = new Date();
                var diffMs = deadlineDate.getTime() - now.getTime();
                if (diffMs <= 0) {
                    return 'Challenge ended';
                }

                var totalSeconds = Math.floor(diffMs / 1000);
                var days = Math.floor(totalSeconds / 86400);
                var hours = Math.floor((totalSeconds % 86400) / 3600);
                var minutes = Math.floor((totalSeconds % 3600) / 60);
                var seconds = totalSeconds % 60;

                if (days > 0) {
                    return 'Ends in ' + days + 'd ' + hours + 'h ' + minutes + 'm ' + seconds + 's';
                }

                if (hours > 0) {
                    return 'Ends in ' + hours + 'h ' + minutes + 'm ' + seconds + 's';
                }

                return 'Ends in ' + minutes + 'm ' + seconds + 's';
            }

            function updateChallengeCountdownLabels() {
                var countdownNodes = document.querySelectorAll('.js-challenge-countdown');
                countdownNodes.forEach(function (node) {
                    var deadlineDate = resolveChallengeDeadline(node.getAttribute('data-challenge-ends'));
                    var iconMarkup = '<i class="fa-solid fa-hourglass-half"></i> ';
                    node.innerHTML = iconMarkup + formatChallengeCountdown(deadlineDate);
                });
            }

            function resetPreviewPanelSizing() {
                if (!previewCopyPanel) {
                    return;
                }
                previewCopyPanel.style.height = '';
                previewCopyPanel.style.maxHeight = '';
            }

            function syncPreviewPanelSizingToImage() {
                if (!outfitModal || !outfitModal.classList.contains('is-open') || !previewCopyPanel || !previewImage) {
                    return;
                }

                if (window.matchMedia('(max-width: 980px)').matches) {
                    resetPreviewPanelSizing();
                    return;
                }

                var imageHeight = Math.floor(previewImage.getBoundingClientRect().height);
                if (imageHeight > 0) {
                    previewCopyPanel.style.height = imageHeight + 'px';
                    previewCopyPanel.style.maxHeight = imageHeight + 'px';
                }
            }

            function parseComments(rawValue) {
                if (!rawValue) {
                    return [];
                }
                try {
                    var parsed = JSON.parse(rawValue);
                    return Array.isArray(parsed) ? parsed : [];
                } catch (error) {
                    return [];
                }
            }

            function getEntryTriggers(entryId) {
                return Array.prototype.slice.call(
                    document.querySelectorAll('.js-open-outfit-preview[data-preview-entry-id="' + String(entryId) + '"]')
                );
            }

            function syncEntryCommentsData(entryId, commentRow) {
                getEntryTriggers(entryId).forEach(function (trigger) {
                    var current = parseComments(trigger.getAttribute('data-preview-comments'));
                    current.unshift(commentRow);
                    trigger.setAttribute('data-preview-comments', JSON.stringify(current));
                });
            }

            function removeCommentFromEntryData(entryId, commentId, source) {
                getEntryTriggers(entryId).forEach(function (trigger) {
                    var current = parseComments(trigger.getAttribute('data-preview-comments'));
                    var filtered = current.filter(function (row) {
                        return !(Number(row.id) === Number(commentId) && String(row.source || 'challenge') === String(source || 'challenge'));
                    });
                    trigger.setAttribute('data-preview-comments', JSON.stringify(filtered));
                });
            }

            function createCommentListItem(commentRow, entryId, interactive) {
                var item = document.createElement('li');
                item.className = 'outfit-preview-comment-item';
                item.setAttribute('data-comment-id', String(commentRow.id || 0));
                item.setAttribute('data-comment-source', String(commentRow.source || 'challenge'));

                var avatar = document.createElement('img');
                avatar.className = 'outfit-preview-comment-avatar';
                avatar.src = commentRow.user_img || '../images/default_avatar.svg';
                avatar.alt = (commentRow.username || 'User') + ' avatar';

                var body = document.createElement('div');
                body.className = 'outfit-preview-comment-body';

                var text = document.createElement('p');
                text.className = 'outfit-preview-comment-text';
                var authorStrong = document.createElement('strong');
                authorStrong.textContent = (commentRow.username || 'User') + ':';
                var commentTextNode = document.createTextNode(' ' + (commentRow.comment || ''));
                text.appendChild(authorStrong);
                text.appendChild(commentTextNode);

                body.appendChild(text);
                item.appendChild(avatar);
                item.appendChild(body);

                if (interactive && commentRow.can_delete === true) {
                    var deleteBtn = document.createElement('button');
                    deleteBtn.type = 'button';
                    deleteBtn.className = 'outfit-preview-comment-delete';
                    deleteBtn.textContent = 'Delete';
                    deleteBtn.setAttribute('data-delete-comment-id', String(commentRow.id || 0));
                    deleteBtn.setAttribute('data-delete-comment-source', String(commentRow.source || 'challenge'));
                    deleteBtn.setAttribute('data-delete-entry-id', String(entryId || 0));
                    item.appendChild(deleteBtn);
                }

                return item;
            }

            function syncEntryVoteData(entryId, voteCount, userVoted) {
                getEntryTriggers(entryId).forEach(function (trigger) {
                    trigger.setAttribute('data-preview-votes', String(voteCount));
                    trigger.setAttribute('data-preview-user-voted', userVoted ? '1' : '0');
                });

                var cardForms = Array.prototype.slice.call(document.querySelectorAll('.challenge-entry-card form'));
                cardForms.forEach(function (form) {
                    var entryField = form.querySelector('input[name="entry_id"]');
                    var voteButton = form.querySelector('button[name="toggle_challenge_vote"]');
                    if (!entryField || !voteButton || Number(entryField.value) !== Number(entryId)) {
                        return;
                    }

                    voteButton.classList.toggle('active', !!userVoted);
                    voteButton.textContent = userVoted ? 'Voted' : 'Vote';

                    var card = form.closest('.challenge-entry-card');
                    if (!card) {
                        return;
                    }

                    var voteStat = card.querySelector('.entry-stats span');
                    if (voteStat) {
                        voteStat.innerHTML = '<i class="fa-solid fa-bolt"></i> ' + String(voteCount) + ' votes';
                    }
                });
            }

            function ajaxSubmitForm(form, onSuccess, actionFieldName) {
                var formData = new FormData(form);

                if (actionFieldName && !formData.has(actionFieldName)) {
                    formData.append(actionFieldName, '1');
                }

                return fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData,
                    credentials: 'same-origin'
                })
                    .then(function (response) {
                        return response.json().catch(function () {
                            return { ok: false, message: 'Invalid server response.' };
                        });
                    })
                    .then(function (payload) {
                        if (!payload || payload.ok !== true) {
                            if (payload && payload.message) {
                                alert(payload.message);
                            }
                            return;
                        }
                        if (typeof onSuccess === 'function') {
                            onSuccess(payload);
                        }
                    })
                    .catch(function () {
                        alert('Request failed. Please try again.');
                    });
            }

            if (!outfitModal) {
                return;
            }

            function refreshBodyLock() {
                var hasOpenModal = modal.classList.contains('is-open') || (outfitModal && outfitModal.classList.contains('is-open'));
                document.body.classList.toggle('modal-open', hasOpenModal);
            }

            function openModal() {
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                refreshBodyLock();
            }

            function closeModal() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                refreshBodyLock();
            }

            function openOutfitModalFromTrigger(trigger) {
                if (!outfitModal || !trigger) {
                    return;
                }

                var image = trigger.getAttribute('data-preview-image') || '../images/no_image.jpg';
                var title = trigger.getAttribute('data-preview-title') || 'Outfit';
                var author = trigger.getAttribute('data-preview-author') || 'Creator';
                var authorId = trigger.getAttribute('data-preview-author-id') || '0';
                var authorAvatar = trigger.getAttribute('data-preview-author-avatar') || '../images/default_avatar.svg';
                var caption = trigger.getAttribute('data-preview-caption') || '';
                var votes = trigger.getAttribute('data-preview-votes') || '0';
                var outfitId = trigger.getAttribute('data-preview-outfit-id') || '0';
                var entryId = trigger.getAttribute('data-preview-entry-id') || '0';
                var userVoted = trigger.getAttribute('data-preview-user-voted') === '1';
                var isOwnEntry = trigger.getAttribute('data-preview-is-own-entry') === '1';
                var interactive = trigger.getAttribute('data-preview-interactive') === '1';
                var commentsRaw = trigger.getAttribute('data-preview-comments') || '[]';
                var comments = [];

                try {
                    comments = JSON.parse(commentsRaw);
                } catch (parseError) {
                    comments = [];
                }

                previewImage.src = image;
                previewTitle.textContent = title;
                previewAuthorLink.textContent = author;
                previewAuthorLink.href = 'Profile.php?user_id=' + encodeURIComponent(authorId);
                if (previewAuthorAvatar) {
                    previewAuthorAvatar.src = authorAvatar;
                }
                previewCaption.textContent = caption || 'No caption provided.';
                previewVotes.textContent = votes;
                previewRemixBtn.href = 'OutfitBuilder.php?remix_outfit_id=' + encodeURIComponent(outfitId);

                if (previewVoteEntryId) {
                    previewVoteEntryId.value = entryId;
                }

                if (previewCommentEntryId) {
                    previewCommentEntryId.value = entryId;
                }

                if (previewVoteBtn) {
                    if (!interactive || entryId === '0') {
                        previewVoteBtn.disabled = true;
                        previewVoteBtn.classList.add('disabled');
                        previewVoteBtn.classList.remove('active');
                        previewVoteBtn.textContent = 'Archived';
                    } else if (isOwnEntry) {
                        previewVoteBtn.disabled = true;
                        previewVoteBtn.classList.add('disabled');
                        previewVoteBtn.classList.remove('active');
                        previewVoteBtn.textContent = 'Your entry';
                    } else if (userVoted) {
                        previewVoteBtn.disabled = false;
                        previewVoteBtn.classList.remove('disabled');
                        previewVoteBtn.classList.add('active');
                        previewVoteBtn.textContent = 'Voted';
                    } else {
                        previewVoteBtn.disabled = false;
                        previewVoteBtn.classList.remove('disabled', 'active');
                        previewVoteBtn.textContent = 'Vote';
                    }
                }

                if (previewVoteForm) {
                    previewVoteForm.style.display = (!interactive || entryId === '0') ? 'none' : 'inline-flex';
                }

                if (previewCommentForm) {
                    previewCommentForm.style.display = (!interactive || entryId === '0') ? 'none' : 'grid';
                }

                if (previewCommentsList) {
                    previewCommentsList.innerHTML = '';
                    if (!comments.length) {
                        var emptyItem = document.createElement('li');
                        emptyItem.className = 'empty';
                        emptyItem.textContent = interactive ? 'No comments yet.' : 'Comments are unavailable for archived winner view.';
                        previewCommentsList.appendChild(emptyItem);
                    } else {
                        comments.forEach(function (commentRow) {
                            previewCommentsList.appendChild(createCommentListItem(commentRow, entryId, interactive));
                        });
                    }
                }

                setCommentsSectionCollapsed(true);

                outfitModal.classList.add('is-open');
                outfitModal.setAttribute('aria-hidden', 'false');
                refreshBodyLock();
                requestAnimationFrame(syncPreviewPanelSizingToImage);

                if (previewImage && previewImage.complete) {
                    requestAnimationFrame(syncPreviewPanelSizingToImage);
                }
            }

            function closeOutfitModal() {
                if (!outfitModal) {
                    return;
                }
                outfitModal.classList.remove('is-open');
                outfitModal.classList.remove('comments-focus');
                outfitModal.setAttribute('aria-hidden', 'true');
                resetPreviewPanelSizing();
                refreshBodyLock();
            }

            if (previewImage) {
                previewImage.addEventListener('load', function () {
                    requestAnimationFrame(syncPreviewPanelSizingToImage);
                });
            }

            window.addEventListener('resize', function () {
                syncPreviewPanelSizingToImage();
            });

            applyOutfitChallengeUiTranslations();
            updateChallengeCountdownLabels();
            window.setInterval(updateChallengeCountdownLabels, 1000);

            window.addEventListener('fitspiration:language-changed', function () {
                applyOutfitChallengeUiTranslations();
                updateChallengeCountdownLabels();
            });

            if (openBtn) {
                openBtn.addEventListener('click', openModal);
            }

            if (closeBtn) {
                closeBtn.addEventListener('click', closeModal);
            }

            if (modal) {
                modal.addEventListener('click', function (event) {
                    if (event.target === modal) {
                        closeModal();
                    }
                });
            }

            if (closeOutfitBtn) {
                closeOutfitBtn.addEventListener('click', closeOutfitModal);
            }

            if (previewCommentsToggleBtn) {
                previewCommentsToggleBtn.addEventListener('click', function () {
                    var shouldCollapse = !previewCommentsSection || !previewCommentsSection.classList.contains('is-collapsed') ? true : false;
                    setCommentsSectionCollapsed(shouldCollapse);
                });
            }

            if (previewCommentsHideBtn) {
                previewCommentsHideBtn.addEventListener('click', function () {
                    setCommentsSectionCollapsed(true);
                });
            }

            if (outfitModal) {
                outfitModal.addEventListener('click', function (event) {
                    if (event.target === outfitModal) {
                        closeOutfitModal();
                    }
                });
            }

            document.addEventListener('click', function (event) {
                var trigger = event.target.closest('.js-open-outfit-preview');
                if (!trigger) {
                    return;
                }

                event.preventDefault();
                openOutfitModalFromTrigger(trigger);
            });

            var voteForms = Array.prototype.slice.call(document.querySelectorAll('form'));
            voteForms.forEach(function (form) {
                var voteButton = form.querySelector('button[name="toggle_challenge_vote"]');
                var entryField = form.querySelector('input[name="entry_id"]');
                if (!voteButton || !entryField) {
                    return;
                }

                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    ajaxSubmitForm(form, function (payload) {
                        var targetEntryId = Number(payload.entry_id || entryField.value || 0);
                        var voteCount = Number(payload.vote_count || 0);
                        var userVoted = payload.user_voted === true || payload.user_voted === 1;

                        syncEntryVoteData(targetEntryId, voteCount, userVoted);

                        if (previewVoteEntryId && Number(previewVoteEntryId.value) === targetEntryId) {
                            previewVotes.textContent = String(voteCount);
                            previewVoteBtn.classList.toggle('active', !!userVoted);
                            previewVoteBtn.classList.remove('disabled');
                            previewVoteBtn.disabled = false;
                            previewVoteBtn.textContent = userVoted ? 'Voted' : 'Vote';
                        }
                    }, 'toggle_challenge_vote');
                });
            });

            if (previewCommentForm) {
                previewCommentForm.addEventListener('submit', function (event) {
                    event.preventDefault();

                    ajaxSubmitForm(previewCommentForm, function (payload) {
                        var targetEntryId = Number(payload.entry_id || 0);
                        var commentRow = payload.comment || null;
                        if (!commentRow || !previewCommentsList) {
                            return;
                        }

                        var existingEmpty = previewCommentsList.querySelector('li.empty');
                        if (existingEmpty) {
                            existingEmpty.remove();
                        }

                        var item = createCommentListItem(commentRow, targetEntryId, true);
                        previewCommentsList.insertBefore(item, previewCommentsList.firstChild);

                        syncEntryCommentsData(targetEntryId, commentRow);

                        if (previewCommentInput) {
                            previewCommentInput.value = '';
                        }
                    }, 'add_challenge_comment');
                });
            }

            document.addEventListener('click', function (event) {
                var deleteBtn = event.target.closest('.outfit-preview-comment-delete');
                if (!deleteBtn) {
                    return;
                }

                if (!window.confirm('Delete this comment?')) {
                    return;
                }

                var fallbackEntryId = previewCommentEntryId ? previewCommentEntryId.value : '0';
                var entryId = Number(deleteBtn.getAttribute('data-delete-entry-id') || fallbackEntryId || 0);
                var commentId = Number(deleteBtn.getAttribute('data-delete-comment-id') || 0);
                var commentSource = deleteBtn.getAttribute('data-delete-comment-source') || 'challenge';

                if (entryId <= 0 || commentId <= 0) {
                    return;
                }

                var formData = new FormData();
                formData.append('entry_id', String(entryId));
                formData.append('comment_id', String(commentId));
                formData.append('comment_source', commentSource);
                formData.append('delete_challenge_comment', '1');

                var bodyToken = document.body ? document.body.getAttribute('data-csrf-token') : '';
                if (bodyToken) {
                    formData.append('csrf_token', bodyToken);
                }

                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData,
                    credentials: 'same-origin'
                })
                    .then(function (response) {
                        return response.json().catch(function () {
                            return { ok: false, message: 'Invalid server response.' };
                        });
                    })
                    .then(function (payload) {
                        if (!payload || payload.ok !== true) {
                            if (payload && payload.message) {
                                alert(payload.message);
                            }
                            return;
                        }

                        var listItem = deleteBtn.closest('.outfit-preview-comment-item');
                        if (listItem) {
                            listItem.remove();
                        }

                        removeCommentFromEntryData(entryId, commentId, commentSource);

                        if (previewCommentsList && !previewCommentsList.querySelector('.outfit-preview-comment-item')) {
                            var emptyItem = document.createElement('li');
                            emptyItem.className = 'empty';
                            emptyItem.textContent = 'No comments yet.';
                            previewCommentsList.appendChild(emptyItem);
                        }
                    })
                    .catch(function () {
                        alert('Request failed. Please try again.');
                    });
            });

            if (autoOpenPreviewEntryId > 0) {
                var autoOpenTrigger = document.querySelector('.js-open-outfit-preview[data-preview-entry-id="' + String(autoOpenPreviewEntryId) + '"]');
                if (autoOpenTrigger) {
                    openOutfitModalFromTrigger(autoOpenTrigger);
                }
            }

            document.addEventListener('keydown', function (event) {
                if (event.key !== 'Escape') {
                    return;
                }

                if (outfitModal && outfitModal.classList.contains('is-open')) {
                    closeOutfitModal();
                    return;
                }

                if (modal && modal.classList.contains('is-open')) {
                    closeModal();
                }
            });
        })();
    </script>
</body>
</html>
