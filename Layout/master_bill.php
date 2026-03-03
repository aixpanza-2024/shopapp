<?php
include("web_header.php");
?>
<main>

<!-----Advanced Income/Expense with header color--->

<div class="row headercolor">
<nav class="navbar navbar-expand-lg navbar-light" style="background-color: #b8860b !important;">
  <div class="container-fluid">

   <!-- Container -->
<div class="d-flex w-100 flex-wrap align-items-center">

  <!-- Search bar (desktop first, mobile second) -->


  <div class="d-flex me-3">
    <img src="../images/logo.jpg" alt="Logo" class="img-fluid" style="max-height: 50px;">
  </div>
  <form class="d-flex flex-grow-1 me-3 order-2 order-sm-1  order-lg-1" role="search">
    <div class="input-group w-100 w-md-50 w-lg-50">
      <input type="search" class="form-control searchInput1" id="searchInput1" placeholder="Search" aria-label="Search">
      <button class="btn btn-outline-light" type="submit">
        <i class="fa fa-search"></i>
      </button>
    </div>
  </form>

  <!-- Buttons (desktop second, mobile first) -->
  <div class="d-flex align-items-center ms-auto order-1 order-sm-2 order-lg-2">
    <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#myModal">
      <i class="fa fa-language"></i>
    </button>
     <!-- Hamburger menu -->
      <?php include_once("../master_mobnav.php"); ?>
  </div>



     
   

  </div>
</nav>





 <!-- advanced income/expense setup  -->

<!-- Listing  Main Category starts  -->
<div class="container-fluid mt-3">
  <div class="row g-3 text-center">


  
<?php

$categorydisplay ="SELECT * FROM `categorie`";
$resultcatdisply=mysqli_query($conn,$categorydisplay);
$countingcat=mysqli_num_rows($resultcatdisply);
if($countingcat==0)
{
echo "Please contact Shop Owner for proceeding";
}
else
{

  while($displaycatrow=mysqli_fetch_array($resultcatdisply))
  {
?>
    <div class="col-md-3 col-sm-3 col-3 clickloadBoxes"  >
    <!-- the data from this div is passed to "productajax.php" using class name clickloadBoxes  from the "ajaxreq.php" using jquery -->
          <h5 class="card-title">
         
      <img src="../images/<?php echo $displaycatrow['Image'] ?>" class="cat-img">
      <input type="hidden" value="<?php echo $displaycatrow['cat_id']?>" class="boxfetch">  
      <br><center>
     <h6 class="translated-text" style="text-transform: capitalize;font-weight: bold;">
     <?php
      echo $displaycatrow['categorie'];
        ?>
        </h5></center>
      </h5>
        <!-- </div>
      </div> -->
    </div>
  <?php
  }
}

?>

  </div>
</div>
</div>




    <!-- Listing Main Category Ends-->




<div class="container-fluid">
<div class="row g-0">


<!----Listing items Start------>


<div class="col-md-8 col-sm-8">
<!-- <div id="printcategory">
</div> -->
  


    <!----Search  and option for changing language Starts here   ------>
<?php
include("../master_searchmenubill.php");

?>
<!----Search Ends------>


</div>

<!-- <div class="d-none d-md-block"> -->
<!-- <div class="hrline mt-2"></div> -->




<!-- </div> -->


<!-- listing the cart products -->
<div class="col-md-4 col-sm-4">
  <div class="col-12 col-sm-12 main-prod">
    <div class="card mt-2 p-3 position-relative" style="min-height: 400px;" > 
<div id="cart-container"></div>
<!-- design for this area is taken from the fechcart page  -->
<!-- javascript function for dispalying the product is written in ajaxreq.php with function loadCart() -->

      <!-- Proceed Button -->
       <!-- <div class="proceed-btn-wrapper position-absolute w-100 d-flex" style="bottom: 0; left: 0; padding: 10px; background: #fff; box-shadow: 0 -1px 5px rgba(0,0,0,0.1);">
  
      </div> -->
      
</div>
    </div>
  </div>
</div>

<!-- JavaScript -->
<script>
  function changeQty(btn, delta) {
    var input = btn.closest('.input-group').querySelector('.qty-input');
    var current = parseInt(input.value);
    if (!isNaN(current)) {
      var newVal = current + delta;
      if (newVal >= 0) {
        input.value = newVal;
      }
    }
  }
</script>






</div>








<!-----Menu Ends---->

</div>
</div>
</main>


<!-----Billing Area Ends--->


<!-- Modal -->
<div class="modal fade" id="myModal" tabindex="-1" aria-labelledby="myModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <!-- Modal Header -->
      <div class="modal-header">
        <h5 class="modal-title" id="myModalLabel">Qalb Chai Language Settings</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body">
       <div id="google_translate_element"></div> 
      </div>

      <!-- Modal Footer -->
      <!-- <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary">Save changes</button>
      </div> -->

    </div>
  </div>

<!-------------Footer -------------------->

<!-- js for active and incative of caegory -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>



<!-- js for active and incative of caegory ends -->
<?php

include_once("web_footer.php");
?>

<!-------------Footer Ends-------------------->




<!-- <div class="row mt-2"> -->
  
<!-- <div class="col-2">
  <p>Checkout</p> -->
<?php
//  include_once("../master_mobnav.php");
  ?>
<!-- </div> -->
<!-- <div class="col-6 bill-card-recent">
<p>Product Name</p>
</div>
<div class="col-6 bill-card-name">
<p>Product Name</p>
</div> -->



