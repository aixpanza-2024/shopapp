<?php
include("web_shopadmin_header.php");

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
// your DB connection file

  $expense_name = $_POST['expense_name'];
  $expense_amount = $_POST['expense_amount'];
  $expense_qty = $_POST['expense_qty'];
  $expense_category = $_POST['expense_category'];
  $expense_note = $_POST['expense_note'];
  $expense_date = date("Y-m-d"); // Automatically set current date

  $sql = "INSERT INTO shop_expenses (expense_name, expense_amount, expense_qty, expense_date, expense_category, expense_note) 
          VALUES ('$expense_name', '$expense_amount', '$expense_qty', '$expense_date', '$expense_category', '$expense_note')";
  
  if (mysqli_query($conn, $sql)) {
    echo "<script>alert('Expense added successfully!');</script>";
  } else {
    echo "<script>alert('Error adding expense: " . mysqli_error($conn) . "');</script>";
  }

  mysqli_close($conn);
}
?>

<main>

  <!-- Header -->
  <nav class="navbar navbar-expand-lg navbar-light" style="background-color: #b8860b !important;">
    <div class="container-fluid d-flex flex-wrap align-items-center justify-content-between">
      <div class="d-flex align-items-center">
        <img src="../images/logo.jpg" alt="Logo" class="img-fluid" style="max-height: 50px;">
        <h5 class="ms-3 text-white">Add Expense</h5>
      </div>

      <div class="d-flex align-items-center">
        <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#myModal">
          <i class="fa fa-language"></i>
        </button>
        <?php include_once("../master_mobnav.php"); ?>
      </div>
    </div>
  </nav>

  <!-- Expense Form -->
  <div class="container py-4">
    <div class="card shadow-lg border-0 rounded-4">
      <div class="card-header text-white" style="background-color:#b8860b;">
        <h5 class="mb-0">Add New Expense</h5>
      </div>

      <div class="card-body">
        <form method="POST" action="">
          <div class="row g-3">

            <!-- Expense Name with Datalist -->
           <!-- Expense Name with Datalist -->
<div class="col-md-6">
  <label for="expense_name" class="form-label">Expense Name</label>
  <input list="expenseOptions" class="form-control form-select-lg" id="expense_name" name="expense_name" required placeholder="Select or type">
  <datalist id="expenseOptions">
    <!-- General Items -->
    <option value="Milk">
    <option value="Tea Powder">
    <option value="Sugar">
    <option value="Gas Cylinder">
    <option value="Snacks Purchase">
    <option value="Cleaning Items">
    <option value="Staff Salary">
    <option value="Electricity Bill">
    <option value="Rent">
    <option value="Water">
    <option value="Soda">
    <option value="Ice Purchase">
    <option value="Bread">
    <option value="Bun">
    <option value="Paper Cups">
    <option value="Auto">
    <option value="Miscellaneous">

    <!-- Vegetables -->
    <option value="Tomato">
    <option value="Onion">
    <option value="Potato">
    <option value="Capsicum">
    <option value="Cabbage">
    <option value="Cauliflower">
    <option value="Carrot">
    <option value="Beans">
    <option value="Green Chilli">
    <option value="Coriander Leaves">
    <option value="Curry Leaves">
    <option value="Ginger">
    <option value="Garlic">
    <option value="Beetroot">
    <option value="Spinach">
    <option value="Drumstick">
    <option value="Ladies Finger">
    <option value="Cucumber">
    <option value="Bottle Gourd">
    <option value="Pumpkin">

    <!-- Fruits -->
    <option value="Pineapple">
    <option value="Banana">
    <option value="Apple">
    <option value="Orange">
    <option value="Watermelon">
    <option value="Papaya">
    <option value="Mango">
    <option value="Lemon">
    <option value="Grapes">
    <option value="Pomegranate">
    <option value="Coconut">
    <option value="Tender Coconut">
  </datalist>
  </div>

            <div class="col-md-3">
              <label for="expense_amount" class="form-label">Amount (₹)</label>
              <input type="number" class="form-control" id="expense_amount" name="expense_amount" required step="0.01" placeholder="0.00">
            </div>

            <div class="col-md-3">
              <label for="expense_qty" class="form-label">Quantity</label>
              <input type="number" class="form-control" id="expense_qty" name="expense_qty" required step="1" min="1" value="1">
            </div>

            <input type="hidden" name="expense_date" value="<?php echo date('Y-m-d'); ?>">

            <div class="col-md-6">
              <label for="expense_category" class="form-label">Category</label>
              <select class="form-select form-select-lg" id="expense_category" name="expense_category">
                <option value="">Select Category</option>
                <option value="Raw Materials">Raw Materials</option>
                <option value="Staff Salary">Staff Salary</option>
                <option value="Maintenance">Maintenance</option>
                <option value="Utilities">Utilities</option>
                <option value="Miscellaneous">Miscellaneous</option>
              </select>
            </div>

            <div class="col-12">
              <label for="expense_note" class="form-label">Notes (optional)</label>
              <textarea class="form-control" id="expense_note" name="expense_note" rows="3" placeholder="Add any remarks"></textarea>
            </div>

          </div>

          <div class="text-center mt-4">
            <button type="submit" class="btn btn-success px-5 fs-5">💾 Save Expense</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Language Modal -->
  <div class="modal fade" id="myModal" tabindex="-1" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">

        <div class="modal-header">
          <h5 class="modal-title" id="myModalLabel">Qalb Chai Language Settings</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div id="google_translate_element"></div> 
        </div>
      </div>
    </div>
  </div>

</main>

<?php include_once("web_shopadmin_footer.php"); ?>

<!-- Responsive Fixes -->
<style>
  @media (max-width: 576px) {
    .card-body form .col-md-6, 
    .card-body form .col-md-3, 
    .card-body form .col-12 {
      flex: 100%;
      max-width: 100%;
    }
  }

  input[list] {
    cursor: pointer;
  }

  .form-select-lg, .form-control {
    font-size: 1rem;
    padding: 0.7rem;
  }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
