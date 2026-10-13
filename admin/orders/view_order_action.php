<?php
// Error Reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// Security Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['admin', 'shop_admin'])) {
    die("Access Denied");
}

require_once '../../config/db.php';

// ==========================================
// ZOHO SMTP EMAIL DISPATCH (PORT 587 + STARTTLS SOCKET)
// ==========================================
function sendZohoOrderMail($pdo, $toEmail, $toName, $subject, $htmlContent) {
    if (empty($toEmail)) return false;

    $stmt = $pdo->query("SELECT * FROM email_settings WHERE id = 1 LIMIT 1");
    $settings = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

    if (!$settings || empty($settings['smtp_email']) || empty($settings['smtp_app_token'])) {
        return false;
    }

    $logoHtml = '';
    if (!empty($settings['business_logo'])) {
        $logoUrl = rtrim($settings['website_url'] ?? '', '/') . '/' . ltrim($settings['business_logo'], '/');
        $logoHtml = '<img src="' . htmlspecialchars($logoUrl) . '" alt="' . htmlspecialchars($settings['business_name'] ?? 'ReadersHub') . '" style="max-height: 48px; margin-bottom: 12px; display: inline-block;">';
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

    $normalizedBody = str_replace(["\r\n", "\r"], "\n", $fullBody);
    $normalizedBody = str_replace("\n", "\r\n", $normalizedBody);

    $domain = (strpos($username, '@') !== false) ? explode('@', $username)[1] : 'readershub.lk';
    $messageId = "<" . time() . "." . md5(uniqid(rand(), true)) . "@" . $domain . ">";

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

        $read($socket);
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

// ==========================================
// HELPER: Recalculate Totals
// ==========================================
function recalculateOrderTotals($pdo, $order_id) {
    $stmt = $pdo->prepare("SELECT SUM(total) as new_subtotal FROM order_items WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $res = $stmt->fetch();
    $new_subtotal = (float)($res['new_subtotal'] ?: 0);

    $stmt = $pdo->prepare("SELECT shipping_cost FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    $shipping = (float)($order['shipping_cost'] ?: 0);

    $new_total = $new_subtotal + $shipping;
    $pdo->prepare("UPDATE orders SET subtotal = ?, total_amount = ? WHERE id = ?")->execute([$new_subtotal, $new_total, $order_id]);
}

// ==========================================
// HANDLE POST ACTIONS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

    if ($order_id === 0) {
        die("Invalid Order ID.");
    }

    $o_stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $o_stmt->execute([$order_id]);
    $order = $o_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        die("Order not found.");
    }

    // -------------------------------------------------------------
    // 1. UPDATE ORDER STATUS & NOTIFY CUSTOMER
    // -------------------------------------------------------------
    if ($action === 'update_status') {
        $new_status = trim($_POST['order_status'] ?? '');
        $allowed = ['pending', 'processing', 'ready', 'packed', 'shipped', 'completed', 'cancelled', 'refunded'];

        if (in_array($new_status, $allowed)) {
            $stmt = $pdo->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
            if ($stmt->execute([$new_status, $order_id])) {
                $_SESSION['swal_msg'] = "Order status updated to " . ucfirst($new_status) . "!";
                $_SESSION['swal_type'] = "success";

                $cName = htmlspecialchars($order['customer_first_name']);
                $ordNo = htmlspecialchars($order['order_number']);
                $totalFormatted = number_format((float)$order['total_amount'], 2);
                $payMethod = htmlspecialchars($order['payment_method']);

                $subject = "";
                $htmlMsg = "";

                switch ($new_status) {
                    case 'pending':
                        $subject = "Order #{$ordNo} Received - Verification Pending";
                        $htmlMsg = "
                            <p>Hello <strong>{$cName}</strong>,</p>
                            <p>Thank you for your order. Your book order <strong>#{$ordNo}</strong> is currently pending confirmation from our inventory team.</p>
                            <div style='background:#fef3c7; border:1px solid #fde68a; padding:14px; border-radius:12px; margin:16px 0; color:#92400e;'>
                                <strong>Status: PENDING</strong><br>
                                Total Payable: <strong>Rs. {$totalFormatted}</strong><br>
                                Payment Method: {$payMethod}
                            </div>
                            <p>We will notify you once items are verified and ready for packing.</p>
                        ";
                        break;

                    case 'processing':
                        $subject = "We are preparing your books - Order #{$ordNo}";
                        $htmlMsg = "
                            <p>Hello <strong>{$cName}</strong>,</p>
                            <p>Your order <strong>#{$ordNo}</strong> is now being <strong>processed</strong> at our bookstore warehouse.</p>
                            <div style='background:#e0f2fe; border:1px solid #bae6fd; padding:14px; border-radius:12px; margin:16px 0; color:#0369a1;'>
                                <strong>Status: PROCESSING</strong><br>
                                Our team is currently gathering and inspecting your book titles.
                            </div>
                            <p>Total Due: <strong>Rs. {$totalFormatted}</strong> ({$payMethod})</p>
                        ";
                        break;

                    case 'ready':
                    case 'packed':
                        $subject = "Your Book Parcel is Packed - Order #{$ordNo}";
                        $htmlMsg = "
                            <p>Hello <strong>{$cName}</strong>,</p>
                            <p>Your order <strong>#{$ordNo}</strong> has been securely packed and is ready for courier collection.</p>
                            <div style='background:#e0e7ff; border:1px solid #c7d2fe; padding:14px; border-radius:12px; margin:16px 0; color:#3730a3;'>
                                <strong>Status: PACKED & READY TO DISPATCH</strong><br>
                                Handover to our courier partner will happen on the next pickup schedule.
                            </div>
                        ";
                        break;

                    case 'shipped':
                        $subject = "Your Books are Shipped! - Order #{$ordNo}";
                        $trackingInfo = "";
                        if (!empty($order['tracking_number'])) {
                            $trackingInfo = "
                                <p style='margin:6px 0 0;'>Courier: <strong>" . htmlspecialchars($order['tracking_provider'] ?: 'Standard Courier') . "</strong></p>
                                <p style='margin:4px 0 0;'>Tracking ID: <span style='font-family:monospace; font-weight:bold; color:#0369a1;'>" . htmlspecialchars($order['tracking_number']) . "</span></p>
                            ";
                        }
                        $htmlMsg = "
                            <p>Hello <strong>{$cName}</strong>,</p>
                            <p>Your book parcel for order <strong>#{$ordNo}</strong> has been dispatched for delivery.</p>
                            <div style='background:#f3e8ff; border:1px solid #e9d5ff; padding:15px; border-radius:12px; margin:16px 0; color:#6b21a8;'>
                                <strong>Status: SHIPPED / ON THE WAY</strong>
                                {$trackingInfo}
                            </div>
                            <p>Please have <strong>Rs. {$totalFormatted}</strong> ready for payment upon delivery ({$payMethod}).</p>
                        ";
                        break;

                    case 'completed':
                        $subject = "Delivered! Enjoy your reading - Order #{$ordNo}";
                        $htmlMsg = "
                            <p>Hello <strong>{$cName}</strong>,</p>
                            <p>Your order <strong>#{$ordNo}</strong> has been marked as <strong>Delivered & Completed</strong>.</p>
                            <div style='background:#d1fae5; border:1px solid #a7f3d0; padding:15px; border-radius:12px; margin:16px 0; color:#065f46;'>
                                <strong>Status: COMPLETED</strong><br>
                                Thank you for shopping with ReadersHub!
                            </div>
                            <p>We hope you enjoy your new titles. You are welcome to leave feedback or browse our new arrivals anytime.</p>
                        ";
                        break;

                    case 'cancelled':
                        $subject = "Order #{$ordNo} has been Cancelled";
                        $htmlMsg = "
                            <p>Hello <strong>{$cName}</strong>,</p>
                            <p>Your order <strong>#{$ordNo}</strong> has been <strong>cancelled</strong>.</p>
                            <div style='background:#ffe4e6; border:1px solid #fecdd3; padding:15px; border-radius:12px; margin:16px 0; color:#9f1239;'>
                                <strong>Status: CANCELLED</strong>
                            </div>
                            <p>If you have any questions or require clarification, please message us on WhatsApp.</p>
                        ";
                        break;

                    case 'refunded':
                        $subject = "Refund Processed - Order #{$ordNo}";
                        $htmlMsg = "
                            <p>Hello <strong>{$cName}</strong>,</p>
                            <p>Your order <strong>#{$ordNo}</strong> has been marked as <strong>Refunded</strong>.</p>
                            <div style='background:#ffe4e6; border:1px solid #fecdd3; padding:15px; border-radius:12px; margin:16px 0; color:#9f1239;'>
                                <strong>Status: REFUNDED</strong><br>
                                Amount: Rs. {$totalFormatted}
                            </div>
                            <p>Our accounts team has issued the refund to your payment method.</p>
                        ";
                        break;
                }

                if (!empty($subject) && !empty($htmlMsg) && !empty($order['customer_email'])) {
                    sendZohoOrderMail($pdo, $order['customer_email'], $order['customer_first_name'], $subject, $htmlMsg);
                }
            }
        }
    }

    // -------------------------------------------------------------
    // 2. UPDATE COURIER / TRACKING & NOTIFY DISPATCH
    // -------------------------------------------------------------
    elseif ($action === 'update_tracking_notes') {
        $provider = trim(htmlspecialchars($_POST['tracking_provider'] ?? '', ENT_QUOTES, 'UTF-8'));
        $number   = trim(htmlspecialchars($_POST['tracking_number'] ?? '', ENT_QUOTES, 'UTF-8'));
        $link     = trim(htmlspecialchars($_POST['tracking_link'] ?? '', ENT_QUOTES, 'UTF-8'));
        $notes    = trim(htmlspecialchars($_POST['admin_notes'] ?? '', ENT_QUOTES, 'UTF-8'));

        $stmt = $pdo->prepare("UPDATE orders SET tracking_provider = ?, tracking_number = ?, tracking_link = ?, admin_notes = ? WHERE id = ?");
        if ($stmt->execute([$provider, $number, $link, $notes, $order_id])) {
            $_SESSION['swal_msg'] = "Tracking & Notes saved successfully!";
            $_SESSION['swal_type'] = "success";

            // If tracking number exists, notify customer
            if (!empty($number) && !empty($order['customer_email'])) {
                $subject = "Tracking Information for Order #" . $order['order_number'];
                $trackBtn = !empty($link) ? "<p style='margin-top:15px;'><a href='" . htmlspecialchars($link) . "' style='background:#0369a1; color:#ffffff; padding:10px 18px; border-radius:8px; text-decoration:none; font-weight:bold; font-size:12px; display:inline-block;'>Track Your Package &rarr;</a></p>" : "";

                $htmlMsg = "
                    <p>Hello <strong>" . htmlspecialchars($order['customer_first_name']) . "</strong>,</p>
                    <p>Courier dispatch details for your order <strong>#" . htmlspecialchars($order['order_number']) . "</strong> have been updated:</p>
                    <div style='background:#f1f5f9; padding:16px; border-radius:12px; margin:15px 0;'>
                        <p style='margin:0 0 6px;'><strong>Courier Partner:</strong> " . htmlspecialchars($provider ?: 'Standard Courier') . "</p>
                        <p style='margin:0;'><strong>Tracking Number:</strong> <span style='font-family:monospace; font-weight:bold; color:#0369a1;'>" . htmlspecialchars($number) . "</span></p>
                    </div>
                    " . $trackBtn . "
                ";

                sendZohoOrderMail($pdo, $order['customer_email'], $order['customer_first_name'], $subject, $htmlMsg);
            }
        }
    }

    // -------------------------------------------------------------
    // 3. REMOVE ITEM FROM ORDER
    // -------------------------------------------------------------
    elseif ($action === 'remove_item') {
        $item_id = (int)$_POST['item_id'];
        $pdo->prepare("DELETE FROM order_items WHERE id = ? AND order_id = ?")->execute([$item_id, $order_id]);
        recalculateOrderTotals($pdo, $order_id);

        $_SESSION['swal_msg'] = "Item removed from order.";
        $_SESSION['swal_type'] = "success";
    }

    // -------------------------------------------------------------
    // 4. UPDATE ITEM QUANTITY
    // -------------------------------------------------------------
    elseif ($action === 'update_item_qty') {
        $item_id = (int)$_POST['item_id'];
        $new_qty = max(1, (int)$_POST['quantity']);

        $stmt = $pdo->prepare("SELECT price FROM order_items WHERE id = ? AND order_id = ?");
        $stmt->execute([$item_id, $order_id]);
        $item = $stmt->fetch();

        if ($item) {
            $new_total = $item['price'] * $new_qty;
            $pdo->prepare("UPDATE order_items SET quantity = ?, total = ? WHERE id = ?")->execute([$new_qty, $new_total, $item_id]);
            recalculateOrderTotals($pdo, $order_id);

            $_SESSION['swal_msg'] = "Item quantity updated.";
            $_SESSION['swal_type'] = "success";
        }
    }

    // -------------------------------------------------------------
    // 5. ADD ITEM TO ORDER & SEND MODIFIED ORDER INVOICE EMAIL
    // -------------------------------------------------------------
    elseif ($action === 'add_item') {
        $product_id = (int)$_POST['product_id'];
        $qty = max(1, (int)$_POST['quantity']);

        if ($product_id > 0) {
            $stmt = $pdo->prepare("SELECT name, regular_price, sale_price FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $p = $stmt->fetch();

            if ($p) {
                $price = $p['sale_price'] > 0 ? (float)$p['sale_price'] : (float)$p['regular_price'];
                $total = $price * $qty;

                $ins = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price, total) VALUES (?, ?, ?, ?, ?, ?)");
                $ins->execute([$order_id, $product_id, $p['name'], $qty, $price, $total]);

                recalculateOrderTotals($pdo, $order_id);

                // Fetch new total
                $tot_stmt = $pdo->prepare("SELECT total_amount FROM orders WHERE id = ?");
                $tot_stmt->execute([$order_id]);
                $updated_total = $tot_stmt->fetchColumn();

                // Dispatch Item Added Notification Email
                if (!empty($order['customer_email'])) {
                    $subject = "Item Added to Order #" . $order['order_number'];
                    $htmlMsg = "
                        <p>Hello <strong>" . htmlspecialchars($order['customer_first_name']) . "</strong>,</p>
                        <p>An additional book item has been added to your order <strong>#" . htmlspecialchars($order['order_number']) . "</strong>:</p>
                        <div style='background:#f8fafc; border:1px solid #e2e8f0; padding:15px; border-radius:12px; margin:15px 0;'>
                            <p style='margin:0 0 4px;'><strong>Title:</strong> " . htmlspecialchars($p['name']) . "</p>
                            <p style='margin:0 0 4px;'><strong>Quantity Added:</strong> " . $qty . "</p>
                            <p style='margin:0;'><strong>Item Price:</strong> Rs. " . number_format($price, 2) . "</p>
                        </div>
                        <p>Updated Total Due: <strong style='font-size:16px; color:#0369a1;'>Rs. " . number_format($updated_total, 2) . "</strong></p>
                    ";
                    sendZohoOrderMail($pdo, $order['customer_email'], $order['customer_first_name'], $subject, $htmlMsg);
                }

                $_SESSION['swal_msg'] = "Product added to order and customer notified!";
                $_SESSION['swal_type'] = "success";
            }
        }
    }

    // -------------------------------------------------------------
    // 6. RESEND FULL ORDER CONFIRMATION EMAIL
    // -------------------------------------------------------------
    elseif ($action === 'resend_confirmation_email') {
        if (!empty($order['customer_email'])) {
            $items_stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $items_stmt->execute([$order_id]);
            $current_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

            $itemsTable = '<table style="width:100%; border-collapse:collapse; margin:16px 0; font-size:13px;">';
            $itemsTable .= '<thead><tr style="background:#f1f5f9; border-bottom:1px solid #e2e8f0; text-align:left;"><th style="padding:8px 10px;">Item</th><th style="padding:8px 10px; text-align:center;">Qty</th><th style="padding:8px 10px; text-align:right;">Total</th></tr></thead><tbody>';
            
            foreach ($current_items as $it) {
                $var = !empty($it['variation_name']) ? '<br><span style="font-size:11px; color:#64748b;">Edition: ' . htmlspecialchars($it['variation_name']) . '</span>' : '';
                $itemsTable .= '<tr style="border-bottom:1px solid #f1f5f9;">';
                $itemsTable .= '<td style="padding:10px 10px;"><strong>' . htmlspecialchars($it['product_name']) . '</strong>' . $var . '</td>';
                $itemsTable .= '<td style="padding:10px 10px; text-align:center;">' . $it['quantity'] . '</td>';
                $itemsTable .= '<td style="padding:10px 10px; text-align:right;">Rs. ' . number_format($it['total'], 2) . '</td>';
                $itemsTable .= '</tr>';
            }
            $itemsTable .= '</tbody></table>';

            $deliveryLabel = ($order['shipping_cost'] == 0) ? 'Free Delivery' : 'Rs. ' . number_format($order['shipping_cost'], 2);

            // Bank Notice if Bank Transfer
            $extraNotice = "";
            if ($order['payment_method'] === 'bank_transfer') {
                $b_stmt = $pdo->query("SELECT * FROM bank_accounts WHERE is_active = 1 ORDER BY id ASC");
                $banks = $b_stmt ? $b_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
                $bankRows = '';
                foreach ($banks as $ab) {
                    $bankRows .= "<div style='margin-bottom:8px; padding-bottom:6px; border-bottom:1px dashed #cbd5e1;'><p style='margin:2px 0;'><strong>Bank:</strong> " . htmlspecialchars($ab['bank_name']) . " (" . htmlspecialchars($ab['branch_name']) . ")</p><p style='margin:2px 0;'><strong>Account Number:</strong> <span style='font-family:monospace; font-weight:bold; color:#0369a1;'>" . htmlspecialchars($ab['account_number']) . "</span></p><p style='margin:2px 0;'><strong>Account Name:</strong> " . htmlspecialchars($ab['account_name']) . "</p></div>";
                }
                $extraNotice = "
                    <div style='background:#f0fdf4; border:1px solid #bbf7d0; border-radius:14px; padding:18px; margin:20px 0;'>
                        <h3 style='margin:0 0 10px; font-size:15px; color:#166534;'>🏦 Bank Deposit / Transfer Details</h3>
                        {$bankRows}
                        <div style='background:#fef3c7; border:1px solid #fde68a; padding:10px 14px; border-radius:8px; margin-top:12px; font-size:12px; color:#92400e;'>
                            📌 Please WhatsApp deposit slip with Order ID to <strong>070 347 27 27</strong>.
                        </div>
                    </div>
                ";
            }

            $subject = "Order Confirmation #" . $order['order_number'];
            $htmlMsg = "
                <p>Hello <strong>" . htmlspecialchars($order['customer_first_name']) . "</strong>,</p>
                <p>Here is the confirmation and details for your order <strong>#" . htmlspecialchars($order['order_number']) . "</strong>:</p>
                {$itemsTable}
                <div style='background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:15px; margin-bottom:15px;'>
                    <div style='display:flex; justify-content:space-between; margin-bottom:5px;'><span>Subtotal:</span> <strong>Rs. " . number_format($order['subtotal'], 2) . "</strong></div>
                    <div style='display:flex; justify-content:space-between; margin-bottom:5px;'><span>Delivery Fee:</span> <strong>{$deliveryLabel}</strong></div>
                    <div style='display:flex; justify-content:space-between; font-size:15px; border-top:1px solid #e2e8f0; padding-top:6px; color:#0369a1;'><span><strong>Total Due:</strong></span> <strong>Rs. " . number_format($order['total_amount'], 2) . "</strong></div>
                </div>
                {$extraNotice}
            ";

            if (sendZohoOrderMail($pdo, $order['customer_email'], $order['customer_first_name'], $subject, $htmlMsg)) {
                $_SESSION['swal_msg'] = "Order confirmation email resent successfully to " . $order['customer_email'] . "!";
                $_SESSION['swal_type'] = "success";
            } else {
                $_SESSION['swal_msg'] = "Could not send email. Please check SMTP settings.";
                $_SESSION['swal_type'] = "error";
            }
        }
    }

    header("Location: view_order.php?id=" . $order_id);
    exit;
}
?>