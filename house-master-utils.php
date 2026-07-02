<?php
if(!function_exists('xschool_schema_cache_is_fresh')){
function xschool_schema_cache_is_fresh($key, $ttlSeconds = 900){
    if(!isset($GLOBALS['_xschool_schema_memory_cache']) || !is_array($GLOBALS['_xschool_schema_memory_cache'])){
        $GLOBALS['_xschool_schema_memory_cache'] = array();
    }
    $memoryCache = &$GLOBALS['_xschool_schema_memory_cache'];
    $key = trim((string)$key);
    if($key === ""){
        return false;
    }
    if(isset($memoryCache[$key]) && ((int)$memoryCache[$key] + (int)$ttlSeconds) > time()){
        return true;
    }

    $sessionFresh = false;
    if(PHP_SAPI !== 'cli' && function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE){
        $cacheBag = isset($_SESSION['_xschool_schema_cache']) && is_array($_SESSION['_xschool_schema_cache'])
            ? $_SESSION['_xschool_schema_cache']
            : array();
        $sessionFresh = isset($cacheBag[$key]) && ((int)$cacheBag[$key] + (int)$ttlSeconds) > time();
    }

    $sharedFresh = false;
    if(function_exists('xschool_schema_cache_shared_file')){
        $sharedFile = xschool_schema_cache_shared_file();
        if($sharedFile !== '' && is_file($sharedFile)){
            $raw = @file_get_contents($sharedFile);
            $decoded = json_decode((string)$raw, true);
            if(is_array($decoded) && isset($decoded[$key]) && ((int)$decoded[$key] + (int)$ttlSeconds) > time()){
                $sharedFresh = true;
            }
        }
    }

    $isFresh = ($sessionFresh || $sharedFresh);
    if($isFresh){
        $memoryCache[$key] = time();
    }
    return $isFresh;
}
}

if(!function_exists('xschool_schema_cache_mark')){
function xschool_schema_cache_mark($key){
    if(!isset($GLOBALS['_xschool_schema_memory_cache']) || !is_array($GLOBALS['_xschool_schema_memory_cache'])){
        $GLOBALS['_xschool_schema_memory_cache'] = array();
    }
    $memoryCache = &$GLOBALS['_xschool_schema_memory_cache'];
    $key = trim((string)$key);
    if($key === ""){
        return;
    }
    $memoryCache[$key] = time();

    if(PHP_SAPI === 'cli' || !function_exists('session_status') || session_status() !== PHP_SESSION_ACTIVE){
        if(function_exists('xschool_schema_cache_write_shared')){
            xschool_schema_cache_write_shared($key, (int)$memoryCache[$key]);
        }
        return;
    }
    if(!isset($_SESSION['_xschool_schema_cache']) || !is_array($_SESSION['_xschool_schema_cache'])){
        $_SESSION['_xschool_schema_cache'] = array();
    }
    $_SESSION['_xschool_schema_cache'][$key] = (int)$memoryCache[$key];
    if(function_exists('xschool_schema_cache_write_shared')){
        xschool_schema_cache_write_shared($key, (int)$_SESSION['_xschool_schema_cache'][$key]);
    }
}
}

if(!function_exists('xschool_schema_cache_shared_file')){
function xschool_schema_cache_shared_file(){
    $tempDir = function_exists('sys_get_temp_dir') ? (string)sys_get_temp_dir() : '';
    if($tempDir === ''){
        return '';
    }
    return rtrim($tempDir, '\\/').DIRECTORY_SEPARATOR.'xschool-2026semester1-schema-cache.json';
}
}

if(!function_exists('xschool_schema_cache_write_shared')){
function xschool_schema_cache_write_shared($key, $timestamp){
    $sharedFile = xschool_schema_cache_shared_file();
    if($sharedFile === ''){
        return false;
    }
    $timestamp = (int)$timestamp;
    $cacheBag = array();
    if(is_file($sharedFile)){
        $decoded = json_decode((string)@file_get_contents($sharedFile), true);
        if(is_array($decoded)){
            $cacheBag = $decoded;
        }
    }
    $cacheBag[$key] = $timestamp > 0 ? $timestamp : time();
    return @file_put_contents($sharedFile, json_encode($cacheBag), LOCK_EX) !== false;
}
}

