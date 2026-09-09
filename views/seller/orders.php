<h1>Orders</h1>

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
            <a href="<?php echo url('seller', 'orders', ['status' => $f]); ?>"><?php echo str_replace('_', ' ', $f); ?></a>
        <?php endif; ?>
    <?php endforeach; ?>
</p>

<?php if (!$seller_id): ?>
    <p class="error">No seller profile found for this account.</p>
<?php elseif (empty($orders)): ?>
    <p>No orders to show for this filter.</p>
<?php else: ?>
    <table class="data-table">
        <tr>
            <th>Order</th>
            <th>Customer</th>
            <th>Items</th>
            <th>Delivery</th>
            <th>Payment</th>
            <th>You Receive</th>
            <th>Rider</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
        <?php foreach ($orders as $o): ?>
            <?php $net = $o['subtotal'] - $o['commission_amount']; ?>
            <tr class="<?php echo $o['status'] === 'pending' ? 'alert' : 'notice'; ?>">
                <td>
                    #<?php echo (int) $o['order_id']; ?><br>
                    <small><?php echo e($o['created_at']); ?></small>
                </td>
                <td>
                    <?php echo e($o['customer_name']); ?><br>
                    <small><?php echo e($o['customer_phone']); ?></small><br>
                    <small><?php echo e($o['delivery_address']); ?></small>
                </td>
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
                <td>
                    <?php echo money($net); ?><br>
                    <small>after <?php echo money($o['commission_amount']); ?> commission</small>
                </td>
                <td><?php echo $o['rider_name'] ? e($o['rider_name']) : 'Unassigned'; ?></td>
                <td><?php echo str_replace('_', ' ', e($o['status'])); ?></td>
                <td>
                    <?php if (isset($allowed_transitions[$o['status']])): ?>
                        <form class="action-form" method="POST"
                              action="<?php echo url('seller', 'orders', ['status' => $filter]); ?>">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="order_id" value="<?php echo $o['order_id']; ?>">
                            <?php foreach ($allowed_transitions[$o['status']] as $next): ?>
                                <button class="btn" type="submit" name="action" value="<?php echo $next; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $next)); ?>
                                </button>
                            <?php endforeach; ?>
                        </form>
                    <?php endif; ?>

                    <?php if ($o['payment_status'] === 'unpaid' && $o['status'] !== 'cancelled'): ?>
                        <form class="action-form" method="POST"
                              action="<?php echo url('seller', 'orders', ['status' => $filter]); ?>">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="order_id" value="<?php echo $o['order_id']; ?>">
                            <button class="btn" type="submit" name="action" value="mark_paid">Mark Paid</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($o['status'] === 'preparing'): ?>
                        <small class="notice">Waiting for a rider to pick this up.</small>
                    <?php endif; ?>

                    <a href="<?php echo url('seller', 'chat', ['order_id' => $o['order_id']]); ?>">Chat</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url('seller', 'bidding'); ?>">Price Bidding</a>
    <a class="nav-link" href="<?php echo url('seller', 'payments'); ?>">Payment Methods</a>
    <a class="nav-link" href="<?php echo url('seller', 'dashboard'); ?>">Back to Dashboard</a>
</nav>
