<?php
// 🔴 HTML HEADER/FOOTER LOAD නොවිය යුතුය (BACKGROUND WEBHOOK) 🔴
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config/db.php'; // Background එකෙන් කෙලින්ම එන නිසා path එක පියවර 2ක් පස්සට විය යුතුයි

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. PayHere එකෙන් එවපු POST Params ටික අල්ලා ගැනීම
    $merchant_id        = $_POST['merchant_id'] ?? '';
    $order_id           = $_POST['order_id'] ?? ''; // අපේ Order Number එක
    $payhere_amount     = $_POST['payhere_amount'] ?? '';
    $payhere_currency   = $_POST['payhere_currency'] ?? '';
    $status_code        = $_POST['status_code'] ?? '';
    $md5sig             = $_POST['md5sig'] ?? '';
    $payment_id         = $_POST['payment_id'] ?? '';
    $method             = $_POST['method'] ?? '';

    // 2. Database එකෙන් සයිට් එකේ තියෙන රහස්‍ය Merchant Secret එක කියවීම
    $stmt = $pdo->prepare("SELECT `merchant_secret` FROM `payment_gateways` WHERE `gateway_id` = 'payhere'");
    $stmt->execute();
    $payhere = $stmt->fetch();
    
    $merchant_secret = !empty($payhere['merchant_secret']) ? trim($payhere['merchant_secret']) : '4eaUVhtl2fJ4DqedOQMA9p8LRClBpp6Xh4eblF818Rn9';

    // 3. Documentation එකට අනුව දත්ත වෙනස් කර නොමැතිදැයි (Check Checksum) දේශීයව Hash එකක් සාදා බැලීම
    $local_md5sig = strtoupper(
        md5(
            $merchant_id . 
            $order_id . 
            $payhere_amount . 
            $payhere_currency . 
            $status_code . 
            strtoupper(md5($merchant_secret)) 
        ) 
    );

    // 4. සත්‍යාපනය සාර්ථක නම් සහ Status Code එක 2 (Success) නම් පමණක් Database එක Update කිරීම
    if (($local_md5sig === $md5sig) && ($status_code == 2)) {
        
        try {
            // මුලින්ම Order එක දැනටමත් Update වෙලාදැයි බැලීම (Duplicate requests වැළැක්වීමට)
            $chk_stmt = $pdo->prepare("SELECT `order_status` FROM `orders` WHERE `order_number` = ?");
            $chk_stmt->execute([$order_id]);
            $current_order = $chk_stmt->fetch();

            if ($current_order && $current_order['order_status'] === 'pending_payment') {
                
                // Order Status එක 'processing' ලෙස වෙනස් කර PayHere Payment ID එක සටහන් කිරීම
                $update_sql = "UPDATE `orders` SET 
                               `order_status` = 'processing', 
                               `payment_method` = 'payhere',
                               `transaction_id` = ? 
                               WHERE `order_number` = ?";
                               
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute([$payment_id, $order_id]);

                // (ঐච්ඡිකයි): මෙතනදී ඔයාට Customer ට ඔටෝමැටික් Email එකක් යවන Invoice Trigger එක ලියන්න පුළුවන්.
            }
            
            // PayHere එකට 200 OK Response එකක් දීම
            http_response_code(200);
            echo "Payment Successfully Verified and Processed.";
            
        } catch (Exception $e) {
            http_response_code(500);
            error_log("PayHere Webhook Database Error: " . $e->getMessage());
        }

    } else {
        // Verification අසාර්ථක නම් හෝ වෙනත් status code එකක් නම් (-1, -2 වැනි)
        http_response_code(400);
        echo "Authentication Failed or Non-success Status Code.";
    }
    exit;
}