if(!function_exists('xschool_schema_ensure_index')){
function xschool_schema_ensure_index($con, $tableName, $indexName, $createSql){
    if(!$con){
        return false;
    }
    $tableName = trim((string)$tableName);
    $indexName = trim((string)$indexName);
    $createSql = trim((string)$createSql);
    if($tableName === '' || $indexName === '' || $createSql === ''){
        return false;
    }
    $tableEsc = mysqli_real_escape_string($con, $tableName);
    $indexEsc = mysqli_real_escape_string($con, $indexName);
    $res = mysqli_query($con, "SHOW INDEX FROM `$tableEsc` WHERE Key_name='$indexEsc'");
    if($res && mysqli_num_rows($res) > 0){
        return true;
    }
    return mysqli_query($con, $createSql) ? true : false;
}
}

if(!function_exists('house_master_is_admin')){
function house_master_is_admin(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === "administrator" &&
        ($_SESSION['SYSTEMTYPE'] === "normal_user" || $_SESSION['SYSTEMTYPE'] === "super_user");
}
}

if(!function_exists('house_master_is_teacher')){
function house_master_is_teacher(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === "user" &&
        $_SESSION['SYSTEMTYPE'] === "Teacher";
}
}

if(!function_exists('house_master_is_student')){
function house_master_is_student(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === "user" &&
        $_SESSION['SYSTEMTYPE'] === "Student";
}
}

if(!function_exists('house_master_landing_page')){
function house_master_landing_page(){
    if(house_master_is_admin()){
        return ($_SESSION['SYSTEMTYPE'] === "super_user") ? "super.php" : "admin.php";
    }
    if(house_master_is_teacher()){
        return "teacher-page.php";
    }
    if(house_master_is_student()){
        return "student-page.php";
    }
    return function_exists('um_home_link_for_session') ? um_home_link_for_session() : "index.php";
}
}

if(!function_exists('house_master_can_manage_module')){
function house_master_can_manage_module($con = null, $moduleKey = ''){
    if(house_master_is_admin()){
        return true;
    }
    $moduleKey = trim((string)$moduleKey);
    if($moduleKey === '' || !$con || !function_exists('um_current_user_can_access_module')){
        return false;
    }
    return um_current_user_can_access_module($con, $moduleKey);
}
}

if(!function_exists('house_master_can_view_senior_dashboard')){
function house_master_can_view_senior_dashboard($con = null){
    if(house_master_is_admin()){
        return true;
    }
    if(isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
       $_SESSION['ACCESSLEVEL'] === "user" &&
       $_SESSION['SYSTEMTYPE'] === "Headmaster"){
        return true;
    }
    if($con && house_master_is_teacher() && isset($_SESSION['USERID'])){
        return house_master_has_senior_assignment($con, $_SESSION['USERID']);
    }
    return false;
}
}

