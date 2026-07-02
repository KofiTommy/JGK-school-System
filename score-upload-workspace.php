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
    $message = "Score upload fatal: ".$lastError['message']." in ".basename($lastError['file']).":".$lastError['line'];
    $logFile = __DIR__.DIRECTORY_SEPARATOR."score-upload-fatal.log";
    @file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] ".$message.PHP_EOL, FILE_APPEND);
    if (!headers_sent()) {
        @http_response_code(500);
        @header('Content-Type: text/plain; charset=utf-8');
    }
    echo $message;
});

include("dbstring.php");
include("check-login.php");
include("class-teacher-utils.php");
include("score-entry-utils.php");
include_once("semester-registry-utils.php");
semester_registry_ensure_academic_year_column($con);

if(!(class_teacher_is_teacher() || class_teacher_is_admin())){
    header("location:".class_teacher_landing_page());
    exit();
}

$pageFile = isset($pageFile) ? (string)$pageFile : "upload-score-entry.php";
$pageTitle = isset($pageTitle) ? (string)$pageTitle : "Upload Scores";
$pageDescription = isset($pageDescription) ? (string)$pageDescription : "Upload score sheets using the official template.";
$scoreType = isset($scoreType) ? (string)$scoreType : "Score";
$scoreLabel = isset($scoreLabel) ? (string)$scoreLabel : "Score";
$scoreLimit = isset($scoreLimit) ? (int)$scoreLimit : 100;
$templatePage = isset($templatePage) ? (string)$templatePage : "#";
$manualEntryPage = isset($manualEntryPage) ? (string)$manualEntryPage : "#";
$importPage = isset($importPage) ? (string)$importPage : "#";
$bodyModifierClass = isset($bodyModifierClass) ? (string)$bodyModifierClass : "score-entry-page--class";
$heroTitle = isset($heroTitle) ? (string)$heroTitle : "Upload Score Sheets";
$heroTips = isset($heroTips) && is_array($heroTips) ? $heroTips : array();
$uploadGuidanceTitle = isset($uploadGuidanceTitle) ? (string)$uploadGuidanceTitle : "Keep the worksheet structure unchanged";

$teacherId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : "";
$teacherIdSafe = mysqli_real_escape_string($con, $teacherId);

$selectedClassId = trim((string)(isset($_GET['class_ID']) ? $_GET['class_ID'] : ''));
$selectedTermId = trim((string)(isset($_GET['term_ID']) ? $_GET['term_ID'] : ''));
$selectedBatchId = trim((string)(isset($_GET['batch_ID']) ? $_GET['batch_ID'] : ''));
$selectedSubjectId = trim((string)(isset($_GET['subject_ID']) ? $_GET['subject_ID'] : ''));
$selectedYearId = semester_registry_normalize_year(isset($_GET['year_ID']) ? $_GET['year_ID'] : '');
$prefillTotal = trim((string)(isset($_GET['prefill_total']) ? $_GET['prefill_total'] : ''));
if($prefillTotal === ''){
    $prefillTotal = (string)$scoreLimit;
}

$flashMessage = isset($_SESSION['Message']) ? (string)$_SESSION['Message'] : "";
$_SESSION['Message'] = "";

