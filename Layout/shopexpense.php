<?php
include("web_shopadmin_header.php");
date_default_timezone_set('Asia/Kolkata');

// Auto-create tables
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS shop_daily_opening (
    id INT AUTO_INCREMENT PRIMARY KEY,
    opening_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    notes VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_date (opening_date)
)");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS shop_savings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    savings_type VARCHAR(100) NOT NULL DEFAULT 'Chitty',
    amount DECIMAL(10,2) NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    savings_date DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0
)");

// Ensure expense rows can be tracked within the session window.
$expenseCreatedAtRes = mysqli_query($conn, "SHOW COLUMNS FROM shop_expenses LIKE 'created_at'");
if ($expenseCreatedAtRes && mysqli_num_rows($expenseCreatedAtRes) === 0) {
    mysqli_query($conn, "ALTER TABLE shop_expenses ADD COLUMN created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP");
}
$expenseSupplierIdRes = mysqli_query($conn, "SHOW COLUMNS FROM shop_expenses LIKE 'supplier_id'");
if ($expenseSupplierIdRes && mysqli_num_rows($expenseSupplierIdRes) === 0) {
    mysqli_query($conn, "ALTER TABLE shop_expenses ADD COLUMN supplier_id INT NULL AFTER expense_category");
}
$expenseSupplierNameRes = mysqli_query($conn, "SHOW COLUMNS FROM shop_expenses LIKE 'supplier_name'");
if ($expenseSupplierNameRes && mysqli_num_rows($expenseSupplierNameRes) === 0) {
    mysqli_query($conn, "ALTER TABLE shop_expenses ADD COLUMN supplier_name VARCHAR(255) NULL AFTER supplier_id");
}
$expensePaymentStatusRes = mysqli_query($conn, "SHOW COLUMNS FROM shop_expenses LIKE 'payment_status'");
if ($expensePaymentStatusRes && mysqli_num_rows($expensePaymentStatusRes) === 0) {
    mysqli_query($conn, "ALTER TABLE shop_expenses ADD COLUMN payment_status VARCHAR(20) NOT NULL DEFAULT 'paid' AFTER supplier_name");
}

// --- Default time range: shop session 2 PM -> 2 AM next day ---
$hour = (int)date('H');
if ($hour < 2) {
    $session_date = date('Y-m-d', strtotime('-1 day'));
} else {
    $session_date = date('Y-m-d');
}
$def_start = $session_date . ' 14:00';
$def_end   = date('Y-m-d', strtotime($session_date . ' +1 day')) . ' 02:00';

$start_time = isset($_GET['start_time']) && $_GET['start_time'] !== '' ? $_GET['start_time'] : $def_start;
$end_time   = isset($_GET['end_time'])   && $_GET['end_time']   !== '' ? $_GET['end_time']   : $def_end;

$start_time = str_replace('T', ' ', $start_time);
$end_time   = str_replace('T', ' ', $end_time);
$report_session_date = date('Y-m-d', strtotime($start_time));
$redirect_qs = '?start_time=' . urlencode(str_replace(' ', 'T', $start_time)) . '&end_time=' . urlencode(str_replace(' ', 'T', $end_time));
$entry_session_date = $session_date;
$now_ts = date('Y-m-d H:i:s');

// Handle opening balance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'opening_balance') {
    $amount = floatval($_POST['ob_amount']);
    $notes  = mysqli_real_escape_string($conn, trim($_POST['ob_notes'] ?? ''));
    mysqli_query($conn, "INSERT INTO shop_daily_opening (opening_date, amount, notes)
        VALUES ('$entry_session_date', $amount, '$notes')
        ON DUPLICATE KEY UPDATE amount=$amount, notes='$notes'");
    echo "<script>alert('Opening balance saved!'); window.location='shopexpense.php{$redirect_qs}';</script>";
    exit;
}

// Handle savings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'savings') {
    $type   = mysqli_real_escape_string($conn, trim($_POST['savings_type']));
    $amount = floatval($_POST['savings_amount']);
    $notes  = mysqli_real_escape_string($conn, trim($_POST['savings_notes'] ?? ''));
    mysqli_query($conn, "INSERT INTO shop_savings (savings_type, amount, notes, savings_date, created_at)
        VALUES ('$type', $amount, '$notes', '$entry_session_date', '$now_ts')");
    echo "<script>alert('Savings recorded!'); window.location='shopexpense.php{$redirect_qs}';</script>";
    exit;
}

