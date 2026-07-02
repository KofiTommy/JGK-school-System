<?php
include_once("class-teacher-utils.php");
include_once("user-management-utils.php");

if(!function_exists('student_attendance_generate_id')){
function student_attendance_generate_id($prefix = "ATT_"){
    $prefix = trim((string)$prefix);
    if($prefix === ""){
        $prefix = "ATT_";
    }
    try{
        return $prefix.strtoupper(bin2hex(random_bytes(8)));
    }catch(Exception $e){
        return $prefix.strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 16));
    }
}
}

if(!function_exists('student_attendance_is_admin')){
function student_attendance_is_admin(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === "administrator" &&
        in_array($_SESSION['SYSTEMTYPE'], array("normal_user", "super_user"), true);
}
}

if(!function_exists('student_attendance_is_teacher')){
function student_attendance_is_teacher(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === "user" &&
        $_SESSION['SYSTEMTYPE'] === "Teacher";
}
}

if(!function_exists('student_attendance_is_student')){
function student_attendance_is_student(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === "user" &&
        $_SESSION['SYSTEMTYPE'] === "Student";
}
}

if(!function_exists('student_attendance_is_headmaster')){
function student_attendance_is_headmaster(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === "user" &&
        $_SESSION['SYSTEMTYPE'] === "Headmaster";
}
}

if(!function_exists('student_attendance_is_assistant_head_academics')){
function student_attendance_is_assistant_head_academics(){
    return function_exists('um_is_assistant_head_academics_user') && um_is_assistant_head_academics_user();
}
}

if(!function_exists('student_attendance_landing_page')){
function student_attendance_landing_page(){
    if(student_attendance_is_admin()){
        return ($_SESSION['SYSTEMTYPE'] === "super_user") ? "super.php" : "admin.php";
    }
    if(student_attendance_is_headmaster()){
        return "headmaster-page.php";
    }
    if(student_attendance_is_assistant_head_academics()){
        return "assistant-head-academics-page.php";
    }
    if(student_attendance_is_teacher()){
        return "teacher-page.php";
    }
    if(student_attendance_is_student()){
        return "student-page.php";
    }
    return function_exists('um_home_link_for_session') ? um_home_link_for_session() : "index.php";
}
}

if(!function_exists('student_attendance_can_access')){
function student_attendance_can_access($con = null){
    if(student_attendance_is_admin() || student_attendance_is_headmaster() || student_attendance_is_assistant_head_academics()){
        return true;
    }
    if(student_attendance_is_teacher()){
        if(!$con){
            return false;
        }
        $teacherId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
        return $teacherId !== '' && class_teacher_has_any_assignment($con, $teacherId);
    }
    if($con && function_exists('um_current_user_can_access_module')){
        return um_current_user_can_access_module($con, 'student_attendance');
    }
    return false;
}
}

if(!function_exists('student_attendance_status_options')){
function student_attendance_status_options(){
    return array(
        'present' => 'Present',
        'absent' => 'Absent',
        'late' => 'Late',
        'excused' => 'Excused'
    );
}
}

if(!function_exists('student_attendance_normalize_status')){
function student_attendance_normalize_status($status){
    $status = strtolower(trim((string)$status));
    $allowed = student_attendance_status_options();
    return isset($allowed[$status]) ? $status : 'present';
}
}

if(!function_exists('student_attendance_session_label')){
function student_attendance_session_label($dateTimeValue, $batchLabel, $termValue){
    $yearValue = "";
    if(trim((string)$dateTimeValue) !== ""){
        $time = strtotime((string)$dateTimeValue);
        if($time){
            $yearValue = date("Y", $time);
        }
    }
    if($yearValue === ""){
        $yearValue = date("Y");
    }
    return trim($yearValue." Batch ".trim((string)$batchLabel)." Semester ".trim((string)$termValue));
}
}

if(!function_exists('student_attendance_teacher_name')){
function student_attendance_teacher_name($row){
    if(!is_array($row)){
        return "";
    }
    return trim(
        (string)(isset($row["teacher_firstname"]) ? $row["teacher_firstname"] : "")." ".
        (string)(isset($row["teacher_othernames"]) ? $row["teacher_othernames"] : "")." ".
        (string)(isset($row["teacher_surname"]) ? $row["teacher_surname"] : "")
    );
}
}

