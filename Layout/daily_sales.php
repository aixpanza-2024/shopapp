<?php
include("web_shopadmin_header.php");
date_default_timezone_set('Asia/Kolkata');

// --- Default time range: full current day ---
$def_start = date('Y-m-d') . ' 00:00';
$def_end   = date('Y-m-d') . ' 23:59';

$start_time = isset($_GET['start_time']) && $_GET['start_time'] !== '' ? $_GET['start_time'] : $def_start;
$end_time   = isset($_GET['end_time'])   && $_GET['end_time']   !== '' ? $_GET['end_time']   : $def_end;

// Normalize: datetime-local inputs send 'T' separator (e.g. 2026-03-07T14:00)
// but DB stores with a space — convert to space for reliable SQL comparison
$start_time = str_replace('T', ' ', $start_time);
$end_time   = str_replace('T', ' ', $end_time);
$pay_filter   = isset($_GET['pay_filter'])    && $_GET['pay_filter']    !== '' ? $_GET['pay_filter']    : 'cash';

$shopId = isset($_SESSION['selectshop']) ? intval($_SESSION['selectshop']) : 0;

// --- Build payment mode WHERE clause ---
$pay_where = '';
if ($pay_filter === 'cash') {
    $pay_where = "AND ds.`Payment Mode` = 'cash'";
} elseif ($pay_filter === 'upi') {
    $pay_where = "AND ds.`Payment Mode` = 'upi'";
} elseif ($pay_filter === 'staff') {
    $pay_where = "AND ds.`Payment Mode` LIKE 'staff%'";
}
// 'all' → no extra filter

// --- Summary: totals ---
$summarySQL = "
    SELECT
        COUNT(DISTINCT ds.`Inv no`) AS total_bills,
        SUM(ds.quantity)            AS total_items,
        SUM(ds.quantity * ds.`Selling Price`) AS total_revenue
    FROM daily_productsale ds
    WHERE ds.sh_id = ?
      AND ds.`payment status` != 'notpaid'
      AND ds.Time >= ?
      AND ds.Time <= ?
      $pay_where
";
$summaryStmt = $conn->prepare($summarySQL);
$summaryStmt->bind_param("iss", $shopId, $start_time, $end_time);
$summaryStmt->execute();
$summary = $summaryStmt->get_result()->fetch_assoc();
$summaryStmt->close();

// --- By category ---
$catSQL = "
    SELECT
        c.categorie          AS category_name,
        SUM(ds.quantity)     AS cat_qty,
        SUM(ds.quantity * ds.`Selling Price`) AS cat_amount
    FROM daily_productsale ds
    JOIN products p  ON ds.p_id = p.p_id
    JOIN categorie c ON c.cat_id = p.categorie
    WHERE ds.sh_id = ?
      AND ds.`payment status` != 'notpaid'
      AND ds.Time >= ?
      AND ds.Time <= ?
      $pay_where
    GROUP BY c.cat_id, c.categorie
    ORDER BY cat_amount DESC
";
$catStmt = $conn->prepare($catSQL);
$catStmt->bind_param("iss", $shopId, $start_time, $end_time);
$catStmt->execute();
$catResult = $catStmt->get_result();
$catStmt->close();

// --- Item detail list ---
$detailSQL = "
    SELECT
        ds.`product name`  AS prod_name,
        ds.quantity,
        ds.`Selling Price` AS price,
        ds.quantity * ds.`Selling Price` AS subtotal,
        ds.`Payment Mode`  AS pay_mode,
        ds.`payment status` AS pay_status,
        ds.`Inv no`        AS inv_no,
        ds.Time,
        c.categorie        AS category_name
    FROM daily_productsale ds
    JOIN products p  ON ds.p_id = p.p_id
    JOIN categorie c ON c.cat_id = p.categorie
    WHERE ds.sh_id = ?
      AND ds.`payment status` != 'notpaid'
      AND ds.Time >= ?
      AND ds.Time <= ?
      $pay_where
    ORDER BY ds.Time DESC
";
$detStmt = $conn->prepare($detailSQL);
$detStmt->bind_param("iss", $shopId, $start_time, $end_time);
$detStmt->execute();
$detResult = $detStmt->get_result();
$detStmt->close();
?>

