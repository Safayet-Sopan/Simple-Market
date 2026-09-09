<?php
// Price bidding. A bid ("offer") is placed by a customer on a product before
// any order exists, which is why offers link to products and not to orders.
//
// The lifecycle is: pending -> countered (seller) -> accepted / rejected.
// An accepted bid carries its price into checkout exactly once;
// offers.converted_order_id records which order spent it, and the
// "converted_order_id IS NULL" guard in order_place() is what stops one
// accepted bid being redeemed for unlimited discounted orders.

function offer_product_for_bidding($conn, $product_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT p.product_id, p.product_name, p.price, p.stock_quantity, sp.shop_name
         FROM products p
         JOIN seller_profiles sp ON sp.seller_id = p.seller_id
         WHERE p.product_id = ? AND p.status = 'active' AND sp.approval_status = 'approved'"
    );
    mysqli_stmt_bind_param($stmt, 'i', $product_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

// One open bid per customer per product, so a seller never sees duplicates.
function offer_open_exists($conn, $product_id, $customer_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT offer_id FROM offers
         WHERE product_id = ? AND customer_id = ?
           AND status IN ('pending','countered') AND converted_order_id IS NULL"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $product_id, $customer_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (bool) $row;
}

function offer_create($conn, $product_id, $customer_id, $price, $product_name)
{
    mysqli_begin_transaction($conn);
    try {
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO offers (product_id, customer_id, offered_price, status)
             VALUES (?, ?, ?, 'pending')"
        );
        mysqli_stmt_bind_param($stmt, 'iid', $product_id, $customer_id, $price);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $message = "New bid of " . money($price) . " on '" . $product_name . "'.";
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

// Loads a bid only if it sits on a product this seller owns.
function offer_find_for_seller($conn, $offer_id, $seller_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT o.offer_id, o.offered_price, o.status, o.customer_id, o.converted_order_id,
                p.product_name, p.price
         FROM offers o
         JOIN products p ON p.product_id = o.product_id
         WHERE o.offer_id = ? AND p.seller_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $offer_id, $seller_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function offer_find_for_customer($conn, $offer_id, $customer_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT o.offer_id, o.status, o.counter_price, o.converted_order_id,
                p.product_name, p.seller_id
         FROM offers o
         JOIN products p ON p.product_id = o.product_id
         WHERE o.offer_id = ? AND o.customer_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $offer_id, $customer_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

// The seller's answer: accept, reject, or counter at a lower price.
// $counter_price is null for accept and reject.
function offer_settle($conn, $offer_id, $new_status, $counter_price, $notify_user_id, $message)
{
    mysqli_begin_transaction($conn);
    try {
        if ($counter_price !== null) {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE offers SET status = ?, counter_price = ? WHERE offer_id = ?"
            );
            mysqli_stmt_bind_param($stmt, 'sdi', $new_status, $counter_price, $offer_id);
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE offers SET status = ? WHERE offer_id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $new_status, $offer_id);
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        notify_user($conn, $notify_user_id, $message);

        mysqli_commit($conn);
        return true;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return false;
    }
}

// The customer's answer to a counter. Notifies the shop rather than a user.
function offer_customer_settle($conn, $offer_id, $new_status, $seller_id, $message)
{
    mysqli_begin_transaction($conn);
    try {
        $stmt = mysqli_prepare($conn, "UPDATE offers SET status = ? WHERE offer_id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $new_status, $offer_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        notify_seller($conn, $seller_id, $message);

        mysqli_commit($conn);
        return true;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return false;
    }
}

// An accepted, unspent bid this customer holds on this product. The agreed
// price is the counter when the seller made one, otherwise what was offered.
function offer_redeemable($conn, $offer_id, $customer_id, $product_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT offer_id, offered_price, counter_price, status
         FROM offers
         WHERE offer_id = ? AND customer_id = ? AND product_id = ?
           AND status = 'accepted' AND converted_order_id IS NULL"
    );
    mysqli_stmt_bind_param($stmt, 'iii', $offer_id, $customer_id, $product_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function offer_list_for_customer($conn, $customer_id)
{
    $rows = [];
    $stmt = mysqli_prepare(
        $conn,
        "SELECT o.offer_id, o.product_id, o.offered_price, o.counter_price, o.status,
                o.created_at, o.converted_order_id,
                p.product_name, p.price, p.stock_quantity, p.status AS product_status,
                sp.shop_name
         FROM offers o
         JOIN products p ON p.product_id = o.product_id
         JOIN seller_profiles sp ON sp.seller_id = p.seller_id
         WHERE o.customer_id = ?
         ORDER BY FIELD(o.status, 'countered', 'accepted', 'pending', 'rejected'), o.created_at DESC"
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

function offer_list_for_seller($conn, $seller_id)
{
    $rows = [];
    $stmt = mysqli_prepare(
        $conn,
        "SELECT o.offer_id, o.offered_price, o.counter_price, o.status, o.created_at,
                o.converted_order_id, p.product_name, p.price, p.stock_quantity,
                u.full_name AS customer_name
         FROM offers o
         JOIN products p ON p.product_id = o.product_id
         JOIN users u ON u.user_id = o.customer_id
         WHERE p.seller_id = ?
         ORDER BY FIELD(o.status, 'pending', 'countered', 'accepted', 'rejected'), o.created_at DESC"
    );
    mysqli_stmt_bind_param($stmt, 'i', $seller_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}