$assignments = array();
$selectedAssignment = null;
$assignmentCount = 0;
$pendingAssignmentCount = 0;
$completedAssignmentCount = 0;
$studentsAwaitingCount = 0;
$studentsRegisteredCount = 0;

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
        $studentsRegisteredCount += $row['total_students'];
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
            $selectedYearId !== "" &&
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
$manualEntryUrl = "#";
$templateUrl = "#";
if($selectedAssignment){
    $scoresReportUrl = "scores-report.php?class_id=".urlencode((string)$selectedAssignment['class_entryid'])
        ."&term_id=".urlencode((string)$selectedAssignment['termname'])
        ."&subject_id=".urlencode((string)$selectedAssignment['subjectid'])
        ."&batchid=".urlencode((string)$selectedAssignment['batchid'])
        ."&year_batch=".urlencode((string)$selectedAssignment['assignment_year']);
    $manualEntryUrl = score_entry_build_url($manualEntryPage, (string)$selectedAssignment['class_entryid'], (string)$selectedAssignment['termname'], (string)$selectedAssignment['batchid'], (string)$selectedAssignment['subjectid'], $prefillTotal, (string)$selectedAssignment['assignment_year']);
    $templateUrl = score_entry_build_url($templatePage, (string)$selectedAssignment['class_entryid'], (string)$selectedAssignment['termname'], (string)$selectedAssignment['batchid'], (string)$selectedAssignment['subjectid'], "", (string)$selectedAssignment['assignment_year']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/teacher-score-entry.css">
<link rel="stylesheet" type="text/css" href="css/teacher-score-upload.css">
<script type="text/javascript" src="scripts/teacher-score-entry.js" defer></script>
<script type="text/javascript" src="scripts/teacher-score-upload.js" defer></script>
</head>
<body class="score-entry-page <?php echo score_entry_esc($bodyModifierClass); ?>">
<div class="header"><?php include("menu.php"); ?></div>
<main class="score-entry-shell score-upload-shell">
    <section class="score-entry-hero">
        <div class="score-entry-hero__copy">
            <span class="score-entry-kicker">Teacher Score Upload</span>
            <h1><?php echo score_entry_esc($heroTitle); ?></h1>
            <p><?php echo score_entry_esc($pageDescription); ?></p>
            <div class="score-entry-hero__stats">
                <article class="score-entry-stat-card"><span>Assignments</span><strong><?php echo (int)$assignmentCount; ?></strong></article>
                <article class="score-entry-stat-card"><span>Registered Students</span><strong><?php echo (int)$studentsRegisteredCount; ?></strong></article>
                <article class="score-entry-stat-card"><span>Awaiting Upload</span><strong><?php echo (int)$studentsAwaitingCount; ?></strong></article>
                <article class="score-entry-stat-card"><span>Completed Sheets</span><strong><?php echo (int)$completedAssignmentCount; ?></strong></article>
            </div>
        </div>
        <aside class="score-entry-hero__aside">
            <div class="score-entry-tip-card">
                <span class="score-entry-tip-card__eyebrow">Upload Guide</span>
                <h2><?php echo score_entry_esc($pageTitle); ?></h2>
                <ul>
                    <?php foreach($heroTips as $tip){ ?>
                    <li><?php echo score_entry_esc((string)$tip); ?></li>
                    <?php } ?>
                </ul>
            </div>
        </aside>
    </section>

    <?php if($flashMessage !== ""){ ?>
    <div class="score-entry-flash"><?php echo $flashMessage; ?></div>
    <?php } ?>

    <div class="score-entry-layout score-upload-layout">
        <section class="score-entry-panel">
            <div class="score-entry-panel__header">
                <div>
                    <span class="score-entry-panel__eyebrow">Assignments</span>
                    <h2>Choose a class and subject</h2>
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
                                <span><?php echo score_entry_esc($assignment['roster_source']); ?></span>
                            </div>
                        </div>
                    </a>
                <?php } ?>
            </div>
            <?php } else { ?>
            <div class="score-entry-empty-state">
                <h3>No subject assignments found</h3>
                <p>Your upload sheets will appear here after your classes and subjects have been assigned.</p>
            </div>
            <?php } ?>
        </section>

        <section class="score-entry-panel">
            <div class="score-entry-panel__header">
                <div>
                    <span class="score-entry-panel__eyebrow">Selected Upload Sheet</span>
                    <h2><?php echo score_entry_esc($pageTitle); ?></h2>
                </div>
                <?php if($selectedAssignment){ ?>
                <div class="score-entry-action-row">
                    <a class="score-entry-link-button" href="<?php echo score_entry_esc($scoresReportUrl); ?>"><i class="fa fa-line-chart"></i> Scores Report</a>
                    <a class="score-entry-link-button" href="<?php echo score_entry_esc($manualEntryUrl); ?>"><i class="fa fa-pencil"></i> Manual Entry</a>
                    <a class="score-entry-link-button" href="<?php echo score_entry_esc($templateUrl); ?>"><i class="fa fa-download"></i> Template Builder</a>
                </div>
                <?php } ?>
            </div>

            <?php if(!$selectedAssignment){ ?>
            <div class="score-entry-empty-state">
                <h3>Choose an assignment to begin</h3>
                <p>Select a class and subject from the list to open the correct upload sheet.</p>
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
                    <p>This assignment does not currently have registered students for the selected session, so there is nothing to upload yet.</p>
                </div>
                <?php } elseif(count($pendingStudents) === 0){ ?>
                <div class="score-entry-empty-state">
                    <h3>All <?php echo score_entry_esc(strtolower($scoreLabel)); ?> records are already saved</h3>
                    <p>Every registered student in this sheet already has a saved score. You can review the report, download the template again, or switch to another assignment.</p>
                    <div class="score-entry-empty-state__actions">
                        <a class="score-entry-link-button" href="<?php echo score_entry_esc($scoresReportUrl); ?>"><i class="fa fa-line-chart"></i> View Saved Scores</a>
                        <a class="score-entry-link-button" href="<?php echo score_entry_esc($manualEntryUrl); ?>"><i class="fa fa-pencil"></i> Manual Entry</a>
                        <a class="score-entry-link-button" href="<?php echo score_entry_esc($templateUrl); ?>"><i class="fa fa-download"></i> Template Builder</a>
                    </div>
                </div>
                <?php } else { ?>
                <div class="score-upload-grid">
                    <section class="score-upload-card">
                        <span class="score-entry-section__eyebrow">Workbook Upload</span>
                        <h3>Upload Completed <?php echo score_entry_esc($scoreLabel); ?> Sheet</h3>
                        <p class="score-upload-lead">Download the template, enter the scores, and upload the completed workbook for this class, subject, and session.</p>

                        <form
                            method="post"
                            action="<?php echo score_entry_esc($importPage); ?>"
                            enctype="multipart/form-data"
                            class="score-upload-form">
                            <input type="hidden" name="assignment-id" value="<?php echo score_entry_esc((string)$selectedAssignment['assignmentid']); ?>">
                            <input type="hidden" name="class_ID" value="<?php echo score_entry_esc((string)$selectedAssignment['class_entryid']); ?>">
                            <input type="hidden" name="term_ID" value="<?php echo score_entry_esc((string)$selectedAssignment['termname']); ?>">
                            <input type="hidden" name="batch_ID" value="<?php echo score_entry_esc((string)$selectedAssignment['batchid']); ?>">
                            <input type="hidden" name="subject_ID" value="<?php echo score_entry_esc((string)$selectedAssignment['subjectid']); ?>">
                            <input type="hidden" name="year_ID" value="<?php echo score_entry_esc((string)$selectedAssignment['assignment_year']); ?>">

                            <div class="score-upload-field-grid">
                                <div class="score-upload-field">
                                    <label for="totalscore">Total Score for This Sheet</label>
                                    <input
                                        type="number"
                                        min="0"
                                        max="<?php echo score_entry_esc((string)$scoreLimit); ?>"
                                        step="0.01"
                                        id="totalscore"
                                        name="totalscore"
                                        class="score-upload-input"
                                        value="<?php echo score_entry_esc($prefillTotal); ?>"
                                        placeholder="Maximum <?php echo (int)$scoreLimit; ?>"
                                        inputmode="decimal"
                                        required>
                                    <small>Every row in this upload will be checked against the same total score. Do not enter more than <?php echo (int)$scoreLimit; ?>.</small>
                                </div>
                                <div class="score-upload-field">
                                    <label for="file1">Completed Workbook</label>
                                    <label class="score-upload-file-picker" for="file1">
                                        <input
                                            type="file"
                                            id="file1"
                                            name="file1"
                                            accept=".xlsx,.xls"
                                            data-upload-file-input
                                            required>
                                        <span class="score-upload-file-picker__icon"><i class="fa fa-file-excel-o"></i></span>
                                        <span class="score-upload-file-picker__copy">
                                            <strong data-upload-file-name>Select an Excel file</strong>
                                            <small>`.xlsx` is recommended. The original downloaded template also works if it was not converted into a binary `.xls` workbook.</small>
                                        </span>
                                    </label>
                                </div>
                            </div>

                            <div class="score-upload-callout">
                                <span class="score-entry-section__eyebrow"><?php echo score_entry_esc($uploadGuidanceTitle); ?></span>
                                <ul>
                                    <li>Do not rename or rearrange the first six template columns: `userid`, `class_name`, `semester`, `batch`, `subjectid`, and `subject`.</li>
                                    <li>Enter the score in the last column only. Blank score cells are skipped during upload.</li>
                                    <li>Only students registered for this exact class, semester, batch, and academic year are accepted.</li>
                                </ul>
                            </div>

                            <div class="score-upload-actions">
                                <a class="score-entry-link-button" href="<?php echo score_entry_esc($templateUrl); ?>"><i class="fa fa-download"></i> Download Template</a>
                                <button type="submit" name="submit_group_data" class="score-entry-button">
                                    <i class="fa fa-upload"></i> Upload <?php echo score_entry_esc($scoreLabel); ?> Sheet
                                </button>
                            </div>
                        </form>
                    </section>
                </div>
                <?php } ?>
            <?php } ?>
        </section>
    </div>
</main>
</body>
</html>
