<?php
// 🔴 ERROR REPORTING ON 🔴
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. STRICT SECURITY: Admin Only Access
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    die("Access Denied: Super Admin Privileges Required.");
}

require_once '../../config/db.php';

// ==========================================
// HANDLE POST ACTIONS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    // ACTION: SAVE ATTRIBUTE
    if ($_POST['action'] == 'save_attribute') {
        $id = $_POST['attribute_id'] ?? '';
        $name = trim($_POST['name']);
        $slug = trim($_POST['slug']);
        $type = $_POST['type'];
        
        // Slug එක හිස් නම් Title එකෙන් හදන්න
        if (empty($slug)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        }

        try {
            if (!empty($id)) {
                // Update Attribute
                $stmt = $pdo->prepare("UPDATE attributes SET name=?, slug=?, type=? WHERE id=?");
                $stmt->execute([$name, $slug, $type, $id]);
                $_SESSION['swal_msg'] = "Attribute updated successfully!";
                $_SESSION['swal_type'] = "success";
            } else {
                // Insert New Attribute
                $stmt = $pdo->prepare("INSERT INTO attributes (name, slug, type) VALUES (?, ?, ?)");
                $stmt->execute([$name, $slug, $type]);
                $_SESSION['swal_msg'] = "New attribute added successfully!";
                $_SESSION['swal_type'] = "success";
            }
        } catch (PDOException $e) {
            $_SESSION['swal_msg'] = "Database Error: Slug may already exist.";
            $_SESSION['swal_type'] = "error";
        } catch (Exception $e) {
            $_SESSION['swal_msg'] = $e->getMessage();
            $_SESSION['swal_type'] = "error";
        }
        header("Location: add_attribute.php");
        exit;
    }

    // ACTION: DELETE ATTRIBUTE
    if ($_POST['action'] == 'delete_attribute') {
        $delete_id = (int)$_POST['attribute_id'];
        try {
            // SAFE DELETE: Check if terms exist for this attribute
            $check_terms = $pdo->prepare("SELECT COUNT(*) FROM attribute_terms WHERE attribute_id = ?");
            $check_terms->execute([$delete_id]);
            if ($check_terms->fetchColumn() > 0) {
                throw new Exception("Cannot delete: Please delete all configured terms for this attribute first.");
            }

            // Delete Record
            $stmt = $pdo->prepare("DELETE FROM attributes WHERE id = ?");
            $stmt->execute([$delete_id]);

            $_SESSION['swal_msg'] = "Attribute deleted safely.";
            $_SESSION['swal_type'] = "success";
        } catch (Exception $e) {
            $_SESSION['swal_msg'] = $e->getMessage();
            $_SESSION['swal_type'] = "error";
        }
        header("Location: add_attribute.php");
        exit;
    }
}
?>