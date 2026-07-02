<?php
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

if(!function_exists('class_teacher_is_admin')){
function class_teacher_is_admin(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === "administrator" &&
        ($_SESSION['SYSTEMTYPE'] === "normal_user" || $_SESSION['SYSTEMTYPE'] === "super_user");
}
}

if(!function_exists('class_teacher_is_teacher')){
function class_teacher_is_teacher(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === "user" &&
        $_SESSION['SYSTEMTYPE'] === "Teacher";
}
}

if(!function_exists('class_teacher_landing_page')){
function class_teacher_landing_page(){
    if(class_teacher_is_admin()){
        return ($_SESSION['SYSTEMTYPE'] === "super_user") ? "super.php" : "admin.php";
    }
    if(class_teacher_is_teacher()){
        return "teacher-page.php";
    }
    if(isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) && $_SESSION['ACCESSLEVEL'] === "user" && $_SESSION['SYSTEMTYPE'] === "Student"){
        return "student-page.php";
    }
    return function_exists('um_home_link_for_session') ? um_home_link_for_session() : "index.php";
}
}

if(!function_exists('class_teacher_can_manage_assignments')){
function class_teacher_can_manage_assignments($con = null){
    if(class_teacher_is_admin()){
        return true;
    }
    if(!$con || !function_exists('um_current_user_can_access_module')){
        return false;
    }
    return um_current_user_can_access_module($con, 'class_teacher_assignment');
}
}

if(!function_exists('ensure_class_teacher_table')){
function ensure_class_teacher_table($con){
    if(xschool_schema_cache_is_fresh('schema_tblclassteacher_v1')){
        return;
    }
    $sql = "CREATE TABLE IF NOT EXISTS tblclassteacher (
        assignmentid VARCHAR(40) NOT NULL PRIMARY KEY,
        userid VARCHAR(30) NOT NULL,
        classid VARCHAR(30) NOT NULL,
        batchid VARCHAR(30) NOT NULL,
        termname INT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        datetimeentry DATETIME NOT NULL,
        recordedby VARCHAR(30) NOT NULL,
        INDEX idx_teacher (userid),
        INDEX idx_class_batch_term (classid,batchid,termname),
        INDEX idx_status (status)
    )";
    mysqli_query($con, $sql);
    xschool_schema_cache_mark('schema_tblclassteacher_v1');
}
}

if(!function_exists('ensure_student_terminal_term_column')){
function ensure_student_terminal_term_column($con){
    if(xschool_schema_cache_is_fresh('schema_tblstudentterminalreport_termname_v1')){
        return;
    }
    $colRes = mysqli_query($con, "SHOW COLUMNS FROM tblstudentterminalreport LIKE 'termname'");
    if(!$colRes || mysqli_num_rows($colRes) === 0){
        mysqli_query($con, "ALTER TABLE tblstudentterminalreport ADD COLUMN termname INT NOT NULL DEFAULT 0 AFTER batchid");
        mysqli_query($con, "CREATE INDEX idx_terminal_user_batch_term ON tblstudentterminalreport(userid,batchid,termname)");
    }
    xschool_schema_cache_mark('schema_tblstudentterminalreport_termname_v1');
}
}

if(!function_exists('class_teacher_is_assigned')){
function class_teacher_is_assigned($con, $teacherId, $classId, $batchId, $termName){
    $teacherId = mysqli_real_escape_string($con, (string)$teacherId);
    $classId = mysqli_real_escape_string($con, (string)$classId);
    $batchId = mysqli_real_escape_string($con, (string)$batchId);
    $termName = (int)$termName;
    $sql = "SELECT assignmentid FROM tblclassteacher
            WHERE userid='$teacherId' AND classid='$classId' AND batchid='$batchId'
              AND termname='$termName' AND status='active' LIMIT 1";
    $res = mysqli_query($con, $sql);
    return ($res && mysqli_num_rows($res) > 0);
}
}

if(!function_exists('class_teacher_has_any_assignment')){
function class_teacher_has_any_assignment($con, $teacherId){
    $teacherId = mysqli_real_escape_string($con, (string)$teacherId);
    if($teacherId === ""){
        return false;
    }
    $sql = "SELECT assignmentid FROM tblclassteacher
            WHERE userid='$teacherId' AND status='active'
            LIMIT 1";
    $res = mysqli_query($con, $sql);
    return ($res && mysqli_num_rows($res) > 0);
}
}

if(!function_exists('class_teacher_can_manage_student_batch')){
function class_teacher_can_manage_student_batch($con, $teacherId, $studentId, $batchId, $termName = null){
    $teacherId = mysqli_real_escape_string($con, (string)$teacherId);
    $studentId = mysqli_real_escape_string($con, (string)$studentId);
    $batchId = mysqli_real_escape_string($con, (string)$batchId);
    $termClause = "";
    if($termName !== null){
        $termClause = " AND ct.termname='".((int)$termName)."'";
    }
    $sql = "SELECT ct.assignmentid
            FROM tbltermregistry tr
            INNER JOIN tblclassteacher ct
                ON ct.classid=tr.class_entryid
               AND ct.batchid=tr.batchid
               AND ct.termname=tr.termname
               AND ct.status='active'
            WHERE ct.userid='$teacherId'
              AND tr.userid='$studentId'
              AND tr.batchid='$batchId'
              $termClause
            LIMIT 1";
    $res = mysqli_query($con, $sql);
    return ($res && mysqli_num_rows($res) > 0);
}
}
?>
