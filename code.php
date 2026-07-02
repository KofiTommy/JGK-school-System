<?php
@$_IN="";
include("dbstring.php");
$_SQL=mysqli_query($con,"SELECT initials FROM tblbranch WHERE branchid='$_SESSION[BRANCHID]'");
if($row=mysqli_fetch_array($_SQL,MYSQLI_ASSOC)){
	$_IN=$row['initials'];
}

@$code_2 = substr(str_shuffle("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"),0,10);
@$code =$_IN.date("Y").date("m").date("d").date("h").date("m").date("s");
$code = $code."_".$code_2;
@$transaction_id =$_IN.date("Y").date("m").date("d").date("h").date("m").date("s");

?>