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

require_once __DIR__ . '/../../config/db.php';

// ==========================================
// ZOHO SMTP EMAIL DISPATCH (PORT 587 + STARTTLS SOCKET)
// ==========================================
function sendZohoStatusMail($pdo, $toEmail, $toName, $subject, $htmlContent) {
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

    // Normalize newlines safely
    $normalizedBody = str_replace(["\r\n", "\r"], "\n", $fullBody);
    $normalizedBody = str_replace("\n", "\r\n", $normalizedBody);

    $domain = (strpos($username, '@') !== false) ? explode('@', $username)[1] : 'readershub.lk';
    $messageId = "<" . time() . "." . md5(uniqid(rand(), true)) . "@" . $domain . ">";

    // Clean RFC 2822 Headers
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

        $read($socket); // 220 banner
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
// HANDLE POST ACTIONS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // ACTION 1: UPDATE ORDER STATUS FROM TABLE OR DROPDOWN
    if ($_POST['action'] === 'update_status') {
        $order_id = (int)($_POST['order_id'] ?? 0);
        $new_status = trim($_POST['order_status'] ?? '');

        $allowed_statuses = ['pending', 'processing', 'ready', 'packed', 'shipped', 'completed', 'cancelled', 'refunded'];

        if (in_array($new_status, $allowed_statuses) && $order_id > 0) {
            try {
                $o_stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
                $o_stmt->execute([$order_id]);
                $order = $o_stmt->fetch(PDO::FETCH_ASSOC);

                if ($order) {
                    $stmt = $pdo->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
                    $stmt->execute([$new_status, $order_id]);

                    $_SESSION['swal_msg'] = "Order #{$order['order_number']} status updated to " . ucfirst($new_status) . "!";
                    $_SESSION['swal_type'] = "success";

                    $cName = htmlspecialchars($order['customer_first_name'] ?? '');
                    $ordNo = htmlspecialchars($order['order_number'] ?? '');
                    $totalFormatted = number_format((float)($order['total_amount'] ?? 0), 2);
                    $payMethod = htmlspecialchars($order['payment_method'] ?? '');

                    $subject = "";
                    $htmlMsg = "";

                    switch ($new_status) {
                        case 'pending':
                            $subject = "Order #{$ordNo} Received - Pending Verification";
                            $htmlMsg = "
                                <p>Hello <strong>{$cName}</strong>,</p>
                                <p>Thank you for ordering with us. Your book order <strong>#{$ordNo}</strong> is currently pending confirmation from our inventory team.</p>
                                <div style='background:#fef3c7; border:1px solid #fde68a; padding:14px; border-radius:12px; margin:16px 0; color:#92400e;'>
                                    <strong>Status: PENDING</strong><br>
                                    Total Amount: <strong>Rs. {$totalFormatted}</strong><br>
                                    Payment Method: {$payMethod}
                                </div>
                                <p>We will notify you as soon as your items are verified and sent for packing.</p>
                            ";
                            break;

                        case 'processing':
                            $subject = "We are preparing your books - Order #{$ordNo}";
                            $htmlMsg = "
                                <p>Hello <strong>{$cName}</strong>,</p>
                                <p>Good news! Your order <strong>#{$ordNo}</strong> is now being <strong>processed</strong>.</p>
                                <div style='background:#e0f2fe; border:1px solid #bae6fd; padding:14px; border-radius:12px; margin:16px 0; color:#0369a1;'>
                                    <strong>Status: PROCESSING</strong><br>
                                    Our warehouse is collecting your book titles and preparing your parcel for dispatch.
                                </div>
                                <p>Total Payable: <strong>Rs. {$totalFormatted}</strong> ({$payMethod})</p>
                            ";
                            break;

                        case 'ready':
                        case 'packed':
                            $subject = "Your Book Parcel is Packed & Ready - Order #{$ordNo}";
                            $htmlMsg = "
                                <p>Hello <strong>{$cName}</strong>,</p>
                                <p>Your order <strong>#{$ordNo}</strong> has been securely inspected, packed, and is ready for courier pickup.</p>
                                <div style='background:#e0e7ff; border:1px solid #c7d2fe; padding:14px; border-radius:12px; margin:16px 0; color:#3730a3;'>
                                    <strong>Status: PACKED & READY TO DISPATCH</strong><br>
                                    Handover to our islandwide delivery partner is scheduled shortly.
                                </div>
                            ";
                            break;

                        case 'shipped':
                            $subject = "Your Books are on the Way! - Order #{$ordNo} Shipped";
                            $trackingInfo = "";
                            if (!empty($order['tracking_number'])) {
                                $trackingInfo = "
                                    <p style='margin:6px 0 0;'>Courier Partner: <strong>" . htmlspecialchars($order['tracking_provider'] ?: 'Standard Courier') . "</strong></p>
                                    <p style='margin:4px 0 0;'>Waybill/Tracking No: <span style='font-family:monospace; font-weight:bold; color:#0369a1;'>" . htmlspecialchars($order['tracking_number']) . "</span></p>
                                ";
                            }
                            $htmlMsg = "
                                <p>Hello <strong>{$cName}</strong>,</p>
                                <p>Your parcel for order <strong>#{$ordNo}</strong> has been handed over to the courier service and is out for delivery.</p>
                                <div style='background:#f3e8ff; border:1px solid #e9d5ff; padding:15px; border-radius:12px; margin:16px 0; color:#6b21a8;'>
                                    <strong>Status: SHIPPED</strong>
                                    {$trackingInfo}
                                </div>
                                <p>Please have <strong>Rs. {$totalFormatted}</strong> ready for payment upon arrival ({$payMethod}).</p>
                            ";
                            break;

                        case 'completed':
                            $subject = "Delivered! Thank you for reading with us - Order #{$ordNo}";
                            $htmlMsg = "
                                <p>Hello <strong>{$cName}</strong>,</p>
                                <p>Your order <strong>#{$ordNo}</strong> has been marked as <strong>Completed / Delivered</strong>.</p>
                                <div style='background:#d1fae5; border:1px solid #a7f3d0; padding:15px; border-radius:12px; margin:16px 0; color:#065f46;'>
                                    <strong>Status: COMPLETED / DELIVERED</strong><br>
                                    We hope you enjoy reading your new titles!
                                </div>
                                <p>Feel free to visit ReadersHub again to explore new arrivals or share a book review.</p>
                            ";
                            break;

                        case 'cancelled':
                            $subject = "Order #{$ordNo} has been Cancelled";
                            $htmlMsg = "
                                <p>Hello <strong>{$cName}</strong>,</p>
                                <p>This is to inform you that your order <strong>#{$ordNo}</strong> has been <strong>cancelled</strong>.</p>
                                <div style='background:#ffe4e6; border:1px solid #fecdd3; padding:15px; border-radius:12px; margin:16px 0; color:#9f1239;'>
                                    <strong>Status: CANCELLED</strong>
                                </div>
                                <p>If this was unexpected or you require assistance, please contact us on WhatsApp (070 347 27 27) immediately.</p>
                            ";
                            break;

                        case 'refunded':
                            $subject = "Refund Notification - Order #{$ordNo}";
                            $htmlMsg = "
                                <p>Hello <strong>{$cName}</strong>,</p>
                                <p>Your order <strong>#{$ordNo}</strong> has been marked as <strong>Refunded</strong>.</p>
                                <div style='background:#ffe4e6; border:1px solid #fecdd3; padding:15px; border-radius:12px; margin:16px 0; color:#9f1239;'>
                                    <strong>Status: REFUNDED</strong><br>
                                    Total Amount: Rs. {$totalFormatted}
                                </div>
                                <p>Our billing department has processed the refund voucher/credit for your account.</p>
                            ";
                            break;
                    }

                    // Dispatch notification to customer
                    if (!empty($subject) && !empty($htmlMsg) && !empty($order['customer_email'])) {
                        sendZohoStatusMail($pdo, $order['customer_email'], $order['customer_first_name'], $subject, $htmlMsg);
                    }

                } else {
                    $_SESSION['swal_msg'] = "Order not found.";
                    $_SESSION['swal_type'] = "error";
                }
            } catch (Throwable $e) {
                $_SESSION['swal_msg'] = "Failed to update order status: " . $e->getMessage();
                $_SESSION['swal_type'] = "error";
            }
        } else {
            $_SESSION['swal_msg'] = "Invalid status or order ID.";
            $_SESSION['swal_type'] = "error";
        }
    }

    // ACTION 2: DELETE ORDER (PERMANENT DELETE)
    if ($_POST['action'] === 'delete_order') {
        $order_id = (int)($_POST['order_id'] ?? 0);

        if ($order_id > 0) {
            try {
                $pdo->beginTransaction();

                $f_stmt = $pdo->prepare("SELECT order_number FROM orders WHERE id = ?");
                $f_stmt->execute([$order_id]);
                $ord_number = $f_stmt->fetchColumn();

                if ($ord_number) {
                    // 1. Delete associated ordered items first
                    $del_items = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
                    $del_items->execute([$order_id]);

                    // 2. Delete the order record
                    $del_order = $pdo->prepare("DELETE FROM orders WHERE id = ?");
                    $del_order->execute([$order_id]);

                    $pdo->commit();

                    $_SESSION['swal_msg'] = "Order #{$ord_number} has been permanently deleted!";
                    $_SESSION['swal_type'] = "success";
                } else {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $_SESSION['swal_msg'] = "Order not found.";
                    $_SESSION['swal_type'] = "error";
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $_SESSION['swal_msg'] = "Error deleting order: " . $e->getMessage();
                $_SESSION['swal_type'] = "error";
            }
        } else {
            $_SESSION['swal_msg'] = "Invalid order ID specified.";
            $_SESSION['swal_type'] = "error";
        }
    }

    // Preserve active filters in redirect
    $redirect_url = "orders.php";
    $get_params = $_GET;
    unset($get_params['action'], $get_params['order_id'], $get_params['order_status']);
    $query_string = http_build_query($get_params);
    if (!empty($query_string)) {
        $redirect_url .= '?' . $query_string;
    }

    header("Location: " . $redirect_url);
    exit;
}

header("Location: orders.php");
exit;