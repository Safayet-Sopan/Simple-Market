<?php

if (!isset($_SESSION['user_id'])) {
    return;
}

$nav_role = $_SESSION['role'] ?? '';

$nav_items_by_role = [
    'admin' => [
        'Dashboard'     => 'dashboard',
        'Users'         => 'users',
        'Approvals'     => 'approvals',
        'Commission'    => 'commission',
        'Sales'         => 'sales',
        'Search'        => 'search',
        'Notifications' => 'notifications',
        'Profile'       => 'profile',
    ],
    'seller' => [
        'Dashboard'     => 'dashboard',
        'Products'      => 'products',
        'Low stock'     => 'low_stock',
        'Orders'        => 'orders',
        'Offers'        => 'bidding',
        'Search'        => 'search',
        'Notifications' => 'notifications',
        'Profile'       => 'profile',
    ],
    'customer' => [
        'Dashboard'     => 'dashboard',
        'Browse'        => 'search',
        'Orders'        => 'orders',
        'Notifications' => 'notifications',
        'Profile'       => 'profile',
    ],
    'rider' => [
        'Dashboard'     => 'dashboard',
        'Deliveries'    => 'deliveries',
        'Notes'         => 'notes',
        'Earnings'      => 'earnings',
        'Chat'          => 'chat',
        'Search'        => 'search',
        'Notifications' => 'notifications',
        'Profile'       => 'profile',
    ],
];

if (!isset($nav_items_by_role[$nav_role])) {
    return;
}
$nav_items = $nav_items_by_role[$nav_role];

if (!isset($nav_active)) {
    $nav_active = '';
    $nav_current = $GLOBALS['route_action'] ?? '';
    foreach ($nav_items as $nav_label => $nav_action) {
        if ($nav_action === $nav_current) {
            $nav_active = $nav_label;
            break;
        }
    }
}

$nav_unread = notification_unread_count($GLOBALS['conn'], $_SESSION['user_id']);
?>
<header class="site-header role-<?php echo e($nav_role); ?>">
    <a class="brand" href="<?php echo url($nav_role, 'dashboard'); ?>">SimpleMarket</a>
    <span class="role-word"><?php echo e($nav_role); ?></span>

    <nav class="site-nav">
        <?php foreach ($nav_items as $nav_label => $nav_action): ?>
            <?php
            $nav_is_active = ($nav_label === $nav_active);
            $nav_id = ($nav_label === 'Notifications') ? ' id="notifications-link"' : '';
            $nav_text = $nav_label;
            if ($nav_label === 'Notifications' && $nav_unread > 0) {
                $nav_text .= ' (' . $nav_unread . ')';
            }
            ?>
            <a class="nav-link<?php echo $nav_is_active ? ' nav-link-active' : ''; ?>"
               href="<?php echo url($nav_role, $nav_action); ?>"<?php echo $nav_id; ?>><?php
                echo e($nav_text);
            ?></a>
        <?php endforeach; ?>
        <a class="nav-link" href="<?php echo url('logout'); ?>">Log out</a>
    </nav>
</header>
