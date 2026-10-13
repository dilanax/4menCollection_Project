<?php
// index.php හරහා Include වෙන නිසා $pdo දැනටමත් තිබේ
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (!$order_id) {
    echo "<script>window.location.href = '/';</script>";
    exit;
}

// Database එකෙන් Order එකේ වත්මන් තත්ත්වය පරීක්ෂා කිරීම
$stmt = $pdo->prepare("SELECT `order_number`, `order_status` FROM `orders` WHERE `id` = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

$is_success = ($order && $order['order_status'] === 'processing');
?>

<div class="min-h-[70vh] flex flex-col items-center justify-center px-4 py-16 text-center bg-gray-50">
    <div class="bg-white p-8 md:p-10 rounded-2xl shadow-sm border border-gray-100 max-w-md w-full">
        
        <?php if ($is_success): ?>
            <div class="w-16 h-16 bg-green-50 text-green-500 rounded-full flex items-center justify-center mx-auto mb-6 border border-green-100 shadow-sm">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            </div>
            <h1 class="text-2xl font-black text-gray-900 mb-2">Payment Successful!</h1>
            <p class="text-sm text-gray-500 mb-6 font-medium">Thank you for your purchase. Your order <span class="font-mono font-bold text-blue-600">#<?php echo htmlspecialchars($order['order_number']); ?></span> has been successfully placed and is being processed.</p>
            
            <div class="space-y-2">
                <a href="/account" class="block w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl transition shadow-md text-sm">Track Your Order</a>
                <a href="/shop" class="block w-full bg-gray-50 hover:bg-gray-100 text-gray-700 border border-gray-200 font-bold py-3 rounded-xl transition text-sm">Continue Shopping</a>
            </div>

        <?php else: ?>
            <div class="w-16 h-16 bg-blue-50 text-blue-500 rounded-full flex items-center justify-center mx-auto mb-6 border border-blue-100 shadow-sm animate-pulse">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <h1 class="text-xl font-bold text-gray-900 mb-2">Verifying Your Payment...</h1>
            <p class="text-sm text-gray-500 mb-6 font-medium">We are waiting for the official gateway confirmation. This usually takes a few seconds. Please refresh the page shortly.</p>
            
            <button onclick="window.location.reload();" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl transition shadow-md text-sm mb-2">Refresh Status</button>
            <a href="/account" class="block text-xs font-bold text-gray-400 hover:text-blue-500 transition underline">Go to Order History</a>
        <?php endif; ?>

    </div>
</div>