if(!function_exists('ensure_house_tables')){
function ensure_house_tables($con){
    if(xschool_schema_cache_is_fresh('schema_house_master_v2', 43200)){
        return;
    }
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblhouse (
        houseid VARCHAR(40) NOT NULL PRIMARY KEY,
        housename VARCHAR(80) NOT NULL,
        description VARCHAR(255) NULL,
        housegender VARCHAR(20) NULL,
        houseresidencetype VARCHAR(20) NULL,
        autoassignenabled TINYINT(1) NOT NULL DEFAULT 1,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        datetimeentry DATETIME NOT NULL,
        recordedby VARCHAR(30) NOT NULL,
        UNIQUE KEY uq_housename (housename),
        INDEX idx_house_status (status)
    )");

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblhousemaster (
        assignmentid VARCHAR(40) NOT NULL PRIMARY KEY,
        houseid VARCHAR(40) NOT NULL,
        userid VARCHAR(30) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        datetimeentry DATETIME NOT NULL,
        recordedby VARCHAR(30) NOT NULL,
        INDEX idx_housemaster_teacher (userid),
        INDEX idx_housemaster_house (houseid),
        INDEX idx_housemaster_status (status)
    )");

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblseniorhouseauthority (
        assignmentid VARCHAR(40) NOT NULL PRIMARY KEY,
        userid VARCHAR(30) NOT NULL,
        designation VARCHAR(40) NOT NULL DEFAULT 'Senior House Master',
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        datetimeentry DATETIME NOT NULL,
        recordedby VARCHAR(30) NOT NULL,
        INDEX idx_seniorhouse_user (userid),
        INDEX idx_seniorhouse_designation (designation),
        INDEX idx_seniorhouse_status (status)
    )");

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblstudenthouse (
        assignmentid VARCHAR(40) NOT NULL PRIMARY KEY,
        userid VARCHAR(30) NOT NULL,
        houseid VARCHAR(40) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        datetimeentry DATETIME NOT NULL,
        recordedby VARCHAR(30) NOT NULL,
        INDEX idx_studenthouse_user (userid),
        INDEX idx_studenthouse_house (houseid),
        INDEX idx_studenthouse_status (status)
    )");

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblexeatrequest (
        exeatid VARCHAR(40) NOT NULL PRIMARY KEY,
        userid VARCHAR(30) NOT NULL,
        houseid VARCHAR(40) NOT NULL,
        exeattype VARCHAR(20) NOT NULL DEFAULT 'external',
        reason VARCHAR(255) NOT NULL,
        dateout DATE NOT NULL,
        timeout TIME NULL,
        datereturn DATE NULL,
        timereturn TIME NULL,
        actualreturndatetime DATETIME NULL,
        returnedby VARCHAR(30) NULL,
        returnnote VARCHAR(255) NULL,
        requestedatetime DATETIME NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        decisionnote VARCHAR(255) NULL,
        decisionby VARCHAR(30) NULL,
        decisiondatetime DATETIME NULL,
        recordedby VARCHAR(30) NOT NULL,
        INDEX idx_exeat_student (userid),
        INDEX idx_exeat_house (houseid),
        INDEX idx_exeat_type (exeattype),
        INDEX idx_exeat_status (status),
        INDEX idx_exeat_requested (requestedatetime)
    )");

    $timeoutCol = mysqli_query($con, "SHOW COLUMNS FROM tblexeatrequest LIKE 'timeout'");
    if(!$timeoutCol || mysqli_num_rows($timeoutCol) === 0){
        mysqli_query($con, "ALTER TABLE tblexeatrequest ADD COLUMN timeout TIME NULL AFTER dateout");
    }
    $dateReturnCol = mysqli_query($con, "SHOW COLUMNS FROM tblexeatrequest LIKE 'datereturn'");
    if(!$dateReturnCol || mysqli_num_rows($dateReturnCol) === 0){
        mysqli_query($con, "ALTER TABLE tblexeatrequest ADD COLUMN datereturn DATE NULL AFTER timeout");
    }
    $exeatTypeCol = mysqli_query($con, "SHOW COLUMNS FROM tblexeatrequest LIKE 'exeattype'");
    if(!$exeatTypeCol || mysqli_num_rows($exeatTypeCol) === 0){
        mysqli_query($con, "ALTER TABLE tblexeatrequest ADD COLUMN exeattype VARCHAR(20) NOT NULL DEFAULT 'external' AFTER houseid");
        mysqli_query($con, "CREATE INDEX idx_exeat_type ON tblexeatrequest(exeattype)");
    }
    $actualReturnCol = mysqli_query($con, "SHOW COLUMNS FROM tblexeatrequest LIKE 'actualreturndatetime'");
    if(!$actualReturnCol || mysqli_num_rows($actualReturnCol) === 0){
        mysqli_query($con, "ALTER TABLE tblexeatrequest ADD COLUMN actualreturndatetime DATETIME NULL AFTER timereturn");
    }
    $returnedByCol = mysqli_query($con, "SHOW COLUMNS FROM tblexeatrequest LIKE 'returnedby'");
    if(!$returnedByCol || mysqli_num_rows($returnedByCol) === 0){
        mysqli_query($con, "ALTER TABLE tblexeatrequest ADD COLUMN returnedby VARCHAR(30) NULL AFTER actualreturndatetime");
    }
    $returnNoteCol = mysqli_query($con, "SHOW COLUMNS FROM tblexeatrequest LIKE 'returnnote'");
    if(!$returnNoteCol || mysqli_num_rows($returnNoteCol) === 0){
        mysqli_query($con, "ALTER TABLE tblexeatrequest ADD COLUMN returnnote VARCHAR(255) NULL AFTER returnedby");
    }
    $houseGenderCol = mysqli_query($con, "SHOW COLUMNS FROM tblhouse LIKE 'housegender'");
    if(!$houseGenderCol || mysqli_num_rows($houseGenderCol) === 0){
        mysqli_query($con, "ALTER TABLE tblhouse ADD COLUMN housegender VARCHAR(20) NULL AFTER description");
    }
    $houseResidenceCol = mysqli_query($con, "SHOW COLUMNS FROM tblhouse LIKE 'houseresidencetype'");
    if(!$houseResidenceCol || mysqli_num_rows($houseResidenceCol) === 0){
        mysqli_query($con, "ALTER TABLE tblhouse ADD COLUMN houseresidencetype VARCHAR(20) NULL AFTER housegender");
    }
    $houseAutoAssignCol = mysqli_query($con, "SHOW COLUMNS FROM tblhouse LIKE 'autoassignenabled'");
    if(!$houseAutoAssignCol || mysqli_num_rows($houseAutoAssignCol) === 0){
        mysqli_query($con, "ALTER TABLE tblhouse ADD COLUMN autoassignenabled TINYINT(1) NOT NULL DEFAULT 1 AFTER houseresidencetype");
    }
    house_master_sync_house_profiles($con);
    xschool_schema_cache_mark('schema_house_master_v2');
}
}

