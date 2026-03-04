<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 315360000); // 10 years
    session_set_cookie_params(315360000);
    session_start();
}

include_once('db.php');
?>
      
      <!-- Product List -->
      <div id="product-list" style="padding-bottom: 60px;">
        <!-- Product Item -->
<?php

$inv = 0;
if(isset($_SESSION['invno']))
{

$inv = $_SESSION['invno'];
}

// Prepared statement
$cartquery = "SELECT * FROM daily_productsale WHERE `Inv no` = ?";
$cartresult = $conn->prepare($cartquery);

// Bind parameter
$cartresult->bind_param("i", $inv);

// Execute
$cartresult->execute();

// Get result
$resultfetch = $cartresult->get_result();

// Fetch staff list for current shop
$staffList = [];
$shopId = isset($_SESSION['selectshop']) ? intval($_SESSION['selectshop']) : 0;
$staffQuery = $conn->prepare("SELECT name FROM staff WHERE sh_id = ? AND status = 'Active' ORDER BY name ASC");
if ($staffQuery) {
    $staffQuery->bind_param("i", $shopId);
    $staffQuery->execute();
    $staffResult = $staffQuery->get_result();
    while ($s = $staffResult->fetch_assoc()) {
        $staffList[] = $s['name'];
    }
    $staffQuery->close();
}
?>



<?php
$grandTotal = 0;
$cartItems = [];
$data = ['items' => [], 'grandtotal' => 0];

// Pre-fetch all rows to compute total before rendering
$allRows = [];
while ($row = $resultfetch->fetch_assoc()) {
    $row['_subtotal'] = $row['quantity'] * $row['Selling Price'];
    $grandTotal += $row['_subtotal'];
    $cartItems[] = [
        'name'     => $row['product name'],
        'qty'      => $row['quantity'],
        'price'    => $row['Selling Price'],
        'subtotal' => $row['_subtotal']
    ];
    $allRows[] = $row;
}
$data = ['items' => $cartItems, 'grandtotal' => $grandTotal];

if (count($allRows) > 0) {
?>

<!-- Sticky total bar at top -->
<div class="listitems" style="position:sticky;top:0;z-index:10;background:#fff;border-bottom:2px solid #b8860b;padding:8px 10px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;">
  <span style="font-size:0.85rem;color:#666;">Invoice #<?php echo $inv; ?></span>
  <span style="font-weight:bold;font-size:1.1rem;color:#b8860b;">₹<?php echo number_format($grandTotal, 2); ?></span>
</div>

<?php
    foreach ($allRows as $cartproduct) {
        $prodid      = $cartproduct['dps_id'];
        $prodownid   = $cartproduct['p_id'];
        $prodname    = $cartproduct['product name'];
        $prodquantity= $cartproduct['quantity'];
        $prodprice   = $cartproduct['Selling Price'];
        $subtotal    = $cartproduct['_subtotal'];

        // Display or process each product
        // echo "Product: $prodname - Quantity: $prodquantity<br>";
        
   ?>

        
        <div class="product-item mb-3 border p-2 rounded listitems">
       <h6 class="mb-2 d-flex justify-content-between align-items-center">
    <?php echo $prodname; ?>
    <button class="btn btn-sm btn-danger ms-2 delete-product" data-id="<?php echo $prodid; ?>">Delete</button>
</h6>
          <!-- <div class="input-group">
            <div class="input-group-prepend">
              <button class="btn btn-outline-secondary qty-btn product-card" data-id="<?php // echo $prodownid; ?>" data-action="sub" type="button" onclick="changeQty(this, -1)">-</button>
            </div>
            <input type="text" class="form-control text-center qty-input" value="<?php // echo $prodquantity;?>" readonly>
            <div class="input-group-append">
              <button class="btn btn-outline-secondary qty-btn product-card" data-id="<?php //echo $prodownid; ?>" data-action="add" type="button">+</button>
            </div>
          </div> -->

<div class="quantity-control d-flex align-items-center">
    <button class="btn btn-light qty-btn product-card" style="height: 40px !important;"  data-id="<?php echo $prodownid; ?>" data-action="sub" type="button" onclick="changeQty(this, -1)">−</button>
    <input type="text" class="form-control qty-input text-center" value="<?php echo $prodquantity; ?>" readonly>
    <button class="btn btn-light qty-btn product-card" style="height: 40px !important;" data-id="<?php echo $prodownid; ?>" data-action="add" type="button" onclick="changeQty(this, 1)">+</button>
</div>


</div>
 <!-- for listing the payment -->
         <div class="product-item mb-3 border p-2 rounded listpayments">
  <div class="d-flex justify-content-between">
    <strong><?php echo $prodname; ?></strong>
    <span>₹<?php echo number_format($subtotal, 2); ?></span>
  </div>
  <div class="text-muted">
    Quantity: <?php echo $prodquantity; ?> × ₹<?php echo number_format($prodprice, 2); ?>
  </div>
</div>



 <!-- thermal billing starts here -->

<div class="billprintof">
<div id="bill-content" >
 

  <hr>

  
  <div class="product-line" style="margin-bottom: 6px;" style=" font-family: monospace; font-size: 12px; width: 240px; padding: 10px;"> 
    <div style="display: flex; justify-content: space-between;">
      <span><strong><?=$prodname ?></strong></span>
      <span>₹<?= number_format($subtotal, 2) ?></span>
    </div>
    <div style="font-size: 11px; color: #555;">
      Qty: <?= $prodquantity ?> × ₹<?= number_format($prodprice, 2) ?>
    </div>
  </div>

  <hr>

  
</div>
</div>


<!-- thermal billing ends here -->

       




<?php
    } // end foreach
?>

  <div class="border-top pt-3 mt-3 text-end listpayments">
  <h5>Grand Total: ₹<?php echo number_format($grandTotal, 2); ?></h5>
</div>

<div class="billoffooter">
  <div style="display: flex; justify-content: space-between; font-size: 13px;">
    <strong>Grand Total</strong>
    <strong>₹<?= number_format($grandTotal, 2) ?></strong>
  </div>

  <div style="text-align: center; margin-top: 10px; font-size: 11px;">
    Thank you! Visit again
  </div>

</div>
     


<?php


} else {
   ?>

<div style="
    display: flex;
    justify-content: center;
    align-items: center;
    height: 20vh;
    font-size: 20px;
    font-weight: bold;
    text-align: center;
">
    <?php echo "No Products Added"; ?>
</div>

<?php
}


