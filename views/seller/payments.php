<h1>Payment Methods</h1>

<?php if ($successMsg): ?>
    <p class="success"><?php echo e($successMsg); ?></p>
<?php endif; ?>

<?php if (!$seller_id): ?>
    <p class="error">No seller profile found for this account.</p>
<?php else: ?>
    <p>Choose what <?php echo e($seller['shop_name']); ?> accepts.
        The customer picks one of these at checkout, then you confirm the money
        arrived from your <a href="<?php echo url('seller', 'orders'); ?>">Orders</a> page.</p>

    <p class="notice">There is no payment gateway. Every method here is settled by
        hand between you and the customer.</p>

    <form class="form-card" method="POST" action="<?php echo url('seller', 'payments'); ?>">
        <?php csrf_field(); ?>
        <?php foreach ($payment_methods as $key => $label): ?>
            <label>
                <input type="checkbox" name="methods[]" value="<?php echo e($key); ?>"
                    <?php echo in_array($key, $selected, true) ? 'checked' : ''; ?>>
                <?php echo e($label); ?>
            </label><br>
        <?php endforeach; ?>

        <span class="error"><?php echo e($methodsErr); ?></span><br>

        <button class="btn" type="submit">Save</button>
    </form>
<?php endif; ?>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url('seller', 'orders'); ?>">Orders</a>
    <a class="nav-link" href="<?php echo url('seller', 'dashboard'); ?>">Back to Dashboard</a>
</nav>
