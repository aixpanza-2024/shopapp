<script>




// initial loading
$(document).ready(function() {
    loadCart();

    // Auto-trigger Tea category (cat_id = 1) on page load
    $('.clickloadBoxes').filter(function() {
        return $(this).find('.boxfetch').val() == '1';
    }).first().trigger('click');
});



//for listing the product to the cart
var currentCategoryRequest = null; // track pending AJAX to prevent race condition

$(document).on('click', '.clickloadBoxes', function() {

  // Highlight the active category
  $('.clickloadBoxes').removeClass('active-box');
  $(this).addClass('active-box');

  // Abort any previous pending category request to prevent race condition
  // (e.g. slow cat2 query overwriting cat1 results after cat1 was already selected)
  if (currentCategoryRequest) {
    currentCategoryRequest.abort();
    currentCategoryRequest = null;
  }

  // Get all the divs with class 'box'
  var categoryId = $(this).find('.boxfetch').val();

      // AJAX call using jQuery
      currentCategoryRequest = $.ajax({
        url: '../productajax.php', // Your server endpoint
        type: 'GET',
        data:  { divId: categoryId },
        success: function(response) {
            console.log("sucesss");
          $('#printcategory').html(response);
          currentCategoryRequest = null;

        },
        error: function(xhr, status, error) {
          if (status === 'abort') return; // ignore intentional aborts
          console.error('Error Status: ' + status);
          console.error('Error Thrown: ' + error);
        }
      });

    });



    // for  listing the product using search



    $(document).on('keyup', '.searchInput1', function() {
      
  // Get all the divs with class 'box'
     var prodname = $(this).val();


      // AJAX call using jQuery
      $.ajax({
        url: '../productajax.php', // Your server endpoint
        type: 'GET',
        data:  { prodsearch: prodname },
        success: function(response) {
            console.log("sucesss");
          $('#printcategory').html(response);
          

        },
        error: function(error) {
          console.error('Error Status: ' + status); // e.g. 'error'
        console.error('Error Thrown: ' + error);  // e.g. the actual error message
        console.error('Response: ' + xhr.responseText);  
        }
      });

  

    });





//for adding the product to the cart
    
  $(document).on('click', '.product-card', function () {


        var prodId = $(this).data("id");
        var action = $(this).data("action");
 //alert("Hello clocked"+prodId)
        $.ajax({
        
            url: "../addtocart.php", // Your server-side script
            type: "POST",
            data: { product_id: prodId,
              action: action
             },
            success: function(response){
                //alert("Product added successfully!");
                loadCart();
                
                console.log(response);
            },
            error: function(xhr, status, error){
                alert("Error adding product.");
                console.log(xhr.responseText);
            }
        });
    });





  //for loading the cart to load the items
function loadCart() {
    $.ajax({
        url: '../fetchcart.php',
        type: 'GET',
        success: function(data) {
            $('#cart-container').html(data);
              $('.listitems').show();
      $('.listpayments').hide();
      $('.paymentbuttons').show();
       $('.billofheader').hide();
       $('.newbill').hide();
      
      $('.printbutton').hide();
       $('.billprintof').hide();
       $('.billoffooter').hide();
 

        },
        error: function() {
            $('#cart-container').html('<p>Error loading cart</p>');
        }
    });
}




  //for hidding the cart and show the billing area
function billpayment() {
         $('.listitems').hide();
      $('.listpayments').show();
        $('.billprintof').hide();
         $('.billofheader').hide();
         $('.billoffooter').hide();
         $('.newbill').hide();
      
       if ($('.paymentbuttons').length > 0) {
      console.log("paymentbuttons found");
      $('.paymentbuttons').hide();
    } else {
      console.log("paymentbuttons NOT found");
    }
    
    $('.printbutton').show();
    
      
      
}

function listbackpayment(){
   $('.listitems').show();
    $('.listpayments').hide();
      $('.paymentbuttons').show();
        $('.printbutton').hide();
        $('.billprintof').hide();
        $('.billofheader').hide();
        $('.billoffooter').hide();
         $('.newbill').hide();
}


function completepayment() {

  
    // Hide buttons while printing
    $('.printbutton').hide();
      $('.listitems').hide();
        $('.listpayments').hide();
          $('.paymentbuttons').hide();
            $('.billprintof').show();
               $('.billofheader').show();
               $('.billoffooter').show();
               $('.newbill').show();
               

    // Get the printable content
    // var printContents = document.getElementById("bill-content").innerHTML;

    // // Open a new window and print
    // var printWindow = window.open('', '', 'height=600,width=800');
    // printWindow.document.write('<html><head><title>Print</title>');
    // printWindow.document.write('</head><body>');
    // printWindow.document.write(printContents);
    // printWindow.document.write('</body></html>');
    // printWindow.document.close();

    // printWindow.focus();
    // printWindow.print();
    // printWindow.close();

    // Call PHP to unset session
    // fetch('unset_session.php') // see below
    //     .then(response => {
    //         if (response.ok) {
    //             location.reload(); // reload for new bill
    //         }
    //     });

    return false;
}






//for deleting the product from the cart
$(document).on('click', '.delete-product', function () {
    const id1 = $(this).data('id');

    // AJAX call using jQuery
      $.ajax({
        url: '../deleteproduct.php', // Your server endpoint
        type: 'POST',
        data:  { delprodid: id1 },
        success: function(response) {
            console.log("sucesss");
            console.log(response);
          loadCart()

        },
        error: function(error) {
          console.error('Error Status: ' + status); // e.g. 'error'
        console.error('Error Thrown: ' + error);  // e.g. the actual error message
        console.error('Response: ' + xhr.responseText);  
        }
      });
});


</script>

