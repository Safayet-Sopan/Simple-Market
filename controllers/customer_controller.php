<?php
// Customer routes: index.php?page=customer&action=<action>
//
// A customer's identity is their users row, so unlike sellers and riders there
// is no profile table to resolve first — $customer_id is current_user_id().

function customer_dispatch($action)
{
    switch ($action) {
        case 'index':
        case 'dashboard':     customer_dashboard();            break;
        case 'search':        customer_search();               break;
        case 'checkout':      customer_checkout();             break;
        case 'orders':        customer_orders();               break;
        case 'tracking':      customer_tracking();             break;
        case 'offers':        customer_offers();               break;
        case 'feedback':      customer_feedback();             break;
        case 'rating':        customer_rating();               break;
        case 'chat':          chat_page('customer');           break;
        case 'notifications': account_notifications('customer'); break;
        case 'profile':       customer_profile();              break;
        case 'password':      account_change_password('customer'); break;
        default:
            http_response_code(404);
            render('partials/not_found', [
                'page_title' => 'Not found',
                'role_css'   => 'customer',
                'attempted'  => $action,
            ]);
    }
}

function customer_dashboard()
{
    global $conn;
    render('customer/dashboard', [
        'page_title' => 'Customer Dashboard',
        'body_class' => 'page-dashboard',
        'role_css'   => 'customer',
        'counts'     => order_customer_counts($conn, current_user_id()),
    ]);
}

function customer_search()
{
    global $conn;

    $keyword     = cleanInput($_GET['keyword'] ?? '');
    $category_id = $_GET['category_id'] ?? '';
    $hasSearched = isset($_GET['keyword']) || isset($_GET['category_id']);

    render('customer/search', [
        'page_title'  => 'Browse',
        'body_class'  => 'page-search',
        'role_css'    => 'customer',
        'keyword'     => $keyword,
        'category_id' => $category_id,
        'categories'  => product_categories($conn),
        'hasSearched' => $hasSearched,
        'products'    => $hasSearched ? product_catalogue($conn, $keyword, $category_id) : [],
    ]);
}

