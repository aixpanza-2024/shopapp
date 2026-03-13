<?php
include("web_shopadmin_header.php");
date_default_timezone_set('Asia/Kolkata');

// Auto-create wastage table
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS daily_wastage (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        product_id  INT NOT NULL,
        prod_name   VARCHAR(255) NOT NULL,
        wastage_qty INT NOT NULL DEFAULT 0,
        reason      ENUM('unsold','damaged','other') NOT NULL DEFAULT 'unsold',
        notes       VARCHAR(500) DEFAULT NULL,
        wastage_date DATE NOT NULL,
        created_at  DATETIME NOT NULL
    )
");

// --- Shop session: opens 2 PM, closes 2 AM next day ---
$hour = (int)date('H');
if ($hour < 2) {
    $session_date = date('Y-m-d', strtotime('-1 day'));
} else {
    $session_date = date('Y-m-d');
}
$next_date = date('Y-m-d', strtotime($session_date . ' +1 day'));

$def_start = $session_date . ' 14:00';
$def_end   = $next_date   . ' 02:00';

$start_time = isset($_GET['start_time']) && $_GET['start_time'] !== ''
    ? str_replace('T', ' ', $_GET['start_time']) : $def_start;
$end_time   = isset($_GET['end_time'])   && $_GET['end_time']   !== ''
    ? str_replace('T', ' ', $_GET['end_time'])   : $def_end;

$avail_date = date('Y-m-d', strtotime($start_time));

