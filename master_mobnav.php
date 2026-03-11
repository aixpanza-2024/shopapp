<div id="mySidenav" class="sidenav hamberger-bill">
  <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">&times;</a>
  <a href="../Layout/master_bill.php">Add Bill</a>
  <a href="../Layout/shopadmin.php">Add Products</a>
  <a href="../Layout/shopexpense.php">Add Expense</a>
  <a href="../Layout/shop_listexpense.php">List Expense</a>
  <a href="../Layout/daily_sales.php">Daily Sales</a>
  <a href="../Adminpanel/index.php">Customer</a>
  <a href="../Adminpanel/index.php">Expense</a>
  <a href="#">Profile</a>
  <?php if (isset($_SESSION['userpermission']) && $_SESSION['userpermission'] === 'Super Admin'): ?>
  <a href="../Adminpanel/index.php">Adminpanel</a>
  <?php endif; ?>
  <a href="../logout.php">Logout</a>
</div>
<span style="font-size:30px;cursor:pointer" class="hamberger-bill" onclick="openNav()">&#9776;</span>

<script>
function openNav() {
  document.getElementById("mySidenav").style.width = "250px";
}

function closeNav() {
  document.getElementById("mySidenav").style.width = "0";
}
</script>