<?php
include_once("Layout/header.php");

include_once("Layout/navbar.php");
?>

 <!-- Search  Product Filters -->
 <div class="container">
                    <div class="row">
                        <div class="col-xl-12">
                            <div class="card spur-card">
                                <div class="card-header">
                                    <div class="spur-card-icon">
                                        <i class="fas fa-chart-bar"></i>
                                    </div>
                                    <div class="spur-card-title">Daily Stock Search</div>
                                   <div class="stockright"> <a href="">Edit Stock</a>
</div>
                                </div>
                                <div class="card-body ">


                                    <form method="POST">
                                <div class="row">
                                    <div class="col-md-3 col-sm-6">
                                        <div class="form-group">
                                        <?php
include_once("shopapi.php");



    ?>
                                            <label for="exampleFormControlInput1">Shop Name</label>

                                            <select class="form-control" name="selectshop" required>
                                            <option value="">--Select Shop--</option>
                                                <?php
                                                if($fetchshopcount> 0) {
                                                while($fetchshop = mysqli_fetch_array($fetchshopq)) {
                                                ?>
                                                <option value="<?php echo $fetchshop['sh_id'] ?>"><?php echo $fetchshop['name'] ?></option>
                                                <?php
                                                }
                                            }else
                                            {
                                            ?>
                                            <option value="">No Data Available</option>
                                            <?php
                                            }
                                                ?>

                                            </select>
                                           
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        
                                    <div class="form-group">
                                        <?php
include_once("supplierapi.php");
    ?>
                                            <label for="exampleFormControlInput1">Supplier Name</label>

                                            <select class="form-control" name="selectsup" required>
                                            <option value="">--Select Supplier--</option>
                                                <?php
                                                
                                                if($fetchsupcount> 0) {
                                                while($fetchsup = mysqli_fetch_array($fetchsupq)) {
                                                ?>
                                                <option value="<?php echo $fetchsup['sup_id'] ?>"><?php echo $fetchsup['name'] ?></option>
                                                <?php
                                                }
                                            }else
                                            {
                                            ?>
                                            <option value="">No Data Available</option>
                                            <?php
                                            }
                                                ?>

                                            </select>
                                           
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                    <div class="form-group">
                                        <?php
include_once("categorieapi.php");
    ?>
                                            <label for="exampleFormControlInput1">Supplier Categorie</label>

                                            <select class="form-control" name="selectcat" required>
                                            <option value="">--Select Categorie--</option>
                                                <?php
                                                
                                                if(mysqli_num_rows($catfetchq) > 0) {
                                                while($catfetchq1 = mysqli_fetch_array($catfetchq)) {
                                                ?>
                                                <option value="<?php echo $catfetchq1['cat_id'] ?>"><?php echo $catfetchq1['categorie'] ?></option>
                                                <?php
                                                }
                                            }else
                                            {
                                            ?>
                                            <option value="">No Data Available</option>
                                            <?php
                                            }
                                                ?>

                                            </select>
                                           
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                  
                                        
                                        <button type="submit"  name="searchstock" class="btn btn-primary btnsearch">Search</button>
                                   
                                    </div>
                                </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        </div>
                    </div>
    
<!-- Add shop php code end -->

                    
                       
                           
                                <!-- <div class="card-body ">
                                    <div class="container">
                                    <div class="row">
                                       
                                    <form method="post">
                                    
                                    <div class="col-md-3 col-sm-6">
                                    
                                    <input type="date" name="date" placeholder="Enter Date" class="form-control">
                              
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                    
                                    <input type="datetime" name="date" placeholder="Enter Date" class="form-control">
                              
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                    <select
                                        class="form-control"
                                        name="payment"
                                        id="payment"
                                    >
                                        <option selected>Select Payment</option>
                                        <option value="0">--Not Paid--</option>
                                        <option value="1">--Fully Paid--</option>
                                        <option value="2">--Partially Paid--</option>
                                        
                                    </select>
                                        </div>


                                        <div class="col-md-3 col-sm-6">
                                    <select
                                        class="form-control"
                                        name="payment"
                                        id="payment"
                                    >
                                        <option selected>Select Payment</option>
                                        <option value="0">--Not Paid--</option>
                                        <option value="1">--Fully Paid--</option>
                                        <option value="2">--Partially Paid--</option>
                                        
                                    </select>
                                        </div>    
                                        <div class="col-md-6 col-sm-6">
                                    <button type="submit"  name="addshop" class="btn btn-primary">Update Stock</button>
