<?php
include("web_shopadmin_header.php");
date_default_timezone_set('Asia/Kolkata');

// Super Admin only
if (!isset($_SESSION['userpermission']) || $_SESSION['userpermission'] !== 'Super Admin') {
    echo "<script>alert('Access denied.'); window.location='shopadmin.php';</script>";
    exit;
}

include_once(__DIR__ . '/../ai_config.php');

$shop_id = intval($_SESSION['selectshop'] ?? 0);

// ── Session time range (same logic as daily_sales.php) ────────────────────
$hour = (int)date('H');
$session_date = ($hour < 2) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
$def_start = $session_date . ' 14:00:00';
$def_end   = date('Y-m-d', strtotime($session_date . ' +1 day')) . ' 02:00:00';

$start_time = isset($_GET['start_time']) && $_GET['start_time'] !== '' ? $_GET['start_time'] : $def_start;
$end_time   = isset($_GET['end_time'])   && $_GET['end_time']   !== '' ? $_GET['end_time']   : $def_end;
$start_time = str_replace('T', ' ', $start_time);
$end_time   = str_replace('T', ' ', $end_time);
$session_label = date('d M Y', strtotime($start_time)) . ' (2 PM → 2 AM)';

$shopWhere = ""; // daily_productsale has no shopid column
$esc_start = mysqli_real_escape_string($conn, $start_time);
$esc_end   = mysqli_real_escape_string($conn, $end_time);

// ── 1. Session totals (paid only) ─────────────────────────────────────────
$totals = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(DISTINCT `Inv no`) AS total_bills,
           SUM(quantity) AS total_items,
           SUM(quantity * `Selling Price`) AS total_revenue,
           AVG(bill_total) AS avg_bill,
           MIN(bill_total) AS min_bill,
           MAX(bill_total) AS max_bill
    FROM (
        SELECT `Inv no`, SUM(quantity * `Selling Price`) AS bill_total
        FROM daily_productsale ds
        WHERE `payment status` != 'notpaid'
          AND ds.Time >= '$esc_start' AND ds.Time <= '$esc_end'
          $shopWhere
        GROUP BY `Inv no`
    ) t
"));

