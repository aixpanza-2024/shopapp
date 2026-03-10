<?php
include_once("Layout/header.php");

include_once("Layout/navbar.php");
?>

 <!-- Add Shops -->
 <div class="container">
                    <div class="row">
                        <div class="col-xl-6">
                            <div class="card spur-card">
                                <div class="card-header">
                                    <div class="spur-card-icon">
                                        <i class="fas fa-chart-bar"></i>
                                    </div>
                                    <div class="spur-card-title">Add Shops</div>
                                </div>
                                <div class="card-body ">
<?php
if(isset($_GET["prodedit"]))
{
    $prodedit=$_GET["prodedit"];
        $prodfetch="Select * from products where p_id='$prodedit'";
            $prodfetchq=mysqli_query($conn, $prodfetch);
if(mysqli_num_rows($prodfetchq) > 0) {
$prodfetchq1 = mysqli_fetch_array($prodfetchq)

?>

                                <form method="POST">
                                    

                                  
                                   
                                    <div class="form-group">
                                            <label for="exampleFormControlSelect1">Choose Categorie</label>
 
                                            <select class="form-control" name="categorie" id="exampleFormControlSelect1" required>
                                            <option value="">--Select Categorie--</option>
                                               <?php
$catfetch="Select * from categorie";
$catfetchq=mysqli_query($conn, $catfetch);
if(mysqli_num_rows($catfetchq) > 0) {
while($catfetchq1 = mysqli_fetch_array($catfetchq)) {
    ?>
                                                <option value="<?php echo $catfetchq1['cat_id']?>"
                                                <?php
if($prodfetchq1['categorie']==$catfetchq1['cat_id']){
    ?>
    selected
    <?php
}
                                                ?>
                                                
                                                ><?php echo $catfetchq1['categorie']?></option>

                                                <?php
}
}
                                                ?>
                                            </select>
                                        </div>

                                    <div class="form-group">
                                            <label for="exampleFormControlInput1">Product Name</label>
                                            <input type="text" value="<?php echo $prodfetchq1['name'];?>" class="form-control" id="name" name="name" placeholder="Enter Product Name">
                                    </div>
                                    

    

                                    <div class="form-group">
                                            <label for="exampleFormControlInput1">Product Purchase/Make Price</label>
                                            <input type="number" value="<?php echo $prodfetchq1['purchaseprice'];?>" class="form-control" id="purchaseprice" name="purchaseprice" placeholder="Enter Product Purchase Amount">
                                    </div>    
                                    <div class="form-group">
                                            <label for="exampleFormControlInput1">Product Sale Price</label>
                                            <input type="number" class="form-control" value="<?php echo $prodfetchq1['saleprice'];?>" id="saleprice" name="saleprice" placeholder="Enter Product Sale Price">
                                    </div>
                                    
                                    <div class="form-group">
                                            <label for="exampleFormControlSelect1">Choose Supplier</label>
 
                                            <select class="form-control" name="supplier" id="supplier" required onchange="updateHiddenInput()">
                                             
                                            <option value="">--Select Supplier--</option>
                                             <?php
$supfetch="Select * from supplier";
$supfetchq=mysqli_query($conn, $supfetch);
if(mysqli_num_rows($supfetchq) > 0) {
while($supfetchq1 = mysqli_fetch_array($supfetchq)) {

    ?>
                                                <option value="<?php echo $supfetchq1['sup_id']?>"
                                                <?php
if($prodfetchq1['sup_id']==$supfetchq1['sup_id']){
    ?>
    selected
    <?php
}
                                                ?>
                                                
                                                
                                                
                                                
                                                >
                                                
                                                
                                                <?php echo $supfetchq1['name']?></option>

                                                <?php
}
}
                                                ?>
                                            </select>
                                        </div>
                                        <input type="hidden" value="<?php echo $prodfetchq1['supplier_text'];?>"id="supplier_text" name="supplier_text">
                                    
                                        <div class="form-group">
                                            <label for="exampleFormControlSelect1">Status</label>
 
                                            <select class="form-control" name="status" id="exampleFormControlSelect1" required>
                                                <option value="Active"
                                               <?php
                                               if($prodfetchq1['status']== "Active"){
                                                ?>
                                                selected
                                                <?php
                                               }
                                               ?>
                                                
                                                
                                                >Active</option>
                                                <option value="Disable"
                                                 <?php
                                               if($prodfetchq1['status']== "Disable"){
                                                ?>
                                                selected
                                                <?php
                                               }
                                               ?>
                                                
                                                >Disable</option>

                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label for="exampleFormControlSelect1">Choose Shop</label>
                                            
<!-- choosing shop based on admin or staff -->

                                            <?php
                                            if($_SESSION['userpermission']=="Super Admin" || $_SESSION['userpermission']=="Admin" )
                                            {
                                                ?>
                                                <select class="form-control" name="shopid" id="exampleFormControlSelect1" required>
                                            
                                        <?php    
                                        }
                                            else{
                                            ?>
                                            <select class="form-control" name="shopid" id="exampleFormControlSelect1" required disabled>
                                            
                                            <?php
                                            }
                                             ?>
                                            <option value="">--Select Shop--</option>

                                                <?php
if($fetchshopcount> 0) {
while($fetchshop = mysqli_fetch_array($fetchshopq)) {
    ?>
                                                <option value="<?php echo $fetchshop['sh_id'];?>" <?php
                                                 if($_SESSION['selectshop']==$fetchshop['sh_id']){
                                                    ?>
                                                     selected 
                                                     <?php
                                                    }
                                                    ?>>
                                                    <?php echo $fetchshop['name']?></option>

                                                <?php
}
}

                                                ?>
                                            </select>
                                            <!-- choosing shop based on admin or staff  end-->
                                        </div>
                                        <div class="form-group">
                                            <label>Expiry</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" name="expiry_value" placeholder="e.g. 30" min="0"
                                                    value="<?php echo !empty($prodfetchq1['expiry_value']) ? htmlspecialchars($prodfetchq1['expiry_value']) : ''; ?>">
                                                <select class="form-control" name="expiry_type">
                                                    <option value="">-- No Expiry --</option>
                                                    <option value="Days"   <?php echo ($prodfetchq1['expiry_type'] === 'Days')   ? 'selected' : ''; ?>>Days</option>
                                                    <option value="Months" <?php echo ($prodfetchq1['expiry_type'] === 'Months') ? 'selected' : ''; ?>>Months</option>
                                                    <option value="Years"  <?php echo ($prodfetchq1['expiry_type'] === 'Years')  ? 'selected' : ''; ?>>Years</option>
                                                </select>
                                            </div>
                                            <small class="text-muted">Leave blank if product has no expiry.</small>
                                        </div>

                                        <button type="submit"  name="updateprod" class="btn btn-primary">Submit</button>
                                    </form>
                                    <?php
}
else{
    echo "Sorry You can't Edit";
}
}
else{
    echo "Sorry You can't Edit";
}
                                    ?>
                                </div>
                            </div>
                        </div>
