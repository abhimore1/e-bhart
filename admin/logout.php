<?php
// Start the session to access session variables
// This should be at the very top, before any output.
session_start();

// Unset all of the session variables related to admin
if (isset($_SESSION['admin_id'])) unset($_SESSION['admin_id']);
if (isset($_SESSION['admin_email'])) unset($_SESSION['admin_email']);
if (isset($_SESSION['admin_role'])) unset($_SESSION['admin_role']);

// If you want to clear the entire session (e.g. if admin and user sessions might co-exist and use same cookie name)
// $_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

// Redirect to login page
header("Location: login.php");
exit; // Ensure no further script execution after redirect
?>
