<?php
session_start();
register_shutdown_function(function () {
    $lastError = error_get_last();
    if (!$lastError) {
        return;
    }
    $fatalTypes = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
    if (!in_array($lastError['type'], $fatalTypes, true)) {
        return;
    }
    $message = "Score entry fatal: ".$lastError['message']." in ".basename($lastError['file']).":".$lastError['line'];
    $logFile = __DIR__.DIRECTORY_SEPARATOR."score-entry-fatal.log";
    @file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] ".$message.PHP_EOL, FILE_APPEND);
    if (!headers_sent()) {
        @http_response_code(500);
        @header('Content-Type: text/plain; charset=utf-8');
    }
    echo $message;
});
include("dbstring.php");
include("check-login.php");
include("audit_notifications.php");
include("score-entry-utils.php");
if(file_exists(__DIR__.DIRECTORY_SEPARATOR."semester-registry-utils.php")){
    include_once("semester-registry-utils.php");
}
if(!function_exists('semester_registry_column_exists')){
    function semester_registry_column_exists($con, $tableName, $columnName){
        $tableSafe = mysqli_real_escape_string($con, (string)$tableName);
        $columnSafe = mysqli_real_escape_string($con, (string)$columnName);
        $sql = "SHOW COLUMNS FROM `".$tableSafe."` LIKE '".$columnSafe."'";
        $result = mysqli_query($con, $sql);
        return ($result && mysqli_num_rows($result) > 0);
    }
}
if(!function_exists('semester_registry_ensure_academic_year_column')){
    function semester_registry_ensure_academic_year_column($con){
        if(!semester_registry_column_exists($con, 'tbltermregistry', 'academicyear')){
            @mysqli_query($con, "ALTER TABLE tbltermregistry ADD COLUMN academicyear VARCHAR(10) NOT NULL DEFAULT '' AFTER batchid");
            @mysqli_query($con, "UPDATE tbltermregistry SET academicyear=CAST(YEAR(datetimeentry) AS CHAR) WHERE TRIM(COALESCE(academicyear,''))=''");
        }
    }
}
if(!function_exists('semester_registry_normalize_year')){
    function semester_registry_normalize_year($value){
        $value = trim((string)$value);
        if($value === '' || preg_match('/^\d{4}$/', $value) !== 1){
            return '';
        }
        return $value;
    }
}
if(!function_exists('semester_registry_resolved_year_sql')){
    function semester_registry_resolved_year_sql($alias = 'tr'){
        return "COALESCE(NULLIF(TRIM(CONVERT(".$alias.".academicyear USING utf8mb4)),''), DATE_FORMAT(".$alias.".datetimeentry, '%Y'))";
    }
}
if(!function_exists('semester_registry_assignment_year_sql')){
    function semester_registry_assignment_year_sql($alias = 'sa'){
        return "DATE_FORMAT(".$alias.".datetimeentry, '%Y')";
    }
}
semester_registry_ensure_academic_year_column($con);

$pageFile = "exam-score-entry.php";
$pageTitle = "Exam Score Entry";
$pageDescription = "Open an assigned exam sheet, enter scores quickly, and keep the scoring flow readable and touch-friendly for teachers on mobile.";
$scoreType = "Exam Score";
$scoreLabel = "Exam Score";
$saveLabel = "Save Exam Scores";
$uploadPage = "upload-exam-score-entry.php";
$scoreLimit = 70;

$teacherId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : "";
$teacherIdSafe = mysqli_real_escape_string($con, $teacherId);

$selectedClassId = trim((string)(isset($_GET['class_ID']) ? $_GET['class_ID'] : (isset($_POST['class_ID']) ? $_POST['class_ID'] : '')));
$selectedTermId = trim((string)(isset($_GET['term_ID']) ? $_GET['term_ID'] : (isset($_POST['term_ID']) ? $_POST['term_ID'] : '')));
$selectedBatchId = trim((string)(isset($_GET['batch_ID']) ? $_GET['batch_ID'] : (isset($_POST['batch_ID']) ? $_POST['batch_ID'] : '')));
$selectedSubjectId = trim((string)(isset($_GET['subject_ID']) ? $_GET['subject_ID'] : (isset($_POST['subject_ID']) ? $_POST['subject_ID'] : '')));
$selectedYearId = semester_registry_normalize_year(isset($_GET['year_ID']) ? $_GET['year_ID'] : (isset($_POST['year_ID']) ? $_POST['year_ID'] : ''));
$prefillTotal = trim((string)(isset($_GET['prefill_total']) ? $_GET['prefill_total'] : ''));
if($prefillTotal === ''){
    $prefillTotal = (string)$scoreLimit;
}

