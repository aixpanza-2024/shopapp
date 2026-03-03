<!-- code for displaying products based on category -->
<?php

include_once("db.php");
$prodid = 0;

if (isset($_GET['divId'])) {
  $data = (int)$_GET['divId'];

  if ($data == 2) {
    // Snacks: check daily availability and expiry
    $proddisplay = "
SELECT
    p.*,
    d.*,
    s.total_sold
FROM products p
LEFT JOIN daily_availability d
    ON d.daid = (
        SELECT da.daid
        FROM daily_availability da
        WHERE da.product_id = p.p_id
        ORDER BY da.updated_at DESC
        LIMIT 1
    )
LEFT JOIN (
    SELECT p_id, SUM(quantity) AS total_sold
    FROM daily_productsale
    GROUP BY p_id
) s
    ON s.p_id = p.p_id
WHERE p.categorie = $data
  AND d.updated_at IS NOT NULL
  AND NOW() <
      (
          CASE UPPER(p.expiry_type)
              WHEN 'MINUTE' THEN DATE_ADD(d.updated_at, INTERVAL p.expiry_value MINUTE)
              WHEN 'HOUR'   THEN DATE_ADD(d.updated_at, INTERVAL p.expiry_value HOUR)
              WHEN 'DAY'    THEN DATE_ADD(d.updated_at, INTERVAL p.expiry_value DAY)
              ELSE DATE_ADD(d.updated_at, INTERVAL p.expiry_value DAY)
          END
      )
  AND (
        d.available_qty
        -
        (
            SELECT IFNULL(SUM(ps.quantity), 0)
            FROM daily_productsale ps
            WHERE ps.p_id = p.p_id
              AND ps.Time >= d.updated_at
              AND ps.Time <
                  (
                      CASE UPPER(p.expiry_type)
                          WHEN 'MINUTE' THEN DATE_ADD(d.updated_at, INTERVAL p.expiry_value MINUTE)
                          WHEN 'HOUR'   THEN DATE_ADD(d.updated_at, INTERVAL p.expiry_value HOUR)
                          WHEN 'DAY'    THEN DATE_ADD(d.updated_at, INTERVAL p.expiry_value DAY)
                          ELSE DATE_ADD(d.updated_at, INTERVAL p.expiry_value DAY)
                      END
                  )
        ) > 0
      );
";
  } elseif ($data == 8) {
    // Plate: admin-activated daily, show when added for today with stock remaining
    date_default_timezone_set('Asia/Kolkata');
    $today = date('Y-m-d');
    $proddisplay = "
SELECT p.*, d.*
FROM products p
INNER JOIN daily_availability d
    ON d.product_id = p.p_id
    AND d.available_date = '$today'
    AND d.available_qty > 0
WHERE p.categorie = $data;
";
  } else {
    // Tea, Juice (1,3): always available, no availability check
    $proddisplay = "
SELECT p.*, d.*
FROM products p
LEFT JOIN (
    SELECT product_id, MAX(updated_at) AS latest_update
    FROM daily_availability
    GROUP BY product_id
) latest ON p.p_id = latest.product_id
LEFT JOIN daily_availability d
    ON d.product_id = latest.product_id
    AND d.updated_at = latest.latest_update
WHERE p.categorie = $data;
";
  }

} elseif (isset($_GET['prodsearch'])) {
    $data = mysqli_real_escape_string($conn, $_GET['prodsearch']);

    $proddisplay = "
    SELECT p.*, d.*
    FROM products p
    LEFT JOIN (
        SELECT product_id, MAX(updated_at) AS latest_update
        FROM daily_availability
        GROUP BY product_id
    ) latest ON p.p_id = latest.product_id
    LEFT JOIN daily_availability d 
        ON d.product_id = latest.product_id 
        AND d.updated_at = latest.latest_update
    WHERE 
        (
            -- Snacks (category 2): show only if not expired AND available_qty > 0
            (p.categorie = 2
             AND (p.name LIKE '%$data%' OR p.saleprice LIKE '%$data%')
             AND d.updated_at IS NOT NULL
             AND (d.available_qty IS NULL OR d.available_qty > 0)
             AND NOW() < CASE UPPER(p.expiry_type)
                WHEN 'MINUTE' THEN DATE_ADD(d.updated_at, INTERVAL p.expiry_value MINUTE)
                WHEN 'HOUR'   THEN DATE_ADD(d.updated_at, INTERVAL p.expiry_value HOUR)
                WHEN 'DAY'    THEN DATE_ADD(d.updated_at, INTERVAL p.expiry_value DAY)
                ELSE DATE_ADD(d.updated_at, INTERVAL p.expiry_value DAY)
             END)
        )
        OR
        (
            -- Tea, Juice, Plate (1,3,8): show regardless of expiry
            (p.categorie IN (1,3,8)
             AND (p.name LIKE '%$data%' OR p.saleprice LIKE '%$data%'))
        );
    ";

} else {

    $proddisplay = "
    SELECT p.*, d.*
    FROM products p
    LEFT JOIN (
        SELECT product_id, MAX(updated_at) AS latest_update
        FROM daily_availability
        GROUP BY product_id
    ) latest ON p.p_id = latest.product_id
    LEFT JOIN daily_availability d 
        ON d.product_id = latest.product_id 
        AND d.updated_at = latest.latest_update
    WHERE 
        p.categorie = 1
        AND (d.available_qty IS NULL OR d.available_qty > 0);
    ";
}





