<?php
include("web_shopadmin_header.php");
date_default_timezone_set('Asia/Kolkata');

// Default shop session: 2 PM to 2 AM next day
$hour = (int)date('H');
if ($hour < 2) {
    $session_date = date('Y-m-d', strtotime('-1 day'));
} else {
    $session_date = date('Y-m-d');
}
$def_start = $session_date . ' 14:00';
$def_end   = date('Y-m-d', strtotime($session_date . ' +1 day')) . ' 02:00';

$start_time = isset($_GET['start_time']) && $_GET['start_time'] !== '' ? $_GET['start_time'] : $def_start;
$end_time   = isset($_GET['end_time']) && $_GET['end_time'] !== '' ? $_GET['end_time'] : $def_end;

$start_time = str_replace('T', ' ', $start_time);
$end_time   = str_replace('T', ' ', $end_time);

$selectedStartTs = strtotime($start_time);
if ((int)date('H', $selectedStartTs) < 2) {
    $session_date = date('Y-m-d', strtotime('-1 day', $selectedStartTs));
} else {
    $session_date = date('Y-m-d', $selectedStartTs);
}

$baseWhere = "
    ds.`payment status` != 'notpaid'
    AND ds.Time >= ?
    AND ds.Time <= ?
    AND ds.`Payment Mode` IN ('cash', 'upi')
";

$summarySQL = "
    SELECT
        COUNT(DISTINCT ds.`Inv no`) AS total_transactions,
        SUM(ds.quantity) AS total_items,
        SUM(ds.quantity * ds.`Selling Price`) AS total_amount
    FROM daily_productsale ds
    WHERE $baseWhere
";
$summaryStmt = $conn->prepare($summarySQL);
$summaryStmt->bind_param("ss", $start_time, $end_time);
$summaryStmt->execute();
$summary = $summaryStmt->get_result()->fetch_assoc();
$summaryStmt->close();

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS daily_wastage (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        prod_name VARCHAR(255) NOT NULL,
        wastage_qty INT NOT NULL DEFAULT 0,
        reason ENUM('unsold','damaged','other') NOT NULL DEFAULT 'unsold',
        notes VARCHAR(500) DEFAULT NULL,
        wastage_date DATE NOT NULL,
        created_at DATETIME NOT NULL
    )
");

$loadedSQL = "
    SELECT
        p.p_id,
        p.name,
        c.categorie AS cat_name,
        COALESCE(da.available_qty, da_prev.old_qty, 0) AS loaded,
        IFNULL(sold.qty_sold, 0) AS sold,
        IFNULL(wasted.wastage_qty, 0) AS wasted
    FROM products p
    JOIN categorie c ON c.cat_id = p.categorie
    LEFT JOIN daily_availability da
           ON da.product_id = p.p_id
          AND da.available_date = ?
    LEFT JOIN (
        SELECT da2.product_id, da2.available_qty AS old_qty, da2.updated_at
        FROM daily_availability da2
        INNER JOIN (
            SELECT product_id, MAX(available_date) AS max_date
            FROM daily_availability
            WHERE available_date < ?
            GROUP BY product_id
        ) latest ON latest.product_id = da2.product_id
                AND latest.max_date = da2.available_date
    ) da_prev ON da_prev.product_id = p.p_id
             AND da.available_qty IS NULL
    LEFT JOIN (
        SELECT p_id, SUM(quantity) AS qty_sold
        FROM daily_productsale
        WHERE `payment status` != 'notpaid'
          AND Time >= ?
          AND Time <= ?
        GROUP BY p_id
    ) sold ON sold.p_id = p.p_id
    LEFT JOIN (
        SELECT product_id, SUM(wastage_qty) AS wastage_qty
        FROM daily_wastage
        WHERE wastage_date = ?
        GROUP BY product_id
    ) wasted ON wasted.product_id = p.p_id
    WHERE (da.available_qty IS NOT NULL OR da_prev.old_qty IS NOT NULL)
      AND p.categorie = 2
      AND (
          p.expiry_value IS NULL OR p.expiry_type IS NULL
          OR NOW() < CASE UPPER(p.expiry_type)
              WHEN 'MINUTE' THEN DATE_ADD(COALESCE(da.updated_at, da_prev.updated_at), INTERVAL p.expiry_value MINUTE)
              WHEN 'HOUR' THEN DATE_ADD(COALESCE(da.updated_at, da_prev.updated_at), INTERVAL p.expiry_value HOUR)
              WHEN 'DAY' THEN DATE_ADD(COALESCE(da.updated_at, da_prev.updated_at), INTERVAL p.expiry_value DAY)
              ELSE DATE_ADD(COALESCE(da.updated_at, da_prev.updated_at), INTERVAL p.expiry_value DAY)
          END
      )
    ORDER BY p.name
";
$loadedStmt = $conn->prepare($loadedSQL);
$loadedStmt->bind_param("sssss", $session_date, $session_date, $start_time, $end_time, $session_date);
$loadedStmt->execute();
$loadedRes = $loadedStmt->get_result();

$allLoadedRows = [];
$leftoverStock = 0;
while ($row = $loadedRes->fetch_assoc()) {
    $row['remaining'] = max(0, (int)$row['loaded'] - (int)$row['sold'] - (int)$row['wasted']);
    $leftoverStock += $row['remaining'];
    $allLoadedRows[] = $row;
}
$loadedStmt->close();

$transactionSQL = "
    SELECT
        ds.`Inv no` AS inv_no,
        MAX(ds.Time) AS txn_time,
        MAX(ds.`Payment Mode`) AS pay_mode,
        SUM(ds.quantity) AS total_items,
        SUM(ds.quantity * ds.`Selling Price`) AS total_amount
    FROM daily_productsale ds
    WHERE $baseWhere
    GROUP BY ds.`Inv no`
    ORDER BY MAX(ds.Time) DESC
    LIMIT 5
