<?php
// Two separate things, deliberately kept in two tables:
//
//   reviews        — the customer's verdict on a PRODUCT
//   seller_ratings — the customer's verdict on the SHOP
//
// Both are gated on a delivered order the customer actually owns, and both are
// one-per-order so a single purchase cannot be reviewed repeatedly.

// ------------------------------------------------------- product reviews ----

// Is this customer entitled to review this product on this order? Only if the
// order is theirs, delivered, and actually contained the product.
function review_eligible($conn, $order_id, $customer_id, $product_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT o.order_id
         FROM orders o
         JOIN order_items oi ON oi.order_id = o.order_id
         WHERE o.order_id = ? AND o.customer_id = ? AND o.status = 'delivered'
           AND oi.product_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'iii', $order_id, $customer_id, $product_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (bool) $row;
}

function review_exists($conn, $order_id, $product_id, $customer_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT review_id FROM reviews WHERE order_id = ? AND product_id = ? AND customer_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'iii', $order_id, $product_id, $customer_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (bool) $row;
}

function review_create($conn, $product_id, $customer_id, $order_id, $rating, $comment)
{
    mysqli_begin_transaction($conn);
    try {
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO reviews (product_id, customer_id, order_id, rating, comment)
             VALUES (?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, 'iiiis', $product_id, $customer_id, $order_id, $rating, $comment);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Let the shop know a review landed.
        $message = "New " . $rating . "-star product review on order #" . $order_id . ".";
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO notifications (user_id, message)
             SELECT sp.user_id, ? FROM seller_profiles sp
             JOIN products p ON p.seller_id = sp.seller_id
             WHERE p.product_id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'si', $message, $product_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($conn);
        return true;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return false;
    }
}

// customer_id in the WHERE clause is what stops one customer deleting another's.
function review_delete($conn, $review_id, $customer_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "DELETE FROM reviews WHERE review_id = ? AND customer_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $review_id, $customer_id);
    mysqli_stmt_execute($stmt);
    $n = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $n;
}

// Everything delivered to this customer that they have not reviewed yet.
function review_pending($conn, $customer_id)
{
    $rows = [];
    $stmt = mysqli_prepare(
        $conn,
        "SELECT o.order_id, o.created_at, p.product_id, p.product_name, sp.shop_name,
                oi.quantity, oi.unit_price
         FROM orders o
         JOIN order_items oi ON oi.order_id = o.order_id
         JOIN products p ON p.product_id = oi.product_id
         JOIN seller_profiles sp ON sp.seller_id = o.seller_id
         WHERE o.customer_id = ? AND o.status = 'delivered'
           AND NOT EXISTS (
               SELECT 1 FROM reviews r
               WHERE r.order_id = o.order_id AND r.product_id = p.product_id
                 AND r.customer_id = o.customer_id
           )
         ORDER BY o.created_at DESC"
    );
    mysqli_stmt_bind_param($stmt, 'i', $customer_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function review_mine($conn, $customer_id)
{
    $rows = [];
    $stmt = mysqli_prepare(
        $conn,
        "SELECT r.review_id, r.rating, r.comment, r.created_at, r.order_id, p.product_name
         FROM reviews r
         JOIN products p ON p.product_id = r.product_id
         WHERE r.customer_id = ?
         ORDER BY r.created_at DESC"
    );
    mysqli_stmt_bind_param($stmt, 'i', $customer_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

// -------------------------------------------------------- shop ratings ------

// Rating a shop requires a delivered order from that shop. Returns the
// seller_id when allowed, null otherwise.
function rating_eligible_seller($conn, $order_id, $customer_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT seller_id FROM orders
         WHERE order_id = ? AND customer_id = ? AND status = 'delivered'"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $order_id, $customer_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row['seller_id'] ?? null;
}

function rating_exists($conn, $order_id, $customer_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT rating_id FROM seller_ratings WHERE order_id = ? AND customer_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $order_id, $customer_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (bool) $row;
}

function rating_create($conn, $seller_id, $customer_id, $order_id, $rating, $comment)
{
    mysqli_begin_transaction($conn);
    try {
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO seller_ratings (seller_id, customer_id, order_id, rating, comment)
             VALUES (?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, 'iiiis', $seller_id, $customer_id, $order_id, $rating, $comment);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        notify_seller(
            $conn, $seller_id,
            "Your shop received a " . $rating . "-star rating on order #" . $order_id . "."
        );

        mysqli_commit($conn);
        return true;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return false;
    }
}

// Delivered orders from shops this customer has not rated yet.
function rating_pending($conn, $customer_id)
{
    $rows = [];
    $stmt = mysqli_prepare(
        $conn,
        "SELECT o.order_id, o.created_at, o.total_amount, sp.shop_name
         FROM orders o
         JOIN seller_profiles sp ON sp.seller_id = o.seller_id
         WHERE o.customer_id = ? AND o.status = 'delivered'
           AND NOT EXISTS (
               SELECT 1 FROM seller_ratings sr
               WHERE sr.order_id = o.order_id AND sr.customer_id = o.customer_id
           )
         ORDER BY o.created_at DESC"
    );
    mysqli_stmt_bind_param($stmt, 'i', $customer_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function rating_mine($conn, $customer_id)
{
    $rows = [];
    $stmt = mysqli_prepare(
        $conn,
        "SELECT sr.rating, sr.comment, sr.created_at, sr.order_id, sp.shop_name
         FROM seller_ratings sr
         JOIN seller_profiles sp ON sp.seller_id = sr.seller_id
         WHERE sr.customer_id = ?
         ORDER BY sr.created_at DESC"
    );
    mysqli_stmt_bind_param($stmt, 'i', $customer_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}
