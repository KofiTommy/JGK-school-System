<?php
session_start();
include("dbstring.php");
include("check-login.php");
include_once("course-registration-utils.php");
course_registration_ensure_tables($con);

if(!(
    (isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) && $_SESSION['ACCESSLEVEL'] === 'administrator' && in_array($_SESSION['SYSTEMTYPE'], array('normal_user','super_user'), true))
    || (function_exists('um_is_assistant_head_academics_user') && um_is_assistant_head_academics_user())
)){
    header("location:".(function_exists('um_home_link_for_session') ? um_home_link_for_session() : 'index.php'));
    exit();
}

function cra_safe($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function cra_flash($type, $message){
    $class = "cra-alert cra-alert--info";
    if($type === "success"){
        $class = "cra-alert cra-alert--success";
    }elseif($type === "error"){
        $class = "cra-alert cra-alert--error";
    }elseif($type === "warning"){
        $class = "cra-alert cra-alert--warning";
    }
    return "<div class=\"$class\">".cra_safe($message)."</div>";
}

function cra_datetime_local($value){
    $value = trim((string)$value);
    if($value === ""){
        return "";
    }
    $time = strtotime($value);
    return $time ? date("Y-m-d\\TH:i", $time) : "";
}

function cra_datetime_readable($value){
    $value = trim((string)$value);
    if($value === ""){
        return "Not Set";
    }
    $time = strtotime($value);
    return $time ? date("d M Y, H:i", $time) : $value;
}

function cra_scope_link($classId, $batchId, $academicYear, $termName){
    return "course-registration-admin.php".course_registration_scope_query(array(
        "classid" => $classId,
        "batchid" => $batchId,
        "academicyear" => $academicYear,
        "termname" => $termName
    ));
}

function cra_export_csv($filename, $headers, $rows){
    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=".$filename);
    $output = fopen("php://output", "w");
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, $headers);
    foreach($rows as $row){
        fputcsv($output, $row);
    }
    fclose($output);
    exit();
}

$selectedClassId = isset($_REQUEST['classid']) ? trim((string)$_REQUEST['classid']) : "";
$selectedBatchId = isset($_REQUEST['batchid']) ? trim((string)$_REQUEST['batchid']) : "";
$selectedAcademicYear = course_registration_normalize_year(isset($_REQUEST['academicyear']) ? $_REQUEST['academicyear'] : "");
$selectedTermName = isset($_REQUEST['termname']) ? trim((string)$_REQUEST['termname']) : "";
$editStudentId = isset($_REQUEST['editstudent']) ? trim((string)$_REQUEST['editstudent']) : "";

if(isset($_POST['save_course_window'])){
    $selectedClassId = isset($_POST['classid']) ? trim((string)$_POST['classid']) : "";
    $selectedBatchId = isset($_POST['batchid']) ? trim((string)$_POST['batchid']) : "";
    $selectedAcademicYear = course_registration_normalize_year(isset($_POST['academicyear']) ? $_POST['academicyear'] : "");
    $selectedTermName = isset($_POST['termname']) ? trim((string)$_POST['termname']) : "";
    $selectedOfferings = array();
    if(isset($_POST['offering_assignment']) && is_array($_POST['offering_assignment'])){
        foreach($_POST['offering_assignment'] as $assignmentId){
            $assignmentId = trim((string)$assignmentId);
            if($assignmentId === ""){
                continue;
            }
            $selectionType = isset($_POST['selection_type'][$assignmentId]) ? trim((string)$_POST['selection_type'][$assignmentId]) : "compulsory";
            $selectedOfferings[$assignmentId] = $selectionType;
        }
    }

    $saveResult = course_registration_save_window($con, array(
        "classid" => $selectedClassId,
        "batchid" => $selectedBatchId,
        "academicyear" => $selectedAcademicYear,
        "termname" => $selectedTermName,
        "status" => isset($_POST['status']) ? $_POST['status'] : "closed",
        "windowtitle" => isset($_POST['windowtitle']) ? $_POST['windowtitle'] : "",
        "minimum_electives" => isset($_POST['minimum_electives']) ? $_POST['minimum_electives'] : 0,
        "maximum_electives" => isset($_POST['maximum_electives']) ? $_POST['maximum_electives'] : 0,
        "openfrom" => isset($_POST['openfrom']) ? str_replace('T', ' ', trim((string)$_POST['openfrom'])) : "",
        "closeat" => isset($_POST['closeat']) ? str_replace('T', ' ', trim((string)$_POST['closeat'])) : "",
        "notes" => isset($_POST['notes']) ? $_POST['notes'] : "",
        "branchid" => course_registration_branch_id()
    ), $selectedOfferings, isset($_SESSION['USERID']) ? $_SESSION['USERID'] : "");

    $_SESSION['Message'] = cra_flash($saveResult['ok'] ? "success" : "error", $saveResult['message']);
    header("location:".cra_scope_link($selectedClassId, $selectedBatchId, $selectedAcademicYear, $selectedTermName));
    exit();
}

