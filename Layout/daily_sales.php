<?php
include("web_shopadmin_header.php");
date_default_timezone_set('Asia/Kolkata');

// --- Default time range: shop session 2 PM → 2 AM next day ---
if (!isset($_SESSION['userpermission']) || $_SESSION['userpermission'] !== 'Super Admin') {
    header('Location: shopadmin.php');
    exit;
}

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

// Auto-create wastage table in case stock_dashboard hasn't been visited yet
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS daily_wastage (
        id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL,
        prod_name VARCHAR(255) NOT NULL, wastage_qty INT NOT NULL DEFAULT 0,
        reason ENUM('unsold','damaged','other') NOT NULL DEFAULT 'unsold',
        notes VARCHAR(500) DEFAULT NULL, wastage_date DATE NOT NULL, created_at DATETIME NOT NULL
    )
");

// --- All loaded products (current session, snacks only, not expired) ---
$allLoadedRes = mysqli_query($conn, "
    SELECT
        p.p_id,
        p.name,
        c.categorie AS cat_name,
        COALESCE(da.available_qty, da_prev.old_qty) AS loaded,
        IFNULL(sold.qty_sold, 0)      AS sold,
        IFNULL(wasted.wastage_qty, 0) AS wasted
    FROM products p
    JOIN categorie c ON c.cat_id = p.categorie
    LEFT JOIN daily_availability da
           ON da.product_id    = p.p_id
          AND da.available_date = '$session_date'
    LEFT JOIN (
        SELECT da2.product_id, da2.available_qty AS old_qty, da2.updated_at
        FROM daily_availability da2
        INNER JOIN (
            SELECT product_id, MAX(available_date) AS max_date
            FROM daily_availability
            WHERE available_date < '$session_date'
            GROUP BY product_id
        ) latest ON latest.product_id = da2.product_id
                AND latest.max_date   = da2.available_date
    ) da_prev ON da_prev.product_id = p.p_id
             AND da.available_qty IS NULL
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
    WHERE (da.available_qty IS NOT NULL OR da_prev.old_qty IS NOT NULL)
      AND p.categorie = 2
      AND (
          p.expiry_value IS NULL OR p.expiry_type IS NULL
          OR NOW() < CASE UPPER(p.expiry_type)
              WHEN 'MINUTE' THEN DATE_ADD(COALESCE(da.updated_at, da_prev.updated_at), INTERVAL p.expiry_value MINUTE)
              WHEN 'HOUR'   THEN DATE_ADD(COALESCE(da.updated_at, da_prev.updated_at), INTERVAL p.expiry_value HOUR)
              WHEN 'DAY'    THEN DATE_ADD(COALESCE(da.updated_at, da_prev.updated_at), INTERVAL p.expiry_value DAY)
              ELSE DATE_ADD(COALESCE(da.updated_at, da_prev.updated_at), INTERVAL p.expiry_value DAY)
          END
      )
    ORDER BY p.name
");
$allLoadedRows = [];
while ($r = mysqli_fetch_assoc($allLoadedRes)) $allLoadedRows[] = $r;
?>

<main>
  <!-- Header -->
  <nav class="navbar navbar-expand-lg navbar-light" style="background-color:#b8860b !important;">
    <div class="container-fluid d-flex flex-wrap align-items-center justify-content-between">
      <div class="d-flex align-items-center">
        <img src="../images/logo.jpg" alt="Logo" class="img-fluid" style="max-height:50px;">
        <h6 class="ms-2 text-white mb-0">Daily Sales</h6>
      </div>
      <div class="d-flex align-items-center gap-2">
        <a href="ai_daily_summary.php?start_time=<?php echo urlencode(str_replace(' ', 'T', $start_time)); ?>&end_time=<?php echo urlencode(str_replace(' ', 'T', $end_time)); ?>"
           class="btn btn-sm fw-semibold text-white" style="background:#16213e;">
          🤖 AI Summary
        </a>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#myModal">
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

    <!-- Loaded Stock Tracker -->
    <?php if (!empty($allLoadedRows)): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-3">
      <div class="card-header text-white fw-bold d-flex justify-content-between align-items-center" style="background:#b8860b;">
        <span>📦 Loaded Stock — Status</span>
        <small class="opacity-75"><?php echo htmlspecialchars($session_date); ?></small>
      </div>
      <div class="card-body p-3">
        <?php foreach ($allLoadedRows as $row):
          $loaded    = (int)$row['loaded'];
          $sold      = (int)$row['sold'];
          $wasted    = (int)$row['wasted'];
          $remaining = max(0, $loaded - $sold - $wasted);
          $pct_sold  = $loaded > 0 ? round(($sold   / $loaded) * 100) : 0;
          $pct_waste = $loaded > 0 ? round(($wasted / $loaded) * 100) : 0;
        ?>
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-1">
            <span class="fw-semibold">
              <?php echo htmlspecialchars($row['name']); ?>
              <span class="badge bg-secondary ms-1" style="font-size:0.7rem;"><?php echo htmlspecialchars($row['cat_name']); ?></span>
            </span>
            <div class="d-flex align-items-center gap-2">
              <span class="text-muted small">
                Loaded: <strong><?php echo $loaded; ?></strong> &nbsp;
                Sold: <strong class="text-success"><?php echo $sold; ?></strong> &nbsp;
                <?php if ($wasted > 0): ?>
                Wasted: <strong class="text-danger"><?php echo $wasted; ?></strong>
                <?php endif; ?>
              </span>
              <button class="btn btn-outline-danger btn-sm py-0 px-2"
                      style="font-size:0.75rem;"
                      onclick="openWastageModal(<?php echo $row['p_id']; ?>, '<?php echo addslashes(htmlspecialchars($row['name'])); ?>')">
                + Wastage
              </button>
            </div>
          </div>
          <div class="progress" style="height:16px;border-radius:8px;background:#e9ecef;">
            <div class="progress-bar" style="width:<?php echo $pct_sold; ?>%;background:#198754;border-radius:8px 0 0 8px;font-size:0.72rem;">
              <?php echo $pct_sold > 12 ? $pct_sold . '%' : ''; ?>
            </div>
            <?php if ($pct_waste > 0): ?>
            <div class="progress-bar" style="width:<?php echo $pct_waste; ?>%;background:#dc3545;border-radius:0;font-size:0.72rem;">
              <?php echo $pct_waste > 8 ? $pct_waste . '%' : ''; ?>
            </div>
            <?php endif; ?>
          </div>
          <div class="d-flex gap-3 mt-1" style="font-size:0.72rem;color:#888;">
            <span><span style="display:inline-block;width:10px;height:10px;background:#198754;border-radius:2px;"></span> Sold</span>
            <span><span style="display:inline-block;width:10px;height:10px;background:#dc3545;border-radius:2px;"></span> Wasted</span>
            <span><span style="display:inline-block;width:10px;height:10px;background:#e9ecef;border:1px solid #ccc;border-radius:2px;"></span> Remaining</span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

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

<!-- Wastage Modal -->
<div class="modal fade" id="wastageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background:#fff3cd;">
        <h6 class="modal-title fw-bold">🗑 Log Wastage — <span id="wastageModalLabel"></span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="w_product_id">
        <input type="hidden" id="w_prod_name">
        <div class="mb-3">
          <label class="form-label fw-semibold">Quantity Wasted <span class="text-danger">*</span></label>
          <input type="number" id="w_qty" class="form-control" min="1" placeholder="e.g. 5">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Reason</label>
          <div class="d-flex gap-3">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="w_reason_radio" id="r_unsold" value="unsold" checked>
              <label class="form-check-label" for="r_unsold">Unsold</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="w_reason_radio" id="r_damaged" value="damaged">
              <label class="form-check-label" for="r_damaged">Damaged</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="w_reason_radio" id="r_other" value="other">
              <label class="form-check-label" for="r_other">Other</label>
            </div>
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label fw-semibold">Notes <span class="text-muted small">(optional)</span></label>
          <input type="text" id="w_notes" class="form-control" placeholder="e.g. fell, expired, returned">
        </div>
        <div id="wastageError" class="text-danger small d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" onclick="submitWastage()">Log Wastage</button>
      </div>
    </div>
  </div>
</div>

<?php include_once("web_shopadmin_footer.php"); ?>

<style>
@media (max-width: 576px) {
  table { font-size: 0.78rem; }
}
</style>

<?php if (!empty($allLoadedRows)): ?>
<script>
const AVAIL_DATE = <?php echo json_encode($session_date); ?>;

function openWastageModal(pid, pname) {
  document.getElementById('w_product_id').value = pid;
  document.getElementById('w_prod_name').value  = pname;
  document.getElementById('wastageModalLabel').textContent = pname;
  document.getElementById('w_qty').value   = '';
  document.getElementById('w_notes').value = '';
  document.querySelector('input[name="w_reason_radio"][value="unsold"]').checked = true;
  document.getElementById('wastageError').classList.add('d-none');
  new bootstrap.Modal(document.getElementById('wastageModal')).show();
}

function submitWastage() {
  const pid    = document.getElementById('w_product_id').value;
  const pname  = document.getElementById('w_prod_name').value;
  const qty    = parseInt(document.getElementById('w_qty').value);
  const reason = document.querySelector('input[name="w_reason_radio"]:checked').value;
  const notes  = document.getElementById('w_notes').value.trim();
  const errEl  = document.getElementById('wastageError');

  if (!qty || qty <= 0) {
    errEl.textContent = 'Please enter a valid quantity.';
    errEl.classList.remove('d-none');
    return;
  }
  errEl.classList.add('d-none');

  fetch(`../wastage_handler.php?action=log&product_id=${pid}&prod_name=${encodeURIComponent(pname)}&qty=${qty}&reason=${reason}&notes=${encodeURIComponent(notes)}&avail_date=${encodeURIComponent(AVAIL_DATE)}`)
    .then(r => r.json())
    .then(d => {
      if (d.ok) {
        bootstrap.Modal.getInstance(document.getElementById('wastageModal')).hide();
        location.reload();
      } else {
        errEl.textContent = d.msg || 'Failed to log wastage.';
        errEl.classList.remove('d-none');
      }
    })
    .catch(() => {
      errEl.textContent = 'Network error. Please try again.';
      errEl.classList.remove('d-none');
    });
}

function deleteWastage(id, btn) {
  if (!confirm('Remove this wastage entry?')) return;
  fetch(`../wastage_handler.php?action=delete&id=${id}`)
    .then(r => r.json())
    .then(d => { if (d.ok) location.reload(); });
}
</script>
<?php endif; ?>
