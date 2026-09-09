<h1>Price Bidding</h1>

<?php if ($successMsg): ?>
    <p class="success"><?php echo e($successMsg); ?></p>
<?php endif; ?>
<?php if ($actionErr): ?>
    <p class="error"><?php echo e($actionErr); ?></p>
<?php endif; ?>
<?php if ($counterErr): ?>
    <p class="error"><?php echo e($counterErr); ?></p>
<?php endif; ?>

<?php if (empty($offers)): ?>
    <p>No customer has bid on your products yet.</p>
<?php else: ?>
    <p class="notice"><?php echo (int) $open_count; ?> bid(s) waiting on you.</p>

    <table class="data-table">
        <tr>
            <th>Product</th>
            <th>Listed</th>
            <th>Customer</th>
            <th>Offered</th>
            <th>Your Counter</th>
            <th>Difference</th>
            <th>Status</th>
            <th>Placed</th>
            <th>Action</th>
        </tr>
        <?php foreach ($offers as $o): ?>
            <?php
            $is_open = in_array($o['status'], ['pending', 'countered'], true)
                && $o['converted_order_id'] === null;
            $difference = $o['price'] - $o['offered_price'];
            $discount_pct = $o['price'] > 0 ? ($difference / $o['price']) * 100 : 0;
            ?>
            <tr class="<?php echo $o['status'] === 'pending' ? 'alert' : 'notice'; ?>">
                <td><?php echo e($o['product_name']); ?></td>
                <td class="num"><?php echo money($o['price']); ?></td>
                <td><?php echo e($o['customer_name']); ?></td>
                <td class="num"><?php echo money($o['offered_price']); ?></td>
                <td><?php echo $o['counter_price'] !== null ? money($o['counter_price']) : '—'; ?></td>
                <td>
                    <?php echo money($difference); ?>
                    (<?php echo number_format($discount_pct, 1); ?>% off)
                </td>
                <td>
                    <?php echo e($o['status']); ?>
                    <?php if ($o['converted_order_id'] !== null): ?>
                        — ordered (#<?php echo (int) $o['converted_order_id']; ?>)
                    <?php endif; ?>
                </td>
                <td><?php echo e($o['created_at']); ?></td>
                <td>
                    <?php if ($is_open): ?>
                        <form class="cell-form" method="POST" action="<?php echo url('seller', 'bidding'); ?>">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="offer_id" value="<?php echo $o['offer_id']; ?>">
                            <button class="btn" type="submit" name="action" value="accept">Accept</button>
                            <button class="btn" type="submit" name="action" value="reject">Reject</button>
                            <input class="field" type="text" name="counter_price" size="6" placeholder="Counter ৳">
                            <button class="btn" type="submit" name="action" value="counter">Counter</button>
                        </form>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url('seller', 'products'); ?>">My Products</a>
    <a class="nav-link" href="<?php echo url('seller', 'dashboard'); ?>">Back to Dashboard</a>
</nav>