</div>  
                                   
                                </div>
                                </div>
                                </div> -->

                                <div class="container">
                    <div class="row">
                        <div class="col-xl-12">
                            <div class="card spur-card">
                                <div class="card-header">
                                    <div class="spur-card-icon">
                                        <i class="fas fa-chart-bar"></i>
                                    </div>
                                    <div class="spur-card-title">Daily Stock Update</div>
                                </div>
                                <div class="card-body ">

<!-- form starts here for daily cost -->
<!-- form starts here for daily cost -->
<!-- form starts here for daily cost -->
                                    <form method="POST">
                                <div class="row">
                                
                                    
                                    <div class="col-md-3 col-sm-6">
                                    <label for="exampleFormControlInput1">Choose Date</label>

                                    <input type="date" name="date" placeholder="Enter Date" class="form-control">
                              
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                    <label for="exampleFormControlInput1">Choose Time</label>

                                    
                                    <input type="time" id="timeInput" name="time" class="form-control">
                              
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                    <label for="exampleFormControlInput1">Choose Payment</label>

                                    <select
                                        class="form-control"
                                        name="payment"
                                        id="payment"
                                        onchange="paymentupdate()"
                                    >
                                        <option selected>Select Payment</option>
                                        <option value="0">Not Paid</option>
                                        <option value="1">Fully Paid</option>
                                        <option value="2">Partially Paid</option>
                                        
                                    </select>
                                        </div>


                                        <div class="col-md-3 col-sm-6">
                                    <label for="exampleFormControlInput1">Amount Paid</label>
                                    <input type="number" id="amountpaid" name="amountpaid" value="0" class="form-control">
                                    <input type="hidden" id="dailyproductstatus" name="dailyproduct" value="Daily Product">
                                        </div>

                                        <div class="col-md-3 col-sm-6">
                                    <label for="exampleFormControlInput1">Bill Amount</label>
                                    <div id="totalsumofid"></div>
                                    <input type="hidden" readonly id="totalsum" name="billamount" value="0" class="form-control">
                                    <input type="hidden" readonly id="totalsum" name="billtype" value=" Daily Products" class="form-control">
                             
                                </div>  
                                           
                                       
                                        <div class="col-md-3 col-sm-6">
                                    <?php
                                    if(isset($_SESSION['shopsearch'])){
                                        ?>
<label class="searchlabel">
<?php
$shopsearch=$_SESSION['shopsearch'];
$fetchshop = "select * from shops where sh_id='$shopsearch' order by sh_id desc";
$fetchshopq = mysqli_query($conn, $fetchshop);
$fetchshopcount= mysqli_num_rows($fetchshopq);
$fetchshop = mysqli_fetch_array($fetchshopq);
?>
Shop Name:<br>
<input type="hidden" readonly id="totalsum" name="shopnameselected" value="<?php echo $fetchshop['sh_id'];?>" class="form-control">
                             
<?php echo $fetchshop['name']?>
</labe>
<?php
                                    }
                                    ?>
                                </div> 
                                
                                <div class="col-md-3 col-sm-6">
                                    <?php
                                    if(isset($_SESSION['suppliersearch'])){
                                        ?>
<label  class="searchlabel">

<?php
$suppliersearch =   $_SESSION['suppliersearch'];
$fetchsup = "select * from supplier where sup_id='$suppliersearch' order by sup_id desc";
$fetchsupq = mysqli_query($conn, $fetchsup);
$fetchsupcount= mysqli_num_rows($fetchsupq);
$fetchsup = mysqli_fetch_array($fetchsupq);
?>
Supplier Name:<br>
<input type="hidden" readonly id="totalsum" name="suppliernameselected" value="<?php echo $fetchsup['sup_id'];?>" class="form-control">
  
<?php echo $fetchsup['name']?>
</labe>
<?php
                                    }
                                    ?>
                                </div> 

                                <div class="col-md-3 col-sm-6">
                                    <?php
                                    if(isset($_SESSION['categorysearch'])){
                                        ?>
<label  class="searchlabel">
    <?php

    
$categoriesearch=$_SESSION['categorysearch'];
$catfetch="Select * from categorie where cat_id='$categoriesearch'";
$catfetchq=mysqli_query($conn, $catfetch);
$catfetch = mysqli_fetch_array($catfetchq);
?>
Categorie Name:<br>
<input type="hidden" readonly id="totalsum" name="categorienameselected" value="<?php echo $catfetch['cat_id'];?>" class="form-control">
  
<?php
echo $catfetch['categorie']?>
</labe>
<?php
                                    }
                                    ?>
                                </div> 
                                        <div class="col-md-3 col-sm-6">
                                    <button type="submit"  name="updatestock" class="btn btnupdate">Update Stock</button>
