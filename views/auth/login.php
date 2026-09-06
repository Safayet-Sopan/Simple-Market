<h1>Login</h1>

<?php if ($flash): ?>
    <p class="<?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></p>
<?php endif; ?>

<?php if ($loginErr): ?>
    <p class="error"><?php echo e($loginErr); ?></p>
<?php endif; ?>

<form class="form-card" id="login-form" method="POST" action="<?php echo url('login'); ?>">
    <?php csrf_field(); ?>
    <input class="field" type="text" name="email" placeholder="Email"
           data-label="Email" data-required data-email
           value="<?php echo e($email); ?>">
    <span class="error"><?php echo e($emailErr); ?></span>

    <input class="field" type="password" name="password" placeholder="Password"
           data-label="Password" data-required>
    <span class="error"><?php echo e($passwordErr); ?></span>

    <label class="check-label">
        <input type="checkbox" name="remember" value="1" <?php echo $remember_checked ? 'checked' : ''; ?>>
        Keep me logged in on this device for <?php echo REMEMBER_DAYS; ?> days
    </label>

    <button class="btn" type="submit">Login</button>
</form>

<p class="form-footnote">Don't have an account? <a href="<?php echo url('register'); ?>">Register</a></p>
