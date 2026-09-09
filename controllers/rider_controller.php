<?php
// Rider routes: index.php?page=rider&action=<action>
//
// Deliveries, earnings and notes all key off rider_id, not user_id, so every
// action resolves the rider profile first.

function rider_dispatch($action)
{
    switch ($action) {
        case 'index':
        case 'dashboard':     rider_dashboard();             break;
        case 'deliveries':    rider_deliveries();            break;
        case 'notes':         rider_notes();                 break;
        case 'earnings':      rider_earnings();              break;
        case 'search':        rider_search();                break;
        case 'chat':          chat_page('rider');            break;
        case 'notifications': account_notifications('rider'); break;
        case 'profile':       rider_profile();               break;
        case 'password':      account_change_password('rider'); break;
        default:
            http_response_code(404);
            render('partials/not_found', [
                'page_title' => 'Not found',
                'role_css'   => 'rider',
                'attempted'  => $action,
            ]);
    }
}

// A rider account can exist without its profile row, so callers check for null.
function rider_context()
{
    global $conn;
    return rider_profile_by_user($conn, current_user_id());
}

function rider_dashboard()
{
    global $conn;
    $rider = rider_context();
    $rider_id = $rider['rider_id'] ?? null;

    render('rider/dashboard', [
        'page_title'        => 'Rider Dashboard',
        'body_class'        => 'page-dashboard',
        'role_css'          => 'rider',
        'rider'             => $rider,
        'active_deliveries' => $rider_id ? order_rider_active_count($conn, $rider_id) : 0,
        'total_earnings'    => $rider_id ? earning_total($conn, $rider_id) : 0,
    ]);
}

function rider_deliveries()
{
    global $conn, $PAYMENT_METHODS;
    $rider = rider_context();
    $rider_id = $rider['rider_id'] ?? null;

    $actionErr = "";
    $successMsg = "";

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $rider_id) {
        $order_id = $_POST['order_id'] ?? '';
        $action   = $_POST['action'] ?? '';

        if (!is_id($order_id)
            || !in_array($action, ['claim', 'out_for_delivery', 'delivered'], true)) {
            $actionErr = "Invalid request.";
        } else {
            $order_id = (int) $order_id;

            if ($action === 'claim') {
                if (delivery_claim($conn, $order_id, $rider_id)) {
                    $successMsg = "Order #{$order_id} is yours.";
                } else {
                    $actionErr = "Another rider claimed that order first.";
                }
            } else {
                if (delivery_advance($conn, $order_id, $rider_id, $action)) {
                    $successMsg = "Order #{$order_id} marked " . str_replace('_', ' ', $action) . ".";
                } else {
                    $actionErr = "Could not update that delivery. Refresh and try again.";
                }
            }
        }
    }

    render('rider/deliveries', [
        'page_title'      => 'My Deliveries',
        'body_class'      => 'page-deliveries',
        'role_css'        => 'rider',
        'rider'           => $rider,
        'rider_id'        => $rider_id,
        'my_deliveries'   => $rider_id ? delivery_mine($conn, $rider_id) : [],
        'available'       => $rider_id ? delivery_available($conn) : [],
        'payment_methods' => $PAYMENT_METHODS,
        'actionErr'       => $actionErr,
        'successMsg'      => $successMsg,
    ]);
}

