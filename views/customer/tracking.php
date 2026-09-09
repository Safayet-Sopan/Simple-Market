<h1>Order Tracking</h1>

<?php if ($asked_for_one && !$order): ?>
    <p class="error">Order not found.</p>
<?php endif; ?>

<?php if ($order): ?>

    <p class="success">Order #<?php echo (int) $order['order_id']; ?> placed on
        <?php echo e($order['created_at']); ?></p>

    <h2>Status</h2>
    <?php if ($order['status'] === 'cancelled'): ?>
        <p class="critical">This order was cancelled.</p>
    <?php else: ?>
        <?php $current_step = array_search($order['status'], $timeline, true); ?>
        <table class="timeline">
            <?php foreach ($timeline as $i => $step): ?>
                <tr class="<?php echo $i <= $current_step ? 'success' : 'notice'; ?>">
                    <td class="cell-status"><?php echo $i <= $current_step ? 'DONE' : 'PENDING'; ?></td>
                    <td><?php echo e($status_labels[$step]); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <h2>Details</h2>
    <p>Shop: <?php echo e($order['shop_name']); ?></p>
    <p>Deliver to: <?php echo e($order['delivery_address']); ?></p>
    <p>Delivery type: <?php echo $order['fast_delivery'] ? 'Fast Delivery' : 'Standard'; ?></p>
    <p>Rider:
        <?php if ($order['rider_id']): ?>
            <?php echo e($order['rider_name']); ?> (<?php echo e($order['vehicle_type']); ?>)
        <?php else: ?>
            Not assigned yet
        <?php endif; ?>
    </p>

    <table class="data-table">
        <tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Line Total</th></tr>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?php echo e($item['product_name']); ?></td>
                <td><?php echo (int) $item['quantity']; ?></td>
                <td class="num"><?php echo money($item['unit_price']); ?></td>
                <td class="num"><?php echo money($item['line_total']); ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <p>Subtotal: <?php echo money($order['subtotal']); ?></p>
    <p>Delivery fee: <?php echo money($order['delivery_fee']); ?></p>
    <p class="order-total"><strong>Total: <?php echo money($order['total_amount']); ?></strong></p>

    <?php if ($order['status'] === 'delivered'): ?>
        <a class="action-link" href="<?php echo url('customer', 'feedback', ['order_id' => $order['order_id']]); ?>">Leave Product Feedback</a>
        <a class="action-link" href="<?php echo url('customer', 'rating', ['order_id' => $order['order_id']]); ?>">Rate the Seller</a>
    <?php endif; ?>

    <a class="action-link" href="<?php echo url('customer', 'chat', ['order_id' => $order['order_id']]); ?>">Message the shop</a>

    <p><a href="<?php echo url('customer', 'tracking'); ?>">All Orders</a></p>

<?php else: ?>

    <?php if (empty($orders)): ?>
        <p>You have not placed any orders yet.</p>
    <?php else: ?>
        <table class="data-table">
            <tr>
                <th>Order</th>
                <th>Shop</th>
                <th>Placed</th>
                <th>Delivery</th>
                <th>Total</th>
                <th>Status</th>
                <th></th>
            </tr>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td>#<?php echo (int) $o['order_id']; ?></td>
                    <td><?php echo e($o['shop_name']); ?></td>
                    <td><?php echo e($o['created_at']); ?></td>
                    <td><?php echo $o['fast_delivery'] ? 'Fast' : 'Standard'; ?></td>
                    <td class="num"><?php echo money($o['total_amount']); ?></td>
                    <td><?php echo str_replace('_', ' ', e($o['status'])); ?></td>
                    <td class="cell-actions">
                        <a href="<?php echo url('customer', 'tracking', ['order_id' => $o['order_id']]); ?>">Track</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

<?php endif; ?>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url('customer', 'orders'); ?>">Manage Orders</a>
    <a class="nav-link" href="<?php echo url('customer', 'dashboard'); ?>">Back to Dashboard</a>
</nav>
