<?php
// Seller routes: index.php?page=seller&action=<action>
//
// Every seller action needs the seller_id, not the user_id: products, orders
// and offers all key off seller_profiles. seller_context() does that lookup
// once and is the first line of each action.

function seller_dispatch($action)
{
    switch ($action) {
        case 'index':
        case 'dashboard':     seller_dashboard();            break;
        case 'products':      seller_products();             break;
        case 'low_stock':     seller_low_stock();            break;
        case 'orders':        seller_orders();               break;
        case 'bidding':       seller_bidding();              break;
        case 'payments':      seller_payments();             break;
        case 'search':        seller_search();               break;
        case 'chat':          chat_page('seller');           break;
        case 'notifications': account_notifications('seller'); break;
        case 'profile':       seller_profile();              break;
        case 'password':      account_change_password('seller'); break;
        default:
            http_response_code(404);
            render('partials/not_found', [
                'page_title' => 'Not found',
                'role_css'   => 'seller',
                'attempted'  => $action,
            ]);
    }
}

// A seller account can exist without its profile row, so callers check for null.
function seller_context()
{
    global $conn;
    return seller_profile_by_user($conn, current_user_id());
}

function seller_dashboard()
{
    global $conn;
    $seller = seller_context();
    $seller_id = $seller['seller_id'] ?? null;

    $counts = ['product_count' => 0, 'low_stock_count' => 0];
    $pending_orders = 0;
    if ($seller_id) {
        $counts = product_seller_counts($conn, $seller_id);
        $pending_orders = order_seller_open_count($conn, $seller_id);
    }

    render('seller/dashboard', [
        'page_title'     => 'Seller Dashboard',
        'body_class'     => 'page-dashboard',
        'role_css'       => 'seller',
        'seller'         => $seller,
        'counts'         => $counts,
        'pending_orders' => $pending_orders,
    ]);
}

