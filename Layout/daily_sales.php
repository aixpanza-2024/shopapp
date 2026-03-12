<?php
include("web_shopadmin_header.php");
date_default_timezone_set('Asia/Kolkata');

// --- Default time range: shop session 2 PM → 2 AM next day ---
$hour = (int)date('H');
if ($hour < 2) {
    // Before 2 AM → still in previous day's session
    $session_date = date('Y-m-d', strtotime('-1 day'));
} else {
    $session_date = date('Y-m-d');
}
$def_start = $session_date . ' 14:00';
$def_end   = date('Y-m-d', strtotime($session_date . ' +1 day')) . ' 02:00';

$start_time = isset($_GET['start_time']) && $_GET['start_time'] !== '' ? $_GET['start_time'] : $def_start;
$end_time   = isset($_GET['end_time'])   && $_GET['end_time']   !== '' ? $_GET['end_time']   : $def_end;

// Normalize: datetime-local inputs send 'T' separator (e.g. 2026-03-07T14:00)
// but DB stores with a space — convert to space for reliable SQL comparison
$start_time = str_replace('T', ' ', $start_time);
$end_time   = str_replace('T', ' ', $end_time);
$pay_filter   = isset($_GET['pay_filter'])    && $_GET['pay_filter']    !== '' ? $_GET['pay_filter']    : 'cash_in_hand';

// --- Build payment mode WHERE clause ---
$pay_where = '';
if ($pay_filter === 'cash') {
    $pay_where = "AND ds.`Payment Mode` = 'cash'";
} elseif ($pay_filter === 'upi') {
    $pay_where = "AND ds.`Payment Mode` = 'upi'";
} elseif ($pay_filter === 'staff') {
    $pay_where = "AND ds.`Payment Mode` LIKE 'staff%'";
} elseif ($pay_filter === 'split') {
    $pay_where = "AND ds.`Payment Mode` LIKE 'split:%'";
} elseif ($pay_filter === 'online') {
    $pay_where = "AND ds.`Payment Mode` = 'online'";
} elseif ($pay_filter === 'cash_in_hand') {
    $pay_where = "AND (ds.`Payment Mode` = 'cash' OR ds.`Payment Mode` LIKE 'split:%')";
}
// 'all' → no extra filter

// --- Summary: totals ---
if ($pay_filter === 'split') {
    // Split only: extract cash portion per invoice
    $summarySQL = "
        SELECT
            COUNT(DISTINCT inv.inv_no)  AS total_bills,
            SUM(inv.item_qty)           AS total_items,
            SUM(inv.cash_amount)        AS total_revenue
        FROM (
            SELECT
                ds.`Inv no` AS inv_no,
                SUM(ds.quantity) AS item_qty,
                CAST(
                    SUBSTRING_INDEX(
                        SUBSTRING_INDEX(MAX(ds.`Payment Mode`), 'cash=', -1),
                        '|', 1
                    ) AS DECIMAL(10,2)
                ) AS cash_amount
            FROM daily_productsale ds
            WHERE ds.`payment status` != 'notpaid'
              AND ds.Time >= ?
              AND ds.Time <= ?
              AND ds.`Payment Mode` LIKE 'split:%'
            GROUP BY ds.`Inv no`
        ) AS inv
    ";
    $summaryStmt = $conn->prepare($summarySQL);
    $summaryStmt->bind_param("ss", $start_time, $end_time);
} elseif ($pay_filter === 'cash_in_hand') {
    // Cash in Hand: pure cash bills (full) + split bills (cash portion only)
    $summarySQL = "
        SELECT
            COUNT(DISTINCT inv_no)  AS total_bills,
            SUM(item_qty)           AS total_items,
            SUM(cash_revenue)       AS total_revenue
        FROM (
            SELECT
                ds.`Inv no` AS inv_no,
                SUM(ds.quantity) AS item_qty,
                SUM(ds.quantity * ds.`Selling Price`) AS cash_revenue
            FROM daily_productsale ds
            WHERE ds.`payment status` != 'notpaid'
              AND ds.Time >= ?
              AND ds.Time <= ?
              AND ds.`Payment Mode` = 'cash'
            GROUP BY ds.`Inv no`

            UNION ALL

            SELECT
                ds.`Inv no` AS inv_no,
                SUM(ds.quantity) AS item_qty,
                CAST(
                    SUBSTRING_INDEX(
                        SUBSTRING_INDEX(MAX(ds.`Payment Mode`), 'cash=', -1),
                        '|', 1
                    ) AS DECIMAL(10,2)
                ) AS cash_revenue
            FROM daily_productsale ds
            WHERE ds.`payment status` != 'notpaid'
              AND ds.Time >= ?
              AND ds.Time <= ?
              AND ds.`Payment Mode` LIKE 'split:%'
            GROUP BY ds.`Inv no`
        ) AS combined
    ";
    $summaryStmt = $conn->prepare($summarySQL);
    $summaryStmt->bind_param("ssss", $start_time, $end_time, $start_time, $end_time);
} else {
    $summarySQL = "
        SELECT
            COUNT(DISTINCT ds.`Inv no`) AS total_bills,
            SUM(ds.quantity)            AS total_items,
            SUM(ds.quantity * ds.`Selling Price`) AS total_revenue
        FROM daily_productsale ds
        WHERE ds.`payment status` != 'notpaid'
          AND ds.Time >= ?
          AND ds.Time <= ?
          $pay_where
    ";
    $summaryStmt = $conn->prepare($summarySQL);
    $summaryStmt->bind_param("ss", $start_time, $end_time);
}
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
    WHERE ds.`payment status` != 'notpaid'
      AND ds.Time >= ?
      AND ds.Time <= ?
      $pay_where
    GROUP BY c.cat_id, c.categorie
    ORDER BY cat_amount DESC
