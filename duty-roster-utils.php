<?php
include_once("house-master-utils.php");

if(!function_exists('xschool_schema_cache_is_fresh')){
function xschool_schema_cache_is_fresh($key, $ttlSeconds = 900){
    static $memoryCache = array();
    $key = trim((string)$key);
    if($key === ""){
        return false;
    }
    if(isset($memoryCache[$key])){
        return $memoryCache[$key];
    }
    if(PHP_SAPI === 'cli' || !function_exists('session_status') || session_status() !== PHP_SESSION_ACTIVE){
        $memoryCache[$key] = false;
        return false;
    }
    $cacheBag = isset($_SESSION['_xschool_schema_cache']) && is_array($_SESSION['_xschool_schema_cache'])
        ? $_SESSION['_xschool_schema_cache']
        : array();
    $isFresh = isset($cacheBag[$key]) && ((int)$cacheBag[$key] + (int)$ttlSeconds) > time();
    $memoryCache[$key] = $isFresh;
    return $isFresh;
}
}

if(!function_exists('xschool_schema_cache_mark')){
function xschool_schema_cache_mark($key){
    static $memoryCache = array();
    $key = trim((string)$key);
    if($key === ""){
        return;
    }
    $memoryCache[$key] = true;
    if(PHP_SAPI === 'cli' || !function_exists('session_status') || session_status() !== PHP_SESSION_ACTIVE){
        return;
    }
    if(!isset($_SESSION['_xschool_schema_cache']) || !is_array($_SESSION['_xschool_schema_cache'])){
        $_SESSION['_xschool_schema_cache'] = array();
    }
    $_SESSION['_xschool_schema_cache'][$key] = time();
}
}

if(!function_exists('duty_roster_is_admin')){
function duty_roster_is_admin(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === "administrator" &&
        ($_SESSION['SYSTEMTYPE'] === "normal_user" || $_SESSION['SYSTEMTYPE'] === "super_user");
}
}

if(!function_exists('duty_roster_is_teacher')){
function duty_roster_is_teacher(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === "user" &&
        $_SESSION['SYSTEMTYPE'] === "Teacher";
}
}

if(!function_exists('duty_roster_is_headmaster')){
function duty_roster_is_headmaster(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === "user" &&
        $_SESSION['SYSTEMTYPE'] === "Headmaster";
}
}

if(!function_exists('duty_roster_landing_page')){
function duty_roster_landing_page(){
    if(duty_roster_is_admin()){
        return ($_SESSION['SYSTEMTYPE'] === "super_user") ? "super.php" : "admin.php";
    }
    if(duty_roster_is_headmaster()){
        return "headmaster-page.php";
    }
    if(duty_roster_is_teacher()){
        return "teacher-page.php";
    }
    if(isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) && $_SESSION['ACCESSLEVEL'] === "user" && $_SESSION['SYSTEMTYPE'] === "Student"){
        return "student-page.php";
    }
    return function_exists('um_home_link_for_session') ? um_home_link_for_session() : "index.php";
}
}

if(!function_exists('duty_roster_can_manage_module')){
function duty_roster_can_manage_module($con = null){
    if(duty_roster_is_admin()){
        return true;
    }
    if(!$con || !function_exists('um_current_user_can_access_module')){
        return false;
    }
    return um_current_user_can_access_module($con, 'duty_roster');
}
}

if(!function_exists('duty_roster_can_view_module')){
function duty_roster_can_view_module($con = null){
    if(duty_roster_can_manage_module($con)){
        return true;
    }
    return duty_roster_is_headmaster();
}
}

if(!function_exists('duty_roster_escape')){
function duty_roster_escape($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
}

if(!function_exists('duty_roster_make_id')){
function duty_roster_make_id($prefix = "DUTY"){
    $prefix = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string)$prefix));
    if($prefix === ""){
        $prefix = "DUTY";
    }
    return $prefix."_".strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 24));
}
}