if(!function_exists('house_master_normalize_senior_designation')){
function house_master_normalize_senior_designation($designation){
    $designation = trim((string)$designation);
    if($designation === "Senior House Mistress"){
        return "Senior House Mistress";
    }
    return "Senior House Master";
}
}

if(!function_exists('house_master_normalize_gender_label')){
function house_master_normalize_gender_label($gender){
    $gender = strtoupper(trim((string)$gender));
    if(in_array($gender, array("F", "FEMALE", "GIRL", "WOMAN"), true)){
        return "Female";
    }
    if(in_array($gender, array("M", "MALE", "BOY", "MAN"), true)){
        return "Male";
    }
    return "";
}
}

if(!function_exists('house_master_normalize_residence_label')){
function house_master_normalize_residence_label($residence){
    $residence = strtoupper(trim((string)$residence));
    if(in_array($residence, array("BOARDING", "BOARDER", "HOSTEL"), true)){
        return "Boarding";
    }
    if(in_array($residence, array("DAY", "DAY STUDENT", "DAY STUDENTS"), true)){
        return "Day";
    }
    return "";
}
}

if(!function_exists('house_master_guess_house_profile')){
function house_master_guess_house_profile($houseName, $description = ""){
    $source = strtoupper(trim((string)$houseName." ".(string)$description));
    $gender = (strpos($source, "GIRLS") !== false || strpos($source, "FEMALE") !== false)
        ? "Female"
        : "Male";
    $residence = (strpos($source, "DAY") !== false || strpos($source, "HOUSE 5") !== false || strpos($source, "HOUSE FIVE") !== false)
        ? "Day"
        : "Boarding";
    return array(
        "housegender" => $gender,
        "houseresidencetype" => $residence,
        "autoassignenabled" => 1
    );
}
}

