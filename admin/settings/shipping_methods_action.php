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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $zone_id = (int)($_POST['zone_id'] ?? 0);

    if ($zone_id === 0) {
        die("Invalid Zone ID.");
    }

    // ADD METHOD
    if ($action == 'add_method') {
        $method_name = trim(htmlspecialchars($_POST['method_name'] ?? '', ENT_QUOTES, 'UTF-8'));
        $cost = (float)($_POST['cost'] ?? 0);
        $min_order_amount = !empty($_POST['min_order_amount']) ? (float)$_POST['min_order_amount'] : null;
        
        if (!empty($method_name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO shipping_methods (zone_id, method_name, cost, min_order_amount, is_active) VALUES (?, ?, ?, ?, 1)");
                $stmt->execute([$zone_id, $method_name, $cost, $min_order_amount]);
                $_SESSION['swal_msg'] = "Shipping method successfully added!";
                $_SESSION['swal_type'] = "success";
            } catch (Exception $e) {
                $_SESSION['swal_msg'] = "Failed to add shipping method.";
                $_SESSION['swal_type'] = "error";
            }
        }
    }

    // UPDATE METHOD
    elseif ($action == 'update_method') {
        $method_id = (int)($_POST['method_id'] ?? 0);
        $method_name = trim(htmlspecialchars($_POST['method_name'] ?? '', ENT_QUOTES, 'UTF-8'));
        $cost = (float)($_POST['cost'] ?? 0);
        $min_order_amount = !empty($_POST['min_order_amount']) ? (float)$_POST['min_order_amount'] : null;
        
        if (!empty($method_name) && $method_id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE shipping_methods SET method_name = ?, cost = ?, min_order_amount = ? WHERE id = ? AND zone_id = ?");
                $stmt->execute([$method_name, $cost, $min_order_amount, $method_id, $zone_id]);
                $_SESSION['swal_msg'] = "Shipping method successfully updated!";
                $_SESSION['swal_type'] = "success";
            } catch (Exception $e) {
                $_SESSION['swal_msg'] = "Failed to update shipping method.";
                $_SESSION['swal_type'] = "error";
            }
        }
    }

    // DELETE METHOD
    elseif ($action == 'delete_method') {
        $method_id = (int)($_POST['method_id'] ?? 0);
        if ($method_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM shipping_methods WHERE id = ? AND zone_id = ?");
                $stmt->execute([$method_id, $zone_id]);
                $_SESSION['swal_msg'] = "Shipping method deleted!";
                $_SESSION['swal_type'] = "success";
            } catch (Exception $e) {
                $_SESSION['swal_msg'] = "Failed to delete shipping method.";
                $_SESSION['swal_type'] = "error";
            }
        }
    }

    // TOGGLE STATUS
    elseif ($action == 'toggle_status') {
        $method_id = (int)($_POST['method_id'] ?? 0);
        $current_status = (int)($_POST['current_status'] ?? 0);
        $new_status = $current_status === 1 ? 0 : 1;
        
        if ($method_id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE shipping_methods SET is_active = ? WHERE id = ? AND zone_id = ?");
                $stmt->execute([$new_status, $method_id, $zone_id]);
                $_SESSION['swal_msg'] = "Method status updated!";
                $_SESSION['swal_type'] = "success";
            } catch (Exception $e) {
                $_SESSION['swal_msg'] = "Failed to update status.";
                $_SESSION['swal_type'] = "error";
            }
        }
    }

    header("Location: shipping_methods.php?zone_id=" . $zone_id);
    exit;
}
?>