<?php
// Handle AJAX actions (Add to Cart, Buy Now, Submit Review, Pre-Order Notification)
if ($is_ajax && isset($_POST['action'])) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    // --- ADD TO BASKET / CART LOGIC ---
    if ($_POST['action'] == 'add' || $_POST['action'] == 'add_to_cart') {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        $product_id = (int)$_POST['product_id'];
        $variation_id = !empty($_POST['variation_id']) ? (int)$_POST['variation_id'] : 0;
        $quantity = !empty($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

        if ($product_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid product selected.']);
            exit;
        }

        // 1. Fetch Item Unit Price (Variation or Simple Product)
        $unit_price = 0.00;
        if ($variation_id > 0) {
            $v_stmt = $pdo->prepare("SELECT regular_price, sale_price FROM product_variations WHERE id = ? AND product_id = ? LIMIT 1");
            $v_stmt->execute([$variation_id, $product_id]);
            $v_data = $v_stmt->fetch(PDO::FETCH_ASSOC);
            if ($v_data) {
                $unit_price = (!empty($v_data['sale_price']) && (float)$v_data['sale_price'] > 0) 
                    ? (float)$v_data['sale_price'] 
                    : (float)$v_data['regular_price'];
            }
        }

        if ($unit_price <= 0) {
            $p_stmt = $pdo->prepare("SELECT regular_price, sale_price, sale_price_dates_from, sale_price_dates_to FROM products WHERE id = ? LIMIT 1");
            $p_stmt->execute([$product_id]);
            $p_data = $p_stmt->fetch(PDO::FETCH_ASSOC);
            if ($p_data) {
                $today = date('Y-m-d');
                $is_on_sale = false;
                if (!empty($p_data['sale_price']) && (float)$p_data['sale_price'] > 0) {
                    $s_from = $p_data['sale_price_dates_from'] ?? null;
                    $s_to = $p_data['sale_price_dates_to'] ?? null;
                    if ((empty($s_from) || $s_from <= $today) && (empty($s_to) || $s_to >= $today)) {
                        $is_on_sale = true;
                    }
                }
                $unit_price = $is_on_sale ? (float)$p_data['sale_price'] : (float)$p_data['regular_price'];
            }
        }

        // 2. Add or Update in Session Cart
        $cart_key = $product_id . '_' . $variation_id;

        if (isset($_SESSION['cart'][$cart_key])) {
            $_SESSION['cart'][$cart_key]['quantity'] += $quantity;
            $_SESSION['cart'][$cart_key]['price'] = $unit_price;
        } else {
            $_SESSION['cart'][$cart_key] = [
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'quantity'     => $quantity,
                'price'        => $unit_price
            ];
        }

        // 3. Calculate Overall Cart Total and Item Count
        $cart_count = 0;
        $cart_total = 0.00;
        foreach ($_SESSION['cart'] as $k => &$ci) {
            $ci_qty = (int)($ci['quantity'] ?? 1);
            $ci_price = (float)($ci['price'] ?? 0.00);

            // Price fallback for any older cart items
            if ($ci_price <= 0) {
                $c_pid = (int)($ci['product_id'] ?? 0);
                $c_vid = (int)($ci['variation_id'] ?? 0);
                if ($c_vid > 0) {
                    $c_vstmt = $pdo->prepare("SELECT regular_price, sale_price FROM product_variations WHERE id = ? LIMIT 1");
                    $c_vstmt->execute([$c_vid]);
                    $cv_row = $c_vstmt->fetch(PDO::FETCH_ASSOC);
                    if ($cv_row) {
                        $ci_price = (!empty($cv_row['sale_price']) && (float)$cv_row['sale_price'] > 0) ? (float)$cv_row['sale_price'] : (float)$cv_row['regular_price'];
                    }
                }
                if ($ci_price <= 0 && $c_pid > 0) {
                    $c_pstmt = $pdo->prepare("SELECT regular_price, sale_price, sale_price_dates_from, sale_price_dates_to FROM products WHERE id = ? LIMIT 1");
                    $c_pstmt->execute([$c_pid]);
                    $cp_row = $c_pstmt->fetch(PDO::FETCH_ASSOC);
                    if ($cp_row) {
                        $today = date('Y-m-d');
                        $c_sale = false;
                        if (!empty($cp_row['sale_price']) && (float)$cp_row['sale_price'] > 0) {
                            $s_from = $cp_row['sale_price_dates_from'] ?? null;
                            $s_to = $cp_row['sale_price_dates_to'] ?? null;
                            if ((empty($s_from) || $s_from <= $today) && (empty($s_to) || $s_to >= $today)) {
                                $c_sale = true;
                            }
                        }
                        $ci_price = $c_sale ? (float)$cp_row['sale_price'] : (float)$cp_row['regular_price'];
                    }
                }
                $ci['price'] = $ci_price;
            }

            $cart_count += $ci_qty;
            $cart_total += ($ci_qty * $ci_price);
        }
        unset($ci);

        echo json_encode([
            'status'               => 'success',
            'message'              => 'Book added to basket successfully!',
            'cart_count'           => $cart_count,
            'cart_total'           => $cart_total,
            'formatted_cart_total' => number_format($cart_total, 2)
        ]);
        exit;
    }

    // --- DIRECT BUY NOW LOGIC ---
    if ($_POST['action'] == 'buy_now') {
        $product_id = (int)$_POST['product_id'];
        $variation_id = !empty($_POST['variation_id']) ? (int)$_POST['variation_id'] : 0;
        $quantity = !empty($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

        if ($product_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid product selected.']);
            exit;
        }

        // Fetch Unit Price
        $unit_price = 0.00;
        if ($variation_id > 0) {
            $v_stmt = $pdo->prepare("SELECT regular_price, sale_price FROM product_variations WHERE id = ? AND product_id = ? LIMIT 1");
            $v_stmt->execute([$variation_id, $product_id]);
            $v_data = $v_stmt->fetch(PDO::FETCH_ASSOC);
            if ($v_data) {
                $unit_price = (!empty($v_data['sale_price']) && (float)$v_data['sale_price'] > 0) 
                    ? (float)$v_data['sale_price'] 
                    : (float)$v_data['regular_price'];
            }
        }

        if ($unit_price <= 0) {
            $p_stmt = $pdo->prepare("SELECT regular_price, sale_price, sale_price_dates_from, sale_price_dates_to FROM products WHERE id = ? LIMIT 1");
            $p_stmt->execute([$product_id]);
            $p_data = $p_stmt->fetch(PDO::FETCH_ASSOC);
            if ($p_data) {
                $today = date('Y-m-d');
                $is_on_sale = false;
                if (!empty($p_data['sale_price']) && (float)$p_data['sale_price'] > 0) {
                    $s_from = $p_data['sale_price_dates_from'] ?? null;
                    $s_to = $p_data['sale_price_dates_to'] ?? null;
                    if ((empty($s_from) || $s_from <= $today) && (empty($s_to) || $s_to >= $today)) {
                        $is_on_sale = true;
                    }
                }
                $unit_price = $is_on_sale ? (float)$p_data['sale_price'] : (float)$p_data['regular_price'];
            }
        }

        $_SESSION['cart'] = [];

        $cart_key = $product_id . '_' . $variation_id;
        $_SESSION['cart'][$cart_key] = [
            'product_id'   => $product_id,
            'variation_id' => $variation_id,
            'quantity'     => $quantity,
            'price'        => $unit_price
        ];

        echo json_encode(['status' => 'success', 'redirect' => '/checkout']);
        exit;
    }

    // --- SUBMIT REVIEW LOGIC ---
    if ($_POST['action'] == 'submit_review') {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'You must be logged in to leave a review.']);
            exit;
        }

        $user_id = (int)$_SESSION['user_id'];
        $product_id = (int)$_POST['product_id'];
        $rating = (int)$_POST['rating'];
        $comment = trim(htmlspecialchars($_POST['comment'], ENT_QUOTES, 'UTF-8'));

        if ($rating < 1 || $rating > 5 || empty($comment)) {
            echo json_encode(['status' => 'error', 'message' => 'Please provide a valid star rating and comment.']);
            exit;
        }

        $verify_stmt = $pdo->prepare("SELECT o.id FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE o.user_id = ? AND oi.product_id = ? AND o.order_status = 'completed' LIMIT 1");
        $verify_stmt->execute([$user_id, $product_id]);
        if (!$verify_stmt->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Only verified purchasers can review this title.']);
            exit;
        }

        try {
            $ins_rev = $pdo->prepare("INSERT INTO product_reviews (product_id, user_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
            $ins_rev->execute([$product_id, $user_id, $rating, $comment]);
            echo json_encode(['status' => 'success', 'message' => 'Your review was posted successfully!']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'You have already reviewed this book or an error occurred.']);
        }
        exit;
    }

    // --- PRE-ORDER / RESTOCK NOTIFICATION HANDLER ---
    if ($_POST['action'] === 'preorder_notify') {
        $pre_product_id = (int)($_POST['product_id'] ?? 0);
        $pre_name       = trim(htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8'));
        $pre_email      = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
        $pre_phone      = trim(htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8'));

        if ($pre_product_id <= 0 || empty($pre_name) || empty($pre_email)) {
            echo json_encode(['status' => 'error', 'message' => 'Please provide both your Name and Email address.']);
            exit;
        }

        if (!filter_var($pre_email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email address provided.']);
            exit;
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS product_preorders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                customer_name VARCHAR(150) NOT NULL,
                customer_email VARCHAR(150) NOT NULL,
                customer_phone VARCHAR(50) NULL,
                status ENUM('pending', 'notified') DEFAULT 'pending',
                notified_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX (product_id),
                INDEX (status)
            )");

            $chk = $pdo->prepare("SELECT id FROM product_preorders WHERE product_id = ? AND customer_email = ? AND status = 'pending' LIMIT 1");
            $chk->execute([$pre_product_id, $pre_email]);
            if ($chk->fetch()) {
                echo json_encode(['status' => 'success', 'message' => 'You are already registered! We will send an email as soon as copies arrive.']);
                exit;
            }

            $ins = $pdo->prepare("INSERT INTO product_preorders (product_id, customer_name, customer_email, customer_phone, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
            $ins->execute([$pre_product_id, $pre_name, $pre_email, $pre_phone]);

            echo json_encode(['status' => 'success', 'message' => 'Pre-order registered! You will receive an instant email when restocked.']);
            exit;
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
}