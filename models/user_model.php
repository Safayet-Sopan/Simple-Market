<?php

function user_find_by_email($conn, $email)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT user_id, full_name, email, password_hash, role, status
         FROM users WHERE email = ?"
    );
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function user_find($conn, $user_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT user_id, full_name, email, phone, role, status, profile_image, created_at
         FROM users WHERE user_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function user_email_taken($conn, $email, $exclude_id = 0)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT user_id FROM users WHERE email = ? AND user_id <> ?"
    );
    mysqli_stmt_bind_param($stmt, 'si', $email, $exclude_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (bool) $row;
}

function user_password_hash($conn, $user_id)
{
    $stmt = mysqli_prepare($conn, "SELECT password_hash FROM users WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row['password_hash'] ?? null;
}

function user_set_password($conn, $user_id, $plain_password)
{
    $hash = password_hash($plain_password, PASSWORD_DEFAULT);
    $stmt = mysqli_prepare($conn, "UPDATE users SET password_hash = ? WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $hash, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function user_update_profile($conn, $user_id, $full_name, $phone)
{
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE users SET full_name = ?, phone = ? WHERE user_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'ssi', $full_name, $phone, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function user_create($conn, $data)
{
    mysqli_begin_transaction($conn);
    try {
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO users (full_name, email, phone, password_hash, role, status)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param(
            $stmt,
            'ssssss',
            $data['full_name'],
            $data['email'],
            $data['phone'],
            $hash,
            $data['role'],
            $data['status']
        );
        mysqli_stmt_execute($stmt);
        $user_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        if ($data['role'] === 'seller') {
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO seller_profiles (user_id, shop_name, approval_status)
                 VALUES (?, ?, 'pending')"
            );
            mysqli_stmt_bind_param($stmt, 'is', $user_id, $data['shop_name']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } elseif ($data['role'] === 'rider') {
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO rider_profiles (user_id, vehicle_type, availability_status)
                 VALUES (?, ?, 'offline')"
            );
            mysqli_stmt_bind_param($stmt, 'is', $user_id, $data['vehicle_type']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        mysqli_commit($conn);
        return $user_id;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return 0;
    }
}

function user_admin_update($conn, $user_id, $full_name, $email, $phone, $status)
{
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE users SET full_name = ?, email = ?, phone = ?, status = ? WHERE user_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'ssssi', $full_name, $email, $phone, $status, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function user_order_attachment_count($conn, $user_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            (SELECT COUNT(*) FROM orders WHERE customer_id = ?) AS as_customer,
            (SELECT COUNT(*) FROM orders o
               JOIN seller_profiles sp ON sp.seller_id = o.seller_id
              WHERE sp.user_id = ?) AS as_seller,
            (SELECT COUNT(*) FROM orders o
               JOIN rider_profiles rp ON rp.rider_id = o.rider_id
              WHERE rp.user_id = ?) AS as_rider"
    );
    mysqli_stmt_bind_param($stmt, 'iii', $user_id, $user_id, $user_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return (int) $row['as_customer'] + (int) $row['as_seller'] + (int) $row['as_rider'];
}

function user_delete($conn, $user_id)
{
    $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $n = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $n;
}

function user_revoke_remember_tokens($conn, $user_id)
{
    $stmt = mysqli_prepare($conn, "DELETE FROM remember_tokens WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function user_search($conn, $keyword = '', $role_filter = '')
{
    $sql = "SELECT u.user_id, u.full_name, u.email, u.phone, u.role, u.status, u.created_at,
                   sp.shop_name, sp.approval_status
            FROM users u
            LEFT JOIN seller_profiles sp ON sp.user_id = u.user_id
            WHERE 1 = 1";
    $types = '';
    $params = [];

    if ($keyword !== '') {
        $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.user_id = ?)";
        $like = '%' . $keyword . '%';
        $id_match = ctype_digit($keyword) ? (int) $keyword : 0;
        $types .= 'ssi';
        $params[] = $like;
        $params[] = $like;
        $params[] = $id_match;
    }
    if ($role_filter !== '') {
        $sql .= " AND u.role = ?";
        $types .= 's';
        $params[] = $role_filter;
    }
    $sql .= " ORDER BY u.created_at DESC";

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


function seller_profile_by_user($conn, $user_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT seller_id, user_id, shop_name, shop_address, business_type,
                commission_rate, payment_methods, approval_status
         FROM seller_profiles WHERE user_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function seller_profile_update($conn, $seller_id, $shop_name, $shop_address, $business_type)
{
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE seller_profiles SET shop_name = ?, shop_address = ?, business_type = ?
         WHERE seller_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'sssi', $shop_name, $shop_address, $business_type, $seller_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function seller_set_payment_methods($conn, $seller_id, $methods_csv)
{
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE seller_profiles SET payment_methods = ? WHERE seller_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'si', $methods_csv, $seller_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function rider_profile_by_user($conn, $user_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT rider_id, user_id, vehicle_type, vehicle_plate, vehicle_capacity,
                availability_status
         FROM rider_profiles WHERE user_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function rider_profile_update($conn, $rider_id, $vehicle_type, $vehicle_plate, $vehicle_capacity, $availability)
{
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE rider_profiles
         SET vehicle_type = ?, vehicle_plate = ?, vehicle_capacity = ?, availability_status = ?
         WHERE rider_id = ?"
    );
    mysqli_stmt_bind_param(
        $stmt,
        'ssssi',
        $vehicle_type,
        $vehicle_plate,
        $vehicle_capacity,
        $availability,
        $rider_id
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function seller_pending_applications($conn, $order_by)
{
    $rows = [];
    $result = mysqli_query(
        $conn,
        "SELECT sp.seller_id, sp.shop_name, sp.shop_address, sp.business_type, sp.applied_at,
                u.full_name, u.email, u.phone
         FROM seller_profiles sp
         JOIN users u ON u.user_id = sp.user_id
         WHERE sp.approval_status = 'pending'
         ORDER BY " . $order_by
    );
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

function seller_find($conn, $seller_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT seller_id, user_id, shop_name, commission_rate FROM seller_profiles WHERE seller_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $seller_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function seller_set_approval($conn, $seller_id, $owner_user_id, $approve, $shop_name)
{
    $approval_status = $approve ? 'approved' : 'rejected';
    $user_status     = $approve ? 'active'   : 'suspended';
    $message = $approve
        ? "Your shop '{$shop_name}' has been approved. You can now log in."
        : "Your shop '{$shop_name}' application was rejected.";

    mysqli_begin_transaction($conn);
    try {
        $stmt = mysqli_prepare($conn, "UPDATE seller_profiles SET approval_status = ? WHERE seller_id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $approval_status, $seller_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, "UPDATE users SET status = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $user_status, $owner_user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        notify_user($conn, $owner_user_id, $message);

        mysqli_commit($conn);
        return true;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return false;
    }
}

function seller_set_commission_rate($conn, $seller_id, $owner_user_id, $rate)
{
    mysqli_begin_transaction($conn);
    try {
        $stmt = mysqli_prepare($conn, "UPDATE seller_profiles SET commission_rate = ? WHERE seller_id = ?");
        mysqli_stmt_bind_param($stmt, 'di', $rate, $seller_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        notify_user(
            $conn,
            $owner_user_id,
            "Your commission rate is now " . number_format($rate, 2) . "%. This applies to new orders only."
        );

        mysqli_commit($conn);
        return true;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return false;
    }
}

function user_search_people($conn, $keyword)
{
    $like = '%' . $keyword . '%';
    $rows = [];
    $stmt = mysqli_prepare(
        $conn,
        "SELECT u.user_id, u.full_name, u.email, u.role, u.status, sp.shop_name
         FROM users u
         LEFT JOIN seller_profiles sp ON sp.user_id = u.user_id
         WHERE u.full_name LIKE ? OR u.email LIKE ? OR sp.shop_name LIKE ?
         ORDER BY u.full_name ASC"
    );
    mysqli_stmt_bind_param($stmt, 'sss', $like, $like, $like);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}
