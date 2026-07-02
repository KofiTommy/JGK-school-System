<?php
session_start();
$_SESSION['Message']="";
include("check-login.php");
include("dbstring.php");
include("class-teacher-utils.php");
ensure_class_teacher_table($con);

if(!class_teacher_can_manage_assignments($con)){
    header("location:".class_teacher_landing_page());
    exit();
}

if(isset($_POST['save_class_teacher'])){
    @$_TeacherId = $_POST['userid'];
    @$_ClassId = $_POST['classid'];
    @$_BatchId = $_POST['batchid'];
    @$_Term = (int)$_POST['term'];
    @$_RecordedBy = $_SESSION['USERID'];

    if(!$_TeacherId || !$_ClassId || !$_BatchId || !$_Term){
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Please select teacher, class, batch and semester.</div>";
    }else{
        $_TeacherId = mysqli_real_escape_string($con, $_TeacherId);
        $_ClassId = mysqli_real_escape_string($con, $_ClassId);
        $_BatchId = mysqli_real_escape_string($con, $_BatchId);
        $_RecordedBy = mysqli_real_escape_string($con, $_RecordedBy);

        $_SQL_EXIST = mysqli_query($con, "SELECT assignmentid FROM tblclassteacher WHERE classid='$_ClassId' AND batchid='$_BatchId' AND termname='$_Term' AND status='active' LIMIT 1");
        if($_SQL_EXIST && $row_exist=mysqli_fetch_array($_SQL_EXIST,MYSQLI_ASSOC)){
            $_AssignmentId = $row_exist['assignmentid'];
            $_SQL_UPDATE = mysqli_query($con, "UPDATE tblclassteacher SET userid='$_TeacherId', recordedby='$_RecordedBy', datetimeentry=NOW(), status='active' WHERE assignmentid='$_AssignmentId'");
            if($_SQL_UPDATE){
                $_SESSION['Message'] = "<div style='color:green;text-align:center;background-color:white'>Class teacher assignment updated successfully.</div>";
            }else{
                $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Failed to update assignment: ".mysqli_error($con)."</div>";
            }
        }else{
            include("code.php");
            $_AssignmentId = $code;
            $_SQL_INSERT = mysqli_query($con, "INSERT INTO tblclassteacher(assignmentid,userid,classid,batchid,termname,status,datetimeentry,recordedby)
                VALUES('$_AssignmentId','$_TeacherId','$_ClassId','$_BatchId','$_Term','active',NOW(),'$_RecordedBy')");
            if($_SQL_INSERT){
                $_SESSION['Message'] = "<div style='color:green;text-align:center;background-color:white'>Class teacher assigned successfully.</div>";
            }else{
                $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Failed to assign class teacher: ".mysqli_error($con)."</div>";
            }
        }
    }
}

if(isset($_GET['deactivate_assignment'])){
    $_AssignmentId = mysqli_real_escape_string($con, $_GET['deactivate_assignment']);
    $_SQL_D = mysqli_query($con, "UPDATE tblclassteacher SET status='inactive' WHERE assignmentid='$_AssignmentId'");
    if($_SQL_D){
        $_SESSION['Message'] = "<div style='color:maroon;text-align:center;background-color:white'>Assignment deactivated.</div>";
    }else{
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Failed to deactivate assignment: ".mysqli_error($con)."</div>";
    }
}

if(isset($_GET['delete_assignment'])){
    $_AssignmentId = mysqli_real_escape_string($con, $_GET['delete_assignment']);
    $_SQL_DEL = mysqli_query($con, "DELETE FROM tblclassteacher WHERE assignmentid='$_AssignmentId'");
    if($_SQL_DEL){
        $_SESSION['Message'] = "<div style='color:maroon;text-align:center;background-color:white'>Assignment deleted successfully.</div>";
    }else{
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Failed to delete assignment: ".mysqli_error($con)."</div>";
    }
}
?>
<html>
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" href="css/class-teacher-assignment.css">
</head>
<body>
<div class="header">
<?php include("menu.php"); ?>
</div>
<div class="main-platform cta-page">
<section class="cta-hero">
    <div>
        <span class="cta-kicker">Academic Setup</span>
        <h1>Class Teacher Assignment</h1>
        <p>Assign class teachers by class, batch, and semester so access stays accurate.</p>
    </div>
    <div class="cta-hero-card">
        <i class="fa fa-user-circle-o"></i>
        <span>Class Teacher Control</span>
    </div>
</section>

<div class="cta-layout">
<aside class="cta-panel cta-form-panel">
<div class="cta-panel-heading">
    <span class="cta-icon"><i class="fa fa-plus"></i></span>
    <div>
        <h2>Assign Teacher</h2>
        <p>Select the scope for the class teacher assignment.</p>
    </div>
</div>
<?php echo $_SESSION['Message']; ?>
<form method="post" action="class-teacher-assignment.php" id="formID" name="formID">
<?php
$_SQL_T = mysqli_query($con,"SELECT userid,firstname,surname,othernames FROM tblsystemuser WHERE systemtype='Teacher' AND status='active' ORDER BY firstname ASC");
echo "<label>Teacher</label>";
echo "<select id='userid' name='userid' class='validate[required]'>";
echo "<option value=''>Select Teacher</option>";
while($row_t=mysqli_fetch_array($_SQL_T,MYSQLI_ASSOC)){
    echo "<option value='$row_t[userid]'>$row_t[firstname] $row_t[othernames] $row_t[surname] ($row_t[userid])</option>";
}
echo "</select>";

$_SQL_C = mysqli_query($con,"SELECT class_entryid,class_name FROM tblclassentry ORDER BY class_name ASC");
echo "<label>Class</label>";
echo "<select id='classid' name='classid' class='validate[required]'>";
echo "<option value=''>Select Class</option>";
while($row_c=mysqli_fetch_array($_SQL_C,MYSQLI_ASSOC)){
    echo "<option value='$row_c[class_entryid]'>$row_c[class_name]</option>";
}
echo "</select>";

$_SQL_B = mysqli_query($con,"SELECT batchid,batch FROM tblbatch ORDER BY datetimeentry DESC");
echo "<label>Batch</label>";
echo "<select id='batchid' name='batchid' class='validate[required]'>";
echo "<option value=''>Select Batch</option>";
while($row_b=mysqli_fetch_array($_SQL_B,MYSQLI_ASSOC)){
    echo "<option value='$row_b[batchid]'>$row_b[batch]</option>";
}
echo "</select>";
?>
<label>Semester</label>
<select id="term" name="term" class="validate[required]">
<option value="">Select Semester</option>
<option value="1">1</option>
<option value="2">2</option>
</select>
<div class="cta-actions"><button class="button-save cta-btn cta-btn-primary" id="save_class_teacher" name="save_class_teacher"><i class="fa fa-save"></i> Save Assignment</button></div>
</form>
</aside>
<main class="cta-panel cta-list-panel">
<div class="cta-panel-heading">
    <span class="cta-icon"><i class="fa fa-list"></i></span>
    <div>
        <h2>Assigned Class Teachers</h2>
        <p>Review active and inactive assignments, print, deactivate, or delete records.</p>
    </div>
</div>
<div class="print-hide cta-print-row">
    <button class="button-save cta-btn cta-btn-print" type="button" onclick="window.print()"><i class="fa fa-print"></i> Print Class Teachers</button>
</div>
<?php
$_SQL_A = mysqli_query($con,"SELECT ct.*,su.firstname,su.surname,su.othernames,ce.class_name,bh.batch
FROM tblclassteacher ct
INNER JOIN tblsystemuser su ON su.userid=ct.userid
INNER JOIN tblclassentry ce ON ce.class_entryid=ct.classid
INNER JOIN tblbatch bh ON bh.batchid=ct.batchid
ORDER BY ct.datetimeentry DESC");
echo "<div class='cta-table-wrap'>";
echo "<table class='cta-table'>";
echo "<caption>Assigned Class Teachers</caption>";
echo "<thead><th>Task</th><th>Teacher</th><th>Class</th><th>Semester</th><th>Batch</th><th>Status</th><th>Date/Time</th></thead>";
echo "<tbody>";
while($row_a=mysqli_fetch_array($_SQL_A,MYSQLI_ASSOC)){
    echo "<tr>";
    echo "<td align='center'>";
    if($row_a['status']==='active'){
        echo "<span class='print-hide'><a class='cta-row-action cta-action-warning' title='Deactivate assignment' onclick=\"javascript:return confirm('Deactivate this assignment?');\" href='class-teacher-assignment.php?deactivate_assignment=$row_a[assignmentid]'><i class='fa fa-ban'></i></a></span> ";
        echo "<span class='print-hide'><a class='cta-row-action cta-action-danger' title='Delete assignment' onclick=\"javascript:return confirm('Delete this assignment permanently?');\" href='class-teacher-assignment.php?delete_assignment=$row_a[assignmentid]'><i class='fa fa-trash'></i></a></span>";
    }else{
        echo "<span class='print-hide'><a class='cta-row-action cta-action-danger' title='Delete assignment' onclick=\"javascript:return confirm('Delete this assignment permanently?');\" href='class-teacher-assignment.php?delete_assignment=$row_a[assignmentid]'><i class='fa fa-trash'></i></a></span>";
    }
    echo "</td>";
    echo "<td>$row_a[firstname] $row_a[othernames] $row_a[surname] ($row_a[userid])</td>";
    echo "<td align='center'>$row_a[class_name]</td>";
    echo "<td align='center'>$row_a[termname]</td>";
    echo "<td align='center'>$row_a[batch]</td>";
    echo "<td align='center'>$row_a[status]</td>";
    echo "<td align='center'>$row_a[datetimeentry]</td>";
    echo "</tr>";
}
echo "</tbody>";
echo "</table>";
echo "</div>";
?>
</main>
</div>
</div>
</body>
</html>