function customer_checkout()
{
    global $conn, $PAYMENT_METHODS;

    $customer_id = current_user_id();
    $product_id  = $_GET['product_id'] ?? $_POST['product_id'] ?? '';
    $offer_id    = $_GET['offer_id'] ?? $_POST['offer_id'] ?? '';

    if (!is_id($product_id)) {
        render('partials/not_found', [
            'page_title' => 'Invalid product',
            'role_css'   => 'customer',
            'attempted'  => 'checkout without a product',
        ]);
        return;
    }
    $product_id = (int) $product_id;

    $product = order_checkout_product($conn, $product_id);
    if (!$product || $product['approval_status'] !== 'approved') {
        render('partials/not_found', [
            'page_title' => 'Unavailable',
            'role_css'   => 'customer',
            'attempted'  => 'a product that is not available',
        ]);
        return;
    }

    // An accepted bid lets this customer buy below the listed price. It must be
    // theirs, for this product, accepted, and not already spent on another order.
    $offerNotice = "";
    $unit_price = (float) $product['price'];

    if (is_id($offer_id)) {
        $offer_id = (int) $offer_id;
        $offer = offer_redeemable($conn, $offer_id, $customer_id, $product_id);

        if ($offer) {
            // A counter-offer is the agreed price when the seller made one.
            $unit_price = (float) ($offer['counter_price'] !== null
                ? $offer['counter_price']
                : $offer['offered_price']);
        } else {
            // The bid was spent, withdrawn or revoked between the click and here.
            // Say so rather than quietly charging the full listed price.
            $offer_id = 0;
            $offerNotice = "That accepted bid is no longer valid, so this order is priced at the listed price.";
        }
    } else {
        $offer_id = 0;
    }

    // Only the methods this shop actually accepts are offered at checkout.
    $shop_methods = [];
    foreach (explode(',', $product['payment_methods']) as $key) {
        $key = trim($key);
        if (array_key_exists($key, $PAYMENT_METHODS)) {
            $shop_methods[$key] = $PAYMENT_METHODS[$key];
        }
    }
    if (empty($shop_methods)) {
        // Shop configured nothing — fall back to the universal option.
        $shop_methods = ['cod' => $PAYMENT_METHODS['cod']];
    }

    $quantityErr = $addressErr = $stockErr = $paymentErr = "";
    $quantity = 1;
    $delivery_address = "";
    $fast_delivery = 0;
    $payment_method = "";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $quantity = $_POST['quantity'] ?? '';
        if (!ctype_digit((string)$quantity) || (int)$quantity < 1) {
            $quantityErr = "Enter a valid quantity";
        } else {
            $quantity = (int) $quantity;
            if ($quantity > $product['stock_quantity']) {
                $stockErr = "Only {$product['stock_quantity']} in stock";
            }
        }

        $delivery_address = cleanInput($_POST['delivery_address'] ?? '');
        if ($delivery_address === '') {
            $addressErr = "Delivery address is required";
        }

        $fast_delivery = isset($_POST['fast_delivery']) ? 1 : 0;

        $payment_method = $_POST['payment_method'] ?? '';
        if ($payment_method === '') {
            $paymentErr = "Choose a payment method";
        } elseif (!array_key_exists($payment_method, $shop_methods)) {
            $paymentErr = "This shop does not accept that payment method";
        }

        if (!$quantityErr && !$addressErr && !$stockErr && !$paymentErr) {
            $subtotal = $unit_price * $quantity;
            $delivery_fee = $fast_delivery ? FAST_DELIVERY_FEE : STANDARD_DELIVERY_FEE;

            // Commission is worked out and STORED at checkout. A later rate
            // change must not rewrite what this order earned the platform.
            $commission_amount = round($subtotal * ($product['commission_rate'] / 100), 2);
            $total_amount = $subtotal + $delivery_fee;

            $order_id = order_place($conn, [
                'customer_id'       => $customer_id,
                'seller_id'         => $product['seller_id'],
                'product_id'        => $product_id,
                'product_name'      => $product['product_name'],
                'quantity'          => $quantity,
                'unit_price'        => $unit_price,
                'delivery_address'  => $delivery_address,
                'fast_delivery'     => $fast_delivery,
                'delivery_fee'      => $delivery_fee,
                'subtotal'          => $subtotal,
                'commission_amount' => $commission_amount,
                'total_amount'      => $total_amount,
                'payment_method'    => $payment_method,
                'offer_id'          => $offer_id,
            ]);

            if ($order_id) {
                redirect_to('customer', 'tracking', ['order_id' => $order_id]);
            }
            $stockErr = "Something went wrong — order was not placed. Please try again.";
        }
    }

    render('customer/checkout', [
        'page_title'       => 'Place Order',
        'body_class'       => 'page-fast-delivery',
        'role_css'         => 'customer',
        'product'          => $product,
        'offer_id'         => $offer_id,
        'offerNotice'      => $offerNotice,
        'unit_price'       => $unit_price,
        'shop_methods'     => $shop_methods,
        'quantity'         => $quantity,
        'delivery_address' => $delivery_address,
        'fast_delivery'    => $fast_delivery,
        'payment_method'   => $payment_method,
        'quantityErr'      => $quantityErr,
        'addressErr'       => $addressErr,
        'stockErr'         => $stockErr,
        'paymentErr'       => $paymentErr,
        'subtotal_preview' => $unit_price * max(1, (int)$quantity),
    ]);
}

