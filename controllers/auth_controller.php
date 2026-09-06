<?php

function auth_home()
{
    global $conn;

    try_remember_login($conn);
    $role = current_role();
    if ($role !== null && in_array($role, ['admin', 'seller', 'customer', 'rider'], true)) {
        redirect_to($role, 'dashboard');
    }

    render('auth/home', [
        'page_title' => 'Local marketplace',
        'body_class' => 'page-home',
        'role_css'   => '',
        'bare'       => true,
        'figures'    => report_public_figures($conn),
    ]);
}

function auth_login()
{
    global $conn;

    $emailErr = $passwordErr = $loginErr = "";
    $email = "";

    try_remember_login($conn);
    if (isset($_SESSION['user_id']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect_to($_SESSION['role'], 'dashboard');
    }

    $flash = flash_get();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = cleanInput($_POST['email'] ?? '');
        $emailErr = validate_email_format($email);

        $password = $_POST['password'] ?? '';
        if ($password === '') {
            $passwordErr = "Password cannot be empty";
        }

        if (!$emailErr && !$passwordErr) {
            $user = user_find_by_email($conn, $email);

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $loginErr = "Invalid email or password";
            } elseif ($user['status'] === 'suspended') {
                $loginErr = "This account has been suspended";
            } elseif ($user['status'] === 'pending') {
                $loginErr = "Your seller application is still pending approval";
            } else {
                login_user($conn, $user, isset($_POST['remember']));
                redirect_to($user['role'], 'dashboard');
            }
        }
    }

    render('auth/login', [
        'page_title'       => 'Login',
        'body_class'       => 'page-login',
        'role_css'         => '',
        'email'            => $email,
        'emailErr'         => $emailErr,
        'passwordErr'      => $passwordErr,
        'loginErr'         => $loginErr,
        'flash'            => $flash,
        'remember_checked' => isset($_POST['remember']),
    ]);
}

function auth_register()
{
    global $conn;

    $allowed_roles = ['customer', 'seller', 'rider'];

    $nameErr = $emailErr = $phoneErr = $passwordErr = $confirmErr = $roleErr = $shopErr = "";
    $full_name = $email = $phone = $role = $shop_name = $vehicle_type = "";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $full_name = cleanInput($_POST['full_name'] ?? '');
        $nameErr = validate_name($full_name);

        $email = cleanInput($_POST['email'] ?? '');
        $emailErr = validate_email_format($email);
        if (!$emailErr && user_email_taken($conn, $email)) {
            $emailErr = "That email is already registered";
        }

        $phone = cleanInput($_POST['phone'] ?? '');
        $phoneErr = validate_phone($phone);

        $password = $_POST['password'] ?? '';
        $passwordErr = validate_password($password);

        $confirm = $_POST['confirm_password'] ?? '';
        if ($confirm === '') {
            $confirmErr = "Please confirm your password";
        } elseif (!$passwordErr && $confirm !== $password) {
            $confirmErr = "Passwords do not match";
        }

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

        if (
            !$nameErr && !$emailErr && !$phoneErr && !$passwordErr
            && !$confirmErr && !$roleErr && !$shopErr
        ) {

            $new_id = user_create($conn, [
                'full_name'    => $full_name,
                'email'        => $email,
                'phone'        => $phone,
                'password'     => $password,
                'role'         => $role,
                'status'       => ($role === 'seller') ? 'pending' : 'active',
                'shop_name'    => $shop_name,
                'vehicle_type' => $vehicle_type,
            ]);

            if ($new_id) {
                flash_set('Registration successful. Please log in.', 'success');
                redirect_to('login');
            }
            $roleErr = "Could not create the account. Please try again.";
        }
    }

    render('auth/register', [
        'page_title'   => 'Register',
        'body_class'   => 'page-register',
        'role_css'     => '',
        'full_name'    => $full_name,
        'email'        => $email,
        'phone'        => $phone,
        'role'         => $role,
        'shop_name'    => $shop_name,
        'vehicle_type' => $vehicle_type,
        'nameErr'      => $nameErr,
        'emailErr'     => $emailErr,
        'phoneErr'     => $phoneErr,
        'passwordErr'  => $passwordErr,
        'confirmErr'   => $confirmErr,
        'roleErr'      => $roleErr,
        'shopErr'      => $shopErr,
    ]);
}

function auth_logout()
{
    global $conn;
    logout_user($conn);
    redirect_to('login');
}
