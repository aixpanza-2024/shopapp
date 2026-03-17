<?php
include("web_shopadmin_header.php");

// Super Admin only
if (!isset($_SESSION['userpermission']) || $_SESSION['userpermission'] !== 'Super Admin') {
    echo "<script>alert('Access denied.'); window.location='shopadmin.php';</script>";
    exit;
}

include_once(__DIR__ . '/../ai_config.php');
include_once(__DIR__ . '/../weather_config.php');

$shop_id   = intval($_SESSION['selectshop'] ?? 0);
$shopWhere = $shop_id > 0 ? "AND shopid=$shop_id" : "";
$now       = date('Y-m-d H:i:s');
$month_ago = date('Y-m-d H:i:s', strtotime('-30 days'));

// ── 1. Current weather ─────────────────────────────────────────────────────
$weatherRow = null;
$wRes = mysqli_query($conn, "SELECT * FROM weather_log ORDER BY recorded_at DESC LIMIT 1");
if ($wRes) $weatherRow = mysqli_fetch_assoc($wRes);

// ── 2. Products with profitability ────────────────────────────────────────
$products = [];
$pRes = mysqli_query($conn, "SELECT p_id, name, saleprice, purchaseprice FROM products WHERE status='Active' $shopWhere ORDER BY name");
while ($r = mysqli_fetch_assoc($pRes)) $products[] = $r;