</div>  
                                   

                                      
                                
                                <hr>
<br>
<br> 
                                    <table class="table table-hover table-in-card">
                                        <thead>
                                            <tr>
                                                <th scope="col">Name</th>
                                                <th scope="col">Sale Price</th>
                                                <th scope="col">Qty</th>
                                                <th scope="col">Expiry</th>
                                                <th scope="col">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
<!-- search stock  -->
<!-- search stock  -->
<!-- search stock  -->
<!-- search stock  -->
<?php
if(isset($_POST['searchstock'])){

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
$totalproducts=mysqli_num_rows($dailystockfetchq);
if (mysqli_num_rows($dailystockfetchq) > 0) {
    $inc=0;
    while($dailystockfetch = mysqli_fetch_array($dailystockfetchq)) {

        ?>
<tr>
                                                
    <td>
         <?php
         echo $dailystockfetch['name'];
         ?>
<input type="hidden" id="name<?php echo $inc;?>" name="productname[]" value="<?php echo $dailystockfetch['name'];?>">
<input type="hidden" id="name<?php echo $inc;?>" name="productid[]" value="<?php echo $dailystockfetch['p_id'];?>">
<input type="hidden" id="name<?php echo $inc;?>" name="categorieid[]" value="<?php echo $dailystockfetch['categorie'];?>">
<input type="hidden" id="name<?php echo $inc;?>" name="categoriename[]" value="<?php echo $dailystockfetch['categorie'];?>">
<input type="hidden" id="name<?php echo $inc;?>" name="shopid[]" value="<?php echo $dailystockfetch['shopid'];?>">
<input type="hidden" id="name<?php echo $inc;?>" name="supid[]" value="<?php echo $dailystockfetch['sup_id'];?>">
<input type="hidden" id="purchaseprice<?php echo $inc;?>" name="purchaseprice[]" value="<?php echo $dailystockfetch['purchaseprice'];?>">
<input type="hidden" id="saleprice<?php echo $inc;?>" name="saleprice[]" value="<?php echo $dailystockfetch['saleprice'];?>">
     
    </td>
    <td >

<?php echo $dailystockfetch['saleprice'];?>

</td>

    <td >
<input type="text"  class="form-control" id="qty<?php echo $inc;?>" required="" name="qty[]" value="0" onkeyup="updateResults(<?php echo $inc;?>,<?php echo $totalproducts;?>)" required="">
    </td>




    <td>
        
            
            <select
                class="form-control"
                name="expiry[]"
                id="expiry" required=""
            >
                <option value="">Select one</option>
                <option value="6 Hour">6 Hour</option>
                <option value="7 Hour">7 Hour</option>
                <option value="8 Hour">8 Hour</option>
                <option value="9 Hour">9 Hour</option>
                <option value="10 Hour">10 Hour</option>
                <option value="1 Day">1 Day</option>
                <option value="2 Day">2 Day</option>
                <option value="3 Day">3 Day</option>
                <option value="4 Day">4 Day</option>
                <option value="5 Day">5 Day</option>
                <option value="6 Day">6 Day</option>
                <option value="7 Day">7 Day</option>
                <option value="8 Day">8 Day</option>
                <option value="9 Day">9 Day</option>
                <option value="10 Day">10 Day</option>
            </select>
        
        


        </td>

    
    <td>
    <input type="text" readonly class="form-control" id="answer<?php echo $inc;?>" value="0" name="total amount">
  
    </td>
                                                </tr>
<?php
$inc++;
}
}
}

