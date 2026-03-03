<?php
$fetchshop = "select * from shops order by sh_id desc";
$fetchshopq = mysqli_query($conn, $fetchshop);
$fetchshopcount= mysqli_num_rows($fetchshopq);