if(isset($_POST['save_student_registration_admin'])){
    $windowId = isset($_POST['windowid']) ? trim((string)$_POST['windowid']) : "";
    $editStudentId = isset($_POST['studentid']) ? trim((string)$_POST['studentid']) : "";
    $selectedAssignments = isset($_POST['selected_assignment']) ? $_POST['selected_assignment'] : array();
    $windowRow = course_registration_fetch_window_by_id($con, $windowId);
    $saveResult = course_registration_admin_update_student_selection($con, $windowId, $editStudentId, $selectedAssignments, isset($_SESSION['USERID']) ? $_SESSION['USERID'] : '');
    $_SESSION['Message'] = cra_flash($saveResult['ok'] ? "success" : "error", $saveResult['message']);
    if($windowRow){
        header("location:".cra_scope_link($windowRow['classid'], $windowRow['batchid'], $windowRow['academicyear'], $windowRow['termname'])."&editstudent=".urlencode($editStudentId)."#admin-student-editor");
    }else{
        header("location:course-registration-admin.php");
    }
    exit();
}

$exportWindowId = isset($_GET['window']) ? trim((string)$_GET['window']) : "";
if($exportWindowId !== "" && isset($_GET['export'])){
    $windowRow = course_registration_fetch_window_by_id($con, $exportWindowId);
    if($windowRow){
        if($_GET['export'] === 'students'){
            $studentRows = course_registration_fetch_admin_student_rows($con, $exportWindowId);
            $csvRows = array();
            foreach($studentRows as $studentRow){
                $csvRows[] = array(
                    $studentRow['userid'],
                    $studentRow['student_name'],
                    $studentRow['is_submitted'] ? 'Submitted' : 'Pending',
                    (int)$studentRow['selectedcount'],
                    trim((string)$studentRow['course_labels']),
                    trim((string)$studentRow['submittedat'])
                );
            }
            cra_export_csv("course-registration-students-".$windowRow['academicyear']."-".$windowRow['termname'].".csv", array("Student ID","Student Name","Status","Selected Count","Course List","Submitted At"), $csvRows);
        }
        if($_GET['export'] === 'courses'){
            $courseRows = course_registration_fetch_admin_course_rows($con, $exportWindowId);
            $csvRows = array();
            foreach($courseRows as $courseRow){
                $csvRows[] = array(
                    $courseRow['subjectlabel'],
                    $courseRow['teacherlabel'],
                    course_registration_selection_type_label($courseRow['selectiontype']),
                    (int)$courseRow['registered_students'],
                    implode("; ", $courseRow['student_list'])
                );
            }
            cra_export_csv("course-registration-courses-".$windowRow['academicyear']."-".$windowRow['termname'].".csv", array("Course","Teacher","Type","Registered Students","Student List"), $csvRows);
        }
    }
}

$classOptions = array();
$classResult = mysqli_query($con, "SELECT class_entryid, class_name FROM tblclassentry ORDER BY class_name ASC");
if($classResult){
    while($row = mysqli_fetch_array($classResult, MYSQLI_ASSOC)){
        $classOptions[] = $row;
    }
}

$batchOptions = array();
$batchResult = mysqli_query($con, "SELECT batchid, batch FROM tblbatch ORDER BY datetimeentry DESC");
if($batchResult){
    while($row = mysqli_fetch_array($batchResult, MYSQLI_ASSOC)){
        $batchOptions[] = $row;
    }
}

