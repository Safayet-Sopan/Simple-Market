<h1>Search</h1>

<form class="filter-bar" method="GET" action="<?php echo BASE_URL; ?>index.php">
    <input type="hidden" name="page" value="seller">
    <input type="hidden" name="action" value="search">
    <select class="field" name="search_type">
        <option value="products" <?php echo ($search_type === 'products') ? 'selected' : ''; ?>>My Products</option>
        <option value="orders"   <?php echo ($search_type === 'orders')   ? 'selected' : ''; ?>>My Orders</option>
    </select>
    <input class="field" type="text" name="keyword" placeholder="Search by name or order ID"
           value="<?php echo e($keyword); ?>">
    <button class="btn" type="submit">Search</button>
</form>

<?php if ($hasSearched && $search_type === 'products'): ?>
    <?php if (empty($products)): ?>
        <p>No products found.</p>
    <?php else: ?>
        <table class="data-table">
            <tr><th>Product</th><th>Price</th><th>Stock</th><th>Status</th></tr>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td><?php echo e($p['product_name']); ?></td>
                    <td class="num"><?php echo money($p['price']); ?></td>
                    <td><?php echo (int) $p['stock_quantity']; ?></td>
                    <td><?php echo e($p['status']); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
<?php elseif ($hasSearched && $search_type === 'orders'): ?>
    <?php if (empty($orders)): ?>
        <p>No orders found.</p>
    <?php else: ?>
        <table class="data-table">
            <tr><th>Order ID</th><th>Customer</th><th>Status</th><th>Total</th><th>Date</th></tr>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td>#<?php echo (int) $o['order_id']; ?></td>
                    <td><?php echo e($o['customer_name']); ?></td>
                    <td><?php echo e($o['status']); ?></td>
                    <td class="num"><?php echo money($o['total_amount']); ?></td>
                    <td><?php echo e($o['created_at']); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
<?php endif; ?>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url('seller', 'dashboard'); ?>">Back to Dashboard</a>
</nav>
