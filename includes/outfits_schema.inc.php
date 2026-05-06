<?php

function ensureOutfitsTable(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS outfits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        img VARCHAR(255) NOT NULL,
        builder_state LONGTEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_outfits_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $columns = $pdo->query("SHOW COLUMNS FROM outfits")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('builder_state', $columns, true)) {
        $pdo->exec("ALTER TABLE outfits ADD COLUMN builder_state LONGTEXT NULL AFTER img");
    }
    if (!in_array('updated_at', $columns, true)) {
        $pdo->exec("ALTER TABLE outfits ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
    }
}

function getWeeklyChallengeTheme(DateTimeInterface $date): array {
    $themes = [
        ['Monochrome Week', 'Build a complete look using one dominant color family.'],
        ['Streetwear Week', 'Mix oversized layers, sneakers, and statement accessories.'],
        ['Old Money Week', 'Curate clean tailoring and understated luxury tones.'],
        ['Denim Remix Week', 'Create an outfit where denim is the hero piece.'],
        ['Minimalist Week', 'Keep it simple with neutral palettes and clean silhouettes.'],
        ['Bold Prints Week', 'Show confidence with patterns, textures, and graphic pieces.'],
        ['Vintage Week', 'Blend retro influences with modern styling details.'],
        ['Night Out Week', 'Design your strongest evening or party look.'],
    ];

    $weekIndex = (int) $date->format('W');
    $theme = $themes[($weekIndex - 1) % count($themes)];

    return [
        'title' => $theme[0],
        'description' => $theme[1],
    ];
}

