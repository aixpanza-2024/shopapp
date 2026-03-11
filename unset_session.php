<?php
ini_set('session.gc_maxlifetime', 315360000); // 10 years
session_set_cookie_params(315360000);
$sess = __DIR__ . '/sessions'; if (!is_dir($sess)) @mkdir($sess, 0750, true); session_save_path($sess);
session_start();
unset($_SESSION['invno']);

header('Content-Type: text/plain');
http_response_code(200);
exit('OK');
?>