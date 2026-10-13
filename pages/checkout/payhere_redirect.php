<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Robust Database Connection Loader
if (!isset($pdo) || !($pdo instanceof PDO)) {
    if (file_exists(__DIR__ . '/../../config/db.php')) {
        require_once __DIR__ . '/../../config/db.php';
    } elseif (file_exists(__DIR__ . '/../config/db.php')) {
        require_once __DIR__ . '/../config/db.php';
    } elseif (file_exists('config/db.php')) {
        require_once 'config/db.php';
    }
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (!$order_id) {
    die("Invalid Order Request.");
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Database Connection Error: Unable to connect to the database. Please check config/db.php on your server.");
}

try {
    // Read Order Details
    $stmt = $pdo->prepare("SELECT * FROM `orders` WHERE `id` = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if (!$order) { die("Order not found."); }

    // 2. Read Payment Gateway Settings from Database
    $payhere = false;
    try {
        $stmt = $pdo->prepare("SELECT * FROM `payment_gateways` WHERE `gateway_id` = 'payhere' AND `is_active` = 1");
        $stmt->execute();
        $payhere = $stmt->fetch();
    } catch (Throwable $e) {
        $payhere = false;
    }

    if (!$payhere) {
        $merchant_id     = '1237960';
        $merchant_secret = '4eaUVhtl2fJ4DqedOQMA9p8LRClBpp6Xh4eblF818Rn9';
        $environment     = 'sandbox';
        $enable_handling_fee = 0;
    } else {
        $merchant_id     = !empty($payhere['merchant_id']) && $payhere['merchant_id'] !== 'admin' ? trim($payhere['merchant_id']) : '1237960';
        $merchant_secret = !empty($payhere['merchant_secret']) ? trim($payhere['merchant_secret']) : '4eaUVhtl2fJ4DqedOQMA9p8LRClBpp6Xh4eblF818Rn9';
        $environment     = !empty($payhere['environment']) ? strtolower(trim($payhere['environment'])) : 'sandbox';
        $enable_handling_fee = $payhere['enable_handling_fee'] ?? 0;
    }

    if (isset($_GET['live']) && $_GET['live'] == '1') {
        $action_url = 'https://www.payhere.lk/pay/checkout';
    } else {
        $action_url = 'https://sandbox.payhere.lk/pay/checkout';
    }

    $base_amount = floatval($order['total_amount']);
    $final_amount = $base_amount;

    if (!empty($payhere) && !empty($payhere['enable_handling_fee']) && $payhere['enable_handling_fee'] == 1) {
        $percentage_fee = ($base_amount * floatval($payhere['handling_fee_percentage'] ?? 0)) / 100;
        $fixed_fee = floatval($payhere['handling_fee_fixed'] ?? 0);
        $final_amount = $base_amount + $percentage_fee + $fixed_fee;
    }

    $formatted_amount = number_format($final_amount, 2, '.', '');
    $currency = (!empty($order['currency']) && trim($order['currency']) !== '') ? strtoupper(trim($order['currency'])) : 'LKR';
    $order_num = (!empty($order['order_number']) && trim($order['order_number']) !== '') ? trim($order['order_number']) : ('ORD-' . $order_id);

    $clean_merchant_id = preg_replace('/[^0-9]/', '', trim($merchant_id));
    if (empty($clean_merchant_id) || $clean_merchant_id === 'admin') {
        $clean_merchant_id = '1237960';
    }
    
    $clean_secret = trim(str_replace(["'", '"', ' '], '', $merchant_secret));

    $secret_hash = strtoupper(md5($clean_secret));
    $hash = strtoupper(
        md5(
            $clean_merchant_id . 
            $order_num . 
            $formatted_amount . 
            $currency .  
            $secret_hash
        ) 
    );

    $domain = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
    $return_url = $domain . "/pages/checkout/payhere_return.php?order_id=" . $order_id;
    $cancel_url = $domain . "/pages/checkout/payhere_cancel.php?order_id=" . $order_id;
    $notify_url = $domain . "/pages/checkout/payhere_notify.php";

    $first_name = !empty($order['customer_first_name']) ? $order['customer_first_name'] : (!empty($order['first_name']) ? $order['first_name'] : 'Customer');
    $last_name  = !empty($order['customer_last_name'])  ? $order['customer_last_name']  : (!empty($order['last_name'])  ? $order['last_name']  : 'Customer');
    $email      = !empty($order['customer_email'])      ? $order['customer_email']      : (!empty($order['email'])      ? $order['email']      : 'customer@readershub.lk');
    $phone      = !empty($order['customer_phone'])      ? $order['customer_phone']      : (!empty($order['phone'])      ? $order['phone']      : '0771234567');
    $address    = !empty($order['billing_address'])     ? $order['billing_address']     : (!empty($order['address'])    ? $order['address']    : 'No Address');
    $city       = !empty($order['billing_town'])        ? $order['billing_town']        : (!empty($order['city'])       ? $order['city']       : 'Colombo');

} catch (Throwable $ex) {
    die("Payment Initialization Error: " . htmlspecialchars($ex->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="referrer" content="origin">
    <title>Redirecting to Secure Payment Gateway...</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 flex flex-col items-center justify-center min-h-screen font-sans p-4">

    <div class="text-center p-8 bg-white rounded-2xl shadow-lg max-w-md w-full border border-gray-100">
        <div class="w-16 h-16 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mx-auto mb-6"></div>
        <h2 class="text-xl font-bold text-gray-800 mb-2">Connecting to PayHere Gateway...</h2>
        <p class="text-xs text-gray-500 mb-6">Redirecting to secure payment screen...</p>

        <div class="bg-gray-50 rounded-xl p-4 text-left text-xs space-y-2 border border-gray-100 mb-6">
            <div class="flex justify-between"><span class="text-gray-500">Gateway URL:</span> <span class="font-mono font-semibold text-blue-600"><?php echo parse_url($action_url, PHP_URL_HOST); ?></span></div>
            <div class="flex justify-between"><span class="text-gray-500">Merchant ID:</span> <span class="font-mono font-semibold text-gray-700"><?php echo htmlspecialchars($clean_merchant_id); ?></span></div>
            <div class="flex justify-between"><span class="text-gray-500">Order ID:</span> <span class="font-mono font-semibold text-gray-700"><?php echo htmlspecialchars($order_num); ?></span></div>
            <div class="flex justify-between"><span class="text-gray-500">Amount:</span> <span class="font-semibold text-emerald-600"><?php echo htmlspecialchars($currency . ' ' . $formatted_amount); ?></span></div>
        </div>

        <button onclick="document.getElementById('payhere_form').submit();" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-xl transition text-sm shadow-md mb-2">
            Click here if not redirected automatically
        </button>
    </div>

    <form id="payhere_form" method="POST" action="<?php echo $action_url; ?>" class="hidden">
        <input type="hidden" name="merchant_id" value="<?php echo htmlspecialchars($clean_merchant_id); ?>">
        <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($return_url); ?>">
        <input type="hidden" name="cancel_url" value="<?php echo htmlspecialchars($cancel_url); ?>">
        <input type="hidden" name="notify_url" value="<?php echo htmlspecialchars($notify_url); ?>">  
        
        <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order_num); ?>">
        <input type="hidden" name="items" value="Order #<?php echo htmlspecialchars($order_num); ?>">
        <input type="hidden" name="currency" value="<?php echo htmlspecialchars($currency); ?>">
        <input type="hidden" name="amount" value="<?php echo htmlspecialchars($formatted_amount); ?>">  
        
        <input type="hidden" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>">
        <input type="hidden" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>">
        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
        <input type="hidden" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
        <input type="hidden" name="address" value="<?php echo htmlspecialchars($address); ?>">
        <input type="hidden" name="city" value="<?php echo htmlspecialchars($city); ?>">
        <input type="hidden" name="country" value="Sri Lanka">
        
        <input type="hidden" name="hash" value="<?php echo $hash; ?>">
    </form>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            setTimeout(function() {
                document.getElementById('payhere_form').submit();
            }, 500);
        });
    </script>
</body>
</html>
