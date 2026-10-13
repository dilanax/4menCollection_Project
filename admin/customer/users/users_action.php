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

require_once '../../../config/db.php'; 

$current_user_id = (int)$_SESSION['user_id'];

// ==========================================
// HANDLE POST ACTIONS (CRUD)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. ADD NEW USER
    if ($action == 'add_user') {
        $username = trim(htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8'));
        $email = trim(filter_var($_POST['email'], FILTER_SANITIZE_EMAIL));
        $password = $_POST['password'];
        $role = $_POST['user_role'];

        if (!empty($username) && !empty($email) && !empty($password) && in_array($role, ['admin', 'shop_admin', 'customer'])) {
            try {
                // Check if email or username already exists
                $check = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
                $check->execute([$email, $username]);
                
                if ($check->rowCount() > 0) {
                    $_SESSION['swal_msg'] = "Email or Username already exists!";
                    $_SESSION['swal_type'] = "error";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, user_role) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$username, $email, $hashed_password, $role]);
                    
                    $_SESSION['swal_msg'] = "User added successfully!";
                    $_SESSION['swal_type'] = "success";
                }
            } catch (Exception $e) {
                $_SESSION['swal_msg'] = "Database Error.";
                $_SESSION['swal_type'] = "error";
            }
        } else {
            $_SESSION['swal_msg'] = "Please fill all fields correctly.";
            $_SESSION['swal_type'] = "error";
        }
    }

    // 2. EDIT USER DETAILS
    elseif ($action == 'edit_user') {
        $edit_id = (int)$_POST['user_id'];
        $username = trim(htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8'));
        $email = trim(filter_var($_POST['email'], FILTER_SANITIZE_EMAIL));
        $role = $_POST['user_role'];

        if ($edit_id > 0 && !empty($username) && !empty($email) && in_array($role, ['admin', 'shop_admin', 'customer'])) {
            // Prevent changing role of the currently logged in admin accidentally
            if ($edit_id == $current_user_id && $role !== 'admin') {
                $_SESSION['swal_msg'] = "You cannot change your own admin role!";
                $_SESSION['swal_type'] = "error";
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, user_role = ? WHERE id = ?");
                    $stmt->execute([$username, $email, $role, $edit_id]);
                    $_SESSION['swal_msg'] = "User details updated successfully!";
                    $_SESSION['swal_type'] = "success";
                } catch (Exception $e) {
                    $_SESSION['swal_msg'] = "Failed to update user. Email/Username may exist.";
                    $_SESSION['swal_type'] = "error";
                }
            }
        }
    }

    // 3. RESET PASSWORD
    elseif ($action == 'reset_password') {
        $reset_id = (int)$_POST['user_id'];
        $new_password = $_POST['new_password'];

        if ($reset_id > 0 && !empty($new_password) && strlen($new_password) >= 6) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashed_password, $reset_id])) {
                $_SESSION['swal_msg'] = "Password reset successfully!";
                $_SESSION['swal_type'] = "success";
            } else {
                $_SESSION['swal_msg'] = "Failed to reset password.";
                $_SESSION['swal_type'] = "error";
            }
        } else {
            $_SESSION['swal_msg'] = "Password must be at least 6 characters long.";
            $_SESSION['swal_type'] = "error";
        }
    }

    // 4. DELETE USER
    elseif ($action == 'delete_user') {
        $delete_id = (int)$_POST['user_id'];
        
        if ($delete_id == $current_user_id) {
            $_SESSION['swal_msg'] = "You cannot delete your own account!";
            $_SESSION['swal_type'] = "error";
        } else {
            try {
                // Note: In a real system, you might want to reassign their orders or soft-delete them instead
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$delete_id]);
                $_SESSION['swal_msg'] = "User deleted successfully!";
                $_SESSION['swal_type'] = "success";
            } catch (Exception $e) {
                $_SESSION['swal_msg'] = "Cannot delete this user. They may have existing orders.";
                $_SESSION['swal_type'] = "error";
            }
        }
    }

    // Redirect back to users page preserving filters
    $redirect_url = "users.php";
    $query_string = http_build_query(array_diff_key($_GET, ['_' => '']));
    if(!empty($query_string)) {
        $redirect_url .= '?' . $query_string;
    }

    header("Location: " . $redirect_url);
    exit;
}
?>