<?php
include_once '../includes/dbh.inc.php';
include_once '../includes/notifications.inc.php';
include_once '../includes/reports.inc.php';
include_once '../includes/likes.inc.php';
include_once '../includes/csrf.inc.php';
include_once '../includes/discovery_filters.inc.php';
include_once '../includes/visual_similarity.inc.php';
include_once '../includes/image_storage.inc.php';

$is_ajax_request = (
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_POST['ajax']) && $_POST['ajax'] === '1')
    || (isset($_GET['ajax']) && $_GET['ajax'] === '1')
);

if ($is_ajax_request && ob_get_level() === 0) {
    ob_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken($is_ajax_request);
}

function sendAjaxJson(array $payload): void {
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit();
}

ensureDiscoveryFilterTables($pdo);
ensureVisualSimilarityTable($pdo);

$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchScope = isset($_GET['search_scope']) ? trim((string) $_GET['search_scope']) : 'all';
if (!in_array($searchScope, ['all', 'pins', 'outfits', 'people'], true)) {
    $searchScope = 'all';
}
$visualSourcePinId = isset($_GET['visual_pin_id']) ? (int) $_GET['visual_pin_id'] : 0;
$isVisualSimilarityMode = $visualSourcePinId > 0;
if ($isVisualSimilarityMode) {
    if (!in_array($searchScope, ['all', 'pins', 'outfits'], true)) {
        $searchScope = 'all';
    }
}
$feedType = isset($_GET['feed']) ? trim($_GET['feed']) : 'new';
if (!in_array($feedType, ['for_you', 'trending', 'following', 'new'], true)) {
    $feedType = 'new';
}
$contentType = isset($_GET['content']) ? trim((string) $_GET['content']) : 'all';
if (!in_array($contentType, ['all', 'pieces', 'outfits'], true)) {
    $contentType = 'all';
}
if ($isVisualSimilarityMode) {
    // Visual similarity should compare across both standalone pieces and full outfits.
    $contentType = 'all';
}
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'date_desc';
if (empty($sort) || !isset($_GET['sort'])) {
    switch ($feedType) {
        case 'trending':
            $sort = 'engagement_desc';
            break;
        case 'for_you':
            $sort = 'relevance_desc';
            break;
        default:
            $sort = 'date_desc';
    }
}
error_log('Received feed type: ' . $feedType . ', content type: ' . $contentType . ', search scope: ' . $searchScope . ', search term: ' . $searchTerm . ', sort: ' . $sort);

$user_id = $_SESSION['user_id'] ?? null;
error_log('Current user_id from session: ' . ($user_id ?? 'Not set'));

$discoveryOptionSets = getDiscoveryFilterOptionSets();
$requestDiscoveryFilters = normalizeDiscoveryFilters([
    'dominant_color' => (string) ($_GET['color'] ?? ''),
    'style_tag' => (string) ($_GET['style'] ?? ''),
    'season' => (string) ($_GET['season'] ?? ''),
    'category' => (string) ($_GET['category'] ?? ''),
]);

$selectedSmartFeed = null;
$selectedSmartFeedId = isset($_GET['smart_feed_id']) ? (int) $_GET['smart_feed_id'] : 0;
$activeDiscoveryFilters = $requestDiscoveryFilters;

if ($user_id && $selectedSmartFeedId > 0) {
    $selectedSmartFeed = getSmartFeedByIdForUser($pdo, (int) $user_id, $selectedSmartFeedId);
    if ($selectedSmartFeed) {
        $activeDiscoveryFilters = normalizeDiscoveryFilters([
            'dominant_color' => (string) ($selectedSmartFeed['dominant_color'] ?? ''),
            'style_tag' => (string) ($selectedSmartFeed['style_tag'] ?? ''),
            'season' => (string) ($selectedSmartFeed['season'] ?? ''),
            'category' => (string) ($selectedSmartFeed['category'] ?? ''),
        ]);

        foreach (['dominant_color', 'style_tag', 'season', 'category'] as $filterKey) {
            if (($requestDiscoveryFilters[$filterKey] ?? '') !== '') {
                $activeDiscoveryFilters[$filterKey] = $requestDiscoveryFilters[$filterKey];
            }
        }
    }
}

$savedSmartFeeds = $user_id ? getSmartFeedsForUser($pdo, (int) $user_id, 20) : [];
$hasActiveDiscoveryFilters = (
    ($activeDiscoveryFilters['dominant_color'] ?? '') !== ''
    || ($activeDiscoveryFilters['style_tag'] ?? '') !== ''
    || ($activeDiscoveryFilters['season'] ?? '') !== ''
    || ($activeDiscoveryFilters['category'] ?? '') !== ''
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_smart_feed'])) {
    $deleteFeedId = (int) ($_POST['delete_smart_feed_id'] ?? 0);
    $deleteOk = false;

    if ($user_id && $deleteFeedId > 0) {
        $deleteOk = deleteSmartFeedForUser($pdo, (int) $user_id, $deleteFeedId);
    }

    $redirectParams = [
        'feed' => $feedType,
        'content' => $contentType,
        'search_scope' => $searchScope,
        'sort' => $sort,
    ];
    if ($searchTerm !== '') {
        $redirectParams['search'] = $searchTerm;
    }
    if (($activeDiscoveryFilters['dominant_color'] ?? '') !== '') {
        $redirectParams['color'] = $activeDiscoveryFilters['dominant_color'];
    }
    if (($activeDiscoveryFilters['style_tag'] ?? '') !== '') {
        $redirectParams['style'] = $activeDiscoveryFilters['style_tag'];
    }
    if (($activeDiscoveryFilters['season'] ?? '') !== '') {
        $redirectParams['season'] = $activeDiscoveryFilters['season'];
    }
    if (($activeDiscoveryFilters['category'] ?? '') !== '') {
        $redirectParams['category'] = $activeDiscoveryFilters['category'];
    }
    if ($selectedSmartFeedId > 0 && $selectedSmartFeedId !== $deleteFeedId) {
        $redirectParams['smart_feed_id'] = $selectedSmartFeedId;
    }
    $redirectParams['smart_feed_status'] = $deleteOk ? 'deleted' : 'delete_error';

    header('Location: Home.php?' . http_build_query($redirectParams));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_smart_feed'])) {
    $saveFilters = normalizeDiscoveryFilters([
        'dominant_color' => (string) ($_POST['dominant_color'] ?? ''),
        'style_tag' => (string) ($_POST['style_tag'] ?? ''),
        'season' => (string) ($_POST['season'] ?? ''),
        'category' => (string) ($_POST['category'] ?? ''),
    ]);

    $feedNameInput = trim((string) ($_POST['feed_name'] ?? ''));
    $saveOk = false;
    if ($user_id) {
        $saveOk = createSmartFeed($pdo, (int) $user_id, $feedNameInput, $saveFilters);
    }

    $redirectParams = [
        'feed' => $feedType,
        'content' => $contentType,
        'search_scope' => $searchScope,
        'sort' => $sort,
    ];
    if ($searchTerm !== '') {
        $redirectParams['search'] = $searchTerm;
    }
    if ($saveFilters['dominant_color'] !== '') {
        $redirectParams['color'] = $saveFilters['dominant_color'];
    }
    if ($saveFilters['style_tag'] !== '') {
        $redirectParams['style'] = $saveFilters['style_tag'];
    }
    if ($saveFilters['season'] !== '') {
        $redirectParams['season'] = $saveFilters['season'];
    }
    if ($saveFilters['category'] !== '') {
        $redirectParams['category'] = $saveFilters['category'];
    }
    $redirectParams['smart_feed_status'] = $saveOk ? 'saved' : 'error';

    header('Location: Home.php?' . http_build_query($redirectParams));
    exit();
}

