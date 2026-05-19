<?php

require_once __DIR__ . '/image_storage.inc.php';

function ensureCollectionCollaborationTables(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS collection_collaborators (
        id INT AUTO_INCREMENT PRIMARY KEY,
        collection_id INT NOT NULL,
        user_id INT NOT NULL,
        role ENUM('editor', 'viewer') NOT NULL DEFAULT 'viewer',
        invited_by INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_collection_user (collection_id, user_id),
        INDEX idx_collection_collaborators_collection (collection_id),
        INDEX idx_collection_collaborators_user (user_id),
        INDEX idx_collection_collaborators_role (role)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function normalizeCollectionRole(string $role): string {
    $normalized = strtolower(trim($role));
    return in_array($normalized, ['editor', 'viewer'], true) ? $normalized : 'viewer';
}

function getCollectionCollaboratorRole(PDO $pdo, int $collectionId, int $userId): ?string {
    if ($collectionId <= 0 || $userId <= 0) {
        return null;
    }

    ensureCollectionCollaborationTables($pdo);

    $stmt = $pdo->prepare('SELECT role FROM collection_collaborators WHERE collection_id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$collectionId, $userId]);
    $role = $stmt->fetchColumn();

    if ($role === false) {
        return null;
    }

    return normalizeCollectionRole((string) $role);
}

function resolveCollectionAccessRole(PDO $pdo, int $collectionId, int $ownerUserId, int $currentUserId): ?string {
    if ($currentUserId <= 0) {
        return null;
    }

    if ($ownerUserId > 0 && $currentUserId === $ownerUserId) {
        return 'owner';
    }

    return getCollectionCollaboratorRole($pdo, $collectionId, $currentUserId);
}

function canUserEditCollectionWithRole(?string $role): bool {
    return in_array((string) $role, ['owner', 'editor'], true);
}

function canUserViewCollectionWithRole(?string $role): bool {
    return in_array((string) $role, ['owner', 'editor', 'viewer'], true);
}

function inviteCollectionCollaboratorByUsername(PDO $pdo, int $collectionId, int $ownerUserId, string $targetUsername, string $role): array {
    ensureCollectionCollaborationTables($pdo);

    $targetUsername = trim($targetUsername);
    if ($collectionId <= 0 || $ownerUserId <= 0 || $targetUsername === '') {
        return ['ok' => false, 'message' => 'Invalid collaborator invite data.'];
    }

    $role = normalizeCollectionRole($role);

    $targetStmt = $pdo->prepare('SELECT id FROM registration WHERE username = ? LIMIT 1');
    $targetStmt->execute([$targetUsername]);
    $targetUserId = (int) $targetStmt->fetchColumn();

    if ($targetUserId <= 0) {
        return ['ok' => false, 'message' => 'User not found.'];
    }

    if ($targetUserId === $ownerUserId) {
        return ['ok' => false, 'message' => 'Owner already has full access.'];
    }

    $upsert = $pdo->prepare(
        'INSERT INTO collection_collaborators (collection_id, user_id, role, invited_by)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE role = VALUES(role), invited_by = VALUES(invited_by)'
    );
    $upsert->execute([$collectionId, $targetUserId, $role, $ownerUserId]);

    return ['ok' => true, 'message' => 'Collaborator invited.', 'user_id' => $targetUserId, 'role' => $role];
}

function updateCollectionCollaboratorRole(PDO $pdo, int $collectionId, int $targetUserId, string $role): bool {
    ensureCollectionCollaborationTables($pdo);

    if ($collectionId <= 0 || $targetUserId <= 0) {
        return false;
    }

    $role = normalizeCollectionRole($role);
    $stmt = $pdo->prepare('UPDATE collection_collaborators SET role = ? WHERE collection_id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$role, $collectionId, $targetUserId]);

    return $stmt->rowCount() > 0;
}

function removeCollectionCollaborator(PDO $pdo, int $collectionId, int $targetUserId): bool {
    ensureCollectionCollaborationTables($pdo);

    if ($collectionId <= 0 || $targetUserId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare('DELETE FROM collection_collaborators WHERE collection_id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$collectionId, $targetUserId]);

    return $stmt->rowCount() > 0;
}

function getCollectionCollaborators(PDO $pdo, int $collectionId): array {
    ensureCollectionCollaborationTables($pdo);

    if ($collectionId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT cc.user_id,
                cc.role,
                cc.created_at,
                r.username,
                r.img AS user_img
         FROM collection_collaborators cc
         INNER JOIN registration r ON r.id = cc.user_id
         WHERE cc.collection_id = ?
         ORDER BY FIELD(cc.role, "editor", "viewer"), cc.created_at ASC, cc.user_id ASC'
    );
    $stmt->execute([$collectionId]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'user_id' => (int) ($row['user_id'] ?? 0),
            'username' => (string) ($row['username'] ?? 'Unknown'),
            'role' => normalizeCollectionRole((string) ($row['role'] ?? 'viewer')),
            'user_img' => buildFitspirationAvatarUrl($row['user_img'] ?? '', (string) ($row['username'] ?? 'Unknown')),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    return $rows;
}

function getUserCreatableCollections(PDO $pdo, int $userId): array {
    ensureCollectionCollaborationTables($pdo);

    if ($userId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT DISTINCT c.collection_id,
                c.title,
                c.user_id,
                COALESCE(cc.role, "owner") AS access_role
         FROM collections c
         LEFT JOIN collection_collaborators cc
           ON cc.collection_id = c.collection_id AND cc.user_id = :user_id
         WHERE c.user_id = :owner_user_id
            OR (cc.user_id = :collab_user_id AND cc.role = "editor")
         ORDER BY c.collection_id DESC'
    );
    $stmt->execute([
        'user_id' => $userId,
        'owner_user_id' => $userId,
        'collab_user_id' => $userId,
    ]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $role = ((int) ($row['user_id'] ?? 0) === $userId) ? 'owner' : normalizeCollectionRole((string) ($row['access_role'] ?? 'editor'));
        $rows[] = [
            'collection_id' => (int) ($row['collection_id'] ?? 0),
            'title' => (string) ($row['title'] ?? 'Collection'),
            'access_role' => $role,
        ];
    }

    return $rows;
}

function getUserCreatablePublicCollections(PDO $pdo, int $userId): array {
    ensureCollectionCollaborationTables($pdo);

    if ($userId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT DISTINCT c.collection_id,
                c.title,
                c.user_id,
                c.privacy,
                COALESCE(cc.role, "owner") AS access_role
         FROM collections c
         LEFT JOIN collection_collaborators cc
           ON cc.collection_id = c.collection_id AND cc.user_id = :user_id
         WHERE c.privacy = "Public"
           AND (
                c.user_id = :owner_user_id
                OR (cc.user_id = :collab_user_id AND cc.role = "editor")
           )
         ORDER BY c.collection_id DESC'
    );
    $stmt->execute([
        'user_id' => $userId,
        'owner_user_id' => $userId,
        'collab_user_id' => $userId,
    ]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $role = ((int) ($row['user_id'] ?? 0) === $userId) ? 'owner' : normalizeCollectionRole((string) ($row['access_role'] ?? 'editor'));
        $rows[] = [
            'collection_id' => (int) ($row['collection_id'] ?? 0),
            'title' => (string) ($row['title'] ?? 'Collection'),
            'access_role' => $role,
            'privacy' => (string) ($row['privacy'] ?? 'Public'),
        ];
    }

    return $rows;
}
