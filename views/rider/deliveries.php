<h1>My Deliveries</h1>

<?php if ($successMsg): ?>
    <p class="success"><?php echo e($successMsg); ?></p>
<?php endif; ?>
<?php if ($actionErr): ?>
    <p class="error"><?php echo e($actionErr); ?></p>
<?php endif; ?>

<?php if (!$rider_id): ?>
    <p class="error">No rider profile found for this account.</p>
<?php else: ?>

    <h2>Carrying Now</h2>

    <?php if (empty($my_deliveries)): ?>
        <p>You are not carrying anything. Claim one from the list below.</p>
    <?php else: ?>
        <table class="data-table">
            <tr>
                <th>Order</th>
                <th>Pick up from</th>
                <th>Deliver to</th>
                <th>Payment</th>
                <th>Your Cut</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            <?php foreach ($my_deliveries as $d): ?>
                <tr class="<?php echo $d['fast_delivery'] ? 'alert' : 'notice'; ?>">
                    <td>
                        #<?php echo (int) $d['order_id']; ?><br>
                        <?php if ($d['fast_delivery']): ?><span class="tag tag-alert">FAST</span><?php endif; ?>
                    </td>
                    <td>
                        <?php echo e($d['shop_name']); ?><br>
                        <small><?php echo e($d['shop_address']); ?></small>
                    </td>
                    <td>
                        <?php echo e($d['customer_name']); ?><br>
                        <small><?php echo e($d['customer_phone']); ?></small><br>
                        <small><?php echo e($d['delivery_address']); ?></small>
                    </td>
                    <td>
                        <?php echo e($payment_methods[$d['payment_method']] ?? $d['payment_method']); ?><br>
                        <strong><?php echo e($d['payment_status']); ?></strong>
                    </td>
                    <td class="num"><?php echo money($d['delivery_fee'] * RIDER_EARNING_RATE); ?></td>
                    <td><?php echo str_replace('_', ' ', e($d['status'])); ?></td>
                    <td class="cell-actions">
                        <?php if ($d['status'] === 'preparing'): ?>
                            <form class="action-form" method="POST" action="<?php echo url('rider', 'deliveries'); ?>">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="order_id" value="<?php echo $d['order_id']; ?>">
                                <button class="btn" type="submit" name="action" value="out_for_delivery">Start Delivery</button>
                            </form>
                        <?php elseif ($d['status'] === 'out_for_delivery'): ?>
                            <form class="action-form" method="POST" action="<?php echo url('rider', 'deliveries'); ?>">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="order_id" value="<?php echo $d['order_id']; ?>">
                                <button class="btn" type="submit" name="action" value="delivered">Mark Delivered</button>
                            </form>
                        <?php else: ?>
                            <small class="notice">Waiting for the shop to finish preparing.</small>
                        <?php endif; ?>
                        <a href="<?php echo url('rider', 'chat', ['order_id' => $d['order_id']]); ?>">Chat</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <h2>Available to Claim</h2>

    <?php if (empty($available)): ?>
        <p>Nothing waiting for a rider right now.</p>
    <?php else: ?>
        <table class="data-table">
            <tr>
                <th>Order</th>
                <th>Pick up from</th>
                <th>Deliver to</th>
                <th>Your Cut</th>
                <th>Placed</th>
                <th>Action</th>
            </tr>
            <?php foreach ($available as $a): ?>
                <tr class="<?php echo $a['fast_delivery'] ? 'alert' : 'notice'; ?>">
                    <td>
                        #<?php echo (int) $a['order_id']; ?><br>
                        <?php if ($a['fast_delivery']): ?><span class="tag tag-alert">FAST</span><?php endif; ?>
                    </td>
                    <td>
                        <?php echo e($a['shop_name']); ?><br>
                        <small><?php echo e($a['shop_address']); ?></small>
                    </td>
                    <td><?php echo e($a['delivery_address']); ?></td>
                    <td class="num"><?php echo money($a['delivery_fee'] * RIDER_EARNING_RATE); ?></td>
                    <td><?php echo e($a['created_at']); ?></td>
                    <td>
                        <form class="action-form" method="POST" action="<?php echo url('rider', 'deliveries'); ?>">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="order_id" value="<?php echo $a['order_id']; ?>">
                            <button class="btn" type="submit" name="action" value="claim">Claim</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

<?php endif; ?>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url('rider', 'notes'); ?>">Delivery Notes</a>
    <a class="nav-link" href="<?php echo url('rider', 'earnings'); ?>">Earnings</a>
    <a class="nav-link" href="<?php echo url('rider', 'dashboard'); ?>">Back to Dashboard</a>
</nav>