?>
 <?php

if(isset($_SESSION['invno']))
{
?>


<div class="paymentbuttons">
<div class="proceed-btn-wrapper position-absolute w-100 d-flex  " style="bottom: 0; left: 0; padding: 10px; background: #fff; box-shadow: 0 -1px 5px rgba(0,0,0,0.1);">
  
    
     <button class="btn btn-danger w-50 me-2" onclick="newbill('debt')">Debt</button>
     <button class="btn btn-success w-50 me-2" data-bs-toggle="modal" data-bs-target="#paymentModal">Print</button>
</div>
</div>


 <div class="printbutton">
<div class="proceed-btn-wrapper position-absolute w-100 d-flex  " style="bottom: 0; left: 0; padding: 10px; background: #fff; box-shadow: 0 -1px 5px rgba(0,0,0,0.1);">
  
   
     <button class="btn btn-info w-50 me-2" onclick="listbackpayment()">Back</button>
  <!-- <button class="btn btn-success w-50" onclick="completepayment()">Print</button> -->
   <button class="btn btn-danger w-100 me-2" data-bs-toggle="modal" data-bs-target="#paymentModal">Print</button>
    </div>
</div>
<div class="newbill">

<div class="proceed-btn-wrapper position-absolute w-100 d-flex" style="bottom: 0; left: 0; padding: 10px; background: #fff; box-shadow: 0 -1px 5px rgba(0,0,0,0.1);">
  
   
 <button class="btn btn-danger w-100 me-2" onclick="newbill()">New Bill</button>
  
</div>
</div>


<!-- <div class="completepayment">
<div class="proceed-btn-wrapper position-absolute w-100 d-flex  " style="bottom: 0; left: 0; padding: 10px; background: #fff; box-shadow: 0 -1px 5px rgba(0,0,0,0.1);">
  
   
     <button class="btn btn-info w-50 me-2" onclick="listbackpayment()">Back</button>
 
    </div>
</div> -->

<?php
}
else
{
?>
<div class="proceed-btn-wrapper position-absolute w-100 d-flex" style="bottom: 0; left: 0; padding: 10px; background: #fff; box-shadow: 0 -1px 5px rgba(0,0,0,0.1);">
  
   
 <button disabled class="btn btn-danger w-50 me-2">Debt</button>
  <button disabled class="btn btn-success w-50">Pay</button>
</div>

<?php
}
?>



<!-- Payment Method Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header text-white" style="background:#b8860b;">
        <h5 class="modal-title" id="paymentModalTitle">Payment Method</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center py-3">

        <!-- View 1: Choose payment type -->
        <div id="payView1">
          <p class="mb-3">How is the customer paying?</p>
          <button class="btn btn-success btn-lg w-100 mb-2" onclick="newbill('normal','cash')" data-bs-dismiss="modal">
            💵 Cash
          </button>
          <button class="btn btn-primary btn-lg w-100 mb-2" onclick="newbill('normal','upi')" data-bs-dismiss="modal">
            📱 UPI
          </button>
          <button class="btn btn-warning btn-lg w-100 text-white" onclick="showStaffPicker()">
            👤 Staff
          </button>
        </div>

        <!-- View 2: Staff picker -->
        <div id="payView2" style="display:none;">
          <p class="mb-3">Select staff member:</p>
          <div class="d-grid gap-2">
            <?php foreach ($staffList as $sname): ?>
            <button class="btn btn-outline-dark btn-lg"
              onclick="newbill('normal','staff','<?php echo htmlspecialchars($sname, ENT_QUOTES); ?>')"
              data-bs-dismiss="modal">
              <?php echo htmlspecialchars($sname); ?>
            </button>
            <?php endforeach; ?>
            <?php if (empty($staffList)): ?>
            <p class="text-muted">No active staff found.</p>
            <?php endif; ?>
          </div>
          <button class="btn btn-link mt-3" onclick="showPayView1()">← Back</button>
        </div>

      </div>
    </div>
  </div>
