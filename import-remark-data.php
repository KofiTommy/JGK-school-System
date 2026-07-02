<?php
session_start();
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

function insertRemarkData($con, $isAdminRole, $isTeacherRole, $_UserId, $_Batch, $_TermName, $_Onroll, $_Attendance, $_TotalAttendance, $_Promotedto, $_Conduct, $_Interest, $_TeacherRemark, $_HeadTeacherRemark){
    if($_UserId==="userid" || $_Attendance==="attendance"){
        return;
    }

    $_UserId = trim((string)$_UserId);
    $_Batch = trim((string)$_Batch);
    if($_UserId==="" || $_Batch===""){
        return;
    }

    $_Batch_Id = "";
    $_SQLB=mysqli_query($con,"SELECT * FROM tblbatch WHERE batch='".mysqli_real_escape_string($con,$_Batch)."' LIMIT 1");
    if($rowb=mysqli_fetch_array($_SQLB,MYSQLI_ASSOC)){
        $_Batch_Id=trim($rowb["batchid"]);
    }
    if($_Batch_Id===""){
        echo "<div style='background-color:white;color:red;' align='center'>Batch not found for row: ".htmlspecialchars($_Batch,ENT_QUOTES,'UTF-8')."</div><br>";
        return;
    }

    $_TermName = (int)$_TermName;
    if($isTeacherRole && !class_teacher_can_manage_student_batch($con, $_SESSION['USERID'], $_UserId, $_Batch_Id, $_TermName)){
        echo "<div style='background-color:white;color:red;' align='center'>Skipped ".$_UserId." (not assigned to this class/batch/semester).</div><br>";
        return;
    }

    $sql="SELECT * FROM tblstudentterminalreport WHERE userid='".mysqli_real_escape_string($con,$_UserId)."' AND batchid='".mysqli_real_escape_string($con,$_Batch_Id)."' AND termname='".$_TermName."'";
    $result = mysqli_query($con,$sql);
    $count = mysqli_num_rows($result);
    if($count>0){
        echo "<div style='background-color:white;color:red;' align='center'>Student Semester Data already stored for ".$_UserId.".</div><br>";
        return;
    }

    include("code.php");
    $_RecordedBy = mysqli_real_escape_string($con, $_SESSION['USERID']);
    $_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblstudentterminalreport(terminalid,userid,batchid,termname,roll,attendance,totalattendance,promotedto,conduct,interest,class_teacher_remark,head_teacher_remark,recordedby,status,datetimeentry)
        VALUES('$code','".mysqli_real_escape_string($con,$_UserId)."','".mysqli_real_escape_string($con,$_Batch_Id)."','".$_TermName."','".mysqli_real_escape_string($con,$_Onroll)."','".mysqli_real_escape_string($con,$_Attendance)."','".mysqli_real_escape_string($con,$_TotalAttendance)."','".mysqli_real_escape_string($con,$_Promotedto)."','".mysqli_real_escape_string($con,$_Conduct)."','".mysqli_real_escape_string($con,$_Interest)."','".mysqli_real_escape_string($con,$_TeacherRemark)."','".mysqli_real_escape_string($con,$_HeadTeacherRemark)."','$_RecordedBy','active',NOW())");

    if($_SQL_EXECUTE){
        echo "<div style='background-color:white;color:green;' align='center'>User Information Successfully Saved</div><br>";
    }else{
        echo "<div style='background-color:white;color:red;' align='center'>Student Remark Data failed to save,Error: ".mysqli_error($con)."</div><br>";
    }
}

require_once 'simplexlsx.class.php';
$counter = 0;
$message = "";

if(isset($_POST['submit_group_data'])){
    $file = isset($_FILES['file1']['tmp_name']) ? $_FILES['file1']['tmp_name'] : "";
    if($file===""){
        $message = "<div style='background-color:white;color:red;' align='center'>Please choose a file before uploading.</div><br>";
    }else{
        $xlsx = new SimpleXLSX($file);
        foreach($xlsx->rows() as $field){
            $_UserId = isset($field[0]) ? $field[0] : "";
            $_Batch = isset($field[1]) ? $field[1] : "";
            $_RawTerm = isset($field[2]) ? trim((string)$field[2]) : "";
            $_LooksNewFormat = (($_RawTerm === "1" || $_RawTerm === "2") && isset($field[10]));
            if($_LooksNewFormat){
                $_TermName = (int)$_RawTerm;
                $_Onroll = isset($field[3]) ? $field[3] : "";
                $_Attendance = isset($field[4]) ? $field[4] : "";
                $_TotalAttendance = isset($field[5]) ? $field[5] : "";
                $_Promotedto = isset($field[6]) ? $field[6] : "";
                $_Conduct = isset($field[7]) ? $field[7] : "";
                $_Interest = isset($field[8]) ? $field[8] : "";
                $_TeacherRemark = isset($field[9]) ? $field[9] : "";
                $_HeadTeacherRemark = isset($field[10]) ? $field[10] : "";
            }else{
                // Backward compatibility with old template (no term column)
                $_TermName = 0;
                $_Onroll = isset($field[2]) ? $field[2] : "";
                $_Attendance = isset($field[3]) ? $field[3] : "";
                $_TotalAttendance = isset($field[4]) ? $field[4] : "";
                $_Promotedto = isset($field[5]) ? $field[5] : "";
                $_Conduct = isset($field[6]) ? $field[6] : "";
                $_Interest = isset($field[7]) ? $field[7] : "";
                $_TeacherRemark = isset($field[8]) ? $field[8] : "";
                $_HeadTeacherRemark = isset($field[9]) ? $field[9] : "";
            }

            if($_TermName <= 0){
                $_TermResolve = mysqli_query($con, "SELECT MAX(termname) AS termname FROM tbltermregistry WHERE userid='".mysqli_real_escape_string($con,$_UserId)."' AND batchid=(SELECT batchid FROM tblbatch WHERE batch='".mysqli_real_escape_string($con,$_Batch)."' LIMIT 1)");
                if($_TermResolve && $row_term=mysqli_fetch_array($_TermResolve,MYSQLI_ASSOC)){
                    $_TermName = (int)$row_term['termname'];
                }
            }
            if($_TermName <= 0){
                echo "<div style='background-color:white;color:red;' align='center'>Skipped ".$_UserId." (missing semester in file and unable to resolve).</div><br>";
                continue;
            }

            $counter++;
            insertRemarkData($con, $isAdminRole, $isTeacherRole, $_UserId, $_Batch, $_TermName, $_Onroll, $_Attendance, $_TotalAttendance, $_Promotedto, $_Conduct, $_Interest, $_TeacherRemark, $_HeadTeacherRemark);
        }
        if($counter>0){
            $message ="<div style='background-color:white;color:green;' align='center'>Remark Data Successfully Uploaded</div><br>";
        }else{
            $message ="<div style='background-color:white;color:red;' align='center'>Remark Data Failed To Upload</div><br>";
        }
    }
}
echo $message;
?>
