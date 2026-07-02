<?php
@$_BackgroundPhoto="";
include("dbstring.php");
$_SQL_Image=mysqli_query($con,"SELECT * FROM tblcompany WHERE backgroundphoto<>''");
if($rows=mysqli_fetch_array($_SQL_Image,MYSQLI_ASSOC)){
$_BackgroundPhoto=$rows["backgroundphoto"];
}
?>


