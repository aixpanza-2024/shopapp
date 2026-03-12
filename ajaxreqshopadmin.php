<?php
include_once("db.php");

if (isset($_GET['prodId']) && isset($_GET['prodvalue'])) {
    $prodid   = intval($_GET['prodId']);   // cast to int for safety
    $quantity = intval($_GET['prodvalue']); // cast to int for safety
    $prodname = $_GET['prodname'];          // get product name
date_default_timezone_set('Asia/Kolkata');

    $date       = date('Y-m-d');
    $time       = date('H:i:s');
    $updated_at = date('Y-m-d H:i:s');
    $available_at = date('Y-m-d H:i:s');

    // First check if record already exists
    $check = $conn->prepare("
        SELECT product_id FROM daily_availability 
        WHERE product_id = ? AND available_date = ?
        LIMIT 1
    ");
    $check->bind_param("is", $prodid, $date);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        // record exists → UPDATE
        $check->close();

        $update = $conn->prepare("
            UPDATE daily_availability
            SET available_qty = ?, updated_at = ?
            WHERE product_id = ? AND available_date = ?
        ");
        $update->bind_param("isis", $quantity, $updated_at, $prodid, $date);
        if ($update->execute()) {


            
           
        } else {
            echo "Error updating: ";
        }
        $update->close();
    } else {
        // record does not exist → INSERT
        $check->close();

        $insert = $conn->prepare("
            INSERT INTO daily_availability 
                (product_id,prodname,available_date, prepared_time, available_qty, is_available, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?,?)
        ");
        $insert->bind_param("issssss", $prodid, $prodname, $date, $time, $quantity, $available_at, $updated_at);
        if ($insert->execute()) {
            // echo "Inserted successfully";
        } else {
            echo "Error inserting the value " ;
        }
        $insert->close();
    }
} else {
    
}
?>
