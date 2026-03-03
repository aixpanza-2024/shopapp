<?php
include("web_shopadmin_header.php");
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
  <!-- <form class="d-flex flex-grow-1 me-3 order-2 order-sm-1  order-lg-1" role="search">
    <div class="input-group w-100 w-md-50 w-lg-50">
      <input type="search" class="form-control searchInput1" id="searchInput1" placeholder="Search" aria-label="Search">
      <button class="btn btn-outline-light" type="submit">
        <i class="fa fa-search"></i>
      </button>
    </div>
  </form> -->

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

</div>




    <!-- Listing Main Category Ends-->




<div class="container-fluid">
<div class="row g-0">


<!----Listing items Start------>


<div class="col-md-10 col-sm-10">
<!-- <div id="printcategory">
</div> -->
  


    <!----Search  and option for changing language Starts here   ------>
<?php
include("../shopadmin_adddaily.php");

?>
<!----Search Ends------>


</div>

<!-- <div class="d-none d-md-block"> -->
<!-- <div class="hrline mt-2"></div> -->




<!-- </div> -->


<!-- listing the cart products -->
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
</div>


</main>

<!-------------Footer -------------------->

<!-- js for active and incative of caegory -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>



<!-- js for active and incative of caegory ends -->
<?php

include_once("web_shopadmin_footer.php");
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



