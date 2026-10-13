<?php
// Error Reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Security Check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'shop_admin'])) {
    die("Access Denied");
}

require_once '../../config/db.php';

// ==========================================
// IMAGE COMPRESSION TO WEBP FUNCTION
// ==========================================
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

// ==========================================
// FORM SUBMISSION HANDLING (INSERT)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['publish']) || isset($_POST['save_draft']))) {
    
    // --- 1. Basic Info (Sanitized) ---
    $name = trim(htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8'));
    $slug = trim(htmlspecialchars($_POST['slug'] ?? '', ENT_QUOTES, 'UTF-8'));
    $description = trim($_POST['description'] ?? ''); 
    $short_description = trim(htmlspecialchars($_POST['short_description'] ?? '', ENT_QUOTES, 'UTF-8'));
    $type = htmlspecialchars($_POST['product_type'] ?? 'simple', ENT_QUOTES, 'UTF-8');
    $brand_id = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
    $status = isset($_POST['publish']) ? 'published' : 'draft';
    $tags = trim(htmlspecialchars($_POST['tags'] ?? '', ENT_QUOTES, 'UTF-8'));
    $video_url = filter_var(trim($_POST['video_url'] ?? ''), FILTER_SANITIZE_URL);
    $tax_status = htmlspecialchars($_POST['tax_status'] ?? 'none', ENT_QUOTES, 'UTF-8');
    
    $publish_date = !empty($_POST['publish_date']) ? $_POST['publish_date'] : date('Y-m-d H:i:s');

    // --- 2. Pricing & Inventory ---
    $regular_price = !empty($_POST['regular_price']) ? (float)$_POST['regular_price'] : null;
    $sale_price = !empty($_POST['sale_price']) ? (float)$_POST['sale_price'] : null;
    $sale_price_dates_from = !empty($_POST['sale_price_dates_from']) ? $_POST['sale_price_dates_from'] : null;
    $sale_price_dates_to = !empty($_POST['sale_price_dates_to']) ? $_POST['sale_price_dates_to'] : null;
    
    $sku = trim(htmlspecialchars($_POST['sku'] ?? '', ENT_QUOTES, 'UTF-8'));
    $manage_stock = isset($_POST['manage_stock']) ? 1 : 0;
    $stock_quantity = !empty($_POST['stock_quantity']) ? (int)$_POST['stock_quantity'] : null;
    $stock_status = htmlspecialchars($_POST['stock_status'] ?? 'instock', ENT_QUOTES, 'UTF-8');
    $min_purchase_qty = !empty($_POST['min_purchase_qty']) ? (int)$_POST['min_purchase_qty'] : 1;
    $max_purchase_qty = !empty($_POST['max_purchase_qty']) ? (int)$_POST['max_purchase_qty'] : null;

    // --- 3. Shipping ---
    $weight = !empty($_POST['weight']) ? (float)$_POST['weight'] : null;
    $length = !empty($_POST['length']) ? (float)$_POST['length'] : null;
    $width = !empty($_POST['width']) ? (float)$_POST['width'] : null;
    $height = !empty($_POST['height']) ? (float)$_POST['height'] : null;
    $shipping_class_id = !empty($_POST['shipping_class_id']) ? (int)$_POST['shipping_class_id'] : null;

    // --- 4. SEO ---
    $meta_title = trim(htmlspecialchars($_POST['meta_title'] ?? '', ENT_QUOTES, 'UTF-8'));
    $meta_description = trim(htmlspecialchars($_POST['meta_description'] ?? '', ENT_QUOTES, 'UTF-8'));
    $focus_keywords = trim(htmlspecialchars($_POST['focus_keywords'] ?? '', ENT_QUOTES, 'UTF-8'));

    // --- 5. Linked Products ---
    $upsell_ids = !empty($_POST['upsell_ids']) ? implode(',', array_map('intval', $_POST['upsell_ids'])) : null;
    $crosssell_ids = !empty($_POST['crosssell_ids']) ? implode(',', array_map('intval', $_POST['crosssell_ids'])) : null;

    // --- 6. Downloadable ---
    $is_downloadable = isset($_POST['is_downloadable']) ? 1 : 0;
    $download_limit = !empty($_POST['download_limit']) ? (int)$_POST['download_limit'] : null;

    // Auto-generate slug if empty
    if (empty($slug)) { $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name))); }

    try {
        $pdo->beginTransaction();

        // --- File Uploads (Main Image) ---
        $main_image_path = null;
        if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] == 0) {
            $target_dir = "../../uploads/products/";
            if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
            $new_filename = uniqid('prod_') . '.webp';
            $target_file = $target_dir . $new_filename;
            
            if (compressAndSaveWebP($_FILES["main_image"]["tmp_name"], $target_file)) {
                $main_image_path = "uploads/products/" . $new_filename;
            }
        }

        // --- File Uploads (Downloadable File) ---
        $download_file_path = null;
        if ($is_downloadable && isset($_FILES['download_file']) && $_FILES['download_file']['error'] == 0) {
            $dl_target_dir = "../../uploads/downloads/";
            if (!file_exists($dl_target_dir)) { mkdir($dl_target_dir, 0777, true); }
            $dl_ext = strtolower(pathinfo($_FILES["download_file"]["name"], PATHINFO_EXTENSION));
            $dl_new_filename = uniqid('dl_') . '.' . $dl_ext;
            if (move_uploaded_file($_FILES["download_file"]["tmp_name"], $dl_target_dir . $dl_new_filename)) {
                $download_file_path = "uploads/downloads/" . $dl_new_filename;
            }
        }

        // --- Insert Product into Database ---
        $sql = "INSERT INTO products (
                    name, slug, description, short_description, type, regular_price, sale_price, 
                    sale_price_dates_from, sale_price_dates_to, sku, manage_stock, stock_quantity, stock_status, 
                    min_purchase_qty, max_purchase_qty, brand_id, main_image, video_url, status, publish_date, tags, tax_status,
                    weight, length, width, height, shipping_class_id, meta_title, meta_description, focus_keywords,
                    is_downloadable, download_file, download_limit, upsell_ids, crosssell_ids
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $name, $slug, $description, $short_description, $type, $regular_price, $sale_price, 
            $sale_price_dates_from, $sale_price_dates_to, $sku, $manage_stock, $stock_quantity, $stock_status, 
            $min_purchase_qty, $max_purchase_qty, $brand_id, $main_image_path, $video_url, $status, $publish_date, $tags, $tax_status,
            $weight, $length, $width, $height, $shipping_class_id, $meta_title, $meta_description, $focus_keywords,
            $is_downloadable, $download_file_path, $download_limit, $upsell_ids, $crosssell_ids
        ]);
        
        $product_id = $pdo->lastInsertId();

        // --- Categories ---
        if (!empty($_POST['categories'])) {
            $cat_stmt = $pdo->prepare("INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)");
            foreach ($_POST['categories'] as $cat_id) { 
                $cat_stmt->execute([$product_id, (int)$cat_id]); 
            }
        }

        // =========================================================================
        // --- FIX: Insert Attributes for ALL product types (Simple & Variable) ---
        // =========================================================================
        if (!empty($_POST['attributes']) && is_array($_POST['attributes'])) {
            $attr_stmt = $pdo->prepare("INSERT INTO product_attributes (product_id, attribute_id, terms_json) VALUES (?, ?, ?)");
            foreach ($_POST['attributes'] as $attr_id => $terms) {
                if (!empty($terms) && is_array($terms)) { 
                    $clean_terms = array_values(array_filter(array_map('trim', $terms)));
                    if (!empty($clean_terms)) {
                        $attr_stmt->execute([
                            $product_id, 
                            (int)$attr_id, 
                            json_encode($clean_terms, JSON_UNESCAPED_UNICODE)
                        ]); 
                    }
                }
            }
        }

        // --- Variations (ONLY for Variable Products) ---
        if ($type === 'variable' && !empty($_POST['var_name'])) {
            $var_stmt = $pdo->prepare("INSERT INTO product_variations (product_id, variation_name, sku, image, regular_price, sale_price, stock_quantity) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($_POST['var_name'] as $index => $var_name) {
                $clean_var_name = trim($var_name);
                $v_price = !empty($_POST['var_regular_price'][$index]) ? (float)$_POST['var_regular_price'][$index] : 0;
                $v_sale = !empty($_POST['var_sale_price'][$index]) && (float)$_POST['var_sale_price'][$index] > 0 ? (float)$_POST['var_sale_price'][$index] : null;
                $v_sku = !empty($_POST['var_sku'][$index]) ? trim($_POST['var_sku'][$index]) : null;
                $v_stock = isset($_POST['var_stock'][$index]) && $_POST['var_stock'][$index] !== '' ? (int)$_POST['var_stock'][$index] : null;
                
                $v_image_path = null;
                if (isset($_FILES['var_image']['error'][$index]) && $_FILES['var_image']['error'][$index] == 0) {
                    $v_new_filename = uniqid('var_') . '_' . $index . '.webp';
                    $v_target_file = "../../uploads/products/" . $v_new_filename;
                    if (compressAndSaveWebP($_FILES["var_image"]["tmp_name"][$index], $v_target_file)) {
                        $v_image_path = "uploads/products/" . $v_new_filename;
                    }
                }
                $var_stmt->execute([$product_id, $clean_var_name, $v_sku, $v_image_path, $v_price, $v_sale, $v_stock]);
            }
        }

        // --- Gallery Upload (WebP Compression) ---
        $gallery_paths = [];
        if (isset($_FILES['gallery_images']) && !empty($_FILES['gallery_images']['name'][0])) {
            foreach ($_FILES['gallery_images']['name'] as $key => $filename) {
                if ($_FILES['gallery_images']['error'][$key] == 0) {
                    $g_new_name = uniqid('gal_') . '_' . $key . '.webp';
                    $g_target_file = "../../uploads/products/" . $g_new_name;
                    if (compressAndSaveWebP($_FILES['gallery_images']['tmp_name'][$key], $g_target_file)) {
                        $gallery_paths[] = "uploads/products/" . $g_new_name;
                    }
                }
            }
            if (!empty($gallery_paths)) {
                $pdo->prepare("UPDATE products SET gallery = ? WHERE id = ?")->execute([json_encode($gallery_paths), $product_id]);
            }
        }

        $pdo->commit();
        $_SESSION['swal_msg'] = "Product created successfully!";
        $_SESSION['swal_type'] = "success";

    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['swal_msg'] = "Database error: " . $e->getMessage();
        $_SESSION['swal_type'] = "error";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['swal_msg'] = "Error: " . $e->getMessage();
        $_SESSION['swal_type'] = "error";
    }
    
    header("Location: add_product.php");
    exit;
}
?>