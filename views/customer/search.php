<h1>Browse Products</h1>

<form class="filter-bar" method="GET" action="<?php echo BASE_URL; ?>index.php">
    <input type="hidden" name="page" value="customer">
    <input type="hidden" name="action" value="search">
    <input class="field" type="text" name="keyword" placeholder="Search products"
           value="<?php echo e($keyword); ?>">
    <select class="field" name="category_id">
        <option value="">All categories</option>
        <?php foreach ($categories as $c): ?>
            <option value="<?php echo $c['category_id']; ?>"
                <?php echo ((string)$c['category_id'] === (string)$category_id) ? 'selected' : ''; ?>>
                <?php echo e($c['category_name']); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Search</button>
    <a class="nav-link" href="<?php echo url('customer', 'search'); ?>">Clear</a>
</form>

<?php if (!$hasSearched): ?>
    <p>Search by name, or pick a category, to see what is on sale.</p>
<?php elseif (empty($products)): ?>
    <p>No products match that search.</p>
<?php else: ?>
    <table class="data-table">
        <tr>
            <th>Image</th>
            <th>Product</th>
            <th>Shop</th>
            <th>Category</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($products as $p): ?>
            <tr>
                <td>
                    <?php if ($p['product_image']): ?>
                        <img class="thumb" width="48" height="48"
                             src="<?php echo BASE_URL . 'uploads/products/' . e($p['product_image']); ?>"
                             alt="<?php echo e($p['product_name']); ?>">
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td>
                    <strong><?php echo e($p['product_name']); ?></strong><br>
                    <small><?php echo e($p['description']); ?></small>
                </td>
                <td><?php echo e($p['shop_name']); ?></td>
                <td><?php echo e($p['category_name'] ?? '—'); ?></td>
                <td class="num"><?php echo money($p['price']); ?></td>
                <td><?php echo (int) $p['stock_quantity']; ?></td>
                <td class="cell-actions">
                    <?php if ($p['stock_quantity'] > 0): ?>
                        <a href="<?php echo url('customer', 'checkout', ['product_id' => $p['product_id']]); ?>">Buy</a>
                        <a href="<?php echo url('customer', 'offers', ['product_id' => $p['product_id']]); ?>">Bid</a>
                    <?php else: ?>
                        <span class="tag tag-alert">Out of stock</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url('customer', 'dashboard'); ?>">Back to Dashboard</a>
</nav>
