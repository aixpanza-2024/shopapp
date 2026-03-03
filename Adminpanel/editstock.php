<?php
include_once("Layout/header.php");

include_once("Layout/navbar.php");
?>

 <!-- Add Shops -->
 <div class="container">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card spur-card">
                                <div class="card-header">
                                    <div class="spur-card-icon">
                                        <i class="fas fa-chart-bar"></i>
                                    </div>
                                    <div class="spur-card-title">Search Stock</div>
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
                                    <label for="exampleFormControlInput1">Choose Date</label>

                                        
                                 <input type="date" class="form-control" name="selectdate">
                              </div>
                                    <div class="col-lg-12 col-sm-12">
                                  
                                        
                                        <button type="submit"  name="searchstock" class="btn btn-primary btnsearch">Search</button>
                                   
                                    </div>
                                </div>
                                    </form>
                                </div>
                            </div>
                        </div>



                        <div class="col-lg-12">
                            <div class="card spur-card">
                                <div class="card-header">
                                    <div class="spur-card-icon">
                                        <i class="fas fa-table"></i>
                                    </div>
                                    <div class="spur-card-title">Shops</div>
                                </div>


                                <form method="post">
                                <div class="card-body ">
                                    <table class="table table-hover table-in-card">
                                        <thead>
                                            <tr><th scope="col" ><input type="checkbox" id="select-all"></th>
                                                <th scope="col">Name</th>
                                                <th scope="col">Qty</th>
                                                <th scope="col">Purchase Price</th>
                                                
                                                <th scope="col">Date of Purchase.</th>
                                                <th scope="col">Wastage Qty</th>
                                                
                                                
                                            </tr>
                                        </thead>
                                        <tbody>

<?php
if(isset($_POST['searchstock'] )){

    $shop=$_POST['selectshop'];
    $_SESSION['shopsearch'] = $shop;
       $supplier=$_POST['selectsup'];
            $_SESSION['suppliersearch'] = $supplier;
                 $cat=$_POST['selectcat'];
                    $_SESSION['categorysearch'] = $cat;
                        $date=$_POST['selectdate'];
                            $_SESSION['datesearch'] = $date;
                            $dateString = $_SESSION['datesearch'];
$dateArray = date_parse($dateString);

//$dateArray = date('Y-m-d', strtotime($dateArray1));

$day = sprintf("%02d",$dateArray['day']);
$month = sprintf("%02d",$dateArray['month']);
$year = $dateArray['year'];

            // Start building the query
$sql = "SELECT * FROM `daily purchase` WHERE Day='$day' and Month='$month' and Year='$year' and ";

// Check if shop is selected
if (!empty($shop)) {
    $sql .= " Sh_id = '$shop'";
}

// Check if supplier is selected
if (!empty($supplier)) {
    // Add AND if previous conditions are present
    if (strpos($sql, "=") !== false) {
        $sql .= " AND";
    }
    $sql .= " Sup_id = '$supplier'";
}

// Check if category is selected
if (!empty($cat)) {
    // Add AND if previous conditions are present
    if (strpos($sql, "=") !== false) {
        $sql .= " AND";
    }
    $sql .= " Cat_id = '$cat'";
}
$dailystockfetchq=mysqli_query($conn, $sql);
$totalproducts=mysqli_num_rows($dailystockfetchq);
if (mysqli_num_rows($dailystockfetchq) > 0) {
    $inc=0;
    while($dailystockfetch = mysqli_fetch_array($dailystockfetchq)) {

        ?>
<tr>
     
        <td>

        <input type="checkbox" id="select-all" name="selected[]" value=" <?php echo $dailystockfetch['Dpp_id'];?>">
    </td>


    <td>
         <?php
         echo $dailystockfetch['Product Name'];
         ?>
    </td>
    <td>
         <?php
         echo $dailystockfetch['Qty'];
         ?>
    </td>
    <td>
         <?php
         echo $dailystockfetch['Product Purchase price'];
         ?>
    </td>
    <td >

<?php

$dateofjoin = $dailystockfetch['Day']."/".$dailystockfetch['Month']."/".$dailystockfetch['Year'];
echo $dateofjoin;
?>

</td>

    <td >
<input type="text"  class="form-control" id="wastageqty<?php echo $inc;?>" name="wastageqty[]" value="<?php echo $dailystockfetch['Wastage'];?>" required="">
<input type="hidden"  class="form-control" id="wastageqty<?php echo $inc;?>" name="dailid[]" value=" <?php echo $dailystockfetch['Dpp_id'];?>" required="">
    </td>




    

    
    
                                                </tr>
<?php
$inc++;
}
}
}
?>
<td>
<button type="submit" class="btn btn-primary btnsearch" name="wastage">Update Wastage</button>
<button type="submit" class="btn btn-primary btnsearch" name="deletedaily">Delete</button>
</td>
                                   
