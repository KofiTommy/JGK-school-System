<?php
session_start();

include("dbstring.php");
include("check-login.php");
include("audit_notifications.php");
include("class-teacher-utils.php");
include_once("score-entry-utils.php");
include_once("semester-registry-utils.php");
require_once("simplexlsx.class.php");
semester_registry_ensure_academic_year_column($con);

if(!(class_teacher_is_teacher() || class_teacher_is_admin())){
    header("location:".class_teacher_landing_page());
    exit();
}

$redirectPage = isset($redirectPage) ? (string)$redirectPage : "upload-score-entry.php";
$scoreType = isset($scoreType) ? (string)$scoreType : "Score";
$scoreLabel = isset($scoreLabel) ? (string)$scoreLabel : "Score";
$scoreLimit = isset($scoreLimit) ? (int)$scoreLimit : 100;
$auditAction = isset($auditAction) ? (string)$auditAction : "SCORE_UPLOAD";

$teacherId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : "";
$selectedClassId = trim((string)(isset($_POST['class_ID']) ? $_POST['class_ID'] : ''));
$selectedTermId = trim((string)(isset($_POST['term_ID']) ? $_POST['term_ID'] : ''));
$selectedBatchId = trim((string)(isset($_POST['batch_ID']) ? $_POST['batch_ID'] : ''));
$selectedSubjectId = trim((string)(isset($_POST['subject_ID']) ? $_POST['subject_ID'] : ''));
$selectedYearId = semester_registry_normalize_year(isset($_POST['year_ID']) ? $_POST['year_ID'] : '');
$totalMark = trim((string)(isset($_POST['totalscore']) ? $_POST['totalscore'] : ''));
$assignmentId = trim((string)(isset($_POST['assignment-id']) ? $_POST['assignment-id'] : ''));

$redirectUrl = score_entry_build_url(
    $redirectPage,
    $selectedClassId,
    $selectedTermId,
    $selectedBatchId,
    $selectedSubjectId,
    $totalMark,
    $selectedYearId
);

if($redirectUrl === $redirectPage){
    $redirectUrl = $redirectPage;
}

function score_upload_finish($redirectUrl, $messages){
    $_SESSION['Message'] = implode("", $messages);
    header("location:".$redirectUrl);
    exit();
}

function score_upload_parse_tsv_rows($filePath){
    $content = @file_get_contents($filePath);
    if($content === false){
        return array();
    }
    $content = preg_replace('/^\xEF\xBB\xBF/', '', (string)$content);
    $content = trim((string)$content);
    if($content === ''){
        return array();
    }
    $lines = preg_split("/\r\n|\n|\r/", $content);
    $rows = array();
    foreach($lines as $line){
        $line = rtrim((string)$line);
        if($line === ''){
            continue;
        }
        $rows[] = str_getcsv($line, "\t");
    }
    return $rows;
}

function score_upload_read_rows($filePath, &$formatLabel, &$errorMessage){
    $formatLabel = "";
    $errorMessage = "";

    $xlsx = new SimpleXLSX($filePath);
    if($xlsx->success()){
        $rows = $xlsx->rows();
        if(is_array($rows) && count($rows) > 0){
            $formatLabel = "xlsx";
            return $rows;
        }
    }else{
        $errorMessage = (string)$xlsx->error();
    }

    $tsvRows = score_upload_parse_tsv_rows($filePath);
    if(count($tsvRows) > 0){
        $formatLabel = "tsv";
        $errorMessage = "";
        return $tsvRows;
    }

    return array();
}

function score_upload_row_value($row, $index){
    return isset($row[$index]) ? trim((string)$row[$index]) : "";
}

$messages = array();

if($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['submit_group_data'])){
    $messages[] = score_entry_alert("warning", "Open the upload sheet first, then choose a file to import.");
    score_upload_finish($redirectUrl, $messages);
}

if($teacherId === ""){
    $messages[] = score_entry_alert("error", "Your teacher session could not be confirmed. Please log in again.");
    score_upload_finish($redirectUrl, $messages);
}

if($assignmentId === "" || $selectedClassId === "" || $selectedBatchId === "" || $selectedTermId === "" || $selectedSubjectId === "" || $selectedYearId === ""){
    $messages[] = score_entry_alert("error", "The selected class, semester, batch, subject, or year is missing. Re-open the upload sheet and try again.");
    score_upload_finish($redirectUrl, $messages);
}

