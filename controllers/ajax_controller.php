<?php
// JSON endpoints: index.php?page=ajax&action=<action>
//
// Everything "real-time" in this project is AJAX polling — setInterval + fetch
// against these routes. There are no sockets anywhere.
//
// index.php defines CSRF_JSON_RESPONSE before loading the helpers for this
// page, so a rejected CSRF check comes back as JSON rather than an HTML page
// the caller cannot parse.

function ajax_dispatch($action)
{
    // Every endpoint needs a session; none of them redirect, they answer 401.
    if (!isset($_SESSION['user_id'])) {
        json_response(['error' => 'Not logged in'], 401);
    }

    switch ($action) {
        case 'poll_notifications': ajax_poll_notifications(); break;
        case 'poll_messages':      ajax_poll_messages();      break;
        case 'send_message':       ajax_send_message();       break;
        case 'update_order_status': ajax_update_order_status(); break;
        default:
            json_response(['error' => 'Unknown endpoint: ' . $action], 404);
    }
}

function ajax_poll_notifications()
{
    global $conn;
    $user_id = current_user_id();
    $latest = notification_latest_unread($conn, $user_id);

    json_response([
        'unread' => notification_unread_count($conn, $user_id),
        'latest' => $latest ? $latest['message'] : null,
    ]);
}

function ajax_poll_messages()
{
    global $conn;
    $user_id = current_user_id();
    $order_id = $_GET['order_id'] ?? '';

    if (!is_id($order_id)) {
        json_response(['error' => 'Invalid order'], 400);
    }
    $order_id = (int) $order_id;

    $order = chat_participants($conn, $order_id);
    if (!can_access_chat($order, $user_id)) {
        json_response(['error' => 'Not your order'], 403);
    }

    // Only fetch what the client has not seen yet.
    $after_id = $_GET['after_id'] ?? '0';
    $after_id = ctype_digit((string)$after_id) ? (int) $after_id : 0;

    $messages = [];
    foreach (chat_messages($conn, $order_id, $after_id) as $row) {
        $messages[] = [
            'message_id' => (int) $row['message_id'],
            'text'       => $row['message_text'],
            'sent_at'    => $row['sent_at'],
            'sender'     => $row['sender_name'],
            'role'       => $row['sender_role'],
            'is_mine'    => ((int) $row['sender_id'] === (int) $user_id),
        ];
    }

    // Reading the thread marks the other side's messages read.
    chat_mark_read($conn, $order_id, $user_id);

    json_response(['messages' => $messages, 'order_status' => $order['status']]);
}

function ajax_send_message()
{
    global $conn;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['error' => 'POST only'], 405);
    }

    $user_id = current_user_id();
    $order_id = $_POST['order_id'] ?? '';
    $message_text = trim($_POST['message_text'] ?? '');

    if (!is_id($order_id)) {
        json_response(['error' => 'Invalid order'], 400);
    }
    $order_id = (int) $order_id;

    if ($message_text === '') {
        json_response(['error' => 'Message is empty'], 400);
    }
    if (mb_strlen($message_text) > 1000) {
        json_response(['error' => 'Message is too long'], 400);
    }

    $order = chat_participants($conn, $order_id);
    if (!can_access_chat($order, $user_id)) {
        json_response(['error' => 'Not your order'], 403);
    }

    // Stored raw and escaped on output, so the text survives intact either way.
    $message_id = chat_send($conn, $order_id, $user_id, $message_text);

    json_response(['ok' => true, 'message_id' => $message_id]);
}

function ajax_update_order_status()
{
    global $conn;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['error' => 'POST only'], 405);
    }

    $user_id = current_user_id();
    $role    = current_role();
    $order_id   = $_POST['order_id'] ?? '';
    $new_status = $_POST['status'] ?? '';

    if (!is_id($order_id) || $new_status === '') {
        json_response(['error' => 'Invalid request'], 400);
    }
    $order_id = (int) $order_id;

    $order = order_with_parties($conn, $order_id);
    if (!$order) {
        json_response(['error' => 'Order not found'], 404);
    }

    // Being the right role is not enough — it has to be this user's order.
    $owns = false;
    if ($role === 'seller') {
        $owns = ((int) $order['seller_user_id'] === (int) $user_id);
    } elseif ($role === 'rider') {
        $owns = ($order['rider_user_id'] !== null
            && (int) $order['rider_user_id'] === (int) $user_id);
    } elseif ($role === 'customer') {
        $owns = ((int) $order['customer_id'] === (int) $user_id);
    }

    if (!$owns) {
        json_response(['error' => 'Not your order'], 403);
    }

    if (!can_transition($role, $order['status'], $new_status)) {
        json_response([
            'error' => "A " . $role . " cannot move an order from '"
                . $order['status'] . "' to '" . $new_status . "'",
        ], 409);
    }

    mysqli_begin_transaction($conn);
    try {
        // Pinning the expected status makes a double submit a no-op rather
        // than a second transition.
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE orders SET status = ? WHERE order_id = ? AND status = ?"
        );
        mysqli_stmt_bind_param($stmt, 'sis', $new_status, $order_id, $order['status']);
        mysqli_stmt_execute($stmt);
        $changed = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($changed === 0) {
            throw new Exception('Status already moved');
        }

        if ($new_status === 'cancelled') {
            restore_order_stock($conn, $order_id);
        }
        if ($new_status === 'delivered') {
            order_settle_delivery($conn, $order_id, $order['rider_id']);
        }

        // Everyone on the order except whoever made the change hears about it.
        $message = "Order #" . $order_id . " is now "
            . str_replace('_', ' ', $new_status) . ".";
        $recipients = [(int) $order['customer_id'], (int) $order['seller_user_id']];
        if ($order['rider_user_id'] !== null) {
            $recipients[] = (int) $order['rider_user_id'];
        }
        foreach (array_unique($recipients) as $recipient) {
            if ($recipient !== (int) $user_id) {
                notify_user($conn, $recipient, $message);
            }
        }

        mysqli_commit($conn);
        json_response(['ok' => true, 'order_id' => $order_id, 'status' => $new_status]);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        json_response(
            ['error' => 'That order changed while you were working — reload and retry'],
            409
        );
    }
}
