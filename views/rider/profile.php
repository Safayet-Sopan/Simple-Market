<h1>My Profile</h1>

<?php if ($successMsg): ?>
    <p class="success"><?php echo e($successMsg); ?></p>
<?php endif; ?>

<form class="form-card" method="POST" action="<?php echo url('rider', 'profile'); ?>">
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

    <?php if ($rider): ?>
        <h2>Vehicle</h2>

        <label>Vehicle Type</label>
        <input class="field" type="text" name="vehicle_type"
               placeholder="Motorcycle, bicycle, van…"
               value="<?php echo e($vehicle_type); ?>">

        <label>Plate Number</label>
        <input class="field" type="text" name="vehicle_plate" value="<?php echo e($vehicle_plate); ?>">

        <label>Capacity</label>
        <input class="field" type="text" name="vehicle_capacity"
               placeholder="e.g. 2 large bags"
               value="<?php echo e($vehicle_capacity); ?>">

        <label>Availability</label>
        <select class="field" name="availability_status">
            <?php foreach ($allowed_availability as $a): ?>
                <option value="<?php echo $a; ?>" <?php if ($availability === $a) echo 'selected'; ?>>
                    <?php echo ucfirst($a); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="notice">Claiming a delivery sets you to busy automatically, and completing
            one sets you back to available.</p>
    <?php else: ?>
        <p class="error">No rider profile is attached to this account. Contact an administrator.</p>
    <?php endif; ?>

    <button class="btn" type="submit">Save Changes</button>
</form>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url('rider', 'password'); ?>">Change Password</a>
    <a class="nav-link" href="<?php echo url('rider', 'dashboard'); ?>">Back to Dashboard</a>
</nav>
