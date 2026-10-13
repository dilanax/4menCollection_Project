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

    // ADD / UPDATE CLASS
    if ($action == 'add_class' || $action == 'update_class') {
        $class_id = (int)($_POST['class_id'] ?? 0);
        $class_name = trim(htmlspecialchars($_POST['class_name'] ?? '', ENT_QUOTES, 'UTF-8'));
        $additional_cost = (float)($_POST['additional_cost'] ?? 0); // Base cost
        
        // --- Process Quantity Conditions into JSON ---
        $cost_conditions = [];
        if (!empty($_POST['qty_min']) && is_array($_POST['qty_min'])) {
            for ($i = 0; $i < count($_POST['qty_min']); $i++) {
                $min = (int)($_POST['qty_min'][$i] ?? 0);
                $max = (int)($_POST['qty_max'][$i] ?? 0); // 0 means infinity/no limit
                $cost = (float)($_POST['cond_cost'][$i] ?? 0);
                
                if ($min > 0) {
                    $cost_conditions[] = ['min' => $min, 'max' => $max, 'cost' => $cost];
                }
            }
        }
        $cost_conditions_json = empty($cost_conditions) ? null : json_encode($cost_conditions);

        if (!empty($class_name)) {
            try {
                if ($action == 'add_class') {
                    $stmt = $pdo->prepare("INSERT INTO shipping_classes (class_name, additional_cost, cost_conditions) VALUES (?, ?, ?)");
                    $stmt->execute([$class_name, $additional_cost, $cost_conditions_json]);
                    $_SESSION['swal_msg'] = "Shipping class successfully added!";
                    $_SESSION['swal_type'] = "success";
                } else {
                    $stmt = $pdo->prepare("UPDATE shipping_classes SET class_name = ?, additional_cost = ?, cost_conditions = ? WHERE id = ?");
                    $stmt->execute([$class_name, $additional_cost, $cost_conditions_json, $class_id]);
                    $_SESSION['swal_msg'] = "Shipping class successfully updated!";
                    $_SESSION['swal_type'] = "success";
                }
            } catch (Exception $e) {
                $_SESSION['swal_msg'] = "Database Error occurred.";
                $_SESSION['swal_type'] = "error";
            }
        } else {
            $_SESSION['swal_msg'] = "Class Name is required.";
            $_SESSION['swal_type'] = "error";
        }
    }

    // DELETE CLASS
    elseif ($action == 'delete_class') {
        $class_id = (int)($_POST['class_id'] ?? 0);
        if ($class_id > 0) {
            try {
                $pdo->beginTransaction();
                // Reset products that had this class to NULL (No class)
                $pdo->prepare("UPDATE products SET shipping_class_id = NULL WHERE shipping_class_id = ?")->execute([$class_id]);
                // Delete the class
                $pdo->prepare("DELETE FROM shipping_classes WHERE id = ?")->execute([$class_id]);
                $pdo->commit();
                
                $_SESSION['swal_msg'] = "Shipping class deleted!";
                $_SESSION['swal_type'] = "success";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['swal_msg'] = "Failed to delete shipping class.";
                $_SESSION['swal_type'] = "error";
            }
        }
    }

    header("Location: shipping_classes.php");
    exit;
}
?>