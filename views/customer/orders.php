<h1>My Orders</h1>

<?php if ($successMsg): ?>
    <p class="success"><?php echo e($successMsg); ?></p>
<?php endif; ?>
<?php if ($actionErr): ?>
    <p class="error"><?php echo e($actionErr); ?></p>
<?php endif; ?>

<p>
    Show:
    <?php foreach ($valid_filters as $f): ?>
        <?php if ($f === $filter): ?>
            <strong><?php echo str_replace('_', ' ', $f); ?></strong>
        <?php else: ?>
            <a href="<?php echo url('customer', 'orders', ['status' => $f]); ?>"><?php echo str_replace('_', ' ', $f); ?></a>
        <?php endif; ?>
    <?php endforeach; ?>
</p>

<?php if (empty($orders)): ?>
    <p>No orders to show for this filter.</p>
<?php else: ?>
    <table class="data-table">
        <tr>
            <th>Order</th>
            <th>Shop</th>
            <th>Items</th>
            <th>Delivery</th>
            <th>Payment</th>
            <th>Total</th>
            <th>Rider</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
        <?php foreach ($orders as $o): ?>
            <tr class="<?php echo $o['status'] === 'pending' ? 'alert' : 'notice'; ?>">
                <td>
                    #<?php echo (int) $o['order_id']; ?><br>
                    <small><?php echo e($o['created_at']); ?></small>
                </td>
                <td><?php echo e($o['shop_name']); ?></td>
                <td>
                    <?php foreach ($o['items'] as $item): ?>
                        <?php echo e($item['product_name']); ?>
                        x<?php echo (int) $item['quantity']; ?>
                        @ <?php echo money($item['unit_price']); ?><br>
                    <?php endforeach; ?>
                </td>
                <td><?php echo $o['fast_delivery'] ? 'Fast' : 'Standard'; ?></td>
                <td>
                    <?php echo e($payment_methods[$o['payment_method']] ?? $o['payment_method']); ?><br>
                    <strong><?php echo e($o['payment_status']); ?></strong>
                </td>
                <td class="num"><?php echo money($o['total_amount']); ?></td>
                <td><?php echo $o['rider_name'] ? e($o['rider_name']) : 'Unassigned'; ?></td>
                <td><?php echo str_replace('_', ' ', e($o['status'])); ?></td>
                <td class="cell-actions">
                    <a href="<?php echo url('customer', 'tracking', ['order_id' => $o['order_id']]); ?>">Track</a>
                    <a href="<?php echo url('customer', 'chat', ['order_id' => $o['order_id']]); ?>">Chat</a>
                    <?php if (can_transition('customer', $o['status'], 'cancelled')): ?>
                        <form class="action-form" method="POST"
                              action="<?php echo url('customer', 'orders', ['status' => $filter]); ?>"
                              onsubmit="return confirm('Cancel order #<?php echo (int) $o['order_id']; ?>?');">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="order_id" value="<?php echo $o['order_id']; ?>">
                            <button class="btn btn-danger" type="submit" name="action" value="cancelled">Cancel</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url('customer', 'tracking'); ?>">Order Tracking</a>
    <a class="nav-link" href="<?php echo url('customer', 'dashboard'); ?>">Back to Dashboard</a>
</nav>
