<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'shop_admin'])) {
    die("Access Denied");
}

require_once '../../config/db.php';

function compressAndSaveWebP($source, $destination, $quality = 85) {
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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    
    $smtp_host = trim($_POST['smtp_host'] ?? 'smtp.zoho.com');
    $smtp_port = (int)($_POST['smtp_port'] ?? 465);
    $smtp_encryption = trim($_POST['smtp_encryption'] ?? 'ssl');
    $smtp_email = trim($_POST['smtp_email'] ?? '');
    $smtp_app_token = trim($_POST['smtp_app_token'] ?? '');
    $from_name = trim(htmlspecialchars($_POST['from_name'] ?? 'ReadersHub', ENT_QUOTES, 'UTF-8'));
    $admin_alert_email = trim($_POST['admin_alert_email'] ?? '');
    
    $business_name = trim(htmlspecialchars($_POST['business_name'] ?? 'ReadersHub', ENT_QUOTES, 'UTF-8'));
    $website_url = filter_var(trim($_POST['website_url'] ?? ''), FILTER_SANITIZE_URL);
    $phone_number = trim(htmlspecialchars($_POST['phone_number'] ?? '', ENT_QUOTES, 'UTF-8'));
    $whatsapp_number = trim(htmlspecialchars($_POST['whatsapp_number'] ?? '', ENT_QUOTES, 'UTF-8'));
    $address = trim($_POST['address'] ?? '');
    
    $facebook_url = filter_var(trim($_POST['facebook_url'] ?? ''), FILTER_SANITIZE_URL);
    $instagram_url = filter_var(trim($_POST['instagram_url'] ?? ''), FILTER_SANITIZE_URL);
    $twitter_url = filter_var(trim($_POST['twitter_url'] ?? ''), FILTER_SANITIZE_URL);

    $logo_path = !empty($_POST['existing_logo']) ? $_POST['existing_logo'] : null;
    $remove_logo = isset($_POST['remove_logo']) && $_POST['remove_logo'] === '1';

    // Remove logo if requested
    if ($remove_logo && empty($_FILES['business_logo']['name'])) {
        if (!empty($logo_path) && file_exists("../../" . $logo_path)) {
            @unlink("../../" . $logo_path);
        }
        $logo_path = null;
    }

    // Upload New Logo
    if (isset($_FILES['business_logo']) && $_FILES['business_logo']['error'] == 0) {
        $target_dir = "../../uploads/settings/";
        if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
        
        $new_filename = 'email_logo_' . uniqid() . '.webp';
        $target_file = $target_dir . $new_filename;
        
        if (compressAndSaveWebP($_FILES["business_logo"]["tmp_name"], $target_file)) {
            if (!empty($logo_path) && file_exists("../../" . $logo_path)) {
                @unlink("../../" . $logo_path);
            }
            $logo_path = "uploads/settings/" . $new_filename;
        }
    }

    try {
        $sql = "UPDATE email_settings SET 
                smtp_host = ?, smtp_port = ?, smtp_encryption = ?, smtp_email = ?, smtp_app_token = ?, 
                from_name = ?, admin_alert_email = ?, business_name = ?, business_logo = ?, 
                phone_number = ?, whatsapp_number = ?, address = ?, website_url = ?, 
                facebook_url = ?, instagram_url = ?, twitter_url = ?
                WHERE id = 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $smtp_host, $smtp_port, $smtp_encryption, $smtp_email, $smtp_app_token,
            $from_name, $admin_alert_email, $business_name, $logo_path,
            $phone_number, $whatsapp_number, $address, $website_url,
            $facebook_url, $instagram_url, $twitter_url
        ]);

        $_SESSION['swal_msg'] = "Zoho SMTP & Business details saved successfully!";
        $_SESSION['swal_type'] = "success";
    } catch (Exception $e) {
        $_SESSION['swal_msg'] = "Error: " . $e->getMessage();
        $_SESSION['swal_type'] = "error";
    }

    header("Location: email_templates.php");
    exit;
}
?>