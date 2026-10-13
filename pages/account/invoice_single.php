<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($pdo)) {
    require_once 'config/db.php';
}

// Redirect unauthenticated guests to login
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$order_number = isset($_GET['order_number']) ? trim($_GET['order_number']) : '';

// 1. Fetch Order Details (Secure: user owns it or user is admin)
$order = null;
if ($order_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND (user_id = ? OR ? IN (SELECT id FROM users WHERE id = ? AND user_role IN ('admin', 'shop_admin'))) LIMIT 1");
    $stmt->execute([$order_id, $user_id, $user_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif (!empty($order_number)) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ? AND (user_id = ? OR ? IN (SELECT id FROM users WHERE id = ? AND user_role IN ('admin', 'shop_admin'))) LIMIT 1");
    $stmt->execute([$order_number, $user_id, $user_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$order) {
    echo "<div class='min-h-[60vh] flex flex-col items-center justify-center p-6 text-center font-sans'>
            <div class='w-16 h-16 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center mb-4'>
                <svg class='w-8 h-8' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'/></svg>
            </div>
            <h2 class='text-2xl font-black text-slate-900 mb-2'>Invoice Not Found</h2>
            <p class='text-xs text-slate-500 mb-6'>The requested order does not exist or you do not have permission to view it.</p>
            <a href='/account' class='bg-sky-700 text-white font-bold text-xs uppercase tracking-wider px-6 py-3 rounded-xl hover:bg-sky-800 transition'>Return to Account</a>
          </div>";
    return;
}

// 2. Fetch Order Items
$item_stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC");
$item_stmt->execute([(int)$order['id']]);
$items = $item_stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Fetch Company / Store Information from email_settings
$settings = [];
try {
    $set_stmt = $pdo->query("SELECT * FROM email_settings WHERE id = 1 LIMIT 1");
    if ($set_stmt) {
        $settings = $set_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {}

$business_name = $settings['business_name'] ?? 'ReadersHub (Pvt) Ltd';
$business_phone = $settings['phone_number'] ?? '+94 77 123 4567';
$business_email = $settings['smtp_email'] ?? 'info@readershub.lk';
$business_address = $settings['address'] ?? 'No. 12, High Level Road, Colombo, Sri Lanka';
?>

<!-- Include html2canvas & jsPDF for instant Client-side PDF & Image conversion -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<style>
    @media print {
        body * {
            visibility: hidden;
        }
        #invoice-card, #invoice-card * {
            visibility: visible;
        }
        #invoice-card {
            position: absolute;
            left: 0;
            top: 0;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            box-shadow: none !important;
            border: none !important;
        }
        .no-print {
            display: none !important;
        }
    }
</style>

<div class="w-full bg-[#f8fafc] py-8 sm:py-12 text-slate-800 min-h-screen font-sans" style="font-family: 'Poppins', sans-serif;">
    <div class="max-w-4xl mx-auto px-4 sm:px-6">
        
        <!-- Top Action Buttons Bar -->
        <div class="no-print flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-xs">
            <a href="/account" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 hover:text-sky-700 transition">
                <span>&larr; Back to My Orders</span>
            </a>

            <div class="flex items-center flex-wrap gap-2.5">
                <!-- Print Button -->
                <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold px-4 py-2.5 rounded-xl transition shadow-2xs">
                    <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Print</span>
                </button>

                <!-- Download as Image (PNG) -->
                <button type="button" id="btn-download-img" onclick="downloadAsImage()" class="inline-flex items-center gap-2 bg-white hover:bg-slate-50 border border-slate-300 text-slate-800 text-xs font-bold px-4 py-2.5 rounded-xl transition shadow-2xs">
                    <svg class="w-4 h-4 text-sky-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>Download Image</span>
                </button>

                <!-- Download as PDF -->
                <button type="button" id="btn-download-pdf" onclick="downloadAsPDF()" class="inline-flex items-center gap-2 bg-sky-700 hover:bg-sky-800 text-white text-xs font-bold px-5 py-2.5 rounded-xl transition shadow-md shadow-sky-700/20 active:scale-98">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Download PDF</span>
                </button>
            </div>
        </div>

        <!-- ==================== THE INVOICE CARD ==================== -->
        <div id="invoice-card" class="bg-white rounded-3xl p-6 sm:p-10 border border-slate-200/90 shadow-sm relative overflow-hidden">
            
            <!-- Watermark / Brand Header -->
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-6 border-b border-slate-100 pb-8">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                        <span class="text-sky-700"><?= htmlspecialchars($business_name); ?></span>
                    </h1>
                    <p class="text-xs text-slate-500 mt-1 max-w-sm"><?= htmlspecialchars($business_address); ?></p>
                    <p class="text-xs text-slate-500 mt-0.5">Hotline: <?= htmlspecialchars($business_phone); ?> | <?= htmlspecialchars($business_email); ?></p>
                </div>

                <div class="sm:text-right">
                    <span class="text-[10px] font-black uppercase tracking-widest text-sky-700 bg-sky-50 border border-sky-100 px-3 py-1 rounded-md inline-block mb-1.5">Official Invoice</span>
                    <h3 class="text-xl font-mono font-black text-slate-900 tracking-tight">#<?= htmlspecialchars($order['order_number']); ?></h3>
                    <p class="text-xs text-slate-400 font-medium mt-1">Date: <strong class="text-slate-700"><?= date('F j, Y', strtotime($order['created_at'])); ?></strong></p>
                    <p class="text-xs text-slate-400 font-medium mt-0.5">Status: <strong class="uppercase text-sky-700"><?= htmlspecialchars($order['order_status'] ?? 'Completed'); ?></strong></p>
                </div>
            </div>

            <!-- Client Details & Shipping Breakdown -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 py-8 border-b border-slate-100 text-xs">
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block mb-1.5">Billed & Shipped To:</span>
                    <h4 class="text-sm font-black text-slate-900">
                        <?= htmlspecialchars(($order['customer_first_name'] ?? '') . ' ' . ($order['customer_last_name'] ?? '')); ?>
                    </h4>
                    <p class="text-slate-600 mt-1 leading-relaxed">
                        <?= htmlspecialchars($order['billing_address'] ?? ''); ?><br>
                        <?= htmlspecialchars(($order['billing_city'] ?? '') . ', ' . ($order['billing_district'] ?? '')); ?><br>
                        <?= htmlspecialchars($order['billing_province'] ?? ''); ?> <?= htmlspecialchars($order['billing_zip_code'] ?? ''); ?>
                    </p>
                    <p class="text-slate-600 font-medium mt-1">Phone: <?= htmlspecialchars($order['customer_phone'] ?? ''); ?></p>
                    <p class="text-slate-600 font-medium mt-0.5">Email: <?= htmlspecialchars($order['customer_email'] ?? ''); ?></p>
                </div>

                <div class="sm:text-right">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block mb-1.5">Order Information:</span>
                    <p class="text-slate-600 font-medium">Payment Method: <strong class="text-slate-900"><?= strtoupper(htmlspecialchars($order['payment_method'] ?? 'COD')); ?></strong></p>
                    
                    <?php if (!empty($order['tracking_number'])): ?>
                        <div class="mt-2.5 sm:inline-block bg-slate-50 p-2.5 rounded-xl border border-slate-200/80">
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">Courier Tracking</span>
                            <span class="font-mono text-xs font-black text-sky-700"><?= htmlspecialchars($order['tracking_provider'] ?? 'Courier'); ?>: <?= htmlspecialchars($order['tracking_number']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Items Table -->
            <div class="py-6">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b-2 border-slate-100 text-[10px] font-black uppercase tracking-wider text-slate-400">
                            <th class="py-3 px-2">Book Title & Edition</th>
                            <th class="py-3 px-2 text-center">Unit Price</th>
                            <th class="py-3 px-2 text-center">Qty</th>
                            <th class="py-3 px-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="py-3.5 px-2">
                                    <span class="font-bold text-slate-900 block"><?= htmlspecialchars($item['product_name']); ?></span>
                                    <?php if (!empty($item['variation_name'])): ?>
                                        <span class="text-[11px] text-sky-700 font-medium">Edition: <?= htmlspecialchars($item['variation_name']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-2 text-center text-slate-500 font-medium">
                                    Rs. <?= number_format($item['price'], 2); ?>
                                </td>
                                <td class="py-3.5 px-2 text-center font-bold text-slate-900">
                                    <?= (int)$item['quantity']; ?>
                                </td>
                                <td class="py-3.5 px-2 text-right font-extrabold text-slate-900">
                                    Rs. <?= number_format($item['total'], 2); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Total Calculations Table -->
            <div class="border-t border-slate-100 pt-4 flex justify-end">
                <div class="w-full sm:w-72 space-y-2 text-xs">
                    <div class="flex justify-between text-slate-500">
                        <span>Items Subtotal:</span>
                        <span class="font-bold text-slate-800">Rs. <?= number_format($order['subtotal'], 2); ?></span>
                    </div>
                    <div class="flex justify-between text-slate-500">
                        <span>Courier Delivery:</span>
                        <span class="font-bold text-slate-800"><?= (float)$order['shipping_cost'] > 0 ? 'Rs. ' . number_format($order['shipping_cost'], 2) : 'Free Delivery'; ?></span>
                    </div>
                    <div class="flex justify-between items-baseline pt-2 border-t-2 border-slate-200 text-sm">
                        <span class="font-black text-slate-900">Total Amount:</span>
                        <span class="text-lg font-black text-sky-700">Rs. <?= number_format($order['total_amount'], 2); ?></span>
                    </div>
                </div>
            </div>

            <!-- Footer Signoff -->
            <div class="mt-10 pt-6 border-t border-slate-100 text-center text-[11px] text-slate-400">
                <p>Thank you for choosing <strong><?= htmlspecialchars($business_name); ?></strong>! Keep reading and expanding your world.</p>
                <p class="mt-1 font-mono text-[10px]">This is an electronically verified system generated invoice.</p>
            </div>

        </div>

    </div>
</div>

<script>
    // Download as PDF using html2canvas & jsPDF
    function downloadAsPDF() {
        const invoice = document.getElementById('invoice-card');
        const btn = document.getElementById('btn-download-pdf');
        const originalText = btn.innerHTML;
        btn.innerText = 'Generating PDF...';
        btn.disabled = true;

        html2canvas(invoice, {
            scale: 2,
            useCORS: true,
            backgroundColor: '#ffffff'
        }).then(canvas => {
            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF('p', 'mm', 'a4');
            const imgData = canvas.toDataURL('image/png');

            const pdfWidth = pdf.internal.pageSize.getWidth();
            const pdfHeight = (canvas.height * pdfWidth) / canvas.width;

            pdf.addImage(imgData, 'PNG', 0, 5, pdfWidth, pdfHeight);
            pdf.save('Invoice-<?= htmlspecialchars($order['order_number']); ?>.pdf');

            btn.innerHTML = originalText;
            btn.disabled = false;
        }).catch(err => {
            console.error('PDF generation error:', err);
            btn.innerHTML = originalText;
            btn.disabled = false;
            alert('Failed to generate PDF. You can also use the Print button to Save as PDF.');
        });
    }

    // Download as Image (PNG) using html2canvas
    function downloadAsImage() {
        const invoice = document.getElementById('invoice-card');
        const btn = document.getElementById('btn-download-img');
        const originalText = btn.innerHTML;
        btn.innerText = 'Generating Image...';
        btn.disabled = true;

        html2canvas(invoice, {
            scale: 2,
            useCORS: true,
            backgroundColor: '#ffffff'
        }).then(canvas => {
            const link = document.createElement('a');
            link.download = 'Invoice-<?= htmlspecialchars($order['order_number']); ?>.png';
            link.href = canvas.toDataURL('image/png');
            link.click();

            btn.innerHTML = originalText;
            btn.disabled = false;
        }).catch(err => {
            console.error('Image generation error:', err);
            btn.innerHTML = originalText;
            btn.disabled = false;
            alert('Failed to generate image.');
        });
    }
</script>