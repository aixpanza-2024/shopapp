<?php
include("web_shopadmin_header.php");

// Create tables
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS product_ingredients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    shop_id INT NOT NULL DEFAULT 0,
    ingredient_name VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,3) NOT NULL DEFAULT 1,
    unit VARCHAR(50) NOT NULL DEFAULT 'piece',
    notes VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_product_id (product_id)
)");

$shop_id = intval($_SESSION['selectshop'] ?? 0);

// Handle add ingredient
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'add_ingredient') {
    $product_id      = intval($_POST['product_id']);
    $ingredient_name = mysqli_real_escape_string($conn, trim($_POST['ingredient_name']));
    $quantity        = floatval($_POST['quantity']);
    $unit            = mysqli_real_escape_string($conn, trim($_POST['unit']));
    $notes           = mysqli_real_escape_string($conn, trim($_POST['notes'] ?? ''));

    if ($product_id > 0 && $ingredient_name !== '') {
        mysqli_query($conn, "INSERT INTO product_ingredients (product_id, shop_id, ingredient_name, quantity, unit, notes)
            VALUES ($product_id, $shop_id, '$ingredient_name', $quantity, '$unit', '$notes')");
        // Also save ingredient name to master list
        mysqli_query($conn, "INSERT IGNORE INTO expense_names_master (name) VALUES ('$ingredient_name')");
    }
    header("Location: ingredients.php?product_id=$product_id");
    exit;
}

// Handle delete ingredient
if (isset($_GET['delete_id'])) {
    $del_id     = intval($_GET['delete_id']);
    $product_id = intval($_GET['product_id'] ?? 0);
    mysqli_query($conn, "DELETE FROM product_ingredients WHERE id=$del_id AND shop_id=$shop_id");
    header("Location: ingredients.php?product_id=$product_id");
    exit;
}

// Selected product
$selected_product_id = intval($_GET['product_id'] ?? 0);

// Load products for this shop
$shopWhere = $shop_id > 0 ? "WHERE shopid=$shop_id AND status='Active'" : "WHERE status='Active'";
$productsRes = mysqli_query($conn, "SELECT p_id, name, saleprice FROM products $shopWhere ORDER BY name");
$products = [];
while ($r = mysqli_fetch_assoc($productsRes)) $products[] = $r;

// Load selected product info
$selectedProduct = null;
if ($selected_product_id > 0) {
    foreach ($products as $p) {
        if ((int)$p['p_id'] === $selected_product_id) { $selectedProduct = $p; break; }
    }
}

// Load existing ingredients for selected product
$ingredients = [];
if ($selected_product_id > 0) {
    $ingRes = mysqli_query($conn, "SELECT * FROM product_ingredients WHERE product_id=$selected_product_id ORDER BY id ASC");
    while ($r = mysqli_fetch_assoc($ingRes)) $ingredients[] = $r;
}

// Load ingredient name suggestions (from master)
$namesRes = mysqli_query($conn, "SELECT name FROM expense_names_master ORDER BY name");
$ingredientNames = [];
while ($r = mysqli_fetch_assoc($namesRes)) $ingredientNames[] = $r['name'];

// All products ingredient count (for overview)
$countRes = mysqli_query($conn, "SELECT product_id, COUNT(*) as cnt FROM product_ingredients WHERE shop_id=$shop_id GROUP BY product_id");
$ingCounts = [];
while ($r = mysqli_fetch_assoc($countRes)) $ingCounts[(int)$r['product_id']] = (int)$r['cnt'];

$units = ['g','kg','ml','L','piece','cup','tbsp','tsp','dozen','pack','bottle','bag','bunch'];
?>

<main>
  <nav class="navbar navbar-expand-lg navbar-light" style="background-color:#b8860b !important;">
    <div class="container-fluid d-flex flex-wrap align-items-center justify-content-between">
      <div class="d-flex align-items-center">
        <img src="../images/logo.jpg" alt="Logo" class="img-fluid" style="max-height:50px;">
        <h5 class="ms-3 text-white mb-0">🧪 Ingredients</h5>
      </div>
      <div class="d-flex align-items-center gap-2">
        <?php include_once("../master_mobnav.php"); ?>
      </div>
    </div>
  </nav>

  <div class="container py-3">

    <!-- Product Selector -->
    <div class="card border-0 shadow-sm rounded-4 mb-3">
      <div class="card-body py-3">
        <label class="form-label fw-semibold small mb-1">Select a Product</label>
        <select id="productSelect" class="form-select" onchange="if(this.value) window.location='ingredients.php?product_id='+this.value;">
          <option value="">— Choose product —</option>
          <?php foreach ($products as $p): ?>
          <option value="<?php echo $p['p_id']; ?>"
            <?php echo $selected_product_id == $p['p_id'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($p['name']); ?>
            <?php $cnt = $ingCounts[(int)$p['p_id']] ?? 0; echo $cnt > 0 ? " ($cnt ingredients)" : ''; ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <?php if ($selectedProduct): ?>

    <!-- Selected Product Header -->
    <div class="card border-0 shadow-sm rounded-4 mb-3" style="background:linear-gradient(135deg,#b8860b,#d4a017);">
      <div class="card-body py-3 text-white d-flex justify-content-between align-items-center">
        <div>
          <div class="fw-bold fs-5"><?php echo htmlspecialchars($selectedProduct['name']); ?></div>
          <div class="small opacity-75">Sale Price: ₹<?php echo number_format($selectedProduct['saleprice'], 2); ?></div>
        </div>
        <div class="text-end">
          <div class="fs-3 fw-bold"><?php echo count($ingredients); ?></div>
          <div class="small opacity-75">ingredient<?php echo count($ingredients) !== 1 ? 's' : ''; ?></div>
        </div>
      </div>
    </div>

    <!-- Add Ingredient Form -->
    <div class="card border-0 shadow-sm rounded-4 mb-3">
      <div class="card-header fw-bold text-white" style="background:#5a7a3a;">
        ➕ Add Ingredient
      </div>
      <div class="card-body py-3">
        <form method="POST" autocomplete="off">
          <input type="hidden" name="form_type" value="add_ingredient">
          <input type="hidden" name="product_id" value="<?php echo $selected_product_id; ?>">
          <div class="row g-2">

            <div class="col-12">
              <label class="form-label mb-1 small">Ingredient Name</label>
              <div class="ing-autocomplete">
                <input type="text" id="ingredient_name" name="ingredient_name"
                       class="form-control" placeholder="e.g. Tomato, Maida, Coconut Oil…"
                       required autocomplete="off">
                <div id="ing_dropdown"></div>
              </div>
            </div>

            <div class="col-5 col-md-3">
              <label class="form-label mb-1 small">Quantity</label>
              <input type="number" name="quantity" class="form-control"
                     value="1" min="0.001" step="any" required>
            </div>

            <div class="col-7 col-md-3">
              <label class="form-label mb-1 small">Unit</label>
              <select name="unit" class="form-select" required>
                <?php foreach ($units as $u): ?>
                <option value="<?php echo $u; ?>"><?php echo $u; ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label mb-1 small">Notes (optional)</label>
              <input type="text" name="notes" class="form-control" placeholder="e.g. finely chopped, washed">
            </div>

          </div>
          <div class="mt-3">
            <button type="submit" class="btn btn-success px-4 fw-semibold">💾 Add Ingredient</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Ingredients List -->
    <?php if (!empty($ingredients)): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-3">
      <div class="card-header fw-bold" style="background:#f8f3e6;">
        📋 Ingredients for <em><?php echo htmlspecialchars($selectedProduct['name']); ?></em>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0 align-middle" style="font-size:0.88rem;">
            <thead class="table-light">
              <tr>
                <th class="ps-3">#</th>
                <th>Ingredient</th>
                <th>Qty</th>
                <th>Unit</th>
                <th>Notes</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($ingredients as $i => $ing): ?>
              <tr>
                <td class="ps-3 text-muted"><?php echo $i + 1; ?></td>
                <td class="fw-semibold"><?php echo htmlspecialchars($ing['ingredient_name']); ?></td>
                <td><?php echo rtrim(rtrim(number_format((float)$ing['quantity'], 3, '.', ''), '0'), '.'); ?></td>
                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($ing['unit']); ?></span></td>
                <td class="text-muted"><?php echo htmlspecialchars($ing['notes'] ?? ''); ?></td>
                <td>
                  <a href="?delete_id=<?php echo $ing['id']; ?>&product_id=<?php echo $selected_product_id; ?>"
                     onclick="return confirm('Remove this ingredient?');"
                     class="btn btn-outline-danger btn-sm py-0 px-1" style="font-size:0.75rem;">✕</a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php else: ?>
    <div class="alert alert-light border rounded-4 text-center text-muted py-4">
      No ingredients added yet for this product.
    </div>
    <?php endif; ?>

    <?php else: ?>

    <!-- Overview when no product selected -->
    <?php
    $productsWithIng = array_filter($products, fn($p) => isset($ingCounts[(int)$p['p_id']]));
    $productsWithout = array_filter($products, fn($p) => !isset($ingCounts[(int)$p['p_id']]));
    ?>
    <div class="row g-2 mb-3">
      <div class="col-6">
        <div class="card border-0 shadow-sm rounded-3 text-center py-3">
          <div class="fs-3 fw-bold text-success"><?php echo count($productsWithIng); ?></div>
          <div class="small text-muted">Products mapped</div>
        </div>
      </div>
      <div class="col-6">
        <div class="card border-0 shadow-sm rounded-3 text-center py-3">
          <div class="fs-3 fw-bold text-warning"><?php echo count($productsWithout); ?></div>
          <div class="small text-muted">Not mapped yet</div>
        </div>
      </div>
    </div>

    <?php if (!empty($productsWithout)): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-3">
      <div class="card-header fw-semibold small" style="background:#fff3cd;">
        ⚠️ Products without ingredients
      </div>
      <div class="card-body p-0">
        <?php foreach (array_values($productsWithout) as $idx => $p): ?>
        <a href="ingredients.php?product_id=<?php echo $p['p_id']; ?>"
           class="d-flex justify-content-between align-items-center px-3 py-2 text-decoration-none text-dark border-bottom hover-bg">
          <span><?php echo htmlspecialchars($p['name']); ?></span>
          <span class="badge bg-warning text-dark">Add →</span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($productsWithIng)): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-3">
      <div class="card-header fw-semibold small" style="background:#d4edda;">
        ✅ Products with ingredients
      </div>
      <div class="card-body p-0">
        <?php foreach (array_values($productsWithIng) as $p): ?>
        <a href="ingredients.php?product_id=<?php echo $p['p_id']; ?>"
           class="d-flex justify-content-between align-items-center px-3 py-2 text-decoration-none text-dark border-bottom hover-bg">
          <span><?php echo htmlspecialchars($p['name']); ?></span>
          <span class="badge bg-success"><?php echo $ingCounts[(int)$p['p_id']]; ?> ingredients</span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>

  </div><!-- /container -->
</main>

<?php include_once("web_shopadmin_footer.php"); ?>

<style>
.hover-bg:hover { background: #fffbf0; }
.ing-autocomplete { position: relative; }
#ing_dropdown {
  display: none;
  position: absolute;
  top: 100%; left: 0; right: 0;
  z-index: 1050;
  background: #fff;
  border: 1px solid #ced4da;
  border-top: none;
  border-radius: 0 0 10px 10px;
  max-height: 240px;
  overflow-y: auto;
  box-shadow: 0 6px 18px rgba(0,0,0,0.12);
}
.ing-option {
  padding: 9px 14px;
  cursor: pointer;
  font-size: 0.93rem;
  border-bottom: 1px solid #f3f3f3;
}
.ing-option:hover, .ing-option.active { background: #d4edda; color: #155724; }
.ing-option mark { background: #b7e1b7; padding: 0; font-weight: 600; }
</style>

<script>
(function () {
  const ALL_OPTIONS = <?php echo json_encode($ingredientNames); ?>;
  const input    = document.getElementById('ingredient_name');
  const dropdown = document.getElementById('ing_dropdown');
  if (!input) return;
  let activeIdx = -1;

  function highlight(text, q) {
    if (!q) return document.createTextNode(text);
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
    const matches = q ? ALL_OPTIONS.filter(o => o.toLowerCase().includes(q.toLowerCase())) : ALL_OPTIONS;
    if (matches.length === 0) { dropdown.style.display = 'none'; return; }
    matches.slice(0, 50).forEach((m, i) => {
      const div = document.createElement('div');
      div.className = 'ing-option';
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
  input.addEventListener('keydown', e => {
    const items = dropdown.querySelectorAll('.ing-option');
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
