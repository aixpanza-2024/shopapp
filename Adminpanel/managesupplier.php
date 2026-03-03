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
                                    <div class="spur-card-title">Add Suppliers</div>
                                </div>
                                <div class="card-body ">


                                    <form method="POST">
                                    

                                    <div class="form-group">
                                            <label for="exampleFormControlInput1">Supplier Name</label>
                                            <input type="text" class="form-control" id="name" name="name" placeholder="Enter Supplier Name">
                                        </div>

                                    <div class="form-group">
                                            <label for="exampleFormControlInput1">Supplier Description</label>
                                            <input type="text" class="form-control" id="desc" name="description" placeholder="Enter supplier description">
                                    </div>    
                                    <div class="form-group">
                                            <label for="exampleFormControlInput1">Phone</label>
                                            <input type="text" class="form-control" id="phone" name="phone" placeholder="Enter phone">
                                    </div> 
                                    
                                    <div class="form-group">
                                            <label for="exampleFormControlInput1">Date of Registerd</label>
                                            <input type="date" class="form-control" id="dateofreg" name="dateofreg" placeholder="Enter categorie Name">
                                    </div>
                                      
                                    <div class="form-group">
                                            <label for="exampleFormControlSelect1">Choose Shop</label>
 
                                            <select class="form-control" name="shopid" id="exampleFormControlSelect1" required>
                                                <?php
if($fetchshopcount> 0) {
while($fetchshop = mysqli_fetch_array($fetchshopq)) {
    ?>
                                                <option value="<?php echo $fetchshop['sh_id']?>"><?php echo $fetchshop['name']?></option>

                                                <?php
}
}
                                                ?>
                                            </select>
                                        </div>
                                        
                                       
                                        <button type="submit"  name="addsupplier" class="btn btn-primary">Submit</button>
                                    </form>
                                </div>
                            </div>
                        </div>
<!-- Add shop php code -->
<?php
if (isset($_POST["addsupplier"])) {
$name= sanitizeInput($_POST['name']);
$description= sanitizeInput($_POST['description']);
$dateofreg= sanitizeInput($_POST['dateofreg']);
$dateofreg= sanitizeInput($_POST['phone']);
$shopid= sanitizeInput($_POST['shopid']);
$phone= sanitizeInput($_POST['phone']);
$date = date("d/m/Y"); 


$addsup="INSERT INTO `supplier`(`name`, `desc`, `regdate`, `date`, `sh_id`,`phone`)
VALUES  ('$name','$description','$dateofreg','$date','$shopid','$phone')";
$addsupq=mysqli_query($conn, $addsup);
if($addsupq==1) {
?>

<script>
    alert("AddedSucessfully"); 
    window.location='managesupplier.php';
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
    
    
<!-- Display categorie php code end -->


                        <div class="col-lg-6">
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
                                    <div class="spur-card-title">Supplier</div>
                                </div>
                               
                                <div class="card-body ">
                                    <table class="table table-hover table-in-card">
                                        <thead>
                                            <tr>
                                                <th scope="col">Supplier Name</th>
                                                <th scope="col">Phone</th>
                                                
                                                <th scope="col">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>

<?php
if(isset($_POST['search'])) {
    $searchid=sanitizeInput($_POST['shopid']);
    $supfetch="Select * from supplier where sh_id='$searchid' order by sup_id desc";
}
else
{

$supfetch="Select * from supplier";
}
$supfetchq=mysqli_query($conn, $supfetch);
if(mysqli_num_rows($supfetchq) > 0) {
while($supfetchq1 = mysqli_fetch_array($supfetchq)) {


    ?>

<tr>
                                                
                                                <td><?php echo $supfetchq1['name'] ?></td>
                                                <td><?php echo $supfetchq1['phone'] ?></td>
                                                <td> <a href="" onclick="return confirmDelete(<?php echo $supfetchq1['sup_id'] ?>)">Delete</a></td>
                                           
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
                
                window.location="deletedata.php?supdelete="+id;
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