if($totalMark === "" || !is_numeric($totalMark) || (float)$totalMark <= 0 || (float)$totalMark > (float)$scoreLimit){
    $messages[] = score_entry_alert("warning", "Enter a valid total score between 0 and ".$scoreLimit." before uploading.");
    score_upload_finish($redirectUrl, $messages);
}

if(!isset($_FILES['file1']) || !is_uploaded_file($_FILES['file1']['tmp_name'])){
    $messages[] = score_entry_alert("error", "Choose an Excel workbook before uploading.");
    score_upload_finish($redirectUrl, $messages);
}

$assignmentIdSafe = mysqli_real_escape_string($con, $assignmentId);
$teacherIdSafe = mysqli_real_escape_string($con, $teacherId);
$selectedSubjectSafe = mysqli_real_escape_string($con, $selectedSubjectId);
$selectedYearSafe = mysqli_real_escape_string($con, $selectedYearId);

$assignmentSql = "SELECT
        sa.assignmentid,
        sa.classid,
        sa.batchid,
        sa.termname,
        ".semester_registry_assignment_year_sql("sa")." AS assignment_year,
        sc.subjectid,
        sub.subject,
        ce.class_name,
        COALESCE(bh.batch, '') AS batch
    FROM tblsubjectassignment sa
    INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
    INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid
    INNER JOIN tblclassentry ce ON ce.class_entryid=sa.classid
    LEFT JOIN tblbatch bh ON bh.batchid=sa.batchid
    WHERE sa.assignmentid='$assignmentIdSafe'
      AND sa.userid='$teacherIdSafe'
      AND sc.subjectid='$selectedSubjectSafe'
      AND ".semester_registry_assignment_year_sql("sa")."='$selectedYearSafe'
    LIMIT 1";

$assignmentRes = mysqli_query($con, $assignmentSql);
$assignmentRow = $assignmentRes ? mysqli_fetch_array($assignmentRes, MYSQLI_ASSOC) : false;
if(!$assignmentRow){
    $messages[] = score_entry_alert("error", "That upload sheet no longer belongs to your account. Please reopen the subject card and try again.");
    score_upload_finish($redirectUrl, $messages);
}

$assignmentStudentContext = score_entry_assignment_student_context(
    $con,
    $assignmentRow['assignmentid'],
    $assignmentRow['classid'],
    $assignmentRow['batchid'],
    $assignmentRow['assignment_year'],
    $assignmentRow['termname']
);
$allowedStudentIds = array_flip($assignmentStudentContext['userids']);
if(count($allowedStudentIds) === 0){
    $messages[] = score_entry_alert("warning", "No students are registered for this upload sheet yet, so nothing can be imported.");
    score_upload_finish($redirectUrl, $messages);
}

$formatLabel = "";
$parseError = "";
$rows = score_upload_read_rows($_FILES['file1']['tmp_name'], $formatLabel, $parseError);
if(count($rows) === 0){
    $messages[] = score_entry_alert("error", "The uploaded workbook could not be read. Save the file as `.xlsx` or upload the original template export, then try again.");
    if($parseError !== ""){
        $messages[] = score_entry_alert("info", "Import detail: ".$parseError);
    }
    score_upload_finish($redirectUrl, $messages);
}

$dataRows = array();
$blankMarkCount = 0;
$invalidMarkCount = 0;
$subjectMismatchCount = 0;

foreach($rows as $row){
    $userId = score_upload_row_value($row, 0);
    if($userId === "" || strtolower($userId) === "userid"){
        continue;
    }

    $rowSubjectId = score_upload_row_value($row, 4);
    $rowMark = score_upload_row_value($row, 6);

    if($rowSubjectId === "" || $rowSubjectId !== $selectedSubjectId){
        $subjectMismatchCount++;
        continue;
    }

    if($rowMark === ""){
        $blankMarkCount++;
        continue;
    }

    if(!is_numeric($rowMark) || (float)$rowMark < 0 || (float)$rowMark > (float)$totalMark || (float)$rowMark > (float)$scoreLimit){
        $invalidMarkCount++;
        continue;
    }

    $dataRows[] = array(
        'userid' => $userId,
        'mark' => $rowMark,
    );
}