if(!function_exists('ensure_student_attendance_tables')){
function ensure_student_attendance_tables($con){
    ensure_class_teacher_table($con);
    if(function_exists('xschool_schema_cache_is_fresh') && xschool_schema_cache_is_fresh('schema_tblstudentattendance_v1')){
        return;
    }

    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblstudentattendancesession (
        sessionid VARCHAR(40) NOT NULL PRIMARY KEY,
        classid VARCHAR(30) NOT NULL,
        batchid VARCHAR(30) NOT NULL,
        termname INT NOT NULL,
        attendancedate DATE NOT NULL,
        teacherid VARCHAR(30) NOT NULL,
        generalnote TEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        datetimeentry DATETIME NOT NULL,
        updatedat DATETIME NULL DEFAULT NULL,
        recordedby VARCHAR(30) NOT NULL,
        UNIQUE KEY uq_attendancesession_class_day (classid,batchid,termname,attendancedate),
        KEY idx_attendancesession_teacher (teacherid),
        KEY idx_attendancesession_date (attendancedate),
        KEY idx_attendancesession_batch (batchid)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblstudentattendanceentry (
        entryid VARCHAR(40) NOT NULL PRIMARY KEY,
        sessionid VARCHAR(40) NOT NULL,
        userid VARCHAR(30) NOT NULL,
        attendancestatus VARCHAR(20) NOT NULL DEFAULT 'present',
        note VARCHAR(255) NULL,
        datetimeentry DATETIME NOT NULL,
        updatedat DATETIME NULL DEFAULT NULL,
        recordedby VARCHAR(30) NOT NULL,
        UNIQUE KEY uq_attendanceentry_session_student (sessionid,userid),
        KEY idx_attendanceentry_session (sessionid),
        KEY idx_attendanceentry_user (userid),
        KEY idx_attendanceentry_status (attendancestatus)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if(function_exists('xschool_schema_cache_mark')){
        xschool_schema_cache_mark('schema_tblstudentattendance_v1');
    }
}
}

if(!function_exists('student_attendance_list_assignments')){
function student_attendance_list_assignments($con, $teacherId = "", $restrictToTeacher = false){
    $assignments = array();
    $teacherClause = "";
    if($restrictToTeacher){
        $teacherIdEsc = mysqli_real_escape_string($con, trim((string)$teacherId));
        if($teacherIdEsc === ""){
            return $assignments;
        }
        $teacherClause = " AND ct.userid='$teacherIdEsc'";
    }

    $sql = "SELECT ct.assignmentid, ct.userid AS teacherid, ct.classid, ct.batchid, ct.termname, ct.datetimeentry AS assignment_datetimeentry,
                   ce.class_name, bh.batch,
                   su.firstname AS teacher_firstname, su.othernames AS teacher_othernames, su.surname AS teacher_surname
            FROM tblclassteacher ct
            INNER JOIN tblclassentry ce ON ce.class_entryid=ct.classid
            INNER JOIN tblbatch bh ON bh.batchid=ct.batchid
            LEFT JOIN tblsystemuser su ON su.userid=ct.userid
            WHERE ct.status='active' AND bh.status='active'".$teacherClause."
            ORDER BY bh.datetimeentry DESC, ct.termname DESC, ce.class_name ASC";
    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $row["teacher_name"] = student_attendance_teacher_name($row);
            $row["session_label"] = student_attendance_session_label($row["assignment_datetimeentry"], $row["batch"], $row["termname"]);
            $row["student_total"] = student_attendance_count_students_for_assignment($con, $row["classid"], $row["batchid"], $row["termname"]);
            $assignments[] = $row;
        }
    }
    return $assignments;
}
}

