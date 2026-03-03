<?php
include_once("Layout/header.php");

include_once("Layout/navbar.php");
?>

 <!-- Add categorie -->
 <div class="container">
                    <div class="row">
                        <div class="col-xl-6">
                            <div class="card spur-card">
                                <div class="card-header">
                                    <div class="spur-card-icon">
                                        <i class="fas fa-chart-bar"></i>
                                    </div>
                                    <div class="spur-card-title">Add Categorie</div>
                                </div>
                                <div class="card-body ">


                                    <form method="POST">
                                    

                                    <div class="form-group">
                                            <label for="exampleFormControlInput1">Categorie Name</label>
                                            <input type="text" class="form-control" id="categorie" name="categorie" placeholder="Enter categorie Name">
                                        </div>

                                      

                                        
                                       
                                        <button type="submit"  name="addcategorie" class="btn btn-primary">Submit</button>
                                    </form>
                                </div>
                            </div>
                        </div>
<!-- Add shop php code -->
<?php
if (isset($_POST["addcategorie"])) {
$name= sanitizeInput($_POST['categorie']);
$date = date("d/m/Y"); 


$addcat="INSERT INTO `categorie`(`categorie`, `date`) 
VALUES  ('$name','$date')";
$addcatq=mysqli_query($conn, $addcat);
if($addcatq==1) {
?>

<script>
    alert("AddedSucessfully"); 
    window.location='managecategorie.php';
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
                            <div class="card spur-card">
                                <div class="card-header">
                                    <div class="spur-card-icon">
                                        <i class="fas fa-table"></i>
                                    </div>
                                    <div class="spur-card-title">Categorie</div>
                                </div>
                                <div class="card-body ">
                                    <table class="table table-hover table-in-card">
                                        <thead>
                                            <tr>
                                                <th scope="col">Categorie Name</th>
                                                
                                                <th scope="col">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>

<?php
include_once("categorieapi.php");
if(mysqli_num_rows($catfetchq) > 0) {
while($catfetchq1 = mysqli_fetch_array($catfetchq)) {


    ?>

<tr>
                                                
                                                <td><?php echo $catfetchq1['categorie'] ?></td>
                                                <td> <a href="" onclick="return confirmDelete(<?php echo $catfetchq1['cat_id'] ?>)">Delete</a></td>
                                           
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
                
                window.location="deletedata.php?catdelete="+id;
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