<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 315360000);
    session_set_cookie_params(315360000);
    $sess = dirname(__DIR__) . '/sessions';
    if (!is_dir($sess)) @mkdir($sess, 0750, true);
    session_save_path($sess);
    session_start();
}
include_once('../db.php');

// ── Verify weather_log exists ─────────────────────────────────────────────────
$tableCheck = $conn->query("SHOW TABLES LIKE 'weather_log'");
$tableExists = $tableCheck && $tableCheck->num_rows > 0;

// ── Recent weather logs (last 30 days) ───────────────────────────────────────
$recentWeather = [];
if ($tableExists) {
    $rw = $conn->query("
        SELECT w_id, temperature, humidity, pressure, weather_type, wind_speed, location, recorded_at
        FROM weather_log
        WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY recorded_at DESC
        LIMIT 100
    ");
    while ($row = $rw->fetch_assoc()) $recentWeather[] = $row;
}

// ── Sales by weather type ─────────────────────────────────────────────────────
$salesByWeather = [];
if ($tableExists) {
    $sbw = $conn->query("
        SELECT
            wl.weather_type,
            COUNT(DISTINCT dp.invno)          AS invoice_count,
            COALESCE(SUM(dp.saleprice * dp.qty), 0) AS total_revenue,
            AVG(wl.temperature)               AS avg_temp,
            AVG(wl.humidity)                  AS avg_humidity
        FROM daily_productsale dp
        JOIN income_invoice ii  ON ii.invno = dp.invno
        JOIN weather_log wl     ON wl.w_id  = ii.weatherid
        WHERE wl.weather_type IS NOT NULL
          AND dp.saleprice > 0
        GROUP BY wl.weather_type
        ORDER BY total_revenue DESC
    ");
    while ($row = $sbw->fetch_assoc()) $salesByWeather[] = $row;
}

// ── Top products by weather condition ────────────────────────────────────────
$topProductsByWeather = [];
if ($tableExists) {
    $tpw = $conn->query("
        SELECT
            wl.weather_type,
            dp.prod_name,
            SUM(dp.qty)                              AS total_qty,
            SUM(dp.saleprice * dp.qty)               AS total_revenue
        FROM daily_productsale dp
        JOIN income_invoice ii ON ii.invno = dp.invno
        JOIN weather_log wl    ON wl.w_id  = ii.weatherid
        WHERE wl.weather_type IS NOT NULL
          AND dp.saleprice > 0
        GROUP BY wl.weather_type, dp.prod_name
        ORDER BY wl.weather_type, total_qty DESC
    ");
    while ($row = $tpw->fetch_assoc()) {
        $topProductsByWeather[$row['weather_type']][] = $row;
    }
    // Keep top 5 per weather type
    foreach ($topProductsByWeather as $wt => $prods) {
        $topProductsByWeather[$wt] = array_slice($prods, 0, 5);
    }
}

// ── Temperature bands vs revenue ─────────────────────────────────────────────
$tempBands = [];
if ($tableExists) {
    $tb = $conn->query("
        SELECT
            CASE
                WHEN wl.temperature < 15  THEN 'Cold (<15°C)'
                WHEN wl.temperature < 25  THEN 'Mild (15–25°C)'
                WHEN wl.temperature < 32  THEN 'Warm (25–32°C)'
                ELSE 'Hot (>32°C)'
            END AS temp_band,
            COUNT(DISTINCT dp.invno)          AS invoice_count,
            SUM(dp.saleprice * dp.qty)        AS total_revenue,
            AVG(wl.humidity)                  AS avg_humidity
        FROM daily_productsale dp
        JOIN income_invoice ii ON ii.invno = dp.invno
        JOIN weather_log wl    ON wl.w_id  = ii.weatherid
        GROUP BY temp_band
        ORDER BY MIN(wl.temperature)
    ");
    while ($row = $tb->fetch_assoc()) $tempBands[] = $row;
}

// ── Last 14 days: daily revenue + weather ─────────────────────────────────────
$dailyTrend = [];
if ($tableExists) {
    $dt = $conn->query("
        SELECT
            DATE(ii.created_at)               AS sale_date,
            AVG(wl.temperature)               AS avg_temp,
            AVG(wl.humidity)                  AS avg_humidity,
            SUM(dp.saleprice * dp.qty)        AS revenue
        FROM daily_productsale dp
        JOIN income_invoice ii ON ii.invno = dp.invno
        JOIN weather_log wl    ON wl.w_id  = ii.weatherid
        WHERE ii.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
        GROUP BY sale_date
        ORDER BY sale_date ASC
    ");
    while ($row = $dt->fetch_assoc()) $dailyTrend[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Weather Analytics</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
body { background:#f8f9fa; font-family: 'Segoe UI', sans-serif; }
.card { border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,.08); }
.weather-badge { font-size:.75rem; padding:3px 8px; border-radius:20px; background:#e9ecef; }
.stat-box { background:#fff; border-radius:10px; padding:1rem 1.5rem; box-shadow:0 1px 4px rgba(0,0,0,.06); }
.stat-label { font-size:.75rem; color:#6c757d; text-transform:uppercase; letter-spacing:.05em; }
.stat-value { font-size:1.6rem; font-weight:700; }
table thead th { background:#343a40; color:#fff; }
</style>
</head>
<body>
<div class="container-fluid py-4 px-4">

  <div class="d-flex align-items-center mb-4">
    <div>
      <h3 class="mb-0">🌤️ Weather Analytics</h3>
      <small class="text-muted">How weather conditions affect your sales</small>
    </div>
    <div class="ms-auto">
      <a href="weather_fetch.php" target="_blank" class="btn btn-sm btn-outline-primary">
        ↻ Refresh Weather Now
      </a>
      <a href="shopadmin.php" class="btn btn-sm btn-outline-secondary ms-2">← Back</a>
    </div>
  </div>

  <?php if (!$tableExists): ?>
  <div class="alert alert-warning">
    The <code>weather_log</code> table doesn't exist yet.
    Make one invoice to auto-create it, or
    <a href="../weather_fetch.php" target="_blank">click here</a> to initialise.
  </div>
  <?php endif; ?>

  <!-- ── Current / Latest Weather ─────────────────────────────────────── -->
  <?php if (!empty($recentWeather)): $latest = $recentWeather[0]; ?>
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
      <div class="stat-box text-center">
        <div class="stat-label">Temperature</div>
        <div class="stat-value text-danger"><?= number_format($latest['temperature'],1) ?>°C</div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="stat-box text-center">
        <div class="stat-label">Humidity</div>
        <div class="stat-value text-primary"><?= $latest['humidity'] ?>%</div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="stat-box text-center">
        <div class="stat-label">Pressure</div>
        <div class="stat-value text-secondary"><?= $latest['pressure'] ?><small style="font-size:.8rem">hPa</small></div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="stat-box text-center">
        <div class="stat-label">Wind</div>
        <div class="stat-value text-success"><?= number_format($latest['wind_speed'],1) ?><small style="font-size:.8rem">m/s</small></div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="stat-box">
        <div class="stat-label">Condition</div>
        <div class="fw-bold fs-5 text-capitalize"><?= htmlspecialchars($latest['weather_type']) ?></div>
        <div class="text-muted small"><?= htmlspecialchars($latest['location']) ?></div>
        <div class="text-muted small">Recorded: <?= $latest['recorded_at'] ?></div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="row g-4">

    <!-- ── Revenue by Weather Type ──────────────────────────────────────── -->
    <?php if (!empty($salesByWeather)): ?>
    <div class="col-12 col-lg-6">
      <div class="card p-3">
        <h6 class="fw-bold mb-3">Revenue by Weather Condition</h6>
        <canvas id="chartWeather" height="220"></canvas>
        <table class="table table-sm mt-3 mb-0">
          <thead><tr><th>Condition</th><th>Invoices</th><th>Revenue (₹)</th><th>Avg Temp</th></tr></thead>
          <tbody>
          <?php foreach ($salesByWeather as $r): ?>
          <tr>
            <td class="text-capitalize"><?= htmlspecialchars($r['weather_type']) ?></td>
            <td><?= $r['invoice_count'] ?></td>
            <td>₹<?= number_format($r['total_revenue']) ?></td>
            <td><?= number_format($r['avg_temp'],1) ?>°C</td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Temperature Bands ────────────────────────────────────────────── -->
    <?php if (!empty($tempBands)): ?>
    <div class="col-12 col-lg-6">
      <div class="card p-3">
        <h6 class="fw-bold mb-3">Sales by Temperature Band</h6>
        <canvas id="chartTemp" height="220"></canvas>
        <table class="table table-sm mt-3 mb-0">
          <thead><tr><th>Temperature</th><th>Invoices</th><th>Revenue (₹)</th><th>Avg Humidity</th></tr></thead>
          <tbody>
          <?php foreach ($tempBands as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['temp_band']) ?></td>
            <td><?= $r['invoice_count'] ?></td>
            <td>₹<?= number_format($r['total_revenue']) ?></td>
            <td><?= number_format($r['avg_humidity'],1) ?>%</td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── 14-Day Trend: Revenue + Temperature ──────────────────────────── -->
    <?php if (!empty($dailyTrend)): ?>
    <div class="col-12">
      <div class="card p-3">
        <h6 class="fw-bold mb-3">14-Day Trend: Revenue vs Temperature</h6>
        <canvas id="chartTrend" height="120"></canvas>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Top Products per Weather Condition ───────────────────────────── -->
    <?php if (!empty($topProductsByWeather)): ?>
    <div class="col-12">
      <div class="card p-3">
        <h6 class="fw-bold mb-3">Top Products per Weather Condition</h6>
        <div class="row g-3">
          <?php foreach ($topProductsByWeather as $wtype => $prods): ?>
          <div class="col-12 col-md-4">
            <div class="border rounded p-2">
              <div class="fw-semibold text-capitalize mb-2">
                ☁️ <?= htmlspecialchars($wtype) ?>
              </div>
              <ol class="mb-0 ps-3">
                <?php foreach ($prods as $p): ?>
                <li class="small">
                  <?= htmlspecialchars($p['prod_name']) ?>
                  <span class="text-muted">(<?= $p['total_qty'] ?> sold)</span>
                </li>
                <?php endforeach; ?>
              </ol>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Recent Weather Log ────────────────────────────────────────────── -->
    <?php if (!empty($recentWeather)): ?>
    <div class="col-12">
      <div class="card p-3">
        <h6 class="fw-bold mb-2">Recent Weather Log <small class="text-muted fw-normal">(last 30 days)</small></h6>
        <div style="max-height:300px;overflow-y:auto;">
          <table class="table table-sm table-hover mb-0">
            <thead>
              <tr><th>Date/Time</th><th>Condition</th><th>Temp °C</th><th>Humidity %</th><th>Pressure hPa</th><th>Wind m/s</th><th>Location</th></tr>
            </thead>
            <tbody>
              <?php foreach ($recentWeather as $w): ?>
              <tr>
                <td><?= $w['recorded_at'] ?></td>
                <td class="text-capitalize"><?= htmlspecialchars($w['weather_type']) ?></td>
                <td><?= $w['temperature'] ?></td>
                <td><?= $w['humidity'] ?></td>
                <td><?= $w['pressure'] ?></td>
                <td><?= $w['wind_speed'] ?></td>
                <td><?= htmlspecialchars($w['location']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if (empty($salesByWeather) && empty($recentWeather)): ?>
    <div class="col-12">
      <div class="alert alert-info">
        No weather data linked to sales yet. Weather readings will be collected automatically
        with each invoice (or every <?= (int)(WEATHER_FETCH_INTERVAL/3600) ?> hours).
        <a href="../weather_fetch.php" target="_blank" class="alert-link">Trigger first fetch →</a>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /row -->
</div><!-- /container -->

<script>
<?php if (!empty($salesByWeather)): ?>
// Chart: Revenue by weather condition
new Chart(document.getElementById('chartWeather'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($salesByWeather, 'weather_type')) ?>,
    datasets: [{
      label: 'Revenue (₹)',
      data:  <?= json_encode(array_map(fn($r) => (float)$r['total_revenue'], $salesByWeather)) ?>,
      backgroundColor: ['#4e73df','#1cc88a','#36b9cc','#f6c23e','#e74a3b','#858796','#fd7e14'],
      borderRadius: 6,
    }]
  },
  options: { plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}} }
});
<?php endif; ?>

<?php if (!empty($tempBands)): ?>
// Chart: Revenue by temperature band
new Chart(document.getElementById('chartTemp'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_column($tempBands, 'temp_band')) ?>,
    datasets: [{
      data: <?= json_encode(array_map(fn($r) => (float)$r['total_revenue'], $tempBands)) ?>,
      backgroundColor: ['#36b9cc','#1cc88a','#f6c23e','#e74a3b'],
    }]
  },
  options: { plugins:{ legend:{ position:'bottom' } } }
});
<?php endif; ?>

<?php if (!empty($dailyTrend)): ?>
// Chart: 14-day trend
new Chart(document.getElementById('chartTrend'), {
  type: 'line',
  data: {
    labels: <?= json_encode(array_column($dailyTrend, 'sale_date')) ?>,
    datasets: [
      {
        label: 'Revenue (₹)',
        data: <?= json_encode(array_map(fn($r) => (float)$r['revenue'], $dailyTrend)) ?>,
        borderColor: '#4e73df',
        backgroundColor: 'rgba(78,115,223,.1)',
        yAxisID: 'yRev',
        tension: .3,
        fill: true,
      },
      {
        label: 'Avg Temp (°C)',
        data: <?= json_encode(array_map(fn($r) => round($r['avg_temp'],1), $dailyTrend)) ?>,
        borderColor: '#e74a3b',
        backgroundColor: 'transparent',
        yAxisID: 'yTemp',
        tension: .3,
        borderDash: [5,3],
      }
    ]
  },
  options: {
    scales: {
      yRev:  { type:'linear', position:'left',  beginAtZero:true, title:{display:true,text:'Revenue (₹)'} },
      yTemp: { type:'linear', position:'right', grid:{drawOnChartArea:false}, title:{display:true,text:'Temperature (°C)'} }
    }
  }
});
<?php endif; ?>
</script>
</body>
</html>
