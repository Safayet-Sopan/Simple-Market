<h1>Price Bidding</h1>

<?php if ($successMsg): ?>
    <p class="success"><?php echo e($successMsg); ?></p>
<?php endif; ?>
<?php if ($actionErr): ?>
    <p class="error"><?php echo e($actionErr); ?></p>
<?php endif; ?>

<?php if ($product): ?>
    <h2>Bid on <?php echo e($product['product_name']); ?></h2>
    <p>Sold by <?php echo e($product['shop_name']); ?> —
       listed at <?php echo money($product['price']); ?></p>

    <form class="form-card" method="POST" action="<?php echo url('customer', 'offers'); ?>">
        <?php csrf_field(); ?>
        <input type="hidden" name="product_id" value="<?php echo (int) $product_id; ?>">
        <input type="hidden" name="action" value="bid">

        <label>Your Offer (৳)</label>
        <input class="field" type="text" name="offered_price"
               data-label="Offer" data-required data-number data-min="0.01"
               value="<?php echo e($offered_price); ?>">
        <span class="error"><?php echo e($priceErr); ?></span>

        <p class="notice">Your offer must be below the listed price. The seller can accept,
            decline, or come back with a counter-offer.</p>

        <button class="btn" type="submit">Send Bid</button>
    </form>
<?php endif; ?>

<h2>Your Bids</h2>

<?php if (empty($my_offers)): ?>
    <p>You have not bid on anything yet. Find something on the
       <a href="<?php echo url('customer', 'search'); ?>">browse page</a>.</p>
<?php else: ?>
    <table class="data-table">
        <tr>
            <th>Product</th>
            <th>Shop</th>
            <th>Listed</th>
            <th>You Offered</th>
            <th>Their Counter</th>
            <th>Status</th>
            <th>Placed</th>
            <th>Action</th>
        </tr>
        <?php foreach ($my_offers as $o): ?>
            <?php
            $is_open = in_array($o['status'], ['pending', 'countered'], true)
                && $o['converted_order_id'] === null;
            $can_order = $o['status'] === 'accepted'
                && $o['converted_order_id'] === null
                && $o['product_status'] === 'active'
                && $o['stock_quantity'] > 0;
            ?>
            <tr class="<?php echo $o['status'] === 'countered' ? 'alert' : 'notice'; ?>">
                <td><?php echo e($o['product_name']); ?></td>
                <td><?php echo e($o['shop_name']); ?></td>
                <td class="num"><?php echo money($o['price']); ?></td>
                <td class="num"><?php echo money($o['offered_price']); ?></td>
                <td class="num"><?php echo $o['counter_price'] !== null ? money($o['counter_price']) : '—'; ?></td>
                <td>
                    <?php echo e($o['status']); ?>
                    <?php if ($o['converted_order_id'] !== null): ?>
                        — ordered (#<?php echo (int) $o['converted_order_id']; ?>)
                    <?php endif; ?>
                </td>
                <td><?php echo e($o['created_at']); ?></td>
                <td class="cell-actions">
                    <?php if ($can_order): ?>
                        <a href="<?php echo url('customer', 'checkout', [
                            'product_id' => $o['product_id'], 'offer_id' => $o['offer_id'],
                        ]); ?>">Order at this price</a>
                    <?php endif; ?>

                    <?php if ($o['status'] === 'countered' && $o['converted_order_id'] === null): ?>
                        <form class="action-form" method="POST" action="<?php echo url('customer', 'offers'); ?>">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="offer_id" value="<?php echo $o['offer_id']; ?>">
                            <button class="btn" type="submit" name="action" value="accept_counter">Accept Counter</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($is_open): ?>
                        <form class="action-form" method="POST" action="<?php echo url('customer', 'offers'); ?>">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="offer_id" value="<?php echo $o['offer_id']; ?>">
                            <button class="btn btn-danger" type="submit" name="action" value="withdraw">Withdraw</button>
                        </form>
                    <?php endif; ?>

                    <?php if (!$is_open && !$can_order && $o['converted_order_id'] === null): ?>
                        —
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url('customer', 'search'); ?>">Browse Products</a>
    <a class="nav-link" href="<?php echo url('customer', 'dashboard'); ?>">Back to Dashboard</a>
</nav>
