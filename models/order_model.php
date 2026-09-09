<?php
// Orders, their line items, and the rules for moving one through its statuses.
//
// One order = one product = one seller. There is no multi-seller cart.
//
// Status ownership is split: the SELLER drives pending -> confirmed -> preparing,
// the RIDER drives preparing -> out_for_delivery -> delivered, and the CUSTOMER
// may only cancel while the order is still pending. order_transitions() is the
// single source of truth, so the pages and the AJAX endpoint cannot disagree.

function order_transitions($role)
{
    if ($role === 'seller') {
        return [
            'pending'   => ['confirmed', 'cancelled'],
            'confirmed' => ['preparing', 'cancelled'],
            'preparing' => ['cancelled'],
        ];
    }

    if ($role === 'rider') {
        return [
            'preparing'        => ['out_for_delivery'],
            'out_for_delivery' => ['delivered'],
        ];
    }

    if ($role === 'customer') {
        // A customer may back out only before the shop starts work.
        return [
            'pending' => ['cancelled'],
        ];
    }

    return [];
}

function can_transition($role, $from, $to)
{
    $map = order_transitions($role);
    return isset($map[$from]) && in_array($to, $map[$from], true);
}

// Puts a cancelled order's stock back. Call inside a transaction.
function restore_order_stock($conn, $order_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE products p
         JOIN order_items oi ON oi.product_id = p.product_id
         SET p.stock_quantity = p.stock_quantity + oi.quantity
         WHERE oi.order_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $order_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// ------------------------------------------------------------- checkout -----

// The product a customer is about to buy, with everything checkout needs from
// the shop. Only active products in approved shops are purchasable.
function order_checkout_product($conn, $product_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT p.product_id, p.product_name, p.price, p.stock_quantity, p.seller_id,
                p.product_image,
                sp.shop_name, sp.commission_rate, sp.approval_status, sp.payment_methods
         FROM products p
         JOIN seller_profiles sp ON sp.seller_id = p.seller_id
         WHERE p.product_id = ? AND p.status = 'active'"
    );
    mysqli_stmt_bind_param($stmt, 'i', $product_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

// Places the order: reserves stock, writes the order and its line, spends the
// bid if one was used, and tells the shop — all or nothing.
//
// Returns the new order_id, or 0 when the stock ran out or the bid was already
// spent between the page load and the submit.
function order_place($conn, $d)
{
    mysqli_begin_transaction($conn);
    try {
        // Re-check stock INSIDE the transaction, as part of the write itself.
        // Checking first and writing after would let two concurrent orders both
        // pass the check and oversell.
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE products SET stock_quantity = stock_quantity - ?
             WHERE product_id = ? AND stock_quantity >= ?"
        );
        mysqli_stmt_bind_param($stmt, 'iii', $d['quantity'], $d['product_id'], $d['quantity']);
        mysqli_stmt_execute($stmt);
        $stock_updated = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($stock_updated === 0) {
            throw new Exception('Stock unavailable');
        }

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO orders (customer_id, seller_id, delivery_address, fast_delivery,
                                 delivery_fee, subtotal, commission_amount, total_amount,
                                 payment_method, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
        );
        mysqli_stmt_bind_param(
            $stmt, 'iisidddds',
            $d['customer_id'], $d['seller_id'], $d['delivery_address'], $d['fast_delivery'],
            $d['delivery_fee'], $d['subtotal'], $d['commission_amount'], $d['total_amount'],
            $d['payment_method']
        );
        mysqli_stmt_execute($stmt);
        $order_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO order_items (order_id, product_id, quantity, unit_price, line_total)
             VALUES (?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param(
            $stmt, 'iiidd',
            $order_id, $d['product_id'], $d['quantity'], $d['unit_price'], $d['subtotal']
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Spend the bid. The "converted_order_id IS NULL" guard is what makes
        // this safe under a concurrent submit — the second one updates 0 rows.
        if (!empty($d['offer_id'])) {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE offers SET converted_order_id = ?
                 WHERE offer_id = ? AND customer_id = ? AND converted_order_id IS NULL"
            );
            mysqli_stmt_bind_param($stmt, 'iii', $order_id, $d['offer_id'], $d['customer_id']);
            mysqli_stmt_execute($stmt);
            $spent = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);

            if ($spent === 0) {
                throw new Exception('Offer already used');
            }
        }

        notify_seller(
            $conn, $d['seller_id'],
            "New order #{$order_id} received for {$d['product_name']} (x{$d['quantity']})."
        );

        mysqli_commit($conn);
        return $order_id;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return 0;
    }
}

// -------------------------------------------------------- status changes ----

function order_find_for_seller($conn, $order_id, $seller_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT order_id, customer_id, status, payment_status, total_amount
         FROM orders WHERE order_id = ? AND seller_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $order_id, $seller_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function order_find_for_customer($conn, $order_id, $customer_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT order_id, seller_id, status, payment_status, total_amount
         FROM orders WHERE order_id = ? AND customer_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $order_id, $customer_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function order_mark_paid($conn, $order_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE orders SET payment_status = 'paid' WHERE order_id = ? AND payment_status = 'unpaid'"
    );
    mysqli_stmt_bind_param($stmt, 'i', $order_id);
    mysqli_stmt_execute($stmt);
    $n = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $n;
}

// Moves an order to $to, but only if it is still sitting on $from. That guard
// in the WHERE clause is what makes a replayed or racing submit a no-op rather
// than a second state change. Returns true when the move happened.
function order_change_status($conn, $order_id, $from, $to, $notify_user_id, $message)
{
    mysqli_begin_transaction($conn);
    try {
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE orders SET status = ? WHERE order_id = ? AND status = ?"
        );
        mysqli_stmt_bind_param($stmt, 'sis', $to, $order_id, $from);
        mysqli_stmt_execute($stmt);
        $changed = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($changed === 0) {
            throw new Exception('Order status changed elsewhere');
        }

        // Cancelling puts the reserved stock back, or it is lost for good.
        if ($to === 'cancelled') {
            restore_order_stock($conn, $order_id);
        }

        if ($notify_user_id) {
            notify_user($conn, $notify_user_id, $message);
        }

        mysqli_commit($conn);
        return true;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return false;
    }
}

// ------------------------------------------------------------- listings -----

// Attaches each order's line items in ONE extra query rather than one per row.
function order_attach_items($conn, $orders)
{
    if (empty($orders)) {
        return $orders;
    }

    $ids = [];
    foreach ($orders as $o) {
        $ids[] = (int) $o['order_id'];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = mysqli_prepare(
        $conn,
        "SELECT oi.order_id, oi.quantity, oi.unit_price, p.product_name, p.product_id
         FROM order_items oi
         JOIN products p ON p.product_id = oi.product_id
         WHERE oi.order_id IN ($placeholders)"
    );
    mysqli_stmt_bind_param($stmt, str_repeat('i', count($ids)), ...$ids);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $by_order = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $by_order[$row['order_id']][] = $row;
    }
    mysqli_stmt_close($stmt);

    foreach ($orders as $i => $o) {
        $orders[$i]['items'] = $by_order[$o['order_id']] ?? [];
    }
    return $orders;
}

// $filter is one of the whitelist in the controller, never raw input.
function order_list_for_seller($conn, $seller_id, $filter)
{
    $sql = "SELECT o.order_id, o.status, o.payment_method, o.payment_status, o.subtotal,
                   o.delivery_fee, o.commission_amount, o.total_amount, o.fast_delivery,
                   o.delivery_address, o.created_at, o.rider_id,
                   u.full_name AS customer_name, u.phone AS customer_phone,
                   ru.full_name AS rider_name
            FROM orders o
            JOIN users u ON u.user_id = o.customer_id
            LEFT JOIN rider_profiles rp ON rp.rider_id = o.rider_id
            LEFT JOIN users ru ON ru.user_id = rp.user_id
            WHERE o.seller_id = ?";

    $types = 'i';
    $params = [$seller_id];

    if ($filter === 'open') {
        $sql .= " AND o.status NOT IN ('delivered','cancelled')";
    } elseif ($filter !== 'all') {
        $sql .= " AND o.status = ?";
        $types .= 's';
        $params[] = $filter;
    }
    $sql .= " ORDER BY FIELD(o.status,'pending','confirmed','preparing','out_for_delivery','delivered','cancelled'), o.created_at DESC";

    $rows = [];
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);

    return order_attach_items($conn, $rows);
}

