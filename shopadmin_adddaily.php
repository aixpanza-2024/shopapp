<!-- products grouped by category -->
<?php
include_once("db.php");

// Fetch all categories that have at least one product
$catresult = mysqli_query($conn,
    "SELECT c.cat_id, c.categorie
     FROM categorie c
     INNER JOIN products p ON p.categorie = c.cat_id
     GROUP BY c.cat_id, c.categorie
     ORDER BY c.categorie ASC"
);
?>

<div class="row g-0">

  <!-- Products Column -->
  <div class="col-12 col-md-10">

    <div class="d-flex justify-content-between align-items-center px-2 mb-3">
      <h5 class="mb-0 fw-bold">Products</h5>
      <a href="shopadmin_addproduct.php" class="btn btn-sm btn-warning">
        <i class="fa fa-plus"></i> Add Product
      </a>
    </div>

    <div id="printresult" class="px-2 mb-2" style="color:green;"></div>

    <?php
    if (!$catresult || mysqli_num_rows($catresult) == 0) {
        echo '<p class="text-center text-muted py-4">No products found.</p>';
    } else {
        while ($catrow = mysqli_fetch_assoc($catresult)) {
            $catid   = intval($catrow['cat_id']);
            $catname = htmlspecialchars($catrow['categorie']);

            $prodresult = mysqli_query($conn,
                "SELECT * FROM products WHERE categorie = '$catid' ORDER BY name ASC"
            );
            if (!$prodresult || mysqli_num_rows($prodresult) == 0) continue;
            ?>

            <div class="category-section mb-4 px-2">
              <div class="category-header mb-2">
                <span class="cat-badge"><?php echo $catname; ?></span>
                <hr class="cat-divider">
              </div>
              <div class="row g-2">
                <?php while ($prod = mysqli_fetch_assoc($prodresult)) { ?>
                <div class="col-6 col-sm-4 col-lg-3">
                  <div class="prod-card">
                    <div class="form-check mb-1">
                      <input type="checkbox"
                             class="form-check-input prod-check dataenter"
                             id="chk<?php echo $prod['p_id']; ?>"
                             data-id="<?php echo $prod['p_id']; ?>">
                      <label class="form-check-label prod-label" for="chk<?php echo $prod['p_id']; ?>">
                        <?php echo htmlspecialchars($prod['name']); ?>
                      </label>
                    </div>
                    <input type="number"
                           class="form-control form-control-sm prod-input dataenter"
                           id="prod<?php echo $prod['p_id']; ?>"
                           data-id="<?php echo $prod['p_id']; ?>"
                           value="10" disabled>
                    <input type="hidden"
                           class="prod-name dataenter"
                           data-id="<?php echo $prod['p_id']; ?>"
                           value="<?php echo htmlspecialchars($prod['name']); ?>"
                           disabled>
                  </div>
                </div>
                <?php } ?>
              </div>
            </div>

            <?php
        }
    }
    ?>
  </div>

  <!-- Today's Products Sidebar -->
  <div class="col-12 col-md-2">
    <div class="todays-panel">
      <h6 class="text-center fw-bold mb-2">Today's Products</h6>
      <div class="todayprods"></div>
    </div>
  </div>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    todaysdata();
});

$(document).on('change', '.dataenter', function() {
    let prodId = $(this).data('id');
    let insert = $('.dataenter[data-id="' + prodId + '"]').not(':checkbox');
    let value  = insert.val();
    let name   = $('.prod-name[data-id="' + prodId + '"]').val();

    if ($(this).is(':checked')) {
        insert.prop('disabled', false);
    } else {
        if (insert.prop('disabled')) {
            insert.prop('disabled', true);
        }
    }

    $.ajax({
        url: '../ajaxreqshopadmin.php',
        type: 'GET',
        data: { prodId: prodId, prodvalue: value, prodname: name },
        success: function(response) {
            $('#printresult').html(response);
            todaysdata();
        },
        error: function(xhr, status, error) {
            console.error('AJAX error:', error);
        }
    });
});

function todaysdata() {
    $.ajax({
        url: '../shopadmin_todaysproduct.php',
        type: 'GET',
        data: { date: new Date().toISOString().slice(0, 10) },
        success: function(todayData) {
            $('.todayprods').html(todayData);
        },
        error: function(xhr, status, error) {
            console.error("Error fetching today's data:", error);
        }
    });
}
</script>