if ($is_ajax_request && $_SERVER['REQUEST_METHOD'] === 'GET' && (string) ($_GET['action'] ?? '') === 'search_suggestions') {
    $queryText = trim((string) ($_GET['q'] ?? ''));
    $suggestionScope = trim((string) ($_GET['search_scope'] ?? 'all'));
    $allowedSuggestionScopes = ['all', 'pins', 'outfits', 'people'];
    if (!in_array($suggestionScope, $allowedSuggestionScopes, true)) {
        $suggestionScope = 'all';
    }

    if (mb_strlen($queryText) < 1) {
        sendAjaxJson([
            'success' => true,
            'suggestions' => [
                'users' => [],
                'pins' => [],
                'outfits' => [],
                'smart_feeds' => [],
                'tags' => [],
            ],
        ]);
    }

    $queryLower = mb_strtolower($queryText);
    $prefixLike = $queryText . '%';
    $containsLike = '%' . $queryText . '%';

    $suggestions = [
        'users' => [],
        'pins' => [],
        'outfits' => [],
        'smart_feeds' => [],
        'tags' => [],
    ];

    if ($suggestionScope === 'all' || $suggestionScope === 'people') {
        try {
            $userStmt = $pdo->prepare(
                "SELECT id, username, img
                 FROM registration
                 WHERE banned = 0
                   AND id <> :user_id
                   AND username LIKE :contains_like
                 ORDER BY
                   CASE
                     WHEN username LIKE :prefix_like THEN 0
                     WHEN username LIKE :contains_like THEN 1
                     ELSE 2
                   END,
                   username ASC
                 LIMIT 6"
            );
            $userStmt->execute([
                'user_id' => (int) ($user_id ?? 0),
                'prefix_like' => $prefixLike,
                'contains_like' => $containsLike,
            ]);
            foreach ($userStmt->fetchAll(PDO::FETCH_ASSOC) as $userRow) {
                $suggestions['users'][] = [
                    'id' => (int) ($userRow['id'] ?? 0),
                    'username' => (string) ($userRow['username'] ?? 'User'),
                    'img' => !empty($userRow['img']) ? '../images/' . (string) $userRow['img'] : '../images/no_image.jpg',
                ];
            }
        } catch (PDOException $e) {
            error_log('Search suggestion users query failed: ' . $e->getMessage());
        }
    }

    if ($suggestionScope === 'all' || $suggestionScope === 'pins' || $suggestionScope === 'outfits') {
        try {
            $pinStmt = $pdo->prepare(
                "SELECT p.id, p.title,
                        CASE WHEN p.link LIKE 'outfit://%' THEN 1 ELSE 0 END AS is_outfit
                 FROM pins p
                 INNER JOIN collections c ON c.collection_id = p.collection_id
                 WHERE c.privacy = 'Public'
                   AND p.title LIKE :contains_like
                 ORDER BY
                   CASE
                     WHEN p.title LIKE :prefix_like THEN 0
                     WHEN p.title LIKE :contains_like THEN 1
                     ELSE 2
                   END,
                   p.id DESC
                 LIMIT 12"
            );
            $pinStmt->execute([
                'prefix_like' => $prefixLike,
                'contains_like' => $containsLike,
            ]);

            foreach ($pinStmt->fetchAll(PDO::FETCH_ASSOC) as $pinRow) {
                $isOutfit = (int) ($pinRow['is_outfit'] ?? 0) === 1;
                if ($isOutfit) {
                    if ($suggestionScope !== 'pins') {
                        $suggestions['outfits'][] = [
                            'id' => (int) ($pinRow['id'] ?? 0),
                            'title' => (string) ($pinRow['title'] ?? 'Outfit'),
                        ];
                    }
                    continue;
                }

                if ($suggestionScope !== 'outfits') {
                    $suggestions['pins'][] = [
                        'id' => (int) ($pinRow['id'] ?? 0),
                        'title' => (string) ($pinRow['title'] ?? 'Pin'),
                    ];
                }
            }

            $suggestions['pins'] = array_slice($suggestions['pins'], 0, 6);
            $suggestions['outfits'] = array_slice($suggestions['outfits'], 0, 6);
        } catch (PDOException $e) {
            error_log('Search suggestion pins query failed: ' . $e->getMessage());
        }
    }

    if ($user_id) {
        try {
            $smartFeedStmt = $pdo->prepare(
                "SELECT id, feed_name
                 FROM smart_feed_filters
                 WHERE user_id = :user_id
                   AND feed_name LIKE :contains_like
                 ORDER BY
                   CASE
                     WHEN feed_name LIKE :prefix_like THEN 0
                     WHEN feed_name LIKE :contains_like THEN 1
                     ELSE 2
                   END,
                   created_at DESC
                 LIMIT 6"
            );
            $smartFeedStmt->execute([
                'user_id' => (int) $user_id,
                'prefix_like' => $prefixLike,
                'contains_like' => $containsLike,
            ]);
            foreach ($smartFeedStmt->fetchAll(PDO::FETCH_ASSOC) as $feedRow) {
                $suggestions['smart_feeds'][] = [
                    'id' => (int) ($feedRow['id'] ?? 0),
                    'feed_name' => (string) ($feedRow['feed_name'] ?? 'Smart Feed'),
                ];
            }
        } catch (PDOException $e) {
            error_log('Search suggestion smart feed query failed: ' . $e->getMessage());
        }
    }

    $optionSets = getDiscoveryFilterOptionSets();
    $tagCandidates = [];
    $optionBuckets = [
        'color' => $optionSets['colors'] ?? [],
        'style' => $optionSets['styles'] ?? [],
        'season' => $optionSets['seasons'] ?? [],
        'category' => $optionSets['categories'] ?? [],
    ];
    foreach ($optionBuckets as $kind => $values) {
        foreach ($values as $optionValue) {
            $optionString = (string) $optionValue;
            $optionLower = mb_strtolower($optionString);
            $position = mb_strpos($optionLower, $queryLower);
            if ($position === false) {
                continue;
            }
            $score = ($position === 0) ? 0 : 1;
            $tagCandidates[] = [
                'kind' => $kind,
                'value' => $optionString,
                'score' => $score,
            ];
        }
    }

    usort($tagCandidates, static function (array $a, array $b): int {
        if ($a['score'] === $b['score']) {
            return strcmp((string) $a['value'], (string) $b['value']);
        }
        return ((int) $a['score'] < (int) $b['score']) ? -1 : 1;
    });
    $tagCandidates = array_slice($tagCandidates, 0, 8);
    foreach ($tagCandidates as $tagRow) {
        $suggestions['tags'][] = [
            'kind' => (string) ($tagRow['kind'] ?? 'style'),
            'value' => (string) ($tagRow['value'] ?? ''),
        ];
    }

    sendAjaxJson([
        'success' => true,
        'suggestions' => $suggestions,
    ]);
}

