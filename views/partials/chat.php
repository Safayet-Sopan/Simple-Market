<h1>Order Chat</h1>

<?php if ($accessErr): ?>
    <p class="error"><?php echo e($accessErr); ?></p>
<?php endif; ?>

<p class="notice">Messages are tied to a single order. The customer, the shop and the
    assigned rider can all see this thread. It refreshes every few seconds.</p>

<h2>Your Threads</h2>

<?php if (empty($threads)): ?>
    <p>No orders to talk about yet.</p>
<?php else: ?>
    <table class="data-table">
        <tr>
            <th>Order</th>
            <th>Shop</th>
            <th>Customer</th>
            <th>Status</th>
            <th>Messages</th>
            <th></th>
        </tr>
        <?php foreach ($threads as $t): ?>
            <tr class="<?php echo $t['unread'] > 0 ? 'alert' : 'notice'; ?>">
                <td>#<?php echo (int) $t['order_id']; ?></td>
                <td><?php echo e($t['shop_name']); ?></td>
                <td><?php echo e($t['customer_name']); ?></td>
                <td><?php echo str_replace('_', ' ', e($t['status'])); ?></td>
                <td>
                    <?php echo (int) $t['total']; ?>
                    <?php if ($t['unread'] > 0): ?>
                        — <strong><?php echo (int) $t['unread']; ?> new</strong>
                    <?php endif; ?>
                </td>
                <td class="cell-actions">
                    <?php if ((int)$t['order_id'] === $order_id): ?>
                        <strong>open</strong>
                    <?php else: ?>
                        <a href="<?php echo url($role, 'chat', ['order_id' => $t['order_id']]); ?>">Open</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<?php if ($order): ?>
    <h2>Order #<?php echo (int) $order['order_id']; ?> — <?php echo e($order['shop_name']); ?></h2>
    <p>Status: <?php echo str_replace('_', ' ', e($order['status'])); ?></p>

    <div class="chat-panel" id="chat-messages"
         data-order-id="<?php echo (int) $order['order_id']; ?>"
         data-poll-url="<?php echo e(url('ajax', 'poll_messages')); ?>"
         data-send-url="<?php echo e(url('ajax', 'send_message')); ?>"></div>

    <p class="chat-status" id="chat-status"></p>

    <form class="chat-composer" id="chat-form" data-csrf="<?php echo e(csrf_token()); ?>">
        <input class="chat-input" type="text" id="chat-input" placeholder="Type a message" autocomplete="off">
        <button class="btn" type="submit">Send</button>
    </form>

    <script src="<?php echo BASE_URL; ?>assets/js/chat_poll.js"></script>
<?php elseif (!$accessErr): ?>
    <p>Pick a thread above to start talking.</p>
<?php endif; ?>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url($role, 'dashboard'); ?>">Back to Dashboard</a>
</nav>