</tbody>
                                    </table>
                                    </form> 
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
 <!-- Search Stock with date Ends -->


 <!-- update stock wastage -->
 <!-- update stock wastage -->
 <!-- update stock wastage -->

<?php
if(isset($_POST['wastage']))
{
    

    for ($i = 0; $i < count($_POST['dailid']); $i++) {
    $wastageqty=$_POST['wastageqty'][$i];
    $wastageid=$_POST['dailid'][$i];

    $upquery="UPDATE `daily purchase` SET `Wastage`='$wastageqty' WHERE `Dpp_id`='$wastageid'";
    $upresult=mysqli_query($conn,$upquery);
    $checkcount=count($_POST['dailid']);
    $checkcount=$checkcount-1;
if($i==$checkcount){
?>
<script>
alert("Wastage Updated Sucessfully")
    </script>
<?php
}
}
}

if(isset($_POST['deletedaily']))
{


    for ($j = 0; $j < count($_POST['selected']); $j++) {
    $selectedqty=$_POST['selected'][$j];

        //fetch details from daily purchase

    $fetchdailypurchasedel="SELECT * FROM `daily purchase` WHERE `Dpp_id`='$selectedqty'";
    $fetchdailypurchasedelresul =   mysqli_query($conn,$fetchdailypurchasedel);
    $deldailydata=mysqli_fetch_array($fetchdailypurchasedelresul);
    $deldata += $deldailydata['Qty'] * $deldailydata['Product Purchase price'];
    $deldataday = $deldailydata['Day'];    
    $deldatamonth = $deldailydata['Month'];    
    $deldatayear = $deldailydata['Year'];    
    $deldatashop = $deldailydata['Sh_id'];    
    $deldatasup = $deldailydata['Sup_id'];    
    $deldatainvoice = $deldailydata['Exp_invoice_no'];
    $deldatatype = "Deleted Data";    




 //delete data from daily purchase using primary key each product ID


    $delquery="Delete from `daily purchase` WHERE `Dpp_id`='$selectedqty'";
    $delresult=mysqli_query($conn,$delquery);
    $checkdelcount=count($_POST['selected']);

    $checkdelcount=$checkdelcount-1;
if($j==$checkdelcount){

    //insert data into expense table

    $deldailyexpense="INSERT INTO `expense`(`Bill Type`, `Description`, `Total Amount`, `Amount Paid`, `Payment Status`, `Sh_id`, `Sup_id`, `Exp_invoice_no`, `Day`, `Month`, `Year`)
 VALUES('$deldatatype','Description','$deldata','0','0','$deldatashop','$deldatasup','$deldatainvoice','$deldataday','$deldatamonth','$deldatayear')";
$resultdeldailyexpense=mysqli_query($conn,$deldailyexpense);
?>
<script>
alert("Deleted Sucessfully")
    </script>
<?php
}
}
}
?>



 <!-- update stock wastage -->
 <!-- update stock wastage -->
 <!-- update stock wastage -->


 <script>
    // JavaScript code to toggle the selection status of all checkboxes
    document.getElementById('select-all').addEventListener('change', function() {
        var checkboxes = document.querySelectorAll('input[name="selected[]"]');
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = document.getElementById('select-all').checked;
        });
    });
</script>

<!-- footer  -->
<?php
include_once("Layout/footer.php");
?>
<!-- footer ends -->