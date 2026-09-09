<h1>Welcome, <?php echo e($_SESSION['full_name']); ?></h1>
<p class="meta">Vehicle: <?php echo e($rider['vehicle_type'] ?? 'Not set'); ?>
   — Status: <?php echo e($rider['availability_status'] ?? 'offline'); ?></p>

<div class="stats">
    <div class="stat-card">
        <p class="stat-label">Active Deliveries</p>
        <h2 class="stat-value"><?php echo (int) $active_deliveries; ?></h2>
        <a class="stat-link" href="<?php echo url('rider', 'deliveries'); ?>">View</a>
    </div>
    <div class="stat-card">
        <p class="stat-label">Total Earnings</p>
        <h2 class="stat-value"><?php echo money($total_earnings); ?></h2>
        <a class="stat-link" href="<?php echo url('rider', 'earnings'); ?>">Breakdown</a>
    </div>
</div>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url('rider', 'profile'); ?>">Update Profile</a>
    <a class="nav-link" href="<?php echo url('rider', 'deliveries'); ?>">Deliveries</a>
    <a class="nav-link" href="<?php echo url('rider', 'notes'); ?>">Delivery Notes</a>
    <a class="nav-link" href="<?php echo url('rider', 'chat'); ?>">Order Chat</a>
</nav>
