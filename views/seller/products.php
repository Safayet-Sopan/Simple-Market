<h1>My Products</h1>

<?php if ($seller && $seller['approval_status'] !== 'approved'): ?>
    <p class="notice">Your shop must be approved before your products are visible to customers.</p>
<?php endif; ?>

<?php if ($successMsg): ?>
    <p class="success"><?php echo e($successMsg); ?></p>
<?php endif; ?>
<?php if ($deleteErr): ?>
    <p class="error"><?php echo e($deleteErr); ?></p>
<?php endif; ?>

<h2><?php echo $editing_product ? 'Edit Product' : 'Add New Product'; ?></h2>

<form class="form-card" method="POST" action="<?php echo url('seller', 'products'); ?>"
      enctype="multipart/form-data">
    <?php csrf_field(); ?>
    <input type="hidden" name="product_id" value="<?php echo e($editing_product['product_id'] ?? ''); ?>">

    <label>Product Name</label>
    <input class="field" type="text" name="product_name"
           data-label="Product name" data-required
           value="<?php echo e($product_name); ?>">
    <span class="error"><?php echo e($nameErr); ?></span>

    <label>Description</label>
    <textarea class="field" name="description"><?php echo e($description); ?></textarea>

    <label>Price (৳)</label>
    <input class="field" type="text" name="price"
           data-label="Price" data-required data-number data-min="0.01"
           value="<?php echo e($price); ?>">
    <span class="error"><?php echo e($priceErr); ?></span>

    <label>Stock Quantity</label>
    <input class="field" type="text" name="stock_quantity"
           data-label="Stock quantity" data-required data-integer data-min="0"
           value="<?php echo e($stock_quantity); ?>">
    <span class="error"><?php echo e($stockErr); ?></span>

    <label>Low Stock Threshold</label>
    <input class="field" type="text" name="low_stock_threshold"
           data-label="Low stock threshold" data-integer data-min="0"
           value="<?php echo e($low_stock_threshold); ?>">

    <label>Category</label>
    <select class="field" name="category_id">
        <option value="">Uncategorized</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?php echo $cat['category_id']; ?>"
                <?php echo ((string)$cat['category_id'] === (string)$category_id) ? 'selected' : ''; ?>>
                <?php echo e($cat['category_name']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Product Image</label>
    <input class="field-file" type="file" name="product_image" accept="image/jpeg,image/png,image/webp">
    <span class="error"><?php echo e($imageErr); ?></span>
    <?php if ($existing_image): ?>
        <p>Current image:
            <img class="thumb" src="<?php echo BASE_URL . 'uploads/products/' . e($existing_image); ?>"
                 alt="<?php echo e($product_name); ?>" width="80">
        </p>
    <?php endif; ?>

    <button class="btn" type="submit" name="save_product" value="1">
        <?php echo $editing_product ? 'Update Product' : 'Add Product'; ?>
    </button>
    <?php if ($editing_product): ?>
        <a class="nav-link" href="<?php echo url('seller', 'products'); ?>">Cancel Edit</a>
    <?php endif; ?>
</form>

<hr>

<h2>All Products</h2>

<?php if (empty($products)): ?>
    <p>You haven't added any products yet.</p>
<?php else: ?>
    <table class="data-table">
        <tr>
            <th>Image</th>
            <th>Name</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Status</th>
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
                <td><?php echo e($p['product_name']); ?></td>
                <td class="num"><?php echo money($p['price']); ?></td>
                <td><?php echo (int) $p['stock_quantity']; ?><?php if ($p['stock_quantity'] <= $p['low_stock_threshold']): ?>
                    <span class="tag tag-alert">Low</span><?php endif; ?></td>
                <td><?php echo e($p['status']); ?></td>
                <td>
                    <a href="<?php echo url('seller', 'products', ['edit' => $p['product_id']]); ?>">Edit</a>
                    <form class="action-form" method="POST" action="<?php echo url('seller', 'products'); ?>">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="toggle_status" value="<?php echo $p['product_id']; ?>">
                        <button class="btn" type="submit"><?php echo $p['status'] === 'active' ? 'Deactivate' : 'Reactivate'; ?></button>
                    </form>
                    <form class="action-form" method="POST" action="<?php echo url('seller', 'products'); ?>"
                          onsubmit="return confirm('Delete &quot;<?php echo e($p['product_name']); ?>&quot; permanently? This cannot be undone.');">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="delete_product" value="<?php echo $p['product_id']; ?>">
                        <button class="btn btn-danger" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url('seller', 'low_stock'); ?>">Low Stock</a>
    <a class="nav-link" href="<?php echo url('seller', 'dashboard'); ?>">Back to Dashboard</a>
</nav>