function order_list_for_customer($conn, $customer_id, $filter)
{
    $sql = "SELECT o.order_id, o.status, o.payment_method, o.payment_status, o.subtotal,
                   o.delivery_fee, o.total_amount, o.fast_delivery, o.delivery_address,
                   o.created_at, sp.shop_name, ru.full_name AS rider_name
            FROM orders o
            JOIN seller_profiles sp ON sp.seller_id = o.seller_id
            LEFT JOIN rider_profiles rp ON rp.rider_id = o.rider_id
            LEFT JOIN users ru ON ru.user_id = rp.user_id
            WHERE o.customer_id = ?";

    $types = 'i';
    $params = [$customer_id];

    if ($filter === 'open') {
        $sql .= " AND o.status NOT IN ('delivered','cancelled')";
    } elseif ($filter !== 'all') {
        $sql .= " AND o.status = ?";
        $types .= 's';
        $params[] = $filter;
    }
    $sql .= " ORDER BY o.created_at DESC";

    $rows = [];
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);

    return order_attach_items($conn, $rows);
}

function order_seller_open_count($conn, $seller_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS cnt FROM orders
         WHERE seller_id = ? AND status NOT IN ('delivered','cancelled')"
    );
    mysqli_stmt_bind_param($stmt, 'i', $seller_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (int) $row['cnt'];
}

