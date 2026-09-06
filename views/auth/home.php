<div class="home-bar">
    <span class="brand">SimpleMarket</span>
    <span class="home-locale">Dhaka, Bangladesh</span>
    <span class="home-bar-actions">
        <a class="btn btn-secondary" href="<?php echo url('login'); ?>">Log in</a>
        <a class="btn" href="<?php echo url('register'); ?>">Create account</a>
    </span>
</div>

<div class="home-hero">
    <div class="home-hero-copy">
        <h1 class="home-title">A local marketplace with its own riders.</h1>
        <p class="home-lede">
            SimpleMarket connects Dhaka's home kitchens, university merch stalls and small
            boutiques with the people buying from them — and delivers the order itself, through
            an in-house rider network. Sellers set their own prices and take bids. Customers
            track every order to the door.
        </p>
        <div class="home-cta">
            <a class="btn" href="<?php echo url('register'); ?>">Start selling</a>
            <a class="btn btn-secondary" href="<?php echo url('register'); ?>">Shop as a customer</a>
        </div>
    </div>

    <form class="home-login" method="POST" action="<?php echo url('login'); ?>">
        <?php csrf_field(); ?>
        <span class="home-login-title">Log in</span>

        <div class="home-field">
            <label for="home-email">Email</label>
            <input class="field" type="text" name="email" id="home-email" placeholder="you@example.com">
        </div>

        <div class="home-field">
            <label for="home-password">Password</label>
            <input class="field" type="password" name="password" id="home-password" placeholder="••••••••">
        </div>

        <button class="btn" type="submit">Log in</button>
        <p class="home-login-note">
            No account yet? <a href="<?php echo url('register'); ?>">Register as a customer,
            seller or rider</a>.
        </p>
    </form>
</div>

<div class="home-roles">
    <div class="home-role">
        <span class="home-role-name">Customer</span>
        <p>Browse every approved shop, bid below the listed price, pick standard or fast
           delivery, then track the order and review what arrived.</p>
    </div>
    <div class="home-role">
        <span class="home-role-name">Seller</span>
        <p>List products with photos and stock, get warned before you run out, counter a
           customer's bid, and choose which payment methods your shop accepts.</p>
    </div>
    <div class="home-role">
        <span class="home-role-name">Rider</span>
        <p>Claim available deliveries, move them through to delivered, message the customer
           on the way, and watch your earnings add up per run.</p>
    </div>
    <div class="home-role">
        <span class="home-role-name">Admin</span>
        <p>Approve shop applications, manage every account, set per-seller commission and
           read the sales the platform is actually doing.</p>
    </div>
</div>

<div class="home-figures">
    <div class="stat-card">
        <span class="stat-label">Approved shops</span>
        <span class="stat-value"><?php echo number_format($figures['shops']); ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Products listed</span>
        <span class="stat-value"><?php echo number_format($figures['products']); ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Orders delivered</span>
        <span class="stat-value"><?php echo number_format($figures['delivered']); ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Riders on the network</span>
        <span class="stat-value"><?php echo number_format($figures['riders']); ?></span>
    </div>
</div>

<div class="home-footer">
    <span><?php echo SITE_NAME; ?> — a student project, not a live storefront.</span>
    <span class="home-footer-note">Cash, bKash, Nagad and bank transfer, confirmed by hand.</span>
</div>
