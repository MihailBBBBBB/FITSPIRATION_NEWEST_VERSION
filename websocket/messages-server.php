<?php

set_time_limit(0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/dbh.inc.php';
require_once __DIR__ . '/../includes/messages_repository.inc.php';
require_once __DIR__ . '/../includes/notifications.inc.php';
require_once __DIR__ . '/../includes/websocket_auth.inc.php';

ensureNotificationsTable($pdo);

$address = '0.0.0.0';
$port = 8081;
$server = stream_socket_server("tcp://{$address}:{$port}", $errorNumber, $errorString);

if ($server === false) {
    fwrite(STDERR, "Unable to start WebSocket server: {$errorString} ({$errorNumber})" . PHP_EOL);
    exit(1);
}

stream_set_blocking($server, false);

$clients = [];
$clientsByUser = [];

function closeClient(array &$clients, array &$clientsByUser, int $clientId, PDO $pdo): void {
    if (!isset($clients[$clientId])) {
        return;
    }

    $client = $clients[$clientId];
    $userId = isset($client['user_id']) ? (int) $client['user_id'] : 0;

    if (isset($client['user_id'], $clientsByUser[$client['user_id']][$clientId])) {
        unset($clientsByUser[$client['user_id']][$clientId]);
        if (empty($clientsByUser[$client['user_id']])) {
            unset($clientsByUser[$client['user_id']]);
        }
    }

    fclose($client['socket']);
    unset($clients[$clientId]);

    if ($userId > 0 && !isset($clientsByUser[$userId])) {
        try {
            setUserPresence($pdo, $userId, false);
            $presence = getUserPresence($pdo, $userId);
            $partnerIds = getConversationPartnerIds($pdo, $userId);

            if (!empty($partnerIds)) {
                broadcastToUsers($clientsByUser, $clients, $partnerIds, [
                    'type' => 'presence_update',
                    'user_id' => $userId,
                    'is_online' => false,
                    'last_seen' => (string) ($presence['last_seen'] ?? ''),
                ]);
            }
        } catch (Throwable $error) {
        }
    }
}

function encodeFrame(string $payload, int $opcode = 0x1): string {
    $finAndOpcode = 0x80 | ($opcode & 0x0F);
    $payloadLength = strlen($payload);

    if ($payloadLength <= 125) {
        return chr($finAndOpcode) . chr($payloadLength) . $payload;
    }

    if ($payloadLength <= 65535) {
        return chr($finAndOpcode) . chr(126) . pack('n', $payloadLength) . $payload;
    }

    return chr($finAndOpcode) . chr(127) . pack('NN', 0, $payloadLength) . $payload;
}

function decodeFrames(string &$buffer): array {
    $messages = [];

    while (strlen($buffer) >= 2) {
        $firstByte = ord($buffer[0]);
        $secondByte = ord($buffer[1]);
        $opcode = $firstByte & 0x0F;
        $isMasked = ($secondByte & 0x80) === 0x80;
        $payloadLength = $secondByte & 0x7F;
        $offset = 2;

        if ($payloadLength === 126) {
            if (strlen($buffer) < 4) {
                break;
            }

            $payloadLength = unpack('n', substr($buffer, 2, 2))[1];
            $offset = 4;
        } elseif ($payloadLength === 127) {
            if (strlen($buffer) < 10) {
                break;
            }

            $lengthData = unpack('N2', substr($buffer, 2, 8));
            $payloadLength = ($lengthData[1] << 32) + $lengthData[2];
            $offset = 10;
        }

        $maskLength = $isMasked ? 4 : 0;
        $frameLength = $offset + $maskLength + $payloadLength;
        if (strlen($buffer) < $frameLength) {
            break;
        }

        $mask = $isMasked ? substr($buffer, $offset, 4) : '';
        $offset += $maskLength;
        $payload = substr($buffer, $offset, $payloadLength);
        $buffer = substr($buffer, $frameLength);

        if ($isMasked) {
            $decoded = '';
            for ($index = 0; $index < $payloadLength; $index++) {
                $decoded .= $payload[$index] ^ $mask[$index % 4];
            }
            $payload = $decoded;
        }

        $messages[] = [
            'opcode' => $opcode,
            'payload' => $payload,
        ];
    }

    return $messages;
}

function sendJson($socket, array $payload): void {
    @fwrite($socket, encodeFrame(json_encode($payload, JSON_UNESCAPED_SLASHES)));
}

function sendJsonToClient(array $clients, int $clientId, array $payload): void {
    if (!isset($clients[$clientId])) {
        return;
    }

    sendJson($clients[$clientId]['socket'], $payload);
}

function broadcastToUsers(array $clientsByUser, array $clients, array $userIds, array $payload): void {
    foreach (array_unique($userIds) as $userId) {
        if (!isset($clientsByUser[$userId])) {
            continue;
        }

        foreach (array_keys($clientsByUser[$userId]) as $clientId) {
            sendJsonToClient($clients, $clientId, $payload);
        }
    }
}

function performHandshake($socket, string $request): array {
    $lines = preg_split("/\r\n/", $request);
    $requestLine = $lines[0] ?? '';
    if (!preg_match('#GET\s+([^\s]+)#', $requestLine, $matches)) {
        return ['ok' => false];
    }

    $headers = [];
    foreach ($lines as $line) {
        if (strpos($line, ':') === false) {
            continue;
        }

        [$name, $value] = explode(':', $line, 2);
        $headers[strtolower(trim($name))] = trim($value);
    }

    if (empty($headers['sec-websocket-key'])) {
        return ['ok' => false];
    }

    $path = $matches[1];
    $queryString = parse_url($path, PHP_URL_QUERY) ?: '';
    parse_str($queryString, $query);

    $userId = (int) ($query['user_id'] ?? 0);
    $sessionId = (string) ($query['session_id'] ?? '');
    $expiresAt = (int) ($query['expires_at'] ?? 0);
    $token = (string) ($query['token'] ?? '');

    if (!isValidWebSocketToken($userId, $sessionId, $expiresAt, $token)) {
        return ['ok' => false];
    }

    $acceptKey = base64_encode(pack('H*', sha1($headers['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
    $response = "HTTP/1.1 101 Switching Protocols\r\n"
        . "Upgrade: websocket\r\n"
        . "Connection: Upgrade\r\n"
        . "Sec-WebSocket-Accept: {$acceptKey}\r\n\r\n";

    fwrite($socket, $response);

    return [
        'ok' => true,
        'user_id' => $userId,
    ];
}

function handleClientPayload(array $payload, int $clientId, array &$clients, array &$clientsByUser, PDO $pdo): void {
    if (!isset($clients[$clientId]['user_id'])) {
        return;
    }

    $userId = (int) $clients[$clientId]['user_id'];
    $type = (string) ($payload['type'] ?? '');

    try {
        if ($type === 'send_message') {
            $recipientId = (int) ($payload['recipient_id'] ?? 0);
            $messageText = trim((string) ($payload['message_text'] ?? ''));

            if ($recipientId <= 0 || $messageText === '') {
                sendJsonToClient($clients, $clientId, ['type' => 'error', 'message' => 'Recipient and message are required']);
                return;
            }

            if ($recipientId === $userId) {
                sendJsonToClient($clients, $clientId, ['type' => 'error', 'message' => 'You cannot message yourself']);
                return;
            }

            if (strlen($messageText) > 5000) {
                sendJsonToClient($clients, $clientId, ['type' => 'error', 'message' => 'Message too long (max 5000 characters)']);
                return;
            }

            $message = sendMessageRecord($pdo, $userId, $recipientId, $messageText);
            addNotification($pdo, $recipientId, $userId, 'message', null);
            broadcastToUsers($clientsByUser, $clients, [$userId, $recipientId], [
                'type' => 'message_created',
                'message' => $message,
            ]);
            return;
        }

        if ($type === 'delete_message') {
            $messageId = (int) ($payload['message_id'] ?? 0);
            if ($messageId <= 0) {
                sendJsonToClient($clients, $clientId, ['type' => 'error', 'message' => 'Message not found']);
                return;
            }

            $result = deleteMessageForUser($pdo, $messageId, $userId);
            broadcastToUsers($clientsByUser, $clients, [$result['sender_id'], $result['recipient_id']], [
                'type' => 'message_deleted',
                'message_id' => $result['message_id'],
                'sender_id' => $result['sender_id'],
                'recipient_id' => $result['recipient_id'],
                'placeholder_text' => (string) ($result['placeholder_text'] ?? 'Message deleted'),
            ]);
            return;
        }

        if ($type === 'mark_conversation_read') {
            $otherUserId = (int) ($payload['other_user_id'] ?? 0);
            if ($otherUserId > 0) {
                $readMessageIds = markConversationAsReadAndGetIds($pdo, $userId, $otherUserId);
                $clearNotifications = $pdo->prepare(
                    'UPDATE notifications
                     SET is_read = TRUE
                     WHERE user_id = ?
                       AND actor_user_id = ?
                       AND type = ?
                       AND is_read = FALSE'
                );
                $clearNotifications->execute([$userId, $otherUserId, 'message']);

                if (!empty($readMessageIds)) {
                    broadcastToUsers($clientsByUser, $clients, [$otherUserId], [
                        'type' => 'conversation_read',
                        'reader_user_id' => $userId,
                        'partner_user_id' => $otherUserId,
                        'message_ids' => $readMessageIds,
                    ]);
                }
            }
            return;
        }

        if ($type === 'typing_start' || $type === 'typing_stop') {
            $recipientId = (int) ($payload['recipient_id'] ?? 0);
            if ($recipientId <= 0 || $recipientId === $userId) {
                return;
            }

            broadcastToUsers($clientsByUser, $clients, [$recipientId], [
                'type' => 'typing_indicator',
                'from_user_id' => $userId,
                'recipient_id' => $recipientId,
                'is_typing' => $type === 'typing_start',
            ]);
            return;
        }
    } catch (Throwable $error) {
        sendJsonToClient($clients, $clientId, ['type' => 'error', 'message' => $error->getMessage()]);
    }
}

echo '[' . date('Y-m-d H:i:s') . "] Messages WebSocket server listening on ws://127.0.0.1:{$port}" . PHP_EOL;

while (true) {
    $readSockets = [$server];
    foreach ($clients as $client) {
        $readSockets[] = $client['socket'];
    }

    $writeSockets = null;
    $exceptSockets = null;

    if (@stream_select($readSockets, $writeSockets, $exceptSockets, 1) === false) {
        continue;
    }

    foreach ($readSockets as $socket) {
        if ($socket === $server) {
            $clientSocket = @stream_socket_accept($server, 0);
            if ($clientSocket === false) {
                continue;
            }

            stream_set_blocking($clientSocket, false);
            $clientId = (int) $clientSocket;
            $clients[$clientId] = [
                'socket' => $clientSocket,
                'handshake_complete' => false,
                'buffer' => '',
            ];
            continue;
        }

        $clientId = (int) $socket;
        $data = @fread($socket, 8192);

        if ($data === '' || $data === false) {
            if (feof($socket)) {
                closeClient($clients, $clientsByUser, $clientId, $pdo);
            }
            continue;
        }

        if (!isset($clients[$clientId])) {
            continue;
        }

        $clients[$clientId]['buffer'] .= $data;

        if (!$clients[$clientId]['handshake_complete']) {
            if (strpos($clients[$clientId]['buffer'], "\r\n\r\n") === false) {
                continue;
            }

            $handshake = performHandshake($socket, $clients[$clientId]['buffer']);
            $clients[$clientId]['buffer'] = '';

            if (!$handshake['ok']) {
                closeClient($clients, $clientsByUser, $clientId, $pdo);
                continue;
            }

            $clients[$clientId]['handshake_complete'] = true;
            $clients[$clientId]['user_id'] = $handshake['user_id'];
            $clientsByUser[$handshake['user_id']][$clientId] = true;

            try {
                $connectedSockets = isset($clientsByUser[$handshake['user_id']]) ? count($clientsByUser[$handshake['user_id']]) : 0;
                if ($connectedSockets === 1) {
                    setUserPresence($pdo, (int) $handshake['user_id'], true);

                    $partnerIds = getConversationPartnerIds($pdo, (int) $handshake['user_id']);
                    if (!empty($partnerIds)) {
                        broadcastToUsers($clientsByUser, $clients, $partnerIds, [
                            'type' => 'presence_update',
                            'user_id' => (int) $handshake['user_id'],
                            'is_online' => true,
                            'last_seen' => '',
                        ]);
                    }
                }
            } catch (Throwable $error) {
            }
            continue;
        }

        $frames = decodeFrames($clients[$clientId]['buffer']);
        foreach ($frames as $frame) {
            if ($frame['opcode'] === 0x8) {
                closeClient($clients, $clientsByUser, $clientId, $pdo);
                continue 2;
            }

            if ($frame['opcode'] === 0x9) {
                @fwrite($socket, encodeFrame($frame['payload'], 0xA));
                continue;
            }

            if ($frame['opcode'] !== 0x1) {
                continue;
            }

            $payload = json_decode($frame['payload'], true);
            if (!is_array($payload)) {
                sendJsonToClient($clients, $clientId, ['type' => 'error', 'message' => 'Invalid message payload']);
                continue;
            }

            handleClientPayload($payload, $clientId, $clients, $clientsByUser, $pdo);
        }
    }
}