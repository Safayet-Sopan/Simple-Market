<?php
// A rider's private working notes — gate codes, landmarks, "call before
// arriving". Every query carries rider_id in its WHERE clause; that is the
// whole security model, so a note id from a form can only ever reach a row
// belonging to the rider who is signed in.

function note_find($conn, $note_id, $rider_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT note_id, order_id, title, body FROM delivery_notes
         WHERE note_id = ? AND rider_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $note_id, $rider_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function note_create($conn, $rider_id, $order_ref, $title, $body)
{
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO delivery_notes (rider_id, order_id, title, body) VALUES (?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, 'iiss', $rider_id, $order_ref, $title, $body);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function note_update($conn, $note_id, $rider_id, $order_ref, $title, $body)
{
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE delivery_notes SET title = ?, body = ?, order_id = ?
         WHERE note_id = ? AND rider_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'ssiii', $title, $body, $order_ref, $note_id, $rider_id);
    mysqli_stmt_execute($stmt);
    $n = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $n;
}

function note_delete($conn, $note_id, $rider_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "DELETE FROM delivery_notes WHERE note_id = ? AND rider_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $note_id, $rider_id);
    mysqli_stmt_execute($stmt);
    $n = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $n;
}

// A note may be pinned to an order, but only one this rider actually carried —
// otherwise a rider could attach notes to a stranger's job.
function note_order_is_mine($conn, $order_id, $rider_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT order_id FROM orders WHERE order_id = ? AND rider_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $order_id, $rider_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (bool) $row;
}

function note_list($conn, $rider_id, $keyword = '')
{
    $rows = [];
    if ($keyword !== '') {
        $like = '%' . $keyword . '%';
        $id_match = ctype_digit($keyword) ? (int) $keyword : 0;
        $stmt = mysqli_prepare(
            $conn,
            "SELECT note_id, order_id, title, body, created_at
             FROM delivery_notes
             WHERE rider_id = ? AND (title LIKE ? OR body LIKE ? OR order_id = ?)
             ORDER BY created_at DESC"
        );
        mysqli_stmt_bind_param($stmt, 'issi', $rider_id, $like, $like, $id_match);
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT note_id, order_id, title, body, created_at
             FROM delivery_notes WHERE rider_id = ? ORDER BY created_at DESC"
        );
        mysqli_stmt_bind_param($stmt, 'i', $rider_id);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}