// Handle expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'expense') {
    $expense_name     = mysqli_real_escape_string($conn, trim($_POST['expense_name']));
    $expense_amount   = floatval($_POST['expense_amount']);
    $expense_qty      = max(1, intval($_POST['expense_qty']));
    $expense_category = mysqli_real_escape_string($conn, $_POST['expense_category']);
    $supplier_id      = intval($_POST['supplier_id'] ?? 0);
    $payment_status_raw = trim($_POST['payment_status'] ?? 'paid');
    $payment_status   = $payment_status_raw === 'not paid' ? 'not paid' : 'paid';
    $expense_note     = mysqli_real_escape_string($conn, trim($_POST['expense_note'] ?? ''));
    $supplier_name    = '';
    if ($supplier_id > 0) {
        $shop_id = intval($_SESSION['selectshop'] ?? 0);
        $supplierSql = "SELECT sup_id, name FROM supplier WHERE sup_id = $supplier_id";
        if ($shop_id > 0) {
            $supplierSql .= " AND sh_id = $shop_id";
        }
        $supplierSql .= " LIMIT 1";
        $supplierRes = mysqli_query($conn, $supplierSql);
        if ($supplierRes && ($supplierRow = mysqli_fetch_assoc($supplierRes))) {
            $supplier_id = (int)$supplierRow['sup_id'];
            $supplier_name = mysqli_real_escape_string($conn, $supplierRow['name']);
        } else {
            $supplier_id = 0;
        }
    }
    $supplier_id_sql = $supplier_id > 0 ? (string)$supplier_id : "NULL";
    $sql = "INSERT INTO shop_expenses (expense_name, expense_amount, expense_qty, expense_date, expense_category, supplier_id, supplier_name, payment_status, expense_note, created_at)
            VALUES ('$expense_name', $expense_amount, $expense_qty, '$entry_session_date', '$expense_category', $supplier_id_sql, '$supplier_name', '$payment_status', '$expense_note', '$now_ts')";
    if (mysqli_query($conn, $sql)) {
        echo "<script>alert('Expense added!'); window.location='shopexpense.php{$redirect_qs}';</script>";
    } else {
        echo "<script>alert('Error: " . mysqli_error($conn) . "');</script>";
    }
    exit;
}

// Session opening balance
$ob_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shop_daily_opening WHERE opening_date='$report_session_date'"));

