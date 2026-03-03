<?php
include_once("Layout/header.php");

include_once("Layout/navbar.php");


if(isset($_SESSION["userpermission"]) && isset($_SESSION["usersession"]))
{

if($_SESSION['userpermission']=="Super Admin")
{

    ?>
<!-- category delete -->
    <?php
if(isset($_GET["catdelete"])){
    $catid = $_GET["catdelete"];
        $deletecat="Delete from categorie where cat_id='$catid'";
        $deletecatresult=mysqli_query($conn,"$deletecat");
        if($deletecatresult==true){
            ?>
            <script>
            alert("Data Deleted Sucessfully!");
            window.location="managecategorie.php";
            </script>
            <?php
        }
    }

    ?>

<!-- category Ends Here  -->

<!-- Supplier Delete -->
    <?php
if(isset($_GET["supdelete"])){
    $supid = $_GET["supdelete"];
        $deletesup="Delete from supplier where sup_id='$supid'";
        $deletesupresult=mysqli_query($conn,"$deletesup");
        if($deletesupresult==true){
            ?>
            <script>
            alert("Data Deleted Sucessfully!");
            window.location="managesupplier.php";
            </script>
            <?php
        }
    }
    ?>


<!-- Supplier Delete End-->

<!-- Supplier Delete -->
<?php
if(isset($_GET["proddelete"])){
    $prodid = $_GET["proddelete"];
        $deleteprod="Delete from products where p_id='$prodid'";
        $deleteprodresult=mysqli_query($conn,"$deleteprod");
        if($deleteprodresult==true){
            ?>
            <script>
            alert("Data Deleted Sucessfully!");
            window.location="manageproducts.php";
            </script>
            <?php
        }
    }
    ?>


<!-- Supplier Delete End-->
    <?php

}
else
{
    ?>
<script>
alert("You cant perform this action");
var recentPageURL = document.referrer;
window.location=recentPageURL;
    </script>
    <?php
}
}
?>
