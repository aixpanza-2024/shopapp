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


                                    <form method="POST">
                                        <div class="form-group">
                                            <label for="exampleFormControlInput1">Shop Name</label>
                                            <input type="text" class="form-control" id="Shop Name" name="name" placeholder="Enter Shop Name" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="exampleFormControlInput1">Shop Address</label>
                                            <textarea class="form-control" id="exampleFormControlTextarea1" name="address" rows="3"></textarea>
                                        
                                        </div>

                                        <div class="form-group">
                                            <label for="exampleFormControlInput1">City</label>
                                            <input type="text" class="form-control" id="City" name="city" placeholder="Enter City Name">
                                        </div>

                                        <div class="form-group">
                                            <label for="exampleFormControlInput1">State</label>
                                            <input type="text" class="form-control" id="State" name="state" placeholder="Enter State Name">
                                        </div>
                                        <div class="form-group">
                                            <label for="exampleFormControlInput1">Country</label>
                                            <input type="text" class="form-control" id="country" placeholder="Enter Country Name">
                                        </div>
                                        <div class="form-group">
                                            <label for="exampleFormControlInput1">Phone</label>
                                            <input type="number" class="form-control" id="Phone" name="phone" placeholder="Enter Phone" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="exampleFormControlInput1">Registration Date</label>
                                            <input type="text" class="form-control" id="regdate" name="regdate" placeholder="Enter Registration Date" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="exampleFormControlInput1">License No</label>
                                            <input type="text" class="form-control" id="license" name="license" placeholder="License Number" required>
                                        </div>

                                        

                                        <div class="form-group">
                                            <label for="exampleFormControlSelect1">Status</label>
                                            <select class="form-control" name="status" id="exampleFormControlSelect1" required>
                                                <option>Select</option>
                                                <option>Active</option>
                                                <option>Temporarly Closed</option>
                                            </select>
                                        </div>
                                        <button type="submit"  name="addshop" class="btn btn-primary">Submit</button>
                                    </form>
                                </div>
                            </div>
                        </div>
<!-- Add shop php code -->
<?php
if (isset($_POST["addshop"])) {
$name= sanitizeInput($_POST['name']);
$address = sanitizeInput($_POST['address']);
$city = sanitizeInput($_POST['city']);
$state = sanitizeInput($_POST['state']);
$country = sanitizeInput($_POST['phone']);
$phone = sanitizeInput($_POST['phone']);
$regdate = sanitizeInput($_POST['regdate']);
$license = sanitizeInput($_POST['license']);
$status =  sanitizeInput($_POST['status']);
$date = date("d/m/Y"); 


$addshop="INSERT INTO `shops`(`name`, `Address`, `city`, `state`, `country`, `phone`, `status`, `date`, `regdate`, `license`)
 VALUES ('$name','$address','$city','$state','$country','$phone','$status','$date','$regdate','$license')";
$addshopq=mysqli_query($conn, $addshop);
if($addshopq==1) {
?>

<script>
    alert("Registerd Sucessfully"); 
    window.location='manageshops.php';
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
    
    
<!-- Add shop php code end -->


                        <div class="col-lg-6">
                            <div class="card spur-card">
                                <div class="card-header">
                                    <div class="spur-card-icon">
                                        <i class="fas fa-table"></i>
                                    </div>
                                    <div class="spur-card-title">Shops</div>
                                </div>
                                <div class="card-body ">
                                    <table class="table table-hover table-in-card">
                                        <thead>
                                            <tr>
                                                <th scope="col">Name</th>
                                                <th scope="col">Address</th>
                                                <th scope="col">Reg Date</th>
                                                <th scope="col">License</th>
                                                <th scope="col">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>

<?php
include_once("shopapi.php");
if($fetchshopcount> 0) {
while($fetchshop = mysqli_fetch_array($fetchshopq)) {


    ?>

<tr>
                                                
                                                <td><?php echo $fetchshop['name'] ?></td>
                                                <td><?php echo $fetchshop['Address'] ?></td>
                                                <td><?php echo $fetchshop['regdate'] ?></td>
                                                <td><?php echo $fetchshop['license'] ?></td>
                                                <td> <a href="manageshops.php?shopof=<?php echo $fetchshop['name'] ?>">Edit</a></td>
                                           
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
 <!-- Add Shops End -->


<!-- footer  -->
<?php
include_once("Layout/footer.php");
?>
<!-- footer ends -->