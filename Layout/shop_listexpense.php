<?php
include("web_shopadmin_header.php");

// Soft delete logic
if (isset($_GET['delete_id'])) {
  $id = intval($_GET['delete_id']);
  $deleteQuery = "UPDATE shop_expenses SET is_deleted = 1 WHERE id = $id";
  mysqli_query($conn, $deleteQuery);
  echo "<script>alert('Expense flagged as deleted!'); window.location='shop_listexpense.php';</script>";
}

// Filters
date_default_timezone_set('Asia/Kolkata');
$filter_date     = isset($_GET['filter_date'])     && $_GET['filter_date']     !== '' ? $_GET['filter_date']     : date('Y-m-d');
$filter_category = isset($_GET['filter_category']) && $_GET['filter_category'] !== '' ? $_GET['filter_category'] : '';
$filter_all      = isset($_GET['filter_all']);   // show all dates when checked

$where = "WHERE is_deleted = 0";
if (!$filter_all) {
    $where .= " AND expense_date = '" . mysqli_real_escape_string($conn, $filter_date) . "'";
}
if ($filter_category !== '') {
    $where .= " AND expense_category = '" . mysqli_real_escape_string($conn, $filter_category) . "'";
}

$query = "SELECT * FROM shop_expenses $where ORDER BY expense_date DESC, id DESC";
$result = mysqli_query($conn, $query);
?>

<main>

  <!-- Header -->
  <nav class="navbar navbar-expand-lg navbar-light" style="background-color: #b8860b !important;">
    <div class="container-fluid d-flex flex-wrap align-items-center justify-content-between">
      <div class="d-flex align-items-center">
        <img src="../images/logo.jpg" alt="Logo" class="img-fluid" style="max-height: 50px;">
       
      </div>

    <div class="d-flex align-items-center">
        <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#myModal">
          <i class="fa fa-language"></i>
        </button>
        <?php include_once("../master_mobnav.php"); ?>
      </div>
    </div>
  </nav>

  <!-- Expense Listing -->
  <div class="container-fluid py-4">
    <div class="card shadow-lg border-0 rounded-4">
      <div class="card-header text-white" style="background-color:#b8860b;">
        <h5 class="mb-0">
          Expenses &mdash;
          <?php echo $filter_all ? 'All Dates' : htmlspecialchars($filter_date); ?>
          <?php if ($filter_category) echo ' &bull; ' . htmlspecialchars($filter_category); ?>
        </h5>
      </div>

      <!-- Filter Form -->
      <div class="card-body border-bottom pb-3">
        <form method="GET" action="shop_listexpense.php" class="row g-2 align-items-end">
          <div class="col-auto">
            <label class="form-label mb-1">Date</label>
            <input type="date" name="filter_date" class="form-control"
              value="<?php echo htmlspecialchars($filter_date); ?>"
              <?php echo $filter_all ? 'disabled' : ''; ?>>
          </div>
          <div class="col-auto">
            <label class="form-label mb-1">Category</label>
            <select name="filter_category" class="form-select">
              <option value="">All Categories</option>
              <?php
              $cats = ['Raw Materials','Staff Salary','Maintenance','Utilities','Miscellaneous'];
              foreach ($cats as $cat) {
                $sel = ($filter_category === $cat) ? 'selected' : '';
                echo "<option value=\"$cat\" $sel>$cat</option>";
              }
              ?>
            </select>
          </div>
          <div class="col-auto d-flex align-items-end gap-2">
            <div class="form-check mb-0" style="padding-top:6px;">
              <input class="form-check-input" type="checkbox" name="filter_all" id="filter_all"
                <?php echo $filter_all ? 'checked' : ''; ?> onchange="this.form.submit();">
              <label class="form-check-label" for="filter_all">All Dates</label>
            </div>
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-warning text-white">Filter</button>
            <a href="shop_listexpense.php" class="btn btn-outline-secondary">Reset</a>
          </div>
        </form>
      </div>

      <div class="card-body table-responsive">
        <table class="table table-striped table-bordered align-middle text-center">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>Date</th>
              <th>Expense Name</th>
              <th>Category</th>
              <th>Qty</th>
              <th>Amount (₹)</th>
              <th>Total (₹)</th>
              <th>Note</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $count = 1;
            $grand_total = 0;
            while ($row = mysqli_fetch_assoc($result)) {
              $total = $row['expense_amount'] * $row['expense_qty'];
              $grand_total += $total;
              echo "<tr>
                <td>{$count}</td>
                <td>{$row['expense_date']}</td>
                <td>{$row['expense_name']}</td>
                <td>{$row['expense_category']}</td>
                <td>{$row['expense_qty']}</td>
                <td>{$row['expense_amount']}</td>
                <td><strong>" . number_format($total, 2) . "</strong></td>
                <td>{$row['expense_note']}</td>
                <td>
                  <a href='shop_listexpense.php?delete_id={$row['id']}' 
                     class='btn btn-danger btn-sm' 
                     onclick=\"return confirm('Are you sure you want to delete this expense?');\">
                     Delete
                  </a>
                </td>
              </tr>";
              $count++;
            }
            ?>
          </tbody>
          <tfoot>
            <tr>
              <th colspan="6" class="text-end">Grand Total:</th>
              <th colspan="3" class="text-start text-success fs-5">₹ <?php echo number_format($grand_total, 2); ?></th>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

</main>

<?php include_once("web_shopadmin_footer.php"); ?>

<!-- Styles -->
<style>
  table th, table td {
    vertical-align: middle;
  }

  @media (max-width: 768px) {
    table {
      font-size: 0.9rem;
    }
    .table th, .table td {
      white-space: nowrap;
    }
  }
</style>
