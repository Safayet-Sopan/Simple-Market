<?php
function url($page, $action = '', $params = [])
{
    $query = 'page=' . urlencode($page);
    if ($action !== '') {
        $query .= '&action=' . urlencode($action);
    }
    foreach ($params as $key => $value) {
        $query .= '&' . urlencode($key) . '=' . urlencode($value);
    }
    return BASE_URL . 'index.php?' . $query;
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function render($view, $data = [])
{
    extract($data, EXTR_SKIP);

    $view_file = __DIR__ . '/../views/' . $view . '.php';
    if (!is_file($view_file)) {
        http_response_code(500);
        die('View not found: ' . e($view));
    }

    if (!isset($page_title)) { $page_title = SITE_NAME; }
    if (!isset($role_css))   { $role_css = current_role() ?: ''; }
    if (!isset($body_class)) { $body_class = ''; }

    if (!isset($bare))       { $bare = false; }

    require __DIR__ . '/../views/partials/header.php';
    require $view_file;
    require __DIR__ . '/../views/partials/footer.php';
}

function json_response($payload, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function redirect_to($page, $action = '', $params = [])
{
    header('Location: ' . url($page, $action, $params));
    exit;
}
