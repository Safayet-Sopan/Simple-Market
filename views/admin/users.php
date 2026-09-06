<h1>Manage Users</h1>

<?php if ($successMsg): ?>
    <p class="success"><?php echo e($successMsg); ?></p>
<?php endif; ?>
<?php if ($actionErr): ?>
    <p class="error"><?php echo e($actionErr); ?></p>
<?php endif; ?>

<h2><?php echo $editing_user ? 'Edit Account' : 'Create Account'; ?></h2>

<form class="form-card" id="user-form" method="POST" action="<?php echo url('admin', 'users'); ?>">
    <?php csrf_field(); ?>
    <input type="hidden" name="user_id" value="<?php echo e($editing_user['user_id'] ?? ''); ?>">

    <label>Full Name</label>
    <input class="field" type="text" name="full_name"
           data-label="Full name" data-required data-pattern="^[a-zA-Z-' ]*$"
           data-pattern-message="Only letters and white spaces are allowed."
           value="<?php echo e($full_name); ?>">
    <span class="error"><?php echo e($nameErr); ?></span>

    <label>Email</label>
    <input class="field" type="text" name="email"
           data-label="Email" data-required data-email
           value="<?php echo e($email); ?>">
    <span class="error"><?php echo e($emailErr); ?></span>

    <label>Phone (optional)</label>
    <input class="field" type="text" name="phone"
           data-label="Phone" data-pattern="^[0-9+\- ]{7,20}$"
           data-pattern-message="Invalid phone number"
           value="<?php echo e($phone); ?>">
    <span class="error"><?php echo e($phoneErr); ?></span>

    <?php if ($editing_user): ?>
        <p class="notice">
            Role is fixed at <strong><?php echo e($editing_user['role']); ?></strong>.
            Leave the password blank to keep the current one.
        </p>
    <?php else: ?>
        <label>Role</label>
        <select class="field" name="role" id="role-select" data-label="Role" data-required>
            <option value="">Select Role</option>
            <?php foreach ($allowed_roles as $r): ?>
                <option value="<?php echo $r; ?>" <?php if ($role === $r) echo 'selected'; ?>>
                    <?php echo ucfirst($r); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <span class="error"><?php echo e($roleErr); ?></span>

        <div class="role-fields" id="seller-fields">
            <label>Shop Name</label>
            <input class="field" type="text" name="shop_name"
                   data-label="Shop name" data-required-if-role="seller"
                   value="<?php echo e($shop_name); ?>">
            <span class="error"><?php echo e($shopErr); ?></span>
        </div>

        <div class="role-fields" id="rider-fields">
            <label>Vehicle Type</label>
            <input class="field" type="text" name="vehicle_type"
                   data-label="Vehicle type" value="<?php echo e($vehicle_type); ?>">
        </div>
    <?php endif; ?>

    <label>Status</label>
    <select class="field" name="status" data-label="Status" data-required>
        <?php foreach ($allowed_statuses as $s): ?>
            <option value="<?php echo $s; ?>" <?php if ($status === $s) echo 'selected'; ?>>
                <?php echo ucfirst($s); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <span class="error"><?php echo e($statusErr); ?></span>

    <label><?php echo $editing_user ? 'New Password (optional)' : 'Password'; ?></label>
    <input class="field" type="password" name="password"
           data-label="Password" <?php echo $editing_user ? '' : 'data-required data-password'; ?>>
    <span class="error"><?php echo e($passwordErr); ?></span>

    <label>Confirm Password</label>
    <input class="field" type="password" name="confirm_password"
           data-label="Confirm password" data-match="password"
           data-match-message="Passwords do not match">
    <span class="error"><?php echo e($confirmErr); ?></span>

    <button class="btn" type="submit" name="save_user" value="1">
        <?php echo $editing_user ? 'Save Changes' : 'Create Account'; ?>
    </button>
    <?php if ($editing_user): ?>
        <a class="nav-link" href="<?php echo url('admin', 'users'); ?>">Cancel</a>
    <?php endif; ?>
</form>

<h2>All Accounts</h2>

<form class="filter-bar" method="GET" action="<?php echo BASE_URL; ?>index.php">
    <input type="hidden" name="page" value="admin">
    <input type="hidden" name="action" value="users">
    <input class="field" type="text" name="q" placeholder="Name, email or user id"
           value="<?php echo e($keyword); ?>">
    <select class="field" name="role">
        <option value="">All roles</option>
        <?php foreach ($allowed_roles as $r): ?>
            <option value="<?php echo $r; ?>" <?php if ($role_filter === $r) echo 'selected'; ?>>
                <?php echo ucfirst($r); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Search</button>
    <a class="nav-link" href="<?php echo url('admin', 'users'); ?>">Clear</a>
</form>

<?php if (empty($users)): ?>
    <p>No accounts match that search.</p>
<?php else: ?>
    <table class="data-table">
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Joined</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?php echo (int) $u['user_id']; ?></td>
                <td>
                    <?php echo e($u['full_name']); ?>
                    <?php if ($u['shop_name']): ?>
                        <br><span class="tag"><?php echo e($u['shop_name']); ?>
                        (<?php echo e($u['approval_status']); ?>)</span>
                    <?php endif; ?>
                </td>
                <td><?php echo e($u['email']); ?></td>
                <td><?php echo e($u['role']); ?></td>
                <td>
                    <span class="tag <?php echo $u['status'] === 'active' ? '' : 'tag-alert'; ?>">
                        <?php echo e($u['status']); ?>
                    </span>
                </td>
                <td><?php echo e($u['created_at']); ?></td>
                <td>
                    <a href="<?php echo url('admin', 'users', ['edit' => $u['user_id']]); ?>">Edit</a>
                    <?php if ((int) $u['user_id'] === (int) $admin_id): ?>
                        <span class="notice">(you)</span>
                    <?php else: ?>
                        <form class="action-form" method="POST" action="<?php echo url('admin', 'users'); ?>"
                              onsubmit="return confirm('Permanently delete <?php echo e($u['email']); ?>? This cannot be undone.');">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="delete_user" value="<?php echo $u['user_id']; ?>">
                            <button class="btn btn-danger" type="submit">Delete</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url('admin', 'approvals'); ?>">Seller Approvals</a>
    <a class="nav-link" href="<?php echo url('admin', 'search'); ?>">Search</a>
    <a class="nav-link" href="<?php echo url('admin', 'dashboard'); ?>">Back to Dashboard</a>
</nav>