function rider_earnings()
{
    global $conn;
    $rider = rider_context();
    $rider_id = $rider['rider_id'] ?? null;

    $fromErr = $toErr = "";
    $date_from = cleanInput($_GET['date_from'] ?? '');
    $date_to   = cleanInput($_GET['date_to'] ?? '');

    if ($date_from !== '' && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $date_from)) {
        $fromErr = "Use the format YYYY-MM-DD";
        $date_from = '';
    }
    if ($date_to !== '' && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $date_to)) {
        $toErr = "Use the format YYYY-MM-DD";
        $date_to = '';
    }

    $summary = ['deliveries' => 0, 'total' => 0, 'fast_jobs' => 0, 'fees_carried' => 0];
    $rows = [];
    $best_day = null;

    if ($rider_id) {
        $summary  = earning_summary($conn, $rider_id, $date_from, $date_to);
        $rows     = earning_rows($conn, $rider_id, $date_from, $date_to);
        $best_day = earning_best_day($conn, $rider_id, $date_from, $date_to);
    }

    render('rider/earnings', [
        'page_title'     => 'Earnings Calculator',
        'body_class'     => 'page-earnings-calculator',
        'role_css'       => 'rider',
        'rider_id'       => $rider_id,
        'summary'        => $summary,
        'rows'           => $rows,
        'best_day'       => $best_day,
        'average'        => $summary['deliveries'] > 0 ? $summary['total'] / $summary['deliveries'] : 0,
        'platform_share' => $summary['fees_carried'] - $summary['total'],
        'date_from'      => $date_from,
        'date_to'        => $date_to,
        'fromErr'        => $fromErr,
        'toErr'          => $toErr,
    ]);
}

function rider_search()
{
    global $conn;
    $rider = rider_context();
    $rider_id = $rider['rider_id'] ?? null;

    $keyword = cleanInput($_GET['keyword'] ?? '');
    $hasSearched = isset($_GET['keyword']) && $keyword !== '';

    render('rider/search', [
        'page_title'  => 'Search',
        'body_class'  => 'page-search',
        'role_css'    => 'rider',
        'keyword'     => $keyword,
        'hasSearched' => $hasSearched,
        'orders'      => ($hasSearched && $rider_id)
            ? order_search_for_rider($conn, $rider_id, $keyword) : [],
    ]);
}

// The rider's own CRUD object. Nothing else in a rider's world is theirs to
// create or destroy — orders and earnings are written by the system.
function rider_notes()
{
    global $conn;
    $rider = rider_context();
    $rider_id = $rider['rider_id'] ?? null;

    $titleErr = $bodyErr = $orderErr = $actionErr = "";
    $successMsg = "";
    $title = $body = "";
    $order_id = "";

    // ------------------------------------------------------------ delete ----
    if ($rider_id && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_note'])) {
        $note_id = $_POST['delete_note'];
        if (!is_id($note_id)) {
            $actionErr = "Invalid request.";
        } elseif (note_delete($conn, (int) $note_id, $rider_id) === 1) {
            $successMsg = "Note deleted.";
        } else {
            $actionErr = "That note is not yours to delete.";
        }
    }

    // -------------------------------------------------- load for editing ----
    $edit_id = $_GET['edit'] ?? '';
    $editing_note = ($rider_id && is_id($edit_id))
        ? note_find($conn, (int) $edit_id, $rider_id)
        : null;
    if ($editing_note) {
        $title    = $editing_note['title'];
        $body     = $editing_note['body'];
        $order_id = $editing_note['order_id'] ?? '';
    }

    // --------------------------------------------------- create / update ----
    if ($rider_id && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_note'])) {
        $posted_id = $_POST['note_id'] ?? '';
        $is_update = is_id($posted_id);
        $posted_id = $is_update ? (int) $posted_id : 0;

        $title = cleanInput($_POST['title'] ?? '');
        if ($title === '') {
            $titleErr = "Give the note a title";
        } elseif (strlen($title) > 120) {
            $titleErr = "Keep the title under 120 characters";
        }

        $body = cleanInput($_POST['body'] ?? '');
        if (strlen($body) > 2000) {
            $bodyErr = "Keep the note under 2000 characters";
        }

        // Pinning to an order is optional, but the order has to be one this
        // rider actually carried.
        $order_id = trim($_POST['order_id'] ?? '');
        $order_ref = null;
        if ($order_id !== '') {
            if (!ctype_digit($order_id)) {
                $orderErr = "Order number must be digits, or leave it blank";
            } elseif (!note_order_is_mine($conn, (int) $order_id, $rider_id)) {
                $orderErr = "That is not one of your deliveries";
            } else {
                $order_ref = (int) $order_id;
            }
        }

        if (!$titleErr && !$bodyErr && !$orderErr) {
            if ($is_update) {
                note_update($conn, $posted_id, $rider_id, $order_ref, $title, $body);
                $successMsg = "Note updated.";
            } else {
                note_create($conn, $rider_id, $order_ref, $title, $body);
                $successMsg = "Note saved.";
            }
            $title = $body = "";
            $order_id = "";
            $editing_note = null;
        }
    }

    $keyword = cleanInput($_GET['q'] ?? '');

    render('rider/notes', [
        'page_title'   => 'Delivery Notes',
        'body_class'   => 'page-delivery-notes',
        'role_css'     => 'rider',
        'rider_id'     => $rider_id,
        'notes'        => $rider_id ? note_list($conn, $rider_id, $keyword) : [],
        'editing_note' => $editing_note,
        'keyword'      => $keyword,
        'title'        => $title,
        'body'         => $body,
        'order_id'     => $order_id,
        'titleErr'     => $titleErr,
        'bodyErr'      => $bodyErr,
        'orderErr'     => $orderErr,
        'actionErr'    => $actionErr,
        'successMsg'   => $successMsg,
    ]);
}