// ── 2. Payment mode breakdown ─────────────────────────────────────────────
$payRows = [];
$payRes = mysqli_query($conn, "
    SELECT
        CASE
            WHEN `Payment Mode` = 'cash' THEN 'Cash'
            WHEN `Payment Mode` = 'upi'  THEN 'UPI'
            WHEN `Payment Mode` = 'online' THEN 'Online'
            WHEN `Payment Mode` LIKE 'split:%' THEN 'Split'
            WHEN `Payment Mode` LIKE 'staff%'  THEN 'Staff'
            ELSE `Payment Mode`
        END AS mode_label,
        COUNT(DISTINCT `Inv no`) AS bills,
        SUM(quantity * `Selling Price`) AS revenue
    FROM daily_productsale ds
    WHERE `payment status` != 'notpaid'
      AND ds.Time >= '$esc_start' AND ds.Time <= '$esc_end'
      $shopWhere
    GROUP BY mode_label ORDER BY revenue DESC
");
while ($r = mysqli_fetch_assoc($payRes)) $payRows[] = $r;

// ── 3. Hourly billing trend ───────────────────────────────────────────────
$hourlyRows = [];
$hRes = mysqli_query($conn, "
    SELECT HOUR(ds.Time) AS hr,
           COUNT(DISTINCT ds.`Inv no`) AS bills,
           SUM(ds.quantity * ds.`Selling Price`) AS revenue
    FROM daily_productsale ds
    WHERE `payment status` != 'notpaid'
      AND ds.Time >= '$esc_start' AND ds.Time <= '$esc_end'
      $shopWhere
    GROUP BY HOUR(ds.Time) ORDER BY hr ASC
");
while ($r = mysqli_fetch_assoc($hRes)) $hourlyRows[] = $r;

// ── 4. Top selling items ──────────────────────────────────────────────────
$topItems = [];
$tRes = mysqli_query($conn, "
    SELECT ds.`product name` AS name,
           SUM(ds.quantity) AS qty,
           SUM(ds.quantity * ds.`Selling Price`) AS revenue,
           ds.`Selling Price` AS price
    FROM daily_productsale ds
    WHERE `payment status` != 'notpaid'
      AND ds.Time >= '$esc_start' AND ds.Time <= '$esc_end'
      $shopWhere
    GROUP BY ds.`product name`, ds.`Selling Price`
    ORDER BY qty DESC LIMIT 15
");
while ($r = mysqli_fetch_assoc($tRes)) $topItems[] = $r;

// ── 5. Anomaly flags ──────────────────────────────────────────────────────

// 5a. Notpaid / pending bills
$notpaid = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(DISTINCT `Inv no`) AS cnt,
           COALESCE(SUM(quantity * `Selling Price`), 0) AS amount
    FROM daily_productsale ds
    WHERE `payment status` = 'notpaid'
      AND ds.Time >= '$esc_start' AND ds.Time <= '$esc_end'
      $shopWhere
"));

// 5b. Staff payment bills
$staffPay = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(DISTINCT `Inv no`) AS cnt,
           COALESCE(SUM(quantity * `Selling Price`), 0) AS amount
    FROM daily_productsale ds
    WHERE `Payment Mode` LIKE 'staff%'
      AND ds.Time >= '$esc_start' AND ds.Time <= '$esc_end'
      $shopWhere
"));

// 5c. Very low amount bills (< ₹20 — possible partial/voided entry)
$lowBillThreshold = 20;
$lowBills = [];
$lbRes = mysqli_query($conn, "
    SELECT `Inv no`, MIN(ds.Time) AS bill_time, `Payment Mode`,
           SUM(quantity * `Selling Price`) AS total
    FROM daily_productsale ds
    WHERE `payment status` != 'notpaid'
      AND ds.Time >= '$esc_start' AND ds.Time <= '$esc_end'
      $shopWhere
    GROUP BY `Inv no`, `Payment Mode`
    HAVING total < $lowBillThreshold
    ORDER BY total ASC LIMIT 10
");
while ($r = mysqli_fetch_assoc($lbRes)) $lowBills[] = $r;

// 5d. Single-item-type bills (all same product — possible selective ringing)
$singleItemBills = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS cnt FROM (
        SELECT `Inv no`
        FROM daily_productsale ds
        WHERE `payment status` != 'notpaid'
          AND ds.Time >= '$esc_start' AND ds.Time <= '$esc_end'
          $shopWhere
        GROUP BY `Inv no`
        HAVING COUNT(DISTINCT `product name`) = 1
    ) t
"));

// 5e. Invoice number gaps (deleted bills) — detect jumps > 1
$invNums = [];
$invRes = mysqli_query($conn, "
    SELECT DISTINCT `Inv no` FROM daily_productsale ds
    WHERE ds.Time >= '$esc_start' AND ds.Time <= '$esc_end'
      $shopWhere
    ORDER BY `Inv no` ASC
");
while ($r = mysqli_fetch_assoc($invRes)) {
    if (is_numeric($r['Inv no'])) $invNums[] = (int)$r['Inv no'];
}
$invGaps = 0;
for ($i = 1; $i < count($invNums); $i++) {
    if ($invNums[$i] - $invNums[$i - 1] > 1) $invGaps++;
}

// 5f. Unusually large quantity on single line item (> 20 units same product same bill)
$highQtyLines = [];
$hqRes = mysqli_query($conn, "
    SELECT `Inv no`, `product name`, quantity, `Selling Price`, ds.Time
    FROM daily_productsale ds
    WHERE quantity > 20
      AND ds.Time >= '$esc_start' AND ds.Time <= '$esc_end'
      $shopWhere
    ORDER BY quantity DESC LIMIT 5
");
while ($r = mysqli_fetch_assoc($hqRes)) $highQtyLines[] = $r;

// ── 6. Wastage for session ────────────────────────────────────────────────
$wastageRows = [];
$wRes = mysqli_query($conn, "
    SELECT prod_name, SUM(wastage_qty) AS total_wasted, reason
    FROM daily_wastage
    WHERE wastage_date = '" . date('Y-m-d', strtotime($start_time)) . "'
    GROUP BY prod_name, reason ORDER BY total_wasted DESC
");
if ($wRes) while ($r = mysqli_fetch_assoc($wRes)) $wastageRows[] = $r;

// ── 7. Previous session comparison ───────────────────────────────────────
$prevStart = date('Y-m-d', strtotime($session_date . ' -1 day')) . ' 14:00:00';
$prevEnd   = date('Y-m-d', strtotime($session_date)) . ' 02:00:00';
$prevTotals = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(DISTINCT `Inv no`) AS total_bills,
           COALESCE(SUM(quantity * `Selling Price`), 0) AS total_revenue
    FROM daily_productsale ds
    WHERE `payment status` != 'notpaid'
      AND ds.Time >= '$prevStart' AND ds.Time <= '$prevEnd'
      $shopWhere
"));

// ── 8. Current weather ────────────────────────────────────────────────────
$weatherRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM weather_log ORDER BY recorded_at DESC LIMIT 1"));

// ── Build AI prompt ───────────────────────────────────────────────────────
$revTotal    = floatval($totals['total_revenue'] ?? 0);
$billCount   = intval($totals['total_bills'] ?? 0);
$itemCount   = intval($totals['total_items'] ?? 0);
$avgBill     = $billCount > 0 ? round($revTotal / $billCount, 2) : 0;
$prevRev     = floatval($prevTotals['total_revenue'] ?? 0);
$prevBills   = intval($prevTotals['total_bills'] ?? 0);
$revChange   = $prevRev > 0 ? round((($revTotal - $prevRev) / $prevRev) * 100, 1) : 0;
$billChange  = $prevBills > 0 ? round((($billCount - $prevBills) / $prevBills) * 100, 1) : 0;

$payLines = array_map(fn($r) => "  {$r['mode_label']}: {$r['bills']} bills, ₹" . number_format($r['revenue'], 0), $payRows);
$hourLines = array_map(fn($r) => "  " . str_pad($r['hr'] . ":00", 6) . " → {$r['bills']} bills, ₹" . number_format($r['revenue'], 0), $hourlyRows);
$topLines  = array_map(fn($r) => "  {$r['name']}: {$r['qty']} sold @ ₹{$r['price']}", $topItems);
$lowLines  = array_map(fn($r) => "  Inv#{$r['Inv no']} @ " . date('h:i A', strtotime($r['bill_time'])) . " — ₹{$r['total']} ({$r['Payment Mode']})", $lowBills);
$hqLines   = array_map(fn($r) => "  {$r['product name']} × {$r['quantity']} in Inv#{$r['Inv no']} @ " . date('h:i A', strtotime($r['Time'])), $highQtyLines);
$wstLines  = array_map(fn($r) => "  {$r['prod_name']}: {$r['total_wasted']} wasted ({$r['reason']})", $wastageRows);

$weatherCtx = $weatherRow ? "{$weatherRow['temperature']}°C, {$weatherRow['humidity']}% humidity, {$weatherRow['weather_type']}" : 'N/A';

$prompt  = "You are a sharp business analyst reviewing a South Indian hotel/snack shop's daily sales session.\n\n";
$prompt .= "**SESSION: {$session_label}**\n";
$prompt .= "Date analyzed: " . date('d M Y H:i') . "\n";
$prompt .= "Weather: {$weatherCtx}\n\n";

$prompt .= "**SESSION TOTALS**\n";
$prompt .= "- Total bills: {$billCount} (" . ($billChange >= 0 ? "+{$billChange}%" : "{$billChange}%") . " vs yesterday)\n";
$prompt .= "- Total items sold: {$itemCount}\n";
$prompt .= "- Total revenue: ₹" . number_format($revTotal, 0) . " (" . ($revChange >= 0 ? "+{$revChange}%" : "{$revChange}%") . " vs yesterday)\n";
$prompt .= "- Avg bill value: ₹{$avgBill}\n";
$prompt .= "- Min bill: ₹" . number_format($totals['min_bill'] ?? 0, 0) . "  |  Max bill: ₹" . number_format($totals['max_bill'] ?? 0, 0) . "\n";
$prompt .= "- Yesterday: {$prevBills} bills, ₹" . number_format($prevRev, 0) . "\n\n";

$prompt .= "**PAYMENT MODE BREAKDOWN**\n" . (implode("\n", $payLines) ?: "  No data") . "\n\n";
$prompt .= "**HOURLY BILLING PATTERN**\n" . (implode("\n", $hourLines) ?: "  No data") . "\n\n";
$prompt .= "**TOP SELLING ITEMS**\n" . (implode("\n", $topLines) ?: "  No data") . "\n\n";
$prompt .= "**ANOMALY FLAGS**\n";
$prompt .= "- Unpaid/pending bills: {$notpaid['cnt']} (₹" . number_format($notpaid['amount'], 0) . " at risk)\n";
$prompt .= "- Staff payment bills: {$staffPay['cnt']} (₹" . number_format($staffPay['amount'], 0) . ")\n";
$prompt .= "- Suspiciously low bills (< ₹{$lowBillThreshold}):\n" . (implode("\n", $lowLines) ?: "  None") . "\n";
$prompt .= "- Bills with only 1 product type: {$singleItemBills['cnt']}\n";
$prompt .= "- Invoice number gaps (possible deleted bills): {$invGaps}\n";
$prompt .= "- Unusually high qty on single line:\n" . (implode("\n", $hqLines) ?: "  None") . "\n\n";
$prompt .= "**WASTAGE**\n" . (implode("\n", $wstLines) ?: "  No wastage logged today") . "\n\n";

$prompt .= "---\n\n";
$prompt .= "Please provide a structured daily summary with these sections:\n\n";
$prompt .= "## 📊 Session Performance\nOverall assessment of today vs yesterday. Revenue trend, bill count trend. Is this a good/average/poor session and why?\n\n";
$prompt .= "## ⏰ Billing Trend Analysis\nAnalyse the hourly pattern. When was peak? When was it slow? What does the pattern suggest about customer flow?\n\n";
$prompt .= "## 🚨 Potential Billing Concerns\nBased on the anomaly flags, identify any suspicious patterns. Be specific about invoice numbers and amounts flagged. Rate the risk level (Low/Medium/High) for each concern.\n\n";
$prompt .= "## 💰 Revenue & Payment Analysis\nBreakdown of payment modes. Any concern about cash vs UPI ratio? Any mode with unusually high or low usage?\n\n";
$prompt .= "## 📦 Wastage & Stock Efficiency\nComment on today's wastage. What does it indicate? What can be done to reduce it?\n\n";
$prompt .= "## ✅ Top 3 Action Items\nSpecific, actionable things the owner should do before or during the next session to improve accuracy, reduce losses, or grow revenue.\n\n";
$prompt .= "Be concise but specific. Use ₹ for Indian Rupees. Flag anything that looks like staff misconduct or billing manipulation directly.";

// ── Handle generate ───────────────────────────────────────────────────────
$aiResult = null;
$aiError  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    if (OPENAI_API_KEY === 'YOUR_OPENAI_API_KEY_HERE') {
        $aiError = 'OpenAI API key not configured. Edit <code>ai_config.php</code>.';
    } else {
        $payload = json_encode([
            'model'       => OPENAI_MODEL,
            'messages'    => [
                ['role' => 'system', 'content' => 'You are a sharp, no-nonsense business analyst for a South Indian hotel shop. Detect billing anomalies and provide clear, actionable insights.'],
                ['role' => 'user',   'content' => $prompt],
            ],
            'max_tokens'  => 2000,
            'temperature' => 0.4,
        ]);

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . OPENAI_API_KEY,
            ],
        ]);
        $raw     = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $curlErr) {
            $aiError = 'Network error: ' . $curlErr;
        } else {
            $resp = json_decode($raw, true);
            if (!empty($resp['error'])) {
                $aiError = $resp['error']['message'] ?? 'OpenAI API error';
            } elseif (!empty($resp['choices'][0]['message']['content'])) {
                $aiResult = $resp['choices'][0]['message']['content'];
                mysqli_query($conn, "CREATE TABLE IF NOT EXISTS ai_daily_summary_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    shop_id INT NOT NULL DEFAULT 0,
                    session_date DATE NOT NULL,
                    start_time DATETIME NOT NULL,
                    end_time DATETIME NOT NULL,
                    summary TEXT NOT NULL,
                    total_bills INT DEFAULT 0,
                    total_revenue DECIMAL(12,2) DEFAULT 0,
                    anomaly_count INT DEFAULT 0,
                    generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_session (shop_id, session_date)
                )");
                $safeSummary  = mysqli_real_escape_string($conn, $aiResult);
                $anomalyCount = intval($notpaid['cnt']) + intval($staffPay['cnt']) + count($lowBills) + $invGaps;
                mysqli_query($conn, "INSERT INTO ai_daily_summary_log
                    (shop_id, session_date, start_time, end_time, summary, total_bills, total_revenue, anomaly_count)
                    VALUES ($shop_id, '" . date('Y-m-d', strtotime($start_time)) . "',
                    '$esc_start', '$esc_end', '$safeSummary', $billCount, $revTotal, $anomalyCount)");
            } else {
                $aiError = 'Unexpected response: ' . htmlspecialchars(substr($raw, 0, 300));
            }
        }
    }
}

// Load saved summary for this session (if already generated today)
$savedSummary = null;
if (!$aiResult) {
    $ssRes = mysqli_query($conn, "
        SELECT * FROM ai_daily_summary_log
        WHERE shop_id=$shop_id
          AND session_date='" . date('Y-m-d', strtotime($start_time)) . "'
        ORDER BY generated_at DESC LIMIT 1
    ");
    if ($ssRes) $savedSummary = mysqli_fetch_assoc($ssRes);
}

// Past summaries (last 7 sessions)
$pastSummaries = [];
$psRes = mysqli_query($conn, "
    SELECT id, session_date, total_bills, total_revenue, anomaly_count, generated_at
    FROM ai_daily_summary_log
    WHERE shop_id=$shop_id
    ORDER BY session_date DESC LIMIT 7
");
if ($psRes) while ($r = mysqli_fetch_assoc($psRes)) $pastSummaries[] = $r;

$redirect_qs = '?start_time=' . urlencode(str_replace(' ', 'T', $start_time)) . '&end_time=' . urlencode(str_replace(' ', 'T', $end_time));
?>

<main>
  <nav class="navbar navbar-expand-lg navbar-light" style="background-color:#1a1a2e !important;">
    <div class="container-fluid d-flex flex-wrap align-items-center justify-content-between">
      <div class="d-flex align-items-center">
        <img src="../images/logo.jpg" alt="Logo" class="img-fluid" style="max-height:50px;">
        <div class="ms-3">
          <h5 class="text-white mb-0">🤖 Daily AI Summary</h5>
          <small class="text-warning"><?php echo htmlspecialchars($session_label); ?></small>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <a href="daily_sales.php<?php echo $redirect_qs; ?>" class="btn btn-outline-light btn-sm">← Sales</a>
        <?php include_once("../master_mobnav.php"); ?>
      </div>
    </div>
  </nav>

  <div class="container py-3">

    <!-- Session selector -->
    <div class="card border-0 shadow-sm rounded-4 mb-3">
      <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
          <div class="col-5">
            <label class="form-label mb-1 small">Session Start</label>
            <input type="datetime-local" name="start_time" class="form-control form-control-sm"
                   value="<?php echo htmlspecialchars(str_replace(' ', 'T', $start_time)); ?>">
          </div>
          <div class="col-5">
            <label class="form-label mb-1 small">Session End</label>
            <input type="datetime-local" name="end_time" class="form-control form-control-sm"
                   value="<?php echo htmlspecialchars(str_replace(' ', 'T', $end_time)); ?>">
          </div>
          <div class="col-2">
            <button type="submit" class="btn btn-warning btn-sm w-100">Go</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Quick Stats -->
    <div class="row g-2 mb-3">
      <div class="col-3">
        <div class="card border-0 shadow-sm rounded-3 text-center py-2">
          <div class="fw-bold fs-5 text-warning"><?php echo $billCount; ?></div>
          <div class="text-muted" style="font-size:0.7rem;">Bills
            <?php if ($prevBills > 0): ?>
            <br><span class="<?php echo $billChange >= 0 ? 'text-success' : 'text-danger'; ?>" style="font-size:0.68rem;">
              <?php echo ($billChange >= 0 ? '▲' : '▼') . abs($billChange) . '%'; ?>
            </span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="col-3">
        <div class="card border-0 shadow-sm rounded-3 text-center py-2">
          <div class="fw-bold fs-6 text-success">₹<?php echo number_format($revTotal, 0); ?></div>
          <div class="text-muted" style="font-size:0.7rem;">Revenue
            <?php if ($prevRev > 0): ?>
            <br><span class="<?php echo $revChange >= 0 ? 'text-success' : 'text-danger'; ?>" style="font-size:0.68rem;">
              <?php echo ($revChange >= 0 ? '▲' : '▼') . abs($revChange) . '%'; ?>
            </span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="col-3">
        <div class="card border-0 shadow-sm rounded-3 text-center py-2">
          <div class="fw-bold fs-6 <?php echo (intval($notpaid['cnt']) + intval($staffPay['cnt']) + count($lowBills) + $invGaps) > 0 ? 'text-danger' : 'text-success'; ?>">
            <?php echo intval($notpaid['cnt']) + intval($staffPay['cnt']) + count($lowBills) + $invGaps; ?>
          </div>
          <div class="text-muted" style="font-size:0.7rem;">Flags</div>
        </div>
      </div>
      <div class="col-3">
        <div class="card border-0 shadow-sm rounded-3 text-center py-2">
          <div class="fw-bold fs-6">₹<?php echo number_format($avgBill, 0); ?></div>
          <div class="text-muted" style="font-size:0.7rem;">Avg Bill</div>
        </div>
      </div>
    </div>

    <!-- Anomaly flags quick view -->
    <?php
    $flags = [];
    if ($notpaid['cnt'] > 0)     $flags[] = ['danger', "🔴 {$notpaid['cnt']} unpaid bill(s) — ₹" . number_format($notpaid['amount'], 0) . " uncollected"];
    if ($staffPay['cnt'] > 0)    $flags[] = ['warning', "🟡 {$staffPay['cnt']} staff-payment bill(s) — ₹" . number_format($staffPay['amount'], 0)];
    if (!empty($lowBills))       $flags[] = ['warning', "🟡 " . count($lowBills) . " suspiciously low bill(s) (< ₹{$lowBillThreshold})"];
    if ($invGaps > 0)            $flags[] = ['danger', "🔴 {$invGaps} invoice number gap(s) — possible deleted bills"];
    if (!empty($highQtyLines))   $flags[] = ['warning', "🟡 " . count($highQtyLines) . " line item(s) with unusually high quantity (> 20)"];
    ?>
    <?php if (!empty($flags)): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-3">
      <div class="card-header fw-bold text-white" style="background:#dc3545;">
        🚨 Anomaly Flags Detected
      </div>
      <div class="card-body py-2 px-3">
        <?php foreach ($flags as [$type, $msg]): ?>
        <div class="alert alert-<?php echo $type; ?> py-1 px-2 mb-1 small mb-2"><?php echo $msg; ?></div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php else: ?>
    <div class="alert alert-success border-0 shadow-sm rounded-4 mb-3 small">✅ No anomaly flags detected for this session.</div>
    <?php endif; ?>

    <!-- Generate button -->
    <div class="card border-0 shadow-sm rounded-4 mb-3" style="background:linear-gradient(135deg,#1a1a2e,#16213e);">
      <div class="card-body text-center py-4">
        <div class="text-white-50 mb-2" style="font-size:0.85rem;">
          AI will analyse billing trends, anomalies, payment patterns, wastage &amp; give actionable insights
        </div>
        <form method="POST" id="genForm">
          <input type="hidden" name="action" value="generate">
          <input type="hidden" name="start_time" value="<?php echo htmlspecialchars($start_time); ?>">
          <input type="hidden" name="end_time" value="<?php echo htmlspecialchars($end_time); ?>">
          <button type="submit" id="genBtn" class="btn btn-warning px-5 fw-bold" style="font-size:1rem;">
            ✨ Generate AI Summary
          </button>
        </form>
        <?php if ($savedSummary): ?>
        <div class="text-white-50 mt-2" style="font-size:0.72rem;">
          Last generated: <?php echo date('d M, h:i A', strtotime($savedSummary['generated_at'])); ?>
          — click above to regenerate
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($aiError): ?>
    <div class="alert alert-danger rounded-4 border-0 shadow-sm"><?php echo $aiError; ?></div>
    <?php endif; ?>

    <!-- AI Result (fresh or saved) -->
    <?php $displayResult = $aiResult ?? ($savedSummary['summary'] ?? null); ?>
    <?php if ($displayResult): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-3">
      <div class="card-header text-white fw-bold d-flex justify-content-between"
           style="background:#1a1a2e;">
        <span>🤖 AI Summary — <?php echo htmlspecialchars($session_label); ?></span>
        <small class="opacity-75"><?php echo $aiResult ? 'Just generated' : 'Saved ' . date('h:i A', strtotime($savedSummary['generated_at'])); ?></small>
      </div>
      <div class="card-body ai-content p-4">
        <?php echo formatMarkdown($displayResult); ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Past summaries -->
    <?php if (!empty($pastSummaries)): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-3">
      <div class="card-header fw-semibold small" style="background:#f8f3e6;">
        🕘 Past Session Summaries
      </div>
      <div class="list-group list-group-flush rounded-bottom-4">
        <?php foreach ($pastSummaries as $ps): ?>
        <?php
          $psStart = $ps['session_date'] . 'T14:00';
          $psEnd   = date('Y-m-d', strtotime($ps['session_date'] . ' +1 day')) . 'T02:00';
          $psUrl   = "ai_daily_summary.php?start_time={$psStart}&end_time={$psEnd}";
        ?>
        <a href="<?php echo $psUrl; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2">
          <div>
            <span class="fw-semibold"><?php echo date('d M Y', strtotime($ps['session_date'])); ?></span>
            <span class="text-muted small ms-2"><?php echo $ps['total_bills']; ?> bills · ₹<?php echo number_format($ps['total_revenue'], 0); ?></span>
          </div>
          <div class="d-flex align-items-center gap-2">
            <?php if ($ps['anomaly_count'] > 0): ?>
            <span class="badge bg-danger"><?php echo $ps['anomaly_count']; ?> flags</span>
            <?php else: ?>
            <span class="badge bg-success">Clean</span>
            <?php endif; ?>
            <small class="text-muted" style="font-size:0.7rem;"><?php echo date('h:i A', strtotime($ps['generated_at'])); ?></small>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>
</main>

<?php include_once("web_shopadmin_footer.php"); ?>

<?php
function formatMarkdown(string $text): string {
    $text = preg_replace('/^#### (.+)$/m',  '<h6 class="mt-3 mb-1 fw-bold">$1</h6>', $text);
    $text = preg_replace('/^### (.+)$/m',   '<h5 class="mt-3 mb-2 fw-bold">$1</h5>', $text);
    $text = preg_replace('/^## (.+)$/m',    '<h4 class="mt-4 mb-2 fw-bold" style="color:#1a1a2e;border-left:4px solid #b8860b;padding-left:10px;">$1</h4>', $text);
    $text = preg_replace('/^# (.+)$/m',     '<h3 class="mt-4 mb-2 fw-bold">$1</h3>', $text);
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.+?)\*/',     '<em>$1</em>', $text);
    $text = preg_replace('/^[-*] (.+)$/m',  '<li>$1</li>', $text);
    $text = preg_replace('/(<li>.*<\/li>\n?)+/', '<ul class="mb-2">$0</ul>', $text);
    $text = preg_replace('/^\d+\. (.+)$/m', '<li>$1</li>', $text);
    $text = nl2br($text);
    $text = preg_replace('/^---$/m', '<hr>', $text);
    $text = str_replace(['Rs.', 'INR '], ['₹', '₹'], $text);
    return $text;
}
?>

<style>
.ai-content { font-size:0.93rem; line-height:1.75; color:#2d2d2d; }
.ai-content ul { padding-left:1.2rem; }
.ai-content li { margin-bottom:4px; }
.ai-content strong { color:#1a1a2e; }
</style>

<script>
document.getElementById('genForm').addEventListener('submit', function() {
    const btn = document.getElementById('genBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Analysing session data…';
});
</script>