if(!function_exists('student_attendance_find_assignment')){
function student_attendance_find_assignment($con, $assignmentId, $teacherId = "", $restrictToTeacher = false){
    $assignmentIdEsc = mysqli_real_escape_string($con, trim((string)$assignmentId));
    if($assignmentIdEsc === ""){
        return null;
    }
    $teacherClause = "";
    if($restrictToTeacher){
        $teacherIdEsc = mysqli_real_escape_string($con, trim((string)$teacherId));
        if($teacherIdEsc === ""){
            return null;
        }
        $teacherClause = " AND ct.userid='$teacherIdEsc'";
    }
    $sql = "SELECT ct.assignmentid, ct.userid AS teacherid, ct.classid, ct.batchid, ct.termname, ct.datetimeentry AS assignment_datetimeentry,
                   ce.class_name, bh.batch,
                   su.firstname AS teacher_firstname, su.othernames AS teacher_othernames, su.surname AS teacher_surname
            FROM tblclassteacher ct
            INNER JOIN tblclassentry ce ON ce.class_entryid=ct.classid
            INNER JOIN tblbatch bh ON bh.batchid=ct.batchid
            LEFT JOIN tblsystemuser su ON su.userid=ct.userid
            WHERE ct.assignmentid='$assignmentIdEsc' AND ct.status='active'".$teacherClause."
            LIMIT 1";
    $res = mysqli_query($con, $sql);
    if($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
        $row["teacher_name"] = student_attendance_teacher_name($row);
        $row["session_label"] = student_attendance_session_label($row["assignment_datetimeentry"], $row["batch"], $row["termname"]);
        $row["student_total"] = student_attendance_count_students_for_assignment($con, $row["classid"], $row["batchid"], $row["termname"]);
        return $row;
    }
    return null;
}
}

if(!function_exists('student_attendance_count_students_for_assignment')){
function student_attendance_count_students_for_assignment($con, $classId, $batchId, $termName){
    $classIdEsc = mysqli_real_escape_string($con, trim((string)$classId));
    $batchIdEsc = mysqli_real_escape_string($con, trim((string)$batchId));
    $termName = (int)$termName;
    if($classIdEsc === "" || $batchIdEsc === "" || $termName <= 0){
        return 0;
    }
    $res = mysqli_query($con, "SELECT COUNT(*) AS total
        FROM tbltermregistry tr
        INNER JOIN tblsystemuser su ON su.userid=tr.userid
        WHERE tr.class_entryid='$classIdEsc'
          AND tr.batchid='$batchIdEsc'
          AND tr.termname='$termName'
          AND tr.status='active'
          AND su.systemtype='Student'");
    if($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
        return (int)$row["total"];
    }
    return 0;
}
}

if(!function_exists('student_attendance_get_roster')){
function student_attendance_get_roster($con, $classId, $batchId, $termName){
    $roster = array();
    $classIdEsc = mysqli_real_escape_string($con, trim((string)$classId));
    $batchIdEsc = mysqli_real_escape_string($con, trim((string)$batchId));
    $termName = (int)$termName;
    if($classIdEsc === "" || $batchIdEsc === "" || $termName <= 0){
        return $roster;
    }
    $sql = "SELECT tr.userid, su.firstname, su.surname, su.othernames, su.gender, su.filename
            FROM tbltermregistry tr
            INNER JOIN tblsystemuser su ON su.userid=tr.userid
            WHERE tr.class_entryid='$classIdEsc'
              AND tr.batchid='$batchIdEsc'
              AND tr.termname='$termName'
              AND tr.status='active'
              AND su.systemtype='Student'
            ORDER BY su.firstname ASC, su.othernames ASC, su.surname ASC";
    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $row["fullname"] = trim($row["firstname"]." ".$row["othernames"]." ".$row["surname"]);
            $roster[] = $row;
        }
    }
    return $roster;
}
}

if(!function_exists('student_attendance_get_session')){
function student_attendance_get_session($con, $classId, $batchId, $termName, $attendanceDate){
    $classIdEsc = mysqli_real_escape_string($con, trim((string)$classId));
    $batchIdEsc = mysqli_real_escape_string($con, trim((string)$batchId));
    $attendanceDateEsc = mysqli_real_escape_string($con, trim((string)$attendanceDate));
    $termName = (int)$termName;
    if($classIdEsc === "" || $batchIdEsc === "" || $attendanceDateEsc === "" || $termName <= 0){
        return null;
    }
    $res = mysqli_query($con, "SELECT * FROM tblstudentattendancesession
        WHERE classid='$classIdEsc'
          AND batchid='$batchIdEsc'
          AND termname='$termName'
          AND attendancedate='$attendanceDateEsc'
        LIMIT 1");
    if($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
        return $row;
    }
    return null;
}
}

if(!function_exists('student_attendance_get_entries')){
function student_attendance_get_entries($con, $sessionId){
    $entries = array();
    $sessionIdEsc = mysqli_real_escape_string($con, trim((string)$sessionId));
    if($sessionIdEsc === ""){
        return $entries;
    }
    $res = mysqli_query($con, "SELECT * FROM tblstudentattendanceentry
        WHERE sessionid='$sessionIdEsc'
        ORDER BY datetimeentry ASC");
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $entries[(string)$row["userid"]] = $row;
        }
    }
    return $entries;
}
}

