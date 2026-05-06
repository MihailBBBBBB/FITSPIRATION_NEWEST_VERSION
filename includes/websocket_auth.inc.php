<?php

function getWebSocketUrl(): string {
    $url = getenv('FITSPIRATION_WS_URL');
    if ($url !== false && $url !== '') {
        return $url;
    }

    return 'ws://127.0.0.1:8081';
}

function getWebSocketSecret(): string {
    $secret = getenv('FITSPIRATION_WS_SECRET');
    if ($secret !== false && $secret !== '') {
        return $secret;
    }

    return 'fitspiration-local-websocket-secret-2026';
}

function getWebSocketTokenTtl(): int {
    return 3600;
}

function createWebSocketToken(int $userId, string $sessionId, int $expiresAt): string {
    return hash_hmac('sha256', $userId . '|' . $sessionId . '|' . $expiresAt, getWebSocketSecret());
}

function buildWebSocketConnectionPayload(int $userId, string $sessionId): array {
    $expiresAt = time() + getWebSocketTokenTtl();

    return [
        'userId' => $userId,
        'sessionId' => $sessionId,
        'expiresAt' => $expiresAt,
        'token' => createWebSocketToken($userId, $sessionId, $expiresAt),
        'url' => getWebSocketUrl(),
    ];
}

function isValidWebSocketToken(int $userId, string $sessionId, int $expiresAt, string $token): bool {
    if ($userId <= 0 || $sessionId === '' || $token === '' || $expiresAt < time()) {
        return false;
    }

    $expectedToken = createWebSocketToken($userId, $sessionId, $expiresAt);
    return hash_equals($expectedToken, $token);
}