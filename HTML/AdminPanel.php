<?php
session_start();
include_once "../JS/headerFooter.php";
include_once "../includes/AdminPanel.inc.php";

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Fitspiration</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../CSS/Main.css?v=13">
    <link rel="stylesheet" href="../CSS/AdminPanel.css?v=5">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="../JS/csrf.js"></script>
    <script src="../JS/translator.js"></script>
    <script src="../JS/AdminPanel.js"></script>
</head>
<body data-csrf-token="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES); ?>">
    <special-header></special-header>

    <div class="layout">
        <special-aside></special-aside>

        <main class="main-content admin-page">
            <section class="admin-hero">
                <div>
                    <p class="admin-eyebrow">Control Center</p>
                    <h1>Admin Panel</h1>
                    <p class="admin-subtitle">Manage users, moderation access, and account safety from one place.</p>
                </div>
            </section>

            <?php if (!empty($action_message)): ?>
                <div class="admin-alert <?php echo $action_type === 'error' ? 'is-error' : 'is-ok'; ?>">
                    <?php echo htmlspecialchars($action_message); ?>
                </div>
            <?php endif; ?>

            <section class="admin-stats-grid">
                <article class="admin-stat-card">
                    <span class="label">Total users</span>
                    <strong><?php echo (int)$stats['total_users']; ?></strong>
                </article>
                <article class="admin-stat-card">
                    <span class="label">Admins</span>
                    <strong><?php echo (int)$stats['admins']; ?></strong>
                </article>
                <article class="admin-stat-card">
                    <span class="label">Banned</span>
                    <strong><?php echo (int)$stats['banned']; ?></strong>
                </article>
                <article class="admin-stat-card">
                    <span class="label">Active users</span>
                    <strong><?php echo (int)$stats['active_users']; ?></strong>
                </article>
                <article class="admin-stat-card">
                    <span class="label">Pins</span>
                    <strong><?php echo (int)$stats['total_pins']; ?></strong>
                </article>
                <article class="admin-stat-card">
                    <span class="label">Comments</span>
                    <strong><?php echo (int)$stats['total_comments']; ?></strong>
                </article>
                <article class="admin-stat-card">
                    <span class="label">Open reports</span>
                    <strong><?php echo (int)$stats['open_reports']; ?></strong>
                </article>
            </section>

            <section class="admin-tabs">
                <a href="AdminPanel.php?tab=users" class="admin-tab <?php echo $admin_tab === 'users' ? 'active' : ''; ?>">User Management</a>
                <a href="AdminPanel.php?tab=content" class="admin-tab <?php echo $admin_tab === 'content' ? 'active' : ''; ?>">Content Moderation</a>
                <a href="AdminPanel.php?tab=reports" class="admin-tab <?php echo $admin_tab === 'reports' ? 'active' : ''; ?>">Reports & Audit</a>
            </section>

            <?php if ($admin_tab === 'users'): ?>
            <section class="admin-management">
                <div class="admin-section-head">
                    <h2>Manage Users</h2>
                    <form method="GET" class="admin-filters">
                        <input type="hidden" name="tab" value="users">
                        <input
                            type="text"
                            name="q"
                            value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Search username or email"
                        >
                        <select name="filter">
                            <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All users</option>
                            <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>Active users</option>
                            <option value="admins" <?php echo $filter === 'admins' ? 'selected' : ''; ?>>Admins</option>
                            <option value="banned" <?php echo $filter === 'banned' ? 'selected' : ''; ?>>Banned</option>
                        </select>
                        <button type="submit" class="admin-btn filter-btn">Apply</button>
                    </form>
                </div>

                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="5" class="admin-empty">No users found for current filter.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="user-meta">
                                                <strong class="no-translate" data-user-content="true"><?php echo htmlspecialchars($user['username']); ?></strong>
                                                <span>ID #<?php echo (int)$user['id']; ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <span class="pill <?php echo (int)$user['is_admin'] === 1 ? 'role-admin' : 'role-user'; ?>">
                                                <?php echo (int)$user['is_admin'] === 1 ? 'Admin' : 'User'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="pill <?php echo (int)$user['banned'] === 1 ? 'status-banned' : 'status-active'; ?>">
                                                <?php echo (int)$user['banned'] === 1 ? 'Banned' : 'Active'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="actions-inline">
                                                <form method="POST">
                                                    <?php echo csrfInput(); ?>
                                                    <input type="hidden" name="admin_tab" value="users">
                                                    <input type="hidden" name="delete_id" value="<?php echo (int)$user['id']; ?>">
                                                    <button type="submit" name="delete_user" class="admin-btn delete-btn" onclick="return confirm('Delete this user account?');">Delete</button>
                                                </form>

                                                <?php if ((int)$user['banned'] === 1): ?>
                                                    <form method="POST">
                                                        <?php echo csrfInput(); ?>
                                                        <input type="hidden" name="admin_tab" value="users">
                                                        <input type="hidden" name="unban_id" value="<?php echo (int)$user['id']; ?>">
                                                        <button type="submit" name="unban_user" class="admin-btn unban-btn" onclick="return confirm('Unban this user?');">Unban</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST">
                                                        <?php echo csrfInput(); ?>
                                                        <input type="hidden" name="admin_tab" value="users">
                                                        <input type="hidden" name="ban_id" value="<?php echo (int)$user['id']; ?>">
                                                        <button type="submit" name="ban_user" class="admin-btn ban-btn" onclick="return confirm('Ban this user?');">Ban</button>
                                                    </form>
                                                <?php endif; ?>

                                                <?php if ((int)$user['is_admin'] === 1): ?>
                                                    <form method="POST">
                                                        <?php echo csrfInput(); ?>
                                                        <input type="hidden" name="admin_tab" value="users">
                                                        <input type="hidden" name="remove_id" value="<?php echo (int)$user['id']; ?>">
                                                        <button type="submit" name="remove_admin" class="admin-btn role-btn" onclick="return confirm('Remove admin role from this account?');">Remove admin</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST">
                                                        <?php echo csrfInput(); ?>
                                                        <input type="hidden" name="admin_tab" value="users">
                                                        <input type="hidden" name="admin_id" value="<?php echo (int)$user['id']; ?>">
                                                        <button type="submit" name="make_admin" class="admin-btn role-btn" onclick="return confirm('Grant admin role to this account?');">Make admin</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php elseif ($admin_tab === 'content'): ?>
            <section class="admin-management">
                <div class="admin-section-head">
                    <h2>Moderate Content</h2>
                </div>

                <?php if ($selectedPin): ?>
                    <article class="selected-pin-panel">
                        <div class="selected-pin-head">
                            <h3>Viewing Pin #<?php echo (int)$selectedPin['id']; ?></h3>
                            <a href="AdminPanel.php?tab=content" class="admin-btn view-btn">Close</a>
                        </div>
                        <div class="selected-pin-layout">
                            <img src="<?php echo !empty($selectedPin['img']) ? '../images/' . htmlspecialchars($selectedPin['img']) : '../images/no_image.jpg'; ?>" alt="Selected pin">
                            <div class="selected-pin-copy">
                                <strong><?php echo htmlspecialchars($selectedPin['title'] ?: 'Untitled pin'); ?></strong>
                                <span class="no-translate" data-user-content="true">By <?php echo htmlspecialchars($selectedPin['username']); ?></span>
                                <span><?php echo (int)$selectedPin['like_count']; ?> likes • <?php echo (int)$selectedPin['comment_count']; ?> comments</span>
                                <?php if (!empty($selectedPin['description'])): ?>
                                    <p><?php echo htmlspecialchars($selectedPin['description']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="selected-pin-comments">
                            <h4>Comments on this pin</h4>
                            <?php if (empty($selectedPinComments)): ?>
                                <p class="admin-empty">No comments for this pin.</p>
                            <?php else: ?>
                                <?php foreach ($selectedPinComments as $comment): ?>
                                    <div class="selected-comment-item">
                                        <div>
                                            <strong class="no-translate" data-user-content="true"><?php echo htmlspecialchars($comment['username']); ?></strong>
                                            <p><?php echo htmlspecialchars($comment['comment']); ?></p>
                                        </div>
                                        <form method="POST">
                                            <?php echo csrfInput(); ?>
                                            <input type="hidden" name="admin_tab" value="content">
                                            <input type="hidden" name="selected_pin_id" value="<?php echo (int)$selectedPin['id']; ?>">
                                            <input type="hidden" name="delete_comment_id" value="<?php echo (int)$comment['id']; ?>">
                                            <button type="submit" name="delete_comment_admin" class="admin-btn delete-btn" onclick="return confirm('Delete this comment?');">Delete comment</button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endif; ?>

                <div class="content-moderation-grid">
                    <article class="moderation-card">
                        <h3>Recent Pins</h3>
                        <div class="moderation-list">
                            <?php if (empty($recentPins)): ?>
                                <p class="admin-empty">No pins found.</p>
                            <?php else: ?>
                                <?php foreach ($recentPins as $pin): ?>
                                    <div class="moderation-item">
                                        <img src="<?php echo !empty($pin['img']) ? '../images/' . htmlspecialchars($pin['img']) : '../images/no_image.jpg'; ?>" alt="Pin">
                                        <div class="moderation-copy">
                                            <strong><?php echo htmlspecialchars($pin['title'] ?: 'Untitled pin'); ?></strong>
                                            <span class="no-translate" data-user-content="true">By <?php echo htmlspecialchars($pin['username']); ?></span>
                                            <span><?php echo (int)$pin['like_count']; ?> likes • <?php echo (int)$pin['comment_count']; ?> comments</span>
                                        </div>
                                        <div class="pin-actions">
                                            <a href="AdminPanel.php?tab=content&pin_id=<?php echo (int)$pin['id']; ?>" class="admin-btn view-btn">Open</a>
                                            <form method="POST">
                                                <?php echo csrfInput(); ?>
                                                <input type="hidden" name="admin_tab" value="content">
                                                <input type="hidden" name="selected_pin_id" value="<?php echo (int)$selected_pin_id; ?>">
                                                <input type="hidden" name="delete_pin_id" value="<?php echo (int)$pin['id']; ?>">
                                                <button type="submit" name="delete_pin_admin" class="admin-btn delete-btn" onclick="return confirm('Delete this pin and related likes/comments?');">Delete pin</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </article>

                    <article class="moderation-card">
                        <h3>Recent Comments</h3>
                        <div class="moderation-list">
                            <?php if (empty($recentComments)): ?>
                                <p class="admin-empty">No comments found.</p>
                            <?php else: ?>
                                <?php foreach ($recentComments as $comment): ?>
                                    <div class="moderation-item comment-item">
                                        <div class="moderation-copy">
                                            <strong class="no-translate" data-user-content="true"><?php echo htmlspecialchars($comment['username']); ?></strong>
                                            <span>On pin #<?php echo (int)$comment['pin_id']; ?>: <?php echo htmlspecialchars($comment['pin_title']); ?></span>
                                            <p><?php echo htmlspecialchars($comment['comment']); ?></p>
                                        </div>
                                        <form method="POST">
                                            <?php echo csrfInput(); ?>
                                            <input type="hidden" name="admin_tab" value="content">
                                            <input type="hidden" name="selected_pin_id" value="<?php echo (int)$selected_pin_id; ?>">
                                            <input type="hidden" name="delete_comment_id" value="<?php echo (int)$comment['id']; ?>">
                                            <button type="submit" name="delete_comment_admin" class="admin-btn delete-btn" onclick="return confirm('Delete this comment?');">Delete comment</button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </article>
                </div>
            </section>
            <?php else: ?>
            <section class="admin-management">
                <div class="admin-section-head">
                    <h2>Reports Queue</h2>
                </div>

                <div class="admin-filters" style="margin-bottom: 1.5rem; padding: 1rem; background: rgba(255,255,255,0.04); border-radius: 8px;">
                    <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem;">
                        <input type="hidden" name="tab" value="reports">
                        <input type="text" name="report_search" placeholder="Search reason/reporter..." value="<?php echo htmlspecialchars($reportSearch ?? ''); ?>" style="padding: 0.4rem 0.6rem; background: #111827; color: #f9fafb; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; font-size: 0.85rem;">
                        <select name="report_category" style="padding: 0.4rem 0.6rem; background: #111827; color: #f9fafb; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; font-size: 0.85rem;">
                            <option value="">All Categories</option>
                            <option value="spam" <?php echo ($reportCategory ?? '') === 'spam' ? 'selected' : ''; ?>>Spam</option>
                            <option value="harassment" <?php echo ($reportCategory ?? '') === 'harassment' ? 'selected' : ''; ?>>Harassment</option>
                            <option value="nudity" <?php echo ($reportCategory ?? '') === 'nudity' ? 'selected' : ''; ?>>Nudity</option>
                            <option value="hate" <?php echo ($reportCategory ?? '') === 'hate' ? 'selected' : ''; ?>>Hate Speech</option>
                            <option value="misinformation" <?php echo ($reportCategory ?? '') === 'misinformation' ? 'selected' : ''; ?>>Misinformation</option>
                            <option value="copyright" <?php echo ($reportCategory ?? '') === 'copyright' ? 'selected' : ''; ?>>Copyright</option>
                            <option value="other" <?php echo ($reportCategory ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                        <select name="report_status" style="padding: 0.4rem 0.6rem; background: #111827; color: #f9fafb; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; font-size: 0.85rem;">
                            <option value="">All Status</option>
                            <option value="open" <?php echo ($reportStatus ?? '') === 'open' ? 'selected' : ''; ?>>Open</option>
                            <option value="in-review" <?php echo ($reportStatus ?? '') === 'in-review' ? 'selected' : ''; ?>>In-Review</option>
                        </select>
                        <button type="submit" style="padding: 0.4rem 0.8rem; background: #0f766e; color: #f3f4f6; border: none; border-radius: 4px; font-size: 0.85rem; font-weight: 600; cursor: pointer;">Filter</button>
                        <a href="AdminPanel.php?tab=reports" style="padding: 0.4rem 0.8rem; background: #475569; color: #f3f4f6; border: none; border-radius: 4px; font-size: 0.85rem; font-weight: 600; cursor: pointer; text-decoration: none; text-align: center;">Clear</a>
                    </form>
                </div>

                <div class="bulk-actions" style="margin-bottom: 1rem; padding: 0.75rem; background: rgba(255,255,255,0.04); border-radius: 8px; display: none;" id="bulkActionsPanel">
                    <form method="POST" id="bulkReportActionsForm" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                        <?php echo csrfInput(); ?>
                        <input type="hidden" name="admin_tab" value="reports">
                        <div id="bulkReportSelections"></div>
                        <select name="bulk_action" required style="padding: 0.4rem 0.6rem; background: #111827; color: #f9fafb; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; font-size: 0.75rem;">
                            <option value="">Select action...</option>
                            <option value="resolve">Mark as Action Taken</option>
                            <option value="dismiss">Dismiss</option>
                            <option value="in-review">Move to In-Review</option>
                        </select>
                        <input type="text" name="bulk_action_note" placeholder="Action note..." maxlength="255" style="padding: 0.4rem 0.6rem; background: #111827; color: #f9fafb; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; font-size: 0.75rem; min-width: 200px;">
                        <button type="submit" style="padding: 0.4rem 0.8rem; background: #0f766e; color: #f3f4f6; border: none; border-radius: 4px; font-size: 0.75rem; font-weight: 600; cursor: pointer;">Execute</button>
                        <button type="button" onclick="cancelBulkActions()" style="padding: 0.4rem 0.8rem; background: #5d6d7b; color: #f3f4f6; border: none; border-radius: 4px; font-size: 0.75rem; font-weight: 600; cursor: pointer;">Cancel</button>
                    </form>
                </div>

                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAllReports" onchange="toggleAllReports(this.checked)"></th>
                                <th>Category</th>
                                <th>Reported by</th>
                                <th>Target</th>
                                <th>Reason</th>
                                <th>Preview</th>
                                <th>Duplicates</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($openReports)): ?>
                                <tr><td colspan="10" class="admin-empty">No open reports.</td></tr>
                            <?php else: ?>
                                <form method="POST" id="bulkReportsForm">
                                <?php echo csrfInput(); ?>
                                <?php foreach ($openReports as $report): ?>
                                    <tr>
                                        <td><input type="checkbox" class="report-checkbox" name="selected_reports[]" value="<?php echo (int)$report['id']; ?>" onchange="updateBulkPanel()"></td>
                                        <td><?php echo htmlspecialchars(ucfirst($report['category'])); ?></td>
                                        <td class="no-translate" data-user-content="true"><?php echo htmlspecialchars($report['reporter_name']); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst($report['target_type'])) . ' #' . (int)$report['target_id']; ?></td>
                                        <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($report['reason']); ?></td>
                                        <td>
                                            <?php if ($report['target_type'] === 'pin' && $report['pin_image']): ?>
                                                <img src="../images/<?php echo htmlspecialchars($report['pin_image']); ?>" alt="Pin" style="max-width: 50px; max-height: 50px; border-radius: 4px;">
                                            <?php else: ?>
                                                <?php echo htmlspecialchars(substr($report['target_preview'], 0, 30)); ?>...
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge" style="background: rgba(239, 68, 68, 0.2); color: #ef4444; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem;">
                                                <?php echo (int)$report['duplicate_count']; ?>
                                            </span>
                                        </td>
                                        <td><span style="background: rgba(59, 130, 246, 0.2); color: #3b82f6; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.7rem;"><?php echo htmlspecialchars(ucfirst($report['status'])); ?></span></td>
                                        <td><?php echo htmlspecialchars($report['created_at']); ?></td>
                                        <td>
                                            <div class="actions-inline">
                                                <?php if ($report['target_type'] === 'pin'): ?>
                                                    <a class="admin-btn view-btn" href="AdminPanel.php?tab=content&pin_id=<?php echo (int)$report['target_id']; ?>">Open</a>
                                                <?php endif; ?>
                                                <form method="POST" style="display: inline-block;">
                                                    <?php echo csrfInput(); ?>
                                                    <input type="hidden" name="admin_tab" value="reports">
                                                    <input type="hidden" name="report_id" value="<?php echo (int)$report['id']; ?>">
                                                    <input type="text" name="admin_note" placeholder="Action note..." maxlength="255" required style="width: 120px; padding: 0.25rem 0.5rem; background: #111827; color: #f9fafb; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; font-size: 0.7rem;">
                                                    <button type="submit" name="resolve_report" class="admin-btn unban-btn" style="padding: 0.25rem 0.5rem; font-size: 0.7rem;">Resolve</button>
                                                </form>
                                                <form method="POST" style="display: inline-block;">
                                                    <?php echo csrfInput(); ?>
                                                    <input type="hidden" name="admin_tab" value="reports">
                                                    <input type="hidden" name="report_id" value="<?php echo (int)$report['id']; ?>">
                                                    <input type="text" name="admin_note" placeholder="Dismiss reason..." maxlength="255" required style="width: 120px; padding: 0.25rem 0.5rem; background: #111827; color: #f9fafb; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; font-size: 0.7rem;">
                                                    <button type="submit" name="dismiss_report" class="admin-btn ban-btn" style="padding: 0.25rem 0.5rem; font-size: 0.7rem;">Dismiss</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </form>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="admin-section-head" style="margin-top: 1rem;">
                    <h2>Audit Log</h2>
                </div>

                <div class="admin-filters" style="margin-bottom: 1.5rem; padding: 1rem; background: rgba(255,255,255,0.04); border-radius: 8px;">
                    <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem;">
                        <input type="hidden" name="tab" value="reports">
                        <input type="text" name="audit_search" placeholder="Search action/admin..." value="<?php echo htmlspecialchars($auditSearch ?? ''); ?>" style="padding: 0.4rem 0.6rem; background: #111827; color: #f9fafb; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; font-size: 0.85rem;">
                        <input type="date" name="audit_date_from" value="<?php echo htmlspecialchars($auditDateFrom ?? ''); ?>" style="padding: 0.4rem 0.6rem; background: #111827; color: #f9fafb; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; font-size: 0.85rem;">
                        <input type="date" name="audit_date_to" value="<?php echo htmlspecialchars($auditDateTo ?? ''); ?>" style="padding: 0.4rem 0.6rem; background: #111827; color: #f9fafb; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; font-size: 0.85rem;">
                        <button type="submit" style="padding: 0.4rem 0.8rem; background: #0f766e; color: #f3f4f6; border: none; border-radius: 4px; font-size: 0.85rem; font-weight: 600; cursor: pointer;">Filter</button>
                        <a href="AdminPanel.php?tab=reports" style="padding: 0.4rem 0.8rem; background: #475569; color: #f3f4f6; border: none; border-radius: 4px; font-size: 0.85rem; font-weight: 600; cursor: pointer; text-decoration: none; text-align: center;">Clear</a>
                    </form>
                </div>

                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Admin</th>
                                <th>Action</th>
                                <th>Target</th>
                                <th>Details</th>
                                <th>Note</th>
                                <th>When</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($auditLogs)): ?>
                                <tr><td colspan="6" class="admin-empty">No audit entries yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($auditLogs as $entry): ?>
                                    <tr>
                                        <td class="no-translate" data-user-content="true"><?php echo htmlspecialchars($entry['admin_name']); ?></td>
                                        <td><?php echo htmlspecialchars($entry['action_type']); ?></td>
                                        <td><?php echo htmlspecialchars($entry['target_type']) . ($entry['target_id'] ? ' #' . (int)$entry['target_id'] : ''); ?></td>
                                        <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($entry['details'] ?? ''); ?></td>
                                        <td style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; color: #bfdbfe;"><?php echo htmlspecialchars($entry['admin_note'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($entry['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php endif; ?>
        </main>
    </div>
    <special-footer></special-footer>
</body>
</html>
