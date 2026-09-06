<?php

$page   = $_GET['page']   ?? 'home';
$action = $_GET['action'] ?? 'index';

$GLOBALS['route_action'] = $action;

if ($page === 'ajax') {
    define('CSRF_JSON_RESPONSE', true);
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

require_once __DIR__ . '/helpers/view_helper.php';
require_once __DIR__ . '/helpers/validation_helper.php';
require_once __DIR__ . '/helpers/functions_helper.php';
require_once __DIR__ . '/helpers/auth_helper.php';

foreach ([
    'notification_model', 'user_model', 'product_model', 'order_model',
    'offer_model', 'review_model', 'message_model', 'earning_model',
    'note_model', 'report_model',
] as $model) {
    require_once __DIR__ . '/models/' . $model . '.php';
}

foreach ([
    'auth_controller', 'account_controller', 'admin_controller', 'seller_controller',
    'customer_controller', 'rider_controller', 'ajax_controller',
] as $controller) {
    require_once __DIR__ . '/controllers/' . $controller . '.php';
}

switch ($page) {

    case 'home':
        auth_home();
        break;

    case 'login':
        auth_login();
        break;

    case 'register':
        auth_register();
        break;

    case 'logout':
        auth_logout();
        break;

    case 'admin':
        require_role('admin');
        admin_dispatch($action);
        break;

    case 'seller':
        require_role('seller');
        seller_dispatch($action);
        break;

    case 'customer':
        require_role('customer');
        customer_dispatch($action);
        break;

    case 'rider':
        require_role('rider');
        rider_dispatch($action);
        break;

    case 'ajax':
        ajax_dispatch($action);
        break;

    default:
        redirect_to('home');
}