if(!function_exists('student_attendance_count_session_statuses')){
function student_attendance_count_session_statuses($entries){
    $counts = array(
        'present' => 0,
        'absent' => 0,
        'late' => 0,
        'excused' => 0
    );
    if(!is_array($entries)){
        return $counts;
    }
    foreach($entries as $entry){
        $status = student_attendance_normalize_status(isset($entry["attendancestatus"]) ? $entry["attendancestatus"] : "");
        if(!isset($counts[$status])){
            $counts[$status] = 0;
        }
        $counts[$status]++;
    }
    return $counts;
}
}

if(!function_exists('student_attendance_save_register')){
function student_attendance_save_register($con, $assignment, $attendanceDate, $statuses, $notes, $generalNote, $recordedBy, &$errorMessage){
    $errorMessage = "";
    if(!is_array($assignment) || empty($assignment)){
        $errorMessage = "Select a valid class-teacher assignment first.";
        return false;
    }
    $attendanceDate = trim((string)$attendanceDate);
    $attendanceTime = strtotime($attendanceDate);
    if($attendanceDate === "" || $attendanceTime === false){
        $errorMessage = "Choose a valid attendance date.";
        return false;
    }
    $attendanceDate = date("Y-m-d", $attendanceTime);
    $roster = student_attendance_get_roster($con, $assignment["classid"], $assignment["batchid"], $assignment["termname"]);
    if(empty($roster)){
        $errorMessage = "There are no active students registered in that class, batch, and semester yet.";
        return false;
    }

    $existingSession = student_attendance_get_session($con, $assignment["classid"], $assignment["batchid"], $assignment["termname"], $attendanceDate);
    $sessionId = $existingSession ? (string)$existingSession["sessionid"] : student_attendance_generate_id("ATTS_");
    $sessionIdEsc = mysqli_real_escape_string($con, $sessionId);
    $classIdEsc = mysqli_real_escape_string($con, (string)$assignment["classid"]);
    $batchIdEsc = mysqli_real_escape_string($con, (string)$assignment["batchid"]);
    $teacherIdEsc = mysqli_real_escape_string($con, (string)$assignment["teacherid"]);
    $attendanceDateEsc = mysqli_real_escape_string($con, $attendanceDate);
    $generalNoteEsc = mysqli_real_escape_string($con, trim((string)$generalNote));
    $recordedByEsc = mysqli_real_escape_string($con, trim((string)$recordedBy));
    $termName = (int)$assignment["termname"];

    $sessionSaved = mysqli_query($con, "INSERT INTO tblstudentattendancesession(
            sessionid, classid, batchid, termname, attendancedate, teacherid, generalnote, status, datetimeentry, updatedat, recordedby
        ) VALUES(
            '$sessionIdEsc', '$classIdEsc', '$batchIdEsc', '$termName', '$attendanceDateEsc', '$teacherIdEsc', '$generalNoteEsc', 'active', NOW(), NOW(), '$recordedByEsc'
        ) ON DUPLICATE KEY UPDATE
            teacherid=VALUES(teacherid),
            generalnote=VALUES(generalnote),
            status='active',
            updatedat=NOW(),
            recordedby=VALUES(recordedby)");
    if(!$sessionSaved){
        $errorMessage = "Attendance register could not be saved right now.";
        return false;
    }

    foreach($roster as $student){
        $studentId = (string)$student["userid"];
        $status = student_attendance_normalize_status(isset($statuses[$studentId]) ? $statuses[$studentId] : "present");
        $note = trim((string)(isset($notes[$studentId]) ? $notes[$studentId] : ""));
        if(strlen($note) > 255){
            $note = substr($note, 0, 255);
        }
        $entryId = student_attendance_generate_id("ATTE_");
        $entryIdEsc = mysqli_real_escape_string($con, $entryId);
        $studentIdEsc = mysqli_real_escape_string($con, $studentId);
        $statusEsc = mysqli_real_escape_string($con, $status);
        $noteEsc = mysqli_real_escape_string($con, $note);

        @mysqli_query($con, "INSERT INTO tblstudentattendanceentry(
                entryid, sessionid, userid, attendancestatus, note, datetimeentry, updatedat, recordedby
            ) VALUES(
                '$entryIdEsc', '$sessionIdEsc', '$studentIdEsc', '$statusEsc', '$noteEsc', NOW(), NOW(), '$recordedByEsc'
            ) ON DUPLICATE KEY UPDATE
                attendancestatus=VALUES(attendancestatus),
                note=VALUES(note),
                updatedat=NOW(),
                recordedby=VALUES(recordedby)");
    }

    return student_attendance_get_session($con, $assignment["classid"], $assignment["batchid"], $assignment["termname"], $attendanceDate);
}
}

