<h1>My Profile</h1>

<?php if ($successMsg): ?>
    <p class="success"><?php echo e($successMsg); ?></p>
<?php endif; ?>

<form class="form-card" method="POST" action="<?php echo url('seller', 'profile'); ?>">
    <?php csrf_field(); ?>
    <label>Full Name</label>
    <input class="field" type="text" name="full_name"
           data-label="Full name" data-required data-pattern="^[a-zA-Z-' ]*$"
           data-pattern-message="Only letters and white spaces are allowed."
           value="<?php echo e($full_name); ?>">
    <span class="error"><?php echo e($nameErr); ?></span>

    <label>Email (cannot be changed)</label>
    <input class="field" type="text" value="<?php echo e($user['email']); ?>" disabled>

    <label>Phone</label>
    <input class="field" type="text" name="phone"
           data-label="Phone" data-pattern="^[0-9+\- ]{7,20}$"
           data-pattern-message="Invalid phone number"
           value="<?php echo e($phone); ?>">
    <span class="error"><?php echo e($phoneErr); ?></span>

    <?php if ($seller): ?>
        <h2>Shop Details</h2>

        <label>Shop Name</label>
        <input class="field" type="text" name="shop_name"
               data-label="Shop name" data-required
               value="<?php echo e($shop_name); ?>">
        <span class="error"><?php echo e($shopErr); ?></span>

        <label>Shop Address</label>
        <input class="field" type="text" name="shop_address" value="<?php echo e($shop_address); ?>">

        <label>Business Type</label>
        <input class="field" type="text" name="business_type" value="<?php echo e($business_type); ?>">

        <p class="notice">Approval status:
            <strong><?php echo e($seller['approval_status']); ?></strong>.
            Commission rate: <?php echo number_format($seller['commission_rate'], 2); ?>%
            (set by an administrator).</p>
    <?php else: ?>
        <p class="error">No seller profile is attached to this account. Contact an administrator.</p>
    <?php endif; ?>

    <button class="btn" type="submit">Save Changes</button>
</form>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url('seller', 'password'); ?>">Change Password</a>
    <a class="nav-link" href="<?php echo url('seller', 'dashboard'); ?>">Back to Dashboard</a>
</nav>
