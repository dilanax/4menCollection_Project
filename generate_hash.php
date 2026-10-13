<?php

$password = 'Admin123';

$hash = password_hash($password, PASSWORD_DEFAULT);

echo '<h2>Password Hash</h2>';
echo '<p><strong>New password:</strong> ' . htmlspecialchars($password) . '</p>';
echo '<p><strong>Hash:</strong></p>';
echo '<textarea style="width:100%;height:100px;">' . htmlspecialchars($hash) . '</textarea>';