$existingWindow = null;
$windowOfferings = array();
$assignmentRows = array();
$windowSummary = array('eligible_count' => 0, 'submitted_count' => 0, 'outstanding_count' => 0, 'course_count' => 0, 'selection_count' => 0);
$adminCourseRows = array();
$adminStudentRows = array();
$windowHasSubmissions = false;
$windowOfferingsLocked = false;
$editStudentRow = null;
$editStudentSelectionMap = array();
$editSelectableOfferings = array();
$editAutoIncludedOfferings = array();

if($selectedClassId !== "" && $selectedBatchId !== "" && $selectedAcademicYear !== "" && $selectedTermName !== ""){
    $existingWindow = course_registration_fetch_window_by_scope($con, $selectedClassId, $selectedBatchId, $selectedAcademicYear, $selectedTermName);
    $assignmentRows = course_registration_fetch_scope_assignments($con, $selectedClassId, $selectedBatchId, $selectedAcademicYear, $selectedTermName);
    if($existingWindow){
        $windowOfferings = course_registration_fetch_offerings($con, $existingWindow['windowid']);
        $windowSummary = course_registration_window_summary($con, $existingWindow['windowid']);
        $adminCourseRows = course_registration_fetch_admin_course_rows($con, $existingWindow['windowid']);
        $adminStudentRows = course_registration_fetch_admin_student_rows($con, $existingWindow['windowid']);
        $windowHasSubmissions = course_registration_window_has_submissions($con, $existingWindow['windowid']);
        $windowOfferingsLocked = ($windowHasSubmissions || (count($assignmentRows) === 0 && count($windowOfferings) > 0));
        if($editStudentId !== ''){
            foreach($adminStudentRows as $studentRow){
                if(trim((string)$studentRow['userid']) === $editStudentId){
                    $editStudentRow = $studentRow;
                    break;
                }
            }
            if($editStudentRow){
                $editStudentSelectionMap = course_registration_fetch_student_selection_map($con, $existingWindow['windowid'], $editStudentId);
                foreach($windowOfferings as $offeringRow){
                    if(strtolower(trim((string)$offeringRow['selectiontype'])) === 'elective'){
                        $editSelectableOfferings[] = $offeringRow;
                    }else{
                        $editAutoIncludedOfferings[] = $offeringRow;
                    }
                }
            }
        }
    }
}

$recentWindows = course_registration_fetch_recent_windows($con, 10);
$offeringSelectionMap = array();
foreach($windowOfferings as $offeringRow){
    $offeringSelectionMap[trim((string)$offeringRow['assignmentid'])] = trim((string)$offeringRow['selectiontype']);
}

