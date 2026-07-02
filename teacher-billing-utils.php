<?php
if(!function_exists('xschool_schema_cache_is_fresh')){
function xschool_schema_cache_is_fresh($key, $ttlSeconds = 900){
    static $memoryCache = array();
    $key = trim((string)$key);
    if($key === ''){
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
    if($key === ''){
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

include_once("user-management-utils.php");
include_once("class-teacher-utils.php");

if(!function_exists('teacher_billing_is_admin')){
function teacher_billing_is_admin(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === 'administrator' &&
        in_array($_SESSION['SYSTEMTYPE'], array('normal_user', 'super_user'), true);
}
}

if(!function_exists('teacher_billing_is_teacher')){
function teacher_billing_is_teacher(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === 'user' &&
        $_SESSION['SYSTEMTYPE'] === 'Teacher';
}
}

if(!function_exists('teacher_billing_landing_page')){
function teacher_billing_landing_page(){
    if(teacher_billing_is_admin()){
        return ($_SESSION['SYSTEMTYPE'] === 'super_user') ? 'super.php' : 'admin.php';
    }
    if(teacher_billing_is_teacher()){
        return 'teacher-page.php';
    }
    if(isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) && $_SESSION['ACCESSLEVEL'] === 'user' && $_SESSION['SYSTEMTYPE'] === 'Student'){
        return 'student-page.php';
    }
    return function_exists('um_home_link_for_session') ? um_home_link_for_session() : 'index.php';
}
}

if(!function_exists('ensure_teacher_billing_table')){
function ensure_teacher_billing_table($con){
    if(!$con){
        return;
    }
    if(xschool_schema_cache_is_fresh('schema_tblteacherbillingassignment_v1')){
        return;
    }
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblteacherbillingassignment (
        assignmentid VARCHAR(40) NOT NULL PRIMARY KEY,
        userid VARCHAR(30) NOT NULL,
        classid VARCHAR(30) NOT NULL,
        batchid VARCHAR(30) NOT NULL,
        termname INT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        datetimeentry DATETIME NOT NULL,
        recordedby VARCHAR(30) NOT NULL,
        INDEX idx_teacher (userid),
        INDEX idx_scope (classid,batchid,termname),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    xschool_schema_cache_mark('schema_tblteacherbillingassignment_v1');
}
}

if(!function_exists('ensure_teacher_billing_item_table')){
function ensure_teacher_billing_item_table($con){
    if(!$con){
        return;
    }
    if(xschool_schema_cache_is_fresh('schema_tblteacherbillingassignmentitem_v1')){
        return;
    }
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblteacherbillingassignmentitem (
        rowid BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        assignmentid VARCHAR(40) NOT NULL,
        itempriceid VARCHAR(40) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        datetimeentry DATETIME NOT NULL,
        recordedby VARCHAR(30) NOT NULL,
        UNIQUE KEY uq_assignment_item (assignmentid,itempriceid),
        KEY idx_assignment (assignmentid),
        KEY idx_itemprice (itempriceid),
        KEY idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    xschool_schema_cache_mark('schema_tblteacherbillingassignmentitem_v1');
}
}

if(!function_exists('teacher_billing_can_manage_assignments')){
function teacher_billing_can_manage_assignments(){
    return teacher_billing_is_admin();
}
}

if(!function_exists('teacher_billing_teacher_has_module')){
function teacher_billing_teacher_has_module($con, $teacherId){
    $teacherId = trim((string)$teacherId);
    if($teacherId === '' || !$con){
        return false;
    }
    if(teacher_billing_is_admin()){
        return true;
    }
    $userRow = function_exists('um_fetch_user_row') ? um_fetch_user_row($con, $teacherId) : null;
    if(!$userRow){
        return false;
    }
    return function_exists('um_user_has_module') ? um_user_has_module($con, 'billing', $userRow) : false;
}
}

if(!function_exists('teacher_billing_has_any_assignment')){
function teacher_billing_has_any_assignment($con, $teacherId){
    ensure_teacher_billing_table($con);
    $teacherIdEsc = mysqli_real_escape_string($con, trim((string)$teacherId));
    if($teacherIdEsc === ''){
        return false;
    }
    $sql = "SELECT assignmentid FROM tblteacherbillingassignment
        WHERE userid='$teacherIdEsc' AND status='active'
        LIMIT 1";
    $res = mysqli_query($con, $sql);
    if($res && mysqli_num_rows($res) > 0){
        return true;
    }
    return false;
}
}

if(!function_exists('teacher_billing_is_assigned')){
function teacher_billing_is_assigned($con, $teacherId, $classId, $batchId, $termName){
    ensure_teacher_billing_table($con);
    $teacherIdEsc = mysqli_real_escape_string($con, trim((string)$teacherId));
    $classIdEsc = mysqli_real_escape_string($con, trim((string)$classId));
    $batchIdEsc = mysqli_real_escape_string($con, trim((string)$batchId));
    $termName = (int)$termName;
    if($teacherIdEsc === '' || $classIdEsc === '' || $batchIdEsc === '' || $termName <= 0){
        return false;
    }
    $sql = "SELECT assignmentid FROM tblteacherbillingassignment
        WHERE userid='$teacherIdEsc'
          AND classid='$classIdEsc'
          AND batchid='$batchIdEsc'
          AND termname='$termName'
          AND status='active'
        LIMIT 1";
    $res = mysqli_query($con, $sql);
    if($res && mysqli_num_rows($res) > 0){
        return true;
    }
    return false;
}
}

if(!function_exists('teacher_billing_is_assigned_pair')){
function teacher_billing_is_assigned_pair($con, $teacherId, $classId, $batchId){
    ensure_teacher_billing_table($con);
    $teacherIdEsc = mysqli_real_escape_string($con, trim((string)$teacherId));
    $classIdEsc = mysqli_real_escape_string($con, trim((string)$classId));
    $batchIdEsc = mysqli_real_escape_string($con, trim((string)$batchId));
    if($teacherIdEsc === '' || $classIdEsc === '' || $batchIdEsc === ''){
        return false;
    }
    $sql = "SELECT assignmentid FROM tblteacherbillingassignment
        WHERE userid='$teacherIdEsc'
          AND classid='$classIdEsc'
          AND batchid='$batchIdEsc'
          AND status='active'
        LIMIT 1";
    $res = mysqli_query($con, $sql);
    if($res && mysqli_num_rows($res) > 0){
        return true;
    }
    return false;
}
}

if(!function_exists('teacher_billing_terms_for_pair')){
function teacher_billing_terms_for_pair($con, $teacherId, $classId, $batchId){
    ensure_teacher_billing_table($con);
    $teacherIdEsc = mysqli_real_escape_string($con, trim((string)$teacherId));
    $classIdEsc = mysqli_real_escape_string($con, trim((string)$classId));
    $batchIdEsc = mysqli_real_escape_string($con, trim((string)$batchId));
    $terms = array();
    if($teacherIdEsc === '' || $classIdEsc === '' || $batchIdEsc === ''){
        return $terms;
    }
    $sql = "SELECT DISTINCT termname
        FROM tblteacherbillingassignment
        WHERE userid='$teacherIdEsc'
          AND classid='$classIdEsc'
          AND batchid='$batchIdEsc'
          AND status='active'
        ORDER BY termname ASC";
    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $termName = (int)$row['termname'];
            if($termName > 0){
                $terms[$termName] = $termName;
            }
        }
    }
    return array_values($terms);
}
}

if(!function_exists('teacher_billing_current_user_can_use_pages')){
function teacher_billing_current_user_can_use_pages($con){
    if(teacher_billing_is_admin()){
        return true;
    }
    if(!teacher_billing_is_teacher()){
        return false;
    }
    $teacherId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
    if($teacherId === '' || !teacher_billing_teacher_has_module($con, $teacherId)){
        return false;
    }
    $scriptName = isset($_SERVER['PHP_SELF']) ? basename((string)$_SERVER['PHP_SELF']) : '';
    return in_array($scriptName, array('payments.php', 'student-billing.php', 'print-student-bills.php'), true);
}
}

if(!function_exists('teacher_billing_enforce_page_access')){
function teacher_billing_enforce_page_access($con){
    ensure_teacher_billing_table($con);
    if(teacher_billing_current_user_can_use_pages($con)){
        return true;
    }

    if(teacher_billing_is_teacher()){
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white;padding:8px;'>You do not have access to the billing module.</div>";
    }

    header("location:".teacher_billing_landing_page());
    exit();
}
}

if(!function_exists('teacher_billing_allowed_scope_sql')){
function teacher_billing_allowed_scope_sql($con, $teacherId, $classField, $batchField, $termField = ''){
    ensure_teacher_billing_table($con);
    if(teacher_billing_is_admin()){
        return '1=1';
    }
    $teacherIdEsc = mysqli_real_escape_string($con, trim((string)$teacherId));
    $classField = trim((string)$classField);
    $batchField = trim((string)$batchField);
    $termField = trim((string)$termField);
    if($teacherIdEsc === '' || $classField === '' || $batchField === ''){
        return '1=0';
    }
    $sql = "(EXISTS (
        SELECT 1
        FROM tblteacherbillingassignment tba
        WHERE tba.userid='$teacherIdEsc'
          AND tba.status='active'
          AND tba.classid=$classField
          AND tba.batchid=$batchField";
    if($termField !== ''){
        $sql .= " AND tba.termname=$termField";
    }
    $sql .= "))";
    return $sql;
}
}

if(!function_exists('teacher_billing_enforce_scope_or_redirect')){
function teacher_billing_enforce_scope_or_redirect($con, $classId, $batchId, $termName = null){
    if(teacher_billing_is_admin()){
        return true;
    }
    $teacherId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
    $termValue = ($termName === null ? 0 : (int)$termName);
    if($classId === '' || $batchId === '' || $termValue <= 0 || !teacher_billing_is_assigned($con, $teacherId, $classId, $batchId, $termValue)){
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white;padding:8px;'>You are not assigned billing access for that class, batch, and semester.</div>";
        header("location:".teacher_billing_landing_page());
        exit();
    }
    return true;
}
}

if(!function_exists('teacher_billing_fetch_assignments')){
function teacher_billing_fetch_assignments($con, $teacherId){
    ensure_teacher_billing_table($con);
    ensure_teacher_billing_item_table($con);
    $teacherIdEsc = mysqli_real_escape_string($con, trim((string)$teacherId));
    $rows = array();
    if($teacherIdEsc === ''){
        return $rows;
    }
    $sql = "SELECT tba.*, ce.class_name, bh.batch,
        (
            SELECT COUNT(*)
            FROM tblteacherbillingassignmentitem tbai
            WHERE tbai.assignmentid=tba.assignmentid
              AND tbai.status='active'
        ) AS selected_item_count
        FROM tblteacherbillingassignment tba
        INNER JOIN tblclassentry ce ON ce.class_entryid=tba.classid
        INNER JOIN tblbatch bh ON bh.batchid=tba.batchid
        WHERE tba.userid='$teacherIdEsc' AND tba.status='active'
        ORDER BY bh.datetimeentry DESC, tba.termname DESC, ce.class_name ASC";
    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if(!function_exists('teacher_billing_class_options')){
function teacher_billing_class_options($con){
    $rows = array();
    if(teacher_billing_is_admin()){
        $res = mysqli_query($con, "SELECT class_entryid, class_name FROM tblclassentry ORDER BY class_name ASC");
        if($res){
            while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
                $rows[] = $row;
            }
        }
        return $rows;
    }

    $teacherId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
    $teacherIdEsc = mysqli_real_escape_string($con, $teacherId);
    $res = mysqli_query($con, "SELECT DISTINCT ce.class_entryid, ce.class_name
        FROM tblteacherbillingassignment tba
        INNER JOIN tblclassentry ce ON ce.class_entryid=tba.classid
        WHERE tba.userid='$teacherIdEsc' AND tba.status='active'
        ORDER BY ce.class_name ASC");
    if($res){
            while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
                $rows[] = $row;
            }
        }
    return $rows;
}
}

if(!function_exists('teacher_billing_batch_options')){
function teacher_billing_batch_options($con){
    $rows = array();
    if(teacher_billing_is_admin()){
        $res = mysqli_query($con, "SELECT batchid, batch FROM tblbatch ORDER BY datetimeentry DESC");
        if($res){
            while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
                $rows[] = $row;
            }
        }
        return $rows;
    }

    $teacherId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
    $teacherIdEsc = mysqli_real_escape_string($con, $teacherId);
    $res = mysqli_query($con, "SELECT DISTINCT bh.batchid, bh.batch, bh.datetimeentry
        FROM tblteacherbillingassignment tba
        INNER JOIN tblbatch bh ON bh.batchid=tba.batchid
        WHERE tba.userid='$teacherIdEsc' AND tba.status='active'
        ORDER BY bh.datetimeentry DESC");
    if($res){
            while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
                $rows[] = $row;
            }
        }
    return $rows;
}
}

if(!function_exists('teacher_billing_assignment_row')){
function teacher_billing_assignment_row($con, $assignmentId){
    ensure_teacher_billing_table($con);
    ensure_teacher_billing_item_table($con);
    $assignmentIdEsc = mysqli_real_escape_string($con, trim((string)$assignmentId));
    if($assignmentIdEsc === ''){
        return null;
    }
    $sql = "SELECT tba.*, ce.class_name, bh.batch, su.firstname, su.othernames, su.surname
        FROM tblteacherbillingassignment tba
        INNER JOIN tblclassentry ce ON ce.class_entryid=tba.classid
        INNER JOIN tblbatch bh ON bh.batchid=tba.batchid
        INNER JOIN tblsystemuser su ON su.userid=tba.userid
        WHERE tba.assignmentid='$assignmentIdEsc'
        LIMIT 1";
    $res = mysqli_query($con, $sql);
    if($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
        return $row;
    }
    return null;
}
}

if(!function_exists('teacher_billing_assignment_item_rows')){
function teacher_billing_assignment_item_rows($con, $assignmentId){
    ensure_teacher_billing_item_table($con);
    $rows = array();
    $assignmentIdEsc = mysqli_real_escape_string($con, trim((string)$assignmentId));
    if($assignmentIdEsc === ''){
        return $rows;
    }
    $sql = "SELECT tbai.*, ip.class_entryid, ip.batch, ip.term, ip.price, itm.itemname
        FROM tblteacherbillingassignmentitem tbai
        INNER JOIN tblitemprice ip ON ip.itempriceid=tbai.itempriceid
        INNER JOIN tblitem itm ON itm.itemid=ip.itemid
        WHERE tbai.assignmentid='$assignmentIdEsc' AND tbai.status='active'
        ORDER BY itm.itemname ASC";
    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if(!function_exists('teacher_billing_scope_itemprice_rows')){
function teacher_billing_scope_itemprice_rows($con, $classId, $batchId, $termName){
    $rows = array();
    $classIdEsc = mysqli_real_escape_string($con, trim((string)$classId));
    $batchIdEsc = mysqli_real_escape_string($con, trim((string)$batchId));
    $termName = (int)$termName;
    if($classIdEsc === '' || $batchIdEsc === '' || $termName <= 0){
        return $rows;
    }
    $sql = "SELECT ip.*, itm.itemname
        FROM tblitemprice ip
        INNER JOIN tblitem itm ON itm.itemid=ip.itemid
        WHERE ip.class_entryid='$classIdEsc'
          AND ip.batch='$batchIdEsc'
          AND ip.term='$termName'
          AND ip.status='active'
          AND itm.status='active'
        ORDER BY itm.itemname ASC";
    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if(!function_exists('teacher_billing_assignment_replace_items')){
function teacher_billing_assignment_replace_items($con, $assignmentId, $itemPriceIds, $recordedBy){
    ensure_teacher_billing_item_table($con);
    $assignmentIdEsc = mysqli_real_escape_string($con, trim((string)$assignmentId));
    if($assignmentIdEsc === ''){
        return false;
    }
    mysqli_query($con, "DELETE FROM tblteacherbillingassignmentitem WHERE assignmentid='$assignmentIdEsc'");
    $recordedByEsc = mysqli_real_escape_string($con, trim((string)$recordedBy));
    $inserted = true;
    $seen = array();
    foreach((array)$itemPriceIds as $itemPriceId){
        $itemPriceId = trim((string)$itemPriceId);
        if($itemPriceId === '' || isset($seen[$itemPriceId])){
            continue;
        }
        $seen[$itemPriceId] = true;
        $itemPriceIdEsc = mysqli_real_escape_string($con, $itemPriceId);
        $sql = "INSERT INTO tblteacherbillingassignmentitem(assignmentid,itempriceid,status,datetimeentry,recordedby)
            VALUES('$assignmentIdEsc','$itemPriceIdEsc','active',NOW(),'$recordedByEsc')";
        if(!mysqli_query($con, $sql)){
            $inserted = false;
        }
    }
    return $inserted;
}
}

if(!function_exists('teacher_billing_allowed_itemprice_ids')){
function teacher_billing_allowed_itemprice_ids($con, $teacherId, $classId, $batchId, $termName){
    ensure_teacher_billing_table($con);
    ensure_teacher_billing_item_table($con);
    $teacherIdEsc = mysqli_real_escape_string($con, trim((string)$teacherId));
    $classIdEsc = mysqli_real_escape_string($con, trim((string)$classId));
    $batchIdEsc = mysqli_real_escape_string($con, trim((string)$batchId));
    $termName = (int)$termName;
    $itemIds = array();
    if($teacherIdEsc === '' || $classIdEsc === '' || $batchIdEsc === '' || $termName <= 0){
        return $itemIds;
    }
    $sql = "SELECT DISTINCT tbai.itempriceid
        FROM tblteacherbillingassignment tba
        INNER JOIN tblteacherbillingassignmentitem tbai ON tbai.assignmentid=tba.assignmentid
        WHERE tba.userid='$teacherIdEsc'
          AND tba.classid='$classIdEsc'
          AND tba.batchid='$batchIdEsc'
          AND tba.termname='$termName'
          AND tba.status='active'
          AND tbai.status='active'";
    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $itemPriceId = trim((string)($row['itempriceid'] ?? ''));
            if($itemPriceId !== ''){
                $itemIds[$itemPriceId] = $itemPriceId;
            }
        }
    }
    return array_values($itemIds);
}
}

if(!function_exists('teacher_billing_scope_has_item_filter')){
function teacher_billing_scope_has_item_filter($con, $teacherId, $classId, $batchId, $termName){
    $allowed = teacher_billing_allowed_itemprice_ids($con, $teacherId, $classId, $batchId, $termName);
    return count($allowed) > 0;
}
}

if(!function_exists('teacher_billing_itemprice_is_allowed')){
function teacher_billing_itemprice_is_allowed($con, $teacherId, $classId, $batchId, $termName, $itemPriceId){
    $itemPriceId = trim((string)$itemPriceId);
    if($itemPriceId === ''){
        return false;
    }
    $allowed = teacher_billing_allowed_itemprice_ids($con, $teacherId, $classId, $batchId, $termName);
    if(empty($allowed)){
        return true;
    }
    return in_array($itemPriceId, $allowed, true);
}
}

if(!function_exists('teacher_billing_generate_bill_id')){
function teacher_billing_generate_bill_id(){
    $code = '';
    @include(__DIR__.DIRECTORY_SEPARATOR.'code.php');
    if(isset($GLOBALS['code']) && trim((string)$GLOBALS['code']) !== ''){
        $code = trim((string)$GLOBALS['code']);
    }elseif(isset($code) && trim((string)$code) !== ''){
        $code = trim((string)$code);
    }
    if($code === ''){
        $code = 'BILL'.date('YmdHis').mt_rand(100,999);
    }
    return $code;
}
}

if(!function_exists('teacher_billing_generate_transaction_id')){
function teacher_billing_generate_transaction_id(){
    $transactionId = '';
    @include(__DIR__.DIRECTORY_SEPARATOR.'code.php');
    if(isset($GLOBALS['transaction_id']) && trim((string)$GLOBALS['transaction_id']) !== ''){
        $transactionId = trim((string)$GLOBALS['transaction_id']);
    }elseif(isset($transaction_id) && trim((string)$transaction_id) !== ''){
        $transactionId = trim((string)$transaction_id);
    }
    if($transactionId === ''){
        $transactionId = 'TRX'.date('YmdHis').mt_rand(100,999);
    }
    return $transactionId;
}
}

if(!function_exists('teacher_billing_student_registered_for_scope')){
function teacher_billing_student_registered_for_scope($con, $studentId, $classId, $batchId, $termName){
    $studentIdEsc = mysqli_real_escape_string($con, trim((string)$studentId));
    $classIdEsc = mysqli_real_escape_string($con, trim((string)$classId));
    $batchIdEsc = mysqli_real_escape_string($con, trim((string)$batchId));
    $termName = (int)$termName;
    if($studentIdEsc === '' || $classIdEsc === '' || $batchIdEsc === '' || $termName <= 0){
        return false;
    }
    $sql = "SELECT userid
        FROM tbltermregistry
        WHERE userid='$studentIdEsc'
          AND class_entryid='$classIdEsc'
          AND batchid='$batchIdEsc'
          AND termname='$termName'
        LIMIT 1";
    $res = mysqli_query($con, $sql);
    return ($res && mysqli_num_rows($res) > 0);
}
}

if(!function_exists('teacher_billing_scope_itemprice_rows_for_user')){
function teacher_billing_scope_itemprice_rows_for_user($con, $teacherId, $classId, $batchId, $termName){
    $rows = teacher_billing_scope_itemprice_rows($con, $classId, $batchId, $termName);
    if(teacher_billing_is_admin()){
        return $rows;
    }
    $teacherId = trim((string)$teacherId);
    if($teacherId === ''){
        return $rows;
    }
    $allowedIds = teacher_billing_allowed_itemprice_ids($con, $teacherId, $classId, $batchId, $termName);
    if(empty($allowedIds)){
        return $rows;
    }
    $allowedMap = array();
    foreach($allowedIds as $allowedId){
        $allowedId = trim((string)$allowedId);
        if($allowedId !== ''){
            $allowedMap[$allowedId] = true;
        }
    }
    $filtered = array();
    foreach($rows as $row){
        $itemPriceId = trim((string)($row['itempriceid'] ?? ''));
        if($itemPriceId !== '' && isset($allowedMap[$itemPriceId])){
            $filtered[] = $row;
        }
    }
    return $filtered;
}
}

if(!function_exists('teacher_billing_billed_item_map')){
function teacher_billing_billed_item_map($con, $studentId, $itemPriceIds){
    $map = array();
    $studentIdEsc = mysqli_real_escape_string($con, trim((string)$studentId));
    if($studentIdEsc === ''){
        return $map;
    }
    $itemIdsSql = array();
    foreach((array)$itemPriceIds as $itemPriceId){
        $itemPriceId = trim((string)$itemPriceId);
        if($itemPriceId !== ''){
            $itemIdsSql[] = "'".mysqli_real_escape_string($con, $itemPriceId)."'";
        }
    }
    if(empty($itemIdsSql)){
        return $map;
    }
    $sql = "SELECT billid,itempriceid,datetimebilled,cost
        FROM tblbilling
        WHERE userid='$studentIdEsc'
          AND itempriceid IN (".implode(",", $itemIdsSql).")";
    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $itemPriceId = trim((string)($row['itempriceid'] ?? ''));
            if($itemPriceId !== ''){
                $map[$itemPriceId] = $row;
            }
        }
    }
    return $map;
}
}

if(!function_exists('teacher_billing_student_scope_summary')){
function teacher_billing_student_scope_summary($con, $studentId, $classId, $batchId, $termName, $teacherId = ''){
    $itemRows = teacher_billing_scope_itemprice_rows_for_user($con, $teacherId, $classId, $batchId, $termName);
    $itemIds = array();
    foreach($itemRows as $itemRow){
        $itemPriceId = trim((string)($itemRow['itempriceid'] ?? ''));
        if($itemPriceId !== ''){
            $itemIds[] = $itemPriceId;
        }
    }
    $billedMap = teacher_billing_billed_item_map($con, $studentId, $itemIds);

    $summary = array(
        'items' => array(),
        'total_items' => 0,
        'billed_items' => 0,
        'pending_items' => 0,
        'total_amount' => 0.0,
        'billed_amount' => 0.0,
        'pending_amount' => 0.0,
        'latest_billed_at' => '',
        'has_billable_items' => !empty($itemRows),
    );

    foreach($itemRows as $itemRow){
        $itemPriceId = trim((string)($itemRow['itempriceid'] ?? ''));
        $price = isset($itemRow['price']) ? (float)$itemRow['price'] : 0.0;
        $billRow = isset($billedMap[$itemPriceId]) ? $billedMap[$itemPriceId] : null;
        $isBilled = is_array($billRow);
        $billedAt = $isBilled ? trim((string)($billRow['datetimebilled'] ?? '')) : '';

        $summary['total_items']++;
        $summary['total_amount'] += $price;
        if($isBilled){
            $summary['billed_items']++;
            $summary['billed_amount'] += $price;
            if($billedAt !== '' && ($summary['latest_billed_at'] === '' || strcmp($billedAt, $summary['latest_billed_at']) > 0)){
                $summary['latest_billed_at'] = $billedAt;
            }
        }else{
            $summary['pending_items']++;
            $summary['pending_amount'] += $price;
        }

        $itemRow['is_billed'] = $isBilled;
        $itemRow['billed_at'] = $billedAt;
        $summary['items'][] = $itemRow;
    }

    return $summary;
}
}

if(!function_exists('teacher_billing_bill_student_scope')){
function teacher_billing_bill_student_scope($con, $studentId, $classId, $batchId, $termName, $recordedBy = '', $teacherId = ''){
    $studentId = trim((string)$studentId);
    $classId = trim((string)$classId);
    $batchId = trim((string)$batchId);
    $termName = (int)$termName;
    $recordedBy = trim((string)$recordedBy);
    $teacherId = trim((string)$teacherId);

    $result = array(
        'ok' => false,
        'tone' => 'error',
        'message' => 'Billing could not be completed.',
        'transactionid' => '',
        'inserted_count' => 0,
        'skipped_count' => 0,
        'inserted_amount' => 0.0,
    );

    if($studentId === '' || $classId === '' || $batchId === '' || $termName <= 0){
        $result['message'] = 'Student, class, batch, and semester are required.';
        return $result;
    }

    if(!teacher_billing_is_admin()){
        if($teacherId === ''){
            $teacherId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
        }
        if(!teacher_billing_is_assigned($con, $teacherId, $classId, $batchId, $termName)){
            $result['message'] = 'You are not assigned billing access for that class, batch, and semester.';
            return $result;
        }
    }

    if(!teacher_billing_student_registered_for_scope($con, $studentId, $classId, $batchId, $termName)){
        $result['tone'] = 'warning';
        $result['message'] = 'This student is not registered for that billing scope.';
        return $result;
    }

    if($recordedBy === ''){
        $recordedBy = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
    }

    $summary = teacher_billing_student_scope_summary($con, $studentId, $classId, $batchId, $termName, $teacherId);
    if(!$summary['has_billable_items']){
        $result['tone'] = 'warning';
        $result['message'] = 'No active billable items were found for this billing scope.';
        return $result;
    }

    if($summary['pending_items'] <= 0){
        $result['ok'] = true;
        $result['tone'] = 'info';
        $result['message'] = 'This student is already fully billed for the selected scope.';
        $result['skipped_count'] = (int)$summary['billed_items'];
        return $result;
    }

    $recordedByEsc = mysqli_real_escape_string($con, $recordedBy);
    $studentIdEsc = mysqli_real_escape_string($con, $studentId);
    $schoolAccount = isset($_SESSION['SCHOOLACCOUNT']) ? trim((string)$_SESSION['SCHOOLACCOUNT']) : '';
    $schoolAccountEsc = mysqli_real_escape_string($con, $schoolAccount);
    $transactionId = teacher_billing_generate_transaction_id();
    $transactionIdEsc = mysqli_real_escape_string($con, $transactionId);
    $insertTransaction = mysqli_query($con, "INSERT INTO tbltransaction(transactionid,userid,datetimepayment,recordedby,status)
        VALUES('$transactionIdEsc','$studentIdEsc',NOW(),'$recordedByEsc','active')");
    if(!$insertTransaction){
        $result['message'] = 'Billing could not start because the transaction record failed to save.';
        return $result;
    }

    $insertFailures = 0;
    foreach($summary['items'] as $itemRow){
        if(!empty($itemRow['is_billed'])){
            continue;
        }

        $billId = teacher_billing_generate_bill_id();
        $billIdEsc = mysqli_real_escape_string($con, $billId);
        $itemPriceIdEsc = mysqli_real_escape_string($con, trim((string)($itemRow['itempriceid'] ?? '')));
        $price = isset($itemRow['price']) ? (float)$itemRow['price'] : 0.0;
        $priceEsc = mysqli_real_escape_string($con, (string)$price);

        $insertBill = mysqli_query($con, "INSERT INTO tblbilling(billid,userid,itempriceid,transactionid,cost,datetimebilled,recordedby,status,referenceid)
            VALUES('$billIdEsc','$studentIdEsc','$itemPriceIdEsc','$transactionIdEsc','$priceEsc',NOW(),'$recordedByEsc','active','$schoolAccountEsc')");
        if($insertBill){
            $result['inserted_count']++;
            $result['inserted_amount'] += $price;
            @mysqli_query($con, "INSERT INTO accountingbookentries(accountId,cr,created,createdBy,dr,modifiedBy,narration,particulars,refAccountId,transactionId)
                VALUES('$schoolAccountEsc','$priceEsc',NOW(),'$recordedByEsc',0,'','Bills','Bills','$studentIdEsc','$transactionIdEsc')");
        }else{
            $insertFailures++;
        }
    }

    if($result['inserted_count'] <= 0){
        @mysqli_query($con, "DELETE FROM tbltransaction WHERE transactionid='$transactionIdEsc'");
        $result['tone'] = 'warning';
        $result['message'] = ($insertFailures > 0)
            ? 'No new bills were saved for this student scope.'
            : 'This student is already fully billed for the selected scope.';
        return $result;
    }

    $result['ok'] = true;
    $result['tone'] = ($insertFailures > 0) ? 'warning' : 'success';
    $result['transactionid'] = $transactionId;
    $result['skipped_count'] = (int)$summary['billed_items'];
    $result['message'] = ($insertFailures > 0)
        ? 'Billing completed with a few issues. Some items were billed, but not every pending item was saved.'
        : 'Pending billing items were added successfully for this student.';
    return $result;
}
}
?>
