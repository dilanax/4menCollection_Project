<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'shop_admin'])) {
    die("Access Denied");
}

require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    // 1. SAVE OR UPDATE RATE RULE (POPUP MODAL)
    if ($_POST['action'] === 'save_rate_rule') {
        $rate_id = !empty($_POST['rate_id']) ? (int)$_POST['rate_id'] : null;
        $method_title = trim(htmlspecialchars($_POST['method_title'] ?? 'Standard Delivery', ENT_QUOTES, 'UTF-8'));
        $country_id = (int)($_POST['country_id'] ?? 1);
        $province_id = !empty($_POST['province_id']) ? (int)$_POST['province_id'] : null;
        $district_id = !empty($_POST['district_id']) ? (int)$_POST['district_id'] : null;
        $shipping_type = ($_POST['shipping_type'] ?? 'paid') === 'free' ? 'free' : 'paid';
        $cost = ($shipping_type === 'free') ? 0.00 : (float)($_POST['cost'] ?? 0);
        $min_order_free = !empty($_POST['min_order_free']) ? (float)$_POST['min_order_free'] : null;
        $estimated_days = trim(htmlspecialchars($_POST['estimated_days'] ?? '2-3 Business Days', ENT_QUOTES, 'UTF-8'));

        try {
            if ($rate_id) {
                // UPDATE existing rule
                $stmt = $pdo->prepare("
                    UPDATE geo_delivery_rates 
                    SET method_title=?, country_id=?, province_id=?, district_id=?, shipping_type=?, cost=?, min_order_free=?, estimated_days=? 
                    WHERE id=?
                ");
                $stmt->execute([$method_title, $country_id, $province_id, $district_id, $shipping_type, $cost, $min_order_free, $estimated_days, $rate_id]);
                $_SESSION['swal_msg'] = "Delivery rate rule updated successfully!";
            } else {
                // INSERT new rule
                $stmt = $pdo->prepare("
                    INSERT INTO geo_delivery_rates (method_title, country_id, province_id, district_id, shipping_type, cost, min_order_free, estimated_days, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$method_title, $country_id, $province_id, $district_id, $shipping_type, $cost, $min_order_free, $estimated_days]);
                $_SESSION['swal_msg'] = "New delivery rate rule saved!";
            }
            $_SESSION['swal_type'] = "success";
        } catch (Exception $e) {
            $_SESSION['swal_msg'] = "Error: " . $e->getMessage();
            $_SESSION['swal_type'] = "error";
        }
    }

    // 2. DELETE RATE RULE
    elseif ($_POST['action'] === 'delete_rate_rule') {
        $rate_id = (int)($_POST['rate_id'] ?? 0);
        if ($rate_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM geo_delivery_rates WHERE id = ?");
            $stmt->execute([$rate_id]);
            $_SESSION['swal_msg'] = "Delivery rule deleted!";
            $_SESSION['swal_type'] = "success";
        }
    }

    // 3. SAVE OR UPDATE WEIGHT RANGE (POPUP MODAL)
    elseif ($_POST['action'] === 'save_weight_rate') {
        $weight_id = !empty($_POST['weight_id']) ? (int)$_POST['weight_id'] : null;
        $label = trim(htmlspecialchars($_POST['label'] ?? '', ENT_QUOTES, 'UTF-8'));
        $min_weight = (float)($_POST['min_weight'] ?? 0);
        $max_weight = !empty($_POST['max_weight']) ? (float)$_POST['max_weight'] : null;
        $extra_cost = (float)($_POST['extra_cost'] ?? 0);

        try {
            if ($weight_id) {
                // UPDATE existing slab
                $stmt = $pdo->prepare("UPDATE geo_weight_rates SET min_weight=?, max_weight=?, extra_cost=?, label=? WHERE id=?");
                $stmt->execute([$min_weight, $max_weight, $extra_cost, $label, $weight_id]);
                $_SESSION['swal_msg'] = "Weight slab updated successfully!";
            } else {
                // INSERT new slab
                $stmt = $pdo->prepare("INSERT INTO geo_weight_rates (min_weight, max_weight, extra_cost, label, is_active) VALUES (?, ?, ?, ?, 1)");
                $stmt->execute([$min_weight, $max_weight, $extra_cost, $label]);
                $_SESSION['swal_msg'] = "Weight slab added successfully!";
            }
            $_SESSION['swal_type'] = "success";
        } catch (Exception $e) {
            $_SESSION['swal_msg'] = "Error: " . $e->getMessage();
            $_SESSION['swal_type'] = "error";
        }
    }

    // 4. DELETE WEIGHT RANGE
    elseif ($_POST['action'] === 'delete_weight_rate') {
        $weight_id = (int)($_POST['weight_id'] ?? 0);
        if ($weight_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM geo_weight_rates WHERE id = ?");
            $stmt->execute([$weight_id]);
            $_SESSION['swal_msg'] = "Weight range deleted!";
            $_SESSION['swal_type'] = "success";
        }
    }

    // 5. SAVE MANUAL PROVINCE / DISTRICT REGION
    elseif ($_POST['action'] === 'save_region') {
        $type = ($_POST['type'] ?? 'province') === 'district' ? 'district' : 'province';
        $parent_id = ($type === 'district') ? (int)($_POST['parent_id'] ?? 0) : 0;
        $name = trim(htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8'));

        if (!empty($name)) {
            $stmt = $pdo->prepare("INSERT INTO geo_regions (country_id, parent_id, type, name) VALUES (1, ?, ?, ?)");
            $stmt->execute([$parent_id, $type, $name]);
            $_SESSION['swal_msg'] = ucfirst($type) . " added successfully!";
            $_SESSION['swal_type'] = "success";
        }
    }

    header("Location: shipping_zones.php");
    exit;
}
?>