</div>

<script>
function showStaffPicker() {
    document.getElementById('payView1').style.display = 'none';
    document.getElementById('payView2').style.display = 'block';
    document.getElementById('paymentModalTitle').textContent = 'Select Staff';
}
function showPayView1() {
    document.getElementById('payView2').style.display = 'none';
    document.getElementById('payView1').style.display = 'block';
    document.getElementById('paymentModalTitle').textContent = 'Payment Method';
}
// Reset to view1 whenever modal is opened
document.addEventListener('show.bs.modal', function(e) {
    if (e.target.id === 'paymentModal') showPayView1();
});

function newbill(type = 'normal', paymentMode = 'cash', staffName = '') {

    var ESC = '\x1B';
    var TXT_ALIGN_CT = ESC + '\x61' + '\x01'; // center align
    var TXT_BOLD_ON  = ESC + '\x45' + '\x01';
    var TXT_BOLD_OFF = ESC + '\x45' + '\x00';
    var TXT_SIZE_2   = ESC + '\x21' + '\x30'; // double height + width
    var TXT_SIZE_1   = ESC + '\x21' + '\x00'; // normal size

    // Build receipt header
    var receipt = '';
    receipt += TXT_ALIGN_CT + TXT_BOLD_ON + TXT_SIZE_2 + 'QALB CHAI\n';
    receipt += TXT_SIZE_1 + TXT_BOLD_OFF;
    receipt += 'Trivandrum, Kerala\n';
    receipt += 'Ph: 7012675369\n\n';

    let data = <?= json_encode($data) ?>;
    let items = data.items;
    let grandTotal = data.grandtotal;
    let now = new Date();
    let dateStr = now.toLocaleDateString();
    let timeStr = now.toLocaleTimeString();
    let invNo = "<?php echo $_SESSION['invno']; ?>";

    receipt += "------------------------------\n";
    receipt += leftRightText("RECEIPT", dateStr + " " + timeStr) + "\n";
    receipt += leftText("INV NO. " + invNo) + "\n";
    receipt += "------------------------------\n";
    receipt += leftRightText("Item", "Price") + "\n";
    receipt += "------------------------------\n";

    items.forEach(item => {
        receipt += leftRightText(item.name + " * " + item.qty, "Rs" + item.subtotal) + "\n";
        receipt += "------------------------------\n";
    });

    receipt += `Grand Total: Rs ${grandTotal}.00\n`;
    if (paymentMode === 'staff' && staffName) {
        receipt += `Payment: STAFF - ${staffName}\n`;
    } else {
        receipt += `Payment: ${paymentMode.toUpperCase()}\n`;
    }

    // 💰 Add balance info only for DEBT bills
    if (type === 'debt') {
        let balance = prompt("Enter balance amount (if any):", "0");
        if (!balance) balance = 0;

        receipt += `Paid: Rs 0.00\n`;
        receipt += `Balance Due: Rs ${balance}.00\n`;
        receipt += "==============================\n";
    }

    receipt += "AI-Powered Smart Billing &\n Web Applications \nwww.aixpanza.com || 9020203686";

    // Print via RawBT (temporarily disabled)
    // var rawbtURL = 'intent:' + encodeURIComponent(receipt) +
    //            '#Intent;scheme=rawbt;package=ru.a402d.rawbtprinter;end;';
    // window.location.href = rawbtURL;

    // 💾 Update DB (status + payment mode)
    let statusType = (type === 'debt') ? 'Debt' : 'Paid';
    let formData = new FormData();
    formData.append('status', statusType);
    formData.append('invno', invNo);
    formData.append('payment_mode', paymentMode);
    formData.append('staff_name', staffName);

    fetch('../updatebilling_status.php', {
        method: 'POST',
        body: formData
    }).then(response => response.text())
      .then(data => console.log('Status updated:', data))
      .catch(err => console.error(err));

    // Reset session after a short delay
    setTimeout(() => {
        fetch('../unset_session.php')
            .then(response => {
                if (response.ok) {
                    location.reload();
                } else {
                    alert("Error creating new bill. Please try again.");
                }
            });
    }, 2000);
}
function centerText(text, width = 32) {
    let space = Math.floor((width - text.length) / 2);
    return " ".repeat(space > 0 ? space : 0) + text;
}

function leftRightText(left, right, width = 32) {
    let spaces = width - left.length - right.length;
    return left + " ".repeat(spaces > 0 ? spaces : 1) + right;
}

function leftText(text, width = 32) {
    // Pad the end of the string with spaces until it reaches the desired width
    return text.padEnd(width, ' ');
}

// Align text fully to the right
function rightText(text, width = 32) {
    // Pad the start of the string with spaces until it reaches the desired width
    return text.padStart(width, ' ');
}
</script>
