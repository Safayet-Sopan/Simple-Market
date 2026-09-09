<?php
// Actions every role shares: the password form and the notifications page.
//
// These were four near-identical files per feature before the MVC migration
// (admin/change_password.php, seller/change_password.php, and so on). The logic
// never differed by role, so it lives here once and each role's dispatcher
// calls it with its own role name for the stylesheet and the back-links.

function account_change_password($role)
{
    global $conn;

    $currentErr = $newErr = $confirmErr = "";
    $successMsg = "";
    $user_id = current_user_id();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Passwords are never run through cleanInput — htmlspecialchars would
        // corrupt the special characters a strong password contains.
        $current_password = $_POST['current_password'] ?? '';
        $new_password     = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        $stored_hash = user_password_hash($conn, $user_id);

        if ($current_password === '') {
            $currentErr = "Current password is required";
        } elseif (!password_verify($current_password, $stored_hash)) {
            $currentErr = "Current password is incorrect";
        }

        if ($new_password === '') {
            $newErr = "New password is required";
        } else {
            $newErr = validate_password($new_password);
            // Only worth saying once the current password actually checked out.
            if ($newErr === "" && !$currentErr
                && password_verify($new_password, $stored_hash)) {
                $newErr = "New password must be different from current password";
            }
        }

        if ($confirm_password === '') {
            $confirmErr = "Please confirm your new password";
        } elseif (!$newErr && $confirm_password !== $new_password) {
            $confirmErr = "Passwords do not match";
        }

        if (!$currentErr && !$newErr && !$confirmErr) {
            user_set_password($conn, $user_id, $new_password);
            $successMsg = "Password changed successfully.";
        }
    }

    render('account/change_password', [
        'page_title' => 'Change Password',
        'body_class' => 'page-change-password',
        'role_css'   => $role,
        'role'       => $role,
        'currentErr' => $currentErr,
        'newErr'     => $newErr,
        'confirmErr' => $confirmErr,
        'successMsg' => $successMsg,
    ]);
}

function account_notifications($role)
{
    global $conn;

    $user_id = current_user_id();
    $actionErr = "";
    $successMsg = "";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'mark_all') {
            $marked = notification_mark_all_read($conn, $user_id);
            $successMsg = $marked . " notification(s) marked as read.";

        } elseif ($action === 'mark_read') {
            $notification_id = $_POST['notification_id'] ?? '';
            if (!is_id($notification_id)) {
                $actionErr = "Invalid request.";
            } else {
                notification_mark_read($conn, (int) $notification_id, $user_id);
                $successMsg = "Marked as read.";
            }

        } elseif ($action === 'clear_read') {
            // Only already-read rows go, so nothing unseen can be thrown away.
            $removed = notification_clear_read($conn, $user_id);
            $successMsg = $removed . " read notification(s) cleared.";
        }
    }

    $notifications = notification_list($conn, $user_id);
    $unread_count = 0;
    foreach ($notifications as $n) {
        if (!$n['is_read']) {
            $unread_count++;
        }
    }

    render('account/notifications', [
        'page_title'    => 'Notifications',
        'body_class'    => 'page-notifications',
        'role_css'      => $role,
        'role'          => $role,
        'notifications' => $notifications,
        'unread_count'  => $unread_count,
        'successMsg'    => $successMsg,
        'actionErr'     => $actionErr,
    ]);
}

// The order-chat page, shared by the three roles that can be party to an order.
// Authorisation lives in the message model so this page and the AJAX endpoints
// agree exactly on who may see a thread.
function chat_page($role)
{
    global $conn;

    $user_id = current_user_id();
    $order_id = $_GET['order_id'] ?? '';
    $accessErr = "";
    $order = null;

    if (is_id($order_id)) {
        $order_id = (int) $order_id;
        $order = chat_participants($conn, $order_id);

        if (!can_access_chat($order, $user_id)) {
            $accessErr = "That order's chat is not available to you.";
            $order = null;
        }
    } else {
        $order_id = 0;
    }

    render('partials/chat', [
        'page_title' => 'Order Chat',
        'body_class' => 'page-chat',
        'role_css'   => $role,
        'role'       => $role,
        'threads'    => chat_threads($conn, $user_id, $role),
        'order'      => $order,
        'order_id'   => $order_id,
        'accessErr'  => $accessErr,
    ]);
}
