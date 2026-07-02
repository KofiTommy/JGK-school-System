<?php
session_start();
$_SESSION['Message'] = "";
include("dbstring.php");
include("check-login.php");
include("class-teacher-utils.php");
include_once("score-entry-utils.php");
include_once("semester-registry-utils.php");
semester_registry_ensure_academic_year_column($con);
if(!(class_teacher_is_teacher() || class_teacher_is_admin())){
    header("location:".class_teacher_landing_page());
    exit();
}

// Handle XLS download at the top to avoid output conflicts
if (isset($_GET['download']) && $_GET['download'] == 'xls') {
    // Clean any existing output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    // Set headers for XLS download
    @$_ClassId = $_GET['class_ID'];
    @$_Term = $_GET['term_ID'];
    @$_BatchId = $_GET['batch_ID'];
    @$_SubjectId = $_GET['subject_ID'];
    @$_AcademicYear = semester_registry_normalize_year($_GET['year_ID'] ?? '');
    @$_ClassName = "";
    @$_SubjectName = "";
    @$_BatchName = "";
    $_AcademicYearWhere = "";
    $_AllowedStudentWhere = " AND 1=0";
    if($_AcademicYear!==""){
        $_AcademicYearWhere = " AND ".semester_registry_resolved_year_sql("tr")."='".mysqli_real_escape_string($con,$_AcademicYear)."'";
    }

    $_AssignmentScopeSql=mysqli_query($con,"SELECT sa.assignmentid, sa.classid, sa.batchid, sa.termname, ".semester_registry_assignment_year_sql("sa")." AS assignment_year
    FROM tblsubjectassignment sa
    INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
    WHERE sa.classid='$_ClassId'
    AND sa.batchid='$_BatchId'
    AND sa.termname='$_Term'
    AND sc.subjectid='$_SubjectId'
    AND sa.userid='$_SESSION[USERID]'
    AND ".semester_registry_assignment_year_sql("sa")."='".mysqli_real_escape_string($con,$_AcademicYear)."'
    LIMIT 1");
    if($_AssignmentScopeSql && ($_AssignmentScopeRow=mysqli_fetch_array($_AssignmentScopeSql,MYSQLI_ASSOC))){
        $_AssignmentStudentContext = score_entry_assignment_student_context(
            $con,
            $_AssignmentScopeRow['assignmentid'],
            $_AssignmentScopeRow['classid'],
            $_AssignmentScopeRow['batchid'],
            $_AssignmentScopeRow['assignment_year'],
            $_AssignmentScopeRow['termname']
        );
        if(count($_AssignmentStudentContext['userids'])>0){
            $_AllowedIds=array();
            foreach($_AssignmentStudentContext['userids'] as $_AllowedStudentId){
                $_AllowedIds[]="'".mysqli_real_escape_string($con,trim((string)$_AllowedStudentId))."'";
            }
            $_AllowedStudentWhere = " AND su.userid IN (".implode(",",$_AllowedIds).")";
        }
    }

    // Fetch class name (keep spaces for display)
    $_SQL_CLASS = mysqli_query($con, "SELECT class_name FROM tblclassentry WHERE class_entryid='$_ClassId'");
    if ($row_cl = mysqli_fetch_array($_SQL_CLASS, MYSQLI_ASSOC)) {
        $_ClassName = $row_cl['class_name'];
    }

    // Fetch subject name (keep spaces for display)
    $_SQL_SUB = mysqli_query($con, "SELECT subject FROM tblsubject WHERE subjectid='$_SubjectId'");
    if ($row_sub = mysqli_fetch_array($_SQL_SUB, MYSQLI_ASSOC)) {
        $_SubjectName = $row_sub['subject'];
    }

    // Fetch batch name (keep spaces for display, fix dash to space)
    $_SQL_BATCH = mysqli_query($con, "SELECT batch FROM tblbatch WHERE batchid='$_BatchId'");
    if ($row_bch = mysqli_fetch_array($_SQL_BATCH, MYSQLI_ASSOC)) {
        // Replace dash with space for display
        $_BatchName = str_replace('-', ' ', $row_bch['batch']);
    }

    // Prepare filename with underscores replacing spaces for safety
    $filenameClassName = str_replace(' ', '_', $_ClassName);
    $filenameSubjectName = str_replace(' ', '_', $_SubjectName);
    $filename = "score_template_{$filenameClassName}_Term{$_Term}_{$filenameSubjectName}.xls";

    // Set headers
    header('Content-Type: application/vnd.ms-excel');
    header('X-Content-Type-Options: nosniff');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header('Cache-Control: max-age=0');

    // Output HTML table for Excel
    echo "<html><body><table border='1'>";
    echo "<tr>";
    $headers = ['userid', 'FirstName', 'Surname', 'Othername', 'class_name', 'semester', 'batch', 'subjectid', 'subject', 'class score', 'exams score'];
    foreach ($headers as $header) {
        echo "<th>$header</th>";
    }
    echo "</tr>";

    // Fetch data
    $_SQL_EXECUTE_VIEW = mysqli_query($con, "SELECT *, su.userid FROM tblsystemuser su 
        INNER JOIN tbltermregistry tr ON su.userid=tr.userid
        INNER JOIN tblsubjectassignment sa ON sa.classid=tr.class_entryid AND sa.batchid=tr.batchid AND sa.termname=tr.termname AND ".semester_registry_resolved_year_sql("tr")."=".semester_registry_assignment_year_sql("sa")."
        INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
        INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
        WHERE tr.class_entryid='$_ClassId' AND tr.batchid='$_BatchId' AND tr.termname='$_Term' 
        $_AcademicYearWhere
        AND su.systemtype='Student' AND sc.subjectid='$_SubjectId' AND sa.userid='$_SESSION[USERID]' $_AllowedStudentWhere");

    while ($row = mysqli_fetch_array($_SQL_EXECUTE_VIEW, MYSQLI_ASSOC)) {
        $_SQL = mysqli_query($con, "SELECT * FROM tblmark mk WHERE mk.userid='$row[userid]' AND mk.testtype='Class Score' AND mk.assignmentid='$row[assignmentid]'");
        if (mysqli_num_rows($_SQL) == 0) {
            echo "<tr>";
            $data = [
                $row['userid'],
                $row['firstname'],
                $row['surname'],
                $row['othernames'],
                $_ClassName,  // Display with spaces
                $_Term,
                $_BatchName,  // Now displays with spaces, e.g. "September 2023"
                $row['subjectid'],
                $row['subject'],
                '', // Class Score
                ''  // Exams Score
            ];
            foreach ($data as $value) {
                echo "<td>$value</td>";
            }
            echo "</tr>";
        }
    }

    echo "</table></body></html>";
    exit; // Exit to prevent further output
}
?>

<?php
include("dbstring.php");
@$_Mark = isset($_POST['marks']) ? $_POST['marks'] : [];
@$_AssignmentId = $_POST['assignmentid'];
@$_UserId = isset($_POST['userid']) ? $_POST['userid'] : [];
@$_TotalMark = isset($_POST['totalscore']) ? $_POST['totalscore'] : 0;
@$_Recordedby = $_SESSION['USERID'];

if (isset($_POST['download_score_template']) && is_array($_Mark)) {
    @$_CheckMark = 0;
    foreach ($_Mark as $_Selected_Mark) {
        if ($_Selected_Mark > $_TotalMark) {
            $_CheckMark = 1;
        }
    }
    if ($_CheckMark == 1) {
        $_SESSION['Message'] = $_SESSION['Message'] . "<div style='color:red;padding:10px;background-color:white;'>Total Mark is less than the mark entered</div>";
    } else {
        $_TotalUsers = is_array($_UserId) ? count($_UserId) : 0;
        for ($k = 0; $k < $_TotalUsers; $k++) {
            $_Selected_User = $_UserId[$k];
            $_Selected_Mark = $_Mark[$k];

            include("code.php");
            @$_MarkId = $code;
            @$_UserFullname = "";

            $_SQL_EXECUTE_USER_2 = mysqli_query($con, "SELECT * FROM tblsystemuser su WHERE su.userid='$_Selected_User'");
            if ($row_u_2 = mysqli_fetch_array($_SQL_EXECUTE_USER_2, MYSQLI_ASSOC)) {
                $_UserFullname = $row_u_2['firstname'] . " " . $row_u_2['othernames'] . " " . $row_u_2['surname'] . " (" . $row_u_2['userid'] . ")";
            }
            $_SQL_EXECUTE = mysqli_query($con, "INSERT INTO tblmark(markid, assignmentid, userid, testtype, mark, totalmark, datetimeentry, status, recordedby)
                VALUES('$_MarkId', '$_AssignmentId', '$_Selected_User', 'Class Score', '$_Selected_Mark', '$_TotalMark', NOW(), 'active', '$_Recordedby')");
            if ($_SQL_EXECUTE) {
                $_SESSION['Message'] = $_SESSION['Message'] . "<div style='color:green;text-align:left;background-color:white'><i class='fa fa-check' style='color:green'></i> $_Selected_Mark Successfully Stored for $_User Fullname</div>";
            } else {
                $_Error = mysqli_error($con);
                $_SESSION['Message'] = $_SESSION['Message'] . "<div style='color:red'>Mark failed to save,$_Error</div>";
            }
        }
    }
}
?>

<?php
include("dbstring.php");
@$_Update_subject = $_POST['update_item'];
@$_Update_subjectid = $_POST['update_subjectid'];

if (isset($_POST['update_item_entry'])) {
    $_SQL_EXECUTE = mysqli_query($con, "UPDATE tblsubject SET subject='$_Update_subject' WHERE subjectid='$_Update_subjectid'");
    if ($_SQL_EXECUTE) {
        $_SESSION['Message'] = "<div style='color:green;text-align:center;background-color:white'>Subject Successfully Updated</div>";
    } else {
        $_Error = mysqli_error($con);
        $_SESSION['Message'] = "<div style='color:red'>Subject failed to update,$_Error</div>";
    }
}
?>

<?php
include("dbstring.php");

if (isset($_GET["assign_subject"])) {
    $_SQL_EXECUTE = mysqli_query($con, "DELETE FROM tblsubjectclassification WHERE classificationid='$_GET[assign_subject]'");
    if ($_SQL_EXECUTE) {
        $_SESSION['Message'] = "<div style='color:maroon;text-align:center;background-color:white'>Subject Successfully Deleted</div>";
    } else {
        $_Error = mysqli_error($con);
        $_SESSION['Message'] = "<div style='color:red'>Subject failed to delete,Error:$_Error</div>";
    }
}
?>

<html>
<head>
<?php
include("links.php");
?>

<script type="text/javascript">
function selectAll() {
    var selall = document.getElementById("all").checked;
    if (selall == true) {
        checkBox();
    } else if (selall == false) {
        uncheckBox();
    }
}

function uncheckBox() {
    var inputs = document.getElementsByName("userid[]");
    for (var i = 0; i < inputs.length; i++) {
        inputs[i].checked = false;
    }
    return false;
}

function checkBox() {
    var inputs = document.getElementsByName("userid[]");
    for (var i = 0; i < inputs.length; i++) {
        inputs[i].checked = true;
    }
    return false;
}
</script>
</head>
<body>
    <div class="header">
        <?php
        include("menu.php");
        ?>
    </div>
    <div class="main-platform" style="background-color:white">
        <table width="100%">
            <tr>
                <td width="40%">
                    <div class="form-entry">
                        <form id="formID" name="formID" method="post" action="download-classexamscore-template.php">
                            <h4>DOWNLOAD CLASS & EXAM SCORES TEMPLATE</h4>
                            <?php
                            include("dbstring.php");
                            $_SQL_2 = mysqli_query($con, "SELECT * FROM tblsubjectassignment sa 
                                INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid 
                                INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
                                INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
                                INNER JOIN tblbatch bch ON bch.batchid=sa.batchid
                                WHERE sa.userid='$_SESSION[USERID]' ORDER BY sa.termname ASC");

                            echo "<table>";
                            echo "<thead><th>CLASS</th><th>SEM.</th><th>SUBJECT</th><th>TASK</th></thead>";
                            while ($row = mysqli_fetch_array($_SQL_2, MYSQLI_ASSOC)) {
                                echo "<tr><td>";
                                echo $row['class_name'];
                                echo "</td>";
                                echo "<td align='center'>";
                                echo $row['termname'];
                                echo "</td>";
                                echo "<td>";
                                echo $row['subject'] . "(" . $row['subjectid'] . ") - " . semester_registry_session_label(date('Y-m-d H:i:s', strtotime($row['datetimeentry'])), $row['batch'], $row['termname']);
                                echo "</td>";
                                echo "<td align='center'>";
                                echo "<a href='download-classexamscore-template.php?class_ID=$row[class_entryid]&term_ID=$row[termname]&batch_ID=$row[batchid]&subject_ID=$row[subjectid]&year_ID=".date('Y', strtotime($row['datetimeentry']))."'><i class='fa fa-plus' style='color:blue'></i></a>";
                                echo "</td>";
                                echo "</tr>";
                            }
                            echo "</table>";
                            ?>
                        </form>
                    </div>
                </td>
                <td width="60%">
                    <div class="form-entry">
                        <form id="formID2" name="formID2" method="post" action="download-classexamscore-template.php">
                            <?php
                            echo $_SESSION['Message'];
                            include("dbstring.php");
                            @$serial = 0;

                            if (isset($_GET['class_ID'])) {
                                @$_BatchId = $_GET['batch_ID'];
                                @$_ClassId = $_GET['class_ID'];
                                @$_Term = $_GET['term_ID'];
                                @$_SubjectId = $_GET['subject_ID'];
                                @$_AcademicYear = semester_registry_normalize_year($_GET['year_ID'] ?? '');
                                @$_ClassName = "";
                                @$_BatchName = "";
                                $_AcademicYearWhere = "";
                                $_AllowedStudentWhere = " AND 1=0";
                                if($_AcademicYear!==""){
                                    $_AcademicYearWhere = " AND ".semester_registry_resolved_year_sql("tr")."='".mysqli_real_escape_string($con,$_AcademicYear)."'";
                                }

                                $_AssignmentScopeSql=mysqli_query($con,"SELECT sa.assignmentid, sa.classid, sa.batchid, sa.termname, ".semester_registry_assignment_year_sql("sa")." AS assignment_year
                                    FROM tblsubjectassignment sa
                                    INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
                                    WHERE sa.classid='$_ClassId'
                                    AND sa.batchid='$_BatchId'
                                    AND sa.termname='$_Term'
                                    AND sc.subjectid='$_SubjectId'
                                    AND sa.userid='$_SESSION[USERID]'
                                    AND ".semester_registry_assignment_year_sql("sa")."='".mysqli_real_escape_string($con,$_AcademicYear)."'
                                    LIMIT 1");
                                if($_AssignmentScopeSql && ($_AssignmentScopeRow=mysqli_fetch_array($_AssignmentScopeSql,MYSQLI_ASSOC))){
                                    $_AssignmentStudentContext = score_entry_assignment_student_context(
                                        $con,
                                        $_AssignmentScopeRow['assignmentid'],
                                        $_AssignmentScopeRow['classid'],
                                        $_AssignmentScopeRow['batchid'],
                                        $_AssignmentScopeRow['assignment_year'],
                                        $_AssignmentScopeRow['termname']
                                    );
                                    if(count($_AssignmentStudentContext['userids'])>0){
                                        $_AllowedIds=array();
                                        foreach($_AssignmentStudentContext['userids'] as $_AllowedStudentId){
                                            $_AllowedIds[]="'".mysqli_real_escape_string($con,trim((string)$_AllowedStudentId))."'";
                                        }
                                        $_AllowedStudentWhere = " AND su.userid IN (".implode(",",$_AllowedIds).")";
                                    }
                                }

                                $_SQL_EXECUTE_VIEW = mysqli_query($con, "SELECT *, su.userid FROM tblsystemuser su 
                                    INNER JOIN tbltermregistry tr ON su.userid=tr.userid
                                    INNER JOIN tblsubjectassignment sa ON sa.classid=tr.class_entryid AND sa.batchid=tr.batchid AND sa.termname=tr.termname AND ".semester_registry_resolved_year_sql("tr")."=".semester_registry_assignment_year_sql("sa")."
                                    INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
                                    INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
                                    WHERE tr.class_entryid='$_ClassId' AND tr.batchid='$_BatchId' AND tr.termname='$_Term' 
                                    $_AcademicYearWhere
                                    AND su.systemtype='Student' AND sc.subjectid='$_SubjectId' AND sa.userid='$_SESSION[USERID]' $_AllowedStudentWhere");

                                $_SQL_CLASS = mysqli_query($con, "SELECT * FROM tblclassentry WHERE class_entryid='$_GET[class_ID]'");
                                if ($row_cl = mysqli_fetch_array($_SQL_CLASS, MYSQLI_ASSOC)) {
                                    $_ClassName = $row_cl['class_name'];
                                }

                                $_SQL_BATCH = mysqli_query($con, "SELECT * FROM tblbatch WHERE batchid='$_GET[batch_ID]'");
                                if ($row_bch = mysqli_fetch_array($_SQL_BATCH, MYSQLI_ASSOC)) {
                                    $_BatchName = $row_bch['batch'];
                                }

                                echo "<div align='right'>
                                    <a href='download-classexamscore-template.php?class_ID={$_ClassId}&term_ID={$_Term}&batch_ID={$_BatchId}&subject_ID={$_SubjectId}&download=xls' class='button-save' style='display:inline-block;padding:8px 16px;background:#2563eb;color:#fff;border-radius:6px;text-decoration:none;'>
                                        <i class='fa fa-download' style='color:white'></i> DOWNLOAD " . strtoupper($_ClassName) . " SCORE TEMPLATE
                                    </a>
                                </div><br/>";
                                echo "<table width='100%' style='background-color:white'>";
                                echo "<caption>";
                                if ($row_ss = mysqli_fetch_array($_SQL_EXECUTE_VIEW, MYSQLI_ASSOC)) {
                                    echo strtoupper($_ClassName) . " : SEMESTER " . $_Term . " : " . strtoupper($row_ss['subject']) . " ---BATCH: " . strtoupper($_BatchName);
                                }
                                echo "</caption>";

                                // Rewind result set to display table
                                mysqli_data_seek($_SQL_EXECUTE_VIEW, 0);

                                // Updated header order
                                echo "<thead>
                                    <th>*</th>
                                    <th>userid</th>
                                    <th>FirstName</th>
                                    <th>Surname</th>
                                    <th>Othername</th>
                                    <th>class_name</th>
                                    <th>semester</th>
                                    <th>batch</th>
                                    <th>subjectid</th>
                                    <th>subject</th>
                                    <th>class score</th>
                                    <th>exams score</th>
                                </thead>";
                                echo "<tbody>";
                                while ($row = mysqli_fetch_array($_SQL_EXECUTE_VIEW, MYSQLI_ASSOC)) {
                                    $_SQL = mysqli_query($con, "SELECT * FROM tblmark mk WHERE mk.userid='$row[userid]' AND mk.testtype='Class Score' AND mk.assignmentid='$row[assignmentid]'");
                                    if (mysqli_num_rows($_SQL) == 0) {
                                        echo "<tr>";
                                        echo "<input type='hidden' id='assignmentid' name='assignmentid' value='$row[assignmentid]' />";
                                        echo "<td align='center'>" . ($serial = $serial + 1) . "</td>";
                                        echo "<td align='center'>" . $row['userid'] . "</td>";
                                        echo "<td align='center'>" . $row['firstname'] . "</td>";
                                        echo "<td align='center'>" . $row['surname'] . "</td>";
                                        echo "<td align='center'>" . $row['othernames'] . "</td>";
                                        echo "<td align='center'>" . $_ClassName . "</td>";
                                        echo "<td align='center'>" . $_Term . "</td>";
                                        echo "<td align='center'>" . $_BatchName . "</td>";
                                        echo "<td align='center'>" . $row['subjectid'] . "</td>";
                                        echo "<td align='center'>" . $row['subject'] . "</td>";
                                        echo "<td align='center'></td>"; // Class Score
                                        echo "<td align='center'></td>"; // Exams Score
                                        echo "</tr>";
                                    }
                                }
                                echo "</tbody>";
                                echo "</table>";
                            }
                            ?>
                        </form>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
