<?php
function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    echo '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

function csrf_valid()
{
    $held = $_SESSION['csrf_token'] ?? '';
    $sent = $_POST['csrf_token'] ?? '';

    if ($held === '' || !is_string($sent) || $sent === '') {
        return false;
    }
    return hash_equals($held, $sent);
}

function csrf_reject()
{
    http_response_code(403);

    if (defined('CSRF_JSON_RESPONSE')) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid or expired security token']);
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">'
        . '<title>Request blocked</title>'
        . '<link rel="stylesheet" href="' . (defined('BASE_URL') ? BASE_URL : '/SimpleMarket/')
        . 'assets/css/style.css"></head><body class="page">'
        . '<div class="form-card"><h1>Request blocked</h1>'
        . '<p class="error">Your security token was missing or has expired.</p>'
        . '<p class="notice">This usually means the page sat open too long, or the '
        . 'form was submitted from somewhere other than SimpleMarket. Go back, '
        . 'reload the page and try again.</p></div></body></html>';
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !csrf_valid()) {
    csrf_reject();
}
