<?php
// Products, and the catalogue the customer browses.
//
// A product belongs to a seller_profiles row, never directly to a user, so
// every seller-scoped query takes a $seller_id.

function product_categories($conn)
{
    $rows = [];
    $result = mysqli_query($conn, "SELECT category_id, category_name FROM categories ORDER BY category_name");
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

function product_find_for_seller($conn, $product_id, $seller_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT * FROM products WHERE product_id = ? AND seller_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $product_id, $seller_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function product_list_for_seller($conn, $seller_id)
{
    $rows = [];
    $stmt = mysqli_prepare(
        $conn,
        "SELECT * FROM products WHERE seller_id = ? ORDER BY created_at DESC"
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

function product_insert($conn, $seller_id, $d)
{
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO products (seller_id, category_id, product_name, description, price,
                               stock_quantity, low_stock_threshold, product_image, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')"
    );
    mysqli_stmt_bind_param(
        $stmt, 'iissdiis',
        $seller_id, $d['category_id'], $d['product_name'], $d['description'], $d['price'],
        $d['stock_quantity'], $d['low_stock_threshold'], $d['product_image']
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function product_update($conn, $product_id, $seller_id, $d)
{
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE products
         SET product_name = ?, description = ?, price = ?, stock_quantity = ?,
             low_stock_threshold = ?, category_id = ?, product_image = ?
         WHERE product_id = ? AND seller_id = ?"
    );
    mysqli_stmt_bind_param(
        $stmt, 'ssdiiisii',
        $d['product_name'], $d['description'], $d['price'], $d['stock_quantity'],
        $d['low_stock_threshold'], $d['category_id'], $d['product_image'],
        $product_id, $seller_id
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Hides a product from customers without touching order history.
function product_toggle_status($conn, $product_id, $seller_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE products SET status = IF(status = 'active', 'inactive', 'active')
         WHERE product_id = ? AND seller_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $product_id, $seller_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// How many order lines reference this product. order_items cascades on
// product_id, so a product with any history must not be deleted.
function product_order_line_count($conn, $product_id)
{
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS ordered FROM order_items WHERE product_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $product_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (int) $row['ordered'];
}

function product_delete($conn, $product_id, $seller_id)
{
    // seller_id stays in the WHERE clause — this is what actually enforces
    // ownership, not the lookup the controller did beforehand.
    $stmt = mysqli_prepare($conn, "DELETE FROM products WHERE product_id = ? AND seller_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $product_id, $seller_id);
    mysqli_stmt_execute($stmt);
    $n = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $n;
}

function product_low_stock($conn, $seller_id)
{
    $rows = [];
    $stmt = mysqli_prepare(
        $conn,
        "SELECT product_id, product_name, stock_quantity, low_stock_threshold, status
         FROM products
         WHERE seller_id = ? AND stock_quantity <= low_stock_threshold
         ORDER BY stock_quantity ASC"
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

function product_search_for_seller($conn, $seller_id, $keyword)
{
    $rows = [];
    $like = '%' . $keyword . '%';
    $stmt = mysqli_prepare(
        $conn,
        "SELECT product_id, product_name, price, stock_quantity, status
         FROM products WHERE seller_id = ? AND product_name LIKE ?
         ORDER BY product_name ASC"
    );
    mysqli_stmt_bind_param($stmt, 'is', $seller_id, $like);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function product_seller_counts($conn, $seller_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS product_count,
                COUNT(CASE WHEN stock_quantity <= low_stock_threshold THEN 1 END) AS low_stock_count
         FROM products WHERE seller_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $seller_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

// The customer-facing catalogue: only active products in approved shops.
// Filters change the SQL shape, so the shape is built from fixed fragments and
// every value is still bound.
function product_catalogue($conn, $keyword, $category_id)
{
    $sql = "SELECT p.product_id, p.product_name, p.description, p.price, p.stock_quantity,
                   p.product_image, sp.shop_name, c.category_name
            FROM products p
            JOIN seller_profiles sp ON sp.seller_id = p.seller_id
            LEFT JOIN categories c ON c.category_id = p.category_id
            WHERE p.status = 'active' AND sp.approval_status = 'approved'";
    $types = '';
    $params = [];

    if ($keyword !== '') {
        $sql .= " AND p.product_name LIKE ?";
        $types .= 's';
        $params[] = '%' . $keyword . '%';
    }
    if ($category_id !== '' && ctype_digit((string)$category_id)) {
        $sql .= " AND p.category_id = ?";
        $types .= 'i';
        $params[] = (int) $category_id;
    }
    $sql .= " ORDER BY p.product_name ASC";

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