";
$catStmt = $conn->prepare($catSQL);
$catStmt->bind_param("ss", $start_time, $end_time);
$catStmt->execute();
$catResult = $catStmt->get_result();
$catStmt->close();

// --- Item detail list ---
if ($pay_filter === 'split') {
    // For split: group by invoice and show cash amount extracted from Payment Mode
    $detailSQL = "
        SELECT
            ds.`Inv no`        AS inv_no,
            ds.Time,
            SUM(ds.quantity)   AS quantity,
            SUM(ds.quantity * ds.`Selling Price`) AS full_total,
            CAST(
                SUBSTRING_INDEX(
                    SUBSTRING_INDEX(MAX(ds.`Payment Mode`), 'cash=', -1),
                    '|', 1
                ) AS DECIMAL(10,2)
            ) AS cash_amount,
            MAX(ds.`Payment Mode`) AS pay_mode
        FROM daily_productsale ds
        WHERE ds.`payment status` != 'notpaid'
          AND ds.Time >= ?
          AND ds.Time <= ?
          AND ds.`Payment Mode` LIKE 'split:%'
        GROUP BY ds.`Inv no`, ds.Time
        ORDER BY ds.Time DESC
    ";
} else {
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
        WHERE ds.`payment status` != 'notpaid'
          AND ds.Time >= ?
          AND ds.Time <= ?
          $pay_where
        ORDER BY ds.Time DESC
    ";
}
$detStmt = $conn->prepare($detailSQL);
$detStmt->bind_param("ss", $start_time, $end_time);
$detStmt->execute();
$detResult = $detStmt->get_result();
$detStmt->close();

// --- Snacks: stock left to sell (current session date) ---
// Auto-create wastage table in case stock_dashboard hasn't been visited yet
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS daily_wastage (
        id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL,
        prod_name VARCHAR(255) NOT NULL, wastage_qty INT NOT NULL DEFAULT 0,
        reason ENUM('unsold','damaged','other') NOT NULL DEFAULT 'unsold',
        notes VARCHAR(500) DEFAULT NULL, wastage_date DATE NOT NULL, created_at DATETIME NOT NULL
    )
