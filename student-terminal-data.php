<?php
session_start();
$_SESSION['Message']="";
include("check-login.php");
include("dbstring.php");
include("class-teacher-utils.php");
ensure_class_teacher_table($con);
ensure_student_terminal_term_column($con);

$isAdminRole = class_teacher_is_admin();
$isTeacherRole = class_teacher_is_teacher();
$isTeacherWithClassRole = ($isTeacherRole && class_teacher_has_any_assignment($con, $_SESSION['USERID']));
if(!$isAdminRole && !$isTeacherRole){
    header("location:".class_teacher_landing_page());
    exit();
}
if($isTeacherRole && !$isTeacherWithClassRole){
    header("location:".class_teacher_landing_page());
    exit();
}
?>

<?php
@$_ClassId=$_POST['classid'];
@$_terminalid=$_POST['terminalid'];
@$_UserId=$_POST['userid'];
@$_Term=$_POST['term'];
@$_BatchId=$_POST['batchid'];
@$_Roll=$_POST['roll'];
@$_Attendance=$_POST['attendance'];
@$_TotalAttendance=$_POST['totalattendance'];
@$_PromotedTo=$_POST['promotedto'];
@$_Conduct=$_POST['conduct'];
@$_Interest=$_POST['interest'];
@$_ClassTeacherRemark=$_POST['class_teacher_remark'];
@$_HeadTeacherRemark=$_POST['head_teacher_remark'];
@$_Recordedby=$_SESSION['USERID'];

$canManageCurrent = true;
if($isTeacherRole){
    $canManageCurrent = class_teacher_is_assigned($con, $_SESSION['USERID'], $_ClassId, $_BatchId, (int)$_Term);
}

