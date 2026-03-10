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
                                    <div class="spur-card-title">Add Products</div>
                                </div>
                                <div class="card-body ">


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
                                                <option value="<?php echo $catfetchq1['cat_id']?>"><?php echo $catfetchq1['categorie']?></option>

                                                <?php
}
}
                                                ?>
                                            </select>
                                        </div>

                                    <div class="form-group">
                                            <label for="exampleFormControlInput1">Product Name</label>
                                            <input type="text" class="form-control" id="name" name="name" placeholder="Enter Product Name">
                                    </div>
                                    

    

                                    <div class="form-group">
                                            <label for="exampleFormControlInput1">Product Purchase/Make Price</label>
                                            <input type="number" class="form-control" id="purchaseprice" name="purchaseprice" placeholder="Enter Product Purchase Amount">
                                    </div>    
                                    <div class="form-group">
                                            <label for="exampleFormControlInput1">Product Sale Price</label>
                                            <input type="number" class="form-control" id="saleprice" name="saleprice" placeholder="Enter Product Sale Price">
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
                                                <option value="<?php echo $supfetchq1['sup_id']?>"><?php echo $supfetchq1['name']?></option>

                                                <?php
}
}
                                                ?>
                                            </select>
                                        </div>
                                        <input type="hidden" id="supplier_text" name="supplier_text">
                                    
                                        <div class="form-group">
                                            <label for="exampleFormControlSelect1">Status</label>
 
                                            <select class="form-control" name="status" id="exampleFormControlSelect1" required>
                                                <option value="Active">Active</option>
                                                <option value="Active">Disable</option>

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
                                                <input type="number" class="form-control" name="expiry_value" placeholder="e.g. 30" min="0">
                                                <select class="form-control" name="expiry_type">
                                                    <option value="">-- No Expiry --</option>
                                                    <option value="Days">Days</option>
                                                    <option value="Months">Months</option>
                                                    <option value="Years">Years</option>
                                                </select>
                                            </div>
                                            <small class="text-muted">Leave blank if product has no expiry.</small>
                                        </div>

                                        <button type="submit"  name="addproducts" class="btn btn-primary">Submit</button>
                                    </form>
                                </div>
                            </div>
                        </div>
<!-- Add shop php code -->
<?php
if (isset($_POST["addproducts"])) {
$categorie= sanitizeInput($_POST['categorie']);
$name= sanitizeInput($_POST['name']);
$purchaseprice= sanitizeInput($_POST['purchaseprice']);
$saleprice= sanitizeInput($_POST['saleprice']);
$supplier= sanitizeInput($_POST['supplier']);
$supplier_text= sanitizeInput($_POST['supplier_text']);
$status= sanitizeInput($_POST['status']);
$expiry_value = !empty($_POST['expiry_value']) ? intval($_POST['expiry_value']) : null;
$expiry_type  = !empty($_POST['expiry_type'])  ? sanitizeInput($_POST['expiry_type']) : null;
if($_SESSION['userpermission']=="Super Admin" || $_SESSION['userpermission']=="Admin")
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
$addprod="INSERT INTO `products`(`categorie`, `name`, `purchaseprice`, `saleprice`, `sup_id`, `supplier_text`, `status`, `shopid`, `date`, `expiry_value`, `expiry_type`)
VALUES  ('$categorie','$name','$purchaseprice','$saleprice','$supplier','$supplier_text','$status','$shopid','$date',$ev,$et)";
$addprodq=mysqli_query($conn, $addprod);
if($addprodq==1) {
?>

<script>
    alert("Added Sucessfully"); 
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

    
    
<!-- Display categorie php code end -->


                        <div class="col-lg-12">
                            <div class="row">
                                <div class="col-lg-2">
                                    
                                </div>
                                    <div class="col-lg-6">
                                    <?php
                                      include_once("searchshop.php");
                                      ?>
                                        
</div>
</div>
<br>    
                            <div class="card spur-card">
                                <div class="card-header">
                                    <div class="spur-card-icon">
                                        <i class="fas fa-table"></i>
                                    </div>
                                    <div class="spur-card-title">Manage products</div>
                                </div>
                               
                                <div class="card-body ">
                                    <table class="table table-hover table-in-card">
                                        <thead>
                                            <tr>
                                                <th scope="col">Name</th>
                                                <th scope="col">Purc_Price</th>
                                                <th scope="col">Sale_price</th>
                                                <th scope="col">Supplier Name</th>
                                                <th scope="col">Expiry</th>
                                                <th scope="col">Edit</th>
                                                <th scope="col">Delete</th>
                                            </tr>
                                        </thead>
                                        <tbody>

<?php
if(isset($_POST['search'])) {
    $searchid=sanitizeInput($_POST['shopid']);
    $prodfetch="Select * from products where shopid='$searchid' order by p_id desc";
}
else
{

    $sessionshop=$_SESSION['selectshop'];
        $prodfetch="Select * from products where shopid='$sessionshop'";
}
$prodfetchq=mysqli_query($conn, $prodfetch);
if(mysqli_num_rows($prodfetchq) > 0) {
while($prodfetchq1 = mysqli_fetch_array($prodfetchq)) {


    ?>

<tr>
                                                
                                                <td><?php echo $prodfetchq1['name'] ?></td>
                                                <td><?php echo $prodfetchq1['purchaseprice'] ?></td>
                                                <td><?php echo $prodfetchq1['saleprice'] ?></td>
                                                <td><?php echo $prodfetchq1['supplier_text'] ?></td>
                                                <td>
                                                    <?php
                                                    if (!empty($prodfetchq1['expiry_value']) && !empty($prodfetchq1['expiry_type'])) {
                                                        echo htmlspecialchars($prodfetchq1['expiry_value']) . ' ' . htmlspecialchars($prodfetchq1['expiry_type']);
                                                    } else {
                                                        echo '<span class="text-muted">—</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td> <a href="" onclick="return confirmDelete(<?php echo $prodfetchq1['p_id'] ?>)">Delete</a></td>
                                                <td> <a href="manageproductsedit.php?prodedit=<?php echo $prodfetchq1['p_id'] ?>">Edit</a></td>
                                           
                                            </tr>
    <?php
}


}
else{
    echo "No Data Found";
}
?>

                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
 <!-- Display Categorie End -->


 <!-- javascript delete pompt -->
 <script>
        function confirmDelete(id) {
            // Ask for confirmation using the browser's built-in confirm dialog
            var userConfirmed = confirm("Are you sure you want to delete this data?");
            
            // If the user confirms, proceed with the deletion
            if (userConfirmed) {
                
                window.location="deletedata.php?proddelete="+id;
                // Optionally, you can redirect to a different page or update the UI
                // window.location.href = "delete.php?id=" + id;
            } else {
                // The user clicked "Cancel" or closed the dialog
                alert("Deletion canceled.");
            }
            
            // Prevent the default behavior of the anchor tag (e.g., navigating to a new page)
            return false;
        }
    </script>
 <!-- javascript delete pompt end -->


<!-- footer  -->
<?php
include_once("Layout/footer.php");
?>
<!-- footer ends -->