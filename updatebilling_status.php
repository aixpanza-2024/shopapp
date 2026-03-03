<?php
include 'db.php';
$status       = mysqli_real_escape_string($conn, $_POST['status']);
$invno        = intval($_POST['invno']);
$payment_mode = isset($_POST['payment_mode']) ? mysqli_real_escape_string($conn, $_POST['payment_mode']) : 'cash';
$staff_name   = isset($_POST['staff_name'])   ? mysqli_real_escape_string($conn, $_POST['staff_name'])   : '';

// For staff billing, store "staff - Name" as the payment mode
$mode_value = ($payment_mode === 'staff' && $staff_name !== '')
    ? "staff - $staff_name"
    : $payment_mode;

mysqli_query($conn, "UPDATE daily_productsale SET `payment status`='$status', `Payment Mode`='$mode_value' WHERE `Inv no`='$invno'");
mysqli_query($conn, "UPDATE income_invoice SET `Payment Mode`='$mode_value', `Payment Status`='$status' WHERE `invno`='$invno'");
echo "Status updated to $status, mode: $mode_value";
