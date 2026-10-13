<?php
// Error Reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Check: Admin Only
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'shop_admin'])) {
    die("Access Denied: Admin Privileges Required.");
}

require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    // ==========================================
    // ACTION: DELETE PRODUCT (COMPLETE CLEANUP)
    // ==========================================
    if ($_POST['action'] == 'delete_product') {
        $delete_id = (int)$_POST['product_id'];
        
        try {
            $pdo->beginTransaction();

            // 1. Fetch file paths before deleting records
            $img_stmt = $pdo->prepare("SELECT main_image, gallery, download_file FROM products WHERE id = ?");
            $img_stmt->execute([$delete_id]);
            $prod = $img_stmt->fetch();

            // 2. Delete relational entries
            $pdo->prepare("DELETE FROM product_categories WHERE product_id = ?")->execute([$delete_id]);
            $pdo->prepare("DELETE FROM product_attributes WHERE product_id = ?")->execute([$delete_id]);
            $pdo->prepare("DELETE FROM product_variations WHERE product_id = ?")->execute([$delete_id]);
            $pdo->prepare("DELETE FROM product_reviews WHERE product_id = ?")->execute([$delete_id]);

            // 3. Delete main product record
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$delete_id]);

            $pdo->commit();

            // 4. Safely unlink physical images and files from server
            if (!empty($prod['main_image']) && file_exists("../../" . $prod['main_image'])) {
                @unlink("../../" . $prod['main_image']);
            }
            if (!empty($prod['gallery'])) {
                $gallery = json_decode($prod['gallery'], true);
                if (is_array($gallery)) {
                    foreach ($gallery as $g_file) {
                        if (file_exists("../../" . $g_file)) {
                            @unlink("../../" . $g_file);
                        }
                    }
                }
            }
            if (!empty($prod['download_file']) && file_exists("../../" . $prod['download_file'])) {
                @unlink("../../" . $prod['download_file']);
            }

            $_SESSION['swal_msg'] = "Product and all associated data deleted successfully.";
            $_SESSION['swal_type'] = "success";

        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['swal_msg'] = "Cannot delete this product. It is linked to existing customer orders.";
            $_SESSION['swal_type'] = "error";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['swal_msg'] = "Error: " . $e->getMessage();
            $_SESSION['swal_type'] = "error";
        }

        header("Location: all_product.php");
        exit;
    }

    // ==========================================
    // ACTION: IMPORT CSV
    // ==========================================
    if ($_POST['action'] == 'import_csv' && isset($_FILES['import_csv'])) {
        if ($_FILES['import_csv']['error'] == 0) {
            $ext = strtolower(pathinfo($_FILES['import_csv']['name'], PATHINFO_EXTENSION));
            if ($ext === 'csv') {
                $handle = fopen($_FILES['import_csv']['tmp_name'], "r");
                $header = fgetcsv($handle); // Skip header row
                $imported_count = 0;
                
                $pdo->beginTransaction();
                try {
                    $import_stmt = $pdo->prepare("INSERT INTO products (name, slug, sku, type, regular_price, sale_price, status, stock_status, stock_quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    while (($data = fgetcsv($handle)) !== FALSE) {
                        if (!empty($data[0])) {
                            $name = trim($data[0]);
                            $base_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
                            
                            // Ensure unique slug
                            $chk_slug = $pdo->prepare("SELECT COUNT(*) FROM products WHERE slug = ?");
                            $chk_slug->execute([$base_slug]);
                            $slug = ($chk_slug->fetchColumn() > 0) ? $base_slug . '-' . uniqid() : $base_slug;

                            $sku = trim($data[1] ?? '');
                            $type = in_array(trim($data[2] ?? ''), ['simple', 'variable']) ? trim($data[2]) : 'simple';
                            $r_price = !empty($data[3]) ? (float)$data[3] : null;
                            $s_price = !empty($data[4]) ? (float)$data[4] : null;
                            $status = in_array(trim($data[5] ?? ''), ['published', 'draft']) ? trim($data[5]) : 'draft';
                            $stk_status = in_array(trim($data[6] ?? ''), ['instock', 'outofstock', 'onbackorder']) ? trim($data[6]) : 'instock';
                            $stk_qty = isset($data[7]) && $data[7] !== '' ? (int)$data[7] : null;
                            
                            $import_stmt->execute([$name, $slug, $sku, $type, $r_price, $s_price, $status, $stk_status, $stk_qty]);
                            $imported_count++;
                        }
                    }
                    
                    $pdo->commit();
                    $_SESSION['swal_msg'] = "$imported_count Products successfully imported!";
                    $_SESSION['swal_type'] = "success";

                } catch (Exception $e) {
                    $pdo->rollBack();
                    $_SESSION['swal_msg'] = "Error importing CSV: " . $e->getMessage();
                    $_SESSION['swal_type'] = "error";
                }
                fclose($handle);
            } else {
                $_SESSION['swal_msg'] = "Please upload a valid .csv format file.";
                $_SESSION['swal_type'] = "error";
            }
        } else {
            $_SESSION['swal_msg'] = "Error uploading file. Please try again.";
            $_SESSION['swal_type'] = "error";
        }
        
        header("Location: all_product.php");
        exit;
    }
}
?>