<?php
session_start();
unset($_SESSION['invno']);

header('Content-Type: text/plain');
http_response_code(200);
exit('OK');
?>