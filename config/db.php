<?php
$host     = 'localhost';
$dbname   = 'loopzglo_readershub_db';
$username = 'loopzglo_readershub_uzr';
$password = '4XP]gB_Uu$LvEgdK'; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Error handling සඳහා Exception mode එක ක්‍රියාත්මක කිරීම
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Data ගන්න ලේසි වෙන්න Associative Array විදිහට Set කිරීම
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}
?>