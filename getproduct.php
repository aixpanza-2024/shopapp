<?php
include_once("db.php");
// Assuming $data is an array of items you want to populate the dropdown with
//$data = array("Atem 1", "Item 2", "Item 3", "Apple", "Orange");

// Filter data based on search term



if(isset($_GET['term'])) {
    $searchTerm = $_GET['term'];
     

  $searchpd="SELECT * FROM `products` where p_id='$searchTerm' or name LIKE '%$searchTerm%'";
  $searchpdresult=mysqli_query($conn,$searchpd);
  $itemcount=mysqli_num_rows($searchpdresult);

  $finaldata = array();
    if($itemcount!=0)
{
//if(!empty($searchTerm)) {
// $results = array_filter($row, function($item) use ($searchTerm) {
//     return strpos(strtolower($item), strtolower($searchTerm)) !== false;
      // });
       while($row=mysqli_fetch_array($searchpdresult)) {
       $finaldata[] = $row['p_id'].":".$row['name'];
       
     
      }

      echo json_encode($finaldata);
    //echo json_encode($results);
   // }
    //else
    //{

      //  echo json_encode($dataof);  
    //}

  }
  
}

// Return JSON response
//echo json_encode($results);
