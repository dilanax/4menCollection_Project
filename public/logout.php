<?php
// Session එක පටන් ගන්න
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// සියලුම Session Variables හිස් කිරීම
$_SESSION = array();

// Session Cookie එකත් මකා දැමීම (උපරිම ආරක්ෂාවට)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Session එක සම්පූර්ණයෙන්ම විනාශ කිරීම
session_destroy();

// නැවතත් Home පිටුවට යැවීම
header("Location: /home");
exit;
?>