function order_search_for_seller($conn, $seller_id, $keyword)
{
    $like = '%' . $keyword . '%';
    $id_match = ctype_digit($keyword) ? (int) $keyword : 0;
    $rows = [];
    $stmt = mysqli_prepare(
        $conn,
        "SELECT o.order_id, o.status, o.total_amount, o.created_at, u.full_name AS customer_name
         FROM orders o
         JOIN users u ON u.user_id = o.customer_id
         WHERE o.seller_id = ? AND (u.full_name LIKE ? OR o.order_id = ?)
         ORDER BY o.created_at DESC"
    );
    mysqli_stmt_bind_param($stmt, 'isi', $seller_id, $like, $id_match);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

// ------------------------------------------------- customer order views -----

// One order, scoped to its customer so an order_id cannot be walked in the URL.
function order_track_detail($conn, $order_id, $customer_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT o.order_id, o.delivery_address, o.fast_delivery, o.delivery_fee, o.subtotal,
                o.total_amount, o.status, o.created_at, o.rider_id,
                sp.shop_name,
                u.full_name AS rider_name, rp.vehicle_type
         FROM orders o
         JOIN seller_profiles sp ON sp.seller_id = o.seller_id
         LEFT JOIN rider_profiles rp ON rp.rider_id = o.rider_id
         LEFT JOIN users u ON u.user_id = rp.user_id
         WHERE o.order_id = ? AND o.customer_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $order_id, $customer_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function order_items($conn, $order_id)
{
    $rows = [];
    $stmt = mysqli_prepare(
        $conn,
        "SELECT oi.quantity, oi.unit_price, oi.line_total, p.product_name
         FROM order_items oi
         JOIN products p ON p.product_id = oi.product_id
         WHERE oi.order_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $order_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function order_track_list($conn, $customer_id)
{
    $rows = [];
    $stmt = mysqli_prepare(
        $conn,
        "SELECT o.order_id, o.total_amount, o.status, o.created_at, o.fast_delivery, sp.shop_name
         FROM orders o
         JOIN seller_profiles sp ON sp.seller_id = o.seller_id
         WHERE o.customer_id = ?
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

// The three numbers on the customer dashboard.
function order_customer_counts($conn, $customer_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            (SELECT COUNT(*) FROM orders
              WHERE customer_id = ? AND status NOT IN ('delivered','cancelled')) AS active_orders,
            (SELECT COUNT(*) FROM orders o
              WHERE o.customer_id = ? AND o.status = 'delivered'
                AND o.order_id NOT IN (SELECT order_id FROM reviews WHERE customer_id = ?)) AS awaiting_feedback,
            (SELECT COUNT(*) FROM offers
              WHERE customer_id = ? AND status IN ('countered','accepted')
                AND converted_order_id IS NULL) AS open_bids"
    );
    mysqli_stmt_bind_param($stmt, 'iiii', $customer_id, $customer_id, $customer_id, $customer_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

// --------------------------------------------------------- rider views ------

// Claiming is ONE guarded UPDATE. If another rider got there first, rider_id is
// no longer NULL and this changes zero rows — which is how the race is settled,
// rather than by checking first and writing after.
function delivery_claim($conn, $order_id, $rider_id)
{
    mysqli_begin_transaction($conn);
    try {
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE orders SET rider_id = ?
             WHERE order_id = ? AND rider_id IS NULL AND status IN ('confirmed','preparing')"
        );
        mysqli_stmt_bind_param($stmt, 'ii', $rider_id, $order_id);
        mysqli_stmt_execute($stmt);
        $claimed = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($claimed === 0) {
            throw new Exception('Already claimed');
        }

        // A rider holding a delivery is busy.
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE rider_profiles SET availability_status = 'busy' WHERE rider_id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'i', $rider_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO notifications (user_id, message)
             SELECT o.customer_id, ? FROM orders o WHERE o.order_id = ?"
        );
        $message = "A rider has picked up order #{$order_id}.";
        mysqli_stmt_bind_param($stmt, 'si', $message, $order_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($conn);
        return true;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return false;
    }
}

// Advancing a delivery. The WHERE clause pins BOTH the owner and the expected
// current status, so a stale form cannot skip a step or move someone else's job.
function delivery_advance($conn, $order_id, $rider_id, $action)
{
    $required_status = ($action === 'out_for_delivery') ? 'preparing' : 'out_for_delivery';

    mysqli_begin_transaction($conn);
    try {
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE orders SET status = ?
             WHERE order_id = ? AND rider_id = ? AND status = ?"
        );
        mysqli_stmt_bind_param($stmt, 'siis', $action, $order_id, $rider_id, $required_status);
        mysqli_stmt_execute($stmt);
        $changed = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($changed === 0) {
            throw new Exception('Not allowed from the current status');
        }

        if ($action === 'delivered') {
            // The rider keeps a share of the delivery fee. The guarded UPDATE
            // above runs once per order, so this cannot double-pay.
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO earnings (rider_id, order_id, amount)
                 SELECT ?, order_id, ROUND(delivery_fee * ?, 2)
                 FROM orders WHERE order_id = ?"
            );
            $rate = RIDER_EARNING_RATE;
            mysqli_stmt_bind_param($stmt, 'idi', $rider_id, $rate, $order_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // Free the rider up for the next job.
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE rider_profiles SET availability_status = 'available' WHERE rider_id = ?"
            );
            mysqli_stmt_bind_param($stmt, 'i', $rider_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $message = "Order #{$order_id} was delivered. You can now review the product and rate the shop.";
        } else {
            $message = "Order #{$order_id} is on its way to you.";
        }

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO notifications (user_id, message)
             SELECT o.customer_id, ? FROM orders o WHERE o.order_id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'si', $message, $order_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($conn);
        return true;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return false;
    }
}

function delivery_mine($conn, $rider_id)
{
    $rows = [];
    $stmt = mysqli_prepare(
        $conn,
        "SELECT o.order_id, o.status, o.delivery_address, o.delivery_fee, o.total_amount,
                o.fast_delivery, o.payment_method, o.payment_status, o.created_at,
                sp.shop_name, sp.shop_address,
                u.full_name AS customer_name, u.phone AS customer_phone
         FROM orders o
         JOIN seller_profiles sp ON sp.seller_id = o.seller_id
         JOIN users u ON u.user_id = o.customer_id
         WHERE o.rider_id = ? AND o.status NOT IN ('delivered','cancelled')
         ORDER BY o.fast_delivery DESC, o.created_at ASC"
    );
    mysqli_stmt_bind_param($stmt, 'i', $rider_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

// Unclaimed jobs any rider can take. Fast-delivery orders surface first — that
// is what the customer paid the higher fee for.
function delivery_available($conn)
{
    $rows = [];
    $result = mysqli_query(
        $conn,
        "SELECT o.order_id, o.status, o.delivery_address, o.delivery_fee, o.fast_delivery,
                o.created_at, sp.shop_name, sp.shop_address
         FROM orders o
         JOIN seller_profiles sp ON sp.seller_id = o.seller_id
         WHERE o.rider_id IS NULL AND o.status IN ('confirmed','preparing')
         ORDER BY o.fast_delivery DESC, o.created_at ASC"
    );
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

function order_rider_active_count($conn, $rider_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS cnt FROM orders
         WHERE rider_id = ? AND status NOT IN ('delivered','cancelled')"
    );
    mysqli_stmt_bind_param($stmt, 'i', $rider_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (int) $row['cnt'];
}

function order_search_for_rider($conn, $rider_id, $keyword)
{
    $like = '%' . $keyword . '%';
    $id_match = ctype_digit($keyword) ? (int) $keyword : 0;
    $rows = [];
    $stmt = mysqli_prepare(
        $conn,
        "SELECT o.order_id, o.status, o.delivery_address, o.delivery_fee, o.created_at,
                sp.shop_name, u.full_name AS customer_name
         FROM orders o
         JOIN seller_profiles sp ON sp.seller_id = o.seller_id
         JOIN users u ON u.user_id = o.customer_id
         WHERE o.rider_id = ?
           AND (o.delivery_address LIKE ? OR sp.shop_name LIKE ? OR o.order_id = ?)
         ORDER BY o.created_at DESC"
    );
    mysqli_stmt_bind_param($stmt, 'issi', $rider_id, $like, $like, $id_match);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

// An order plus the ids that prove who is allowed to touch it. Used by the
// AJAX status endpoint, which serves all three roles from one place.
function order_with_parties($conn, $order_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT o.order_id, o.status, o.customer_id, o.rider_id,
                sp.user_id AS seller_user_id, rp.user_id AS rider_user_id
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

// Records the rider's cut and frees them up. Used by the AJAX path, where the
// order is reached generically rather than through delivery_advance().
function order_settle_delivery($conn, $order_id, $rider_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO earnings (rider_id, order_id, amount)
         SELECT rider_id, order_id, ROUND(delivery_fee * ?, 2)
         FROM orders WHERE order_id = ? AND rider_id IS NOT NULL"
    );
    $rate = RIDER_EARNING_RATE;
    mysqli_stmt_bind_param($stmt, 'di', $rate, $order_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($rider_id) {
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE rider_profiles SET availability_status = 'available' WHERE rider_id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'i', $rider_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}
