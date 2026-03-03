<!-- code for displaying products based on category -->
<?php
include_once("db.php");

$proddisplay = "SELECT * FROM `products` WHERE categorie='2' OR categorie='8'";
$resultproddisply = mysqli_query($conn, $proddisplay);
$countingprod = mysqli_num_rows($resultproddisply);
?>
</div>

<div class="row">
  <div class="col-10">
    <h2 class="text-center">Products</h2>
<div id="printresult" style="background-color:cream;color:green"></div>

<table class="table table-bordered table-striped justify-content-center">
  <thead>
    <tr>
      <th>Choose</th>
      <th>Product Name</th>
     <th>Choose</th>
      <th>Product Name</th>
      <th>Choose</th>
      <th>Product Name</th>
    </tr>
  </thead>
  <tbody>
    <?php
    if ($countingprod == 0) {
        echo "<tr><td colspan='4' class='text-center'>No Products Found</td></tr>";
    } else {
        $counter = 0;
    while ($displayprodrow = mysqli_fetch_array($resultproddisply)) {
            if ($counter % 3 == 0) {
                echo "<tr>"; // open a new row every 3 products
            }
            ?>
            <td>
              <input type="checkbox" class="form-check-input prod-check dataenter" 
                id="prod<?php echo $displayprodrow['p_id'];?>" 
                data-id="<?php echo $displayprodrow['p_id']; ?>">
              
            </td>
            <td>
              <?php echo htmlspecialchars($displayprodrow['name']); ?>
              <input type="text" class="form-control prod-input dataenter" id="prod<?php echo $displayprodrow['p_id'];?>" data-id="<?php echo $displayprodrow['p_id']; ?>" value="10" disabled>
           <input type="hidden" 
       class="form-control prod-name dataenter" 
       id="prod<?php echo $displayprodrow['p_id'];?>"
       data-id="<?php echo $displayprodrow['p_id']; ?>" 
       value="<?php echo $displayprodrow['name']; ?>" 
       disabled>
            </td>
            <?php
            if ($counter % 3 == 2) {
                echo "</tr>"; // close row after 3 products
            }
            $counter++;
        }

        // If total products not multiple of 3 → fill empty cells
        $remaining = $counter % 3;
        if ($remaining != 0) {
            $emptyCells = (3 - $remaining) * 2; // 2 <td> per product
            echo str_repeat("<td></td>", $emptyCells) . "</tr>";
        }
    }
    ?>
  </tbody>
</table>

</div>
<div class="col-2">
    <h2 class="text-center">Todays Products</h2>
    <div class="todayprods">
      </div>
</div>
</div>


<!-- code for displaying the product ends here -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// $(document).on('change', '.prod-check', function() {
//     let prodId = $(this).data('id');
//     let inputBox = $('.prod-input[data-id="' + prodId + '"]');

//     if ($(this).is(':checked')) {
//         inputBox.prop('disabled', false);
//     } else {
//         inputBox.prop('disabled', true);
//         inputBox.val(''); // clear if you want
//     }
// });

$(document).ready(function() {
    todaysdata();
});


$(document).on('change', '.dataenter', function() { 
  let prodId = $(this).data('id'); // from checkbox
    let insert = $('.dataenter[data-id="' + prodId + '"]').not(':checkbox'); 
    let value = insert.val(); // textbox value
    let name = $('.prod-name[data-id="' + prodId + '"]').val(); // get product name

    // alert("ProdId = " + prodId + " , Value = " + value + " , Name = " + name); 
    if ($(this).is(':checked')) {
        insert.prop('disabled', false);
    } else {
         if (insert.prop('disabled')) {
        insert.prop('disabled', true);
    } else {
        // do nothing → keep it enabled
    }
        // inputBox.val(''); // clear if you want
    }

       // AJAX call using jQuery
     $.ajax({
    url: '../ajaxreqshopadmin.php',
    type: 'GET',
    data: { 
        prodId: prodId, 
        prodvalue: value,   // second value
        prodname:name // get product name
    },
    success: function(response) {
        console.log("success");
        $('#printresult').html(response);

       todaysdata();

    },
        error: function(error) {
          console.error('Error Status: ' + status); // e.g. 'error'
        console.error('Error Thrown: ' + error);  // e.g. the actual error message
        console.error('Response: ' + xhr.responseText);  
        }
      });







});





function todaysdata(){
        // fetch today's data
    $.ajax({
        url: '../shopadmin_todaysproduct.php',
        type: 'GET',
        data: { date: new Date().toISOString().slice(0,10) }, // YYYY-MM-DD
        success: function(todayData) {
            $('.todayprods').html(todayData);
        },
        error: function(xhr, status, error) {
            console.error("Error fetching today’s data:", error);
        }
    });
}

</script>

 








