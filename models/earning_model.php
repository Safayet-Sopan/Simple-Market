<?php
// Rider earnings. One row is written per completed delivery, by the guarded
// UPDATE in delivery_advance() — which runs at most once per order, so a rider
// cannot be paid twice for the same job.

// Builds the shared date filter used by all three earnings queries, so the
// summary, the detail list and the best-day rollup always cover the same window.
function earning_date_filter($rider_id, $date_from, $date_to)
{
    $filter = "";
    $types = "i";
    $params = [$rider_id];

    if ($date_from !== '') {
        $filter .= " AND e.earned_at >= ?";
        $params[] = $date_from . " 00:00:00";
        $types .= "s";
    }
    if ($date_to !== '') {
        $filter .= " AND e.earned_at <= ?";
        $params[] = $date_to . " 23:59:59";
        $types .= "s";
    }
    return [$filter, $types, $params];
}

function earning_summary($conn, $rider_id, $date_from, $date_to)
{
    list($filter, $types, $params) = earning_date_filter($rider_id, $date_from, $date_to);

    $stmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS deliveries,
                COALESCE(SUM(e.amount), 0) AS total,
                COALESCE(SUM(CASE WHEN o.fast_delivery = 1 THEN 1 ELSE 0 END), 0) AS fast_jobs,
                COALESCE(SUM(o.delivery_fee), 0) AS fees_carried
         FROM earnings e
         JOIN orders o ON o.order_id = e.order_id
         WHERE e.rider_id = ?" . $filter
    );
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function earning_rows($conn, $rider_id, $date_from, $date_to)
{
    list($filter, $types, $params) = earning_date_filter($rider_id, $date_from, $date_to);

    $rows = [];
    $stmt = mysqli_prepare(
        $conn,
        "SELECT e.amount, e.earned_at, o.order_id, o.delivery_fee, o.fast_delivery,
                o.delivery_address, sp.shop_name
         FROM earnings e
         JOIN orders o ON o.order_id = e.order_id
         JOIN seller_profiles sp ON sp.seller_id = o.seller_id
         WHERE e.rider_id = ?" . $filter . "
         ORDER BY e.earned_at DESC"
    );
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

// The single best-earning day in the window, so a rider can see which days pay.
function earning_best_day($conn, $rider_id, $date_from, $date_to)
{
    list($filter, $types, $params) = earning_date_filter($rider_id, $date_from, $date_to);

    $stmt = mysqli_prepare(
        $conn,
        "SELECT DATE(e.earned_at) AS day, COUNT(*) AS jobs, SUM(e.amount) AS earned
         FROM earnings e
         WHERE e.rider_id = ?" . $filter . "
         GROUP BY DATE(e.earned_at)
         ORDER BY earned DESC
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function earning_total($conn, $rider_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT COALESCE(SUM(amount),0) AS total FROM earnings WHERE rider_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $rider_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (float) $row['total'];
}