$resultproddisply=mysqli_query($conn,$proddisplay) or die("Query failed: " . mysqli_error($conn));
$countingprod=mysqli_num_rows($resultproddisply);
if($countingprod==0)
{
echo "No Products are found in this category";
}
else
{

  while($displayprodrow=mysqli_fetch_array($resultproddisply))
  {
     $pid = $displayprodrow['p_id'];
    $category = (int)$displayprodrow['categorie'];

    // 1️⃣ Get today's available quantity
    $getAvail = mysqli_query($conn, "
        SELECT available_qty
        FROM daily_availability 
        WHERE product_id = '$pid' 
        ORDER BY daid DESC 
        LIMIT 1
    ");
$countingprod1=mysqli_num_rows($getAvail);
if($countingprod1==0)
{

}
else
    {
    $availRow = mysqli_fetch_assoc($getAvail);



    $available_qty = isset($availRow['available_qty']) ? (int)$availRow['available_qty'] : 0;

    // 2️⃣ Get today's total sold
    $getSold = mysqli_query($conn, "
        SELECT SUM(quantity) AS total_sold
        FROM daily_productsale
        WHERE p_id = '$pid'
        AND DATE(time) = CURDATE()
    ");
$countingprod2=mysqli_num_rows($getSold);
if($countingprod2==0)
{

}
else
    {
    $soldRow = mysqli_fetch_assoc($getSold);
    $total_sold = isset($soldRow['total_sold']) ? (int)$soldRow['total_sold'] : 0;

    // 3️⃣ Skip product if sold quantity >= available
    // $category = category of the current product (eg: from DB)

// Apply stock check for Snacks (2) and Plate (8)
if ($displayprodrow['categorie'] == "2" || $displayprodrow['categorie'] == "8") {
    if ($total_sold >= $available_qty) {
        continue; // ❌ hide the product
    }
}
    }
    
    }

// For category 1, 3, 4 → the continue should NOT run

?>


  


<!-- code for displaying the product -->

<div class="col-4 card-products">
  <div class="col-12">
   
<!-- product-card is the class used to add products to cart suing class name from ajareq.php -->
<!-- <div class="card product-card" id="prod<?php //echo $prodid;?>" data-id="<?php //echo $displayprodrow['p_id']; ?>">
  
  <img src="../images/logo_small.png" class="card-img-top" alt="...">
  <div class="card-body cardpad">
   <h5 class="card-title" style="font-weight: bold;text-transform:capitalize;"><?php
        // echo $displayprodrow['name'];
        ?></h5>
    <p class="card-text" style="color:#b8860b;">
    <?php
        // echo "₹".$displayprodrow['saleprice'];
        ?>    
    </p>
    
  </div>
</div> -->
<div class="card product-card shadow-sm border-0" id="prod<?php echo $prodid;?>" data-id="<?php echo $displayprodrow['p_id']; ?>">
  
  <img src="../images/logo_small.png" class="card-img-top img-fluid" alt="<?php echo htmlspecialchars($displayprodrow['name']); ?>">

  <div class="card-body text-center">
    <h5 class="card-title mb-2 product-title">
      <?php echo htmlspecialchars($displayprodrow['name']); ?>
    </h5>
    <p class="card-text product-price mb-0">
      ₹<?php echo number_format($displayprodrow['saleprice'], 2); ?>
    </p>
  </div>

</div>

</div>
</div>
<!-- code for displaying the product ends here -->


  <?php
  $prodid++;
  }
}
  ?>








