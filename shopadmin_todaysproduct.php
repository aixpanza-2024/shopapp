<?php
header("Content-Type: text/html; charset=UTF-8");
?>
<table class="table table-bordered table-striped justify-content-center">
  <thead>
    <tr>
      
      <th>Product</th>
     <th>Quantitty</th>
     
    </tr>
  </thead>
  <tbody>
    <?php
    include_once("db.php");
    // Fetch today's products from the database
    $today = date('Y-m-d');
    $todaysProducts = "SELECT * FROM daily_availability WHERE available_date = '$today'";
    $resultTodaysProducts = mysqli_query($conn, $todaysProducts);
    $countTodaysProducts = mysqli_num_rows($resultTodaysProducts);

    if ($countTodaysProducts == 0) {
        echo "<tr><td colspan='6' class='text-center'>No Today's Products Found</td></tr>";
    } else {
        while ($row = mysqli_fetch_array($resultTodaysProducts)) {
            echo "<tr>";
           
            echo "<td>" . htmlspecialchars($row['prodname']) . "</td>";
            echo "<td translate=no>" . htmlspecialchars($row['available_qty']) . "</td>";
            echo "</tr>";
        }
    }
    ?>
  </tbody>

</table>