// Ensure master expense names table exists
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS expense_names_master (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    UNIQUE KEY unique_name (name)
)");
// Seed hardcoded defaults into master table (only inserts if not already present)
$defaultNames = [
    // Operations & Utilities
    'Gas Cylinder','Electricity Bill','Rent','Water','Auto','Miscellaneous',
    'Staff Salary','Cleaning Items','Paper Cups','Soda','Ice Purchase',

    // Flours & Grains
    'Wheat Flour','Maida','Rice Flour','Rava','Besan','Corn Flour','Ragi Flour',
    'Rice','Basmati Rice','Poha','Vermicelli','Bread','Bun','Noodles','Pasta',

    // Oils & Fats
    'Coconut Oil','Sunflower Oil','Palm Oil','Groundnut Oil','Sesame Oil',
    'Mustard Oil','Refined Oil','Ghee','Butter','Margarine','Vanaspati',

    // Dairy
    'Milk','Curd','Paneer','Cream','Khoya','Condensed Milk','Cheese','Eggs',

    // Spices & Masalas
    'Salt','Turmeric','Red Chilli Powder','Coriander Powder','Cumin Seeds',
    'Mustard Seeds','Black Pepper','Cardamom','Cloves','Cinnamon','Bay Leaves',
    'Fenugreek Seeds','Asafoetida','Garam Masala','Sambar Powder','Rasam Powder',
    'Biryani Masala','Chicken Masala','Fennel Seeds','Cumin Powder','Pepper Powder',

    // Pulses & Lentils
    'Toor Dal','Chana Dal','Moong Dal','Urad Dal','Masoor Dal',
    'Rajma','Chana','Green Gram','Black Eyed Peas',

    // Meat & Seafood
    'Chicken','Mutton','Fish','Prawns','Beef','Pork',

    // Sauces & Condiments
    'Tamarind','Jaggery','Sugar','Vinegar','Soy Sauce','Tomato Sauce',
    'Chilli Sauce','Baking Powder','Baking Soda','Yeast','Vanilla Essence',
    'Food Colour','Coconut Milk','Coconut Cream',

    // Beverages & Dry
    'Tea Powder','Coffee Powder','Cocoa Powder','Horlicks','Boost',

    // Vegetables
    'Tomato','Onion','Potato','Capsicum','Cabbage','Cauliflower','Carrot',
    'Beans','Green Chilli','Coriander Leaves','Curry Leaves','Ginger','Garlic',
    'Beetroot','Spinach','Drumstick','Ladies Finger','Cucumber','Bottle Gourd',
    'Pumpkin','Brinjal','Bitter Gourd','Ridge Gourd','Snake Gourd','Ash Gourd',
    'Raw Banana','Raw Papaya','Yam','Colocasia','Sweet Potato','Spring Onion',
    'Celery','Leek','Mushroom','Baby Corn','Broccoli',

    // Fruits
    'Pineapple','Banana','Apple','Orange','Watermelon','Papaya','Mango',
    'Lemon','Grapes','Pomegranate','Coconut','Tender Coconut','Guava',
    'Sapota','Jackfruit','Dates','Dry Grapes',

    // Snacks & Bakery
    'Snacks Purchase','Biscuits','Bread Crumbs','Vermicelli','Macaroni',
];
foreach ($defaultNames as $dn) {
    $esc = mysqli_real_escape_string($conn, $dn);
    mysqli_query($conn, "INSERT IGNORE INTO expense_names_master (name) VALUES ('$esc')");
}

// Past expense names for autocomplete: master list + any used names not yet in master
$pastNamesRes = mysqli_query($conn, "
    SELECT name FROM expense_names_master
    UNION
    SELECT DISTINCT expense_name FROM shop_expenses WHERE is_deleted=0
    ORDER BY name
");
$pastNames = [];
while ($r = mysqli_fetch_assoc($pastNamesRes)) $pastNames[] = $r['name'];

$supplierRows = [];
$shopId = intval($_SESSION['selectshop'] ?? 0);
$supplierWhere = $shopId > 0 ? "WHERE sh_id = $shopId" : "";
$suppliersRes = mysqli_query($conn, "SELECT sup_id, name FROM supplier $supplierWhere ORDER BY name");
if ($suppliersRes) {
    while ($r = mysqli_fetch_assoc($suppliersRes)) {
        $supplierRows[] = $r;
    }
}

// Session totals summary
$sessionExpRow = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(expense_amount * expense_qty), 0) AS total
    FROM shop_expenses
    WHERE is_deleted = 0
      AND (
        (created_at IS NOT NULL AND created_at >= '$start_time' AND created_at <= '$end_time')
        OR (created_at IS NULL AND expense_date = '$report_session_date')
      )
"));
$sessionSavRow = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(amount), 0) AS total
    FROM shop_savings
    WHERE is_deleted = 0
      AND (
        (created_at >= '$start_time' AND created_at <= '$end_time')
        OR (created_at IS NULL AND savings_date = '$report_session_date')
      )
