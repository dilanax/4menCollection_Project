<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h3 style='color:blue;'>Testing bank_config.php execution...</h3>";
require_once 'bank_config.php';
echo "<h3 style='color:green;'>SUCCESS: Loaded completely without errors!</h3>";