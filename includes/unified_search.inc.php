<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once 'dbh.inc.php';
require_once 'discovery_filters.inc.php';
require_once 'visual_similarity.inc.php';
require_once 'image_storage.inc.php';

function unifiedSearchRespond(array $payload): void {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

function unifiedSearchNormalizeScope(string $scope): string {
    $allowed = ['all', 'pins', 'outfits', 'people', 'boards'];
    return in_array($scope, $allowed, true) ? $scope : 'all';
}

function unifiedSearchTokenize(string $text): array {
    $parts = preg_split('/[^a-z0-9]+/i', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
    if (!$parts) {
        return [];
    }

    $stop = ['the', 'and', 'for', 'with', 'this', 'that', 'look', 'style', 'outfit', 'pin'];
    $tokens = [];
    foreach ($parts as $part) {
        if (mb_strlen($part) < 3 || in_array($part, $stop, true)) {
            continue;
        }
        $tokens[] = $part;
        if (count($tokens) >= 4) {
            break;
        }
    }

    return $tokens;
}

function unifiedSearchBuildTagSuggestions(string $queryText): array {
    $q = mb_strtolower($queryText);
    $sets = getDiscoveryFilterOptionSets();
    $bucketMap = [
        'color' => $sets['colors'] ?? [],
        'style' => $sets['styles'] ?? [],
        'season' => $sets['seasons'] ?? [],
        'category' => $sets['categories'] ?? [],
    ];

    $rows = [];
    foreach ($bucketMap as $kind => $values) {
        foreach ($values as $value) {
            $valueStr = (string) $value;
            $valueLower = mb_strtolower($valueStr);
            $pos = mb_strpos($valueLower, $q);
            if ($pos === false) {
                continue;
            }

            $rows[] = [
                'kind' => $kind,
                'value' => $valueStr,
                'score' => $pos === 0 ? 0 : 1,
            ];
        }
    }

    usort($rows, static function(array $a, array $b): int {
        if ((int) $a['score'] === (int) $b['score']) {
            return strcmp((string) $a['value'], (string) $b['value']);
        }
        return ((int) $a['score'] < (int) $b['score']) ? -1 : 1;
    });

    $rows = array_slice($rows, 0, 8);
    return array_map(static function(array $row): array {
        return [
            'kind' => (string) ($row['kind'] ?? 'style'),
            'value' => (string) ($row['value'] ?? ''),
        ];
    }, $rows);
}

function unifiedSearchBuildFacetCounts(array $pins, array $outfits): array {
    $all = array_merge($pins, $outfits);
    $facets = [
        'type' => [
            'pins' => count($pins),
            'outfits' => count($outfits),
        ],
        'color' => [],
        'style' => [],
        'season' => [],
        'category' => [],
    ];

    foreach ($all as $row) {
        foreach (['dominant_color' => 'color', 'style_tag' => 'style', 'season' => 'season', 'category' => 'category'] as $sourceKey => $facetKey) {
            $value = trim((string) ($row[$sourceKey] ?? ''));
            if ($value === '') {
                continue;
            }
            $facets[$facetKey][$value] = ($facets[$facetKey][$value] ?? 0) + 1;
        }
    }

    foreach (['color', 'style', 'season', 'category'] as $facetName) {
        arsort($facets[$facetName]);
        $facets[$facetName] = array_slice($facets[$facetName], 0, 10, true);
    }

    return $facets;
}

function unifiedSearchRunTypeQueries(PDO $pdo, string $queryText, string $scope, int $limit, int $currentUserId): array {
    $prefixLike = $queryText . '%';
    $containsLike = '%' . $queryText . '%';

    $users = [];
    $pins = [];
    $outfits = [];
    $boards = [];

    if ($scope === 'all' || $scope === 'people') {
        $stmt = $pdo->prepare(
            "SELECT r.id, r.username, r.img,
                    (SELECT COUNT(*) FROM follows f WHERE f.following_id = r.id) AS follower_count,
                    (
                        CASE WHEN LOWER(r.username) = :exact THEN 320 ELSE 0 END
                        + CASE WHEN LOWER(r.username) LIKE :prefix THEN 220 ELSE 0 END
                        + CASE WHEN LOWER(r.username) LIKE :contains THEN 80 ELSE 0 END
                        + LEAST(30, (SELECT COUNT(*) FROM follows f2 WHERE f2.following_id = r.id) * 0.1)
                    ) AS score
             FROM registration r
             WHERE r.banned = 0
               AND r.username LIKE :contains_raw
               AND r.id <> :user_id
             ORDER BY score DESC, r.username ASC
             LIMIT {$limit}"
        );
        $stmt->execute([
            'exact' => mb_strtolower($queryText),
            'prefix' => mb_strtolower($prefixLike),
            'contains' => mb_strtolower($containsLike),
            'contains_raw' => $containsLike,
            'user_id' => $currentUserId,
        ]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if ($scope === 'all' || $scope === 'pins' || $scope === 'outfits') {
        $stmt = $pdo->prepare(
            "SELECT p.id,
                    p.title,
                    p.img,
                    p.link,
                    COALESCE(p.user_id, c.user_id) AS creator_id,
                    COALESCE(pr.username, cr.username, 'Unknown') AS creator_name,
                    COALESCE(pdm.dominant_color, '') AS dominant_color,
                    COALESCE(pdm.style_tag, '') AS style_tag,
                    COALESCE(pdm.season, '') AS season,
                    COALESCE(pdm.category, '') AS category,
                    (SELECT COUNT(*) FROM likes l WHERE l.pin_id = p.id) AS like_count,
                    CASE WHEN p.link LIKE 'outfit://%' THEN 1 ELSE 0 END AS is_outfit,
                    (
                        CASE WHEN LOWER(COALESCE(p.title, '')) = :exact THEN 320 ELSE 0 END
                        + CASE WHEN LOWER(COALESCE(p.title, '')) LIKE :prefix THEN 210 ELSE 0 END
                        + CASE WHEN LOWER(COALESCE(p.title, '')) LIKE :contains THEN 85 ELSE 0 END
                        + CASE WHEN LOWER(COALESCE(pr.username, cr.username, '')) LIKE :prefix THEN 70 ELSE 0 END
                        + LEAST(40, (SELECT COUNT(*) FROM likes l2 WHERE l2.pin_id = p.id) * 0.2)
                    ) AS score
             FROM pins p
             INNER JOIN collections c ON c.collection_id = p.collection_id
             LEFT JOIN registration pr ON pr.id = p.user_id
             LEFT JOIN registration cr ON cr.id = c.user_id
             LEFT JOIN pin_discovery_meta pdm ON pdm.pin_id = p.id
             WHERE c.privacy = 'Public'
               AND LOWER(COALESCE(p.title, '')) LIKE :contains
             ORDER BY score DESC, p.id DESC
             LIMIT " . ($limit * 3)
        );
        $stmt->execute([
            'exact' => mb_strtolower($queryText),
            'prefix' => mb_strtolower($prefixLike),
            'contains' => mb_strtolower($containsLike),
        ]);

        $pinRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($pinRows as $row) {
            $isOutfit = (int) ($row['is_outfit'] ?? 0) === 1;
            if ($isOutfit) {
                $outfits[] = $row;
            } else {
                $pins[] = $row;
            }
        }

        $pins = array_slice($pins, 0, $limit);
        $outfits = array_slice($outfits, 0, $limit);

        if ($scope === 'pins') {
            $outfits = [];
        }
        if ($scope === 'outfits') {
            $pins = [];
        }
    }

    if ($scope === 'all' || $scope === 'boards') {
        $stmt = $pdo->prepare(
            "SELECT c.collection_id AS id,
                    c.title,
                    c.description,
                    c.img,
                    c.user_id,
                    r.username,
                    (
                        CASE WHEN LOWER(COALESCE(c.title, '')) = :exact THEN 300 ELSE 0 END
                        + CASE WHEN LOWER(COALESCE(c.title, '')) LIKE :prefix THEN 180 ELSE 0 END
                        + CASE WHEN LOWER(COALESCE(c.title, '')) LIKE :contains THEN 85 ELSE 0 END
                        + CASE WHEN LOWER(COALESCE(c.description, '')) LIKE :contains THEN 40 ELSE 0 END
                    ) AS score
             FROM collections c
             LEFT JOIN registration r ON r.id = c.user_id
             WHERE c.privacy = 'Public'
               AND (
                    LOWER(COALESCE(c.title, '')) LIKE :contains
                    OR LOWER(COALESCE(c.description, '')) LIKE :contains
               )
             ORDER BY score DESC, c.collection_id DESC
             LIMIT {$limit}"
        );
        $stmt->execute([
            'exact' => mb_strtolower($queryText),
            'prefix' => mb_strtolower($prefixLike),
            'contains' => mb_strtolower($containsLike),
        ]);
        $boards = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    return [
        'users' => $users,
        'pins' => $pins,
        'outfits' => $outfits,
        'boards' => $boards,
    ];
}

function unifiedSearchFormatResults(array $rows): array {
    $users = array_map(static function(array $row): array {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'username' => (string) ($row['username'] ?? 'User'),
            'img' => buildFitspirationAvatarUrl($row['img'] ?? '', (string) ($row['username'] ?? 'User')),
            'score' => (float) ($row['score'] ?? 0),
        ];
    }, $rows['users'] ?? []);

    $pins = array_map(static function(array $row): array {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? 'Pin'),
            'img' => !empty($row['img']) ? '../images/' . (string) $row['img'] : '../images/no_image.jpg',
            'creator_id' => (int) ($row['creator_id'] ?? 0),
            'creator_name' => (string) ($row['creator_name'] ?? 'Unknown'),
            'dominant_color' => (string) ($row['dominant_color'] ?? ''),
            'style_tag' => (string) ($row['style_tag'] ?? ''),
            'season' => (string) ($row['season'] ?? ''),
            'category' => (string) ($row['category'] ?? ''),
            'like_count' => (int) ($row['like_count'] ?? 0),
            'score' => (float) ($row['score'] ?? 0),
        ];
    }, $rows['pins'] ?? []);

    $outfits = array_map(static function(array $row): array {
        $outfitId = 0;
        $link = (string) ($row['link'] ?? '');
        if (str_starts_with($link, 'outfit://')) {
            $outfitId = (int) substr($link, 9);
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'outfit_id' => $outfitId,
            'title' => (string) ($row['title'] ?? 'Outfit'),
            'img' => !empty($row['img']) ? '../images/' . (string) $row['img'] : '../images/no_image.jpg',
            'creator_id' => (int) ($row['creator_id'] ?? 0),
            'creator_name' => (string) ($row['creator_name'] ?? 'Unknown'),
            'dominant_color' => (string) ($row['dominant_color'] ?? ''),
            'style_tag' => (string) ($row['style_tag'] ?? ''),
            'season' => (string) ($row['season'] ?? ''),
            'category' => (string) ($row['category'] ?? ''),
            'like_count' => (int) ($row['like_count'] ?? 0),
            'score' => (float) ($row['score'] ?? 0),
        ];
    }, $rows['outfits'] ?? []);

    $boards = array_map(static function(array $row): array {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? 'Board'),
            'description' => (string) ($row['description'] ?? ''),
            'img' => !empty($row['img']) ? '../images/' . (string) $row['img'] : '../images/no_image.jpg',
            'owner_id' => (int) ($row['user_id'] ?? 0),
            'owner_name' => (string) ($row['username'] ?? 'Unknown'),
            'score' => (float) ($row['score'] ?? 0),
        ];
    }, $rows['boards'] ?? []);

    return [
        'users' => $users,
        'pins' => $pins,
        'outfits' => $outfits,
        'boards' => $boards,
    ];
}

