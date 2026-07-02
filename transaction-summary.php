<?php
include("dbstring.php");
if($_SESSION['ACCESSLEVEL']=="user" && $_SESSION['SYSTEMTYPE']=="Student")
{

	$_today=date("Y");
	echo "<h4>TOTAL BILLS AS AT $_today</h4>";
	$_SQL_EXECUTE=mysqli_query($con,"SELECT SUM(ip.price) AS TOTAL_BILLS FROM tblbilling bi 
	INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid WHERE bi.userid='$_SESSION[USERID]'");
	@$_Total_Bill=0;
	if($_Row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
	$_Total_Bill=$_Row['TOTAL_BILLS'];
	}
	if(mysqli_num_rows($_SQL_EXECUTE)>=0){
	echo "<div style='border-bottom:1px solid lightblue;padding:10px;'>TOTAL BILLS: $_SESSION[SYMBOL] ".  $_Total_Bill ."</div>";	
	}

	echo "<br/><br/><h4>TOTAL PAYMENTS AS AT $_today</h4>";
	$_SQL_EXECUTE=mysqli_query($con,"SELECT SUM(payment) AS TOTAL_PAYMENT FROM tblpayment pm WHERE pm.userid='$_SESSION[USERID]'");
	@$_Total_payment=0;
	if($_Row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
	$_Total_payment=$_Row['TOTAL_PAYMENT'];
	}
	if(mysqli_num_rows($_SQL_EXECUTE)>=0){
	echo "<div style='border-bottom:1px solid lightblue;padding:10px;'>TOTAL PAYMENTS: $_SESSION[SYMBOL] ". $_Total_payment ."</div>";	
	}

	echo "<br/><br/><h4>TOTAL ARREARS AS AT $_today</h4>";
	@$_Total_Arrears=$_Total_Bill-$_Total_payment;
	if($_Total_Arrears>0)
	{
	echo "<div style='border-bottom:1px solid lightblue;padding:10px;background-color:#fee;color:red'>TOTAL ARREARS: $_SESSION[SYMBOL] ". $_Total_Arrears ."</div>";	
	}
	else{
	echo "<div style='border-bottom:1px solid lightblue;padding:10px;'>TOTAL ARREARS: $_SESSION[SYMBOL] ". $_Total_Arrears ."</div>";	

	}
}
else if($_SESSION['ACCESSLEVEL']=="administrator")
{
$_today=date("Y");
	echo "<h4>TOTAL BILLS AS AT $_today</h4>";
	$_SQL_EXECUTE=mysqli_query($con,"SELECT SUM(ip.price) AS TOTAL_BILLS FROM tblbilling bi 
	INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid");
	@$_Total_Bill=0;
	if($_Row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
	$_Total_Bill=$_Row['TOTAL_BILLS'];
	}
	if(mysqli_num_rows($_SQL_EXECUTE)>=0){
	echo "<div style='border-bottom:1px solid lightblue;padding:10px;'>TOTAL BILLS: $_SESSION[SYMBOL] ".  $_Total_Bill ."</div>";	
	}

	echo "<br/><br/><h4>TOTAL PAYMENTS AS AT $_today</h4>";
	$_SQL_EXECUTE=mysqli_query($con,"SELECT SUM(payment) AS TOTAL_PAYMENT FROM tblpayment pm");
	@$_Total_payment=0;
	if($_Row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
	$_Total_payment=$_Row['TOTAL_PAYMENT'];
	}
	if(mysqli_num_rows($_SQL_EXECUTE)>=0){
	echo "<div style='border-bottom:1px solid lightblue;padding:10px;'>TOTAL PAYMENTS: $_SESSION[SYMBOL] ". $_Total_payment ."</div>";	
	}

	echo "<br/><br/><h4>TOTAL ARREARS AS AT $_today</h4>";
	@$_Total_Arrears=$_Total_Bill-$_Total_payment;
	if($_Total_Arrears>0)
	{
	echo "<div style='border-bottom:1px solid lightblue;padding:10px;background-color:#fee;color:red'>TOTAL ARREARS: $_SESSION[SYMBOL] ". $_Total_Arrears ."</div>";	
	}
	else{
	echo "<div style='border-bottom:1px solid lightblue;padding:10px;'>TOTAL ARREARS: $_SESSION[SYMBOL] ". $_Total_Arrears ."</div>";	

	}

}
?>