$current_user_name = 'You';
$current_user_image = '../images/no_image.jpg';
if ($user_id) {
    try {
        $currentUserStmt = $pdo->prepare('SELECT username, img FROM registration WHERE id = ? LIMIT 1');
        $currentUserStmt->execute([$user_id]);
        $currentUser = $currentUserStmt->fetch(PDO::FETCH_ASSOC);
        if ($currentUser) {
            if (!empty($currentUser['username'])) {
                $current_user_name = $currentUser['username'];
            }
            if (!empty($currentUser['img'])) {
                $current_user_image = '../images/' . htmlspecialchars($currentUser['img']);
            }
        }
    } catch (PDOException $e) {
        error_log('Error fetching current user profile: ' . $e->getMessage());
    }
}

try {
    // Prepare personalization helpers
    $userKeywords = [];
    $interactedCreatorIds = [];
    $followingCount = 0;

    if ($user_id) {
        $followingCountStmt = $pdo->prepare('SELECT COUNT(*) FROM follows WHERE follower_id = ?');
        $followingCountStmt->execute([(int) $user_id]);
        $followingCount = (int) $followingCountStmt->fetchColumn();

        $keywordStmt = $pdo->prepare(
            "SELECT p.title
             FROM pins p
             INNER JOIN likes l ON l.pin_id = p.id AND l.user_id = ?
             WHERE p.title IS NOT NULL AND p.title <> ''
             UNION ALL
             SELECT p2.title
             FROM comments c
             INNER JOIN pins p2 ON p2.id = c.pin_id
             WHERE c.user_id = ? AND p2.title IS NOT NULL AND p2.title <> ''"
        );
        $keywordStmt->execute([(int) $user_id, (int) $user_id]);
        $titlesForKeywords = $keywordStmt->fetchAll(PDO::FETCH_COLUMN);

        $stopWords = [
            'the', 'and', 'for', 'with', 'you', 'your', 'from', 'look', 'style', 'outfit', 'new', 'fit', 'this', 'that',
            'is', 'are', 'to', 'of', 'in', 'on', 'at', 'a', 'an', 'by', 'or', 'it', 'as', 'be', 'my', 'our', 'best', 'top',
            'street', 'wear', 'fashion'
        ];
        $tokenFrequency = [];
        foreach ($titlesForKeywords as $titleText) {
            $normalized = strtolower((string) $titleText);
            $tokens = preg_split('/[^a-z0-9]+/', $normalized);
            if (!$tokens) {
                continue;
            }
            foreach ($tokens as $token) {
                if (strlen($token) < 4 || in_array($token, $stopWords, true)) {
                    continue;
                }
                $tokenFrequency[$token] = ($tokenFrequency[$token] ?? 0) + 1;
            }
        }
        arsort($tokenFrequency);
        $userKeywords = array_slice(array_keys($tokenFrequency), 0, 5);

        $creatorInteractionStmt = $pdo->prepare(
            "SELECT DISTINCT COALESCE(p.user_id, c.user_id) AS creator_id
             FROM pins p
             INNER JOIN collections c ON c.collection_id = p.collection_id
             LEFT JOIN likes l ON l.pin_id = p.id AND l.user_id = ?
             LEFT JOIN comments cm ON cm.pin_id = p.id AND cm.user_id = ?
             WHERE (l.user_id IS NOT NULL OR cm.user_id IS NOT NULL)"
        );
        $creatorInteractionStmt->execute([(int) $user_id, (int) $user_id]);
        $interactedCreatorIds = array_map('intval', $creatorInteractionStmt->fetchAll(PDO::FETCH_COLUMN));
    }

    // Определяем условие сортировки
    $orderBy = 'p.id DESC'; // По умолчанию
    switch ($sort) {
        case 'likes_asc':
            $orderBy = '(SELECT COUNT(*) FROM likes l WHERE l.pin_id = p.id) ASC';
            break;
        case 'likes_desc':
            $orderBy = '(SELECT COUNT(*) FROM likes l WHERE l.pin_id = p.id) DESC';
            break;
        case 'date_asc':
            $orderBy = 'p.id ASC';
            break;
        case 'date_desc':
            $orderBy = 'p.id DESC';
            break;
        case 'engagement_desc':
            $orderBy = '((SELECT COUNT(*) FROM likes l WHERE l.pin_id = p.id) + (SELECT COUNT(*) FROM comments c2 WHERE c2.pin_id = p.id) * 2) DESC';
            break;
        case 'relevance_desc':
            $orderBy = 'p.id DESC';
            break;
    }

    $queryParams = [
        ':user_id' => (int) $user_id,
        ':search_like' => '%' . mb_strtolower($searchTerm) . '%',
        ':search_exact' => mb_strtolower($searchTerm),
        ':search_prefix' => mb_strtolower($searchTerm) . '%',
        ':filter_color_like' => $activeDiscoveryFilters['dominant_color'] !== '' ? $activeDiscoveryFilters['dominant_color'] : '%',
        ':filter_style_like' => $activeDiscoveryFilters['style_tag'] !== '' ? $activeDiscoveryFilters['style_tag'] : '%',
        ':filter_season_like' => $activeDiscoveryFilters['season'] !== '' ? $activeDiscoveryFilters['season'] : '%',
        ':filter_category_like' => $activeDiscoveryFilters['category'] !== '' ? $activeDiscoveryFilters['category'] : '%',
    ];

    $visualSourcePin = null;
    if ($isVisualSimilarityMode) {
        $visualSourceStmt = $pdo->prepare(
            "SELECT p.id, p.title,
                    COALESCE(pdm.dominant_color, '') AS dominant_color,
                    COALESCE(pdm.style_tag, '') AS style_tag,
                    COALESCE(pdm.season, '') AS season,
                    COALESCE(pdm.category, '') AS category
             FROM pins p
             LEFT JOIN pin_discovery_meta pdm ON pdm.pin_id = p.id
             WHERE p.id = ?
             LIMIT 1"
        );
        $visualSourceStmt->execute([$visualSourcePinId]);
        $visualSourcePin = $visualSourceStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$visualSourcePin) {
            $isVisualSimilarityMode = false;
        }
    }

    $searchRankSql = "
        (
            CASE WHEN LOWER(COALESCE(p.title, '')) = :search_exact THEN 300 ELSE 0 END
            + CASE WHEN LOWER(COALESCE(p.title, '')) LIKE :search_prefix THEN 180 ELSE 0 END
            + CASE WHEN LOWER(COALESCE(pr.username, cr.username, '')) = :search_exact THEN 160 ELSE 0 END
            + CASE WHEN LOWER(COALESCE(pr.username, cr.username, '')) LIKE :search_prefix THEN 120 ELSE 0 END
            + CASE WHEN LOWER(COALESCE(c.title, '')) = :search_exact THEN 140 ELSE 0 END
            + CASE WHEN LOWER(COALESCE(c.title, '')) LIKE :search_prefix THEN 100 ELSE 0 END
            + CASE WHEN LOWER(COALESCE(p.title, '')) LIKE :search_like THEN 40 ELSE 0 END
        )
    ";

    $searchScopeSql = '';
    switch ($searchScope) {
        case 'pins':
            $searchScopeSql = "AND LOWER(COALESCE(p.title, '')) LIKE :search_like";
            break;
        case 'outfits':
            $searchScopeSql = "AND LOWER(COALESCE(p.title, '')) LIKE :search_like";
            break;
        case 'people':
            $searchScopeSql = "AND LOWER(COALESCE(pr.username, cr.username, '')) LIKE :search_like";
            break;
        default:
            $searchScopeSql = "AND (
                LOWER(COALESCE(p.title, '')) LIKE :search_like
                OR LOWER(COALESCE(pr.username, cr.username, '')) LIKE :search_like
                OR LOWER(COALESCE(c.title, '')) LIKE :search_like
            )";
            break;
    }

    $effectiveContentType = $contentType;
    if ($searchScope === 'outfits') {
        $effectiveContentType = 'outfits';
    } elseif ($searchScope === 'pins') {
        $effectiveContentType = 'pieces';
    }

    $discoveryFilterSql = "
        AND COALESCE(pdm.dominant_color, '') LIKE :filter_color_like
        AND COALESCE(pdm.style_tag, '') LIKE :filter_style_like
        AND COALESCE(pdm.season, '') LIKE :filter_season_like
        AND COALESCE(pdm.category, '') LIKE :filter_category_like
    ";

    $contentTypeSql = '';
    if ($effectiveContentType === 'outfits') {
        $contentTypeSql = "AND p.link LIKE 'outfit://%'";
    } elseif ($effectiveContentType === 'pieces') {
        $contentTypeSql = "AND (p.link IS NULL OR p.link NOT LIKE 'outfit://%')";
    }

    $searchOrderBySql = $orderBy;
    if ($searchTerm !== '') {
        $searchOrderBySql = 'search_rank DESC, ' . $orderBy;
    }

    $query = "
        SELECT p.id, p.title, p.img,
               COALESCE(p.user_id, c.user_id) as creator_id,
               COALESCE(pr.username, cr.username, 'Unknown') as creator_name,
               COALESCE(pr.img, cr.img, '') as creator_img,
               (SELECT COUNT(*) FROM likes l WHERE l.pin_id = p.id) as like_count,
               (SELECT COUNT(*) FROM likes l WHERE l.user_id = :user_id AND l.pin_id = p.id) as user_liked,
               (SELECT COUNT(*) FROM comments c2 WHERE c2.pin_id = p.id) as comment_count,
               COALESCE(pdm.dominant_color, '') as dominant_color,
               COALESCE(pdm.style_tag, '') as style_tag,
               COALESCE(pdm.season, '') as season,
               COALESCE(pdm.category, '') as category,
               {$searchRankSql} as search_rank,
               p.link as pin_link,
               CASE WHEN p.link LIKE 'outfit://%' THEN CAST(SUBSTRING(p.link, 10) AS UNSIGNED) ELSE NULL END as outfit_post_id,
               'Fresh pick' as feed_badge
        FROM pins p
        INNER JOIN collections c ON p.collection_id = c.collection_id
        LEFT JOIN pin_discovery_meta pdm ON pdm.pin_id = p.id
        LEFT JOIN registration pr ON p.user_id = pr.id
        LEFT JOIN registration cr ON c.user_id = cr.id
        WHERE c.privacy = 'Public'
        {$searchScopeSql}
        {$discoveryFilterSql}
        {$contentTypeSql}
        ORDER BY {$searchOrderBySql}
    ";
    
    // Build query based on feed type
    if ($isVisualSimilarityMode && $visualSourcePin) {
        $query = "
            SELECT p.id, p.title, p.img,
                   COALESCE(p.user_id, c.user_id) as creator_id,
                   COALESCE(pr.username, cr.username, 'Unknown') as creator_name,
                   COALESCE(pr.img, cr.img, '') as creator_img,
                   (SELECT COUNT(*) FROM likes l WHERE l.pin_id = p.id) as like_count,
                   (SELECT COUNT(*) FROM likes l WHERE l.user_id = :user_id AND l.pin_id = p.id) as user_liked,
                   (SELECT COUNT(*) FROM comments c2 WHERE c2.pin_id = p.id) as comment_count,
                   COALESCE(pdm.dominant_color, '') as dominant_color,
                   COALESCE(pdm.style_tag, '') as style_tag,
                   COALESCE(pdm.season, '') as season,
                   COALESCE(pdm.category, '') as category,
                   {$searchRankSql} as search_rank,
                   p.link as pin_link,
                   CASE WHEN p.link LIKE 'outfit://%' THEN CAST(SUBSTRING(p.link, 10) AS UNSIGNED) ELSE NULL END as outfit_post_id,
                   'Looks like this' as feed_badge
            FROM pins p
            INNER JOIN collections c ON p.collection_id = c.collection_id
            LEFT JOIN pin_discovery_meta pdm ON pdm.pin_id = p.id
            LEFT JOIN registration pr ON p.user_id = pr.id
            LEFT JOIN registration cr ON c.user_id = cr.id
            WHERE c.privacy = 'Public'
                            AND p.id <> :visual_source_pin_id
              {$searchScopeSql}
              {$discoveryFilterSql}
              {$contentTypeSql}
                        ORDER BY p.id DESC
                        LIMIT 220
        ";
                $queryParams[':visual_source_pin_id'] = (int) $visualSourcePin['id'];
    } elseif ($feedType === 'for_you' && $user_id) {
        $keywordScoreParts = [];
        $keywordBadgeParts = [];
        foreach ($userKeywords as $index => $keyword) {
            $paramKey = ':kw' . $index;
            $queryParams[$paramKey] = '%' . $keyword . '%';
            $keywordScoreParts[] = "CASE WHEN LOWER(p.title) LIKE {$paramKey} THEN 22 ELSE 0 END";
            $keywordBadgeParts[] = "WHEN LOWER(p.title) LIKE {$paramKey} THEN CONCAT('Because you liked ', '{$keyword}')";
        }

        $creatorScorePart = '0';
        if (!empty($interactedCreatorIds)) {
            $creatorScorePart = 'CASE WHEN COALESCE(p.user_id, c.user_id) IN (' . implode(',', array_map('intval', $interactedCreatorIds)) . ') THEN 48 ELSE 0 END';
        }

        $keywordScoreSql = !empty($keywordScoreParts) ? '(' . implode(' + ', $keywordScoreParts) . ')' : '0';
        $keywordBadgeSql = !empty($keywordBadgeParts) ? implode(' ', $keywordBadgeParts) : '';

                $forYouOrderBy = ($searchTerm !== '') ? 'search_rank DESC, relevance_score DESC' : 'relevance_score DESC';

                $query = "
            SELECT DISTINCT p.id, p.title, p.img,
                   COALESCE(p.user_id, c.user_id) as creator_id,
                   COALESCE(pr.username, cr.username, 'Unknown') as creator_name,
                   COALESCE(pr.img, cr.img, '') as creator_img,
                   (SELECT COUNT(*) FROM likes l WHERE l.pin_id = p.id) as like_count,
                   (SELECT COUNT(*) FROM likes l WHERE l.user_id = :user_id AND l.pin_id = p.id) as user_liked,
                   (SELECT COUNT(*) FROM comments c2 WHERE c2.pin_id = p.id) as comment_count,
                                     COALESCE(pdm.dominant_color, '') as dominant_color,
                                     COALESCE(pdm.style_tag, '') as style_tag,
                                     COALESCE(pdm.season, '') as season,
                                     COALESCE(pdm.category, '') as category,
                                     {$searchRankSql} as search_rank,
                   p.link as pin_link,
                   CASE WHEN p.link LIKE 'outfit://%' THEN CAST(SUBSTRING(p.link, 10) AS UNSIGNED) ELSE NULL END as outfit_post_id,
                   (
                        CASE WHEN c.user_id IN (SELECT following_id FROM follows WHERE follower_id = :user_id) THEN 120 ELSE 0 END
                        + {$creatorScorePart}
                        + {$keywordScoreSql}
                        + ((SELECT COUNT(*) FROM likes l WHERE l.pin_id = p.id) * 1.4)
                        + ((SELECT COUNT(*) FROM comments c2 WHERE c2.pin_id = p.id) * 2.1)
                        + LEAST(35, p.id * 0.01)
                   ) AS relevance_score,
                   CASE
                        WHEN c.user_id IN (SELECT following_id FROM follows WHERE follower_id = :user_id) THEN 'From Following'
                        {$keywordBadgeSql}
                        ELSE 'For You Pick'
                   END AS feed_badge
            FROM pins p
            INNER JOIN collections c ON p.collection_id = c.collection_id
            LEFT JOIN pin_discovery_meta pdm ON pdm.pin_id = p.id
            LEFT JOIN registration pr ON p.user_id = pr.id
            LEFT JOIN registration cr ON c.user_id = cr.id
            WHERE c.privacy = 'Public'
            {$searchScopeSql}
            {$discoveryFilterSql}
            {$contentTypeSql}
            ORDER BY {$forYouOrderBy}
            LIMIT 100
        ";
    } elseif ($feedType === 'trending') {
        $trendingOrderBy = '((SELECT COUNT(*) FROM likes l WHERE l.pin_id = p.id) + (SELECT COUNT(*) FROM comments cc WHERE cc.pin_id = p.id) * 2) DESC';
        if ($searchTerm !== '') {
            $trendingOrderBy = 'search_rank DESC, ' . $trendingOrderBy;
        }

        $query = "
            SELECT p.id, p.title, p.img,
                   COALESCE(p.user_id, c.user_id) as creator_id,
                   COALESCE(pr.username, cr.username, 'Unknown') as creator_name,
                   COALESCE(pr.img, cr.img, '') as creator_img,
                   (SELECT COUNT(*) FROM likes l WHERE l.pin_id = p.id) as like_count,
                   (SELECT COUNT(*) FROM likes l WHERE l.user_id = :user_id AND l.pin_id = p.id) as user_liked,
                   (SELECT COUNT(*) FROM comments c2 WHERE c2.pin_id = p.id) as comment_count,
                   COALESCE(pdm.dominant_color, '') as dominant_color,
                   COALESCE(pdm.style_tag, '') as style_tag,
                   COALESCE(pdm.season, '') as season,
                   COALESCE(pdm.category, '') as category,
                   {$searchRankSql} as search_rank,
                   p.link as pin_link,
                   CASE WHEN p.link LIKE 'outfit://%' THEN CAST(SUBSTRING(p.link, 10) AS UNSIGNED) ELSE NULL END as outfit_post_id,
                   'Trending' as feed_badge
            FROM pins p
            INNER JOIN collections c ON p.collection_id = c.collection_id
            LEFT JOIN pin_discovery_meta pdm ON pdm.pin_id = p.id
            LEFT JOIN registration pr ON p.user_id = pr.id
            LEFT JOIN registration cr ON c.user_id = cr.id
            WHERE c.privacy = 'Public'
            {$searchScopeSql}
            {$discoveryFilterSql}
            {$contentTypeSql}
            ORDER BY {$trendingOrderBy}
            LIMIT 100
        ";
    } elseif ($feedType === 'following' && $user_id) {
        $query = "
            SELECT p.id, p.title, p.img,
                   COALESCE(p.user_id, c.user_id) as creator_id,
                   COALESCE(pr.username, cr.username, 'Unknown') as creator_name,
                   COALESCE(pr.img, cr.img, '') as creator_img,
                   (SELECT COUNT(*) FROM likes l WHERE l.pin_id = p.id) as like_count,
                   (SELECT COUNT(*) FROM likes l WHERE l.user_id = :user_id AND l.pin_id = p.id) as user_liked,
                   (SELECT COUNT(*) FROM comments c2 WHERE c2.pin_id = p.id) as comment_count,
                   COALESCE(pdm.dominant_color, '') as dominant_color,
                   COALESCE(pdm.style_tag, '') as style_tag,
                   COALESCE(pdm.season, '') as season,
                   COALESCE(pdm.category, '') as category,
                   {$searchRankSql} as search_rank,
                   p.link as pin_link,
                   CASE WHEN p.link LIKE 'outfit://%' THEN CAST(SUBSTRING(p.link, 10) AS UNSIGNED) ELSE NULL END as outfit_post_id,
                   'From Following' as feed_badge
            FROM pins p
            INNER JOIN collections c ON p.collection_id = c.collection_id
            LEFT JOIN pin_discovery_meta pdm ON pdm.pin_id = p.id
            LEFT JOIN registration pr ON p.user_id = pr.id
            LEFT JOIN registration cr ON c.user_id = cr.id
            WHERE c.privacy = 'Public'
            AND c.user_id IN (SELECT following_id FROM follows WHERE follower_id = :user_id)
            {$searchScopeSql}
            {$discoveryFilterSql}
            {$contentTypeSql}
            ORDER BY {$searchOrderBySql}
            LIMIT 100
        ";
    } else {
        // 'new' feed - all public pins
        $query = "
            SELECT p.id, p.title, p.img,
                   COALESCE(p.user_id, c.user_id) as creator_id,
                   COALESCE(pr.username, cr.username, 'Unknown') as creator_name,
                   COALESCE(pr.img, cr.img, '') as creator_img,
                   (SELECT COUNT(*) FROM likes l WHERE l.pin_id = p.id) as like_count,
                   (SELECT COUNT(*) FROM likes l WHERE l.user_id = :user_id AND l.pin_id = p.id) as user_liked,
                   (SELECT COUNT(*) FROM comments c2 WHERE c2.pin_id = p.id) as comment_count,
                   COALESCE(pdm.dominant_color, '') as dominant_color,
                   COALESCE(pdm.style_tag, '') as style_tag,
                   COALESCE(pdm.season, '') as season,
                   COALESCE(pdm.category, '') as category,
                   {$searchRankSql} as search_rank,
                   p.link as pin_link,
                   CASE WHEN p.link LIKE 'outfit://%' THEN CAST(SUBSTRING(p.link, 10) AS UNSIGNED) ELSE NULL END as outfit_post_id,
                   'Fresh pick' as feed_badge
            FROM pins p
            INNER JOIN collections c ON p.collection_id = c.collection_id
            LEFT JOIN pin_discovery_meta pdm ON pdm.pin_id = p.id
            LEFT JOIN registration pr ON p.user_id = pr.id
            LEFT JOIN registration cr ON c.user_id = cr.id
            WHERE c.privacy = 'Public'
            {$searchScopeSql}
            {$discoveryFilterSql}
            {$contentTypeSql}
            ORDER BY {$searchOrderBySql}
            LIMIT 100
        ";
    }
    $stmt = $pdo->prepare($query);
    $stmt->execute($queryParams);
    $pins1 = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($isVisualSimilarityMode && $visualSourcePin && !empty($pins1)) {
        $rankedVisualRows = rankPinsByVisualSimilarity($pdo, (int) $visualSourcePin['id'], $pins1);
        foreach ($rankedVisualRows as &$rankedVisualRow) {
            if (!isset($rankedVisualRow['feed_badge']) || $rankedVisualRow['feed_badge'] === '') {
                $rankedVisualRow['feed_badge'] = 'Looks like this';
            }
        }
        unset($rankedVisualRow);
        $pins1 = array_slice($rankedVisualRows, 0, 100);
    }

    error_log('Public pins fetched for search "' . $searchTerm . '", sort "' . $sort . '": ' . count($pins1));
} catch (PDOException $e) {
    error_log('Error fetching public pins: ' . $e->getMessage());
    $pins1 = [];
}