if(!function_exists('house_master_sync_house_profiles')){
function house_master_sync_house_profiles($con){
    $res = mysqli_query($con, "SELECT houseid, housename, description, housegender, houseresidencetype, autoassignenabled FROM tblhouse");
    if(!$res){
        return;
    }
    while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
        $updates = array();
        $guessed = house_master_guess_house_profile($row["housename"], $row["description"]);
        $houseIdEsc = mysqli_real_escape_string($con, (string)$row["houseid"]);

        $currentGender = house_master_normalize_gender_label(isset($row["housegender"]) ? $row["housegender"] : "");
        if($currentGender === "" && $guessed["housegender"] !== ""){
            $genderEsc = mysqli_real_escape_string($con, $guessed["housegender"]);
            $updates[] = "housegender='$genderEsc'";
        }

        $currentResidence = house_master_normalize_residence_label(isset($row["houseresidencetype"]) ? $row["houseresidencetype"] : "");
        if($currentResidence === "" && $guessed["houseresidencetype"] !== ""){
            $residenceEsc = mysqli_real_escape_string($con, $guessed["houseresidencetype"]);
            $updates[] = "houseresidencetype='$residenceEsc'";
        }

        if(!isset($row["autoassignenabled"]) || $row["autoassignenabled"] === null || $row["autoassignenabled"] === ""){
            $updates[] = "autoassignenabled=1";
        }

        if(!empty($updates)){
            mysqli_query($con, "UPDATE tblhouse SET ".implode(", ", $updates)." WHERE houseid='$houseIdEsc' LIMIT 1");
        }
    }
}
}

if(!function_exists('house_master_house_profile_matches')){
function house_master_house_profile_matches($house, $gender, $residence){
    if(!is_array($house) || empty($house)){
        return false;
    }
    $gender = house_master_normalize_gender_label($gender);
    $residence = house_master_normalize_residence_label($residence);
    $storedGender = house_master_normalize_gender_label(isset($house["housegender"]) ? $house["housegender"] : "");
    $storedResidence = house_master_normalize_residence_label(isset($house["houseresidencetype"]) ? $house["houseresidencetype"] : "");
    $guessedProfile = house_master_guess_house_profile(
        isset($house["housename"]) ? $house["housename"] : "",
        isset($house["description"]) ? $house["description"] : ""
    );
    $guessedGender = house_master_normalize_gender_label($guessedProfile["housegender"]);
    $guessedResidence = house_master_normalize_residence_label($guessedProfile["houseresidencetype"]);

    $storedMatches = ($storedGender !== "" && $storedResidence !== "" && $storedGender === $gender && $storedResidence === $residence);
    $guessedMatches = ($guessedGender !== "" && $guessedResidence !== "" && $guessedGender === $gender && $guessedResidence === $residence);

    return ($storedMatches || $guessedMatches) &&
        strtolower(trim((string)(isset($house["status"]) ? $house["status"] : ""))) === "active" &&
        (!isset($house["autoassignenabled"]) || (int)$house["autoassignenabled"] === 1);
}
}

if(!function_exists('house_master_dashboard_label')){
function house_master_dashboard_label($con, $teacherId){
    $summary = house_master_get_teacher_role_summary($con, $teacherId);
    return $summary["dashboard_label"];
}
}

