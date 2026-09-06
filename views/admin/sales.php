<?php
function sales_sort_link($column, $label, $current_sort, $dir, $date_from, $date_to)
{
    $next_dir = ($current_sort === $column && $dir === 'DESC') ? 'asc' : 'desc';
    $params = ['sort' => $column, 'dir' => $next_dir];
    if ($date_from !== '') { $params['date_from'] = $date_from; }
    if ($date_to !== '')   { $params['date_to']   = $date_to; }

    $marker = ($current_sort === $column) ? ($dir === 'DESC' ? ' v' : ' ^') : '';
    return '<a href="' . e(url('admin', 'sales', $params)) . '">' . e($label . $marker) . '</a>';
}
?>
<h1>Sales Overview of Sellers</h1>

<form class="filter-bar" method="GET" action="<?php echo BASE_URL; ?>index.php">
    <input type="hidden" name="page" value="admin">
    <input type="hidden" name="action" value="sales">

    <label>From</label>
    <input class="field" type="date" name="date_from" value="<?php echo e($date_from); ?>">
    <span class="error"><?php echo e($fromErr); ?></span>

    <label>To</label>
    <input class="field" type="date" name="date_to" value="<?php echo e($date_to); ?>">
    <span class="error"><?php echo e($toErr); ?></span>

    <input type="hidden" name="sort" value="<?php echo e($sort); ?>">
    <input type="hidden" name="dir" value="<?php echo $dir === 'ASC' ? 'asc' : 'desc'; ?>">
    <button class="btn" type="submit">Apply</button>
    <a href="<?php echo url('admin', 'sales'); ?>">Clear</a>
</form>

<div class="stats">
    <div class="stat-card">
        <p class="stat-label">Gross Sales</p>
        <h2 class="stat-value"><?php echo money($totals['gross_sales']); ?></h2>
    </div>
    <div class="stat-card">
        <p class="stat-label">Platform Commission</p>
        <h2 class="stat-value"><?php echo money($totals['commission_earned']); ?></h2>
    </div>
    <div class="stat-card">
        <p class="stat-label">Delivery Fees</p>
        <h2 class="stat-value"><?php echo money($totals['delivery_fees']); ?></h2>
    </div>
    <div class="stat-card">
        <p class="stat-label">Delivered / All Orders</p>
        <h2 class="stat-value"><?php echo (int) $totals['delivered_orders']; ?> / <?php echo (int) $totals['all_orders']; ?></h2>
    </div>
    <div class="stat-card">
        <p class="stat-label">Top Shop</p>
        <h2 class="stat-value"><?php echo e($top_shop); ?></h2>
    </div>
</div>

<?php if (empty($sellers)): ?>
    <p>No approved sellers yet.</p>
<?php else: ?>
    <table class="data-table">
        <tr>
            <th><?php echo sales_sort_link('shop', 'Shop', $sort, $dir, $date_from, $date_to); ?></th>
            <th>Owner</th>
            <th><?php echo sales_sort_link('orders', 'Delivered', $sort, $dir, $date_from, $date_to); ?></th>
            <th>Cancelled</th>
            <th><?php echo sales_sort_link('sales', 'Gross Sales', $sort, $dir, $date_from, $date_to); ?></th>
            <th><?php echo sales_sort_link('commission', 'Commission', $sort, $dir, $date_from, $date_to); ?></th>
            <th>Net Payout</th>
            <th>Avg Order</th>
            <th>Rate</th>
        </tr>
        <?php foreach ($sellers as $s): ?>
            <?php
            $net_payout = $s['gross_sales'] - $s['commission_earned'];
            $avg_order = $s['delivered_orders'] > 0 ? $s['gross_sales'] / $s['delivered_orders'] : 0;
            ?>
            <tr class="<?php echo $s['delivered_orders'] == 0 ? 'notice' : ''; ?>">
                <td><?php echo e($s['shop_name']); ?></td>
                <td><?php echo e($s['full_name']); ?></td>
                <td class="num"><?php echo (int) $s['delivered_orders']; ?></td>
                <td class="num"><?php echo (int) $s['cancelled_orders']; ?></td>
                <td class="num"><?php echo money($s['gross_sales']); ?></td>
                <td class="num"><?php echo money($s['commission_earned']); ?></td>
                <td class="num"><?php echo money($net_payout); ?></td>
                <td class="num"><?php echo money($avg_order); ?></td>
                <td class="num"><?php echo number_format($s['commission_rate'], 2); ?>%</td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url('admin', 'commission'); ?>">Commission Calculator</a>
    <a class="nav-link" href="<?php echo url('admin', 'dashboard'); ?>">Back to Dashboard</a>
</nav>
