<?php
include_once("Layout/header.php");
include_once("db.php");
?>


        <main>

<div class="container login-align ">
    <div class="row justify-content-center">
        <div class="col-md-6 login-div shadow p-3 mb-5 bg-white rounded">
        <div class="login-head">
<img
    src="images/logo_small.jpg"
    class="img-fluid rounded-top login-image"
    alt="" />

</div>
        <div class="login-head">
Qalb Chai
</div>
<!-- action="Layout/master_bill.php" -->
            <form  method="post">
                <div class="form-group mt-2 ">
                    <label for="username">Username:</label>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username">
                </div>
                <div class="form-group mt-2">
                    <label for="password">Password:</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password">
                </div>
                <div class="d-flex justify-content-end">
                <button type="reset" class="btn btn-chai
                 mt-2">Reset <i class="fa fa-refresh" aria-hidden="true"></i></button>
                 <div>&nbsp;</div>

                <button type="submit" name="login" class="btn btn-outline btn-chai
                 mt-2">Login <i class="fa fa-arrow-circle-right " aria-hidden="true"></i></button>
</div>
            </form>
        </div>
    </div>
</div>

<!-- login process code -->
<?php
if (isset($_POST["login"])) {
    $username =  sanitizeInput($_POST['username']);
        $password = sanitizeInput($_POST['password']);
            $loginq = "select * from staff where username='$username' and password = '$password'";
                $result = mysqli_query($conn, $loginq);
                    if (mysqli_num_rows($result) > 0) {
                        $row = mysqli_fetch_assoc($result);
                        $userid = $row['st_id'];
                        $_SESSION['usersession'] = $userid;
                        $name = $row['name'];
                        $_SESSION['username'] = $name;
                        $permission =$row['shoprole'];
                        $_SESSION['userpermission'] = $permission;
                        $selectshop =$row['sh_id'];
                        $_SESSION['selectshop']=$selectshop;
                        ?>
<script>
    
    window.location="Layout/master_bill.php"
    </script>
<?php
                        

}
else {
?>
<script>
    alert("Enterd Username and password is wrong");
    window.location="index.php"
    </script>
<?php
}
}
?>
<!-- login process code end -->



    </main>
    
    <!-----Test------->
    


    <!---------->


       
        <?php
include_once("Layout/footer.php");
?>