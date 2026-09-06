<?php

if (!defined('SITE_NAME')) {

    define('SITE_NAME', 'SimpleMarket');
    define('BASE_URL', '/SimpleMarket/');

    define('UPLOAD_PATH', 'uploads/');

    define('STANDARD_DELIVERY_FEE', 30.00);
    define('FAST_DELIVERY_FEE', 70.00);

    define('RIDER_EARNING_RATE', 0.80);
}

$PAYMENT_METHODS = [
    'cod'   => 'Cash on Delivery',
    'bkash' => 'bKash (manual, send to the shop number)',
    'nagad' => 'Nagad (manual, send to the shop number)',
    'bank'  => 'Bank Transfer',
];
