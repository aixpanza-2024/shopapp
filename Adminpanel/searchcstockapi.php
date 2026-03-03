<?php
    $shop=$_POST['selectshop'];
    $_SESSION['shopsearch'] = $shop;
       $supplier=$_POST['selectsup'];
            $_SESSION['suppliersearch'] = $supplier;
                 $cat=$_POST['selectcat'];
                    $_SESSION['categorysearch'] = $cat;

            // Start building the query
$sql = "SELECT * FROM products WHERE";

// Check if shop is selected
if (!empty($shop)) {
    $sql .= " shopid = '$shop'";
}

// Check if supplier is selected
if (!empty($supplier)) {
    // Add AND if previous conditions are present
    if (strpos($sql, "=") !== false) {
        $sql .= " AND";
    }
    $sql .= " sup_id = '$supplier'";
}

// Check if category is selected
if (!empty($cat)) {
    // Add AND if previous conditions are present
    if (strpos($sql, "=") !== false) {
        $sql .= " AND";
    }
    $sql .= " categorie = '$cat'";
}

$dailystockfetchq=mysqli_query($conn, $sql);