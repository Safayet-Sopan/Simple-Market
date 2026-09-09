<?php
// Order chat. Messages are strictly order-scoped: only the customer, the shop
// owner and the assigned rider on that order may read or post.
//
// The authorisation pair below is used by BOTH the chat page and the AJAX
// endpoints, so the two can never disagree about who may see a thread.

function chat_participants($conn, $order_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT o.order_id, o.status, o.customer_id,
                sp.user_id AS seller_user_id, sp.shop_name,
                rp.user_id AS rider_user_id
         FROM orders o
         JOIN seller_profiles sp ON sp.seller_id = o.seller_id
         LEFT JOIN rider_profiles rp ON rp.rider_id = o.rider_id
         WHERE o.order_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $order_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function can_access_chat($order, $user_id)
{
    if (!$order) {
        return false;
    }

    return (int) $order['customer_id'] === (int) $user_id
        || (int) $order['seller_user_id'] === (int) $user_id
        || ($order['rider_user_id'] !== null && (int) $order['rider_user_id'] === (int) $user_id);
}

// Every order this user is party to that is still live. Delivered orders stay
// open for a while so a delivery can still be discussed; cancelled ones do not.
//
// $role picks the join column, from a fixed set — never from raw input.
function chat_threads($conn, $user_id, $role)
{
    if ($role === 'customer') {
        $where = "o.customer_id = ?";
    } elseif ($role === 'seller') {
        $where = "sp.user_id = ?";
    } else {
        $where = "rp.user_id = ?";
    }

    $rows = [];
    $stmt = mysqli_prepare(
        $conn,
        "SELECT o.order_id, o.status, sp.shop_name, cu.full_name AS customer_name,
                (SELECT COUNT(*) FROM messages m
                 WHERE m.order_id = o.order_id AND m.sender_id != ? AND m.is_read = 0) AS unread,
                (SELECT COUNT(*) FROM messages m WHERE m.order_id = o.order_id) AS total
         FROM orders o
         JOIN seller_profiles sp ON sp.seller_id = o.seller_id
         JOIN users cu ON cu.user_id = o.customer_id
         LEFT JOIN rider_profiles rp ON rp.rider_id = o.rider_id
         WHERE " . $where . " AND o.status != 'cancelled'
         ORDER BY o.created_at DESC"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $user_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

// Messages on one order newer than $after_id, oldest first.
function chat_messages($conn, $order_id, $after_id)
{
    $rows = [];
    $stmt = mysqli_prepare(
        $conn,
        "SELECT m.message_id, m.message_text, m.sent_at, m.sender_id,
                u.full_name AS sender_name, u.role AS sender_role
         FROM messages m
         JOIN users u ON u.user_id = m.sender_id
         WHERE m.order_id = ? AND m.message_id > ?
         ORDER BY m.message_id ASC"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $order_id, $after_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function chat_send($conn, $order_id, $sender_id, $text)
{
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO messages (order_id, sender_id, message_text) VALUES (?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, 'iis', $order_id, $sender_id, $text);
    mysqli_stmt_execute($stmt);
    $id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $id;
}

// Reading a thread marks the other side's messages read — never your own.
function chat_mark_read($conn, $order_id, $reader_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE messages SET is_read = 1
         WHERE order_id = ? AND sender_id != ? AND is_read = 0"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $order_id, $reader_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
