<?php
// 🔴 ERROR REPORTING ON 🔴
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'shop_admin'])) { die("Access Denied"); }

require_once '../../config/db.php';

// WebP Compression Function
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
    
    // --- 1. SAVE OR UPDATE POST ---
    if ($_POST['action'] == 'save_post') {
        $post_id = !empty($_POST['post_id']) ? (int)$_POST['post_id'] : null;
        $title = trim(htmlspecialchars($_POST['title'] ?? '', ENT_QUOTES, 'UTF-8'));
        $slug = trim(htmlspecialchars($_POST['slug'] ?? '', ENT_QUOTES, 'UTF-8'));
        $content = trim($_POST['content'] ?? ''); // Allow HTML from Editor
        $excerpt = trim(htmlspecialchars($_POST['excerpt'] ?? '', ENT_QUOTES, 'UTF-8'));
        $status = isset($_POST['publish']) ? 'published' : 'draft';
        $published_at = !empty($_POST['published_at']) ? $_POST['published_at'] : date('Y-m-d H:i:s');
        $tags = trim(htmlspecialchars($_POST['tags'] ?? '', ENT_QUOTES, 'UTF-8'));
        $seo_title = trim(htmlspecialchars($_POST['seo_title'] ?? '', ENT_QUOTES, 'UTF-8'));
        $seo_desc = trim(htmlspecialchars($_POST['seo_desc'] ?? '', ENT_QUOTES, 'UTF-8'));
        $author_id = $_SESSION['user_id'];

        if (empty($slug)) { $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title))); }

        try {
            $pdo->beginTransaction();

            // Image Upload
            $featured_image = $_POST['existing_image'] ?? null;
            if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] == 0) {
                $target_dir = "../../uploads/blog/";
                if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
                $new_filename = uniqid('blog_') . '.webp';
                if (compressAndSaveWebP($_FILES["featured_image"]["tmp_name"], $target_dir . $new_filename)) {
                    if ($featured_image && file_exists("../../" . $featured_image)) { unlink("../../" . $featured_image); }
                    $featured_image = "uploads/blog/" . $new_filename;
                }
            }

            if ($post_id) {
                $sql = "UPDATE blog_posts SET title=?, slug=?, content=?, excerpt=?, featured_image=?, status=?, published_at=?, seo_title=?, seo_desc=?, tags=? WHERE id=?";
                $pdo->prepare($sql)->execute([$title, $slug, $content, $excerpt, $featured_image, $status, $published_at, $seo_title, $seo_desc, $tags, $post_id]);
                $_SESSION['swal_msg'] = "Post updated successfully!";
            } else {
                $sql = "INSERT INTO blog_posts (title, slug, content, excerpt, featured_image, status, author_id, published_at, seo_title, seo_desc, tags) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $pdo->prepare($sql)->execute([$title, $slug, $content, $excerpt, $featured_image, $status, $author_id, $published_at, $seo_title, $seo_desc, $tags]);
                $post_id = $pdo->lastInsertId();
                $_SESSION['swal_msg'] = "Post created successfully!";
            }

            // Sync Categories
            $pdo->prepare("DELETE FROM blog_post_categories WHERE post_id = ?")->execute([$post_id]);
            if (!empty($_POST['categories'])) {
                $cat_stmt = $pdo->prepare("INSERT INTO blog_post_categories (post_id, category_id) VALUES (?, ?)");
                foreach ($_POST['categories'] as $cat_id) { $cat_stmt->execute([$post_id, (int)$cat_id]); }
            }

            $pdo->commit();
            $_SESSION['swal_type'] = "success";
            header("Location: add_post.php?edit=" . $post_id);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['swal_msg'] = "Error saving post. Slug might already exist.";
            $_SESSION['swal_type'] = "error";
            header("Location: add_post.php" . ($post_id ? "?edit=".$post_id : ""));
            exit;
        }
    }

    // --- 2. DELETE POST ---
    elseif ($_POST['action'] == 'delete_post') {
        $post_id = (int)$_POST['post_id'];
        
        try {
            // Get image to delete from server
            $stmt = $pdo->prepare("SELECT featured_image FROM blog_posts WHERE id = ?");
            $stmt->execute([$post_id]);
            $post = $stmt->fetch();

            if ($post && $post['featured_image'] && file_exists("../../" . $post['featured_image'])) {
                unlink("../../" . $post['featured_image']);
            }

            $pdo->prepare("DELETE FROM blog_posts WHERE id = ?")->execute([$post_id]);
            
            $_SESSION['swal_msg'] = "Blog post deleted successfully!";
            $_SESSION['swal_type'] = "success";
        } catch (Exception $e) {
            $_SESSION['swal_msg'] = "Failed to delete post.";
            $_SESSION['swal_type'] = "error";
        }
        
        header("Location: blog_posts.php");
        exit;
    }
}
?>