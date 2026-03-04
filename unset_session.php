<?php
ini_set('session.gc_maxlifetime', 315360000); // 10 years
session_set_cookie_params(315360000);
session_start();
unset($_SESSION['invno']);

header('Content-Type: text/plain');
http_response_code(200);
exit('OK');
?>