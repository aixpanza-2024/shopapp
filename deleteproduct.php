<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 315360000); // 10 years
    session_set_cookie_params(315360000);
    session_save_path(realpath(__DIR__ . '/sessions'));
    session_start();
}
require_once 'db.php'; 
if(isset($_SESSION['invno']))
{
$invoiceId = $_SESSION['invno'];
}
$productId = $_POST['delprodid'];

try {
    // 1. Delete the product
    $deleteStmt = $conn->prepare("DELETE FROM daily_productsale WHERE `Inv no` = ? AND `dps_id` = ?");
    $deleteStmt->bind_param("ii", $invoiceId, $productId); // assuming both are integers
    $deleteStmt->execute();


    // 2. Check if any products are left in the invoice
$countStmt = $conn->prepare("SELECT COUNT(*) FROM daily_productsale WHERE `Inv no` = ?");
$countStmt->bind_param("i", $invoiceId); // 'i' for integer
$countStmt->execute();
$countStmt->bind_result($remaining);
$countStmt->fetch();
$countStmt->close();


    // 3. If no products left, update the invoice status
    if ($remaining == 0) {
        $updateStmt = $conn->prepare("UPDATE income_invoice SET `Payment Status` = 'cancelled' WHERE invno = ?");
        $updateStmt->bind_param("i", $invoiceId); // assuming both are integers
        $updateStmt->execute();
        echo "No products left";
        unset($_SESSION['invno']);
    }
    else
    {
        echo 'delete success';
    }

    
} catch (Exception $e) {
    echo 'error';
}


?>