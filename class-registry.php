<?php
session_start();
$_SESSION['Message']="";
?>
<?php
include("dbstring.php");
include_once("student-index-utils.php");
$_SchoolIndexReady = student_index_ensure_schema($con);
@$_ClassId=$_POST['classid'];
@$_BatchId=$_POST["batchid"];
@$_UserId=$_POST['userid'];
@$_Class=$_POST['class'];
@$_Recordedby=$_SESSION['USERID'];

if(isset($_POST['register_class'])){
	$_SelectedUsers = array();
	if(isset($_POST['userid'])){
		if(is_array($_POST['userid'])){
			$_SelectedUsers = $_POST['userid'];
		}else if(trim((string)$_POST['userid']) !== ''){
			$_SelectedUsers = array($_POST['userid']);
		}
	}

	if($_Class=="" || $_BatchId==""){
		$_SESSION['Message']="<div style='color:red;padding:5px;text-align:center;border:1px solid #eaa;background-color:#fee;'>Please select class and batch</div>";
	}
	else if(count($_SelectedUsers)==0)
	{
		$_SESSION['Message']="<div style='color:red;padding:5px;text-align:center;border:1px solid #eaa;background-color:#fee;'>No student selected</div>";	
	}
	else{
		$_Success=0;
		$_Duplicate=0;
		$_Failed=0;
		$_IndexGenerated=0;
		$_IndexExisting=0;
		$_IndexFailed=0;
		$_SESSION['Message']="";

		foreach($_SelectedUsers as $selectedUserId){
			$_UserId = mysqli_real_escape_string($con, trim($selectedUserId));
			if($_UserId==""){ continue; }

			$_SQL_CHECK=mysqli_query($con,"SELECT classid FROM tblclass WHERE userid='$_UserId' AND batchid='$_BatchId' AND status='active'");
			if(mysqli_num_rows($_SQL_CHECK)>0){
				$_Duplicate++;
				continue;
			}

			include("code.php");
			$_ClassId=$code;
			$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblclass(classid,userid,class_entryid,batchid,datetimeentry,recordedby,status)
				VALUES('$_ClassId','$_UserId','$_Class','$_BatchId',NOW(),'$_Recordedby','active')");
			if($_SQL_EXECUTE){
				$_Success++;
				$_IndexResult = student_index_assign_for_class($con, $_UserId, $_Class, $_BatchId, $_Recordedby);
				if($_IndexResult["success"]){
					if($_IndexResult["created"]){
						$_IndexGenerated++;
					}else{
						$_IndexExisting++;
					}
				}else{
					$_IndexFailed++;
				}
			}
			else{
				$_Failed++;
			}
		}

		if($_Success>0){
			$_SESSION['Message'].="<div style='color:green;padding:5px;text-align:center;border:1px solid #aea;background-color:#efe;'>Class Successfully Registered for $_Success student(s)</div>";
		}
		if($_IndexGenerated>0){
			$_SESSION['Message'].="<div style='color:green;padding:5px;text-align:center;border:1px solid #aea;background-color:#efe;'>School index generated for $_IndexGenerated student(s)</div>";
		}
		if($_IndexExisting>0){
			$_SESSION['Message'].="<div style='color:#23608a;padding:5px;text-align:center;border:1px solid #b6d4ec;background-color:#eef8ff;'>$_IndexExisting student(s) already had a school index number</div>";
		}
		if($_Duplicate>0){
			$_SESSION['Message'].="<div style='color:#8a6d3b;padding:5px;text-align:center;border:1px solid #faebcc;background-color:#fcf8e3;'>$_Duplicate student(s) skipped (already has class in selected batch)</div>";
		}
		if($_Failed>0){
			$_SESSION['Message'].="<div style='color:red;padding:5px;text-align:center;border:1px solid #eaa;background-color:#fee;'>$_Failed student(s) failed to register</div>";
		}
		if($_IndexFailed>0){
			$_SESSION['Message'].="<div style='color:#8a6d3b;padding:5px;text-align:center;border:1px solid #faebcc;background-color:#fcf8e3;'>Class registration was saved, but $_IndexFailed school index number(s) could not be generated</div>";
		}
	}
}
?>

