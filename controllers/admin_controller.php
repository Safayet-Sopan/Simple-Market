<?php

function admin_dispatch($action)
{
    switch ($action) {
        case 'index':
        case 'dashboard':     admin_dashboard();            break;
        case 'users':         admin_users();                break;
        case 'approvals':     admin_approvals();            break;
        case 'commission':    admin_commission();           break;
        case 'sales':         admin_sales();                break;
        case 'search':        admin_search();               break;
        case 'notifications': account_notifications('admin'); break;
        case 'profile':       admin_profile();              break;
        case 'password':      account_change_password('admin'); break;
        default:
            http_response_code(404);
            render('partials/not_found', [
                'page_title' => 'Not found',
                'role_css'   => 'admin',
                'attempted'  => $action,
            ]);
    }
}

function admin_dashboard()
{
    global $conn;
    render('admin/dashboard', [
        'page_title' => 'Admin Dashboard',
        'body_class' => 'page-dashboard',
        'role_css'   => 'admin',
        'stats'      => report_admin_dashboard($conn),
    ]);
}

function admin_profile()
{
    global $conn;

    $nameErr = $phoneErr = "";
    $successMsg = "";
    $user_id = current_user_id();
    $user = user_find($conn, $user_id);

    $full_name = $user['full_name'];
    $phone     = $user['phone'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $full_name = cleanInput($_POST['full_name'] ?? '');
        $nameErr = validate_name($full_name);

        $phone = cleanInput($_POST['phone'] ?? '');
        $phoneErr = validate_phone($phone);

        if (!$nameErr && !$phoneErr) {
            user_update_profile($conn, $user_id, $full_name, $phone);
            $_SESSION['full_name'] = $full_name;
            $successMsg = "Profile updated successfully.";
        }
    }

    render('admin/profile', [
        'page_title' => 'My Profile',
        'body_class' => 'page-profile',
        'role_css'   => 'admin',
        'user'       => $user,
        'full_name'  => $full_name,
        'phone'      => $phone,
        'nameErr'    => $nameErr,
        'phoneErr'   => $phoneErr,
        'successMsg' => $successMsg,
    ]);
}

function admin_search()
{
    global $conn;

    $keyword = cleanInput($_GET['keyword'] ?? '');
    $hasSearched = isset($_GET['keyword']) && $keyword !== '';

    render('admin/search', [
        'page_title'  => 'Search Users',
        'body_class'  => 'page-search',
        'role_css'    => 'admin',
        'keyword'     => $keyword,
        'hasSearched' => $hasSearched,
        'results'     => $hasSearched ? user_search_people($conn, $keyword) : [],
    ]);
}

function admin_approvals()
{
    global $conn;

    $actionErr = "";
    $successMsg = "";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $seller_id = $_POST['seller_id'] ?? '';
        $action    = $_POST['action'] ?? '';

        if (!is_id($seller_id) || !in_array($action, ['approve', 'reject'], true)) {
            $actionErr = "Invalid request.";
        } else {
            $seller = seller_find($conn, (int) $seller_id);
            if (!$seller) {
                $actionErr = "Seller not found.";
            } else {
                $ok = seller_set_approval(
                    $conn, (int) $seller_id, $seller['user_id'],
                    $action === 'approve', $seller['shop_name']
                );
                if ($ok) {
                    $successMsg = "Seller " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully.";
                } else {
                    $actionErr = "Something went wrong. Please try again.";
                }
            }
        }
    }

    $sort = ($_GET['sort'] ?? 'newest') === 'oldest' ? 'oldest' : 'newest';
    $order_by = ($sort === 'oldest') ? 'sp.applied_at ASC' : 'sp.applied_at DESC';

    render('admin/approvals', [
        'page_title' => 'Seller Approvals',
        'body_class' => 'page-seller-approvals',
        'role_css'   => 'admin',
        'pending'    => seller_pending_applications($conn, $order_by),
        'sort'       => $sort,
        'successMsg' => $successMsg,
        'actionErr'  => $actionErr,
    ]);
}

