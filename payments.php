<?php
session_start();
if(!isset($_SESSION['Message'])){
    $_SESSION['Message']="";
}
?>

<?php
if(!function_exists('payments_sms_safe')){
function payments_sms_safe($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
}

if(!function_exists('payments_student_billing_totals')){
function payments_student_billing_totals($con, $userId, $classId = "", $termId = "", $batchId = "", $allowedItemPriceIds = array()){
    $userIdEsc = mysqli_real_escape_string($con, (string)$userId);
    $filters = array();
    if(trim((string)$classId) !== ""){
        $filters[] = "ip.class_entryid='".mysqli_real_escape_string($con, (string)$classId)."'";
    }
    if(trim((string)$termId) !== ""){
        $filters[] = "ip.term='".mysqli_real_escape_string($con, (string)$termId)."'";
    }
    if(trim((string)$batchId) !== ""){
        $filters[] = "ip.batch='".mysqli_real_escape_string($con, (string)$batchId)."'";
    }
    $allowedIds = array();
    foreach((array)$allowedItemPriceIds as $itemPriceId){
        $itemPriceId = trim((string)$itemPriceId);
        if($itemPriceId !== ''){
            $allowedIds[] = "'".mysqli_real_escape_string($con, $itemPriceId)."'";
        }
    }
    if(!empty($allowedIds)){
        $filters[] = "ip.itempriceid IN (".implode(",", $allowedIds).")";
    }
    $filterSql = $filters ? " AND ".implode(" AND ", $filters) : "";

    $totals = array(
        "total_cost" => 0.0,
        "total_paid" => 0.0,
        "balance" => 0.0
    );

    $costSql = "SELECT COALESCE(SUM(bi.cost),0) AS total_cost
        FROM tblbilling bi
        INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid
        WHERE bi.userid='$userIdEsc'".$filterSql;
    $costRes = mysqli_query($con, $costSql);
    if($costRes && ($costRow = mysqli_fetch_array($costRes, MYSQLI_ASSOC))){
        $totals["total_cost"] = (float)$costRow["total_cost"];
    }

    $paidSql = "SELECT COALESCE(SUM(pm.payment),0) AS total_paid
        FROM tblpayment pm
        INNER JOIN tblbilling bi ON pm.billid=bi.billid
        INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid
        WHERE bi.userid='$userIdEsc' AND pm.status='active'".$filterSql;
    $paidRes = mysqli_query($con, $paidSql);
    if($paidRes && ($paidRow = mysqli_fetch_array($paidRes, MYSQLI_ASSOC))){
        $totals["total_paid"] = (float)$paidRow["total_paid"];
    }

    $totals["balance"] = $totals["total_cost"] - $totals["total_paid"];
    return $totals;
}
}

if(!function_exists('payments_scope_item_rows')){
function payments_scope_item_rows($con, $classId, $batchId, $termId, $allowedItemPriceIds = array()){
    $rows = array();
    $classIdEsc = mysqli_real_escape_string($con, trim((string)$classId));
    $batchIdEsc = mysqli_real_escape_string($con, trim((string)$batchId));
    $termId = (int)$termId;
    if($classIdEsc === '' || $batchIdEsc === '' || $termId <= 0){
        return $rows;
    }
    $filters = array(
        "ip.class_entryid='$classIdEsc'",
        "ip.batch='$batchIdEsc'",
        "ip.term='$termId'",
        "ip.status='active'",
        "itm.status='active'"
    );
    $allowedIds = array();
    foreach((array)$allowedItemPriceIds as $itemPriceId){
        $itemPriceId = trim((string)$itemPriceId);
        if($itemPriceId !== ''){
            $allowedIds[] = "'".mysqli_real_escape_string($con, $itemPriceId)."'";
        }
    }
    if(!empty($allowedIds)){
        $filters[] = "ip.itempriceid IN (".implode(",", $allowedIds).")";
    }
    $sql = "SELECT ip.*, itm.itemname
        FROM tblitemprice ip
        INNER JOIN tblitem itm ON itm.itemid=ip.itemid
        WHERE ".implode(" AND ", $filters)."
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

if(!function_exists('payments_generate_bill_id')){
function payments_generate_bill_id(){
    $code = '';
    @include(__DIR__.DIRECTORY_SEPARATOR."code.php");
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

if(!function_exists('payments_generate_transaction_id')){
function payments_generate_transaction_id(){
    $transactionId = '';
    @include(__DIR__.DIRECTORY_SEPARATOR."code.php");
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

if(!function_exists('payments_student_registered_for_scope')){
function payments_student_registered_for_scope($con, $userId, $classId, $batchId, $termId){
    $userIdEsc = mysqli_real_escape_string($con, trim((string)$userId));
    $classIdEsc = mysqli_real_escape_string($con, trim((string)$classId));
    $batchIdEsc = mysqli_real_escape_string($con, trim((string)$batchId));
    $termId = (int)$termId;
    if($userIdEsc === '' || $classIdEsc === '' || $batchIdEsc === '' || $termId <= 0){
        return false;
    }
    $sql = "SELECT userid FROM tbltermregistry
        WHERE userid='$userIdEsc'
          AND class_entryid='$classIdEsc'
          AND batchid='$batchIdEsc'
          AND termname='$termId'
        LIMIT 1";
    $res = mysqli_query($con, $sql);
    return ($res && mysqli_num_rows($res) > 0);
}
}

if(!function_exists('payments_ensure_billing_for_student_scope')){
function payments_ensure_billing_for_student_scope($con, $userId, $classId, $batchId, $termId, $allowedItemPriceIds = array(), $recordedBy = ''){
    if(!payments_student_registered_for_scope($con, $userId, $classId, $batchId, $termId)){
        return 0;
    }
    $itemRows = payments_scope_item_rows($con, $classId, $batchId, $termId, $allowedItemPriceIds);
    if(empty($itemRows)){
        return 0;
    }
    $userIdEsc = mysqli_real_escape_string($con, trim((string)$userId));
    $missingRows = array();
    foreach($itemRows as $itemRow){
        $itemPriceId = trim((string)($itemRow['itempriceid'] ?? ''));
        if($itemPriceId === ''){
            continue;
        }
        $itemPriceIdEsc = mysqli_real_escape_string($con, $itemPriceId);
        $checkRes = mysqli_query($con, "SELECT billid FROM tblbilling WHERE userid='$userIdEsc' AND itempriceid='$itemPriceIdEsc' LIMIT 1");
        if(!$checkRes || mysqli_num_rows($checkRes) === 0){
            $missingRows[] = $itemRow;
        }
    }
    if(empty($missingRows)){
        return 0;
    }
    if($recordedBy === ''){
        $recordedBy = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
    }
    $recordedByEsc = mysqli_real_escape_string($con, $recordedBy);
    $transactionId = payments_generate_transaction_id();
    $transactionIdEsc = mysqli_real_escape_string($con, $transactionId);
    @mysqli_query($con, "INSERT INTO tbltransaction(transactionid,userid,datetimepayment,recordedby,status)
        VALUES('$transactionIdEsc','$userIdEsc',NOW(),'$recordedByEsc','active')");

    $schoolAccount = isset($_SESSION['SCHOOLACCOUNT']) ? trim((string)$_SESSION['SCHOOLACCOUNT']) : '';
    $schoolAccountEsc = mysqli_real_escape_string($con, $schoolAccount);
    $insertedCount = 0;
    foreach($missingRows as $itemRow){
        $billId = payments_generate_bill_id();
        $billIdEsc = mysqli_real_escape_string($con, $billId);
        $itemPriceIdEsc = mysqli_real_escape_string($con, (string)$itemRow['itempriceid']);
        $price = isset($itemRow['price']) ? (float)$itemRow['price'] : 0;
        $priceEsc = mysqli_real_escape_string($con, (string)$price);
        $insertBill = mysqli_query($con, "INSERT INTO tblbilling(billid,userid,itempriceid,transactionid,cost,datetimebilled,recordedby,status,referenceid)
            VALUES('$billIdEsc','$userIdEsc','$itemPriceIdEsc','$transactionIdEsc','$priceEsc',NOW(),'$recordedByEsc','active','$schoolAccountEsc')");
        if($insertBill){
            $insertedCount++;
            @mysqli_query($con, "INSERT INTO accountingbookentries(accountId,cr,created,createdBy,dr,modifiedBy,narration,particulars,refAccountId,transactionId)
                VALUES('$schoolAccountEsc','$priceEsc',NOW(),'$recordedByEsc',0,'','Bills','Bills','$userIdEsc','$transactionIdEsc')");
        }
    }
    return $insertedCount;
}
}

if(!function_exists('payments_ensure_billing_for_scope')){
function payments_ensure_billing_for_scope($con, $classId, $batchId, $termId, $allowedItemPriceIds = array(), $recordedBy = ''){
    $classIdEsc = mysqli_real_escape_string($con, trim((string)$classId));
    $batchIdEsc = mysqli_real_escape_string($con, trim((string)$batchId));
    $termId = (int)$termId;
    if($classIdEsc === '' || $batchIdEsc === '' || $termId <= 0){
        return 0;
    }
    $sql = "SELECT DISTINCT tr.userid
        FROM tbltermregistry tr
        INNER JOIN tblsystemuser su ON su.userid=tr.userid
        WHERE tr.class_entryid='$classIdEsc'
          AND tr.batchid='$batchIdEsc'
          AND tr.termname='$termId'
          AND su.systemtype='Student'
          AND su.status='active'";
    $res = mysqli_query($con, $sql);
    if(!$res){
        return 0;
    }
    $totalInserted = 0;
    while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
        $studentId = trim((string)($row['userid'] ?? ''));
        if($studentId !== ''){
            $totalInserted += (int)payments_ensure_billing_for_student_scope($con, $studentId, $classId, $batchId, $termId, $allowedItemPriceIds, $recordedBy);
        }
    }
    return $totalInserted;
}
}

if(!function_exists('payments_send_balance_sms_scope')){
function payments_send_balance_sms_scope($con, $batchId, $classId = "", $termId = ""){
    $batchId = trim((string)$batchId);
    $classId = trim((string)$classId);
    $termId = trim((string)$termId);
    if($batchId === ""){
        return "<div style='color:red;background-color:white;padding:8px;' align='left'><i class='fa fa-times' style='color:red'></i> Batch is required for bulk balance SMS.</div>";
    }

    $batchIdEsc = mysqli_real_escape_string($con, $batchId);
    $classIdEsc = mysqli_real_escape_string($con, $classId);

    $className = "All Classes";
    $batchName = $batchId;
    $semesterName = ($termId !== "" ? "Semester ".$termId : "All Semesters");

    $classRes = ($classId !== "")
        ? mysqli_query($con, "SELECT class_name FROM tblclassentry WHERE class_entryid='$classIdEsc' LIMIT 1")
        : false;
    if($classRes && ($rowClass = mysqli_fetch_array($classRes, MYSQLI_ASSOC))){
        $className = trim((string)$rowClass['class_name']);
    }

    $batchRes = mysqli_query($con, "SELECT batch FROM tblbatch WHERE batchid='$batchIdEsc' LIMIT 1");
    if($batchRes && ($rowBatch = mysqli_fetch_array($batchRes, MYSQLI_ASSOC))){
        $batchName = trim((string)$rowBatch['batch']);
    }

    $studentSql = "SELECT DISTINCT su.userid,su.firstname,su.othernames,su.surname,su.nextofkin_contact
        FROM tblclass cl
        INNER JOIN tblsystemuser su ON cl.userid=su.userid
        WHERE cl.batchid='$batchIdEsc'
          AND su.systemtype='Student'
          AND su.status='active'";
    if($classId !== ""){
        $studentSql .= " AND cl.class_entryid='$classIdEsc'";
    }
    $studentSql .= " ORDER BY su.firstname, su.othernames, su.surname";

    $studentRes = mysqli_query($con, $studentSql);
    if(!$studentRes){
        return "<div style='color:red;background-color:white;padding:8px;' align='left'><i class='fa fa-times' style='color:red'></i> Bulk balance SMS query failed.</div>";
    }

    $currencyLabel = isset($_SESSION['CURRENCY']) && trim((string)$_SESSION['CURRENCY']) !== ""
        ? trim((string)$_SESSION['CURRENCY'])
        : "GHS";

    $sentCount = 0;
    $noPhoneCount = 0;
    $noBalanceCount = 0;
    $failedCount = 0;
    $failedStudents = array();

    while($studentRow = mysqli_fetch_array($studentRes, MYSQLI_ASSOC)){
        $studentId = (string)$studentRow['userid'];
        $parentPhone = trim((string)($studentRow['nextofkin_contact'] ?? ''));
        if($parentPhone === ""){
            $noPhoneCount++;
            continue;
        }

        $balanceTotals = payments_student_billing_totals($con, $studentId, $classId, $termId, $batchId);
        if($balanceTotals['balance'] <= 0){
            $noBalanceCount++;
            continue;
        }

        $studentName = trim((string)($studentRow['firstname'] ?? '')." ".(string)($studentRow['othernames'] ?? '')." ".(string)($studentRow['surname'] ?? ''));
        if($studentName === ""){
            $studentName = $studentId;
        }

        $smsMessage = "Billing balance for ".$studentName." (".$studentId.")."
            ." Scope: ".$className.", ".$semesterName.", ".$batchName."."
            ." Total billed: ".$currencyLabel." ".number_format((float)$balanceTotals['total_cost'], 2)."."
            ." Paid: ".$currencyLabel." ".number_format((float)$balanceTotals['total_paid'], 2)."."
            ." Balance: ".$currencyLabel." ".number_format((float)$balanceTotals['balance'], 2).".";

        $smsCode = "";
        if(send_bulk_sms_message($parentPhone, $smsMessage, $smsCode)){
            $sentCount++;
        }else{
            $failedCount++;
            $failedStudents[] = payments_sms_safe($studentId);
        }
    }

    $summary = "<div style='color:green;background-color:white;padding:8px;' align='left'><i class='fa fa-envelope' style='color:green'></i> Bulk balance SMS summary for "
        .payments_sms_safe($className).", ".payments_sms_safe($semesterName).", ".payments_sms_safe($batchName)
        .": Sent ".$sentCount.", No Phone ".$noPhoneCount.", No Balance ".$noBalanceCount.", Failed ".$failedCount.".</div>";
    if($failedCount > 0){
        $summary .= "<div style='color:#b45309;background-color:white;padding:8px;' align='left'><i class='fa fa-times' style='color:#b45309'></i> Failed student IDs: ".implode(", ", $failedStudents)."</div>";
    }
    return $summary;
}
}

if(!function_exists('payments_balance_scope_data')){
function payments_balance_scope_data($con, $batchId, $classId = "", $termId = "", $allowedItemPriceIds = array()){
    $batchId = trim((string)$batchId);
    $classId = trim((string)$classId);
    $termId = trim((string)$termId);
    if($batchId === ""){
        return array(
            "rows" => array(),
            "summary" => array(
                "student_count" => 0,
                "owing_count" => 0,
                "total_cost" => 0.0,
                "total_paid" => 0.0,
                "total_balance" => 0.0
            )
        );
    }

    $batchIdEsc = mysqli_real_escape_string($con, $batchId);
    $classIdEsc = mysqli_real_escape_string($con, $classId);
    $termIdEsc = mysqli_real_escape_string($con, $termId);

    $billingFilters = array("ip.batch='$batchIdEsc'");
    if($classId !== ""){
        $billingFilters[] = "ip.class_entryid='$classIdEsc'";
    }
    if($termId !== ""){
        $billingFilters[] = "ip.term='$termIdEsc'";
    }
    $allowedIds = array();
    foreach((array)$allowedItemPriceIds as $itemPriceId){
        $itemPriceId = trim((string)$itemPriceId);
        if($itemPriceId !== ''){
            $allowedIds[] = "'".mysqli_real_escape_string($con, $itemPriceId)."'";
        }
    }
    if(!empty($allowedIds)){
        $billingFilters[] = "ip.itempriceid IN (".implode(",", $allowedIds).")";
    }
    $billingWhere = implode(" AND ", $billingFilters);

    $billedAggSql = "SELECT bi.userid, SUM(bi.cost) AS total_cost
        FROM tblbilling bi
        INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid
        WHERE $billingWhere
        GROUP BY bi.userid";

    $paidAggSql = "SELECT bi.userid, SUM(pm.payment) AS total_paid
        FROM tblpayment pm
        INNER JOIN tblbilling bi ON pm.billid=bi.billid
        INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid
        WHERE pm.status='active' AND $billingWhere
        GROUP BY bi.userid";

    if($termId !== ""){
        $studentBaseSql = "SELECT DISTINCT tr.userid, tr.class_entryid
            FROM tbltermregistry tr
            WHERE tr.batchid='$batchIdEsc' AND tr.termname='$termIdEsc'".($classId !== "" ? " AND tr.class_entryid='$classIdEsc'" : "");
    }else{
        $studentBaseSql = "SELECT cl.userid, cl.class_entryid
            FROM tblclass cl
            INNER JOIN (
                SELECT userid, batchid, MAX(datetimeentry) AS max_datetime
                FROM tblclass
                WHERE batchid='$batchIdEsc'
                GROUP BY userid, batchid
            ) latest
                ON latest.userid=cl.userid
               AND latest.batchid=cl.batchid
               AND latest.max_datetime=cl.datetimeentry
            WHERE cl.batchid='$batchIdEsc'".($classId !== "" ? " AND cl.class_entryid='$classIdEsc'" : "");
    }

    $sql = "SELECT base.userid,
                base.class_entryid,
                su.firstname,
                su.othernames,
                su.surname,
                su.nextofkin_contact,
                ce.class_name,
                COALESCE(costs.total_cost, 0) AS total_cost,
                COALESCE(pays.total_paid, 0) AS total_paid
            FROM ($studentBaseSql) base
            INNER JOIN tblsystemuser su ON base.userid=su.userid
            LEFT JOIN tblclassentry ce ON base.class_entryid=ce.class_entryid
            LEFT JOIN ($billedAggSql) costs ON base.userid=costs.userid
            LEFT JOIN ($paidAggSql) pays ON base.userid=pays.userid
            WHERE su.systemtype='Student' AND su.status='active'
            ORDER BY ce.class_name ASC, su.firstname ASC, su.othernames ASC, su.surname ASC";

    $rows = array();
    $summary = array(
        "student_count" => 0,
        "owing_count" => 0,
        "total_cost" => 0.0,
        "total_paid" => 0.0,
        "total_balance" => 0.0
    );

    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $row["total_cost"] = (float)$row["total_cost"];
            $row["total_paid"] = (float)$row["total_paid"];
            $row["balance"] = $row["total_cost"] - $row["total_paid"];
            $rows[] = $row;

            $summary["student_count"]++;
            $summary["total_cost"] += $row["total_cost"];
            $summary["total_paid"] += $row["total_paid"];
            $summary["total_balance"] += $row["balance"];
            if($row["balance"] > 0){
                $summary["owing_count"]++;
            }
        }
    }

    return array("rows" => $rows, "summary" => $summary);
}
}

include_once("check-login.php");
include_once("dbstring.php");
include_once("teacher-billing-utils.php");
ensure_teacher_billing_table($con);

if(!function_exists('payments_scope_query_suffix')){
function payments_scope_query_suffix($classId = '', $batchId = '', $termId = ''){
    $parts = array();
    if(trim((string)$classId) !== ''){
        $parts[] = 'scope_class_id='.urlencode((string)$classId);
    }
    if(trim((string)$batchId) !== ''){
        $parts[] = 'scope_batch_id='.urlencode((string)$batchId);
    }
    if(trim((string)$termId) !== ''){
        $parts[] = 'scope_term_id='.urlencode((string)$termId);
    }
    return implode('&', $parts);
}
}

if(!function_exists('payments_page_url')){
function payments_page_url($userId = '', $classId = '', $batchId = '', $termId = ''){
    $parts = array();
    if(trim((string)$userId) !== ''){
        $parts[] = 'userid='.urlencode((string)$userId);
    }
    $scopeSuffix = payments_scope_query_suffix($classId, $batchId, $termId);
    if($scopeSuffix !== ''){
        $parts[] = $scopeSuffix;
    }
    return 'payments.php'.(!empty($parts) ? '?'.implode('&', $parts) : '');
}
}

if(!function_exists('payments_page_anchor_url')){
function payments_page_anchor_url($userId = '', $classId = '', $batchId = '', $termId = '', $anchor = 'payment-interface'){
    $url = payments_page_url($userId, $classId, $batchId, $termId);
    $anchor = trim((string)$anchor);
    if($anchor !== ''){
        $url .= '#'.rawurlencode($anchor);
    }
    return $url;
}
}

$paymentsCurrentUserId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
$paymentsIsAdmin = teacher_billing_is_admin();
$paymentsIsTeacher = teacher_billing_is_teacher();
$paymentsTeacherHasModule = $paymentsIsTeacher && teacher_billing_teacher_has_module($con, $paymentsCurrentUserId);

if(!$paymentsIsAdmin && !$paymentsTeacherHasModule){
    $_SESSION['Message'] = "<div style='color:red;background-color:white;padding:8px;' align='left'><i class='fa fa-times' style='color:red'></i> You do not have access to class payments.</div>";
    header("location:".teacher_billing_landing_page());
    exit();
}

$paymentsTeacherAssignments = $paymentsTeacherHasModule ? teacher_billing_fetch_assignments($con, $paymentsCurrentUserId) : array();
$paymentsTeacherHasScope = ($paymentsIsAdmin || count($paymentsTeacherAssignments) > 0);
$paymentsScopeClassRows = $paymentsIsAdmin ? array() : teacher_billing_class_options($con);
$paymentsScopeBatchRows = $paymentsIsAdmin ? array() : teacher_billing_batch_options($con);

if(!function_exists('payments_teacher_scope_allowed')){
function payments_teacher_scope_allowed($con, $teacherHasModule, $classId, $batchId, $termId){
    if(!$teacherHasModule){
        return true;
    }
    return teacher_billing_is_assigned(
        $con,
        isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '',
        trim((string)$classId),
        trim((string)$batchId),
        (int)$termId
    );
}
}

if($paymentsTeacherHasModule && !$paymentsTeacherHasScope){
    $_SESSION['Message'] = "<div style='color:#b45309;background-color:white;padding:8px;' align='left'><i class='fa fa-info-circle' style='color:#b45309'></i> Admin has not assigned any fee-collection class to you yet.</div>";
}

if(isset($_POST['send_balance_sms'])){
    if($paymentsTeacherHasModule){
        $_SESSION['Message'] = "<div style='color:red;background-color:white;padding:8px;' align='left'><i class='fa fa-times' style='color:red'></i> Teachers can collect payments only. Parent balance SMS remains admin-only.</div>";
    }else{
    include("dbstring.php");
    include("house-master-utils.php");

    $_SmsUserId = trim((string)($_POST['sms_userid'] ?? ($_GET['userid'] ?? '')));
    $_SmsClassId = trim((string)($_POST['sms_class_id'] ?? ''));
    $_SmsTermId = trim((string)($_POST['sms_term_id'] ?? ''));
    $_SmsBatchId = trim((string)($_POST['sms_batch_id'] ?? ''));

    if($_SmsUserId === ""){
        $_SESSION['Message'] = "<div style='color:red;background-color:white;padding:8px;' align='left'><i class='fa fa-times' style='color:red'></i> No student was selected for the balance SMS.</div>";
    }else{
        $_SmsUserIdEsc = mysqli_real_escape_string($con, $_SmsUserId);
        $_StudentSql = mysqli_query($con, "SELECT userid,firstname,othernames,surname,nextofkin_contact FROM tblsystemuser WHERE userid='$_SmsUserIdEsc' LIMIT 1");
        if(!$_StudentSql || mysqli_num_rows($_StudentSql) === 0){
            $_SESSION['Message'] = "<div style='color:red;background-color:white;padding:8px;' align='left'><i class='fa fa-times' style='color:red'></i> Student record could not be found for the balance SMS.</div>";
        }else{
            $_StudentRow = mysqli_fetch_array($_StudentSql, MYSQLI_ASSOC);
            $_ParentPhone = trim((string)($_StudentRow['nextofkin_contact'] ?? ''));
            if($_ParentPhone === ""){
                $_SESSION['Message'] = "<div style='color:#b45309;background-color:white;padding:8px;' align='left'><i class='fa fa-phone' style='color:#b45309'></i> Parent phone number is missing for ".payments_sms_safe($_SmsUserId).".</div>";
            }else{
                $_BalanceTotals = payments_student_billing_totals($con, $_SmsUserId, $_SmsClassId, $_SmsTermId, $_SmsBatchId);
                if($_BalanceTotals['balance'] <= 0){
                    $_SESSION['Message'] = "<div style='color:#1d4ed8;background-color:white;padding:8px;' align='left'><i class='fa fa-info-circle' style='color:#1d4ed8'></i> No outstanding bill balance was found, so no SMS was sent.</div>";
                }else{
                    $_StudentName = trim((string)($_StudentRow['firstname'] ?? '')." ".(string)($_StudentRow['othernames'] ?? '')." ".(string)($_StudentRow['surname'] ?? ''));
                    if($_StudentName === ""){
                        $_StudentName = $_SmsUserId;
                    }

                    $_ScopeBits = array();
                    if($_SmsClassId !== ""){
                        $_ClassScopeSql = mysqli_query($con, "SELECT class_name FROM tblclassentry WHERE class_entryid='".mysqli_real_escape_string($con, $_SmsClassId)."' LIMIT 1");
                        if($_ClassScopeSql && ($rowClassScope = mysqli_fetch_array($_ClassScopeSql, MYSQLI_ASSOC))){
                            $_ScopeBits[] = trim((string)$rowClassScope['class_name']);
                        }
                    }
                    if($_SmsTermId !== ""){
                        $_ScopeBits[] = "Semester ".$_SmsTermId;
                    }
                    if($_SmsBatchId !== ""){
                        $_BatchScopeSql = mysqli_query($con, "SELECT batch FROM tblbatch WHERE batchid='".mysqli_real_escape_string($con, $_SmsBatchId)."' LIMIT 1");
                        if($_BatchScopeSql && ($rowBatchScope = mysqli_fetch_array($_BatchScopeSql, MYSQLI_ASSOC))){
                            $_ScopeBits[] = trim((string)$rowBatchScope['batch']);
                        }
                    }

                    $_CurrencyLabel = isset($_SESSION['CURRENCY']) && trim((string)$_SESSION['CURRENCY']) !== ""
                        ? trim((string)$_SESSION['CURRENCY'])
                        : "GHS";
                    $_ScopeText = $_ScopeBits ? " Scope: ".implode(", ", $_ScopeBits)."." : "";
                    $_SMSMessage = "Billing balance for ".$_StudentName." (".$_SmsUserId.").".$_ScopeText
                        ." Total billed: ".$_CurrencyLabel." ".number_format((float)$_BalanceTotals['total_cost'], 2)."."
                        ." Paid: ".$_CurrencyLabel." ".number_format((float)$_BalanceTotals['total_paid'], 2)."."
                        ." Balance: ".$_CurrencyLabel." ".number_format((float)$_BalanceTotals['balance'], 2).".";

                    $_SMSCode = "";
                    $_SmsSent = send_bulk_sms_message($_ParentPhone, $_SMSMessage, $_SMSCode);
                    if($_SmsSent){
                        $_SESSION['Message'] = "<div style='color:green;background-color:white;padding:8px;' align='left'><i class='fa fa-check' style='color:green'></i> Balance SMS sent successfully to the parent contact.</div>";
                    }else{
                        $_SESSION['Message'] = "<div style='color:#b45309;background-color:white;padding:8px;' align='left'><i class='fa fa-times' style='color:#b45309'></i> Balance SMS failed to send. Code: ".payments_sms_safe($_SMSCode)."</div>";
                    }
                }
            }
        }
    }
    }
}

if(isset($_POST['send_class_balance_sms'])){
    if($paymentsTeacherHasModule){
        $_SESSION['Message'] = "<div style='color:red;background-color:white;padding:8px;' align='left'><i class='fa fa-times' style='color:red'></i> Teachers can collect payments only. Parent balance SMS remains admin-only.</div>";
    }else{
    include("dbstring.php");
    include("house-master-utils.php");

    $_BulkClassId = trim((string)($_POST['bulk_sms_class_id'] ?? ''));
    $_BulkTermId = trim((string)($_POST['bulk_sms_term_id'] ?? ''));
    $_BulkBatchId = trim((string)($_POST['bulk_sms_batch_id'] ?? ''));
    $_SESSION['Message'] = payments_send_balance_sms_scope($con, $_BulkBatchId, $_BulkClassId, $_BulkTermId);
    }
}

if(isset($_POST['send_scope_balance_sms'])){
    if($paymentsTeacherHasModule){
        $_SESSION['Message'] = "<div style='color:red;background-color:white;padding:8px;' align='left'><i class='fa fa-times' style='color:red'></i> Teachers can collect payments only. Parent balance SMS remains admin-only.</div>";
    }else{
    include("dbstring.php");
    include("house-master-utils.php");

    $_ScopeBatchId = trim((string)($_POST['scope_sms_batch_id'] ?? ''));
    $_ScopeClassId = trim((string)($_POST['scope_sms_class_id'] ?? ''));
    $_ScopeTermId = trim((string)($_POST['scope_sms_term_id'] ?? ''));

    $_SESSION['Message'] = payments_send_balance_sms_scope($con, $_ScopeBatchId, $_ScopeClassId, $_ScopeTermId);
    }
}
?>


<?php
//Declare the variables
@$_TransactionId_Bi="";
//@$_Receivedby=$_SESSION['USERID'];
//@$_PaymentDate=$_POST['batch_month']." ".$_POST['batch_year'];
if(isset($_POST["print_all_bill"]))
{
      if($paymentsTeacherHasModule){
        $_SESSION['Message'] = "<div style='color:red;background-color:white;padding:8px;' align='left'><i class='fa fa-times' style='color:red'></i> Teachers can collect payments only. Print billing slips remains admin-only on this page.</div>";
        header("location:".payments_page_url(trim((string)($_POST['getuserid'] ?? '')), trim((string)($_GET['scope_class_id'] ?? '')), trim((string)($_GET['scope_batch_id'] ?? '')), trim((string)($_GET['scope_term_id'] ?? ''))));
        exit();
      }
      include("dbstring.php");
      include("config.php");
      include("company.php");
      //Get all the ordered items

require('fpdf181/fpdf.php');
//ob_start();

$pdf = new FPDF();
$pdf->AddPage();
$_GETUserID=$_POST["getuserid"];
$_SQL_SU="SELECT * FROM tblsystemuser WHERE userid='$_GETUserID'";

$width_cell=array(10,80,40,50,30);
$pdf->SetFont('Arial','B',18);

//Background color of header//
//Heading of the pdf
// Logo
     $pdf->Image("logo/".$_Logo,$width_cell[0]+$width_cell[1],7,22);
     $pdf->Ln(24);

$p=10;
$pdf->SetFillColor(255,255,255);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3],10,$_CompanyName,0,0,'C',true);
$pdf->Ln($p);
//$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,"C.D.C",0,0,'C',true);
//$pdf->Ln($p);

$pdf->SetFont('Arial','B',12);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3],10,$_Address." ".$_Location,0,0,'C',true);
$pdf->Ln($p);

//$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'LOCATION: OYOKO ROUNABOUT, KOFORIDUA',0,0,'C',true);
//$pdf->Ln($p);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3],10,'Tel:'. $_Telephone1. " ". $_Telephone2,0,0,'C',true);
$pdf->Ln($p);
$pdf->SetFont('Arial','B',14);

  $text_height = 5;
  $text_length = 70;
  $n=9;

      $pdf->Cell($text_length,$text_height,'PAYMENT DETAILS',0,0,'L',true);
      //$pdf->SetTextColor(0);
     $pdf->SetFont('Arial','B',12);

      $pdf->Ln($n);
 	$_Result=mysqli_query($con,$_SQL_SU);

      if($row_ps=mysqli_fetch_array($_Result,MYSQLI_ASSOC)){
      	@$_StaffName=$row_ps['firstname']." ".$row_ps['othernames']." ".$row_ps['surname']." (".$row_ps['userid'].")";
      	    $pdf->Cell($text_length,$text_height,'NAME:'.$_StaffName,0,0,'L',true);
      $pdf->Ln(10);
      }
  

 @$_Class_ID=$_POST['getclass'];
@$_Term_ID=$_POST['gettermid'];

$_SQL_CLASS=mysqli_query($con,"SELECT * FROM tblclassentry ce WHERE ce.class_entryid='$_Class_ID' ORDER BY ce.class_name ASC");
while($row_class=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC))
{
@$_Total_Amount=0;
@$_Total_Balance=0;
@$_Total_Amount_Paid=0;

$pdf->SetFont('Arial','B',12);
$pdf->Cell($text_length,$text_height,strtoupper($row_class['class_name']),0,0,'L',true);
$pdf->Ln($n);

$_SQL_TERM=mysqli_query($con,"SELECT * FROM tbltermregistry tr WHERE tr.class_entryid='$_Class_ID' AND tr.termname='$_Term_ID' 
	AND tr.userid='$_GETUserID' ORDER BY tr.termname ASC");


$pdf->SetFillColor(255,255,255);

$pdf->SetFont('Arial','B',12);
//Header starts //
$pdf->Cell($width_cell[0],10,'*',1,0,'C',true);

//First header column //
$pdf->Cell($width_cell[1],10,'ITEM',1,0,'C',true);

//$pdf->Cell($width_cell[2],10,'AMOUNT',1,0,'C',true);
$pdf->Cell($width_cell[2],10,'PAYMENT',1,0,'C',true);

$pdf->Cell($width_cell[3],10,'PAYMENT DATE/TIME',1,0,'C',true);
$pdf->Ln($n);

@$_Total_Amount_Paid=0;
@$_Total_amount_To_Pay=0;

while($row_tr=mysqli_fetch_array($_SQL_TERM,MYSQLI_ASSOC))
{
//$pdf->Cell($text_length,$text_height,"Term: ".$row_tr['termname'],0,0,'L',true);
$pdf->Cell($width_cell[0]+$width_cell[1],10,"Semester: ".$row_tr['termname'],1,0,'L',true);
//$pdf->Ln(10);

$_SQL_BIL=mysqli_query($con,"SELECT SUM(ip.price) AS Total_Amount,transactionid FROM tblbilling bi INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid WHERE bi.userid='$_GETUserID' AND ip.class_entryid='$row_tr[class_entryid]' AND ip.term='$row_tr[termname]'");
if($row_b=mysqli_fetch_array($_SQL_BIL,MYSQLI_ASSOC)){
$pdf->Cell($width_cell[2]+$width_cell[3],10,"AMOUNT TO PAY: $_SESSION[SYMBOL] ".$row_b['Total_Amount'],1,0,'R',true);
$_Total_amount_To_Pay=$row_b['Total_Amount'];
$_TransactionId_Bi=$row_b['transactionid'];
}

      //$pdf->SetTextColor(0);
$pdf->SetFont('Arial','B',12);
$pdf->Ln(10);

$_SQL_EXECUTE_3="SELECT * FROM tblbilling bi 
INNER JOIN tblsystemuser su ON bi.userid=su.userid 
INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid
INNER JOIN tblitem itm ON ip.itemid=itm.itemid
INNER JOIN tblclassentry ce ON ip.class_entryid=ce.class_entryid
INNER JOIN tblbatch b ON ip.batch=b.batchid
INNER JOIN tblpayment pm ON pm.billid=bi.billid
WHERE bi.userid='$_GETUserID' AND ip.class_entryid='$row_class[class_entryid]' AND ip.term='$row_tr[termname]' ORDER BY pm.datetimepayment DESC";
//$_SQL_EXECUTE_3_r=$_SQL_EXECUTE_3;


@$serial=0;
@$_Total_Amount_single=0;
@$_Total_Amount_Paid_Single=0;
@$_Total_Balance_Single=0;

//while($row_p=mysqli_fetch_array($_SQL_EXECUTE_3,MYSQLI_ASSOC)){


///header ends///
$pdf->SetFont('Arial','',10);
//Background color of header //
$pdf->SetFillColor(255,255,255);
//to give alternate background fill color to rows//
$fill =false;

@$_AdditionalPrice=0;

@$serial=0;
//each record is one row //
foreach ($dbo->query($_SQL_EXECUTE_3) as $row_p) 
{

$pdf->Cell($width_cell[0],10,$serial=$serial+1,1,0,'C',$fill);
//$pdf->Ln(10);
  //$pdf->Cell($width_cell[0],10,"Gross Salary",1,0,'C',$fill);
$pdf->Cell($width_cell[1],10,$row_p['itemname'],1,0,'L',$fill);
//$pdf->Ln(10);

//$_Total_Amount=$_Total_Amount+$row_p['price'];
//$_Total_Amount_single=$_Total_Amount_single+$row_p['price'];
//$pdf->Cell($width_cell[2],10,$row_p['price'],1,0,'C',$fill);
//$pdf->Ln(10);
				
$pdf->Cell($width_cell[2],10,$row_p['payment'],1,0,'C',$fill);
$_Total_Amount_Paid_Single=@$_Total_Amount_Paid_Single + $row_p['payment'];
//$pdf->Ln(10);
$pdf->Cell($width_cell[3],10,$row_p['datetimepayment'],1,0,'C',$fill);

/*$_Balance=$row_p['price']-$row_p['payment'];
$_Total_Balance=$_Total_Balance+$_Balance;
$_Total_Balance_Single=$_Total_Balance_Single+$_Balance;
$pdf->Cell($width_cell[4],10,$_Balance,1,0,'C',$fill);
*/
$fill = !$fill;
$pdf->Ln(10);
}

//Footer of the table
 //$pdf->Cell($width_cell[0],10,'',1,0,'C',true);
// $pdf->Cell($width_cell[0],10,'',1,0,'C',true);
$pdf->Cell($width_cell[0]+$width_cell[1],10,'Sub Total:',1,0,'R',true);
//$pdf->Cell($width_cell[2],10,$_Total_Amount_single,1,0,'C',true);
$pdf->Cell($width_cell[2],10,$_Total_Amount_Paid_Single,1,0,'C',true);
$_Total_Amount_Paid=$_Total_Amount_Paid+$_Total_Amount_Paid_Single;

$pdf->Cell($width_cell[3],10,"",1,0,'C',true);
$pdf->Ln(10); 
}
$pdf->SetFont('Arial','B',10);
$pdf->Cell($width_cell[0]+$width_cell[1],10,'GRAND TOTAL:',1,0,'R',true);
//$pdf->Cell($width_cell[2],10,$_SESSION['SYMBOL']." ".$_Total_Amount,1,0,'C',true);
$pdf->Cell($width_cell[2],10,$_SESSION['SYMBOL']." ".$_Total_Amount_Paid,1,0,'C',true);
$pdf->Cell($width_cell[3],10,"BALANCE: ".$_SESSION['SYMBOL']." ".($_Total_amount_To_Pay-$_Total_Amount_Paid),1,0,'C',true);
$pdf->Ln(15); 
}
/*$pdf->Cell($width_cell[2]+$width_cell[3],10,'Grand Total: Ghc',1,0,'C',true);
$pdf->Cell($width_cell[4],10,$_GrandTotal,1,0,'C',true);
*/
$pdf->SetFont('Arial','',10);

$tomorrow = mktime(0,0,0,date("m"),date("d"),date("Y"));
$tdate= date("d/m/Y", $tomorrow);
$pdf->SetFillColor(0,0,0);
//$pdf->PutLink("http://www.braintechconsult.com","BTC");
//$pdf->Ln(10);                    //$pdf->SetStyle('I',true);       
//$pdf->Cell(0,10,'Print Date/Time: '.$tdate .','.$todayTime,0);
$pdf->Cell(0,10,'Print Date/Time: '.$tdate,0);

//$pdf->Ln(10);                    //$pdf->SetStyle('I',true);       
//$pdf->Cell(0,10,'Paid By: '.$_Receivedby,0);
 $pdf->Ln(10); 

 $_SQL_AC=mysqli_query($con,"SELECT * FROM tbltransaction tr 
  INNER JOIN tblsystemuser su ON tr.recordedby=su.userid 
  WHERE tr.transactionid='$_TransactionId_Bi'");
 if($row_bi=mysqli_fetch_array($_SQL_AC,MYSQLI_ASSOC)){
 $pdf->Cell(0,10,'ADMINISTRATOR:'. strtoupper($row_bi['firstname']." ".$row_bi['othernames']." ".$row_bi['surname']),0);
 
 $pdf->Ln(10); 
 //$pdf->Cell(0,10,'SIGNATURE:'.strtoupper($row_bi['firstname']." ".$row_bi['othernames']." ".$row_bi['surname']),0);
 $pdf->Cell(0,10,'SIGNATURE:..........................................',0);
 
 //$pdf->Ln(5); 
 
//$pdf->Cell(0,3,$pdf->Image('accountants/'.$row_bi['signature']),0,0,'L',false);
 

}
 
 //$pdf->Ln(8); 

//$pdf->SetFont('Arial','B',8);

 /*$pdf->Ln(14); 
 $pdf->Cell(0,10,'Developed by: Brainstorm Technologies Consult',0);
 $pdf->Ln(8); 
 $pdf->Cell(0,10,'Accra,Takoradi,Koforidua - 0342-292-121',0);
/// end of records ///
 $pdf->Ln(50);
*/
//}
$pdf->Output();
 //ob_end_flush(); 
 //}
}
?>


<?php
//Declare the variables
@$_UserID=$_POST['userid'];
@$_TransactionId_Bi="";

@$_Receivedby=$_SESSION['USERID'];
//@$_PaymentDate=$_POST['batch_month']." ".$_POST['batch_year'];
if(isset($_POST["print_bill"]))
{
      if($paymentsTeacherHasModule){
        $_SESSION['Message'] = "<div style='color:red;background-color:white;padding:8px;' align='left'><i class='fa fa-times' style='color:red'></i> Teachers can collect payments only. Print billing slips remains admin-only on this page.</div>";
        header("location:".payments_page_url(trim((string)($_POST['userid'] ?? ($_GET['userid'] ?? ''))), trim((string)($_POST['scope_classid'] ?? ($_GET['scope_class_id'] ?? ''))), trim((string)($_POST['scope_batchid'] ?? ($_GET['scope_batch_id'] ?? ''))), trim((string)($_POST['scope_termid'] ?? ($_GET['scope_term_id'] ?? '')))));
        exit();
      }
      include("dbstring.php");
      include("config.php");
      include("company.php");
      //Get all the ordered items

require('fpdf181/fpdf.php');
//ob_start();

$pdf = new FPDF();
$pdf->AddPage();

$_SQL_SU="SELECT * FROM tblsystemuser WHERE userid='$_UserID'";

$width_cell=array(10,80,40,50,30);
$pdf->SetFont('Arial','B',18);
//if(mysqli_num_rows(mysqli_query($con,$_SQL_EXECUTE_SP))>0)
//{	

//Background color of header//
//Heading of the pdf
// Logo
 $pdf->Image("logo/".$_Logo,$width_cell[0]+$width_cell[1],3,22);
  $pdf->Ln(20);

$p=10;
$pdf->SetFillColor(255,255,255);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3],10,$_CompanyName,0,0,'C',true);
$pdf->Ln($p);
//$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,"C.D.C",0,0,'C',true);
//$pdf->Ln($p);

$pdf->SetFont('Arial','B',12);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3],10,$_Address." ".$_Location,0,0,'C',true);
$pdf->Ln($p);

//$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'LOCATION: OYOKO ROUNABOUT, KOFORIDUA',0,0,'C',true);
//$pdf->Ln($p);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3],10,'Tel:'. $_Telephone1. " ". $_Telephone2,0,0,'C',true);
$pdf->Ln($p);
$pdf->SetFont('Arial','B',14);

  $text_height = 5;
  $text_length = 70;
  $n=9;

      $pdf->Cell($text_length,$text_height,'PAYMENT DETAILS',0,0,'L',true);
      //$pdf->SetTextColor(0);
     $pdf->SetFont('Arial','B',12);

      $pdf->Ln($n);
 	$_Result=mysqli_query($con,$_SQL_SU);

      if($row_ps=mysqli_fetch_array($_Result,MYSQLI_ASSOC)){
      	@$_StaffName=$row_ps['firstname']." ".$row_ps['othernames']." ".$row_ps['surname']." (".$row_ps['userid'].")";
      	    $pdf->Cell($text_length,$text_height,'NAME:'.$_StaffName,0,0,'L',true);
      $pdf->Ln(10);
      }
  

@$_Class_ID=$_POST['class_id'];
@$_Term_ID=$_POST['term_id'];
@$_Bill_Term=$_POST['bill_id'];

$_SQL_CLASS=mysqli_query($con,"SELECT * FROM tblclassentry ce WHERE ce.class_entryid='$_Class_ID' ORDER BY ce.class_name ASC");
while($row_class=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC))
{
@$_Total_Amount=0;
@$_Total_Balance=0;
@$_Total_Amount_Paid=0;

$pdf->SetFont('Arial','B',12);
$pdf->Cell($text_length,$text_height,strtoupper($row_class['class_name']),0,0,'L',true);
$pdf->Ln($n);

$_SQL_TERM=mysqli_query($con,"SELECT * FROM tbltermregistry tr WHERE tr.class_entryid='$_Class_ID' AND tr.termname='$_Term_ID' 
AND tr.userid='$_UserID' ORDER BY tr.termname ASC");


$pdf->SetFillColor(255,255,255);
$pdf->SetFont('Arial','B',12);
//Header starts //
$pdf->Cell($width_cell[0],10,'*',1,0,'C',true);

//First header column //
$pdf->Cell($width_cell[1],10,'ITEM',1,0,'C',true);

//$pdf->Cell($width_cell[2],10,'AMOUNT',1,0,'C',true);
$pdf->Cell($width_cell[2],10,'PAYMENT',1,0,'C',true);

$pdf->Cell($width_cell[3],10,'PAYMENT DATE/TIME',1,0,'C',true);
$pdf->Ln($n);

@$_Total_Amount_Paid=0;
@$_Total_amount_To_Pay=0;

while($row_tr=mysqli_fetch_array($_SQL_TERM,MYSQLI_ASSOC))
{
//$pdf->Cell($text_length,$text_height,"Term: ".$row_tr['termname'],0,0,'L',true);
$pdf->Cell($width_cell[0]+$width_cell[1],10,"Semester: ".$row_tr['termname'],1,0,'L',true);
//$pdf->Ln(10);

$_SQL_BIL=mysqli_query($con,"SELECT SUM(ip.price) AS Total_Amount,transactionid FROM tblbilling bi INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid WHERE bi.userid='$_UserID' AND ip.class_entryid='$row_tr[class_entryid]' AND ip.term='$row_tr[termname]' AND bi.billid='$_Bill_Term'");
if($row_b=mysqli_fetch_array($_SQL_BIL,MYSQLI_ASSOC)){
$pdf->Cell($width_cell[2]+$width_cell[3],10,"AMOUNT TO PAY: $_SESSION[SYMBOL] ".$row_b['Total_Amount'],1,0,'R',true);
$_Total_amount_To_Pay=$row_b['Total_Amount'];
$_TransactionId_Bi=$row_b['transactionid'];
}

      //$pdf->SetTextColor(0);
$pdf->SetFont('Arial','B',12);
$pdf->Ln(10);

$_SQL_EXECUTE_3="SELECT * FROM tblbilling bi 
INNER JOIN tblsystemuser su ON bi.userid=su.userid 
INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid
INNER JOIN tblitem itm ON ip.itemid=itm.itemid
INNER JOIN tblclassentry ce ON ip.class_entryid=ce.class_entryid
INNER JOIN tblbatch b ON ip.batch=b.batchid
INNER JOIN tblpayment pm ON pm.billid=bi.billid
WHERE bi.userid='$_UserID' AND ip.class_entryid='$row_class[class_entryid]' AND bi.billid='$_Bill_Term'
AND ip.term='$row_tr[termname]' ORDER BY pm.datetimepayment DESC";
//$_SQL_EXECUTE_3_r=$_SQL_EXECUTE_3;


@$serial=0;
@$_Total_Amount_single=0;
@$_Total_Amount_Paid_Single=0;
@$_Total_Balance_Single=0;

//while($row_p=mysqli_fetch_array($_SQL_EXECUTE_3,MYSQLI_ASSOC)){


///header ends///
$pdf->SetFont('Arial','',10);
//Background color of header //
$pdf->SetFillColor(255,255,255);
//to give alternate background fill color to rows//
$fill =false;

@$_AdditionalPrice=0;

@$serial=0;
//each record is one row //
foreach ($dbo->query($_SQL_EXECUTE_3) as $row_p) 
{

$pdf->Cell($width_cell[0],10,$serial=$serial+1,1,0,'C',$fill);
//$pdf->Ln(10);
  //$pdf->Cell($width_cell[0],10,"Gross Salary",1,0,'C',$fill);
$pdf->Cell($width_cell[1],10,$row_p['itemname'],1,0,'L',$fill);
//$pdf->Ln(10);

//$_Total_Amount=$_Total_Amount+$row_p['price'];
//$_Total_Amount_single=$_Total_Amount_single+$row_p['price'];
//$pdf->Cell($width_cell[2],10,$row_p['price'],1,0,'C',$fill);
//$pdf->Ln(10);
				
$pdf->Cell($width_cell[2],10,$row_p['payment'],1,0,'C',$fill);
$_Total_Amount_Paid_Single=@$_Total_Amount_Paid_Single + $row_p['payment'];
//$pdf->Ln(10);
$pdf->Cell($width_cell[3],10,$row_p['datetimepayment'],1,0,'C',$fill);

/*$_Balance=$row_p['price']-$row_p['payment'];
$_Total_Balance=$_Total_Balance+$_Balance;
$_Total_Balance_Single=$_Total_Balance_Single+$_Balance;
$pdf->Cell($width_cell[4],10,$_Balance,1,0,'C',$fill);
*/
$fill = !$fill;
$pdf->Ln(10);
}

//Footer of the table
 //$pdf->Cell($width_cell[0],10,'',1,0,'C',true);
// $pdf->Cell($width_cell[0],10,'',1,0,'C',true);
$pdf->Cell($width_cell[0]+$width_cell[1],10,'Sub Total:',1,0,'R',true);
//$pdf->Cell($width_cell[2],10,$_Total_Amount_single,1,0,'C',true);
$pdf->Cell($width_cell[2],10,$_Total_Amount_Paid_Single,1,0,'C',true);
$_Total_Amount_Paid=$_Total_Amount_Paid+$_Total_Amount_Paid_Single;

$pdf->Cell($width_cell[3],10,"",1,0,'C',true);
$pdf->Ln(10); 
}
$pdf->SetFont('Arial','B',10);
$pdf->Cell($width_cell[0]+$width_cell[1],10,'GRAND TOTAL:',1,0,'R',true);
//$pdf->Cell($width_cell[2],10,$_SESSION['SYMBOL']." ".$_Total_Amount,1,0,'C',true);
$pdf->Cell($width_cell[2],10,$_SESSION['SYMBOL']." ".$_Total_Amount_Paid,1,0,'C',true);
$pdf->Cell($width_cell[3],10,"BALANCE: ".$_SESSION['SYMBOL']." ".($_Total_amount_To_Pay-$_Total_Amount_Paid),1,0,'C',true);
$pdf->Ln(15); 
}
/*$pdf->Cell($width_cell[2]+$width_cell[3],10,'Grand Total: Ghc',1,0,'C',true);
$pdf->Cell($width_cell[4],10,$_GrandTotal,1,0,'C',true);
*/
$pdf->SetFont('Arial','',8);

$tomorrow = mktime(0,0,0,date("m"),date("d"),date("Y"));
$tdate= date("d/m/Y", $tomorrow);
$pdf->SetFillColor(0,0,0);
//$pdf->PutLink("http://www.braintechconsult.com","BTC");
//$pdf->Ln(10);                    //$pdf->SetStyle('I',true);       
//$pdf->Cell(0,10,'Print Date/Time: '.$tdate .','.$todayTime,0);
$pdf->Cell(0,10,'Print Date/Time: '.$tdate,0);

//$pdf->Ln(10);                    //$pdf->SetStyle('I',true);       
//$pdf->Cell(0,10,'Paid By: '.$_Receivedby,0);
 $pdf->Ln(10); 
$_SQL_AC=mysqli_query($con,"SELECT * FROM tbltransaction tr 
  INNER JOIN tblsystemuser su ON tr.recordedby=su.userid 
  WHERE tr.transactionid='$_TransactionId_Bi'");
 if($row_bi=mysqli_fetch_array($_SQL_AC,MYSQLI_ASSOC)){
 $pdf->Cell(0,10,'ADMINISTRATOR:'. strtoupper($row_bi['firstname']." ".$row_bi['othernames']." ".$row_bi['surname']),0);
 }

 $pdf->Ln(10); 
 $pdf->Cell(0,10,'SIGNATURE:.........................................................................',0);

/* $pdf->Ln(8); 

$pdf->SetFont('Arial','B',8);

 $pdf->Ln(14); 
 $pdf->Cell(0,10,'Developed by: Brainstorm Technologies Consult',0);
 $pdf->Ln(8); 
 $pdf->Cell(0,10,'Accra,Takoradi,Koforidua - 0342-292-121',0);
/// end of records ///
 $pdf->Ln(50);
 */

//}
$pdf->Output();
 //ob_end_flush(); 
 //}
}
?>


<?php
if(isset($_POST['pay_bill']))
{
    $_ScopeClass = isset($_POST['scope_classid']) ? trim((string)$_POST['scope_classid']) : "";
    $_ScopeBatch = isset($_POST['scope_batchid']) ? trim((string)$_POST['scope_batchid']) : "";
    $_ScopeTerm = isset($_POST['scope_termid']) ? (int)$_POST['scope_termid'] : 0;
    $_ReturnUrl = payments_page_anchor_url(isset($_POST['userid']) ? (string)$_POST['userid'] : '', $_ScopeClass, $_ScopeBatch, (string)$_ScopeTerm);
    if($paymentsTeacherHasModule && !payments_teacher_scope_allowed($con, true, $_ScopeClass, $_ScopeBatch, $_ScopeTerm)){
        $_SESSION['Message'] = "<div style='color:red;background-color:white;padding:8px;' align='left'><i class='fa fa-times' style='color:red'></i> You are not assigned to collect payment for that class, batch, and semester.</div>";
        header("location:".$_ReturnUrl);
        exit();
    }
	$_Amount_Paid=$_POST['payment'];
	$_User_ID=$_POST['userid'];
	$_ItemPriceId=$_POST['item-price-id'];
	$_Transaction_Id=$_POST['transactioncode'];
	$_Bill_Id=$_POST['bill_id'];
    if($paymentsTeacherHasModule && !teacher_billing_itemprice_is_allowed($con, $paymentsCurrentUserId, $_ScopeClass, $_ScopeBatch, $_ScopeTerm, $_ItemPriceId)){
        $_SESSION['Message'] = "<div style='color:red;background-color:white;padding:8px;' align='left'><i class='fa fa-times' style='color:red'></i> That billing item is not part of the fee-collection items assigned to you for this class.</div>";
        header("location:".$_ReturnUrl);
        exit();
    }

@$_Bill_Payment=0;
if($_Amount_Paid<=0){
$_SESSION['Message']=$_SESSION['Message']."<div style='color:red;background-color:white;padding:8px;' align='left'><i class='fa fa-times' style='color:red'></i> No amount has been entered</div>";
header("location:".$_ReturnUrl);
exit();
}
else{
//Check if balance is zero
$_SQL_CHECK_BALANCE_1=mysqli_query($con,"SELECT sum(payment) AS payment FROM tblbilling bi INNER JOIN tblpayment pm 
ON bi.billid=pm.billid
WHERE bi.userid='$_User_ID' AND bi.itempriceid='$_ItemPriceId'");	
if($row_1=mysqli_fetch_array($_SQL_CHECK_BALANCE_1,MYSQLI_ASSOC)){
$_Bill_Payment=$row_1['payment']+$_Amount_Paid;
//$_Bill_Id=$row_1['billid'];
//echo "Bill P".$_Bill_Payment ."<br/>";
}
$_SQL_CHECK_BALANCE_2=mysqli_query($con,"SELECT * FROM tblitemprice 
WHERE itempriceid='$_ItemPriceId'");	
if($row_2=mysqli_fetch_array($_SQL_CHECK_BALANCE_2,MYSQLI_ASSOC)){
@$_Actual_Payment=$row_2['price'];
@$_ItemId=$row_2['itemid'];
//echo "Actual".$_Actual_Payment;
}
if($_Bill_Payment>$_Actual_Payment){
$_SQL_Item=mysqli_query($con,"SELECT * FROM tblitem WHERE itemid='$_ItemId'");
if($row_item=mysqli_fetch_array($_SQL_Item,MYSQLI_ASSOC)){
@$_ItemName=$row_item['itemname'];
}
$_SESSION['Message']=$_SESSION['Message']."<div style='color:red;background-color:white;padding:8px;' align='left'><i class='fa fa-times' style='color:red'></i> Student has finished paying for the $_ItemName or Amount entered is more than the balance</div>";
header("location:".$_ReturnUrl);
exit();
}
else
{
$_SQL_Item_2=mysqli_query($con,"SELECT * FROM tblitem WHERE itemid='$_ItemId'");
if($row_item_2=mysqli_fetch_array($_SQL_Item_2,MYSQLI_ASSOC)){
@$_ItemName_2=$row_item_2['itemname'];
}
include("code.php");
@$_Payment_Id=$code;

$_SQL_Bill_Pay=mysqli_query($con,"INSERT INTO tblpayment(paymentid,userid,billid,transactionid,payment,datetimepayment,recordedby,status,referenceid)
		VALUES('$_Payment_Id','$_User_ID','$_Bill_Id','$_Transaction_Id','$_Amount_Paid',NOW(),'$_SESSION[USERID]','active','$_SESSION[PAYMENTACCOUNT]')");
	if($_SQL_Bill_Pay){

$_SQL_B=mysqli_query($con,"INSERT INTO accountingbookentries(accountId,cr,created,createdBy,dr,modifiedBy,narration,particulars,refAccountId,transactionId)
VALUES('$_SESSION[PAYMENTACCOUNT]',0,NOW(),'$_SESSION[USERID]','$_Amount_Paid','','Payment','Payment','$_User_ID','$_Transaction_Id')");
if($_SQL_B){}


	//$_SQL_Payment=mysqli_query($con,"UPDATE tblpayment SET payment=payment+'$_Amount_Paid' 
	//	WHERE userid='$_User_ID' AND transactionid='$_Transaction_Id'");	
	//if($_SQL_Payment){

   //SEND SMS ALERT: SHORT MESSAGE OF THE CUSTOMER'S MESSAGE
                    $_SQLCl=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE userid='$_User_ID'");
                    @$_StudentName="";
                    @$SMSIsEnable=0;
                
                    if($rowcl=mysqli_fetch_array($_SQLCl,MYSQLI_ASSOC)){
                      $_StudentName=$rowcl['firstname']." ".$rowcl['othernames']." ".$rowcl['surname']."(".$rowcl['userid'].")";
                      //$_ClientName=substr($_, start)
                      $Receiver_Mobile=$rowcl['nextofkin_contact'];
                      $SMSIsEnable=$rowcl['smsalert'];
                    }
                    //GET TOTAL SALES FOR TODAY
                    @$_TotalDailySales=0;
                    $_SQLTOTAL=mysqli_query($con,"SELECT SUM(op.payment) AS DailyTotalSales FROM tblpayment op WHERE date_format(op.datetimepayment,'%d-%m-%Y')=date_format(NOW(),'%d-%m-%Y')");
                    if($rowdts=mysqli_fetch_array($_SQLTOTAL,MYSQLI_ASSOC)){
                      $_TotalDailySales=$rowdts["DailyTotalSales"];
                    } 
                    else{
                     $_Error=mysqli_error($con);
                    echo "<div style='color:red'>Failed to get Total Sales</div>";
                    }
                    $_today=date("d")."-".date("m")."-".date("Y")." ".date("H:i:s A",time());
                    //SMS to manager
                    //$ShortTransactionMsg="$_StudentName paid GHC$_Amount_Paid at $_today.\nCurrent Sales is GHC$_TotalDailySales\n Received by $_SESSION[USERID]";
                    
                    //SMS to student
                    $ShortTransactionMsg="$_StudentName paid GHC$_Amount_Paid at $_today";
                    if($SMSIsEnable==1){
                   
                      $phone=$Receiver_Mobile;
                      $message=$ShortTransactionMsg;
                      include("bulksms/bulksms.php");
                    }

	         $_SESSION['Message']="<div style='color:green;text-align:left;background-color:white;padding:8px;'><i class='fa fa-check' style='color:green'></i> Amount of $_Amount_Paid $_SESSION[CURRENCY] Successfully Paid for $_ItemName_2</div>";
             header("location:".$_ReturnUrl);
             exit();
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>Failed to pay,$_Error</div>";
        header("location:".$_ReturnUrl);
        exit();
	
	}
	}
}
}
?>
<?php
include("dbstring.php");
//@$_ClassId=$_POST['classid'];
@$_UserId=$_POST['userid'];
@$_Class=$_POST['class'];
@$_Batch=$_POST['batch'];
@$_Term=$_POST['term'];
@$_Recordedby=$_SESSION['USERID'];
//echo $_SESSION['USERID'];

if(isset($_POST['bill_student'])){
if($paymentsTeacherHasModule){
    $_SESSION['Message']=$_SESSION['Message']."<div style='color:red;background-color:white;padding:8px;' align='left'><i class='fa fa-times' style='color:red'></i> Teachers can collect payments only. Billing items remain admin-only.</div>";
}
else{
//Create payment container
include("code.php");
@$_Payment_Id=$code;
@$_Transaction_Code=$transaction_id;

$_SQL_Payment=mysqli_query($con,"INSERT INTO tblpayment(paymentid,userid,transactionid,payment,datetimepayment,recordedby,status,paymentdatetime)
	VALUES('$_Payment_Id','$_UserId','$_Transaction_Code',0,NOW(),'$_SESSION[USERID]','active',NOW())");
if($_SQL_Payment){
@$_TransId=$_Transaction_Code;
}

$_SQL_EXECUTE_2=mysqli_query($con,"SELECT * FROM tbltermregistry tr 
				INNER JOIN tblitemprice ip ON tr.class_entryid=ip.class_entryid AND tr.termname=ip.term
				INNER JOIN tblitem itm ON ip.itemid=itm.itemid
				INNER JOIN tblclassentry ce ON ce.class_entryid=tr.class_entryid
				WHERE tr.userid='$_UserId' AND ip.class_entryid='$_Class' AND ip.term='$_Term' AND ip.batch='$_Batch'");

while($row_b=mysqli_fetch_array($_SQL_EXECUTE_2,MYSQLI_ASSOC))
{
include("code.php");
@$_BillId=$code;

$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblbilling(billid,userid,itempriceid,transactionid,datetimebilled,recordedby,status)
	VALUES('$_BillId','$_UserId','$row_b[itempriceid]','$_TransId',NOW(),'$_Recordedby','active')");
if($_SQL_EXECUTE){
	$_SESSION['Message']=$_SESSION['Message']."<div style='color:green;text-align:center;background-color:white'>$_BillId Successfully Created</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>$_BillId failed to create,$_Error</div>";
	}
}
}
}
?>

<html>
<head>
<?php
include("links.php");
?>

<script type="text/javascript">
function SearchItem(str)
{
  if(str=="")
  {
  document.getElementById("search-result").innerHTML="";
  return;
  }
  else
  {
    if(window.XMLHttpRequest)
    {
      xmlhttp = new XMLHttpRequest();
    }
    else
    {
      xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
    }
    xmlhttp.onreadystatechange = function()
    {
      if(this.readyState==4 && this.status==200)
      {
        document.getElementById("search-result").innerHTML = this.responseText;
      }
    };
    xmlhttp.open("GET","display-student.php?search-item="+str,true);
    xmlhttp.send();
  }
}
</script>


<script>
  var rnd;
function getItemID()
{
rnd=Math.floor( Math.random()*100000000);
document.getElementById("item-id").value=rnd;
}
</script>

<script type="text/javascript">
var gbatch;
function getBatch()
{
gbatch=getElementById("batch").value;
 //return _batch;  
}
function getStudentBill(str)
{
	if(str=="")
  {
  
  document.getElementById("search-result").innerHTML="";
  return;
  }
  else
  {
    if(window.XMLHttpRequest)
    {
      xmlhttp = new XMLHttpRequest();
    }
    else
    {
      xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
    }
    xmlhttp.onreadystatechange = function()
    {
      if(this.readyState==4 && this.status==200)
      {
        document.getElementById("search-result").innerHTML = this.responseText;
      }
    };
    xmlhttp.open("GET","display-class-bill.php?search-bill="+str+"&batch="+gbatch,true);
    xmlhttp.send();
  }
}
</script>
</head>
<body>
	<div class="header">
		<!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
	<?php
	include("menu.php");
	?>		

	</div><br/>
<div class="form-entry" style="background-color:transparent">
	<br/><br/>
	<table width="100%" style="background-color:white">
		<tr>

			<td width="100%">
				<?php
				echo "<div style='padding:8px;color:green'>$_SESSION[Message]</div>";
				?>
				<?php
				$_ScopeSelectedBatch = trim((string)($_POST['scope_sms_batch_id'] ?? ($_GET['scope_batch_id'] ?? '')));
				$_ScopeSelectedClass = trim((string)($_POST['scope_sms_class_id'] ?? ($_GET['scope_class_id'] ?? '')));
				$_ScopeSelectedTerm = trim((string)($_POST['scope_sms_term_id'] ?? ($_GET['scope_term_id'] ?? '')));
				if($paymentsTeacherHasModule && $_ScopeSelectedBatch === '' && $_ScopeSelectedClass === '' && $_ScopeSelectedTerm === '' && !empty($paymentsTeacherAssignments)){
					$_ScopeSelectedBatch = trim((string)($paymentsTeacherAssignments[0]['batchid'] ?? ''));
					$_ScopeSelectedClass = trim((string)($paymentsTeacherAssignments[0]['classid'] ?? ''));
					$_ScopeSelectedTerm = trim((string)($paymentsTeacherAssignments[0]['termname'] ?? ''));
				}

				$_ScopePanelTitle = $paymentsTeacherHasModule ? "Assigned Class Payments" : "Bulk Parent Balance SMS";
				$_ScopePanelText = $paymentsTeacherHasModule
					? "Load the class, batch, and semester assigned to you, then collect student payments from that scope."
					: "Choose a batch, then optionally narrow it to one class or one semester.";
				$_ScopeFormAction = payments_page_url(isset($_GET['userid']) ? (string)$_GET['userid'] : '');
				$_ScopeTermOptions = array();
				if($paymentsTeacherHasModule && $_ScopeSelectedClass !== '' && $_ScopeSelectedBatch !== ''){
					$_ScopeTermOptions = teacher_billing_terms_for_pair($con, $paymentsCurrentUserId, $_ScopeSelectedClass, $_ScopeSelectedBatch);
				}
				if($paymentsTeacherHasModule && empty($_ScopeTermOptions)){
					foreach($paymentsTeacherAssignments as $_assignmentRow){
						$_TermValue = (int)($_assignmentRow['termname'] ?? 0);
						if($_TermValue > 0){
							$_ScopeTermOptions[$_TermValue] = $_TermValue;
						}
					}
					$_ScopeTermOptions = array_values($_ScopeTermOptions);
				}

				echo "<div style='background-color:#f8fafc;border:1px solid #cbd5e1;border-radius:12px;padding:14px;margin:0 0 16px 0;'>";
				echo "<div style='display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px;align-items:center;margin-bottom:10px;'>";
				echo "<div style='font-weight:bold;color:#0f172a'>".payments_sms_safe($_ScopePanelTitle)."</div>";
				echo "<div style='color:#475569'>".payments_sms_safe($_ScopePanelText)."</div>";
				echo "</div>";
				echo "<form method='post' action='".payments_sms_safe($_ScopeFormAction)."' style='display:flex;flex-wrap:wrap;gap:10px;align-items:end;'>";

				echo "<div style='min-width:180px;flex:1 1 180px;'>";
				echo "<label style='display:block;font-weight:bold;margin-bottom:4px;'>Batch</label>";
				echo "<select name='scope_sms_batch_id' style='width:100%;padding:8px;' required>";
				echo "<option value=''>Select Batch</option>";
				if($paymentsTeacherHasModule){
					foreach($paymentsScopeBatchRows as $rowBatchList){
						$_Selected = ($_ScopeSelectedBatch === (string)$rowBatchList['batchid']) ? " selected" : "";
						echo "<option value='".payments_sms_safe((string)$rowBatchList['batchid'])."'".$_Selected.">".payments_sms_safe((string)$rowBatchList['batch'])."</option>";
					}
				}else{
					$_BatchListSql = mysqli_query($con, "SELECT batchid,batch FROM tblbatch ORDER BY batch DESC");
					while($_BatchListSql && ($rowBatchList = mysqli_fetch_array($_BatchListSql, MYSQLI_ASSOC))){
						$_Selected = ($_ScopeSelectedBatch === (string)$rowBatchList['batchid']) ? " selected" : "";
						echo "<option value='".payments_sms_safe((string)$rowBatchList['batchid'])."'".$_Selected.">".payments_sms_safe((string)$rowBatchList['batch'])."</option>";
					}
				}
				echo "</select>";
				echo "</div>";

				echo "<div style='min-width:180px;flex:1 1 180px;'>";
				echo "<label style='display:block;font-weight:bold;margin-bottom:4px;'>Class</label>";
				echo "<select name='scope_sms_class_id' style='width:100%;padding:8px;'".($paymentsTeacherHasModule ? " required" : "").">";
				echo "<option value=''>".($paymentsTeacherHasModule ? "Select Assigned Class" : "All Classes In Batch")."</option>";
				if($paymentsTeacherHasModule){
					foreach($paymentsScopeClassRows as $rowClassList){
						$_Selected = ($_ScopeSelectedClass === (string)$rowClassList['class_entryid']) ? " selected" : "";
						echo "<option value='".payments_sms_safe((string)$rowClassList['class_entryid'])."'".$_Selected.">".payments_sms_safe((string)$rowClassList['class_name'])."</option>";
					}
				}else{
					$_ClassListSql = mysqli_query($con, "SELECT class_entryid,class_name FROM tblclassentry ORDER BY class_name ASC");
					while($_ClassListSql && ($rowClassList = mysqli_fetch_array($_ClassListSql, MYSQLI_ASSOC))){
						$_Selected = ($_ScopeSelectedClass === (string)$rowClassList['class_entryid']) ? " selected" : "";
						echo "<option value='".payments_sms_safe((string)$rowClassList['class_entryid'])."'".$_Selected.">".payments_sms_safe((string)$rowClassList['class_name'])."</option>";
					}
				}
				echo "</select>";
				echo "</div>";

				echo "<div style='min-width:180px;flex:1 1 180px;'>";
				echo "<label style='display:block;font-weight:bold;margin-bottom:4px;'>Semester</label>";
				echo "<select name='scope_sms_term_id' style='width:100%;padding:8px;'".($paymentsTeacherHasModule ? " required" : "").">";
				if(!$paymentsTeacherHasModule){
					echo "<option value=''>All Semesters</option>";
					echo "<option value='1'".($_ScopeSelectedTerm === "1" ? " selected" : "").">Semester 1</option>";
					echo "<option value='2'".($_ScopeSelectedTerm === "2" ? " selected" : "").">Semester 2</option>";
				}else{
					echo "<option value=''>Select Assigned Semester</option>";
					foreach($_ScopeTermOptions as $_AllowedTerm){
						$_Selected = ($_ScopeSelectedTerm === (string)$_AllowedTerm) ? " selected" : "";
						echo "<option value='".payments_sms_safe((string)$_AllowedTerm)."'".$_Selected.">Semester ".payments_sms_safe((string)$_AllowedTerm)."</option>";
					}
				}
				echo "</select>";
				echo "</div>";

				echo "<div>";
				echo "<button type='submit' id='view_scope_balances' name='view_scope_balances' class='button-pay'".(!$paymentsTeacherHasScope ? " disabled" : "")."><i class='fa fa-table' style='color:yellow'></i> ".($paymentsTeacherHasModule ? "Load Class Payments" : "View Balances")."</button>";
				if(!$paymentsTeacherHasModule){
					echo "<button type='submit' id='send_scope_balance_sms' name='send_scope_balance_sms' class='button-pay' style='margin-left:8px;' onclick=\"return confirm('Send balance SMS to all matching parents with outstanding balances?');\"><i class='fa fa-bullhorn' style='color:yellow'></i> Send Bulk Balance SMS</button>";
				}
				echo "</div>";
				echo "</form>";
				echo "</div>";

				$_ScopeShowResults = false;
				$_ScopeCanLoad = true;
				if($paymentsTeacherHasModule){
					if($_ScopeSelectedBatch !== '' && $_ScopeSelectedClass !== '' && $_ScopeSelectedTerm !== ''){
						$_ScopeCanLoad = payments_teacher_scope_allowed($con, true, $_ScopeSelectedClass, $_ScopeSelectedBatch, (int)$_ScopeSelectedTerm);
						$_ScopeShowResults = $_ScopeCanLoad;
					}
					if(!$paymentsTeacherHasScope){
						echo "<div style='color:#b45309;background-color:white;padding:8px;border-radius:8px;margin:0 0 16px 0;' align='left'><i class='fa fa-info-circle' style='color:#b45309'></i> Admin has not assigned any fee-collection class to you yet.</div>";
					}elseif(!$_ScopeCanLoad){
						echo "<div style='color:red;background-color:white;padding:8px;border-radius:8px;margin:0 0 16px 0;' align='left'><i class='fa fa-times' style='color:red'></i> The selected class scope is not part of your billing assignment.</div>";
					}
				}else{
					$_ScopeShowResults = ($_ScopeSelectedBatch !== "" && (isset($_POST['view_scope_balances']) || isset($_POST['send_scope_balance_sms'])));
				}

				if($_ScopeShowResults){
                    $_ScopeAllowedItemIds = array();
                    if($paymentsTeacherHasModule){
                        $_ScopeAllowedItemIds = teacher_billing_allowed_itemprice_ids($con, $paymentsCurrentUserId, $_ScopeSelectedClass, $_ScopeSelectedBatch, (int)$_ScopeSelectedTerm);
                        payments_ensure_billing_for_scope($con, $_ScopeSelectedClass, $_ScopeSelectedBatch, (int)$_ScopeSelectedTerm, $_ScopeAllowedItemIds, $paymentsCurrentUserId);
                    }
					$_ScopeData = payments_balance_scope_data($con, $_ScopeSelectedBatch, $_ScopeSelectedClass, $_ScopeSelectedTerm, $_ScopeAllowedItemIds);
					$_ScopeRows = isset($_ScopeData['rows']) && is_array($_ScopeData['rows']) ? $_ScopeData['rows'] : array();
					$_ScopeSummary = isset($_ScopeData['summary']) && is_array($_ScopeData['summary']) ? $_ScopeData['summary'] : array();
					$_CurrencySymbol = isset($_SESSION['SYMBOL']) ? $_SESSION['SYMBOL'] : '';

					$_ScopeBatchLabel = $_ScopeSelectedBatch;
					$_ScopeClassLabel = ($_ScopeSelectedClass !== "" ? $_ScopeSelectedClass : "All Classes");
					$_ScopeTermLabel = ($_ScopeSelectedTerm !== "" ? "Semester ".$_ScopeSelectedTerm : "All Semesters");

					$_ScopeBatchRes = mysqli_query($con, "SELECT batch FROM tblbatch WHERE batchid='".mysqli_real_escape_string($con, $_ScopeSelectedBatch)."' LIMIT 1");
					if($_ScopeBatchRes && ($rowScopeBatch = mysqli_fetch_array($_ScopeBatchRes, MYSQLI_ASSOC))){
						$_ScopeBatchLabel = trim((string)$rowScopeBatch['batch']);
					}
					if($_ScopeSelectedClass !== ""){
						$_ScopeClassRes = mysqli_query($con, "SELECT class_name FROM tblclassentry WHERE class_entryid='".mysqli_real_escape_string($con, $_ScopeSelectedClass)."' LIMIT 1");
						if($_ScopeClassRes && ($rowScopeClass = mysqli_fetch_array($_ScopeClassRes, MYSQLI_ASSOC))){
							$_ScopeClassLabel = trim((string)$rowScopeClass['class_name']);
						}
					}

					echo "<div style='background-color:#ffffff;border:1px solid #dbe4ee;border-radius:12px;padding:14px;margin:0 0 16px 0;'>";
					echo "<div style='display:flex;flex-wrap:wrap;justify-content:space-between;gap:12px;align-items:center;margin-bottom:12px;'>";
					echo "<div style='font-weight:bold;color:#0f172a'>Balance View: ".payments_sms_safe($_ScopeClassLabel)." | ".payments_sms_safe($_ScopeTermLabel)." | ".payments_sms_safe($_ScopeBatchLabel)."</div>";
					echo "<div style='color:#475569'>Students: ".number_format((int)($_ScopeSummary['student_count'] ?? 0))." | Owing: ".number_format((int)($_ScopeSummary['owing_count'] ?? 0))."</div>";
					echo "</div>";

					echo "<div style='display:flex;flex-wrap:wrap;gap:10px;margin-bottom:14px;'>";
					echo "<div style='padding:10px 12px;border:1px solid #dbe4ee;border-radius:10px;background:#f8fafc;min-width:170px;'><div style='font-size:12px;color:#64748b;'>Total Billed</div><div style='font-weight:bold;color:#0f172a;'>".payments_sms_safe($_CurrencySymbol)." ".number_format((float)($_ScopeSummary['total_cost'] ?? 0), 2)."</div></div>";
					echo "<div style='padding:10px 12px;border:1px solid #dbe4ee;border-radius:10px;background:#f8fafc;min-width:170px;'><div style='font-size:12px;color:#64748b;'>Total Paid</div><div style='font-weight:bold;color:#0f172a;'>".payments_sms_safe($_CurrencySymbol)." ".number_format((float)($_ScopeSummary['total_paid'] ?? 0), 2)."</div></div>";
					echo "<div style='padding:10px 12px;border:1px solid #dbe4ee;border-radius:10px;background:#fff7ed;min-width:170px;'><div style='font-size:12px;color:#9a3412;'>Outstanding Balance</div><div style='font-weight:bold;color:#9a3412;'>".payments_sms_safe($_CurrencySymbol)." ".number_format((float)($_ScopeSummary['total_balance'] ?? 0), 2)."</div></div>";
                    if($paymentsTeacherHasModule){
                        echo "<div style='padding:10px 12px;border:1px solid #dbe4ee;border-radius:10px;background:#ecfeff;min-width:170px;'><div style='font-size:12px;color:#0f766e;'>Collectible Items</div><div style='font-weight:bold;color:#0f766e;'>".(!empty($_ScopeAllowedItemIds) ? number_format((int)count($_ScopeAllowedItemIds))." selected by admin" : "All billed items in scope")."</div></div>";
                    }
					echo "</div>";

					echo "<div style='overflow:auto;'>";
					echo "<table style='width:100%;background-color:white;border-collapse:collapse;min-width:860px;'>";
					echo "<thead><tr style='background:#eff6ff;'>";
					echo "<th style='padding:10px;border:1px solid #dbe4ee;'>#</th>";
					echo "<th style='padding:10px;border:1px solid #dbe4ee;'>Student</th>";
					echo "<th style='padding:10px;border:1px solid #dbe4ee;'>Class</th>";
					echo "<th style='padding:10px;border:1px solid #dbe4ee;'>Parent Contact</th>";
					echo "<th style='padding:10px;border:1px solid #dbe4ee;'>Billed</th>";
					echo "<th style='padding:10px;border:1px solid #dbe4ee;'>Paid</th>";
					echo "<th style='padding:10px;border:1px solid #dbe4ee;'>Balance</th>";
					echo "<th style='padding:10px;border:1px solid #dbe4ee;'>Status</th>";
					echo "<th style='padding:10px;border:1px solid #dbe4ee;'>Action</th>";
					echo "</tr></thead><tbody>";

					if(empty($_ScopeRows)){
						echo "<tr><td colspan='9' style='padding:14px;text-align:center;color:#64748b;border:1px solid #dbe4ee;'>No students matched this balance scope.</td></tr>";
					}else{
						$_ScopeIndex = 0;
						foreach($_ScopeRows as $_ScopeRow){
							$_ScopeIndex++;
							$_ScopeUserId = (string)($_ScopeRow['userid'] ?? '');
							$_ScopeStudentName = trim((string)($_ScopeRow['firstname'] ?? '')." ".(string)($_ScopeRow['othernames'] ?? '')." ".(string)($_ScopeRow['surname'] ?? ''));
							if($_ScopeStudentName === ""){
								$_ScopeStudentName = $_ScopeUserId;
							}
							$_ScopeBalance = (float)($_ScopeRow['balance'] ?? 0);
							$_ScopeStatus = $_ScopeBalance > 0 ? "Outstanding" : "Cleared";
							$_ScopeStatusColor = $_ScopeBalance > 0 ? "#b45309" : "#15803d";
							echo "<tr>";
							echo "<td style='padding:10px;border:1px solid #e5e7eb;text-align:center;'>".$_ScopeIndex."</td>";
							echo "<td style='padding:10px;border:1px solid #e5e7eb;'>".payments_sms_safe(strtoupper($_ScopeStudentName))." <br><small style='color:#64748b;'>".payments_sms_safe($_ScopeUserId)."</small></td>";
							echo "<td style='padding:10px;border:1px solid #e5e7eb;'>".payments_sms_safe((string)($_ScopeRow['class_name'] ?? '-'))."</td>";
							echo "<td style='padding:10px;border:1px solid #e5e7eb;'>".payments_sms_safe((string)($_ScopeRow['nextofkin_contact'] ?? ''))."</td>";
							echo "<td style='padding:10px;border:1px solid #e5e7eb;text-align:right;'>".payments_sms_safe($_CurrencySymbol)." ".number_format((float)($_ScopeRow['total_cost'] ?? 0), 2)."</td>";
							echo "<td style='padding:10px;border:1px solid #e5e7eb;text-align:right;'>".payments_sms_safe($_CurrencySymbol)." ".number_format((float)($_ScopeRow['total_paid'] ?? 0), 2)."</td>";
							echo "<td style='padding:10px;border:1px solid #e5e7eb;text-align:right;font-weight:bold;color:".$_ScopeStatusColor.";'>".payments_sms_safe($_CurrencySymbol)." ".number_format($_ScopeBalance, 2)."</td>";
							echo "<td style='padding:10px;border:1px solid #e5e7eb;text-align:center;color:".$_ScopeStatusColor.";font-weight:bold;'>".$_ScopeStatus."</td>";
							echo "<td style='padding:10px;border:1px solid #e5e7eb;text-align:center;'><a href='".payments_sms_safe(payments_page_anchor_url($_ScopeUserId, $_ScopeSelectedClass, $_ScopeSelectedBatch, $_ScopeSelectedTerm))."' class='button-pay' style='display:inline-block;text-decoration:none;'><i class='fa fa-credit-card' style='color:yellow'></i> Open</a></td>";
							echo "</tr>";
						}
					}

					echo "</tbody></table>";
					echo "</div>";
					echo "</div>";
				}
				?>
				<?php
				//echo "<form id='form' method='post' action='payments.php'>"; 
				//echo "<div class='form-entry'>";
			//	echo "<input type='text' id='search-item' name='search-item' placeholder='Search Student by Index Number or First Name or Surname or Othernames' onkeyup='SearchItem(this.value)'/>";
				//echo " </div><br/>";   
				//echo "</form>";
				//echo "<form id='formID' method='post' action='pointofsale.php'>"; 
			//	echo "<div id='search-result' name='search-result'></div>";
				//echo "</form>";
				?>

	<?php

if(isset($_GET['userid']))
{
	@$_Get_UserID="";
	@$_Get_Class="";
	@$_Get_TermId="";
	$_DetailScopeClass = trim((string)($_GET['scope_class_id'] ?? ''));
	$_DetailScopeBatch = trim((string)($_GET['scope_batch_id'] ?? ''));
	$_DetailScopeTerm = trim((string)($_GET['scope_term_id'] ?? ''));
    $_DetailAllowedItemIds = ($paymentsTeacherHasModule && $_DetailScopeClass !== '' && $_DetailScopeBatch !== '' && $_DetailScopeTerm !== '')
        ? teacher_billing_allowed_itemprice_ids($con, $paymentsCurrentUserId, $_DetailScopeClass, $_DetailScopeBatch, (int)$_DetailScopeTerm)
        : array();
	if($paymentsTeacherHasModule && !payments_teacher_scope_allowed($con, true, $_DetailScopeClass, $_DetailScopeBatch, (int)$_DetailScopeTerm)){
		echo "<div style='color:red;background-color:white;padding:8px;border-radius:8px;margin:0 0 14px 0;' align='left'><i class='fa fa-times' style='color:red'></i> You can only open student payments inside the class, batch, and semester assigned to you.</div>";
	}
	else{
    if($paymentsTeacherHasModule && $_DetailScopeClass !== '' && $_DetailScopeBatch !== '' && $_DetailScopeTerm !== ''){
        payments_ensure_billing_for_student_scope($con, $_GET['userid'], $_DetailScopeClass, $_DetailScopeBatch, (int)$_DetailScopeTerm, $_DetailAllowedItemIds, $paymentsCurrentUserId);
    }

	@$_FullName="";
	$_SQL_SU=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE userid='$_GET[userid]'");
		if($row_su=mysqli_fetch_array($_SQL_SU,MYSQLI_ASSOC)){
		$_FullName=$row_su['firstname']." ".$row_su['othernames']." ".$row_su['surname']."(".$row_su['userid'].")";
			}

$_OverallTotals = payments_student_billing_totals(
	$con,
	$_GET['userid'],
	($paymentsTeacherHasModule ? $_DetailScopeClass : ""),
	($paymentsTeacherHasModule ? $_DetailScopeTerm : ""),
	($paymentsTeacherHasModule ? $_DetailScopeBatch : ""),
    ($paymentsTeacherHasModule ? $_DetailAllowedItemIds : array())
);
$_OverallBilledText = (isset($_SESSION['SYMBOL']) ? $_SESSION['SYMBOL']." " : "").number_format((float)$_OverallTotals['total_cost'], 2);
$_OverallPaidText = (isset($_SESSION['SYMBOL']) ? $_SESSION['SYMBOL']." " : "").number_format((float)$_OverallTotals['total_paid'], 2);
$_OverallBalanceText = (isset($_SESSION['SYMBOL']) ? $_SESSION['SYMBOL']." " : "").number_format((float)$_OverallTotals['balance'], 2);

echo "<div id='payment-interface' style='display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:12px;background-color:#eef6ff;border:1px solid #bfdbfe;border-radius:12px;padding:12px 14px;margin:0 0 14px 0;scroll-margin-top:100px;'>";
echo "<div style='color:#1e3a8a;font-weight:bold'>Overall Billed: ".payments_sms_safe($_OverallBilledText)." | Paid: ".payments_sms_safe($_OverallPaidText)." | Balance: ".payments_sms_safe($_OverallBalanceText)."</div>";
if(!$paymentsTeacherHasModule){
echo "<form method='post' action='payments.php?userid=".urlencode((string)$_GET['userid'])."' style='margin:0;'>";
echo "<input type='hidden' name='sms_userid' value='".payments_sms_safe((string)$_GET['userid'])."'>";
echo "<button type='submit' class='button-pay' id='send_balance_sms_overall' name='send_balance_sms'><i class='fa fa-envelope' style='color:yellow'></i> Text Parent Balance</button>";
echo "</form>";
}
echo "</div>";

include("dbstring.php");
echo "<table style='background-color:white'>";
echo "<caption>Hi, $_FullName do you want to make payment?</caption>";
echo "<thead><th>*</th><th>ITEM</th><th>AMOUNT</th><th>PAID</th><th>BALANCE</th><th>PAYMENT</th><th colspan='2'>ACTION</th></thead>";
echo "<tbody>";

$_ClassScopeWhere = "";
if($paymentsTeacherHasModule){
	$_ClassScopeWhere .= " AND class_entryid='".mysqli_real_escape_string($con, $_DetailScopeClass)."'";
	$_ClassScopeWhere .= " AND batchid='".mysqli_real_escape_string($con, $_DetailScopeBatch)."'";
}
$_SQL_CR=mysqli_query($con,"SELECT * FROM tblclass WHERE userid='$_GET[userid]'".$_ClassScopeWhere);
while($row_cr=mysqli_fetch_array($_SQL_CR,MYSQLI_ASSOC))
{
//Get all the classes regisetred for the studenet
$_Class_Reg_ID=$row_cr['class_entryid'];
$_Batch_Id=$row_cr['batchid'];
				
$_SQL_CLASS=mysqli_query($con,"SELECT * FROM tblclassentry ce WHERE ce.class_entryid='$_Class_Reg_ID' ORDER BY ce.class_name ASC");
while($row_class=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC))
{

echo "<tr>";
echo "<td colspan='12' align='left' style='font-weight:bold'>";
echo strtoupper($row_class['class_name']);
echo "</td>";
echo "</tr>";

$_TermScopeWhere = "";
if($paymentsTeacherHasModule){
	$_TermScopeWhere = " AND tr.termname='".mysqli_real_escape_string($con, $_DetailScopeTerm)."'";
}
$_SQL_TERM=mysqli_query($con,"SELECT * FROM tbltermregistry tr WHERE tr.class_entryid='$row_class[class_entryid]' AND tr.batchid='$_Batch_Id' AND tr.userid='$_GET[userid]'".$_TermScopeWhere." ORDER BY tr.termname ASC");
while($row_tr=mysqli_fetch_array($_SQL_TERM,MYSQLI_ASSOC))
{
$_RowItemPriceFilter = "";
if($paymentsTeacherHasModule && !empty($_DetailAllowedItemIds)){
    $_EscapedItemIds = array();
    foreach($_DetailAllowedItemIds as $_AllowedItemId){
        $_EscapedItemIds[] = "'".mysqli_real_escape_string($con, (string)$_AllowedItemId)."'";
    }
    $_RowItemPriceFilter = " AND ip.itempriceid IN (".implode(",", $_EscapedItemIds).")";
}
echo "<tr>";
echo "<td colspan='12' align='left' style='background-color:#ede;font-weight:bold'>";
echo "SEMESTER: ". $row_tr['termname'];
//echo "<input type='hidden' id='class_id' name='class_id' value='$row_class[class_entryid]' />";
//echo "<input type='hidden' id='term_id' name='term_id' value='$row_tr[termname]' />";
echo "</td>";
echo "</tr>";

				$_SQL_EXECUTE_3=mysqli_query($con,"SELECT * FROM tblbilling bi INNER JOIN tblsystemuser su 
					ON bi.userid=su.userid INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid
					INNER JOIN tblitem itm ON ip.itemid=itm.itemid
					INNER JOIN tblclassentry ce ON ip.class_entryid=ce.class_entryid
					INNER JOIN tblbatch b ON ip.batch=b.batchid
					
				 WHERE bi.userid='$_GET[userid]' AND ip.class_entryid='$row_tr[class_entryid]' AND ip.batch='$_Batch_Id' AND ip.term='$row_tr[termname]'".$_RowItemPriceFilter." ORDER BY ip.term ASC");
				//$_SQL_EXECUTE_3_r=$_SQL_EXECUTE_3;


	/*	if(!mysqli_num_rows($_SQL_EXECUTE_3_r)>0){
			@$_FullName="";
			$_SQL_SU=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE userid='$_GET[userid]'");
			if($row_su=mysqli_fetch_array($_SQL_SU,MYSQLI_ASSOC)){
			$_FullName=$row_su['firstname']." ".$row_su['othernames']." ".$row_su['surname']."(".$row_su['userid'].")";
			}
		echo "<div style='color:red;text-align:center'>There is no bill available for $_FullName</div>";
		}
		else{
			*/
				@$serial=0;

				/*if($row_p_r=mysqli_fetch_array($_SQL_EXECUTE_3_r,MYSQLI_ASSOC)){
				//echo "<tr>";
				//echo "<td colspan='12' style='background-color:#eed;border-bottom:1px solid orange;color:blue;font-weight:bold'>";
				//echo $row_p_r['firstname']." ".$row_p_r['othernames']." ".$row_p_r['surname']." (". $row_p_r['userid'].")";
				//echo "</td>";
				//echo "</tr>";
				}
				*/
				@$_Total_Amount_Paid=0;
				@$_Total_Balance=0;
				@$_Balance=0;
				@$_Total_Amount=0;
				
				while($row_p=mysqli_fetch_array($_SQL_EXECUTE_3,MYSQLI_ASSOC)){
				$_RowFormId = "pay_form_".$serial."_".preg_replace('/[^A-Za-z0-9_]/', '_', (string)$row_p['billid']);
				$_RowPrintFormId = "print_form_".$serial."_".preg_replace('/[^A-Za-z0-9_]/', '_', (string)$row_p['billid']);
				echo "<tr>";
				$_Get_UserID=$row_p['userid'];
				$_Get_Class=$row_class['class_entryid'];
				$_Get_TermId=$row_tr['termname'];
				
				echo "<td align='center'>";
				echo $serial=$serial+1;
				echo "</td>";

				echo "<td>";
				echo "<form id='".payments_sms_safe($_RowFormId)."' method='post' action='".payments_sms_safe(payments_page_url((string)$_GET['userid'], (string)$row_class['class_entryid'], (string)$_Batch_Id, (string)$row_tr['termname']))."' style='display:none;'>";
				echo "<input type='hidden' name='pay_bill' value='1' />";
				echo "<input type='hidden' name='userid' value='".payments_sms_safe((string)$row_p['userid'])."' />";
				echo "<input type='hidden' name='class_id' value='".payments_sms_safe((string)$row_class['class_entryid'])."' />";
				echo "<input type='hidden' name='term_id' value='".payments_sms_safe((string)$row_tr['termname'])."' />";
				echo "<input type='hidden' name='item-price-id' value='".payments_sms_safe((string)$row_p['itempriceid'])."' />";
				echo "<input type='hidden' name='transactioncode' value='".payments_sms_safe((string)$row_p['transactionid'])."' />";
				echo "<input type='hidden' name='bill_id' value='".payments_sms_safe((string)$row_p['billid'])."' />";
				echo "<input type='hidden' name='scope_classid' value='".payments_sms_safe((string)$row_class['class_entryid'])."' />";
				echo "<input type='hidden' name='scope_batchid' value='".payments_sms_safe((string)$_Batch_Id)."' />";
				echo "<input type='hidden' name='scope_termid' value='".payments_sms_safe((string)$row_tr['termname'])."' />";
				echo "</form>";
				if(!$paymentsTeacherHasModule){
					echo "<form id='".payments_sms_safe($_RowPrintFormId)."' method='post' action='".payments_sms_safe(payments_page_url((string)$_GET['userid'], (string)$row_class['class_entryid'], (string)$_Batch_Id, (string)$row_tr['termname']))."' style='display:none;'>";
					echo "<input type='hidden' name='print_bill' value='1' />";
					echo "<input type='hidden' name='userid' value='".payments_sms_safe((string)$row_p['userid'])."' />";
					echo "<input type='hidden' name='class_id' value='".payments_sms_safe((string)$row_class['class_entryid'])."' />";
					echo "<input type='hidden' name='term_id' value='".payments_sms_safe((string)$row_tr['termname'])."' />";
					echo "<input type='hidden' name='bill_id' value='".payments_sms_safe((string)$row_p['billid'])."' />";
					echo "<input type='hidden' name='scope_classid' value='".payments_sms_safe((string)$row_class['class_entryid'])."' />";
					echo "<input type='hidden' name='scope_batchid' value='".payments_sms_safe((string)$_Batch_Id)."' />";
					echo "<input type='hidden' name='scope_termid' value='".payments_sms_safe((string)$row_tr['termname'])."' />";
					echo "</form>";
				}
				echo $row_p['itemname'];
				echo "</td>";

				echo "<td align='center'>";
				echo $row_p['cost'];
				$_Total_Amount=$_Total_Amount+$row_p['cost'];
				echo "</td>";

				echo "<td align='center'>";
				$_SQL_SUBPAYMENT=mysqli_query($con,"SELECT sum(pm.payment) AS payment FROM tblpayment pm WHERE pm.transactionid='$row_p[transactionid]' AND pm.billid='$row_p[billid]'");
				if($row_subpy=mysqli_fetch_array($_SQL_SUBPAYMENT)){
				echo $row_subpy['payment'];
				@$_Total_Payment=$row_subpy['payment'];
				$_Total_Amount_Paid=$_Total_Amount_Paid+$row_subpy['payment'];
				}
				 echo "</td>";

				echo "<td align='center'>";
				//echo $row_p['payment'];
				$_Balance=$row_p['cost']-$_Total_Payment;
				$_Total_Balance=$_Total_Balance+$_Balance;
				echo $_Balance;
				echo "</td>";

				echo "<td align='center'>";
				echo "<input type='number' step='0.01' min='0.01' style='text-align:center' id='payment_".payments_sms_safe((string)$serial)."' name='payment' form='".payments_sms_safe($_RowFormId)."' required placeholder='Enter Amount'/>";
				echo "</td>";

				echo "<td align='center'>";
				echo "<button class='button-pay' type='submit' form='".payments_sms_safe($_RowFormId)."' id='pay_bill_".payments_sms_safe((string)$serial)."'><i class='fa fa-plus' style='color:white'></i> Pay</button>";
				echo "</td>";

				echo "<td align='center'>";
				if(!$paymentsTeacherHasModule){
				echo "<button class='button-pay' type='submit' form='".payments_sms_safe($_RowPrintFormId)."' id='print_bill_".payments_sms_safe((string)$serial)."'><i class='fa fa-print' style='color:white'></i> Print</button>";
				}
				echo "</td>";

				echo "</tr>";
				}
				echo "<tr style='background-color:#eeb;color:blue;font-weight:bold'>";
				echo "<td colspan='2' align='right'>";
				echo "TOTAL:";
				echo "</td>";
				echo "<td colspan='1' align='center'>";
				echo $_SESSION['SYMBOL']." ". $_Total_Amount;
				echo "</td>";

				echo "<td colspan='1' align='center'>";
				echo $_SESSION['SYMBOL']." ". $_Total_Amount_Paid;
				echo "</td>";

				echo "<td colspan='1' align='center'>";
				echo $_SESSION['SYMBOL']." ". $_Total_Balance;
				echo "</td>";

				echo "<td colspan='2'>";
				echo "</td>";

				echo "<td align='center'>";
				if(!$paymentsTeacherHasModule){
					echo "<form id='formID4' name='formID4' method='post' action='payments.php?userid=".urlencode((string)$_GET['userid'])."' style='display:inline-block;margin:0 6px 0 0;'>";
					echo "<input type='hidden' name='bulk_sms_class_id' value='".payments_sms_safe($_Get_Class)."' />";
					echo "<input type='hidden' name='bulk_sms_term_id' value='".payments_sms_safe($_Get_TermId)."' />";
					echo "<input type='hidden' name='bulk_sms_batch_id' value='".payments_sms_safe($_Batch_Id)."' />";
					echo "<button id='send_class_balance_sms' name='send_class_balance_sms' class='button-pay' onclick=\"return confirm('Send balance SMS to all parents with outstanding balances in this class and semester?');\"><i class='fa fa-bullhorn' style='color:yellow'></i> Text Class Balances</button>";
					echo "</form>";
					echo "<form id='formID2' name='formID2' method='post' action='payments.php?userid=".urlencode((string)$_GET['userid'])."' style='display:inline-block;margin:0 6px 0 0;'>";
					echo "<input type='hidden' name='sms_userid' value='".payments_sms_safe($_Get_UserID)."' />";
					echo "<input type='hidden' name='sms_class_id' value='".payments_sms_safe($_Get_Class)."' />";
					echo "<input type='hidden' name='sms_term_id' value='".payments_sms_safe($_Get_TermId)."' />";
					echo "<input type='hidden' name='sms_batch_id' value='".payments_sms_safe($_Batch_Id)."' />";
					echo "<button id='send_balance_sms_scope' name='send_balance_sms' class='button-pay'><i class='fa fa-envelope' style='color:yellow'></i> Text Balance</button>";
					echo "</form>";
					echo "<form id='formID3' name='formID3' method='post' action='payments.php?userid=".urlencode((string)$_GET['userid'])."' style='display:inline-block;margin:0;'>";
					echo "<input type='hidden' id='getuserid' name='getuserid' value='$_Get_UserID' />";
					echo "<input type='hidden' id='getclass' name='getclass' value='$_Get_Class' />";
					echo "<input type='hidden' id='gettermid' name='gettermid' value='$_Get_TermId' />";
					echo "<button id='print_all_bill' name='print_all_bill' class='button-pay'><i class='fa fa-print' style='color:yellow'></i> Print All</button>";
					echo "</form>";
				}
				echo "</td>";
				
				echo "</tr>";
			}
		}
	}
echo "</tbody>";
echo "</table>";
}
}
?>
</div>
			</td>
		</tr>
	</table>

 
<button onclick="topFunction()" id="myBtn" title="Go to top">Top</button> 

 <script>
//Get the button
var mybutton = document.getElementById("myBtn");

// When the user scrolls down 20px from the top of the document, show the button
window.onscroll = function() {scrollFunction()};

function scrollFunction() {
  if (document.body.scrollTop > 50 || document.documentElement.scrollTop > 50) {
    mybutton.style.display = "block";
  } else {
    mybutton.style.display = "none";
  }
}

// When the user clicks on the button, scroll to the top of the document
function topFunction() {
  document.body.scrollTop = 0;
  document.documentElement.scrollTop = 0;
}
</script>
<br/>
</div>
<br/><br/>
</body>
</html>
