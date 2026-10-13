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
// ZOHO SMTP PRE-ORDER RESTOCK EMAIL FUNCTION
// ==========================================
function sendRestockNotificationEmail($pdo, $toEmail, $customerName, $bookTitle, $bookUrl) {
    if (empty($toEmail)) return false;

    $stmt = $pdo->query("SELECT * FROM email_settings WHERE id = 1 LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$settings || empty($settings['smtp_email']) || empty($settings['smtp_app_token'])) {
        return false;
    }

    $business_name = $settings['business_name'] ?: 'ReadersHub';
    $subject = "Back in Stock: '{$bookTitle}' is Now Available! - {$business_name}";

    $htmlContent = "
        <p>Hello <strong>" . htmlspecialchars($customerName) . "</strong>,</p>
        <p>Great news! The title you pre-ordered / requested, <strong>\"" . htmlspecialchars($bookTitle) . "\"</strong>, is now back in stock and ready for courier delivery!</p>
        <div style='margin: 24px 0;'>
            <a href='{$bookUrl}' style='background:#0369a1; color:#ffffff; padding:12px 24px; border-radius:10px; text-decoration:none; font-weight:bold; font-size:13px; display:inline-block;'>Order Your Copy Now &rarr;</a>
        </div>
        <p style='font-size:12px; color:#64748b; line-height:1.5;'>Copies on this batch may be limited. Place your order soon to secure your book before it sells out again.</p>
    ";

    $fullBody = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 20px; color: #1e293b; }
            .email-wrapper { max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; }
            .email-header { background: #0369a1; padding: 24px; text-align: center; color: #ffffff; }
            .email-body { padding: 26px 24px; font-size: 13px; line-height: 1.6; color: #334155; }
            .email-footer { background: #f8fafc; padding: 16px; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #f1f5f9; }
        </style>
    </head>
    <body>
        <div class="email-wrapper">
            <div class="email-header">
                <h2 style="margin: 0; font-size: 19px; font-weight: 800;">' . htmlspecialchars($business_name) . '</h2>
            </div>
            <div class="email-body">
                ' . $htmlContent . '
            </div>
            <div class="email-footer">
                <p style="margin: 0 0 4px;">' . htmlspecialchars($business_name) . ' | ' . htmlspecialchars($settings['phone_number'] ?? '') . '</p>
                <p style="margin: 0;">WhatsApp: ' . htmlspecialchars($settings['whatsapp_number'] ?? '') . '</p>
            </div>
        </div>
    </body>
    </html>';

    $host = $settings['smtp_host'] ?: 'smtp.zoho.com';
    $port = (int)($settings['smtp_port'] ?: 465);
    $username = trim($settings['smtp_email']);
    $password = trim($settings['smtp_app_token']);
    $fromName = $settings['from_name'] ?: 'ReadersHub';

    $headers = [
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <" . $username . ">",
        "To: <" . $toEmail . ">",
        "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
        "Date: " . date("r")
    ];
    $emailData = implode("\r\n", $headers) . "\r\n\r\n" . $fullBody;

    if (function_exists('curl_init')) {
        $ch = curl_init();
        $scheme = ($port == 465) ? "smtps" : "smtp";
        curl_setopt($ch, CURLOPT_URL, "{$scheme}://{$host}:{$port}");
        curl_setopt($ch, CURLOPT_USERNAME, $username);
        curl_setopt($ch, CURLOPT_PASSWORD, $password);
        curl_setopt($ch, CURLOPT_MAIL_FROM, "<{$username}>");
        curl_setopt($ch, CURLOPT_MAIL_RCPT, ["<{$toEmail}>"]);
        curl_setopt($ch, CURLOPT_USE_SSL, CURLUSESSL_ALL);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);

        $temp = fopen('php://memory', 'r+');
        fwrite($temp, $emailData);
        rewind($temp);
        curl_setopt($ch, CURLOPT_INFILE, $temp);
        curl_setopt($ch, CURLOPT_UPLOAD, true);

        $res = curl_exec($ch);
        fclose($temp);
        curl_close($ch);
        return (bool)$res;
    }
    return false;
}

// ==========================================
// FORM SUBMISSION HANDLING (UPDATE)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['publish']) || isset($_POST['save_draft']))) {
    
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    if (!$product_id) {
        die("Invalid Product ID.");
    }

    // --- 1. Basic Info ---
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

    if (empty($slug)) { $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name))); }

    try {
        $pdo->beginTransaction();

        // --- Cover (Main) Image Handling with Explicit Removal Support ---
        $main_image_path = $_POST['existing_main_image'] ?? null;
        $remove_main_image = isset($_POST['remove_main_image']) && $_POST['remove_main_image'] === '1';

        // 1. If user explicitly clicked Remove and did not pick a new file
        if ($remove_main_image && empty($_FILES['main_image']['name'])) {
            if (!empty($main_image_path) && file_exists("../../" . $main_image_path)) {
                @unlink("../../" . $main_image_path);
            }
            $main_image_path = null;
        }

        // 2. If a new main image was uploaded
        if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] == 0) {
            $target_dir = "../../uploads/products/";
            if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
            $new_filename = uniqid('prod_') . '.webp';
            $target_file = $target_dir . $new_filename;
            
            if (compressAndSaveWebP($_FILES["main_image"]["tmp_name"], $target_file)) {
                if (!empty($main_image_path) && file_exists("../../" . $main_image_path)) {
                    @unlink("../../" . $main_image_path);
                }
                $main_image_path = "uploads/products/" . $new_filename;
            }
        }

        // --- Downloadable File Handling ---
        $download_file_path = $_POST['existing_download_file'] ?? null;
        if ($is_downloadable && isset($_FILES['download_file']) && $_FILES['download_file']['error'] == 0) {
            $dl_target_dir = "../../uploads/downloads/";
            if (!file_exists($dl_target_dir)) { mkdir($dl_target_dir, 0777, true); }
            $dl_ext = strtolower(pathinfo($_FILES["download_file"]["name"], PATHINFO_EXTENSION));
            $dl_new_filename = uniqid('dl_') . '.' . $dl_ext;
            if (move_uploaded_file($_FILES["download_file"]["tmp_name"], $dl_target_dir . $dl_new_filename)) {
                if (!empty($download_file_path) && file_exists("../../" . $download_file_path)) {
                    @unlink("../../" . $download_file_path);
                }
                $download_file_path = "uploads/downloads/" . $dl_new_filename;
            }
        }

        // --- Update Main Product Record ---
        $sql = "UPDATE products SET 
                name=?, slug=?, description=?, short_description=?, type=?, regular_price=?, sale_price=?, 
                sale_price_dates_from=?, sale_price_dates_to=?, sku=?, manage_stock=?, stock_quantity=?, stock_status=?, 
                min_purchase_qty=?, max_purchase_qty=?, brand_id=?, main_image=?, video_url=?, status=?, publish_date=?, tags=?, tax_status=?,
                weight=?, length=?, width=?, height=?, shipping_class_id=?, meta_title=?, meta_description=?, focus_keywords=?,
                is_downloadable=?, download_file=?, download_limit=?, upsell_ids=?, crosssell_ids=? 
                WHERE id=?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $name, $slug, $description, $short_description, $type, $regular_price, $sale_price, 
            $sale_price_dates_from, $sale_price_dates_to, $sku, $manage_stock, $stock_quantity, $stock_status, 
            $min_purchase_qty, $max_purchase_qty, $brand_id, $main_image_path, $video_url, $status, $publish_date, $tags, $tax_status,
            $weight, $length, $width, $height, $shipping_class_id, $meta_title, $meta_description, $focus_keywords,
            $is_downloadable, $download_file_path, $download_limit, $upsell_ids, $crosssell_ids,
            $product_id
        ]);

        // --- Update Categories (Sync) ---
        $pdo->prepare("DELETE FROM product_categories WHERE product_id = ?")->execute([$product_id]);
        if (!empty($_POST['categories'])) {
            $cat_stmt = $pdo->prepare("INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)");
            foreach ($_POST['categories'] as $cat_id) { $cat_stmt->execute([$product_id, (int)$cat_id]); }
        }

        // --- Update Attributes for ALL product types ---
        $pdo->prepare("DELETE FROM product_attributes WHERE product_id = ?")->execute([$product_id]);
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

        // --- Update Variations (ONLY for Variable Products) ---
        if ($type === 'variable') {
            $submitted_var_ids = !empty($_POST['var_id']) ? array_filter($_POST['var_id']) : [];
            if (!empty($submitted_var_ids)) {
                $in = str_repeat('?,', count($submitted_var_ids) - 1) . '?';
                $del_stmt = $pdo->prepare("DELETE FROM product_variations WHERE product_id = ? AND id NOT IN ($in)");
                $params = array_merge([$product_id], $submitted_var_ids);
                $del_stmt->execute($params);
            } else {
                $pdo->prepare("DELETE FROM product_variations WHERE product_id = ?")->execute([$product_id]);
            }

            if (!empty($_POST['var_name'])) {
                foreach ($_POST['var_name'] as $index => $var_name) {
                    $clean_var_name = trim($var_name);
                    $v_id = !empty($_POST['var_id'][$index]) ? (int)$_POST['var_id'][$index] : null;
                    $v_price = !empty($_POST['var_regular_price'][$index]) ? (float)$_POST['var_regular_price'][$index] : 0;
                    $v_sale = !empty($_POST['var_sale_price'][$index]) && (float)$_POST['var_sale_price'][$index] > 0 ? (float)$_POST['var_sale_price'][$index] : null;
                    $v_sku = !empty($_POST['var_sku'][$index]) ? trim($_POST['var_sku'][$index]) : null;
                    $v_stock = isset($_POST['var_stock'][$index]) && $_POST['var_stock'][$index] !== '' ? (int)$_POST['var_stock'][$index] : null;
                    
                    $v_image_path = !empty($_POST['existing_var_image'][$index]) ? $_POST['existing_var_image'][$index] : null;
                    if (isset($_FILES['var_image']['error'][$index]) && $_FILES['var_image']['error'][$index] == 0) {
                        $v_new_filename = uniqid('var_') . '_' . $index . '.webp';
                        $v_target_file = "../../uploads/products/" . $v_new_filename;
                        if (compressAndSaveWebP($_FILES["var_image"]["tmp_name"][$index], $v_target_file)) {
                            if (!empty($v_image_path) && file_exists("../../" . $v_image_path)) {
                                @unlink("../../" . $v_image_path);
                            }
                            $v_image_path = "uploads/products/" . $v_new_filename;
                        }
                    }

                    if ($v_id) {
                        $pdo->prepare("UPDATE product_variations SET variation_name=?, sku=?, image=?, regular_price=?, sale_price=?, stock_quantity=? WHERE id=?")->execute([$clean_var_name, $v_sku, $v_image_path, $v_price, $v_sale, $v_stock, $v_id]);
                    } else {
                        $pdo->prepare("INSERT INTO product_variations (product_id, variation_name, sku, image, regular_price, sale_price, stock_quantity) VALUES (?, ?, ?, ?, ?, ?, ?)")->execute([$product_id, $clean_var_name, $v_sku, $v_image_path, $v_price, $v_sale, $v_stock]);
                    }
                }
            }
        } else {
            $pdo->prepare("DELETE FROM product_variations WHERE product_id = ?")->execute([$product_id]);
        }

        // =========================================================================
        // --- Gallery Handling ---
        // =========================================================================
        $orig_stmt = $pdo->prepare("SELECT gallery FROM products WHERE id = ?");
        $orig_stmt->execute([$product_id]);
        $orig_gallery_raw = $orig_stmt->fetchColumn();
        $orig_gallery = !empty($orig_gallery_raw) ? json_decode($orig_gallery_raw, true) : [];
        if (!is_array($orig_gallery)) $orig_gallery = [];

        $retained_gallery = $_POST['retained_gallery'] ?? [];
        if (!is_array($retained_gallery)) $retained_gallery = [];

        foreach ($orig_gallery as $old_img) {
            if (!in_array($old_img, $retained_gallery)) {
                if (file_exists("../../" . $old_img)) {
                    @unlink("../../" . $old_img);
                }
            }
        }

        $new_gallery_paths = [];
        if (isset($_FILES['gallery_images']) && !empty($_FILES['gallery_images']['name'][0])) {
            $target_dir = "../../uploads/products/";
            if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
            
            foreach ($_FILES['gallery_images']['name'] as $key => $filename) {
                if ($_FILES['gallery_images']['error'][$key] == 0) {
                    $g_new_name = uniqid('gal_') . '_' . $key . '.webp';
                    $g_target_file = $target_dir . $g_new_name;
                    if (compressAndSaveWebP($_FILES['gallery_images']['tmp_name'][$key], $g_target_file)) {
                        $new_gallery_paths[] = "uploads/products/" . $g_new_name;
                    }
                }
            }
        }

        $final_gallery = array_values(array_merge($retained_gallery, $new_gallery_paths));
        $pdo->prepare("UPDATE products SET gallery = ? WHERE id = ?")->execute([json_encode($final_gallery), $product_id]);

        $pdo->commit();

        // =========================================================================
        // 🚀 AUTO PRE-ORDER RESTOCK NOTIFICATION DISPATCH
        // =========================================================================
        $is_now_in_stock = ($stock_quantity > 0) || ($stock_status === 'instock');
        $notified_count = 0;

        if ($is_now_in_stock) {
            try {
                $po_stmt = $pdo->prepare("SELECT * FROM product_preorders WHERE product_id = ? AND status = 'pending'");
                $po_stmt->execute([$product_id]);
                $waiting_users = $po_stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($waiting_users)) {
                    $book_url = "https://readershub.loopzglobal.com/product/" . ($slug ?: $product_id);

                    foreach ($waiting_users as $user) {
                        if (sendRestockNotificationEmail($pdo, $user['customer_email'], $user['customer_name'], $name, $book_url)) {
                            $pdo->prepare("UPDATE product_preorders SET status = 'notified', notified_at = NOW() WHERE id = ?")->execute([$user['id']]);
                            $notified_count++;
                        }
                    }
                }
            } catch (Exception $pe) {
                // Preorder notification errors are caught silently to not break product update
            }
        }

        if ($notified_count > 0) {
            $_SESSION['swal_msg'] = "Product updated & {$notified_count} pre-order customer(s) notified via email!";
        } else {
            $_SESSION['swal_msg'] = "Product updated successfully!";
        }
        $_SESSION['swal_type'] = "success";

    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['swal_msg'] = "Database error: " . $e->getMessage();
        $_SESSION['swal_type'] = "error";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['swal_msg'] = $e->getMessage();
        $_SESSION['swal_type'] = "error";
    }
    
    header("Location: edit_product.php?id=" . $product_id);
    exit;
}
?>