function seller_products()
{
    global $conn;
    $seller = seller_context();
    $seller_id = $seller['seller_id'] ?? null;

    $nameErr = $priceErr = $stockErr = $imageErr = $deleteErr = "";
    $successMsg = "";

    // ------------------------------------------------ deactivate toggle ----
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status']) && $seller_id) {
        product_toggle_status($conn, (int) $_POST['toggle_status'], $seller_id);
        redirect_to('seller', 'products');
    }

    // ------------------------------------------------------------ delete ----
    //
    // A product that already appears on an order is NOT deletable. order_items
    // cascades on product_id, so removing the product would strip line items
    // out of orders that were placed, paid for and delivered — the totals would
    // survive but the record of what was bought would not. Deactivating is the
    // right move for those.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product']) && $seller_id) {
        $delete_id = (int) $_POST['delete_product'];
        $owned = product_find_for_seller($conn, $delete_id, $seller_id);

        if (!$owned) {
            $deleteErr = "That product does not belong to your shop.";
        } else {
            $ordered = product_order_line_count($conn, $delete_id);
            if ($ordered > 0) {
                $deleteErr = "This product appears on $ordered past order(s), so deleting it "
                    . "would erase part of those orders. Deactivate it instead — customers "
                    . "stop seeing it and the order history stays intact.";
            } elseif (product_delete($conn, $delete_id, $seller_id) === 1) {
                // Drop the uploaded image too. A missing file is not an error.
                if (!empty($owned['product_image'])) {
                    $file = __DIR__ . '/../uploads/products/' . basename($owned['product_image']);
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                $successMsg = "Product deleted.";
            } else {
                $deleteErr = "Could not delete that product.";
            }
        }
    }

    // -------------------------------------------------- load for editing ----
    $edit_id = $_GET['edit'] ?? '';
    $editing_product = ($seller_id && is_id($edit_id))
        ? product_find_for_seller($conn, (int) $edit_id, $seller_id)
        : null;

    $product_name        = $editing_product['product_name'] ?? '';
    $description         = $editing_product['description'] ?? '';
    $price               = $editing_product['price'] ?? '';
    $stock_quantity      = $editing_product['stock_quantity'] ?? '';
    $low_stock_threshold = $editing_product['low_stock_threshold'] ?? 5;
    $category_id         = $editing_product['category_id'] ?? '';
    $existing_image      = $editing_product['product_image'] ?? '';

    // --------------------------------------------------- create / update ----
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product']) && $seller_id) {
        $product_id = $_POST['product_id'] ?? '';

        $product_name = cleanInput($_POST['product_name'] ?? '');
        if ($product_name === '') {
            $nameErr = "Product name is required";
        }

        $description = cleanInput($_POST['description'] ?? '');

        $price = $_POST['price'] ?? '';
        if ($price === '') {
            $priceErr = "Price is required";
        } elseif (!is_numeric($price) || $price <= 0) {
            $priceErr = "Price must be a positive number";
        }

        $stock_quantity = $_POST['stock_quantity'] ?? '';
        if ($stock_quantity === '') {
            $stockErr = "Stock quantity is required";
        } elseif (!ctype_digit((string)$stock_quantity)) {
            $stockErr = "Stock must be a whole number";
        }

        $low_stock_threshold = ctype_digit((string)($_POST['low_stock_threshold'] ?? ''))
            ? (int) $_POST['low_stock_threshold'] : 5;
        $category_id = (($_POST['category_id'] ?? '') !== '') ? (int) $_POST['category_id'] : null;

        // Image upload is optional; an edit keeps the existing file if none came.
        $product_image = $existing_image;
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
            // Trust the sniffed type, never the browser-supplied one.
            $file_type = mime_content_type($_FILES['product_image']['tmp_name']);

            if (!in_array($file_type, $allowed_types, true)) {
                $imageErr = "Only JPG, PNG, or WEBP images are allowed";
            } elseif ($_FILES['product_image']['size'] > 2 * 1024 * 1024) {
                $imageErr = "Image must be under 2MB";
            } else {
                $ext = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
                $new_filename = 'product_' . $seller_id . '_' . time() . '.' . $ext;
                $destination = __DIR__ . '/../uploads/products/' . $new_filename;

                if (move_uploaded_file($_FILES['product_image']['tmp_name'], $destination)) {
                    $product_image = $new_filename;
                } else {
                    $imageErr = "Failed to upload image";
                }
            }
        }

        if (!$nameErr && !$priceErr && !$stockErr && !$imageErr) {
            $data = [
                'product_name'        => $product_name,
                'description'         => $description,
                'price'               => (float) $price,
                'stock_quantity'      => (int) $stock_quantity,
                'low_stock_threshold' => $low_stock_threshold,
                'category_id'         => $category_id,
                'product_image'       => $product_image,
            ];

            if (is_id($product_id)) {
                product_update($conn, (int) $product_id, $seller_id, $data);
                $successMsg = "Product updated successfully.";
            } else {
                product_insert($conn, $seller_id, $data);
                $successMsg = "Product added successfully.";
            }

            // Reset the form after a successful save.
            $product_name = $description = "";
            $price = $stock_quantity = "";
            $low_stock_threshold = 5;
            $category_id = "";
            $editing_product = null;
        }
    }

    render('seller/products', [
        'page_title'          => 'My Products',
        'body_class'          => 'page-products',
        'role_css'            => 'seller',
        'seller'              => $seller,
        'products'            => $seller_id ? product_list_for_seller($conn, $seller_id) : [],
        'categories'          => product_categories($conn),
        'editing_product'     => $editing_product,
        'existing_image'      => $existing_image,
        'product_name'        => $product_name,
        'description'         => $description,
        'price'               => $price,
        'stock_quantity'      => $stock_quantity,
        'low_stock_threshold' => $low_stock_threshold,
        'category_id'         => $category_id,
        'nameErr'             => $nameErr,
        'priceErr'            => $priceErr,
        'stockErr'            => $stockErr,
        'imageErr'            => $imageErr,
        'deleteErr'           => $deleteErr,
        'successMsg'          => $successMsg,
    ]);
}

function seller_low_stock()
{
    global $conn;
    $seller = seller_context();
    $seller_id = $seller['seller_id'] ?? null;

    render('seller/low_stock', [
        'page_title' => 'Low Stock Alert',
        'body_class' => 'page-low-stock-alert',
        'role_css'   => 'seller',
        'products'   => $seller_id ? product_low_stock($conn, $seller_id) : [],
    ]);
}

