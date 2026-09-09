<h1>Earnings Calculator</h1>

<?php if (!$rider_id): ?>
    <p class="error">No rider profile found for this account.</p>
<?php else: ?>

    <p class="notice">You keep <?php echo number_format(RIDER_EARNING_RATE * 100, 0); ?>%
        of the delivery fee on every completed delivery. Earnings are recorded the
        moment you mark an order delivered.</p>

    <form class="filter-bar" method="GET" action="<?php echo BASE_URL; ?>index.php">
        <input type="hidden" name="page" value="rider">
        <input type="hidden" name="action" value="earnings">

        <label>From</label>
        <input class="field" type="date" name="date_from" value="<?php echo e($date_from); ?>">
        <span class="error"><?php echo e($fromErr); ?></span>

        <label>To</label>
        <input class="field" type="date" name="date_to" value="<?php echo e($date_to); ?>">
        <span class="error"><?php echo e($toErr); ?></span>

        <button class="btn" type="submit">Apply</button>
        <a class="nav-link" href="<?php echo url('rider', 'earnings'); ?>">Clear</a>
    </form>

    <div class="stats">
        <div class="stat-card">
            <p class="stat-label">You Earned</p>
            <h2 class="stat-value"><?php echo money($summary['total']); ?></h2>
            <p class="stat-caption">across <?php echo (int) $summary['deliveries']; ?> delivery(s)</p>
        </div>
        <div class="stat-card">
            <p class="stat-label">Average per Delivery</p>
            <h2 class="stat-value"><?php echo money($average); ?></h2>
        </div>
        <div class="stat-card">
            <p class="stat-label">Fast Jobs</p>
            <h2 class="stat-value"><?php echo (int) $summary['fast_jobs']; ?></h2>
            <p class="stat-caption">of <?php echo (int) $summary['deliveries']; ?></p>
        </div>
        <div class="stat-card">
            <p class="stat-label">Best Day</p>
            <h2 class="stat-value">
                <?php echo $best_day ? money($best_day['earned']) : '—'; ?>
            </h2>
            <p class="stat-caption">
                <?php echo $best_day
                    ? e($best_day['day']) . ' — ' . (int) $best_day['jobs'] . ' job(s)'
                    : 'no deliveries yet'; ?>
            </p>
        </div>
        <div class="stat-card">
            <p class="stat-label">Platform Share</p>
            <h2 class="stat-value"><?php echo money($platform_share); ?></h2>
            <p class="stat-caption">of <?php echo money($summary['fees_carried']); ?> in fees carried</p>
        </div>
    </div>

    <h2>Every Delivery</h2>

    <?php if (empty($rows)): ?>
        <p>No completed deliveries in this window.</p>
    <?php else: ?>
        <table class="data-table">
            <tr>
                <th>Order</th>
                <th>Shop</th>
                <th>Delivered To</th>
                <th>Type</th>
                <th>Fee</th>
                <th>You Kept</th>
                <th>When</th>
            </tr>
            <?php foreach ($rows as $r): ?>
                <tr class="<?php echo $r['fast_delivery'] ? 'alert' : 'notice'; ?>">
                    <td>#<?php echo (int) $r['order_id']; ?></td>
                    <td><?php echo e($r['shop_name']); ?></td>
                    <td><?php echo e($r['delivery_address']); ?></td>
                    <td><?php echo $r['fast_delivery'] ? 'Fast' : 'Standard'; ?></td>
                    <td class="num"><?php echo money($r['delivery_fee']); ?></td>
                    <td class="num"><?php echo money($r['amount']); ?></td>
                    <td><?php echo e($r['earned_at']); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

<?php endif; ?>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url('rider', 'deliveries'); ?>">My Deliveries</a>
    <a class="nav-link" href="<?php echo url('rider', 'dashboard'); ?>">Back to Dashboard</a>
</nav>
