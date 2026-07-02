<?php
session_start();
include("dbstring.php");
include("check-login.php");
include_once("course-registration-utils.php");
course_registration_ensure_tables($con);

if(!(isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) && $_SESSION['ACCESSLEVEL'] === 'user' && $_SESSION['SYSTEMTYPE'] === 'Student')){
    header("location:".(function_exists('um_home_link_for_session') ? um_home_link_for_session() : 'index.php'));
    exit();
}

function csr_safe($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function csr_flash($type, $message){
    $class = "cra-alert cra-alert--info";
    if($type === "success"){
        $class = "cra-alert cra-alert--success";
    }elseif($type === "error"){
        $class = "cra-alert cra-alert--error";
    }elseif($type === "warning"){
        $class = "cra-alert cra-alert--warning";
    }
    return "<div class=\"$class\">".csr_safe($message)."</div>";
}

function csr_datetime_readable($value){
    $value = trim((string)$value);
    if($value === ""){
        return "Not Set";
    }
    $time = strtotime($value);
    return $time ? date("d M Y, H:i", $time) : $value;
}

$studentId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : "";

if(isset($_POST['submit_course_registration'])){
    $selectedWindowId = isset($_POST['windowid']) ? trim((string)$_POST['windowid']) : "";
    $selectedAssignments = isset($_POST['selected_assignment']) ? $_POST['selected_assignment'] : array();
    $submitResult = course_registration_submit_student_selection($con, $selectedWindowId, $studentId, $selectedAssignments, $studentId);
    $_SESSION['Message'] = csr_flash($submitResult['ok'] ? "success" : "error", $submitResult['message']);
    header("location:student-course-registration.php".course_registration_scope_query(array("window" => $selectedWindowId)));
    exit();
}

$studentWindows = course_registration_fetch_student_windows($con, $studentId);
$selectedWindowId = isset($_GET['window']) ? trim((string)$_GET['window']) : "";
if($selectedWindowId === "" && count($studentWindows) > 0){
    $selectedWindowId = trim((string)$studentWindows[0]['windowid']);
}

$selectedWindow = null;
foreach($studentWindows as $windowRow){
    if(trim((string)$windowRow['windowid']) === $selectedWindowId){
        $selectedWindow = $windowRow;
        break;
    }
}

$windowOfferings = array();
$selectedMap = array();
$submissionRow = null;
$autoIncludedOfferings = array();
$selectableOfferings = array();
if($selectedWindow){
    $windowOfferings = course_registration_fetch_offerings($con, $selectedWindow['windowid']);
    $selectedMap = course_registration_fetch_student_selection_map($con, $selectedWindow['windowid'], $studentId);
    $submissionRow = course_registration_fetch_student_submission_row($con, $selectedWindow['windowid'], $studentId);
    foreach($windowOfferings as $offeringRow){
        if(strtolower(trim((string)$offeringRow['selectiontype'])) === 'elective'){
            $selectableOfferings[] = $offeringRow;
        }else{
            $autoIncludedOfferings[] = $offeringRow;
        }
    }
}

$flashMessage = "";
if(isset($_SESSION['Message']) && trim((string)$_SESSION['Message']) !== ""){
    $flashMessage = (string)$_SESSION['Message'];
    $_SESSION['Message'] = "";
}

$openWindowCount = 0;
foreach($studentWindows as $windowRow){
    if(!empty($windowRow['is_open_now'])){
        $openWindowCount++;
    }
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
<main class="cr-shell cr-shell--student">
    <?php if($flashMessage !== ""){ ?><div class="cr-flash"><?php echo $flashMessage; ?></div><?php } ?>

    <section class="cr-hero">
        <div>
            <span class="cr-kicker">Student Course Registration</span>
            <h1>Select the semester courses you want to register.</h1>
            <p>Once the school opens registration for your class, the classified subjects for that semester appear here automatically. Choose your courses and submit once for the semester.</p>
        </div>
        <div class="cr-hero__meta">
            <article><span>Open Windows</span><strong><?php echo (int)$openWindowCount; ?></strong></article>
            <article><span>Available Sessions</span><strong><?php echo count($studentWindows); ?></strong></article>
            <article><span>Selected Session</span><strong><?php echo $selectedWindow ? csr_safe($selectedWindow['session_label']) : 'Not Loaded'; ?></strong></article>
        </div>
    </section>

    <?php if(count($studentWindows) === 0){ ?>
    <?php echo csr_flash("warning", "There is no semester course registration window linked to your current semester records yet."); ?>
    <?php }else{ ?>
    <section class="cr-section">
        <div class="cr-section__header">
            <div><span class="cr-panel__eyebrow">Available Sessions</span><h2>Choose the semester you want to register</h2></div>
        </div>
        <div class="cr-window-picker">
            <?php foreach($studentWindows as $windowRow){ ?>
            <a class="cr-window-picker__item <?php echo $selectedWindowId === $windowRow['windowid'] ? 'is-active' : ''; ?>" href="student-course-registration.php<?php echo csr_safe(course_registration_scope_query(array('window' => $windowRow['windowid']))); ?>">
                <strong><?php echo csr_safe($windowRow['class_name']); ?></strong>
                <span><?php echo csr_safe($windowRow['session_label']); ?></span>
                <small><?php echo csr_safe(course_registration_window_status_label($windowRow)); ?></small>
            </a>
            <?php } ?>
        </div>
    </section>

    <?php if($selectedWindow){ ?>
    <section class="cr-section">
        <div class="cr-section__header">
            <div>
                <span class="cr-panel__eyebrow">Current Window</span>
                <h2><?php echo csr_safe($selectedWindow['class_name']); ?> | <?php echo csr_safe($selectedWindow['session_label']); ?></h2>
            </div>
            <span class="cr-pill <?php echo !empty($selectedWindow['is_open_now']) ? 'cr-pill--success' : 'cr-pill--warning'; ?>"><?php echo csr_safe(course_registration_window_status_label($selectedWindow)); ?></span>
        </div>
        <div class="cr-summary-grid">
            <article><span>Selectable Courses</span><strong><?php echo count($selectableOfferings); ?></strong></article>
            <article><span>Auto Included</span><strong><?php echo count($autoIncludedOfferings); ?></strong></article>
            <article><span>Registration Mode</span><strong>Student Selection</strong></article>
            <article><span>Last Submitted</span><strong><?php echo $submissionRow ? csr_safe(csr_datetime_readable($submissionRow['submittedat'])) : 'Not Yet Submitted'; ?></strong></article>
        </div>
        <?php if(!empty($selectedWindow['is_open_now'])){ ?>
        <?php echo csr_flash("info", $submissionRow ? "You can still correct or change your selected courses until the registration closing time." : "You can keep updating this course selection until the registration closing time."); ?>
        <?php } ?>
    </section>

    <section class="cr-section">
        <div class="cr-student-layout">
            <div class="cr-panel cr-panel--wide">
                <div class="cr-panel__header">
                    <div><span class="cr-panel__eyebrow">Course Form</span><h2>Semester course selection</h2></div>
                </div>
                <?php if(count($windowOfferings) === 0){ ?>
                <?php echo csr_flash("warning", "No courses are available in this registration window yet. The school needs current subject classification for this session first."); ?>
                <?php }else{ ?>
                <form method="post" action="student-course-registration.php<?php echo csr_safe(course_registration_scope_query(array('window' => $selectedWindow['windowid']))); ?>" class="cr-student-form">
                    <input type="hidden" name="windowid" value="<?php echo csr_safe($selectedWindow['windowid']); ?>">

                    <div class="cr-form-block">
                        <div class="cr-form-block__header">
                            <h3>Available Courses</h3>
                            <p>Select the courses you want to register for this semester. Your teachers will mark only the students who register each course.</p>
                        </div>
                        <?php if(count($selectableOfferings) === 0){ ?>
                        <p class="cr-muted">There are no student-selectable courses in this window yet.</p>
                        <?php }else{ ?>
                        <div class="cr-offering-list">
                            <?php foreach($selectableOfferings as $offeringRow){ $assignmentId = trim((string)$offeringRow['assignmentid']); ?>
                            <article class="cr-offering-card">
                                <label class="cr-offering-card__check">
                                    <input type="checkbox" name="selected_assignment[]" value="<?php echo csr_safe($assignmentId); ?>" <?php echo isset($selectedMap[$assignmentId]) ? 'checked' : ''; ?> <?php echo empty($selectedWindow['is_open_now']) ? 'disabled' : ''; ?>>
                                    <div class="cr-offering-card__main">
                                        <h4><?php echo csr_safe($offeringRow['subjectlabel']); ?></h4>
                                        <p><?php echo csr_safe($offeringRow['teacherlabel']); ?></p>
                                    </div>
                                </label>
                                <span class="cr-pill">Selectable</span>
                            </article>
                            <?php } ?>
                        </div>
                        <?php } ?>
                    </div>

                    <?php if(count($autoIncludedOfferings) > 0){ ?>
                    <div class="cr-form-block">
                        <div class="cr-form-block__header">
                            <h3>Auto Included Courses</h3>
                            <p>These courses were already fixed by the school for this window.</p>
                        </div>
                        <div class="cr-offering-list">
                            <?php foreach($autoIncludedOfferings as $offeringRow){ ?>
                            <article class="cr-offering-card cr-offering-card--locked">
                                <label class="cr-offering-card__check">
                                    <input type="checkbox" checked disabled>
                                    <div class="cr-offering-card__main">
                                        <h4><?php echo csr_safe($offeringRow['subjectlabel']); ?></h4>
                                        <p><?php echo csr_safe($offeringRow['teacherlabel']); ?></p>
                                    </div>
                                </label>
                                <span class="cr-pill"><?php echo csr_safe(course_registration_selection_type_label($offeringRow['selectiontype'])); ?></span>
                            </article>
                            <?php } ?>
                        </div>
                    </div>
                    <?php } ?>

                    <?php if(!empty($selectedWindow['is_open_now'])){ ?>
                    <div class="cr-window-form__actions">
                        <button type="submit" name="submit_course_registration" class="button-save"><i class="fa fa-check-circle"></i> <?php echo $submissionRow ? 'Update Semester Courses' : 'Submit Semester Courses'; ?></button>
                    </div>
                    <?php }else{ ?>
                    <?php echo csr_flash("info", "This registration window is closed now. Your last submitted courses stay visible below."); ?>
                    <?php } ?>
                </form>
                <?php } ?>
            </div>

            <aside class="cr-panel cr-panel--side">
                <div class="cr-panel__header">
                    <div><span class="cr-panel__eyebrow">Registration Notes</span><h2>Before you submit</h2></div>
                </div>
                <ul class="cr-check-list">
                    <li>Choose only the courses you really want to take for this semester.</li>
                    <li>You can correct your selection again any time while the window remains open.</li>
                    <li>Your teachers will see only the students who registered each course.</li>
                    <li>Submit at least one course before the registration window closes.</li>
                </ul>
                <?php if(trim((string)$selectedWindow['notes']) !== ''){ ?>
                <div class="cr-note-card">
                    <span>Admin Note</span>
                    <strong><?php echo csr_safe($selectedWindow['notes']); ?></strong>
                </div>
                <?php } ?>
            </aside>
        </div>
    </section>
    <?php } ?>
    <?php } ?>
</main>
</body>
</html>
