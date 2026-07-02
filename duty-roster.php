<?php
session_start();
include("dbstring.php");
include("check-login.php");
include("duty-roster-utils.php");
ensure_duty_roster_tables($con);

if(!duty_roster_can_view_module($con)){
    header("location:".duty_roster_landing_page());
    exit();
}

$canManageDutyRoster = duty_roster_can_manage_module($con);
$isHeadmasterViewer = duty_roster_is_headmaster();
$currentBranchId = isset($_SESSION['BRANCHID']) ? trim((string)$_SESSION['BRANCHID']) : "";
$dutyBranchFilter = "";
$dutyRosterJoinBranchFilter = "";
if($isHeadmasterViewer && $currentBranchId !== ""){
    $branchEsc = mysqli_real_escape_string($con, $currentBranchId);
    $dutyBranchFilter = " AND userid IN (SELECT userid FROM tblsystemuser WHERE branchid='$branchEsc' AND systemtype='Teacher')";
    $dutyRosterJoinBranchFilter = " AND su.branchid='$branchEsc'";
}

function dr_alert($type, $message){
    $class = "duty-roster-alert duty-roster-alert--info";
    if($type === "success"){ $class = "duty-roster-alert duty-roster-alert--success"; }
    elseif($type === "error"){ $class = "duty-roster-alert duty-roster-alert--error"; }
    elseif($type === "warning"){ $class = "duty-roster-alert duty-roster-alert--warning"; }
    return "<div class=\"".$class."\">".duty_roster_escape($message)."</div>";
}

function dr_teacher_name($row){
    return duty_roster_teacher_fullname($row);
}

function dr_normalize_teacher_ids($values){
    $items = array();
    foreach((array)$values as $value){
        $value = trim((string)$value);
        if($value !== ""){
            $items[$value] = true;
        }
    }
    return array_keys($items);
}

function dr_phase_class($phase){
    $phase = strtolower(trim((string)$phase));
    if($phase === "active"){ return "duty-roster-badge duty-roster-badge--active"; }
    if($phase === "upcoming"){ return "duty-roster-badge duty-roster-badge--upcoming"; }
    if($phase === "completed"){ return "duty-roster-badge duty-roster-badge--completed"; }
    if($phase === "inactive"){ return "duty-roster-badge duty-roster-badge--inactive"; }
    if($phase === "sent"){ return "duty-roster-badge duty-roster-badge--sent"; }
    if($phase === "failed" || $phase === "no_phone"){ return "duty-roster-badge duty-roster-badge--failed"; }
    return "duty-roster-badge duty-roster-badge--neutral";
}

function dr_reminder_badge_class($status){
    $status = strtoupper(trim((string)$status));
    if($status === "SENT"){ return "duty-roster-badge duty-roster-badge--sent"; }
    if($status === "FAILED" || $status === "NO_PHONE"){ return "duty-roster-badge duty-roster-badge--failed"; }
    if($status === "N/A"){ return "duty-roster-badge duty-roster-badge--completed"; }
    return "duty-roster-badge duty-roster-badge--neutral";
}

$flashMessage = isset($_SESSION['Message']) ? (string)$_SESSION['Message'] : "";
$_SESSION['Message'] = "";
$runSummary = null;
$today = duty_roster_normalize_date();
$currentWeek = duty_roster_current_week_window($today);
$nextWeek = duty_roster_next_week_window($today);

$formValues = array(
    "dutyid" => "",
    "dutygroupid" => "",
    "userid" => "",
    "teacherids" => array(),
    "dutytitle" => "",
    "dutylocation" => "",
    "dutynote" => "",
    "startdate" => $nextWeek['start'],
    "enddate" => $nextWeek['end'],
);