$suggestedUsers = [];
if ($feedType === 'following' && empty($pins1) && $user_id) {
    try {
        $suggestStmt = $pdo->prepare(
            "SELECT r.id, r.username, r.img,
                    (SELECT COUNT(*) FROM follows f WHERE f.following_id = r.id) AS followers_count,
                    (SELECT COUNT(*)
                     FROM pins p
                     INNER JOIN collections c ON c.collection_id = p.collection_id
                     WHERE COALESCE(p.user_id, c.user_id) = r.id AND c.privacy = 'Public') AS public_pins
             FROM registration r
             WHERE r.id <> :user_id
               AND r.id NOT IN (SELECT following_id FROM follows WHERE follower_id = :user_id)
               AND r.banned = 0
             ORDER BY followers_count DESC, public_pins DESC
             LIMIT 5"
        );
        $suggestStmt->execute([':user_id' => (int) $user_id]);
        $suggestedUsers = $suggestStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error loading suggested users for following feed: ' . $e->getMessage());
    }
}

if ($is_ajax_request && $_SERVER['REQUEST_METHOD'] === 'GET' && (string)($_GET['action'] ?? '') === 'get_pin_modal') {
    $pinId = filter_var($_GET['pin_id'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
    if ((int)$pinId <= 0) {
        sendAjaxJson(['success' => false, 'message' => 'Invalid pin id.']);
    }

    try {
        $pinQuery = "
            SELECT p.id, p.img, p.title,
                   COALESCE(p.user_id, c.user_id) as creator_id,
                   COALESCE(pr.username, cr.username, 'Unknown') as creator_name,
                   COALESCE(pr.img, cr.img, '') as creator_img,
                   (SELECT COUNT(*) FROM likes WHERE pin_id = p.id) as like_count,
                   EXISTS(SELECT 1 FROM likes WHERE pin_id = p.id AND user_id = ?) as user_liked,
                   p.link as pin_link,
                   CASE WHEN p.link LIKE 'outfit://%' THEN CAST(SUBSTRING(p.link, 10) AS UNSIGNED) ELSE NULL END as outfit_post_id
            FROM pins p
            LEFT JOIN collections c ON p.collection_id = c.collection_id
            LEFT JOIN registration pr ON p.user_id = pr.id
            LEFT JOIN registration cr ON c.user_id = cr.id
            WHERE p.id = ?
            LIMIT 1
        ";
        $pinStmt = $pdo->prepare($pinQuery);
        $pinStmt->execute([(int)$user_id, (int)$pinId]);
        $pinData = $pinStmt->fetch(PDO::FETCH_ASSOC);

        if (!$pinData) {
            sendAjaxJson(['success' => false, 'message' => 'Pin not found.']);
        }

        $commentQuery = "
            SELECT c.id, c.comment, c.created_at, c.user_id, r.username, r.img as user_img
            FROM comments c
            JOIN registration r ON c.user_id = r.id
            WHERE c.pin_id = ?
            ORDER BY c.created_at DESC
        ";
        $commentStmt = $pdo->prepare($commentQuery);
        $commentStmt->execute([(int)$pinId]);
        $commentsData = $commentStmt->fetchAll(PDO::FETCH_ASSOC);

        $creatorId = (int)($pinData['creator_id'] ?? 0);
        $sessionId = (int)($user_id ?? 0);
        $commentsPayload = [];
        foreach ($commentsData as $commentRow) {
            $commentAuthorId = (int)($commentRow['user_id'] ?? 0);
            $commentsPayload[] = [
                'id' => (int)($commentRow['id'] ?? 0),
                'comment' => (string)($commentRow['comment'] ?? ''),
                'user_id' => $commentAuthorId,
                'username' => (string)($commentRow['username'] ?? 'Unknown'),
                'user_img' => !empty($commentRow['user_img']) ? '../images/' . (string)$commentRow['user_img'] : '../images/no_image.jpg',
                'can_delete' => ($commentAuthorId === $sessionId) || ($creatorId === $sessionId),
            ];
        }

        sendAjaxJson([
            'success' => true,
            'pin' => [
                'id' => (int)$pinData['id'],
                'image' => !empty($pinData['img']) ? '../images/' . (string)$pinData['img'] : '../images/no_image.jpg',
                'title' => (string)($pinData['title'] ?? 'Pin'),
                'creator_id' => $creatorId,
                'creator_name' => (string)($pinData['creator_name'] ?? 'Unknown'),
                'creator_img' => !empty($pinData['creator_img']) ? '../images/' . (string)$pinData['creator_img'] : '../images/no_image.jpg',
                'like_count' => (int)($pinData['like_count'] ?? 0),
                'user_liked' => !empty($pinData['user_liked']),
                'outfit_post_id' => !empty($pinData['outfit_post_id']) ? (int)$pinData['outfit_post_id'] : null,
            ],
            'comments' => $commentsPayload,
        ]);
    } catch (PDOException $e) {
        error_log('Error loading pin modal data: ' . $e->getMessage());
        sendAjaxJson(['success' => false, 'message' => 'Database error while loading pin modal.']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_pin'])) {
    $pin_id = filter_var($_POST['pin_id'], FILTER_SANITIZE_NUMBER_INT);
    $reportReason = trim($_POST['report_reason'] ?? '');
    $reportCategory = trim($_POST['report_category'] ?? 'other');

    $result = createContentReport($pdo, (int)$user_id, 'pin', (int)$pin_id, $reportReason, $reportCategory);
    $status = $result['ok'] ? 'ok' : 'error';
    $redirect_url = "Home.php?pin_id=" . urlencode((string)$pin_id) . "&sort=" . urlencode($sort) . "&report_status=" . urlencode($status);
    if ($searchTerm) {
        $redirect_url .= "&search=" . urlencode($searchTerm);
    }
    $redirect_url .= "&report_msg=" . urlencode($result['message']);
    header("Location: $redirect_url#pinModal");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_comment'])) {
    $comment_id = filter_var($_POST['comment_id'], FILTER_SANITIZE_NUMBER_INT);
    $pin_id = filter_var($_POST['pin_id'], FILTER_SANITIZE_NUMBER_INT);
    $reportReason = trim($_POST['report_reason'] ?? '');
    $reportCategory = trim($_POST['report_category'] ?? 'other');

    $result = createContentReport($pdo, (int)$user_id, 'comment', (int)$comment_id, $reportReason, $reportCategory);
    $status = $result['ok'] ? 'ok' : 'error';
    $redirect_url = "Home.php?pin_id=" . urlencode((string)$pin_id) . "&sort=" . urlencode($sort) . "&report_status=" . urlencode($status);
    if ($searchTerm) {
        $redirect_url .= "&search=" . urlencode($searchTerm);
    }
    $redirect_url .= "&report_msg=" . urlencode($result['message']);
    header("Location: $redirect_url#pinModal");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    $reportTargetType = trim($_POST['report_target_type'] ?? '');
    $reportTargetId = filter_var($_POST['report_target_id'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
    $pin_id = filter_var($_POST['pin_id'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
    $reportReason = trim($_POST['report_reason'] ?? '');
    $reportCategory = trim($_POST['report_category'] ?? 'other');

    if (!in_array($reportTargetType, ['pin', 'comment'], true)) {
        $result = ['ok' => false, 'message' => 'Invalid report target.'];
    } else {
        $result = createContentReport($pdo, (int)$user_id, $reportTargetType, (int)$reportTargetId, $reportReason, $reportCategory);
    }

    $status = $result['ok'] ? 'ok' : 'error';
    $redirect_url = "Home.php?pin_id=" . urlencode((string)$pin_id) . "&sort=" . urlencode($sort) . "&report_status=" . urlencode($status);
    if ($searchTerm) {
        $redirect_url .= "&search=" . urlencode($searchTerm);
    }
    $redirect_url .= "&report_msg=" . urlencode($result['message']);
    header("Location: $redirect_url#pinModal");
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_like'])) {
    $pin_id = filter_var($_POST['pin_id'], FILTER_SANITIZE_NUMBER_INT);
    $user_id = $_SESSION['user_id'];
    $isAjax = (
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_POST['ajax']) && $_POST['ajax'] === '1')
        || (isset($_GET['ajax']) && $_GET['ajax'] === '1')
    );
    error_log("Attempting to toggle like: user_id={$user_id}, pin_id={$pin_id}");

    try {
        if (!pinExists($pdo, (int) $pin_id)) {
            error_log("Pin does not exist: pin_id={$pin_id}");
            if ($isAjax) {
                sendAjaxJson(['success' => false, 'message' => 'Pin not found.']);
            }
            header("Location: Home.php" . ($searchTerm ? "?search=" . urlencode($searchTerm) : "") . "&sort=" . urlencode($sort) . "?error=pinnotfound");
            exit();
        }

        $likeData = togglePinLike($pdo, (int) $user_id, (int) $pin_id);

        if ($isAjax) {
            sendAjaxJson([
                'success' => true,
                'like' => $likeData,
            ]);
        }

        $redirect_url = "Home.php?pin_id=" . urlencode($pin_id) . "&sort=" . urlencode($sort);
        if ($searchTerm) {
            $redirect_url .= "&search=" . urlencode($searchTerm);
        }
        header("Location: $redirect_url#pinModal");
        exit();
    } catch (PDOException $e) {
        error_log('Error toggling like: ' . $e->getMessage());
        if ($isAjax) {
            sendAjaxJson(['success' => false, 'message' => 'Database error.']);
        }
        $redirect_url = "Home.php?error=dberror&sort=" . urlencode($sort);
        if ($searchTerm) {
            $redirect_url .= "&search=" . urlencode($searchTerm);
        }
        header("Location: $redirect_url#pinModal");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $pin_id = filter_var($_POST['pin_id'], FILTER_SANITIZE_NUMBER_INT);
    $user_id = $_SESSION['user_id'];
    $comment = trim(strip_tags((string)($_POST['comment'] ?? '')));
    $isAjax = (
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_POST['ajax']) && $_POST['ajax'] === '1')
        || (isset($_GET['ajax']) && $_GET['ajax'] === '1')
    );

    if ($comment === '') {
        if ($isAjax) {
            sendAjaxJson(['success' => false, 'message' => 'Comment cannot be empty.']);
        }
        $redirect_url = "Home.php?pin_id=" . urlencode($pin_id) . "&error=emptycomment&sort=" . urlencode($sort);
        if ($searchTerm) {
            $redirect_url .= "&search=" . urlencode($searchTerm);
        }
        header("Location: $redirect_url#pinModal");
        exit();
    }
    
    try {
        $query = "SELECT id FROM pins WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$pin_id]);
        $pin_exists = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pin_exists) {
            error_log("Pin does not exist: pin_id={$pin_id}");
            if ($isAjax) {
                sendAjaxJson(['success' => false, 'message' => 'Pin not found.']);
            }
            $redirect_url = "Home.php?error=pinnotfound&sort=" . urlencode($sort);
            if ($searchTerm) {
                $redirect_url .= "&search=" . urlencode($searchTerm);
            }
            header("Location: $redirect_url#pinModal");
            exit();
        }

        $query = "INSERT INTO comments (pin_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$pin_id, $user_id, $comment]);
        $new_comment_id = (int)$pdo->lastInsertId();
        error_log("Comment added: user_id={$user_id}, pin_id={$pin_id}, comment={$comment}");

        $pin_owner_id = getPinOwnerId($pdo, $pin_id);
        if ($pin_owner_id) {
            addNotification($pdo, $pin_owner_id, $user_id, 'comment', $pin_id);
        }

        if ($isAjax) {
            sendAjaxJson([
                'success' => true,
                'comment' => [
                    'id' => $new_comment_id,
                    'username' => $current_user_name,
                    'user_img' => $current_user_image,
                    'comment' => $comment,
                    'pin_id' => (int)$pin_id,
                ],
            ]);
        }

        $redirect_url = "Home.php?pin_id=" . urlencode($pin_id) . "&sort=" . urlencode($sort);
        if ($searchTerm) {
            $redirect_url .= "&search=" . urlencode($searchTerm);
        }
        header("Location: $redirect_url#pinModal");
        exit();
    } catch (PDOException $e) {
        error_log('Error adding comment: ' . $e->getMessage());
        if ($isAjax) {
            sendAjaxJson(['success' => false, 'message' => 'Database error while adding comment.']);
        }
        $redirect_url = "Home.php?error=dberror&sort=" . urlencode($sort);
        if ($searchTerm) {
            $redirect_url .= "&search=" . urlencode($searchTerm);
        }
        header("Location: $redirect_url#pinModal");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment'])) {
    $comment_id = filter_var($_POST['comment_id'], FILTER_SANITIZE_NUMBER_INT);
    $pin_id = filter_var($_POST['pin_id'], FILTER_SANITIZE_NUMBER_INT);
    $user_id = $_SESSION['user_id'];
    $isAjax = (
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_POST['ajax']) && $_POST['ajax'] === '1')
        || (isset($_GET['ajax']) && $_GET['ajax'] === '1')
    );
    error_log("Attempting to delete comment: user_id={$user_id}, comment_id={$comment_id}, pin_id={$pin_id}");

    try {
        $query = "
            SELECT c.id, c.user_id, p.user_id as pin_owner_id
            FROM comments c
            JOIN pins p ON c.pin_id = p.id
            WHERE c.id = ? AND c.pin_id = ?
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$comment_id, $pin_id]);
        $comment_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$comment_data) {
            error_log("Comment or pin not found: comment_id={$comment_id}, pin_id={$pin_id}");
            if ($isAjax) {
                sendAjaxJson(['success' => false, 'message' => 'Comment not found.']);
            }
            $redirect_url = "Home.php?pin_id=" . urlencode($pin_id) . "&error=commentnotfound&sort=" . urlencode($sort);
            if ($searchTerm) {
                $redirect_url .= "&search=" . urlencode($searchTerm);
            }
            header("Location: $redirect_url#pinModal");
            exit();
        }

        if ($comment_data['user_id'] != $user_id && $comment_data['pin_owner_id'] != $user_id) {
            error_log("Unauthorized comment deletion attempt: user_id={$user_id}, comment_id={$comment_id}");
            if ($isAjax) {
                sendAjaxJson(['success' => false, 'message' => 'You are not allowed to delete this comment.']);
            }
            $redirect_url = "Home.php?pin_id=" . urlencode($pin_id) . "&error=unauthorized&sort=" . urlencode($sort);
            if ($searchTerm) {
                $redirect_url .= "&search=" . urlencode($searchTerm);
            }
            header("Location: $redirect_url#pinModal");
            exit();
        }

        $query = "DELETE FROM comments WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$comment_id]);
        error_log("Comment deleted: comment_id={$comment_id}, user_id={$user_id}");

        if ($isAjax) {
            sendAjaxJson([
                'success' => true,
                'comment_id' => (int) $comment_id,
                'pin_id' => (int) $pin_id,
            ]);
        }

        $redirect_url = "Home.php?pin_id=" . urlencode($pin_id) . "&sort=" . urlencode($sort);
        if ($searchTerm) {
            $redirect_url .= "&search=" . urlencode($searchTerm);
        }
        header("Location: $redirect_url#pinModal");
        exit();
    } catch (PDOException $e) {
        error_log('Error deleting comment: ' . $e->getMessage());
        if ($isAjax) {
            sendAjaxJson(['success' => false, 'message' => 'Database error while deleting comment.']);
        }
        $redirect_url = "Home.php?pin_id=" . urlencode($pin_id) . "&error=dberror&sort=" . urlencode($sort);
        if ($searchTerm) {
            $redirect_url .= "&search=" . urlencode($searchTerm);
        }
        header("Location: $redirect_url#pinModal");
        exit();
    }
}

