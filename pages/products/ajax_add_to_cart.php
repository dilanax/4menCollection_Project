<?php
// Session පටන් ගැනීම (දත්ත සේව් කිරීමට අත්‍යවශ්‍යයි)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// බ්‍රවුසරයට පවසනවා මේක JSON උත්තරයක් කියලා
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    
    // Cart එකක් නැත්නම් හිස් එකක් හදනවා
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $product_id = (int)$_POST['product_id'];
    $variation_id = !empty($_POST['variation_id']) ? (int)$_POST['variation_id'] : 0;
    $quantity = !empty($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

    $cart_key = $product_id . '_' . $variation_id;

    // Cart එකට සේව් කිරීම
    if (isset($_SESSION['cart'][$cart_key])) {
        $_SESSION['cart'][$cart_key]['quantity'] += $quantity;
    } else {
        $_SESSION['cart'][$cart_key] = [
            'product_id' => $product_id,
            'variation_id' => $variation_id,
            'quantity' => $quantity
        ];
    }
    
    // සාර්ථකයි කියලා JSON Response එක දෙනවා
    echo json_encode(['status' => 'success', 'message' => 'Product added to cart']);
    exit;
}

// අනවශ්‍ය විදිහට මේ පිටුවට ආවොත්
echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
exit;