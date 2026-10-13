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
    $action = $_POST['action'];

    if ($action == 'add_category' || $action == 'update_category') {
        $cat_id = (int)($_POST['category_id'] ?? 0);
        $name = trim(htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8'));
        $slug = trim(htmlspecialchars($_POST['slug'] ?? '', ENT_QUOTES, 'UTF-8'));
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;
        $description = trim(htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8'));

        if (empty($slug)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        }

        if (!empty($name)) {
            try {
                if ($action == 'add_category') {
                    $stmt = $pdo->prepare("INSERT INTO blog_categories (name, slug, parent_id, description) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $slug, $parent_id, $description]);
                    $_SESSION['swal_msg'] = "Blog Category added successfully!";
                } else {
                    // Prevent setting category as its own parent
                    if ($cat_id === $parent_id) { $parent_id = 0; }
                    $stmt = $pdo->prepare("UPDATE blog_categories SET name=?, slug=?, parent_id=?, description=? WHERE id=?");
                    $stmt->execute([$name, $slug, $parent_id, $description, $cat_id]);
                    $_SESSION['swal_msg'] = "Blog Category updated successfully!";
                }
                $_SESSION['swal_type'] = "success";
            } catch (PDOException $e) {
                $_SESSION['swal_msg'] = "Error: Slug might already exist.";
                $_SESSION['swal_type'] = "error";
            }
        }
    } elseif ($action == 'delete_category') {
        $cat_id = (int)$_POST['category_id'];
        try {
            $pdo->prepare("UPDATE blog_categories SET parent_id = 0 WHERE parent_id = ?")->execute([$cat_id]);
            $pdo->prepare("DELETE FROM blog_post_categories WHERE category_id = ?")->execute([$cat_id]);
            $pdo->prepare("DELETE FROM blog_categories WHERE id = ?")->execute([$cat_id]);
            $_SESSION['swal_msg'] = "Category deleted successfully!";
            $_SESSION['swal_type'] = "success";
        } catch (Exception $e) {
            $_SESSION['swal_msg'] = "Failed to delete category.";
            $_SESSION['swal_type'] = "error";
        }
    }

    header("Location: blog_categories.php");
    exit;
}
?>