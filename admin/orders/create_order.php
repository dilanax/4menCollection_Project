<?php
// 🔴 ERROR REPORTING ON 🔴
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Security Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'shop_admin'])) {
    header("Location: /login");
    exit;
}

require_once '../../config/db.php';

// SweetAlert Session Messages
$message = '';
$messageType = '';
if (isset($_SESSION['swal_msg'])) {
    $message = $_SESSION['swal_msg'];
    $messageType = $_SESSION['swal_type'];
    unset($_SESSION['swal_msg']);
    unset($_SESSION['swal_type']);
}

// Fetch Data for Dropdowns (FIXED: Users query)
$customers = $pdo->query("SELECT id, username, email FROM users WHERE user_role = 'customer' ORDER BY username ASC")->fetchAll();
$products = $pdo->query("SELECT id, name, sku, regular_price, sale_price FROM products WHERE status = 'published' ORDER BY name ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Order - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;900&display=swap');
        body { font-family: 'Poppins', sans-serif; background-color: #f3f4f6; color: #1f2937; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #e5e7eb; }
        ::-webkit-scrollbar-thumb { background: #9ca3af; border-radius: 10px; }
        
        .select2-container--default .select2-selection--single { background-color: #f9fafb; border: 1px solid #d1d5db; border-radius: 0.5rem; height: 42px; display: flex; align-items: center; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px; }
        .select2-dropdown { background-color: #ffffff; border: 1px solid #d1d5db; color: #374151; }
        .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable { background-color: #2563eb; color: white;}
    </style>
</head>
<body class="flex h-screen overflow-hidden bg-gray-100">

    <?php 
    if(file_exists('../../sidebar.php')){ require_once '../../sidebar.php'; } 
    elseif(file_exists('../includes/sidebar.php')) { require_once '../includes/sidebar.php'; } 
    ?>

    <main class="flex-1 flex flex-col h-screen overflow-hidden bg-gray-50">
        
        <header class="bg-white shadow-sm border-b border-gray-200 px-4 md:px-8 py-4 flex justify-between items-center z-10 sticky top-0 flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <button id="mobile-menu-open-header" class="md:hidden text-gray-600 hover:text-blue-600 focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900 tracking-tight">Create Manual Order</h1>
            </div>
            <a href="orders.php" class="text-sm font-bold text-gray-600 hover:text-gray-900 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition whitespace-nowrap">Cancel</a>
        </header>

        <div class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8 custom-scrollbar">

            <form action="create_order_action.php" method="POST" id="createOrderForm" class="flex flex-col lg:flex-row gap-6 lg:gap-8">
                
                <div class="w-full lg:w-8/12 space-y-6">
                    
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="bg-gray-50 p-4 border-b border-gray-200 flex items-center justify-between">
                            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                Customer Details
                            </h3>
                        </div>
                        <div class="p-6 space-y-5">
                            
                            <div class="flex gap-4 border-b border-gray-100 pb-4 mb-4">
                                <label class="flex items-center gap-2 cursor-pointer font-bold text-gray-700 text-sm">
                                    <input type="radio" name="customer_type" value="new" checked class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                    New / Guest Customer
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer font-bold text-gray-700 text-sm">
                                    <input type="radio" name="customer_type" value="existing" class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                    Existing Customer
                                </label>
                            </div>

                            <div id="existing_customer_div" class="hidden">
                                <label class="block text-sm font-bold text-gray-700 mb-1">Select Customer</label>
                                <select name="existing_user_id" class="select2-single w-full">
                                    <option value="">Search by username or email...</option>
                                    <?php foreach($customers as $c): ?>
                                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['username'].' - '.$c['email']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div id="new_customer_div" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">First Name</label>
                                    <input type="text" name="first_name" class="w-full bg-gray-50 border border-gray-300 text-gray-900 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Last Name</label>
                                    <input type="text" name="last_name" class="w-full bg-gray-50 border border-gray-300 text-gray-900 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Email</label>
                                    <input type="email" name="email" class="w-full bg-gray-50 border border-gray-300 text-gray-900 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Phone Number</label>
                                    <input type="text" name="phone" class="w-full bg-gray-50 border border-gray-300 text-gray-900 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="bg-gray-50 p-4 border-b border-gray-200">
                            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                Shipping Address
                            </h3>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Street Address</label>
                                <input type="text" name="address" class="w-full bg-gray-50 border border-gray-300 text-gray-900 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="House number and street name">
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Town / City</label>
                                    <input type="text" name="town" class="w-full bg-gray-50 border border-gray-300 text-gray-900 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">District</label>
                                    <input type="text" name="district" class="w-full bg-gray-50 border border-gray-300 text-gray-900 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Province</label>
                                    <input type="text" name="province" class="w-full bg-gray-50 border border-gray-300 text-gray-900 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Postal Code</label>
                                    <input type="text" name="zip_code" class="w-full bg-gray-50 border border-gray-300 text-gray-900 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="bg-gray-50 p-4 border-b border-gray-200 flex justify-between items-center">
                            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                                Order Items
                            </h3>
                            <button type="button" id="add_row_btn" class="bg-blue-100 hover:bg-blue-200 text-blue-700 text-xs font-bold px-3 py-1.5 rounded border border-blue-200 transition">
                                + Add Row
                            </button>
                        </div>
                        <div class="p-6 overflow-x-auto">
                            <table class="w-full text-left border-collapse min-w-[600px]">
                                <thead>
                                    <tr class="text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200">
                                        <th class="pb-3 w-1/2">Product</th>
                                        <th class="pb-3 w-24 text-center">Price</th>
                                        <th class="pb-3 w-20 text-center">Qty</th>
                                        <th class="pb-3 w-24 text-right">Total</th>
                                        <th class="pb-3 w-10"></th>
                                    </tr>
                                </thead>
                                <tbody id="product_table_body" class="divide-y divide-gray-100">
                                </tbody>
                            </table>
                            <p id="no_items_msg" class="text-sm text-gray-500 text-center py-6 border-b border-gray-100">No products added yet. Click "+ Add Row" to start.</p>
                        </div>
                    </div>

                </div>

                <div class="w-full lg:w-4/12 space-y-6">
                    
                    <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                        <h3 class="text-lg font-bold text-gray-900 mb-4 border-b border-gray-100 pb-3">Order Actions</h3>
                        
                        <div class="space-y-4 mb-6">
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1 uppercase tracking-wider">Order Status</label>
                                <select name="order_status" class="w-full bg-gray-50 border border-gray-300 text-gray-900 font-bold rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="pending">Pending</option>
                                    <option value="processing">Processing</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1 uppercase tracking-wider">Payment Method</label>
                                <select name="payment_method" class="w-full bg-gray-50 border border-gray-300 text-gray-900 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="Cash on Delivery">Cash on Delivery</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Card Payment">Card Payment (Manual)</option>
                                </select>
                            </div>
                        </div>

                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-200 mb-6 space-y-3">
                            <div class="flex justify-between text-sm font-medium text-gray-600">
                                <span>Subtotal:</span>
                                <span>Rs. <span id="display_subtotal">0.00</span></span>
                            </div>
                            <div class="flex justify-between items-center text-sm font-medium text-gray-600 border-b border-gray-200 pb-3">
                                <span>Shipping:</span>
                                <div class="flex items-center">
                                    <span class="mr-1">Rs.</span>
                                    <input type="number" step="0.01" name="shipping_cost" id="shipping_input" value="0" class="w-20 bg-white border border-gray-300 rounded px-2 py-1 text-right text-gray-900 focus:outline-none focus:border-blue-500 font-bold">
                                </div>
                            </div>
                            <div class="flex justify-between text-lg font-black text-gray-900 pt-1">
                                <span>Total:</span>
                                <span class="text-blue-600">Rs. <span id="display_total">0.00</span></span>
                            </div>
                        </div>

                        <button type="submit" name="create_order" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl transition shadow-md">
                            Create Order
                        </button>
                    </div>

                    <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                        <h3 class="text-sm font-bold text-gray-900 mb-3 border-b border-gray-100 pb-2">Admin Notes (Private)</h3>
                        <textarea name="order_notes" rows="4" placeholder="Add internal notes about this order..." class="w-full bg-gray-50 border border-gray-300 text-gray-900 rounded-lg px-3 py-3 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"></textarea>
                    </div>

                </div>
            </form>
        </div>
    </main>

    <template id="product_row_template">
        <tr class="product-row hover:bg-gray-50 transition border-b border-gray-100">
            <td class="py-3 pr-2">
                <select name="product_id[]" class="product-selector w-full bg-white border border-gray-300 text-gray-900 rounded px-2 py-1.5 text-sm" required>
                    <option value="">Select Product...</option>
                    <?php foreach($products as $p): 
                        $price = $p['sale_price'] > 0 ? $p['sale_price'] : $p['regular_price'];
                    ?>
                        <option value="<?php echo $p['id']; ?>" data-price="<?php echo $price; ?>"><?php echo htmlspecialchars($p['name']); ?> (Rs. <?php echo $price; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td class="py-3 px-2">
                <input type="number" step="0.01" name="price[]" class="row-price w-full bg-gray-50 border border-gray-300 text-gray-900 text-center rounded px-2 py-1.5 text-sm focus:outline-none focus:border-blue-500" value="0">
            </td>
            <td class="py-3 px-2">
                <input type="number" name="qty[]" value="1" min="1" class="row-qty w-full bg-gray-50 border border-gray-300 text-gray-900 text-center rounded px-2 py-1.5 text-sm focus:outline-none focus:border-blue-500 font-bold">
            </td>
            <td class="py-3 pl-2 text-right font-bold text-gray-900 text-sm">
                Rs. <span class="row-total-display">0.00</span>
            </td>
            <td class="py-3 text-right">
                <button type="button" class="remove-row-btn text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 p-1.5 rounded transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                </button>
            </td>
        </tr>
    </template>

    <script>
        <?php if($message): ?>
            Swal.fire({ toast: true, position: 'top-end', icon: '<?php echo $messageType; ?>', title: '<?php echo addslashes($message); ?>', showConfirmButton: false, timer: 3000, timerProgressBar: true });
        <?php endif; ?>

        $(document).ready(function() {
            $('.select2-single').select2({ width: '100%' });

            $('input[name="customer_type"]').change(function() {
                if ($(this).val() === 'existing') {
                    $('#existing_customer_div').removeClass('hidden');
                    $('#new_customer_div').addClass('hidden');
                    $('#new_customer_div input').prop('required', false);
                    $('select[name="existing_user_id"]').prop('required', true);
                } else {
                    $('#existing_customer_div').addClass('hidden');
                    $('#new_customer_div').removeClass('hidden');
                    $('select[name="existing_user_id"]').prop('required', false);
                }
            });

            const tbody = document.getElementById('product_table_body');
            const template = document.getElementById('product_row_template');
            const noItemsMsg = document.getElementById('no_items_msg');

            $('#add_row_btn').click(function() {
                const clone = template.content.cloneNode(true);
                tbody.appendChild(clone);
                noItemsMsg.style.display = 'none';
            });

            $(document).on('click', '.remove-row-btn', function() {
                $(this).closest('tr').remove();
                if(tbody.children.length === 0) {
                    noItemsMsg.style.display = 'block';
                }
                calculateGrandTotal();
            });

            $(document).on('change', '.product-selector', function() {
                const selectedOption = $(this).find('option:selected');
                const price = selectedOption.data('price') || 0;
                const row = $(this).closest('tr');
                row.find('.row-price').val(price);
                calculateRowTotal(row);
            });

            $(document).on('input', '.row-qty, .row-price', function() {
                calculateRowTotal($(this).closest('tr'));
            });

            $('#shipping_input').on('input', function() {
                calculateGrandTotal();
            });

            $('#createOrderForm').submit(function(e) {
                if(tbody.children.length === 0) {
                    e.preventDefault();
                    Swal.fire({ icon: 'error', title: 'Oops...', text: 'Please add at least one product to the order.'});
                }
            });

            function calculateRowTotal(row) {
                const qty = parseFloat(row.find('.row-qty').val()) || 0;
                const price = parseFloat(row.find('.row-price').val()) || 0;
                const total = qty * price;
                row.find('.row-total-display').text(total.toFixed(2));
                calculateGrandTotal();
            }

            function calculateGrandTotal() {
                let subtotal = 0;
                $('.product-row').each(function() {
                    const qty = parseFloat($(this).find('.row-qty').val()) || 0;
                    const price = parseFloat($(this).find('.row-price').val()) || 0;
                    subtotal += (qty * price);
                });

                const shipping = parseFloat($('#shipping_input').val()) || 0;
                const grandTotal = subtotal + shipping;

                $('#display_subtotal').text(subtotal.toFixed(2));
                $('#display_total').text(grandTotal.toFixed(2));
            }
        });

        const headerOpenBtn = document.getElementById('mobile-menu-open-header');
        if(headerOpenBtn) {
            headerOpenBtn.addEventListener('click', function() {
                const actualOpenBtn = document.getElementById('mobile-menu-open');
                if(actualOpenBtn) actualOpenBtn.click();
            });
        }
    </script>
</body>
</html>