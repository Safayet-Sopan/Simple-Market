<h1>Notifications
    <span id="unread-badge"><?php echo $unread_count > 0 ? '(' . $unread_count . ')' : ''; ?></span>
</h1>

<?php if ($successMsg): ?>
    <p class="success"><?php echo e($successMsg); ?></p>
<?php endif; ?>
<?php if ($actionErr): ?>
    <p class="error"><?php echo e($actionErr); ?></p>
<?php endif; ?>

<?php if (empty($notifications)): ?>
    <p>Nothing here yet.</p>
<?php else: ?>
    <form class="button-bar" method="POST" action="<?php echo url($role, 'notifications'); ?>">
        <?php csrf_field(); ?>
        <button class="btn" type="submit" name="action" value="mark_all">Mark All Read</button>
        <button class="btn btn-secondary" type="submit" name="action" value="clear_read">Clear Read</button>
    </form>

    <table class="data-table">
        <tr>
            <th></th>
            <th>Message</th>
            <th>When</th>
            <th></th>
        </tr>
        <?php foreach ($notifications as $n): ?>
            <tr class="<?php echo $n['is_read'] ? 'notice' : 'alert'; ?>">
                <td class="cell-status"><?php echo $n['is_read'] ? 'read' : 'NEW'; ?></td>
                <td><?php echo e($n['message']); ?></td>
                <td><?php echo e($n['created_at']); ?></td>
                <td class="cell-actions">
                    <?php if (!$n['is_read']): ?>
                        <form class="action-form" method="POST" action="<?php echo url($role, 'notifications'); ?>">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="notification_id" value="<?php echo $n['notification_id']; ?>">
                            <button class="btn btn-sm" type="submit" name="action" value="mark_read">Mark Read</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url($role, 'dashboard'); ?>">Back to Dashboard</a>
</nav>
