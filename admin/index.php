<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Admin කෙනෙක් ලොග් වෙලාද කියලා බලනවා
if (isset($_SESSION['user_id']) && in_array($_SESSION['user_role'], ['admin', 'shop_admin'])) {
    // Admin කෙනෙක් නම්, ඔයා අලුතින් හදපු dashboard.php එකට යවනවා
    header("Location: /admin/dashboard/dashboard.php");
    exit;
} else {
    // ලොග් වෙලා නැත්නම් හෝ සාමාන්‍ය customer කෙනෙක් නම්, කෙලින්ම Login එකට විසි කරනවා
    header("Location: /login");
    exit;
}
?>