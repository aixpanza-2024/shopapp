<?php
$shopidofses=$_SESSION['selectshop'];
$fetchsup = "select * from supplier where sh_id='$shopidofses' order by sup_id desc";
$fetchsupq = mysqli_query($conn, $fetchsup);
$fetchsupcount= mysqli_num_rows($fetchsupq);
