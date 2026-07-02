<?php
session_start();
include("dbstring.php");
include("check-login.php");
include_once("student-attendance-utils.php");
ensure_student_attendance_tables($con);

if(!student_attendance_can_access($con)){
    header("location:".student_attendance_landing_page());
    exit();
}

function sa_esc($value){ return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8"); }
function sa_alert($type, $message){
    $class = "attendance-alert attendance-alert--info";
    if($type === "success"){ $class = "attendance-alert attendance-alert--success"; }
    elseif($type === "error"){ $class = "attendance-alert attendance-alert--error"; }
    elseif($type === "warning"){ $class = "attendance-alert attendance-alert--warning"; }
    return "<div class=\"$class\">".sa_esc($message)."</div>";
}
function sa_date($value){
    $time = strtotime((string)$value);
    return $time ? date("d M Y", $time) : (string)$value;
}
function sa_datetime($value){
    $time = strtotime((string)$value);
    return $time ? date("d M Y, g:i a", $time) : (string)$value;
}
function sa_status_label($status){
    $options = student_attendance_status_options();
    $status = student_attendance_normalize_status($status);
    return isset($options[$status]) ? $options[$status] : ucfirst($status);
}
function sa_status_chip_class($status){
    $status = student_attendance_normalize_status($status);
    return "attendance-chip attendance-chip--".$status;
}

$flashMessage = isset($_SESSION["ATTENDANCE_MESSAGE"]) ? (string)$_SESSION["ATTENDANCE_MESSAGE"] : "";
unset($_SESSION["ATTENDANCE_MESSAGE"]);

$currentUserId = isset($_SESSION["USERID"]) ? trim((string)$_SESSION["USERID"]) : "";
$isTeacherViewer = student_attendance_is_teacher();
$isAdminViewer = student_attendance_is_admin();
$restrictAssignments = $isTeacherViewer;

$assignments = student_attendance_list_assignments($con, $currentUserId, $restrictAssignments);
$assignmentMap = array();
foreach($assignments as $assignmentRow){
    $assignmentMap[(string)$assignmentRow["assignmentid"]] = $assignmentRow;
}

$selectedAssignmentId = trim((string)(isset($_POST["assignmentid"]) ? $_POST["assignmentid"] : (isset($_GET["assignmentid"]) ? $_GET["assignmentid"] : "")));
if($selectedAssignmentId === "" && !empty($assignments)){
    $selectedAssignmentId = (string)$assignments[0]["assignmentid"];
}
$selectedAssignment = ($selectedAssignmentId !== "" && isset($assignmentMap[$selectedAssignmentId]))
    ? $assignmentMap[$selectedAssignmentId]
    : null;

$attendanceDate = trim((string)(isset($_POST["attendance_date"]) ? $_POST["attendance_date"] : (isset($_GET["attendance_date"]) ? $_GET["attendance_date"] : date("Y-m-d"))));
if($attendanceDate === "" || strtotime($attendanceDate) === false){
    $attendanceDate = date("Y-m-d");
}

if(isset($_POST["save_attendance_register"])){
    if(!$selectedAssignment){
        $flashMessage = sa_alert("warning", "Select a valid class before saving attendance.");
    }else{
        $statuses = isset($_POST["attendance_status"]) && is_array($_POST["attendance_status"]) ? $_POST["attendance_status"] : array();
        $notes = isset($_POST["attendance_note"]) && is_array($_POST["attendance_note"]) ? $_POST["attendance_note"] : array();
        $generalNote = isset($_POST["general_note"]) ? trim((string)$_POST["general_note"]) : "";
        $errorMessage = "";
        $savedSession = student_attendance_save_register($con, $selectedAssignment, $attendanceDate, $statuses, $notes, $generalNote, $currentUserId, $errorMessage);
        if($savedSession){
            engagement_track_event_action(
                $con,
                'teacher_attendance_saved',
                (string)$selectedAssignment["assignmentid"].'|'.$attendanceDate,
                $currentUserId
            );
            $savedEntries = student_attendance_get_entries($con, (string)$savedSession["sessionid"]);
            $savedCounts = student_attendance_count_session_statuses($savedEntries);
            $_SESSION["ATTENDANCE_MESSAGE"] = sa_alert(
                "success",
                "Attendance saved for ".trim((string)$selectedAssignment["class_name"])." on ".sa_date($attendanceDate).
                ". Present: ".(int)$savedCounts["present"].", Absent: ".(int)$savedCounts["absent"].", Late: ".(int)$savedCounts["late"].", Excused: ".(int)$savedCounts["excused"]."."
            );
            header("location:student-attendance.php?assignmentid=".rawurlencode((string)$selectedAssignment["assignmentid"])."&attendance_date=".rawurlencode($attendanceDate));
            exit();
        }
        $flashMessage = sa_alert("error", $errorMessage !== "" ? $errorMessage : "Attendance could not be saved right now.");
    }
}

$selectedRoster = array();
$selectedSession = null;
$selectedEntries = array();
$selectedCounts = array(
    "present" => 0,
    "absent" => 0,
    "late" => 0,
    "excused" => 0
);
$recentSessions = array();

if($selectedAssignment){
    $selectedRoster = student_attendance_get_roster($con, $selectedAssignment["classid"], $selectedAssignment["batchid"], $selectedAssignment["termname"]);
    $selectedSession = student_attendance_get_session($con, $selectedAssignment["classid"], $selectedAssignment["batchid"], $selectedAssignment["termname"], $attendanceDate);
    if($selectedSession){
        $selectedEntries = student_attendance_get_entries($con, (string)$selectedSession["sessionid"]);
        $selectedCounts = student_attendance_count_session_statuses($selectedEntries);
    }
    $recentSessions = student_attendance_list_recent_sessions($con, $selectedAssignment, 10);
}

$todayDate = date("Y-m-d");
$todaySessionCount = 0;
$todayMarkedCount = 0;
if($isTeacherViewer){
    $teacherSummary = student_attendance_teacher_dashboard_summary($con, $currentUserId);
    $todaySessionCount = (int)$teacherSummary["today_session_count"];
    $todayMarkedCount = (int)$teacherSummary["today_marked_count"];
}else{
    $resSessions = mysqli_query($con, "SELECT COUNT(*) AS total FROM tblstudentattendancesession WHERE attendancedate='".mysqli_real_escape_string($con, $todayDate)."'");
    if($resSessions && ($row = mysqli_fetch_array($resSessions, MYSQLI_ASSOC))){
        $todaySessionCount = (int)$row["total"];
    }
    $resEntries = mysqli_query($con, "SELECT COUNT(*) AS total
        FROM tblstudentattendanceentry ate
        INNER JOIN tblstudentattendancesession ats ON ats.sessionid=ate.sessionid
        WHERE ats.attendancedate='".mysqli_real_escape_string($con, $todayDate)."'");
    if($resEntries && ($row = mysqli_fetch_array($resEntries, MYSQLI_ASSOC))){
        $todayMarkedCount = (int)$row["total"];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/student-attendance.css">
</head>
<body class="student-attendance-page">
<div class="header print-hide"><?php include("menu.php"); ?></div>

<main class="attendance-shell">
    <?php if($flashMessage !== ""){ ?><div class="attendance-flash"><?php echo $flashMessage; ?></div><?php } ?>

    <section class="attendance-hero">
        <div class="attendance-hero__copy">
            <span class="attendance-kicker">Daily Register</span>
            <h1>Student Attendance</h1>
            <p>Attendance is linked to active class-teacher assignments, so teachers mark the correct class for the correct batch and semester every day.</p>
            <div class="attendance-metrics">
                <article><span>Assigned Classes</span><strong><?php echo number_format(count($assignments)); ?></strong></article>
                <article><span>Registers Today</span><strong><?php echo number_format($todaySessionCount); ?></strong></article>
                <article><span>Students Marked Today</span><strong><?php echo number_format($todayMarkedCount); ?></strong></article>
                <article><span>Working Date</span><strong><?php echo sa_esc(sa_date($attendanceDate)); ?></strong></article>
            </div>
        </div>
        <aside class="attendance-guide">
            <h2>How It Works</h2>
            <div class="attendance-guide__list">
                <article><strong>1. Pick Class</strong><span>Choose one class-teacher assignment from the list below.</span></article>
                <article><strong>2. Mark Status</strong><span>Set each student to Present, Absent, Late, or Excused.</span></article>
                <article><strong>3. Save Daily Register</strong><span>The same date can be reopened later to correct or review the record.</span></article>
            </div>
        </aside>
    </section>

    <div class="attendance-layout">
        <section class="attendance-panel attendance-panel--main">
            <div class="attendance-panel__head">
                <div>
                    <span class="attendance-panel__eyebrow">Assigned Classes</span>
                    <h2>Choose The Class For Attendance</h2>
                </div>
            </div>

            <?php if(empty($assignments)){ ?>
            <div class="attendance-empty">
                <h3>No class assignment available</h3>
                <p><?php echo $isTeacherViewer ? "You do not have any active class-teacher assignment yet. Once admin assigns a class to you, it will appear here for daily attendance." : "There are no active class-teacher assignments available yet."; ?></p>
            </div>
            <?php }else{ ?>
            <div class="attendance-assignment-grid">
                <?php foreach($assignments as $assignment){ ?>
                <a class="attendance-assignment-card<?php echo $selectedAssignment && (string)$selectedAssignment["assignmentid"] === (string)$assignment["assignmentid"] ? " is-active" : ""; ?>" href="student-attendance.php?assignmentid=<?php echo sa_esc((string)$assignment["assignmentid"]); ?>&attendance_date=<?php echo sa_esc($attendanceDate); ?>">
                    <div class="attendance-assignment-card__top">
                        <h3><?php echo sa_esc($assignment["class_name"]); ?></h3>
                        <span class="attendance-chip attendance-chip--neutral"><?php echo number_format((int)$assignment["student_total"]); ?> Students</span>
                    </div>
                    <p><?php echo sa_esc($assignment["session_label"]); ?></p>
                    <?php if(!$isTeacherViewer){ ?><small><?php echo sa_esc($assignment["teacher_name"] !== "" ? $assignment["teacher_name"] : $assignment["teacherid"]); ?></small><?php } ?>
                </a>
                <?php } ?>
            </div>
            <?php } ?>

            <?php if($selectedAssignment){ ?>
            <section class="attendance-register-card">
                <div class="attendance-register-card__head">
                    <div>
                        <span class="attendance-panel__eyebrow">Daily Register</span>
                        <h2><?php echo sa_esc($selectedAssignment["class_name"]); ?></h2>
                        <p><?php echo sa_esc($selectedAssignment["session_label"]); ?><?php if(!$isTeacherViewer){ ?> - <?php echo sa_esc($selectedAssignment["teacher_name"] !== "" ? $selectedAssignment["teacher_name"] : $selectedAssignment["teacherid"]); ?><?php } ?></p>
                    </div>
                    <div class="attendance-register-card__actions print-hide">
                        <a class="button-show" href="student-attendance-report.php?assignmentid=<?php echo sa_esc((string)$selectedAssignment["assignmentid"]); ?>&date_to=<?php echo sa_esc($attendanceDate); ?>"><i class="fa fa-bar-chart"></i> View Summary</a>
                        <button type="button" class="button-print" onclick="window.print();"><i class="fa fa-print"></i> Print Register</button>
                    </div>
                </div>

                <form method="get" action="student-attendance.php" class="attendance-date-bar print-hide">
                    <input type="hidden" name="assignmentid" value="<?php echo sa_esc((string)$selectedAssignment["assignmentid"]); ?>">
                    <div class="attendance-field">
                        <label for="attendance_date">Attendance Date</label>
                        <input type="date" id="attendance_date" name="attendance_date" value="<?php echo sa_esc($attendanceDate); ?>">
                    </div>
                    <button type="submit" class="button-show"><i class="fa fa-search"></i> Load Register</button>
                </form>

                <div class="attendance-summary-row">
                    <article><span>Roster Size</span><strong><?php echo number_format(count($selectedRoster)); ?></strong></article>
                    <article><span>Present</span><strong><?php echo number_format((int)$selectedCounts["present"]); ?></strong></article>
                    <article><span>Absent</span><strong><?php echo number_format((int)$selectedCounts["absent"]); ?></strong></article>
                    <article><span>Late / Excused</span><strong><?php echo number_format((int)$selectedCounts["late"] + (int)$selectedCounts["excused"]); ?></strong></article>
                </div>

                <?php if(empty($selectedRoster)){ ?>
                <div class="attendance-empty">
                    <h3>No active students found</h3>
                    <p>This class has no active semester registry yet for the selected batch and semester, so attendance cannot be taken today.</p>
                </div>
                <?php }else{ ?>
                <form method="post" action="student-attendance.php?assignmentid=<?php echo sa_esc((string)$selectedAssignment["assignmentid"]); ?>&attendance_date=<?php echo sa_esc($attendanceDate); ?>" class="attendance-register-form">
                    <input type="hidden" name="assignmentid" value="<?php echo sa_esc((string)$selectedAssignment["assignmentid"]); ?>">
                    <input type="hidden" name="attendance_date" value="<?php echo sa_esc($attendanceDate); ?>">

                    <div class="attendance-bulk-actions print-hide">
                        <button type="button" class="button-print attendance-bulk-btn" data-mark-all="present">Mark All Present</button>
                        <button type="button" class="button-print attendance-bulk-btn" data-mark-all="absent">Mark All Absent</button>
                        <button type="button" class="button-print attendance-bulk-btn" data-mark-all="late">Mark All Late</button>
                        <button type="button" class="button-print attendance-bulk-btn" data-mark-all="excused">Mark All Excused</button>
                    </div>

                    <div class="attendance-field">
                        <label for="general_note">Register Note</label>
                        <textarea id="general_note" name="general_note" rows="3" placeholder="Optional note for today's class attendance"><?php echo sa_esc($selectedSession ? (string)$selectedSession["generalnote"] : ""); ?></textarea>
                    </div>

                    <div class="attendance-student-list">
                        <?php foreach($selectedRoster as $student){ ?>
                        <?php
                        $studentId = (string)$student["userid"];
                        $studentEntry = isset($selectedEntries[$studentId]) ? $selectedEntries[$studentId] : null;
                        $studentStatus = $studentEntry ? student_attendance_normalize_status($studentEntry["attendancestatus"]) : "present";
                        $studentNote = $studentEntry ? (string)$studentEntry["note"] : "";
                        ?>
                        <article class="attendance-student-card">
                            <div class="attendance-student-card__head">
                                <div>
                                    <h3><?php echo sa_esc($student["fullname"]); ?></h3>
                                    <p><?php echo sa_esc($studentId); ?><?php if(trim((string)$student["gender"]) !== ""){ ?> - <?php echo sa_esc($student["gender"]); ?><?php } ?></p>
                                </div>
                                <span class="<?php echo sa_status_chip_class($studentStatus); ?>"><?php echo sa_esc(sa_status_label($studentStatus)); ?></span>
                            </div>

                            <div class="attendance-student-card__grid">
                                <div class="attendance-field">
                                    <label for="attendance_status_<?php echo sa_esc($studentId); ?>">Status</label>
                                    <select id="attendance_status_<?php echo sa_esc($studentId); ?>" name="attendance_status[<?php echo sa_esc($studentId); ?>]" class="attendance-status-select">
                                        <?php foreach(student_attendance_status_options() as $statusKey => $statusLabel){ ?>
                                        <option value="<?php echo sa_esc($statusKey); ?>"<?php echo $studentStatus === $statusKey ? " selected" : ""; ?>><?php echo sa_esc($statusLabel); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="attendance-field">
                                    <label for="attendance_note_<?php echo sa_esc($studentId); ?>">Note</label>
                                    <input type="text" id="attendance_note_<?php echo sa_esc($studentId); ?>" name="attendance_note[<?php echo sa_esc($studentId); ?>]" value="<?php echo sa_esc($studentNote); ?>" placeholder="Optional note">
                                </div>
                            </div>
                        </article>
                        <?php } ?>
                    </div>

                    <div class="attendance-register-actions print-hide">
                        <button type="submit" name="save_attendance_register" class="button-save"><i class="fa fa-save"></i> Save Daily Attendance</button>
                    </div>
                </form>
                <?php } ?>
            </section>
            <?php } ?>
        </section>

        <aside class="attendance-panel attendance-panel--side">
            <div class="attendance-panel__head">
                <div>
                    <span class="attendance-panel__eyebrow">Recent Records</span>
                    <h2>Attendance History</h2>
                </div>
            </div>

            <?php if(!$selectedAssignment){ ?>
            <div class="attendance-empty attendance-empty--compact">
                <p>Choose a class first to see recent attendance dates and quick summaries.</p>
            </div>
            <?php }elseif(empty($recentSessions)){ ?>
            <div class="attendance-empty attendance-empty--compact">
                <p>No attendance has been saved yet for this class.</p>
            </div>
            <?php }else{ ?>
            <div class="attendance-history-list">
                <?php foreach($recentSessions as $sessionRow){ ?>
                <a class="attendance-history-card<?php echo $attendanceDate === (string)$sessionRow["attendancedate"] ? " is-active" : ""; ?>" href="student-attendance.php?assignmentid=<?php echo sa_esc((string)$selectedAssignment["assignmentid"]); ?>&attendance_date=<?php echo sa_esc((string)$sessionRow["attendancedate"]); ?>">
                    <div class="attendance-history-card__top">
                        <strong><?php echo sa_esc(sa_date($sessionRow["attendancedate"])); ?></strong>
                        <span class="attendance-chip attendance-chip--neutral"><?php echo number_format((int)$sessionRow["student_total"]); ?> Marked</span>
                    </div>
                    <div class="attendance-history-card__stats">
                        <span>P: <?php echo number_format((int)$sessionRow["present_total"]); ?></span>
                        <span>A: <?php echo number_format((int)$sessionRow["absent_total"]); ?></span>
                        <span>L: <?php echo number_format((int)$sessionRow["late_total"]); ?></span>
                        <span>E: <?php echo number_format((int)$sessionRow["excused_total"]); ?></span>
                    </div>
                </a>
                <?php } ?>
            </div>
            <?php } ?>

            <section class="attendance-help-card">
                <h3>Status Guide</h3>
                <div class="attendance-guide__list attendance-guide__list--compact">
                    <article><strong>Present</strong><span>Student attended class normally.</span></article>
                    <article><strong>Absent</strong><span>Student did not attend the class.</span></article>
                    <article><strong>Late</strong><span>Student came after attendance had started.</span></article>
                    <article><strong>Excused</strong><span>Student was away with a valid explanation.</span></article>
                </div>
            </section>
        </aside>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var bulkButtons = document.querySelectorAll('[data-mark-all]');
    if (bulkButtons.length === 0) {
        return;
    }
    bulkButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var value = button.getAttribute('data-mark-all') || 'present';
            document.querySelectorAll('.attendance-status-select').forEach(function (select) {
                select.value = value;
                select.dispatchEvent(new Event('change'));
            });
        });
    });

    document.querySelectorAll('.attendance-status-select').forEach(function (select) {
        select.addEventListener('change', function () {
            var card = select.closest('.attendance-student-card');
            var chip = card ? card.querySelector('.attendance-chip') : null;
            if (!chip) {
                return;
            }
            var labels = {
                present: 'Present',
                absent: 'Absent',
                late: 'Late',
                excused: 'Excused'
            };
            chip.className = 'attendance-chip attendance-chip--' + select.value;
            chip.textContent = labels[select.value] || 'Present';
        });
    });
});
</script>
</body>
</html>
