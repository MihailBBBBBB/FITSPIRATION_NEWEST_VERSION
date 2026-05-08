<?php

function ensureTableColumn(PDO $pdo, string $tableName, string $columnName, string $definition): void {
    $stmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table_name
          AND COLUMN_NAME = :column_name
    ');
    $stmt->execute([
        'table_name' => $tableName,
        'column_name' => $columnName,
    ]);

    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec(sprintf(
            'ALTER TABLE `%s` ADD COLUMN `%s` %s',
            $tableName,
            $columnName,
            $definition
        ));
    }
}

function ensureTableIndex(PDO $pdo, string $tableName, string $indexName, string $definition): void {
    $stmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table_name
          AND INDEX_NAME = :index_name
    ');
    $stmt->execute([
        'table_name' => $tableName,
        'index_name' => $indexName,
    ]);

    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec(sprintf('ALTER TABLE `%s` ADD %s', $tableName, $definition));
    }
}

function ensureModerationTables(PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS reports (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reporter_user_id INT NOT NULL,
            target_type VARCHAR(32) NOT NULL,
            target_id INT NOT NULL,
            reason VARCHAR(255) NOT NULL,
            category VARCHAR(32) NOT NULL DEFAULT "other",
            status VARCHAR(32) NOT NULL DEFAULT "open",
            rate_limited_until DATETIME NULL,
            resolved_by INT NULL,
            resolved_at DATETIME NULL,
            admin_note VARCHAR(255) NOT NULL DEFAULT "",
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS audit_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            admin_user_id INT NOT NULL,
            action_type VARCHAR(64) NOT NULL,
            target_type VARCHAR(32) NOT NULL,
            target_id INT NULL,
            details TEXT NOT NULL,
            admin_note VARCHAR(255) NOT NULL DEFAULT "",
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS user_reports (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            report_id INT NOT NULL,
            viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    ensureTableIndex($pdo, 'reports', 'idx_reports_target', 'INDEX idx_reports_target (target_type, target_id)');
    ensureTableIndex($pdo, 'reports', 'idx_reports_status', 'INDEX idx_reports_status (status)');
    ensureTableIndex($pdo, 'reports', 'idx_reports_reporter', 'INDEX idx_reports_reporter (reporter_user_id)');
    ensureTableIndex($pdo, 'audit_log', 'idx_audit_admin', 'INDEX idx_audit_admin (admin_user_id)');
    ensureTableIndex($pdo, 'audit_log', 'idx_audit_target', 'INDEX idx_audit_target (target_type, target_id)');
    ensureTableIndex($pdo, 'user_reports', 'uniq_user_report', 'UNIQUE INDEX uniq_user_report (user_id, report_id)');
}

function createContentReport(PDO $pdo, int $reporterUserId, string $targetType, int $targetId, string $reason, string $category = 'other'): array {
    $targetType = strtolower(trim($targetType));
    $reason = trim($reason);
    $category = strtolower(trim($category));

    if (!in_array($targetType, ['pin', 'comment'], true)) {
        return ['ok' => false, 'message' => 'Unsupported report target.'];
    }

    $validCategories = ['spam', 'harassment', 'nudity', 'hate', 'misinformation', 'copyright', 'other'];
    if (!in_array($category, $validCategories)) {
        $category = 'other';
    }

    if ($targetId <= 0) {
        return ['ok' => false, 'message' => 'Invalid target selected.'];
    }

    if ($reason === '') {
        return ['ok' => false, 'message' => 'Please describe why you are reporting this content.'];
    }

    if (strlen($reason) > 255) {
        $reason = substr($reason, 0, 255);
    }

    // Rate limiting check
    $rateCheck = $pdo->prepare('SELECT rate_limited_until FROM reports WHERE reporter_user_id = :uid ORDER BY created_at DESC LIMIT 1');
    $rateCheck->execute(['uid' => $reporterUserId]);
    $lastReport = $rateCheck->fetch(PDO::FETCH_ASSOC);
    
    if ($lastReport && $lastReport['rate_limited_until']) {
        if (strtotime($lastReport['rate_limited_until']) > time()) {
            $waitTime = ceil((strtotime($lastReport['rate_limited_until']) - time()) / 60);
            return ['ok' => false, 'message' => "Please wait $waitTime minute(s) before submitting another report."];
        }
    }

    if ($targetType === 'pin') {
        $stmt = $pdo->prepare('SELECT id, user_id FROM pins WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $targetId]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$target) {
            return ['ok' => false, 'message' => 'Pin not found.'];
        }
        if ((int)($target['user_id'] ?? 0) === $reporterUserId) {
            return ['ok' => false, 'message' => 'You cannot report your own pin.'];
        }
    } else {
        $stmt = $pdo->prepare('SELECT id, user_id FROM comments WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $targetId]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$target) {
            return ['ok' => false, 'message' => 'Comment not found.'];
        }
        if ((int)($target['user_id'] ?? 0) === $reporterUserId) {
            return ['ok' => false, 'message' => 'You cannot report your own comment.'];
        }
    }

    $dup = $pdo->prepare('SELECT id FROM reports WHERE reporter_user_id = :uid AND target_type = :tt AND target_id = :tid AND status IN ("open", "in-review") LIMIT 1');
    $dup->execute([
        'uid' => $reporterUserId,
        'tt' => $targetType,
        'tid' => $targetId,
    ]);

    if ($dup->fetch(PDO::FETCH_ASSOC)) {
        return ['ok' => false, 'message' => 'You already submitted a report for this content. Please wait for it to be reviewed.'];
    }

    // Set rate limiting: 5 minute cooldown after each report
    $rateLimitUntil = date('Y-m-d H:i:s', time() + (5 * 60));

    $insert = $pdo->prepare('INSERT INTO reports (reporter_user_id, target_type, target_id, reason, category, status, rate_limited_until) VALUES (:uid, :tt, :tid, :reason, :category, "open", :rate_limited_until)');
    $insert->execute([
        'uid' => $reporterUserId,
        'tt' => $targetType,
        'tid' => $targetId,
        'reason' => $reason,
        'category' => $category,
        'rate_limited_until' => $rateLimitUntil,
    ]);

    return ['ok' => true, 'message' => 'Report submitted.'];
}

function writeAuditLog(PDO $pdo, int $adminUserId, string $actionType, string $targetType, ?int $targetId, string $details = '', string $adminNote = ''): void {
    $stmt = $pdo->prepare('INSERT INTO audit_log (admin_user_id, action_type, target_type, target_id, details, admin_note) VALUES (:admin_user_id, :action_type, :target_type, :target_id, :details, :admin_note)');
    $stmt->execute([
        'admin_user_id' => $adminUserId,
        'action_type' => trim($actionType),
        'target_type' => trim($targetType),
        'target_id' => $targetId,
        'details' => trim($details),
        'admin_note' => trim($adminNote),
    ]);
}

function getReportStats(PDO $pdo): array {
    $stmt = $pdo->prepare('SELECT COUNT(*) as open_reports FROM reports WHERE status = "open"');
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt2 = $pdo->prepare('SELECT COUNT(*) as in_review FROM reports WHERE status = "in-review"');
    $stmt2->execute();
    $result2 = $stmt2->fetch(PDO::FETCH_ASSOC);
    
    return [
        'open_reports' => (int)$result['open_reports'],
        'in_review_reports' => (int)$result2['in_review'],
    ];
}

function getReportsByCategory(PDO $pdo, ?string $category = null): array {
    if ($category) {
        $stmt = $pdo->prepare('SELECT * FROM reports WHERE category = :category ORDER BY created_at DESC');
        $stmt->execute(['category' => strtolower(trim($category))]);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM reports ORDER BY created_at DESC');
        $stmt->execute();
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTargetReportCount(PDO $pdo, string $targetType, int $targetId): int {
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM reports WHERE target_type = :tt AND target_id = :tid AND status IN ("open", "in-review")');
    $stmt->execute(['tt' => $targetType, 'tid' => $targetId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)$result['count'];
}

function getUserReports(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare('SELECT * FROM user_reports WHERE user_id = :uid ORDER BY created_at DESC');
    $stmt->execute(['uid' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function markReportViewed(PDO $pdo, int $userId, int $reportId): void {
    $stmt = $pdo->prepare('INSERT INTO user_reports (user_id, report_id, viewed_at) VALUES (:uid, :rid, NOW()) ON DUPLICATE KEY UPDATE viewed_at = NOW()');
    $stmt->execute(['uid' => $userId, 'rid' => $reportId]);
}
