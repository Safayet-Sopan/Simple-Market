<h1>Seller Approvals</h1>

<?php if ($successMsg): ?>
    <p class="success"><?php echo e($successMsg); ?></p>
<?php endif; ?>
<?php if ($actionErr): ?>
    <p class="error"><?php echo e($actionErr); ?></p>
<?php endif; ?>

<p>
    Sort by:
    <a href="<?php echo url('admin', 'approvals', ['sort' => 'newest']); ?>">Newest First</a> |
    <a href="<?php echo url('admin', 'approvals', ['sort' => 'oldest']); ?>">Oldest First</a>
</p>

<?php if (empty($pending)): ?>
    <p>No pending seller applications.</p>
<?php else: ?>
    <table class="data-table">
        <tr>
            <th>Shop Name</th>
            <th>Owner</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Address</th>
            <th>Business Type</th>
            <th>Applied</th>
            <th>Action</th>
        </tr>
        <?php foreach ($pending as $s): ?>
            <tr>
                <td><?php echo e($s['shop_name']); ?></td>
                <td><?php echo e($s['full_name']); ?></td>
                <td><?php echo e($s['email']); ?></td>
                <td><?php echo e($s['phone']); ?></td>
                <td><?php echo e($s['shop_address']); ?></td>
                <td><?php echo e($s['business_type']); ?></td>
                <td><?php echo e($s['applied_at']); ?></td>
                <td>
                    <form class="action-form" method="POST" action="<?php echo url('admin', 'approvals'); ?>">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="seller_id" value="<?php echo $s['seller_id']; ?>">
                        <input type="hidden" name="action" value="approve">
                        <button class="btn" type="submit">Approve</button>
                    </form>
                    <form class="action-form" method="POST" action="<?php echo url('admin', 'approvals'); ?>">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="seller_id" value="<?php echo $s['seller_id']; ?>">
                        <input type="hidden" name="action" value="reject">
                        <button class="btn btn-danger" type="submit">Reject</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url('admin', 'dashboard'); ?>">Back to Dashboard</a>
</nav>