if(!function_exists('student_attendance_list_recent_sessions')){
function student_attendance_list_recent_sessions($con, $assignment, $limit = 14){
    $sessions = array();
    if(!is_array($assignment) || empty($assignment)){
        return $sessions;
    }
    $classIdEsc = mysqli_real_escape_string($con, (string)$assignment["classid"]);
    $batchIdEsc = mysqli_real_escape_string($con, (string)$assignment["batchid"]);
    $termName = (int)$assignment["termname"];
    $limit = max(1, (int)$limit);
    $sql = "SELECT ats.*,
                   SUM(CASE WHEN ate.attendancestatus='present' THEN 1 ELSE 0 END) AS present_total,
                   SUM(CASE WHEN ate.attendancestatus='absent' THEN 1 ELSE 0 END) AS absent_total,
                   SUM(CASE WHEN ate.attendancestatus='late' THEN 1 ELSE 0 END) AS late_total,
                   SUM(CASE WHEN ate.attendancestatus='excused' THEN 1 ELSE 0 END) AS excused_total,
                   COUNT(ate.entryid) AS student_total
            FROM tblstudentattendancesession ats
            LEFT JOIN tblstudentattendanceentry ate ON ate.sessionid=ats.sessionid
            WHERE ats.classid='$classIdEsc'
              AND ats.batchid='$batchIdEsc'
              AND ats.termname='$termName'
            GROUP BY ats.sessionid
            ORDER BY ats.attendancedate DESC
            LIMIT ".$limit;
    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $sessions[] = $row;
        }
    }
    return $sessions;
}
}

