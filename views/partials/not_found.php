<h1>Page not found</h1>
<p class="error">No route matches <code><?php echo e($attempted); ?></code>.</p>
<p class="notice"><a href="<?php echo url(current_role() ?: 'home', 'dashboard'); ?>">Back to your dashboard</a></p>
