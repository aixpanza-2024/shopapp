<?php
include("web_header.php");
?>
<main>

<!-----Billing Area--->

<div class="row">
<div class="col-md-4 col-sm-4">
<div class="col-12 col-sm-12 main-prod">

<div class="row mt-2">
<!-- <div class="col-2">
<?php
 // include_once("../master_mobnav.php");
  ?>
</div> -->
<div class="col-6 bill-card-recent">
<?php
  include_once("../master_mobnav.php");
  ?>
  Inv :0001/2024
</div>
<div class="col-6 bill-card-name">
  Guest
</div>
</div>

<div class="card mt-2">
  <div class="card-body">
    <h4 class="card-title">
      <div class="row">
        <div class="col-7 prodname">
        Product name
        
        <!-- <p class="card-text prodname">x1</p> -->
</div>
<div class="col-5 bill-card-name prodname">
₹ Amount
</div>
  
  </h4>
    
  </div>
</div>

<div class="card mt-2">
  <div class="card-body">
    <h4 class="card-title">
      <div class="row">
        <div class="col-7 prodname">
        Product name
        <!-- <p class="card-text prodname">x1</p> -->
</div>
<div class="col-5 bill-card-name prodname">
₹ Amount
</div>
  
  </h4>
    
  </div>
</div>

<div class="card mt-2">
  <div class="card-body">
    <h4 class="card-title">
      <div class="row">
        <div class="col-7 prodname">
        Product name
        <!-- <p class="card-text prodname">x1</p> -->
</div>
<div class="col-5 bill-card-name prodname">
₹ Amount
</div>
  
  </h4>
    
  </div>
</div>


<div class="card mt-2">
  <div class="card-body">
    <h4 class="card-title">
      <div class="row">
        <div class="col-7 prodname">
        Product name
        <!-- <p class="card-text prodname">x1</p> -->
</div>
<div class="col-5 bill-card-name prodname">
₹ Amount
</div>
  
  </h4>
    
  </div>
</div>




</div>

<div class="col-12 col-sm-12">
<div class="card mt-2">
  <div class="card-body p-2">
    <h4 class="card-title">
      <div class="row">
        <div class="col-7">
       <div class="points"> Points </div><br>
        Grand Total

        

        
</div>
<div class="col-5 bill-card-name">
  <div class="row">
<div class="col-12">
<div class="points">100 pts<a href=""> (Use Points)</a></div>

</div>
</div>
      
      <br>
₹ 100
<br><div class="points">Paid with points: ₹ 10</div>4
<!----Cash/Upi------>
  <div class="row">
<div class="col-12">
<?php
include("../master_cashmenubill.php");
?>
</div>
<!--Cash/Upi ends--->

<!----Credit payment ------>
<!-- <div class="col-6">
<?php
//include("../master_creditmenubill.php");
?>
</div> -->
<!----Credit payment ends ------>
</div>


</div>
</div>
  
  </h4>
    
  </div>
</div>
</div>

<!-----------Mob Nav------------->
<div class="fixed-bottom bg-light p-2 mobile-footer d-block d-md-none">
        <div class="row">
<!--             
<!-----Mobile New Bill------>
<div class="col-2">
<?php
include("../master_newbill.php");
?>
</div>
<!-----New Bill end------>



<!-----Mobile Customer st------>
<div class="col-2">
<?php
include("../master_customermenubill.php");
?>
</div>
<!-----Customer end------>



<!----Mobile Cash/Upi------>
<div class="col-2">
<?php
include("../master_cashmenubill.php");
?>
</div>

<!--Cash/Upi ends--->

<!---Mobile Paylater--->
<div class="col-2">
<?php
include("../master_paylatermenubill.php");
?>
</div>
<!---Paylater Ends--->


<!--- Mobile Credit Payments--->
<div class="col-2">
<?php
include("../master_creditmenubill.php");
?>
</div> 

<!---Credit Payments Ends--->


<!--- Mobile Expense--->
<div class="col-2">
<?php
include("../master_expensemenubill.php");
?>
</div>
<!---Expense Ends--->
    </div>
      </div>
<!------Mob NAv ends----------->
</div>






<!----Menu Starts------>


<div class="col-md-8 col-sm-4 verticalline">




    <!----Search Starts------>
<?php
include("../master_searchmenubill.php");
?>
<!----Search Ends------>



<div class="d-none d-md-block">
<div class="hrline mt-2"></div>

 <!----Newbill Starts------>
 <div class="col-2">
 <?php
include("../master_newbill.php");
?>
</div>
<!----Newbill Ends------>
<!-----Customer st------>
<div class="col-2">
<?php
include("../master_customermenubill.php");
?>
</div>
<!-----Customer end------>

<!----Cash/Upi------>
<div class="col-2">
<?php
include("../master_cashmenubill.php");
?>
</div>

<!--Cash/Upi ends--->

<!---Paylater--->
<div class="col-2">
<?php
include("../master_paylatermenubill.php");
?>
</div>

<!---Paylater Ends--->


<!---Credit Payments--->
<div class="col-2">
<?php
include("../master_creditmenubill.php");
?>
</div>
<!---Credit Payments Ends--->
<div class="d-none d-md-block">
<div class="hrline mt-2"></div>

<!---Expense--->
<?php
include("../master_expensemenubill.php");
?>

<!---Expense Ends--->


<!---Profile--->
<?php
include("../master_profile.php");
?>

<!---Profile Ends--->


<!---Admin Panel--->

        <a href="../Adminpanel/index.php" class="btn btn-outline btn-chai-menu mt-3">Admin Panel</a>

<!---Admin Panel Ends--->


<!---Logout--->
<?php
include("../logout.php");
?>

<!---Logout Ends--->



</div>




</div>



<!-----Menu Ends---->

</div>

<!-----Billing Area Ends--->





</main>

<!-------------Footer -------------------->

<?php

include_once("web_footer.php");
?>

<!-------------Footer Ends-------------------->