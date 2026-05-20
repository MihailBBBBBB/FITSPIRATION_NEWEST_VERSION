<?php
if (!isset($_SESSION['user_id'])) {
    die("Not logged in");
}

require_once __DIR__ . "/dbh.inc.php";
require_once __DIR__ . "/messages_repository.inc.php";

ensureMessagePresenceTable($pdo);
$user_id = $_SESSION['user_id'];

try {
    $conversations = getConversationSummaries($pdo, (int) $user_id);

} catch (PDOException $e) {
    error_log("Messages query failed: " . $e->getMessage());
    $conversations = [];
}
