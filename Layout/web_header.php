<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 315360000); // 10 years
    session_set_cookie_params(315360000);
    session_save_path(realpath(__DIR__ . '/../sessions'));
    session_start();
}
// Refresh session cookie expiry on every page load so it never expires
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), session_id(), time() + 315360000, '/');
}
// Redirect to login if session is lost
if (!isset($_SESSION['usersession'])) {
    header('Location: /shopapp/index.php');
    exit;
}
include_once("../db.php");

?>
<!doctype html>
<html lang="en">
    <head>
        <title>Qalb Chai POS</title>
        <!-- Required meta tags -->
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no" />

        <!-- PWA -->
        <link rel="manifest" href="/shopapp/manifest.json">
        <meta name="theme-color" content="#b8860b">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="QalbPOS">
        <link rel="apple-touch-icon" href="/shopapp/images/logo_small.png">

        <!-- Bootstrap CSS v5.2.1 -->
        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
            rel="stylesheet"
            integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN"
            crossorigin="anonymous"
        />
       
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
        <script type="text/javascript">
        function googleTranslateElementInit() {
            new google.translate.TranslateElement({
                pageLanguage: 'en',
                includedLanguages: 'en,ml,ta,hi',
                layout: google.translate.TranslateElement.InlineLayout.SIMPLE
            }, 'google_translate_element');
        }
    </script>
    <script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">

    <link href="../css/style.css" rel="stylesheet" />
    <link href="../css/translation.css" rel="stylesheet" />
    </head>

    <body style="top:0px !important;">
    <script>
      if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/shopapp/sw.js');
      }
    </script>
        <header>
       
            <!-- place navbar here -->
        </header>
       
        