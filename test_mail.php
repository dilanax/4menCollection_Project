<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/db.php';

$stmt = $pdo->query("SELECT * FROM email_settings WHERE id = 1 LIMIT 1");
$settings = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

if (!$settings) {
    die("<h3>Error: email_settings table record not found!</h3>");
}

$host = 'smtp.zoho.com';
$port = 587; // Open port on your hosting server
$username = trim($settings['smtp_email']);
$password = trim($settings['smtp_app_token']);
$toEmail = $settings['admin_alert_email'] ?: $username;

echo "<h2>Zoho SMTP Socket Test (Port 587 + STARTTLS)</h2>";
echo "Connecting to <strong>tcp://{$host}:{$port}</strong>...<br><br>";

$socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 15);

if (!$socket) {
    die("<h3 style='color:red;'>Connection Failed: $errstr ($errno)</h3>");
}

function readResponse($socket) {
    $response = "";
    while ($str = fgets($socket, 515)) {
        $response .= $str;
        if (substr($str, 3, 1) == " ") { break; }
    }
    return $response;
}

function sendCommand($socket, $cmd) {
    echo "<span style='color:blue;'>&gt; " . htmlspecialchars(substr($cmd, 0, 50)) . "...</span><br>";
    fputs($socket, $cmd . "\r\n");
    $res = readResponse($socket);
    echo "<span style='color:green;'>&lt; " . htmlspecialchars(trim($res)) . "</span><br>";
    return $res;
}

echo "<div style='background:#f8fafc; border:1px solid #cbd5e1; padding:15px; border-radius:10px; font-family:monospace; font-size:12px;'>";

// 1. Initial 220 banner
$welcome = readResponse($socket);
echo "<span style='color:green;'>&lt; " . htmlspecialchars(trim($welcome)) . "</span><br>";

// 2. EHLO
sendCommand($socket, "EHLO readershub.lk");

// 3. STARTTLS Negotiation
$tlsRes = sendCommand($socket, "STARTTLS");
if (strpos($tlsRes, '220') === false) {
    echo "</div><h3 style='color:red;'>STARTTLS Rejected</h3>";
    fclose($socket);
    exit;
}

// 4. Secure the socket stream with TLS
$crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
if (!$crypto) {
    echo "</div><h3 style='color:red;'>TLS Handshake Failed</h3>";
    fclose($socket);
    exit;
}
echo "<span style='color:purple;'>* TLS encryption established successfully!</span><br>";

// 5. EHLO after TLS
sendCommand($socket, "EHLO readershub.lk");

// 6. AUTH LOGIN (Username & App Token)
sendCommand($socket, "AUTH LOGIN");
sendCommand($socket, base64_encode($username));
$passRes = sendCommand($socket, base64_encode($password));

if (strpos($passRes, '235') === false) {
    echo "</div><h3 style='color:red;'>❌ Authentication Failed! Please verify Zoho App Password.</h3>";
    fclose($socket);
    exit;
}

// 7. Mail Dispatch
sendCommand($socket, "MAIL FROM:<{$username}>");
sendCommand($socket, "RCPT TO:<{$toEmail}>");
sendCommand($socket, "DATA");

$headers = [
    "From: ReadersHub <{$username}>",
    "To: <{$toEmail}>",
    "Subject: Zoho SMTP Port 587 Live Test - Success!",
    "MIME-Version: 1.0",
    "Content-Type: text/html; charset=UTF-8",
    "Date: " . date("r")
];
$msg = implode("\r\n", $headers) . "\r\n\r\n<h3>Hello from ReadersHub!</h3><p>Your Zoho SMTP port 587 connection is working 100%!</p>";

$sendRes = sendCommand($socket, $msg . "\r\n.");
sendCommand($socket, "QUIT");
fclose($socket);

echo "</div>";

if (strpos($sendRes, '250') !== false) {
    echo "<h3 style='color:green;'>🎉 SUCCESS! Test email was successfully sent to {$toEmail}! Check your Inbox / Spam folder.</h3>";
} else {
    echo "<h3 style='color:red;'>❌ Failed to queue message.</h3>";
}