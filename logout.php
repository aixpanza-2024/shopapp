<?php
ini_set('session.gc_maxlifetime', 315360000);
session_set_cookie_params(315360000);
session_start();

// Destroy all session data
session_unset();
session_destroy();

// Expire the session cookie in the browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

// Redirect to login
header('Location: /shopapp/index.php');
exit;
?>