if(!function_exists('student_attendance_teacher_dashboard_summary')){
function student_attendance_teacher_dashboard_summary($con, $teacherId){
    $teacherIdEsc = mysqli_real_escape_string($con, trim((string)$teacherId));
    $today = date("Y-m-d");
    $summary = array(
        'assignment_count' => 0,
        'today_session_count' => 0,
        'today_marked_count' => 0
    );
    if($teacherIdEsc === ""){
        return $summary;
    }
    $resAssignments = mysqli_query($con, "SELECT COUNT(*) AS total FROM tblclassteacher WHERE userid='$teacherIdEsc' AND status='active'");
    if($resAssignments && ($row = mysqli_fetch_array($resAssignments, MYSQLI_ASSOC))){
        $summary['assignment_count'] = (int)$row['total'];
    }
    $resSessions = mysqli_query($con, "SELECT COUNT(*) AS total FROM tblstudentattendancesession WHERE teacherid='$teacherIdEsc' AND attendancedate='$today'");
    if($resSessions && ($row = mysqli_fetch_array($resSessions, MYSQLI_ASSOC))){
        $summary['today_session_count'] = (int)$row['total'];
    }
    $resEntries = mysqli_query($con, "SELECT COUNT(ate.entryid) AS total
        FROM tblstudentattendanceentry ate
        INNER JOIN tblstudentattendancesession ats ON ats.sessionid=ate.sessionid
        WHERE ats.teacherid='$teacherIdEsc' AND ats.attendancedate='$today'");
    if($resEntries && ($row = mysqli_fetch_array($resEntries, MYSQLI_ASSOC))){
        $summary['today_marked_count'] = (int)$row['total'];
    }
    return $summary;
}
}

if(!function_exists('student_attendance_normalize_date_range')){
function student_attendance_normalize_date_range($dateFrom, $dateTo, $defaultDays = 30){
    $defaultDays = max(1, (int)$defaultDays);
    $dateFrom = trim((string)$dateFrom);
    $dateTo = trim((string)$dateTo);
    $today = date("Y-m-d");

    $toTime = strtotime($dateTo);
    if($dateTo === "" || $toTime === false){
        $dateTo = $today;
        $toTime = strtotime($dateTo);
    }else{
        $dateTo = date("Y-m-d", $toTime);
    }

    $fromTime = strtotime($dateFrom);
    if($dateFrom === "" || $fromTime === false){
        $dateFrom = date("Y-m-d", strtotime("-".($defaultDays - 1)." days", $toTime));
        $fromTime = strtotime($dateFrom);
    }else{
        $dateFrom = date("Y-m-d", $fromTime);
    }

    if($fromTime > $toTime){
        $swap = $dateFrom;
        $dateFrom = $dateTo;
        $dateTo = $swap;
    }

    return array($dateFrom, $dateTo);
}
}

if(!function_exists('student_attendance_date_range_clause')){
function student_attendance_date_range_clause($con, $columnName, $dateFrom, $dateTo){
    $columnName = trim((string)$columnName);
    if($columnName === ""){
        $columnName = "attendancedate";
    }
    $dateFromEsc = mysqli_real_escape_string($con, trim((string)$dateFrom));
    $dateToEsc = mysqli_real_escape_string($con, trim((string)$dateTo));
    return " AND ".$columnName." BETWEEN '".$dateFromEsc."' AND '".$dateToEsc."'";
}
}

if(!function_exists('student_attendance_status_counts_template')){
function student_attendance_status_counts_template(){
    return array(
        'present' => 0,
        'absent' => 0,
        'late' => 0,
        'excused' => 0
    );
}
}

if(!function_exists('student_attendance_status_count_sql')){
function student_attendance_status_count_sql($alias = 'ate'){
    $alias = preg_replace('/[^A-Za-z0-9_]/', '', (string)$alias);
    if($alias === ""){
        $alias = 'ate';
    }
    return "SUM(CASE WHEN ".$alias.".attendancestatus='present' THEN 1 ELSE 0 END) AS present_total,
            SUM(CASE WHEN ".$alias.".attendancestatus='absent' THEN 1 ELSE 0 END) AS absent_total,
            SUM(CASE WHEN ".$alias.".attendancestatus='late' THEN 1 ELSE 0 END) AS late_total,
            SUM(CASE WHEN ".$alias.".attendancestatus='excused' THEN 1 ELSE 0 END) AS excused_total";
}
}

if(!function_exists('student_attendance_assignment_range_overview')){
function student_attendance_assignment_range_overview($con, $assignment, $dateFrom, $dateTo){
    $summary = array(
        'session_total' => 0,
        'student_total' => 0,
        'marked_total' => 0,
        'present_total' => 0,
        'absent_total' => 0,
        'late_total' => 0,
        'excused_total' => 0,
        'attendance_rate' => 0
    );
    if(!is_array($assignment) || empty($assignment)){
        return $summary;
    }
    $classIdEsc = mysqli_real_escape_string($con, (string)$assignment["classid"]);
    $batchIdEsc = mysqli_real_escape_string($con, (string)$assignment["batchid"]);
    $termName = (int)$assignment["termname"];
    $sql = "SELECT COUNT(DISTINCT ats.sessionid) AS session_total,
                   COUNT(ate.entryid) AS marked_total,
                   ".student_attendance_status_count_sql('ate')."
            FROM tblstudentattendancesession ats
            LEFT JOIN tblstudentattendanceentry ate ON ate.sessionid=ats.sessionid
            WHERE ats.classid='$classIdEsc'
              AND ats.batchid='$batchIdEsc'
              AND ats.termname='$termName'".
              student_attendance_date_range_clause($con, 'ats.attendancedate', $dateFrom, $dateTo);
    $res = mysqli_query($con, $sql);
    if($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
        $summary['session_total'] = (int)$row['session_total'];
        $summary['marked_total'] = (int)$row['marked_total'];
        $summary['present_total'] = (int)$row['present_total'];
        $summary['absent_total'] = (int)$row['absent_total'];
        $summary['late_total'] = (int)$row['late_total'];
        $summary['excused_total'] = (int)$row['excused_total'];
    }
    $summary['student_total'] = student_attendance_count_students_for_assignment($con, $assignment["classid"], $assignment["batchid"], $assignment["termname"]);
    if($summary['marked_total'] > 0){
        $summary['attendance_rate'] = round((($summary['present_total'] + $summary['late_total']) / $summary['marked_total']) * 100, 1);
    }
    return $summary;
}
}

if(!function_exists('student_attendance_assignment_daily_summary')){
function student_attendance_assignment_daily_summary($con, $assignment, $dateFrom, $dateTo){
    $days = array();
    if(!is_array($assignment) || empty($assignment)){
        return $days;
    }
    $classIdEsc = mysqli_real_escape_string($con, (string)$assignment["classid"]);
    $batchIdEsc = mysqli_real_escape_string($con, (string)$assignment["batchid"]);
    $termName = (int)$assignment["termname"];
    $sql = "SELECT ats.attendancedate,
                   COUNT(ate.entryid) AS marked_total,
                   ".student_attendance_status_count_sql('ate')."
            FROM tblstudentattendancesession ats
            LEFT JOIN tblstudentattendanceentry ate ON ate.sessionid=ats.sessionid
            WHERE ats.classid='$classIdEsc'
              AND ats.batchid='$batchIdEsc'
              AND ats.termname='$termName'".
              student_attendance_date_range_clause($con, 'ats.attendancedate', $dateFrom, $dateTo)."
            GROUP BY ats.attendancedate
            ORDER BY ats.attendancedate ASC";
    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $row['marked_total'] = (int)$row['marked_total'];
            $row['present_total'] = (int)$row['present_total'];
            $row['absent_total'] = (int)$row['absent_total'];
            $row['late_total'] = (int)$row['late_total'];
            $row['excused_total'] = (int)$row['excused_total'];
            $row['attendance_rate'] = $row['marked_total'] > 0 ? round((($row['present_total'] + $row['late_total']) / $row['marked_total']) * 100, 1) : 0;
            $days[] = $row;
        }
    }
    return $days;
}
}

