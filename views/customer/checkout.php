<h1>Place Order</h1>

<h2><?php echo e($product['product_name']); ?></h2>
<p>Sold by <?php echo e($product['shop_name']); ?></p>
<p>Price: <?php echo money($product['price']); ?> |
   In stock: <?php echo (int) $product['stock_quantity']; ?></p>

<?php if ($offerNotice): ?>
    <p class="alert"><?php echo e($offerNotice); ?></p>
<?php endif; ?>

<?php if ($offer_id): ?>
    <p class="success">Accepted bid applied — you pay <?php echo money($unit_price); ?>
        per unit instead of <?php echo money($product['price']); ?>.</p>
<?php endif; ?>

<?php if ($stockErr): ?>
    <p class="error"><?php echo e($stockErr); ?></p>
<?php endif; ?>

<form class="form-card" method="POST" action="<?php echo url('customer', 'checkout'); ?>">
    <?php csrf_field(); ?>
    <input type="hidden" name="product_id" value="<?php echo (int) $product['product_id']; ?>">
    <?php if ($offer_id): ?>
        <input type="hidden" name="offer_id" value="<?php echo (int) $offer_id; ?>">
    <?php endif; ?>

    <label>Quantity</label>
    <input class="field" type="text" name="quantity"
           data-label="Quantity" data-required data-integer data-min="1"
           data-max="<?php echo (int) $product['stock_quantity']; ?>"
           value="<?php echo e($quantity); ?>">
    <span class="error"><?php echo e($quantityErr); ?></span>

    <label>Delivery Address</label>
    <input class="field" type="text" name="delivery_address"
           data-label="Delivery address" data-required
           value="<?php echo e($delivery_address); ?>">
    <span class="error"><?php echo e($addressErr); ?></span>

    <label>Payment Method</label>
    <select class="field" name="payment_method" data-label="Payment method" data-required>
        <option value="">-- Select --</option>
        <?php foreach ($shop_methods as $key => $label): ?>
            <option value="<?php echo e($key); ?>" <?php echo $payment_method === $key ? 'selected' : ''; ?>>
                <?php echo e($label); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <span class="error"><?php echo e($paymentErr); ?></span>

    <label>
        <input type="checkbox" name="fast_delivery" value="1" <?php echo $fast_delivery ? 'checked' : ''; ?>>
        Fast Delivery (+<?php echo money(FAST_DELIVERY_FEE); ?> instead of <?php echo money(STANDARD_DELIVERY_FEE); ?>)
    </label>

    <p>Estimated subtotal: <?php echo money($subtotal_preview); ?> (delivery fee added at checkout)</p>

    <button class="btn" type="submit">Place Order</button>
</form>

<nav class="page-nav">
    <a class="nav-link" href="<?php echo url('customer', 'search'); ?>">Back to Search</a>
</nav>