<?php
include("dbstring.php");
include_once("student-index-utils.php");
$_SchoolIndexReady = student_index_ensure_schema($con);

if(isset($_GET["delete_class"]))
{
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblclass WHERE classid='$_GET[delete_class]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:red;padding:5px;text-align:center;border:1px solid #eaa;background-color:#fee;'>Class Successfully Deleted</div>";	

	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red;padding:5px;text-align:center;border:1px solid #eaa;background-color:#fee;'>Class failed to delete,Error:$_Error</div>";	
	}
}
?>

<html>
<head>
<?php
include("links.php");
?>
<link rel="stylesheet" href="css/class-registry.css">
<script type="text/javascript">
function toggleAllStudents(){
  var selectAll = document.getElementById("all_students");
  var inputs = document.getElementsByName("userid[]");
  for(var i=0;i<inputs.length;i++){
    inputs[i].checked = selectAll.checked;
  }
}

function filterStudents(){
  var q = document.getElementById("student_filter").value.toLowerCase();
  var rows = document.querySelectorAll(".student-row");
  for(var i=0;i<rows.length;i++){
    var txt = rows[i].getAttribute("data-student");
    rows[i].style.display = txt.indexOf(q) !== -1 ? "" : "none";
  }
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
<div class="main-platform registry-page">
	<section class="registry-hero">
		<div>
			<span class="registry-kicker">Student Records</span>
			<h1>Class Registry</h1>
			<p>Register students into classes by batch and review their active class records.</p>
		</div>
		<div class="registry-hero-card">
			<i class="fa fa-users"></i>
			<span>Group Registration</span>
		</div>
	</section>

	<div class="registry-layout">
		<aside class="registry-panel registry-form-panel">
			<div class="registry-panel-heading">
				<span class="registry-icon"><i class="fa fa-plus"></i></span>
				<div>
					<h2>Group Class Registration</h2>
					<p>Select class, batch, then choose students to register.</p>
				</div>
			</div>
			<form method="post" id="formID" name="formID" action="class-registry.php">
			<input type="hidden" id="classid" name="classid" value="<?php include("shortcode.php");echo $shortcode;?>" />
			
			<label>Class</label>
			<?php	
			$_SQL_2=mysqli_query($con,"SELECT * FROM tblclassentry ORDER BY datetimeentry ASC");

			echo "<select id='class' name='class' class='validate[required]'>";
			echo "<option value=''>Select Class</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[class_entryid]'>$row[class_name]</option>";
				}	
			echo "</select>";
			?>

			<label>Batch</label>
			<?php	
			$_SQL_3=mysqli_query($con,"SELECT * FROM tblbatch ORDER BY datetimeentry DESC");

			echo "<select id='batchid' name='batchid' class='validate[required]'>";
			echo "<option value=''>Select Batch</option>";
				while($row=mysqli_fetch_array($_SQL_3,MYSQLI_ASSOC)){
					echo "<option value='$row[batchid]'>$row[batch]</option>";
				}	
			echo "</select>";
			?>
			
			<label>Find Student</label>
			<input type="text" id="student_filter" onkeyup="filterStudents()" placeholder="Type name or user id" />

			<div class="registry-student-picker">
				<table class="registry-picker-table">
					<thead>
						<tr>
							<th width="10%"><input type="checkbox" id="all_students" onclick="toggleAllStudents()" /></th>
							<th align="left">Student</th>
						</tr>
					</thead>
					<tbody>
						<?php
						$_SchoolIndexColumn = $_SchoolIndexReady ? "schoolindexnumber" : "'' AS schoolindexnumber";
						$_SQL_STUDENTS=mysqli_query($con,"SELECT userid,$_SchoolIndexColumn,firstname,surname,othernames FROM tblsystemuser WHERE systemtype='Student' AND status='active' ORDER BY firstname ASC,surname ASC");
						while($stu=mysqli_fetch_array($_SQL_STUDENTS,MYSQLI_ASSOC)){
							$_SchoolIndex=trim((string)(isset($stu['schoolindexnumber']) ? $stu['schoolindexnumber'] : ""));
							$_StudentRef=$_SchoolIndex !== "" ? $_SchoolIndex : $stu['userid'];
							$_FullName=trim($stu['firstname']." ".$stu['othernames']." ".$stu['surname']." (".$_StudentRef.")");
							$_IndexText=htmlspecialchars(strtolower($_FullName." ".$stu['userid']." ".$_SchoolIndex), ENT_QUOTES, 'UTF-8');
							$_UserIdSafe=htmlspecialchars($stu['userid'], ENT_QUOTES, 'UTF-8');
							$_FullNameSafe=htmlspecialchars($_FullName, ENT_QUOTES, 'UTF-8');
							echo "<tr class='student-row' data-student='$_IndexText'>";
							echo "<td><input type='checkbox' name='userid[]' value='$_UserIdSafe' /></td>";
							echo "<td>$_FullNameSafe</td>";
							echo "</tr>";
						}
						?>
					</tbody>
				</table>
			</div>

			<div class="registry-actions"><button class="button-save registry-btn registry-btn-primary" id="register_class" name="register_class"><i class="fa fa-save"></i> Save Class Registry</button></div>
		</form>

		</aside>
			<main class="registry-panel registry-list-panel">
				<div class="registry-panel-heading">
					<span class="registry-icon"><i class="fa fa-list"></i></span>
					<div>
						<h2>Registered Classes</h2>
						<p>Students are grouped with their active class and batch records underneath.</p>
					</div>
				</div>
				<?php
				echo $_SESSION['Message'];

				include("dbstring.php");
				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE systemtype='Student' ORDER BY firstname ASC");

				//Registered clients
				echo "<div class='registry-table-wrap'>";
				echo "<table class='registry-table'>";
				echo "<caption>Class Registry</caption>";
				echo "<thead><th>*</th><th colspan=1>TASK</th><th>STUENT</th><th>CLASS</th><th>BATCH</th><th>ENTRY DATE/TIME</th></thead>";
				echo "<tbody>";
		
				@$serial=0;
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr class='registry-student-row'>";
					echo "<td align='center'>";
				echo $serial=$serial+1 .".";
				echo "</td>";

				echo "<td align='center' ><a class='registry-row-action' title='View $row[firstname] ($row[userid])' href='class-registry.php?view_user=$row[userid]'><i class='fa fa-plus'></i></a></td>";
				
					$_DisplayIndex=trim((string)(isset($row['schoolindexnumber']) ? $row['schoolindexnumber'] : ""));
					$_DisplayRef=$_DisplayIndex !== "" ? $_DisplayIndex : $row['userid'];
					echo "<td colspan='4'>$row[firstname] $row[othernames] $row[surname] ($_DisplayRef)";
					if($_DisplayIndex !== "" && $_DisplayIndex !== $row['userid']){
						echo " <span class='registry-index-chip'>Login ID: $row[userid]</span>";
					}
					echo "</td>";
			
				echo "</tr>";

				$_SQL_CLASS=mysqli_query($con,"SELECT *,cl.datetimeentry FROM tblclass cl 
				INNER JOIN tblclassentry ce ON cl.class_entryid=ce.class_entryid
				INNER JOIN tblbatch bh ON cl.batchid=bh.batchid
				 WHERE  cl.userid='$row[userid]' AND cl.status='active' ORDER BY ce.class_name ASC");
				
				while($row_cl=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC)){

				echo "<tr class='registry-class-row'>";
				echo "<td colspan='1'>";
				echo "</td>";
				echo "<td align='center'><a class='registry-row-action registry-action-danger' onclick=\"javascript:return confirm('Do you want to remove class?')\" title='Remove class $row_cl[class_name]' href='class-registry.php?delete_class=$row_cl[classid]'><i class='fa fa-trash-o'></i></a></td>";
				echo "<td colspan='1' align='right'>";
				echo "Class:";
				echo "</td>";
				echo "<td colspan='1'>";
				echo $row_cl['class_name'];
				echo "</td>";

				echo "<td colspan='1'>";
				echo $row_cl["batch"];
				echo "</td>";

				echo "<td colspan='1'>";
				echo $row_cl['datetimeentry'];
				echo "</td>";
				echo "</tr>";

		}
				}
				echo "</tbody>";
				echo "</table>";
				echo "</div>";
				?>
			</main>
	</div>


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