function customer_orders()
{
    global $conn, $PAYMENT_METHODS;

    $customer_id = current_user_id();
    $actionErr = "";
    $successMsg = "";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $order_id = $_POST['order_id'] ?? '';
        $action   = $_POST['action'] ?? '';

        if (!is_id($order_id)) {
            $actionErr = "Invalid order.";
        } else {
            $order_id = (int) $order_id;
            $order = order_find_for_customer($conn, $order_id, $customer_id);

            if (!$order) {
                $actionErr = "Order not found.";
            } elseif (!can_transition('customer', $order['status'], $action)) {
                // A customer may only cancel, and only while still pending.
                $actionErr = "You can only cancel an order while it is still pending.";
            } else {
                $ok = order_change_status(
                    $conn, $order_id, $order['status'], 'cancelled',
                    null, ''
                );
                if ($ok) {
                    notify_seller($conn, $order['seller_id'],
                        "Order #{$order_id} was cancelled by the customer.");
                    $successMsg = "Order #{$order_id} cancelled and the stock returned.";
                } else {
                    $actionErr = "Could not cancel that order. Refresh and try again.";
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

    render('customer/orders', [
        'page_title'      => 'My Orders',
        'body_class'      => 'page-orders',
        'role_css'        => 'customer',
        'orders'          => order_list_for_customer($conn, $customer_id, $filter),
        'filter'          => $filter,
        'valid_filters'   => $valid_filters,
        'payment_methods' => $PAYMENT_METHODS,
        'actionErr'       => $actionErr,
        'successMsg'      => $successMsg,
    ]);
}

function customer_tracking()
{
    global $conn;

    $customer_id = current_user_id();
    $order_id = $_GET['order_id'] ?? '';

    $order = null;
    $items = [];
    $orders = [];

    if (is_id($order_id)) {
        $order = order_track_detail($conn, (int) $order_id, $customer_id);
        if ($order) {
            $items = order_items($conn, (int) $order_id);
        }
    } else {
        $orders = order_track_list($conn, $customer_id);
    }

    render('customer/tracking', [
        'page_title' => 'Order Tracking',
        'body_class' => 'page-order-tracking',
        'role_css'   => 'customer',
        // The view must not read $_GET itself, so say here whether a specific
        // order was asked for — that is what separates "not found" from "list".
        'asked_for_one' => $order_id !== '',
        'order'      => $order,
        'items'      => $items,
        'orders'     => $orders,
        // The status path every non-cancelled order walks, in order.
        'timeline'   => ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered'],
        'status_labels' => [
            'pending'          => 'Order placed — waiting for the shop to confirm',
            'confirmed'        => 'Confirmed by the shop',
            'preparing'        => 'Being prepared',
            'out_for_delivery' => 'Out for delivery',
            'delivered'        => 'Delivered',
            'cancelled'        => 'Cancelled',
        ],
    ]);
}

function customer_offers()
{
    global $conn;

    $customer_id = current_user_id();
    $product_id = $_GET['product_id'] ?? $_POST['product_id'] ?? '';

    $priceErr = $actionErr = "";
    $successMsg = "";
    $offered_price = "";

    // A product_id is optional — without one this page is just the bid list.
    $product = is_id($product_id)
        ? offer_product_for_bidding($conn, (int) $product_id)
        : null;
    $product_id = $product ? (int) $product_id : 0;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? 'bid';

        if ($action === 'bid') {
            if (!$product) {
                $actionErr = "This product is not available.";
            } else {
                $offered_price = $_POST['offered_price'] ?? '';

                if ($offered_price === '') {
                    $priceErr = "Enter the price you want to offer";
                } elseif (!is_numeric($offered_price)) {
                    $priceErr = "Offer must be a number";
                } elseif ((float)$offered_price <= 0) {
                    $priceErr = "Offer must be greater than 0";
                } elseif ((float)$offered_price >= (float)$product['price']) {
                    $priceErr = "Offer must be below the listed price of " . money($product['price']);
                }

                if (!$priceErr) {
                    // One open bid per product, so sellers never see duplicates.
                    if (offer_open_exists($conn, $product_id, $customer_id)) {
                        $actionErr = "You already have an open bid on this product.";
                    } else {
                        $value = round((float)$offered_price, 2);
                        if (offer_create($conn, $product_id, $customer_id, $value, $product['product_name'])) {
                            $successMsg = "Bid sent. You will be notified when the seller responds.";
                            $offered_price = "";
                        } else {
                            $actionErr = "Something went wrong. Your bid was not sent.";
                        }
                    }
                }
            }

        } elseif (in_array($action, ['accept_counter', 'withdraw'], true)) {
            $offer_id = $_POST['offer_id'] ?? '';

            if (!is_id($offer_id)) {
                $actionErr = "Invalid request.";
            } else {
                $offer_id = (int) $offer_id;
                $offer = offer_find_for_customer($conn, $offer_id, $customer_id);

                if (!$offer) {
                    $actionErr = "Bid not found.";
                } elseif ($offer['converted_order_id'] !== null) {
                    $actionErr = "That bid has already been turned into an order.";
                } elseif ($action === 'accept_counter' && $offer['status'] !== 'countered') {
                    $actionErr = "There is no counter-offer to accept.";
                } elseif ($action === 'withdraw'
                    && !in_array($offer['status'], ['pending', 'countered'], true)) {
                    $actionErr = "That bid is already settled.";
                } else {
                    $new_status = ($action === 'accept_counter') ? 'accepted' : 'rejected';
                    $message = ($action === 'accept_counter')
                        ? "The customer accepted your counter of " . money($offer['counter_price'])
                            . " for '" . $offer['product_name'] . "'."
                        : "A customer withdrew their bid on '" . $offer['product_name'] . "'.";

                    if (offer_customer_settle($conn, $offer_id, $new_status,
                                              $offer['seller_id'], $message)) {
                        $successMsg = ($action === 'accept_counter')
                            ? "Counter-offer accepted. You can now order at that price."
                            : "Bid withdrawn.";
                    } else {
                        $actionErr = "Something went wrong. Please try again.";
                    }
                }
            }
        }
    }

    render('customer/offers', [
        'page_title'    => 'Price Bidding',
        'body_class'    => 'page-make-offer',
        'role_css'      => 'customer',
        'product'       => $product,
        'product_id'    => $product_id,
        'offered_price' => $offered_price,
        'my_offers'     => offer_list_for_customer($conn, $customer_id),
        'priceErr'      => $priceErr,
        'actionErr'     => $actionErr,
        'successMsg'    => $successMsg,
    ]);
}

function customer_feedback()
{
    global $conn;

    $customer_id = current_user_id();
    $ratingErr = $commentErr = $actionErr = "";
    $successMsg = "";
    $rating = "";
    $comment = "";

    // Deleting is handled before the submit branch, which would otherwise try
    // to read a rating out of this request.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_review'])) {
        $review_id = $_POST['delete_review'];
        if (!is_id($review_id)) {
            $actionErr = "Invalid request.";
        } elseif (review_delete($conn, (int) $review_id, $customer_id) === 1) {
            $successMsg = "Review deleted. You can write a new one for that product.";
        } else {
            $actionErr = "That review is not yours to delete.";
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $order_id   = $_POST['order_id'] ?? '';
        $product_id = $_POST['product_id'] ?? '';

        if (!is_id($order_id) || !is_id($product_id)) {
            $actionErr = "Invalid request.";
        } else {
            $order_id = (int) $order_id;
            $product_id = (int) $product_id;

            $rating = $_POST['rating'] ?? '';
            $ratingErr = validate_rating($rating);
            if (!$ratingErr) {
                $rating = (int) $rating;
            }

            $comment = cleanInput($_POST['comment'] ?? '');
            if (strlen($comment) > 500) {
                $commentErr = "Keep the comment under 500 characters";
            }

            if (!$ratingErr && !$commentErr) {
                if (!review_eligible($conn, $order_id, $customer_id, $product_id)) {
                    $actionErr = "You can only review a product from an order that reached you.";
                } elseif (review_exists($conn, $order_id, $product_id, $customer_id)) {
                    $actionErr = "You have already reviewed that product for this order.";
                } elseif (review_create($conn, $product_id, $customer_id, $order_id, $rating, $comment)) {
                    $successMsg = "Thanks — your review is live.";
                    $rating = "";
                    $comment = "";
                } else {
                    $actionErr = "Could not save your review. Please try again.";
                }
            }
        }
    }

    render('customer/feedback', [
        'page_title'       => 'Product Feedback',
        'body_class'       => 'page-product-feedback',
        'role_css'         => 'customer',
        'pending_reviews'  => review_pending($conn, $customer_id),
        'my_reviews'       => review_mine($conn, $customer_id),
        'ratingErr'        => $ratingErr,
        'commentErr'       => $commentErr,
        'actionErr'        => $actionErr,
        'successMsg'       => $successMsg,
    ]);
}

function customer_rating()
{
    global $conn;

    $customer_id = current_user_id();
    $ratingErr = $commentErr = $actionErr = "";
    $successMsg = "";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $order_id = $_POST['order_id'] ?? '';

        if (!is_id($order_id)) {
            $actionErr = "Invalid request.";
        } else {
            $order_id = (int) $order_id;

            $rating = $_POST['rating'] ?? '';
            $ratingErr = validate_rating($rating);
            if (!$ratingErr) {
                $rating = (int) $rating;
            }

            $comment = cleanInput($_POST['comment'] ?? '');
            if (strlen($comment) > 500) {
                $commentErr = "Keep the comment under 500 characters";
            }

            if (!$ratingErr && !$commentErr) {
                $seller_id = rating_eligible_seller($conn, $order_id, $customer_id);

                if (!$seller_id) {
                    $actionErr = "You can only rate a shop after an order from it is delivered.";
                } elseif (rating_exists($conn, $order_id, $customer_id)) {
                    $actionErr = "You have already rated the shop for that order.";
                } elseif (rating_create($conn, $seller_id, $customer_id, $order_id, $rating, $comment)) {
                    $successMsg = "Thanks — your rating has been recorded.";
                } else {
                    $actionErr = "Could not save your rating. Please try again.";
                }
            }
        }
    }

    render('customer/rating', [
        'page_title'      => 'Rate a Shop',
        'body_class'      => 'page-seller-rating',
        'role_css'        => 'customer',
        'pending_ratings' => rating_pending($conn, $customer_id),
        'my_ratings'      => rating_mine($conn, $customer_id),
        'ratingErr'       => $ratingErr,
        'commentErr'      => $commentErr,
        'actionErr'       => $actionErr,
        'successMsg'      => $successMsg,
    ]);
}

function customer_profile()
{
    global $conn;

    $nameErr = $phoneErr = "";
    $successMsg = "";
    $user_id = current_user_id();
    $user = user_find($conn, $user_id);

    $full_name = $user['full_name'];
    $phone     = $user['phone'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $full_name = cleanInput($_POST['full_name'] ?? '');
        $nameErr = validate_name($full_name);

        $phone = cleanInput($_POST['phone'] ?? '');
        $phoneErr = validate_phone($phone);

        if (!$nameErr && !$phoneErr) {
            user_update_profile($conn, $user_id, $full_name, $phone);
            $_SESSION['full_name'] = $full_name;
            $successMsg = "Profile updated successfully.";
        }
    }

    render('customer/profile', [
        'page_title' => 'My Profile',
        'body_class' => 'page-profile',
        'role_css'   => 'customer',
        'user'       => $user,
        'full_name'  => $full_name,
        'phone'      => $phone,
        'nameErr'    => $nameErr,
        'phoneErr'   => $phoneErr,
        'successMsg' => $successMsg,
    ]);
}