?>

<!-- search stock -->
<!-- search stock -->
<!-- search stock -->
<!-- search stock -->





                                        </tbody>
                                    </table>
                                    </form>
<!-- form ends here for daily cost -->
<!-- form ends here for daily cost -->
<!-- form ends here for daily cost -->
                                </div>
                            
                        </div>
                        
                    </div>
                </div>
                </div>
                </div>

<!-- Add php code to insert data into expense table and daily stock table -->
<!-- Add php code to insert data into expense table and daily stock table -->
<!-- Add php code to insert data into expense table and daily stock table -->
<?php
if (isset($_POST['updatestock'])) {


 
$billtype=$_POST['billtype'];
$billamount=$_POST['billamount'];
$paymentstatus=$_POST['payment'];
$amountpaid=$_POST['amountpaid'];
$shop_id = $_POST['shopnameselected'];
$sup_id = $_POST['suppliernameselected'];
$date = $_POST['date'];
$day=date('d', strtotime($date));
$month = date('m', strtotime($date));
$year = date('Y', strtotime($date));

//checking if a invoice is already created or not 

$checkinvoice="SELECT * FROM `expense` WHERE `Sh_id`='$shop_id' AND `Sup_id`='$sup_id' AND `Day`='$day' AND `Month`='$month' AND `Year`='$year '";   
$resultcheckinvoice= mysqli_query($conn,$checkinvoice);
$countcheckinvoice=mysqli_num_rows($resultcheckinvoice);
if($countcheckinvoice!=0)
{

    $fetchcheckinvoice  = mysqli_fetch_array($resultcheckinvoice);
    
    $invoiceno = $fetchcheckinvoice['Exp_invoice_no'];
}
else
{
//inserton of data into INVOICE NO table
$selexinvoice="select * from expense_invoice order by ex_inv  desc limit 1";
$resultselexinvoice=mysqli_query($conn,$selexinvoice);
$numselexinvoice=mysqli_num_rows($resultselexinvoice);
if($numselexinvoice== 0){
$invoiceno=001;    
}else{
    $rowselexinvoice=mysqli_fetch_array($resultselexinvoice);
    $invoicenofetching=$rowselexinvoice["invno"];
    $invoiceno=$invoicenofetching+1;
}
//inserton of data into INVOICE NO table
$insertexinvoice= "INSERT INTO `expense_invoice`(`invno`, `date`) VALUES('$invoiceno','$date')";
$resultinsertexinvoice=mysqli_query($conn,$insertexinvoice);
if($resultinsertexinvoice!=1){
}
//inserton of data into expense table

    $insertdailyexpense="INSERT INTO `expense`(`Bill Type`, `Description`, `Total Amount`, `Amount Paid`, `Payment Status`, `Sh_id`, `Sup_id`, `Exp_invoice_no`, `Day`, `Month`, `Year`)
    VALUES('$billtype','Description','$billamount','$amountpaid','$paymentstatus','$shop_id','$sup_id','$invoiceno','$day','$month','$year')";

$resultinsertdailyexpense=mysqli_query($conn,$insertdailyexpense);
if($resultinsertdailyexpense!= 1){
}
}


$updatedailyexpense="UPDATE expense
SET 
    `Bill Type` = '$billtype',
    `Description` = 'Description',
    `Total Amount` = '$billamount',
    `Amount Paid` = '$amountpaid',
    `Payment Status` = '$paymentstatus',
    `Sh_id` = '$shop_id',
    `Sup_id` = '$sup_id',
    `Day` = '$day',
    `Month` = '$month',
    `Year` = '$year'
WHERE 
    Exp_invoice_no = '$invoiceno'";


$resultupdatedailyexpense=mysqli_query($conn,$updatedailyexpense);
if($resultupdatedailyexpense!= 1){
}


//inserton of data into daily purchase table
    for ($i = 0; $i < count($_POST['productname']); $i++) {
$productname = $_POST['productname'][$i];
$productid = $_POST['productid'][$i];
$qty = $_POST['qty'][$i];
$saleprice = $_POST['saleprice'][$i];
$purchaseprice = $_POST['purchaseprice'][$i];
$date = $_POST['date'];
$day=date('d', strtotime($date));
$month = date('m', strtotime($date));
$year = date('Y', strtotime($date));
$time = $_POST['time'];
$categorieid = $_POST['categorieid'][$i];
$categoriename = $_POST['categoriename'][$i];
$purchasestatus = $_POST['payment'];
$shop_id = $_POST['shopid'][$i];
$sup_id = $_POST['supid'][$i];
$expiry = $_POST['expiry'][$i];
$expiryof = explode(' ', trim($_POST['expiry'][$i]));
$dayhour = $expiryof[0];
$daytype = $expiryof[1];

$ckexist    =   "SELECT * FROM `daily purchase` WHERE `P_id`='$productid'AND `Day`='$day'AND `Month`='$month' AND `Year`='$year'AND`Sh_id`='$shop_id'AND`Sup_id`='$sup_id'";
$resultckexist = mysqli_query($conn,$ckexist);
$countckexist = mysqli_num_rows($resultckexist);

if($countckexist==0)
{
$insertdaily="INSERT INTO `daily purchase`(`Product Name`, `P_id`, `Qty`, `Product Sale price`, `Product Purchase price`, `Day`, `Month`, `Year`,
 `Time`, `Cat_id`, `Cat_name`, `Purchase Status`, `Sh_id`, `Sup_id`, `Expiry Time`, `Day Hour Type`, `Day Hour`, `Exp_invoice_no`,`Wastage`)
 VALUES('$productname','$productid','$qty','$saleprice','$purchaseprice','$day','$month','$year',
 '$time','$categorieid','$categoriename','$purchasestatus','$shop_id','$sup_id','$expiry','$daytype','$dayhour','$invoiceno','0')";
$insertdailyq=mysqli_query($conn, $insertdaily);
}
else
{
    if($qty==0)
    {

    }
    else
    {
    $updatedaily = "UPDATE `daily purchase` SET `Qty`='$qty'WHERE `P_id`='$productid'AND `Day`='$day'AND `Month`='$month' AND `Year`='$year'AND`Sh_id`='$shop_id'AND`Sup_id`='$sup_id'";
    $updatetdailyq=mysqli_query($conn, $updatedaily);
    }
}
?>
<script>
    alert("Daily Cost Updated");
    window.location="dailystock.php";
    </script>
<?php

    }
        }
    
            
