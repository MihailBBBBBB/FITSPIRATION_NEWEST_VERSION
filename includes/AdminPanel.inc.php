<?php
require_once __DIR__ . '/reports.inc.php';
require_once __DIR__ . '/csrf.inc.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../HTML/LogIn.php?error=notloggedin');
    exit();
}

$current_admin_id = (int)$_SESSION['user_id'];


$adminCheck = $pdo->prepare('SELECT is_admin FROM registration WHERE id = :id LIMIT 1');
$adminCheck->execute(['id' => $current_admin_id]);
$currentAdmin = $adminCheck->fetch(PDO::FETCH_ASSOC);

if (!$currentAdmin || (int)$currentAdmin['is_admin'] !== 1) {
    header('Location: ../HTML/Main.php');
    exit();
}

function adminUserExists(PDO $pdo, int $userId): bool {
    $stmt = $pdo->prepare('SELECT 1 FROM registration WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    return (bool) $stmt->fetchColumn();
}

function adminReportIsActionable(PDO $pdo, int $reportId): bool {
    $stmt = $pdo->prepare('SELECT 1 FROM reports WHERE id = :id AND status IN ("open", "in-review") LIMIT 1');
    $stmt->execute(['id' => $reportId]);
    return (bool) $stmt->fetchColumn();
}

function adminActiveAdminCount(PDO $pdo): int {
    return (int) $pdo->query('SELECT COUNT(*) FROM registration WHERE is_admin = 1 AND banned = 0')->fetchColumn();
}

$action_message = '';
$action_type = 'ok';

$admin_tab = isset($_GET['tab']) ? trim($_GET['tab']) : 'users';
if (!in_array($admin_tab, ['users', 'content', 'reports'], true)) {
    $admin_tab = 'users';
}

$selected_pin_id = isset($_GET['pin_id']) ? (int)$_GET['pin_id'] : 0;
$selectedPin = null;
$selectedPinComments = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $targetId = 0;
    $targetPinId = 0;
    $targetCommentId = 0;

    if (isset($_POST['admin_tab'])) {
        $postedTab = trim((string)$_POST['admin_tab']);
        if (in_array($postedTab, ['users', 'content', 'reports'], true)) {
            $admin_tab = $postedTab;
        }
    }

    if (isset($_POST['selected_pin_id'])) {
        $selected_pin_id = (int)$_POST['selected_pin_id'];
    }

    if (isset($_POST['delete_id'])) {
        $targetId = (int)$_POST['delete_id'];
    } elseif (isset($_POST['ban_id'])) {
        $targetId = (int)$_POST['ban_id'];
    } elseif (isset($_POST['admin_id'])) {
        $targetId = (int)$_POST['admin_id'];
    } elseif (isset($_POST['remove_id'])) {
        $targetId = (int)$_POST['remove_id'];
    } elseif (isset($_POST['unban_id'])) {
        $targetId = (int)$_POST['unban_id'];
    } elseif (isset($_POST['delete_pin_id'])) {
        $targetPinId = (int)$_POST['delete_pin_id'];
    } elseif (isset($_POST['delete_comment_id'])) {
        $targetCommentId = (int)$_POST['delete_comment_id'];
    } elseif (isset($_POST['report_id'])) {
        $targetId = (int)$_POST['report_id'];
    }

    if (isset($_POST['delete_pin_admin'])) {
        if ($targetPinId <= 0) {
            $action_message = 'Invalid pin selected.';
            $action_type = 'error';
        } else {
            $imgStmt = $pdo->prepare('SELECT img FROM pins WHERE id = :id LIMIT 1');
            $imgStmt->execute(['id' => $targetPinId]);
            $pinMeta = $imgStmt->fetch(PDO::FETCH_ASSOC);

            if (!$pinMeta) {
                $action_message = 'Pin not found.';
                $action_type = 'error';
            } else {
                $pdo->prepare('DELETE FROM likes WHERE pin_id = :id')->execute(['id' => $targetPinId]);
                $pdo->prepare('DELETE FROM comments WHERE pin_id = :id')->execute(['id' => $targetPinId]);
                $pdo->prepare('DELETE FROM notifications WHERE pin_id = :id')->execute(['id' => $targetPinId]);
                $pdo->prepare('DELETE FROM pins WHERE id = :id LIMIT 1')->execute(['id' => $targetPinId]);

                if ($selected_pin_id === $targetPinId) {
                    $selected_pin_id = 0;
                }

                if (!empty($pinMeta['img'])) {
                    $imgPath = __DIR__ . '/../images/' . $pinMeta['img'];
                    if (is_file($imgPath)) {
                        @unlink($imgPath);
                    }
                }

                $action_message = 'Pin removed.';
                writeAuditLog($pdo, $current_admin_id, 'delete_pin', 'pin', $targetPinId, 'Deleted pin from admin panel moderation.');
            }
        }
    } elseif (isset($_POST['delete_comment_admin'])) {
        if ($targetCommentId <= 0) {
            $action_message = 'Invalid comment selected.';
            $action_type = 'error';
        } else {
            $existsStmt = $pdo->prepare('SELECT 1 FROM comments WHERE id = :id LIMIT 1');
            $existsStmt->execute(['id' => $targetCommentId]);
            if (!$existsStmt->fetchColumn()) {
                $action_message = 'Comment not found.';
                $action_type = 'error';
            } else {
                $stmt = $pdo->prepare('DELETE FROM comments WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $targetCommentId]);
                $action_message = 'Comment removed.';
                writeAuditLog($pdo, $current_admin_id, 'delete_comment', 'comment', $targetCommentId, 'Deleted comment from admin panel moderation.');
            }
        }
    } elseif (isset($_POST['resolve_report'])) {
        if ($targetId <= 0) {
            $action_message = 'Invalid report selected.';
            $action_type = 'error';
        } elseif (!adminReportIsActionable($pdo, $targetId)) {
            $action_message = 'Report is no longer actionable.';
            $action_type = 'error';
        } else {
            $adminNote = trim($_POST['admin_note'] ?? '');
            if (!$adminNote) {
                $action_message = 'Please provide a resolution note explaining the action taken.';
                $action_type = 'error';
            } else {
                $stmt = $pdo->prepare('UPDATE reports SET status = "action-taken", resolved_by = :admin_id, resolved_at = NOW(), admin_note = :note WHERE id = :id LIMIT 1');
                $stmt->execute([
                    'admin_id' => $current_admin_id,
                    'note' => substr($adminNote, 0, 255),
                    'id' => $targetId,
                ]);
                $action_message = 'Report marked as action taken.';
                writeAuditLog($pdo, $current_admin_id, 'resolve_report', 'report', $targetId, 'Resolved report from queue.', $adminNote);
            }
        }
    } elseif (isset($_POST['in_review_report'])) {
        if ($targetId <= 0) {
            $action_message = 'Invalid report selected.';
            $action_type = 'error';
        } elseif (!adminReportIsActionable($pdo, $targetId)) {
            $action_message = 'Report is no longer actionable.';
            $action_type = 'error';
        } else {
            $adminNote = trim($_POST['admin_note'] ?? 'Moving to review.');
            $stmt = $pdo->prepare('UPDATE reports SET status = "in-review", resolved_by = :admin_id, admin_note = :note WHERE id = :id LIMIT 1');
            $stmt->execute([
                'admin_id' => $current_admin_id,
                'note' => substr($adminNote, 0, 255),
                'id' => $targetId,
            ]);
            $action_message = 'Report moved to in-review.';
            writeAuditLog($pdo, $current_admin_id, 'in_review_report', 'report', $targetId, 'Moved report to in-review.', $adminNote);
        }
    } elseif (isset($_POST['dismiss_report'])) {
        if ($targetId <= 0) {
            $action_message = 'Invalid report selected.';
            $action_type = 'error';
        } elseif (!adminReportIsActionable($pdo, $targetId)) {
            $action_message = 'Report is no longer actionable.';
            $action_type = 'error';
        } else {
            $adminNote = trim($_POST['admin_note'] ?? '');
            if (!$adminNote) {
                $action_message = 'Please provide a note explaining why you are dismissing this report.';
                $action_type = 'error';
            } else {
                $stmt = $pdo->prepare('UPDATE reports SET status = "dismissed", resolved_by = :admin_id, resolved_at = NOW(), admin_note = :note WHERE id = :id LIMIT 1');
                $stmt->execute([
                    'admin_id' => $current_admin_id,
                    'note' => substr($adminNote, 0, 255),
                    'id' => $targetId,
                ]);
                $action_message = 'Report dismissed.';
                writeAuditLog($pdo, $current_admin_id, 'dismiss_report', 'report', $targetId, 'Dismissed report from queue.', $adminNote);
            }
        }
    } elseif (isset($_POST['bulk_action']) && isset($_POST['selected_reports'])) {
        $bulkAction = trim($_POST['bulk_action']);
        $selectedReports = array_filter(array_map('intval', $_POST['selected_reports'] ?? []), fn($x) => $x > 0);
        $actionNote = trim($_POST['bulk_action_note'] ?? 'Bulk action executed');
        
        if (empty($selectedReports)) {
            $action_message = 'No reports selected.';
            $action_type = 'error';
        } elseif (!$actionNote && ($bulkAction === 'resolve' || $bulkAction === 'dismiss')) {
            $action_message = 'Please provide a note for this bulk action.';
            $action_type = 'error';
        } else {
            $count = 0;
            $validReportStmt = $pdo->prepare('SELECT id FROM reports WHERE id IN (' . str_repeat('?,', count($selectedReports) - 1) . '?) AND status IN ("open", "in-review")');
            $validReportStmt->execute($selectedReports);
            $selectedReports = array_map('intval', $validReportStmt->fetchAll(PDO::FETCH_COLUMN));

            if (empty($selectedReports)) {
                $action_message = 'Selected reports are no longer actionable.';
                $action_type = 'error';
            } else {
                $placeholders = str_repeat('?,', count($selectedReports) - 1) . '?';
            
                if ($bulkAction === 'resolve') {
                    $stmt = $pdo->prepare("UPDATE reports SET status = 'action-taken', resolved_by = ?, resolved_at = NOW(), admin_note = ? WHERE id IN ($placeholders)");
                    $params = array_merge([$current_admin_id, substr($actionNote, 0, 255)], $selectedReports);
                    $stmt->execute($params);
                    $count = count($selectedReports);
                    $action_message = "Resolved $count report(s).";
                    foreach ($selectedReports as $rid) {
                        writeAuditLog($pdo, $current_admin_id, 'bulk_resolve_report', 'report', $rid, 'Bulk action.', $actionNote);
                    }
                } elseif ($bulkAction === 'dismiss') {
                    $stmt = $pdo->prepare("UPDATE reports SET status = 'dismissed', resolved_by = ?, resolved_at = NOW(), admin_note = ? WHERE id IN ($placeholders)");
                    $params = array_merge([$current_admin_id, substr($actionNote, 0, 255)], $selectedReports);
                    $stmt->execute($params);
                    $count = count($selectedReports);
                    $action_message = "Dismissed $count report(s).";
                    foreach ($selectedReports as $rid) {
                        writeAuditLog($pdo, $current_admin_id, 'bulk_dismiss_report', 'report', $rid, 'Bulk action.', $actionNote);
                    }
                } elseif ($bulkAction === 'in-review') {
                    $stmt = $pdo->prepare("UPDATE reports SET status = 'in-review', resolved_by = ? WHERE id IN ($placeholders)");
                    $params = array_merge([$current_admin_id], $selectedReports);
                    $stmt->execute($params);
                    $count = count($selectedReports);
                    $action_message = "Moved $count report(s) to in-review.";
                    foreach ($selectedReports as $rid) {
                        writeAuditLog($pdo, $current_admin_id, 'bulk_in_review_report', 'report', $rid, 'Bulk action.');
                    }
                } else {
                    $action_message = 'Unsupported bulk action.';
                    $action_type = 'error';
                }
            }
        }
    } elseif ($targetId <= 0) {
        $action_message = 'Invalid user selected.';
        $action_type = 'error';
    } elseif (isset($_POST['delete_user'])) {
        if (!adminUserExists($pdo, $targetId)) {
            $action_message = 'User not found.';
            $action_type = 'error';
        } elseif ($targetId === $current_admin_id) {
            $action_message = 'You cannot delete your own account from the admin panel.';
            $action_type = 'error';
        } elseif ((int) $pdo->query('SELECT is_admin FROM registration WHERE id = ' . (int) $targetId)->fetchColumn() === 1 && adminActiveAdminCount($pdo) <= 1) {
            $action_message = 'You cannot delete the last active admin.';
            $action_type = 'error';
        } else {
            $stmt = $pdo->prepare('DELETE FROM registration WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $targetId]);
            $action_message = 'User deleted.';
            writeAuditLog($pdo, $current_admin_id, 'delete_user', 'user', $targetId, 'Deleted user account from admin panel.');
        }
    } elseif (isset($_POST['ban_user'])) {
        if (!adminUserExists($pdo, $targetId)) {
            $action_message = 'User not found.';
            $action_type = 'error';
        } elseif ($targetId === $current_admin_id) {
            $action_message = 'You cannot ban your own account.';
            $action_type = 'error';
        } elseif ((int) $pdo->query('SELECT is_admin FROM registration WHERE id = ' . (int) $targetId)->fetchColumn() === 1 && adminActiveAdminCount($pdo) <= 1) {
            $action_message = 'You cannot ban the last active admin.';
            $action_type = 'error';
        } else {
            $stmt = $pdo->prepare('UPDATE registration SET is_admin = 0, banned = 1 WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $targetId]);
            $action_message = 'User banned.';
            writeAuditLog($pdo, $current_admin_id, 'ban_user', 'user', $targetId, 'Set banned=1 and removed admin role.');
        }
    } elseif (isset($_POST['unban_user'])) {
        if (!adminUserExists($pdo, $targetId)) {
            $action_message = 'User not found.';
            $action_type = 'error';
        } else {
            $stmt = $pdo->prepare('UPDATE registration SET banned = 0 WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $targetId]);
            $action_message = 'User unbanned.';
            writeAuditLog($pdo, $current_admin_id, 'unban_user', 'user', $targetId, 'Set banned=0.');
        }
    } elseif (isset($_POST['make_admin'])) {
        if (!adminUserExists($pdo, $targetId)) {
            $action_message = 'User not found.';
            $action_type = 'error';
        } else {
            $stmt = $pdo->prepare('UPDATE registration SET is_admin = 1 WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $targetId]);
            $action_message = 'Admin role granted.';
            writeAuditLog($pdo, $current_admin_id, 'make_admin', 'user', $targetId, 'Granted admin role.');
        }
    } elseif (isset($_POST['remove_admin'])) {
        if (!adminUserExists($pdo, $targetId)) {
            $action_message = 'User not found.';
            $action_type = 'error';
        } elseif ($targetId === $current_admin_id) {
            $action_message = 'You cannot remove your own admin role.';
            $action_type = 'error';
        } elseif ((int) $pdo->query('SELECT is_admin FROM registration WHERE id = ' . (int) $targetId)->fetchColumn() === 1 && adminActiveAdminCount($pdo) <= 1) {
            $action_message = 'You cannot remove the last active admin.';
            $action_type = 'error';
        } else {
            $stmt = $pdo->prepare('UPDATE registration SET is_admin = 0 WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $targetId]);
            $action_message = 'Admin role removed.';
            writeAuditLog($pdo, $current_admin_id, 'remove_admin', 'user', $targetId, 'Removed admin role.');
        }
    }
}

$stats = [
    'total_users' => 0,
    'admins' => 0,
    'banned' => 0,
    'active_users' => 0,
];

$stats['total_users'] = (int)$pdo->query('SELECT COUNT(*) FROM registration')->fetchColumn();
$stats['admins'] = (int)$pdo->query('SELECT COUNT(*) FROM registration WHERE is_admin = 1')->fetchColumn();
$stats['banned'] = (int)$pdo->query('SELECT COUNT(*) FROM registration WHERE banned = 1')->fetchColumn();
$stats['active_users'] = max(0, $stats['total_users'] - $stats['banned']);
$stats['total_pins'] = (int)$pdo->query('SELECT COUNT(*) FROM pins')->fetchColumn();
$stats['total_comments'] = (int)$pdo->query('SELECT COUNT(*) FROM comments')->fetchColumn();
$stats['open_reports'] = (int)$pdo->query('SELECT COUNT(*) FROM reports WHERE status = "open"')->fetchColumn();
$stats['in_review_reports'] = (int)$pdo->query('SELECT COUNT(*) FROM reports WHERE status = "in-review"')->fetchColumn();
$stats['total_reports'] = (int)$pdo->query('SELECT COUNT(*) FROM reports WHERE status IN ("open", "in-review", "action-taken", "dismissed")')->fetchColumn();

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'all';
$allowedFilters = ['all', 'admins', 'banned', 'active'];
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

$usersSql = 'SELECT id, username, email, is_admin, banned FROM registration WHERE 1=1';
$params = [];

if ($search !== '') {
    $usersSql .= ' AND (username LIKE :search OR email LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

if ($filter === 'admins') {
    $usersSql .= ' AND is_admin = 1';
} elseif ($filter === 'banned') {
    $usersSql .= ' AND banned = 1';
} elseif ($filter === 'active') {
    $usersSql .= ' AND banned = 0';
}

$usersSql .= ' ORDER BY is_admin DESC, banned ASC, id DESC';
$stmt = $pdo->prepare($usersSql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$recentPinsSql = '
    SELECT p.id, p.title, p.img, p.user_id,
           COALESCE(r.username, "Unknown") AS username,
           COALESCE(c.title, "No collection") AS collection_title,
           (SELECT COUNT(*) FROM likes l WHERE l.pin_id = p.id) AS like_count,
           (SELECT COUNT(*) FROM comments cm WHERE cm.pin_id = p.id) AS comment_count
    FROM pins p
    LEFT JOIN registration r ON p.user_id = r.id
    LEFT JOIN collections c ON p.collection_id = c.collection_id
    ORDER BY p.id DESC
    LIMIT 20
';
$recentPins = $pdo->query($recentPinsSql)->fetchAll(PDO::FETCH_ASSOC);

$recentCommentsSql = '
    SELECT c.id, c.comment, c.pin_id, c.user_id, c.created_at,
           COALESCE(r.username, "Unknown") AS username,
           COALESCE(p.title, "Deleted pin") AS pin_title
    FROM comments c
    LEFT JOIN registration r ON c.user_id = r.id
    LEFT JOIN pins p ON c.pin_id = p.id
    ORDER BY c.id DESC
    LIMIT 20
';
$recentComments = $pdo->query($recentCommentsSql)->fetchAll(PDO::FETCH_ASSOC);

// Report filters
$reportSearch = isset($_GET['report_search']) ? trim($_GET['report_search']) : '';
$reportCategory = isset($_GET['report_category']) ? trim($_GET['report_category']) : '';
$reportStatus = isset($_GET['report_status']) ? trim($_GET['report_status']) : '';

$openReportsSql = '
        SELECT rp.id, rp.reporter_user_id, rp.target_type, rp.target_id, rp.reason, rp.category, rp.status, rp.created_at,
                     COALESCE(rr.username, "Unknown") AS reporter_name,
                     CASE
                         WHEN rp.target_type = "pin" THEN COALESCE(p.title, "Deleted pin")
                         WHEN rp.target_type = "comment" THEN COALESCE(cm.comment, "Deleted comment")
                         ELSE "Unknown"
                     END AS target_preview,
                     CASE
                         WHEN rp.target_type = "pin" THEN COALESCE(p.img, "")
                         ELSE ""
                     END AS pin_image,
                     (SELECT COUNT(*) FROM reports WHERE target_type = rp.target_type AND target_id = rp.target_id AND status IN ("open", "in-review")) AS duplicate_count
        FROM reports rp
        LEFT JOIN registration rr ON rr.id = rp.reporter_user_id
        LEFT JOIN pins p ON rp.target_type = "pin" AND p.id = rp.target_id
        LEFT JOIN comments cm ON rp.target_type = "comment" AND cm.id = rp.target_id
        WHERE rp.status IN ("open", "in-review")';

$reportParams = [];

if ($reportSearch !== '') {
    $openReportsSql .= ' AND (rp.reason LIKE :search OR rr.username LIKE :search OR rp.category LIKE :search)';
    $reportParams['search'] = '%' . $reportSearch . '%';
}

if ($reportCategory !== '') {
    $openReportsSql .= ' AND rp.category = :category';
    $reportParams['category'] = $reportCategory;
}

if ($reportStatus !== '' && in_array($reportStatus, ['open', 'in-review'], true)) {
    $openReportsSql .= ' AND rp.status = :status';
    $reportParams['status'] = $reportStatus;
}

$openReportsSql .= ' ORDER BY rp.created_at DESC LIMIT 100';

$stmt = $pdo->prepare($openReportsSql);
$stmt->execute($reportParams);
$openReports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Audit log filters
$auditSearch = isset($_GET['audit_search']) ? trim($_GET['audit_search']) : '';
$auditAction = isset($_GET['audit_action']) ? trim($_GET['audit_action']) : '';
$auditDateFrom = isset($_GET['audit_date_from']) ? trim($_GET['audit_date_from']) : '';
$auditDateTo = isset($_GET['audit_date_to']) ? trim($_GET['audit_date_to']) : '';

$auditLogSql = '
        SELECT a.id, a.admin_user_id, a.action_type, a.target_type, a.target_id, a.details, a.admin_note, a.created_at,
                     COALESCE(r.username, "Unknown") AS admin_name
        FROM audit_log a
        LEFT JOIN registration r ON r.id = a.admin_user_id
        WHERE 1=1';

$auditParams = [];

if ($auditSearch !== '') {
    $auditLogSql .= ' AND (a.action_type LIKE :asearch OR r.username LIKE :asearch OR a.details LIKE :asearch)';
    $auditParams['asearch'] = '%' . $auditSearch . '%';
}

if ($auditAction !== '') {
    $auditLogSql .= ' AND a.action_type = :action';
    $auditParams['action'] = $auditAction;
}

if ($auditDateFrom !== '') {
    $auditLogSql .= ' AND a.created_at >= :date_from';
    $auditParams['date_from'] = $auditDateFrom . ' 00:00:00';
}

if ($auditDateTo !== '') {
    $auditLogSql .= ' AND a.created_at <= :date_to';
    $auditParams['date_to'] = $auditDateTo . ' 23:59:59';
}

$auditLogSql .= ' ORDER BY a.created_at DESC LIMIT 150';

$stmt = $pdo->prepare($auditLogSql);
$stmt->execute($auditParams);
$auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($admin_tab === 'content' && $selected_pin_id > 0) {
    $selectedPinSql = '
        SELECT p.id, p.title, p.description, p.img,
               COALESCE(r.username, "Unknown") AS username,
               (SELECT COUNT(*) FROM likes l WHERE l.pin_id = p.id) AS like_count,
               (SELECT COUNT(*) FROM comments cm WHERE cm.pin_id = p.id) AS comment_count
        FROM pins p
        LEFT JOIN registration r ON p.user_id = r.id
        WHERE p.id = :pin_id
        LIMIT 1
    ';
    $selectedPinStmt = $pdo->prepare($selectedPinSql);
    $selectedPinStmt->execute(['pin_id' => $selected_pin_id]);
    $selectedPin = $selectedPinStmt->fetch(PDO::FETCH_ASSOC);

    if ($selectedPin) {
        $selectedCommentsSql = '
            SELECT c.id, c.comment, c.created_at,
                   COALESCE(r.username, "Unknown") AS username
            FROM comments c
            LEFT JOIN registration r ON c.user_id = r.id
            WHERE c.pin_id = :pin_id
            ORDER BY c.id DESC
        ';
        $selectedCommentsStmt = $pdo->prepare($selectedCommentsSql);
        $selectedCommentsStmt->execute(['pin_id' => $selected_pin_id]);
        $selectedPinComments = $selectedCommentsStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $selected_pin_id = 0;
    }
}