<?php
session_start();
include("dbstring.php");
include("check-login.php");
include("class-teacher-utils.php");
include("duty-roster-utils.php");
include("student-attendance-utils.php");
include("house-master-utils.php");
include_once("semester-registry-utils.php");
include_once("voting-utils.php");
include_once("teacher-billing-utils.php");
include_once("counselling-utils.php");
ensure_class_teacher_table($con);
ensure_duty_roster_tables($con);
ensure_student_attendance_tables($con);
ensure_house_tables($con);
ensure_voting_tables($con);
ensure_teacher_billing_table($con);
ensure_counselling_tables($con);
counselling_process_due_reminders($con);
if(!(isset($_SESSION['ACCESSLEVEL'],$_SESSION['SYSTEMTYPE']) && $_SESSION['ACCESSLEVEL']==="user" && $_SESSION['SYSTEMTYPE']==="Teacher")){
    header("location:".class_teacher_landing_page());
    exit();
}
function td_esc($v){ return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8"); }
function td_alert($type,$message){
    $class="teacher-inline-alert teacher-inline-alert--info";
    if($type==="success"){$class="teacher-inline-alert teacher-inline-alert--success";}
    elseif($type==="error"){$class="teacher-inline-alert teacher-inline-alert--error";}
    elseif($type==="warning"){$class="teacher-inline-alert teacher-inline-alert--warning";}
    return "<div class=\"$class\">".td_esc($message)."</div>";
}
function td_term($term){ $term=trim((string)$term); return $term==="" ? "Semester" : "Semester ".$term; }
function td_session_label($dateTimeValue,$batchLabel,$termValue){
    $yearValue = "";
    if(trim((string)$dateTimeValue) !== ""){
        $time = strtotime((string)$dateTimeValue);
        if($time){ $yearValue = date("Y",$time); }
    }
    if($yearValue === ""){ $yearValue = date("Y"); }
    return trim($yearValue." Batch ".trim((string)$batchLabel)." Semester ".trim((string)$termValue));
}
function td_date($value){ $time=strtotime((string)$value); return $time ? date("d M Y, H:i",$time) : (string)$value; }
function td_perf_delta_class($delta){
    if($delta === null){
        return "teacher-perf-delta teacher-perf-delta--neutral";
    }
    if((float)$delta > 0){
        return "teacher-perf-delta teacher-perf-delta--up";
    }
    if((float)$delta < 0){
        return "teacher-perf-delta teacher-perf-delta--down";
    }
    return "teacher-perf-delta teacher-perf-delta--neutral";
}
function td_perf_delta_text($delta){
    if($delta === null){
        return "No previous year";
    }
    $delta = round((float)$delta, 2);
    if($delta > 0){
        return "+".number_format($delta, 2)."%";
    }
    if($delta < 0){
        return number_format($delta, 2)."%";
    }
    return "0.00%";
}
function td_message_target_key($type, $group, $value){
    return trim((string)$type)."|".trim((string)$group)."|".trim((string)$value);
}
function td_message_add_target(&$options, $type, $group, $value, $label){
    $key = td_message_target_key($type, $group, $value);
    if(isset($options[$key])){
        return;
    }
    $options[$key] = array(
        'recipient_type' => trim((string)$type),
        'recipient_group' => trim((string)$group),
        'recipient_value' => trim((string)$value),
        'recipient_label' => trim((string)$label)
    );
}
function td_teacher_message_targets($con, $teacherId){
    $teacherIdEsc = mysqli_real_escape_string($con, (string)$teacherId);
    $options = array();
    td_message_add_target($options, 'group', 'admins', '', 'Admin Only');

    $classRes = mysqli_query($con, "SELECT ct.classid,ct.batchid,ct.termname,ce.class_name,bh.batch
        FROM tblclassteacher ct
        INNER JOIN tblclassentry ce ON ce.class_entryid=ct.classid
        LEFT JOIN tblbatch bh ON bh.batchid=ct.batchid
        WHERE ct.userid='$teacherIdEsc' AND ct.status='active'
        ORDER BY bh.datetimeentry DESC, ct.termname DESC, ce.class_name ASC");
    if($classRes){
        while($row = mysqli_fetch_array($classRes, MYSQLI_ASSOC)){
            $payload = trim((string)$row['classid'])."|".trim((string)$row['batchid'])."|".trim((string)$row['termname']);
            $label = "My Class Students";
            $classLabel = trim((string)$row['class_name']);
            $batchLabel = trim((string)$row['batch']);
            $termLabel = trim((string)$row['termname']);
            if($classLabel !== '' || $batchLabel !== '' || $termLabel !== ''){
                $label .= " (".trim($classLabel.($batchLabel !== '' ? " · ".$batchLabel : '').($termLabel !== '' ? " · Semester ".$termLabel : '')).")";
            }
            td_message_add_target($options, 'class_scope', 'students', $payload, $label);
        }
    }

    $houseRes = mysqli_query($con, "SELECT hm.houseid,h.housename
        FROM tblhousemaster hm
        INNER JOIN tblhouse h ON h.houseid=hm.houseid
        WHERE hm.userid='$teacherIdEsc' AND hm.status='active' AND h.status='active'
        ORDER BY h.housename ASC");
    if($houseRes){
        while($row = mysqli_fetch_array($houseRes, MYSQLI_ASSOC)){
            $houseId = trim((string)$row['houseid']);
            if($houseId === ''){
                continue;
            }
            $label = "My House Students";
            $houseLabel = trim((string)$row['housename']);
            if($houseLabel !== ''){
                $label .= " (".$houseLabel.")";
            }
            td_message_add_target($options, 'house_scope', 'students', $houseId, $label);
        }
    }

    return $options;
}
$teacherId = isset($_SESSION['USERID']) ? (string)$_SESSION['USERID'] : "";
$teacherIdEsc = mysqli_real_escape_string($con, $teacherId);
$teacherMessageTargets = td_teacher_message_targets($con, $teacherId);
$teacherDefaultMessageTarget = key($teacherMessageTargets);

if(isset($_POST['send_message'])){
    $message = trim((string)(isset($_POST['message']) ? $_POST['message'] : ""));
    if($message === ""){
        $_SESSION['Message'] = td_alert("warning","Please type a message before sending.");
    } else {
        include("code.php");
        $messageId = mysqli_real_escape_string($con, (string)$code);
        $messageEsc = mysqli_real_escape_string($con, $message);
        $targetKey = isset($_POST['message_target']) ? trim((string)$_POST['message_target']) : (string)$teacherDefaultMessageTarget;
        if($targetKey === '' || !isset($teacherMessageTargets[$targetKey])){
            $targetKey = (string)$teacherDefaultMessageTarget;
        }
        $targetMeta = isset($teacherMessageTargets[$targetKey]) ? $teacherMessageTargets[$targetKey] : array(
            'recipient_type' => 'group',
            'recipient_group' => 'admins',
            'recipient_value' => '',
            'recipient_label' => 'Admin Only'
        );
        $messageAudienceEsc = mysqli_real_escape_string($con, $targetMeta['recipient_group']);
        $messageTypeEsc = mysqli_real_escape_string($con, $targetMeta['recipient_type']);
        $messageValueEsc = mysqli_real_escape_string($con, $targetMeta['recipient_value']);
        $messageLabelEsc = mysqli_real_escape_string($con, $targetMeta['recipient_label']);
        $_SQL = mysqli_query($con,"INSERT INTO tblmessages(messageid,messages,datetimeentry,status,sentby,recipient_group,recipient_type,recipient_value,recipient_label)
            VALUES('$messageId','$messageEsc',NOW(),'active','$teacherIdEsc','$messageAudienceEsc','$messageTypeEsc','$messageValueEsc','$messageLabelEsc')");
        if($_SQL){
            engagement_track_daily_action($con, 'teacher_message_sent_daily', $teacherId);
        }
        $_SESSION['Message'] = $_SQL ? td_alert("success","Message successfully submitted.") : td_alert("error","Message failed to submit.");
    }
    header("location:teacher-page.php#teacher-messages");
    exit();
}
if(isset($_POST["delete_message"])){
    $messageId = trim((string)(isset($_POST["messageid"]) ? $_POST["messageid"] : ""));
    if($messageId !== ""){
        $messageIdEsc = mysqli_real_escape_string($con, $messageId);
        $_SQL_D = mysqli_query($con,"DELETE FROM tblmessages WHERE messageid='$messageIdEsc' AND sentby='$teacherIdEsc' LIMIT 1");
        $_SESSION['Message'] = ($_SQL_D && mysqli_affected_rows($con)>0) ? td_alert("success","Message successfully deleted.") : td_alert("error","Message could not be deleted.");
    }
    header("location:teacher-page.php#teacher-messages");
    exit();
}

$flashMessage = isset($_SESSION['Message']) ? $_SESSION['Message'] : "";
$_SESSION['Message'] = "";
$teacherName = isset($_SESSION['FULLNAME']) ? trim((string)$_SESSION['FULLNAME']) : "";
$teacherShortName = $teacherName !== "" ? explode(" ", $teacherName)[0] : "Teacher";
$teacherBranch = "";
$teacherFilename = "";
$teacherProfileRes = mysqli_query($con,"SELECT su.firstname,su.surname,su.othernames,su.filename,br.location
    FROM tblsystemuser su LEFT JOIN tblbranch br ON su.branchid=br.branchid
    WHERE su.userid='$teacherIdEsc' LIMIT 1");
if($teacherProfileRes && $row=mysqli_fetch_array($teacherProfileRes,MYSQLI_ASSOC)){
    $full = trim($row['firstname']." ".$row['othernames']." ".$row['surname']);
    if($full !== ""){ $teacherName = $full; $teacherShortName = explode(" ", $full)[0]; }
    $teacherBranch = trim((string)$row['location']);
    $teacherFilename = trim((string)$row['filename']);
}
$teacherImage = "uploads/comm.gif";
if($teacherFilename !== "" && file_exists(__DIR__.DIRECTORY_SEPARATOR."uploads".DIRECTORY_SEPARATOR.$teacherFilename)){
    $teacherImage = "uploads/".rawurlencode($teacherFilename);
}
$dutyDashboard = duty_roster_get_teacher_dashboard_context($con, $teacherId);
$attendanceSummary = student_attendance_teacher_dashboard_summary($con, $teacherId);
$teacherHasBillingModule = teacher_billing_teacher_has_module($con, $teacherId);
$teacherBillingAssignments = $teacherHasBillingModule ? teacher_billing_fetch_assignments($con, $teacherId) : array();
$teacherBillingScopeCount = count($teacherBillingAssignments);
$teacherCounsellingSummary = counselling_teacher_assignment_summary($con, $teacherId);
$teacherHasCounsellingAccess = ((int)$teacherCounsellingSummary['total_scope_count']) > 0;
$teacherCounsellingSoonRows = $teacherHasCounsellingAccess ? counselling_dashboard_notification_rows($con, $teacherId, 'teacher', 15) : array();
$teacherCounsellingSoonCount = count($teacherCounsellingSoonRows);
$teacherCounsellingSoonMessage = "";
if($teacherCounsellingSoonCount > 0){
    $teacherNextCounselling = $teacherCounsellingSoonRows[0];
    $teacherNextStudent = counselling_person_name($teacherNextCounselling);
    $teacherNextTime = counselling_format_time(isset($teacherNextCounselling['scheduled_time']) ? $teacherNextCounselling['scheduled_time'] : '');
    $teacherNextDate = counselling_format_date(isset($teacherNextCounselling['scheduled_date']) ? $teacherNextCounselling['scheduled_date'] : '');
    $teacherCounsellingSoonMessage = $teacherCounsellingSoonCount > 1
        ? "Counselling reminder: You have ".number_format((int)$teacherCounsellingSoonCount)." meetings due within the next 15 minutes. Next is with ".$teacherNextStudent." at ".$teacherNextTime." on ".$teacherNextDate.". Open Guidance & Counselling."
        : "Counselling reminder: Your meeting with ".$teacherNextStudent." starts at ".$teacherNextTime." on ".$teacherNextDate.". Open Guidance & Counselling.";
}

$classTeacherRoles = array();
$classTeacherLookup = array();
$classTeacherRes = mysqli_query($con,"SELECT ct.classid,ct.batchid,ct.termname,ct.datetimeentry AS role_datetimeentry,ce.class_name,bh.batch
    FROM tblclassteacher ct
    INNER JOIN tblclassentry ce ON ce.class_entryid=ct.classid
    INNER JOIN tblbatch bh ON bh.batchid=ct.batchid
    WHERE ct.userid='$teacherIdEsc' AND ct.status='active'
    ORDER BY ct.datetimeentry DESC,ct.termname DESC,ce.class_name ASC");
if($classTeacherRes){
    while($row=mysqli_fetch_array($classTeacherRes,MYSQLI_ASSOC)){
        $key = $row["batchid"]."|".$row["classid"]."|".$row["termname"];
        $classTeacherLookup[$key] = true;
        $row["session_label"] = td_session_label($row["role_datetimeentry"], $row["batch"], $row["termname"]);
        $classTeacherRoles[] = $row;
    }
}

$assignmentGroups = array();
$assignedSubjectCount = 0;
$activeBatchIds = array();
$recentTeachingGroupLimit = 6;
$assignmentRes = mysqli_query($con,"SELECT sa.classid,sa.batchid,sa.termname,sa.datetimeentry AS assignment_datetimeentry,ce.class_name,bh.batch,sub.subject
    FROM tblsubjectassignment sa
    INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
    INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
    INNER JOIN tblclassentry ce ON ce.class_entryid=sa.classid
    INNER JOIN tblbatch bh ON bh.batchid=sa.batchid
    WHERE sa.userid='$teacherIdEsc' AND sa.status='active' AND bh.status='active'
    ORDER BY bh.datetimeentry DESC,sa.termname DESC,ce.class_name ASC,sub.subject ASC");
if($assignmentRes){
    while($row=mysqli_fetch_array($assignmentRes,MYSQLI_ASSOC)){
        $assignedSubjectCount++;
        $activeBatchIds[$row["batchid"]] = true;
        $key = $row["batchid"]."|".$row["classid"]."|".$row["termname"];
        if(!isset($assignmentGroups[$key])){
            $assignmentGroups[$key] = array(
                "class_name"=>$row["class_name"],
                "batch"=>$row["batch"],
                "termname"=>$row["termname"],
                "session_label"=>td_session_label($row["assignment_datetimeentry"], $row["batch"], $row["termname"]),
                "sort_timestamp"=>(strtotime((string)$row["assignment_datetimeentry"]) ?: 0),
                "subjects"=>array(),
                "is_class_teacher"=>isset($classTeacherLookup[$key])
            );
        } else {
            $currentSortTime = (strtotime((string)$row["assignment_datetimeentry"]) ?: 0);
            if($currentSortTime > (int)$assignmentGroups[$key]["sort_timestamp"]){
                $assignmentGroups[$key]["sort_timestamp"] = $currentSortTime;
                $assignmentGroups[$key]["session_label"] = td_session_label($row["assignment_datetimeentry"], $row["batch"], $row["termname"]);
            }
        }
        $assignmentGroups[$key]["subjects"][] = $row["subject"];
    }
}
$teachingGroups = array_values($assignmentGroups);
usort($teachingGroups, function($left, $right){
    $leftSort = isset($left["sort_timestamp"]) ? (int)$left["sort_timestamp"] : 0;
    $rightSort = isset($right["sort_timestamp"]) ? (int)$right["sort_timestamp"] : 0;
    if($leftSort === $rightSort){
        return strcmp((string)$left["class_name"], (string)$right["class_name"]);
    }
    return ($rightSort <=> $leftSort);
});
$recentTeachingGroups = array_slice($teachingGroups, 0, $recentTeachingGroupLimit);
$teachingGroupCount = count($teachingGroups);
$recentTeachingGroupCount = count($recentTeachingGroups);
$classTeacherRoleCount = count($classTeacherRoles);
$teacherCanTakeAttendance = ($classTeacherRoleCount > 0);
$activeBatchCount = count($activeBatchIds);
$myMessageCount = 0;
$messageUnreadCount = um_message_unread_count($con, $teacherId, 'Teacher');
$teacherVotingSnapshot = voting_dashboard_snapshot($con, voting_default_branch_id($con), 'Teacher');
$countRes = mysqli_query($con,"SELECT COUNT(*) AS total_messages FROM tblmessages WHERE sentby='$teacherIdEsc' AND status='active'");
if($countRes && $countRow=mysqli_fetch_array($countRes,MYSQLI_ASSOC)){ $myMessageCount = (int)$countRow["total_messages"]; }
$myMessages = array();
$myMessagesRes = mysqli_query($con,"SELECT messageid,messages,datetimeentry,recipient_label FROM tblmessages
    WHERE sentby='$teacherIdEsc' AND status='active' ORDER BY datetimeentry DESC LIMIT 6");
if($myMessagesRes){ while($row=mysqli_fetch_array($myMessagesRes,MYSQLI_ASSOC)){ $myMessages[] = $row; } }

$teacherPerformanceYearOptions = array();
$teacherPerformanceYearsRes = mysqli_query($con, "SELECT DISTINCT ".semester_registry_assignment_year_sql("sa")." AS assignment_year
    FROM tblsubjectassignment sa
    WHERE sa.userid='$teacherIdEsc' AND sa.status='active'
    ORDER BY assignment_year DESC");
if($teacherPerformanceYearsRes){
    while($row = mysqli_fetch_array($teacherPerformanceYearsRes, MYSQLI_ASSOC)){
        $optionYear = semester_registry_normalize_year(isset($row["assignment_year"]) ? $row["assignment_year"] : "");
        if($optionYear !== "" && !in_array($optionYear, $teacherPerformanceYearOptions, true)){
            $teacherPerformanceYearOptions[] = $optionYear;
        }
    }
}
if(count($teacherPerformanceYearOptions) === 0){
    $teacherPerformanceYearOptions[] = date("Y");
}
$teacherPerformanceYear = isset($_GET["teacher_perf_year"])
    ? semester_registry_normalize_year($_GET["teacher_perf_year"])
    : "";
if($teacherPerformanceYear === ""){
    $teacherPerformanceYear = $teacherPerformanceYearOptions[0];
}
if(!in_array($teacherPerformanceYear, $teacherPerformanceYearOptions, true)){
    array_unshift($teacherPerformanceYearOptions, $teacherPerformanceYear);
    $teacherPerformanceYearOptions = array_values(array_unique($teacherPerformanceYearOptions));
}

$teacherPerformancePreviousYear = null;
foreach($teacherPerformanceYearOptions as $yearOption){
    if((int)$yearOption < (int)$teacherPerformanceYear){
        $teacherPerformancePreviousYear = $yearOption;
        break;
    }
}

$teacherPerfRows = array();
$teacherPerfLabels = array();
$teacherPerfAvg = array();
$teacherPerfPass = array();
$teacherPerfTrendLabels = array();
$teacherPerfTrendAvg = array();
$teacherPerfTrendPass = array();
$teacherPerfComparisonRows = array();
$teacherPerfOverallByYear = array();
$teacherPerfCurrentAvg = 0;
$teacherPerfCurrentPass = 0;
$teacherPerfSubjectCount = 0;
$teacherPerfBestSubject = "N/A";
$teacherPerfBestScore = 0;
$teacherPerfYearDelta = null;

$teacherPerfYearEsc = mysqli_real_escape_string($con, $teacherPerformanceYear);
$teacherPerfSql = mysqli_query($con, "SELECT
        sub.subjectid,
        sub.subject,
        COUNT(*) AS entries_count,
        ROUND(AVG(CASE WHEN mk.totalmark > 0 THEN (mk.mark / mk.totalmark) * 100 ELSE 0 END),2) AS avg_percent,
        ROUND(AVG(CASE WHEN mk.totalmark > 0 AND ((mk.mark / mk.totalmark) * 100) >= 50 THEN 100 ELSE 0 END),2) AS pass_rate,
        ROUND(AVG(CASE WHEN mk.totalmark > 0 AND ((mk.mark / mk.totalmark) * 100) >= 80 THEN 100 ELSE 0 END),2) AS excellence_rate
    FROM tblmark mk
    INNER JOIN tblsubjectassignment sa ON sa.assignmentid = mk.assignmentid
    INNER JOIN tblsubjectclassification sc ON sa.classificationid = sc.classificationid
    INNER JOIN tblsubject sub ON sc.subjectid = sub.subjectid
    WHERE sa.userid='$teacherIdEsc'
      AND sa.status='active'
      AND mk.status='active'
      AND ".semester_registry_assignment_year_sql("sa")."='$teacherPerfYearEsc'
    GROUP BY sub.subjectid, sub.subject
    ORDER BY avg_percent DESC, sub.subject ASC");
if($teacherPerfSql){
    while($row = mysqli_fetch_array($teacherPerfSql, MYSQLI_ASSOC)){
        $teacherPerfRows[] = $row;
        $teacherPerfLabels[] = (string)$row["subject"];
        $teacherPerfAvg[] = (float)$row["avg_percent"];
        $teacherPerfPass[] = (float)$row["pass_rate"];
        $teacherPerfCurrentAvg += (float)$row["avg_percent"];
        $teacherPerfCurrentPass += (float)$row["pass_rate"];
    }
}
$teacherPerfSubjectCount = count($teacherPerfRows);
if($teacherPerfSubjectCount > 0){
    $teacherPerfCurrentAvg = round($teacherPerfCurrentAvg / $teacherPerfSubjectCount, 2);
    $teacherPerfCurrentPass = round($teacherPerfCurrentPass / $teacherPerfSubjectCount, 2);
    $teacherPerfBestSubject = (string)$teacherPerfRows[0]["subject"];
    $teacherPerfBestScore = (float)$teacherPerfRows[0]["avg_percent"];
}

$teacherPerfTrendSql = mysqli_query($con, "SELECT
        ".semester_registry_assignment_year_sql("sa")." AS assignment_year,
        COUNT(*) AS entries_count,
        ROUND(AVG(CASE WHEN mk.totalmark > 0 THEN (mk.mark / mk.totalmark) * 100 ELSE 0 END),2) AS avg_percent,
        ROUND(AVG(CASE WHEN mk.totalmark > 0 AND ((mk.mark / mk.totalmark) * 100) >= 50 THEN 100 ELSE 0 END),2) AS pass_rate
    FROM tblmark mk
    INNER JOIN tblsubjectassignment sa ON sa.assignmentid = mk.assignmentid
    WHERE sa.userid='$teacherIdEsc'
      AND sa.status='active'
      AND mk.status='active'
    GROUP BY ".semester_registry_assignment_year_sql("sa")."
    ORDER BY assignment_year ASC");
if($teacherPerfTrendSql){
    while($row = mysqli_fetch_array($teacherPerfTrendSql, MYSQLI_ASSOC)){
        $trendYear = semester_registry_normalize_year(isset($row["assignment_year"]) ? $row["assignment_year"] : "");
        if($trendYear === ""){
            continue;
        }
        $teacherPerfOverallByYear[$trendYear] = array(
            "avg_percent" => (float)$row["avg_percent"],
            "pass_rate" => (float)$row["pass_rate"],
            "entries_count" => (int)$row["entries_count"]
        );
        $teacherPerfTrendLabels[] = $trendYear;
        $teacherPerfTrendAvg[] = (float)$row["avg_percent"];
        $teacherPerfTrendPass[] = (float)$row["pass_rate"];
    }
}
if($teacherPerformancePreviousYear !== null && isset($teacherPerfOverallByYear[$teacherPerformanceYear], $teacherPerfOverallByYear[$teacherPerformancePreviousYear])){
    $teacherPerfYearDelta = (float)$teacherPerfOverallByYear[$teacherPerformanceYear]["avg_percent"] - (float)$teacherPerfOverallByYear[$teacherPerformancePreviousYear]["avg_percent"];
}

$teacherPerfHistoryMap = array();
$teacherPerfHistorySql = mysqli_query($con, "SELECT
        sub.subjectid,
        sub.subject,
        ".semester_registry_assignment_year_sql("sa")." AS assignment_year,
        ROUND(AVG(CASE WHEN mk.totalmark > 0 THEN (mk.mark / mk.totalmark) * 100 ELSE 0 END),2) AS avg_percent
    FROM tblmark mk
    INNER JOIN tblsubjectassignment sa ON sa.assignmentid = mk.assignmentid
    INNER JOIN tblsubjectclassification sc ON sa.classificationid = sc.classificationid
    INNER JOIN tblsubject sub ON sc.subjectid = sub.subjectid
    WHERE sa.userid='$teacherIdEsc'
      AND sa.status='active'
      AND mk.status='active'
    GROUP BY sub.subjectid, sub.subject, ".semester_registry_assignment_year_sql("sa")."
    ORDER BY sub.subject ASC, assignment_year ASC");
if($teacherPerfHistorySql){
    while($row = mysqli_fetch_array($teacherPerfHistorySql, MYSQLI_ASSOC)){
        $subjectId = (string)$row["subjectid"];
        $historyYear = semester_registry_normalize_year(isset($row["assignment_year"]) ? $row["assignment_year"] : "");
        if($subjectId === "" || $historyYear === ""){
            continue;
        }
        if(!isset($teacherPerfHistoryMap[$subjectId])){
            $teacherPerfHistoryMap[$subjectId] = array(
                "subject" => (string)$row["subject"],
                "years" => array()
            );
        }
        $teacherPerfHistoryMap[$subjectId]["years"][$historyYear] = (float)$row["avg_percent"];
    }
}

foreach($teacherPerfRows as $row){
    $subjectId = (string)$row["subjectid"];
    $previousYearForSubject = null;
    $previousYearScore = null;
    if(isset($teacherPerfHistoryMap[$subjectId]["years"]) && is_array($teacherPerfHistoryMap[$subjectId]["years"])){
        $subjectYears = array_keys($teacherPerfHistoryMap[$subjectId]["years"]);
        rsort($subjectYears, SORT_NUMERIC);
        foreach($subjectYears as $subjectYear){
            if((int)$subjectYear < (int)$teacherPerformanceYear){
                $previousYearForSubject = $subjectYear;
                $previousYearScore = (float)$teacherPerfHistoryMap[$subjectId]["years"][$subjectYear];
                break;
            }
        }
    }
    $teacherPerfComparisonRows[] = array(
        "subject" => (string)$row["subject"],
        "current_avg" => (float)$row["avg_percent"],
        "previous_year" => $previousYearForSubject,
        "previous_avg" => $previousYearScore,
        "delta" => ($previousYearScore === null ? null : ((float)$row["avg_percent"] - (float)$previousYearScore))
    );
}

$engagementSummary = engagement_get_summary($con, $teacherId);
$engagementRecent = engagement_get_recent_activity($con, $teacherId, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/teacher-dashboard.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="teacher-dashboard-page">
<div class="header"><?php include("menu.php"); ?></div>
<main class="teacher-shell">
<?php if($flashMessage !== ""){ ?><div class="teacher-flash"><?php echo $flashMessage; ?></div><?php } ?>
<?php if($teacherCounsellingSoonMessage !== ""){ ?><div class="teacher-flash"><?php echo td_alert("warning", $teacherCounsellingSoonMessage); ?></div><?php } ?>

<section class="teacher-hero">
    <div class="teacher-hero__copy">
        <span class="teacher-kicker">Teacher Workspace</span>
        <h1>Welcome back, <?php echo td_esc($teacherShortName); ?>.</h1>
        <div class="teacher-hero__utility">
            <div class="teacher-live-clock">
                <div class="xschool-live-clock" data-live-clock>
                    <div class="xschool-live-clock__top">
                        <span class="xschool-live-clock__eyebrow">Live Date &amp; Time</span>
                        <span class="xschool-live-clock__status"><i class="fa fa-circle"></i> Live</span>
                    </div>
                    <div class="xschool-live-clock__time" data-live-clock-time>--:--:--</div>
                    <div class="xschool-live-clock__date" data-live-clock-date>Loading current date</div>
                    <div class="xschool-live-clock__zone" data-live-clock-zone>Local time</div>
                </div>
            </div>
        </div>
        <div class="teacher-stat-grid">
            <article class="teacher-stat-card"><span>Assigned Subjects</span><strong><?php echo (int)$assignedSubjectCount; ?></strong></article>
            <article class="teacher-stat-card"><span>Recent Groups</span><strong><?php echo (int)$recentTeachingGroupCount; ?></strong></article>
            <article class="teacher-stat-card"><span>Class Teacher Roles</span><strong><?php echo (int)$classTeacherRoleCount; ?></strong></article>
            <article class="teacher-stat-card"><span>My Messages</span><strong><?php echo (int)$myMessageCount; ?></strong></article>
            <?php if($teacherCanTakeAttendance){ ?>
            <article class="teacher-stat-card"><span>Attendance Today</span><strong><?php echo (int)$attendanceSummary["today_session_count"]; ?></strong></article>
            <?php } ?>
        </div>
    </div>
    <aside class="teacher-profile-card">
        <div class="teacher-profile-card__top">
            <img src="<?php echo td_esc($teacherImage); ?>" alt="<?php echo td_esc($teacherName !== "" ? $teacherName : $_SESSION['USERNAME']); ?>">
            <div>
                <span class="teacher-profile-card__eyebrow">Profile</span>
                <h2><?php echo td_esc($teacherName !== "" ? $teacherName : $_SESSION['USERNAME']); ?></h2>
                <p>Teacher<?php echo ($teacherBranch !== "" ? " | ".td_esc($teacherBranch) : ""); ?></p>
            </div>
        </div>
        <div class="teacher-profile-meta">
            <div class="teacher-profile-meta__item"><span>Branch</span><strong><?php echo td_esc($teacherBranch !== "" ? $teacherBranch : "Not Set"); ?></strong></div>
            <div class="teacher-profile-meta__item"><span>Active Batches</span><strong><?php echo (int)$activeBatchCount; ?></strong></div>
            <div class="teacher-profile-meta__item"><span>Image Status</span><strong><?php echo td_esc($teacherFilename !== "" ? "Uploaded" : "Not Uploaded"); ?></strong></div>
        </div>
        <div class="teacher-profile-actions">
            <a class="teacher-secondary-link" href="uploaduser-image.php"><i class="fa fa-arrow-circle-up"></i> Upload Image</a>
            <a class="teacher-secondary-link" href="edit-account.php"><i class="fa fa-user"></i> Edit Profile</a>
            <a class="teacher-secondary-link" href="change-password.php"><i class="fa fa-key"></i> Change Password</a>
        </div>
    </aside>
</section>

<section class="teacher-section">
    <div class="teacher-section__heading">
        <div><span class="teacher-section__eyebrow">Teacher Tools</span><h2>Open today's tools</h2></div>
    </div>
    <div class="teacher-quick-grid">
        <a class="teacher-action-card" href="view-teacher-subject.php"><span class="teacher-action-card__icon"><i class="fa fa-search"></i></span><h3>Assigned Subjects</h3></a>
        <a class="teacher-action-card" href="teacher-course-registration.php"><span class="teacher-action-card__icon"><i class="fa fa-list-alt"></i></span><h3>Course Registration</h3><p>See the exact students who registered for each course you teach this semester.</p></a>
        <?php if($teacherCanTakeAttendance){ ?>
        <a class="teacher-action-card" href="student-attendance.php"><span class="teacher-action-card__icon"><i class="fa fa-check-square-o"></i></span><h3>Student Attendance</h3></a>
        <a class="teacher-action-card" href="student-attendance-report.php"><span class="teacher-action-card__icon"><i class="fa fa-bar-chart"></i></span><h3>Attendance Summary</h3></a>
        <?php } ?>
        <a class="teacher-action-card" href="class-score-entry.php"><span class="teacher-action-card__icon"><i class="fa fa-pencil"></i></span><h3>Class Score Entry</h3></a>
        <a class="teacher-action-card" href="exam-score-entry.php"><span class="teacher-action-card__icon"><i class="fa fa-edit"></i></span><h3>Exam Score Entry</h3></a>
        <a class="teacher-action-card" href="upload-class-score-entry.php"><span class="teacher-action-card__icon"><i class="fa fa-upload"></i></span><h3>Upload Class Scores</h3><p>Use the newer Excel upload flow for class score sheets.</p></a>
        <a class="teacher-action-card" href="upload-exam-score-entry.php"><span class="teacher-action-card__icon"><i class="fa fa-upload"></i></span><h3>Upload Exam Scores</h3><p>Use the newer Excel upload flow for exam score sheets.</p></a>
        <?php if($teacherCanTakeAttendance){ ?>
        <a class="teacher-action-card" href="student-terminal-data.php"><span class="teacher-action-card__icon"><i class="fa fa-commenting"></i></span><h3>Student Remarks</h3></a>
        <?php } ?>
        <a class="teacher-action-card" href="terminal-report.php"><span class="teacher-action-card__icon"><i class="fa fa-book"></i></span><h3>Terminal Reports</h3></a>
        <?php if($teacherHasCounsellingAccess){ ?>
        <a class="teacher-action-card" href="guidance-counselling.php"><span class="teacher-action-card__icon"><i class="fa fa-heartbeat"></i></span><h3>Counselling Cases<?php if($teacherCounsellingSoonCount > 0){ ?><span class="teacher-action-card__badge"><?php echo (int)$teacherCounsellingSoonCount; ?> Soon</span><?php } ?><?php if((int)$teacherCounsellingSummary['pending_count'] > 0){ ?><span class="teacher-action-card__badge"><?php echo (int)$teacherCounsellingSummary['pending_count']; ?> Pending</span><?php } ?></h3><p><?php echo $teacherCounsellingSoonCount > 0 ? "A counselling meeting starts soon. Open the case now and prepare for the session." : "Review student counselling requests, reply privately, and confirm the next session."; ?></p></a>
        <?php } ?>
        <a class="teacher-action-card" href="lesson-timetable-report.php"><span class="teacher-action-card__icon"><i class="fa fa-calendar"></i></span><h3>Lesson Timetable</h3><p>Open your weekly lesson schedule and check today’s teaching periods quickly.</p></a>
        <a class="teacher-action-card" href="online-class.php"><span class="teacher-action-card__icon"><i class="fa fa-video-camera"></i></span><h3>Online Class</h3><p>Schedule a live class link for the right students and manage it from one page.</p></a>
        <a class="teacher-action-card" href="scores-report.php"><span class="teacher-action-card__icon"><i class="fa fa-line-chart"></i></span><h3>Scores Report</h3><p>Check reporting summaries and score outputs for your classes.</p></a>
        <?php if($teacherHasBillingModule){ ?>
        <a class="teacher-action-card" href="payments.php"><span class="teacher-action-card__icon"><i class="fa fa-credit-card"></i></span><h3>Class Payments<?php if($teacherBillingScopeCount > 0){ ?><span class="teacher-action-card__badge"><?php echo (int)$teacherBillingScopeCount; ?> Scope<?php echo ((int)$teacherBillingScopeCount === 1 ? "" : "s"); ?></span><?php } ?></h3><p><?php echo $teacherBillingScopeCount > 0 ? "Open the payment view for the class fee-collection scopes assigned to you." : "Open class payments. Admin still needs to assign your fee-collection class scope."; ?></p></a>
        <?php } ?>
        <a class="teacher-action-card" href="online-voting.php"><?php if($teacherVotingSnapshot && !empty($teacherVotingSnapshot["contest"])){ ?><span class="teacher-action-card__icon"><i class="fa fa-trophy"></i></span><h3>Online Voting</h3><p><?php echo td_esc($teacherVotingSnapshot["contest"]["title"]); ?> is <?php echo td_esc(strtolower(voting_status_label($teacherVotingSnapshot["contest"]["resolved_status"]))); ?>.</p><?php }else{ ?><span class="teacher-action-card__icon"><i class="fa fa-trophy"></i></span><h3>Online Voting</h3><p>Voting details will appear when a contest is available.</p><?php } ?></a>
        <a class="teacher-action-card" href="messages.php"><span class="teacher-action-card__icon"><i class="fa fa-comments"></i></span><h3>Message Board<?php if($messageUnreadCount > 0){ ?><span class="teacher-action-card__badge"><?php echo (int)$messageUnreadCount; ?> New</span><?php } ?></h3><p><?php echo $messageUnreadCount > 0 ? number_format((int)$messageUnreadCount)." unread message".((int)$messageUnreadCount === 1 ? "" : "s")." waiting for you." : "Open the wider message board when you need more than the dashboard preview."; ?></p></a>
    </div>
</section>

<?php if($teacherVotingSnapshot && !empty($teacherVotingSnapshot["contest"])){ ?>
<section class="teacher-section teacher-voting-section">
    <div class="teacher-section__heading">
        <div><span class="teacher-section__eyebrow">Voting</span><h2>Current voting update</h2></div>
        <a class="teacher-panel__link" href="online-voting.php">View Voting</a>
    </div>
    <div class="teacher-voting-card">
        <div class="teacher-voting-card__summary">
            <article><span>Contest</span><strong><?php echo td_esc($teacherVotingSnapshot["contest"]["title"]); ?></strong></article>
            <article><span>Status</span><strong><?php echo td_esc(voting_status_label($teacherVotingSnapshot["contest"]["resolved_status"])); ?></strong></article>
            <article><span>Total Votes</span><strong><?php echo number_format((int)$teacherVotingSnapshot["summary"]["total_votes"]); ?></strong></article>
            <article><span>Leader</span><strong><?php echo td_esc($teacherVotingSnapshot["summary"]["leader_name"]); ?></strong></article>
        </div>
        <div class="teacher-voting-card__leaders">
            <?php foreach($teacherVotingSnapshot["top_candidates"] as $voteCandidate){ ?>
            <a class="teacher-voting-leader" href="online-voting.php?contest=<?php echo rawurlencode((string)$teacherVotingSnapshot["contest"]["contestid"]); ?>&candidate=<?php echo rawurlencode((string)$voteCandidate["candidateid"]); ?>">
                <img src="<?php echo td_esc(voting_candidate_photo($voteCandidate)); ?>" alt="<?php echo td_esc($voteCandidate["candidatename"]); ?>">
                <div>
                    <strong><?php echo td_esc($voteCandidate["candidatename"]); ?></strong>
                    <span><?php echo number_format((int)$voteCandidate["totalvotes"]); ?> votes</span>
                </div>
            </a>
            <?php } ?>
        </div>
    </div>
</section>
<?php } ?>

<div class="teacher-layout">
    <div class="teacher-panel-stack teacher-panel-stack--main">
    <section class="teacher-panel teacher-panel--wide">
        <div class="teacher-panel__header">
            <div><span class="teacher-panel__eyebrow">Teaching Load</span><h2>Recent assigned subjects and classes</h2></div>
            <a class="teacher-panel__link" href="view-teacher-subject.php">View Full Subject List</a>
        </div>
        <?php if(count($recentTeachingGroups) > 0){ ?>
        <div class="teacher-load-grid">
            <?php foreach($recentTeachingGroups as $group){ ?>
            <article class="teacher-load-card">
                <div class="teacher-load-card__head">
                    <div>
                        <h3><?php echo td_esc($group["class_name"]); ?></h3>
                        <p><?php echo td_esc($group["session_label"]); ?></p>
                    </div>
                    <div class="teacher-load-card__badges">
                        <span class="teacher-pill"><?php echo count($group["subjects"]); ?> Subject<?php echo (count($group["subjects"])===1?"":"s"); ?></span>
                        <?php if($group["is_class_teacher"]){ ?><span class="teacher-pill teacher-pill--accent">Class Teacher</span><?php } ?>
                    </div>
                </div>
                <div class="teacher-chip-row"><?php foreach($group["subjects"] as $subject){ ?><span class="teacher-chip"><?php echo td_esc($subject); ?></span><?php } ?></div>
            </article>
            <?php } ?>
        </div>
        <?php if($teachingGroupCount > $recentTeachingGroupLimit){ ?>
        <div class="teacher-empty-state teacher-empty-state--compact">
            <p>Showing latest <?php echo (int)$recentTeachingGroupLimit; ?> of <?php echo (int)$teachingGroupCount; ?>.</p>
        </div>
        <?php } ?>
        <?php } else { ?>
        <div class="teacher-empty-state"><h3>No subject assignments yet</h3></div>
        <?php } ?>
    </section>

    <section class="teacher-panel teacher-panel--wide">
        <div class="teacher-panel__header">
            <div><span class="teacher-panel__eyebrow">Subject Performance</span><h2>How your subjects are performing</h2></div>
            <a class="teacher-panel__link" href="scores-report.php">Open Scores Report</a>
        </div>
        <form method="get" action="teacher-page.php" class="teacher-performance-toolbar">
            <div class="teacher-performance-toolbar__field">
                <label for="teacher_perf_year">Academic Year</label>
                <select id="teacher_perf_year" name="teacher_perf_year">
                    <?php foreach($teacherPerformanceYearOptions as $yearOption){ ?>
                    <option value="<?php echo td_esc($yearOption); ?>"<?php echo ($teacherPerformanceYear === $yearOption ? " selected" : ""); ?>><?php echo td_esc($yearOption); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="teacher-performance-toolbar__actions">
                <button type="submit" class="teacher-primary-btn"><i class="fa fa-filter"></i> View Year</button>
            </div>
        </form>

        <div class="teacher-performance-scope">
            <strong>Selected Year:</strong> <?php echo td_esc($teacherPerformanceYear); ?> Academic Year
            <?php if($teacherPerformancePreviousYear !== null){ ?>
            <span>Compared with <?php echo td_esc($teacherPerformancePreviousYear); ?></span>
            <?php } ?>
        </div>

        <div class="teacher-performance-kpis">
            <article class="teacher-performance-kpi">
                <span>Subjects With Scores</span>
                <strong><?php echo number_format((int)$teacherPerfSubjectCount); ?></strong>
            </article>
            <article class="teacher-performance-kpi">
                <span>Average Score</span>
                <strong><?php echo number_format((float)$teacherPerfCurrentAvg, 2); ?>%</strong>
            </article>
            <article class="teacher-performance-kpi">
                <span>Average Pass Rate</span>
                <strong><?php echo number_format((float)$teacherPerfCurrentPass, 2); ?>%</strong>
            </article>
            <article class="teacher-performance-kpi">
                <span>Top Subject</span>
                <strong><?php echo td_esc($teacherPerfBestSubject); ?></strong>
                <small><?php echo number_format((float)$teacherPerfBestScore, 2); ?>%</small>
            </article>
            <article class="teacher-performance-kpi">
                <span>Year Comparison</span>
                <strong><?php echo ($teacherPerformancePreviousYear !== null ? td_esc($teacherPerformancePreviousYear) : "No prior year"); ?></strong>
                <small class="<?php echo td_esc(td_perf_delta_class($teacherPerfYearDelta)); ?>"><?php echo td_esc(td_perf_delta_text($teacherPerfYearDelta)); ?></small>
            </article>
        </div>

        <?php if(count($teacherPerfRows) > 0 || count($teacherPerfTrendLabels) > 0){ ?>
        <div class="teacher-performance-grid">
            <article class="teacher-performance-card">
                <div class="teacher-performance-card__head">
                    <h3><?php echo td_esc($teacherPerformanceYear); ?> Subject Snapshot</h3>
                    <span>Average score and pass rate by subject</span>
                </div>
                <div class="teacher-performance-chart-wrap">
                    <canvas id="teacherSubjectPerformanceChart" height="280" aria-label="Teacher subject performance chart"></canvas>
                </div>
            </article>
            <article class="teacher-performance-card">
                <div class="teacher-performance-card__head">
                    <h3>Previous Years Trend</h3>
                    <span>Overall performance across academic years</span>
                </div>
                <div class="teacher-performance-chart-wrap">
                    <canvas id="teacherSubjectTrendChart" height="280" aria-label="Teacher subject trend chart"></canvas>
                </div>
            </article>
        </div>

        <div class="teacher-performance-table-wrap">
            <div class="teacher-performance-card__head">
                <h3>Subject-by-Subject Comparison</h3>
                <span>Compare this year with the previous year where data exists</span>
            </div>
            <div class="teacher-performance-table-scroll">
                <table class="teacher-performance-table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th><?php echo td_esc($teacherPerformanceYear); ?> Avg %</th>
                            <th>Previous Year</th>
                            <th>Previous Avg %</th>
                            <th>Change</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($teacherPerfComparisonRows as $comparisonRow){ ?>
                        <tr>
                            <td><?php echo td_esc($comparisonRow["subject"]); ?></td>
                            <td><?php echo number_format((float)$comparisonRow["current_avg"], 2); ?></td>
                            <td><?php echo td_esc($comparisonRow["previous_year"] !== null ? $comparisonRow["previous_year"] : "N/A"); ?></td>
                            <td><?php echo ($comparisonRow["previous_avg"] !== null ? number_format((float)$comparisonRow["previous_avg"], 2) : "N/A"); ?></td>
                            <td><span class="<?php echo td_esc(td_perf_delta_class($comparisonRow["delta"])); ?>"><?php echo td_esc(td_perf_delta_text($comparisonRow["delta"])); ?></span></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php } else { ?>
        <div class="teacher-empty-state teacher-empty-state--compact">
            <p>No score data was found for the selected academic year yet.</p>
        </div>
        <?php } ?>
    </section>
    </div>

    <div class="teacher-panel-stack">
        <section class="teacher-panel">
            <div class="teacher-panel__header">
                <div><span class="teacher-panel__eyebrow">Engagement</span><h2>Keep your teaching flow active</h2></div>
            </div>
            <div class="teacher-engagement-hero teacher-engagement-hero--<?php echo td_esc($engagementSummary["badge"]["tone"]); ?>">
                <div class="teacher-engagement-hero__copy">
                    <span class="teacher-engagement-hero__eyebrow">Current Level</span>
                    <h3><?php echo td_esc($engagementSummary["badge"]["label"]); ?></h3>
                    <div class="teacher-engagement-stars" aria-label="<?php echo (int)$engagementSummary["stars"]; ?> stars">
                        <?php for($starIndex = 1; $starIndex <= 5; $starIndex++){ ?>
                        <i class="fa fa-star<?php echo ($starIndex <= (int)$engagementSummary["stars"]) ? " is-active" : ""; ?>"></i>
                        <?php } ?>
                    </div>
                    <div class="teacher-engagement-total"><?php echo number_format((int)$engagementSummary["total_points"]); ?> points</div>
                    <div class="teacher-engagement-meter" aria-hidden="true">
                        <span class="teacher-engagement-meter__fill" style="width: <?php echo (int)$engagementSummary["progress_percent"]; ?>%;"></span>
                    </div>
                    <p class="teacher-engagement-progress-copy">
                        <?php if(!empty($engagementSummary["next_badge"])){ ?>
                            <?php echo number_format((int)$engagementSummary["points_to_next"]); ?> more point<?php echo ((int)$engagementSummary["points_to_next"] === 1 ? "" : "s"); ?> to reach <?php echo td_esc((string)$engagementSummary["next_badge"]["label"]); ?>.
                        <?php } else { ?>
                            Top level reached. Keep the momentum going.
                        <?php } ?>
                    </p>
                </div>
                <div class="teacher-engagement-side">
                    <article class="teacher-engagement-stat">
                        <span>This Week</span>
                        <strong><?php echo number_format((int)$engagementSummary["week_points"]); ?></strong>
                    </article>
                    <article class="teacher-engagement-stat">
                        <span>Active Streak</span>
                        <strong><?php echo number_format((int)$engagementSummary["streak_days"]); ?> Day<?php echo ((int)$engagementSummary["streak_days"] === 1 ? "" : "s"); ?></strong>
                    </article>
                    <article class="teacher-engagement-stat">
                        <span>Progress</span>
                        <strong><?php echo (int)$engagementSummary["progress_percent"]; ?>%</strong>
                    </article>
                </div>
            </div>
            <div class="teacher-engagement-list">
                <?php if(count($engagementRecent) > 0){ ?>
                    <?php foreach($engagementRecent as $activity){ ?>
                    <article class="teacher-engagement-item">
                        <div class="teacher-engagement-item__meta">
                            <strong><?php echo td_esc((string)$activity["actionlabel"]); ?></strong>
                            <span><?php echo td_esc(td_date((string)$activity["datetimeentry"])); ?></span>
                        </div>
                        <div class="teacher-engagement-points">+<?php echo number_format((int)$activity["pointvalue"]); ?></div>
                    </article>
                    <?php } ?>
                <?php } else { ?>
                <div class="teacher-empty-state teacher-empty-state--compact"><p>No activity yet.</p></div>
                <?php } ?>
            </div>
        </section>
        <section class="teacher-panel">
            <div class="teacher-panel__header">
                <div><span class="teacher-panel__eyebrow">Duty Roster</span><h2>Duty reminders on your dashboard</h2></div>
            </div>
            <?php if(count($dutyDashboard["cards"]) > 0){ ?>
            <div class="teacher-duty-grid">
                <?php foreach($dutyDashboard["cards"] as $card){ ?>
                <article class="teacher-duty-card teacher-duty-card--<?php echo td_esc($card["tone"]); ?>">
                    <div class="teacher-duty-card__top">
                        <span class="teacher-duty-label"><?php echo td_esc($card["label"]); ?></span>
                        <span class="teacher-duty-period"><?php echo td_esc($card["period"]); ?></span>
                    </div>
                    <h3><?php echo td_esc($card["title"]); ?></h3>
                    <p><?php echo td_esc($card["location"] !== "" ? $card["location"] : "Pending"); ?></p>
                    <?php if($card["note"] !== ""){ ?><small><?php echo td_esc($card["note"]); ?></small><?php } ?>
                </article>
                <?php } ?>
            </div>
            <?php } else { ?>
            <div class="teacher-empty-state teacher-empty-state--compact"><p>No duty roster yet.</p></div>
            <?php } ?>
        </section>

        <section class="teacher-panel">
            <div class="teacher-panel__header">
                <div><span class="teacher-panel__eyebrow">Class Teacher</span><h2>My class-teacher duties</h2></div>
            </div>
            <?php if($classTeacherRoleCount > 0){ ?>
            <div class="teacher-role-list">
                <?php foreach($classTeacherRoles as $role){ ?>
                <article class="teacher-role-card">
                    <h3><?php echo td_esc($role["class_name"]); ?></h3>
                    <p><?php echo td_esc($role["session_label"]); ?></p>
                    <span>Class teacher assignment</span>
                </article>
                <?php } ?>
            </div>
            <?php } else { ?>
            <div class="teacher-empty-state teacher-empty-state--compact"><p>No class-teacher role yet.</p></div>
            <?php } ?>
        </section>

        <section class="teacher-panel">
            <div class="teacher-panel__header">
                <div><span class="teacher-panel__eyebrow">Resources</span><h2>Downloads and links</h2></div>
            </div>
            <div class="teacher-resource-list">
                <a class="teacher-resource-link" href="download-classscore-template.php"><span class="teacher-resource-link__icon"><i class="fa fa-download"></i></span><span class="teacher-resource-link__body"><strong>Class Score Template</strong></span></a>
                <a class="teacher-resource-link" href="download-examscore-template.php"><span class="teacher-resource-link__icon"><i class="fa fa-download"></i></span><span class="teacher-resource-link__body"><strong>Exam Score Template</strong></span></a>
                <a class="teacher-resource-link" href="download-classexamscore-template.php"><span class="teacher-resource-link__icon"><i class="fa fa-download"></i></span><span class="teacher-resource-link__body"><strong>Class & Exam Template</strong></span></a>
                <a class="teacher-resource-link" href="lesson-timetable-report.php"><span class="teacher-resource-link__icon"><i class="fa fa-calendar"></i></span><span class="teacher-resource-link__body"><strong>Lesson Timetable</strong></span></a>
                <a class="teacher-resource-link" href="examinationtimetablereport.php"><span class="teacher-resource-link__icon"><i class="fa fa-calendar"></i></span><span class="teacher-resource-link__body"><strong>Exam Timetable Report</strong></span></a>
            </div>
        </section>
    </div>
</div>

<div class="teacher-layout teacher-layout--messages">
    <section class="teacher-panel" id="teacher-messages">
        <div class="teacher-panel__header">
            <div><span class="teacher-panel__eyebrow">Message Center</span><h2>Send and manage your messages</h2></div>
            <a class="teacher-panel__link" href="messages.php">View Messages<?php if($messageUnreadCount > 0){ ?><span class="teacher-panel__badge"><?php echo (int)$messageUnreadCount; ?> New</span><?php } ?></a>
        </div>
        <form method="post" action="teacher-page.php#teacher-messages" class="teacher-message-form">
            <label for="message">Write a message</label>
            <textarea id="message" name="message" placeholder="Share an update, request support, or leave a note for the school team." required></textarea>
            <?php if(count($teacherMessageTargets) > 1){ ?>
            <label for="message_target">Send To</label>
            <select id="message_target" name="message_target">
                <?php foreach($teacherMessageTargets as $targetKey => $targetMeta){ ?>
                <option value="<?php echo td_esc($targetKey); ?>"<?php echo ((string)$targetKey === (string)$teacherDefaultMessageTarget ? " selected" : ""); ?>><?php echo td_esc($targetMeta['recipient_label']); ?></option>
                <?php } ?>
            </select>
            <div class="teacher-message-target-preview" data-teacher-target-preview>
                Sending to: <strong><?php echo td_esc($teacherMessageTargets[$teacherDefaultMessageTarget]['recipient_label']); ?></strong>
            </div>
            <?php } ?>
            <div class="teacher-message-form__actions">
                <span><?php echo (count($teacherMessageTargets) > 1) ? "Choose who should receive this message before sending." : "Your message will go to admin only."; ?></span>
                <button class="teacher-primary-btn" type="submit" name="send_message"><i class="fa fa-send"></i> Send Message</button>
            </div>
        </form>

        <div class="teacher-message-list">
            <?php if(count($myMessages) > 0){ ?>
                <?php foreach($myMessages as $message){ ?>
                <article class="teacher-message-card">
                    <div class="teacher-message-card__meta">
                        <span><?php echo td_esc(td_date($message["datetimeentry"])); ?><?php echo (trim((string)$message["recipient_label"]) !== '' ? ' · To: '.td_esc((string)$message["recipient_label"]) : ''); ?></span>
                        <form method="post" action="teacher-page.php#teacher-messages">
                            <input type="hidden" name="messageid" value="<?php echo td_esc((string)$message["messageid"]); ?>">
                            <button type="submit" name="delete_message" class="teacher-message-delete" onclick="return confirm('Delete this message?');"><i class="fa fa-trash"></i> Delete</button>
                        </form>
                    </div>
                    <p><?php echo nl2br(td_esc($message["messages"])); ?></p>
                </article>
                <?php } ?>
            <?php } else { ?>
            <div class="teacher-empty-state teacher-empty-state--compact"><p>No messages yet.</p></div>
            <?php } ?>
        </div>
    </section>

</div>
</main>
<script>
(function(){
    if(typeof Chart !== 'function'){
        return;
    }
    var subjectCanvas = document.getElementById('teacherSubjectPerformanceChart');
    var trendCanvas = document.getElementById('teacherSubjectTrendChart');
    var subjectLabels = <?php echo json_encode(array_values($teacherPerfLabels)); ?>;
    var subjectAverageScores = <?php echo json_encode(array_values($teacherPerfAvg)); ?>;
    var subjectPassRates = <?php echo json_encode(array_values($teacherPerfPass)); ?>;
    var trendLabels = <?php echo json_encode(array_values($teacherPerfTrendLabels)); ?>;
    var trendAverageScores = <?php echo json_encode(array_values($teacherPerfTrendAvg)); ?>;
    var trendPassRates = <?php echo json_encode(array_values($teacherPerfTrendPass)); ?>;
    if(subjectCanvas && subjectLabels.length > 0){
        new Chart(subjectCanvas, {
            type: 'bar',
            data: {
                labels: subjectLabels,
                datasets: [
                    {
                        label: 'Average Score',
                        data: subjectAverageScores,
                        backgroundColor: 'rgba(46, 122, 199, 0.72)',
                        borderColor: '#2e7ac7',
                        borderWidth: 1.2,
                        borderRadius: 10,
                        maxBarThickness: 42
                    },
                    {
                        label: 'Pass Rate',
                        data: subjectPassRates,
                        backgroundColor: 'rgba(15, 154, 141, 0.7)',
                        borderColor: '#0f9a8d',
                        borderWidth: 1.2,
                        borderRadius: 10,
                        maxBarThickness: 42
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context){
                                return context.dataset.label + ': ' + Number(context.raw).toFixed(2) + '%';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#17314b'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value){
                                return value + '%';
                            },
                            color: '#5f748c'
                        },
                        grid: {
                            color: 'rgba(17, 42, 68, 0.08)'
                        }
                    }
                }
            }
        });
    }
    if(trendCanvas && trendLabels.length > 0){
        new Chart(trendCanvas, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [
                    {
                        label: 'Average Score',
                        data: trendAverageScores,
                        borderColor: '#2e7ac7',
                        backgroundColor: 'rgba(46, 122, 199, 0.14)',
                        fill: true,
                        tension: 0.28,
                        borderWidth: 2.5,
                        pointRadius: 4,
                        pointHoverRadius: 5
                    },
                    {
                        label: 'Pass Rate',
                        data: trendPassRates,
                        borderColor: '#0f9a8d',
                        backgroundColor: 'rgba(15, 154, 141, 0.1)',
                        fill: true,
                        tension: 0.28,
                        borderWidth: 2.5,
                        pointRadius: 4,
                        pointHoverRadius: 5
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context){
                                return context.dataset.label + ': ' + Number(context.raw).toFixed(2) + '%';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#17314b'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value){
                                return value + '%';
                            },
                            color: '#5f748c'
                        },
                        grid: {
                            color: 'rgba(17, 42, 68, 0.08)'
                        }
                    }
                }
            }
        });
    }
})();

(function(){
    var select = document.getElementById('message_target');
    var preview = document.querySelector('[data-teacher-target-preview]');
    if(!select || !preview){
        return;
    }
    var strong = preview.querySelector('strong');
    var syncPreview = function(){
        var text = '';
        if(select.selectedIndex >= 0 && select.options[select.selectedIndex]){
            text = select.options[select.selectedIndex].text;
        }
        if(strong){
            strong.textContent = text;
        }else{
            preview.textContent = text === '' ? '' : 'Sending to: ' + text;
        }
    };
    select.addEventListener('change', syncPreview);
    syncPreview();
})();
</script>
</body>
</html>