function admin_commission()
{
    global $conn;

    $rateErr = $actionErr = "";
    $successMsg = "";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $seller_id       = $_POST['seller_id'] ?? '';
        $commission_rate = $_POST['commission_rate'] ?? '';

        if (!is_id($seller_id)) {
            $actionErr = "Invalid seller.";
        }

        if ($commission_rate === '') {
            $rateErr = "Commission rate is required";
        } elseif (!is_numeric($commission_rate)) {
            $rateErr = "Commission rate must be a number";
        } elseif ((float)$commission_rate < 0 || (float)$commission_rate > 100) {
            $rateErr = "Commission rate must be between 0 and 100";
        }

        if (!$rateErr && !$actionErr) {
            $rate = round((float) $commission_rate, 2);
            $seller = seller_find($conn, (int) $seller_id);

            if (!$seller) {
                $actionErr = "Seller not found.";
            } elseif (seller_set_commission_rate($conn, (int) $seller_id, $seller['user_id'], $rate)) {
                $successMsg = "Commission rate for '" . $seller['shop_name'] . "' updated to "
                    . number_format($rate, 2) . "%.";
            } else {
                $actionErr = "Something went wrong. Please try again.";
            }
        }
    }

    render('admin/commission', [
        'page_title' => 'Commission Calculator',
        'body_class' => 'page-commission-calculator',
        'role_css'   => 'admin',
        'totals'     => report_commission_totals($conn),
        'sellers'    => report_commission_by_seller($conn),
        'rateErr'    => $rateErr,
        'actionErr'  => $actionErr,
        'successMsg' => $successMsg,
    ]);
}