"));
$sessionSavRes = mysqli_query($conn, "
    SELECT *
    FROM shop_savings
    WHERE is_deleted = 0
      AND (
        (created_at >= '$start_time' AND created_at <= '$end_time')
        OR (created_at IS NULL AND savings_date = '$report_session_date')
      )
    ORDER BY COALESCE(created_at, CONCAT(savings_date, ' 00:00:00')) DESC, id DESC
");
$sessionSavRows = [];
while ($r = mysqli_fetch_assoc($sessionSavRes)) $sessionSavRows[] = $r;
?>

<main>
  <nav class="navbar navbar-expand-lg navbar-light" style="background-color:#b8860b !important;">
    <div class="container-fluid d-flex flex-wrap align-items-center justify-content-between">
      <div class="d-flex align-items-center">
        <img src="../images/logo.jpg" alt="Logo" class="img-fluid" style="max-height:50px;">
        <h5 class="ms-3 text-white mb-0">Daily Finance</h5>
      </div>
      <div class="d-flex align-items-center gap-2">
        <a href="shop_listexpense.php" class="btn btn-outline-light btn-sm">📋 View Expenses</a>
        <?php include_once("../master_mobnav.php"); ?>
      </div>
    </div>
  </nav>

  <div class="container py-3">

    <div class="card shadow-sm border-0 rounded-4 mb-3">
      <div class="card-body">
        <form method="GET" action="shopexpense.php" class="row g-2 align-items-end">
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

    <!-- Session Summary Row -->
    <div class="row g-2 mb-3">
      <div class="col-4">
        <div class="card border-0 shadow-sm rounded-3 text-center py-2">
          <div class="text-muted" style="font-size:0.72rem;">Opening Balance</div>
          <div class="fw-bold fs-6 <?php echo $ob_row ? 'text-success' : 'text-warning'; ?>">
            <?php echo $ob_row ? '₹' . number_format($ob_row['amount'], 2) : '—'; ?>
          </div>
        </div>
      </div>
      <div class="col-4">
        <div class="card border-0 shadow-sm rounded-3 text-center py-2">
          <div class="text-muted" style="font-size:0.72rem;">Expenses In Range</div>
          <div class="fw-bold fs-6 text-danger">₹<?php echo number_format($sessionExpRow['total'], 2); ?></div>
        </div>
      </div>
      <div class="col-4">
        <div class="card border-0 shadow-sm rounded-3 text-center py-2">
          <div class="text-muted" style="font-size:0.72rem;">Savings In Range</div>
          <div class="fw-bold fs-6 text-primary">₹<?php echo number_format($sessionSavRow['total'], 2); ?></div>
        </div>
      </div>
    </div>

    <!-- Opening Balance Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-3">
      <div class="card-header fw-bold text-white d-flex justify-content-between align-items-center"
           style="background:#5a7a3a;">
        <span>💰 Opening Balance</span>
        <small class="opacity-75"><?php echo htmlspecialchars($report_session_date); ?></small>
      </div>
      <div class="card-body py-3">
        <?php if ($ob_row): ?>
        <div class="alert alert-success py-2 mb-2 d-flex justify-content-between align-items-center">
          <span>Session balance: <strong>₹<?php echo number_format($ob_row['amount'], 2); ?></strong>
            <?php if ($ob_row['notes']) echo ' — <em>' . htmlspecialchars($ob_row['notes']) . '</em>'; ?>
          </span>
          <small class="text-muted">tap below to update</small>
        </div>
        <?php endif; ?>
        <form method="POST">
          <input type="hidden" name="form_type" value="opening_balance">
          <div class="row g-2 align-items-end">
            <div class="col-5 col-md-4">
              <label class="form-label mb-1 small">Amount (₹)</label>
              <input type="number" name="ob_amount" class="form-control"
                     value="<?php echo $ob_row ? htmlspecialchars($ob_row['amount']) : ''; ?>"
                     placeholder="0.00" step="0.01" min="0" required>
            </div>
            <div class="col-5 col-md-6">
              <label class="form-label mb-1 small">Notes (optional)</label>
              <input type="text" name="ob_notes" class="form-control"
                     value="<?php echo $ob_row ? htmlspecialchars($ob_row['notes'] ?? '') : ''; ?>"
                     placeholder="e.g. from safe, previous day">
            </div>
            <div class="col-2 col-md-2">
              <button type="submit" class="btn btn-success w-100">
                <?php echo $ob_row ? '✏️' : '💾'; ?>
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Expense Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-3">
      <div class="card-header fw-bold text-white" style="background:#b8860b;">
        📤 Add Expense
      </div>
      <div class="card-body py-3">
        <form method="POST" autocomplete="off">
          <input type="hidden" name="form_type" value="expense">
          <div class="row g-2">

            <!-- Expense name with custom autocomplete -->
            <div class="col-12">
              <label class="form-label mb-1 small">Expense Name</label>
              <div class="expense-autocomplete">
                <input type="text" id="expense_name" name="expense_name" class="form-control"
                       placeholder="Type to search..." required autocomplete="off">
                <div id="expense_dropdown"></div>
              </div>
            </div>

            <div class="col-6 col-md-4">
              <label class="form-label mb-1 small">Amount (₹)</label>
              <input type="number" name="expense_amount" class="form-control"
                     placeholder="0.00" step="0.01" min="0" required>
            </div>

            <div class="col-6 col-md-2">
              <label class="form-label mb-1 small">Qty</label>
              <input type="number" name="expense_qty" class="form-control"
                     value="1" min="1" step="1" required>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label mb-1 small">Category</label>
              <select name="expense_category" class="form-select" required>
                <option value="">Select Category</option>
                <?php foreach (['Raw Materials','Staff Salary','Maintenance','Utilities','Miscellaneous'] as $cat): ?>
                <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label mb-1 small">Supplier Name</label>
              <select name="supplier_id" class="form-select" <?php echo !empty($supplierRows) ? 'required' : ''; ?>>
                <option value=""><?php echo !empty($supplierRows) ? 'Select Supplier' : 'No suppliers found'; ?></option>
                <?php foreach ($supplierRows as $supplier): ?>
                <option value="<?php echo (int)$supplier['sup_id']; ?>"><?php echo htmlspecialchars($supplier['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label mb-1 small">Payment Status</label>
              <select name="payment_status" class="form-select" required>
                <option value="paid">Paid</option>
                <option value="not paid">Not Paid</option>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label mb-1 small">Notes (optional)</label>
              <input type="text" name="expense_note" class="form-control" placeholder="Add any remarks">
            </div>
          </div>
          <div class="mt-3">
            <button type="submit" class="btn btn-warning text-white px-4 fw-semibold">💾 Save Expense</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Savings Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-3">
      <div class="card-header fw-bold text-white" style="background:#1a6496;">
        🏦 Record Savings
      </div>
      <div class="card-body py-3">
        <form method="POST">
          <input type="hidden" name="form_type" value="savings">
          <div class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
              <label class="form-label mb-1 small">Type</label>
              <select name="savings_type" class="form-select" required>
                <option value="Chitty">Chitty</option>
                <option value="Fixed Deposit">Fixed Deposit</option>
                <option value="Personal">Personal</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="col-6 col-md-3">
              <label class="form-label mb-1 small">Amount (₹)</label>
              <input type="number" name="savings_amount" class="form-control"
                     placeholder="0.00" step="0.01" min="0" required>
            </div>
            <div class="col-9 col-md-4">
              <label class="form-label mb-1 small">Notes (optional)</label>
              <input type="text" name="savings_notes" class="form-control"
                     placeholder="e.g. week 3 chitty payment">
            </div>
            <div class="col-3 col-md-2">
              <button type="submit" class="btn btn-primary w-100">💾</button>
            </div>
          </div>
        </form>

        <?php if (!empty($sessionSavRows)): ?>
        <div class="mt-3">
          <div class="small text-muted mb-2">
            Showing savings from <?php echo htmlspecialchars(date('d M Y h:i A', strtotime($start_time))); ?>
            to <?php echo htmlspecialchars(date('d M Y h:i A', strtotime($end_time))); ?>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0 align-middle" style="font-size:0.85rem;">
              <thead class="table-light">
                <tr><th>Type</th><th>Amount</th><th>Notes</th><th></th></tr>
              </thead>
              <tbody>
                <?php foreach ($sessionSavRows as $s): ?>
                <tr>
                  <td><?php echo htmlspecialchars($s['savings_type']); ?></td>
                  <td class="fw-semibold">₹<?php echo number_format($s['amount'], 2); ?></td>
                  <td class="text-muted"><?php echo htmlspecialchars($s['notes'] ?? ''); ?></td>
                  <td>
                    <a href="?delete_saving=<?php echo $s['id']; ?>&start_time=<?php echo urlencode(str_replace(' ', 'T', $start_time)); ?>&end_time=<?php echo urlencode(str_replace(' ', 'T', $end_time)); ?>"
                       onclick="return confirm('Remove this saving?');"
                       class="btn btn-outline-danger btn-sm py-0 px-1" style="font-size:0.75rem;">✕</a>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /container -->
</main>

<?php include_once("web_shopadmin_footer.php"); ?>

<?php
// Handle savings soft-delete (after output starts, redirect)
if (isset($_GET['delete_saving'])) {
    $sid = intval($_GET['delete_saving']);
    mysqli_query($conn, "UPDATE shop_savings SET is_deleted=1 WHERE id=$sid");
    echo "<script>window.location='shopexpense.php{$redirect_qs}';</script>";
}
?>

<style>
/* Autocomplete dropdown */
.expense-autocomplete { position: relative; }
#expense_dropdown {
  display: none;
  position: absolute;
  top: 100%; left: 0; right: 0;
  z-index: 1050;
  background: #fff;
  border: 1px solid #ced4da;
  border-top: none;
  border-radius: 0 0 10px 10px;
  max-height: 260px;
  overflow-y: auto;
  box-shadow: 0 6px 18px rgba(0,0,0,0.12);
}
.exp-option {
  padding: 9px 14px;
  cursor: pointer;
  font-size: 0.93rem;
  border-bottom: 1px solid #f3f3f3;
}
.exp-option:hover, .exp-option.active {
  background: #fff3cd;
  color: #856404;
}
.exp-option mark {
  background: #ffd700;
  padding: 0;
  font-weight: 600;
}
</style>

<script>
(function () {
  const ALL_OPTIONS = <?php echo json_encode($pastNames); ?>;

  const input    = document.getElementById('expense_name');
  const dropdown = document.getElementById('expense_dropdown');
  let activeIdx  = -1;

  function highlight(text, q) {
    const idx = text.toLowerCase().indexOf(q.toLowerCase());
    if (idx === -1) return document.createTextNode(text);
    const frag = document.createDocumentFragment();
    frag.appendChild(document.createTextNode(text.slice(0, idx)));
    const mark = document.createElement('mark');
    mark.textContent = text.slice(idx, idx + q.length);
    frag.appendChild(mark);
    frag.appendChild(document.createTextNode(text.slice(idx + q.length)));
    return frag;
  }

  function renderDropdown(q) {
    dropdown.innerHTML = '';
    activeIdx = -1;

    const matches = q
      ? ALL_OPTIONS.filter(o => o.toLowerCase().includes(q.toLowerCase()))
      : ALL_OPTIONS;
    if (matches.length === 0) { dropdown.style.display = 'none'; return; }

    matches.slice(0, 50).forEach((m, i) => {
      const div = document.createElement('div');
      div.className = 'exp-option';
      div.dataset.idx = i;
      div.appendChild(highlight(m, q));
      div.addEventListener('mousedown', e => {
        e.preventDefault();
        input.value = m;
        dropdown.style.display = 'none';
      });
      dropdown.appendChild(div);
    });
    dropdown.style.display = 'block';
  }

  input.addEventListener('focus', () => renderDropdown(input.value.trim()));
  input.addEventListener('input', () => renderDropdown(input.value.trim()));
  input.addEventListener('keyup', e => {
    if (['ArrowDown','ArrowUp','Enter','Escape'].includes(e.key)) return;
    renderDropdown(input.value.trim());
  });

  input.addEventListener('keydown', e => {
    const items = dropdown.querySelectorAll('.exp-option');
    if (e.key === 'ArrowDown') {
      activeIdx = Math.min(activeIdx + 1, items.length - 1);
    } else if (e.key === 'ArrowUp') {
      activeIdx = Math.max(activeIdx - 1, -1);
    } else if (e.key === 'Enter' && activeIdx >= 0) {
      e.preventDefault();
      input.value = items[activeIdx].textContent;
      dropdown.style.display = 'none';
      return;
    } else if (e.key === 'Escape') {
      dropdown.style.display = 'none'; return;
    } else return;
    items.forEach((el, i) => el.classList.toggle('active', i === activeIdx));
    if (activeIdx >= 0) items[activeIdx].scrollIntoView({ block: 'nearest' });
  });

  document.addEventListener('click', e => {
    if (!input.contains(e.target) && !dropdown.contains(e.target))
      dropdown.style.display = 'none';
  });
})();
</script>
