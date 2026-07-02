<?php
@$_SESSION["Message"]="";

$tables=array("tblcontact","tbltransferpayment","tblreturnpayment",
"tblreturnitem","tblproducttransfer","tblpayclient","tblmemo","tbllogs","tblitemdamaged",
"tblgroupcode","tblexpenses","tbldocument","tbldepositslip","tblcustomerawards",
"tblcustomerreferral","tblcashdeposit","tblbalance","tbldailygrandtotal","tblpos",
"tblorderpayment","tblclient","tblstockitem","tblitem","tblcategory","tblstaff","tblsubbranch",
"tblsubcompany","tblbranch","tblcompany");

for($i=0;$i<count($tables);$i++){
	deleteFromGlobalTables($tables[$i]);
}

function deleteFromGlobalTables($table_name){
include("dbstringglobal.php");

$sql="DELETE FROM $table_name";
$result=mysqli_query($_GlobalCon,$sql);

if($result){
	$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>Table, $table_name successfully deleted</div>";
}
else{
	$_Error=mysqli_error($_GlobalCon);
	$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>Table, $table_name failed to delete, Error:$_Error</div>";
}

}

echo $_SESSION["Message"];
?>