// Includes the vehicle fields, which are the rider's unique "update vehicle"
// feature rather than a page of their own.
function rider_profile()
{
    global $conn;

    $nameErr = $phoneErr = "";
    $successMsg = "";
    $user_id = current_user_id();
    $user = user_find($conn, $user_id);
    $rider = rider_context();

    $full_name = $user['full_name'];
    $phone     = $user['phone'];
    $vehicle_type     = $rider['vehicle_type'] ?? '';
    $vehicle_plate    = $rider['vehicle_plate'] ?? '';
    $vehicle_capacity = $rider['vehicle_capacity'] ?? '';
    $availability     = $rider['availability_status'] ?? 'offline';

    $allowed_availability = ['available', 'busy', 'offline'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $full_name = cleanInput($_POST['full_name'] ?? '');
        $nameErr = validate_name($full_name);

        $phone = cleanInput($_POST['phone'] ?? '');
        $phoneErr = validate_phone($phone);

        $vehicle_type     = cleanInput($_POST['vehicle_type'] ?? '');
        $vehicle_plate    = cleanInput($_POST['vehicle_plate'] ?? '');
        $vehicle_capacity = cleanInput($_POST['vehicle_capacity'] ?? '');

        $availability = $_POST['availability_status'] ?? 'offline';
        if (!in_array($availability, $allowed_availability, true)) {
            $availability = 'offline';
        }

        if (!$nameErr && !$phoneErr) {
            user_update_profile($conn, $user_id, $full_name, $phone);
            if ($rider) {
                rider_profile_update(
                    $conn, $rider['rider_id'],
                    $vehicle_type, $vehicle_plate, $vehicle_capacity, $availability
                );
            }
            $_SESSION['full_name'] = $full_name;
            $successMsg = "Profile updated successfully.";
        }
    }

    render('rider/profile', [
        'page_title'           => 'My Profile',
        'body_class'           => 'page-profile',
        'role_css'             => 'rider',
        'user'                 => $user,
        'rider'                => $rider,
        'full_name'            => $full_name,
        'phone'                => $phone,
        'vehicle_type'         => $vehicle_type,
        'vehicle_plate'        => $vehicle_plate,
        'vehicle_capacity'     => $vehicle_capacity,
        'availability'         => $availability,
        'allowed_availability' => $allowed_availability,
        'nameErr'              => $nameErr,
        'phoneErr'             => $phoneErr,
        'successMsg'           => $successMsg,
    ]);
}