if(!function_exists('student_attendance_assignment_student_summary')){
function student_attendance_assignment_student_summary($con, $assignment, $dateFrom, $dateTo){
    $students = array();
    if(!is_array($assignment) || empty($assignment)){
        return $students;
    }
    $classIdEsc = mysqli_real_escape_string($con, (string)$assignment["classid"]);
    $batchIdEsc = mysqli_real_escape_string($con, (string)$assignment["batchid"]);
    $termName = (int)$assignment["termname"];
    $sql = "SELECT tr.userid, su.firstname, su.othernames, su.surname,
                   COUNT(ate.entryid) AS marked_total,
                   ".student_attendance_status_count_sql('ate')."
            FROM tbltermregistry tr
            INNER JOIN tblsystemuser su ON su.userid=tr.userid
            LEFT JOIN tblstudentattendancesession ats
                   ON ats.classid=tr.class_entryid
                  AND ats.batchid=tr.batchid
                  AND ats.termname=tr.termname".
                  student_attendance_date_range_clause($con, 'ats.attendancedate', $dateFrom, $dateTo)."
            LEFT JOIN tblstudentattendanceentry ate
                   ON ate.sessionid=ats.sessionid
                  AND ate.userid=tr.userid
            WHERE tr.class_entryid='$classIdEsc'
              AND tr.batchid='$batchIdEsc'
              AND tr.termname='$termName'
              AND tr.status='active'
            GROUP BY tr.userid, su.firstname, su.othernames, su.surname
            ORDER BY su.firstname ASC, su.othernames ASC, su.surname ASC";
    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $row['fullname'] = trim($row['firstname']." ".$row['othernames']." ".$row['surname']);
            $row['marked_total'] = (int)$row['marked_total'];
            $row['present_total'] = (int)$row['present_total'];
            $row['absent_total'] = (int)$row['absent_total'];
            $row['late_total'] = (int)$row['late_total'];
            $row['excused_total'] = (int)$row['excused_total'];
            $row['attendance_rate'] = $row['marked_total'] > 0 ? round((($row['present_total'] + $row['late_total']) / $row['marked_total']) * 100, 1) : 0;
            $students[] = $row;
        }
    }
    return $students;
}
}

