<form method="post">
                                              <select class="form-control" name="shopid" id="exampleFormControlSelect1" required>
                                                <option value="">--Search Shop--</option>

                                               <?php
                                               
                                               include("shopapi.php");
                                                 
if($fetchshopcount> 0) {
    
while($fetchshop = mysqli_fetch_array($fetchshopq)) {
    ?>
                                                <option value="<?php echo $fetchshop['sh_id']?>"><?php echo $fetchshop['name']?></option>

                                                <?php
}
}
                                                ?>
                                            </select>
                                        
                                        </div>
                                        <div class="col-md-4">
                                            <button type="submit" class="btn btn-primary" name="search"><i class="fa fa-search" aria-hidden="true"></i> Search</button>


</form>                       