if(!function_exists('ensure_duty_roster_tables')){
function ensure_duty_roster_tables($con){
    if(xschool_schema_cache_is_fresh('schema_duty_roster_v3')){
        return;
    }
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tbldutyroster (
        dutyid VARCHAR(40) NOT NULL PRIMARY KEY,
        dutygroupid VARCHAR(40) NOT NULL DEFAULT '',
        userid VARCHAR(30) NOT NULL,
        dutytitle VARCHAR(120) NOT NULL,
        dutylocation VARCHAR(120) DEFAULT '',
        dutynote VARCHAR(255) DEFAULT '',
        startdate DATE NOT NULL,
        enddate DATE NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        datetimeentry DATETIME NOT NULL,
        recordedby VARCHAR(30) NOT NULL,
        INDEX idx_duty_group (dutygroupid),
        INDEX idx_duty_teacher (userid),
        INDEX idx_duty_status_dates (status,startdate,enddate),
        INDEX idx_duty_start (startdate),
        INDEX idx_duty_end (enddate)
    )");

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tbldutyreminderlog (
        logid VARCHAR(40) NOT NULL PRIMARY KEY,
        dutyid VARCHAR(40) NOT NULL,
        userid VARCHAR(30) NOT NULL,
        remindertype VARCHAR(30) NOT NULL DEFAULT 'upcoming_week',
        runweekstart DATE NOT NULL,
        targetweekstart DATE NOT NULL,
        mobile VARCHAR(20) DEFAULT '',
        sms_message VARCHAR(255) DEFAULT '',
        sms_status VARCHAR(30) NOT NULL,
        sms_code VARCHAR(80) DEFAULT '',
        datetimeentry DATETIME NOT NULL,
        recordedby VARCHAR(30) NOT NULL,
        INDEX idx_dutyreminder_duty (dutyid),
        INDEX idx_dutyreminder_user (userid),
        INDEX idx_dutyreminder_type_week (remindertype,targetweekstart),
        UNIQUE KEY uq_duty_week_reminder (dutyid,remindertype,targetweekstart)
    )");

    $columnRes = mysqli_query($con, "SHOW COLUMNS FROM tbldutyroster LIKE 'dutygroupid'");
    if(!$columnRes || mysqli_num_rows($columnRes) === 0){
        @mysqli_query($con, "ALTER TABLE tbldutyroster ADD COLUMN dutygroupid VARCHAR(40) NOT NULL DEFAULT '' AFTER dutyid");
    }
    @mysqli_query($con, "UPDATE tbldutyroster SET dutygroupid=dutyid WHERE TRIM(COALESCE(dutygroupid,''))=''");
    $indexRes = mysqli_query($con, "SHOW INDEX FROM tbldutyroster WHERE Key_name='idx_duty_group'");
    if(!$indexRes || mysqli_num_rows($indexRes) === 0){
        @mysqli_query($con, "CREATE INDEX idx_duty_group ON tbldutyroster (dutygroupid)");
    }

    xschool_schema_cache_mark('schema_duty_roster_v3');
}
}

if(!function_exists('duty_roster_normalize_date')){
function duty_roster_normalize_date($value = null){
    if($value === null || trim((string)$value) === ""){
        return date("Y-m-d");
    }
    $time = strtotime((string)$value);
    if($time === false){
        return date("Y-m-d");
    }
    return date("Y-m-d", $time);
}
}

if(!function_exists('duty_roster_week_window')){
function duty_roster_week_window($dateValue = null, $offsetWeeks = 0){
    $baseDate = duty_roster_normalize_date($dateValue);
    $date = new DateTime($baseDate);
    $date->setTime(0, 0, 0);
    $dayNumber = (int)$date->format("N");
    if($dayNumber > 1){
        $date->modify("-".($dayNumber - 1)." days");
    }
    if($offsetWeeks !== 0){
        $date->modify(($offsetWeeks > 0 ? "+" : "").$offsetWeeks." week");
    }
    $weekStart = $date->format("Y-m-d");
    $weekEndDate = clone $date;
    $weekEndDate->modify("+6 days");
    return array(
        "start" => $weekStart,
        "end" => $weekEndDate->format("Y-m-d"),
    );
}
}

