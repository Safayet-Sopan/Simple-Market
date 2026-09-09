<h1>Welcome, <?php echo e($_SESSION['full_name']); ?></h1>

<div class="stats">
    <div class="stat-card">
        <p class="stat-label">Active Orders</p>
        <h2 class="stat-value"><?php echo (int) $counts['active_orders']; ?></h2>
        <a class="stat-link" href="<?php echo url('customer', 'orders'); ?>">Track</a>
    </div>
    <div class="stat-card <?php echo $counts['awaiting_feedback'] > 0 ? 'alert' : ''; ?>">
        <p class="stat-label">Awaiting Your Feedback</p>
        <h2 class="stat-value"><?php echo (int) $counts['awaiting_feedback']; ?></h2>
        <a class="stat-link" href="<?php echo url('customer', 'feedback'); ?>">Review</a>
    </div>
    <div class="stat-card <?php echo $counts['open_bids'] > 0 ? 'alert' : ''; ?>">
        <p class="stat-label">Bids Needing You</p>
        <h2 class="stat-value"><?php echo (int) $counts['open_bids']; ?></h2>
        <a class="stat-link" href="<?php echo url('customer', 'offers'); ?>">Open</a>
    </div>
</div>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url('customer', 'search'); ?>">Browse Products</a>
    <a class="nav-link" href="<?php echo url('customer', 'tracking'); ?>">Order Tracking</a>
    <a class="nav-link" href="<?php echo url('customer', 'rating'); ?>">Rate a Shop</a>
    <a class="nav-link" href="<?php echo url('customer', 'chat'); ?>">Order Chat</a>
</nav>