");
$snacksRes = mysqli_query($conn, "
    SELECT
        p.name,
        IFNULL(da_today.available_qty, 0)    AS loaded_today,
        IFNULL(da_prev.old_qty, 0)           AS old_stock,
        IFNULL(sold.qty_sold, 0)             AS sold,
        IFNULL(wasted.wastage_qty, 0)        AS wasted
    FROM products p
    JOIN categorie c ON c.cat_id = p.categorie AND LOWER(c.categorie) LIKE '%snack%'
    LEFT JOIN daily_availability da_today
        ON da_today.product_id = p.p_id AND da_today.available_date = '$session_date'
    LEFT JOIN (
        SELECT da2.product_id, da2.available_qty AS old_qty
        FROM daily_availability da2
        INNER JOIN (
            SELECT product_id, MAX(available_date) AS max_date
            FROM daily_availability
            WHERE available_date < '$session_date'
            GROUP BY product_id
        ) latest ON latest.product_id = da2.product_id AND latest.max_date = da2.available_date
    ) da_prev ON da_prev.product_id = p.p_id AND da_today.available_qty IS NULL
    LEFT JOIN (
        SELECT p_id, SUM(quantity) AS qty_sold
        FROM daily_productsale
        WHERE Time >= '$def_start' AND Time <= '$def_end'
          AND `payment status` != 'notpaid'
        GROUP BY p_id
    ) sold ON sold.p_id = p.p_id
    LEFT JOIN (
        SELECT product_id, SUM(wastage_qty) AS wastage_qty
        FROM daily_wastage
        WHERE wastage_date = '$session_date'
        GROUP BY product_id
    ) wasted ON wasted.product_id = p.p_id
    WHERE (da_today.available_qty IS NOT NULL OR da_prev.old_qty IS NOT NULL)
    ORDER BY p.name
");
$snackRows = [];
while ($r = mysqli_fetch_assoc($snacksRes)) $snackRows[] = $r;
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
              $isSuperAdmin = isset($_SESSION['userpermission']) && $_SESSION['userpermission'] === 'Super Admin';
              $opts = $isSuperAdmin ? [
                'cash_in_hand' => '🧾 Cash in Hand',
                'cash'         => '💵 Cash',
                'upi'          => '📱 UPI',
                'split'        => '✂️ Split (Cash)',
                'online'       => '🌐 Online',
                'staff'        => '👤 Staff',
                'all'          => '📋 All',
              ] : [
                'cash_in_hand' => '🧾 Cash in Hand',
                'cash'         => '💵 Cash',
                'split'        => '✂️ Split (Cash)',
              ];
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
            <div class="text-muted small">
              <?php
              if ($pay_filter === 'split')        echo 'Cash Revenue';
              elseif ($pay_filter === 'cash_in_hand') echo 'Cash in Hand';
              else echo 'Revenue';
              ?>
            </div>
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

    <!-- Snacks Stock Chart -->
    <div class="card shadow-sm border-0 rounded-4 mb-3">
      <div class="card-header text-white" style="background:#b8860b;">
        <strong>Snacks — Items Left to Sell</strong>
      </div>
      <div class="card-body">
        <?php if (empty($snackRows)): ?>
        <p class="text-muted text-center mb-0">No snack stock loaded today.</p>
        <?php else: ?>
        <canvas id="snacksChart" style="max-height:320px;"></canvas>
        <?php endif; ?>
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
            <?php if ($pay_filter === 'split' && $pay_filter !== 'cash_in_hand'): ?>
            <tr>
              <th>Time</th>
              <th>Inv#</th>
              <th>Total Items</th>
              <th>Bill Total</th>
              <th>Cash Received</th>
              <th>UPI Received</th>
            </tr>
            <?php else: ?>
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
            <?php endif; ?>
          </thead>
          <tbody>
            <?php if ($detResult->num_rows === 0): ?>
            <tr><td colspan="8" class="text-center text-muted py-3">No sales found for this period.</td></tr>
            <?php elseif ($pay_filter === 'split' && $pay_filter !== 'cash_in_hand'): ?>
            <?php while ($det = $detResult->fetch_assoc()):
                preg_match('/cash=([\d.]+)\|upi=([\d.]+)/', $det['pay_mode'], $m);
                $cash_amt = isset($m[1]) ? (float)$m[1] : 0;
                $upi_amt  = isset($m[2]) ? (float)$m[2] : 0;
            ?>
            <tr>
              <td><?php echo date('h:i A', strtotime($det['Time'])); ?></td>
              <td><?php echo htmlspecialchars($det['inv_no']); ?></td>
              <td><?php echo intval($det['quantity']); ?></td>
              <td>₹<?php echo number_format($det['full_total'], 2); ?></td>
              <td><strong class="text-success">₹<?php echo number_format($cash_amt, 2); ?></strong></td>
              <td><span class="text-primary">₹<?php echo number_format($upi_amt, 2); ?></span></td>
            </tr>
            <?php endwhile; ?>
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
                if ($mode === 'cash') {
                    echo '<span class="badge bg-success">Cash</span>';
                } elseif (str_starts_with($mode, 'split:')) {
                    preg_match('/cash=([\d.]+)\|upi=([\d.]+)/', $det['pay_mode'], $m);
                    $c = isset($m[1]) ? number_format((float)$m[1], 2) : '?';
                    $u = isset($m[2]) ? number_format((float)$m[2], 2) : '?';
                    echo '<span class="badge bg-warning text-dark">Split</span>';
                    echo '<br><small class="text-success">💵₹' . $c . '</small>';
                } else {
                    echo htmlspecialchars($det['pay_mode']);
                }
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

<?php if (!empty($snackRows)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
  const rows   = <?php echo json_encode($snackRows); ?>;
  const labels = rows.map(r => r.name);
  const loaded = rows.map(r => parseInt(r.loaded_today));
  const oldStk = rows.map(r => parseInt(r.old_stock));
  const sold   = rows.map(r => parseInt(r.sold));
  const wasted = rows.map(r => parseInt(r.wasted));
  const left   = rows.map((r, i) => Math.max(0, loaded[i] + oldStk[i] - sold[i] - wasted[i]));

  new Chart(document.getElementById('snacksChart'), {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [
        { label: 'Loaded Today', data: loaded, backgroundColor: '#4a90d9' },
        { label: 'Old Stock',    data: oldStk,  backgroundColor: '#adb5bd' },
        { label: 'Sold',         data: sold,    backgroundColor: '#b8860b' },
        { label: 'Wasted',       data: wasted,  backgroundColor: '#dc3545' },
        { label: 'Left',         data: left,    backgroundColor: '#28a745' },
      ]
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      plugins: { legend: { position: 'bottom' } },
      scales: { x: { beginAtZero: true } }
    }
  });
})();
</script>
<?php endif; ?>
