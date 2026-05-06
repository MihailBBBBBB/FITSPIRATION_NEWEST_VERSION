<?php

function ensureDiscoveryFilterTables(PDO $pdo): void {
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS pin_discovery_meta (
            pin_id INT NOT NULL PRIMARY KEY,
            dominant_color VARCHAR(32) NOT NULL DEFAULT '',
            style_tag VARCHAR(50) NOT NULL DEFAULT '',
            season VARCHAR(20) NOT NULL DEFAULT '',
            category VARCHAR(50) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_pin_discovery_color (dominant_color),
            INDEX idx_pin_discovery_style (style_tag),
            INDEX idx_pin_discovery_season (season),
            INDEX idx_pin_discovery_category (category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS smart_feed_filters (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            feed_name VARCHAR(120) NOT NULL,
            dominant_color VARCHAR(32) NOT NULL DEFAULT '',
            style_tag VARCHAR(50) NOT NULL DEFAULT '',
            season VARCHAR(20) NOT NULL DEFAULT '',
            category VARCHAR(50) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_smart_feed_user (user_id),
            INDEX idx_smart_feed_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $initialized = true;
}

function getDiscoveryFilterOptionSets(): array {
    return [
        'colors' => [
            'black', 'white', 'gray', 'blue', 'red', 'green', 'brown', 'beige',
            'pink', 'purple', 'yellow', 'orange', 'multi'
        ],
        'styles' => [
            'streetwear', 'minimalist', 'casual', 'formal', 'sport', 'boho',
            'vintage', 'y2k', 'grunge', 'preppy'
        ],
        'seasons' => ['winter', 'spring', 'summer', 'autumn', 'all-season'],
        'categories' => ['tops', 'bottoms', 'outerwear', 'footwear', 'accessories', 'full-look'],
    ];
}

function normalizeDiscoveryFilterValue(string $value, array $allowedValues): string {
    $normalized = strtolower(trim($value));
    return in_array($normalized, $allowedValues, true) ? $normalized : '';
}

function normalizeDiscoveryFilters(array $filters): array {
    $options = getDiscoveryFilterOptionSets();

    return [
        'dominant_color' => normalizeDiscoveryFilterValue((string) ($filters['dominant_color'] ?? ''), $options['colors']),
        'style_tag' => normalizeDiscoveryFilterValue((string) ($filters['style_tag'] ?? ''), $options['styles']),
        'season' => normalizeDiscoveryFilterValue((string) ($filters['season'] ?? ''), $options['seasons']),
        'category' => normalizeDiscoveryFilterValue((string) ($filters['category'] ?? ''), $options['categories']),
    ];
}

function savePinDiscoveryMeta(PDO $pdo, int $pinId, array $filters): bool {
    ensureDiscoveryFilterTables($pdo);

    if ($pinId <= 0) {
        return false;
    }

    $normalized = normalizeDiscoveryFilters($filters);

    $stmt = $pdo->prepare(
        'INSERT INTO pin_discovery_meta (pin_id, dominant_color, style_tag, season, category)
         VALUES (:pin_id, :dominant_color, :style_tag, :season, :category)
         ON DUPLICATE KEY UPDATE
             dominant_color = VALUES(dominant_color),
             style_tag = VALUES(style_tag),
             season = VALUES(season),
             category = VALUES(category)'
    );

    return $stmt->execute([
        'pin_id' => $pinId,
        'dominant_color' => $normalized['dominant_color'],
        'style_tag' => $normalized['style_tag'],
        'season' => $normalized['season'],
        'category' => $normalized['category'],
    ]);
}

function createSmartFeed(PDO $pdo, int $userId, string $feedName, array $filters): bool {
    ensureDiscoveryFilterTables($pdo);

    if ($userId <= 0) {
        return false;
    }

    $normalized = normalizeDiscoveryFilters($filters);
    if ($normalized['dominant_color'] === '' && $normalized['style_tag'] === '' && $normalized['season'] === '' && $normalized['category'] === '') {
        return false;
    }

    $feedName = trim($feedName);
    if ($feedName === '') {
        $parts = array_filter([
            $normalized['dominant_color'],
            $normalized['style_tag'],
            $normalized['season'],
            $normalized['category'],
        ], static fn($value) => $value !== '');
        $feedName = ucfirst(implode(' ', $parts));
        if ($feedName === '') {
            $feedName = 'Smart Feed';
        }
    }

    if (mb_strlen($feedName) > 120) {
        $feedName = mb_substr($feedName, 0, 120);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO smart_feed_filters (user_id, feed_name, dominant_color, style_tag, season, category)
         VALUES (:user_id, :feed_name, :dominant_color, :style_tag, :season, :category)'
    );

    return $stmt->execute([
        'user_id' => $userId,
        'feed_name' => $feedName,
        'dominant_color' => $normalized['dominant_color'],
        'style_tag' => $normalized['style_tag'],
        'season' => $normalized['season'],
        'category' => $normalized['category'],
    ]);
}

function getSmartFeedsForUser(PDO $pdo, int $userId, int $limit = 12): array {
    ensureDiscoveryFilterTables($pdo);

    if ($userId <= 0) {
        return [];
    }

    $limit = max(1, min(50, $limit));

    $stmt = $pdo->prepare(
        "SELECT id, feed_name, dominant_color, style_tag, season, category, created_at
         FROM smart_feed_filters
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT {$limit}"
    );
    $stmt->execute([$userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function getSmartFeedByIdForUser(PDO $pdo, int $userId, int $feedId): ?array {
    ensureDiscoveryFilterTables($pdo);

    if ($userId <= 0 || $feedId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, feed_name, dominant_color, style_tag, season, category
         FROM smart_feed_filters
         WHERE id = ? AND user_id = ?
         LIMIT 1'
    );
    $stmt->execute([$feedId, $userId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}
