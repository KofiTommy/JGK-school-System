<?php
session_start();
$_SESSION['Message']="";
?>
<?php
include("dbstring.php");
if(isset($_POST['pay_bill']))
{
	$_Amount_Paid=$_POST['payment'];
	$_User_ID=$_POST['userid'];
	$_ItemPriceId=$_POST['item-price-id'];
	$_Transaction_Id=$_POST['transactioncode'];

//Check if balance is zero
$_SQL_CHECK_BALANCE_1=mysqli_query($con,"SELECT * FROM tblbilling 
WHERE userid='$_User_ID' AND itempriceid='$_ItemPriceId'");	
if($row_1=mysqli_fetch_array($_SQL_CHECK_BALANCE_1,MYSQLI_ASSOC)){
@$_Bill_Payment=$row_1['payment']+$_Amount_Paid;
//echo "Bill P".$_Bill_Payment ."<br/>";
}
$_SQL_CHECK_BALANCE_2=mysqli_query($con,"SELECT * FROM tblitemprice 
WHERE itempriceid='$_ItemPriceId'");	
if($row_2=mysqli_fetch_array($_SQL_CHECK_BALANCE_2,MYSQLI_ASSOC)){
@$_Actual_Payment=$row_2['price'];
@$_ItemId=$row_2['itemid'];
//echo "Actual".$_Actual_Payment;
}
if($_Bill_Payment>$_Actual_Payment){
$_SQL_Item=mysqli_query($con,"SELECT * FROM tblitem WHERE itemid='$_ItemId'");
if($row_item=mysqli_fetch_array($_SQL_Item,MYSQLI_ASSOC)){
@$_ItemName=$row_item['itemname'];
}
$_SESSION['Message']=$_SESSION['Message']."<div style='color:red;background-color:white;padding:8px;' align='left'><i class='fa fa-times' style='color:red'></i> Student has finished paying for the $_ItemName or Amount entered is more than the balance</div>";
}
else
{
$_SQL_Item_2=mysqli_query($con,"SELECT * FROM tblitem WHERE itemid='$_ItemId'");
if($row_item_2=mysqli_fetch_array($_SQL_Item_2,MYSQLI_ASSOC)){
@$_ItemName_2=$row_item_2['itemname'];
}

	$_SQL_Bill_Pay=mysqli_query($con,"UPDATE tblbilling SET payment=payment+'$_Amount_Paid' 
	WHERE userid='$_User_ID' AND itempriceid='$_ItemPriceId' AND transactionid='$_Transaction_Id'");
	if($_SQL_Bill_Pay){
	$_SQL_Payment=mysqli_query($con,"UPDATE tblpayment SET payment=payment+'$_Amount_Paid' 
		WHERE userid='$_User_ID' AND transactionid='$_Transaction_Id'");	
	if($_SQL_Payment){
	$_SESSION['Message']="<div style='color:green;text-align:left;background-color:white;padding:8px;'><i class='fa fa-check' style='color:green'></i> Amount of $_Amount_Paid $_SESSION[CURRENCY] Successfully Paid for $_ItemName_2</div>";
	
	}
	else{
		$_Error_1=mysqli_error($con);
	$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>Failed to update bill record,$_Error_1</div>";
	
	}
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>Failed to pay,$_Error</div>";
	
	}
	}
}
?>
<?php
include("dbstring.php");
//@$_ClassId=$_POST['classid'];
@$_UserId=$_POST['userid'];
@$_Class=$_POST['class'];
@$_Batch=$_POST['batch'];
@$_Term=$_POST['term'];
@$_Recordedby=$_SESSION['USERID'];
//echo $_SESSION['USERID'];

if(isset($_POST['bill_student'])){
//Create payment container
include("code.php");
@$_Payment_Id=$code;
@$_Transaction_Code=$transaction_id;

$_SQL_Payment=mysqli_query($con,"INSERT INTO tblpayment(paymentid,userid,transactionid,payment,datetimepayment,recordedby,status)
	VALUES('$_Payment_Id','$_UserId','$_Transaction_Code',0,NOW(),'$_SESSION[USERID]','active')");
if($_SQL_Payment){
@$_TransId=$_Transaction_Code;
}

$_SQL_EXECUTE_2=mysqli_query($con,"SELECT * FROM tbltermregistry tr 
				INNER JOIN tblitemprice ip ON tr.class_entryid=ip.class_entryid AND tr.termname=ip.term
				INNER JOIN tblitem itm ON ip.itemid=itm.itemid
				INNER JOIN tblclassentry ce ON ce.class_entryid=tr.class_entryid
				WHERE tr.userid='$_UserId' AND ip.class_entryid='$_Class' AND ip.term='$_Term' AND ip.batch='$_Batch'");

while($row_b=mysqli_fetch_array($_SQL_EXECUTE_2,MYSQLI_ASSOC))
{
include("code.php");
@$_BillId=$code;

$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblbilling(billid,userid,itempriceid,transactionid,datetimebilled,recordedby,status)
	VALUES('$_BillId','$_UserId','$row_b[itempriceid]','$_TransId',NOW(),'$_Recordedby','active')");
if($_SQL_EXECUTE){
	$_SESSION['Message']=$_SESSION['Message']."<div style='color:green;text-align:center;background-color:white'>$_BillId Successfully Created</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>$_BillId failed to create,$_Error</div>";
	}
}
}
?>

<html>
<head>
<?php
include("links.php");
?>

<script type="text/javascript">
function SearchItem(str)
{
  if(str=="")
  {
  document.getElementById("search-result").innerHTML="";
  return;
  }
  else
  {
    if(window.XMLHttpRequest)
    {
      xmlhttp = new XMLHttpRequest();
    }
    else
    {
      xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
    }
    xmlhttp.onreadystatechange = function()
    {
      if(this.readyState==4 && this.status==200)
      {
        document.getElementById("search-result").innerHTML = this.responseText;
      }
    };
    xmlhttp.open("GET","display-student.php?search-item="+str,true);
    xmlhttp.send();
  }
}
</script>


<script>
  var rnd;
function getItemID()
{
rnd=Math.floor( Math.random()*100000000);
document.getElementById("item-id").value=rnd;
}
</script>

<script type="text/javascript">
var gbatch;
function getBatch()
{
gbatch=getElementById("batch").value;
 //return _batch;  
}
function getStudentBill(str)
{
	if(str=="")
  {
  
  document.getElementById("search-result").innerHTML="";
  return;
  }
  else
  {
    if(window.XMLHttpRequest)
    {
      xmlhttp = new XMLHttpRequest();
    }
    else
    {
      xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
    }
    xmlhttp.onreadystatechange = function()
    {
      if(this.readyState==4 && this.status==200)
      {
        document.getElementById("search-result").innerHTML = this.responseText;
      }
    };
    xmlhttp.open("GET","display-class-bill.php?search-bill="+str+"&batch="+gbatch,true);
    xmlhttp.send();
  }
}
</script>

<style type="text/css">
#search-item{
	text-align: center;
	background-color: white;
	border-bottom: 1px solid orange;
}
</style>

</head>

<body>

	<div class="header">
		<!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
	<?php
	include("menu.php");

	?>		
	<?php
	include("side-menu.php");

	?>
	</div>


<br/>
<br/>

<div class="main" style="">
	<br/><br/>
	<table width="100%">
		<tr>

			<td width="100%">
				<?php
				echo "<div style='padding:8px;color:green'>$_SESSION[Message]</div>";
				?>
				<?php
				@$_Overall_total_balance=0;
				@$_Overall_Total_Amount=0;
				@$_Overall_Total_Amount_Paid=0;

				include("dbstring.php");
				//if(isset($_GET['userid'])){
				echo "<table style='background-color:white'>";
				echo "<caption>PAYMENTS</caption>";
				echo "<thead><th>STUDENT</th><th>*</th><th>CLASS</th><th>TERM</th><th>BATCH</th><th>ITEM</th><th>AMOUNT</th><th>PAID</th><th>BALANCE</th><th colspan='1'>ACTION</th></thead>";
				echo "<tbody>";
				

				$_SQL_EXECUTE_1=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.systemtype<>'super_user' ORDER BY su.firstname");
				while($row_1=mysqli_fetch_array($_SQL_EXECUTE_1,MYSQLI_ASSOC))
				{
				$_UserID=$row_1['userid'];
				@$_Total_Balance=0;
				@$_Total_Amount_Paid=0;
				@$_Total_Amount=0;

				$_SQL_EXECUTE_3=mysqli_query($con,"SELECT * FROM tblbilling bi INNER JOIN tblsystemuser su 
					ON bi.userid=su.userid INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid
					INNER JOIN tblitem itm ON ip.itemid=itm.itemid
					INNER JOIN tblclassentry ce ON ip.class_entryid=ce.class_entryid
					INNER JOIN tblbatch b ON ip.batch=b.batchid
				 WHERE bi.userid='$_UserID'");
				$_SQL_EXECUTE_3_r=$_SQL_EXECUTE_3;

				@$serial=0;

				if($row_p_r=mysqli_fetch_array($_SQL_EXECUTE_3_r,MYSQLI_ASSOC)){
				echo "<tr>";
				echo "<td colspan='12' style='background-color:#eed;border-bottom:1px solid orange;color:blue;font-weight:bold'>";
				echo $row_p_r['firstname']." ".$row_p_r['othernames']." ".$row_p_r['surname']." (". $row_p_r['userid'].")";
				echo "</td>";
				echo "</tr>";
				}

				while($row_p=mysqli_fetch_array($_SQL_EXECUTE_3,MYSQLI_ASSOC)){
				echo "<form id='formID' name='formID' method='post'>";
				
				echo "<tr>";
				echo "<td align='center'>";
				echo "<input type='hidden' id='userid' name='userid' value='$row_p[userid]' />";
				echo "</td>";

				echo "<td align='center'>";
				echo $serial=$serial+1;
				echo "</td>";

				echo "<td align='center'>";
				echo $row_p['class_name'];
				echo "</td>";

				echo "<td align='center'>";
				echo $row_p['term'];
				echo "</td>";

				echo "<td align='center'>";
				echo $row_p['batch'];
				echo "</td>";

				echo "<td>";
				echo "<input type='hidden' id='item-price-id' name='item-price-id' value='$row_p[itempriceid]' />";
				echo "<input type='hidden' id='transactioncode' name='transactioncode' value='$row_p[transactionid]' />";
				echo $row_p['itemname'];
				echo "</td>";

				echo "<td align='center'>";
				echo $row_p['price'];
				$_Total_Amount=$_Total_Amount+$row_p['price'];
				echo "</td>";

				echo "<td align='center'>";
				echo $row_p['payment'];
				$_Total_Amount_Paid=$_Total_Amount_Paid+$row_p['payment'];
				echo "</td>";

				echo "<td align='center'>";
				//echo $row_p['payment'];
				@$_Balance=$row_p['price']-$row_p['payment'];
				$_Total_Balance=$_Total_Balance+$_Balance;
				
				echo $_Balance;
				echo "</td>";


				//echo "<td align='center'>";
				//echo "<input type='text' style='text-align:center' id='payment' name='payment' class='validate[required,custom[number]]' placeholder='Enter Amount'/>";
				//echo "</td>";

				//echo "<td align='center'>";
				//echo "<button class='button-pay'  id='pay_bill' name='pay_bill'><i class='fa fa-plus' style='color:white'></i> Pay</button>";
				//echo "</td>";

				echo "<td align='center'>";
				echo "<button class='button-pay'  id='print_bill' name='print_bill'><i class='fa fa-print' style='color:white'></i> Print</button>";
				echo "</td>";


				echo "</tr>";
				echo "</form>";
				}
				echo "<tr style='background-color:#eeb;color:blue;font-weight:bold'>";
				
				echo "<td colspan='6' align='right'>";
				echo "TOTAL:";
				echo "</td>";
				echo "<td colspan='1' align='center'>";
				echo $_SESSION['SYMBOL']." ". $_Total_Amount;
				$_Overall_Total_Amount=$_Overall_Total_Amount+$_Total_Amount;
				echo "</td>";

				echo "<td colspan='1' align='center'>";
				echo $_SESSION['SYMBOL']." ". $_Total_Amount_Paid;
				$_Overall_Total_Amount_Paid=$_Overall_Total_Amount_Paid+$_Total_Amount_Paid;
				echo "</td>";

				echo "<td colspan='1' align='center'>";
				echo $_SESSION['SYMBOL']." ". $_Total_Balance;
				$_Overall_total_balance=$_Overall_total_balance + $_Total_Balance;
				echo "</td>";

				echo "<td align='center'>";
				echo "<button class='button-pay'  id='print_all_bill' name='print_all_bill'><i class='fa fa-print' style='color:white'></i> Print All</button>";
				echo "</td>";

				
				
				echo "</tr>";
			}
			echo "<tr style='background-color:#dff;font-weight:bold;color:darkgreen'>";
			echo "<td colspan='4'>";
			echo "</td>";
			echo "<td>";

			echo "<td colspan='1' align='right'>";
			echo "GRAND TOTAL:";
			echo "</td>";
			echo "<td align='center'>";
			echo $_SESSION['SYMBOL']." ". $_Overall_Total_Amount;
			echo "</td>";

			echo "<td align='center'>";
			echo $_SESSION['SYMBOL']." ". $_Overall_Total_Amount_Paid;
			echo "</td>";

			echo "<td align='center'>";
			echo $_SESSION['SYMBOL']." ". $_Overall_total_balance;
			echo "</td>";
			echo "</tr>";
			
			echo "</tbody>";
			echo "</table>";
				
			//}
				?>

				
			</td>
		</tr>

	</table>
		<!--Login-->
		
		<br/><br/><br/>

</div>

<!--Footer-->

</body>
</html>