if(isset($_POST['register_student_terminal']))
{
if(!$canManageCurrent){
    $_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>You are not assigned as class teacher for this class, semester and batch.</div>";
}else{
    $_SQL_CHECK=mysqli_query($con,"SELECT * FROM tblstudentterminalreport WHERE (userid='$_UserId' AND batchid='$_BatchId' AND termname='$_Term')");
    if(mysqli_num_rows($_SQL_CHECK)>0)
    {
    	$_SQL_UPDATE=mysqli_query($con,"UPDATE tblstudentterminalreport SET roll='$_Roll',
    		attendance='$_Attendance',totalattendance='$_TotalAttendance',promotedto='$_PromotedTo',
    		conduct='$_Conduct',interest='$_Interest',class_teacher_remark='$_ClassTeacherRemark',
    		head_teacher_remark='$_HeadTeacherRemark',recordedby='$_Recordedby' 
    		WHERE batchid='$_BatchId' AND userid='$_UserId' AND termname='$_Term'");
    	if($_SQL_UPDATE){
    		$_SESSION['Message']="<div style='color:red'>Student Terminal Data Successfully Updated</div>";	

    		}
    //$_SESSION['Message']="<div style='color:red'>Student has already registered for Term $_Term or Batch: $_BatchName already created</div>";	
    }
    else{
    $_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblstudentterminalreport(terminalid,userid,batchid,termname,
    	roll,attendance,totalattendance,promotedto,conduct,interest,class_teacher_remark,
    	head_teacher_remark,recordedby,status,datetimeentry)
    	VALUES('$_terminalid','$_UserId','$_BatchId','$_Term','$_Roll','$_Attendance','$_TotalAttendance','$_PromotedTo','$_Conduct','$_Interest','$_ClassTeacherRemark','$_HeadTeacherRemark','$_Recordedby','active',NOW())");
    	if($_SQL_EXECUTE){
    	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Student Terminal Data Successfully Saved</div>";
    	}
    	else{
    		$_Error=mysqli_error($con);
    		$_SESSION['Message']="<div style='color:red'>Student Terminal Data failed to save,$_Error</div>";
    	}
    }
}
}
?>

<?php
if(isset($_GET["delete_term"]))
{
if(!$isAdminRole){
    $_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>Only administrators can delete remark records.</div>";
}else{
    $_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblstudentterminalreport WHERE terminalid='$_GET[delete_term]'");
    	if($_SQL_EXECUTE){
    	$_SESSION['Message']="<div style='color:maroon;text-align:center;background-color:white'>Term Successfully Deleted</div>";
    	}
    	else{
    		$_Error=mysqli_error($con);
    		$_SESSION['Message']="<div style='color:red;text-align:center'>Term failed to delete,Error:$_Error</div>";
    	}
}
}
?>

<html>
<head>
<?php
include("links.php");
?>
<link rel="stylesheet" href="css/student-terminal-data.css">

</head>
<body>

	<div class="header">
		<!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
	<?php
	include("menu.php");

	?>		
	<?php
	//include("side-menu.php");

	?>
	</div>
<div class="main-platform std-page">
	<section class="std-hero">
		<div>
			<span class="std-kicker">Terminal Records</span>
			<h1>Student Semester Data</h1>
			<p>Capture attendance, conduct, remarks, and promotion details for terminal reports.</p>
		</div>
		<div class="std-hero-card">
			<i class="fa fa-address-card-o"></i>
			<span>Semester Data Entry</span>
		</div>
	</section>

	<div class="std-layout">
		<aside class="std-panel std-form-panel">
			<div class="std-panel-heading">
				<span class="std-icon"><i class="fa fa-pencil-square-o"></i></span>
				<div>
					<h2>Data Entry</h2>
					<p>Select a student from the list, then complete the report details here.</p>
				</div>
			</div>
		
			<form method="post" id="formID" name="formID" action="student-terminal-data.php">

			<label>Semester Id</label>
			<input type="text" id="terminalid" name="terminalid" value="<?php include("code.php");echo $code;?>" class="validate[required]" readonly/>

			<fieldset class="std-fieldset"><legend>Selected Student</legend>
			<?php
			if(isset($_GET['view_user'])){
				@$Class_ID=$_GET['class_id'];
				@$_Batch_ID=$_GET['batch_id'];
				@$_Term_Name=$_GET['term_name'];
                $allowView = true;
                if($isTeacherRole){
                    $allowView = class_teacher_is_assigned($con, $_SESSION['USERID'], $Class_ID, $_Batch_ID, (int)$_Term_Name);
                }
                if(!$allowView){
                    echo "<div class='std-alert std-alert-danger'>You are not assigned to this class, semester and batch.</div>";
                }else{

			@$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE userid='$_GET[view_user]'");
			
			if($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
			$_FullName=$row['firstname']." ".$row['surname']." ".$row['othernames']."(".$row['userid'].")";
			echo "<input type='text' id='firstname' name='firstname' value='$_FullName' class='validate[required]' readonly/>";
			echo "<input type='hidden' id='userid' name='userid' value='$row[userid]' class='validate[required]' readonly/>";
			echo "<input type='hidden' id='classid' name='classid' value='$Class_ID' class='validate[required]' readonly/>";
			echo "<input type='hidden' id='batchid' name='batchid' value='$_Batch_ID' class='validate[required]' readonly/>";
			echo "<input type='hidden' id='term' name='term' value='$_Term_Name' class='validate[required]' readonly/>";
			
			$_SQL_CLASS=mysqli_query($con,"SELECT * FROM tblclassentry WHERE class_entryid='$Class_ID'");
			if($row_cl=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC)){
			//echo "Class: ". $row_cl['class_name'];
			echo "<input type='text' id='class_1' name='class_1' value='$row_cl[class_name] Semester: $_Term_Name' readonly/>";
			}

			$_SQL_BAT=mysqli_query($con,"SELECT * FROM tblbatch WHERE batchid='$_Batch_ID'");
			if($row_bh=mysqli_fetch_array($_SQL_BAT,MYSQLI_ASSOC)){
			//echo "Class: ". $row_cl['class_name'];
			echo "<input type='text' id='batchname' name='batchname' value='$row_bh[batch]' readonly/>";
			}

			}
            }
            }else{
                echo "<div class='std-empty-note'><i class='fa fa-info-circle'></i> Choose a student using the book icon in the records panel.</div>";
            }
			?>
			
			</fieldset>

			<div class="std-form-grid">
			<div>
			<label>No. On Roll</label>
			<input type="number" id="roll" name="roll" value="" class="validate[required]" />
			</div>


<div>
<label>Attendance</label>
<input type="number" id="attendance" name="attendance" value="" class="validate[required]" />
</div>


<div>
<label>Total Attendance</label>
<input type="number" id="totalattendance" name="totalattendance" value="" class="validate[required]" />
</div>

<div>
<label>Promoted To</label>
	<?php	
			$_SQL_2=mysqli_query($con,"SELECT * FROM tblclassentry");

			echo "<select id='promotedto' name='promotedto'>";
			echo "<option value=''>Select Class</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
				echo "<option value='$row[class_entryid]'>$row[class_name]</option>";
				}
				
			echo "</select>";
			?>
</div>


<div>
<label>Conduct</label>
<input type="text" id="conduct" name="conduct" value="" class="validate[required]" />
</div>

