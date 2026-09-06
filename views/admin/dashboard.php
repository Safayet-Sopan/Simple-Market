<h1>Welcome, <?php echo e($_SESSION['full_name']); ?></h1>

<div class="stats">
    <div class="stat-card">
        <p class="stat-label">Pending Seller Approvals</p>
        <h2 class="stat-value"><?php echo (int) $stats['pending_sellers']; ?></h2>
        <a class="stat-link" href="<?php echo url('admin', 'approvals'); ?>">Review</a>
    </div>
    <div class="stat-card">
        <p class="stat-label">Active Sellers</p>
        <h2 class="stat-value"><?php echo (int) $stats['active_sellers']; ?></h2>
    </div>
    <div class="stat-card">
        <p class="stat-label">Delivered Orders</p>
        <h2 class="stat-value"><?php echo (int) $stats['total_orders']; ?></h2>
    </div>
    <div class="stat-card">
        <p class="stat-label">Total Revenue</p>
        <h2 class="stat-value"><?php echo money($stats['total_revenue']); ?></h2>
        <a class="stat-link" href="<?php echo url('admin', 'sales'); ?>">View Breakdown</a>
    </div>
</div>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url('admin', 'users'); ?>">Manage Users</a>
    <a class="nav-link" href="<?php echo url('admin', 'commission'); ?>">Commission Calculator</a>
    <a class="nav-link" href="<?php echo url('admin', 'sales'); ?>">Sales Overview</a>
</nav>