?>






<!-- Add php code to insert data into expense table and daily stock table -->
<!-- Add php code to insert data into expense table and daily stock table -->
<!-- Add php code to insert data into expense table and daily stock table -->


                
 <!-- Add Shops End -->


 <script>
function updateResults(inc,totalproducts) {
    
    var incid=inc;
    var purchaseprice=parseInt(0);
    //alert("hloooo");
    purchaseprice = parseInt(document.getElementById("purchaseprice"+incid).value);
  
    var qty = parseInt(document.getElementById("qty"+incid).value);
     var answer= purchaseprice * qty;
    document.getElementById("answer"+incid).value = answer;
    var totalsumis=0; 
    var totalsum =0;

    console.log("before single"+totalsum);
                console.log("before multiple"+totalsumis);


    for (var i = 0; i < totalproducts; i++) {
                // Convert input value to a number
            
                
               
                totalsum    =   parseInt(document.getElementById("answer"+i).value); 
                
                          
                totalsumis += totalsum;
                console.log("total sum single"+totalsum);
                console.log("total sum multiple"+totalsumis);
                document.getElementById("totalsum").value = totalsumis;
                document.getElementById("totalsumofid").innerText = totalsumis;
                // Add the value to the sum (if it's a valid number)
                // if (!isNaN(value)) {
                //     sum += value;
                // }
            }
    
    // You can perform further actions here, like sending AJAX requests or updating other parts of the page
   
}
</script>


<script>
    // Optional JavaScript for handling the input
    const timeInput = document.getElementById('timeInput');
    
    timeInput.addEventListener('input', function() {
        // Here you can add any desired logic when the user inputs a time
        console.log('Selected time:', timeInput.value);
    });
</script>


<!-- footer  -->
<?php
include_once("Layout/footer.php");
?>
<!-- footer ends -->