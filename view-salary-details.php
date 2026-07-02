<?php
session_start();
$_SESSION['Message']="";
?>
<?php
include("code.php");
@$_SalaryDetailId=$code;
//@$_TeacherId=$_POST['userid'];
@$_UserId=$_POST['userid'];
@$_Gross_Salary=$_POST['grosssalary'];
@$_Allowance=$_POST['allowance'];
@$_TotalDeduction=$_POST['totaldeduction'];
@$_NetPay=$_POST['netpay'];
@$_Recordedby=$_SESSION['USERID'];

include("dbstring.php");

if(isset($_POST['register_salary_details']))
{
	$_SQL_EXECUTE_3=mysqli_query($con,"SELECT * FROM tblsalarydetails WHERE userid='$_UserId'");
	if(mysqli_num_rows($_SQL_EXECUTE_3)>0)
	{
	$_SESSION['Message']=$_SESSION['Message']."<div style='color:red;padding:10px;background-color:white'><i class='fa fa-times' style='color:red'></i> $_UserId already saved</div>";

	}else{
//echo $_SESSION['USERID'];
	$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblsalarydetails(salarydetailid,userid,grosssalary,allowance,totaldeduction,netpay,datetimeentry,status,recordedby)
		VALUES('$_SalaryDetailId','$_UserId','$_Gross_Salary','$_Allowance','$_TotalDeduction','$_NetPay',NOW(),'active','$_Recordedby')");
	if($_SQL_EXECUTE){
		$_SESSION['Message']="<div style='color:green;text-align:left;background-color:white'><i class='fa fa-check' style='color:green'></i>Salary Details Successfully Saved</div>";
		}
		else{
			$_Error=mysqli_error($con);
			$_SESSION['Message']="<div style='color:red'><i class='fa fa-times' style='color:red'></i> Salary Details failed to save,$_Error</div>";
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
function getTotalDeduction()
{
 var gs=document.getElementById("grosssalary").value;
 var fa=document.getElementById("allowance").value;
 var td=document.getElementById("total_deduction").value;
 var ctd =((gs*td)/100);
 var np=((+gs)-(+ctd))+(+fa);
 document.getElementById("totaldeduction").value=ctd;
 document.getElementById("netpay").value=np;
}
</script>

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

<br/>
<br/>
<br/>
<br/>
<div class="main" style="">

	<table width="100%">
		<tr>

			<td width="100%">
				<?php
				echo $_SESSION['Message'];

				include("dbstring.php");
								//Registered clients
				echo "<table width='100%' style='background-color:white'>";
				echo "<caption>SALARY DETAILS</caption>";
				echo "<thead><th colspan=1>Task</th><th>Staff</th><th>Gross Salary</th><th>Allowance</th><th>Total Deduction</th><th>Net Pay</th></thead>";
				echo "<tbody>";
				

				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM  tblsystemuser su
				INNER JOIN tblsalarydetails sd ON su.userid=sd.userid 
				WHERE su.systemtype='Teacher' OR su.systemtype='User'");


				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr>";
				echo "<td align='center'><a title='View $row[firstname] ($row[userid])' href='salary-details.php?view_user=$row[userid]'><i class='fa fa-book' style='color:blue'></i></a></td>";
				
				echo "<td>$row[firstname] $row[othernames] $row[surname] ($row[userid])</td>";
				echo "<td align='center'>";
				echo $row['grosssalary'];
				echo "</td>";

				echo "<td align='center'>";
				echo $row['allowance'];
				echo "</td>";

				echo "<td align='center'>";
				echo $row['totaldeduction'];
				echo "</td>";

				echo "<td align='center'>";
				echo $row['netpay'];
				echo "</td>";
				
				echo "</tr>";
				}
				echo "</tbody>";
				echo "</table>";
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