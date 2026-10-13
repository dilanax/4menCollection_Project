<?php

// 1. Orders වලට අදාළ Email යැවීමේ Function එක
function sendOrderEmail($pdo, $order_id, $template_key, $to_email) {
    // Order විස්තර ලබා ගැනීම
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) return false;

    // Email Template එක ලබා ගැනීම
    $t_stmt = $pdo->prepare("SELECT subject, body FROM email_templates WHERE template_key = ?");
    $t_stmt->execute([$template_key]);
    $template = $t_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$template) return false; // Template එක නැත්නම් යවන්නේ නෑ

    $subject = $template['subject'];
    $body = $template['body'];

    // Shortcodes Replace කිරීම
    $replacements = [
        '{order_number}' => $order['order_number'],
        '{customer_name}' => $order['customer_first_name'] . ' ' . $order['customer_last_name'],
        '{total_amount}' => number_format($order['total_amount'], 2),
        '{status}' => ucfirst($order['order_status']),
        '{tracking_provider}' => !empty($order['tracking_provider']) ? $order['tracking_provider'] : 'N/A',
        '{tracking_number}' => !empty($order['tracking_number']) ? $order['tracking_number'] : 'N/A',
        '{tracking_link}' => !empty($order['tracking_link']) ? $order['tracking_link'] : 'N/A'
    ];

    foreach ($replacements as $key => $value) {
        $subject = str_replace($key, $value, $subject);
        $body = str_replace($key, $value, $body);
    }

    return sendHtmlEmail($to_email, $subject, $body);
}

// 2. අලුත් Account එකක් හැදුනාම ඒකෙ විස්තර යවන Function එක
function sendAccountEmail($pdo, $customer_name, $username, $password, $to_email) {
    $template_key = 'new_account_details';
    
    $t_stmt = $pdo->prepare("SELECT subject, body FROM email_templates WHERE template_key = ?");
    $t_stmt->execute([$template_key]);
    $template = $t_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$template) return false;

    $subject = $template['subject'];
    $body = $template['body'];

    // Username සහ Password Replace කිරීම
    $replacements = [
        '{customer_name}' => $customer_name,
        '{username}' => $username,
        '{password}' => $password
    ];

    foreach ($replacements as $key => $value) {
        $subject = str_replace($key, $value, $subject);
        $body = str_replace($key, $value, $body);
    }

    return sendHtmlEmail($to_email, $subject, $body);
}

// 3. පොදු HTML Email යැවීමේ Function එක (කේතය නැවත නැවත ලිවීම වැළැක්වීමට)
function sendHtmlEmail($to_email, $subject, $body) {
    $html_body = nl2br(htmlspecialchars($body));
    $full_html = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; border-radius: 8px;'>
        <h2 style='color: #2563eb; margin-top: 0;'>Loopz Global</h2>
        <div style='color: #333; line-height: 1.6; font-size: 15px;'>
            $html_body
        </div>
        <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
        <p style='font-size: 12px; color: #999; text-align: center; margin-bottom: 0;'>This is an automated message. Please do not reply directly to this email.</p>
    </div>
    ";

    $headers = "From: Loopz Global <info@loopzglobal.com>\r\n";
    $headers .= "Reply-To: info@loopzglobal.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    return mail($to_email, $subject, $full_html, $headers);
}
?>