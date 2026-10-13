<?php
// 🔴 ERROR REPORTING ON 🔴
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Security Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'shop_admin'])) {
    die("Access Denied");
}

require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_order'])) {
    
    try {
        $pdo->beginTransaction();

        // 1. Customer Details Handling
        $customer_type = $_POST['customer_type'] ?? 'new';
        $user_id = null;
        $first_name = '';
        $last_name = '';
        $email = '';
        $phone = '';

        if ($customer_type === 'existing' && !empty($_POST['existing_user_id'])) {
            $user_id = (int)$_POST['existing_user_id'];
            // FIXED: 'users' table has 'username' not 'first_name'
            $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            if ($user) {
                $first_name = $user['username'] ?? '';
                $last_name = ''; // Assuming no last name field
                $email = $user['email'] ?? '';
            }
        } else {
            $first_name = trim(htmlspecialchars($_POST['first_name'] ?? ''));
            $last_name = trim(htmlspecialchars($_POST['last_name'] ?? ''));
            $email = trim(htmlspecialchars($_POST['email'] ?? ''));
            $phone = trim(htmlspecialchars($_POST['phone'] ?? ''));
        }

        // 2. Billing/Shipping Details
        $address = trim(htmlspecialchars($_POST['address'] ?? ''));
        $town = trim(htmlspecialchars($_POST['town'] ?? ''));
        $district = trim(htmlspecialchars($_POST['district'] ?? ''));
        $province = trim(htmlspecialchars($_POST['province'] ?? ''));
        $zip_code = trim(htmlspecialchars($_POST['zip_code'] ?? ''));
        
        // FIXED: Adding required field billing_city
        $billing_city = $town; 

        // 3. Order Meta Data
        $order_status = $_POST['order_status'] ?? 'pending';
        $payment_method = $_POST['payment_method'] ?? 'Cash on Delivery';
        $payment_status = 'pending'; // Default payment status
        $shipping_cost = !empty($_POST['shipping_cost']) ? (float)$_POST['shipping_cost'] : 0;
        $order_notes = trim(htmlspecialchars($_POST['order_notes'] ?? ''));
        
        // Default Shipping methods/zones to prevent SQL errors
        $shipping_zone_id = 0;
        $shipping_method_id = 0;

        // Generate Unique Order Number
        $order_number = 'ORD-' . strtoupper(substr(uniqid(), -6));

        // 4. Calculate Subtotal from Items
        $subtotal = 0;
        $items_to_insert = [];

        if (!empty($_POST['product_id']) && is_array($_POST['product_id'])) {
            foreach ($_POST['product_id'] as $index => $pid) {
                $pid = (int)$pid;
                $qty = (int)($_POST['qty'][$index] ?? 1);
                $price = (float)($_POST['price'][$index] ?? 0);
                
                if ($pid > 0 && $qty > 0) {
                    $row_total = $price * $qty;
                    $subtotal += $row_total;
                    
                    // Fetch product name
                    $p_stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
                    $p_stmt->execute([$pid]);
                    $p_name = $p_stmt->fetchColumn() ?: 'Unknown Product';

                    $items_to_insert[] = [
                        'product_id' => $pid,
                        'product_name' => $p_name,
                        'quantity' => $qty,
                        'price' => $price,
                        'total' => $row_total
                    ];
                }
            }
        }

        if (empty($items_to_insert)) {
            throw new Exception("You must add at least one valid product to create an order.");
        }

        $total_amount = $subtotal + $shipping_cost;

        // 5. Insert Order (FIXED to match your exact DB schema)
        $sql = "INSERT INTO orders (
            order_number, user_id, customer_first_name, customer_last_name, customer_email, customer_phone,
            billing_address, billing_city, billing_town, billing_district, billing_province, billing_zip_code,
            shipping_zone_id, shipping_method_id, shipping_cost, subtotal, total_amount, 
            payment_method, payment_status, order_status, order_notes, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $order_number, $user_id, $first_name, $last_name, $email, $phone,
            $address, $billing_city, $town, $district, $province, $zip_code,
            $shipping_zone_id, $shipping_method_id, $shipping_cost, $subtotal, $total_amount,
            $payment_method, $payment_status, $order_status, $order_notes
        ]);

        $new_order_id = $pdo->lastInsertId();

        // 6. Insert Order Items
        $item_stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price, total) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($items_to_insert as $item) {
            $item_stmt->execute([
                $new_order_id, 
                $item['product_id'], 
                $item['product_name'], 
                $item['quantity'], 
                $item['price'], 
                $item['total']
            ]);
        }

        $pdo->commit();
        $_SESSION['swal_msg'] = "Order #{$order_number} created successfully!";
        $_SESSION['swal_type'] = "success";
        
        header("Location: view_order.php?id=" . $new_order_id);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['swal_msg'] = "Error: " . $e->getMessage();
        $_SESSION['swal_type'] = "error";
        header("Location: create_order.php");
        exit;
    }
} else {
    header("Location: orders.php");
    exit;
}
?>