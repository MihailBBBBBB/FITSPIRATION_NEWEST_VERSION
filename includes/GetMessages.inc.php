<?php
if (!isset($_SESSION['user_id'])) {
    die("Not logged in");
}

require_once "dbh.inc.php";
require_once "messages_repository.inc.php";

ensureMessagePresenceTable($pdo);
$user_id = $_SESSION['user_id'];

try {
    // Get distinct conversation partners
    $conversationsQuery = "
        SELECT DISTINCT
            CASE 
                WHEN sender_id = ? THEN recipient_id 
                ELSE sender_id 
            END as other_user_id
        FROM messages
        WHERE sender_id = ? OR recipient_id = ?
        AND NOT ((sender_id = ? AND deleted_by_sender = TRUE) OR (recipient_id = ? AND deleted_by_recipient = TRUE))
    ";
    
    $stmt = $pdo->prepare($conversationsQuery);
    $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
    $conversation_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $conversations = [];
    
    // For each conversation partner, get their details and last message
    foreach ($conversation_ids as $other_id) {
        $detailQuery = "
            SELECT 
                ? as other_user_id,
                u.username,
                u.img,
                COALESCE(mp.is_online, 0) as is_online,
                mp.last_seen,
                MAX(m.created_at) as last_message_time,
                (SELECT message_text FROM messages 
                 WHERE (sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?)
                 ORDER BY created_at DESC LIMIT 1) as last_message,
                (SELECT COUNT(*) FROM messages 
                 WHERE recipient_id = ? AND sender_id = ? AND is_read = FALSE) as unread_count
            FROM registration u
            LEFT JOIN message_presence mp ON mp.user_id = u.id
            LEFT JOIN messages m ON (m.sender_id = ? OR m.recipient_id = ?) AND (m.sender_id = ? OR m.recipient_id = ?)
            WHERE u.id = ?
            GROUP BY u.id, u.username, u.img, mp.is_online, mp.last_seen
        ";
        
        $detailStmt = $pdo->prepare($detailQuery);
        $detailStmt->execute([
            $other_id,
            $user_id, $other_id, $other_id, $user_id,
            $user_id, $other_id,
            $user_id, $other_id, $user_id, $other_id,
            $other_id
        ]);
        
        $conv = $detailStmt->fetch(PDO::FETCH_ASSOC);
        if ($conv) {
            $conversations[] = $conv;
        }
    }
    
    // Sort by last message time
    usort($conversations, function($a, $b) {
        $timeA = strtotime($a['last_message_time'] ?? 0);
        $timeB = strtotime($b['last_message_time'] ?? 0);
        return $timeB - $timeA;
    });

} catch (PDOException $e) {
    error_log("Messages query failed: " . $e->getMessage());
    $conversations = [];
}
