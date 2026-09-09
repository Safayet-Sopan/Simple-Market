<h1>Change Password</h1>

<?php if ($successMsg): ?>
    <p class="success"><?php echo e($successMsg); ?></p>
<?php endif; ?>

<form class="form-card" method="POST" action="<?php echo url($role, 'password'); ?>">
    <?php csrf_field(); ?>
    <label>Current Password</label>
    <input class="field" type="password" name="current_password"
           data-label="Current password" data-required>
    <span class="error"><?php echo e($currentErr); ?></span>

    <label>New Password</label>
    <input class="field" type="password" name="new_password"
           data-label="New password" data-required data-password>
    <span class="error"><?php echo e($newErr); ?></span>

    <label>Confirm New Password</label>
    <input class="field" type="password" name="confirm_password"
           data-label="Confirm password" data-required data-match="new_password"
           data-match-message="Passwords do not match">
    <span class="error"><?php echo e($confirmErr); ?></span>

    <button class="btn" type="submit">Update Password</button>
</form>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url($role, 'profile'); ?>">Back to Profile</a>
    <a class="nav-link" href="<?php echo url($role, 'dashboard'); ?>">Back to Dashboard</a>
</nav>
