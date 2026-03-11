<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 315360000); // 10 years
    session_set_cookie_params(315360000);
    session_start();
}

include_once('db.php');
// Fetch input

if(isset($_POST['product_id']))
{

$product_id = $_POST['product_id'];
$action = isset($_POST['action']) ? $_POST['action'] : 'add';
$shopid =  $_SESSION['selectshop'];
$customer_id = 0;
$quantity=1;
$dpp_id=1;//daily product addition details
$payment_mode = "cash";
$payment_status = "notpaid";
$event_name="NA";
$event_id=0;
$staffid=$_SESSION['usersession'];


//fetch the last id of this product 
// Fetching the latest availability entry
// Prepare the query
$latestproductid = $conn->prepare("
    SELECT daid, product_id
    FROM daily_availability
    WHERE product_id = ?
    ORDER BY daid DESC
    LIMIT 1
");

// Bind the parameter
$latestproductid->bind_param("s", $product_id);

// Execute
$latestproductid->execute();

// Get result
$result = $latestproductid->get_result();


$recentid = null; 
if ($rcentidof = $result->fetch_assoc()) {
    $recentid = $rcentidof['daid'];
}


$dpp_id = $recentid ?? 0; // 0 for products with no daily_availability record (e.g. Tea)

//fetching the weather result
$weatherid = 0; // default if weather_log is empty
$stmtwthr = $conn->prepare("SELECT w_id, temperature, humidity, pressure, weather_type, wind_speed, location, recorded_at FROM weather_log ORDER BY w_id DESC limit 1");

// Execute the statement
$stmtwthr->execute();

// Bind result variables
$stmtwthr->bind_result($w_id, $temperature, $humidity, $pressure, $weather_type, $wind_speed, $location, $recorded_at);

// Fetch results
while ($stmtwthr->fetch()) {
$weatherid=$w_id;// Default weather value (replace with actual API value later)
}

// Close statement and connection
$stmtwthr->close();



// Get current date/time components in IST
date_default_timezone_set('Asia/Kolkata');
$day = date('d');
$month = date('m');
$year = date('Y');
$time = date('Y-m-d H:i:s');

// Prepare SQL query using prepared statements
$query = "SELECT * FROM products WHERE p_id = ?";
$stmt = $conn->prepare($query);

// Bind parameter
$stmt->bind_param("s", $product_id);

// Execute  
$stmt->execute();

// Get result
$result = $stmt->get_result();

// Check if product exists
if ($result->num_rows > 0) {

    // Fetch product info first so variables are always defined
    $product = $result->fetch_assoc();
    $prodname = $product['name'];
    $prodid = $product['p_id'];
    $prodpurchase = $product['purchaseprice'];
    $prodsaleprice = $product['saleprice'];
    $royalty_point = intdiv($prodsaleprice, 10);

    //enter the code here to check if the product qty has already been sold today or not and accordingly allow or disallow the addition of the product to the cart
   //////////////////////////////////////////code starts here///////////////////////////////////

    // 1️⃣ Get today's available quantity
    $getAvail = mysqli_query($conn, "
        SELECT available_qty
        FROM daily_availability
        WHERE product_id = '$product_id'
        ORDER BY daid DESC
        LIMIT 1
    ");
$countingprod1=mysqli_num_rows($getAvail);
if($countingprod1==0)
{

}
else
    {
    $availRow = mysqli_fetch_assoc($getAvail);

    $available_qty = isset($availRow['available_qty']) ? (int)$availRow['available_qty'] : 0;
// 2️⃣ Get today's total sold
    $getSold = mysqli_query($conn, "
        SELECT SUM(quantity) AS total_sold
        FROM daily_productsale
        WHERE p_id = '$product_id'
        AND dpp_id = '$dpp_id'
    ");
$countingprod2=mysqli_num_rows($getSold);
if($countingprod2==0)
{

}
else
    {
    $soldRow = mysqli_fetch_assoc($getSold);
    $total_sold = isset($soldRow['total_sold']) ? (int)$soldRow['total_sold'] : 0;
    }
   //////code ends here/////////////////////////////////////////

    }

}
else {
    echo "Something went wrong: product not found.";
    exit;
}

// Close statement
$stmt->close();

////////////invoice setting stars here///////////////////////////////////
$session_was_fresh = !isset($_SESSION['invno']);
if (isset($_SESSION['invno'])) {

    $invno=$_SESSION['invno'];

//echo "enerd here invnolast";
//unset($_SESSION['invno']);
}
else
{

// Step 1: Fetch next invno from MAX of both tables to prevent ghost invno collisions
$select_stmt = $conn->prepare("
    SELECT COALESCE(MAX(inv), 0) + 1 AS next_invno
    FROM (
        SELECT invno AS inv FROM income_invoice WHERE sh_id = ? AND Year = ?
        UNION ALL
        SELECT `Inv no` AS inv FROM daily_productsale WHERE sh_id = ? AND Year = ?
    ) AS all_invoices
");
$select_stmt->bind_param("iiii", $shopid, $year, $shopid, $year);
$select_stmt->execute();
$row = $select_stmt->get_result()->fetch_assoc();
$invno = $row['next_invno'];
$select_stmt->close();
$_SESSION['invno'] = $invno;

}
//invoice creation ends here 

//code for checking if the product with same id already there in the same invoice 
$check_stmt_samepd = $conn->prepare("SELECT * FROM daily_productsale WHERE `Inv no` = ? AND `p_id` = ?");
$check_stmt_samepd->bind_param("ii", $invno, $prodid);
if (!$check_stmt_samepd->execute()) {
    echo "Something went wrong";
} else {


    $resultcount = $check_stmt_samepd->get_result();
    $count = $resultcount->num_rows;
    $prodcount = 0;
    if ($count > 0) {
        $rowquantity = $resultcount->fetch_assoc();
        $prodcount = $rowquantity['quantity'];
    }


if ($count > 0) {

    if($action=="sub"){

        if($prodcount>1){
        $update_stmt = $conn->prepare("
        UPDATE daily_productsale
        SET `quantity` = `quantity` - 1 ,`Royalty Point` = `Royalty Point`- '$royalty_point'
        WHERE `Inv no` = ? AND `p_id` = ?");
        $update_stmt->bind_param("si", $invno, $prodid);
        if ($update_stmt->execute()) {
            echo "✅ qtysuccessfully!";
        } else {
            echo "❌ Error qty product: " . $update_stmt->error;
        }
        }
        // else: qty is already 1, do nothing (cannot go below 1)

    }
    else{

    $update_stmt = $conn->prepare("
        UPDATE daily_productsale
        SET `quantity` = `quantity` + 1 ,`Royalty Point` = `Royalty Point`+ '$royalty_point'
        WHERE `Inv no` = ? AND `p_id` = ?
    ");
     $update_stmt->bind_param("si", $invno, $prodid);
    if ($update_stmt->execute()) {
        echo "✅ qtysuccessfully!";
    } else {
        echo "❌ Error qty product: " . $update_stmt->error;
    }
    }
//code for updating the quantity

}
else
{


if ($session_was_fresh) {

$invoice_stmt = $conn->prepare("INSERT INTO income_invoice (
    `invno`, `Day`, `Month`, `Year`, `C_id`, `Payment Mode`, `Payment Status`, `sh_id`,`time`,`w_id`
) VALUES (?, ?, ?, ?, ?, ?, ?, ?,?,?)");
$invoice_stmt->bind_param("siiiissisi", $invno, $day, $month, $year, $customer_id, $payment_mode, $payment_status, $shopid,$time,$weatherid);
if ($invoice_stmt->execute()) {
    echo "✅ Invoice inserted successfully with INVNO: $invno";
} else {
    echo "❌ Execute failed: " . $invoice_stmt->error;
}
$invoice_stmt->close();
}
$purchased_for = '';
$insert_stmt = $conn->prepare("INSERT INTO daily_productsale(
    `product name`, `quantity`,`dpp_id`,`p_id`,
    `Day`, `Month`, `Year`, `Time`, `Event Name`, `Event_id`, `payment status`, `Royalty Point`,
    `Purchased For`, `Buying Price`, `Selling Price`, `Payment Mode`, `sh_id`, `Inv no`,`staff_id`
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?)");

// Bind parameters: example
$insert_stmt->bind_param("siiisssssisisddsiii",
     $prodname, $quantity,$dpp_id,$product_id,
    $day, $month, $year, $time, $event_name, $event_id, $payment_status, $royalty_point,
    $purchased_for, $prodpurchase, $prodsaleprice, $payment_mode, $shopid, $invno, $staffid
);

if (!$insert_stmt) {
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}

// Execute
if ($insert_stmt->execute()) {
    echo "✅ Product inserted successfully!";
} else {
    echo "❌ Error inserting product: " . $insert_stmt->error;
}

$insert_stmt->close();
    }

}
    // $insert_stmt = $conn->prepare("INSERT INTO income_invoice (shop_id, Year, invno) VALUES (?, ?, ?)");
    // $insert_stmt->bind_param("iii", $shop_id, $year, $new_invoice_no);
    // $insert_stmt->execute();
    // $insert_stmt->close();

// Step 2: Format invoice number



//////////////invoice ends here




    
   
} 

 // You can now proceed with your logic (e.g.,to add product to the cart)




else
{
   
}

?>



