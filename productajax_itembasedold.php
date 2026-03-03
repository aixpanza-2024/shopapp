<!-- code for displaying products based on category -->
<?php

include_once("db.php");

if(isset($_POST['divId']))
{
$data= $_POST['divId'];
$proddisplay ="SELECT * FROM `products` where name='$data'";
}
elseif(isset($_POST['prodname']))
{
  $data= $_POST['prodname'];

  $data= mysqli_escape_string($conn,$_POST['prodname']);
$proddisplay ="SELECT * FROM `products` WHERE column_name LIKE '%data'";

}
elseif(isset($_POST['prodname1']))
{
  $data= $_POST['prodname1'];
  $proddisplay ="SELECT * FROM `products` where p_id='$data'";
}
else
{
  $proddisplay ="SELECT * FROM `products`";
}


$resultproddisply=mysqli_query($conn,$proddisplay);
$countingprod=mysqli_num_rows($resultproddisply);
if($countingprod==0)
{
echo "No Products are found in this category";
}
else
{

  while($displayprodrow=mysqli_fetch_array($resultproddisply))
  {
?>

  


<!-- code for displaying the product -->

<div class="col-4 card-products">
  <div class="col-12">
<div class="card">
  <img src="../images/logo_small.jpg" class="card-img-top" alt="...">
  <div class="card-body cardpad">
    <h5 class="card-title"><?php
        echo $displayprodrow['name'];
        ?></h5>
    <p class="card-text">
    <?php
        echo "₹".$displayprodrow['saleprice'];
        ?>    
    </p>
    <!-- <a href="#" class="btn btn-success">+</a>
    <a href="#" class="btn btn-danger">-</a> -->
  </div>
</div>
</div>
</div>
<!-- code for displaying the product ends here -->


  <?php
  }
}
  ?>








