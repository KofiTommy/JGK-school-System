<?php
session_start();
include("dbstring.php");
include("check-login.php");
include_once("student-attendance-utils.php");
ensure_student_attendance_tables($con);

$isTeacherViewer = student_attendance_is_teacher();
$isAdminViewer = student_attendance_is_admin();
$isStudentViewer = student_attendance_is_student();
$hasModuleAccess = student_attendance_can_access($con);

if(!$isStudentViewer && !$hasModuleAccess){
    header("location:".student_attendance_landing_page());
    exit();
}

function sar_esc($value){ return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8"); }
function sar_date($value){
    $time = strtotime((string)$value);
    return $time ? date("d M Y", $time) : (string)$value;
}
function sar_status_label($status){
    $options = student_attendance_status_options();
    $status = student_attendance_normalize_status($status);
    return isset($options[$status]) ? $options[$status] : ucfirst($status);
}
function sar_status_chip_class($status){
    return "attendance-chip attendance-chip--".student_attendance_normalize_status($status);
}
function sar_percent($part, $whole){
    $whole = (float)$whole;
    if($whole <= 0){
        return 0;
    }
    return round((((float)$part) / $whole) * 100, 1);
}
function sar_range_label($dateFrom, $dateTo){
    return sar_date($dateFrom)." - ".sar_date($dateTo);
}
function sar_total_marked_from_overview($overview){
    return (int)$overview['present_total'] + (int)$overview['absent_total'] + (int)$overview['late_total'] + (int)$overview['excused_total'];
}

$currentUserId = isset($_SESSION["USERID"]) ? trim((string)$_SESSION["USERID"]) : "";
$dateFromInput = isset($_GET["date_from"]) ? trim((string)$_GET["date_from"]) : "";
$dateToInput = isset($_GET["date_to"]) ? trim((string)$_GET["date_to"]) : "";
list($dateFrom, $dateTo) = student_attendance_normalize_date_range($dateFromInput, $dateToInput, 30);
$rangeLabel = sar_range_label($dateFrom, $dateTo);

$assignments = array();
$assignmentMap = array();
$selectedAssignmentId = trim((string)(isset($_GET["assignmentid"]) ? $_GET["assignmentid"] : ""));
$selectedAssignment = null;
$overview = array(
    'session_total' => 0,
    'student_total' => 0,
    'marked_total' => 0,
    'present_total' => 0,
    'absent_total' => 0,
    'late_total' => 0,
    'excused_total' => 0,
    'attendance_rate' => 0
);
$dailySummary = array();
$studentSummary = array();
$recentSessions = array();
$pageTitle = $isStudentViewer ? "My Attendance Summary" : "Attendance Summary";
$pageIntro = $isStudentViewer
    ? "Review your attendance between any two dates, see your attendance trend, and print the summary when needed."
    : "Choose a class-teacher assignment and a date range to review attendance trends, student summary totals, and the overall attendance rate.";

if(!$isStudentViewer){
    $restrictAssignments = $isTeacherViewer;
    $assignments = student_attendance_list_assignments($con, $currentUserId, $restrictAssignments);
    foreach($assignments as $assignmentRow){
        $assignmentMap[(string)$assignmentRow["assignmentid"]] = $assignmentRow;
    }
    if($selectedAssignmentId === "" && !empty($assignments)){
        $selectedAssignmentId = (string)$assignments[0]["assignmentid"];
    }
    if($selectedAssignmentId !== "" && isset($assignmentMap[$selectedAssignmentId])){
        $selectedAssignment = $assignmentMap[$selectedAssignmentId];
        $overview = student_attendance_assignment_range_overview($con, $selectedAssignment, $dateFrom, $dateTo);
        $dailySummary = student_attendance_assignment_daily_summary($con, $selectedAssignment, $dateFrom, $dateTo);
        $studentSummary = student_attendance_assignment_student_summary($con, $selectedAssignment, $dateFrom, $dateTo);
        $recentSessions = student_attendance_list_recent_sessions($con, $selectedAssignment, 8);
    }
}else{
    $overview = student_attendance_student_overview($con, $currentUserId, $dateFrom, $dateTo);
    $dailySummary = student_attendance_student_daily_summary($con, $currentUserId, $dateFrom, $dateTo);
    $recentSessions = student_attendance_student_recent_history($con, $currentUserId, 8);
    $overview['marked_total'] = (int)$overview['session_total'];
    $overview['student_total'] = 1;
}

$overallMarkedTotal = sar_total_marked_from_overview($overview);
$dailyPeak = 0;
foreach($dailySummary as $dailyRow){
    if($isStudentViewer){
        $dailyPeak = max($dailyPeak, (int)$dailyRow['attendance_score']);
    }else{
        $dailyPeak = max($dailyPeak, (int)$dailyRow['marked_total']);
    }
}
if($dailyPeak <= 0){
    $dailyPeak = 100;
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
    <section class="attendance-hero">
        <div class="attendance-hero__copy">
            <span class="attendance-kicker"><?php echo $isStudentViewer ? "Attendance History" : "Attendance Report"; ?></span>
            <h1><?php echo sar_esc($pageTitle); ?></h1>
            <p><?php echo sar_esc($pageIntro); ?></p>
            <div class="attendance-metrics">
                <article><span>Date Range</span><strong><?php echo sar_esc($rangeLabel); ?></strong></article>
                <article><span><?php echo $isStudentViewer ? "Days Marked" : "Registers"; ?></span><strong><?php echo number_format((int)$overview["session_total"]); ?></strong></article>
                <article><span><?php echo $isStudentViewer ? "Present / Late" : "Students"; ?></span><strong><?php echo number_format($isStudentViewer ? ((int)$overview["present_total"] + (int)$overview["late_total"]) : (int)$overview["student_total"]); ?></strong></article>
                <article><span>Attendance Rate</span><strong><?php echo number_format((float)$overview["attendance_rate"], 1); ?>%</strong></article>
            </div>
        </div>
        <aside class="attendance-guide">
            <h2><?php echo $isStudentViewer ? "What This Shows" : "How To Use It"; ?></h2>
            <div class="attendance-guide__list">
                <?php if($isStudentViewer){ ?>
                <article><strong>1. Pick Dates</strong><span>Choose the period you want to review, then reload the summary.</span></article>
                <article><strong>2. Read The Graph</strong><span>The trend bar shows how your attendance looked on each school day.</span></article>
                <article><strong>3. Print If Needed</strong><span>Use the print button when you need an attendance summary.</span></article>
                <?php }else{ ?>
                <article><strong>1. Select Class</strong><span>Choose the class-teacher assignment you want to review.</span></article>
                <article><strong>2. Set The Period</strong><span>Load any date range to compare attendance from one time to another.</span></article>
                <article><strong>3. Review Students</strong><span>Use the student table to spot strong attendance and students needing attention.</span></article>
                <?php } ?>
            </div>
        </aside>
    </section>

    <section class="attendance-panel attendance-panel--main">
        <div class="attendance-panel__head">
            <div>
                <span class="attendance-panel__eyebrow">Filters</span>
                <h2><?php echo $isStudentViewer ? "Choose The Period To Review" : "Choose Class And Period"; ?></h2>
            </div>
            <div class="attendance-register-card__actions print-hide">
                <button type="button" class="button-print" onclick="window.print();"><i class="fa fa-print"></i> Print Summary</button>
            </div>
        </div>

        <form method="get" action="student-attendance-report.php" class="attendance-filter-grid print-hide">
            <?php if(!$isStudentViewer){ ?>
            <div class="attendance-field">
                <label for="assignmentid">Class Assignment</label>
                <select id="assignmentid" name="assignmentid">
                    <?php foreach($assignments as $assignment){ ?>
                    <option value="<?php echo sar_esc((string)$assignment["assignmentid"]); ?>"<?php echo $selectedAssignment && (string)$selectedAssignment["assignmentid"] === (string)$assignment["assignmentid"] ? " selected" : ""; ?>>
                        <?php echo sar_esc($assignment["class_name"]." - ".$assignment["session_label"]); ?>
                    </option>
                    <?php } ?>
                </select>
            </div>
            <?php } ?>
            <div class="attendance-field">
                <label for="date_from">From</label>
                <input type="date" id="date_from" name="date_from" value="<?php echo sar_esc($dateFrom); ?>">
            </div>
            <div class="attendance-field">
                <label for="date_to">To</label>
                <input type="date" id="date_to" name="date_to" value="<?php echo sar_esc($dateTo); ?>">
            </div>
            <div class="attendance-filter-actions">
                <button type="submit" class="button-show"><i class="fa fa-search"></i> Load Summary</button>
                <a class="button-print attendance-filter-reset" href="student-attendance-report.php<?php echo (!$isStudentViewer && $selectedAssignment) ? '?assignmentid='.rawurlencode((string)$selectedAssignment["assignmentid"]) : ''; ?>"><i class="fa fa-refresh"></i> Reset</a>
            </div>
        </form>
    </section>

    <?php if(!$isStudentViewer && empty($assignments)){ ?>
    <section class="attendance-panel">
        <div class="attendance-empty">
            <h3>No class assignments found</h3>
            <p><?php echo $isTeacherViewer ? "You do not have any active class-teacher assignment yet, so there is no attendance summary to show." : "There are no active class-teacher assignments available for attendance reporting yet."; ?></p>
        </div>
    </section>
    <?php }elseif(!$isStudentViewer && !$selectedAssignment){ ?>
    <section class="attendance-panel">
        <div class="attendance-empty">
            <h3>Select a class assignment</h3>
            <p>Choose a class from the filter above to load the attendance summary for that date range.</p>
        </div>
    </section>
    <?php }else{ ?>
    <div class="attendance-layout">
        <section class="attendance-panel attendance-panel--main">
            <div class="attendance-panel__head">
                <div>
                    <span class="attendance-panel__eyebrow"><?php echo $isStudentViewer ? "My Attendance" : "Summary"; ?></span>
                    <h2><?php echo $isStudentViewer ? "Attendance Across The Selected Period" : sar_esc($selectedAssignment["class_name"]); ?></h2>
                    <p>
                        <?php
                        if($isStudentViewer){
                            echo sar_esc($rangeLabel);
                        }else{
                            echo sar_esc($selectedAssignment["session_label"]." - ".$rangeLabel);
                        }
                        ?>
                    </p>
                </div>
            </div>

            <div class="attendance-summary-row attendance-summary-row--report">
                <article><span><?php echo $isStudentViewer ? "Days Marked" : "Registers"; ?></span><strong><?php echo number_format((int)$overview["session_total"]); ?></strong></article>
                <article><span>Present</span><strong><?php echo number_format((int)$overview["present_total"]); ?></strong></article>
                <article><span>Absent</span><strong><?php echo number_format((int)$overview["absent_total"]); ?></strong></article>
                <article><span>Late / Excused</span><strong><?php echo number_format((int)$overview["late_total"] + (int)$overview["excused_total"]); ?></strong></article>
            </div>

            <div class="attendance-chart-grid">
                <article class="attendance-chart-card">
                    <div class="attendance-chart-card__head">
                        <div>
                            <span class="attendance-panel__eyebrow">Status Graph</span>
                            <h3>Attendance Distribution</h3>
                        </div>
                        <span class="attendance-chip attendance-chip--neutral"><?php echo number_format((int)$overallMarkedTotal); ?> Records</span>
                    </div>
                    <?php if($overallMarkedTotal > 0){ ?>
                    <div class="attendance-progress-list">
                        <?php foreach(student_attendance_status_options() as $statusKey => $statusLabel){ ?>
                        <?php
                        $count = isset($overview[$statusKey."_total"]) ? (int)$overview[$statusKey."_total"] : 0;
                        $percent = sar_percent($count, $overallMarkedTotal);
                        $fillWidth = $count > 0 ? max(4, min(100, $percent)) : 0;
                        ?>
                        <article class="attendance-progress-row">
                            <div class="attendance-progress-row__top">
                                <span class="<?php echo sar_esc(sar_status_chip_class($statusKey)); ?>"><?php echo sar_esc($statusLabel); ?></span>
                                <strong><?php echo number_format($percent, 1); ?>%</strong>
                            </div>
                            <div class="attendance-progress-track">
                                <span class="attendance-progress-fill attendance-progress-fill--<?php echo sar_esc($statusKey); ?>" style="width: <?php echo $fillWidth; ?>%;"></span>
                            </div>
                            <small><?php echo number_format($count); ?> record<?php echo $count === 1 ? "" : "s"; ?></small>
                        </article>
                        <?php } ?>
                    </div>
                    <?php }else{ ?>
                    <div class="attendance-empty attendance-empty--compact">
                        <h3>No attendance yet</h3>
                        <p>There are no saved attendance records inside the selected date range.</p>
                    </div>
                    <?php } ?>
                </article>

                <article class="attendance-chart-card">
                    <div class="attendance-chart-card__head">
                        <div>
                            <span class="attendance-panel__eyebrow">Trend Graph</span>
                            <h3><?php echo $isStudentViewer ? "Daily Attendance Trend" : "Class Attendance By Day"; ?></h3>
                        </div>
                        <span class="attendance-chip attendance-chip--neutral"><?php echo number_format(count($dailySummary)); ?> Day<?php echo count($dailySummary) === 1 ? "" : "s"; ?></span>
                    </div>
                    <?php if(!empty($dailySummary)){ ?>
                    <div class="attendance-trend-list">
                        <?php foreach($dailySummary as $dailyRow){ ?>
                        <?php
                        $barPercent = $isStudentViewer
                            ? max(10, min(100, (float)$dailyRow['attendance_score']))
                            : max(4, min(100, (float)$dailyRow['attendance_rate']));
                        ?>
                        <article class="attendance-trend-item">
                            <div class="attendance-trend-item__head">
                                <div>
                                    <strong><?php echo sar_esc(sar_date($dailyRow['attendancedate'])); ?></strong>
                                    <?php if($isStudentViewer){ ?>
                                    <small><?php echo sar_esc(trim((string)$dailyRow['class_name'])." - ".student_attendance_session_label($dailyRow['attendancedate'], $dailyRow['batch'], $dailyRow['termname'])); ?></small>
                                    <?php }else{ ?>
                                    <small><?php echo number_format((int)$dailyRow['present_total']); ?> present, <?php echo number_format((int)$dailyRow['absent_total']); ?> absent</small>
                                    <?php } ?>
                                </div>
                                <?php if($isStudentViewer){ ?>
                                <span class="<?php echo sar_esc(sar_status_chip_class($dailyRow['attendancestatus'])); ?>"><?php echo sar_esc(sar_status_label($dailyRow['attendancestatus'])); ?></span>
                                <?php }else{ ?>
                                <strong><?php echo number_format((float)$dailyRow['attendance_rate'], 1); ?>%</strong>
                                <?php } ?>
                            </div>
                            <div class="attendance-trend-bar">
                                <span class="attendance-trend-fill<?php echo $isStudentViewer ? " attendance-trend-fill--".sar_esc(student_attendance_normalize_status($dailyRow['attendancestatus'])) : ""; ?>" style="width: <?php echo $barPercent; ?>%;"></span>
                            </div>
                            <?php if($isStudentViewer && trim((string)$dailyRow['note']) !== ""){ ?>
                            <small><?php echo sar_esc($dailyRow['note']); ?></small>
                            <?php }elseif(!$isStudentViewer){ ?>
                            <small><?php echo number_format((int)$dailyRow['marked_total']); ?> students marked</small>
                            <?php } ?>
                        </article>
                        <?php } ?>
                    </div>
                    <?php }else{ ?>
                    <div class="attendance-empty attendance-empty--compact">
                        <h3>No trend available yet</h3>
                        <p>Once attendance has been saved inside this date range, the daily graph will appear here.</p>
                    </div>
                    <?php } ?>
                </article>
            </div>

            <article class="attendance-chart-card">
                <div class="attendance-chart-card__head">
                    <div>
                        <span class="attendance-panel__eyebrow"><?php echo $isStudentViewer ? "Attendance History" : "Student Summary"; ?></span>
                        <h3><?php echo $isStudentViewer ? "Attendance Records In This Period" : "Students In This Class"; ?></h3>
                    </div>
                </div>

                <?php if($isStudentViewer){ ?>
                    <?php if(!empty($dailySummary)){ ?>
                    <div class="attendance-table-wrap">
                        <table class="attendance-data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Class</th>
                                    <th>Status</th>
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($dailySummary as $dailyRow){ ?>
                                <tr>
                                    <td><?php echo sar_esc(sar_date($dailyRow['attendancedate'])); ?></td>
                                    <td><?php echo sar_esc(trim((string)$dailyRow['class_name'])." / ".trim((string)$dailyRow['batch'])." / Semester ".trim((string)$dailyRow['termname'])); ?></td>
                                    <td><span class="<?php echo sar_esc(sar_status_chip_class($dailyRow['attendancestatus'])); ?>"><?php echo sar_esc(sar_status_label($dailyRow['attendancestatus'])); ?></span></td>
                                    <td><?php echo sar_esc(trim((string)$dailyRow['note']) !== "" ? $dailyRow['note'] : "-"); ?></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php }else{ ?>
                    <div class="attendance-empty attendance-empty--compact">
                        <h3>No records found</h3>
                        <p>Your attendance history for the chosen period will appear here once attendance has been taken.</p>
                    </div>
                    <?php } ?>
                <?php }else{ ?>
                    <?php if(!empty($studentSummary)){ ?>
                    <div class="attendance-table-wrap">
                        <table class="attendance-data-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Marked</th>
                                    <th>Present</th>
                                    <th>Absent</th>
                                    <th>Late</th>
                                    <th>Excused</th>
                                    <th>Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($studentSummary as $studentRow){ ?>
                                <tr>
                                    <td><?php echo sar_esc($studentRow['fullname'] !== "" ? $studentRow['fullname'] : $studentRow['userid']); ?></td>
                                    <td><?php echo number_format((int)$studentRow['marked_total']); ?></td>
                                    <td><?php echo number_format((int)$studentRow['present_total']); ?></td>
                                    <td><?php echo number_format((int)$studentRow['absent_total']); ?></td>
                                    <td><?php echo number_format((int)$studentRow['late_total']); ?></td>
                                    <td><?php echo number_format((int)$studentRow['excused_total']); ?></td>
                                    <td><?php echo number_format((float)$studentRow['attendance_rate'], 1); ?>%</td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php }else{ ?>
                    <div class="attendance-empty attendance-empty--compact">
                        <h3>No student summary yet</h3>
                        <p>Once attendance has been recorded for this class inside the selected period, the student breakdown will appear here.</p>
                    </div>
                    <?php } ?>
                <?php } ?>
            </article>
        </section>

        <aside class="attendance-panel">
            <?php if(!$isStudentViewer && $selectedAssignment){ ?>
            <article class="attendance-chart-card attendance-chart-card--compact">
                <div class="attendance-chart-card__head">
                    <div>
                        <span class="attendance-panel__eyebrow">Selected Class</span>
                        <h3><?php echo sar_esc($selectedAssignment["class_name"]); ?></h3>
                    </div>
                    <span class="attendance-chip attendance-chip--neutral"><?php echo number_format((int)$selectedAssignment["student_total"]); ?> Students</span>
                </div>
                <p class="attendance-mini-note"><?php echo sar_esc($selectedAssignment["session_label"]); ?></p>
                <?php if(!$isTeacherViewer){ ?>
                <p class="attendance-mini-note">Teacher: <?php echo sar_esc($selectedAssignment["teacher_name"] !== "" ? $selectedAssignment["teacher_name"] : $selectedAssignment["teacherid"]); ?></p>
                <?php } ?>
                <div class="attendance-register-actions print-hide">
                    <a class="button-show" href="student-attendance.php?assignmentid=<?php echo sar_esc((string)$selectedAssignment["assignmentid"]); ?>&attendance_date=<?php echo sar_esc($dateTo); ?>"><i class="fa fa-check-square-o"></i> Open Daily Register</a>
                </div>
            </article>
            <?php } ?>

            <article class="attendance-help-card">
                <h3><?php echo $isStudentViewer ? "Recent Attendance" : "Recent Registers"; ?></h3>
                <?php if(!empty($recentSessions)){ ?>
                <div class="attendance-history-list">
                    <?php foreach($recentSessions as $recentRow){ ?>
                    <?php if($isStudentViewer){ ?>
                    <article class="attendance-history-card">
                        <div class="attendance-history-card__top">
                            <h3><?php echo sar_esc(sar_date($recentRow['attendancedate'])); ?></h3>
                            <span class="<?php echo sar_esc(sar_status_chip_class($recentRow['attendancestatus'])); ?>"><?php echo sar_esc(sar_status_label($recentRow['attendancestatus'])); ?></span>
                        </div>
                        <p><?php echo sar_esc(trim((string)$recentRow['class_name'])." / ".trim((string)$recentRow['batch'])." / Semester ".trim((string)$recentRow['termname'])); ?></p>
                        <?php if(trim((string)$recentRow['note']) !== ""){ ?><small><?php echo sar_esc($recentRow['note']); ?></small><?php } ?>
                    </article>
                    <?php }else{ ?>
                    <?php $recentMarkedTotal = isset($recentRow['marked_total']) ? (int)$recentRow['marked_total'] : (isset($recentRow['student_total']) ? (int)$recentRow['student_total'] : 0); ?>
                    <article class="attendance-history-card<?php echo (string)$recentRow['attendancedate'] === $dateTo ? " is-active" : ""; ?>">
                        <div class="attendance-history-card__top">
                            <h3><?php echo sar_esc(sar_date($recentRow['attendancedate'])); ?></h3>
                            <span class="attendance-chip attendance-chip--neutral"><?php echo number_format($recentMarkedTotal); ?> Marked</span>
                        </div>
                        <div class="attendance-history-card__stats">
                            <span class="attendance-chip attendance-chip--present"><?php echo number_format((int)$recentRow['present_total']); ?> Present</span>
                            <span class="attendance-chip attendance-chip--absent"><?php echo number_format((int)$recentRow['absent_total']); ?> Absent</span>
                            <span class="attendance-chip attendance-chip--late"><?php echo number_format((int)$recentRow['late_total']); ?> Late</span>
                        </div>
                    </article>
                    <?php } ?>
                    <?php } ?>
                </div>
                <?php }else{ ?>
                <div class="attendance-empty attendance-empty--compact">
                    <h3>No recent record yet</h3>
                    <p><?php echo $isStudentViewer ? "Your recent attendance will appear here as soon as teachers record it." : "Recent saved registers for this class will appear here."; ?></p>
                </div>
                <?php } ?>
            </article>
        </aside>
    </div>
    <?php } ?>
</main>
</body>
</html>