if(!function_exists('duty_roster_current_week_window')){
function duty_roster_current_week_window($dateValue = null){
    return duty_roster_week_window($dateValue, 0);
}
}

if(!function_exists('duty_roster_next_week_window')){
function duty_roster_next_week_window($dateValue = null){
    return duty_roster_week_window($dateValue, 1);
}
}

if(!function_exists('duty_roster_format_date')){
function duty_roster_format_date($value){
    $time = strtotime((string)$value);
    return $time ? date("d M Y", $time) : (string)$value;
}
}

if(!function_exists('duty_roster_format_period')){
function duty_roster_format_period($startDate, $endDate){
    $startDate = duty_roster_normalize_date($startDate);
    $endDate = duty_roster_normalize_date($endDate);
    if($startDate === $endDate){
        return duty_roster_format_date($startDate);
    }
    return duty_roster_format_date($startDate)." - ".duty_roster_format_date($endDate);
}
}

if(!function_exists('duty_roster_overlaps_window')){
function duty_roster_overlaps_window($startDate, $endDate, $windowStart, $windowEnd){
    $startDate = duty_roster_normalize_date($startDate);
    $endDate = duty_roster_normalize_date($endDate);
    $windowStart = duty_roster_normalize_date($windowStart);
    $windowEnd = duty_roster_normalize_date($windowEnd);
    return ($endDate >= $windowStart && $startDate <= $windowEnd);
}
}

if(!function_exists('duty_roster_phase_label')){
function duty_roster_phase_label($startDate, $endDate, $today = null, $status = 'active'){
    $status = trim((string)$status);
    if($status !== "active"){
        return "Inactive";
    }

    $today = duty_roster_normalize_date($today);
    $startDate = duty_roster_normalize_date($startDate);
    $endDate = duty_roster_normalize_date($endDate);

    if($today < $startDate){
        return "Upcoming";
    }
    if($today > $endDate){
        return "Completed";
    }
    return "Active";
}
}

if(!function_exists('duty_roster_teacher_fullname')){
function duty_roster_teacher_fullname($row){
    $full = trim(
        (isset($row['firstname']) ? $row['firstname'] : '')." ".
        (isset($row['othernames']) ? $row['othernames'] : '')." ".
        (isset($row['surname']) ? $row['surname'] : '')
    );
    return $full !== "" ? preg_replace('/\s+/', ' ', $full) : (isset($row['userid']) ? (string)$row['userid'] : "Teacher");
}
}

if(!function_exists('duty_roster_dashboard_card')){
function duty_roster_dashboard_card($row, $label, $tone){
    $note = isset($row['dutynote']) ? trim((string)$row['dutynote']) : "";
    $teamSummary = isset($row['team_summary']) ? trim((string)$row['team_summary']) : "";
    if($teamSummary !== ""){
        $note = trim($note !== "" ? $note." Team: ".$teamSummary : "Team: ".$teamSummary);
    }
    return array(
        "dutyid" => isset($row['dutyid']) ? (string)$row['dutyid'] : "",
        "dutygroupid" => isset($row['dutygroupid']) ? (string)$row['dutygroupid'] : "",
        "label" => (string)$label,
        "tone" => (string)$tone,
        "title" => isset($row['dutytitle']) ? (string)$row['dutytitle'] : "Duty Assignment",
        "location" => isset($row['dutylocation']) ? trim((string)$row['dutylocation']) : "",
        "note" => $note,
        "team_summary" => $teamSummary,
        "group_size" => isset($row['group_size']) ? (int)$row['group_size'] : 1,
        "period" => duty_roster_format_period(
            isset($row['startdate']) ? $row['startdate'] : "",
            isset($row['enddate']) ? $row['enddate'] : ""
        ),
    );
}
}

