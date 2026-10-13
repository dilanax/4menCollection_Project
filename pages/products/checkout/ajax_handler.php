<?php
// Helper Function: Random Password Generator
function generateRandomPassword($length = 8) {
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%';
    return substr(str_shuffle($chars), 0, $length);
}

// ==========================================
// ZOHO SMTP EMAIL DISPATCH (CLEAN RFC HEADERS + INBOX COMPLIANT)
// ==========================================
function sendZohoMail($pdo, $toEmail, $toName, $subject, $htmlContent) {
    if (empty($toEmail)) return false;

    $stmt = $pdo->query("SELECT * FROM email_settings WHERE id = 1 LIMIT 1");
    $settings = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

    if (!$settings || empty($settings['smtp_email']) || empty($settings['smtp_app_token'])) {
        return false;
    }

    $logoHtml = '';
    if (!empty($settings['business_logo'])) {
        $logoUrl = rtrim($settings['website_url'] ?? '', '/') . '/' . ltrim($settings['business_logo'], '/');
        $logoHtml = '<img src="' . htmlspecialchars($logoUrl) . '" alt="' . htmlspecialchars($settings['business_name'] ?? 'ReadersHub') . '" style="max-height: 44px; margin-bottom: 12px; display: inline-block;">';
    }

    $fullBody = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 20px; color: #1e293b; }
        .email-wrapper { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 20px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .email-header { background: #0369a1; padding: 28px 24px; text-align: center; color: #ffffff; }
        .email-body { padding: 28px 24px; font-size: 14px; line-height: 1.6; color: #334155; }
        .email-footer { background: #f8fafc; padding: 18px 24px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-header">
            ' . $logoHtml . '
            <h2 style="margin: 0; font-size: 20px; font-weight: 800; letter-spacing: -0.5px;">' . htmlspecialchars($settings['business_name'] ?? 'ReadersHub') . '</h2>
        </div>
        <div class="email-body">
            ' . $htmlContent . '
        </div>
        <div class="email-footer">
            <p style="margin: 0 0 4px;">' . htmlspecialchars($settings['business_name'] ?? 'ReadersHub') . ' | ' . htmlspecialchars($settings['phone_number'] ?? '') . '</p>
            <p style="margin: 0;">WhatsApp: ' . htmlspecialchars($settings['whatsapp_number'] ?? '') . ' | ' . htmlspecialchars($settings['address'] ?? '') . '</p>
        </div>
    </div>
</body>
</html>';

    $host = !empty($settings['smtp_host']) ? trim($settings['smtp_host']) : 'smtp.zoho.com';
    $port = !empty($settings['smtp_port']) ? (int)$settings['smtp_port'] : 587;
    $username = trim($settings['smtp_email']);
    $password = trim($settings['smtp_app_token']);
    $fromName = !empty($settings['from_name']) ? trim($settings['from_name']) : 'ReadersHub';

    // Normalize HTML body newlines to \r\n safely
    $normalizedBody = str_replace(["\r\n", "\r"], "\n", $fullBody);
    $normalizedBody = str_replace("\n", "\r\n", $normalizedBody);

    $domain = (strpos($username, '@') !== false) ? explode('@', $username)[1] : 'readershub.lk';
    $messageId = "<" . time() . "." . md5(uniqid(rand(), true)) . "@" . $domain . ">";

    // Clean standard RFC 2822 Headers
    $headers = [
        "Date: " . date("r"),
        "To: <{$toEmail}>",
        "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$username}>",
        "Reply-To: <{$username}>",
        "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
        "Message-ID: {$messageId}",
        "X-Mailer: ReadersHub PHP Mailer",
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "Content-Transfer-Encoding: 8bit"
    ];

    $emailData = implode("\r\n", $headers) . "\r\n\r\n" . $normalizedBody;

    $read = function($socket) {
        $resp = "";
        while ($str = fgets($socket, 515)) {
            $resp .= $str;
            if (substr($str, 3, 1) === " ") break;
        }
        return $resp;
    };

    try {
        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 12);
        if (!$socket) return false;

        $read($socket); // 220
        fputs($socket, "EHLO readershub.lk\r\n"); $read($socket);
        fputs($socket, "STARTTLS\r\n");
        $tls = $read($socket);
        if (strpos($tls, '220') === false) { fclose($socket); return false; }

        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return false;
        }

        fputs($socket, "EHLO readershub.lk\r\n"); $read($socket);
        fputs($socket, "AUTH LOGIN\r\n"); $read($socket);
        fputs($socket, base64_encode($username) . "\r\n"); $read($socket);
        fputs($socket, base64_encode($password) . "\r\n");
        $auth = $read($socket);
        if (strpos($auth, '235') === false) { fclose($socket); return false; }

        fputs($socket, "MAIL FROM:<{$username}>\r\n"); $read($socket);
        fputs($socket, "RCPT TO:<{$toEmail}>\r\n"); $read($socket);
        fputs($socket, "DATA\r\n"); $read($socket);
        fputs($socket, $emailData . "\r\n.\r\n");
        $sent = $read($socket);
        fputs($socket, "QUIT\r\n");
        fclose($socket);

        return (strpos($sent, '250') !== false);
    } catch (Exception $e) {
        return false;
    }
}

// -------------------------------------------------------------
// 1. AJAX ENDPOINT: Shipping Calculation
// -------------------------------------------------------------
if (isset($_POST['action']) && $_POST['action'] === 'calculate_shipping') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    
    $district_name = trim(str_replace(' District', '', $_POST['district_name'] ?? ''));
    $province_name = trim($_POST['province_name'] ?? '');

    $district_id = null;
    $province_id = null;

    if (!empty($district_name)) {
        $dist_stmt = $pdo->prepare("SELECT id, parent_id FROM geo_regions WHERE type = 'district' AND name LIKE ? LIMIT 1");
        $dist_stmt->execute(["%$district_name%"]);
        $dist_row = $dist_stmt->fetch(PDO::FETCH_ASSOC);
        if ($dist_row) {
            $district_id = (int)$dist_row['id'];
            $province_id = (int)$dist_row['parent_id'];
        }
    }

    if (!$province_id && !empty($province_name)) {
        $prov_stmt = $pdo->prepare("SELECT id FROM geo_regions WHERE type = 'province' AND name LIKE ? LIMIT 1");
        $prov_stmt->execute(["%$province_name%"]);
        $province_id = $prov_stmt->fetchColumn() ?: null;
    }

    $subtotal = 0;
    $total_order_weight = 0;

    foreach ($_SESSION['cart'] as $item) {
        $pid = (int)$item['product_id'];
        $vid = (int)$item['variation_id'];
        $qty = (int)$item['quantity'];

        $stmt = $pdo->prepare("SELECT regular_price, sale_price, weight FROM products WHERE id = ?");
        $stmt->execute([$pid]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($p) {
            $price = $p['sale_price'] > 0 ? (float)$p['sale_price'] : (float)$p['regular_price'];
            if ($vid > 0) {
                $v_stmt = $pdo->prepare("SELECT regular_price, sale_price FROM product_variations WHERE id = ?");
                $v_stmt->execute([$vid]);
                $v = $v_stmt->fetch(PDO::FETCH_ASSOC);
                if ($v) { $price = $v['sale_price'] > 0 ? (float)$v['sale_price'] : (float)$v['regular_price']; }
            }
            $subtotal += ($price * $qty);

            $item_weight = (float)($p['weight'] ?? 0);
            if ($item_weight > 0) {
                $total_order_weight += ($item_weight * $qty);
            }
        }
    }

    $extra_weight_fee = 0.00;
    $weight_label = '';

    if ($total_order_weight > 0) {
        $w_stmt = $pdo->prepare("
            SELECT extra_cost, label FROM geo_weight_rates 
            WHERE is_active = 1 
            AND ? >= min_weight 
            AND (? <= max_weight OR max_weight IS NULL)
            ORDER BY min_weight DESC LIMIT 1
        ");
        $w_stmt->execute([$total_order_weight, $total_order_weight]);
        $matched_weight = $w_stmt->fetch(PDO::FETCH_ASSOC);

        if ($matched_weight) {
            $extra_weight_fee = (float)$matched_weight['extra_cost'];
            $weight_label = $matched_weight['label'] ? " [Weight: {$total_order_weight}kg]" : "";
        }
    }

    $rate_sql = "
        SELECT * FROM geo_delivery_rates 
        WHERE is_active = 1 
        AND (
            district_id = ? 
            OR (province_id = ? AND district_id IS NULL)
            OR (province_id IS NULL AND district_id IS NULL)
        )
        ORDER BY district_id DESC, province_id DESC, cost ASC
    ";
    
    $r_stmt = $pdo->prepare($rate_sql);
    $r_stmt->execute([$district_id, $province_id]);
    $matched_rates = $r_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($matched_rates)) {
        $matched_rates = [
            [
                'id' => 0,
                'method_title' => 'Standard Delivery',
                'shipping_type' => 'paid',
                'cost' => 350.00,
                'min_order_free' => null,
                'estimated_days' => '2-4 Days'
            ]
        ];
    }

    $available_methods = [];
    foreach ($matched_rates as $r) {
        $final_cost = 0;

        if ($r['shipping_type'] === 'free') {
            $final_cost = 0.00;
        } else {
            $final_cost = (float)$r['cost'] + $extra_weight_fee;

            if (!empty($r['min_order_free']) && $subtotal >= (float)$r['min_order_free']) {
                $final_cost = 0.00;
            }
        }

        $available_methods[] = [
            'id' => $r['id'],
            'name' => htmlspecialchars($r['method_title']) . $weight_label . " (" . htmlspecialchars($r['estimated_days'] ?? '2-3 Days') . ")",
            'cost' => $final_cost,
            'formatted_cost' => number_format($final_cost, 2)
        ];
    }

    echo json_encode([
        'status' => 'success',
        'subtotal' => $subtotal,
        'formatted_subtotal' => number_format($subtotal, 2),
        'methods' => $available_methods,
        'zone_id' => $district_id ?: ($province_id ?: 1),
        'total_weight' => $total_order_weight
    ]);
    exit;
}

// -------------------------------------------------------------
// 2. AJAX ENDPOINT: Place Order
// -------------------------------------------------------------
if (isset($_POST['action']) && $_POST['action'] === 'place_order') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    
    try {
        $pdo->beginTransaction();

        $first_name = trim(htmlspecialchars($_POST['first_name'] ?? '', ENT_QUOTES, 'UTF-8'));
        $last_name  = trim(htmlspecialchars($_POST['last_name'] ?? '', ENT_QUOTES, 'UTF-8'));
        $email      = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
        $phone      = trim(htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8'));
        
        $address    = trim(htmlspecialchars($_POST['address'] ?? '', ENT_QUOTES, 'UTF-8'));
        $province   = trim(htmlspecialchars($_POST['province'] ?? '', ENT_QUOTES, 'UTF-8'));
        $district   = trim(htmlspecialchars($_POST['district'] ?? '', ENT_QUOTES, 'UTF-8'));
        $city       = trim(htmlspecialchars($_POST['city'] ?? '', ENT_QUOTES, 'UTF-8'));
        $zip_code   = trim(htmlspecialchars($_POST['zip_code'] ?? '', ENT_QUOTES, 'UTF-8'));
        
        $method_id  = (int)($_POST['shipping_method'] ?? 0);
        
        // Capture Payment Method safely
        $raw_method = strtolower(trim($_POST['payment_method'] ?? 'bank_transfer'));
        if ($raw_method === 'payhere') {
            $payment_method = 'payhere';
        } elseif ($raw_method === 'cod' || $raw_method === 'cash_on_delivery') {
            $payment_method = 'COD';
        } else {
            $payment_method = 'bank_transfer';
        }
        
        $order_notes = trim(htmlspecialchars($_POST['order_notes'] ?? '', ENT_QUOTES, 'UTF-8'));
        $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

        if (empty($first_name) || empty($email) || empty($phone) || empty($address) || empty($province) || empty($district) || empty($city)) {
            throw new Exception("Please fill in all required delivery details (Name, Email, Phone, Address, Province, District, City).");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email address provided.");
        }

        // Auto Account Creation for Guests
        $account_created = false;
        $plain_password = '';

        if ($user_id === null) {
            $u_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $u_stmt->execute([$email]);
            $existing_user = $u_stmt->fetch();

            if ($existing_user) {
                $user_id = $existing_user['id'];
            } else {
                $plain_password = 'Rh#' . rand(1000, 9999) . '!';
                $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
                $username = strtolower(explode('@', $email)[0]) . rand(10, 99);

                $ins_user = $pdo->prepare("INSERT INTO users (username, email, password, user_role) VALUES (?, ?, ?, 'customer')");
                $ins_user->execute([$username, $email, $hashed_password]);
                
                $user_id = $pdo->lastInsertId();
                $account_created = true;
            }
        }

        // Subtotal & Items Calculation
        $subtotal = 0;
        $total_order_weight = 0;
        $order_items_data = [];

        foreach ($_SESSION['cart'] as $item) {
            $pid = (int)$item['product_id'];
            $vid = (int)$item['variation_id'];
            $qty = (int)$item['quantity'];

            $stmt = $pdo->prepare("SELECT name, regular_price, sale_price, weight FROM products WHERE id = ?");
            $stmt->execute([$pid]);
            $p = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($p) {
                $price = $p['sale_price'] > 0 ? (float)$p['sale_price'] : (float)$p['regular_price'];
                $v_name = null;
                if ($vid > 0) {
                    $v_stmt = $pdo->prepare("SELECT variation_name, regular_price, sale_price FROM product_variations WHERE id = ?");
                    $v_stmt->execute([$vid]);
                    $v = $v_stmt->fetch(PDO::FETCH_ASSOC);
                    if ($v) {
                        $price = $v['sale_price'] > 0 ? (float)$v['sale_price'] : (float)$v['regular_price'];
                        $v_name = $v['variation_name'];
                    }
                }

                $item_total = $price * $qty;
                $subtotal += $item_total;

                $item_weight = (float)($p['weight'] ?? 0);
                if ($item_weight > 0) {
                    $total_order_weight += ($item_weight * $qty);
                }

                $order_items_data[] = [
                    'pid' => $pid, 'vid' => $vid, 'p_name' => $p['name'], 'v_name' => $v_name, 
                    'qty' => $qty, 'price' => $price, 'total' => $item_total
                ];
            }
        }

        // Weight Extra Calculation
        $extra_weight_fee = 0.00;
        if ($total_order_weight > 0) {
            $w_stmt = $pdo->prepare("
                SELECT extra_cost FROM geo_weight_rates 
                WHERE is_active = 1 
                AND ? >= min_weight 
                AND (? <= max_weight OR max_weight IS NULL)
                ORDER BY min_weight DESC LIMIT 1
            ");
            $w_stmt->execute([$total_order_weight, $total_order_weight]);
            $matched_weight = $w_stmt->fetch(PDO::FETCH_ASSOC);
            if ($matched_weight) {
                $extra_weight_fee = (float)$matched_weight['extra_cost'];
            }
        }

        // Determine Final Shipping Cost & Title from geo_delivery_rates
        $shipping_cost = 350.00;
        $method_title = 'Standard Delivery';

        if ($method_id > 0) {
            $rate_stmt = $pdo->prepare("SELECT * FROM geo_delivery_rates WHERE id = ? LIMIT 1");
            $rate_stmt->execute([$method_id]);
            $matched_rule = $rate_stmt->fetch(PDO::FETCH_ASSOC);

            if ($matched_rule) {
                $method_title = $matched_rule['method_title'];
                if ($matched_rule['shipping_type'] === 'free' || (!empty($matched_rule['min_order_free']) && $subtotal >= (float)$matched_rule['min_order_free'])) {
                    $shipping_cost = 0.00;
                } else {
                    $shipping_cost = (float)$matched_rule['cost'] + $extra_weight_fee;
                }
            }
        }
        
        $total_amount = $subtotal + $shipping_cost;

        // ==============================================================
        // 🔢 SEQUENTIAL ORDER NUMBER GENERATION (STARTS AT #ORD-20000)
        // ==============================================================
        $next_num = 20000;
        try {
            $last_order_stmt = $pdo->query("
                SELECT CAST(SUBSTRING(order_number, 5) AS UNSIGNED) AS num 
                FROM orders 
                WHERE order_number REGEXP '^ORD-[0-9]+$' 
                ORDER BY num DESC 
                LIMIT 1
            ");
            $last_num = $last_order_stmt ? (int)$last_order_stmt->fetchColumn() : 0;
            if ($last_num >= 20000) {
                $next_num = $last_num + 1;
            }
        } catch (Exception $e) {
            $next_num = 20000;
        }

        while (true) {
            $chk_dup = $pdo->prepare("SELECT id FROM orders WHERE order_number = ? LIMIT 1");
            $chk_dup->execute(['ORD-' . $next_num]);
            if (!$chk_dup->fetchColumn()) {
                break;
            }
            $next_num++;
        }

        $order_number = 'ORD-' . $next_num;

        // SAFE FOREIGN KEY CHECK: Prevent Foreign Key 1452 Error
        $valid_legacy_shipping_method_id = null;
        if ($method_id > 0) {
            try {
                $chk_sm = $pdo->prepare("SELECT id FROM shipping_methods WHERE id = ? LIMIT 1");
                $chk_sm->execute([$method_id]);
                if ($chk_sm->fetchColumn()) {
                    $valid_legacy_shipping_method_id = $method_id;
                }
            } catch (Exception $e) {
                $valid_legacy_shipping_method_id = null;
            }
        }

        // Insert Order Record
        $o_sql = "INSERT INTO orders (
                    order_number, user_id, customer_first_name, customer_last_name, customer_email, customer_phone, 
                    billing_address, billing_city, billing_province, billing_district, billing_town, billing_zip_code, 
                    shipping_zone_id, shipping_method_id, shipping_cost, subtotal, total_amount, payment_method, order_notes
                  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?)";
                  
        $o_stmt = $pdo->prepare($o_sql);
        $o_stmt->execute([
            $order_number, 
            $user_id, 
            $first_name, 
            $last_name, 
            $email, 
            $phone, 
            $address, 
            $city, 
            $province, 
            $district, 
            $city, 
            $zip_code, 
            $valid_legacy_shipping_method_id, 
            $shipping_cost, 
            $subtotal, 
            $total_amount, 
            $payment_method, 
            $order_notes
        ]);
        
        $new_order_id = $pdo->lastInsertId();

        // Insert Order Items & Auto-deduct Stock
        $i_stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, variation_id, product_name, variation_name, quantity, price, total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $upd_stock_stmt = $pdo->prepare("
            UPDATE products 
            SET 
                stock_quantity = GREATEST(0, COALESCE(stock_quantity, 0) - ?),
                stock_status = CASE 
                    WHEN (COALESCE(stock_quantity, 0) - ?) <= 0 THEN 'outofstock' 
                    ELSE 'instock' 
                END
            WHERE id = ?
        ");

        $upd_var_stmt = $pdo->prepare("
            UPDATE product_variations 
            SET stock_quantity = GREATEST(0, COALESCE(stock_quantity, 0) - ?) 
            WHERE id = ?
        ");

        foreach ($order_items_data as $i) {
            $order_qty = (int)$i['qty'];
            $p_id      = (int)$i['pid'];
            $v_id      = !empty($i['vid']) ? (int)$i['vid'] : null;

            // 1. Insert order item
            $i_stmt->execute([
                $new_order_id, $p_id, $v_id, $i['p_name'], $i['v_name'], $order_qty, $i['price'], $i['total']
            ]);

            // 2. Auto-deduct stock from products
            $upd_stock_stmt->execute([$order_qty, $order_qty, $p_id]);

            // 3. Auto-deduct variation stock
            if (!empty($v_id)) {
                $upd_var_stmt->execute([$order_qty, $v_id]);
            }
        }

        $pdo->commit();
        unset($_SESSION['cart']);

        // Account Creation Email
        if ($account_created) {
            $login_url = "https://readershub.lk/login";
            $accSubject = "Welcome to ReadersHub! Your Account Details";
            $accHtml = "
                <p>Hello <strong>" . htmlspecialchars($first_name) . "</strong>,</p>
                <p>Thank you for shopping with us! A personal reader account has been created for you:</p>
                <div style='background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; padding:18px; margin:20px 0;'>
                    <p style='margin:0 0 6px;'><strong>Username / Email:</strong> " . htmlspecialchars($email) . "</p>
                    <p style='margin:0;'><strong>Temporary Password:</strong> <span style='background:#e0f2fe; color:#0369a1; padding:3px 8px; border-radius:5px; font-weight:bold;'>" . htmlspecialchars($plain_password) . "</span></p>
                </div>
                <p><a href='{$login_url}' style='background:#0369a1; color:#fff; padding:10px 20px; border-radius:8px; text-decoration:none; font-weight:bold;'>Sign In &rarr;</a></p>
            ";
            sendZohoMail($pdo, $email, $first_name, $accSubject, $accHtml);
        }

        // Shared Items Table for Emails
        $itemsHtmlTable = '<table style="width:100%; border-collapse:collapse; margin:16px 0; font-size:13px;">';
        $itemsHtmlTable .= '<thead><tr style="background:#f1f5f9; border-bottom:1px solid #e2e8f0; text-align:left;"><th style="padding:8px 10px;">Item</th><th style="padding:8px 10px; text-align:center;">Qty</th><th style="padding:8px 10px; text-align:right;">Total</th></tr></thead><tbody>';
        
        foreach ($order_items_data as $it) {
            $varLabel = !empty($it['v_name']) ? '<br><span style="font-size:11px; color:#64748b;">Edition: ' . htmlspecialchars($it['v_name']) . '</span>' : '';
            $itemsHtmlTable .= '<tr style="border-bottom:1px solid #f1f5f9;">';
            $itemsHtmlTable .= '<td style="padding:10px 10px;"><strong>' . htmlspecialchars($it['p_name']) . '</strong>' . $varLabel . '</td>';
            $itemsHtmlTable .= '<td style="padding:10px 10px; text-align:center;">' . $it['qty'] . '</td>';
            $itemsHtmlTable .= '<td style="padding:10px 10px; text-align:right;">Rs. ' . number_format($it['total'], 2) . '</td>';
            $itemsHtmlTable .= '</tr>';
        }
        $itemsHtmlTable .= '</tbody></table>';

        $deliveryLabel = ($shipping_cost == 0) ? 'Free Delivery' : 'Rs. ' . number_format($shipping_cost, 2);

        // Fetch Admin Email Address for Notifications
        $set_stmt = $pdo->query("SELECT admin_alert_email, smtp_email FROM email_settings WHERE id = 1 LIMIT 1");
        $em_set = $set_stmt ? $set_stmt->fetch(PDO::FETCH_ASSOC) : [];
        $adminMail = !empty($em_set['admin_alert_email']) ? $em_set['admin_alert_email'] : ($em_set['smtp_email'] ?? '');

        // ==============================================================
        // CASE A: PAYHERE PAYMENT GATEWAY
        // ==============================================================
        if ($payment_method === 'payhere') {
            $gw_stmt = $pdo->prepare("SELECT * FROM payment_gateways WHERE gateway_id = 'payhere' AND is_active = 1 LIMIT 1");
            $gw_stmt->execute();
            $gw = $gw_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$gw || empty($gw['merchant_id']) || empty($gw['merchant_secret'])) {
                throw new Exception("PayHere gateway is currently unavailable. Please choose another payment method.");
            }

            $merchant_id = trim($gw['merchant_id']);
            $merchant_secret = trim($gw['merchant_secret']);
            $currency = 'LKR';
            $formatted_amount = number_format($total_amount, 2, '.', '');

            $hash = strtoupper(
                md5(
                    $merchant_id . 
                    $order_number . 
                    $formatted_amount . 
                    $currency . 
                    strtoupper(md5($merchant_secret))
                )
            );

            $action_url = ($gw['environment'] === 'live') 
                ? 'https://www.payhere.lk/pay/checkout' 
                : 'https://sandbox.payhere.lk/pay/checkout';

            echo json_encode([
                'status'           => 'success',
                'payment_type'     => 'payhere',
                'action_url'       => $action_url,
                'payhere_data'     => [
                    'merchant_id'    => $merchant_id,
                    'return_url'     => 'https://readershub.lk/payhere_return?order_id=' . $order_number,
                    'cancel_url'     => 'https://readershub.lk/checkout?cancel=1',
                    'notify_url'     => 'https://readershub.lk/payhere_notify',
                    'first_name'     => $first_name,
                    'last_name'      => $last_name,
                    'email'          => $email,
                    'phone'          => $phone,
                    'address'        => $address,
                    'city'           => $city,
                    'country'        => 'Sri Lanka',
                    'order_id'       => $order_number,
                    'items'          => 'Order #' . $order_number,
                    'currency'       => $currency,
                    'amount'         => $formatted_amount,
                    'hash'           => $hash
                ]
            ]);
            exit;
        }

        // ==============================================================
        // CASE B: DIRECT BANK TRANSFER
        // ==============================================================
        if ($payment_method === 'bank_transfer') {
            $b_stmt = $pdo->query("SELECT * FROM bank_accounts WHERE is_active = 1 ORDER BY id ASC");
            $active_banks = $b_stmt ? $b_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            $bankRowsHtml = '';
            if (!empty($active_banks)) {
                foreach ($active_banks as $ab) {
                    $bankRowsHtml .= "
                        <div style='margin-bottom:10px; padding-bottom:8px; border-bottom:1px dashed #cbd5e1;'>
                            <p style='margin:3px 0;'><strong>Bank:</strong> " . htmlspecialchars($ab['bank_name']) . " (" . htmlspecialchars($ab['branch_name']) . ")</p>
                            <p style='margin:3px 0;'><strong>Account Number:</strong> <span style='font-family:monospace; font-size:15px; font-weight:bold; color:#0369a1;'>" . htmlspecialchars($ab['account_number']) . "</span></p>
                            <p style='margin:3px 0;'><strong>Account Name:</strong> " . htmlspecialchars($ab['account_name']) . "</p>
                        </div>
                    ";
                }
            } else {
                $bankRowsHtml = "
                    <div style='margin-bottom:10px;'>
                        <p style='margin:3px 0;'><strong>Bank:</strong> Pan Asia Bank (Balangoda branch)</p>
                        <p style='margin:3px 0;'><strong>Account Number:</strong> <span style='font-family:monospace; font-size:15px; font-weight:bold; color:#0369a1;'>106211100130</span></p>
                        <p style='margin:3px 0;'><strong>Account Name:</strong> Readers Hub (Pvt) Ltd</p>
                    </div>
                ";
            }

            $bankNoticeHtml = "
                <div style='background:#f0fdf4; border:1px solid #bbf7d0; border-radius:14px; padding:18px; margin:20px 0;'>
                    <h3 style='margin:0 0 10px; font-size:15px; color:#166534;'>🏦 Bank Deposit / Transfer Details</h3>
                    {$bankRowsHtml}
                    <div style='background:#fef3c7; border:1px solid #fde68a; padding:12px 14px; border-radius:10px; margin-top:14px; font-size:12px; color:#92400e; line-height:1.5;'>
                        📌 <strong>උපදෙස්:</strong> කරුණාකර ඔබගේ මුදල් තැන්පත් කළ රිසිට්පත (Deposit Slip) ඔබගේ Order අංකය (<strong>#{$order_number}</strong>) සමඟ පහත අංකයට WhatsApp කරන්න:<br>
                        <a href='https://wa.me/94703472727?text=Hello%20ReadersHub,%20here%20is%20my%20bank%20deposit%20slip%20for%20order%20%23{$order_number}' style='display:inline-block; margin-top:8px; background:#16a34a; color:#ffffff; padding:8px 16px; border-radius:8px; text-decoration:none; font-weight:bold;'>WhatsApp: 070 347 27 27 &rarr;</a>
                    </div>
                </div>
            ";

            // 1. Send Email to Customer
            $ordSubject = "Order Received #" . $order_number . " - Bank Transfer Details";
            $ordHtml = "
                <p>Hello <strong>" . htmlspecialchars($first_name) . "</strong>,</p>
                <p>Thank you for ordering with ReadersHub! Your book order <strong>#" . htmlspecialchars($order_number) . "</strong> has been registered. Please complete the bank transfer below to initiate delivery:</p>
                {$itemsHtmlTable}
                <div style='background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:15px; margin-bottom:15px;'>
                    <div style='display:flex; justify-content:space-between; margin-bottom:5px;'><span>Subtotal:</span> <strong>Rs. " . number_format($subtotal, 2) . "</strong></div>
                    <div style='display:flex; justify-content:space-between; margin-bottom:5px;'><span>Delivery (" . htmlspecialchars($method_title) . "):</span> <strong>{$deliveryLabel}</strong></div>
                    <div style='display:flex; justify-content:space-between; font-size:15px; border-top:1px solid #e2e8f0; padding-top:6px; color:#0369a1;'><span><strong>Total Due:</strong></span> <strong>Rs. " . number_format($total_amount, 2) . "</strong></div>
                </div>
                {$bankNoticeHtml}
            ";
            sendZohoMail($pdo, $email, $first_name, $ordSubject, $ordHtml);

            // 2. Send Alert Email to Admin
            if (!empty($adminMail)) {
                $adminSubject = "🚨 New Bank Transfer Order: #" . $order_number;
                $adminHtml = "
                    <p>A new customer order has been placed via Bank Transfer on ReadersHub:</p>
                    <p><strong>Order Number:</strong> " . htmlspecialchars($order_number) . "</p>
                    <p><strong>Customer:</strong> " . htmlspecialchars($first_name . ' ' . $last_name) . " (" . htmlspecialchars($phone) . ")</p>
                    <p><strong>Total Value:</strong> Rs. " . number_format($total_amount, 2) . " (Direct Bank Transfer)</p>
                    <p>Please check your WhatsApp (070 347 27 27) for the deposit slip.</p>
                ";
                sendZohoMail($pdo, $adminMail, 'Admin', $adminSubject, $adminHtml);
            }

            echo json_encode([
                'status'          => 'success', 
                'payment_type'    => 'bank_transfer',
                'order_number'    => htmlspecialchars($order_number), 
                'account_created' => $account_created,
                'message'         => 'Order placed! Please transfer payment to our Pan Asia Bank account and send the slip via WhatsApp.'
            ]);
            exit;
        }

        // ==============================================================
        // CASE C: CASH ON DELIVERY (COD)
        // ==============================================================
        if ($payment_method === 'COD') {
            // 1. Send Email to Customer
            $ordSubject = "Order Confirmation #" . $order_number;
            $ordHtml = "
                <p>Hello <strong>" . htmlspecialchars($first_name) . "</strong>,</p>
                <p>We have successfully received your order <strong>#" . htmlspecialchars($order_number) . "</strong> via Cash on Delivery:</p>
                {$itemsHtmlTable}
                <div style='background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:15px; margin-bottom:20px;'>
                    <div style='display:flex; justify-content:space-between; margin-bottom:5px;'><span>Subtotal:</span> <strong>Rs. " . number_format($subtotal, 2) . "</strong></div>
                    <div style='display:flex; justify-content:space-between; margin-bottom:5px;'><span>Delivery (" . htmlspecialchars($method_title) . "):</span> <strong>{$deliveryLabel}</strong></div>
                    <div style='display:flex; justify-content:space-between; font-size:15px; border-top:1px solid #e2e8f0; padding-top:6px; color:#0369a1;'><span><strong>Total Due (COD):</strong></span> <strong>Rs. " . number_format($total_amount, 2) . "</strong></div>
                </div>
            ";
            sendZohoMail($pdo, $email, $first_name, $ordSubject, $ordHtml);

            // 2. Send Alert Email to Admin
            if (!empty($adminMail)) {
                $adminSubject = "🚨 New Order Received: #" . $order_number;
                $adminHtml = "
                    <p>A new customer order has been placed on ReadersHub:</p>
                    <p><strong>Order Number:</strong> " . htmlspecialchars($order_number) . "</p>
                    <p><strong>Customer:</strong> " . htmlspecialchars($first_name . ' ' . $last_name) . " (" . htmlspecialchars($phone) . ")</p>
                    <p><strong>Total Value:</strong> Rs. " . number_format($total_amount, 2) . " (Cash on Delivery)</p>
                ";
                sendZohoMail($pdo, $adminMail, 'Admin', $adminSubject, $adminHtml);
            }

            echo json_encode([
                'status'          => 'success', 
                'payment_type'    => 'cod',
                'order_number'    => htmlspecialchars($order_number), 
                'account_created' => $account_created,
                'message'         => 'Order placed successfully via Cash on Delivery!'
            ]);
            exit;
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}
?>