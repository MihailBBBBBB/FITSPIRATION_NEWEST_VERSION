<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/dbh.inc.php';
require_once __DIR__ . '/csrf.inc.php';
require_once __DIR__ . '/outfits_schema.inc.php';

if (!function_exists('isAjaxRequest')) {
    function isAjaxRequest(): bool {
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        return $requestedWith === 'xmlhttprequest';
    }
}

if (!function_exists('sendJsonResponse')) {
    function sendJsonResponse(array $payload, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../HTML/Login.php?error=notloggedin');
    exit();
}

$userId = (int) $_SESSION['user_id'];
$status = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
$autoOpenPreviewEntryId = isset($_GET['open_entry']) ? (int) $_GET['open_entry'] : 0;
$challengeSort = isset($_GET['sort']) ? trim((string) $_GET['sort']) : 'most_voted';
$allowedChallengeSorts = ['most_voted', 'newest', 'followed'];
if (!in_array($challengeSort, $allowedChallengeSorts, true)) {
    $challengeSort = 'most_voted';
}

$challengeSortLabels = [
    'most_voted' => 'Most voted',
    'newest' => 'Newest',
    'followed' => 'Followed',
];
$challengeSortLabel = $challengeSortLabels[$challengeSort] ?? 'Most voted';

$challengeNotice = '';
$challengeNoticeType = 'ok';
$previousWeekWinner = null;
$entryCommentsMap = [];
$challengeBadgeStats = getUserChallengeBadgeStats($pdo, $userId);

try {
    ensureOutfitsTable($pdo);
    $activeChallenge = ensureCurrentWeeklyChallenge($pdo);
    $challengeId = (int) ($activeChallenge['id'] ?? 0);
    $challengeBadgeStats = getUserChallengeBadgeStats($pdo, $userId);

    if ($challengeId <= 0) {
        throw new RuntimeException('Challenge could not be initialized.');
    }

    $previousChallengeStmt = $pdo->prepare(
        'SELECT id, week_key, theme, description, starts_at, ends_at
         FROM outfit_challenges
         WHERE starts_at < :active_starts_at
         ORDER BY starts_at DESC, id DESC
         LIMIT 1'
    );
    $previousChallengeStmt->execute([
        'active_starts_at' => (string) ($activeChallenge['starts_at'] ?? date('Y-m-d')),
    ]);
    $previousChallenge = $previousChallengeStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($previousChallenge) {
        $previousWinnerStmt = $pdo->prepare(
            'SELECT e.id,
                    e.challenge_id,
                    e.outfit_id,
                    e.user_id,
                    e.caption,
                    e.updated_at,
                    o.name AS outfit_name,
                    o.img AS outfit_img,
                    r.username,
                    r.img AS user_img,
                    COALESCE((SELECT COUNT(*) FROM outfit_challenge_votes v WHERE v.entry_id = e.id), 0) AS vote_count,
                    COALESCE((
                        SELECT COUNT(*)
                        FROM pins p
                        INNER JOIN likes l ON l.pin_id = p.id
                        WHERE p.link = CONCAT("outfit://", e.outfit_id)
                    ), 0) AS like_count
             FROM outfit_challenge_entries e
             INNER JOIN outfits o ON o.id = e.outfit_id
             INNER JOIN registration r ON r.id = e.user_id
             WHERE e.challenge_id = :challenge_id
             ORDER BY vote_count DESC, like_count DESC, e.updated_at DESC, e.id DESC
             LIMIT 1'
        );
        $previousWinnerStmt->execute([
            'challenge_id' => (int) $previousChallenge['id'],
        ]);
        $winnerRow = $previousWinnerStmt->fetch(PDO::FETCH_ASSOC);

        if ($winnerRow) {
            $previousWeekWinner = [
                'challenge' => [
                    'id' => (int) $previousChallenge['id'],
                    'week_key' => (string) ($previousChallenge['week_key'] ?? ''),
                    'theme' => (string) ($previousChallenge['theme'] ?? 'Previous Week'),
                    'starts_at' => (string) ($previousChallenge['starts_at'] ?? ''),
                    'ends_at' => (string) ($previousChallenge['ends_at'] ?? ''),
                ],
                'entry' => [
                    'id' => (int) ($winnerRow['id'] ?? 0),
                    'outfit_id' => (int) ($winnerRow['outfit_id'] ?? 0),
                    'user_id' => (int) ($winnerRow['user_id'] ?? 0),
                    'username' => (string) ($winnerRow['username'] ?? 'Unknown'),
                    'user_img' => (string) ($winnerRow['user_img'] ?? ''),
                    'outfit_name' => (string) ($winnerRow['outfit_name'] ?? 'Winning Outfit'),
                    'outfit_img' => (string) ($winnerRow['outfit_img'] ?? ''),
                    'caption' => (string) ($winnerRow['caption'] ?? ''),
                    'vote_count' => (int) ($winnerRow['vote_count'] ?? 0),
                    'like_count' => (int) ($winnerRow['like_count'] ?? 0),
                ],
            ];
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireValidCsrfToken();
        $isAjax = isAjaxRequest();

        if (isset($_POST['add_challenge_comment'])) {
            $entryId = isset($_POST['entry_id']) ? (int) $_POST['entry_id'] : 0;
            $commentText = trim((string) ($_POST['challenge_comment'] ?? ''));

            if ($entryId <= 0) {
                if ($isAjax) {
                    sendJsonResponse(['ok' => false, 'status' => 'comment_failed', 'message' => 'Invalid entry selected.'], 400);
                }
                header('Location: ../HTML/OutfitChallenge.php?status=comment_failed');
                exit();
            }

            if ($commentText === '') {
                if ($isAjax) {
                    sendJsonResponse(['ok' => false, 'status' => 'empty_comment', 'message' => 'Comment cannot be empty.', 'entry_id' => $entryId], 400);
                }
                header('Location: ../HTML/OutfitChallenge.php?status=empty_comment&open_entry=' . $entryId);
                exit();
            }

            $commentText = mb_substr($commentText, 0, 1000);

            $entryLookupStmt = $pdo->prepare('SELECT id FROM outfit_challenge_entries WHERE id = ? AND challenge_id = ? LIMIT 1');
            $entryLookupStmt->execute([$entryId, $challengeId]);
            $entryLookup = $entryLookupStmt->fetch(PDO::FETCH_ASSOC);

            if (!$entryLookup) {
                if ($isAjax) {
                    sendJsonResponse(['ok' => false, 'status' => 'comment_failed', 'message' => 'Entry not found for this challenge.', 'entry_id' => $entryId], 404);
                }
                header('Location: ../HTML/OutfitChallenge.php?status=comment_failed&open_entry=' . $entryId);
                exit();
            }

            $insertCommentStmt = $pdo->prepare('INSERT INTO outfit_challenge_comments (challenge_id, entry_id, user_id, comment, created_at) VALUES (?, ?, ?, ?, NOW())');
            $insertCommentStmt->execute([$challengeId, $entryId, $userId, $commentText]);
            $newCommentId = (int) $pdo->lastInsertId();

            if ($isAjax) {
                $usernameStmt = $pdo->prepare('SELECT username, img FROM registration WHERE id = ? LIMIT 1');
                $usernameStmt->execute([$userId]);
                $currentUserRow = $usernameStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $currentUsername = (string) ($currentUserRow['username'] ?? 'You');
                $currentUserImg = !empty($currentUserRow['img']) ? '../images/' . (string) $currentUserRow['img'] : '../images/no_image.jpg';

                sendJsonResponse([
                    'ok' => true,
                    'status' => 'comment_added',
                    'entry_id' => $entryId,
                    'comment' => [
                        'id' => $newCommentId,
                        'source' => 'challenge',
                        'user_id' => $userId,
                        'username' => $currentUsername,
                        'user_img' => $currentUserImg,
                        'comment' => $commentText,
                        'can_delete' => true,
                        'created_at' => date('Y-m-d H:i:s'),
                    ],
                ]);
            }

            header('Location: ../HTML/OutfitChallenge.php?status=comment_added&open_entry=' . $entryId);
            exit();
        }

        if (isset($_POST['delete_challenge_comment'])) {
            $entryId = isset($_POST['entry_id']) ? (int) $_POST['entry_id'] : 0;
            $commentId = isset($_POST['comment_id']) ? (int) $_POST['comment_id'] : 0;
            $commentSource = isset($_POST['comment_source']) ? trim((string) $_POST['comment_source']) : 'challenge';

            if ($entryId <= 0 || $commentId <= 0) {
                if ($isAjax) {
                    sendJsonResponse(['ok' => false, 'status' => 'comment_delete_failed', 'message' => 'Invalid comment delete request.'], 400);
                }
                header('Location: ../HTML/OutfitChallenge.php?status=comment_delete_failed');
                exit();
            }

            $entryLookupStmt = $pdo->prepare('SELECT id, outfit_id FROM outfit_challenge_entries WHERE id = ? AND challenge_id = ? LIMIT 1');
            $entryLookupStmt->execute([$entryId, $challengeId]);
            $entryLookup = $entryLookupStmt->fetch(PDO::FETCH_ASSOC);

            if (!$entryLookup) {
                if ($isAjax) {
                    sendJsonResponse(['ok' => false, 'status' => 'comment_delete_failed', 'message' => 'Entry not found for this challenge.', 'entry_id' => $entryId], 404);
                }
                header('Location: ../HTML/OutfitChallenge.php?status=comment_delete_failed&open_entry=' . $entryId);
                exit();
            }

            $deleted = false;
            if ($commentSource === 'pin') {
                $pinLookupStmt = $pdo->prepare('SELECT id FROM pins WHERE link = CONCAT("outfit://", ?) LIMIT 1');
                $pinLookupStmt->execute([(int) ($entryLookup['outfit_id'] ?? 0)]);
                $pinId = (int) $pinLookupStmt->fetchColumn();

                if ($pinId > 0) {
                    $deleteStmt = $pdo->prepare('DELETE FROM comments WHERE id = ? AND pin_id = ? AND user_id = ? LIMIT 1');
                    $deleteStmt->execute([$commentId, $pinId, $userId]);
                    $deleted = $deleteStmt->rowCount() > 0;
                }
            } else {
                $deleteStmt = $pdo->prepare('DELETE FROM outfit_challenge_comments WHERE id = ? AND challenge_id = ? AND entry_id = ? AND user_id = ? LIMIT 1');
                $deleteStmt->execute([$commentId, $challengeId, $entryId, $userId]);
                $deleted = $deleteStmt->rowCount() > 0;
            }

            if (!$deleted) {
                if ($isAjax) {
                    sendJsonResponse(['ok' => false, 'status' => 'comment_delete_forbidden', 'message' => 'Unable to delete this comment.', 'entry_id' => $entryId], 403);
                }
                header('Location: ../HTML/OutfitChallenge.php?status=comment_delete_forbidden&open_entry=' . $entryId);
                exit();
            }

            if ($isAjax) {
                sendJsonResponse([
                    'ok' => true,
                    'status' => 'comment_deleted',
                    'entry_id' => $entryId,
                    'comment_id' => $commentId,
                    'comment_source' => $commentSource,
                ]);
            }

            header('Location: ../HTML/OutfitChallenge.php?status=comment_deleted&open_entry=' . $entryId);
            exit();
        }

        if (isset($_POST['delete_challenge_participation'])) {
            $myEntryForDeleteStmt = $pdo->prepare('SELECT id FROM outfit_challenge_entries WHERE challenge_id = ? AND user_id = ? LIMIT 1');
            $myEntryForDeleteStmt->execute([$challengeId, $userId]);
            $myEntryIdForDelete = (int) $myEntryForDeleteStmt->fetchColumn();

            if ($myEntryIdForDelete <= 0) {
                header('Location: ../HTML/OutfitChallenge.php?status=no_entry_to_delete');
                exit();
            }

            $pdo->beginTransaction();
            try {
                $deleteVotesStmt = $pdo->prepare('DELETE FROM outfit_challenge_votes WHERE entry_id = ?');
                $deleteVotesStmt->execute([$myEntryIdForDelete]);

                $deleteEntryStmt = $pdo->prepare('DELETE FROM outfit_challenge_entries WHERE id = ? AND challenge_id = ? AND user_id = ? LIMIT 1');
                $deleteEntryStmt->execute([$myEntryIdForDelete, $challengeId, $userId]);

                $pdo->commit();
                header('Location: ../HTML/OutfitChallenge.php?status=entry_deleted');
                exit();
            } catch (Throwable $deleteError) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('OutfitChallenge delete participation failed: ' . $deleteError->getMessage());
                header('Location: ../HTML/OutfitChallenge.php?status=delete_failed');
                exit();
            }
        }

        if (isset($_POST['submit_outfit_to_challenge'])) {
            $outfitId = isset($_POST['outfit_id']) ? (int) $_POST['outfit_id'] : 0;
            $caption = trim((string) ($_POST['entry_caption'] ?? ''));
            $caption = mb_substr($caption, 0, 255);

            if ($outfitId <= 0) {
                header('Location: ../HTML/OutfitChallenge.php?status=missing_outfit');
                exit();
            }

            $ownOutfitStmt = $pdo->prepare('SELECT id FROM outfits WHERE id = ? AND user_id = ? LIMIT 1');
            $ownOutfitStmt->execute([$outfitId, $userId]);
            $ownOutfit = $ownOutfitStmt->fetch(PDO::FETCH_ASSOC);

            if (!$ownOutfit) {
                header('Location: ../HTML/OutfitChallenge.php?status=invalid_outfit');
                exit();
            }

            $existingEntryStmt = $pdo->prepare('SELECT id FROM outfit_challenge_entries WHERE challenge_id = ? AND user_id = ? LIMIT 1');
            $existingEntryStmt->execute([$challengeId, $userId]);
            $existingEntryId = (int) $existingEntryStmt->fetchColumn();

            if ($existingEntryId > 0) {
                $updateEntryStmt = $pdo->prepare('UPDATE outfit_challenge_entries SET outfit_id = ?, caption = ? WHERE id = ? AND challenge_id = ? AND user_id = ?');
                $updateEntryStmt->execute([$outfitId, $caption, $existingEntryId, $challengeId, $userId]);
                header('Location: ../HTML/OutfitChallenge.php?status=entry_updated');
                exit();
            }

            $insertEntryStmt = $pdo->prepare('INSERT INTO outfit_challenge_entries (challenge_id, outfit_id, user_id, caption) VALUES (?, ?, ?, ?)');
            $insertEntryStmt->execute([$challengeId, $outfitId, $userId, $caption]);
            header('Location: ../HTML/OutfitChallenge.php?status=entry_submitted');
            exit();
        }

        if (isset($_POST['toggle_challenge_vote'])) {
            $entryId = isset($_POST['entry_id']) ? (int) $_POST['entry_id'] : 0;
            $returnModal = isset($_POST['return_modal']) && (string) $_POST['return_modal'] === '1';
            $openEntrySuffix = ($returnModal && $entryId > 0) ? ('&open_entry=' . $entryId) : '';

            if ($entryId <= 0) {
                if ($isAjax) {
                    sendJsonResponse(['ok' => false, 'status' => 'invalid_vote', 'message' => 'Invalid vote target.'], 400);
                }
                header('Location: ../HTML/OutfitChallenge.php?status=invalid_vote');
                exit();
            }

            $entryStmt = $pdo->prepare('SELECT id, user_id FROM outfit_challenge_entries WHERE id = ? AND challenge_id = ? LIMIT 1');
            $entryStmt->execute([$entryId, $challengeId]);
            $entryRow = $entryStmt->fetch(PDO::FETCH_ASSOC);

            if (!$entryRow) {
                if ($isAjax) {
                    sendJsonResponse(['ok' => false, 'status' => 'invalid_vote', 'message' => 'Challenge entry not found.', 'entry_id' => $entryId], 404);
                }
                header('Location: ../HTML/OutfitChallenge.php?status=invalid_vote' . $openEntrySuffix);
                exit();
            }

            if ((int) $entryRow['user_id'] === $userId) {
                if ($isAjax) {
                    sendJsonResponse(['ok' => false, 'status' => 'self_vote_blocked', 'message' => 'You cannot vote for your own entry.', 'entry_id' => $entryId], 400);
                }
                header('Location: ../HTML/OutfitChallenge.php?status=self_vote_blocked' . $openEntrySuffix);
                exit();
            }

            $hasVoteStmt = $pdo->prepare('SELECT id FROM outfit_challenge_votes WHERE entry_id = ? AND voter_id = ? LIMIT 1');
            $hasVoteStmt->execute([$entryId, $userId]);
            $existingVoteId = (int) $hasVoteStmt->fetchColumn();

            $didVote = false;
            $statusValue = 'voted';

            if ($existingVoteId > 0) {
                $deleteVoteStmt = $pdo->prepare('DELETE FROM outfit_challenge_votes WHERE id = ? LIMIT 1');
                $deleteVoteStmt->execute([$existingVoteId]);
                $didVote = false;
                $statusValue = 'vote_removed';

                if ($isAjax) {
                    $voteCountStmt = $pdo->prepare('SELECT COUNT(*) FROM outfit_challenge_votes WHERE entry_id = ?');
                    $voteCountStmt->execute([$entryId]);
                    sendJsonResponse([
                        'ok' => true,
                        'status' => $statusValue,
                        'entry_id' => $entryId,
                        'user_voted' => $didVote,
                        'vote_count' => (int) $voteCountStmt->fetchColumn(),
                    ]);
                }

                header('Location: ../HTML/OutfitChallenge.php?status=vote_removed' . $openEntrySuffix);
                exit();
            }

            $insertVoteStmt = $pdo->prepare('INSERT INTO outfit_challenge_votes (challenge_id, entry_id, voter_id) VALUES (?, ?, ?)');
            $insertVoteStmt->execute([$challengeId, $entryId, $userId]);
            $didVote = true;
            $statusValue = 'voted';

            if ($isAjax) {
                $voteCountStmt = $pdo->prepare('SELECT COUNT(*) FROM outfit_challenge_votes WHERE entry_id = ?');
                $voteCountStmt->execute([$entryId]);
                sendJsonResponse([
                    'ok' => true,
                    'status' => $statusValue,
                    'entry_id' => $entryId,
                    'user_voted' => $didVote,
                    'vote_count' => (int) $voteCountStmt->fetchColumn(),
                ]);
            }

            header('Location: ../HTML/OutfitChallenge.php?status=voted' . $openEntrySuffix);
            exit();
        }
    }

    switch ($status) {
        case 'entry_submitted':
            $challengeNotice = 'Outfit submitted to this week\'s challenge.';
            break;
        case 'entry_updated':
            $challengeNotice = 'Your challenge submission has been updated.';
            break;
        case 'voted':
            $challengeNotice = '';
            break;
        case 'vote_removed':
            $challengeNotice = '';
            break;
        case 'self_vote_blocked':
            $challengeNotice = 'You cannot vote for your own challenge entry.';
            $challengeNoticeType = 'error';
            break;
        case 'missing_outfit':
            $challengeNotice = 'Choose an outfit before submitting.';
            $challengeNoticeType = 'error';
            break;
        case 'invalid_outfit':
            $challengeNotice = 'That outfit is not available.';
            $challengeNoticeType = 'error';
            break;
        case 'invalid_vote':
            $challengeNotice = 'Could not process vote for that entry.';
            $challengeNoticeType = 'error';
            break;
        case 'entry_deleted':
            $challengeNotice = 'Your participation has been removed from this week\'s challenge.';
            break;
        case 'no_entry_to_delete':
            $challengeNotice = 'You do not have an active participation to delete.';
            $challengeNoticeType = 'error';
            break;
        case 'delete_failed':
            $challengeNotice = 'Could not delete participation. Please try again.';
            $challengeNoticeType = 'error';
            break;
        case 'comment_added':
            $challengeNotice = '';
            break;
        case 'comment_deleted':
            $challengeNotice = '';
            break;
        case 'empty_comment':
            $challengeNotice = 'Comment cannot be empty.';
            $challengeNoticeType = 'error';
            break;
        case 'comment_unavailable':
            $challengeNotice = 'Comments are unavailable for this entry.';
            $challengeNoticeType = 'error';
            break;
        case 'comment_failed':
            $challengeNotice = 'Could not post comment. Please try again.';
            $challengeNoticeType = 'error';
            break;
        case 'comment_delete_forbidden':
            $challengeNotice = 'You can only delete your own comments.';
            $challengeNoticeType = 'error';
            break;
        case 'comment_delete_failed':
            $challengeNotice = 'Could not delete comment. Please try again.';
            $challengeNoticeType = 'error';
            break;
    }

    $myOutfitsStmt = $pdo->prepare('SELECT id, name, img, COALESCE(updated_at, created_at) AS activity_at FROM outfits WHERE user_id = ? ORDER BY COALESCE(updated_at, created_at) DESC, id DESC LIMIT 100');
    $myOutfitsStmt->execute([$userId]);
    $myOutfits = $myOutfitsStmt->fetchAll(PDO::FETCH_ASSOC);

    $myEntryStmt = $pdo->prepare('SELECT id, outfit_id, caption FROM outfit_challenge_entries WHERE challenge_id = ? AND user_id = ? LIMIT 1');
    $myEntryStmt->execute([$challengeId, $userId]);
    $myEntry = $myEntryStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $entriesOrderBy = 'vote_count DESC, like_count DESC, e.updated_at DESC, e.id DESC';
    $entriesExtraWhere = '';

    if ($challengeSort === 'newest') {
        $entriesOrderBy = 'e.updated_at DESC, e.id DESC';
    } elseif ($challengeSort === 'followed') {
        $entriesExtraWhere = ' AND e.user_id IN (SELECT following_id FROM follows WHERE follower_id = :viewer_user_id_followed)';
        $entriesOrderBy = 'vote_count DESC, like_count DESC, e.updated_at DESC, e.id DESC';
    }

    $entriesSql =
        'SELECT e.id,
                e.challenge_id,
                e.outfit_id,
                e.user_id,
                e.caption,
                e.created_at,
                e.updated_at,
                o.name AS outfit_name,
                o.img AS outfit_img,
                r.username,
                r.img AS user_img,
                COALESCE((SELECT COUNT(*) FROM outfit_challenge_votes v WHERE v.entry_id = e.id), 0) AS vote_count,
                COALESCE((
                    SELECT COUNT(*)
                    FROM pins p
                    INNER JOIN likes l ON l.pin_id = p.id
                    WHERE p.link = CONCAT("outfit://", e.outfit_id)
                ), 0) AS like_count,
                EXISTS(
                    SELECT 1
                    FROM outfit_challenge_votes v2
                    WHERE v2.entry_id = e.id AND v2.voter_id = :viewer_user_id
                ) AS user_voted
         FROM outfit_challenge_entries e
         INNER JOIN outfits o ON o.id = e.outfit_id
         INNER JOIN registration r ON r.id = e.user_id
         WHERE e.challenge_id = :challenge_id' .
         $entriesExtraWhere .
         ' ORDER BY ' . $entriesOrderBy;

    $entriesStmt = $pdo->prepare($entriesSql);

    $entriesParams = [
        'viewer_user_id' => $userId,
        'challenge_id' => $challengeId,
    ];

    if ($challengeSort === 'followed') {
        $entriesParams['viewer_user_id_followed'] = $userId;
    }

    $entriesStmt->execute($entriesParams);
    $challengeEntries = $entriesStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($challengeEntries)) {
        $entryIds = array_map(static fn(array $entry): int => (int) ($entry['id'] ?? 0), $challengeEntries);
        $entryIds = array_values(array_filter($entryIds, static fn(int $id): bool => $id > 0));

        if (!empty($entryIds)) {
            $placeholders = implode(',', array_fill(0, count($entryIds), '?'));
            $commentMapSql =
              'SELECT merged.entry_id,
                   merged.comment_id,
                   merged.comment_source,
                   merged.user_id,
                   merged.comment,
                   merged.created_at,
                   COALESCE(r.username, "User") AS username,
                   COALESCE(r.img, "") AS user_img
               FROM (
                  SELECT cc.entry_id,
                      cc.id AS comment_id,
                      "challenge" AS comment_source,
                      cc.user_id,
                      cc.comment,
                      cc.created_at
                  FROM outfit_challenge_comments cc
                  WHERE cc.entry_id IN (' . $placeholders . ')

                  UNION ALL

                  SELECT e.id AS entry_id,
                      c.id AS comment_id,
                      "pin" AS comment_source,
                      c.user_id,
                      c.comment,
                      c.created_at
                  FROM outfit_challenge_entries e
                  INNER JOIN pins p ON p.link = CONCAT("outfit://", e.outfit_id)
                  INNER JOIN comments c ON c.pin_id = p.id
                  WHERE e.id IN (' . $placeholders . ')
               ) AS merged
               LEFT JOIN registration r ON r.id = merged.user_id
               ORDER BY merged.created_at DESC';

            $commentMapStmt = $pdo->prepare($commentMapSql);
             $commentMapStmt->execute(array_merge($entryIds, $entryIds));

            foreach ($commentMapStmt->fetchAll(PDO::FETCH_ASSOC) as $commentRow) {
                $entryKey = (int) ($commentRow['entry_id'] ?? 0);
                if ($entryKey <= 0) {
                    continue;
                }

                if (!isset($entryCommentsMap[$entryKey])) {
                    $entryCommentsMap[$entryKey] = [];
                }

                $entryCommentsMap[$entryKey][] = [
                    'id' => (int) ($commentRow['comment_id'] ?? 0),
                    'source' => (string) ($commentRow['comment_source'] ?? 'challenge'),
                    'user_id' => (int) ($commentRow['user_id'] ?? 0),
                    'username' => (string) ($commentRow['username'] ?? 'User'),
                    'user_img' => !empty($commentRow['user_img']) ? '../images/' . (string) $commentRow['user_img'] : '../images/no_image.jpg',
                    'comment' => (string) ($commentRow['comment'] ?? ''),
                    'can_delete' => ((int) ($commentRow['user_id'] ?? 0) === $userId),
                    'created_at' => (string) ($commentRow['created_at'] ?? ''),
                ];
            }
        }
    }
} catch (Throwable $error) {
    error_log('OutfitChallenge error: ' . $error->getMessage());
    $activeChallenge = [
        'theme' => 'Weekly Challenge',
        'description' => 'Challenge currently unavailable.',
        'starts_at' => date('Y-m-d'),
        'ends_at' => date('Y-m-d'),
        'week_key' => date('o-\\WW'),
    ];
    $challengeNotice = 'Failed to load challenge data. Please try again.';
    $challengeNoticeType = 'error';
    $myOutfits = [];
    $myEntry = null;
    $challengeEntries = [];
    $previousWeekWinner = null;
    $entryCommentsMap = [];
    $challengeBadgeStats = getUserChallengeBadgeStats($pdo, (int) ($_SESSION['user_id'] ?? 0));
}