$modal_pin_data = ['image' => '', 'title' => '', 'like_count' => 0, 'user_liked' => false, 'creator_name' => '', 'creator_id' => '', 'outfit_post_id' => null];
if (isset($_GET['pin_id'])) {
    $pin_id = filter_var($_GET['pin_id'], FILTER_SANITIZE_NUMBER_INT);
    $user_id = $_SESSION['user_id'];

    $query = "
        SELECT p.id, p.img, p.title,
               COALESCE(p.user_id, c.user_id) as creator_id,
               COALESCE(pr.username, cr.username, 'Unknown') as creator_name,
               COALESCE(pr.img, cr.img, '') as creator_img,
               (SELECT COUNT(*) FROM likes WHERE pin_id = p.id) as like_count,
             EXISTS(SELECT 1 FROM likes WHERE pin_id = p.id AND user_id = ?) as user_liked,
             p.link as pin_link,
             CASE WHEN p.link LIKE 'outfit://%' THEN CAST(SUBSTRING(p.link, 10) AS UNSIGNED) ELSE NULL END as outfit_post_id
        FROM pins p
        LEFT JOIN collections c ON p.collection_id = c.collection_id
        LEFT JOIN registration pr ON p.user_id = pr.id
        LEFT JOIN registration cr ON c.user_id = cr.id
        WHERE p.id = ?
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, $pin_id]);
    $pin_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pin_data) {
        $modal_pin_data = [
            'image' => $pin_data['img'] ? '../images/' . htmlspecialchars($pin_data['img']) : '../images/no_image.jpg',
            'title' => htmlspecialchars($pin_data['title'] ?? 'Pin'),
            'like_count' => $pin_data['like_count'],
            'user_liked' => $pin_data['user_liked'],
            'creator_name' => htmlspecialchars($pin_data['creator_name'] ?? 'Unknown'),
            'creator_id' => $pin_data['creator_id'] ? htmlspecialchars($pin_data['creator_id']) : '',
            'creator_img' => $pin_data['creator_img'] ? '../images/' . htmlspecialchars($pin_data['creator_img']) : '../images/no_image.jpg',
            'outfit_post_id' => !empty($pin_data['outfit_post_id']) ? (int) $pin_data['outfit_post_id'] : null,
        ];
    }
    
    $query = "
    SELECT c.id, c.comment, c.created_at, c.user_id, r.username, r.img as user_img
    FROM comments c
    JOIN registration r ON c.user_id = r.id
    WHERE c.pin_id = ?
    ORDER BY c.created_at DESC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$pin_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Loaded comments for pin_id {$pin_id}: " . count($comments));
} else {
    $comments = [];
}

