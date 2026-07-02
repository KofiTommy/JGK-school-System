<?php
session_start();
$_SESSION['Message']="";
include("check-login.php");
include("dbstring.php");
include("teacher-billing-utils.php");
ensure_teacher_billing_table($con);
teacher_billing_enforce_page_access($con);
$__teacherBillingUserId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
$__teacherBillingIsAdmin = teacher_billing_is_admin();
$__teacherBillingScopeClasses = teacher_billing_class_options($con);
$__teacherBillingScopeBatches = teacher_billing_batch_options($con);
$__teacherBillingHasScope = (count($__teacherBillingScopeClasses) > 0 && count($__teacherBillingScopeBatches) > 0);

?>


<?php
@$_TransId="";
//@$_ClassId=$_POST['classid'];
@$_UserId=$_POST['userid'];
@$_Class=$_POST['class'];
@$_Batch=$_POST['batch'];
@$_Term=$_POST['term'];
@$_Recordedby=$_SESSION['USERID'];
//echo $_SESSION['USERID'];

if(isset($_POST['bill_student'])){
teacher_billing_enforce_scope_or_redirect($con, trim((string)$_Class), trim((string)$_Batch), (int)$_Term);

foreach($_UserId as $selecteduser){
//Get individual student id
$_UserId=$selecteduser;
//Create payment container
include("code.php");
@$_Payment_Id=$code;
@$_Transaction_Code=$transaction_id;

@$_FullName="";
$_SQL_USER=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE userid='$selecteduser'");
if($rowfn=mysqli_fetch_array($_SQL_USER,MYSQLI_ASSOC)){
$_FullName=$rowfn["firstname"]." ".$rowfn["othernames"]." ".$rowfn["surname"]."(".$rowfn["userid"].")";
}

$_SQL_Payment=mysqli_query($con,"INSERT INTO tbltransaction(transactionid,userid,datetimepayment,recordedby,status)
	VALUES('$_Transaction_Code','$_UserId',NOW(),'$_SESSION[USERID]','active')");
if($_SQL_Payment){
$_TransId=$_Transaction_Code;
}

$_SQL_EXECUTE_2=mysqli_query($con,"SELECT * FROM tbltermregistry tr 
				INNER JOIN tblitemprice ip ON tr.class_entryid=ip.class_entryid AND tr.termname=ip.term
				INNER JOIN tblitem itm ON ip.itemid=itm.itemid
				INNER JOIN tblclassentry ce ON ce.class_entryid=tr.class_entryid
				WHERE tr.userid='$_UserId' AND ip.class_entryid='$_Class' AND ip.term='$_Term' AND ip.batch='$_Batch' AND ip.status='active' AND itm.status='active'");

while($row_b=mysqli_fetch_array($_SQL_EXECUTE_2,MYSQLI_ASSOC))
{
include("code.php");
@$_BillId=$code;

//Check if the bill is duplicated
$_SQL_CHECK=mysqli_query($con,"SELECT * FROM tblbilling bi INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid
INNER JOIN tblitem itm ON itm.itemid=ip.itemid
 WHERE bi.userid='$_UserId' AND bi.itempriceid='$row_b[itempriceid]'");
if(mysqli_num_rows($_SQL_CHECK)>0)
{
	if($row_item=mysqli_fetch_array($_SQL_CHECK,MYSQLI_ASSOC)){
	@$_ItemName=$row_item['itemname'];
	}
	
$_SESSION['Message']=$_SESSION['Message']."<div style='color:red;text-align:left;background-color:white;padding:5px;'><i class='fa fa-check' style='color:red'></i> $_ItemName already billed</div>";
}
else{
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblbilling(billid,userid,itempriceid,transactionid,cost,datetimebilled,recordedby,status,referenceid)
	VALUES('$_BillId','$_UserId','$row_b[itempriceid]','$_TransId','$row_b[price]',NOW(),'$_Recordedby','active','$_SESSION[SCHOOLACCOUNT]')");
if($_SQL_EXECUTE){

$_SQL_B=mysqli_query($con,"INSERT INTO accountingbookentries(accountId,cr,created,createdBy,dr,modifiedBy,narration,particulars,refAccountId,transactionId)
VALUES('$_SESSION[SCHOOLACCOUNT]','$row_b[price]',NOW(),'$_Recordedby',0,'','Bills','Bills','$_UserId','$_TransId')");
if($_SQL_B){}

	$_SQL_CHECK_1=mysqli_query($con,"SELECT * FROM tblbilling bi INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid
INNER JOIN tblitem itm ON itm.itemid=ip.itemid
 WHERE bi.userid='$_UserId' AND bi.itempriceid='$row_b[itempriceid]'");
if(mysqli_num_rows($_SQL_CHECK_1)>0)
{
	if($row_item_1=mysqli_fetch_array($_SQL_CHECK_1,MYSQLI_ASSOC)){
	@$_ItemName_1=$row_item_1['itemname'];
	}
	$_SESSION['Message']=$_SESSION['Message']."<div style='color:green;text-align:left;background-color:white;padding:5px;'><i class='fa fa-check' style='color:green'></i>  $_ItemName_1 Successfully Billed for $_FullName</div>";
	}

	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>$_ItemName_1 failed to bill,$_Error</div>";
	}
}
}
}
}
}
?>

<html>
<head>
<?php
include("links.php");
?>

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

<script type="text/javascript">
function selectAll(){
  var selall = document.getElementById("all").checked;
  if(selall==true){
    checkBox();
  }
  else if(selall==false){
    uncheckBox();
  }  
 }
 

 function uncheckBox(){
   var inputs = document.getElementsByName("userid[]");
    for(var i=0;i<inputs.length;i++){
     inputs[i].checked=false;
    }
     return false;
 }

  function checkBox(){
var inputs = document.getElementsByName("userid[]");
   
    for(var i=0;i<inputs.length;i++){
     inputs[i].checked=true;
    }
 return false;
 }
</script>
</head>
<body>
	<div class="header">
		<!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
	<?php
	include("menu.php");
	?>		
	</div>
<div class="main-platform" style="">
	<br/><br/>
	<form method="post" id="formID" name="formID" action="group-student-billing.php">

	<table width="100%">
	<caption>Group Students Billing </caption>
		<tr>

			<td valign="top" width="40%" align="center">
				
			<div class="form-entry" align="left">
<?php if(!$__teacherBillingIsAdmin && !$__teacherBillingHasScope){ ?>
<div style="margin-bottom:12px;color:#92400e;background:#fffbeb;border:1px solid #fcd34d;padding:10px;">
No billing class has been assigned yet. Ask admin to open <strong>Teacher Billing Assignment</strong> under Billing and assign your class, batch, and semester.
</div>
<?php } ?>
<form method="post" id="formID" name="formID" action="group-student-billing.php">
	<fieldset><legend>SEARCH STUDENTS</legend>
	<?php	
			$_ClassOptions = $__teacherBillingScopeClasses;
			echo "<select id='classentryid' name='classentryid' >";
			echo "<option value=''>Select Class</option>";
				foreach($_ClassOptions as $row){
					echo "<option value='".$row['class_entryid']."'>".$row['class_name']."</option>";
				}
				
			echo "</select><br/><br/>";
			?>		
				<?php	
			$_BatchOptions = $__teacherBillingScopeBatches;
			echo "<select id='batchid' name='batchid'>";
			echo "<option value=''>Select Batch</option>";
				foreach($_BatchOptions as $row){
					echo "<option value='".$row['batchid']."'>".$row['batch']."</option>";
				}
				
			echo "</select>";
			?><br/><br/>
			<button class="button-show" name="searchstudent"><i class="fa fa-search"></i> SEARCH STUDENT</button>
</fieldset><br/><br/>


			<?php	
			if(isset($_POST["searchstudent"])){
				@$_ClassentryID=$_POST["classentryid"];
				@$_BatchID=$_POST["batchid"];
				if(!$__teacherBillingIsAdmin && !teacher_billing_is_assigned_pair($con, $__teacherBillingUserId, trim((string)$_ClassentryID), trim((string)$_BatchID))){
					echo "<div style='color:red;text-align:center;background-color:white;padding:8px;'>You are not assigned billing access for that class and batch.</div>";
				}
				else{

				@$_Batch="";
				@$_ClassName="";
				$_SQL_CL=mysqli_query($con,"SELECT * FROM tblclassentry WHERE class_entryid='$_ClassentryID'");
				if($rowcl=mysqli_fetch_array($_SQL_CL)){
					$_ClassName=$rowcl["class_name"];
				}
				$_SQL_BH=mysqli_query($con,"SELECT * FROM tblbatch WHERE batchid='$_BatchID'");
				if($rowb=mysqli_fetch_array($_SQL_BH)){
					$_Batch=$rowb["batch"];
				}
		$_SQL_2=mysqli_query($con,"SELECT * FROM tblsystemuser su INNER JOIN tblclass cl
			ON su.userid=cl.userid
			 WHERE cl.class_entryid='$_ClassentryID' AND cl.batchid='$_BatchID' 
			 AND su.systemtype='Student' ORDER BY su.userid ASC");
			
			echo "<table>";
			echo "<caption>list of $_ClassName students, Batch:$_Batch </caption>";
			echo "<thead><th><input type='checkbox' id='all' name='all' Onclick='selectAll()' /></th><th>*</th><th>Index Number</th><th>Student</th></thead>";
			echo "<tbody>";
			@$serial=0;
			while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC))
			{
			echo "<tr>";
			echo "<td>";
			echo "<input type='checkbox' id='userid' name='userid[]' value='$row[userid]' />";
			echo "</td>";
			echo "<td>";
			echo $serial=$serial+1;
			echo "</td>";
			
			echo "<td>$row[userid]</td>";
			echo "<td>";
			echo $row['firstname']." ". $row['othernames']." ". $row['surname'];
			echo "</td>";
			echo "</tr>";
			}	
			echo "</tbody>";
			echo "</table>";
		}
		}
		?>
		</div>
		</td>

			<td valign="top" width="20%" align="center">
				
				<div class="form-entry" align="left">
			<?php	
			$_ClassOptions = $__teacherBillingScopeClasses;
			echo "<select id='class' name='class' >";
			echo "<option value=''>Select Class</option>";
				foreach($_ClassOptions as $row){
					echo "<option value='".$row['class_entryid']."'>".$row['class_name']."</option>";
				}
				
			echo "</select><br/><br/>";
			?>

			<?php	
			$_BatchOptions = $__teacherBillingScopeBatches;
			echo "<select id='batch' name='batch'>";
			echo "<option value=''>Select Batch</option>";
				foreach($_BatchOptions as $row){
					echo "<option value='".$row['batchid']."'>".$row['batch']."</option>";
				}
				
			echo "</select><br/><br/>";
			?>


			<select id="term" name="term" >
				<option value="" >Select Semester</option>
				<option value="1">1</option>
				<option value="2">2</option>
			
			</select><br/><br/>

			<div align="center"><button class="button-save" id="bill_student" name="bill_student"><i class="fa fa-plus"></i> BILL STUDENT</button></div>
		</form>
		<br/>
		</div>
			</td>
			<!--
			<td width="40%">
				<?php
				
				echo $_SESSION['Message'];
				

				@$_Overall_Total=0;
				$_SQL_EXECUTE_1=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.systemtype='Student' ORDER BY su.firstname");
				
				echo "<table style='background-color:white'>";
				//echo "<caption>STUDENTS BILLING</caption>";
				echo "<thead><th>Class</th><th>Item</th><th>Amount</th><th>Status</th><th>Billed Date/Time</th></thead>";
				echo "<tbody>";
				
				while($row_1=mysqli_fetch_array($_SQL_EXECUTE_1,MYSQLI_ASSOC)){
				$_UserID=$row_1['userid'];

				echo "<tr style='background-color:lightblue;border-bottom:1px solid orange;font-weight:bold'>";
				echo "<td colspan='5'>";
				echo strtoupper($row_1['firstname']." ".$row_1['othernames']." ".$row_1['surname']."(".$row_1['userid'].")");
				echo "</td>";
				echo "</tr>";

			$_SQL_CR=mysqli_query($con,"SELECT * FROM tblclass WHERE userid='$row_1[userid]'");
			while($row_cr=mysqli_fetch_array($_SQL_CR,MYSQLI_ASSOC)){
			//Get all the classes regisetred for the studenet
			$_Class_Reg_ID=$row_cr['class_entryid'];
			@$_Class_Inner="";

			$_SQL_CLASS=mysqli_query($con,"SELECT * FROM tblclassentry ce WHERE ce.class_entryid='$_Class_Reg_ID' ORDER BY ce.class_name ASC");
			while($row_class=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC))
			{
			////echo "<tr>";
			//echo "<td colspan='4' align='left' style='font-weight:bold'>";
			$_Class_Inner =strtoupper($row_class['class_name']);
			//echo "</td>";
			//echo "</tr>";

			$_SQL_TERM=mysqli_query($con,"SELECT * FROM tbltermregistry tr WHERE tr.class_entryid='$row_class[class_entryid]' AND tr.userid='$row_1[userid]' ORDER BY tr.termname ASC");
			while($row_tr=mysqli_fetch_array($_SQL_TERM,MYSQLI_ASSOC))
			{
			@$_Bactch_Inner="";
			$_SQL_BATCH=mysqli_query($con,"SELECT * FROM tblbatch bch WHERE bch.batchid='$row_tr[batchid]'");
			//echo "<form method='post' action='student-billing.php'>";

			echo "<tr>";
			echo "<td colspan='4' align='left' style='background-color:#fff;font-weight:bold'>";
			echo  $_Class_Inner ;
			echo "<input type='hidden' id='class_id' name='class_id' value='$row_class[class_entryid]' />";
			echo "<input type='hidden' id='term_id' name='term_id' value='$row_tr[termname]' />";
			echo "</td>";
			echo "</tr>";

			while($row_batch=mysqli_fetch_array($_SQL_BATCH,MYSQLI_ASSOC))
			{
			//echo "<tr>";
			////echo "<td colspan='4' align='left' style='font-weight:bold'>";
			$_Bactch_Inner = strtoupper($row_batch['batch']);
			//echo "</td>";
			//echo "</tr>";
			echo "<tr>";
			echo "<td colspan='5' style='background-color:#fff;font-weight:bold'>";
			echo "Semester: ".$row_tr['termname'];
			echo "</td>";
			echo "</tr>";

			echo "<tr>";

			echo "<td colspan='5' align='left' style='background-color:#ede;font-weight:bold'>";
			echo "Batch:".$_Bactch_Inner;
			//echo "<input type='hidden' id='class_id' name='class_id' value='$row_class[class_entryid]' />";
			//echo "<input type='hidden' id='term_id' name='term_id' value='$row_tr[termname]' />";
			echo "</td>";


			echo "</tr>";

			
				$_SQL_EXECUTE_2=mysqli_query($con,"SELECT * FROM tbltermregistry tr 
				INNER JOIN tblitemprice ip ON tr.class_entryid=ip.class_entryid AND tr.termname=ip.term
				INNER JOIN tblitem itm ON ip.itemid=itm.itemid
				INNER JOIN tblclassentry ce ON ce.class_entryid=tr.class_entryid
				INNER JOIN tblbatch b ON ip.batch=b.batchid
				WHERE tr.userid='$_UserID' AND ip.class_entryid='$row_class[class_entryid]' AND ip.term='$row_tr[termname]' AND ip.batch='$row_tr[batchid]' AND ip.status='active' AND itm.status='active' ");

				@$_Total_Amount=0;
				
					while($row_2=mysqli_fetch_array($_SQL_EXECUTE_2,MYSQLI_ASSOC))
					{
					echo "<tr>";
					//echo "<td align='center'>";
					//echo $row_2['class_name'];
					//echo "</td>";

					//echo "<td align='center'>";
					//echo $row_2['term'];
					//echo "</td>";

					echo "<td>";
					//echo $row_2['batch'];
					echo "</td>";

					echo "<td>";
					echo $row_2['itemname'];
					echo "</td>";


					echo "<td align='center'>";
					echo $row_2['price'];
					$_Total_Amount=$_Total_Amount+$row_2['price'];
					echo "</td>";

					echo "<td align='center'>";
					//echo $row_2['itempriceid'];
					$_SQL_BILL=mysqli_query($con,"SELECT * FROM tblbilling bi WHERE bi.userid='$_UserID' AND bi.itempriceid='$row_2[itempriceid]'");
					if(mysqli_num_rows($_SQL_BILL)>0)
					{
						echo " <i class='fa fa-check' style='color:green'></i>";
					}
					echo "</td>";

					echo "<td colspan='1' align='center'>";
					$_SQL_BILL_DATE=mysqli_query($con,"SELECT * FROM tblbilling bi WHERE bi.userid='$_UserID' AND bi.itempriceid='$row_2[itempriceid]'");
					if($row_bill=mysqli_fetch_array($_SQL_BILL_DATE,MYSQLI_ASSOC))
					{
					echo $row_bill['datetimebilled'];
					}
					echo "</td>";		
					echo "</tr>";
					}
				}
					echo "<tr style='background-color:#dee;font-size:18px;'>";
					echo "<td colspan='2' align='right'>";
					echo "TOTAL:";
					echo "</td>";
					echo "<td>";
					echo $_SESSION['SYMBOL']." ". $_Total_Amount;
					$_Overall_Total=$_Overall_Total+$_Total_Amount;
					echo "</td>";
					
					echo "<td colspan='2'>";
					echo "</td>";
					echo "</tr>";
				}
				echo "<tr style='background-color:#ece;font-size:18px;'>";
					echo "<td colspan='2' align='right'>";
					echo "OVERALL TOTAL:";
					echo "</td>";
					echo "<td>";
					echo $_SESSION['SYMBOL']." ". $_Overall_Total;
					echo "</td>";

					echo "<td colspan='2'>";
					echo "</td>";					
					echo "</tr>";
				}
			}
		}	
echo "</tbody>";
echo "</table>";

?>
</td>-->
</tr>
</table>
</div>
<br/><br/>
<button onclick="topFunction()" id="myBtn" title="Go to top">Top</button> 

 <script>
//Get the button
var mybutton = document.getElementById("myBtn");

// When the user scrolls down 20px from the top of the document, show the button
window.onscroll = function() {scrollFunction()};

function scrollFunction() {
  if (document.body.scrollTop > 50 || document.documentElement.scrollTop > 50) {
    mybutton.style.display = "block";
  } else {
    mybutton.style.display = "none";
  }
}

// When the user clicks on the button, scroll to the top of the document
function topFunction() {
  document.body.scrollTop = 0;
  document.documentElement.scrollTop = 0;
}
</script>

</body>
</html>