function ensureOutfitChallengeTables(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS outfit_challenges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        week_key VARCHAR(16) NOT NULL UNIQUE,
        theme VARCHAR(120) NOT NULL,
        description VARCHAR(255) NOT NULL,
        starts_at DATE NOT NULL,
        ends_at DATE NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_outfit_challenges_week (week_key),
        INDEX idx_outfit_challenges_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS outfit_challenge_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        challenge_id INT NOT NULL,
        outfit_id INT NOT NULL,
        user_id INT NOT NULL,
        caption VARCHAR(255) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_challenge_user (challenge_id, user_id),
        UNIQUE KEY uniq_challenge_outfit (challenge_id, outfit_id),
        INDEX idx_entries_challenge (challenge_id),
        INDEX idx_entries_outfit (outfit_id),
        INDEX idx_entries_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS outfit_challenge_votes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        challenge_id INT NOT NULL,
        entry_id INT NOT NULL,
        voter_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_entry_voter (entry_id, voter_id),
        INDEX idx_votes_challenge (challenge_id),
        INDEX idx_votes_entry (entry_id),
        INDEX idx_votes_voter (voter_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS outfit_challenge_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        challenge_id INT NOT NULL,
        entry_id INT NOT NULL,
        user_id INT NOT NULL,
        comment TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_challenge_comments_challenge (challenge_id),
        INDEX idx_challenge_comments_entry (entry_id),
        INDEX idx_challenge_comments_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ensureCurrentWeeklyChallenge(PDO $pdo): array {
    ensureOutfitChallengeTables($pdo);

    $now = new DateTimeImmutable('now');
    $weekKey = $now->format('o') . '-W' . $now->format('W');

    $existingStmt = $pdo->prepare('SELECT id, week_key, theme, description, starts_at, ends_at FROM outfit_challenges WHERE week_key = ? LIMIT 1');
    $existingStmt->execute([$weekKey]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        return $existing;
    }

    $startsAt = $now->modify('monday this week')->format('Y-m-d');
    $endsAt = $now->modify('sunday this week')->format('Y-m-d');
    $theme = getWeeklyChallengeTheme($now);

    $insertStmt = $pdo->prepare('INSERT INTO outfit_challenges (week_key, theme, description, starts_at, ends_at, is_active) VALUES (?, ?, ?, ?, ?, 1)');
    $insertStmt->execute([$weekKey, $theme['title'], $theme['description'], $startsAt, $endsAt]);

    $challengeId = (int) $pdo->lastInsertId();

    return [
        'id' => $challengeId,
        'week_key' => $weekKey,
        'theme' => $theme['title'],
        'description' => $theme['description'],
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
    ];
}

function getCurrentIsoWeekKey(): string {
    $now = new DateTimeImmutable('now');
    return $now->format('o') . '-W' . $now->format('W');
}

function calculateWeekKeyStreak(array $weekKeys, ?string $anchorWeekKey = null): int {
    if (empty($weekKeys)) {
        return 0;
    }

    $anchor = $anchorWeekKey ?: getCurrentIsoWeekKey();
    $anchorDate = DateTimeImmutable::createFromFormat('o-\\WW-N', $anchor . '-1');
    if (!$anchorDate) {
        $anchorDate = new DateTimeImmutable('monday this week');
    }

    $weekSet = [];
    foreach ($weekKeys as $weekKey) {
        $normalized = trim((string) $weekKey);
        if ($normalized !== '') {
            $weekSet[$normalized] = true;
        }
    }

    $streak = 0;
    $cursor = $anchorDate;
    while (true) {
        $cursorWeekKey = $cursor->format('o') . '-W' . $cursor->format('W');
        if (!isset($weekSet[$cursorWeekKey])) {
            break;
        }
        $streak++;
        $cursor = $cursor->modify('-1 week');
    }

    return $streak;
}

function getUserChallengeBadgeStats(PDO $pdo, int $userId): array {
    ensureOutfitChallengeTables($pdo);

    $default = [
        'participation_count' => 0,
        'participation_streak' => 0,
        'top3_finishes' => 0,
        'wins_count' => 0,
        'first_win_earned' => false,
        'voted_weeks_count' => 0,
        'voting_streak' => 0,
        'badges' => [
            'weekly_participation' => false,
            'top3_finisher' => false,
            'first_win' => false,
            'voting_streak' => false,
        ],
    ];

    if ($userId <= 0) {
        return $default;
    }

    $participationStmt = $pdo->prepare(
        'SELECT c.week_key
         FROM outfit_challenge_entries e
         INNER JOIN outfit_challenges c ON c.id = e.challenge_id
         WHERE e.user_id = ?
         ORDER BY c.starts_at DESC, c.id DESC'
    );
    $participationStmt->execute([$userId]);
    $participationWeekKeys = array_values(array_unique(array_filter(array_map('strval', $participationStmt->fetchAll(PDO::FETCH_COLUMN)))));

    $votedWeeksStmt = $pdo->prepare(
           'SELECT c.week_key
         FROM outfit_challenge_votes v
         INNER JOIN outfit_challenges c ON c.id = v.challenge_id
         WHERE v.voter_id = ?
            GROUP BY c.week_key
            ORDER BY MAX(c.starts_at) DESC, MAX(c.id) DESC'
    );
    $votedWeeksStmt->execute([$userId]);
    $votedWeekKeys = array_values(array_unique(array_filter(array_map('strval', $votedWeeksStmt->fetchAll(PDO::FETCH_COLUMN)))));

    $entriesScoreStmt = $pdo->query(
        'SELECT e.id,
                e.challenge_id,
                e.user_id,
                e.updated_at,
                COALESCE((SELECT COUNT(*) FROM outfit_challenge_votes v WHERE v.entry_id = e.id), 0) AS vote_count,
                COALESCE((
                    SELECT COUNT(*)
                    FROM pins p
                    INNER JOIN likes l ON l.pin_id = p.id
                    WHERE p.link = CONCAT("outfit://", e.outfit_id)
                ), 0) AS like_count
         FROM outfit_challenge_entries e
         ORDER BY e.challenge_id ASC, e.id DESC'
    );
    $allEntries = $entriesScoreStmt->fetchAll(PDO::FETCH_ASSOC);

    $entriesByChallenge = [];
    foreach ($allEntries as $entryRow) {
        $challengeId = (int) ($entryRow['challenge_id'] ?? 0);
        if ($challengeId <= 0) {
            continue;
        }
        if (!isset($entriesByChallenge[$challengeId])) {
            $entriesByChallenge[$challengeId] = [];
        }
        $entriesByChallenge[$challengeId][] = [
            'id' => (int) ($entryRow['id'] ?? 0),
            'user_id' => (int) ($entryRow['user_id'] ?? 0),
            'updated_at' => (string) ($entryRow['updated_at'] ?? ''),
            'vote_count' => (int) ($entryRow['vote_count'] ?? 0),
            'like_count' => (int) ($entryRow['like_count'] ?? 0),
        ];
    }

    $top3Finishes = 0;
    $winsCount = 0;

    foreach ($entriesByChallenge as $challengeRows) {
        usort($challengeRows, static function (array $a, array $b): int {
            if ($a['vote_count'] !== $b['vote_count']) {
                return $b['vote_count'] <=> $a['vote_count'];
            }
            if ($a['like_count'] !== $b['like_count']) {
                return $b['like_count'] <=> $a['like_count'];
            }
            if ($a['updated_at'] !== $b['updated_at']) {
                return strcmp($b['updated_at'], $a['updated_at']);
            }
            return $b['id'] <=> $a['id'];
        });

        foreach ($challengeRows as $index => $rankedEntry) {
            if ((int) ($rankedEntry['user_id'] ?? 0) !== $userId) {
                continue;
            }

            $rankPosition = $index + 1;
            if ($rankPosition <= 3) {
                $top3Finishes++;
            }
            if ($rankPosition === 1) {
                $winsCount++;
            }
            break;
        }
    }

    $participationCount = count($participationWeekKeys);
    $participationStreak = calculateWeekKeyStreak($participationWeekKeys);
    $votedWeeksCount = count($votedWeekKeys);
    $votingStreak = calculateWeekKeyStreak($votedWeekKeys);

    return [
        'participation_count' => $participationCount,
        'participation_streak' => $participationStreak,
        'top3_finishes' => $top3Finishes,
        'wins_count' => $winsCount,
        'first_win_earned' => $winsCount > 0,
        'voted_weeks_count' => $votedWeeksCount,
        'voting_streak' => $votingStreak,
        'badges' => [
            'weekly_participation' => $participationCount > 0,
            'top3_finisher' => $top3Finishes > 0,
            'first_win' => $winsCount > 0,
            'voting_streak' => $votingStreak >= 2,
        ],
    ];
}