<main>
  <!-- Header -->
  <nav class="navbar navbar-expand-lg navbar-light" style="background-color:#b8860b !important;">
    <div class="container-fluid d-flex flex-wrap align-items-center justify-content-between">
      <div class="d-flex align-items-center">
        <img src="../images/logo.jpg" alt="Logo" class="img-fluid" style="max-height:50px;">
        <h6 class="ms-2 text-white mb-0">Daily Sales</h6>
      </div>
      <div class="d-flex align-items-center">
        <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#myModal">
          <i class="fa fa-language"></i>
        </button>
        <?php include_once("../master_mobnav.php"); ?>
      </div>
    </div>
  </nav>

  <div class="container-fluid py-3">

    <!-- Filter Form -->
    <div class="card shadow-sm border-0 rounded-4 mb-3">
      <div class="card-body">
        <form method="GET" action="daily_sales.php" class="row g-2 align-items-end">
          <div class="col-md-4 col-6">
            <label class="form-label mb-1">From</label>
            <input type="datetime-local" name="start_time" class="form-control"
              value="<?php echo htmlspecialchars(str_replace(' ', 'T', $start_time)); ?>">
          </div>
          <div class="col-md-4 col-6">
            <label class="form-label mb-1">To</label>
            <input type="datetime-local" name="end_time" class="form-control"
              value="<?php echo htmlspecialchars(str_replace(' ', 'T', $end_time)); ?>">
          </div>
          <div class="col-md-2 col-6">
            <label class="form-label mb-1">Payment</label>
            <select name="pay_filter" class="form-select">
              <?php
              $opts = ['cash' => '💵 Cash'];
              foreach ($opts as $val => $lbl) {
                $sel = ($pay_filter === $val) ? 'selected' : '';
                echo "<option value=\"$val\" $sel>$lbl</option>";
              }
              ?>
            </select>
          </div>
          <div class="col-md-2 col-6">
            <button type="submit" class="btn btn-warning text-white w-100">Apply</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-3">
      <div class="col-4">
        <div class="card text-center border-0 shadow-sm rounded-4 h-100">
          <div class="card-body py-3">
            <div style="font-size:1.6rem;font-weight:bold;color:#b8860b;">
              <?php echo intval($summary['total_bills']); ?>
            </div>
            <div class="text-muted small">Bills</div>
          </div>
        </div>
      </div>
      <div class="col-4">
        <div class="card text-center border-0 shadow-sm rounded-4 h-100">
          <div class="card-body py-3">
            <div style="font-size:1.6rem;font-weight:bold;color:#b8860b;">
              <?php echo intval($summary['total_items']); ?>
            </div>
            <div class="text-muted small">Items</div>
          </div>
        </div>
      </div>
      <div class="col-4">
        <div class="card text-center border-0 shadow-sm rounded-4 h-100">
          <div class="card-body py-3">
            <div style="font-size:1.5rem;font-weight:bold;color:#b8860b;">
              ₹<?php echo number_format($summary['total_revenue'] ?? 0, 0); ?>
            </div>
            <div class="text-muted small">Revenue</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Category Breakdown -->
    <div class="card shadow-sm border-0 rounded-4 mb-3">
      <div class="card-header text-white" style="background:#b8860b;">
        <strong>By Category</strong>
      </div>
      <div class="card-body p-0">
        <table class="table table-striped table-bordered align-middle text-center mb-0">
          <thead class="table-dark">
            <tr>
              <th>Category</th>
              <th>Items Sold</th>
              <th>Amount (₹)</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $catTotal = 0;
            $catQtyTotal = 0;
            while ($cat = $catResult->fetch_assoc()):
                $catTotal    += $cat['cat_amount'];
                $catQtyTotal += $cat['cat_qty'];
            ?>
            <tr>
              <td><?php echo htmlspecialchars($cat['category_name']); ?></td>
              <td><?php echo intval($cat['cat_qty']); ?></td>
              <td><strong>₹<?php echo number_format($cat['cat_amount'], 2); ?></strong></td>
            </tr>
            <?php endwhile; ?>
            <tr class="table-warning fw-bold">
              <td>Total</td>
              <td><?php echo $catQtyTotal; ?></td>
              <td>₹<?php echo number_format($catTotal, 2); ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Item Detail Table -->
    <div class="card shadow-sm border-0 rounded-4">
      <div class="card-header text-white" style="background:#b8860b;">
        <strong>Item Details</strong>
      </div>
      <div class="card-body table-responsive p-0">
        <table class="table table-striped table-bordered align-middle text-center mb-0" style="font-size:0.85rem;">
          <thead class="table-dark">
            <tr>
              <th>Time</th>
              <th>Inv#</th>
              <th>Item</th>
              <th>Category</th>
              <th>Qty</th>
              <th>Price</th>
              <th>Subtotal</th>
              <th>Mode</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($detResult->num_rows === 0): ?>
            <tr><td colspan="8" class="text-center text-muted py-3">No sales found for this period.</td></tr>
            <?php else: ?>
            <?php while ($det = $detResult->fetch_assoc()): ?>
            <tr>
              <td><?php echo date('h:i A', strtotime($det['Time'])); ?></td>
              <td><?php echo htmlspecialchars($det['inv_no']); ?></td>
              <td><?php echo htmlspecialchars($det['prod_name']); ?></td>
              <td><?php echo htmlspecialchars($det['category_name']); ?></td>
              <td><?php echo intval($det['quantity']); ?></td>
              <td>₹<?php echo number_format($det['price'], 2); ?></td>
              <td>₹<?php echo number_format($det['subtotal'], 2); ?></td>
              <td>
                <?php
                $mode = strtolower($det['pay_mode']);
                if ($mode === 'cash')       echo '<span class="badge bg-success">Cash</span>';
                elseif ($mode === 'upi')    echo '<span class="badge bg-primary">UPI</span>';
                elseif (str_starts_with($mode, 'staff')) echo '<span class="badge bg-warning text-dark">' . htmlspecialchars($det['pay_mode']) . '</span>';
                else echo htmlspecialchars($det['pay_mode']);
                ?>
              </td>
            </tr>
            <?php endwhile; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /container -->
</main>

<?php include_once("web_shopadmin_footer.php"); ?>

<style>
@media (max-width: 576px) {
  table { font-size: 0.78rem; }
}
</style>