function unifiedSearchVisualSimilarity(PDO $pdo, int $sourcePinId, string $scope, int $limit): array {
    $sourceStmt = $pdo->prepare(
        "SELECT p.id, p.title, p.link,
                COALESCE(pdm.dominant_color, '') AS dominant_color,
                COALESCE(pdm.style_tag, '') AS style_tag,
                COALESCE(pdm.season, '') AS season,
                COALESCE(pdm.category, '') AS category
         FROM pins p
         LEFT JOIN pin_discovery_meta pdm ON pdm.pin_id = p.id
         WHERE p.id = ?
         LIMIT 1"
    );
    $sourceStmt->execute([$sourcePinId]);
    $source = $sourceStmt->fetch(PDO::FETCH_ASSOC);

    if (!$source) {
        return [
            'source' => null,
            'results' => ['pins' => [], 'outfits' => []],
            'facets' => ['type' => ['pins' => 0, 'outfits' => 0], 'color' => [], 'style' => [], 'season' => [], 'category' => []],
        ];
    }

    $params = [
        'source_id' => $sourcePinId,
    ];
    $scopeSql = '';
    if ($scope === 'pins') {
        $scopeSql = "AND (p.link IS NULL OR p.link NOT LIKE 'outfit://%')";
    } elseif ($scope === 'outfits') {
        $scopeSql = "AND p.link LIKE 'outfit://%'";
    }

    $similarStmt = $pdo->prepare(
        "SELECT p.id,
                p.title,
                p.img,
                p.link,
                COALESCE(pdm.dominant_color, '') AS dominant_color,
                COALESCE(pdm.style_tag, '') AS style_tag,
                COALESCE(pdm.season, '') AS season,
                COALESCE(pdm.category, '') AS category,
                (SELECT COUNT(*) FROM likes l WHERE l.pin_id = p.id) AS like_count,
                0 AS score,
                CASE WHEN p.link LIKE 'outfit://%' THEN 1 ELSE 0 END AS is_outfit
         FROM pins p
         INNER JOIN collections c ON c.collection_id = p.collection_id
         LEFT JOIN pin_discovery_meta pdm ON pdm.pin_id = p.id
         WHERE c.privacy = 'Public'
           AND p.id <> :source_id
           {$scopeSql}
         ORDER BY p.id DESC
         LIMIT " . max(80, $limit * 6)
    );
    $similarStmt->execute($params);

    $rankedRows = rankPinsByVisualSimilarity($pdo, $sourcePinId, $similarStmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    $rankedRows = array_slice($rankedRows, 0, $limit);

    $pins = [];
    $outfits = [];
    foreach ($rankedRows as $row) {
        if ((int) ($row['is_outfit'] ?? 0) === 1) {
            $outfits[] = $row;
        } else {
            $pins[] = $row;
        }
    }

    $formatted = unifiedSearchFormatResults([
        'users' => [],
        'pins' => $pins,
        'outfits' => $outfits,
        'boards' => [],
    ]);

    return [
        'source' => [
            'id' => (int) ($source['id'] ?? 0),
            'title' => (string) ($source['title'] ?? 'Pin'),
            'dominant_color' => (string) ($source['dominant_color'] ?? ''),
            'style_tag' => (string) ($source['style_tag'] ?? ''),
            'season' => (string) ($source['season'] ?? ''),
            'category' => (string) ($source['category'] ?? ''),
        ],
        'results' => [
            'pins' => $formatted['pins'],
            'outfits' => $formatted['outfits'],
        ],
        'facets' => unifiedSearchBuildFacetCounts($pins, $outfits),
    ];
}

try {
    ensureDiscoveryFilterTables($pdo);
    ensureVisualSimilarityTable($pdo);

    $action = trim((string) ($_GET['action'] ?? 'typeahead'));
    $queryText = trim((string) ($_GET['q'] ?? ''));
    $scope = unifiedSearchNormalizeScope(trim((string) ($_GET['search_scope'] ?? 'all')));
    $limit = (int) ($_GET['limit'] ?? ($action === 'typeahead' ? 6 : 20));
    $limit = max(1, min(40, $limit));
    $currentUserId = (int) ($_SESSION['user_id'] ?? 0);

    if ($action === 'visual_similarity') {
        $sourcePinId = (int) ($_GET['source_pin_id'] ?? 0);
        if ($sourcePinId <= 0) {
            unifiedSearchRespond(['success' => false, 'error' => 'Missing source pin id']);
        }

        $payload = unifiedSearchVisualSimilarity($pdo, $sourcePinId, $scope, $limit);
        unifiedSearchRespond([
            'success' => true,
            'mode' => 'visual_similarity',
            'scope' => $scope,
            'data' => $payload,
        ]);
    }

    if (mb_strlen($queryText) < 1) {
        unifiedSearchRespond([
            'success' => true,
            'mode' => $action,
            'scope' => $scope,
            'query' => $queryText,
            'results' => [
                'users' => [],
                'pins' => [],
                'outfits' => [],
                'boards' => [],
            ],
            'suggestions' => [
                'users' => [],
                'pins' => [],
                'outfits' => [],
                'collections' => [],
                'tags' => [],
            ],
            'facets' => [
                'type' => ['pins' => 0, 'outfits' => 0, 'people' => 0, 'boards' => 0],
                'color' => [],
                'style' => [],
                'season' => [],
                'category' => [],
            ],
        ]);
    }

    $rows = unifiedSearchRunTypeQueries($pdo, $queryText, $scope, $limit, $currentUserId);
    $formatted = unifiedSearchFormatResults($rows);

    $facets = unifiedSearchBuildFacetCounts($rows['pins'], $rows['outfits']);
    $facets['type']['people'] = count($formatted['users']);
    $facets['type']['boards'] = count($formatted['boards']);

    if ($action === 'typeahead') {
        unifiedSearchRespond([
            'success' => true,
            'mode' => 'typeahead',
            'scope' => $scope,
            'query' => $queryText,
            'suggestions' => [
                'users' => array_slice($formatted['users'], 0, 6),
                'pins' => array_slice($formatted['pins'], 0, 6),
                'outfits' => array_slice($formatted['outfits'], 0, 6),
                'collections' => array_slice($formatted['boards'], 0, 6),
                'tags' => unifiedSearchBuildTagSuggestions($queryText),
            ],
            'facets' => $facets,
        ]);
    }

    $blended = [];
    foreach (['users', 'pins', 'outfits', 'boards'] as $typeKey) {
        foreach ($formatted[$typeKey] as $row) {
            $row['type'] = $typeKey;
            $blended[] = $row;
        }
    }

    usort($blended, static function(array $a, array $b): int {
        $aScore = (float) ($a['score'] ?? 0);
        $bScore = (float) ($b['score'] ?? 0);
        if ($aScore === $bScore) {
            return 0;
        }
        return ($aScore > $bScore) ? -1 : 1;
    });

    unifiedSearchRespond([
        'success' => true,
        'mode' => 'search',
        'scope' => $scope,
        'query' => $queryText,
        'results' => [
            'users' => $formatted['users'],
            'pins' => $formatted['pins'],
            'outfits' => $formatted['outfits'],
            'boards' => $formatted['boards'],
            'blended' => array_slice($blended, 0, $limit),
        ],
        'facets' => $facets,
    ]);
} catch (Throwable $e) {
    error_log('Unified search endpoint error: ' . $e->getMessage());
    unifiedSearchRespond(['success' => false, 'error' => 'Search failed']);
}
