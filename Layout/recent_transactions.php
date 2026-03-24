<?php
include("web_shopadmin_header.php");
date_default_timezone_set('Asia/Kolkata');

// Handle wastage form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_wastage') {
    $w_product_id = (int)$_POST['product_id'];
    $w_prod_name  = trim($_POST['prod_name']);
    $w_qty        = (int)$_POST['wastage_qty'];
    $w_reason     = in_array($_POST['reason'], ['unsold','damaged','other']) ? $_POST['reason'] : 'unsold';
    $w_notes      = trim($_POST['notes'] ?? '');
    $w_date       = trim($_POST['wastage_date']);

    if ($w_product_id > 0 && $w_qty > 0 && $w_date !== '') {
        $ins = $conn->prepare("INSERT INTO daily_wastage (product_id, prod_name, wastage_qty, reason, notes, wastage_date, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $ins->bind_param("isisss", $w_product_id, $w_prod_name, $w_qty, $w_reason, $w_notes, $w_date);
        $ins->execute();
        $ins->close();
    }
    // Redirect to same page to avoid re-submit
    $redirect = 'recent_transactions.php';
    if (!empty($_POST['redirect_qs'])) {
        $redirect .= '?' . $_POST['redirect_qs'];
    }
    header("Location: $redirect");
    exit;
}

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
    if ((int)$row['loaded'] <= 0) continue;  // skip items with no loaded stock
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
$transactions = [];
$tableTotal = 0;
while ($row = $transactionResult->fetch_assoc()) {
    $tableTotal += floatval($row['total_amount']);
    $transactions[] = $row;
}
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
        <span>₹<?php echo number_format($tableTotal, 2); ?></span>
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
            <?php if (empty($transactions)): ?>
            <tr><td colspan="5" class="text-center text-muted py-3">No transactions found for this period.</td></tr>
            <?php else: ?>
            <?php foreach ($transactions as $txn): ?>
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
            <?php endforeach; ?>
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
              <th>Loaded</th>
              <th>Sold</th>
              <th>Wasted</th>
              <th>Left</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($allLoadedRows)): ?>
            <tr><td colspan="6" class="text-center text-muted py-3">No stock data found for this session.</td></tr>
            <?php else: ?>
            <?php foreach ($allLoadedRows as $row): ?>
            <tr>
              <td class="text-start"><?php echo htmlspecialchars($row['name']); ?></td>
              <td><?php echo (int)$row['loaded']; ?></td>
              <td class="text-success fw-semibold"><?php echo (int)$row['sold']; ?></td>
              <td class="text-danger fw-semibold"><?php echo (int)$row['wasted']; ?></td>
              <td class="fw-bold"><?php echo (int)$row['remaining']; ?></td>
              <td>
                <button class="btn btn-sm btn-outline-danger px-2 py-0"
                  onclick="openWastage(<?php echo (int)$row['p_id']; ?>, '<?php echo addslashes($row['name']); ?>', <?php echo (int)$row['remaining']; ?>)">
                  <i class="fa fa-trash-o"></i>
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<!-- Wastage Modal -->
<div class="modal fade" id="wastageModal" tabindex="-1" aria-labelledby="wastageModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4">
      <div class="modal-header text-white" style="background:#b8860b;">
        <h6 class="modal-title" id="wastageModalLabel"><i class="fa fa-trash-o me-2"></i>Mark Wastage</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="recent_transactions.php">
        <input type="hidden" name="action" value="mark_wastage">
        <input type="hidden" name="product_id" id="w_product_id">
        <input type="hidden" name="prod_name" id="w_prod_name_hidden">
        <input type="hidden" name="wastage_date" value="<?php echo htmlspecialchars($session_date); ?>">
        <input type="hidden" name="redirect_qs" value="<?php echo htmlspecialchars(http_build_query(array_filter(['start_time' => $_GET['start_time'] ?? '', 'end_time' => $_GET['end_time'] ?? '']))); ?>">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Product</label>
            <input type="text" class="form-control" id="w_prod_name_display" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Wastage Qty <span class="text-muted small" id="w_max_label"></span></label>
            <input type="number" name="wastage_qty" id="w_qty" class="form-control" min="1" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Reason</label>
            <select name="reason" class="form-select">
              <option value="unsold">Unsold / Expired</option>
              <option value="damaged">Damaged</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="mb-1">
            <label class="form-label fw-semibold">Notes <span class="text-muted small">(optional)</span></label>
            <textarea name="notes" class="form-control" rows="2" maxlength="500"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Record Wastage</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openWastage(pid, name, remaining) {
  document.getElementById('w_product_id').value = pid;
  document.getElementById('w_prod_name_hidden').value = name;
  document.getElementById('w_prod_name_display').value = name;
  document.getElementById('w_qty').value = remaining > 0 ? remaining : 1;
  document.getElementById('w_qty').max = remaining > 0 ? remaining : 9999;
  document.getElementById('w_max_label').textContent = remaining > 0 ? '(max ' + remaining + ')' : '';
  var modal = new bootstrap.Modal(document.getElementById('wastageModal'));
  modal.show();
}
</script>

<?php include_once("web_shopadmin_footer.php"); ?>

<style>
@media (max-width: 576px) {
  table { font-size: 0.8rem; }
}
</style>
