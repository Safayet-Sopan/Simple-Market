<h1>Search Users</h1>

<form class="filter-bar" method="GET" action="<?php echo BASE_URL; ?>index.php">
    <input type="hidden" name="page" value="admin">
    <input type="hidden" name="action" value="search">
    <input class="field" type="text" name="keyword"
           placeholder="Search by name, email, or shop name"
           value="<?php echo e($keyword); ?>">
    <button class="btn" type="submit">Search</button>
</form>

<?php if ($hasSearched): ?>
    <?php if (empty($results)): ?>
        <p>No results found.</p>
    <?php else: ?>
        <table class="data-table">
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Shop</th>
                <th>Status</th>
            </tr>
            <?php foreach ($results as $r): ?>
                <tr>
                    <td><?php echo e($r['full_name']); ?></td>
                    <td><?php echo e($r['email']); ?></td>
                    <td><?php echo e($r['role']); ?></td>
                    <td><?php echo e($r['shop_name'] ?? '—'); ?></td>
                    <td><?php echo e($r['status']); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
<?php endif; ?>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url('admin', 'dashboard'); ?>">Back to Dashboard</a>
</nav>