if(!function_exists('student_attendance_student_overview')){
function student_attendance_student_overview($con, $studentId, $dateFrom, $dateTo){
    $summary = array(
        'session_total' => 0,
        'present_total' => 0,
        'absent_total' => 0,
        'late_total' => 0,
        'excused_total' => 0,
        'attendance_rate' => 0
    );
    $studentIdEsc = mysqli_real_escape_string($con, trim((string)$studentId));
    if($studentIdEsc === ""){
        return $summary;
    }
    $sql = "SELECT COUNT(ate.entryid) AS session_total,
                   ".student_attendance_status_count_sql('ate')."
            FROM tblstudentattendanceentry ate
            INNER JOIN tblstudentattendancesession ats ON ats.sessionid=ate.sessionid
            WHERE ate.userid='$studentIdEsc'".
            student_attendance_date_range_clause($con, 'ats.attendancedate', $dateFrom, $dateTo);
    $res = mysqli_query($con, $sql);
    if($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
        $summary['session_total'] = (int)$row['session_total'];
        $summary['present_total'] = (int)$row['present_total'];
        $summary['absent_total'] = (int)$row['absent_total'];
        $summary['late_total'] = (int)$row['late_total'];
        $summary['excused_total'] = (int)$row['excused_total'];
    }
    if($summary['session_total'] > 0){
        $summary['attendance_rate'] = round((($summary['present_total'] + $summary['late_total']) / $summary['session_total']) * 100, 1);
    }
    return $summary;
}
}

if(!function_exists('student_attendance_student_daily_summary')){
function student_attendance_student_daily_summary($con, $studentId, $dateFrom, $dateTo){
    $days = array();
    $studentIdEsc = mysqli_real_escape_string($con, trim((string)$studentId));
    if($studentIdEsc === ""){
        return $days;
    }
    $sql = "SELECT ats.attendancedate, ate.attendancestatus, ate.note, ce.class_name, bh.batch, ats.termname
            FROM tblstudentattendanceentry ate
            INNER JOIN tblstudentattendancesession ats ON ats.sessionid=ate.sessionid
            LEFT JOIN tblclassentry ce ON ce.class_entryid=ats.classid
            LEFT JOIN tblbatch bh ON bh.batchid=ats.batchid
            WHERE ate.userid='$studentIdEsc'".
            student_attendance_date_range_clause($con, 'ats.attendancedate', $dateFrom, $dateTo)."
            ORDER BY ats.attendancedate ASC";
    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $row['attendancestatus'] = student_attendance_normalize_status($row['attendancestatus']);
            $row['attendance_score'] = ($row['attendancestatus'] === 'present') ? 100 : (($row['attendancestatus'] === 'late') ? 70 : (($row['attendancestatus'] === 'excused') ? 45 : 10));
            $days[] = $row;
        }
    }
    return $days;
}
}

if(!function_exists('student_attendance_student_recent_history')){
function student_attendance_student_recent_history($con, $studentId, $limit = 15){
    $history = array();
    $studentIdEsc = mysqli_real_escape_string($con, trim((string)$studentId));
    $limit = max(1, (int)$limit);
    if($studentIdEsc === ""){
        return $history;
    }
    $sql = "SELECT ats.attendancedate, ate.attendancestatus, ate.note, ce.class_name, bh.batch, ats.termname
            FROM tblstudentattendanceentry ate
            INNER JOIN tblstudentattendancesession ats ON ats.sessionid=ate.sessionid
            LEFT JOIN tblclassentry ce ON ce.class_entryid=ats.classid
            LEFT JOIN tblbatch bh ON bh.batchid=ats.batchid
            WHERE ate.userid='$studentIdEsc'
            ORDER BY ats.attendancedate DESC
            LIMIT ".$limit;
    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $row['attendancestatus'] = student_attendance_normalize_status($row['attendancestatus']);
            $history[] = $row;
        }
    }
    return $history;
}
}
?>
