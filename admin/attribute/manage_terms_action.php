<?php
// 🔴 ERROR REPORTING ON 🔴
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Check: Admin Only
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    die("Access Denied: Super Admin Privileges Required.");
}

require_once '../../config/db.php';

// ==========================================
// HANDLE POST ACTIONS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    $attr_id = (int)($_POST['attr_id'] ?? 0);
    
    // ACTION: SAVE TERM
    if ($_POST['action'] == 'save_term') {
        $term_id = $_POST['term_id'] ?? '';
        $name = trim($_POST['name']);
        $slug = trim($_POST['slug']);
        $color_code = !empty($_POST['color_code']) ? $_POST['color_code'] : null;
        
        if (empty($slug)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        }

        try {
            if (!empty($term_id)) {
                $stmt = $pdo->prepare("UPDATE attribute_terms SET name=?, slug=?, color_code=? WHERE id=? AND attribute_id=?");
                $stmt->execute([$name, $slug, $color_code, $term_id, $attr_id]);
                $_SESSION['swal_msg'] = "Term updated successfully!";
                $_SESSION['swal_type'] = "success";
            } else {
                $stmt = $pdo->prepare("INSERT INTO attribute_terms (attribute_id, name, slug, color_code) VALUES (?, ?, ?, ?)");
                $stmt->execute([$attr_id, $name, $slug, $color_code]);
                $_SESSION['swal_msg'] = "New term added successfully!";
                $_SESSION['swal_type'] = "success";
            }
        } catch (PDOException $e) {
            $_SESSION['swal_msg'] = "Database Error: Term slug may already exist.";
            $_SESSION['swal_type'] = "error";
        } catch (Exception $e) {
            $_SESSION['swal_msg'] = "An error occurred.";
            $_SESSION['swal_type'] = "error";
        }
        header("Location: manage_terms.php?attr_id=" . $attr_id);
        exit;
    }

    // ACTION: DELETE TERM
    if ($_POST['action'] == 'delete_term') {
        $delete_id = (int)$_POST['term_id'];
        try {
            // Note: In a full system, you'd check if this term is assigned to products (product_attributes table)
            // before allowing deletion. Assuming basic deletion for now as per original code.
            
            $stmt = $pdo->prepare("DELETE FROM attribute_terms WHERE id = ? AND attribute_id = ?");
            $stmt->execute([$delete_id, $attr_id]);

            $_SESSION['swal_msg'] = "Term deleted safely.";
            $_SESSION['swal_type'] = "success";
        } catch (Exception $e) {
            $_SESSION['swal_msg'] = "Cannot delete this term.";
            $_SESSION['swal_type'] = "error";
        }
        header("Location: manage_terms.php?attr_id=" . $attr_id);
        exit;
    }
}
?>