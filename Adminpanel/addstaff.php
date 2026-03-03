<?php
include_once("Layout/header.php");

include_once("Layout/navbar.php");
?>

 <!-- Add Staffs -->
 <div class="container">
                    <div class="row">
                        <div class="col-xl-6">
                            <div class="card spur-card">
                                <div class="card-header">
                                    <div class="spur-card-icon">
                                        <i class="fas fa-chart-bar"></i>
                                    </div>
                                    <div class="spur-card-title">Add Staffs</div>
                                </div>
                                <div class="card-body ">

                                

                                    <form method="POST" enctype="multipart/form-data">
                                        <div class="form-group">
                                            <label for="exampleFormControlInput1">Name</label>
                                            <input type="text" class="form-control"  id="name" name="name" placeholder="Enter Name" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="exampleFormControlInput1">Age</label>
                                            <input type="text" class="form-control" id="age" name="age" placeholder="Enter age">
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="gender" id="male" value="male" checked />
                                            <label class="form-check-label" for=""> Male </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="gender" id="male"  value="female"/>
                                            <label class="form-check-label" for=""> Female </label>
                                        </div>
                                        <div class="form-group">
                                            <label for="exampleFormControlInput1">Username</label>
                                            <input type="text" class="form-control" id="username" name="username" placeholder="Enter Username" required>
                                        </div>


                                        <div class="form-group">
                                            <label for="exampleFormControlInput1">Password</label>
                                            <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="exampleFormControlInput1">Joining Date</label>
                                            <input type="date" class="form-control" id="joining date" name="joiningdate" placeholder="Enter Joining Date" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="exampleFormControlInput1">Designation</label>
                                            <input type="text" class="form-control" id="designation"  name="designation" placeholder="Enter Designation Name">
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


                                         <div class="form-group">
                                            <label for="exampleFormControlSelect1">Permission Role</label>
 
                                            <select class="form-control" name="shoprole" id="exampleFormControlSelect1" required>
                                               
                                                <option value="">--Select--</option>
                                                <option value="Super Admin">Super Admin</option>
                                                <option value="Super Admin">Admin</option>
                                                <option value="Super Admin">Sales</option>
                                                <option value="Super Admin">Logistics</option>

                                        
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="exampleFormControlInput1">Health Card</label>
                                            <input type="file" class="form-control" id="healthcard" name="healthcard" placeholder="Enter health" >
                                        </div>


                                        <div class="form-group">
                                            <label for="exampleFormControlInput1">Identification Card</label>
                                            <input type="file" class="form-control" id="identificationcard" name="identificationcard" placeholder="Enter Identification " >
                                        </div>


                                        <div class="form-group">
                                            <label for="exampleFormControlInput1">Phone</label>
                                            <input type="number" class="form-control" id="Phone" name="phone" placeholder="Enter Phone" required>
                                        </div>




                                        <div class="form-group">
                                            <label for="exampleFormControlInput1">Address</label>
                                            <textarea class="form-control" id="exampleFormControlTextarea1" name="address" rows="3"></textarea>
                                        
                                        </div>

                                       
                                       
                                     

                                       
                                       
                                     

                                        <div class="form-group">
                                            <label for="exampleFormControlInput1">Profile Picture</label>
                                            <input type="file" class="form-control" id="profile" name="profile" placeholder="Enter profile " required>
                                        </div>

                                    
                                        <button type="submit"  name="addstaff" class="btn btn-primary">Submit</button>
                                    </form>
                                </div>
                            </div>
                        </div>
