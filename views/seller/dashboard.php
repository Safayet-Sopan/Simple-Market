<?php if ($seller && $seller['approval_status'] === 'pending'): ?>
    <p class="notice">Your shop application is still pending admin approval.</p>
<?php endif; ?>

<h1>Welcome, <?php echo e($seller['shop_name'] ?? $_SESSION['full_name']); ?></h1>

<div class="stats">
    <div class="stat-card">
        <p class="stat-label">Products Listed</p>
        <h2 class="stat-value"><?php echo (int) $counts['product_count']; ?></h2>
        <a class="stat-link" href="<?php echo url('seller', 'products'); ?>">Manage</a>
    </div>
    <div class="stat-card <?php echo $counts['low_stock_count'] > 0 ? 'alert' : ''; ?>">
        <p class="stat-label">Low Stock Items</p>
        <h2 class="stat-value"><?php echo (int) $counts['low_stock_count']; ?></h2>
        <a class="stat-link" href="<?php echo url('seller', 'low_stock'); ?>">View</a>
    </div>
    <div class="stat-card">
        <p class="stat-label">Pending Orders</p>
        <h2 class="stat-value"><?php echo (int) $pending_orders; ?></h2>
        <a class="stat-link" href="<?php echo url('seller', 'orders'); ?>">View</a>
    </div>
</div>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url('seller', 'bidding'); ?>">Price Offers</a>
    <a class="nav-link" href="<?php echo url('seller', 'orders'); ?>">Orders</a>
    <a class="nav-link" href="<?php echo url('seller', 'payments'); ?>">Payment Methods</a>
    <a class="nav-link" href="<?php echo url('seller', 'chat'); ?>">Order Chat</a>
</nav>
