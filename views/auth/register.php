<h1>Register</h1>

<form class="form-card" method="POST" action="<?php echo url('register'); ?>" id="register-form">
    <?php csrf_field(); ?>
    <input class="field" type="text" name="full_name" placeholder="Full Name"
           data-label="Full name" data-required data-pattern="^[a-zA-Z-' ]*$"
           data-pattern-message="Only letters and white spaces are allowed."
           value="<?php echo e($full_name); ?>">
    <span class="error"><?php echo e($nameErr); ?></span>

    <input class="field" type="text" name="email" placeholder="Email"
           data-label="Email" data-required data-email
           value="<?php echo e($email); ?>">
    <span class="error"><?php echo e($emailErr); ?></span>

    <input class="field" type="text" name="phone" placeholder="Phone"
           data-label="Phone" data-pattern="^[0-9+\- ]{7,20}$"
           data-pattern-message="Invalid phone number"
           value="<?php echo e($phone); ?>">
    <span class="error"><?php echo e($phoneErr); ?></span>

    <input class="field" type="password" name="password" placeholder="Password"
           data-label="Password" data-required data-password>
    <span class="error"><?php echo e($passwordErr); ?></span>

    <input class="field" type="password" name="confirm_password" placeholder="Confirm Password"
           data-label="Confirm password" data-required data-match="password"
           data-match-message="Passwords do not match">
    <span class="error"><?php echo e($confirmErr); ?></span>

    <select class="field" name="role" id="role-select" data-label="Role" data-required>
        <option value="">Select Role</option>
        <option value="customer" <?php if ($role === 'customer') echo 'selected'; ?>>Customer</option>
        <option value="seller"   <?php if ($role === 'seller')   echo 'selected'; ?>>Seller</option>
        <option value="rider"    <?php if ($role === 'rider')    echo 'selected'; ?>>Rider</option>
    </select>
    <span class="error"><?php echo e($roleErr); ?></span>

    <div class="role-fields" id="seller-fields" style="display:none;">
        <input class="field" type="text" name="shop_name" placeholder="Shop Name"
               data-label="Shop name" data-required-if-role="seller"
               value="<?php echo e($shop_name); ?>">
        <span class="error"><?php echo e($shopErr); ?></span>
    </div>

    <div class="role-fields" id="rider-fields" style="display:none;">
        <input class="field" type="text" name="vehicle_type" placeholder="Vehicle Type"
               data-label="Vehicle type" value="<?php echo e($vehicle_type); ?>">
    </div>

    <button class="btn" type="submit">Register</button>
</form>

<p class="form-footnote">Already have an account? <a href="<?php echo url('login'); ?>">Login</a></p>
