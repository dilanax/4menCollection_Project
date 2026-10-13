
<?php
$password = "Admin@12345";

$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

echo "Password: " . $password . "<br>";
echo "Hashed Password: " . $hashedPassword;
?>
