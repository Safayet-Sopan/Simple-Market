<h1>Rate a Shop</h1>

<?php if ($successMsg): ?>
    <p class="success"><?php echo e($successMsg); ?></p>
<?php endif; ?>
<?php if ($actionErr): ?>
    <p class="error"><?php echo e($actionErr); ?></p>
<?php endif; ?>

<p class="notice">Shop ratings are separate from product reviews — this one is about the
    service: packaging, accuracy, how the shop handled the order.</p>

<h2>Shops Waiting for a Rating</h2>

<?php if (empty($pending_ratings)): ?>
    <p>Nothing to rate. A shop becomes rateable once its order is delivered.</p>
<?php else: ?>
    <?php foreach ($pending_ratings as $pr): ?>
        <table class="review-list">
            <tr>
                <td>
                    <strong><?php echo e($pr['shop_name']); ?></strong><br>
                    order #<?php echo (int) $pr['order_id']; ?>
                    — <?php echo money($pr['total_amount']); ?>
                    on <?php echo e($pr['created_at']); ?>
                </td>
                <td>
                    <form class="cell-form" method="POST" action="<?php echo url('customer', 'rating'); ?>">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="order_id" value="<?php echo $pr['order_id']; ?>">

                        <label>Rating</label>
                        <select class="field" name="rating" data-label="Rating" data-required>
                            <option value="">--</option>
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?> star<?php echo $i > 1 ? 's' : ''; ?></option>
                            <?php endfor; ?>
                        </select>

                        <label>Comment (optional)</label>
                        <input class="field" type="text" name="comment" size="40"
                               data-label="Comment" data-maxlength="500">

                        <button class="btn" type="submit">Submit Rating</button>
                    </form>
                    <span class="error"><?php echo e($ratingErr); ?></span>
                    <span class="error"><?php echo e($commentErr); ?></span>
                </td>
            </tr>
        </table>
    <?php endforeach; ?>
<?php endif; ?>

<h2>Your Past Ratings</h2>

<?php if (empty($my_ratings)): ?>
    <p>You have not rated a shop yet.</p>
<?php else: ?>
    <table class="data-table">
        <tr><th>Shop</th><th>Order</th><th>Rating</th><th>Comment</th><th>Written</th></tr>
        <?php foreach ($my_ratings as $r): ?>
            <tr>
                <td><?php echo e($r['shop_name']); ?></td>
                <td>#<?php echo (int) $r['order_id']; ?></td>
                <td><?php echo (int) $r['rating']; ?> / 5</td>
                <td><?php echo e($r['comment']); ?></td>
                <td><?php echo e($r['created_at']); ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url('customer', 'feedback'); ?>">Product Feedback</a>
    <a class="nav-link" href="<?php echo url('customer', 'dashboard'); ?>">Back to Dashboard</a>
</nav>