if(!function_exists('house_master_get_teacher_role_summary')){
function house_master_get_teacher_role_summary($con, $teacherId){
    static $summaryCache = array();
    $teacherId = trim((string)$teacherId);
    if($teacherId === ""){
        return array(
            "dashboard_label" => "House Master Dashboard",
            "has_house_assignment" => false,
            "has_senior_assignment" => false
        );
    }
    if(isset($summaryCache[$teacherId])){
        return $summaryCache[$teacherId];
    }

    $teacherIdEsc = mysqli_real_escape_string($con, $teacherId);
    $summary = array(
        "dashboard_label" => "House Master Dashboard",
        "has_house_assignment" => false,
        "has_senior_assignment" => false
    );

    $res = mysqli_query($con, "SELECT
            su.gender,
            EXISTS(
                SELECT 1 FROM tblhousemaster hm
                WHERE hm.userid='$teacherIdEsc' AND hm.status='active'
                LIMIT 1
            ) AS has_house_assignment,
            EXISTS(
                SELECT 1 FROM tblseniorhouseauthority sha
                WHERE sha.userid='$teacherIdEsc' AND sha.status='active'
                LIMIT 1
            ) AS has_senior_assignment
        FROM tblsystemuser su
        WHERE su.userid='$teacherIdEsc'
        LIMIT 1");
    if($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
        $summary["has_house_assignment"] = (int)$row["has_house_assignment"] === 1;
        $summary["has_senior_assignment"] = (int)$row["has_senior_assignment"] === 1;
        if(house_master_normalize_gender_label($row["gender"]) === "Female"){
            $summary["dashboard_label"] = "House Mistress Dashboard";
        }
    }

    $summaryCache[$teacherId] = $summary;
    return $summary;
}
}

if(!function_exists('assign_student_to_house')){
function assign_student_to_house($con, $studentId, $houseId, $recordedBy){
    $studentId = mysqli_real_escape_string($con, (string)$studentId);
    $houseId = mysqli_real_escape_string($con, (string)$houseId);
    $recordedBy = mysqli_real_escape_string($con, (string)$recordedBy);
    if($studentId === "" || $houseId === ""){
        return false;
    }

    mysqli_query($con, "UPDATE tblstudenthouse SET status='inactive' WHERE userid='$studentId' AND status='active'");
    include("code.php");
    $assignmentId = $code;
    return mysqli_query($con, "INSERT INTO tblstudenthouse(assignmentid,userid,houseid,status,datetimeentry,recordedby)
        VALUES('$assignmentId','$studentId','$houseId','active',NOW(),'$recordedBy')");
}
}

if(!function_exists('get_student_active_house')){
function get_student_active_house($con, $studentId){
    $studentId = mysqli_real_escape_string($con, (string)$studentId);
    $sql = "SELECT sh.houseid,h.housename
            FROM tblstudenthouse sh
            INNER JOIN tblhouse h ON h.houseid=sh.houseid
            WHERE sh.userid='$studentId' AND sh.status='active' AND h.status='active'
            ORDER BY sh.datetimeentry DESC LIMIT 1";
    $res = mysqli_query($con, $sql);
    if($res && $row=mysqli_fetch_array($res, MYSQLI_ASSOC)){
        return $row;
    }
    return null;
}
}

if(!function_exists('house_master_can_manage_student')){
function house_master_can_manage_student($con, $teacherId, $studentId){
    $teacherId = mysqli_real_escape_string($con, (string)$teacherId);
    $studentId = mysqli_real_escape_string($con, (string)$studentId);
    $sql = "SELECT hm.assignmentid
            FROM tblstudenthouse sh
            INNER JOIN tblhousemaster hm ON hm.houseid=sh.houseid
            WHERE sh.userid='$studentId'
              AND sh.status='active'
              AND hm.userid='$teacherId'
              AND hm.status='active'
            LIMIT 1";
    $res = mysqli_query($con, $sql);
    return ($res && mysqli_num_rows($res) > 0);
}
}

if(!function_exists('get_teacher_house_filter_sql')){
function get_teacher_house_filter_sql($con, $teacherId){
    $teacherId = mysqli_real_escape_string($con, (string)$teacherId);
    return "SELECT houseid FROM tblhousemaster WHERE userid='$teacherId' AND status='active'";
}
}

if(!function_exists('house_master_has_assignment')){
function house_master_has_assignment($con, $teacherId){
    $summary = house_master_get_teacher_role_summary($con, $teacherId);
    return !empty($summary["has_house_assignment"]);
}
}

if(!function_exists('house_master_has_senior_assignment')){
function house_master_has_senior_assignment($con, $teacherId){
    $summary = house_master_get_teacher_role_summary($con, $teacherId);
    return !empty($summary["has_senior_assignment"]);
}
}

if(!function_exists('house_master_can_manage_exeat')){
function house_master_can_manage_exeat($con, $teacherId){
    return house_master_has_assignment($con, $teacherId) || house_master_has_senior_assignment($con, $teacherId);
}
}

if(!function_exists('house_master_is_senior_for_exeat')){
function house_master_is_senior_for_exeat($con, $teacherId){
    return house_master_has_senior_assignment($con, $teacherId);
}
}

if(!function_exists('house_master_exeat_scope_sql')){
function house_master_exeat_scope_sql($con, $teacherId, $houseColumn = 'houseid'){
    $teacherId = mysqli_real_escape_string($con, (string)$teacherId);
    $houseColumn = trim((string)$houseColumn);
    if($houseColumn === ''){
        $houseColumn = 'houseid';
    }
    if(house_master_has_senior_assignment($con, $teacherId)){
        return "1=1";
    }
    return $houseColumn." IN (SELECT houseid FROM tblhousemaster WHERE userid='".$teacherId."' AND status='active')";
}
}

if(!function_exists('house_master_exeat_scope_label')){
function house_master_exeat_scope_label($con, $teacherId){
    return house_master_has_senior_assignment($con, $teacherId) ? "all houses" : "your assigned house(s)";
}
}

if(!function_exists('house_master_exeat_expected_return_sql')){
function house_master_exeat_expected_return_sql($alias = 'er'){
    $alias = trim((string)$alias);
    if($alias === ''){
        $alias = 'er';
    }
    return "STR_TO_DATE(CONCAT(".$alias.".datereturn, ' ', COALESCE(".$alias.".timereturn,'00:00:00')), '%Y-%m-%d %H:%i:%s')";
}
}

if(!function_exists('house_master_exeat_overdue_sql')){
function house_master_exeat_overdue_sql($alias = 'er'){
    $alias = trim((string)$alias);
    if($alias === ''){
        $alias = 'er';
    }
    return $alias.".status='approved' AND ".$alias.".actualreturndatetime IS NULL AND ".$alias.".datereturn IS NOT NULL AND ".house_master_exeat_expected_return_sql($alias)." < NOW()";
}
}

if(!function_exists('get_senior_house_assignment')){
function get_senior_house_assignment($con, $teacherId){
    $teacherId = mysqli_real_escape_string($con, (string)$teacherId);
    $res = mysqli_query($con, "SELECT assignmentid,designation,datetimeentry
        FROM tblseniorhouseauthority
        WHERE userid='$teacherId' AND status='active'
        ORDER BY datetimeentry DESC
        LIMIT 1");
    if($res && $row=mysqli_fetch_array($res, MYSQLI_ASSOC)){
        return $row;
    }
    return null;
}
}

if(!function_exists('notify_house_master_assignment')){
function notify_house_master_assignment($con, $teacherId, $houseName, $assignedBy, $action = "assigned"){
    $teacherId = mysqli_real_escape_string($con, (string)$teacherId);
    $houseName = mysqli_real_escape_string($con, (string)$houseName);
    $assignedBy = mysqli_real_escape_string($con, (string)$assignedBy);
    $action = strtolower(trim((string)$action)) === "updated" ? "updated" : "assigned";

    $msgId = strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 30));
    $message = ($action === "updated")
        ? "You are now updated as House Master for ".$houseName.". Please check house students and exeat requests."
        : "You have been assigned as House Master for ".$houseName.". Please check house students and exeat requests.";
    $message = mysqli_real_escape_string($con, $message);

    mysqli_query($con, "INSERT INTO tblmessages(messageid,messages,datetimeentry,status,sentby)
        VALUES('$msgId','$message',NOW(),'active','$assignedBy')");
}
}

if(!function_exists('notify_senior_house_assignment')){
function notify_senior_house_assignment($con, $teacherId, $designation, $assignedBy, $action = "assigned"){
    $teacherId = mysqli_real_escape_string($con, (string)$teacherId);
    $designation = mysqli_real_escape_string($con, house_master_normalize_senior_designation($designation));
    $assignedBy = mysqli_real_escape_string($con, (string)$assignedBy);
    $action = strtolower(trim((string)$action)) === "updated" ? "updated" : "assigned";

    $msgId = strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 30));
    $message = ($action === "updated")
        ? "Your senior house role has been updated. You are now ".$designation.". Please monitor the senior house dashboard."
        : "You have been assigned as ".$designation.". Please monitor the senior house dashboard.";
    $message = mysqli_real_escape_string($con, $message);

    mysqli_query($con, "INSERT INTO tblmessages(messageid,messages,datetimeentry,status,sentby)
        VALUES('$msgId','$message',NOW(),'active','$assignedBy')");
}
}

if(!function_exists('send_bulk_sms_message')){
function send_bulk_sms_message($phone, $message, &$resultCode = null){
    $phone = preg_replace('/[^0-9\+]/', '', (string)$phone);
    $message = trim((string)$message);
    if($phone === "" || $message === ""){
        $resultCode = "INVALID_INPUT";
        return false;
    }

    $key = "e7c782f1f1c83d0f373c";
    $senderId = "AYISEC";
    $msg = urlencode($message);
    $url = "http://clientlogin.bulksmsgh.com/smsapi?key={$key}&to={$phone}&msg={$msg}&sender_id={$senderId}";

    $response = false;
    if(function_exists('curl_init')){
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        $response = curl_exec($ch);
        if($response === false){
            $resultCode = trim((string)curl_error($ch)) !== '' ? trim((string)curl_error($ch)) : 'SMS_GATEWAY_TIMEOUT';
        }
        curl_close($ch);
    }
    if($response === false){
        $context = stream_context_create(array(
            'http' => array(
                'timeout' => 8,
                'ignore_errors' => true
            )
        ));
        $response = @file_get_contents($url, false, $context);
    }
    $raw = trim((string)$response);
    if($raw === ""){
        if(trim((string)$resultCode) === ""){
            $resultCode = "SMS_GATEWAY_TIMEOUT";
        }
        return false;
    }
    $resultCode = $raw;

    if($raw === "1000"){
        return true;
    }

    $json = json_decode($raw, true);
    if(is_array($json)){
        $apiCode = isset($json['code']) ? (string)$json['code'] : '';
        $apiSuccess = !empty($json['success']);
        $resultCode = ($apiCode !== '' ? $apiCode : $raw);
        if($apiSuccess && $apiCode === "1000"){
            return true;
        }
    }

    return false;
}
}

if(!function_exists('notify_house_masters_new_exeat')){
function notify_house_masters_new_exeat($con, $houseId, $studentName, $exeatType, $departureText, $returnText, &$summary = null){
    $houseIdEsc = mysqli_real_escape_string($con, (string)$houseId);
    $studentName = trim((string)$studentName);
    $exeatType = strtolower(trim((string)$exeatType));
    if($exeatType !== "internal"){ $exeatType = "external"; }
    $departureText = trim((string)$departureText);
    $returnText = trim((string)$returnText);

    $summary = array("sent" => 0, "failed" => 0, "no_phone" => 0, "total" => 0);
    $sql = "SELECT su.mobile, su.firstname, su.surname, su.othernames
            FROM tblhousemaster hm
            INNER JOIN tblsystemuser su ON su.userid=hm.userid
            WHERE hm.houseid='$houseIdEsc' AND hm.status='active' AND su.status='active'";
    $res = mysqli_query($con, $sql);
    if(!$res){
        return false;
    }

    while($row=mysqli_fetch_array($res, MYSQLI_ASSOC)){
        $summary["total"]++;
        $teacherPhone = trim((string)$row['mobile']);
        if($teacherPhone === ""){
            $summary["no_phone"]++;
            continue;
        }
        $msg = "New ".ucfirst($exeatType)." exeat request: ".$studentName.". Out: ".$departureText.". Return: ".$returnText.". Please review for approval.";
        $code = "";
        $ok = send_bulk_sms_message($teacherPhone, $msg, $code);
        if($ok){
            $summary["sent"]++;
        }else{
            $summary["failed"]++;
        }
    }
    return true;
}
}
?>
