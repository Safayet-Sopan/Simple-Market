<h1>Commission Calculator</h1>

<?php if ($successMsg): ?>
    <p class="success"><?php echo e($successMsg); ?></p>
<?php endif; ?>
<?php if ($actionErr): ?>
    <p class="error"><?php echo e($actionErr); ?></p>
<?php endif; ?>

<div class="stats">
    <div class="stat-card">
        <p class="stat-label">Commission Earned</p>
        <h2 class="stat-value"><?php echo money($totals['earned']); ?></h2>
        <p class="stat-caption">from <?php echo (int) $totals['delivered_orders']; ?> delivered order(s)</p>
    </div>
    <div class="stat-card">
        <p class="stat-label">Commission In Progress</p>
        <h2 class="stat-value"><?php echo money($totals['pending']); ?></h2>
        <p class="stat-caption">orders not yet delivered</p>
    </div>
</div>

<h2>Quick Estimate</h2>
<p class="notice">Work out the platform cut on an order value before committing to a rate.</p>
<div class="calc">
    <label class="calc-label">Order subtotal ৳</label>
    <input class="calc-input" type="text" id="calc_amount" value="1000">
    <label class="calc-label">Commission rate %</label>
    <input class="calc-input" type="text" id="calc_rate" value="10">
</div>
<p class="calc-readout">Platform commission: <strong id="calc_commission">৳0.00</strong>
    | Seller receives: <strong id="calc_payout">৳0.00</strong></p>

<h2>Per-Seller Breakdown</h2>
<p class="notice">Commission is stored on each order at checkout, so changing a rate
    affects new orders only — earned totals below never change retroactively.</p>

<?php if (empty($sellers)): ?>
    <p>No approved sellers yet.</p>
<?php else: ?>
    <table class="data-table">
        <tr>
            <th>Shop</th>
            <th>Owner</th>
            <th>Delivered Orders</th>
            <th>Gross Sales</th>
            <th>Commission Earned</th>
            <th>Current Rate</th>
            <th>Set New Rate</th>
        </tr>
        <?php foreach ($sellers as $s): ?>
            <tr>
                <td><?php echo e($s['shop_name']); ?></td>
                <td><?php echo e($s['full_name']); ?></td>
                <td><?php echo (int) $s['delivered_orders']; ?></td>
                <td class="num"><?php echo money($s['gross_sales']); ?></td>
                <td class="num"><?php echo money($s['commission_earned']); ?></td>
                <td><?php echo number_format($s['commission_rate'], 2); ?>%</td>
                <td>
                    <form class="cell-form" method="POST" action="<?php echo url('admin', 'commission'); ?>">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="seller_id" value="<?php echo $s['seller_id']; ?>">
                        <input class="field" type="text" name="commission_rate" size="5"
                               data-label="Commission rate" data-required
                               data-number data-min="0" data-max="100"
                               value="<?php echo e($s['commission_rate']); ?>">
                        <button class="btn" type="submit">Update</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <span class="error"><?php echo e($rateErr); ?></span>
<?php endif; ?>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url('admin', 'sales'); ?>">Sales Overview</a>
    <a class="nav-link" href="<?php echo url('admin', 'dashboard'); ?>">Back to Dashboard</a>
</nav>

<script>
    var amountInput = document.getElementById('calc_amount');
    var rateInput = document.getElementById('calc_rate');
    var commissionOut = document.getElementById('calc_commission');
    var payoutOut = document.getElementById('calc_payout');

    function formatTaka(value) {
        return '৳' + value.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function recalculate() {
        var amount = parseFloat(amountInput.value);
        var rate = parseFloat(rateInput.value);

        if (isNaN(amount) || isNaN(rate) || amount < 0 || rate < 0 || rate > 100) {
            commissionOut.textContent = '—';
            payoutOut.textContent = '—';
            return;
        }

        var commission = amount * (rate / 100);
        commissionOut.textContent = formatTaka(commission);
        payoutOut.textContent = formatTaka(amount - commission);
    }

    amountInput.addEventListener('input', recalculate);
    rateInput.addEventListener('input', recalculate);
    recalculate();
</script>