function admin_sales()
{
    global $conn;

    $fromErr = $toErr = "";
    $date_from = cleanInput($_GET['date_from'] ?? '');
    $date_to   = cleanInput($_GET['date_to'] ?? '');

    if ($date_from !== '' && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $date_from)) {
        $fromErr = "Use the format YYYY-MM-DD";
        $date_from = '';
    }
    if ($date_to !== '' && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $date_to)) {
        $toErr = "Use the format YYYY-MM-DD";
        $date_to = '';
    }

    $sort_columns = [
        'sales'      => 'gross_sales',
        'commission' => 'commission_earned',
        'orders'     => 'delivered_orders',
        'shop'       => 'sp.shop_name',
    ];
    $sort = $_GET['sort'] ?? 'sales';
    if (!array_key_exists($sort, $sort_columns)) {
        $sort = 'sales';
    }
    $dir = (($_GET['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';
    $order_by = $sort_columns[$sort] . ' ' . $dir;

    $sellers = report_seller_sales($conn, $date_from, $date_to, $order_by);

    $top_shop = '—';
    $best_sales = 0;
    foreach ($sellers as $s) {
        if ($s['gross_sales'] > $best_sales) {
            $best_sales = $s['gross_sales'];
            $top_shop = $s['shop_name'];
        }
    }

    render('admin/sales', [
        'page_title' => 'Sales Overview',
        'body_class' => 'page-sales-overview',
        'role_css'   => 'admin',
        'sellers'    => $sellers,
        'totals'     => report_platform_totals($conn, $date_from, $date_to),
        'top_shop'   => $top_shop,
        'date_from'  => $date_from,
        'date_to'    => $date_to,
        'fromErr'    => $fromErr,
        'toErr'      => $toErr,
        'sort'       => $sort,
        'dir'        => $dir,
    ]);
}

function admin_users()
{
    global $conn;

    $allowed_roles    = ['admin', 'seller', 'customer', 'rider'];
    $allowed_statuses = ['active', 'pending', 'suspended'];

    $nameErr = $emailErr = $phoneErr = $passwordErr = $confirmErr = "";
    $roleErr = $shopErr = $statusErr = $actionErr = "";
    $successMsg = "";

    $full_name = $email = $phone = $role = $shop_name = $vehicle_type = "";
    $status = 'active';
    $admin_id = current_user_id();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
        $delete_id = $_POST['delete_user'];

        if (!is_id($delete_id)) {
            $actionErr = "Invalid request.";
        } elseif ((int) $delete_id === (int) $admin_id) {
            $actionErr = "You cannot delete the account you are signed in with.";
        } else {
            $attached = user_order_attachment_count($conn, (int) $delete_id);
            if ($attached > 0) {
                $actionErr = "That account appears on $attached order(s). Deleting it would "
                    . "take those orders with it. Suspend the account instead — it blocks "
                    . "the login and keeps the trading history.";
            } elseif (user_delete($conn, (int) $delete_id) === 1) {
                $successMsg = "Account deleted.";
            } else {
                $actionErr = "No such account.";
            }
        }
    }

    $edit_id = $_GET['edit'] ?? '';
    $editing_user = is_id($edit_id) ? user_find($conn, (int) $edit_id) : null;
    if ($editing_user) {
        $full_name = $editing_user['full_name'];
        $email     = $editing_user['email'];
        $phone     = $editing_user['phone'];
        $role      = $editing_user['role'];
        $status    = $editing_user['status'];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
        $posted_id = $_POST['user_id'] ?? '';
        $is_update = is_id($posted_id);
        $posted_id = $is_update ? (int) $posted_id : 0;

        $full_name = cleanInput($_POST['full_name'] ?? '');
        $nameErr = validate_name($full_name);

        $email = cleanInput($_POST['email'] ?? '');
        $emailErr = validate_email_format($email);
        if (!$emailErr && user_email_taken($conn, $email, $posted_id)) {
            $emailErr = "That email is already registered";
        }

        $phone = cleanInput($_POST['phone'] ?? '');
        $phoneErr = validate_phone($phone);

        $status = cleanInput($_POST['status'] ?? '');
        if (!in_array($status, $allowed_statuses, true)) {
            $statusErr = "Pick a valid status";
        }

        $password = $_POST['password'] ?? '';
        $set_password = ($password !== '');
        if (!$is_update && $password === '') {
            $passwordErr = "Password cannot be empty";
        } elseif ($set_password) {
            $passwordErr = validate_password($password);
            if (!$passwordErr && ($_POST['confirm_password'] ?? '') !== $password) {
                $confirmErr = "Passwords do not match";
            }
        }

        if (!$is_update) {
            $role = cleanInput($_POST['role'] ?? '');
            if ($role === '') {
                $roleErr = "Must select a role";
            } elseif (!in_array($role, $allowed_roles, true)) {
                $roleErr = "Invalid role selected";
            }

            if ($role === 'seller') {
                $shop_name = cleanInput($_POST['shop_name'] ?? '');
                if ($shop_name === '') {
                    $shopErr = "Shop name is required for sellers";
                }
            } elseif ($role === 'rider') {
                $vehicle_type = cleanInput($_POST['vehicle_type'] ?? '');
            }
        }

        if (!$nameErr && !$emailErr && !$phoneErr && !$passwordErr
            && !$confirmErr && !$roleErr && !$shopErr && !$statusErr) {

            if ($is_update) {
                user_admin_update($conn, $posted_id, $full_name, $email, $phone, $status);
                if ($set_password) {
                    user_set_password($conn, $posted_id, $password);
                }
                if ($status !== 'active') {
                    user_revoke_remember_tokens($conn, $posted_id);
                }
                $successMsg = "Account updated.";
                $editing_user = null;
            } else {
                $new_id = user_create($conn, [
                    'full_name'    => $full_name,
                    'email'        => $email,
                    'phone'        => $phone,
                    'password'     => $password,
                    'role'         => $role,
                    'status'       => $status,
                    'shop_name'    => $shop_name,
                    'vehicle_type' => $vehicle_type,
                ]);

                if ($new_id) {
                    notify_user($conn, $new_id, "An administrator created this SimpleMarket account for you.");
                    $successMsg = "Account created for " . $email . ".";
                    $full_name = $email = $phone = $role = $shop_name = $vehicle_type = "";
                    $status = 'active';
                } else {
                    $actionErr = "Could not create that account. Please try again.";
                }
            }
        }
    }

    $keyword = cleanInput($_GET['q'] ?? '');
    $role_filter = $_GET['role'] ?? '';
    if (!in_array($role_filter, $allowed_roles, true)) {
        $role_filter = '';
    }

    render('admin/users', [
        'page_title'       => 'Manage Users',
        'body_class'       => 'page-manage-users',
        'role_css'         => 'admin',
        'users'            => user_search($conn, $keyword, $role_filter),
        'editing_user'     => $editing_user,
        'admin_id'         => $admin_id,
        'allowed_roles'    => $allowed_roles,
        'allowed_statuses' => $allowed_statuses,
        'keyword'          => $keyword,
        'role_filter'      => $role_filter,
        'full_name'        => $full_name,
        'email'            => $email,
        'phone'            => $phone,
        'role'             => $role,
        'status'           => $status,
        'shop_name'        => $shop_name,
        'vehicle_type'     => $vehicle_type,
        'nameErr'          => $nameErr,
        'emailErr'         => $emailErr,
        'phoneErr'         => $phoneErr,
        'passwordErr'      => $passwordErr,
        'confirmErr'       => $confirmErr,
        'roleErr'          => $roleErr,
        'shopErr'          => $shopErr,
        'statusErr'        => $statusErr,
        'actionErr'        => $actionErr,
        'successMsg'       => $successMsg,
    ]);
}
