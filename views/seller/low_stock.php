<h1>Low Stock Alert</h1>

<?php if (empty($products)): ?>
    <p>All your products are sufficiently stocked. Nothing to worry about right now.</p>
<?php else: ?>
    <p class="notice"><?php echo count($products); ?> product(s) need restocking.</p>

    <table class="data-table">
        <tr>
            <th>Product</th>
            <th>Current Stock</th>
            <th>Threshold</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
        <?php foreach ($products as $p): ?>
            <tr class="<?php echo $p['stock_quantity'] == 0 ? 'critical' : 'alert'; ?>">
                <td><?php echo e($p['product_name']); ?></td>
                <td class="num"><?php echo (int) $p['stock_quantity']; ?></td>
                <td class="num"><?php echo (int) $p['low_stock_threshold']; ?></td>
                <td><?php echo $p['stock_quantity'] == 0 ? 'Out of stock' : 'Low stock'; ?></td>
                <td><a href="<?php echo url('seller', 'products', ['edit' => $p['product_id']]); ?>">Restock</a></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url('seller', 'dashboard'); ?>">Back to Dashboard</a>
</nav>
