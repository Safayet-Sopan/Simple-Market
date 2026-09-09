<h1>Search My Deliveries</h1>

<form class="filter-bar" method="GET" action="<?php echo BASE_URL; ?>index.php">
    <input type="hidden" name="page" value="rider">
    <input type="hidden" name="action" value="search">
    <input class="field" type="text" name="keyword" placeholder="Address, shop or order #"
           value="<?php echo e($keyword); ?>">
    <button class="btn" type="submit">Search</button>
</form>

<?php if ($hasSearched): ?>
    <?php if (empty($orders)): ?>
        <p>No deliveries match that search.</p>
    <?php else: ?>
        <table class="data-table">
            <tr>
                <th>Order</th>
                <th>Shop</th>
                <th>Customer</th>
                <th>Address</th>
                <th>Fee</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td>#<?php echo (int) $o['order_id']; ?></td>
                    <td><?php echo e($o['shop_name']); ?></td>
                    <td><?php echo e($o['customer_name']); ?></td>
                    <td><?php echo e($o['delivery_address']); ?></td>
                    <td class="num"><?php echo money($o['delivery_fee']); ?></td>
                    <td><?php echo str_replace('_', ' ', e($o['status'])); ?></td>
                    <td><?php echo e($o['created_at']); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
<?php endif; ?>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url('rider', 'dashboard'); ?>">Back to Dashboard</a>
</nav>
