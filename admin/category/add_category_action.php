<?php
// Error Reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'shop_admin'])) {
    die("Access Denied");
}

require_once '../../config/db.php';

// WebP Image Compression Function
function compressAndSaveWebP($source, $destination, $quality = 80) {
    $info = getimagesize($source);
    if (!$info) return false;
    
    $mime = $info['mime'];
    if ($mime == 'image/jpeg') { $image = imagecreatefromjpeg($source); } 
    elseif ($mime == 'image/png') { 
        $image = imagecreatefrompng($source); 
        imagepalettetotruecolor($image); 
        imagealphablending($image, true); 
        imagesavealpha($image, true); 
    } 
    elseif ($mime == 'image/webp') { $image = imagecreatefromwebp($source); } 
    else { return false; }

    $result = imagewebp($image, $destination, $quality);
    imagedestroy($image);
    return $result;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    // ==========================================
    // ACTION: SAVE / UPDATE CATEGORY
    // ==========================================
    if ($_POST['action'] == 'save_category') {
        $id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $name = trim(htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8'));
        $slug = trim(htmlspecialchars($_POST['slug'] ?? '', ENT_QUOTES, 'UTF-8'));
        $parent_id = (int)($_POST['parent_id'] ?? 0);
        
        if (empty($slug)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        }

        $image_path = !empty($_POST['existing_image']) ? $_POST['existing_image'] : null;
        $remove_image = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';

        // 1. If user clicked remove and did not select a replacement image
        if ($remove_image && empty($_FILES['image']['name'])) {
            if (!empty($image_path) && file_exists("../../" . $image_path)) {
                @unlink("../../" . $image_path);
            }
            $image_path = null;
        }

        // 2. If a new image was uploaded
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $target_dir = "../../uploads/categories/";
            if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
            
            $new_filename = uniqid('cat_') . '.webp';
            $target_file = $target_dir . $new_filename;
            
            if (compressAndSaveWebP($_FILES["image"]["tmp_name"], $target_file)) {
                if (!empty($image_path) && file_exists("../../" . $image_path)) {
                    @unlink("../../" . $image_path);
                }
                $image_path = "uploads/categories/" . $new_filename;
            }
        }

        try {
            if (!empty($id)) {
                if ($id == $parent_id) { 
                    throw new Exception("A category cannot be its own parent."); 
                }
                
                $stmt = $pdo->prepare("UPDATE categories SET name=?, slug=?, parent_id=?, image=? WHERE id=?");
                $stmt->execute([$name, $slug, $parent_id, $image_path, $id]);
                $_SESSION['swal_msg'] = "Category updated successfully!";
                $_SESSION['swal_type'] = "success";
            } else {
                $stmt = $pdo->prepare("INSERT INTO categories (name, slug, parent_id, image) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $slug, $parent_id, $image_path]);
                $_SESSION['swal_msg'] = "New category added successfully!";
                $_SESSION['swal_type'] = "success";
            }
        } catch (PDOException $e) {
            $_SESSION['swal_msg'] = "Database Error: Slug may already exist.";
            $_SESSION['swal_type'] = "error";
        } catch (Exception $e) {
            $_SESSION['swal_msg'] = $e->getMessage();
            $_SESSION['swal_type'] = "error";
        }
        header("Location: add_category.php");
        exit;
    }

    // ==========================================
    // ACTION: DELETE CATEGORY
    // ==========================================
    if ($_POST['action'] == 'delete_category') {
        $delete_id = (int)$_POST['category_id'];
        try {
            // Check for associated products
            $check_prod = $pdo->prepare("SELECT COUNT(*) FROM product_categories WHERE category_id = ?");
            $check_prod->execute([$delete_id]);
            if ($check_prod->fetchColumn() > 0) {
                throw new Exception("Cannot delete: Products are assigned to this category.");
            }

            // Check for sub-categories
            $check_sub = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = ?");
            $check_sub->execute([$delete_id]);
            if ($check_sub->fetchColumn() > 0) {
                throw new Exception("Cannot delete: Delete its sub-categories first.");
            }

            // Delete Image
            $img_stmt = $pdo->prepare("SELECT image FROM categories WHERE id = ?");
            $img_stmt->execute([$delete_id]);
            $img = $img_stmt->fetchColumn();

            // Delete Record
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$delete_id]);

            if ($img && file_exists("../../" . $img)) { 
                @unlink("../../" . $img); 
            }

            $_SESSION['swal_msg'] = "Category deleted safely.";
            $_SESSION['swal_type'] = "success";
        } catch (Exception $e) {
            $_SESSION['swal_msg'] = $e->getMessage();
            $_SESSION['swal_type'] = "error";
        }
        header("Location: add_category.php");
        exit;
    }
}
?>