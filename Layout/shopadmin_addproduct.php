<?php
include("web_shopadmin_header.php");

$success = '';
$error   = '';

if (isset($_POST['addproduct'])) {
    $name          = mysqli_real_escape_string($conn, trim($_POST['name']));
    $categorie     = intval($_POST['categorie']);
    $purchaseprice = floatval($_POST['purchaseprice']);
    $saleprice     = floatval($_POST['saleprice']);
    $date          = date("d/m/Y");
    $shopid        = isset($_SESSION['selectshop']) ? intval($_SESSION['selectshop']) : 1;

    if ($name === '' || $categorie === 0) {
        $error = "Product name and category are required.";
    } else {
        $sql = "INSERT INTO products (categorie, name, purchaseprice, saleprice, status, shopid, date)
                VALUES ('$categorie', '$name', '$purchaseprice', '$saleprice', 'Active', '$shopid', '$date')";
        if (mysqli_query($conn, $sql)) {
            $success = "Product added successfully!";
        } else {
            $error = "Failed to add product. Please try again.";
        }
    }
}

// Fetch categories for dropdown
$catfetch = mysqli_query($conn, "SELECT * FROM categorie ORDER BY categorie ASC");
?>

<main>

<!-- Header / Navbar -->
<div class="row headercolor">
  <nav class="navbar navbar-expand-lg navbar-light" style="background-color: #b8860b !important;">
    <div class="container-fluid">
      <div class="d-flex w-100 align-items-center">
        <div class="d-flex me-3">
          <img src="../images/logo.jpg" alt="Logo" class="img-fluid" style="max-height: 50px;">
        </div>
        <div class="d-flex align-items-center ms-auto">
          <a href="shopadmin.php" class="btn btn-outline-light btn-sm me-2">
            <i class="fa fa-arrow-left"></i> Back
          </a>
          <?php include_once("../master_mobnav.php"); ?>
        </div>
      </div>
    </div>
  </nav>
</div>

<!-- Add Product Form -->
<div class="container-fluid mt-3">
  <div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-7 col-lg-5">

      <div class="card shadow-sm">
        <div class="card-header" style="background-color:#b8860b; color:#fff;">
          <h5 class="mb-0"><i class="fa fa-plus me-2"></i>Add New Product</h5>
        </div>
        <div class="card-body">

          <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
              <?php echo $success; ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php endif; ?>
          <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
              <?php echo $error; ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php endif; ?>

          <form method="POST">

            <div class="mb-3">
              <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
              <select class="form-select" name="categorie" required>
                <option value="">-- Select Category --</option>
                <?php
                if ($catfetch && mysqli_num_rows($catfetch) > 0) {
                    while ($cat = mysqli_fetch_assoc($catfetch)) {
                        $selected = (isset($_POST['categorie']) && $_POST['categorie'] == $cat['cat_id']) ? 'selected' : '';
                        echo "<option value='{$cat['cat_id']}' $selected>" . htmlspecialchars($cat['categorie']) . "</option>";
                    }
                }
                ?>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Product Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="name"
                     placeholder="Enter product name"
                     value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                     required>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Purchase / Make Price</label>
              <input type="number" step="0.01" class="form-control" name="purchaseprice"
                     placeholder="0.00"
                     value="<?php echo isset($_POST['purchaseprice']) ? htmlspecialchars($_POST['purchaseprice']) : ''; ?>">
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Sale Price</label>
              <input type="number" step="0.01" class="form-control" name="saleprice"
                     placeholder="0.00"
                     value="<?php echo isset($_POST['saleprice']) ? htmlspecialchars($_POST['saleprice']) : ''; ?>">
            </div>

            <div class="d-flex gap-2">
              <button type="submit" name="addproduct" class="btn btn-warning fw-semibold flex-grow-1">
                <i class="fa fa-check me-1"></i> Add Product
              </button>
              <a href="shopadmin.php" class="btn btn-outline-secondary">Cancel</a>
            </div>

          </form>
        </div>
      </div>

    </div>
  </div>
</div>

</main>

<?php include_once("web_shopadmin_footer.php"); ?>