function seller_orders()
{
    global $conn, $PAYMENT_METHODS;
    $seller = seller_context();
    $seller_id = $seller['seller_id'] ?? null;

    $actionErr = "";
    $successMsg = "";

    // What a seller may move an order to. out_for_delivery and delivered belong
    // to the rider — see order_transitions() in models/order_model.php.
    $allowed_transitions = order_transitions('seller');

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $seller_id) {
        $order_id = $_POST['order_id'] ?? '';
        $action   = $_POST['action'] ?? '';

        if (!is_id($order_id)) {
            $actionErr = "Invalid order.";
        } else {
            $order_id = (int) $order_id;
            $order = order_find_for_seller($conn, $order_id, $seller_id);

            if (!$order) {
                $actionErr = "Order not found.";

            } elseif ($action === 'mark_paid') {
                // Manual payment confirmation — there is no gateway to ask.
                if (order_mark_paid($conn, $order_id) === 1) {
                    $successMsg = "Order #{$order_id} marked as paid.";
                } else {
                    $actionErr = "That order is already marked paid.";
                }

            } elseif (!can_transition('seller', $order['status'], $action)) {
                $actionErr = "You cannot move an order from '" . $order['status']
                    . "' to '" . $action . "'.";

            } else {
                $message = ($action === 'cancelled')
                    ? "Order #{$order_id} was cancelled by the shop."
                    : "Order #{$order_id} is now " . str_replace('_', ' ', $action) . ".";

                if (order_change_status($conn, $order_id, $order['status'], $action,
                                        $order['customer_id'], $message)) {
                    $successMsg = "Order #{$order_id} updated to " . str_replace('_', ' ', $action) . ".";
                } else {
                    $actionErr = "Could not update that order. Refresh and try again.";
                }
            }
        }
    }

    $valid_filters = ['open', 'pending', 'confirmed', 'preparing', 'out_for_delivery',
                      'delivered', 'cancelled', 'all'];
    $filter = $_GET['status'] ?? 'open';
    if (!in_array($filter, $valid_filters, true)) {
        $filter = 'open';
    }

    render('seller/orders', [
        'page_title'          => 'Orders',
        'body_class'          => 'page-orders',
        'role_css'            => 'seller',
        'seller_id'           => $seller_id,
        'orders'              => $seller_id ? order_list_for_seller($conn, $seller_id, $filter) : [],
        'filter'              => $filter,
        'valid_filters'       => $valid_filters,
        'allowed_transitions' => $allowed_transitions,
        'payment_methods'     => $PAYMENT_METHODS,
        'actionErr'           => $actionErr,
        'successMsg'          => $successMsg,
    ]);
}

function seller_bidding()
{
    global $conn;
    $seller = seller_context();
    $seller_id = $seller['seller_id'] ?? null;

    $counterErr = $actionErr = "";
    $successMsg = "";

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $seller_id) {
        $offer_id = $_POST['offer_id'] ?? '';
        $action   = $_POST['action'] ?? '';

        if (!is_id($offer_id) || !in_array($action, ['accept', 'reject', 'counter'], true)) {
            $actionErr = "Invalid request.";
        } else {
            $offer_id = (int) $offer_id;
            $offer = offer_find_for_seller($conn, $offer_id, $seller_id);

            if (!$offer) {
                $actionErr = "Offer not found.";
            } elseif ($offer['converted_order_id'] !== null) {
                $actionErr = "That bid has already been turned into an order.";
            } elseif (!in_array($offer['status'], ['pending', 'countered'], true)) {
                $actionErr = "That bid has already been settled.";
            } else {
                $counter_price = null;
                if ($action === 'counter') {
                    $raw = $_POST['counter_price'] ?? '';
                    if ($raw === '') {
                        $counterErr = "Counter price is required";
                    } elseif (!is_numeric($raw)) {
                        $counterErr = "Counter price must be a number";
                    } elseif ((float)$raw <= 0) {
                        $counterErr = "Counter price must be greater than 0";
                    } elseif ((float)$raw > (float)$offer['price']) {
                        $counterErr = "Counter price cannot exceed the listed price";
                    } else {
                        $counter_price = round((float)$raw, 2);
                    }
                }

                if (!$counterErr) {
                    if ($action === 'accept') {
                        $new_status = 'accepted';
                        $message = "Your offer of " . money($offer['offered_price'])
                            . " for '" . $offer['product_name'] . "' was accepted. Order now at that price.";
                    } elseif ($action === 'reject') {
                        $new_status = 'rejected';
                        $message = "Your offer for '" . $offer['product_name'] . "' was declined.";
                    } else {
                        $new_status = 'countered';
                        $message = "The seller countered your offer for '" . $offer['product_name']
                            . "' at " . money($counter_price) . ".";
                    }

                    if (offer_settle($conn, $offer_id, $new_status, $counter_price,
                                     $offer['customer_id'], $message)) {
                        $successMsg = "Bid " . $new_status . ".";
                    } else {
                        $actionErr = "Something went wrong. Please try again.";
                    }
                }
            }
        }
    }

    $offers = $seller_id ? offer_list_for_seller($conn, $seller_id) : [];
    $open_count = 0;
    foreach ($offers as $o) {
        if ($o['status'] === 'pending') {
            $open_count++;
        }
    }

    render('seller/bidding', [
        'page_title' => 'Price Bidding',
        'body_class' => 'page-price-bidding',
        'role_css'   => 'seller',
        'offers'     => $offers,
        'open_count' => $open_count,
        'counterErr' => $counterErr,
        'actionErr'  => $actionErr,
        'successMsg' => $successMsg,
    ]);
}

