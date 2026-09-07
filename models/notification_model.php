<?php

function notification_unread_count($conn, $user_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0"
    );
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (int) ($row['cnt'] ?? 0);
}

function notification_list($conn, $user_id)
{
    $rows = [];
    $stmt = mysqli_prepare(
        $conn,
        "SELECT notification_id, message, is_read, created_at
         FROM notifications WHERE user_id = ? ORDER BY is_read ASC, created_at DESC"
    );
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function notification_mark_read($conn, $notification_id, $user_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $notification_id, $user_id);
    mysqli_stmt_execute($stmt);
    $n = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $n;
}

function notification_mark_all_read($conn, $user_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0"
    );
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $n = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $n;
}

function notification_clear_read($conn, $user_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "DELETE FROM notifications WHERE user_id = ? AND is_read = 1"
    );
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $n = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $n;
}

function notify_user($conn, $user_id, $message)
{
    $stmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, 'is', $user_id, $message);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function notify_seller($conn, $seller_id, $message)
{
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO notifications (user_id, message)
         SELECT user_id, ? FROM seller_profiles WHERE seller_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'si', $message, $seller_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function notification_latest_unread($conn, $user_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT message, created_at FROM notifications
         WHERE user_id = ? AND is_read = 0
         ORDER BY created_at DESC LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}