if(!function_exists('duty_roster_group_member_rows')){
function duty_roster_group_member_rows($con, $dutyGroupId){
    ensure_duty_roster_tables($con);
    $dutyGroupId = trim((string)$dutyGroupId);
    if($dutyGroupId === ""){
        return array();
    }
    $dutyGroupIdEsc = mysqli_real_escape_string($con, $dutyGroupId);
    $rows = array();
    $sql = "SELECT dr.dutyid,dr.dutygroupid,dr.userid,dr.status,
                   su.firstname,su.othernames,su.surname
            FROM tbldutyroster dr
            INNER JOIN tblsystemuser su ON su.userid=dr.userid
            WHERE dr.dutygroupid='$dutyGroupIdEsc'
              AND dr.status='active'
              AND su.systemtype='Teacher'
            ORDER BY su.firstname ASC, su.othernames ASC, su.surname ASC";
    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $row['teacher_name'] = duty_roster_teacher_fullname($row);
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if(!function_exists('duty_roster_group_member_ids')){
function duty_roster_group_member_ids($con, $dutyGroupId){
    $rows = duty_roster_group_member_rows($con, $dutyGroupId);
    $ids = array();
    foreach($rows as $row){
        $userId = trim((string)$row['userid']);
        if($userId !== ""){
            $ids[] = $userId;
        }
    }
    return $ids;
}
}

if(!function_exists('duty_roster_team_summary_from_names')){
function duty_roster_team_summary_from_names($names, $maxNames = 3){
    $clean = array();
    foreach((array)$names as $name){
        $name = preg_replace('/\s+/', ' ', trim((string)$name));
        if($name !== ""){
            $clean[] = $name;
        }
    }
    $clean = array_values(array_unique($clean));
    if(count($clean) === 0){
        return "";
    }
    if(count($clean) <= $maxNames){
        return implode(", ", $clean);
    }
    $shown = array_slice($clean, 0, $maxNames);
    $remaining = count($clean) - count($shown);
    return implode(", ", $shown)." +".$remaining." other".($remaining === 1 ? "" : "s");
}
}

if(!function_exists('duty_roster_attach_group_context')){
function duty_roster_attach_group_context($con, &$row, $excludeUserId = ""){
    $groupId = trim((string)(isset($row['dutygroupid']) ? $row['dutygroupid'] : ""));
    if($groupId === ""){
        $row['team_names'] = array();
        $row['team_summary'] = "";
        $row['group_size'] = 1;
        return;
    }
    $members = duty_roster_group_member_rows($con, $groupId);
    $excludeUserId = trim((string)$excludeUserId);
    $names = array();
    foreach($members as $member){
        $memberUserId = trim((string)(isset($member['userid']) ? $member['userid'] : ""));
        if($excludeUserId !== "" && $memberUserId === $excludeUserId){
            continue;
        }
        $names[] = isset($member['teacher_name']) ? $member['teacher_name'] : duty_roster_teacher_fullname($member);
    }
    $row['team_names'] = $names;
    $row['team_summary'] = duty_roster_team_summary_from_names($names);
    $row['group_size'] = max(1, count($members));
}
}

if(!function_exists('duty_roster_get_teacher_dashboard_context')){
function duty_roster_get_teacher_dashboard_context($con, $teacherId, $today = null){
    ensure_duty_roster_tables($con);
    $teacherId = trim((string)$teacherId);
    $today = duty_roster_normalize_date($today);
    $currentWeek = duty_roster_current_week_window($today);
    $nextWeek = duty_roster_next_week_window($today);

    $context = array(
        "active_now" => null,
        "this_week" => null,
        "next_week" => null,
        "upcoming" => null,
        "cards" => array(),
        "current_week" => $currentWeek,
        "next_week_window" => $nextWeek,
    );

    if($teacherId === ""){
        return $context;
    }

    $teacherIdEsc = mysqli_real_escape_string($con, $teacherId);
    $sql = "SELECT dutyid,dutygroupid,userid,dutytitle,dutylocation,dutynote,startdate,enddate,status
            FROM tbldutyroster
            WHERE userid='$teacherIdEsc'
              AND status='active'
              AND enddate >= '$currentWeek[start]'
            ORDER BY startdate ASC, enddate ASC, datetimeentry DESC";
    $res = mysqli_query($con, $sql);
    if(!$res){
        return $context;
    }

    while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
        duty_roster_attach_group_context($con, $row, $teacherId);
        $startDate = duty_roster_normalize_date($row['startdate']);
        $endDate = duty_roster_normalize_date($row['enddate']);

        if($context['active_now'] === null && $startDate <= $today && $endDate >= $today){
            $context['active_now'] = $row;
        }
        if($context['this_week'] === null && duty_roster_overlaps_window($startDate, $endDate, $currentWeek['start'], $currentWeek['end'])){
            $context['this_week'] = $row;
        }
        if($context['next_week'] === null && duty_roster_overlaps_window($startDate, $endDate, $nextWeek['start'], $nextWeek['end'])){
            $context['next_week'] = $row;
        }
        if($context['upcoming'] === null && $startDate >= $today){
            $context['upcoming'] = $row;
        }
    }

    $usedDutyIds = array();
    if($context['active_now'] !== null){
        $context['cards'][] = duty_roster_dashboard_card($context['active_now'], "Active Now", "active");
        $usedDutyIds[$context['active_now']['dutyid']] = true;
    } elseif($context['this_week'] !== null){
        $context['cards'][] = duty_roster_dashboard_card($context['this_week'], "Later This Week", "current");
        $usedDutyIds[$context['this_week']['dutyid']] = true;
    }

    if($context['next_week'] !== null && !isset($usedDutyIds[$context['next_week']['dutyid']])){
        $context['cards'][] = duty_roster_dashboard_card($context['next_week'], "Coming Next Week", "upcoming");
        $usedDutyIds[$context['next_week']['dutyid']] = true;
    } elseif($context['upcoming'] !== null && !isset($usedDutyIds[$context['upcoming']['dutyid']])){
        $context['cards'][] = duty_roster_dashboard_card($context['upcoming'], "Next Duty", "planned");
    }

    return $context;
}
}

if(!function_exists('duty_roster_has_reminder_log')){
function duty_roster_has_reminder_log($con, $dutyId, $targetWeekStart, $reminderType = "upcoming_week"){
    $dutyId = mysqli_real_escape_string($con, trim((string)$dutyId));
    $targetWeekStart = duty_roster_normalize_date($targetWeekStart);
    $reminderType = mysqli_real_escape_string($con, trim((string)$reminderType));
    $sql = "SELECT logid FROM tbldutyreminderlog
            WHERE dutyid='$dutyId'
              AND remindertype='$reminderType'
              AND targetweekstart='$targetWeekStart'
            LIMIT 1";
    $res = mysqli_query($con, $sql);
    return ($res && mysqli_num_rows($res) > 0);
}
}

if(!function_exists('duty_roster_get_reminder_log')){
function duty_roster_get_reminder_log($con, $dutyId, $targetWeekStart, $reminderType = "upcoming_week"){
    $dutyId = mysqli_real_escape_string($con, trim((string)$dutyId));
    $targetWeekStart = duty_roster_normalize_date($targetWeekStart);
    $reminderType = mysqli_real_escape_string($con, trim((string)$reminderType));
    $sql = "SELECT sms_status,sms_code,mobile,datetimeentry,targetweekstart,remindertype
            FROM tbldutyreminderlog
            WHERE dutyid='$dutyId'
              AND remindertype='$reminderType'
              AND targetweekstart='$targetWeekStart'
            ORDER BY datetimeentry DESC
            LIMIT 1";
    $res = mysqli_query($con, $sql);
    if($res && $row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
        return $row;
    }
    return null;
}
}

if(!function_exists('duty_roster_log_reminder')){
function duty_roster_log_reminder($con, $dutyId, $userId, $mobile, $message, $smsStatus, $smsCode, $runWeekStart, $targetWeekStart, $recordedBy, $reminderType = "upcoming_week"){
    $logId = duty_roster_make_id("DRL");
    $dutyId = mysqli_real_escape_string($con, trim((string)$dutyId));
    $userId = mysqli_real_escape_string($con, trim((string)$userId));
    $mobile = mysqli_real_escape_string($con, trim((string)$mobile));
    $message = mysqli_real_escape_string($con, trim((string)$message));
    $smsStatus = mysqli_real_escape_string($con, trim((string)$smsStatus));
    $smsCode = mysqli_real_escape_string($con, trim((string)$smsCode));
    $runWeekStart = duty_roster_normalize_date($runWeekStart);
    $targetWeekStart = duty_roster_normalize_date($targetWeekStart);
    $recordedBy = mysqli_real_escape_string($con, trim((string)$recordedBy));
    $reminderType = mysqli_real_escape_string($con, trim((string)$reminderType));

    mysqli_query($con, "INSERT INTO tbldutyreminderlog
        (logid,dutyid,userid,remindertype,runweekstart,targetweekstart,mobile,sms_message,sms_status,sms_code,datetimeentry,recordedby)
        VALUES('$logId','$dutyId','$userId','$reminderType','$runWeekStart','$targetWeekStart','$mobile','$message','$smsStatus','$smsCode',NOW(),'$recordedBy')");
}
}

if(!function_exists('duty_roster_reminder_scope_label')){
function duty_roster_reminder_scope_label($reminderType){
    $reminderType = trim((string)$reminderType);
    if($reminderType === "current_week"){
        return "This Week";
    }
    return "Next Week";
}
}

if(!function_exists('duty_roster_week_window_for_type')){
function duty_roster_week_window_for_type($referenceDate = null, $reminderType = "upcoming_week"){
    if(trim((string)$reminderType) === "current_week"){
        return duty_roster_current_week_window($referenceDate);
    }
    return duty_roster_next_week_window($referenceDate);
}
}

if(!function_exists('duty_roster_build_sms_message')){
function duty_roster_build_sms_message($dutyRow, $targetWeek, $reminderType = "upcoming_week"){
    $teacherName = trim((string)(isset($dutyRow['teacher_name']) ? $dutyRow['teacher_name'] : "Teacher"));
    $teacherShortName = $teacherName !== "" ? explode(" ", $teacherName)[0] : "Teacher";
    $dutyTitle = trim((string)(isset($dutyRow['dutytitle']) ? $dutyRow['dutytitle'] : "Duty"));
    $dutyLocation = trim((string)(isset($dutyRow['dutylocation']) ? $dutyRow['dutylocation'] : ""));
    $dutyNote = trim((string)(isset($dutyRow['dutynote']) ? $dutyRow['dutynote'] : ""));
    $period = duty_roster_format_period(
        isset($dutyRow['startdate']) ? $dutyRow['startdate'] : "",
        isset($dutyRow['enddate']) ? $dutyRow['enddate'] : ""
    );

    $scopeText = ($reminderType === "current_week") ? "this week" : "next week";
    $message = "Reminder ".$teacherShortName.": you are on duty ".$scopeText." for ".$dutyTitle." (".$period.").";
    if($dutyLocation !== ""){
        $message .= " Location: ".$dutyLocation.".";
    }
    if($dutyNote !== ""){
        $message .= " Note: ".$dutyNote.".";
    } elseif($reminderType === "current_week"){
        $message .= " Please report as scheduled.";
    } else {
        $message .= " Please prepare ahead of ".$targetWeek['start'].".";
    }
    $teamSummary = trim((string)(isset($dutyRow['team_summary']) ? $dutyRow['team_summary'] : ""));
    if($teamSummary !== ""){
        $message .= " Team: ".$teamSummary.".";
    }
    return trim($message);
}
}

if(!function_exists('duty_roster_send_single_reminder')){
function duty_roster_send_single_reminder($con, $dutyId, $reminderType = "upcoming_week", $referenceDate = null, $recordedBy = "SYSTEM"){
    ensure_duty_roster_tables($con);
    $today = duty_roster_normalize_date($referenceDate);
    $runWeek = duty_roster_current_week_window($today);
    $targetWeek = duty_roster_week_window_for_type($today, $reminderType);
    $recordedBy = trim((string)$recordedBy) !== "" ? trim((string)$recordedBy) : "SYSTEM";
    $dutyId = trim((string)$dutyId);

    $result = array(
        "ok" => false,
        "status" => "NOT_FOUND",
        "scope" => duty_roster_reminder_scope_label($reminderType),
        "target_week_start" => $targetWeek['start'],
        "target_week_end" => $targetWeek['end'],
        "teacher" => "",
        "duty" => "",
        "message" => "",
    );

    if($dutyId === ""){
        $result['status'] = "INVALID_DUTY";
        return $result;
    }

    $dutyIdEsc = mysqli_real_escape_string($con, $dutyId);
    $sql = "SELECT dr.dutyid,dr.dutygroupid,dr.userid,dr.dutytitle,dr.dutylocation,dr.dutynote,dr.startdate,dr.enddate,
                   su.mobile,su.firstname,su.othernames,su.surname
            FROM tbldutyroster dr
            INNER JOIN tblsystemuser su ON su.userid=dr.userid
            WHERE dr.dutyid='$dutyIdEsc'
              AND dr.status='active'
              AND su.status='active'
              AND su.systemtype='Teacher'
            LIMIT 1";
    $res = mysqli_query($con, $sql);
    if(!$res || !($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
        return $result;
    }

    $row['teacher_name'] = duty_roster_teacher_fullname($row);
    duty_roster_attach_group_context($con, $row, $row['userid']);
    $result['teacher'] = $row['teacher_name'];
    $result['duty'] = (string)$row['dutytitle'];

    if(!duty_roster_overlaps_window($row['startdate'], $row['enddate'], $targetWeek['start'], $targetWeek['end'])){
        $result['status'] = "NOT_DUE";
        return $result;
    }

    if(duty_roster_has_reminder_log($con, $dutyId, $targetWeek['start'], $reminderType)){
        $result['status'] = "ALREADY_SENT";
        return $result;
    }

    $teacherPhone = trim((string)$row['mobile']);
    $message = duty_roster_build_sms_message($row, $targetWeek, $reminderType);
    $result['message'] = $message;

    if($teacherPhone === ""){
        $result['status'] = "NO_PHONE";
        duty_roster_log_reminder($con, $dutyId, $row['userid'], "", $message, "NO_PHONE", "NO_PHONE", $runWeek['start'], $targetWeek['start'], $recordedBy, $reminderType);
        return $result;
    }

    $smsCode = "";
    $ok = send_bulk_sms_message($teacherPhone, $message, $smsCode);
    $result['ok'] = $ok;
    $result['status'] = $ok ? "SENT" : "FAILED";
    duty_roster_log_reminder($con, $dutyId, $row['userid'], $teacherPhone, $message, $result['status'], $smsCode, $runWeek['start'], $targetWeek['start'], $recordedBy, $reminderType);
    return $result;
}
}

if(!function_exists('duty_roster_run_weekly_reminders')){
function duty_roster_run_weekly_reminders($con, $referenceDate = null, $recordedBy = "SYSTEM"){
    ensure_duty_roster_tables($con);
    $today = duty_roster_normalize_date($referenceDate);
    $runWeek = duty_roster_current_week_window($today);
    $nextWeek = duty_roster_next_week_window($today);
    $recordedBy = trim((string)$recordedBy) !== "" ? trim((string)$recordedBy) : "SYSTEM";

    $summary = array(
        "run_week_start" => $runWeek['start'],
        "run_week_end" => $runWeek['end'],
        "current_week_start" => $runWeek['start'],
        "current_week_end" => $runWeek['end'],
        "target_week_start" => $nextWeek['start'],
        "target_week_end" => $nextWeek['end'],
        "next_week_start" => $nextWeek['start'],
        "next_week_end" => $nextWeek['end'],
        "total_due" => 0,
        "sent" => 0,
        "failed" => 0,
        "no_phone" => 0,
        "skipped" => 0,
        "current_week_due" => 0,
        "next_week_due" => 0,
        "current_week_sent" => 0,
        "next_week_sent" => 0,
        "current_week_failed" => 0,
        "next_week_failed" => 0,
        "current_week_no_phone" => 0,
        "next_week_no_phone" => 0,
        "current_week_skipped" => 0,
        "next_week_skipped" => 0,
        "items" => array(),
    );

    $sql = "SELECT dr.dutyid,dr.dutygroupid,dr.userid,dr.dutytitle,dr.dutylocation,dr.dutynote,dr.startdate,dr.enddate,
                   su.mobile,su.firstname,su.othernames,su.surname
            FROM tbldutyroster dr
            INNER JOIN tblsystemuser su ON su.userid=dr.userid
            WHERE dr.status='active'
              AND su.status='active'
              AND su.systemtype='Teacher'
              AND dr.enddate >= '$runWeek[start]'
              AND dr.startdate <= '$nextWeek[end]'
            ORDER BY dr.startdate ASC, dr.enddate ASC, su.firstname ASC";
    $res = mysqli_query($con, $sql);
    if(!$res){
        return $summary;
    }

    while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
        $summary['total_due']++;
        $row['teacher_name'] = duty_roster_teacher_fullname($row);
        duty_roster_attach_group_context($con, $row, $row['userid']);
        $dutyId = (string)$row['dutyid'];
        $teacherId = (string)$row['userid'];
        $teacherPhone = trim((string)$row['mobile']);
        $scopes = array();

        if(duty_roster_overlaps_window($row['startdate'], $row['enddate'], $runWeek['start'], $runWeek['end'])){
            $scopes['current_week'] = $runWeek;
        }
        if(duty_roster_overlaps_window($row['startdate'], $row['enddate'], $nextWeek['start'], $nextWeek['end'])){
            $scopes['upcoming_week'] = $nextWeek;
        }

        foreach($scopes as $reminderType => $scopeWindow){
            $scopePrefix = ($reminderType === "current_week") ? "current_week" : "next_week";
            $summary['total_due']++;
            $summary[$scopePrefix.'_due']++;

            if(duty_roster_has_reminder_log($con, $dutyId, $scopeWindow['start'], $reminderType)){
                $summary['skipped']++;
                $summary[$scopePrefix.'_skipped']++;
                $summary['items'][] = array(
                    "teacher" => $row['teacher_name'],
                    "duty" => $row['dutytitle'],
                    "scope" => duty_roster_reminder_scope_label($reminderType),
                    "status" => "Already Sent",
                );
                continue;
            }

            $status = "";
            $smsCode = "";
            $message = duty_roster_build_sms_message($row, $scopeWindow, $reminderType);
            if($teacherPhone === ""){
                $status = "NO_PHONE";
                $summary['no_phone']++;
                $summary[$scopePrefix.'_no_phone']++;
                duty_roster_log_reminder($con, $dutyId, $teacherId, "", $message, $status, $status, $runWeek['start'], $scopeWindow['start'], $recordedBy, $reminderType);
            } else {
                $ok = send_bulk_sms_message($teacherPhone, $message, $smsCode);
                $status = $ok ? "SENT" : "FAILED";
                if($ok){
                    $summary['sent']++;
                    $summary[$scopePrefix.'_sent']++;
                } else {
                    $summary['failed']++;
                    $summary[$scopePrefix.'_failed']++;
                }
                duty_roster_log_reminder($con, $dutyId, $teacherId, $teacherPhone, $message, $status, $smsCode, $runWeek['start'], $scopeWindow['start'], $recordedBy, $reminderType);
            }

            $summary['items'][] = array(
                "teacher" => $row['teacher_name'],
                "duty" => $row['dutytitle'],
                "scope" => duty_roster_reminder_scope_label($reminderType),
                "status" => $status,
            );
        }
    }

    return $summary;
}
}
?>
