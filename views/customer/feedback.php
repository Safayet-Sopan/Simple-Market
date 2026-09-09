<h1>Product Feedback</h1>

<?php if ($successMsg): ?>
    <p class="success"><?php echo e($successMsg); ?></p>
<?php endif; ?>
<?php if ($actionErr): ?>
    <p class="error"><?php echo e($actionErr); ?></p>
<?php endif; ?>

<h2>Waiting for Your Review</h2>

<?php if (empty($pending_reviews)): ?>
    <p>Nothing to review. Reviews unlock once an order is delivered.</p>
<?php else: ?>
    <?php foreach ($pending_reviews as $pr): ?>
        <table class="review-list">
            <tr>
                <td>
                    <strong><?php echo e($pr['product_name']); ?></strong><br>
                    from <?php echo e($pr['shop_name']); ?>
                    — order #<?php echo (int) $pr['order_id']; ?>
                    (<?php echo (int) $pr['quantity']; ?> x <?php echo money($pr['unit_price']); ?>)
                </td>
                <td>
                    <form class="cell-form" method="POST" action="<?php echo url('customer', 'feedback'); ?>">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="order_id" value="<?php echo $pr['order_id']; ?>">
                        <input type="hidden" name="product_id" value="<?php echo $pr['product_id']; ?>">

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

                        <button class="btn" type="submit">Submit Review</button>
                    </form>
                    <span class="error"><?php echo e($ratingErr); ?></span>
                    <span class="error"><?php echo e($commentErr); ?></span>
                </td>
            </tr>
        </table>
    <?php endforeach; ?>
<?php endif; ?>

<h2>Your Past Reviews</h2>

<?php if (empty($my_reviews)): ?>
    <p>You have not reviewed anything yet.</p>
<?php else: ?>
    <table class="data-table">
        <tr>
            <th>Product</th>
            <th>Order</th>
            <th>Rating</th>
            <th>Comment</th>
            <th>Written</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($my_reviews as $r): ?>
            <tr>
                <td><?php echo e($r['product_name']); ?></td>
                <td>#<?php echo (int) $r['order_id']; ?></td>
                <td><?php echo (int) $r['rating']; ?> / 5</td>
                <td><?php echo e($r['comment']); ?></td>
                <td><?php echo e($r['created_at']); ?></td>
                <td>
                    <form class="action-form" method="POST" action="<?php echo url('customer', 'feedback'); ?>"
                          onsubmit="return confirm('Delete your review of &quot;<?php echo e($r['product_name']); ?>&quot;?');">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="delete_review" value="<?php echo $r['review_id']; ?>">
                        <button class="btn btn-danger" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url('customer', 'rating'); ?>">Rate a Shop</a>
    <a class="nav-link" href="<?php echo url('customer', 'tracking'); ?>">My Orders</a>
    <a class="nav-link" href="<?php echo url('customer', 'dashboard'); ?>">Back to Dashboard</a>
</nav>
