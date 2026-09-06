<h1>My Profile</h1>

<?php if ($successMsg): ?>
    <p class="success"><?php echo e($successMsg); ?></p>
<?php endif; ?>

<form class="form-card" method="POST" action="<?php echo url('admin', 'profile'); ?>">
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

    <button class="btn" type="submit">Save Changes</button>
</form>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url('admin', 'password'); ?>">Change Password</a>
    <a class="nav-link" href="<?php echo url('admin', 'dashboard'); ?>">Back to Dashboard</a>
</nav>