<!-- Add staff php code -->
<?php
if (isset($_POST["addstaff"])) {
$name= sanitizeInput($_POST['name']);
$age= sanitizeInput($_POST['age']);
$gender= sanitizeInput($_POST['gender']);
$username= sanitizeInput($_POST['username']);
$password=sanitizeInput($_POST['password']);
$joiningdate=sanitizeInput($_POST['joiningdate']);
$addeddate= date('d/m/y');
$designation=sanitizeInput($_POST['designation']);
$shopid=sanitizeInput($_POST['shopid']);

$identificationcard=$_FILES["identificationcard"]["name"];
move_uploaded_file($_FILES["identificationcard"]["tmp_name"],
"upload/".$_FILES["identificationcard"]["name"]);

$profile=$_FILES["profile"]["name"];
move_uploaded_file($_FILES["profile"]["tmp_name"],
"upload/".$_FILES["profile"]["name"]);


$healthcard =$_FILES["healthcard"]["name"];
move_uploaded_file($_FILES["healthcard"]["tmp_name"],
"upload/".$_FILES["healthcard"]["name"]);

$phone=sanitizeInput($_POST["phone"]);

$address = sanitizeInput($_POST['address']);

$shoprole=sanitizeInput($_POST["shoprole"]);


echo $addstaff="INSERT INTO `staff`(`name`, `age`, `gender`, `username`, `password`, `joiningdate`, `addeddate`, `designation`, `addedby`, `healthcard`, `identificationcard`, `profile`, `status`, `phone`, `Address`, `sh_id`,`shoprole`)
 VALUES ('$name','$age','$gender','$username','$password','$joiningdate','$addeddate','$designation','staffname','$healthcard','$identificationcard','$profile',1,'$phone','$address','$shopid','$shoprole')";
$addstaffq=mysqli_query($conn, $addstaff);
if($addstaffq==1) {
?>

<script>
    alert("Staff added sucessfully"); 
    window.location='addstaff.php';
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
    
    
<!-- Add staff php code end -->


                        <div class="col-lg-6">
                             <div class="row">
                                        <div class="col-md-2"></div>
                                        <div class="col-md-6">
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
                                   
                                    <div class="spur-card-title">
                                        View 
                                        <?php
                                       if(isset($_POST['search'])) {
    $searchid=sanitizeInput($_POST['shopid']);
$fetchshop = "select * from shops where sh_id='$searchid' order by sh_id desc";
$fetchshopq=mysqli_query($conn, $fetchshop);
$fetchshopcount=mysqli_num_rows($fetchshopq);
if($fetchshopcount== 1) {
                                            
    
                                            $fetchshop1 = mysqli_fetch_array($fetchshopq);
                                        
                                        echo $fetchshop1['name'];
                                            }
                                            else
                                            {

                                            }
                                        }
                                        ?>
                                        
                                        Staff
                                      

                                    </div>
                                </div>
                                <div class="card-body ">
                                    <table class="table table-hover table-in-card">
                                        <thead>
                                            <tr>
                                                <th scope="col">Name</th>
                                                <th scope="col">Address</th>
                                                <th scope="col">Age</th>
                                                <th scope="col">Phone</th>
                                                <th scope="col">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>

<?php
if(isset($_POST['search'])) {
    $searchid=sanitizeInput($_POST['shopid']);
$fetchstaff = "select * from staff where sh_id='$searchid' order by st_id desc";
}
else
{
$fetchstaff = "select * from staff order by st_id desc";  
}
$fetchstaffq = mysqli_query($conn, $fetchstaff);
$fetchstaffcount= mysqli_num_rows($fetchstaffq);
if($fetchstaffcount> 0) {
while($fetchstaffof = mysqli_fetch_array($fetchstaffq)) {

    ?>

<tr>
                                                
                                                <td><?php echo $fetchstaffof['name'] ?></td>
                                                <td><?php echo $fetchstaffof['Address'] ?></td>
                                                <td><?php echo $fetchstaffof['age'] ?></td>
                                                <td><?php echo $fetchstaffof['phone'] ?></td>
                                                <td> <a href="manageshops.php?shopid=<?php echo $fetchstaffof['st_id'] ?>">Edit</a></td>
                                         
                                            </tr>
    <?php
}
}
else{
    echo "No Data Found ";
}
?>

                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
 <!-- Add Staff End -->


<!-- footer  -->
<?php
include_once("Layout/footer.php");
?>
<!-- footer ends -->