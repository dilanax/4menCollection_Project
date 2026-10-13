<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db.php'; 

// Security Check: Only allow AJAX requests
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
if (!$is_ajax || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Direct access not permitted');
}

header('Content-Type: application/json');

function generateRandomPassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    return substr(str_shuffle($chars), 0, $length);
}

// ========================================================================
// ACTION 1: CALCULATE SHIPPING
// ========================================================================
if (isset($_POST['action']) && $_POST['action'] == 'calculate_shipping') {
    
    $district_name = trim($_POST['district_name'] ?? '');
    $clean_district = trim(str_replace('District', '', $district_name));
    
    // 1. Flexible Zone Matching: try exact match, cleaned district name, LIKE match, or default zone
    $z_stmt = $pdo->prepare("SELECT id FROM shipping_zones WHERE zone_name = ? OR zone_name = ? OR zone_name LIKE ? ORDER BY id ASC LIMIT 1");
    $z_stmt->execute([$district_name, $clean_district, "%" . $clean_district . "%"]);
    $zone = $z_stmt->fetch();
    
    if (!$zone) {
        // Fallback: search for any existing zone in shipping_zones
        try {
            $z_stmt = $pdo->query("SELECT id FROM shipping_zones ORDER BY id ASC LIMIT 1");
            $zone = $z_stmt->fetch();
        } catch (Exception $e) {
            $zone = false;
        }
    }
    
    if (!$zone) {
        try {
            $pdo->exec("INSERT INTO shipping_zones (zone_name, is_default) VALUES ('Standard Zone', 1)");
            $zone_id = (int)$pdo->lastInsertId();
        } catch (Exception $e) {
            $zone_id = 1;
        }
    } else {
        $zone_id = (int)$zone['id'];
    }

    $subtotal = 0;
    $total_shipping_class_cost = 0;

    foreach ($_SESSION['cart'] as $item) {
        $pid = (int)$item['product_id'];
        $vid = (int)$item['variation_id'];
        $qty = (int)$item['quantity'];

        $stmt = $pdo->prepare("SELECT p.regular_price, p.sale_price, p.shipping_class_id, 
                                      sc.additional_cost, sc.cost_conditions 
                               FROM products p 
                               LEFT JOIN shipping_classes sc ON p.shipping_class_id = sc.id 
                               WHERE p.id = ?");
        $stmt->execute([$pid]);
        $p = $stmt->fetch();

        if ($p) {
            $price = $p['sale_price'] > 0 ? $p['sale_price'] : $p['regular_price'];
            if ($vid > 0) {
                $v_stmt = $pdo->prepare("SELECT regular_price, sale_price FROM product_variations WHERE id = ?");
                $v_stmt->execute([$vid]);
                $v = $v_stmt->fetch();
                if ($v) { $price = $v['sale_price'] > 0 ? $v['sale_price'] : $v['regular_price']; }
            }
            $subtotal += ($price * $qty);

            if ($p['shipping_class_id']) {
                $item_class_cost = 0;
                $condition_met = false;
                if (!empty($p['cost_conditions'])) {
                    $conditions = json_decode($p['cost_conditions'], true);
                    if (is_array($conditions)) {
                        foreach ($conditions as $cond) {
                            $min = (int)$cond['min'];
                            $max = (int)($cond['max'] > 0 ? $cond['max'] : 9999999);
                            $cost = (float)$cond['cost'];
                            if ($qty >= $min && $qty <= $max) {
                                $item_class_cost = $cost * $qty;
                                $condition_met = true;
                                break;
                            }
                        }
                    }
                }
                if (!$condition_met && $p['additional_cost'] > 0) {
                    $item_class_cost = $p['additional_cost'] * $qty;
                }
                $total_shipping_class_cost += $item_class_cost;
            }
        }
    }

    // 2. Fetch shipping methods for the matched zone
    try {
        $methods_stmt = $pdo->prepare("SELECT id, method_name, cost, min_order_amount FROM shipping_methods WHERE zone_id = ? AND is_active = 1 ORDER BY cost ASC");
        $methods_stmt->execute([$zone_id]);
        $raw_methods = $methods_stmt->fetchAll();
    } catch (Exception $e) {
        $raw_methods = [];
    }

    // Fallback 1: If no shipping methods assigned to this specific zone, fetch any active shipping methods
    if (empty($raw_methods)) {
        try {
            $fallback_stmt = $pdo->query("SELECT id, method_name, cost, min_order_amount FROM shipping_methods WHERE is_active = 1 ORDER BY cost ASC");
            $raw_methods = $fallback_stmt->fetchAll();
        } catch (Exception $e) {
            $raw_methods = [];
        }
    }

    $available_methods = [];

    if (!empty($raw_methods)) {
        foreach ($raw_methods as $m) {
            if ($m['min_order_amount'] !== null && $m['min_order_amount'] > 0) {
                if ($subtotal < $m['min_order_amount']) { continue; }
            }
            
            $final_method_cost = $m['cost'] + $total_shipping_class_cost;
            if ($m['cost'] == 0 && $m['min_order_amount'] > 0) { $final_method_cost = 0; }

            $available_methods[] = [
                'id' => $m['id'],
                'name' => htmlspecialchars($m['method_name']),
                'cost' => $final_method_cost,
                'formatted_cost' => number_format($final_method_cost, 2)
            ];
        }
    }

    // Fallback 2: Auto-insert standard method into shipping_methods table if empty to satisfy Foreign Key constraints
    if (empty($available_methods)) {
        $default_cost = 350.00 + $total_shipping_class_cost;
        $real_m_id = 9999;
        try {
            $ins_m = $pdo->prepare("INSERT INTO shipping_methods (zone_id, method_name, cost, is_active) VALUES (?, 'Standard Delivery', 350.00, 1)");
            $ins_m->execute([$zone_id]);
            $real_m_id = (int)$pdo->lastInsertId();
        } catch (Exception $e) {
            $real_m_id = 9999;
        }

        $available_methods[] = [
            'id' => $real_m_id,
            'name' => 'Standard Delivery',
            'cost' => $default_cost,
            'formatted_cost' => number_format($default_cost, 2)
        ];
    }

    echo json_encode([
        'status' => 'success',
        'subtotal' => $subtotal,
        'formatted_subtotal' => number_format($subtotal, 2),
        'methods' => $available_methods,
        'zone_id' => $zone_id
    ]);
    exit;
}

// ========================================================================
// ACTION 2: PLACE ORDER
// ========================================================================
if (isset($_POST['action']) && $_POST['action'] == 'place_order') {
    try {
        $pdo->beginTransaction();

        // Check if shipping to a different address
        $ship_to_different = isset($_POST['ship_to_different']) ? true : false;

        $first_name = trim(htmlspecialchars($_POST['billing_first_name'] ?? '', ENT_QUOTES, 'UTF-8'));
        $last_name = trim(htmlspecialchars($_POST['billing_last_name'] ?? '', ENT_QUOTES, 'UTF-8'));
        $email = trim(filter_var($_POST['billing_email'] ?? '', FILTER_SANITIZE_EMAIL));
        $phone = trim(htmlspecialchars($_POST['billing_phone'] ?? '', ENT_QUOTES, 'UTF-8'));
        $address = trim(htmlspecialchars($_POST['billing_address_1'] ?? '', ENT_QUOTES, 'UTF-8')) . ' ' . trim(htmlspecialchars($_POST['billing_address_2'] ?? '', ENT_QUOTES, 'UTF-8'));
        $province = trim(htmlspecialchars($_POST['billing_province'] ?? '', ENT_QUOTES, 'UTF-8'));
        $district = trim(htmlspecialchars($_POST['billing_district'] ?? '', ENT_QUOTES, 'UTF-8'));
        $town = trim(htmlspecialchars($_POST['billing_city'] ?? '', ENT_QUOTES, 'UTF-8')); 
        $zip_code = trim(htmlspecialchars($_POST['billing_zip'] ?? '', ENT_QUOTES, 'UTF-8')); 

        if ($ship_to_different) {
            $province = trim(htmlspecialchars($_POST['shipping_province'] ?? '', ENT_QUOTES, 'UTF-8'));
            $district = trim(htmlspecialchars($_POST['shipping_district'] ?? '', ENT_QUOTES, 'UTF-8'));
        }
        
        $zone_id = (int)($_POST['zone_id'] ?? 0);
        $method_id = (int)($_POST['shipping_method'] ?? 0);
        $payment_method = trim(htmlspecialchars($_POST['payment_method'] ?? 'COD', ENT_QUOTES, 'UTF-8')); 
        $order_notes = trim(htmlspecialchars($_POST['order_notes'] ?? '', ENT_QUOTES, 'UTF-8'));
        $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

        if(empty($first_name) || empty($email) || empty($phone) || empty($address) || empty($province) || empty($district) || empty($town) || empty($zone_id) || empty($method_id)) {
            throw new Exception("Please fill in all required fields and select a valid shipping area.");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }

        // --- ACCOUNT AUTOMATION ---
        $is_new_user = false;
        $temp_password = '';
        $username = '';

        if ($user_id === null) {
            $u_stmt = $pdo->prepare("SELECT id, username, user_role FROM users WHERE email = ? LIMIT 1");
            $u_stmt->execute([$email]);
            $existing_user = $u_stmt->fetch();

            if ($existing_user) {
                $user_id = $existing_user['id'];
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_role'] = $existing_user['user_role'];
                $_SESSION['user_name'] = $existing_user['username'];
            } else {
                $is_new_user = true;
                $temp_password = generateRandomPassword(10);
                $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
                
                $base_username = explode('@', $email)[0];
                $base_username = preg_replace("/[^a-zA-Z0-9]/", "", $base_username);
                $username = $base_username . rand(100,999);

                $ins_user = $pdo->prepare("INSERT INTO users (username, email, password, user_role) VALUES (?, ?, ?, 'customer')");
                $ins_user->execute([$username, $email, $hashed_password]);
                $user_id = $pdo->lastInsertId();

                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_role'] = 'customer';
                $_SESSION['user_name'] = $username;
            }
        }

        // --- CALCULATION ---
        $subtotal = 0;
        $total_shipping_class_cost = 0;
        $order_items_data = [];

        foreach ($_SESSION['cart'] as $item) {
            $pid = (int)$item['product_id'];
            $vid = (int)$item['variation_id'];
            $qty = (int)$item['quantity'];

            $stmt = $pdo->prepare("SELECT p.name, p.regular_price, p.sale_price, p.shipping_class_id, 
                                          sc.additional_cost, sc.cost_conditions 
                                   FROM products p 
                                   LEFT JOIN shipping_classes sc ON p.shipping_class_id = sc.id 
                                   WHERE p.id = ?");
            $stmt->execute([$pid]);
            $p = $stmt->fetch();

            if ($p) {
                $price = $p['sale_price'] > 0 ? $p['sale_price'] : $p['regular_price'];
                $v_name = null;
                if ($vid > 0) {
                    $v_stmt = $pdo->prepare("SELECT variation_name, regular_price, sale_price FROM product_variations WHERE id = ?");
                    $v_stmt->execute([$vid]);
                    $v = $v_stmt->fetch();
                    if ($v) {
                        $price = $v['sale_price'] > 0 ? $v['sale_price'] : $v['regular_price'];
                        $v_name = $v['variation_name'];
                    }
                }

                $item_total = $price * $qty;
                $subtotal += $item_total;

                if ($p['shipping_class_id']) {
                    $item_class_cost = 0;
                    $condition_met = false;
                    if (!empty($p['cost_conditions'])) {
                        $conditions = json_decode($p['cost_conditions'], true);
                        if (is_array($conditions)) {
                            foreach ($conditions as $cond) {
                                $min = (int)$cond['min'];
                                $max = (int)($cond['max'] > 0 ? $cond['max'] : 9999999);
                                $cost = (float)$cond['cost'];
                                if ($qty >= $min && $qty <= $max) {
                                    $item_class_cost = $cost * $qty;
                                    $condition_met = true;
                                    break;
                                }
                            }
                        }
                    }
                    if (!$condition_met && $p['additional_cost'] > 0) {
                        $item_class_cost = $p['additional_cost'] * $qty;
                    }
                    $total_shipping_class_cost += $item_class_cost;
                }

                $order_items_data[] = [
                    'pid' => $pid, 'vid' => $vid, 'p_name' => $p['name'], 'v_name' => $v_name, 
                    'qty' => $qty, 'price' => $price, 'total' => $item_total
                ];
            }
        }

        if ($method_id == 9999) {
            $shipping_cost = 350.00 + $total_shipping_class_cost;
        } else {
            $m_stmt = $pdo->prepare("SELECT cost, min_order_amount FROM shipping_methods WHERE id = ?");
            $m_stmt->execute([$method_id]);
            $method = $m_stmt->fetch();
            
            if (!$method) {
                $shipping_cost = 350.00 + $total_shipping_class_cost;
            } else {
                if ($method['min_order_amount'] !== null && $method['min_order_amount'] > 0 && $subtotal < $method['min_order_amount']) {
                     throw new Exception("This shipping method requires a minimum order of Rs. " . number_format($method['min_order_amount'], 2));
                }
                $shipping_cost = $method['cost'] + $total_shipping_class_cost;
                if ($method['cost'] == 0 && $method['min_order_amount'] > 0) { $shipping_cost = 0; }
            }
        }
        
        $total_amount = $subtotal + $shipping_cost;
        $order_number = 'ORD-' . strtoupper(uniqid());

        // Default status based on Gateway Selection
        // If PayHere is selected, order starts as 'pending_payment'
        $initial_order_status = ($payment_method === 'payhere') ? 'pending_payment' : 'pending';

        // --- FOREIGN KEY SAFETY CHECK FOR ORDERS TABLE ---
        $db_zone_id = null;
        if (!empty($zone_id)) {
            try {
                $z_chk = $pdo->prepare("SELECT id FROM shipping_zones WHERE id = ? LIMIT 1");
                $z_chk->execute([$zone_id]);
                if ($z_chk->fetch()) { $db_zone_id = $zone_id; }
            } catch (Exception $e) {}
        }
        if (empty($db_zone_id)) {
            try {
                $z_first = $pdo->query("SELECT id FROM shipping_zones ORDER BY id ASC LIMIT 1")->fetch();
                if ($z_first) { $db_zone_id = (int)$z_first['id']; }
            } catch (Exception $e) {}
        }

        $db_method_id = null;
        if (!empty($method_id) && $method_id != 9999) {
            try {
                $m_chk = $pdo->prepare("SELECT id FROM shipping_methods WHERE id = ? LIMIT 1");
                $m_chk->execute([$method_id]);
                if ($m_chk->fetch()) { $db_method_id = $method_id; }
            } catch (Exception $e) {}
        }
        if (empty($db_method_id)) {
            try {
                $m_first = $pdo->query("SELECT id FROM shipping_methods ORDER BY id ASC LIMIT 1")->fetch();
                if ($m_first) {
                    $db_method_id = (int)$m_first['id'];
                } else {
                    // Auto create standard shipping method if DB table is completely empty
                    $target_zone = $db_zone_id ?? 1;
                    $ins_fallback = $pdo->prepare("INSERT INTO shipping_methods (zone_id, method_name, cost, is_active) VALUES (?, 'Standard Delivery', 350.00, 1)");
                    $ins_fallback->execute([$target_zone]);
                    $db_method_id = (int)$pdo->lastInsertId();
                }
            } catch (Exception $e) {}
        }

        // --- SAVING THE ORDER ---
        $o_sql = "INSERT INTO orders (
                    order_number, user_id, customer_first_name, customer_last_name, customer_email, customer_phone, 
                    billing_address, billing_province, billing_district, billing_town, billing_zip_code, 
                    shipping_zone_id, shipping_method_id, shipping_cost, subtotal, total_amount, payment_method, order_notes, order_status
                  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                  
        $o_stmt = $pdo->prepare($o_sql);
        $o_stmt->execute([
            $order_number, $user_id, $first_name, $last_name, $email, $phone, 
            $address, $province, $district, $town, $zip_code, 
            $db_zone_id, $db_method_id, $shipping_cost, $subtotal, $total_amount, $payment_method, $order_notes, $initial_order_status
        ]);
        
        $new_order_id = $pdo->lastInsertId();

        $i_stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, variation_id, product_name, variation_name, quantity, price, total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $update_product_stock = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity > 0");
        $update_variation_stock = $pdo->prepare("UPDATE product_variations SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity > 0");

        foreach ($order_items_data as $i) {
            $i_stmt->execute([$new_order_id, $i['pid'], $i['vid'], $i['p_name'], $i['v_name'], $i['qty'], $i['price'], $i['total']]);
            
            if ($i['vid'] > 0) {
                $update_variation_stock->execute([$i['qty'], $i['vid']]);
            } else {
                $update_product_stock->execute([$i['qty'], $i['pid']]);
            }
        }

        $pdo->commit();
        unset($_SESSION['cart']); 

        // ---- EMAIL NOTIFICATIONS (Only if not a gateway redirect transaction) ----
        require_once __DIR__ . '/../../config/email_helper.php'; 
        
        if ($is_new_user) {
            $full_name = $first_name . ' ' . $last_name;
            if (function_exists('sendAccountEmail')) {
                sendAccountEmail($pdo, $full_name, $username, $temp_password, $email);
            }
        }

        if ($payment_method !== 'payhere') {
            if (function_exists('sendOrderEmail')) {
                sendOrderEmail($pdo, $new_order_id, 'new_order_admin', 'info@loopzglobal.com');
                sendOrderEmail($pdo, $new_order_id, 'status_pending', $email);
            }
            echo json_encode(['status' => 'success', 'order_number' => htmlspecialchars($order_number), 'message' => 'Order placed successfully!']);
        } else {
            // ✨ IF PAYHERE, SEND THE REDIRECT ROUTE URL FOR THE FRONTEND FETCH TO INTERCEPT ✨
            echo json_encode([
                'status' => 'success', 
                'order_number' => htmlspecialchars($order_number), 
                'redirect_url' => '/pages/checkout/payhere_redirect.php?order_id=' . $new_order_id
            ]);
        }
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
exit;