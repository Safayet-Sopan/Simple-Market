<?php

function report_public_figures($conn)
{
    $row = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT
            (SELECT COUNT(*) FROM seller_profiles WHERE approval_status = 'approved') AS shops,
            (SELECT COUNT(*) FROM products WHERE status = 'active')                   AS products,
            (SELECT COUNT(*) FROM orders WHERE status = 'delivered')                  AS delivered,
            (SELECT COUNT(*) FROM rider_profiles)                                     AS riders"
    ));

    return [
        'shops'     => (int) ($row['shops'] ?? 0),
        'products'  => (int) ($row['products'] ?? 0),
        'delivered' => (int) ($row['delivered'] ?? 0),
        'riders'    => (int) ($row['riders'] ?? 0),
    ];
}

function report_admin_dashboard($conn)
{
    $row = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT
            (SELECT COUNT(*) FROM seller_profiles WHERE approval_status = 'pending')  AS pending_sellers,
            (SELECT COUNT(*) FROM seller_profiles WHERE approval_status = 'approved') AS active_sellers,
            (SELECT COUNT(*) FROM orders WHERE status = 'delivered')                  AS total_orders,
            (SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status = 'delivered') AS total_revenue"
    ));
    return $row;
}

function report_commission_totals($conn)
{
    return mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT
            COALESCE(SUM(CASE WHEN status = 'delivered' THEN commission_amount END), 0) AS earned,
            COALESCE(SUM(CASE WHEN status NOT IN ('delivered','cancelled') THEN commission_amount END), 0) AS pending,
            COUNT(CASE WHEN status = 'delivered' THEN 1 END) AS delivered_orders
         FROM orders"
    ));
}

function report_commission_by_seller($conn)
{
    $rows = [];
    $result = mysqli_query(
        $conn,
        "SELECT sp.seller_id, sp.shop_name, sp.commission_rate, u.full_name,
                COUNT(CASE WHEN o.status = 'delivered' THEN 1 END) AS delivered_orders,
                COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN o.commission_amount END), 0) AS commission_earned,
                COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN o.subtotal END), 0) AS gross_sales
         FROM seller_profiles sp
         JOIN users u ON u.user_id = sp.user_id
         LEFT JOIN orders o ON o.seller_id = sp.seller_id
         WHERE sp.approval_status = 'approved'
         GROUP BY sp.seller_id, sp.shop_name, sp.commission_rate, u.full_name
         ORDER BY commission_earned DESC"
    );
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

function report_seller_sales($conn, $date_from, $date_to, $order_by)
{
    list($date_filter, $types, $params) = report_date_filter($date_from, $date_to);

    $sql = "SELECT sp.seller_id, sp.shop_name, sp.commission_rate, u.full_name,
                   COUNT(CASE WHEN o.status = 'delivered' THEN 1 END) AS delivered_orders,
                   COUNT(CASE WHEN o.status = 'cancelled' THEN 1 END) AS cancelled_orders,
                   COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN o.subtotal END), 0) AS gross_sales,
                   COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN o.commission_amount END), 0) AS commission_earned
            FROM seller_profiles sp
            JOIN users u ON u.user_id = sp.user_id
            LEFT JOIN orders o ON o.seller_id = sp.seller_id" . $date_filter . "
            WHERE sp.approval_status = 'approved'
            GROUP BY sp.seller_id, sp.shop_name, sp.commission_rate, u.full_name
            ORDER BY " . $order_by;

    $rows = [];
    $stmt = mysqli_prepare($conn, $sql);
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function report_platform_totals($conn, $date_from, $date_to)
{
    list($date_filter, $types, $params) = report_date_filter($date_from, $date_to);

    $sql = "SELECT COUNT(CASE WHEN o.status = 'delivered' THEN 1 END) AS delivered_orders,
                   COUNT(*) AS all_orders,
                   COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN o.subtotal END), 0) AS gross_sales,
                   COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN o.commission_amount END), 0) AS commission_earned,
                   COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN o.delivery_fee END), 0) AS delivery_fees
            FROM orders o
            WHERE 1 = 1" . $date_filter;

    $stmt = mysqli_prepare($conn, $sql);
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function report_date_filter($date_from, $date_to)
{
    $filter = "";
    $types = "";
    $params = [];

    if ($date_from !== '') {
        $filter .= " AND o.created_at >= ?";
        $params[] = $date_from . " 00:00:00";
        $types .= "s";
    }
    if ($date_to !== '') {
        $filter .= " AND o.created_at <= ?";
        $params[] = $date_to . " 23:59:59";
        $types .= "s";
    }
    return [$filter, $types, $params];
}