if(isset($_POST['save_all_mark'])){
    $selectedClassId = trim((string)(isset($_POST['class_ID']) ? $_POST['class_ID'] : $selectedClassId));
    $selectedTermId = trim((string)(isset($_POST['term_ID']) ? $_POST['term_ID'] : $selectedTermId));
    $selectedBatchId = trim((string)(isset($_POST['batch_ID']) ? $_POST['batch_ID'] : $selectedBatchId));
    $selectedSubjectId = trim((string)(isset($_POST['subject_ID']) ? $_POST['subject_ID'] : $selectedSubjectId));
    $selectedYearId = semester_registry_normalize_year(isset($_POST['year_ID']) ? $_POST['year_ID'] : $selectedYearId);

    $messages = array();
    $marks = isset($_POST['marks']) && is_array($_POST['marks']) ? $_POST['marks'] : array();
    $selectedUsers = isset($_POST['userid']) && is_array($_POST['userid']) ? $_POST['userid'] : array();
    $assignmentId = trim((string)(isset($_POST['assignmentid']) ? $_POST['assignmentid'] : ''));
    $totalMark = trim((string)(isset($_POST['totalscore']) ? $_POST['totalscore'] : ''));
    $prefillTotal = $totalMark;

    $savedCount = 0;
    $duplicateCount = 0;
    $skippedCount = 0;
    $invalidCount = 0;
    $unregisteredCount = 0;
    $errorCount = 0;

    if($teacherId === ""){
        $messages[] = score_entry_alert("error", "Your teacher session could not be confirmed. Please log in again.");
    }elseif($assignmentId === "" || $selectedClassId === "" || $selectedBatchId === "" || $selectedTermId === "" || $selectedSubjectId === ""){
        $messages[] = score_entry_alert("error", "Choose a class, batch, semester, and subject before saving scores.");
    }elseif($totalMark === "" || !is_numeric($totalMark) || (float)$totalMark <= 0 || (float)$totalMark > (float)$scoreLimit){
        $messages[] = score_entry_alert("warning", "Enter a valid total exam score between 0 and ".$scoreLimit." before saving.");
    }elseif(count($selectedUsers) === 0){
        $messages[] = score_entry_alert("warning", "Select at least one student. Typing a mark will auto-select that student row.");
    }else{
        $assignmentSafe = mysqli_real_escape_string($con, $assignmentId);
        $assignmentAuth = mysqli_query($con, "SELECT assignmentid FROM tblsubjectassignment
            WHERE assignmentid='$assignmentSafe' AND userid='$teacherIdSafe' LIMIT 1");

        if(!$assignmentAuth || mysqli_num_rows($assignmentAuth) === 0){
            $messages[] = score_entry_alert("error", "That score sheet no longer belongs to your account. Please re-open the assignment and try again.");
        }else{
            $assignmentStudentContext = score_entry_assignment_student_context($con, $assignmentId, $selectedClassId, $selectedBatchId, $selectedYearId, $selectedTermId);
            $allowedStudentIds = array_flip($assignmentStudentContext['userids']);

            if(count($allowedStudentIds) === 0){
                $messages[] = score_entry_alert("warning", "No students have registered this course for the selected semester yet.");
            }else{
                foreach($selectedUsers as $selectedUserRaw){
                    $selectedUser = trim((string)$selectedUserRaw);
                    if($selectedUser === ""){
                        continue;
                    }

                    if(!isset($allowedStudentIds[$selectedUser])){
                        $unregisteredCount++;
                        continue;
                    }

                    $selectedMark = trim((string)(isset($marks[$selectedUser]) ? $marks[$selectedUser] : ''));
                    if($selectedMark === '' || !is_numeric($selectedMark)){
                        $skippedCount++;
                        continue;
                    }

                    if((float)$selectedMark < 0 || (float)$selectedMark > (float)$totalMark || (float)$selectedMark > (float)$scoreLimit){
                        $invalidCount++;
                        continue;
                    }

                    include("code.php");
                    $markId = mysqli_real_escape_string($con, (string)$code);
                    $selectedUserSafe = mysqli_real_escape_string($con, $selectedUser);
                    $selectedMarkSafe = mysqli_real_escape_string($con, $selectedMark);
                    $totalMarkSafe = mysqli_real_escape_string($con, $totalMark);
                    $recordedBySafe = mysqli_real_escape_string($con, $teacherId);

                    $duplicateRes = mysqli_query($con, "SELECT 1 FROM tblmark
                        WHERE assignmentid='$assignmentSafe'
                          AND userid='$selectedUserSafe'
                          AND testtype='$scoreType'
                        LIMIT 1");
                    if($duplicateRes && mysqli_num_rows($duplicateRes) > 0){
                        $duplicateCount++;
                        continue;
                    }

                    $insertRes = mysqli_query($con, "INSERT INTO tblmark(markid,assignmentid,userid,testtype,mark,totalmark,datetimeentry,status,recordedby)
                        VALUES('$markId','$assignmentSafe','$selectedUserSafe','$scoreType','$selectedMarkSafe','$totalMarkSafe',NOW(),'active','$recordedBySafe')");

                    if($insertRes){
                        $savedCount++;
                    }else{
                        $errorCount++;
                    }
                }
            }

            if($savedCount > 0){
                $messages[] = score_entry_alert("success", "Saved ".$scoreLabel." for ".$savedCount." student(s).");
            }
            if($duplicateCount > 0){
                $messages[] = score_entry_alert("warning", $duplicateCount." student(s) were skipped because ".$scoreLabel." already exists for them.");
            }
            if($skippedCount > 0){
                $messages[] = score_entry_alert("info", $skippedCount." selected student(s) were skipped because no valid mark was entered.");
            }
            if($invalidCount > 0){
        $messages[] = score_entry_alert("warning", $invalidCount." selected student(s) were skipped because the mark was negative, above ".$scoreLimit.", or greater than the total score.");
            }
            if($unregisteredCount > 0){
                $messages[] = score_entry_alert("warning", $unregisteredCount." selected student(s) were skipped because they did not register this course for the semester.");
            }
            if($errorCount > 0){
                $messages[] = score_entry_alert("error", $errorCount." score record(s) could not be saved due to a server error.");
            }

            if($savedCount > 0 && isset($_SESSION['SYSTEMTYPE']) && $_SESSION['SYSTEMTYPE'] === "Teacher"){
                logSystemChange(
                    $con,
                    "SCORE_ENTRY_EXAM",
                    "Teacher entered exam scores for ".$savedCount." student(s). Assignment: ".$assignmentId.", Total: ".$totalMark
                );
            }

            if(empty($messages)){
                $messages[] = score_entry_alert("info", "No scores were saved. Check that you entered marks for the selected students.");
            }
        }
    }

    $_SESSION['Message'] = implode("", $messages);
    header("location:".score_entry_build_url($pageFile, $selectedClassId, $selectedTermId, $selectedBatchId, $selectedSubjectId, $prefillTotal, $selectedYearId));
    exit();
}

$flashMessage = isset($_SESSION['Message']) ? (string)$_SESSION['Message'] : "";
$_SESSION['Message'] = "";

$assignments = array();
$selectedAssignment = null;
$assignmentCount = 0;
$pendingAssignmentCount = 0;
$completedAssignmentCount = 0;
$studentsAwaitingCount = 0;

$assignmentSql = "SELECT
        sa.assignmentid,
        sa.classid AS class_entryid,
        sa.batchid,
        sa.termname,
        sa.datetimeentry AS assignment_datetimeentry,
        ".semester_registry_assignment_year_sql("sa")." AS assignment_year,
        sc.subjectid,
        sub.subject,
        ce.class_name,
        COALESCE(bh.batch, '') AS batch,
        MAX(bh.datetimeentry) AS batch_sort,
        COUNT(DISTINCT CASE WHEN stu.userid IS NOT NULL THEN stu.userid END) AS total_students,
        COUNT(DISTINCT CASE WHEN mk.markid IS NOT NULL THEN mk.userid END) AS saved_students
    FROM tblsubjectassignment sa
    INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
    INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid
    INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
    LEFT JOIN tblbatch bh ON bh.batchid=sa.batchid
    LEFT JOIN tbltermregistry tr ON tr.class_entryid=sa.classid
        AND tr.batchid=sa.batchid
        AND tr.termname=sa.termname
        AND ".semester_registry_resolved_year_sql("tr")."=".semester_registry_assignment_year_sql("sa")."
    LEFT JOIN tblsystemuser stu ON stu.userid=tr.userid AND stu.systemtype='Student'
    LEFT JOIN tblmark mk ON mk.assignmentid=sa.assignmentid AND mk.userid=tr.userid AND mk.testtype='$scoreType'
    WHERE sa.userid='$teacherIdSafe' AND sa.status='active'
    GROUP BY sa.assignmentid, sa.classid, sa.batchid, sa.termname, sc.subjectid, sub.subject, ce.class_name, bh.batch
    ORDER BY batch_sort DESC, sa.termname DESC, ce.class_name ASC, sub.subject ASC";
$assignmentRes = mysqli_query($con, $assignmentSql);
if($assignmentRes){
    while($row = mysqli_fetch_array($assignmentRes, MYSQLI_ASSOC)){
        $studentContext = score_entry_assignment_student_context(
            $con,
            $row['assignmentid'],
            $row['class_entryid'],
            $row['batchid'],
            $row['assignment_year'],
            $row['termname']
        );
        $row['uses_course_registration'] = !empty($studentContext['uses_course_registration']);
        $row['roster_source'] = $row['uses_course_registration'] ? 'Course Registration' : 'Semester Registry';
        $row['total_students'] = count($studentContext['userids']);
        $row['saved_students'] = score_entry_saved_mark_count($con, $row['assignmentid'], $scoreType, $studentContext['userids']);
        $row['pending_students'] = max($row['total_students'] - $row['saved_students'], 0);
        $row['status_meta'] = score_entry_status_meta($row['total_students'], $row['saved_students']);
        $row['session_label'] = score_entry_session_label($row['assignment_datetimeentry'], $row['batch'], $row['termname'], $row['assignment_year']);
        $row['search_label'] = strtolower(trim($row['class_name']." ".$row['subject']." ".$row['session_label']." ".$row['batch']." semester ".$row['termname']));
        $assignments[] = $row;

        $assignmentCount++;
        $studentsAwaitingCount += $row['pending_students'];
        if($row['pending_students'] > 0){
            $pendingAssignmentCount++;
        }elseif($row['total_students'] > 0){
            $completedAssignmentCount++;
        }

        if(
            $selectedClassId !== "" &&
            $selectedBatchId !== "" &&
            $selectedTermId !== "" &&
            $selectedSubjectId !== "" &&
            $selectedClassId === (string)$row['class_entryid'] &&
            $selectedBatchId === (string)$row['batchid'] &&
            $selectedTermId === (string)$row['termname'] &&
            $selectedSubjectId === (string)$row['subjectid'] &&
            $selectedYearId === (string)$row['assignment_year']
        ){
            $selectedAssignment = $row;
        }
    }
}

$pendingStudents = array();

if($selectedAssignment){
    $assignmentIdSafe = mysqli_real_escape_string($con, (string)$selectedAssignment['assignmentid']);
    $assignmentStudentContext = score_entry_assignment_student_context(
        $con,
        $selectedAssignment['assignmentid'],
        $selectedAssignment['class_entryid'],
        $selectedAssignment['batchid'],
        $selectedAssignment['assignment_year'],
        $selectedAssignment['termname']
    );

    if(count($assignmentStudentContext['userids']) > 0){
        $userIdParts = array();
        foreach($assignmentStudentContext['userids'] as $studentId){
            $userIdParts[] = "'".mysqli_real_escape_string($con, (string)$studentId)."'";
        }
        $studentSql = "SELECT
                su.userid,
                su.firstname,
                su.othernames,
                su.surname,
                mk.markid AS existing_mark_id,
                mk.mark AS existing_mark
            FROM tblsystemuser su
            LEFT JOIN tblmark mk ON mk.assignmentid='$assignmentIdSafe'
                AND mk.userid=su.userid
                AND mk.testtype='$scoreType'
            WHERE su.userid IN (".implode(',', $userIdParts).")
            ORDER BY su.firstname ASC, su.othernames ASC, su.surname ASC, su.userid ASC";

        $studentRes = mysqli_query($con, $studentSql);
        if($studentRes){
            while($row = mysqli_fetch_array($studentRes, MYSQLI_ASSOC)){
                if(trim((string)$row['existing_mark_id']) === ""){
                    $pendingStudents[] = $row;
                }
            }
        }
    }
}

$scoresReportUrl = "#";
$uploadUrl = "#";
if($selectedAssignment){
    $scoresReportUrl = "scores-report.php?class_id=".urlencode((string)$selectedAssignment['class_entryid'])
        ."&term_id=".urlencode((string)$selectedAssignment['termname'])
        ."&subject_id=".urlencode((string)$selectedAssignment['subjectid'])
        ."&batchid=".urlencode((string)$selectedAssignment['batchid'])
        ."&year_batch=".urlencode((string)$selectedAssignment['assignment_year']);
    $uploadUrl = score_entry_build_url($uploadPage, (string)$selectedAssignment['class_entryid'], (string)$selectedAssignment['termname'], (string)$selectedAssignment['batchid'], (string)$selectedAssignment['subjectid'], "", (string)$selectedAssignment['assignment_year']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/teacher-score-entry.css">
<script type="text/javascript" src="scripts/teacher-score-entry.js" defer></script>
</head>
<body class="score-entry-page score-entry-page--exam">
<div class="header"><?php include("menu.php"); ?></div>
<main class="score-entry-shell">
    <section class="score-entry-hero">
        <div class="score-entry-hero__copy">
            <span class="score-entry-kicker">Teacher Score Workspace</span>
            <h1><?php echo score_entry_esc($pageTitle); ?></h1>
            <p><?php echo score_entry_esc($pageDescription); ?></p>
            <div class="score-entry-hero__stats">
                <article class="score-entry-stat-card"><span>Assignments</span><strong><?php echo (int)$assignmentCount; ?></strong></article>
                <article class="score-entry-stat-card"><span>Pending Sheets</span><strong><?php echo (int)$pendingAssignmentCount; ?></strong></article>
                <article class="score-entry-stat-card"><span>Students Awaiting</span><strong><?php echo (int)$studentsAwaitingCount; ?></strong></article>
                <article class="score-entry-stat-card"><span>Completed Sheets</span><strong><?php echo (int)$completedAssignmentCount; ?></strong></article>
            </div>
        </div>
        <aside class="score-entry-hero__aside">
            <div class="score-entry-tip-card">
                <span class="score-entry-tip-card__eyebrow">Teacher Tips</span>
                <h2>Made easier for exam days</h2>
                <ul>
                    <li>Search and open any assigned exam sheet without digging through a large table.</li>
                    <li>Keep the selected exam sheet open after saving so you can continue without losing context.</li>
                    <li>Type an exam score and the student row auto-selects itself for saving.</li>
                    <li>Exam score cannot be more than <?php echo (int)$scoreLimit; ?> marks for any student.</li>
                    <li>Use the Scores Report link to review saved records once entry is complete.</li>
                </ul>
            </div>
        </aside>
    </section>

    <?php if($flashMessage !== ""){ ?>
    <div class="score-entry-flash"><?php echo $flashMessage; ?></div>
    <?php } ?>

    <div class="score-entry-layout">
        <section class="score-entry-panel">
            <div class="score-entry-panel__header">
                <div>
                    <span class="score-entry-panel__eyebrow">Assignments</span>
                    <h2>Pick a class and subject</h2>
                </div>
            </div>

            <div class="score-entry-assignment-search">
                <input
                    type="search"
                    id="assignmentSearch"
                    class="score-entry-search"
                    placeholder="Search by class, subject, or session"
                    autocomplete="off">
            </div>

            <?php if(count($assignments) > 0){ ?>
            <div class="score-entry-assignment-grid" id="assignmentGrid">
                <?php foreach($assignments as $assignment){ ?>
                    <?php
                    $isActive = $selectedAssignment && $selectedAssignment['assignmentid'] === $assignment['assignmentid'];
                    $cardUrl = score_entry_build_url(
                        $pageFile,
                        (string)$assignment['class_entryid'],
                        (string)$assignment['termname'],
                        (string)$assignment['batchid'],
                        (string)$assignment['subjectid'],
                        $prefillTotal,
                        (string)$assignment['assignment_year']
                    );
                    ?>
                    <a
                        href="<?php echo score_entry_esc($cardUrl); ?>"
                        class="score-entry-assignment-card<?php echo $isActive ? " score-entry-assignment-card--active" : ""; ?>"
                        data-assignment-label="<?php echo score_entry_esc($assignment['search_label']); ?>">
                        <div class="score-entry-assignment-card__top">
                            <div>
                                <h3><?php echo score_entry_esc($assignment['class_name']); ?></h3>
                                <p><?php echo score_entry_esc($assignment['subject']); ?></p>
                            </div>
                            <span class="<?php echo score_entry_esc($assignment['status_meta']['class']); ?>"><?php echo score_entry_esc($assignment['status_meta']['label']); ?></span>
                        </div>
                        <div class="score-entry-chip-row">
                            <span class="score-entry-chip score-entry-chip--accent"><?php echo score_entry_esc($assignment['session_label']); ?></span>
                        </div>
                        <div class="score-entry-assignment-card__bottom">
                            <div class="score-entry-meta-row">
                                <span><strong><?php echo (int)$assignment['total_students']; ?></strong> students</span>
                                <span><strong><?php echo (int)$assignment['saved_students']; ?></strong> saved</span>
                            </div>
                            <div class="score-entry-meta-row">
                                <span><strong><?php echo (int)$assignment['pending_students']; ?></strong> pending</span>
                            </div>
                        </div>
                    </a>
                <?php } ?>
            </div>
            <?php } else { ?>
            <div class="score-entry-empty-state">
                <h3>No subject assignments found</h3>
                <p>Your assigned exam score sheets will appear here once classes, subjects, and year-based sessions have been linked to your account.</p>
            </div>
            <?php } ?>
        </section>

        <section class="score-entry-panel">
            <div class="score-entry-panel__header">
                <div>
                    <span class="score-entry-panel__eyebrow">Selected Score Sheet</span>
                    <h2><?php echo score_entry_esc($pageTitle); ?></h2>
                </div>
                <?php if($selectedAssignment){ ?>
                <div class="score-entry-action-row">
                    <a class="score-entry-link-button" href="<?php echo score_entry_esc($scoresReportUrl); ?>"><i class="fa fa-line-chart"></i> Scores Report</a>
                    <a class="score-entry-link-button" href="<?php echo score_entry_esc($uploadUrl); ?>"><i class="fa fa-upload"></i> Bulk Upload</a>
                </div>
                <?php } ?>
            </div>

            <?php if(!$selectedAssignment){ ?>
            <div class="score-entry-empty-state">
                <h3>Choose an assignment to begin</h3>
                <p>Select any subject card on the left to open its <?php echo score_entry_esc(strtolower($scoreLabel)); ?> sheet. The selected score sheet will stay in view after each save so teachers can continue smoothly.</p>
            </div>
            <?php } else { ?>
                <div class="score-entry-sheet-summary">
                    <div class="score-entry-summary-grid">
                        <article class="score-entry-summary-card"><span>Class</span><strong><?php echo score_entry_esc($selectedAssignment['class_name']); ?></strong></article>
                        <article class="score-entry-summary-card"><span>Subject</span><strong><?php echo score_entry_esc($selectedAssignment['subject']); ?></strong></article>
                        <article class="score-entry-summary-card"><span>Session</span><strong><?php echo score_entry_esc($selectedAssignment['session_label']); ?></strong></article>
                        <article class="score-entry-summary-card"><span>Roster Source</span><strong><?php echo score_entry_esc($selectedAssignment['roster_source']); ?></strong></article>
                        <article class="score-entry-summary-card"><span>Total Students</span><strong><?php echo (int)$selectedAssignment['total_students']; ?></strong></article>
                        <article class="score-entry-summary-card"><span>Already Saved</span><strong><?php echo (int)$selectedAssignment['saved_students']; ?></strong></article>
                        <article class="score-entry-summary-card"><span>Still Pending</span><strong><?php echo (int)$selectedAssignment['pending_students']; ?></strong></article>
                        <article class="score-entry-summary-card"><span>Mode</span><strong><?php echo score_entry_esc($scoreLabel); ?></strong></article>
                    </div>
                </div>

                <?php if((int)$selectedAssignment['total_students'] === 0){ ?>
                <div class="score-entry-empty-state">
                    <h3>No registered students yet</h3>
                    <p>This assignment does not currently have registered students for the selected session, so there is no score sheet to enter yet.</p>
                </div>
                <?php } elseif(count($pendingStudents) === 0){ ?>
                <div class="score-entry-empty-state">
                    <h3>All exam scores are already entered</h3>
                    <p>Every registered student in this score sheet already has a saved <?php echo score_entry_esc(strtolower($scoreLabel)); ?>. You can review the saved records or open another assignment from the left.</p>
                    <div class="score-entry-empty-state__actions">
                        <a class="score-entry-link-button" href="<?php echo score_entry_esc($scoresReportUrl); ?>"><i class="fa fa-line-chart"></i> View Saved Scores</a>
                        <a class="score-entry-link-button" href="<?php echo score_entry_esc($uploadUrl); ?>"><i class="fa fa-upload"></i> Bulk Upload Page</a>
                    </div>
                </div>
                <?php } else { ?>
                <form
                    method="post"
                    action="<?php echo score_entry_esc(score_entry_build_url($pageFile, $selectedAssignment['class_entryid'], $selectedAssignment['termname'], $selectedAssignment['batchid'], $selectedAssignment['subjectid'], $prefillTotal, $selectedAssignment['assignment_year'])); ?>"
                    data-score-sheet>
                    <input type="hidden" name="assignmentid" value="<?php echo score_entry_esc((string)$selectedAssignment['assignmentid']); ?>">
                    <input type="hidden" name="class_ID" value="<?php echo score_entry_esc((string)$selectedAssignment['class_entryid']); ?>">
                    <input type="hidden" name="term_ID" value="<?php echo score_entry_esc((string)$selectedAssignment['termname']); ?>">
                    <input type="hidden" name="batch_ID" value="<?php echo score_entry_esc((string)$selectedAssignment['batchid']); ?>">
                    <input type="hidden" name="subject_ID" value="<?php echo score_entry_esc((string)$selectedAssignment['subjectid']); ?>">
                    <input type="hidden" name="year_ID" value="<?php echo score_entry_esc((string)$selectedAssignment['assignment_year']); ?>">

                    <div class="score-entry-sheet-toolbar">
                        <div class="score-entry-sheet-toolbar__group">
                            <div class="score-entry-field">
                                <label for="totalscore">Total Score</label>
                                <input
                                    type="number"
                                    min="0"
                                    max="<?php echo score_entry_esc((string)$scoreLimit); ?>"
                                    step="0.01"
                                    id="totalscore"
                                    name="totalscore"
                                    class="score-entry-total-input"
                                    data-role="total-score"
                                    value="<?php echo score_entry_esc($prefillTotal); ?>"
                                    placeholder="Maximum <?php echo (int)$scoreLimit; ?>">
                                <small>Use one total score value for the whole sheet. Exam score cannot be more than <?php echo (int)$scoreLimit; ?>.</small>
                            </div>
                            <div class="score-entry-field">
                                <label for="studentSearch">Search Students</label>
                                <input
                                    type="search"
                                    id="studentSearch"
                                    class="score-entry-search"
                                    data-role="student-search"
                                    placeholder="Search by student name or ID"
                                    autocomplete="off">
                                <small>Only students without saved <?php echo score_entry_esc(strtolower($scoreLabel)); ?> are shown.</small>
                            </div>
                        </div>

                        <div class="score-entry-sheet-toolbar__group">
                            <div class="score-entry-action-row">
                                <button type="button" class="score-entry-button-secondary" data-role="select-visible"><i class="fa fa-check-square-o"></i> Select Visible</button>
                                <button type="button" class="score-entry-button-secondary" data-role="clear-visible"><i class="fa fa-square-o"></i> Clear Visible</button>
                            </div>
                            <span class="score-entry-validation-note" data-role="validation-note">Typing in a mark auto-selects the student row for saving. Do not enter more than <?php echo (int)$scoreLimit; ?> for exam score.</span>
                        </div>
                    </div>

                    <div class="score-entry-student-list">
                        <?php $serial = 0; ?>
                        <?php foreach($pendingStudents as $student){ ?>
                            <?php
                            $serial++;
                            $studentName = trim($student['firstname']." ".$student['othernames']." ".$student['surname']);
                            $studentLabel = strtolower(trim($studentName." ".$student['userid']));
                            ?>
                            <article
                                class="score-entry-student-row"
                                data-student-row
                                data-student-label="<?php echo score_entry_esc($studentLabel); ?>">
                                <div class="score-entry-student-row__main">
                                    <input
                                        type="checkbox"
                                        class="score-entry-student-row__check"
                                        name="userid[]"
                                        value="<?php echo score_entry_esc((string)$student['userid']); ?>"
                                        data-role="student-checkbox">
                                    <div class="score-entry-student-row__meta">
                                        <span class="score-entry-student-row__index"><?php echo (int)$serial; ?></span>
                                        <div class="score-entry-student-row__text">
                                            <h3 class="score-entry-student-row__name"><?php echo score_entry_esc($studentName); ?></h3>
                                            <p>Student ID: <?php echo score_entry_esc((string)$student['userid']); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="score-entry-student-row__aside">
                                    <div class="score-entry-field">
                                        <label for="marks_<?php echo score_entry_esc((string)$student['userid']); ?>"><?php echo score_entry_esc($scoreLabel); ?></label>
                                        <input
                                            type="number"
                                            min="0"
                                            max="<?php echo score_entry_esc((string)$scoreLimit); ?>"
                                            step="0.01"
                                            id="marks_<?php echo score_entry_esc((string)$student['userid']); ?>"
                                            name="marks[<?php echo score_entry_esc((string)$student['userid']); ?>]"
                                            class="score-entry-mark-input"
                                            data-role="student-mark"
                                            placeholder="Enter mark"
                                            inputmode="decimal">
                                    </div>
                                </div>
                            </article>
                        <?php } ?>
                    </div>

                    <div class="score-entry-sticky">
                        <div class="score-entry-sticky__meta">
                            <div class="score-entry-sticky__metric"><span>Visible Students</span><strong data-role="visible-count"><?php echo count($pendingStudents); ?></strong></div>
                            <div class="score-entry-sticky__metric"><span>Selected</span><strong data-role="selected-count">0</strong></div>
                            <div class="score-entry-sticky__metric"><span>Entered Marks</span><strong data-role="entered-count">0</strong></div>
                            <div class="score-entry-sticky__metric"><span>Need Attention</span><strong data-role="invalid-count">0</strong></div>
                        </div>
                        <button type="submit" name="save_all_mark" class="score-entry-button" data-role="save-button">
                            <i class="fa fa-save"></i> <?php echo score_entry_esc($saveLabel); ?>
                        </button>
                    </div>
                </form>
                <?php } ?>
            <?php } ?>
        </section>
    </div>
</main>
</body>
</html>