<div>
<label>Interest (Special Aptitude)</label>
<input type="text" id="interest" name="interest" value="" class="validate[required]" />
</div>

<div class="std-span-2">
<label>Class Teacher's Remark</label>
<input type="text" id="class_teacher_remark" name="class_teacher_remark" value="" class="validate[required]" />
</div>

<div class="std-span-2">
<label>Head Teacher's Remark</label>
<input type="text" id="head_teacher_remark" name="head_teacher_remark" value="" />
</div>
</div>

				
			<div class="std-actions"><button class="button-save std-btn std-btn-primary" id="register_student_terminal" name="register_student_terminal"><i class="fa fa-save"></i> Save Data</button></div>
		</form>

		</aside>
<main class="std-panel std-records-panel">
<div class="std-panel-heading">
	<span class="std-icon"><i class="fa fa-list"></i></span>
	<div>
		<h2>Student Records</h2>
		<p>Open a student's semester record to enter or update terminal data.</p>
	</div>
</div>
<?php
echo $_SESSION['Message'];
include("dbstring.php");
			
if($isTeacherRole){
    $_TeacherId=mysqli_real_escape_string($con, $_SESSION['USERID']);
    $_SQL_SU=mysqli_query($con,"SELECT DISTINCT su.*
    FROM tblsystemuser su
    INNER JOIN tbltermregistry tr ON tr.userid=su.userid
    INNER JOIN tblclassteacher ct ON ct.classid=tr.class_entryid AND ct.batchid=tr.batchid AND ct.termname=tr.termname
    WHERE su.systemtype='Student' AND ct.userid='$_TeacherId' AND ct.status='active'");
}else{
    $_SQL_SU=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE systemtype='Student'");
}
if(mysqli_num_rows($_SQL_SU)>0){
				//Registered clients
				echo "<div class='std-table-wrap'>";
				echo "<table class='std-table std-main-table'>";
				echo "<caption>students' end of semester data</caption>";
				echo "<thead><th colspan=1>Task</th><th>Class</th><th>Term</th><th>Batch</th><th>Entry Date/Time</th></thead>";
				echo "<tbody>";
	while($row_c=mysqli_fetch_array($_SQL_SU,MYSQLI_ASSOC)){
				echo "<tr class='std-student-row'>";
				//echo "<td align='center'><a title='View $row_c[firstname] ($row_c[userid])' href='student-terminal-data.php?view_user=$row_c[userid]&class_id=$row_c[class_entryid]'><i class='fa fa-book' style='color:blue'></i></a></td>";
				echo "<td colspan='4'>$row_c[firstname] $row_c[othernames] $row_c[surname] ($row_c[userid])</td>";
				echo "<td align='center'></td>";
				echo "</tr>";

                if($isTeacherRole){
                    $_TeacherIdForClass = mysqli_real_escape_string($con, $_SESSION['USERID']);
                    $_SQL_EXECUTE=mysqli_query($con,"SELECT DISTINCT cl.*, ce.class_name
                        FROM tblclass cl
                        INNER JOIN tblclassentry ce ON cl.class_entryid=ce.class_entryid
                        INNER JOIN tbltermregistry tr ON tr.userid=cl.userid AND tr.class_entryid=cl.class_entryid
                        INNER JOIN tblclassteacher ct ON ct.classid=tr.class_entryid AND ct.batchid=tr.batchid AND ct.termname=tr.termname
                        WHERE cl.userid='$row_c[userid]' AND cl.status='active'
                          AND ct.userid='$_TeacherIdForClass' AND ct.status='active'
                        ORDER BY ce.class_name ASC");
                }else{
    				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblclass cl 
    					INNER JOIN tblclassentry ce ON cl.class_entryid=ce.class_entryid 
    					WHERE cl.userid='$row_c[userid]' AND cl.status='active' ORDER BY ce.class_name ASC");
                }

				
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr>";
			//	echo "<td align='center'><a title='View ($row[class_name])' href='student-terminal-data.php?view_user=$row[userid]&class_id=$row[class_entryid]'><i class='fa fa-book' style='color:blue'></i></a></td>";
				
				//echo "<td>$row[firstname] $row[othernames] $row[surname] ($row[userid])</td>";
				//echo "<td align='center'></td>";
				//echo "<td align='center'></td>";
				echo "<td align='center'></td>";
				
				echo "<td align='center'>$row[class_name]</td>";
				echo "<td align='center'></td>";				
				echo "</tr>";

				$_SQL_TERM=mysqli_query($con,"SELECT * FROM tbltermregistry tr 
				INNER JOIN tblbatch b ON tr.batchid=b.batchid
				WHERE tr.userid='$row[userid]' AND tr.class_entryid='$row[class_entryid]' AND tr.batchid='$row[batchid]' ORDER BY tr.termname ASC");
				while($row_tr=mysqli_fetch_array($_SQL_TERM,MYSQLI_ASSOC))
				{
                if($isTeacherRole && !class_teacher_is_assigned($con, $_SESSION['USERID'], $row['class_entryid'], $row_tr['batchid'], (int)$row_tr['termname'])){
                    continue;
                }
				echo "<tr class='std-term-row'>";
				//echo "<td align='center'><a onclick=\"javascript:return confirm('Do you want to remove term?')\" title='Remove term $row_tr[termname]' href='student-terminal-data.php?delete_term=$row_tr[terminalid]'<i class='fa fa-times' style='color:red'></i></a></td>";
				echo "<td align='center'><a class='std-row-action' title='View ($row[class_name] Term: $row_tr[termname])' href='student-terminal-data.php?view_user=$row[userid]&batch_id=$row_tr[batchid]&class_id=$row[class_entryid]&term_name=$row_tr[termname]'><i class='fa fa-book'></i></a></td>";
			
				echo "<td colspan='1' align='right'>";
				//echo "Term:";
				echo "</td>";
				echo "<td align='center'>";
				echo $row_tr['termname'];
				echo "</td>";
				echo "<td align='center'>$row_tr[batch]</td>";
				echo "<td align='center'>";
				echo $row_tr['datetimeentry'];
				echo "</td>";
				echo "</tr>";

                if($isTeacherRole){
				$_SQL_STR=mysqli_query($con,"SELECT * FROM tblstudentterminalreport str INNER JOIN tblbatch bch ON str.batchid=bch.batchid WHERE str.userid='$row_tr[userid]' AND str.batchid='$row_tr[batchid]' AND str.termname='$row_tr[termname]' ORDER BY str.datetimeentry DESC");
                }else{
				$_SQL_STR=mysqli_query($con,"SELECT * FROM tblstudentterminalreport str INNER JOIN tblbatch bch ON str.batchid=bch.batchid WHERE str.userid='$row_tr[userid]' AND str.batchid='$row_tr[batchid]' AND (str.termname='$row_tr[termname]' OR str.termname='0') ORDER BY str.termname DESC, str.datetimeentry DESC");
                }
				if(mysqli_num_rows($_SQL_STR)>0)
				{
				echo "<tr>";
				echo "<td colspan='5'>";

				echo "<div class='std-nested-wrap'><table class='std-table std-nested-table'>";
				echo "<caption>Semester: $row_tr[termname], $row_tr[batch]</caption>";
				echo "<thead><th>Batch</th><th>Roll</th><th>Attend.</th><th>Total Attend.</th><th>Promoted To</th><th>Conduct</th><th>Interest</th><th>Class Teacher's Remark</th><th>Head Teacher's Remark</th><th>Date/Time</th></thead>";
				echo "<tbody>";
				if($row_str=mysqli_fetch_array($_SQL_STR,MYSQLI_ASSOC)){
				echo "<tr>";
				echo "<td align='center'>";
				echo $row_str["batch"];
				echo "</td>";

				echo "<td align='center'>";
				echo $row_str["roll"];
				echo "</td>";

				echo "<td align='center'>";
				echo $row_str["attendance"];
				echo "</td>";

				echo "<td align='center'>";
				echo $row_str["totalattendance"];
				echo "</td>";
				
				echo "<td align='center'>";
				echo $row_str["promotedto"];
				echo "</td>";
				
				echo "<td align='center'>";
				echo $row_str["conduct"];
				echo "</td>";
				
				echo "<td align='center'>";
				echo $row_str["interest"];
				echo "</td>";

				echo "<td>";
				echo $row_str["class_teacher_remark"];
				echo "</td>";

				echo "<td>";
				echo $row_str["head_teacher_remark"];
				echo "</td>";
				
				echo "<td>";
				echo $row_str["datetimeentry"];
				echo "</td>";
				
				echo "</tr>";
				}	
				echo "</tbody>";
				echo "</table></div>";
				echo "</td>";
				echo "</tr>";
			}
			}
		}
	}
echo "</tbody>";
echo "</table>";
echo "</div>";
}else{
    echo "<div class='std-empty-state'><i class='fa fa-users'></i><h3>No student records found</h3><p>There are no students available for your current access level.</p></div>";
}
?>
</main>
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
</div>
</body>
</html>