$flashMessage = "";
if(isset($_SESSION['Message']) && trim((string)$_SESSION['Message']) !== ""){
    $flashMessage = (string)$_SESSION['Message'];
    $_SESSION['Message'] = "";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/course-registration.css">
</head>
<body class="cr-page">
<div class="header"><?php include("menu.php"); ?></div>
<main class="cr-shell">
    <?php if($flashMessage !== ""){ ?><div class="cr-flash"><?php echo $flashMessage; ?></div><?php } ?>

    <section class="cr-hero">
        <div>
            <span class="cr-kicker">Semester Course Registration</span>
            <h1>Open or close the registration window and let students choose their semester courses.</h1>
            <p>The course list follows the classified subjects for the selected class, academic year, and semester, so admin does not need to build the list manually.</p>
        </div>
        <div class="cr-hero__meta">
            <article><span>Recent Windows</span><strong><?php echo count($recentWindows); ?></strong></article>
            <article><span>Active Scope</span><strong><?php echo $existingWindow ? cra_safe($existingWindow['session_label']) : 'Not Loaded'; ?></strong></article>
            <article><span>Registration State</span><strong><?php echo $existingWindow ? cra_safe(course_registration_window_status_label($existingWindow)) : 'Awaiting Setup'; ?></strong></article>
        </div>
    </section>

    <div class="cr-layout">
        <section class="cr-panel cr-panel--form">
            <div class="cr-panel__header">
                <div><span class="cr-panel__eyebrow">Admin Setup</span><h2>Registration Window</h2></div>
            </div>
            <form method="get" action="course-registration-admin.php" class="cr-filter-grid">
                <label>
                    <span>Class</span>
                    <select name="classid">
                        <option value="">Select Class</option>
                        <?php foreach($classOptions as $classRow){ ?>
                        <option value="<?php echo cra_safe($classRow['class_entryid']); ?>" <?php echo $selectedClassId === $classRow['class_entryid'] ? 'selected' : ''; ?>><?php echo cra_safe($classRow['class_name']); ?></option>
                        <?php } ?>
                    </select>
                </label>
                <label>
                    <span>Batch</span>
                    <select name="batchid">
                        <option value="">Select Batch</option>
                        <?php foreach($batchOptions as $batchRow){ ?>
                        <option value="<?php echo cra_safe($batchRow['batchid']); ?>" <?php echo $selectedBatchId === $batchRow['batchid'] ? 'selected' : ''; ?>><?php echo cra_safe($batchRow['batch']); ?></option>
                        <?php } ?>
                    </select>
                </label>
                <label>
                    <span>Academic Year</span>
                    <select name="academicyear">
                        <option value="">Select Academic Year</option>
                        <?php foreach(course_registration_year_options() as $yearOption){ ?>
                        <option value="<?php echo cra_safe($yearOption); ?>" <?php echo $selectedAcademicYear === $yearOption ? 'selected' : ''; ?>><?php echo cra_safe($yearOption); ?></option>
                        <?php } ?>
                    </select>
                </label>
                <label>
                    <span>Semester</span>
                    <select name="termname">
                        <option value="">Select Semester</option>
                        <option value="1" <?php echo $selectedTermName === '1' ? 'selected' : ''; ?>>Semester 1</option>
                        <option value="2" <?php echo $selectedTermName === '2' ? 'selected' : ''; ?>>Semester 2</option>
                    </select>
                </label>
                <div class="cr-filter-grid__actions">
                    <button type="submit" class="button-show"><i class="fa fa-search"></i> Load Scope</button>
                    <a href="course-registration-admin.php" class="cr-reset-link">Reset</a>
                </div>
            </form>

            <?php if($selectedClassId !== "" && $selectedBatchId !== "" && $selectedAcademicYear !== "" && $selectedTermName !== ""){ ?>
            <form method="post" action="course-registration-admin.php" class="cr-window-form">
                <input type="hidden" name="classid" value="<?php echo cra_safe($selectedClassId); ?>">
                <input type="hidden" name="batchid" value="<?php echo cra_safe($selectedBatchId); ?>">
                <input type="hidden" name="academicyear" value="<?php echo cra_safe($selectedAcademicYear); ?>">
                <input type="hidden" name="termname" value="<?php echo cra_safe($selectedTermName); ?>">

                <div class="cr-window-grid">
                    <label>
                        <span>Window Title</span>
                        <input type="text" name="windowtitle" value="<?php echo cra_safe($existingWindow ? $existingWindow['windowtitle'] : ''); ?>" placeholder="Optional title for this registration window">
                    </label>
                    <label>
                        <span>Status</span>
                        <select name="status">
                            <option value="open" <?php echo ($existingWindow && $existingWindow['status'] === 'open') ? 'selected' : ''; ?>>Open</option>
                            <option value="closed" <?php echo (!$existingWindow || $existingWindow['status'] === 'closed') ? 'selected' : ''; ?>>Closed</option>
                        </select>
                    </label>
                    <label>
                        <span>Open From</span>
                        <input type="datetime-local" name="openfrom" value="<?php echo cra_safe($existingWindow ? cra_datetime_local($existingWindow['openfrom']) : ''); ?>">
                    </label>
                    <label>
                        <span>Close At</span>
                        <input type="datetime-local" name="closeat" value="<?php echo cra_safe($existingWindow ? cra_datetime_local($existingWindow['closeat']) : ''); ?>">
                    </label>
                </div>

                <label class="cr-notes-field">
                    <span>Admin Notes</span>
                    <textarea name="notes" rows="3" placeholder="Add any guidance or reminder for this semester registration window"><?php echo cra_safe($existingWindow ? $existingWindow['notes'] : ''); ?></textarea>
                </label>

                <div class="cr-panel__subhead">
                    <div>
                        <h3>Detected Classified Subjects</h3>
                        <p><?php echo count($assignmentRows) > 0 ? 'These courses are loaded automatically from the subject classification for this session. Students will choose from this list first, and teachers can be assigned later.' : 'No classified subjects were found yet for this scope.'; ?></p>
                    </div>
                    <?php if($windowOfferingsLocked){ ?><span class="cr-pill cr-pill--warning">Existing offerings preserved</span><?php } ?>
                </div>

                <?php if(count($assignmentRows) === 0 && !$existingWindow){ ?>
                <?php echo cra_flash("warning", "No classified subjects exist yet for this class, academic year, and semester."); ?>
                <?php }else{ ?>
                <div class="cr-offering-list">
                    <?php
                    if($windowOfferingsLocked){
                        foreach($windowOfferings as $offeringRow){
                    ?>
                    <article class="cr-offering-card">
                        <div class="cr-offering-card__main">
                            <h4><?php echo cra_safe($offeringRow['subjectlabel']); ?></h4>
                            <p><?php echo cra_safe($offeringRow['teacherlabel']); ?></p>
                        </div>
                        <span class="cr-pill"><?php echo cra_safe(course_registration_selection_type_label($offeringRow['selectiontype'])); ?></span>
                    </article>
                    <?php
                        }
                    }else{
                        foreach($assignmentRows as $assignmentRow){
                    ?>
                    <article class="cr-offering-card">
                        <div class="cr-offering-card__main">
                            <h4><?php echo cra_safe($assignmentRow['subject']); ?></h4>
                            <p><?php echo cra_safe($assignmentRow['teacherlabel']); ?></p>
                        </div>
                        <span class="cr-pill">Selectable</span>
                    </article>
                    <?php
                        }
                    }
                    ?>
                </div>
                <div class="cr-window-form__actions">
                    <button type="submit" name="save_course_window" class="button-save"><i class="fa fa-save"></i> Save Registration Window</button>
                </div>
                <?php } ?>
            </form>
            <?php }else{ ?>
            <?php echo cra_flash("info", "Choose class, batch, academic year, and semester first to prepare the course registration window."); ?>
            <?php } ?>
        </section>

        <aside class="cr-panel cr-panel--side">
            <div class="cr-panel__header">
                <div><span class="cr-panel__eyebrow">Recent Windows</span><h2>Quick Load</h2></div>
            </div>
            <div class="cr-window-list">
                <?php foreach($recentWindows as $recentWindow){ ?>
                <a class="cr-window-list__item" href="<?php echo cra_safe(cra_scope_link($recentWindow['classid'], $recentWindow['batchid'], $recentWindow['academicyear'], $recentWindow['termname'])); ?>">
                    <div>
                        <strong><?php echo cra_safe($recentWindow['class_name']); ?></strong>
                        <span><?php echo cra_safe($recentWindow['session_label']); ?></span>
                    </div>
                    <span class="cr-window-list__meta"><?php echo (int)$recentWindow['submitted_count']; ?>/<?php echo (int)$recentWindow['eligible_count']; ?></span>
                </a>
                <?php } ?>
            </div>
        </aside>
    </div>

    <?php if($existingWindow){ ?>
    <section class="cr-section">
        <div class="cr-section__header">
            <div><span class="cr-panel__eyebrow">Class Summary</span><h2><?php echo cra_safe($existingWindow['class_name']); ?> registration summary</h2></div>
            <div class="cr-section__actions">
                <a class="cr-secondary-button" href="<?php echo cra_safe("course-registration-admin.php?window=".$existingWindow['windowid']."&export=courses"); ?>"><i class="fa fa-download"></i> Download Course List</a>
                <a class="cr-secondary-button" href="<?php echo cra_safe("course-registration-admin.php?window=".$existingWindow['windowid']."&export=students"); ?>"><i class="fa fa-download"></i> Download Student List</a>
            </div>
        </div>
        <div class="cr-summary-grid">
            <article><span>Eligible Students</span><strong><?php echo (int)$windowSummary['eligible_count']; ?></strong></article>
            <article><span>Submitted</span><strong><?php echo (int)$windowSummary['submitted_count']; ?></strong></article>
            <article><span>Outstanding</span><strong><?php echo (int)$windowSummary['outstanding_count']; ?></strong></article>
            <article><span>Selectable Courses</span><strong><?php echo (int)$windowSummary['course_count']; ?></strong></article>
            <article><span>Selected Entries</span><strong><?php echo (int)$windowSummary['selection_count']; ?></strong></article>
            <article><span>Window Status</span><strong><?php echo cra_safe(course_registration_window_status_label($existingWindow)); ?></strong></article>
        </div>
        <div class="cr-detail-grid">
            <article class="cr-detail-card">
                <span>Open From</span>
                <strong><?php echo cra_safe(cra_datetime_readable($existingWindow['openfrom'])); ?></strong>
            </article>
            <article class="cr-detail-card">
                <span>Close At</span>
                <strong><?php echo cra_safe(cra_datetime_readable($existingWindow['closeat'])); ?></strong>
            </article>
            <article class="cr-detail-card">
                <span>Course Source</span>
                <strong>Teacher Subject Assignments</strong>
            </article>
        </div>
    </section>

    <section class="cr-section">
        <div class="cr-section__header">
            <div><span class="cr-panel__eyebrow">Course Demand</span><h2>Registered course counts</h2></div>
        </div>
        <div class="cr-course-grid">
            <?php foreach($adminCourseRows as $courseRow){ ?>
            <article class="cr-course-card">
                <div class="cr-course-card__head">
                    <div>
                        <h3><?php echo cra_safe($courseRow['subjectlabel']); ?></h3>
                        <p><?php echo cra_safe($courseRow['teacherlabel']); ?></p>
                    </div>
                    <span class="cr-pill"><?php echo cra_safe(course_registration_selection_type_label($courseRow['selectiontype'])); ?></span>
                </div>
                <div class="cr-course-card__stats">
                    <span><?php echo (int)$courseRow['registered_students']; ?> registered</span>
                    <strong>of <?php echo (int)$windowSummary['eligible_count']; ?></strong>
                </div>
                <?php if(count($courseRow['student_list']) > 0){ ?>
                <ul class="cr-inline-list">
                    <?php foreach($courseRow['student_list'] as $studentLabel){ ?>
                    <li><?php echo cra_safe($studentLabel); ?></li>
                    <?php } ?>
                </ul>
                <?php }else{ ?>
                <p class="cr-muted">No student has selected this course yet.</p>
                <?php } ?>
            </article>
            <?php } ?>
        </div>
    </section>

    <?php if($editStudentRow){ ?>
    <section class="cr-section" id="admin-student-editor">
        <div class="cr-section__header">
            <div><span class="cr-panel__eyebrow">Admin Edit</span><h2>Edit semester courses for <?php echo cra_safe($editStudentRow['student_name']); ?></h2></div>
        </div>
        <div class="cr-student-layout">
            <div class="cr-panel cr-panel--wide">
                <div class="cr-panel__header">
                    <div><span class="cr-panel__eyebrow">Registration Override</span><h2>Update selected courses</h2></div>
                </div>
                <form method="post" action="course-registration-admin.php<?php echo cra_safe(course_registration_scope_query(array(
                    'classid' => $existingWindow['classid'],
                    'batchid' => $existingWindow['batchid'],
                    'academicyear' => $existingWindow['academicyear'],
                    'termname' => $existingWindow['termname'],
                    'editstudent' => $editStudentId
                ))); ?>#admin-student-editor" class="cr-student-form">
                    <input type="hidden" name="windowid" value="<?php echo cra_safe($existingWindow['windowid']); ?>">
                    <input type="hidden" name="studentid" value="<?php echo cra_safe($editStudentId); ?>">

                    <div class="cr-form-block">
                        <div class="cr-form-block__header">
                            <h3>Selectable Courses</h3>
                            <p>Tick the courses this student should carry for the semester. This works even if the student window is already closed.</p>
                        </div>
                        <?php if(count($editSelectableOfferings) === 0){ ?>
                        <p class="cr-muted">There are no student-selectable courses in this window.</p>
                        <?php }else{ ?>
                        <div class="cr-offering-list">
                            <?php foreach($editSelectableOfferings as $offeringRow){ $assignmentId = trim((string)$offeringRow['assignmentid']); ?>
                            <article class="cr-offering-card">
                                <label class="cr-offering-card__check">
                                    <input type="checkbox" name="selected_assignment[]" value="<?php echo cra_safe($assignmentId); ?>" <?php echo isset($editStudentSelectionMap[$assignmentId]) ? 'checked' : ''; ?>>
                                    <div class="cr-offering-card__main">
                                        <h4><?php echo cra_safe($offeringRow['subjectlabel']); ?></h4>
                                        <p><?php echo cra_safe($offeringRow['teacherlabel']); ?></p>
                                    </div>
                                </label>
                                <span class="cr-pill">Selectable</span>
                            </article>
                            <?php } ?>
                        </div>
                        <?php } ?>
                    </div>

                    <?php if(count($editAutoIncludedOfferings) > 0){ ?>
                    <div class="cr-form-block">
                        <div class="cr-form-block__header">
                            <h3>Auto Included Courses</h3>
                            <p>These are fixed courses already attached to this window.</p>
                        </div>
                        <div class="cr-offering-list">
                            <?php foreach($editAutoIncludedOfferings as $offeringRow){ ?>
                            <article class="cr-offering-card cr-offering-card--locked">
                                <label class="cr-offering-card__check">
                                    <input type="checkbox" checked disabled>
                                    <div class="cr-offering-card__main">
                                        <h4><?php echo cra_safe($offeringRow['subjectlabel']); ?></h4>
                                        <p><?php echo cra_safe($offeringRow['teacherlabel']); ?></p>
                                    </div>
                                </label>
                                <span class="cr-pill"><?php echo cra_safe(course_registration_selection_type_label($offeringRow['selectiontype'])); ?></span>
                            </article>
                            <?php } ?>
                        </div>
                    </div>
                    <?php } ?>

                    <div class="cr-window-form__actions">
                        <button type="submit" name="save_student_registration_admin" class="button-save"><i class="fa fa-save"></i> Save Student Registration</button>
                        <a href="<?php echo cra_safe(cra_scope_link($existingWindow['classid'], $existingWindow['batchid'], $existingWindow['academicyear'], $existingWindow['termname'])); ?>" class="cr-reset-link">Close Editor</a>
                    </div>
                </form>
            </div>

            <aside class="cr-panel cr-panel--side">
                <div class="cr-panel__header">
                    <div><span class="cr-panel__eyebrow">Student Snapshot</span><h2><?php echo cra_safe($editStudentRow['userid']); ?></h2></div>
                </div>
                <div class="cr-summary-grid">
                    <article><span>Status</span><strong><?php echo $editStudentRow['is_submitted'] ? 'Submitted' : 'Pending'; ?></strong></article>
                    <article><span>Selected</span><strong><?php echo (int)$editStudentRow['selectedcount']; ?></strong></article>
                    <article><span>Submitted At</span><strong><?php echo cra_safe(cra_datetime_readable($editStudentRow['submittedat'])); ?></strong></article>
                </div>
                <div class="cr-note-card">
                    <span>Current Courses</span>
                    <strong><?php echo cra_safe(trim((string)$editStudentRow['course_labels']) !== '' ? $editStudentRow['course_labels'] : 'No saved course list yet'); ?></strong>
                </div>
            </aside>
        </div>
    </section>
    <?php } ?>

    <section class="cr-section">
        <div class="cr-section__header">
            <div><span class="cr-panel__eyebrow">Student List</span><h2>Who has registered and who is still pending</h2></div>
        </div>
        <div class="cr-student-table-wrap">
            <table class="cr-student-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Status</th>
                        <th>Selected</th>
                        <th>Courses</th>
                        <th>Submitted At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($adminStudentRows as $studentRow){ ?>
                    <tr>
                        <td data-label="Student">
                            <strong><?php echo cra_safe($studentRow['student_name']); ?></strong>
                            <span><?php echo cra_safe($studentRow['userid']); ?></span>
                        </td>
                        <td data-label="Status"><span class="cr-pill <?php echo $studentRow['is_submitted'] ? 'cr-pill--success' : 'cr-pill--warning'; ?>"><?php echo $studentRow['is_submitted'] ? 'Submitted' : 'Pending'; ?></span></td>
                        <td data-label="Selected"><?php echo (int)$studentRow['selectedcount']; ?></td>
                        <td data-label="Courses"><?php echo cra_safe(trim((string)$studentRow['course_labels']) !== '' ? $studentRow['course_labels'] : 'Not submitted yet'); ?></td>
                        <td data-label="Submitted At"><?php echo cra_safe(cra_datetime_readable($studentRow['submittedat'])); ?></td>
                        <td data-label="Action"><a class="cr-secondary-button" href="<?php echo cra_safe(cra_scope_link($existingWindow['classid'], $existingWindow['batchid'], $existingWindow['academicyear'], $existingWindow['termname'])."&editstudent=".urlencode((string)$studentRow['userid'])."#admin-student-editor"); ?>"><i class="fa fa-edit"></i> Edit</a></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php } ?>
</main>
</body>
</html>