if($canManageDutyRoster && isset($_POST['save_duty'])){
    $formValues['dutyid'] = trim((string)(isset($_POST['dutyid']) ? $_POST['dutyid'] : ""));
    $formValues['dutygroupid'] = trim((string)(isset($_POST['dutygroupid']) ? $_POST['dutygroupid'] : ""));
    $formValues['userid'] = trim((string)(isset($_POST['userid']) ? $_POST['userid'] : ""));
    $formValues['teacherids'] = dr_normalize_teacher_ids(isset($_POST['teacherids']) ? $_POST['teacherids'] : array());
    if(count($formValues['teacherids']) === 0 && $formValues['userid'] !== ""){
        $formValues['teacherids'] = array($formValues['userid']);
    }
    $formValues['dutytitle'] = trim((string)(isset($_POST['dutytitle']) ? $_POST['dutytitle'] : ""));
    $formValues['dutylocation'] = trim((string)(isset($_POST['dutylocation']) ? $_POST['dutylocation'] : ""));
    $formValues['dutynote'] = trim((string)(isset($_POST['dutynote']) ? $_POST['dutynote'] : ""));
    $formValues['startdate'] = trim((string)(isset($_POST['startdate']) ? $_POST['startdate'] : ""));
    $formValues['enddate'] = trim((string)(isset($_POST['enddate']) ? $_POST['enddate'] : ""));

    if(count($formValues['teacherids']) === 0 || $formValues['dutytitle'] === "" || $formValues['startdate'] === "" || $formValues['enddate'] === ""){
        $flashMessage = dr_alert("warning", "Please select one or more teachers and complete the duty title and dates.");
    } elseif(strtotime($formValues['enddate']) === false || strtotime($formValues['startdate']) === false){
        $flashMessage = dr_alert("warning", "Please enter valid duty dates.");
    } elseif($formValues['enddate'] < $formValues['startdate']){
        $flashMessage = dr_alert("warning", "Duty end date cannot be earlier than the start date.");
    } else {
        $teacherRows = array();
        foreach($formValues['teacherids'] as $teacherIdValue){
            $teacherIdEsc = mysqli_real_escape_string($con, $teacherIdValue);
            $teacherCheck = mysqli_query($con, "SELECT userid,firstname,surname,othernames FROM tblsystemuser WHERE userid='$teacherIdEsc' AND systemtype='Teacher' AND status='active' LIMIT 1");
            if(!$teacherCheck || !($teacherRow = mysqli_fetch_array($teacherCheck, MYSQLI_ASSOC))){
                $teacherRows = array();
                break;
            }
            $teacherRows[$teacherIdValue] = $teacherRow;
        }

        if(count($teacherRows) !== count($formValues['teacherids'])){
            $flashMessage = dr_alert("error", "One or more selected teachers are no longer active.");
        } else {
            $dutyTitleEsc = mysqli_real_escape_string($con, $formValues['dutytitle']);
            $locationEsc = mysqli_real_escape_string($con, $formValues['dutylocation']);
            $noteEsc = mysqli_real_escape_string($con, $formValues['dutynote']);
            $startEsc = mysqli_real_escape_string($con, $formValues['startdate']);
            $endEsc = mysqli_real_escape_string($con, $formValues['enddate']);
            $recordedByEsc = mysqli_real_escape_string($con, (string)$_SESSION['USERID']);

            if($formValues['dutyid'] !== ""){
                $dutyIdEsc = mysqli_real_escape_string($con, $formValues['dutyid']);
                $exists = mysqli_query($con, "SELECT dutyid,dutygroupid,userid FROM tbldutyroster WHERE dutyid='$dutyIdEsc' LIMIT 1");
                if($exists && ($existingDuty = mysqli_fetch_array($exists, MYSQLI_ASSOC))){
                    $groupId = trim((string)$existingDuty['dutygroupid']) !== "" ? trim((string)$existingDuty['dutygroupid']) : (string)$existingDuty['dutyid'];
                    $groupIdEsc = mysqli_real_escape_string($con, $groupId);
                    $groupRows = array();
                    $groupRes = mysqli_query($con, "SELECT dutyid,userid FROM tbldutyroster WHERE dutygroupid='$groupIdEsc' AND status='active' ORDER BY datetimeentry ASC");
                    if($groupRes){
                        while($groupRow = mysqli_fetch_array($groupRes, MYSQLI_ASSOC)){
                            $groupRows[(string)$groupRow['userid']] = (string)$groupRow['dutyid'];
                        }
                    }
                    if(count($groupRows) === 0){
                        $groupRows[(string)$existingDuty['userid']] = (string)$existingDuty['dutyid'];
                    }

                    $duplicateFound = false;
                    foreach($formValues['teacherids'] as $teacherIdValue){
                        if(isset($groupRows[$teacherIdValue])){
                            continue;
                        }
                        $teacherIdEsc = mysqli_real_escape_string($con, $teacherIdValue);
                        $duplicate = mysqli_query($con, "SELECT dutyid FROM tbldutyroster
                            WHERE userid='$teacherIdEsc'
                              AND dutytitle='$dutyTitleEsc'
                              AND startdate='$startEsc'
                              AND enddate='$endEsc'
                              AND status='active'
                            LIMIT 1");
                        if($duplicate && mysqli_num_rows($duplicate) > 0){
                            $duplicateFound = true;
                            break;
                        }
                    }

                    if($duplicateFound){
                        $flashMessage = dr_alert("warning", "One of the selected teachers already has that duty entry for the same date range.");
                    } else {
                        $selectedMap = array();
                        foreach($formValues['teacherids'] as $teacherIdValue){
                            $selectedMap[$teacherIdValue] = true;
                        }

                        foreach($groupRows as $teacherIdValue => $rowDutyId){
                            $rowDutyIdEsc = mysqli_real_escape_string($con, $rowDutyId);
                            if(isset($selectedMap[$teacherIdValue])){
                                mysqli_query($con, "UPDATE tbldutyroster
                                    SET dutygroupid='$groupIdEsc',
                                        dutytitle='$dutyTitleEsc',
                                        dutylocation='$locationEsc',
                                        dutynote='$noteEsc',
                                        startdate='$startEsc',
                                        enddate='$endEsc',
                                        status='active',
                                        recordedby='$recordedByEsc',
                                        datetimeentry=NOW()
                                    WHERE dutyid='$rowDutyIdEsc'
                                    LIMIT 1");
                            } else {
                                mysqli_query($con, "DELETE FROM tbldutyreminderlog WHERE dutyid='$rowDutyIdEsc'");
                                mysqli_query($con, "DELETE FROM tbldutyroster WHERE dutyid='$rowDutyIdEsc' LIMIT 1");
                            }
                        }

                        foreach($formValues['teacherids'] as $teacherIdValue){
                            if(isset($groupRows[$teacherIdValue])){
                                continue;
                            }
                            $teacherIdEsc = mysqli_real_escape_string($con, $teacherIdValue);
                            $newDutyId = duty_roster_make_id("DUTY");
                            mysqli_query($con, "INSERT INTO tbldutyroster
                                (dutyid,dutygroupid,userid,dutytitle,dutylocation,dutynote,startdate,enddate,status,datetimeentry,recordedby)
                                VALUES('$newDutyId','$groupIdEsc','$teacherIdEsc','$dutyTitleEsc','$locationEsc','$noteEsc','$startEsc','$endEsc','active',NOW(),'$recordedByEsc')");
                        }

                        $_SESSION['Message'] = dr_alert("success", count($formValues['teacherids']) > 1
                            ? "Duty team updated successfully."
                            : "Duty roster assignment updated successfully.");
                        header("location:duty-roster.php");
                        exit();
                    }
                } else {
                    $flashMessage = dr_alert("error", "The duty assignment you tried to edit could not be found.");
                }
            } else {
                $duplicateFound = false;
                foreach($formValues['teacherids'] as $teacherIdValue){
                    $teacherIdEsc = mysqli_real_escape_string($con, $teacherIdValue);
                    $duplicate = mysqli_query($con, "SELECT dutyid FROM tbldutyroster
                        WHERE userid='$teacherIdEsc'
                          AND dutytitle='$dutyTitleEsc'
                          AND startdate='$startEsc'
                          AND enddate='$endEsc'
                          AND status='active'
                        LIMIT 1");
                    if($duplicate && mysqli_num_rows($duplicate) > 0){
                        $duplicateFound = true;
                        break;
                    }
                }
                if($duplicateFound){
                    $flashMessage = dr_alert("warning", "One of the selected teachers already has that duty entry for the same date range.");
                } else {
                    $groupId = duty_roster_make_id("DGRP");
                    $groupIdEsc = mysqli_real_escape_string($con, $groupId);
                    $ok = true;
                    foreach($formValues['teacherids'] as $teacherIdValue){
                        $teacherIdEsc = mysqli_real_escape_string($con, $teacherIdValue);
                        $dutyId = duty_roster_make_id("DUTY");
                        $insertOk = mysqli_query($con, "INSERT INTO tbldutyroster
                            (dutyid,dutygroupid,userid,dutytitle,dutylocation,dutynote,startdate,enddate,status,datetimeentry,recordedby)
                            VALUES('$dutyId','$groupIdEsc','$teacherIdEsc','$dutyTitleEsc','$locationEsc','$noteEsc','$startEsc','$endEsc','active',NOW(),'$recordedByEsc')");
                        if(!$insertOk){
                            $ok = false;
                        }
                    }
                    $_SESSION['Message'] = $ok
                        ? dr_alert("success", count($formValues['teacherids']) > 1 ? "Duty team saved successfully." : "Duty roster assignment saved successfully.")
                        : dr_alert("error", "Failed to save the duty roster assignment.");
                    header("location:duty-roster.php");
                    exit();
                }
            }
        }
    }
}

if($canManageDutyRoster && isset($_POST['change_duty_status'])){
    $dutyId = trim((string)(isset($_POST['dutyid']) ? $_POST['dutyid'] : ""));
    $action = trim((string)(isset($_POST['status_action']) ? $_POST['status_action'] : ""));
    if($dutyId !== ""){
        $dutyIdEsc = mysqli_real_escape_string($con, $dutyId);
        if($action === "deactivate"){
            $ok = mysqli_query($con, "UPDATE tbldutyroster SET status='inactive' WHERE dutyid='$dutyIdEsc' LIMIT 1");
            $_SESSION['Message'] = $ok ? dr_alert("success", "Duty assignment deactivated.") : dr_alert("error", "Failed to deactivate duty assignment.");
        } elseif($action === "activate"){
            $ok = mysqli_query($con, "UPDATE tbldutyroster SET status='active' WHERE dutyid='$dutyIdEsc' LIMIT 1");
            $_SESSION['Message'] = $ok ? dr_alert("success", "Duty assignment reactivated.") : dr_alert("error", "Failed to reactivate duty assignment.");
        } elseif($action === "delete"){
            mysqli_query($con, "DELETE FROM tbldutyreminderlog WHERE dutyid='$dutyIdEsc'");
            $ok = mysqli_query($con, "DELETE FROM tbldutyroster WHERE dutyid='$dutyIdEsc' LIMIT 1");
            $_SESSION['Message'] = $ok ? dr_alert("success", "Duty assignment deleted permanently.") : dr_alert("error", "Failed to delete duty assignment.");
        }
    }
    header("location:duty-roster.php");
    exit();
}

if($canManageDutyRoster && isset($_POST['run_due_reminders'])){
    $runSummary = duty_roster_run_weekly_reminders($con, null, isset($_SESSION['USERID']) ? $_SESSION['USERID'] : "SYSTEM");
    $flashMessage = dr_alert(
        "info",
        "Reminder run completed for ".duty_roster_format_date($runSummary['current_week_start'])." - ".duty_roster_format_date($runSummary['current_week_end'])." and ".duty_roster_format_date($runSummary['next_week_start'])." - ".duty_roster_format_date($runSummary['next_week_end'])."."
    );
}

if($canManageDutyRoster && isset($_POST['send_single_reminder'])){
    $dutyId = trim((string)(isset($_POST['dutyid']) ? $_POST['dutyid'] : ""));
    $reminderType = trim((string)(isset($_POST['reminder_type']) ? $_POST['reminder_type'] : "upcoming_week"));
    $singleResult = duty_roster_send_single_reminder($con, $dutyId, $reminderType, null, isset($_SESSION['USERID']) ? $_SESSION['USERID'] : "SYSTEM");
    $scopeLabel = isset($singleResult['scope']) ? $singleResult['scope'] : duty_roster_reminder_scope_label($reminderType);
    if($singleResult['status'] === "SENT"){
        $_SESSION['Message'] = dr_alert("success", $scopeLabel." reminder sent for ".$singleResult['teacher'].".");
    } elseif($singleResult['status'] === "ALREADY_SENT"){
        $_SESSION['Message'] = dr_alert("info", $scopeLabel." reminder was already sent for ".$singleResult['teacher'].".");
    } elseif($singleResult['status'] === "NO_PHONE"){
        $_SESSION['Message'] = dr_alert("warning", $singleResult['teacher']." has no phone number, so the ".$scopeLabel." reminder could not be sent.");
    } elseif($singleResult['status'] === "NOT_DUE"){
        $_SESSION['Message'] = dr_alert("warning", "That duty does not fall within the selected ".$scopeLabel." window.");
    } elseif($singleResult['status'] === "FAILED"){
        $_SESSION['Message'] = dr_alert("error", $scopeLabel." reminder failed for ".$singleResult['teacher'].".");
    } else {
        $_SESSION['Message'] = dr_alert("error", "The reminder could not be sent for that duty entry.");
    }
    header("location:duty-roster.php");
    exit();
}

if($canManageDutyRoster && isset($_GET['edit_duty'])){
    $editDutyId = trim((string)$_GET['edit_duty']);
    if($editDutyId !== ""){
        $editDutyIdEsc = mysqli_real_escape_string($con, $editDutyId);
        $editRes = mysqli_query($con, "SELECT dutyid,dutygroupid,userid,dutytitle,dutylocation,dutynote,startdate,enddate
            FROM tbldutyroster
            WHERE dutyid='$editDutyIdEsc'
            LIMIT 1");
        if($editRes && $editRow = mysqli_fetch_array($editRes, MYSQLI_ASSOC)){
            $editGroupId = trim((string)$editRow['dutygroupid']) !== "" ? trim((string)$editRow['dutygroupid']) : (string)$editRow['dutyid'];
            $editTeacherIds = duty_roster_group_member_ids($con, $editGroupId);
            if(count($editTeacherIds) === 0){
                $editTeacherIds = array((string)$editRow['userid']);
            }
            $formValues = array(
                "dutyid" => (string)$editRow['dutyid'],
                "dutygroupid" => $editGroupId,
                "userid" => (string)$editRow['userid'],
                "teacherids" => $editTeacherIds,
                "dutytitle" => (string)$editRow['dutytitle'],
                "dutylocation" => (string)$editRow['dutylocation'],
                "dutynote" => (string)$editRow['dutynote'],
                "startdate" => (string)$editRow['startdate'],
                "enddate" => (string)$editRow['enddate'],
            );
        }
    }
}

$teacherOptions = array();
$teacherRes = mysqli_query($con, "SELECT userid,firstname,surname,othernames,mobile
    FROM tblsystemuser
    WHERE systemtype='Teacher' AND status='active'
    ORDER BY firstname ASC, othernames ASC, surname ASC");
if($teacherRes){
    while($row = mysqli_fetch_array($teacherRes, MYSQLI_ASSOC)){
        $teacherOptions[] = $row;
    }
}

$activeNowCount = 0;
$currentRes = mysqli_query($con, "SELECT COUNT(*) AS total_count FROM tbldutyroster
    WHERE status='active' AND '$today' BETWEEN startdate AND enddate$dutyBranchFilter");
if($currentRes && $row = mysqli_fetch_array($currentRes, MYSQLI_ASSOC)){
    $activeNowCount = (int)$row['total_count'];
}

$nextWeekCount = 0;
$nextRes = mysqli_query($con, "SELECT COUNT(*) AS total_count FROM tbldutyroster
    WHERE status='active'
      AND enddate >= '$nextWeek[start]'
      AND startdate <= '$nextWeek[end]'$dutyBranchFilter");
if($nextRes && $row = mysqli_fetch_array($nextRes, MYSQLI_ASSOC)){
    $nextWeekCount = (int)$row['total_count'];
}

$scheduledCount = 0;
$scheduledRes = mysqli_query($con, "SELECT COUNT(*) AS total_count FROM tbldutyroster
    WHERE status='active' AND enddate >= '$today'$dutyBranchFilter");
if($scheduledRes && $row = mysqli_fetch_array($scheduledRes, MYSQLI_ASSOC)){
    $scheduledCount = (int)$row['total_count'];
}

$teacherOnRosterCount = 0;
$teacherCountRes = mysqli_query($con, "SELECT COUNT(DISTINCT userid) AS total_count FROM tbldutyroster
    WHERE status='active' AND enddate >= '$today'$dutyBranchFilter");
if($teacherCountRes && $row = mysqli_fetch_array($teacherCountRes, MYSQLI_ASSOC)){
    $teacherOnRosterCount = (int)$row['total_count'];
}

$todayDutyNames = array();
$todayDutyRows = mysqli_query($con, "SELECT DISTINCT CONCAT_WS(' ', su.firstname, su.othernames, su.surname) AS teacher_name
    FROM tbldutyroster dr
    INNER JOIN tblsystemuser su ON su.userid=dr.userid
    WHERE dr.status='active'
      AND '$today' BETWEEN dr.startdate AND dr.enddate$dutyRosterJoinBranchFilter
    ORDER BY su.firstname ASC, su.surname ASC");
if($todayDutyRows){
    while($todayDutyRow = mysqli_fetch_array($todayDutyRows, MYSQLI_ASSOC)){
        $teacherName = trim((string)$todayDutyRow['teacher_name']);
        if($teacherName !== ""){
            $todayDutyNames[] = $teacherName;
        }
    }
}
$todayDutySummary = count($todayDutyNames) > 0
    ? duty_roster_team_summary_from_names($todayDutyNames, 4)
    : "No teacher is currently on duty.";

$dutyRows = array();
$listSql = "SELECT dr.dutyid,dr.dutygroupid,dr.userid,dr.dutytitle,dr.dutylocation,dr.dutynote,dr.startdate,dr.enddate,dr.status,dr.datetimeentry,
                   su.firstname,su.othernames,su.surname,su.mobile,
                   (
                     SELECT rl.sms_status FROM tbldutyreminderlog rl
                     WHERE rl.dutyid=dr.dutyid
                     ORDER BY rl.datetimeentry DESC
                     LIMIT 1
                   ) AS last_sms_status,
                   (
                     SELECT rl.datetimeentry FROM tbldutyreminderlog rl
                     WHERE rl.dutyid=dr.dutyid
                     ORDER BY rl.datetimeentry DESC
                     LIMIT 1
                   ) AS last_sms_datetime,
                   (
                     SELECT rl.targetweekstart FROM tbldutyreminderlog rl
                     WHERE rl.dutyid=dr.dutyid
                     ORDER BY rl.datetimeentry DESC
                     LIMIT 1
                   ) AS last_target_week
            FROM tbldutyroster dr
            INNER JOIN tblsystemuser su ON su.userid=dr.userid
            WHERE 1=1$dutyRosterJoinBranchFilter
            ORDER BY
                CASE
                    WHEN dr.status='active' AND '$today' BETWEEN dr.startdate AND dr.enddate THEN 0
                    WHEN dr.status='active' AND dr.startdate > '$today' THEN 1
                    WHEN dr.status='active' THEN 2
                    ELSE 3
                END,
                dr.startdate ASC,
                dr.datetimeentry DESC";
$listRes = mysqli_query($con, $listSql);
if($listRes){
    while($row = mysqli_fetch_array($listRes, MYSQLI_ASSOC)){
        duty_roster_attach_group_context($con, $row, isset($row['userid']) ? $row['userid'] : "");
        $dutyRows[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/duty-roster.css">
</head>
<body class="duty-roster-page">
<div class="header"><?php include("menu.php"); ?></div>
<main class="duty-roster-shell">
    <?php if($flashMessage !== ""){ ?><div class="duty-roster-flash"><?php echo $flashMessage; ?></div><?php } ?>

    <section class="duty-roster-hero">
        <div>
            <span class="duty-roster-kicker"><?php echo ($isHeadmasterViewer ? "School Supervision" : "Teacher Operations"); ?></span>
            <h1><?php echo ($isHeadmasterViewer ? "Teachers on duty." : "Duty roster with weekly reminders."); ?></h1>
            <p><?php echo ($isHeadmasterViewer
                ? "Review the current and upcoming teacher duty schedule for your branch."
                : "Assign teachers to duty weeks, surface the reminder on their dashboard, and send SMS notifications for both the current week and the following week without repeating the same alert twice."); ?></p>
            <div class="duty-roster-summary-grid">
                <article class="duty-roster-summary-card"><span>Active This Week</span><strong><?php echo $activeNowCount; ?></strong></article>
                <article class="duty-roster-summary-card"><span>Starting Next Week</span><strong><?php echo $nextWeekCount; ?></strong></article>
                <article class="duty-roster-summary-card"><span>Upcoming Duties</span><strong><?php echo $scheduledCount; ?></strong></article>
                <article class="duty-roster-summary-card"><span>Teachers On Roster</span><strong><?php echo $teacherOnRosterCount; ?></strong></article>
            </div>
        </div>
        <aside class="duty-roster-quick-note">
            <h3><?php echo ($isHeadmasterViewer ? "Teachers on duty today" : "How the reminder flow works"); ?></h3>
            <?php if($isHeadmasterViewer){ ?>
            <p class="duty-roster-helper"><?php echo duty_roster_escape($todayDutySummary); ?></p>
            <?php } else { ?>
            <ul>
                <li>Teachers see their current or upcoming duty directly on the dashboard.</li>
                <li>At the start of each week, one reminder run can notify teachers for this week and next week.</li>
                <li>Each reminder scope is logged separately, so the same duty will not be sent twice for the same week.</li>
            </ul>
            <?php } ?>
        </aside>
    </section>

    <div class="duty-roster-layout">
        <?php if($canManageDutyRoster){ ?>
        <section class="duty-roster-panel duty-roster-form-card">
            <div class="duty-roster-panel__head">
                <div>
                    <span class="duty-roster-panel__eyebrow"><?php echo ($formValues['dutyid'] !== "" ? "Edit Assignment" : "New Assignment"); ?></span>
                    <h2><?php echo ($formValues['dutyid'] !== "" ? "Update a duty entry or team" : "Create a teacher duty entry or team"); ?></h2>
                </div>
            </div>
            <p class="duty-roster-helper">Select one teacher for a single duty or choose several teachers to create a duty team. Each teacher still keeps an individual roster row, but the reminder SMS will include the names of the other teachers on duty with them.</p>

            <form method="post" action="duty-roster.php">
                <input type="hidden" name="dutyid" value="<?php echo duty_roster_escape($formValues['dutyid']); ?>">
                <input type="hidden" name="dutygroupid" value="<?php echo duty_roster_escape($formValues['dutygroupid']); ?>">
                <div class="duty-roster-form-grid">
                    <div class="duty-roster-field duty-roster-field--full">
                        <label>Teachers On Duty</label>
                        <div class="duty-roster-teacher-picker">
                            <?php foreach($teacherOptions as $teacher){ ?>
                            <?php $teacherUserId = (string)$teacher['userid']; ?>
                            <label class="duty-roster-teacher-option">
                                <input type="checkbox" name="teacherids[]" value="<?php echo duty_roster_escape($teacherUserId); ?>"<?php echo (in_array($teacherUserId, $formValues['teacherids'], true) ? " checked" : ""); ?>>
                                <span class="duty-roster-teacher-option__body">
                                    <strong><?php echo duty_roster_escape(dr_teacher_name($teacher)); ?></strong>
                                    <small><?php echo duty_roster_escape($teacherUserId.(trim((string)$teacher['mobile']) !== "" ? " · ".$teacher['mobile'] : "")); ?></small>
                                </span>
                            </label>
                            <?php } ?>
                        </div>
                        <small class="duty-roster-picker-note">Pick one teacher for a normal duty or several teachers for a shared duty team.</small>
                    </div>
                    <div class="duty-roster-field">
                        <label for="dutytitle">Duty Title</label>
                        <input type="text" id="dutytitle" name="dutytitle" value="<?php echo duty_roster_escape($formValues['dutytitle']); ?>" placeholder="Morning Assembly, Break Supervision, Weekend Prep">
                    </div>
                    <div class="duty-roster-field">
                        <label for="startdate">Start Date</label>
                        <input type="date" id="startdate" name="startdate" value="<?php echo duty_roster_escape($formValues['startdate']); ?>">
                    </div>
                    <div class="duty-roster-field">
                        <label for="enddate">End Date</label>
                        <input type="date" id="enddate" name="enddate" value="<?php echo duty_roster_escape($formValues['enddate']); ?>">
                    </div>
                    <div class="duty-roster-field">
                        <label for="dutylocation">Location</label>
                        <input type="text" id="dutylocation" name="dutylocation" value="<?php echo duty_roster_escape($formValues['dutylocation']); ?>" placeholder="Main Gate, Dining Hall, JHS Block">
                    </div>
                    <div class="duty-roster-field duty-roster-field--full">
                        <label for="dutynote">Shared Dashboard / SMS Message</label>
                        <textarea id="dutynote" name="dutynote" placeholder="Add one shared instruction for the selected teachers. Their SMS will also include the names of the other teachers on duty with them."><?php echo duty_roster_escape($formValues['dutynote']); ?></textarea>
                    </div>
                </div>
                <div class="duty-roster-form-actions">
                    <button type="submit" class="duty-roster-btn" name="save_duty"><i class="fa fa-save"></i> <?php echo ($formValues['dutyid'] !== "" ? "Update Duty" : "Save Duty"); ?></button>
                    <?php if($formValues['dutyid'] !== ""){ ?>
                    <a class="duty-roster-btn-secondary" href="duty-roster.php"><i class="fa fa-refresh"></i> Cancel Edit</a>
                    <?php } ?>
                </div>
            </form>
        </section>

        <aside class="duty-roster-panel">
            <div class="duty-roster-panel__head">
                <div>
                    <span class="duty-roster-panel__eyebrow">Reminder Runner</span>
                    <h2>Weekly SMS setup</h2>
                </div>
            </div>

            <div class="duty-roster-run-card">
                <p class="duty-roster-helper">Use the button below when you want to trigger reminders manually. This run covers <strong>this week</strong> <?php echo duty_roster_escape(duty_roster_format_date($currentWeek['start'])." to ".duty_roster_format_date($currentWeek['end'])); ?> and <strong>next week</strong> <?php echo duty_roster_escape(duty_roster_format_date($nextWeek['start'])." to ".duty_roster_format_date($nextWeek['end'])); ?>.</p>
                <form method="post" action="duty-roster.php">
                    <div class="duty-roster-tools">
                        <button type="submit" class="duty-roster-btn" name="run_due_reminders"><i class="fa fa-send"></i> Send Due Reminders Now</button>
                    </div>
                </form>
                <?php if(is_array($runSummary)){ ?>
                <div class="duty-roster-run-summary">
                    <strong>Run Summary</strong>
                    <ul>
                        <li>Total reminders due: <?php echo (int)$runSummary['total_due']; ?></li>
                        <li>This week due: <?php echo (int)$runSummary['current_week_due']; ?></li>
                        <li>Next week due: <?php echo (int)$runSummary['next_week_due']; ?></li>
                        <li>SMS sent: <?php echo (int)$runSummary['sent']; ?></li>
                        <li>Already logged and skipped: <?php echo (int)$runSummary['skipped']; ?></li>
                        <li>No phone number: <?php echo (int)$runSummary['no_phone']; ?></li>
                        <li>Failed SMS sends: <?php echo (int)$runSummary['failed']; ?></li>
                    </ul>
                </div>
                <?php } ?>
            </div>

        </aside>
        <?php } ?>
    </div>

    <section class="duty-roster-table-card">
        <div class="duty-roster-panel__head">
            <div>
                <span class="duty-roster-panel__eyebrow">Roster Review</span>
                <h2>Scheduled teacher duties</h2>
            </div>
            <div class="duty-roster-muted">Current week: <?php echo duty_roster_escape(duty_roster_format_date($currentWeek['start'])." - ".duty_roster_format_date($currentWeek['end'])); ?></div>
        </div>
        <p class="duty-roster-helper"><?php echo ($canManageDutyRoster
            ? "This list shows the current phase of each duty, the teacher's phone availability, and the latest reminder status so you can quickly spot what still needs attention."
            : "This list shows which teachers are on duty, the duty period, and the current duty status."); ?></p>

        <?php if(count($dutyRows) > 0){ ?>
        <div class="duty-roster-table-wrap">
            <table class="duty-roster-table">
                <thead>
                    <tr>
                        <th>Teacher</th>
                        <th>Duty</th>
                        <th>Period</th>
                        <th>Status</th>
                        <th>Latest Reminder</th>
                        <?php if($canManageDutyRoster){ ?><th>Actions</th><?php } ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($dutyRows as $row){ ?>
                    <?php
                    $phase = duty_roster_phase_label($row['startdate'], $row['enddate'], $today, $row['status']);
                    $lastReminderStatus = trim((string)$row['last_sms_status']);
                    $lastReminderText = $lastReminderStatus !== ""
                        ? $lastReminderStatus." on ".duty_roster_format_date($row['last_sms_datetime']).($row['last_target_week'] !== "" ? " for week of ".duty_roster_format_date($row['last_target_week']) : "")
                        : "No reminder log yet";
                    $hasCurrentWeek = duty_roster_overlaps_window($row['startdate'], $row['enddate'], $currentWeek['start'], $currentWeek['end']);
                    $hasNextWeek = duty_roster_overlaps_window($row['startdate'], $row['enddate'], $nextWeek['start'], $nextWeek['end']);
                    $currentWeekLog = $hasCurrentWeek ? duty_roster_get_reminder_log($con, $row['dutyid'], $currentWeek['start'], 'current_week') : null;
                    $nextWeekLog = $hasNextWeek ? duty_roster_get_reminder_log($con, $row['dutyid'], $nextWeek['start'], 'upcoming_week') : null;
                    $currentWeekStatus = !$hasCurrentWeek ? "N/A" : ($currentWeekLog ? strtoupper((string)$currentWeekLog['sms_status']) : "PENDING");
                    $nextWeekStatus = !$hasNextWeek ? "N/A" : ($nextWeekLog ? strtoupper((string)$nextWeekLog['sms_status']) : "PENDING");
                    ?>
                    <tr>
                        <td>
                            <div class="duty-roster-person">
                                <strong><?php echo duty_roster_escape(dr_teacher_name($row)); ?></strong>
                                <small><?php echo duty_roster_escape($row['userid']); ?></small>
                                <small><?php echo duty_roster_escape(trim((string)$row['mobile']) !== "" ? $row['mobile'] : "No mobile number"); ?></small>
                                <?php if((int)(isset($row['group_size']) ? $row['group_size'] : 1) > 1){ ?>
                                <small><?php echo duty_roster_escape("Duty team of ".(int)$row['group_size']." teachers"); ?></small>
                                <?php } ?>
                            </div>
                        </td>
                        <td>
                            <div class="duty-roster-person">
                                <strong><?php echo duty_roster_escape($row['dutytitle']); ?></strong>
                                <small><?php echo duty_roster_escape(trim((string)$row['dutylocation']) !== "" ? $row['dutylocation'] : "Location not set"); ?></small>
                                <small><?php echo duty_roster_escape(trim((string)$row['dutynote']) !== "" ? $row['dutynote'] : "No note added"); ?></small>
                                <?php if(trim((string)(isset($row['team_summary']) ? $row['team_summary'] : "")) !== ""){ ?>
                                <small><?php echo duty_roster_escape("On duty with: ".$row['team_summary']); ?></small>
                                <?php } ?>
                            </div>
                        </td>
                        <td>
                            <div class="duty-roster-person">
                                <strong><?php echo duty_roster_escape(duty_roster_format_period($row['startdate'], $row['enddate'])); ?></strong>
                                <small>Saved <?php echo duty_roster_escape(duty_roster_format_date($row['datetimeentry'])); ?></small>
                            </div>
                        </td>
                        <td>
                            <span class="<?php echo duty_roster_escape(dr_phase_class($phase)); ?>"><?php echo duty_roster_escape($phase); ?></span>
                        </td>
                        <td>
                            <div class="duty-roster-person">
                                <strong>This Week <span class="<?php echo duty_roster_escape(dr_reminder_badge_class($currentWeekStatus)); ?>"><?php echo duty_roster_escape($currentWeekStatus); ?></span></strong>
                                <small><?php echo duty_roster_escape($hasCurrentWeek ? ($currentWeekLog ? "Logged ".duty_roster_format_date($currentWeekLog['datetimeentry']) : "Not sent yet for ".duty_roster_format_date($currentWeek['start'])) : "Duty is not in this week"); ?></small>
                                <strong style="margin-top:8px;">Next Week <span class="<?php echo duty_roster_escape(dr_reminder_badge_class($nextWeekStatus)); ?>"><?php echo duty_roster_escape($nextWeekStatus); ?></span></strong>
                                <small><?php echo duty_roster_escape($hasNextWeek ? ($nextWeekLog ? "Logged ".duty_roster_format_date($nextWeekLog['datetimeentry']) : "Not sent yet for ".duty_roster_format_date($nextWeek['start'])) : "Duty is not in next week"); ?></small>
                                <small><?php echo duty_roster_escape($lastReminderText); ?></small>
                            </div>
                        </td>
                        <?php if($canManageDutyRoster){ ?>
                        <td>
                            <div class="duty-roster-row-actions">
                                <?php if($hasCurrentWeek){ ?>
                                <form method="post" action="duty-roster.php">
                                    <input type="hidden" name="dutyid" value="<?php echo duty_roster_escape($row['dutyid']); ?>">
                                    <input type="hidden" name="reminder_type" value="current_week">
                                    <button type="submit" class="duty-roster-inline-btn" name="send_single_reminder"<?php echo ($currentWeekLog ? " disabled" : ""); ?>><i class="fa fa-send"></i> <?php echo ($currentWeekLog ? "This Week Sent" : "Send This Week"); ?></button>
                                </form>
                                <?php } ?>
                                <?php if($hasNextWeek){ ?>
                                <form method="post" action="duty-roster.php">
                                    <input type="hidden" name="dutyid" value="<?php echo duty_roster_escape($row['dutyid']); ?>">
                                    <input type="hidden" name="reminder_type" value="upcoming_week">
                                    <button type="submit" class="duty-roster-inline-btn" name="send_single_reminder"<?php echo ($nextWeekLog ? " disabled" : ""); ?>><i class="fa fa-send"></i> <?php echo ($nextWeekLog ? "Next Week Sent" : "Send Next Week"); ?></button>
                                </form>
                                <?php } ?>
                                <a class="duty-roster-inline-btn" href="duty-roster.php?edit_duty=<?php echo urlencode($row['dutyid']); ?>"><i class="fa fa-pencil"></i> Edit</a>
                                <form method="post" action="duty-roster.php" onsubmit="return confirm('Change the status of this duty assignment?');">
                                    <input type="hidden" name="dutyid" value="<?php echo duty_roster_escape($row['dutyid']); ?>">
                                    <input type="hidden" name="status_action" value="<?php echo ($row['status'] === 'active' ? 'deactivate' : 'activate'); ?>">
                                    <button type="submit" class="duty-roster-inline-btn" name="change_duty_status"><i class="fa fa-toggle-on"></i> <?php echo ($row['status'] === 'active' ? 'Deactivate' : 'Activate'); ?></button>
                                </form>
                                <form method="post" action="duty-roster.php" onsubmit="return confirm('Delete this duty assignment permanently?');">
                                    <input type="hidden" name="dutyid" value="<?php echo duty_roster_escape($row['dutyid']); ?>">
                                    <input type="hidden" name="status_action" value="delete">
                                    <button type="submit" class="duty-roster-inline-btn" name="change_duty_status"><i class="fa fa-trash"></i> Delete</button>
                                </form>
                            </div>
                        </td>
                        <?php } ?>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } else { ?>
        <div class="duty-roster-empty">No teacher duty entries have been added yet. Once you save one above, it will appear here for review and reminder tracking.</div>
        <?php } ?>
    </section>
</main>
</body>
</html>