// ── 3. Top selling items last 30 days ────────────────────────────────────
$topSales = [];
$shopSaleWhere = "";
$sRes = mysqli_query($conn, "
    SELECT ds.`p_id`, ds.`product name` AS p_name, SUM(ds.quantity) AS total_qty,
           SUM(ds.quantity * ds.`Selling Price`) AS total_revenue
    FROM daily_productsale ds
    WHERE ds.`payment status` != 'notpaid'
      AND ds.Time >= '$month_ago'
      $shopSaleWhere
    GROUP BY ds.`p_id`, ds.`product name`
    ORDER BY total_qty DESC
    LIMIT 10
");
if ($sRes) while ($r = mysqli_fetch_assoc($sRes)) $topSales[] = $r;

// ── 4. Ingredients mapped ─────────────────────────────────────────────────
$ingredientMap = [];
$iRes = mysqli_query($conn, "
    SELECT pi.product_id, p.name AS product_name,
           GROUP_CONCAT(CONCAT(pi.ingredient_name, ' (', pi.quantity, ' ', pi.unit, ')') ORDER BY pi.ingredient_name SEPARATOR ', ') AS ingredients
    FROM product_ingredients pi
    JOIN products p ON p.p_id = pi.product_id
    WHERE pi.shop_id = $shop_id OR pi.shop_id = 0
    GROUP BY pi.product_id, p.name
");
if ($iRes) while ($r = mysqli_fetch_assoc($iRes)) $ingredientMap[] = $r;

// ── 5. All raw materials ever purchased (last 90 days) ────────────────────
$rawMaterials = [];
$eRes = mysqli_query($conn, "
    SELECT DISTINCT expense_name FROM shop_expenses
    WHERE is_deleted=0 AND expense_category='Raw Materials'
    ORDER BY expense_name
    LIMIT 60
");
if ($eRes) while ($r = mysqli_fetch_assoc($eRes)) $rawMaterials[] = $r['expense_name'];

// Also from master
$mRes = mysqli_query($conn, "SELECT name FROM expense_names_master ORDER BY name LIMIT 80");
$masterNames = [];
if ($mRes) while ($r = mysqli_fetch_assoc($mRes)) $masterNames[] = $r['name'];

// ── 6. Billing patterns – peak hours ─────────────────────────────────────
$peakHours = [];
$hRes = mysqli_query($conn, "
    SELECT HOUR(Time) AS hr, COUNT(DISTINCT `Inv no`) AS bills
    FROM daily_productsale
    WHERE Time >= '$month_ago' $shopSaleWhere
    GROUP BY HOUR(Time)
    ORDER BY bills DESC
    LIMIT 3
");
if ($hRes) while ($r = mysqli_fetch_assoc($hRes)) $peakHours[] = $r['hr'] . ':00';

// ── 7. Monthly expense total on raw materials ─────────────────────────────
$expRow = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(expense_amount * expense_qty), 0) AS total
    FROM shop_expenses
    WHERE is_deleted=0 AND expense_category='Raw Materials'
    AND expense_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
") ?: $conn->query("SELECT 0 AS total"));

// ── Build prompt context ──────────────────────────────────────────────────
$location   = $weatherRow['location'] ?? OWM_CITY;
$temp       = $weatherRow ? $weatherRow['temperature'] . '°C' : 'N/A';
$humidity   = $weatherRow ? $weatherRow['humidity'] . '%' : 'N/A';
$weather_d  = $weatherRow ? $weatherRow['weather_type'] : 'N/A';
$month_name = date('F');
$season     = in_array(date('n'), [3,4,5]) ? 'Summer' : (in_array(date('n'), [6,7,8,9]) ? 'Monsoon' : (in_array(date('n'), [10,11]) ? 'Post-Monsoon' : 'Winter'));

$productLines = [];
foreach ($products as $p) {
    $margin = $p['saleprice'] > 0 ? round((($p['saleprice'] - $p['purchaseprice']) / $p['saleprice']) * 100) : 0;
    $productLines[] = "- {$p['name']}: sell ₹{$p['saleprice']}, cost ₹{$p['purchaseprice']}, margin {$margin}%";
}

$topSaleLines = [];
foreach ($topSales as $s) {
    $topSaleLines[] = "- {$s['p_name']}: {$s['total_qty']} units sold, ₹" . number_format($s['total_revenue'], 0) . " revenue";
}

$ingLines = [];
foreach ($ingredientMap as $im) {
    $ingLines[] = "- {$im['product_name']}: {$im['ingredients']}";
}

$allIngredients = array_unique(array_merge($rawMaterials, $masterNames));
sort($allIngredients);

// Build prompt blocks
$peakHoursStr  = !empty($peakHours) ? implode(', ', $peakHours) : 'Not enough data';
$productBlock  = !empty($productLines) ? implode("\n", $productLines) : 'No products found';
$topSalesBlock = !empty($topSaleLines) ? implode("\n", $topSaleLines) : 'No sales data yet';
$ingBlock      = !empty($ingLines) ? implode("\n", $ingLines) : 'No ingredients mapped yet — go to Ingredients page';
$ingredientsBlock = !empty($allIngredients) ? implode(', ', array_slice($allIngredients, 0, 60)) : 'None';

// Rebuild prompt with actual values
$prompt = "You are a food business consultant for a South Indian hotel/snack shop. Analyze this shop's data and suggest the most profitable new snacks/dishes to introduce.\n\n";
$prompt .= "**SHOP CONTEXT**\n";
$prompt .= "- Location: {$location}\n";
$prompt .= "- Date: " . date('d M Y') . ", Season: {$season} ({$month_name})\n";
$prompt .= "- Current weather: {$temp}, Humidity: {$humidity}, Condition: {$weather_d}\n";
$prompt .= "- Peak billing hours: {$peakHoursStr}\n";
$prompt .= "- Monthly raw material spend: ₹" . number_format($expRow['total'], 0) . "\n\n";

$prompt .= "**CURRENT MENU WITH PROFITABILITY**\n{$productBlock}\n\n";
$prompt .= "**TOP SELLING ITEMS (last 30 days)**\n{$topSalesBlock}\n\n";
$prompt .= "**INGREDIENTS ALREADY MAPPED TO PRODUCTS**\n{$ingBlock}\n\n";
$prompt .= "**RAW MATERIALS / INGREDIENTS AVAILABLE IN SHOP**\n{$ingredientsBlock}\n\n";

$prompt .= "---\n\nBased on this data, provide:\n\n";
$prompt .= "## 1. Top 5 New Snacks/Dishes to Introduce\n";
$prompt .= "For each: dish name, why it fits this location/weather/season, which available ingredients can be reused, estimated selling price (₹) and profit margin %, current trend relevance.\n\n";
$prompt .= "## 2. Quick Wins\n";
$prompt .= "List 3 dishes that can be made immediately using only ingredients already available — zero new purchases needed.\n\n";
$prompt .= "## 3. High-Margin Opportunity\n";
$prompt .= "One premium dish or combo that could justify a higher price for the current customer base.\n\n";
$prompt .= "## 4. Seasonal / Weather Tip\n";
$prompt .= "1-2 specific suggestions based on today's weather ({$weather_d}, {$temp}, humidity {$humidity}).\n\n";
$prompt .= "Keep suggestions practical for a South Indian hotel. Be specific with ingredient names and pricing in Indian Rupees (₹).";

// ── Handle Generate request ───────────────────────────────────────────────
$aiResult    = null;
$aiError     = null;
$generating  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    $generating = true;
    if (OPENAI_API_KEY === 'YOUR_OPENAI_API_KEY_HERE') {
        $aiError = 'OpenAI API key not configured. Please edit <code>ai_config.php</code> and add your API key.';
    } else {
        $payload = json_encode([
            'model'    => OPENAI_MODEL,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a profitable food business consultant specialising in South Indian snack shops and hotels. Give practical, data-driven advice.'],
                ['role' => 'user',   'content' => $prompt],
            ],
            'max_tokens'  => 2000,
            'temperature' => 0.7,
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

        $raw      = curl_exec($ch);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $curlErr) {
            $aiError = 'Network error: ' . $curlErr;
        } else {
            $resp = json_decode($raw, true);
            if (!empty($resp['error'])) {
                $aiError = $resp['error']['message'] ?? 'OpenAI API error';
            } elseif (!empty($resp['choices'][0]['message']['content'])) {
                $aiResult = $resp['choices'][0]['message']['content'];
                // Save to DB for history
                mysqli_query($conn, "CREATE TABLE IF NOT EXISTS ai_suggestion_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    shop_id INT NOT NULL DEFAULT 0,
                    suggestion TEXT NOT NULL,
                    prompt_summary VARCHAR(500) DEFAULT NULL,
                    generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )");
                $safeResult = mysqli_real_escape_string($conn, $aiResult);
                $summary    = mysqli_real_escape_string($conn, "Weather: {$temp}, {$weather_d}. Products: " . count($products) . ". Sales data: " . count($topSales) . " items.");
                mysqli_query($conn, "INSERT INTO ai_suggestion_log (shop_id, suggestion, prompt_summary) VALUES ($shop_id, '$safeResult', '$summary')");
            } else {
                $aiError = 'Unexpected response from AI. Raw: ' . htmlspecialchars(substr($raw, 0, 300));
            }
        }
    }
}

// Ensure table exists then load past suggestions
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS ai_suggestion_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shop_id INT NOT NULL DEFAULT 0,
    suggestion TEXT NOT NULL,
    prompt_summary VARCHAR(500) DEFAULT NULL,
    generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)");
$history = [];
$hRes = mysqli_query($conn, "SELECT * FROM ai_suggestion_log WHERE shop_id=$shop_id ORDER BY generated_at DESC LIMIT 5");
if ($hRes) while ($r = mysqli_fetch_assoc($hRes)) $history[] = $r;
?>

<main>
  <nav class="navbar navbar-expand-lg navbar-light" style="background-color:#1a1a2e !important;">
    <div class="container-fluid d-flex flex-wrap align-items-center justify-content-between">
      <div class="d-flex align-items-center">
        <img src="../images/logo.jpg" alt="Logo" class="img-fluid" style="max-height:50px;">
        <div class="ms-3">
          <h5 class="text-white mb-0">🤖 AI Suggestions</h5>
          <small class="text-warning opacity-75">Super Admin</small>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <a href="ingredients.php" class="btn btn-outline-light btn-sm">🧪 Ingredients</a>
        <?php include_once("../master_mobnav.php"); ?>
      </div>
    </div>
  </nav>

  <div class="container py-3">

    <!-- Context Summary Cards -->
    <div class="row g-2 mb-3">
      <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-3 text-center py-2">
          <div class="text-muted" style="font-size:0.7rem;">Location</div>
          <div class="fw-bold small"><?php echo htmlspecialchars($location); ?></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-3 text-center py-2">
          <div class="text-muted" style="font-size:0.7rem;">Weather</div>
          <div class="fw-bold small"><?php echo $temp; ?> · <?php echo $humidity; ?> humidity</div>
          <div style="font-size:0.7rem;" class="text-muted"><?php echo htmlspecialchars($weather_d); ?></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-3 text-center py-2">
          <div class="text-muted" style="font-size:0.7rem;">Products</div>
          <div class="fw-bold small"><?php echo count($products); ?> active</div>
          <div style="font-size:0.7rem;" class="text-muted"><?php echo count($ingredientMap); ?> mapped</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-3 text-center py-2">
          <div class="text-muted" style="font-size:0.7rem;">Top Sellers (30d)</div>
          <div class="fw-bold small"><?php echo count($topSales); ?> items tracked</div>
          <div style="font-size:0.7rem;" class="text-muted">Season: <?php echo $season; ?></div>
        </div>
      </div>
    </div>

    <?php if (count($ingredientMap) === 0): ?>
    <div class="alert alert-warning rounded-4 border-0 shadow-sm mb-3">
      <strong>Tip:</strong> Map ingredients to your products first on the
      <a href="ingredients.php" class="alert-link">Ingredients page</a> —
      the more data you provide, the better the AI suggestions.
    </div>
    <?php endif; ?>

    <!-- Generate Button -->
    <div class="card border-0 shadow-sm rounded-4 mb-3" style="background:linear-gradient(135deg,#1a1a2e,#16213e);">
      <div class="card-body py-4 text-center">
        <div class="text-white mb-2" style="font-size:0.9rem;">
          AI will analyse your menu, sales, ingredients, weather &amp; trends
        </div>
        <form method="POST" id="genForm">
          <input type="hidden" name="action" value="generate">
          <button type="submit" class="btn btn-warning px-5 fw-bold" id="genBtn" style="font-size:1rem;">
            ✨ Generate AI Suggestions
          </button>
        </form>
        <div class="text-white-50 mt-2" style="font-size:0.75rem;">
          Powered by OpenAI · Uses your live shop data
        </div>
      </div>
    </div>

    <!-- Result -->
    <?php if ($aiError): ?>
    <div class="alert alert-danger rounded-4 border-0 shadow-sm">
      <strong>Error:</strong> <?php echo $aiError; ?>
    </div>
    <?php endif; ?>

    <?php if ($aiResult): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-3" id="aiResultCard">
      <div class="card-header text-white fw-bold d-flex justify-content-between align-items-center"
           style="background:#1a1a2e;">
        <span>🤖 AI Analysis — <?php echo date('d M Y, h:i A'); ?></span>
        <small class="opacity-75"><?php echo htmlspecialchars(OPENAI_MODEL); ?></small>
      </div>
      <div class="card-body ai-content p-4">
        <?php echo formatMarkdown($aiResult); ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Past Suggestions History -->
    <?php if (!empty($history) && !$aiResult): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-3">
      <div class="card-header fw-semibold" style="background:#f8f3e6;">
        🕘 Previous AI Suggestions
      </div>
      <div class="accordion" id="historyAccordion">
        <?php foreach ($history as $i => $h): ?>
        <div class="accordion-item border-0 border-bottom">
          <h2 class="accordion-header">
            <button class="accordion-button <?php echo $i > 0 ? 'collapsed' : ''; ?> py-2 small"
                    type="button" data-bs-toggle="collapse"
                    data-bs-target="#hist<?php echo $h['id']; ?>">
              <?php echo date('d M Y, h:i A', strtotime($h['generated_at'])); ?>
              <span class="ms-2 text-muted" style="font-size:0.75rem;"><?php echo htmlspecialchars($h['prompt_summary']); ?></span>
            </button>
          </h2>
          <div id="hist<?php echo $h['id']; ?>" class="accordion-collapse collapse <?php echo $i === 0 ? 'show' : ''; ?>"
               data-bs-parent="#historyAccordion">
            <div class="accordion-body ai-content">
              <?php echo formatMarkdown($h['suggestion']); ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>
</main>

<?php include_once("web_shopadmin_footer.php"); ?>

<?php
function formatMarkdown(string $text): string {
    // Headers
    $text = preg_replace('/^#### (.+)$/m',   '<h6 class="mt-3 mb-1 fw-bold">$1</h6>', $text);
    $text = preg_replace('/^### (.+)$/m',    '<h5 class="mt-3 mb-1 fw-bold">$1</h5>', $text);
    $text = preg_replace('/^## (.+)$/m',     '<h4 class="mt-4 mb-2 fw-bold" style="color:#1a1a2e;">$1</h4>', $text);
    $text = preg_replace('/^# (.+)$/m',      '<h3 class="mt-4 mb-2 fw-bold">$1</h3>', $text);
    // Bold / italic
    $text = preg_replace('/\*\*(.+?)\*\*/',  '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.+?)\*/',      '<em>$1</em>', $text);
    // Bullet lists
    $text = preg_replace('/^[-*] (.+)$/m',   '<li>$1</li>', $text);
    $text = preg_replace('/(<li>.*<\/li>\n?)+/', '<ul class="mb-2">$0</ul>', $text);
    // Numbered lists
    $text = preg_replace('/^\d+\. (.+)$/m',  '<li>$1</li>', $text);
    // Line breaks
    $text = nl2br($text);
    // Horizontal rule
    $text = preg_replace('/^---$/m', '<hr>', $text);
    // Rupee symbol safety
    $text = str_replace('Rs.', '₹', $text);
    return $text;
}
?>

<style>
.ai-content { font-size: 0.93rem; line-height: 1.7; color: #2d2d2d; }
.ai-content h4 { border-left: 4px solid #b8860b; padding-left: 10px; }
.ai-content ul { padding-left: 1.2rem; }
.ai-content li { margin-bottom: 4px; }
.ai-content strong { color: #1a1a2e; }
</style>

<script>
document.getElementById('genForm').addEventListener('submit', function () {
    const btn = document.getElementById('genBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Analysing your data…';
});
</script>