<!-- Add shop php code -->
<?php
if (isset($_POST["updateprod"])) {
    $categorie= sanitizeInput($_POST['categorie']);
    $name= sanitizeInput($_POST['name']);
    $purchaseprice= sanitizeInput($_POST['purchaseprice']);
    $saleprice= sanitizeInput($_POST['saleprice']);
    $supplier= sanitizeInput($_POST['supplier']);
    $supplier_text= sanitizeInput($_POST['supplier_text']);
    $status= sanitizeInput($_POST['status']);
    $expiry_value = !empty($_POST['expiry_value']) ? intval($_POST['expiry_value']) : null;
    $expiry_type  = !empty($_POST['expiry_type'])  ? sanitizeInput($_POST['expiry_type']) : null;
    if($_SESSION['userpermission']!="Super Admin" || $_SESSION['userpermission']=="Admin")
    {
    $shopid= sanitizeInput($_POST['shopid']);
    }
    else
    {
        $shopid= sanitizeInput($_SESSION['selectshop']);
    }
    $date = date("d/m/Y");

$ev = $expiry_value !== null ? "'$expiry_value'" : "NULL";
$et = $expiry_type  !== null ? "'$expiry_type'"  : "NULL";
$updateprod="UPDATE `products` SET `categorie`='$categorie',`name`='$name',`purchaseprice`='$purchaseprice',`saleprice`='$saleprice',`sup_id`='$supplier',
`supplier_text`='$supplier_text',`status`='$status',`shopid`='$shopid',`expiry_value`=$ev,`expiry_type`=$et WHERE `p_id`='$prodedit'";
$updateprodq=mysqli_query($conn, $updateprod);
if($updateprodq==1) {
?>

<script>
    alert("Updated Sucessfully"); 
    window.location='manageproducts.php';
</script>

<?php
}
else{
    ?>
    
    <script>
    alert("Something went wrong");
    return false;
     </script>


    <?php
}
}

?>
    
  <!-- script for stoting hidden field -->
<script>
        function updateHiddenInput() {
            var dropdown = document.getElementById("supplier");
            var selectedOptionText = dropdown.options[dropdown.selectedIndex].text;
            document.getElementById("supplier_text").value = selectedOptionText;
        }
    </script>
<!-- script for stoting hidden field -->
  
<!-- Add shop php code end -->

                        
                    </div>
                </div>
 <!-- Add Shops End -->


<!-- footer  -->
<?php
include_once("Layout/footer.php");
?>
<!-- footer ends -->