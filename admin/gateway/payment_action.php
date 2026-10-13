<?php
// ERROR REPORTING ON
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'shop_admin'])) { die("Access Denied"); }

require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. SWITCH TOGGLE FROM CONTROLLER LIST
    if ($action == 'toggle_status') {
        $id = (int)$_POST['id'];
        $current_status = (int)$_POST['current_status'];
        $new_status = $current_status === 1 ? 0 : 1;

        try {
            $stmt = $pdo->prepare("UPDATE payment_gateways SET is_active = ? WHERE id = ?");
            $stmt->execute([$new_status, $id]);
            $_SESSION['swal_msg'] = "Gateway status changed!";
            $_SESSION['swal_type'] = "success";
        } catch (Exception $e) {
            $_SESSION['swal_msg'] = "Error changing status.";
            $_SESSION['swal_type'] = "error";
        }
        header("Location: payment_controller.php");
        exit;
    }

    // 2. SAVE PAYHERE KEYS & SETTINGS
    if ($action == 'save_payhere') {
        $id = (int)($_POST['id'] ?? 0);
        $display_name = trim(htmlspecialchars($_POST['display_name'] ?? 'Pay Online (Card / PayHere)', ENT_QUOTES, 'UTF-8'));
        $environment = in_array($_POST['environment'] ?? '', ['sandbox', 'live']) ? $_POST['environment'] : 'sandbox';
        $merchant_id = trim($_POST['merchant_id'] ?? '');
        $merchant_secret = trim($_POST['merchant_secret'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        try {
            $sql = "UPDATE payment_gateways SET 
                    display_name = ?, 
                    environment = ?, 
                    merchant_id = ?, 
                    merchant_secret = ?, 
                    is_active = ? 
                    WHERE gateway_id = 'payhere'";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $display_name, 
                $environment, 
                $merchant_id, 
                $merchant_secret, 
                $is_active
            ]);

            $_SESSION['swal_msg'] = "PayHere settings updated successfully!";
            $_SESSION['swal_type'] = "success";
        } catch (Exception $e) {
            $_SESSION['swal_msg'] = "Database error: " . $e->getMessage();
            $_SESSION['swal_type'] = "error";
        }

        header("Location: payhere_config.php");
        exit;
    }

    // 3. SAVE / UPDATE BANK ACCOUNT
    if ($action == 'save_bank_account') {
        $acc_id       = (int)($_POST['account_id'] ?? 0);
        $bank_name    = trim(htmlspecialchars($_POST['bank_name'] ?? '', ENT_QUOTES, 'UTF-8'));
        $account_name = trim(htmlspecialchars($_POST['account_name'] ?? '', ENT_QUOTES, 'UTF-8'));
        $account_num  = trim(htmlspecialchars($_POST['account_number'] ?? '', ENT_QUOTES, 'UTF-8'));
        $branch_name  = trim(htmlspecialchars($_POST['branch_name'] ?? '', ENT_QUOTES, 'UTF-8'));
        $instructions = trim(htmlspecialchars($_POST['instructions'] ?? '', ENT_QUOTES, 'UTF-8'));

        if (empty($bank_name) || empty($account_name) || empty($account_num) || empty($branch_name)) {
            $_SESSION['swal_msg'] = "Please fill in all required fields.";
            $_SESSION['swal_type'] = "error";
            header("Location: bank_config.php");
            exit;
        }

        try {
            if ($acc_id > 0) {
                $u_stmt = $pdo->prepare("UPDATE bank_accounts SET bank_name=?, account_name=?, account_number=?, branch_name=?, instructions=? WHERE id=?");
                $u_stmt->execute([$bank_name, $account_name, $account_num, $branch_name, $instructions, $acc_id]);
                $_SESSION['swal_msg'] = "Bank account updated successfully!";
            } else {
                $i_stmt = $pdo->prepare("INSERT INTO bank_accounts (bank_name, account_name, account_number, branch_name, instructions, is_active) VALUES (?, ?, ?, ?, ?, 1)");
                $i_stmt->execute([$bank_name, $account_name, $account_num, $branch_name, $instructions]);
                $_SESSION['swal_msg'] = "New bank account added successfully!";
            }
            $_SESSION['swal_type'] = "success";
        } catch (Exception $e) {
            $_SESSION['swal_msg'] = "Database error: " . $e->getMessage();
            $_SESSION['swal_type'] = "error";
        }

        header("Location: bank_config.php");
        exit;
    }

    // 4. DELETE BANK ACCOUNT
    if ($action == 'delete_bank_account') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $del = $pdo->prepare("DELETE FROM bank_accounts WHERE id = ?");
                $del->execute([$id]);
                $_SESSION['swal_msg'] = "Bank account deleted successfully!";
                $_SESSION['swal_type'] = "success";
            } catch (Exception $e) {
                $_SESSION['swal_msg'] = "Database error: " . $e->getMessage();
                $_SESSION['swal_type'] = "error";
            }
        }
        header("Location: bank_config.php");
        exit;
    }
}
?>