if($subjectMismatchCount > 0){
    $messages[] = score_entry_alert("error", "The uploaded file does not match the selected subject sheet. Open the correct template for this subject and try again.");
    score_upload_finish($redirectUrl, $messages);
}

if($invalidMarkCount > 0){
    $messages[] = score_entry_alert("error", $invalidMarkCount." row(s) contain invalid marks. Check that every score is numeric, not negative, not above ".$scoreLimit.", and not greater than the total score.");
    score_upload_finish($redirectUrl, $messages);
}

if(count($dataRows) === 0){
    $messages[] = score_entry_alert("warning", "No score rows were found in the uploaded workbook. Fill the last score column and try again.");
    if($blankMarkCount > 0){
        $messages[] = score_entry_alert("info", $blankMarkCount." row(s) were skipped because the score cell was blank.");
    }
    score_upload_finish($redirectUrl, $messages);
}

$savedCount = 0;
$duplicateCount = 0;
$unregisteredCount = 0;
$errorCount = 0;

foreach($dataRows as $rowData){
    $selectedUser = trim((string)$rowData['userid']);
    if($selectedUser === ""){
        continue;
    }

    if(!isset($allowedStudentIds[$selectedUser])){
        $unregisteredCount++;
        continue;
    }

    $selectedUserSafe = mysqli_real_escape_string($con, $selectedUser);
    $duplicateSql = "SELECT 1 FROM tblmark
        WHERE assignmentid='$assignmentIdSafe'
          AND userid='$selectedUserSafe'
          AND testtype='$scoreType'
        LIMIT 1";
    $duplicateRes = mysqli_query($con, $duplicateSql);
    if($duplicateRes && mysqli_num_rows($duplicateRes) > 0){
        $duplicateCount++;
        continue;
    }

    include("code.php");
    $markIdSafe = mysqli_real_escape_string($con, (string)$code);
    $markSafe = mysqli_real_escape_string($con, (string)$rowData['mark']);
    $totalMarkSafe = mysqli_real_escape_string($con, $totalMark);
    $recordedBySafe = mysqli_real_escape_string($con, $teacherId);

    $insertSql = "INSERT INTO tblmark(markid,assignmentid,userid,testtype,mark,totalmark,datetimeentry,status,recordedby)
        VALUES('$markIdSafe','$assignmentIdSafe','$selectedUserSafe','$scoreType','$markSafe','$totalMarkSafe',NOW(),'active','$recordedBySafe')";
    $insertRes = mysqli_query($con, $insertSql);
    if($insertRes){
        $savedCount++;
    }else{
        $errorCount++;
    }
}

if($savedCount > 0){
    $messages[] = score_entry_alert("success", "Uploaded ".$scoreLabel." for ".$savedCount." student(s).");
}
if($duplicateCount > 0){
    $messages[] = score_entry_alert("warning", $duplicateCount." row(s) were skipped because those students already have saved ".$scoreLabel." in this sheet.");
}
if($unregisteredCount > 0){
    $messages[] = score_entry_alert("warning", $unregisteredCount." row(s) were skipped because the students are not registered for this subject in the selected session.");
}
if($blankMarkCount > 0){
    $messages[] = score_entry_alert("info", $blankMarkCount." row(s) were ignored because the score cell was blank.");
}
if($errorCount > 0){
    $messages[] = score_entry_alert("error", $errorCount." row(s) could not be saved due to a server error.");
}
if($formatLabel === "tsv"){
    $messages[] = score_entry_alert("info", "Imported the workbook from the template's tab-based format. Saving the completed sheet as `.xlsx` is still recommended for future uploads.");
}
if($savedCount === 0 && empty($messages)){
    $messages[] = score_entry_alert("info", "No new scores were uploaded from this workbook.");
}

if($savedCount > 0 && isset($_SESSION['SYSTEMTYPE']) && $_SESSION['SYSTEMTYPE'] === "Teacher"){
    logSystemChange(
        $con,
        $auditAction,
        "Teacher uploaded ".$scoreType." rows. Saved: ".$savedCount.", Duplicates: ".$duplicateCount.", Unregistered: ".$unregisteredCount.", Assignment: ".$assignmentId.", Total: ".$totalMark
    );
}

score_upload_finish($redirectUrl, $messages);