";
$transactionStmt = $conn->prepare($transactionSQL);
$transactionStmt->bind_param("ss", $start_time, $end_time);
$transactionStmt->execute();
$transactionResult = $transactionStmt->get_result();
$transactionStmt->close();
?>

<main>
  <nav class="navbar navbar-expand-lg navbar-light" style="background-color:#b8860b !important;">
    <div class="container-fluid d-flex flex-wrap align-items-center justify-content-between">
      <div class="d-flex align-items-center">
        <img src="../images/logo.jpg" alt="Logo" class="img-fluid" style="max-height:50px;">
        <h6 class="ms-2 text-white mb-0">Recent Transactions</h6>
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
    <div class="card shadow-sm border-0 rounded-4 mb-3">
      <div class="card-body">
        <form method="GET" action="recent_transactions.php" class="row g-2 align-items-end">
          <div class="col-md-5 col-6">
            <label class="form-label mb-1">From</label>
            <input type="datetime-local" name="start_time" class="form-control"
              value="<?php echo htmlspecialchars(str_replace(' ', 'T', $start_time)); ?>">
          </div>
          <div class="col-md-5 col-6">
            <label class="form-label mb-1">To</label>
            <input type="datetime-local" name="end_time" class="form-control"
              value="<?php echo htmlspecialchars(str_replace(' ', 'T', $end_time)); ?>">
          </div>
          <div class="col-md-2 col-12">
            <button type="submit" class="btn btn-warning text-white w-100">Apply</button>
          </div>
        </form>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-4">
        <div class="card text-center border-0 shadow-sm rounded-4 h-100">
          <div class="card-body py-3">
            <div style="font-size:1.6rem;font-weight:bold;color:#b8860b;">
              <?php echo (int)($summary['total_transactions'] ?? 0); ?>
            </div>
            <div class="text-muted small">Transactions</div>
          </div>
        </div>
      </div>
      <div class="col-4">
        <div class="card text-center border-0 shadow-sm rounded-4 h-100">
          <div class="card-body py-3">
            <div style="font-size:1.6rem;font-weight:bold;color:#b8860b;">
              <?php echo (int)($summary['total_items'] ?? 0); ?>
            </div>
            <div class="text-muted small">Items Sold</div>
          </div>
        </div>
      </div>
      <div class="col-4">
        <div class="card text-center border-0 shadow-sm rounded-4 h-100">
          <div class="card-body py-3">
            <div style="font-size:1.6rem;font-weight:bold;color:#b8860b;">
              <?php echo (int)$leftoverStock; ?>
            </div>
            <div class="text-muted small">Leftover Stock</div>
          </div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm border-0 rounded-4 mb-3">
      <div class="card-header text-white d-flex justify-content-between align-items-center" style="background:#b8860b;">
        <strong>Last 5 Cash / UPI Transactions</strong>
        <span>₹<?php echo number_format($summary['total_amount'] ?? 0, 2); ?></span>
      </div>
      <div class="card-body table-responsive p-0">
        <table class="table table-striped table-bordered align-middle text-center mb-0">
          <thead class="table-dark">
            <tr>
              <th>Time</th>
              <th>Inv#</th>
              <th>Mode</th>
              <th>Items</th>
              <th>Amount</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($transactionResult->num_rows === 0): ?>
            <tr><td colspan="5" class="text-center text-muted py-3">No transactions found for this period.</td></tr>
            <?php else: ?>
            <?php while ($txn = $transactionResult->fetch_assoc()): ?>
            <tr>
              <td><?php echo date('d M h:i A', strtotime($txn['txn_time'])); ?></td>
              <td><?php echo htmlspecialchars($txn['inv_no']); ?></td>
              <td>
                <?php if (strtolower($txn['pay_mode']) === 'cash'): ?>
                  <span class="badge bg-success">Cash</span>
                <?php else: ?>
                  <span class="badge bg-primary">UPI</span>
                <?php endif; ?>
              </td>
              <td><?php echo (int)$txn['total_items']; ?></td>
              <td>₹<?php echo number_format($txn['total_amount'], 2); ?></td>
            </tr>
            <?php endwhile; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-header text-white fw-bold d-flex justify-content-between align-items-center" style="background:#b8860b;">
        <span>Loaded Stock Status</span>
        <small class="opacity-75"><?php echo htmlspecialchars($session_date); ?></small>
      </div>
      <div class="card-body table-responsive p-0">
        <table class="table table-striped table-bordered align-middle text-center mb-0">
          <thead class="table-dark">
            <tr>
              <th>Item</th>
              <th>Category</th>
              <th>Loaded</th>
              <th>Sold</th>
              <th>Wasted</th>
              <th>Left</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($allLoadedRows)): ?>
            <tr><td colspan="6" class="text-center text-muted py-3">No stock data found for this session.</td></tr>
            <?php else: ?>
            <?php foreach ($allLoadedRows as $row): ?>
            <tr>
              <td><?php echo htmlspecialchars($row['name']); ?></td>
              <td><?php echo htmlspecialchars($row['cat_name']); ?></td>
              <td><?php echo (int)$row['loaded']; ?></td>
              <td class="text-success fw-semibold"><?php echo (int)$row['sold']; ?></td>
              <td class="text-danger fw-semibold"><?php echo (int)$row['wasted']; ?></td>
              <td class="fw-bold"><?php echo (int)$row['remaining']; ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<?php include_once("web_shopadmin_footer.php"); ?>

<style>
@media (max-width: 576px) {
  table { font-size: 0.8rem; }
}
</style>