function seller_payments()
{
    global $conn, $PAYMENT_METHODS;
    $seller = seller_context();
    $seller_id = $seller['seller_id'] ?? null;

    $methodsErr = "";
    $successMsg = "";

    // Stored as a comma-separated list of keys from $PAYMENT_METHODS.
    $selected = array_filter(explode(',', $seller['payment_methods'] ?? 'cod'));

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $seller_id) {
        $posted = $_POST['methods'] ?? [];
        if (!is_array($posted)) {
            $posted = [];
        }

        // Keep only keys we actually recognise — never trust the posted list.
        $clean = [];
        foreach ($posted as $key) {
            if (array_key_exists($key, $PAYMENT_METHODS) && !in_array($key, $clean, true)) {
                $clean[] = $key;
            }
        }

        if (empty($clean)) {
            $methodsErr = "Pick at least one payment method — customers cannot check out otherwise.";
        } else {
            seller_set_payment_methods($conn, $seller_id, implode(',', $clean));
            $selected = $clean;
            $successMsg = "Payment methods updated. Customers will see these at checkout.";
        }
    }

    render('seller/payments', [
        'page_title'      => 'Payment Methods',
        'body_class'      => 'page-payment-methods',
        'role_css'        => 'seller',
        'seller'          => $seller,
        'seller_id'       => $seller_id,
        'payment_methods' => $PAYMENT_METHODS,
        'selected'        => $selected,
        'methodsErr'      => $methodsErr,
        'successMsg'      => $successMsg,
    ]);
}

function seller_search()
{
    global $conn;
    $seller = seller_context();
    $seller_id = $seller['seller_id'] ?? null;

    $keyword     = cleanInput($_GET['keyword'] ?? '');
    $search_type = ($_GET['search_type'] ?? 'products') === 'orders' ? 'orders' : 'products';
    $hasSearched = isset($_GET['keyword']) && $keyword !== '';

    $products = [];
    $orders = [];
    if ($hasSearched && $seller_id) {
        if ($search_type === 'products') {
            $products = product_search_for_seller($conn, $seller_id, $keyword);
        } else {
            $orders = order_search_for_seller($conn, $seller_id, $keyword);
        }
    }

    render('seller/search', [
        'page_title'  => 'Search',
        'body_class'  => 'page-search',
        'role_css'    => 'seller',
        'keyword'     => $keyword,
        'search_type' => $search_type,
        'hasSearched' => $hasSearched,
        'products'    => $products,
        'orders'      => $orders,
    ]);
}

// The pre-MVC seller/profile.php was a copy of the change-password page, so a
// seller never actually had a profile editor. This is that missing page: the
// account fields plus the shop details that live on seller_profiles.
function seller_profile()
{
    global $conn;

    $nameErr = $phoneErr = $shopErr = "";
    $successMsg = "";
    $user_id = current_user_id();
    $user = user_find($conn, $user_id);
    $seller = seller_context();

    $full_name     = $user['full_name'];
    $phone         = $user['phone'];
    $shop_name     = $seller['shop_name'] ?? '';
    $shop_address  = $seller['shop_address'] ?? '';
    $business_type = $seller['business_type'] ?? '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $full_name = cleanInput($_POST['full_name'] ?? '');
        $nameErr = validate_name($full_name);

        $phone = cleanInput($_POST['phone'] ?? '');
        $phoneErr = validate_phone($phone);

        $shop_name     = cleanInput($_POST['shop_name'] ?? '');
        $shop_address  = cleanInput($_POST['shop_address'] ?? '');
        $business_type = cleanInput($_POST['business_type'] ?? '');
        if ($seller && $shop_name === '') {
            $shopErr = "Shop name is required";
        }

        if (!$nameErr && !$phoneErr && !$shopErr) {
            user_update_profile($conn, $user_id, $full_name, $phone);
            if ($seller) {
                seller_profile_update($conn, $seller['seller_id'], $shop_name, $shop_address, $business_type);
            }
            $_SESSION['full_name'] = $full_name;
            $successMsg = "Profile updated successfully.";
        }
    }

    render('seller/profile', [
        'page_title'    => 'My Profile',
        'body_class'    => 'page-profile',
        'role_css'      => 'seller',
        'user'          => $user,
        'seller'        => $seller,
        'full_name'     => $full_name,
        'phone'         => $phone,
        'shop_name'     => $shop_name,
        'shop_address'  => $shop_address,
        'business_type' => $business_type,
        'nameErr'       => $nameErr,
        'phoneErr'      => $phoneErr,
        'shopErr'       => $shopErr,
        'successMsg'    => $successMsg,
    ]);
}
