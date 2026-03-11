<?php
include("web_shopadmin_header.php");
date_default_timezone_set('Asia/Kolkata');
$today = date('Y-m-d');

// ── 1. TODAY'S SUMMARY ───────────────────────────────────────────────────────
$summRes = mysqli_query($conn, "
    SELECT
        COUNT(DISTINCT `Inv no`)                          AS bills,
        IFNULL(SUM(quantity), 0)                         AS items,
        IFNULL(SUM(quantity * `Selling Price`), 0)       AS revenue
    FROM daily_productsale
    WHERE DATE(Time) = '$today'
      AND `payment status` != 'notpaid'
");
$summ = mysqli_fetch_assoc($summRes);

// ── 2. LOADED PRODUCTS (have daily_availability for today) ───────────────────
$loadedRes = mysqli_query($conn, "
    SELECT
        p.p_id,
        p.name,
        c.categorie     AS cat_name,
        da.available_qty AS loaded,
        IFNULL((
            SELECT SUM(ps.quantity)
            FROM daily_productsale ps
            WHERE ps.p_id = p.p_id
              AND DATE(ps.Time) = '$today'
              AND ps.`payment status` != 'notpaid'
        ), 0) AS sold
    FROM products p
    JOIN categorie c ON c.cat_id = p.categorie
    JOIN daily_availability da ON da.product_id = p.p_id
                               AND da.available_date = '$today'
    ORDER BY c.cat_id, p.name
");

// ── 3. SALES BY CATEGORY (for chart + table) ─────────────────────────────────
$catRes = mysqli_query($conn, "
    SELECT
        c.categorie  AS cat_name,
        IFNULL(SUM(ds.quantity), 0)                        AS qty_sold,
        IFNULL(SUM(ds.quantity * ds.`Selling Price`), 0)   AS revenue
    FROM daily_productsale ds
    JOIN products p  ON p.p_id    = ds.p_id
    JOIN categorie c ON c.cat_id  = p.categorie
    WHERE DATE(ds.Time) = '$today'
      AND ds.`payment status` != 'notpaid'
    GROUP BY c.cat_id, c.categorie
    ORDER BY qty_sold DESC
");
$catRows  = [];
$chartLbls = [];
$chartQty  = [];
$chartRev  = [];
while ($r = mysqli_fetch_assoc($catRes)) {
    $catRows[]   = $r;
    $chartLbls[] = $r['cat_name'];
    $chartQty[]  = (int)$r['qty_sold'];
    $chartRev[]  = (float)$r['revenue'];
}

// ── 4. EACH PRODUCT SOLD TODAY ────────────────────────────────────────────────
$prodRes = mysqli_query($conn, "
    SELECT
        p.name,
        c.categorie AS cat_name,
        IFNULL(SUM(ds.quantity), 0)                       AS qty_sold,
        IFNULL(SUM(ds.quantity * ds.`Selling Price`), 0)  AS revenue
    FROM daily_productsale ds
    JOIN products p  ON p.p_id   = ds.p_id
    JOIN categorie c ON c.cat_id = p.categorie
    WHERE DATE(ds.Time) = '$today'
      AND ds.`payment status` != 'notpaid'
    GROUP BY ds.p_id
    ORDER BY qty_sold DESC
");
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

  <!-- ── SUMMARY CARDS ── -->
  <div class="row g-3 mb-4">
    <div class="col-4">
      <div class="card border-0 shadow-sm rounded-4 text-center h-100">
        <div class="card-body py-3">
          <div style="font-size:1.8rem;font-weight:700;color:#b8860b;"><?php echo intval($summ['bills']); ?></div>
          <div class="text-muted small">Bills Today</div>
        </div>
      </div>
    </div>
    <div class="col-4">
      <div class="card border-0 shadow-sm rounded-4 text-center h-100">
        <div class="card-body py-3">
          <div style="font-size:1.8rem;font-weight:700;color:#b8860b;"><?php echo intval($summ['items']); ?></div>
          <div class="text-muted small">Items Sold</div>
        </div>
      </div>
    </div>
    <div class="col-4">
      <div class="card border-0 shadow-sm rounded-4 text-center h-100">
        <div class="card-body py-3">
          <div style="font-size:1.4rem;font-weight:700;color:#b8860b;">₹<?php echo number_format($summ['revenue'], 0); ?></div>
          <div class="text-muted small">Revenue</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── LOADED STOCK TRACKER ── -->
  <?php if (mysqli_num_rows($loadedRes) > 0): ?>
  <div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header text-white fw-bold" style="background:#b8860b;">
      📦 Loaded Today — Stock Status
    </div>
    <div class="card-body p-3">
      <?php while ($row = mysqli_fetch_assoc($loadedRes)):
        $loaded    = (int)$row['loaded'];
        $sold      = (int)$row['sold'];
        $remaining = max(0, $loaded - $sold);
        $pct       = $loaded > 0 ? round(($sold / $loaded) * 100) : 0;
        $barColor  = $pct >= 90 ? '#dc3545' : ($pct >= 60 ? '#ffc107' : '#198754');
      ?>
      <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <span class="fw-semibold"><?php echo htmlspecialchars($row['name']); ?>
            <span class="badge bg-secondary ms-1" style="font-size:0.7rem;"><?php echo htmlspecialchars($row['cat_name']); ?></span>
          </span>
          <span class="text-muted small">
            Loaded: <strong><?php echo $loaded; ?></strong> &nbsp;|&nbsp;
            Sold: <strong class="text-danger"><?php echo $sold; ?></strong> &nbsp;|&nbsp;
            Left: <strong class="text-success"><?php echo $remaining; ?></strong>
          </span>
        </div>
        <div class="progress" style="height:18px;border-radius:8px;background:#e9ecef;">
          <div class="progress-bar" role="progressbar"
               style="width:<?php echo $pct; ?>%;background:<?php echo $barColor; ?>;border-radius:8px;font-size:0.75rem;"
               title="<?php echo $pct; ?>% sold">
            <?php echo $pct > 10 ? $pct . '%' : ''; ?>
          </div>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── CHART + CATEGORY TABLE ── -->
  <div class="row g-3 mb-4">
    <div class="col-md-7">
      <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-header text-white fw-bold" style="background:#b8860b;">
          📊 Sales by Category
        </div>
        <div class="card-body">
          <canvas id="catChart" height="200"></canvas>
        </div>
      </div>
    </div>
    <div class="col-md-5">
      <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-header text-white fw-bold" style="background:#b8860b;">
          🗂 Category Breakdown
        </div>
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
              <tr><td colspan="3" class="text-muted py-3">No sales yet today.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- ── PRODUCT-LEVEL TABLE ── -->
  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-header text-white fw-bold" style="background:#b8860b;">
      🧾 Product-Level Sales Today
    </div>
    <div class="card-body table-responsive p-0">
      <table class="table table-striped table-bordered align-middle text-center mb-0" style="font-size:0.85rem;">
        <thead class="table-dark">
          <tr><th>Product</th><th>Category</th><th>Qty Sold</th><th>Revenue</th></tr>
        </thead>
        <tbody>
          <?php
          $grandQty = 0; $grandRev = 0;
          while ($pr = mysqli_fetch_assoc($prodRes)):
            $grandQty += $pr['qty_sold'];
            $grandRev += $pr['revenue'];
          ?>
          <tr>
            <td><?php echo htmlspecialchars($pr['name']); ?></td>
            <td><?php echo htmlspecialchars($pr['cat_name']); ?></td>
            <td><strong><?php echo intval($pr['qty_sold']); ?></strong></td>
            <td>₹<?php echo number_format($pr['revenue'], 0); ?></td>
          </tr>
          <?php endwhile; ?>
          <?php if ($grandQty === 0): ?>
          <tr><td colspan="4" class="text-muted py-3">No sales yet today.</td></tr>
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

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('catChart').getContext('2d');
new Chart(ctx, {
  type: 'bar',
  data: {
    labels: <?php echo json_encode($chartLbls); ?>,
    datasets: [
      {
        label: 'Items Sold',
        data: <?php echo json_encode($chartQty); ?>,
        backgroundColor: '#b8860b',
        borderRadius: 6,
        yAxisID: 'y',
      },
      {
        label: 'Revenue (₹)',
        data: <?php echo json_encode($chartRev); ?>,
        backgroundColor: '#6c757d55',
        borderRadius: 6,
        yAxisID: 'y2',
        type: 'line',
        borderColor: '#6c757d',
        tension: 0.3,
        pointRadius: 4,
      }
    ]
  },
  options: {
    responsive: true,
    plugins: { legend: { position: 'top' } },
    scales: {
      y:  { beginAtZero: true, title: { display: true, text: 'Items' } },
      y2: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: '₹ Revenue' } }
    }
  }
});
</script>

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

<style>
@media (max-width: 576px) {
  table { font-size: 0.78rem; }
}
</style>

<?php include_once("web_shopadmin_footer.php"); ?>