// ── 1. SUMMARY ────────────────────────────────────────────────────────────────
$summRes = mysqli_query($conn, "
    SELECT
        COUNT(DISTINCT `Inv no`)                          AS bills,
        IFNULL(SUM(quantity), 0)                         AS items,
        IFNULL(SUM(quantity * `Selling Price`), 0)       AS revenue
    FROM daily_productsale
    WHERE Time >= '$start_time'
      AND Time <= '$end_time'
      AND `payment status` != 'notpaid'
");
$summ = mysqli_fetch_assoc($summRes);

// Wastage summary for this session date
$wasteSummRes = mysqli_query($conn, "
    SELECT IFNULL(SUM(wastage_qty), 0) AS total_wasted
    FROM daily_wastage
    WHERE wastage_date = '$avail_date'
");
$wasteSumm = mysqli_fetch_assoc($wasteSummRes);

// ── 2. LOADED PRODUCTS (with sold + wastage, including carry-over stock) ──────
$loadedRes = mysqli_query($conn, "
    SELECT
        p.p_id,
        p.name,
        c.categorie AS cat_name,
        COALESCE(da.available_qty, da_prev.old_qty) AS loaded,
        IFNULL((
            SELECT SUM(ps.quantity)
            FROM daily_productsale ps
            WHERE ps.p_id = p.p_id
              AND ps.Time >= '$start_time'
              AND ps.Time <= '$end_time'
              AND ps.`payment status` != 'notpaid'
        ), 0) AS sold,
        IFNULL((
            SELECT SUM(dw.wastage_qty)
            FROM daily_wastage dw
            WHERE dw.product_id = p.p_id
              AND dw.wastage_date = '$avail_date'
        ), 0) AS wasted
    FROM products p
    JOIN categorie c ON c.cat_id = p.categorie
    LEFT JOIN daily_availability da
           ON da.product_id    = p.p_id
          AND da.available_date = '$avail_date'
    LEFT JOIN (
        SELECT da2.product_id, da2.available_qty AS old_qty
        FROM daily_availability da2
        INNER JOIN (
            SELECT product_id, MAX(available_date) AS max_date
            FROM daily_availability
            WHERE available_date < '$avail_date'
            GROUP BY product_id
        ) latest ON latest.product_id = da2.product_id
                AND latest.max_date   = da2.available_date
    ) da_prev ON da_prev.product_id = p.p_id
             AND da.available_qty IS NULL
    WHERE (da.available_qty IS NOT NULL OR da_prev.old_qty IS NOT NULL)
    ORDER BY c.cat_id, p.name
");

// ── 3. SALES BY CATEGORY ──────────────────────────────────────────────────────
$catRes = mysqli_query($conn, "
    SELECT
        c.categorie  AS cat_name,
        IFNULL(SUM(ds.quantity), 0)                        AS qty_sold,
        IFNULL(SUM(ds.quantity * ds.`Selling Price`), 0)   AS revenue
    FROM daily_productsale ds
    JOIN products p  ON p.p_id    = ds.p_id
    JOIN categorie c ON c.cat_id  = p.categorie
    WHERE ds.Time >= '$start_time'
      AND ds.Time <= '$end_time'
      AND ds.`payment status` != 'notpaid'
    GROUP BY c.cat_id, c.categorie
    ORDER BY qty_sold DESC
");
$catRows   = [];
$chartLbls = [];
$chartQty  = [];
$chartRev  = [];
while ($r = mysqli_fetch_assoc($catRes)) {
    $catRows[]   = $r;
    $chartLbls[] = $r['cat_name'];
    $chartQty[]  = (int)$r['qty_sold'];
    $chartRev[]  = (float)$r['revenue'];
}

// ── 4. HOURLY SALES ───────────────────────────────────────────────────────────
$hourlyRes = mysqli_query($conn, "
    SELECT HOUR(Time) AS hr, SUM(quantity) AS items,
           IFNULL(SUM(quantity * `Selling Price`), 0) AS revenue
    FROM daily_productsale
    WHERE Time >= '$start_time' AND Time <= '$end_time'
      AND `payment status` != 'notpaid'
    GROUP BY HOUR(Time) ORDER BY hr
");
$sessionHours = array_merge(range(14, 23), range(0, 2));
$hourlyItems  = array_fill_keys($sessionHours, 0);
$hourlyRev    = array_fill_keys($sessionHours, 0);
while ($h = mysqli_fetch_assoc($hourlyRes)) {
    $hr = (int)$h['hr'];
    if (isset($hourlyItems[$hr])) {
        $hourlyItems[$hr] = (int)$h['items'];
        $hourlyRev[$hr]   = (float)$h['revenue'];
    }
}
$hourLabels = [];
foreach ($sessionHours as $hr) $hourLabels[] = date('h A', mktime($hr, 0, 0));

// ── 5. 7-SESSION TREND ────────────────────────────────────────────────────────
$trendRows = [];
for ($i = 6; $i >= 0; $i--) {
    $s_date  = date('Y-m-d', strtotime("-$i days", strtotime($session_date)));
    $trendRows[$s_date] = [
        'label' => date('d M', strtotime($s_date)),
        'start' => $s_date . ' 14:00:00',
        'end'   => date('Y-m-d', strtotime($s_date . ' +1 day')) . ' 02:00:00'
    ];
}
$trendStmt = $conn->prepare("
    SELECT DATE(Time) AS sale_date,
           COUNT(DISTINCT `Inv no`) AS bills,
           IFNULL(SUM(quantity), 0) AS items,
           IFNULL(SUM(quantity * `Selling Price`), 0) AS revenue
    FROM daily_productsale
    WHERE `payment status` != 'notpaid'
      AND (" . implode(' OR ', array_fill(0, 7, "(Time >= ? AND Time <= ?)")) . ")
    GROUP BY DATE(Time)
");
$bindArgs = [];
foreach ($trendRows as $tr) { $bindArgs[] = $tr['start']; $bindArgs[] = $tr['end']; }
$trendStmt->bind_param(str_repeat('ss', 7), ...$bindArgs);
$trendStmt->execute();
$trendMap = [];
$trendResult = $trendStmt->get_result();
while ($t = mysqli_fetch_assoc($trendResult)) $trendMap[$t['sale_date']] = $t;
$trendStmt->close();

$trendLabels = []; $trendItems = []; $trendRevenue = []; $trendBills = [];
foreach ($trendRows as $s_date => $tr) {
    $trendLabels[]  = $tr['label'];
    $trendItems[]   = isset($trendMap[$s_date]) ? (int)$trendMap[$s_date]['items']   : 0;
    $trendRevenue[] = isset($trendMap[$s_date]) ? (float)$trendMap[$s_date]['revenue'] : 0;
    $trendBills[]   = isset($trendMap[$s_date]) ? (int)$trendMap[$s_date]['bills']   : 0;
}

// ── 6. PRODUCT-LEVEL SALES ────────────────────────────────────────────────────
$prodRes = mysqli_query($conn, "
    SELECT p.name, c.categorie AS cat_name,
           IFNULL(SUM(ds.quantity), 0) AS qty_sold,
           IFNULL(SUM(ds.quantity * ds.`Selling Price`), 0) AS revenue
    FROM daily_productsale ds
    JOIN products p  ON p.p_id   = ds.p_id
    JOIN categorie c ON c.cat_id = p.categorie
    WHERE ds.Time >= '$start_time' AND ds.Time <= '$end_time'
      AND ds.`payment status` != 'notpaid'
    GROUP BY ds.p_id ORDER BY qty_sold DESC
");

// ── 7. WASTAGE LOG FOR THIS SESSION ──────────────────────────────────────────
$wasteLogRes = mysqli_query($conn, "
    SELECT id, prod_name, wastage_qty, reason, notes,
           DATE_FORMAT(created_at, '%h:%i %p') AS time_logged
    FROM daily_wastage
    WHERE wastage_date = '$avail_date'
    ORDER BY created_at DESC
");
$wasteLog = [];
while ($w = mysqli_fetch_assoc($wasteLogRes)) $wasteLog[] = $w;
?>

<main>
<!-- ── HEADER ── -->
<nav class="navbar navbar-expand-lg navbar-light" style="background-color:#b8860b !important;">
  <div class="container-fluid d-flex flex-wrap align-items-center justify-content-between">
    <div class="d-flex align-items-center">
      <img src="../images/logo.jpg" alt="Logo" class="img-fluid" style="max-height:50px;">
      <h6 class="ms-2 text-white mb-0">Stock &amp; Sales Dashboard</h6>
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

  <!-- ── DATE-TIME FILTER ── -->
  <div class="card shadow-sm border-0 rounded-4 mb-3">
    <div class="card-body">
      <form method="GET" action="stock_dashboard.php" class="row g-2 align-items-end">
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

  <!-- ── SUMMARY CARDS ── -->
  <div class="row g-2 mb-4">
    <div class="col-3">
      <div class="card border-0 shadow-sm rounded-4 text-center h-100">
        <div class="card-body py-3 px-1">
          <div style="font-size:1.6rem;font-weight:700;color:#b8860b;"><?php echo intval($summ['bills']); ?></div>
          <div class="text-muted small">Bills</div>
        </div>
      </div>
    </div>
    <div class="col-3">
      <div class="card border-0 shadow-sm rounded-4 text-center h-100">
        <div class="card-body py-3 px-1">
          <div style="font-size:1.6rem;font-weight:700;color:#b8860b;"><?php echo intval($summ['items']); ?></div>
          <div class="text-muted small">Sold</div>
        </div>
      </div>
    </div>
    <div class="col-3">
      <div class="card border-0 shadow-sm rounded-4 text-center h-100">
        <div class="card-body py-3 px-1">
          <div style="font-size:1.3rem;font-weight:700;color:#b8860b;">₹<?php echo number_format($summ['revenue'], 0); ?></div>
          <div class="text-muted small">Revenue</div>
        </div>
      </div>
    </div>
    <div class="col-3">
      <div class="card border-0 shadow-sm rounded-4 text-center h-100">
        <div class="card-body py-3 px-1">
          <div style="font-size:1.6rem;font-weight:700;color:#dc3545;"><?php echo intval($wasteSumm['total_wasted']); ?></div>
          <div class="text-muted small">Wastage</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── LOADED STOCK TRACKER ── -->
  <?php if (mysqli_num_rows($loadedRes) > 0): ?>
  <div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header text-white fw-bold d-flex justify-content-between align-items-center" style="background:#b8860b;">
      <span>📦 Loaded Stock — Status</span>
      <small class="opacity-75"><?php echo htmlspecialchars($avail_date); ?></small>
    </div>
    <div class="card-body p-3">
      <?php while ($row = mysqli_fetch_assoc($loadedRes)):
        $loaded    = (int)$row['loaded'];
        $sold      = (int)$row['sold'];
        $wasted    = (int)$row['wasted'];
        $remaining = max(0, $loaded - $sold - $wasted);
        $pct_sold  = $loaded > 0 ? round(($sold   / $loaded) * 100) : 0;
        $pct_waste = $loaded > 0 ? round(($wasted / $loaded) * 100) : 0;
        $barColor  = ($sold + $wasted) >= $loaded ? '#dc3545'
                   : ($pct_sold >= 60             ? '#ffc107' : '#198754');
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
              Wasted: <strong class="text-danger"><?php echo $wasted; ?></strong> &nbsp;
              <?php endif; ?>
              Left: <strong><?php echo $remaining; ?></strong>
            </span>
            <button class="btn btn-outline-danger btn-sm py-0 px-2"
                    style="font-size:0.75rem;"
                    onclick="openWastageModal(<?php echo $row['p_id']; ?>, '<?php echo addslashes(htmlspecialchars($row['name'])); ?>')">
              + Wastage
            </button>
          </div>
        </div>
        <!-- Stacked progress: sold + wasted -->
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
      <?php endwhile; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── WASTAGE LOG ── -->
  <?php if (!empty($wasteLog)): ?>
  <div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header fw-bold" style="background:#fff3cd;">
      🗑 Wastage Log — <?php echo htmlspecialchars($avail_date); ?>
    </div>
    <div class="card-body p-0">
      <table class="table table-sm table-striped align-middle mb-0 text-center" style="font-size:0.85rem;">
        <thead class="table-dark">
          <tr><th>Time</th><th>Product</th><th>Qty</th><th>Reason</th><th>Notes</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($wasteLog as $wl): ?>
          <tr>
            <td><?php echo htmlspecialchars($wl['time_logged']); ?></td>
            <td class="text-start"><?php echo htmlspecialchars($wl['prod_name']); ?></td>
            <td><strong class="text-danger"><?php echo intval($wl['wastage_qty']); ?></strong></td>
            <td>
              <?php
              $badges = ['unsold' => 'bg-secondary', 'damaged' => 'bg-danger', 'other' => 'bg-warning text-dark'];
              $badge  = $badges[$wl['reason']] ?? 'bg-secondary';
              echo '<span class="badge ' . $badge . '">' . ucfirst(htmlspecialchars($wl['reason'])) . '</span>';
              ?>
            </td>
            <td class="text-muted small"><?php echo htmlspecialchars($wl['notes'] ?: '—'); ?></td>
            <td>
              <button class="btn btn-outline-danger btn-sm py-0 px-1" style="font-size:0.7rem;"
                      onclick="deleteWastage(<?php echo intval($wl['id']); ?>, this)">✕</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── CHART + CATEGORY TABLE ── -->
  <div class="row g-3 mb-4">
    <div class="col-md-7">
      <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-header text-white fw-bold" style="background:#b8860b;">📊 Sales by Category</div>
        <div class="card-body"><canvas id="catChart" height="200"></canvas></div>
      </div>
    </div>
    <div class="col-md-5">
      <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-header text-white fw-bold" style="background:#b8860b;">🗂 Category Breakdown</div>
        <div class="card-body p-0">
          <table class="table table-striped align-middle mb-0 text-center" style="font-size:0.85rem;">
            <thead class="table-dark">
              <tr><th>Category</th><th>Items Sold</th><th>Revenue</th></tr>
            </thead>
            <tbody>
              <?php foreach ($catRows as $cr): ?>
              <tr>
                <td><?php echo htmlspecialchars($cr['cat_name']); ?></td>
                <td><strong><?php echo intval($cr['qty_sold']); ?></strong></td>
                <td>₹<?php echo number_format($cr['revenue'], 0); ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($catRows)): ?>
              <tr><td colspan="3" class="text-muted py-3">No sales in this period.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- ── LINE CHARTS ── -->
  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-header text-white fw-bold" style="background:#b8860b;">⏰ Hourly Sales</div>
        <div class="card-body"><canvas id="hourlyChart" height="220"></canvas></div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-header text-white fw-bold" style="background:#b8860b;">📈 7-Session Trend</div>
        <div class="card-body"><canvas id="trendChart" height="220"></canvas></div>
      </div>
    </div>
  </div>

  <!-- ── PRODUCT-LEVEL TABLE ── -->
  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-header text-white fw-bold" style="background:#b8860b;">🧾 Product-Level Sales</div>
    <div class="card-body table-responsive p-0">
      <table class="table table-striped table-bordered align-middle text-center mb-0" style="font-size:0.85rem;">
        <thead class="table-dark">
          <tr><th>Product</th><th>Category</th><th>Qty Sold</th><th>Revenue</th></tr>
        </thead>
        <tbody>
          <?php
          $grandQty = 0; $grandRev = 0;
          while ($pr = mysqli_fetch_assoc($prodRes)):
            $grandQty += $pr['qty_sold']; $grandRev += $pr['revenue'];
          ?>
          <tr>
            <td><?php echo htmlspecialchars($pr['name']); ?></td>
            <td><?php echo htmlspecialchars($pr['cat_name']); ?></td>
            <td><strong><?php echo intval($pr['qty_sold']); ?></strong></td>
            <td>₹<?php echo number_format($pr['revenue'], 0); ?></td>
          </tr>
          <?php endwhile; ?>
          <?php if ($grandQty === 0): ?>
          <tr><td colspan="4" class="text-muted py-3">No sales in this period.</td></tr>
          <?php else: ?>
          <tr class="table-warning fw-bold">
            <td colspan="2">Total</td>
            <td><?php echo $grandQty; ?></td>
            <td>₹<?php echo number_format($grandRev, 0); ?></td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- /container -->
</main>

<!-- ── WASTAGE MODAL ── -->
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

<!-- Language Modal -->
<div class="modal fade" id="myModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Language Settings</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body"><div id="google_translate_element"></div></div>
    </div>
  </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('catChart').getContext('2d'), {
  type: 'bar',
  data: {
    labels: <?php echo json_encode($chartLbls); ?>,
    datasets: [
      { label: 'Items Sold', data: <?php echo json_encode($chartQty); ?>, backgroundColor: '#b8860b', borderRadius: 6, yAxisID: 'y' },
      { label: 'Revenue (₹)', data: <?php echo json_encode($chartRev); ?>, backgroundColor: '#6c757d55', borderRadius: 6, yAxisID: 'y2', type: 'line', borderColor: '#6c757d', tension: 0.3, pointRadius: 4 }
    ]
  },
  options: {
    responsive: true, plugins: { legend: { position: 'top' } },
    scales: {
      y:  { beginAtZero: true, title: { display: true, text: 'Items' } },
      y2: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: '₹ Revenue' } }
    }
  }
});

new Chart(document.getElementById('hourlyChart').getContext('2d'), {
  type: 'line',
  data: {
    labels: <?php echo json_encode($hourLabels); ?>,
    datasets: [
      { label: 'Items Sold', data: <?php echo json_encode(array_values($hourlyItems)); ?>, borderColor: '#b8860b', backgroundColor: 'rgba(184,134,11,0.12)', fill: true, tension: 0.4, pointRadius: 3, yAxisID: 'y' },
      { label: 'Revenue (₹)', data: <?php echo json_encode(array_values($hourlyRev)); ?>, borderColor: '#198754', backgroundColor: 'rgba(25,135,84,0.08)', fill: false, tension: 0.4, pointRadius: 3, yAxisID: 'y2' }
    ]
  },
  options: {
    responsive: true, plugins: { legend: { position: 'top' } },
    scales: {
      x:  { ticks: { maxTicksLimit: 8, font: { size: 10 } } },
      y:  { beginAtZero: true, title: { display: true, text: 'Items' } },
      y2: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: '₹' } }
    }
  }
});

new Chart(document.getElementById('trendChart').getContext('2d'), {
  type: 'line',
  data: {
    labels: <?php echo json_encode($trendLabels); ?>,
    datasets: [
      { label: 'Items Sold', data: <?php echo json_encode($trendItems); ?>, borderColor: '#b8860b', backgroundColor: 'rgba(184,134,11,0.12)', fill: true, tension: 0.4, pointRadius: 5, pointHoverRadius: 7, yAxisID: 'y' },
      { label: 'Revenue (₹)', data: <?php echo json_encode($trendRevenue); ?>, borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,0.08)', fill: false, tension: 0.4, pointRadius: 5, pointHoverRadius: 7, yAxisID: 'y2' },
      { label: 'Bills', data: <?php echo json_encode($trendBills); ?>, borderColor: '#dc3545', backgroundColor: 'transparent', fill: false, tension: 0.4, pointRadius: 4, borderDash: [5, 4], yAxisID: 'y' }
    ]
  },
  options: {
    responsive: true, plugins: { legend: { position: 'top' } },
    scales: {
      y:  { beginAtZero: true, title: { display: true, text: 'Items / Bills' } },
      y2: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: '₹ Revenue' } }
    }
  }
});
</script>

<!-- Wastage JS -->
<script>
const AVAIL_DATE = <?php echo json_encode($avail_date); ?>;

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

<style>
@media (max-width: 576px) {
  table { font-size: 0.78rem; }
}
</style>

<?php include_once("web_shopadmin_footer.php"); ?>
